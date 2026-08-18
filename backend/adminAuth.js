const jwt = require("jsonwebtoken");

const JWT_SECRET = process.env.JWT_SECRET;

function login(username, password) {
  const adminUsername = process.env.ADMIN_USERNAME;
  const adminPassword = process.env.ADMIN_PASSWORD;
  if (!adminUsername || !adminPassword || !JWT_SECRET) {
    throw new Error("Admin authentication is not configured");
  }
  if (username !== adminUsername || password !== adminPassword) return null;
  return jwt.sign({ username, role: "admin" }, JWT_SECRET, { expiresIn: "8h" });
}

function requireAdmin(req, res, next) {
  try {
    const header = req.headers.authorization || "";
    if (!header.startsWith("Bearer ")) return res.status(401).json({ error: "Authentication required" });
    const decoded = jwt.verify(header.slice(7), JWT_SECRET);
    if (decoded.role !== "admin") return res.status(403).json({ error: "Admin access required" });
    req.admin = decoded;
    next();
  } catch (e) {
    res.status(401).json({ error: "Invalid or expired token" });
  }
}

module.exports = { login, requireAdmin };
