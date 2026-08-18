const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const fs = require('fs');

const dir = path.join(__dirname, 'storage');
fs.mkdirSync(dir, { recursive: true });
const db = new sqlite3.Database(path.join(dir, 'voters.sqlite'));

function run(sql, params=[]) { return new Promise((resolve,reject)=>db.run(sql,params,function(err){err?reject(err):resolve(this)})); }
function all(sql, params=[]) { return new Promise((resolve,reject)=>db.all(sql,params,(e,r)=>e?reject(e):resolve(r))); }
function get(sql, params=[]) { return new Promise((resolve,reject)=>db.get(sql,params,(e,r)=>e?reject(e):resolve(r))); }

async function initDatabase() {
  await run(`PRAGMA journal_mode=WAL`);
  await run(`CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, email TEXT, password TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'viewer', is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL)`);
  await run(`CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL, stored_path TEXT, page_count INTEGER DEFAULT 0, status TEXT NOT NULL DEFAULT 'pending', ocr_used INTEGER DEFAULT 0, error_msg TEXT, uploaded_at TEXT NOT NULL)`);
  await run(`CREATE TABLE IF NOT EXISTS voter_records (id INTEGER PRIMARY KEY AUTOINCREMENT, document_id INTEGER, voter_id TEXT, serial_no TEXT, name TEXT, father_name TEXT, mother_name TEXT, birth_date TEXT, gender TEXT, occupation TEXT, address TEXT, village TEXT, ward TEXT, union_name TEXT, upazila TEXT, district TEXT, division TEXT, post_code TEXT, pdf_filename TEXT, page_number INTEGER, raw_text TEXT, confidence REAL DEFAULT 0, created_at TEXT NOT NULL, FOREIGN KEY(document_id) REFERENCES documents(id))`);
  await run(`CREATE INDEX IF NOT EXISTS idx_voter_name_father ON voter_records(name,father_name)`);
  await run(`CREATE INDEX IF NOT EXISTS idx_voter_location ON voter_records(district,upazila)`);
  await run(`CREATE INDEX IF NOT EXISTS idx_voter_id ON voter_records(voter_id)`);
}

module.exports={db,run,all,get,initDatabase};
