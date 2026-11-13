import os
from dataclasses import dataclass
from pathlib import Path

@dataclass(frozen=True)
class Settings:
    token: str = os.getenv("WORKER_API_TOKEN", "")
    storage_root: Path = Path(os.getenv("SHARED_STORAGE_ROOT", "/var/data")).resolve()
    db_host: str = os.getenv("DB_HOST", "")
    db_port: int = int(os.getenv("DB_PORT", "3306"))
    db_name: str = os.getenv("DB_DATABASE", "")
    db_user: str = os.getenv("DB_USERNAME", "")
    db_password: str = os.getenv("DB_PASSWORD", "")
    db_charset: str = os.getenv("DB_CHARSET", "utf8mb4")
    db_timeout: int = int(os.getenv("DB_CONNECT_TIMEOUT", "10"))
    min_text_chars: int = int(os.getenv("PDF_TEXT_MIN_CHARS", "25"))
    ocr_languages: str = os.getenv("OCR_LANGUAGES", "ben+eng")
    ocr_dpi: int = int(os.getenv("OCR_DPI", "220"))
    ocr_timeout: int = int(os.getenv("OCR_TIMEOUT_SECONDS", "120"))
    max_pdf_mb: int = int(os.getenv("MAX_PDF_MB", "100"))

settings = Settings()
