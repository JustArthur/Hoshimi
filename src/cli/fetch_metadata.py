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
  python fetch_metadata.py --type anime  --cover-only "Y:/Animes"
  python fetch_metadata.py --type anime  --cover-only "Y:/Animes/Initial D" --cover-force
"""

import argparse
import glob as _glob
import json
import os
import re
import tempfile
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
DELAY_ANILIST   = 1.5
DELAY_TMDB      = 0.25

# ─── Console ──────────────────────────────────────────────────────────────────

def info(msg: str)  -> None: print(f"[INFO]  {msg}", flush=True)
def ok(msg: str)    -> None: print(f"[ OK ]  {msg}", flush=True)
def warn(msg: str)  -> None: print(f"[WARN]  {msg}", flush=True)
def err(msg: str)   -> None: print(f"[ERR ]  {msg}", flush=True)
def sep()           -> None: print(f"\n{'─' * 54}", flush=True)

def hide_file(path: Path) -> None:
    """Marque le fichier comme caché sur Windows (attribut H)."""
    if os.name == "nt":
        try:
            import ctypes
            ctypes.windll.kernel32.SetFileAttributesW(str(path), 0x2)
        except Exception:
            pass

# ─── Helpers internes ─────────────────────────────────────────────────────────

def _has_cjk(text: str) -> bool:
    return any('　' <= c <= '鿿' or '＀' <= c <= '￯' for c in text)


def _fill_from_en(fr: dict, en: dict, fields: tuple) -> None:
    """Copie les champs vides (ou en japonais) de `fr` depuis `en`."""
    for f in fields:
        fr_val = fr.get(f) or ""
        en_val = en.get(f) or ""
        if (not fr_val or _has_cjk(fr_val)) and en_val:
            fr[f] = en_val


def _media_ep(media_id: int, media_type: str, suffix: str = "") -> str:
    """Construit /tv/{id}/… ou /movie/{id}/…"""
    return f"/{'tv' if media_type == 'tv' else 'movie'}/{media_id}{suffix}"


def _read_title(folder: Path) -> Tuple[str, bool]:
    """Retourne (titre, False) — toujours le nom du dossier."""
    return (folder.name, False)


def _list_subdirs(root: Path) -> list:
    """Sous-dossiers triés, liste vide (+ warn) si aucun."""
    dirs = sorted(d for d in root.iterdir() if d.is_dir())
    if not dirs:
        warn(f"Aucun sous-dossier trouvé dans {root}")
    return dirs


def _scan_loop(subdirs: list, label: str, delay: float, step_fn) -> None:
    """Boucle de scan commune : appelle step_fn(i, total, folder) → True/None/False."""
    done = skipped = failed = 0
    total = len(subdirs)
    for i, folder in enumerate(subdirs, 1):
        result = step_fn(i, total, folder)
        if result is None:  skipped += 1
        elif result:        done    += 1
        else:               failed  += 1
        if result is not None:
            time.sleep(delay)
    sep()
    print(f"✓ {label}  —  {done} traité(s)  |  {skipped} ignoré(s)  |  {failed} échoué(s)\n", flush=True)


def _lean_episode(ep: dict) -> dict:
    """Retourne un épisode allégé (sans URLs redondantes) pour metadata.json."""
    still = (ep.get("still_urls") or {}).get("w300") or (ep.get("still_urls") or {}).get("w500")
    out = {
        "name":     ep.get("name"),
        "overview": ep.get("overview"),
        "air_date": ep.get("air_date"),
        "runtime":  ep.get("runtime"),
        "still":    still,
    }
    return {k: v for k, v in out.items() if v is not None}


def _lean_meta(media_type: str, details: dict, images: dict,
               credits: dict, anilist_genres: list) -> dict:
    """Construit le metadata.json v2 minimal (seuls les champs utilisés par le PHP)."""
    is_tv  = media_type == "tv"
    yr_str = (details.get("first_air_date" if is_tv else "release_date") or "")[:4]
    nets   = details.get("networks") or details.get("production_companies") or []
    genres = anilist_genres or [
        g["name"] if isinstance(g, dict) else g
        for g in (details.get("genres") or [])
    ]

    def _url(img_type: str, *sizes: str) -> Optional[str]:
        items = images.get(img_type) or []
        urls  = (items[0].get("urls") or {}) if items else {}
        return next((urls[s] for s in sizes if urls.get(s)), None)

    logos = [
        {s: lg["urls"][s] for s in ("w500", "original") if (lg.get("urls") or {}).get(s)}
        for lg in (images.get("logos") or [])[:3]
    ]
    cast = [
        {k: p[k] for k in ("name", "character", "profile_url") if p.get(k)}
        for p in (credits.get("cast") or [])
    ]
    meta: dict = {
        "v":              2,
        "media_type":     media_type,
        "tmdb_id":        details.get("id"),
        "title":          details.get("name") or details.get("title"),
        "title_original": details.get("original_name") or details.get("original_title"),
        "overview":       details.get("overview"),
        "status":         details.get("status"),
        "year":           int(yr_str) if yr_str.isdigit() else None,
        "genres":         genres or None,
        "score":          round(float(details.get("vote_average") or 0), 1) or None,
        "popularity":     int(details.get("popularity") or 0) or None,
        "format":         "TV" if is_tv else "Film",
        "studio":         nets[0]["name"] if nets else None,
        "cover":          _url("posters",   "w500", "original"),
        "banner":         _url("backdrops", "w1280", "original"),
        "logos":          logos or None,
        "cast":           cast  or None,
    }
    if is_tv:
        meta["episodes_total"] = details.get("number_of_episodes")
        meta["seasons_count"]  = details.get("number_of_seasons")
        rt = (details.get("episode_run_time") or [None])[0]
        if rt:
            meta["runtime"] = rt
    else:
        meta["runtime"] = details.get("runtime")
        col = details.get("belongs_to_collection")
        if col:
            meta["collection_id"]   = col.get("id")
            meta["collection_name"] = col.get("name")
    return {k: v for k, v in meta.items() if v is not None}

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
        req = urllib.request.Request(url, headers={
            "User-Agent": "Mozilla/5.0 (compatible; Hoshimi/1.0)",
            "Referer": "https://anilist.co",
        })
        with urllib.request.urlopen(req) as r, open(dest, "wb") as f:
            f.write(r.read())
        return True
    except Exception as e:
        warn(f"Impossible de télécharger {url} : {e}")
        return False

# ══════════════════════════════════════════════════════════════════════════════
#  CLÉ API TMDB
# ══════════════════════════════════════════════════════════════════════════════

def _find_api_key(cli_key: Optional[str]) -> Optional[str]:
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

_ANILIST_QUERY_BODY = """
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
      edges {
        role
        node {
          name { full }
          image { large medium }
        }
        voiceActors(language: JAPANESE) {
          name { full }
          image { large medium }
        }
      }
    }
