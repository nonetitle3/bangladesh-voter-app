const express = require("express");
const multer = require("multer");
const { login, requireAdmin } = require("./adminAuth");
const { parsePdf } = require("./pdfImporter");

const router = express.Router();
const upload = multer({ storage: multer.memoryStorage(), limits: { files: 100, fileSize: 50 * 1024 * 1024 }, fileFilter: (req, file, cb) => cb(null, file.mimetype === "application/pdf") });

router.post("/login", (req, res) => {
  try {
    const token = login(req.body.username, req.body.password);
    if (!token) return res.status(401).json({ error: "Invalid username or password" });
    res.json({ success: true, token });
  } catch (e) { res.status(500).json({ error: e.message }); }
});
router.get("/me", requireAdmin, (req, res) => res.json({ authenticated: true, username: req.admin.username, role: req.admin.role }));
router.post("/import-pdf", requireAdmin, upload.array("files", 100), async (req, res) => {
  const results = [], all = [];
  for (const file of req.files || []) {
    try {
      const parsed = await parsePdf(file.buffer);
      all.push(...parsed.voters);
      results.push({ filename: file.originalname, pages: parsed.pages, voters: parsed.voters.length, location: parsed.location, success: true });
    } catch (e) { results.push({ filename: file.originalname, success: false, error: e.message }); }
  }
  const unique = new Map();
  for (const v of all) if (v.voterNumber && !unique.has(v.voterNumber)) unique.set(v.voterNumber, v);
  res.json({ success: true, filesProcessed: (req.files || []).length, totalImported: all.length, uniqueVoters: unique.size, duplicates: all.length - unique.size, voters: [...unique.values()], results });
});
module.exports = router;
