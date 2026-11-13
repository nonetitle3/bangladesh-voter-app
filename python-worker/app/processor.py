from pathlib import Path
from datetime import datetime, timezone
from .config import settings
from .database import connect
from .extractor import extract_pdf
from .parser import parse_page

RECORD_FIELDS = [
    "voter_id", "serial_no", "name", "father_name", "mother_name", "birth_date",
    "gender", "occupation", "address", "village", "ward", "union_name",
    "upazila", "district", "division", "post_code",
]

def safe_pdf_path(stored_path: str) -> Path:
    candidate = Path(stored_path)
    if not candidate.is_absolute():
        candidate = settings.storage_root / candidate
    resolved = candidate.resolve()
    try:
        resolved.relative_to(settings.storage_root)
    except ValueError:
        raise ValueError("Document path is outside SHARED_STORAGE_ROOT")
    if resolved.suffix.lower() != ".pdf":
        raise ValueError("Only PDF files can be processed")
    return resolved

def _now():
    return datetime.now(timezone.utc).replace(tzinfo=None)

def process_document(document_id: int) -> dict:
    db = connect()
    try:
        with db.cursor() as cur:
            cur.execute("SELECT id, filename, stored_path, status FROM documents WHERE id=%s", (document_id,))
            doc = cur.fetchone()
            if not doc:
                raise LookupError(f"Document {document_id} not found")
            if doc["status"] == "completed":
                return {"document_id": document_id, "status": "completed", "skipped": True, "reason": "already_completed"}
            if doc["status"] not in ("pending", "processing", "failed"):
                raise ValueError(f"Document status '{doc['status']}' is not eligible for processing")
            pdf_path = safe_pdf_path(doc["stored_path"] or "")
            db.begin()
            cur.execute("UPDATE documents SET status='processing', error_msg=NULL WHERE id=%s", (document_id,))
            db.commit()

        pages = extract_pdf(pdf_path)
        records_count = 0
        any_ocr = False
        for page in pages:
            with db.cursor() as cur:
                cur.execute(
                    "SELECT id,status,ocr_used FROM processing_pages WHERE document_id=%s AND page_number=%s",
                    (document_id, page.page_number),
                )
                prior = cur.fetchone()
                if prior and prior["status"] == "completed":
                    any_ocr = any_ocr or bool(prior["ocr_used"])
                    continue

                now = _now()
                db.begin()
                cur.execute(
                    """INSERT INTO processing_pages
                    (document_id,page_number,status,ocr_used,extracted_text,normalized_text,confidence,error_msg,started_at,completed_at,created_at,updated_at)
                    VALUES (%s,%s,'processing',%s,%s,%s,%s,NULL,%s,NULL,%s,%s)
                    ON DUPLICATE KEY UPDATE status='processing',ocr_used=VALUES(ocr_used),
                    extracted_text=VALUES(extracted_text),normalized_text=VALUES(normalized_text),
                    confidence=VALUES(confidence),error_msg=NULL,started_at=VALUES(started_at),completed_at=NULL,updated_at=VALUES(updated_at)""",
                    (document_id,page.page_number,page.ocr_used,page.extracted_text,page.normalized_text,page.confidence,now,now,now),
                )
                cur.execute(
                    "SELECT id FROM processing_pages WHERE document_id=%s AND page_number=%s",
                    (document_id,page.page_number),
                )
                page_row = cur.fetchone()
                page_id = page_row["id"]

                # Delete only voter rows created by this worker on a previous failed attempt.
                # Legacy/imported rows are never deleted or rewritten by this worker.
                if prior:
                    cur.execute(
                        """DELETE FROM voter_records WHERE id IN
                        (SELECT voter_record_id FROM processing_page_records WHERE processing_page_id=%s)""",
                        (page_id,),
                    )
                    cur.execute("DELETE FROM processing_page_records WHERE processing_page_id=%s", (page_id,))

                cur.execute(
                    "SELECT COUNT(*) AS total FROM voter_records WHERE document_id=%s AND page_number=%s",
                    (document_id,page.page_number),
                )
                existing_count = cur.fetchone()["total"]
                # If the legacy system already populated this page, retain those rows and
                # avoid duplicating them during a migration-time worker retry.
                if existing_count == 0:
                    for record in parse_page(page.extracted_text):
                        values = [record.get(field) for field in RECORD_FIELDS]
                        cur.execute(
                            """INSERT INTO voter_records
                            (document_id,voter_id,serial_no,name,father_name,mother_name,birth_date,gender,occupation,address,village,ward,union_name,upazila,district,division,post_code,pdf_filename,page_number,raw_text,confidence,created_at)
                            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
                            (document_id,*values,doc["filename"],page.page_number,record["raw_text"],record["confidence"],now),
                        )
                        record_id = cur.lastrowid
                        cur.execute(
                            "INSERT INTO processing_page_records (processing_page_id,voter_record_id,created_at,updated_at) VALUES (%s,%s,%s,%s)",
                            (page_id,record_id,now,now),
                        )
                        records_count += 1

                cur.execute(
                    "UPDATE processing_pages SET status='completed',completed_at=%s,updated_at=%s WHERE id=%s",
                    (now,now,page_id),
                )
                db.commit()
            any_ocr = any_ocr or page.ocr_used

        with db.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS total, SUM(status='completed') AS completed, SUM(status='failed') AS failed, SUM(ocr_used=1) AS ocr_pages FROM processing_pages WHERE document_id=%s",
                (document_id,),
            )
            summary = cur.fetchone()
            final_status = "completed" if summary["completed"] == len(pages) and not summary["failed"] else "failed"
            any_ocr = any_ocr or bool(summary["ocr_pages"])
            cur.execute(
                "UPDATE documents SET page_count=%s,status=%s,ocr_used=%s,error_msg=%s WHERE id=%s",
                (len(pages),final_status,any_ocr,None if final_status=="completed" else "One or more pages failed; retry the document to continue.",document_id),
            )
            db.commit()
        return {
            "document_id":document_id,"status":final_status,"page_count":len(pages),
            "pages_completed":summary["completed"],"records_inserted":records_count,"ocr_used":any_ocr,
        }
    except Exception as exc:
        db.rollback()
        try:
            with db.cursor() as cur:
                cur.execute("UPDATE documents SET status='failed',error_msg=%s WHERE id=%s", (str(exc)[:4000],document_id))
                db.commit()
        except Exception:
            db.rollback()
        raise
    finally:
        db.close()