"""

def _anilist_query(adult: bool = False) -> str:
    filter_adult = ", isAdult: true" if adult else ""
    return (
        "query ($search: String) {\n"
        "  Media(search: $search, type: ANIME" + filter_adult + ") {"
        + _ANILIST_QUERY_BODY +
        "  }\n}"
    )


def anilist_search(name: str, adult: bool = False) -> Optional[dict]:
    payload = json.dumps({"query": _anilist_query(adult), "variables": {"search": name}}).encode()
    req = urllib.request.Request(
        ANILIST_URL,
        data=payload,
        headers={
            "Content-Type": "application/json",
            "User-Agent": "Mozilla/5.0 (compatible; Hoshimi/1.0)",
        },
        method="POST",
    )
    for attempt in range(4):
        try:
            with urllib.request.urlopen(req, timeout=10) as r:
                data = json.loads(r.read().decode())
                return data.get("data", {}).get("Media")
        except urllib.error.HTTPError as e:
            if e.code == 429:
                wait = 10 * (2 ** attempt)  # 10s, 20s, 40s, 80s
                warn(f"Erreur AniList : HTTP 429 — attente {wait}s avant retry ({attempt + 1}/3)…")
                time.sleep(wait)
            else:
                warn(f"Erreur AniList : {e}")
                return None
        except Exception as e:
            warn(f"Erreur AniList : {e}")
            return None
    warn(f"Erreur AniList : 429 persistant après 3 retries pour {name!r}")
    return None


def process_anime(
    name: str,
    output_dir: Path,
    api_key: Optional[str],
    force: bool,
    year: Optional[int] = None,
    adult: bool = False,
    skip_images: bool = False,
) -> bool:
    """Tente TMDB (TV) en premier, fallback AniList si rien trouvé ou pas de clé."""
    metadata_path = output_dir / "metadata.json"
    cover_exists  = any(output_dir.glob("cover.*"))
    json_existed  = metadata_path.exists()

    if not force and json_existed and cover_exists:
        return True  # skip silencieux

    # ── 1. TMDB (anime = série TV) ────────────────────────────────────────────
    if api_key:
        # Lookup direct par ID si fichier .tmdb_id présent
        tmdb_id_file = output_dir / ".tmdb_id"
        if tmdb_id_file.exists():
            forced_id = tmdb_id_file.read_text(encoding="utf-8").strip()
            media_id  = int(forced_id)
            ok(f"  [TMDB]  ID forcé depuis .tmdb_id : {media_id}")
            found = ({"id": media_id}, "tv")
        else:
            found = search_tmdb(name, api_key, "tv", auto=True, year=year)
        if found:
            result, media_type = found
            media_id = result["id"]
            details  = fetch_with_fallback(f"/tv/{media_id}", api_key)
            images   = fetch_images(media_id, "tv", api_key, adult=adult)
            if not skip_images:
                covers = download_covers_tmdb(images, output_dir)
            credits  = fetch_credits(media_id, "tv", api_key)

            al_data        = anilist_search(name, adult=adult)
            anilist_genres = al_data.get("genres", []) if al_data else []
            if anilist_genres:
                ok(f"  [AL]    {len(anilist_genres)} genre(s) AniList : {', '.join(anilist_genres)}")
            else:
                warn("  [AL]    Aucun genre AniList trouvé, genres TMDB conservés.")

            al_cast = []
            for edge in ((al_data or {}).get("characters") or {}).get("edges", []):
                char = edge.get("node") or {}
                vas  = edge.get("voiceActors") or []
                va   = vas[0] if vas else None
                al_cast.append({k: v for k, v in {
                    "name":        ((va or {}).get("name") or {}).get("full") or (char.get("name") or {}).get("full"),
                    "character":   (char.get("name") or {}).get("full"),
                    "profile_url": (char.get("image") or {}).get("large") or (char.get("image") or {}).get("medium"),
                    "role":        edge.get("role"),
                }.items() if v})

            payload = _lean_meta("tv", details, images, credits, anilist_genres)
            if al_cast:
                payload["cast"] = al_cast
                ok(f"  [AL]    {len(al_cast)} personnage(s) AniList utilisé(s) pour le cast")
            elif credits:
                ok(f"  [CAST]  {len(credits.get('cast', []))} VA TMDB utilisé(s) en fallback")

            metadata_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
            hide_file(metadata_path)
            ok(f"  [TMDB]  metadata.json {'mis à jour' if json_existed else 'créé'}")

            # Fallback cover AniList si TMDB n'a rien téléchargé
            if not skip_images and not any(output_dir.glob("cover.*")):
                ci  = (al_data or {}).get("coverImage") or {}
                url = ci.get("extraLarge") or ci.get("large")
                if url:
                    ext = Path(urllib.parse.urlparse(url).path).suffix or ".jpg"
                    if download_file(url, output_dir / f"cover{ext}"):
                        ok(f"  [AL]    cover{ext} (AniList fallback)")
                    else:
                        warn("  [IMG]   Aucune cover disponible (TMDB + AniList)")

            return True
        warn(f"TMDB : aucun résultat pour {name!r} → tentative AniList…")

    # ── 2. Fallback AniList ───────────────────────────────────────────────────
    result = anilist_search(name, adult=adult)
    if not result:
        warn(f"AniList : aucun résultat pour {name!r}")
        return False

    if not json_existed or force:
        al  = result
        ci  = al.get("coverImage") or {}
        avg = al.get("averageScore") or 0

        al_cast = []
        for edge in (al.get("characters") or {}).get("edges", []):
            char = edge.get("node") or {}
            vas  = edge.get("voiceActors") or []
            va   = vas[0] if vas else None
            al_cast.append({k: v for k, v in {
                "name":        ((va  or {}).get("name") or {}).get("full") or ((char.get("name") or {}).get("full")),
                "character":   (char.get("name") or {}).get("full"),
                "profile_url": (char.get("image") or {}).get("large") or (char.get("image") or {}).get("medium"),
                "role":        edge.get("role"),
            }.items() if v})

        al_meta = {
            "v":              2,
            "media_type":     "anime",
            "title":          (al.get("title") or {}).get("english") or (al.get("title") or {}).get("romaji"),
            "title_romaji":   (al.get("title") or {}).get("romaji"),
            "title_original": (al.get("title") or {}).get("native"),
            "overview":       al.get("description"),
            "status":         al.get("status"),
            "year":           al.get("seasonYear") or (al.get("startDate") or {}).get("year"),
            "genres":         al.get("genres") or None,
            "score":          round(avg / 10, 1) if avg else None,
            "popularity":     al.get("popularity"),
            "format":         al.get("format"),
            "episodes_total": al.get("episodes"),
            "cover":          ci.get("extraLarge") or ci.get("large"),
            "banner":         al.get("bannerImage"),
            "studio":         ((al.get("studios") or {}).get("nodes") or [{}])[0].get("name"),
            "anilist_id":     al.get("id"),
            "cast":           al_cast or None,
        }
        metadata_path.write_text(
            json.dumps({k: v for k, v in al_meta.items() if v is not None}, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        hide_file(metadata_path)
        ok(f"  [AL]    metadata.json {'mis à jour' if json_existed else 'créé'}")

    if not skip_images and not cover_exists:
        ci  = result.get("coverImage") or {}
        url = ci.get("extraLarge") or ci.get("large")
        if url:
            ext = Path(urllib.parse.urlparse(url).path).suffix or ".jpg"
            if download_file(url, output_dir / f"cover{ext}"):
                ok(f"  [IMG]   cover{ext} enregistrée")

    return True


def scan_anime(root: Path, api_key: Optional[str], force: bool, adult: bool = False) -> None:
    subdirs = _list_subdirs(root)
    if not subdirs:
        return
    src_hint = "TMDB → AniList" if api_key else "AniList"
    info(f"Scan Anime ({src_hint})  —  {len(subdirs)} dossier(s) dans {root}\n")

    def step(i: int, total: int, folder: Path):
        raw_name, has_title = _read_title(folder)
        raw_no_year, year   = parse_title_year(raw_name)
        name   = clean_name(raw_no_year)
        suffix = f"  (.title: {raw_name!r})" if has_title else ""
        print(f"[{i:>3}/{total}] {folder.name}{suffix}" + (f" [{year}]" if year else ""), flush=True)
        effective_force = force and not (folder / ".no-force").exists()
        if effective_force != force:
            warn("         [!] .no-force présent — --force ignoré pour ce dossier.")
        if not effective_force and (folder / "metadata.json").exists() and any(folder.glob("cover.*")):
            print("         --  Déjà complet, ignoré.", flush=True)
            return None
        return process_anime(name, folder, api_key, effective_force, year=year, adult=adult) or False

    _scan_loop(subdirs, "Anime", DELAY_ANILIST, step)

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
    """Récupère en fr-FR, complète les champs vides/japonais avec en-US puis les traductions."""
    fr = tmdb_get(endpoint, api_key, {"language": "fr-FR"})
    en = tmdb_get(endpoint, api_key, {"language": "en-US"})
    _fill_from_en(fr, en, ("title", "name", "overview", "tagline"))

    # Si le titre est encore japonais, chercher dans /translations
    title_field = "name" if "name" in fr else "title"
    if _has_cjk(fr.get(title_field) or ""):
        media_id = fr.get("id")
        if media_id:
            is_tv = "name" in fr
            trans_ep = f"/{'tv' if is_tv else 'movie'}/{media_id}/translations"
            try:
                trans = tmdb_get(trans_ep, api_key, {})
                for t in trans.get("translations", []):
                    if t.get("iso_639_1") == "en":
                        en_title = t.get("data", {}).get("name") or t.get("data", {}).get("title")
                        if en_title:
                            fr[title_field] = en_title
                        break
            except Exception:
                pass

    return fr


def fetch_images(media_id: int, media_type: str, api_key: str, adult: bool = False) -> dict:
    params: dict = {"include_image_language": "fr,en,null"}
    raw = tmdb_get(_media_ep(media_id, media_type, "/images"), api_key, params)
    # Si aucun poster (fréquent pour contenu adulte), retenter sans filtre de langue
    if adult and not raw.get("posters"):
        raw = tmdb_get(_media_ep(media_id, media_type, "/images"), api_key, {})

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
        "posters":   enrich(pick_best(raw.get("posters",   [])),       ["w185", "w342", "w500", "original"]),
        "backdrops": enrich(pick_best(raw.get("backdrops", [])),       ["w300", "w780", "w1280", "original"]),
        "logos":     enrich(pick_best(raw.get("logos",     []), "en"), ["w185", "w300", "w500", "original"]),
    }


# ── Helpers affichage ─────────────────────────────────────────────────────────

def _name(r: dict) -> str: return r.get("title") or r.get("name") or "?"
def _year(r: dict) -> str:
    d = r.get("release_date") or r.get("first_air_date") or ""
    return d[:4] if d else "????"
def _label(t: str) -> str: return "Série TV" if t == "tv" else "Film"


# ── Recherche & sélection ─────────────────────────────────────────────────────

def search_tmdb(
    title: str,
    api_key: str,
    forced_type: Optional[str],
    auto: bool = False,
    year: Optional[int] = None,
    adult: bool = False,
) -> Optional[Tuple[dict, str]]:
    candidates = []
    for lang in ("fr-FR", "en-US", "ja-JP"):
        for mtype in ([forced_type] if forced_type else ["movie", "tv"]):
            ep     = "/search/tv" if mtype == "tv" else "/search/movie"
            params: dict = {"query": title, "language": lang}
            if year:
                params["first_air_date_year" if mtype == "tv" else "primary_release_year"] = year
            if adult:
                params["include_adult"] = "true"
            data = tmdb_get(ep, api_key, params)
            for r in data.get("results", []):
                r["_mtype"] = mtype
                candidates.append(r)
        if candidates:
            break  # fr-FR a trouvé → pas besoin de en-US

    if not candidates:
        return None

    title_lc = title.strip().lower()

    def score(r: dict) -> tuple:
        n = _name(r).strip().lower()
        o = (r.get("original_title") or r.get("original_name") or "").strip().lower()
        return (n == title_lc or o == title_lc, n.startswith(title_lc) or o.startswith(title_lc), r.get("popularity", 0))

    candidates.sort(key=score, reverse=True)
    top = candidates[:8]

    # ── Mode automatique (scan) ──
    if auto:
        exact = [r for r in top if score(r)[0]]

        if len(exact) <= 1:
            chosen = top[0]
            print(f"    → {_name(chosen)} ({_year(chosen)}) [{_label(chosen['_mtype'])}]")
            return chosen, chosen["_mtype"]

        warn(f"Ambiguïté pour {title!r} :")
        for i, r in enumerate(exact, 1):
            print(f"    {i}. [{_label(r['_mtype']):8}] {_name(r)} ({_year(r)}) "
                  f"— * {r.get('vote_average', 0):.1f}  pop: {r.get('popularity', 0):.1f}")

        fallback = max(exact, key=lambda r: r.get("vote_average", 0))
        print(f"  [?] Choix automatique dans 15 s → {_name(fallback)} ({_year(fallback)})")
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
              f"— * {r.get('vote_average', 0):.1f}  pop: {r.get('popularity', 0):.1f}")

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
        print(f"  ↓  Cover   → {dest.name}")
        if download_file(url, dest):
            downloaded["cover"] = str(dest)

    if images["backdrops"]:
        best = images["backdrops"][0]
        downloaded["banner"] = best["urls"].get("w1280") or best["urls"][COVER_SIZE]
        print(f"  ~> Banner  → (URL stockée dans metadata.json)")

    if images["logos"]:
        print(f"  #  Logo(s) → {len(images['logos'])} disponible(s) (stocké dans metadata.json)")

    return downloaded


# ── Crédits (cast / crew) ─────────────────────────────────────────────────────

def fetch_credits(media_id: int, media_type: str, api_key: str) -> dict:
    raw  = tmdb_get(_media_ep(media_id, media_type, "/credits"), api_key, {"language": "fr-FR"})
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


def fetch_release_dates(movie_id: int, api_key: str, countries: tuple = ("FR", "US")) -> Optional[str]:
    """Retourne la date de sortie digitale (VOD, type 4) pour le premier pays disponible."""
    raw = tmdb_get(f"/movie/{movie_id}/release_dates", api_key)
    by_country = {r["iso_3166_1"]: r["release_dates"] for r in raw.get("results", [])}
    for country in countries:
        for entry in by_country.get(country, []):
            if entry.get("type") == 4 and entry.get("release_date"):
                return entry["release_date"][:10]  # garder seulement YYYY-MM-DD
    return None


# ── Traitement d'un titre TMDB ────────────────────────────────────────────────

def process_tmdb(
    title: str,
    output_dir: Path,
    api_key: str,
    forced_type: Optional[str],
    auto: bool = False,
    force: bool = False,
    year: Optional[int] = None,
    adult: bool = False,
    skip_images: bool = False,
) -> bool:
    metadata_path = output_dir / "metadata.json"
    json_existed  = metadata_path.exists()

    if not force and json_existed:
        return True  # skip silencieux

    tmdb_id_file = output_dir / ".tmdb_id"
    if tmdb_id_file.exists():
        forced_id = int(tmdb_id_file.read_text(encoding="utf-8").strip())
        ok(f"  [TMDB]  ID forcé depuis .tmdb_id : {forced_id}")
        media_type = forced_type or "movie"
        found = ({"id": forced_id}, media_type)
    else:
        found = search_tmdb(title, api_key, forced_type, auto=auto, year=year, adult=adult)
    if not found:
        warn(f"Aucun résultat TMDB pour {title!r}")
        return False

    result, media_type = found
    media_id = result["id"]
    details  = fetch_with_fallback(_media_ep(media_id, media_type), api_key)
    images   = fetch_images(media_id, media_type, api_key, adult=adult)
    if not skip_images:
        covers = download_covers_tmdb(images, output_dir)
    credits  = fetch_credits(media_id, media_type, api_key)

    if credits:
        ok(f"  [CAST]  {len(credits.get('cast', []))} acteur(s) récupéré(s)")
    payload = _lean_meta(media_type, details, images, credits, [])
    if media_type == "movie":
        vod_date = fetch_release_dates(media_id, api_key)
        if vod_date:
            payload["release_date_vod"] = vod_date
            ok(f"  [VOD]   sortie digitale : {vod_date}")
        col_id = payload.get("collection_id")
        if col_id:
            try:
                col_data = tmdb_get(f"/collection/{col_id}", api_key, {"language": "fr-FR"})
                parts = []
                for p in col_data.get("parts") or []:
                    parts.append({
                        "tmdb_id":      p.get("id"),
                        "title":        p.get("title"),
                        "release_date": p.get("release_date"),
                        "poster_path":  p.get("poster_path"),
                    })
                payload["collection_parts"] = parts
                ok(f"  [COL]   {col_data.get('name')} — {len(parts)} film(s)")
            except Exception:
                pass
    metadata_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    hide_file(metadata_path)
    ok(f"  [JSON]  metadata.json {'mis à jour' if json_existed else 'créé'}")
    return True


def scan_tmdb(root: Path, api_key: str, forced_type: Optional[str], force: bool, adult: bool = False) -> None:
    subdirs = _list_subdirs(root)
    if not subdirs:
        return
    type_lbl = _label(forced_type or "movie")
    info(f"Scan {type_lbl} (TMDB)  —  {len(subdirs)} dossier(s) dans {root}\n")

    def step(i: int, total: int, folder: Path):
        raw_title, has_title = _read_title(folder)
        title, year = parse_title_year(raw_title)
        suffix = f"  (.title: {raw_title!r})" if has_title else ""
        print(f"[{i:>3}/{total}] {folder.name}{suffix}" + (f" [{year}]" if year else ""), flush=True)
        if not force and (folder / "metadata.json").exists():
            print("         --  metadata.json déjà présent, ignoré.", flush=True)
            return None
        return process_tmdb(title, folder, api_key, forced_type, auto=True, force=force, year=year, adult=adult) or False

    _scan_loop(subdirs, type_lbl, DELAY_TMDB, step)

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
    """Récupère les épisodes d'une saison en FR + EN (fallback sur les champs vides)."""
    endpoint = f"/tv/{series_id}/season/{season_num}"
    fr = tmdb_get(endpoint, api_key, {"language": "fr-FR"})
    en = tmdb_get(endpoint, api_key, {"language": "en-US"})

    _fill_from_en(fr, en, ("name", "overview"))

    en_eps = {e["episode_number"]: e for e in en.get("episodes", [])}
    enriched = []
    for ep in fr.get("episodes", []):
        ep_num = ep["episode_number"]
        if ep_num < ep_start:
            continue
        if ep_end is not None and ep_num > ep_end:
            continue
        _fill_from_en(ep, en_eps.get(ep_num, {}), ("name", "overview"))
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
    return sum(1 for f in season_dir.iterdir()
               if f.is_file() and f.suffix.lower() in VIDEO_EXT)


