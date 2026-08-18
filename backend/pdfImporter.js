const pdfParse = require("pdf-parse");

function banglaDigitsToEnglish(value = "") {
  const b = "০১২৩৪৫৬৭৮৯", e = "0123456789";
  return value.replace(/[০-৯]/g, ch => e[b.indexOf(ch)]);
}
function clean(value = "") {
  return value.replace(/\u00a0/g, " ").replace(/[ \t]+/g, " ").trim();
}
function field(text, label, next = []) {
  const end = next.length ? `(?=${next.map(x => x.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")).join("|")}|$)` : "$";
  const m = text.match(new RegExp(`${label}\\s*:\\s*(.*?)${end}`, "i"));
  return m ? clean(m[1]) : "";
}
function location(text) {
  const pick = (re) => { const m = text.match(re); return m ? clean(m[1]) : ""; };
  return {
    district: pick(/জেলা\s*:\s*(.*?)(?=\s+উপজেলা\s*:)/),
    upazila: pick(/উপজেলা\s*:\s*(.*?)(?=\s+ইউনিয়ন\s*:)/),
    union: pick(/ইউনিয়ন\s*:\s*(.*?)(?=\s+ডাকঘর\s*:)/),
    postOffice: pick(/ডাকঘর\s*:\s*(.*?)(?=\s+ভোটার এলাকার কোড\s*:)/),
    areaCode: banglaDigitsToEnglish(pick(/ভোটার এলাকার কোড\s*:\s*([০-৯0-9]+)/)),
    areaName: pick(/ভোটার এলাকার নাম\s*:\s*(.*?)(?=\s|$)/)
  };
}
function parseVoters(text, loc) {
  const out = [], re = /(?:^|\n)\s*([০-৯0-9]{1,6})\.\s*নাম\s*:/g, matches = [...text.matchAll(re)];
  for (let i = 0; i < matches.length; i++) {
    const m = matches[i], start = m.index + m[0].indexOf("নাম"), end = i + 1 < matches.length ? matches[i + 1].index : text.length;
    const block = text.slice(start, end);
    const occupation = block.match(/পেশা\s*:\s*(.*?)(?=,?\s*জন্ম তারিখ\s*:)/i);
    const dob = block.match(/জন্ম তারিখ\s*:\s*([০-৯0-9]{1,2}\/[০-৯0-9]{1,2}\/[০-৯0-9]{4})/i);
    const voter = {
      serial: banglaDigitsToEnglish(m[1]),
      voterNumber: banglaDigitsToEnglish(field(block, "ভোটার নং", ["পিতা"])),
      name: field(block, "নাম", ["ভোটার নং"]),
      father: field(block, "পিতা", ["মাতা"]),
      mother: field(block, "মাতা", ["পেশা", "জন্ম তারিখ"]),
      occupation: occupation ? clean(occupation[1]) : "",
      dateOfBirth: dob ? banglaDigitsToEnglish(dob[1]) : "",
      address: field(block, "ঠিকানা"),
      ...loc
    };
    if (voter.voterNumber || voter.name) out.push(voter);
  }
  return out;
}
async function parsePdf(buffer) {
  const result = await pdfParse(buffer);
  const text = clean(result.text);
  const loc = location(text);
  return { pages: result.numpages, location: loc, voters: parseVoters(text, loc) };
}
module.exports = { parsePdf };
