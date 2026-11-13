import fitz
from app import extractor

def test_native_text_page_does_not_trigger_ocr(monkeypatch, tmp_path):
    pdf_path = tmp_path / "native.pdf"
    doc = fitz.open()
    page = doc.new_page()
    page.insert_text((72, 72), "Voter Name: Example Person")
    doc.save(pdf_path)
    doc.close()
    def fail_if_called(_page):
        raise AssertionError("OCR should not run for readable native text")
    monkeypatch.setattr(extractor, "_ocr_page", fail_if_called)
    result = list(extractor.extract_pdf(pdf_path))
    assert len(result) == 1
    assert "Example Person" in result[0].extracted_text
    assert result[0].ocr_used is False

def test_empty_page_uses_ocr_fallback(monkeypatch, tmp_path):
    pdf_path = tmp_path / "scanned.pdf"
    doc = fitz.open()
    doc.new_page()
    doc.save(pdf_path)
    doc.close()
    monkeypatch.setattr(extractor, "_ocr_page", lambda _page: ("নাম: পরীক্ষামূলক", 0.91))
    result = list(extractor.extract_pdf(pdf_path))
    assert result[0].extracted_text == "নাম: পরীক্ষামূলক"
    assert result[0].ocr_used is True
    assert result[0].confidence == 0.91

def test_ocr_words_are_reassembled_into_lines(monkeypatch, tmp_path):
    from app import extractor
    from PIL import Image
    import fitz
    pdf_path = tmp_path / "ocr-lines.pdf"
    doc = fitz.open()
    doc.new_page()
    doc.save(pdf_path)
    doc.close()
    fake_data = {
        "text": ["নাম:", "আব্দুল", "করিম", "পিতা:", "রহিম"],
        "conf": ["90", "91", "92", "88", "89"],
        "block_num": [1, 1, 1, 1, 1],
        "par_num": [1, 1, 1, 1, 1],
        "line_num": [1, 1, 1, 2, 2],
    }
    monkeypatch.setattr(extractor.pytesseract, "image_to_data", lambda *a, **k: fake_data)
    result = list(extractor.extract_pdf(pdf_path))
    assert result[0].extracted_text == "নাম: আব্দুল করিম\nপিতা: রহিম"