def auto_build_season_map(
    series_dir: Path,
    series_id: int,
    api_key: str,
    season_dirs: list,
    tmdb_season_count: int,
) -> dict:
    """Génère automatiquement le season-map quand TMDB a moins de saisons que les dossiers locaux."""
    info(f"  TMDB : {tmdb_season_count} saison(s) pour {len(season_dirs)} dossier(s) local/-aux")
    info("  → Génération automatique du .season-map.json…")

    all_episodes: list[tuple[int, int]] = []
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

    ok(f"  Season map calculé ({len(season_map)} entrée(s)) — sera intégré dans metadata.json")
    return season_map


def process_series_seasons(series_dir: Path, api_key: str, force: bool) -> Tuple[int, int, int]:
    """Fetch les saisons d'une série et les embed dans metadata.json. Retourne (done, skipped, failed)."""
    metadata_path = series_dir / "metadata.json"
    if not metadata_path.exists():
        warn(f"  {series_dir.name} : pas de metadata.json à la racine, ignoré.")
        return 0, 0, 0

    try:
        meta = json.loads(metadata_path.read_text(encoding="utf-8"))
        if meta.get("media_type") != "tv":
            warn(f"  {series_dir.name} : pas une série TV (media_type={meta.get('media_type')!r}), ignoré.")
            return 0, 0, 0
        series_id: int = meta.get("tmdb_id") or meta.get("details", {}).get("id")
        if not series_id:
            warn(f"  {series_dir.name} : pas de tmdb_id dans metadata.json, ignoré.")
            return 0, 0, 0
    except Exception as e:
        warn(f"  {series_dir.name} : metadata.json illisible ({e}), ignoré.")
        return 0, 0, 0

    # ── Chargement du mapping (clé season_map dans metadata ou .season-map.json) ─
    season_map: dict = meta.get("season_map", {})
    season_map_file = series_dir / ".season-map.json"
    if not season_map and season_map_file.exists():
        try:
            season_map = json.loads(season_map_file.read_text(encoding="utf-8"))
            info(f"  .season-map.json chargé ({len(season_map)} entrée(s))")
        except Exception as e:
            warn(f"  .season-map.json illisible : {e}")

    tmdb_season_count = meta.get("seasons_count") or meta.get("details", {}).get("number_of_seasons", 0)

    def season_order(name: str) -> int:
        num = parse_season_number(name)
        if num is not None:
            return num
        entry = season_map.get(name, {})
        if "number" in entry:
            return int(entry["number"])
        keys = list(season_map.keys())
        return keys.index(name) + 1 if name in keys else 99999

    real_dirs = sorted(
        (d for d in series_dir.iterdir() if d.is_dir() and (
            parse_season_number(d.name) is not None or d.name in season_map
        )),
        key=lambda d: season_order(d.name),
    )

    if real_dirs:
        season_entries: list[tuple[str, Optional[Path]]] = [(d.name, d) for d in real_dirs]
        # Ajouter les saisons TMDB absentes localement
        if tmdb_season_count:
            covered_tmdb: set[int] = set()
            for folder_name, _ in season_entries:
                local_num = parse_season_number(folder_name)
                if local_num is None:
                    entry = season_map.get(folder_name, {})
                    local_num = int(entry["number"]) if "number" in entry else season_order(folder_name)
                mapping = season_map.get(folder_name) or season_map.get(f"Season {local_num}") or {}
                covered_tmdb.add(int(mapping.get("tmdb_season", local_num)))
            for n in range(1, tmdb_season_count + 1):
                if n not in covered_tmdb:
                    info(f"  {series_dir.name} : saison {n} absente en local, synthétisée depuis TMDB.")
                    season_entries.append((f"Season {n:02d}", None))
    elif tmdb_season_count:
        # Pas de dossier local du tout : synthétiser toutes les saisons
        info(f"  {series_dir.name} : aucun dossier local, {tmdb_season_count} saison(s) synthétisée(s) depuis metadata.")
        season_entries = [(f"Season {n:02d}", None) for n in range(1, tmdb_season_count + 1)]
    else:
        warn(f"  {series_dir.name} : aucun dossier Season N/ et seasons_count inconnu, ignoré.")
        return 0, 0, 0

    # ── Auto-génération du season-map si TMDB a moins de saisons ─────────────
    if not season_map and tmdb_season_count > 0 and len(season_entries) > tmdb_season_count:
        real_season_dirs = [d for _, d in season_entries if d is not None]
        if real_season_dirs:
            season_map = auto_build_season_map(
                series_dir, series_id, api_key, real_season_dirs, tmdb_season_count
            )

    if force and (series_dir / ".no-force").exists():
        warn(f"  [!] .no-force présent dans {series_dir.name} — --force ignoré pour les saisons.")
        force = False

    existing_seasons: dict = meta.get("seasons", {})
    tmdb_cache: dict[int, dict] = {}
    done = skipped = failed = 0

    for folder_name, real_dir in season_entries:
        local_num = parse_season_number(folder_name)
        if local_num is None:
            entry = season_map.get(folder_name, {})
            local_num = int(entry["number"]) if "number" in entry else season_order(folder_name)
        display_name = folder_name if parse_season_number(folder_name) is None else f"Season {local_num:>2}"

        mapping  = season_map.get(folder_name) or season_map.get(f"Season {local_num}") or {}
        tmdb_num = int(mapping.get("tmdb_season", local_num))
        ep_start = int(mapping.get("ep_start", 1))
        ep_end   = int(mapping["ep_end"]) if "ep_end" in mapping else None

        map_tag = ""
        if tmdb_num != local_num or ep_start > 1 or ep_end is not None:
            ep_range = f" ep {ep_start}–{ep_end or '∞'}" if (ep_start > 1 or ep_end) else ""
            map_tag = f" [→ TMDB S{tmdb_num}{ep_range}]"

        # ── Créer le dossier s'il n'existe pas encore ───────────────────────
        if real_dir is None:
            new_dir = series_dir / folder_name
            if not new_dir.exists():
                new_dir.mkdir()
                ok(f"    {display_name}  [+] dossier créé : {new_dir}")

        # ── Skip : déjà dans le metadata v2 ─────────────────────────────────
        if not force:
            folder_eps = existing_seasons.get(folder_name, {}).get("episodes")
            if folder_eps is not None:
                print(f"    {display_name}  --  déjà présente ({len(folder_eps)} éps), ignorée.{map_tag}")
                skipped += 1
                continue
            # Migration legacy : Season N/metadata.json → root metadata.json (dossier réel uniquement)
            if real_dir is not None:
                season_meta_legacy = real_dir / "metadata.json"
                if season_meta_legacy.exists():
                    try:
                        old      = json.loads(season_meta_legacy.read_text(encoding="utf-8"))
                        old_eps  = old.get("episodes", [])
                        if old_eps:
                            lean     = [_lean_episode(e) for e in old_eps]
                            s_entry: dict = {
                                "tmdb_season": old.get("_tmdb_season", tmdb_num),
                                "episodes":    lean,
                            }
                            ep_range_old = old.get("_episode_range")
                            if ep_range_old:
                                s_entry["ep_start"] = ep_range_old[0]
                                s_entry["ep_end"]   = ep_range_old[1]
                            existing_seasons[folder_name] = s_entry
                            ok(f"    {display_name}  ✓  {len(lean)} éps migré(s) depuis Season N/metadata.json")
                            skipped += 1
                            continue
                    except Exception:
                        pass  # fall through to API fetch

        print(f"    {display_name}  ↓  téléchargement TMDB S{tmdb_num}…{map_tag}")
        try:
            if tmdb_num not in tmdb_cache:
                tmdb_cache[tmdb_num] = fetch_season_data(series_id, tmdb_num, api_key)

            raw = tmdb_cache[tmdb_num]
            if ep_start > 1 or ep_end is not None:
                episodes = [
                    e for e in raw.get("episodes", [])
                    if ep_start <= e.get("episode_number", 0) <= (ep_end or 99_999)
                ]
            else:
                episodes = raw.get("episodes", [])

            lean_eps = [_lean_episode(e) for e in episodes]
            s_entry = {"tmdb_season": tmdb_num, "episodes": lean_eps}
            if ep_start > 1:
                s_entry["ep_start"] = ep_start
            if ep_end is not None:
                s_entry["ep_end"] = ep_end
            existing_seasons[folder_name] = s_entry

            ok(f"    {display_name}  ✓  {len(lean_eps)} épisode(s)")
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

    # ── Écriture du root metadata.json avec seasons + season_map embarqués ────
    real_folder_names = {folder_name for folder_name, real_dir in season_entries if real_dir is not None}
    seen_sig: dict[tuple, str] = {}
    deduped: dict = {}
    for key, val in existing_seasons.items():
        sig = (val.get("tmdb_season", 0), val.get("ep_start"), val.get("ep_end"))
        if sig not in seen_sig:
            seen_sig[sig] = key
            deduped[key] = val
        else:
            prev_key = seen_sig[sig]
            prefer_new = key in real_folder_names and prev_key not in real_folder_names
            if prefer_new:
                del deduped[prev_key]
                seen_sig[sig] = key
                deduped[key] = val
                warn(f"  Doublon {sig} : {prev_key!r} remplacé par {key!r} (dossier réel)")
            else:
                warn(f"  Doublon {sig} : {key!r} ignoré (gardé : {prev_key!r})")

    if season_map:
        meta["season_map"] = season_map
    meta["seasons"] = deduped
    try:
        metadata_path.write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")
        hide_file(metadata_path)
    except Exception as e:
        warn(f"  Impossible d'écrire {metadata_path.name} : {e}")

    return done, skipped, failed


