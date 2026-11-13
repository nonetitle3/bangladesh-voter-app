from dataclasses import replace
from pathlib import Path
import pytest
import app.processor as processor
from app.config import settings
from app.processor import safe_pdf_path

def test_path_must_remain_inside_shared_storage(monkeypatch, tmp_path):
    root = tmp_path / "storage"
    root.mkdir()
    monkeypatch.setattr(processor, "settings", replace(settings, storage_root=root.resolve()))
    assert safe_pdf_path("pdfs/file.pdf") == (root / "pdfs/file.pdf").resolve()
    with pytest.raises(ValueError):
        safe_pdf_path("../escape.pdf")
    with pytest.raises(ValueError):
        safe_pdf_path(str(tmp_path / "outside.pdf"))
