import React, { useState } from "react";

const API = process.env.REACT_APP_API_URL || "https://bangladesh-voter-api.onrender.com/api";

async function readJson(response) {
  const text = await response.text();
  try { return JSON.parse(text); }
  catch { throw new Error(`Server returned non-JSON response (${response.status}). Check the Render backend URL.`); }
}

export default function AdminPanel() {
  const [token, setToken] = useState(localStorage.getItem("adminToken") || "");
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("");
  const [files, setFiles] = useState([]);
  const [result, setResult] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  async function login(e) {
    e.preventDefault(); setError("");
    try {
      const r = await fetch(`${API}/admin/login`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username, password }) });
      const data = await readJson(r);
      if (!r.ok) throw new Error(data.error || "Login failed");
      localStorage.setItem("adminToken", data.token); setToken(data.token); setPassword("");
    } catch (e) { setError(e.message); }
  }

  async function importPdfs(e) {
    e.preventDefault(); if (!files.length) return;
    setBusy(true); setError(""); setResult(null);
    const form = new FormData(); [...files].forEach(file => form.append("files", file));
    try {
      const r = await fetch(`${API}/admin/preview-pdf`, { method: "POST", headers: { Authorization: `Bearer ${token}` }, body: form });
      const data = await readJson(r); if (!r.ok) throw new Error(data.error || "PDF preview failed"); setResult(data);
    } catch (e) { setError(e.message); } finally { setBusy(false); }
  }

  function logout() { localStorage.removeItem("adminToken"); setToken(""); setResult(null); }

  if (!token) return <div style={styles.page}><form onSubmit={login} style={styles.card}><h1>Admin Login</h1><input style={styles.input} value={username} onChange={e => setUsername(e.target.value)} placeholder="Username" autoComplete="username"/><input style={styles.input} type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="Password" autoComplete="current-password"/><button style={styles.button}>Login</button>{error && <p style={styles.error}>{error}</p>}</form></div>;
  return <div style={styles.page}><div style={styles.container}><header style={styles.header}><h1>Voter Data Admin</h1><button onClick={logout}>Logout</button></header><section style={styles.card}><h2>Bulk PDF Preview</h2><p>Select multiple PDFs with the same voter-list structure. Preview does not modify the live voter database.</p><form onSubmit={importPdfs}><input type="file" accept="application/pdf" multiple onChange={e => setFiles(e.target.files)} /><p>{files.length} PDF file(s) selected</p><button style={styles.button} disabled={busy}>{busy ? "Processing..." : "Create Preview"}</button></form>{error && <p style={styles.error}>{error}</p>}</section>{result && <section style={styles.card}><h2>Preview Result</h2><div style={styles.stats}><b>Files: {result.filesProcessed}</b><b>Records: {result.totalImported}</b><b>Unique: {result.uniqueVoters}</b><b>Duplicates: {result.duplicates}</b></div><h3>Files</h3><div style={{overflowX:"auto"}}><table style={styles.table}><thead><tr><th>File</th><th>Voters</th><th>Location</th><th>Status</th></tr></thead><tbody>{result.results?.map((x,i)=><tr key={i}><td>{x.filename}</td><td>{x.voters || 0}</td><td>{x.location ? `${x.location.district || ""} / ${x.location.upazila || ""} / ${x.location.union || ""}` : "-"}</td><td>{x.success ? "OK" : x.error}</td></tr>)}</tbody></table></div></section>}</div></div>;
}

const styles = { page:{minHeight:"100vh",background:"#f3f4f6",padding:"40px 20px",boxSizing:"border-box",fontFamily:"Arial,sans-serif"}, container:{maxWidth:1100,margin:"auto"}, card:{background:"white",padding:24,borderRadius:12,boxShadow:"0 2px 10px rgba(0,0,0,.08)",marginBottom:20}, header:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:20}, input:{display:"block",width:"100%",maxWidth:420,padding:12,margin:"10px 0",boxSizing:"border-box",border:"1px solid #ccc",borderRadius:6}, button:{padding:"11px 18px",border:0,borderRadius:6,cursor:"pointer"}, error:{color:"#b91c1c"}, stats:{display:"flex",gap:24,flexWrap:"wrap",margin:"15px 0"}, table:{width:"100%",borderCollapse:"collapse"} };