def scan_seasons(root: Path, api_key: str, force: bool) -> None:
    if (root / "metadata.json").exists():
        series_dirs = [root]
        info(f"Saisons (TMDB)  —  série unique : {root.name}\n")
    else:
        series_dirs = sorted(d for d in root.iterdir() if d.is_dir())
        info(f"Saisons (TMDB)  —  {len(series_dirs)} série(s) dans {root}\n")

    total_done = total_skipped = total_failed = 0
    for series_dir in series_dirs:
        print(f"[dir] {series_dir.name}")
        d, s, f = process_series_seasons(series_dir, api_key, force)
        total_done += d; total_skipped += s; total_failed += f
        print()

    sep()
    print(f"✓ Saisons  —  {total_done} traité(s)  |  {total_skipped} ignoré(s)  |  {total_failed} échoué(s)\n")


# ══════════════════════════════════════════════════════════════════════════════
#  --seasons  : refresh épisodes/saisons uniquement
# ══════════════════════════════════════════════════════════════════════════════

def refresh_seasons(root: Path, api_key: str) -> None:
    """Reforce le fetch des épisodes pour toutes les saisons (force=True)."""
    scan_seasons(root, api_key, force=True)


# ══════════════════════════════════════════════════════════════════════════════
#  --update   : mise à jour des champs volatils (status, score, overview…)
# ══════════════════════════════════════════════════════════════════════════════

