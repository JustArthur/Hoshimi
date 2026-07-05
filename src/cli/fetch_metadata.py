#!/usr/bin/env python3
"""
HOSHIMI (星見) — fetch_metadata.py
Télécharge les métadonnées depuis AniList (anime) ou TMDB (film / série).
Remplace fetch-metadata.php, fetch-metadata.ps1 et tmdb_fetch.py.

Usage :
  python fetch_metadata.py --media anime  --scan "Y:/Animes"
  python fetch_metadata.py --media film   --scan "Y:/Films"
  python fetch_metadata.py --media serie  --scan "Y:/Series"
  python fetch_metadata.py --media anime  "Attack on Titan"
  python fetch_metadata.py --media film   "Inception"
  python fetch_metadata.py --media serie  "Breaking Bad"
  python fetch_metadata.py --media anime  --scan "Y:/Animes" --force
"""

import argparse
import json
import os
import re
import sys
import time
import threading
import urllib.request
import urllib.parse
import urllib.error
from pathlib import Path
from typing import Optional, Tuple

# ─── Constantes ───────────────────────────────────────────────────────────────

ANILIST_URL     = "https://graphql.anilist.co"
TMDB_BASE_URL   = "https://api.themoviedb.org/3"
TMDB_IMAGE_BASE = "https://image.tmdb.org/t/p"
COVER_SIZE      = "original"
DELAY_ANILIST   = 0.5   # secondes entre chaque requête AniList
DELAY_TMDB      = 0.25  # secondes entre chaque requête TMDB

# ─── Console ──────────────────────────────────────────────────────────────────

def cprint(color: str, tag: str, msg: str) -> None:
    codes = {"cyan": "36", "green": "32", "yellow": "33", "red": "31", "magenta": "35"}
    print(f"\033[{codes[color]}m[{tag}]\033[0m  {msg}")

def info(msg: str)  -> None: cprint("cyan",    "INFO ", msg)
def ok(msg: str)    -> None: cprint("green",   "OK   ", msg)
def warn(msg: str)  -> None: cprint("yellow",  "WARN ", msg)
def err(msg: str)   -> None: cprint("red",     "ERROR", msg)
def sep()           -> None: print(f"\n{'─' * 54}")

# ─── Nettoyage du nom ─────────────────────────────────────────────────────────

def parse_title_year(raw: str) -> Tuple[str, Optional[int]]:
    """Extrait l'année entre parenthèses en fin de chaîne : 'Titre (2025)' → ('Titre', 2025)."""
    m = re.search(r'\s*\((\d{4})\)\s*$', raw)
    if m:
        year = int(m.group(1))
        if 1900 <= year <= 2100:
            return raw[:m.start()].strip(), year
    return raw, None


def clean_name(raw: str) -> str:
    """Supprime les balises scène / numéros de saison et normalise les espaces."""
    s = re.sub(r"\[.*?\]",          "",  raw)
    s = re.sub(r"\(.*?\)",          "",  s)
    s = re.sub(r"\bS\d{1,2}\b",     "",  s,  flags=re.IGNORECASE)
    s = re.sub(r"\bSeason\s*\d+\b", "",  s,  flags=re.IGNORECASE)
    s = re.sub(r"\bSaison\s*\d+\b", "",  s,  flags=re.IGNORECASE)
    s = re.sub(r"\bPart\s*\d+\b",   "",  s,  flags=re.IGNORECASE)
    s = re.sub(r"\d{4}$",           "",  s)
    s = re.sub(r"[_.]",             " ", s)
    return re.sub(r"\s{2,}",        " ", s).strip()

# ─── Téléchargement générique ─────────────────────────────────────────────────

def download_file(url: str, dest: Path) -> bool:
    try:
        with urllib.request.urlopen(url) as r, open(dest, "wb") as f:
            f.write(r.read())
        return True
    except Exception as e:
        warn(f"Impossible de télécharger {url} : {e}")
        return False

# ══════════════════════════════════════════════════════════════════════════════
#  CLÉ API TMDB
# ══════════════════════════════════════════════════════════════════════════════

def _find_api_key(cli_key: Optional[str]) -> Optional[str]:
    """Cherche la clé TMDB sans quitter si absente."""
    if cli_key:
        return cli_key
    key = os.environ.get("TMDB_API_KEY")
    if key:
        return key
    env_file = Path(__file__).resolve().parent.parent.parent / ".env"
    if env_file.exists():
        for line in env_file.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line.startswith("TMDB_API_KEY"):
                _, _, value = line.partition("=")
                value = value.strip().strip('"').strip("'")
                if value:
                    return value
    return None


def load_api_key(cli_key: Optional[str]) -> str:
    key = _find_api_key(cli_key)
    if key:
        return key
    err(
        "Clé API TMDB introuvable.\n"
        "        → Ajoutez  TMDB_API_KEY=ta_clé  dans le fichier .env à la racine du projet\n"
        "        → Ou définissez la variable d'environnement  TMDB_API_KEY\n"
        "        → Ou passez  --api-key ta_clé\n"
        "        Clé gratuite : https://www.themoviedb.org/settings/api"
    )
    sys.exit(1)

