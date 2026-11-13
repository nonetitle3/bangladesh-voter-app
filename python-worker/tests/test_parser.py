from app.parser import parse_page, split_voters, normalize_gender

def test_parse_bengali_voter_fields():
    text = """ক্রমিক: ১২৩
নাম: মোঃ আব্দুল করিম
পিতা: মোঃ রহিম
মাতা: আয়েশা বেগম
ভোটার নং: ১৯৮৭৬৫৪৩২১
জন্ম তারিখ: ০১/০২/১৯৯০
লিঙ্গ: পুরুষ
জেলা: ময়মনসিংহ"""
    records = parse_page(text)
    assert len(records) == 1
    assert records[0]["name"] == "মোঃ আব্দুল করিম"
    assert records[0]["father_name"] == "মোঃ রহিম"
    assert records[0]["voter_id"] == "১৯৮৭৬৫৪৩২১"
    assert records[0]["gender"] == "পুরুষ"
    assert records[0]["raw_text"] == text

def test_split_multiple_voters_by_serial_markers():
    text = "১\nনাম: প্রথম\nপিতা: পিতা ১\n২\nনাম: দ্বিতীয়\nপিতা: পিতা ২"
    assert len(split_voters(text)) == 2

def test_gender_normalization_keeps_unknown_values():
    assert normalize_gender("Female") == "মহিলা"
    assert normalize_gender("অন্যান্য") == "অন্যান্য"