_UPDATE_FIELDS_TV    = ("status", "vote_average", "overview", "number_of_episodes", "number_of_seasons", "tagline")
_UPDATE_FIELDS_MOVIE = ("status", "vote_average", "overview", "runtime", "tagline")

_PROTECTED_KEYS = ("seasons", "cast", "logos", "images", "banner", "cover",
                   "tmdb_id", "title", "title_original", "year", "format",
                   "media_type", "v", "release_date_vod", "popularity",
                   "studio", "genres", "episodes")

def update_meta_fields(media_dir: Path, api_key: str, is_anime: bool, adult: bool) -> bool:
    """Met à jour les champs volatils d'un metadata.json sans toucher au cast ni aux saisons."""
    metadata_path = media_dir / "metadata.json"
    if not metadata_path.exists():
        warn(f"  {media_dir.name} : pas de metadata.json, ignoré.")
        return False

    try:
        original_text = metadata_path.read_text(encoding="utf-8")
        meta = json.loads(original_text)
    except Exception as e:
        warn(f"  {media_dir.name} : metadata.json illisible ({e}), ignoré.")
        return False

    # Snapshot des clés protégées — restaurées inconditionnellement après écriture
    protected_snapshot = {k: meta[k] for k in _PROTECTED_KEYS if k in meta}

    changed = False
    tmdb_id    = meta.get("tmdb_id")
    media_type = meta.get("media_type", "tv")
    is_tv      = media_type == "tv"

    # ── Mise à jour TMDB ──────────────────────────────────────────────────────
    if tmdb_id and api_key:
        try:
            details = fetch_with_fallback(f"/{'tv' if is_tv else 'movie'}/{tmdb_id}", api_key)
            fields  = _UPDATE_FIELDS_TV if is_tv else _UPDATE_FIELDS_MOVIE

            mapping = {
                "status":             ("status",         lambda v: v),
                "vote_average":       ("score",          lambda v: round(float(v), 1) if v else None),
                "overview":           ("overview",       lambda v: v if v else None),
                "number_of_episodes": ("episodes_total", lambda v: int(v) if v else None),
                "number_of_seasons":  ("seasons_count",  lambda v: int(v) if v else None),
                "runtime":            ("runtime",        lambda v: int(v) if v else None),
                "tagline":            ("tagline",        lambda v: v or None),
            }

            for tmdb_key in fields:
                local_key = mapping[tmdb_key][0]
                if local_key in _PROTECTED_KEYS:
                    continue
                val = details.get(tmdb_key)
                if val is None:
                    continue
                meta_key, transform = mapping[tmdb_key]
                new_val = transform(val)
                if new_val is not None and meta.get(meta_key) != new_val:
                    old = meta.get(meta_key)
                    meta[meta_key] = new_val
                    ok(f"  {meta_key}: {old!r} → {new_val!r}")
                    changed = True

            time.sleep(DELAY_TMDB)
        except Exception as e:
            warn(f"  TMDB update échoué : {e}")

    # ── Mise à jour AniList (score uniquement, genres déjà protégés) ──────────
    if is_anime:
        al = anilist_search(clean_name(media_dir.name), adult=adult)
        if al:
            if not tmdb_id:
                avg = al.get("averageScore")
                new_score = round(avg / 10, 1) if avg else None
                if new_score and meta.get("score") != new_score:
                    meta["score"] = new_score
                    ok(f"  score (AniList): {new_score}")
                    changed = True
                al_status = al.get("status")
                if al_status and meta.get("status") != al_status:
                    meta["status"] = al_status
                    ok(f"  status (AniList): {al_status}")
                    changed = True
            time.sleep(DELAY_ANILIST)

    if changed:
        # Restaurer les clés protégées avant d'écrire
        meta.update(protected_snapshot)
        metadata_path.write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")
        hide_file(metadata_path)
        ok(f"  metadata.json mis à jour")
    else:
        info(f"  Aucun changement détecté.")

    return True


