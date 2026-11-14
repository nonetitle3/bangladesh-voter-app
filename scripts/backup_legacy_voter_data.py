#!/usr/bin/env python3
"""Create a consistent, non-destructive backup bundle for the voter-app migration."""
from __future__ import annotations
import argparse, hashlib, json, shutil, sqlite3
from datetime import datetime, timezone
from pathlib import Path

def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--sqlite", required=True, help="Path to backend/storage/voters.sqlite")
    parser.add_argument("--pdf-dir", help="Path to the live PDF storage directory")
    parser.add_argument("--json-dir", help="Path to backend/data JSON hierarchy directory")
    parser.add_argument("--output", default="./migration-backups")
    args = parser.parse_args()

    source = Path(args.sqlite).expanduser().resolve()
    if not source.is_file():
        parser.error(f"SQLite source not found: {source}")
    root = Path(args.output).expanduser().resolve()
    root.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    bundle = root / f"voter-migration-{stamp}"
    bundle.mkdir()
    destination = bundle / "voters.sqlite"

    # SQLite backup API includes committed WAL content and does not mutate the source.
    src = sqlite3.connect(f"file:{source}?mode=ro", uri=True)
    dst = sqlite3.connect(destination)
    try:
        src.backup(dst)
        integrity = dst.execute("PRAGMA integrity_check").fetchone()[0]
        if integrity != "ok":
            raise RuntimeError(f"Backup integrity check failed: {integrity}")
        tables = {}
        for table in ("users", "documents", "voter_records"):
            exists = dst.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (table,)).fetchone()
            if not exists:
                raise RuntimeError(f"Required source table missing: {table}")
            tables[table] = dst.execute(f'SELECT COUNT(*) FROM "{table}"').fetchone()[0]
    finally:
        dst.close()
        src.close()

    pdf_manifest = []
    if args.pdf_dir:
        pdf_source = Path(args.pdf_dir).expanduser().resolve()
        if not pdf_source.is_dir():
            raise RuntimeError(f"PDF directory not found: {pdf_source}")
        target = bundle / "pdfs"
        for file in sorted(pdf_source.rglob("*")):
            if file.is_file():
                relative = file.relative_to(pdf_source)
                copied = target / relative
                copied.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(file, copied)
                pdf_manifest.append({"path": str(relative), "size": copied.stat().st_size, "sha256": sha256(copied)})

    json_manifest = []
    if args.json_dir:
        json_source = Path(args.json_dir).expanduser().resolve()
        if not json_source.is_dir():
            raise RuntimeError(f"JSON data directory not found: {json_source}")
        target = bundle / "legacy-json"
        for file in sorted(json_source.rglob("*.json")):
            relative = file.relative_to(json_source)
            copied = target / relative
            copied.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(file, copied)
            json_manifest.append({"path": str(relative), "size": copied.stat().st_size, "sha256": sha256(copied)})

    manifest = {
        "created_at": datetime.now(timezone.utc).isoformat(),
        "source_sqlite_path": str(source),
        "sqlite_backup": {"file": "voters.sqlite", "sha256": sha256(destination), "integrity_check": "ok", "tables": tables},
        "pdf_files": pdf_manifest,
        "json_files": json_manifest,
        "note": "Backup copies only. Original SQLite, PDF and JSON sources were not modified.",
    }
    (bundle / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"backup_directory": str(bundle), **manifest}, ensure_ascii=False, indent=2))
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
