const express = require("express");
const multer = require("multer");
const crypto = require("crypto");
const { login, requireAdmin } = require("./adminAuth");
const { parsePdf } = require("./pdfImporter");

const router = express.Router();
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { files: 100, fileSize: 50 * 1024 * 1024 },
  fileFilter: (req, file, cb) => cb(null, file.mimetype === "application/pdf")
});

const previews = new Map();

router.post("/login", (req, res) => {
  try {
    const token = login(req.body.username, req.body.password);
    if (!token) return res.status(401).json({ error: "Invalid username or password" });
    res.json({ success: true, token });
  } catch (e) { res.status(500).json({ error: e.message }); }
});

router.get("/me", requireAdmin, (req, res) =>
  res.json({ authenticated: true, username: req.admin.username, role: req.admin.role })
);

// Parse PDFs without modifying the live voter database.
router.post("/preview-pdf", requireAdmin, upload.array("files", 100), async (req, res) => {
  const results = [], all = [];
  for (const file of req.files || []) {
    try {
      const parsed = await parsePdf(file.buffer);
      const sample = parsed.voters.slice(0, 20);
      all.push(...parsed.voters);
      results.push({ filename: file.originalname, pages: parsed.pages, voters: parsed.voters.length, sample, location: parsed.location, success: true });
    } catch (e) {
      results.push({ filename: file.originalname, success: false, error: e.message });
    }
  }
  const unique = new Map();
  for (const v of all) {
    const key = v.voterNumber || `${v.name}|${v.dateOfBirth}|${v.father}`;
    if (!unique.has(key)) unique.set(key, v);
  }
  const previewId = crypto.randomUUID();
  previews.set(previewId, { createdAt: Date.now(), voters: [...unique.values()], results });
  // Keep preview storage bounded and short-lived.
  for (const [id, value] of previews) if (Date.now() - value.createdAt > 15 * 60 * 1000) previews.delete(id);
  res.json({ success: true, previewId, filesProcessed: (req.files || []).length, totalImported: all.length, uniqueVoters: unique.size, duplicates: all.length - unique.size, results });
});

// Return a small preview for review in the admin UI.
router.get("/preview/:id", requireAdmin, (req, res) => {
  const item = previews.get(req.params.id);
  if (!item) return res.status(404).json({ error: "Preview expired or not found" });
  res.json({ previewId: req.params.id, results: item.results, voters: item.voters.slice(0, 100) });
});

// Commit only after the administrator explicitly confirms the preview.
router.post("/preview/:id/confirm", requireAdmin, (req, res) => {
  const item = previews.get(req.params.id);
  if (!item) return res.status(404).json({ error: "Preview expired or not found" });
  // Deliberately do not overwrite the existing voter database yet.
  // The next data-storage step should validate the target hierarchy and write a backup first.
  previews.delete(req.params.id);
  res.json({ success: true, confirmed: true, votersReadyForImport: item.voters.length, message: "Preview confirmed. Database write is intentionally disabled until the target data hierarchy is validated." });
});

module.exports = router;