def scan_update(root: Path, api_key: str, is_anime: bool, adult: bool) -> None:
    """Parcourt tous les sous-dossiers et met à jour les champs volatils."""
    if (root / "metadata.json").exists():
        dirs = [root]
    else:
        dirs = sorted(d for d in root.iterdir() if d.is_dir())

    info(f"Update  —  {len(dirs)} élément(s) dans {root}\n")
    for i, d in enumerate(dirs, 1):
        print(f"[{i:>3}/{len(dirs)}] {d.name}")
        update_meta_fields(d, api_key, is_anime=is_anime, adult=adult)
        print()

    sep()
    print(f"✓ Update terminé — {len(dirs)} élément(s) traité(s)\n")


# ══════════════════════════════════════════════════════════════════════════════
#  --cover-only : re-télécharge uniquement la cover depuis metadata.json
# ══════════════════════════════════════════════════════════════════════════════

def _download_cover_for_dir(media_dir: Path, force: bool) -> bool:
    meta_path = media_dir / "metadata.json"
    if not meta_path.exists():
        warn(f"  {media_dir.name} : pas de metadata.json, ignoré.")
        return False

    try:
        meta = json.loads(meta_path.read_text(encoding="utf-8"))
    except Exception as e:
        warn(f"  {media_dir.name} : JSON invalide — {e}")
        return False

    url = meta.get("cover") or meta.get("images", {}).get("posters", [{}])[0].get("urls", {}).get("w500")
    if not url:
        warn(f"  {media_dir.name} : aucune URL cover dans metadata.json")
        return False

    # Détermine l'extension depuis l'URL
    ext = Path(urllib.parse.urlparse(url).path).suffix or ".jpg"
    dest = media_dir / f"cover{ext}"

    existing = list(media_dir.glob("cover.*"))
    if existing and not force:
        info(f"  {media_dir.name} : cover déjà présente ({existing[0].name}), ignoré (--force pour écraser)")
        return False

    # Supprime l'ancienne cover si on force
    if force:
        for old in existing:
            old.unlink(missing_ok=True)

    if download_file(url, dest):
        ok(f"  {media_dir.name} : cover téléchargée → {dest.name}")
        return True
    return False