# ══════════════════════════════════════════════════════════════════════════════
#  ANILIST — Anime
# ══════════════════════════════════════════════════════════════════════════════

ANILIST_QUERY = """
query ($search: String) {
  Media(search: $search, type: ANIME) {
    id idMal
    title { romaji english native }
    type format status
    description(asHtml: false)
    startDate { year month day }
    season seasonYear episodes
    coverImage { extraLarge large medium color }
    bannerImage
    genres averageScore popularity
    trailer { id site }
    studios(isMain: true) { nodes { name } }
    relations { edges { relationType node { id title { romaji english } type } } }
    characters(sort: [ROLE, RELEVANCE], perPage: 20) {
      nodes {
        id
        name { full native }
        image { medium large }
      }
    }
  }
}
"""


def anilist_search(name: str) -> Optional[dict]:
    payload = json.dumps({"query": ANILIST_QUERY, "variables": {"search": name}}).encode()
    req = urllib.request.Request(
        ANILIST_URL,
        data=payload,
        headers={
            "Content-Type": "application/json",
            "User-Agent": "Mozilla/5.0 (compatible; Hoshimi/1.0)",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=10) as r:
            data = json.loads(r.read().decode())
            return data.get("data", {}).get("Media")
    except Exception as e:
        warn(f"Erreur AniList : {e}")
        return None


def process_anime(
    name: str,
    output_dir: Path,
    api_key: Optional[str],
    force: bool,
    year: Optional[int] = None,
) -> bool:
    """Tente TMDB (TV) en premier, fallback AniList si rien trouvé ou pas de clé."""
    metadata_path = output_dir / "metadata.json"
    cover_exists  = any(output_dir.glob("cover.*"))
    json_existed  = metadata_path.exists()

    if not force and json_existed and cover_exists:
        return True  # skip silencieux

    # ── 1. TMDB (anime = série TV) ────────────────────────────────────────────
    if api_key:
        found = search_tmdb(name, api_key, "tv", auto=True, year=year)
        if found:
            result, media_type = found
            media_id = result["id"]
            details  = fetch_with_fallback(f"/tv/{media_id}", api_key)
            images   = fetch_images(media_id, "tv", api_key)
            covers   = download_covers_tmdb(images, output_dir)
            credits  = fetch_credits(media_id, "tv", api_key)

            # Toujours enrichir les genres via AniList (plus granulaires que TMDB)
            al_data        = anilist_search(name)
            anilist_genres = al_data.get("genres", []) if al_data else []
            if anilist_genres:
                ok(f"  [AL]    {len(anilist_genres)} genre(s) AniList : {', '.join(anilist_genres)}")
            else:
                warn("  [AL]    Aucun genre AniList trouvé, genres TMDB conservés.")

            metadata_path.write_text(
                json.dumps(
                    {
                        "media_type": "tv",
                        "details": details,
                        "images": images,
                        "covers": covers,
                        "credits": credits,
                        "anilist_genres": anilist_genres,
                    },
                    ensure_ascii=False,
                    indent=2,
                ),
                encoding="utf-8",
            )
            ok(f"  [TMDB]  metadata.json {'mis à jour' if json_existed else 'créé'}")
            if credits:
                ok(f"  [CAST]  {len(credits.get('cast', []))} acteur(s) récupéré(s)")
            return True
        warn(f"TMDB : aucun résultat pour {name!r} → tentative AniList…")

    # ── 2. Fallback AniList ───────────────────────────────────────────────────
    result = anilist_search(name)
    if not result:
        warn(f"AniList : aucun résultat pour {name!r}")
        return False

    if not json_existed or force:
        metadata_path.write_text(
            json.dumps({"Media": result}, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        ok(f"  [AL]    metadata.json {'mis à jour' if json_existed else 'créé'}")

    if not cover_exists:
        url = (result.get("coverImage") or {}).get("extraLarge") \
           or (result.get("coverImage") or {}).get("large")
        if url:
            ext = Path(urllib.parse.urlparse(url).path).suffix or ".jpg"
            if download_file(url, output_dir / f"cover{ext}"):
                ok(f"  [IMG]   cover{ext} enregistrée")

    banner_url = result.get("bannerImage")
    if banner_url and not any(output_dir.glob("banner.*")):
        ext = Path(urllib.parse.urlparse(banner_url).path).suffix or ".jpg"
        download_file(banner_url, output_dir / f"banner{ext}")

    return True


def scan_anime(root: Path, api_key: Optional[str], force: bool) -> None:
    subdirs = sorted(d for d in root.iterdir() if d.is_dir())
    if not subdirs:
        warn(f"Aucun sous-dossier trouvé dans {root}")
        return

    done = skipped = failed = 0
    total    = len(subdirs)
    src_hint = "TMDB → AniList" if api_key else "AniList"
    info(f"Scan Anime ({src_hint})  —  {total} dossier(s) dans {root}\n")

    for i, folder in enumerate(subdirs, 1):
        title_file = folder / ".title"
        raw_name   = title_file.read_text(encoding="utf-8").strip() if title_file.exists() else folder.name
        raw_no_year, year_hint = parse_title_year(raw_name)
        name = clean_name(raw_no_year)

        year_tag = f" [{year_hint}]" if year_hint else ""
        suffix   = f"  (.title: {raw_name!r})" if title_file.exists() else ""
        print(f"[{i:>3}/{total}] {folder.name}{suffix}{year_tag}")

        metadata_path = folder / "metadata.json"
        cover_exists  = any(folder.glob("cover.*"))

        if not force and metadata_path.exists() and cover_exists:
            print("         ⏭  Déjà complet, skip.")
            skipped += 1
            continue

        if process_anime(name, folder, api_key, force, year=year_hint):
            done += 1
        else:
            failed += 1

        time.sleep(DELAY_ANILIST)

    sep()
    print(f"✅ Anime  —  {done} traité(s)  |  {skipped} ignoré(s)  |  {failed} échoué(s)\n")

# ══════════════════════════════════════════════════════════════════════════════
#  TMDB — Film / Série
# ══════════════════════════════════════════════════════════════════════════════

def tmdb_get(endpoint: str, api_key: str, params: Optional[dict] = None) -> dict:
    p = dict(params or {})
    p["api_key"] = api_key
    url = f"{TMDB_BASE_URL}{endpoint}?{urllib.parse.urlencode(p)}"
    try:
        with urllib.request.urlopen(url, timeout=10) as r:
            return json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        if e.code == 404:
            raise LookupError(f"404 Not Found : {endpoint}")
        err(f"HTTP {e.code} pour {endpoint} : {e.read().decode()[:200]}")
        sys.exit(1)
    except urllib.error.URLError as e:
        err(f"Réseau : {e.reason}")
        sys.exit(1)


def fetch_with_fallback(endpoint: str, api_key: str) -> dict:
    """Récupère en fr-FR, complète les champs vides avec en-US."""
    fr = tmdb_get(endpoint, api_key, {"language": "fr-FR"})
    en = tmdb_get(endpoint, api_key, {"language": "en-US"})
    for field in ("title", "name", "overview", "tagline"):
        if not fr.get(field) and en.get(field):
            fr[field] = en[field]
    return fr


def fetch_images(media_id: int, media_type: str, api_key: str) -> dict:
    endpoint = f"/{'tv' if media_type == 'tv' else 'movie'}/{media_id}/images"
    raw = tmdb_get(endpoint, api_key, {"include_image_language": "fr,en,null"})

    def pick_best(items: list, prefer_lang: str = "fr") -> list:
        preferred = [i for i in items if i.get("iso_639_1") == prefer_lang]
        others    = [i for i in items if i.get("iso_639_1") != prefer_lang]
        for lst in (preferred, others):
            lst.sort(key=lambda x: x.get("vote_count", 0), reverse=True)
        return preferred + others

    def enrich(items: list, sizes: list) -> list:
        return [
            {**img, "urls": {s: f"{TMDB_IMAGE_BASE}/{s}{img['file_path']}" for s in sizes}}
            for img in items
        ]

    return {
        "posters":   enrich(pick_best(raw.get("posters",   [])),            ["w185", "w342", "w500", "original"]),
        "backdrops": enrich(pick_best(raw.get("backdrops", [])),            ["w300", "w780", "w1280", "original"]),
        "logos":     enrich(pick_best(raw.get("logos",     []), "en"),      ["w185", "w300", "w500", "original"]),
    }


# ── Helpers affichage ─────────────────────────────────────────────────────────

def _name(r: dict)  -> str: return r.get("title") or r.get("name") or "?"
def _year(r: dict)  -> str:
    d = r.get("release_date") or r.get("first_air_date") or ""
    return d[:4] if d else "????"
def _label(t: str)  -> str: return "Série TV" if t == "tv" else "Film"


# ── Recherche & sélection ─────────────────────────────────────────────────────

def search_tmdb(
    title: str,
    api_key: str,
    forced_type: Optional[str],
    auto: bool = False,
    year: Optional[int] = None,
) -> Optional[Tuple[dict, str]]:
    candidates = []
    for mtype in ([forced_type] if forced_type else ["movie", "tv"]):
        ep     = "/search/tv" if mtype == "tv" else "/search/movie"
        params: dict = {"query": title, "language": "fr-FR"}
        if year:
            params["first_air_date_year" if mtype == "tv" else "primary_release_year"] = year
        data = tmdb_get(ep, api_key, params)
        for r in data.get("results", []):
            r["_mtype"] = mtype
            candidates.append(r)

    if not candidates:
        return None

    title_lc = title.strip().lower()

    def score(r: dict) -> tuple:
        n = _name(r).strip().lower()
        o = (r.get("original_title") or r.get("original_name") or "").strip().lower()
        return (n == title_lc or o == title_lc, n.startswith(title_lc) or o.startswith(title_lc), r.get("popularity", 0))

    candidates.sort(key=score, reverse=True)
    top = candidates[:8]

    def is_exact(r: dict) -> bool:
        n = _name(r).strip().lower()
        o = (r.get("original_title") or r.get("original_name") or "").strip().lower()
        return n == title_lc or o == title_lc

    # ── Mode automatique (scan) ──
    if auto:
        exact = [r for r in top if is_exact(r)]

        if len(exact) <= 1:
            chosen = top[0]
            print(f"    → {_name(chosen)} ({_year(chosen)}) [{_label(chosen['_mtype'])}]")
            return chosen, chosen["_mtype"]

        warn(f"Ambiguïté pour {title!r} :")
        for i, r in enumerate(exact, 1):
            print(f"    {i}. [{_label(r['_mtype']):8}] {_name(r)} ({_year(r)}) "
                  f"— ⭐ {r.get('vote_average', 0):.1f}  pop: {r.get('popularity', 0):.1f}")

        fallback = max(exact, key=lambda r: r.get("vote_average", 0))
        print(f"  ⏳ Choix automatique dans 15 s → {_name(fallback)} ({_year(fallback)})")
        print(f"     Votre choix (1-{len(exact)}) : ", end="", flush=True)

        container: list = [None]

        def _read() -> None:
            try:
                raw = input().strip()
                idx = int(raw) - 1
                if 0 <= idx < len(exact):
                    container[0] = exact[idx]
            except (ValueError, EOFError):
                pass

        t = threading.Thread(target=_read, daemon=True)
        t.start()
        t.join(timeout=15)

        chosen = container[0] or fallback
        tag = "Choix manuel" if container[0] else "Timeout → automatique"
        print(f"  ✓  {tag} : {_name(chosen)} ({_year(chosen)})")
        return chosen, chosen["_mtype"]

    # ── Mode interactif (titre unique) ──
    if len(candidates) == 1:
        chosen = candidates[0]
        print(f"✓  Résultat unique : {_name(chosen)} ({_year(chosen)}) [{_label(chosen['_mtype'])}]")
        return chosen, chosen["_mtype"]

    print(f"\n{len(top)} résultat(s). Lequel voulez-vous ? (Entrée = 1)")
    for i, r in enumerate(top, 1):
        print(f"  {i}. [{_label(r['_mtype']):8}] {_name(r)} ({_year(r)}) "
              f"— ⭐ {r.get('vote_average', 0):.1f}  pop: {r.get('popularity', 0):.1f}")

    try:
        raw = input("\nVotre choix : ").strip()
        idx = int(raw) - 1 if raw else 0
        if not (0 <= idx < len(top)):
            raise ValueError
    except (ValueError, EOFError):
        idx = 0
        print("Choix invalide → premier résultat sélectionné.")

    chosen = top[idx]
    print(f"✓  {_name(chosen)} ({_year(chosen)}) [{_label(chosen['_mtype'])}]\n")
    return chosen, chosen["_mtype"]


# ── Téléchargement covers TMDB ────────────────────────────────────────────────

def download_covers_tmdb(images: dict, output_dir: Path) -> dict:
    downloaded: dict = {}

    if images["posters"]:
        best = images["posters"][0]
        url  = best["urls"][COVER_SIZE]
        ext  = Path(best["file_path"]).suffix or ".jpg"
        dest = output_dir / f"cover{ext}"
        print(f"  ⬇  Cover   → {dest.name}")
        if download_file(url, dest):
            downloaded["cover"] = str(dest)

    if images["backdrops"]:
        best = images["backdrops"][0]
        downloaded["banner"] = best["urls"].get("w1280") or best["urls"][COVER_SIZE]
        print(f"  🔗 Banner  → {downloaded['banner'][:70]}…")

    if images["logos"]:
        print(f"  🏷  Logo(s) → {len(images['logos'])} disponible(s) (stocké dans metadata.json)")

    return downloaded


# ── Crédits (cast / crew) pour les films ─────────────────────────────────────

def fetch_credits(media_id: int, media_type: str, api_key: str) -> dict:
    endpoint = f"/{'tv' if media_type == 'tv' else 'movie'}/{media_id}/credits"
    raw  = tmdb_get(endpoint, api_key, {"language": "fr-FR"})
    cast = raw.get("cast", [])
    crew = raw.get("crew", [])
    return {
        "cast": [
            {
                "id":          p.get("id"),
                "name":        p.get("name"),
                "character":   p.get("character"),
                "order":       p.get("order", 999),
                "profile_url": f"{TMDB_IMAGE_BASE}/w185{p['profile_path']}" if p.get("profile_path") else None,
            }
            for p in sorted(cast, key=lambda x: x.get("order", 999))[:20]
        ],
        "crew": [
            {"id": p.get("id"), "name": p.get("name"), "job": p.get("job")}
            for p in crew
            if p.get("job") in ("Director", "Screenplay", "Writer", "Story", "Producer", "Executive Producer")
        ],
    }


# ── Traitement d'un titre TMDB ────────────────────────────────────────────────

def process_tmdb(
    title: str,
    output_dir: Path,
    api_key: str,
    forced_type: Optional[str],
    auto: bool = False,
    force: bool = False,
    year: Optional[int] = None,
) -> bool:
    metadata_path = output_dir / "metadata.json"
    json_existed  = metadata_path.exists()

    if not force and json_existed:
        return True  # skip silencieux

    found = search_tmdb(title, api_key, forced_type, auto=auto, year=year)
    if not found:
        warn(f"Aucun résultat TMDB pour {title!r}")
        return False

    result, media_type = found
    media_id = result["id"]

    endpoint = f"/{'tv' if media_type == 'tv' else 'movie'}/{media_id}"
    details  = fetch_with_fallback(endpoint, api_key)
    images   = fetch_images(media_id, media_type, api_key)
    covers   = download_covers_tmdb(images, output_dir)
    credits  = fetch_credits(media_id, media_type, api_key)

    payload: dict = {"media_type": media_type, "details": details, "images": images, "covers": covers}
    if credits:
        payload["credits"] = credits
        ok(f"  [CAST]  {len(credits.get('cast', []))} acteur(s) récupéré(s)")

    metadata_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    ok(f"  [JSON]  metadata.json {'mis à jour' if json_existed else 'créé'}")
    return True


def scan_tmdb(root: Path, api_key: str, forced_type: Optional[str], force: bool) -> None:
    subdirs = sorted(d for d in root.iterdir() if d.is_dir())
    if not subdirs:
        warn(f"Aucun sous-dossier trouvé dans {root}")
        return

    done = skipped = failed = 0
    total     = len(subdirs)
    type_lbl  = _label(forced_type or "movie")
    info(f"Scan {type_lbl} (TMDB)  —  {total} dossier(s) dans {root}\n")

    for i, folder in enumerate(subdirs, 1):
        title_file = folder / ".title"
        raw_title  = title_file.read_text(encoding="utf-8").strip() if title_file.exists() else folder.name
        title, year_hint = parse_title_year(raw_title)

        year_tag = f" [{year_hint}]" if year_hint else ""
        suffix   = f"  (.title: {raw_title!r})" if title_file.exists() else ""
        print(f"[{i:>3}/{total}] {folder.name}{suffix}{year_tag}")

        if not force and (folder / "metadata.json").exists():
            print("         ⏭  metadata.json déjà présent, skip.")
            skipped += 1
            continue

        success = process_tmdb(title, folder, api_key, forced_type, auto=True, force=force, year=year_hint)
        if success:
            done += 1
        else:
            failed += 1

        time.sleep(DELAY_TMDB)

    sep()
    print(f"✅ {type_lbl}  —  {done} traité(s)  |  {skipped} ignoré(s)  |  {failed} échoué(s)\n")

# ══════════════════════════════════════════════════════════════════════════════
#  SAISONS — épisodes TMDB par dossier Season N/
# ══════════════════════════════════════════════════════════════════════════════

def parse_season_number(folder_name: str) -> Optional[int]:
    """'Season 1' / 'Saison 2' / 'S03' → int, None si non reconnu."""
    m = re.search(r'(?:Season|Saison|S)\s*0*(\d+)', folder_name, re.IGNORECASE)
    return int(m.group(1)) if m else None


def fetch_season_data(
    series_id: int,
    season_num: int,
    api_key: str,
    ep_start: int = 1,
    ep_end: Optional[int] = None,
) -> dict:
    """Récupère les épisodes d'une saison en FR + EN (fallback sur les champs vides).
    ep_start / ep_end permettent d'extraire une plage d'épisodes quand TMDB fusionne
    plusieurs saisons locales en une seule (ex. Dandadan S1+S2 → TMDB Season 1 ep 1-24).
    """
    endpoint = f"/tv/{series_id}/season/{season_num}"
    fr = tmdb_get(endpoint, api_key, {"language": "fr-FR"})
    en = tmdb_get(endpoint, api_key, {"language": "en-US"})

    for field in ("name", "overview"):
        if not fr.get(field) and en.get(field):
            fr[field] = en[field]

    en_eps = {e["episode_number"]: e for e in en.get("episodes", [])}
    enriched = []
    for ep in fr.get("episodes", []):
        ep_num = ep["episode_number"]
        if ep_num < ep_start:
            continue
        if ep_end is not None and ep_num > ep_end:
            continue
        en_ep = en_eps.get(ep_num, {})
        for field in ("name", "overview"):
            if not ep.get(field) and en_ep.get(field):
                ep[field] = en_ep[field]
        if ep.get("still_path"):
            ep["still_urls"] = {
                s: f"{TMDB_IMAGE_BASE}/{s}{ep['still_path']}"
                for s in ["w185", "w300", "w500", "original"]
            }
        enriched.append(ep)

    fr["episodes"] = enriched
    if ep_start > 1 or ep_end is not None:
        fr["_episode_range"] = [ep_start, ep_end]
    fr["_tmdb_season"] = season_num
    return fr


VIDEO_EXT = {'.mkv', '.mp4', '.avi', '.webm'}


def _count_local_episodes(season_dir: Path) -> int:
    """Compte les fichiers vidéo dans un dossier saison."""
    return sum(1 for f in season_dir.iterdir()
               if f.is_file() and f.suffix.lower() in VIDEO_EXT)


def auto_build_season_map(
    series_dir: Path,
    series_id: int,
    api_key: str,
    season_dirs: list,
    tmdb_season_count: int,
) -> dict:
    """Génère automatiquement le season-map quand TMDB a moins de saisons que les dossiers locaux.

    Principe : on récupère tous les épisodes TMDB dans l'ordre, puis on les distribue
    aux dossiers locaux en fonction du nombre de fichiers vidéo dans chacun.
    Le fichier .season-map.json est écrit dans series_dir.
    """
    info(f"  TMDB : {tmdb_season_count} saison(s) pour {len(season_dirs)} dossier(s) local/-aux")
    info("  → Génération automatique du .season-map.json…")

    # 1. Récupérer tous les épisodes TMDB (toutes saisons disponibles)
    all_episodes: list[tuple[int, int]] = []  # (tmdb_season, episode_number)
    for tmdb_s in range(1, tmdb_season_count + 1):
        try:
            raw = tmdb_get(f"/tv/{series_id}/season/{tmdb_s}", api_key, {"language": "fr-FR"})
            for ep in raw.get("episodes", []):
                all_episodes.append((tmdb_s, ep["episode_number"]))
            time.sleep(DELAY_TMDB)
        except LookupError:
            warn(f"  TMDB Season {tmdb_s} introuvable.")

    if not all_episodes:
        warn("  Impossible de récupérer les épisodes TMDB pour générer le mapping.")
        return {}

    # 2. Distribuer aux dossiers locaux selon leur nombre d'épisodes
    season_map: dict = {}
    ep_idx = 0

    for season_dir in season_dirs:
        local_count = _count_local_episodes(season_dir)
        if local_count == 0 or ep_idx >= len(all_episodes):
            continue

        end_idx      = min(ep_idx + local_count - 1, len(all_episodes) - 1)
        first_s, first_ep = all_episodes[ep_idx]
        _last_s,  last_ep = all_episodes[end_idx]

        season_map[season_dir.name] = {
            "tmdb_season": first_s,
            "ep_start":    first_ep,
            "ep_end":      last_ep,
        }
        print(f"    {season_dir.name:12}  →  TMDB S{first_s} ep {first_ep}–{last_ep}  ({local_count} fichier(s))")
        ep_idx += local_count

    if not season_map:
        return {}

    # 3. Écrire le fichier
    map_file = series_dir / ".season-map.json"
    map_file.write_text(json.dumps(season_map, ensure_ascii=False, indent=2), encoding="utf-8")
    ok(f"  .season-map.json créé ({len(season_map)} entrée(s))")
    return season_map


def process_series_seasons(series_dir: Path, api_key: str, force: bool) -> Tuple[int, int, int]:
    """Fetch les saisons d'une série. Retourne (done, skipped, failed).

    Détecte automatiquement si TMDB fusionne des saisons locales (ex. Dandadan)
    et génère un .season-map.json si nécessaire. Vous pouvez aussi créer ce fichier
    manuellement pour un contrôle précis :

        {
          "Season 1": { "tmdb_season": 1, "ep_start": 1,  "ep_end": 12 },
          "Season 2": { "tmdb_season": 1, "ep_start": 13, "ep_end": 24 }
        }
    """
    metadata_path = series_dir / "metadata.json"
    if not metadata_path.exists():
        warn(f"  {series_dir.name} : pas de metadata.json à la racine, ignoré.")
        return 0, 0, 0

    try:
        meta = json.loads(metadata_path.read_text(encoding="utf-8"))
        if meta.get("media_type") != "tv":
            warn(f"  {series_dir.name} : pas une série TV (media_type={meta.get('media_type')!r}), ignoré.")
            return 0, 0, 0
        series_id: int = meta["details"]["id"]
    except Exception as e:
        warn(f"  {series_dir.name} : metadata.json illisible ({e}), ignoré.")
        return 0, 0, 0

    # ── Chargement du mapping (manuel ou auto-généré) ─────────────────────────
    season_map: dict = {}
    season_map_file = series_dir / ".season-map.json"
    if season_map_file.exists():
        try:
            season_map = json.loads(season_map_file.read_text(encoding="utf-8"))
            info(f"  .season-map.json chargé ({len(season_map)} entrée(s))")
        except Exception as e:
            warn(f"  .season-map.json illisible : {e}")

    def season_order(d: Path) -> int:
        num = parse_season_number(d.name)
        if num is not None:
            return num
        entry = season_map.get(d.name, {})
        if "number" in entry:
            return int(entry["number"])
        keys = list(season_map.keys())
        return keys.index(d.name) + 1 if d.name in keys else 99999

    season_dirs = sorted(
        (d for d in series_dir.iterdir() if d.is_dir() and (
            parse_season_number(d.name) is not None or d.name in season_map
        )),
        key=season_order,
    )
    if not season_dirs:
        warn(f"  {series_dir.name} : aucun dossier Season N/ détecté.")
        return 0, 0, 0

    # ── Auto-génération du season-map si TMDB a moins de saisons ─────────────
    if not season_map:
        tmdb_season_count = meta.get("details", {}).get("number_of_seasons", 0)
        if tmdb_season_count > 0 and len(season_dirs) > tmdb_season_count:
            season_map = auto_build_season_map(
                series_dir, series_id, api_key, season_dirs, tmdb_season_count
            )

    # Cache pour éviter de fetcher deux fois la même saison TMDB
    tmdb_cache: dict[int, dict] = {}

    done = skipped = failed = 0
    for season_dir in season_dirs:
        local_num = parse_season_number(season_dir.name)
        if local_num is None:
            entry = season_map.get(season_dir.name, {})
            local_num = int(entry["number"]) if "number" in entry else season_order(season_dir)
        season_meta = season_dir / "metadata.json"
        display_name = season_dir.name if parse_season_number(season_dir.name) is None else f"Season {local_num:>2}"

        # Résolution du mapping
        mapping   = season_map.get(season_dir.name) or season_map.get(f"Season {local_num}") or {}
        tmdb_num  = int(mapping.get("tmdb_season", local_num))
        ep_start  = int(mapping.get("ep_start", 1))
        ep_end_v  = mapping.get("ep_end")
        ep_end    = int(ep_end_v) if ep_end_v is not None else None

        map_tag = ""
        if tmdb_num != local_num or ep_start > 1 or ep_end is not None:
            ep_range = f" ep {ep_start}–{ep_end or '∞'}" if (ep_start > 1 or ep_end) else ""
            map_tag = f" [→ TMDB S{tmdb_num}{ep_range}]"

        if not force and season_meta.exists():
            ep_count = len(json.loads(season_meta.read_text(encoding="utf-8")).get("episodes", []))
            print(f"    {display_name}  ⏭  déjà présent ({ep_count} éps), skip.{map_tag}")
            skipped += 1
            continue

        print(f"    {display_name}  ⬇  fetching TMDB S{tmdb_num}…{map_tag}")
        try:
            if tmdb_num not in tmdb_cache:
                # Fetch complet d'abord, on filtrera après (pour le cache)
                tmdb_cache[tmdb_num] = fetch_season_data(series_id, tmdb_num, api_key)

            raw = tmdb_cache[tmdb_num]

            # Filtrage de la plage d'épisodes si nécessaire
            if ep_start > 1 or ep_end is not None:
                episodes = [
                    e for e in raw.get("episodes", [])
                    if ep_start <= e.get("episode_number", 0) <= (ep_end or 99_999)
                ]
                data = {**raw, "episodes": episodes,
                        "_episode_range": [ep_start, ep_end], "_tmdb_season": tmdb_num}
            else:
                data = raw

            ep_count = len(data.get("episodes", []))
            existed  = season_meta.exists()
            season_meta.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
            ok(f"    {display_name}  ✓  {ep_count} épisode(s) → metadata.json {'mis à jour' if existed else 'créé'}")
            done += 1

        except LookupError:
            warn(
                f"    {display_name}  ✗  TMDB Season {tmdb_num} introuvable (404).\n"
                f"             Créez un .season-map.json dans {series_dir.name}/\n"
                f"             pour mapper ce dossier sur la bonne saison TMDB."
            )
            failed += 1
        except Exception as e:
            warn(f"    Season {local_num:>2}  ✗  erreur : {e}")
            failed += 1

        time.sleep(DELAY_TMDB)

    return done, skipped, failed


def scan_seasons(root: Path, api_key: str, force: bool) -> None:
    # Si le dossier pointé contient lui-même un metadata.json → c'est une série unique
    if (root / "metadata.json").exists():
        series_dirs = [root]
        info(f"Saisons (TMDB)  —  série unique : {root.name}\n")
    else:
        series_dirs = sorted(d for d in root.iterdir() if d.is_dir())
        info(f"Saisons (TMDB)  —  {len(series_dirs)} série(s) dans {root}\n")

    total_done = total_skipped = total_failed = 0
    for series_dir in series_dirs:
        print(f"📂 {series_dir.name}")
        d, s, f = process_series_seasons(series_dir, api_key, force)
        total_done += d; total_skipped += s; total_failed += f
        print()

    sep()
    print(f"✅ Saisons  —  {total_done} traité(s)  |  {total_skipped} ignoré(s)  |  {total_failed} échoué(s)\n")


# ══════════════════════════════════════════════════════════════════════════════
#  MAIN
# ══════════════════════════════════════════════════════════════════════════════

def main() -> None:
    parser = argparse.ArgumentParser(
        prog="fetch_metadata.py",
        description=(
            "HOSHIMI — Télécharge les métadonnées depuis AniList (anime) ou TMDB (film / série).\n"
            "Remplace fetch-metadata.php, fetch-metadata.ps1 et tmdb_fetch.py.\n"
        ),
        epilog=(
            "Exemples :\n"
            "  python fetch_metadata.py --media anime  --scan \"Y:/Animes\"\n"
            "  python fetch_metadata.py --media film   --scan \"Y:/Films\"\n"
            "  python fetch_metadata.py --media serie  --scan \"Y:/Series\"\n"
            "  python fetch_metadata.py --media anime  \"Attack on Titan\"\n"
            "  python fetch_metadata.py --media film   \"Inception\"\n"
            "  python fetch_metadata.py --media anime  --scan \"Y:/Animes\" --force\n"
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )

    parser.add_argument(
        "--media", "-m",
        required=True,
        choices=["anime", "film", "serie", "season"],
        help=(
            "Type de média :\n"
            "  anime  → AniList (TMDB TV en priorité si clé dispo)\n"
            "  film   → TMDB movie\n"
            "  serie  → TMDB TV (métadonnées de la série)\n"
            "  season → TMDB TV par saison (épisodes + stills dans Season N/metadata.json)"
        ),
    )
    parser.add_argument(
        "--scan", "-s",
        metavar="DOSSIER",
        help="Scanne tous les sous-dossiers du répertoire (mode batch)",
    )
    parser.add_argument(
        "title",
        nargs="?",
        help="Titre unique à rechercher (mode interactif)",
    )
    parser.add_argument(
        "--api-key",
        default=None,
        help="Clé API TMDB (obligatoire pour film/serie, optionnelle pour anime — active le fallback TMDB)",
    )
    parser.add_argument(
        "-o", "--output",
        default=None,
        metavar="DOSSIER",
        help="Dossier de sortie en mode titre unique (défaut : répertoire courant)",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Réécrit les metadata.json déjà existants",
    )

    args = parser.parse_args()

    if not args.scan and not args.title:
        parser.error("Spécifiez --scan DOSSIER pour un scan batch, ou TITRE pour le mode interactif.")

    is_anime    = args.media == "anime"
    is_season   = args.media == "season"
    forced_type = "tv" if args.media == "serie" else ("movie" if args.media == "film" else None)

    if is_anime:
        # Clé TMDB optionnelle pour anime : active TMDB→AniList, sans bloquer si absente
        api_key = _find_api_key(args.api_key)
        if api_key:
            info("Clé TMDB trouvée — stratégie : TMDB d'abord, AniList en fallback.")
        else:
            warn("Clé TMDB absente — fallback AniList uniquement.")
    else:
        api_key = load_api_key(args.api_key)

    # ── Mode scan ──────────────────────────────────────────────────────────────
    if args.scan:
        root = Path(args.scan)
        if not root.is_dir():
            err(f"Dossier introuvable : {root}")
            sys.exit(1)
        if is_season:
            scan_seasons(root, api_key, force=args.force)
        elif is_anime:
            scan_anime(root, api_key=api_key, force=args.force)
        else:
            scan_tmdb(root, api_key, forced_type, force=args.force)
        return

    # ── Mode titre unique / dossier unique ────────────────────────────────────
    if is_season:
        # En mode season, l'argument positionnel est le chemin du dossier série
        series_path = Path(args.title) if args.title else (Path(args.output) if args.output else None)
        if not series_path or not series_path.is_dir():
            parser.error("--media season : spécifiez le chemin du dossier de la série en argument.")
        scan_seasons(series_path, api_key, force=args.force)
        return

    if not args.title:
        parser.error("Spécifiez un TITRE en argument ou --scan DOSSIER.")

    output_dir = Path(args.output) if args.output else Path(".")
    raw_title, year_hint = parse_title_year(args.title)
    year_tag = f" [{year_hint}]" if year_hint else ""
    print(f"\n🔍  Recherche : {raw_title!r}{year_tag}  [{args.media}] …\n")

    if is_anime:
        success = process_anime(clean_name(raw_title), output_dir, api_key=api_key, force=args.force, year=year_hint)
    else:
        success = process_tmdb(raw_title, output_dir, api_key, forced_type, auto=False, force=args.force, year=year_hint)

    if success:
        print(f"\n✅  Terminé → {output_dir / 'metadata.json'}\n")
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
