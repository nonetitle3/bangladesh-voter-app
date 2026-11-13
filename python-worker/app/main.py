import hmac
from fastapi import FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field
from .config import settings
from .database import connect
from .processor import process_document

app = FastAPI(title="Bangladesh Voter PDF Worker", version="1.0.0")

class ProcessRequest(BaseModel):
    document_id: int = Field(gt=0)

def require_token(authorization: str | None):
    if not settings.token:
        raise HTTPException(status_code=503, detail="Worker API token is not configured")
    supplied = (authorization or "").removeprefix("Bearer ").strip()
    if not hmac.compare_digest(supplied, settings.token):
        raise HTTPException(status_code=401, detail="Invalid worker token")

@app.get("/health")
def health():
    return {"status":"ok","service":"pdf-worker","ocr_languages":settings.ocr_languages}

@app.get("/ready")
def ready():
    try:
        db = connect()
        with db.cursor() as cur:
            cur.execute("SELECT 1")
        db.close()
        return {"status":"ready","database":"connected"}
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"Database unavailable: {str(exc)[:200]}")

@app.post("/internal/process")
def process(request: ProcessRequest, authorization: str | None = Header(default=None)):
    require_token(authorization)
    try:
        return process_document(request.document_id)
    except LookupError as exc:
        raise HTTPException(status_code=404, detail=str(exc))
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc))
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"PDF processing failed: {str(exc)[:500]}")