def scan_cover_only(root: Path, force: bool) -> None:
    if (root / "metadata.json").exists():
        dirs = [root]
    else:
        dirs = sorted(d for d in root.iterdir() if d.is_dir())

    info(f"Cover-only  —  {len(dirs)} élément(s) dans {root}\n")
    done = skipped = 0
    for media_dir in dirs:
        if not media_dir.is_dir():
            continue
        result = _download_cover_for_dir(media_dir, force=force)
        if result:
            done += 1
        else:
            skipped += 1

    sep()
    print(f"✓ Cover-only  —  {done} téléchargée(s)  |  {skipped} ignorée(s)\n")


# ══════════════════════════════════════════════════════════════════════════════
#  FORCE SCAN — helpers pour préserver les IDs existants
# ══════════════════════════════════════════════════════════════════════════════

def _prepare_force_scan(root: Path) -> dict:
    """Lit les tmdb_id dans chaque metadata.json et écrit les .tmdb_id temporaires."""
    injected: dict[str, int] = {}
    for folder in root.iterdir():
        if not folder.is_dir():
            continue
        meta_path = folder / "metadata.json"
        if not meta_path.exists():
            continue
        try:
            meta    = json.loads(meta_path.read_text(encoding="utf-8"))
            tmdb_id = meta.get("tmdb_id")
            if tmdb_id:
                (folder / ".tmdb_id").write_text(str(tmdb_id), encoding="utf-8")
                injected[folder.name] = int(tmdb_id)
        except Exception:
            pass
    return injected


def _cleanup_force_scan(root: Path, injected: dict) -> None:
    """Supprime les .tmdb_id temporaires créés par _prepare_force_scan."""
    for name in injected:
        (root / name / ".tmdb_id").unlink(missing_ok=True)


# ══════════════════════════════════════════════════════════════════════════════
#  MAIN
# ══════════════════════════════════════════════════════════════════════════════

