"""Conservative label-based parser ported from the existing backend/voterParser.js."""
import re

LABELS = {
    "voter_id": [r"ভোটার\s*(?:নং|নম্বর)\s*[:：-]?", r"NID\s*(?:No|Number)?\s*[:：-]?", r"voter\s*id\s*[:：-]?"],
    "serial_no": [r"ক্রমিক\s*[:：-]?", r"serial\s*[:：-]?"],
    "name": [r"^নাম\s*[:：-]?", r"^name\s*[:：-]?"],
    "father_name": [r"পিতা\s*[:：-]?", r"father(?:'s)?\s*name\s*[:：-]?"],
    "mother_name": [r"মাতা\s*[:：-]?", r"mother(?:'s)?\s*name\s*[:：-]?"],
    "birth_date": [r"জন্ম\s*তারিখ\s*[:：-]?", r"DOB\s*[:：-]?"],
    "occupation": [r"পেশা\s*[:：-]?", r"occupation\s*[:：-]?"],
    "gender": [r"লিঙ্গ\s*[:：-]?", r"gender\s*[:：-]?"],
    "address": [r"ঠিকানা\s*[:：-]?", r"address\s*[:：-]?"],
    "ward": [r"ওয়ার্ড\s*(?:নং|নম্বর)?\s*[:：-]?", r"ward\s*(?:no|number)?\s*[:：-]?"],
    "union_name": [r"ইউনিয়ন\s*[:：-]?", r"union\s*[:：-]?"],
    "upazila": [r"উপজেলা\s*[:：-]?", r"upazila\s*[:：-]?"],
    "district": [r"জেলা\s*[:：-]?", r"district\s*[:：-]?"],
    "division": [r"বিভাগ\s*[:：-]?", r"division\s*[:：-]?"],
    "post_code": [r"পোস্ট\s*কোড\s*[:：-]?", r"post\s*code\s*[:：-]?"],
}
COMPILED = {k: [re.compile(p, re.I) for p in patterns] for k, patterns in LABELS.items()}

def clean(value: str) -> str:
    return re.sub(r"[\t ]+", " ", str(value or "")).strip().rstrip("|").strip()

def normalize_gender(value: str) -> str:
    value = clean(value)
    if re.search(r"মহিলা|নারী|\bfemale\b|\bf\b", value, re.I):
        return "মহিলা"
    if re.search(r"পুরুষ|\bmale\b|\bm\b", value, re.I):
        return "পুরুষ"
    return value

def split_voters(text: str) -> list[str]:
    lines = [clean(x) for x in text.splitlines() if clean(x)]
    starts = [i for i, line in enumerate(lines) if re.fullmatch(r"(?:ক্রমিক\s*)?[০-৯0-9]{1,6}[.)\-:]?", line)]
    if len(starts) < 2:
        return ["\n".join(lines)] if lines else []
    return ["\n".join(lines[s:starts[i + 1] if i + 1 < len(starts) else len(lines)]) for i, s in enumerate(starts)]

def parse_record(raw: str) -> dict:
    lines = [clean(x) for x in raw.splitlines()]
    record = {key: None for key in LABELS}
    for field, patterns in COMPILED.items():
        for i, line in enumerate(lines):
            for pattern in patterns:
                match = pattern.search(line)
                if match:
                    value = clean(line[match.end():]) or (clean(lines[i + 1]) if i + 1 < len(lines) else "")
                    if value:
                        record[field] = value
                        break
            if record[field]:
                break
    record["gender"] = normalize_gender(record["gender"] or "") or None
    record["raw_text"] = raw
    record["confidence"] = sum(bool(record[k]) for k in LABELS) / len(LABELS)
    return record

def parse_page(text: str) -> list[dict]:
    return [parse_record(raw) for raw in split_voters(text)]
