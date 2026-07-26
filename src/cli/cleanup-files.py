#!/usr/bin/env python3
"""
HOSHIMI — cleanup-files.py
- Cache  : metadata.json, .season-map.json, .no-force, .folder-map.json
- Supprime : .title, cover.jpg.webp, cover.png.webp
"""

import ctypes
import os
import sys
from pathlib import Path

# ── Chemins racine à scanner ──────────────────────────────────────────────────
ROOTS = [
    Path(os.environ.get("ANIMES_PATH",  "Y:/Animes")),
    Path(os.environ.get("SERIES_PATH",  "Y:/Series")),
    Path(os.environ.get("FILMS_PATH",   "Y:/Films")),
]

HIDE    = {"metadata.json", ".season-map.json", ".no-force", ".folder-map.json"}
DELETE  = {".title", "cover.jpg.webp", "cover.png.webp"}

# ─────────────────────────────────────────────────────────────────────────────

def hide(path: Path) -> bool:
    try:
        ctypes.windll.kernel32.SetFileAttributesW(str(path), 0x02)
        return True
    except Exception:
        return False

def is_hidden(path: Path) -> bool:
    try:
        attrs = ctypes.windll.kernel32.GetFileAttributesW(str(path))
        return bool(attrs & 0x02)
    except Exception:
        return False

# ─────────────────────────────────────────────────────────────────────────────

hidden_ok  = 0
hidden_skip = 0
deleted    = 0
errors     = 0

for root in ROOTS:
    if not root.exists():
        print(f"  [SKIP]  {root}  (introuvable)")
        continue

    print(f"\n  Scan  {root}")

    for f in root.rglob("*"):
        if not f.is_file():
            continue

        name = f.name

        if name in DELETE:
            try:
                f.unlink()
                print(f"  [DEL]   {f.relative_to(root)}")
                deleted += 1
            except Exception as e:
                print(f"  [ERR]   {f.relative_to(root)}  ({e})")
                errors += 1

        elif name in HIDE:
            if is_hidden(f):
                hidden_skip += 1
            elif hide(f):
                print(f"  [HID]   {f.relative_to(root)}")
                hidden_ok += 1
            else:
                print(f"  [ERR]   {f.relative_to(root)}  (hide échoué)")
                errors += 1

print(f"""
  ─────────────────────────────
  Cachés   : {hidden_ok}  ({hidden_skip} déjà cachés)
  Supprimés: {deleted}
  Erreurs  : {errors}
""")
