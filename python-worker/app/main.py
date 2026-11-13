import hmac
import logging
import threading
from fastapi import BackgroundTasks, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field
from .config import settings
from .database import connect
from .processor import process_document

app = FastAPI(title="Bangladesh Voter PDF Worker", version="1.1.0")
_active: set[int] = set()
_active_lock = threading.Lock()

class ProcessRequest(BaseModel):
    document_id: int = Field(gt=0)

def require_token(authorization: str | None):
    if not settings.token:
        raise HTTPException(status_code=503, detail="Worker API token is not configured")
    supplied = (authorization or "").removeprefix("Bearer ").strip()
    if not hmac.compare_digest(supplied, settings.token):
        raise HTTPException(status_code=401, detail="Invalid worker token")

def _run_document(document_id: int):
    try:
        process_document(document_id)
    except Exception:
        logging.exception("PDF worker failed for document_id=%s", document_id)
    finally:
        with _active_lock:
            _active.discard(document_id)

@app.get("/health")
def health():
    return {"status":"ok","service":"pdf-worker","ocr_languages":settings.ocr_languages}

@app.get("/ready")
def ready():
    try:
        db = connect()
        try:
            with db.cursor() as cur:
                cur.execute("SELECT 1")
        finally:
            db.close()
        return {"status":"ready","database":"connected"}
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"Database unavailable: {str(exc)[:200]}")

@app.post("/internal/process", status_code=202)
def process(request: ProcessRequest, background_tasks: BackgroundTasks, authorization: str | None = Header(default=None)):
    require_token(authorization)
    db = connect()
    try:
        with db.cursor() as cur:
            cur.execute("SELECT id,status FROM documents WHERE id=%s", (request.document_id,))
            doc = cur.fetchone()
    finally:
        db.close()
    if not doc:
        raise HTTPException(status_code=404, detail="Document not found")
    if doc["status"] == "completed":
        return {"document_id":request.document_id,"status":"completed","already_completed":True}
    with _active_lock:
        if request.document_id in _active:
            return {"document_id":request.document_id,"status":"processing","already_running":True}
        _active.add(request.document_id)
    background_tasks.add_task(_run_document, request.document_id)
    return {"document_id":request.document_id,"status":"queued"}

@app.get("/internal/status/{document_id}")
def process_status(document_id: int, authorization: str | None = Header(default=None)):
    require_token(authorization)
    db = connect()
    try:
        with db.cursor() as cur:
            cur.execute("SELECT id,status,page_count,error_msg FROM documents WHERE id=%s", (document_id,))
            doc = cur.fetchone()
            if not doc:
                raise HTTPException(status_code=404, detail="Document not found")
            cur.execute(
                """SELECT COUNT(*) AS total,
                SUM(status='completed') AS completed,
                SUM(status='failed') AS failed
                FROM processing_pages WHERE document_id=%s""",
                (document_id,),
            )
            pages = cur.fetchone()
        return {
            "document_id":document_id,"status":doc["status"],
            "page_count":doc["page_count"],"pages_total":pages["total"] or 0,
            "pages_completed":pages["completed"] or 0,"pages_failed":pages["failed"] or 0,
            "error":doc["error_msg"],
        }
    finally:
        db.close()