def main() -> None:
    parser = argparse.ArgumentParser(
        prog="fetch_metadata.py",
        description="HOSHIMI — Fetch métadonnées depuis AniList (anime) ou TMDB (film/série).",
        epilog=(
            "Exemples :\n"
            "  python fetch_metadata.py --type anime  --scan /media/animes\n"
            "  python fetch_metadata.py --type film   --scan /media/films\n"
            "  python fetch_metadata.py --type serie  --scan /media/series\n"
            "  python fetch_metadata.py --type anime  --dir /media/animes/\"Attack on Titan\"\n"
            "  python fetch_metadata.py --type anime  --force /media/animes\n"
            "  python fetch_metadata.py --type film   --update /media/films\n"
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )

    parser.add_argument("--type", "-t", required=True,
                        choices=["anime", "film", "serie"],
                        help="Type de média")

    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--scan",    metavar="DIR",
                      help="Scan batch — skip si metadata.json déjà présent")
    mode.add_argument("--dir",     metavar="DIR",
                      help="Fetch d'une seule oeuvre (conserve les images existantes)")
    mode.add_argument("--force",   metavar="DIR",
                      help="Rescan forcé — réutilise les IDs existants, re-télécharge les images")
    mode.add_argument("--update",  metavar="DIR",
                      help="Màj champs volatils uniquement (status, score, overview…)")
    mode.add_argument("--seasons",    metavar="DIR",
                      help="Refresh épisodes/saisons uniquement")
    mode.add_argument("--cover-only", metavar="DIR",
                      help="Re-télécharge uniquement la cover depuis l'URL dans metadata.json")

    parser.add_argument("--tmdb-id", type=int, default=None, metavar="ID",
                        help="Force l'ID TMDB (uniquement avec --dir)")
    parser.add_argument("--cover-force", action="store_true",
                        help="Avec --cover-only : écrase les covers déjà présentes")
    parser.add_argument("--api-key", default=None,
                        help="Clé API TMDB (obligatoire pour film/serie, optionnelle pour anime)")

    args = parser.parse_args()

    is_anime    = args.type == "anime"
    adult       = False
    forced_type = "tv" if args.type == "serie" else ("movie" if args.type == "film" else None)

    if is_anime:
        api_key = _find_api_key(args.api_key)
        if api_key:
            info("Clé TMDB trouvée — stratégie : TMDB d'abord, AniList en fallback.")
        else:
            warn("Clé TMDB absente — fallback AniList uniquement.")
    else:
        api_key = load_api_key(args.api_key)

    # ── --scan : scan batch (skip existants) ──────────────────────────────────
    if args.scan:
        root = Path(args.scan)
        if not root.is_dir():
            err(f"Dossier introuvable : {root}"); sys.exit(1)
        if is_anime:
            scan_anime(root, api_key=api_key, force=False, adult=adult)
            sep(); info("Lancement automatique du scan des saisons…\n")
            scan_seasons(root, api_key, force=False)
        elif args.type == "serie":
            scan_tmdb(root, api_key, forced_type, force=False, adult=adult)
            sep(); info("Lancement automatique du scan des saisons…\n")
            scan_seasons(root, api_key, force=False)
        else:
            scan_tmdb(root, api_key, forced_type, force=False, adult=adult)
        clear_php_cache(); return

    # ── --dir : fetch d'une oeuvre précise (sans re-télécharger les images) ──
    if args.dir:
        target = Path(args.dir)
        if not target.is_dir():
            err(f"Dossier introuvable : {target}"); sys.exit(1)

        tmdb_id_file = target / ".tmdb_id"
        if args.tmdb_id is not None:
            tmdb_id_file.write_text(str(args.tmdb_id), encoding="utf-8")
            ok(f"[TMDB] ID épinglé dans .tmdb_id : {args.tmdb_id}")

        raw_title, has_title = _read_title(target)
        raw_no_year, year    = parse_title_year(raw_title)

        print(f"\nFetch précis : {raw_title!r} [{args.type}] …\n")

        if is_anime:
            success = process_anime(
                clean_name(raw_no_year), target, api_key,
                force=True, year=year, adult=adult, skip_images=True,
            )
        else:
            success = process_tmdb(
                raw_no_year, target, api_key, forced_type,
                auto=False, force=True, year=year, adult=adult, skip_images=True,
            )

        if success and args.type in ("serie", "anime"):
            sep(); info("Lancement du scan des saisons…\n")
            scan_seasons(target, api_key, force=True)

        if success:
            print(f"\n✓  Terminé → {target / 'metadata.json'}\n")
        else:
            sys.exit(1)
        clear_php_cache(); return

    # ── --force : rescan forcé (réutilise les IDs, re-télécharge les images) ─
    if args.force:
        root = Path(args.force)
        if not root.is_dir():
            err(f"Dossier introuvable : {root}"); sys.exit(1)
        injected = _prepare_force_scan(root)
        if injected:
            info(f"{len(injected)} ID(s) TMDB préservé(s) depuis les metadata.json existants.")
        try:
            if is_anime:
                scan_anime(root, api_key=api_key, force=True, adult=adult)
                sep(); info("Lancement automatique du scan des saisons…\n")
                scan_seasons(root, api_key, force=True)
            elif args.type == "serie":
                scan_tmdb(root, api_key, forced_type, force=True, adult=adult)
                sep(); info("Lancement automatique du scan des saisons…\n")
                scan_seasons(root, api_key, force=True)
            else:
                scan_tmdb(root, api_key, forced_type, force=True, adult=adult)
        finally:
            _cleanup_force_scan(root, injected)
        clear_php_cache(); return

    # ── --update : màj champs volatils ────────────────────────────────────────
    if args.update:
        target = Path(args.update)
        if not target.is_dir():
            err(f"Dossier introuvable : {target}"); sys.exit(1)
        scan_update(target, api_key, is_anime=is_anime, adult=adult)
        clear_php_cache(); return

    # ── --seasons : refresh épisodes/saisons ──────────────────────────────────
    if args.seasons:
        target = Path(args.seasons)
        if not target.is_dir():
            err(f"Dossier introuvable : {target}"); sys.exit(1)
        refresh_seasons(target, api_key)
        clear_php_cache(); return

    # ── --cover-only : re-télécharge uniquement la cover ─────────────────────
    if args.cover_only:
        target = Path(args.cover_only)
        if not target.is_dir():
            err(f"Dossier introuvable : {target}"); sys.exit(1)
        force_cover = args.cover_force
        scan_cover_only(target, force=force_cover)
        clear_php_cache(); return


def clear_php_cache() -> None:
    pattern = os.path.join(tempfile.gettempdir(), 'hoshimi_scanner_*.json')
    removed = 0
    for f in _glob.glob(pattern):
        try:
            os.unlink(f)
            removed += 1
        except OSError:
            pass
    if removed:
        print(f"\n[cache] {removed} fichier(s) de cache PHP supprimé(s).")


if __name__ == "__main__":
    main()
