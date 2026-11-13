from dataclasses import dataclass
from io import BytesIO
from pathlib import Path
import re
import fitz
import pytesseract
from PIL import Image
from .config import settings

@dataclass
class PageResult:
    page_number: int
    extracted_text: str
    normalized_text: str
    ocr_used: bool
    confidence: float | None

def normalize_search_text(text: str) -> str:
    # Keep original text intact; normalization is a separate, conservative search copy.
    text = text.replace("\u200c", "").replace("\u200d", "")
    text = re.sub(r"[\u00a0\t ]+", " ", text)
    text = re.sub(r"\r\n?", "\n", text)
    return text.strip()

def _ocr_page(page: fitz.Page) -> tuple[str, float | None]:
    pix = page.get_pixmap(dpi=settings.ocr_dpi, colorspace=fitz.csRGB, alpha=False)
    image = Image.open(BytesIO(pix.tobytes("png")))
    data = pytesseract.image_to_data(
        image, lang=settings.ocr_languages, config="--psm 6",
        output_type=pytesseract.Output.DICT, timeout=settings.ocr_timeout,
    )
    words, confidences = [], []
    for word, confidence in zip(data.get("text", []), data.get("conf", [])):
        word = word.strip()
        try:
            score = float(confidence)
        except (TypeError, ValueError):
            score = -1
        if word:
            words.append(word)
            if score >= 0:
                confidences.append(score)
    confidence = (sum(confidences) / len(confidences) / 100) if confidences else None
    return " ".join(words), confidence

def extract_pdf(path: Path) -> list[PageResult]:
    if not path.is_file():
        raise FileNotFoundError("PDF file does not exist in the shared storage volume")
    if path.stat().st_size > settings.max_pdf_mb * 1024 * 1024:
        raise ValueError(f"PDF exceeds configured limit of {settings.max_pdf_mb} MB")
    results = []
    with fitz.open(path) as pdf:
        if pdf.is_encrypted:
            raise ValueError("Encrypted/password-protected PDFs are not supported")
        for index, page in enumerate(pdf, start=1):
            native_text = page.get_text("text", sort=True) or ""
            text = native_text
            used_ocr = False
            confidence = None
            if len(re.sub(r"\s+", "", native_text)) < settings.min_text_chars:
                text, confidence = _ocr_page(page)
                used_ocr = True
            results.append(PageResult(index, text, normalize_search_text(text), used_ocr, confidence))
    return results
