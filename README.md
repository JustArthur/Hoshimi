# 🎌 Hoshimi (星見)

<div align="center">

**Une médiathèque personnelle Zero-DB pour vos animes, séries et films locaux**

Transformez vos dossiers vidéo en une plateforme de streaming élégante et moderne.  
Pas de base de données, pas de complexité — juste vos fichiers et une interface soignée.

[Installation](#-installation-rapide) • [Fonctionnalités](#-fonctionnalités) • [Configuration](#%EF%B8%8F-configuration) • [FAQ](#-faq)

</div>

---

## ✨ Fonctionnalités

### 📚 Bibliothèque Intelligente
- **Scanner automatique** — détecte vos saisons, épisodes et fichiers sans configuration
- **Triple catalogue** — pages dédiées Animes, Séries et Films avec filtres indépendants
- **Métadonnées enrichies** — synopsis, scores, genres, studios depuis AniList + TMDB
- **Genres AniList** — genres précis pour les animes (Action, Mecha, Isekai…) via AniList GraphQL
- **Genres TMDB** — genres pour séries et films, avec décomposition des genres composés (ex. "Action & Adventure" → deux genres distincts)
- **Miniatures d'épisodes** — aperçus visuels générés via FFmpeg pour chaque épisode
- **Multi-format** — supporte MP4, MKV, WebM, AVI avec extraction automatique des métadonnées

### 🎬 Lecteur Vidéo Avancé
- **Video.js** — lecteur HTML5 fluide et responsive (`fluid: true`)
- **Reprise de lecture** — reprend exactement là où vous vous êtes arrêté
- **Épisode suivant automatique** — countdown de 10s à la fin d'un épisode
- **Raccourcis clavier** — Espace (play/pause), J/L (±10s), F (plein écran), M (mute), T (mode théâtre)
- **Playlist dynamique** — navigation rapide entre les épisodes d'une saison
- **Suggestions intelligentes** — 10 titres similaires calculés par similarité Jaccard, proximité de note et d'année

### 🎨 Interface Moderne
- **Sidebar de filtres** — filtres latéraux style ADN (genre, année, tri) sur toutes les pages catalogue, avec drawer mobile
- **Design adaptatif** — responsive mobile, tablette et desktop avec breakpoints cohérents à 960px / 768px / 480px
- **Couleurs dynamiques** — accent personnalisé par anime (extrait depuis AniList)
- **Mode sombre natif** — interface sombre optimisée pour les longues sessions
- **Recherche en direct** — filtrage instantané par titre sans rechargement
- **Pagination** — 20 titres par page sur les catalogues
- **Progression visuelle** — barres de progression sur chaque carte épisode

### 💾 Stockage Local (Zero-DB)
- **Favoris** — système de favoris géré via localStorage du navigateur
- **Listes personnalisées** — créez vos propres collections ("À voir", "En cours", etc.)
- **Historique de visionnage** — sauvegarde automatique de votre progression
- **Aucune base de données** — tout fonctionne avec vos fichiers locaux

### 🚀 Performances
- **Docker optimisé** — consommation RAM/CPU minimale (< 200 MB au repos)
- **Cache intelligent** — métadonnées mises en cache pour éviter les requêtes API
- **Streaming efficace** — support des range requests pour le seek instantané
- **Pages d'erreur custom** — page 404 intégrée, URLs invalides redirigées correctement

---

## 🎯 Pourquoi Hoshimi ?

| Critère | Hoshimi | Plex/Jellyfin |
|---------|---------|---------------|
| **Installation** | 5 min avec Docker | 30+ min + configuration |
| **Base de données** | ❌ Aucune | ✅ PostgreSQL/SQLite |
| **RAM utilisée** | ~150 MB | 500 MB - 2 GB |
| **Métadonnées** | AniList + TMDB (anime-first) | TMDB/TVDB (généraliste) |
| **Transcoding** | ❌ Lecture directe | ✅ (gourmand en CPU) |
| **Interface** | Moderne, épurée | Complète, complexe |
| **Idéal pour** | Collection anime/série/film locale | Médiathèque complète |

---

## 📋 Prérequis

- **Windows 10/11** avec WSL2 activé ([guide Microsoft](https://learn.microsoft.com/fr-fr/windows/wsl/install))
- **Docker Desktop** ([télécharger](https://www.docker.com/products/docker-desktop/))
- **PowerShell 5.1+** (inclus dans Windows)
- **Dossier de médias** organisé en saisons/épisodes

---

## 🚀 Installation Rapide

### 1️⃣ Télécharger le projet
```bash
git clone https://github.com/JustArthur/Hoshimi.git
cd Hoshimi
```

### 2️⃣ Configuration initiale
```bash
cp .env.example .env
notepad .env
```

### 3️⃣ Pointer vers vos médias
Éditez `docker-compose.yml` :
```yaml
volumes:
  animes_data:
    driver_opts:
      device: "Y:\\Animes"   # ← Votre dossier d'animes
  series_data:
    driver_opts:
      device: "Y:\\Series"   # ← Votre dossier de séries
  films_data:
    driver_opts:
      device: "Y:\\Films"    # ← Votre dossier de films
```

### 4️⃣ Démarrage
```bash
docker compose up -d
```

**🎉 C'est tout !** Ouvrez `http://localhost:8080`

---

## 📂 Structure des Fichiers Attendue

### Animes
```
Y:\Animes\
├── One Piece\
│   ├── Season 01\
│   │   ├── One Piece S01E01 720p VOSTFR.mp4
│   │   └── ...
│   ├── Season 02\
│   └── metadata.json        # ← Généré automatiquement
│
└── Attack on Titan\
    ├── Season 00\            # ← Films / OAV
    ├── Season 01\
    └── metadata.json
```

### Séries & Films
Même structure — un dossier par titre, saisons numérotées.

### 📝 Règles de Nommage

#### ✅ Formats supportés
- `Anime S01E01 720p VOSTFR.mp4`
- `Anime - 01 - Titre [1080p].mp4`
- `[Fansub] Anime - 01 (720p).mp4`

#### 🏷️ Tags détectés automatiquement
| Tag | Affiché dans | Exemple |
|-----|--------------|---------|
| **Qualité** | Badge épisode | `720p`, `1080p`, `4K` |
| **Langue** | Badge couleur | `VOSTFR`, `VF`, `MULTI`, `VO` |
| **Fansub** | Ignoré | `[Team]`, `(Group)` |

#### 🎬 Saisons spéciales
- **Season 00** → Films / OAV / Spéciaux
- **Season 01, 02…** → Saisons normales

---

## ⚙️ Configuration Avancée

### 🔄 Synchronisation des Métadonnées

```bash
# Animes — récupère TMDB + AniList (genres précis)
python src/cli/fetch_metadata.py --media anime --scan Y:/Animes

# Forcer la re-synchronisation même si déjà présent
python src/cli/fetch_metadata.py --media anime --scan Y:/Animes --force

# Séries / Films
python src/cli/fetch_metadata.py --media serie --scan Y:/Series
python src/cli/fetch_metadata.py --media film  --scan Y:/Films

# Vider le cache PHP après une synchronisation
docker exec hoshimi_php sh -c "rm -f /tmp/hoshimi_scanner_*.json"
```

### 🖼️ Génération des Miniatures

```bash
# Générer toutes les miniatures
docker compose exec php php /var/www/html/cli/generate-thumbnails.php

# Forcer la régénération
docker compose exec php php /var/www/html/cli/generate-thumbnails.php --force

# Capturer à 20% au lieu de 30%
docker compose exec php php /var/www/html/cli/generate-thumbnails.php --at=20
```

---

## 🛠️ Maintenance & Commandes

| Action | Commande |
|--------|----------|
| **Démarrer** | `docker compose up -d` |
| **Arrêter** | `docker compose down` |
| **Redémarrer** | `docker compose restart` |
| **Voir les logs** | `docker compose logs -f` |
| **Logs PHP uniquement** | `docker compose logs -f php` |
| **Rebuilder (après MAJ)** | `docker compose up -d --build` |
| **Accès shell PHP** | `docker compose exec php sh` |
| **Vider cache scanner** | `docker exec hoshimi_php sh -c "rm -f /tmp/hoshimi_scanner_*.json"` |

---

## 🔐 Résolution des Problèmes

### ❌ Permission Denied sur Windows

1. Clic droit sur votre dossier médias → Propriétés → Sécurité
2. Modifier → Ajouter → "Tout le monde" → OK
3. Cochez "Contrôle total" → Appliquer

### ❌ "No compatible source was found for this media"

```bash
# Vérifie que Docker voit tes fichiers
docker compose exec php ls /media/animes/

# Teste un fichier précis
docker compose exec php ffprobe "/media/animes/One Piece/Season 01/..."
```

### ❌ Les miniatures ne se génèrent pas

1. FFmpeg installé : `docker compose exec php ffmpeg -version`
2. Permissions : `docker compose exec php ls -la /var/www/html/public/images/thumbnails/`

### ❌ Genres insuffisants pour les animes

Relancez le script avec `--force` pour récupérer les genres AniList :
```bash
python src/cli/fetch_metadata.py --media anime --scan Y:/Animes --force
docker exec hoshimi_php sh -c "rm -f /tmp/hoshimi_scanner_*.json"
```

---

## 📱 Accès Multi-Appareils

### Sur le Réseau Local

```bash
ipconfig  # Cherchez "Adresse IPv4" → ex: 192.168.1.42
```
Depuis mobile/tablette : `http://192.168.1.42:8080`

### Sur Internet (Avancé)

⚠️ **Non recommandé sans VPN** — exposerait vos fichiers en ligne.

- **Tailscale** (VPN mesh gratuit)
- **Cloudflare Tunnel** (gratuit, aucun port forwarding)

---

## 🔑 Clés API

### AniList
**Gratuit • Aucune clé requise**  
L'API GraphQL d'AniList ne nécessite pas d'authentification pour la lecture.

### TMDB
**Gratuit • Clé requise**  
Créez un compte sur [themoviedb.org](https://www.themoviedb.org/settings/api) et ajoutez dans `.env` → `TMDB_API_KEY=...`

---

## 📊 Spécifications Techniques

| Composant | Technologie | Version |
|-----------|-------------|---------|
| **Frontend** | HTML5, CSS3, JavaScript | Native |
| **Backend** | PHP | 8.3 |
| **Serveur Web** | Nginx | 1.25 |
| **Lecteur Vidéo** | Video.js (fluid/responsive) | 8.10 |
| **Métadonnées** | FFmpeg, FFprobe | 8.0 |
| **APIs** | AniList GraphQL, TMDB REST | — |
| **Container** | Docker, Alpine Linux | — |

---

## 🤝 Contribution

1. Fork le projet
2. Créez une branche (`git checkout -b feature/amelioration`)
3. Commit (`git commit -m 'Ajout fonctionnalité X'`)
4. Push (`git push origin feature/amelioration`)
5. Ouvrez une Pull Request

---

## 📄 Licence

Ce projet est sous licence MIT. Voir `LICENSE` pour plus de détails.

---

## ❓ FAQ

**Q : Hoshimi supporte-t-il les films et séries ?**  
R : Oui — trois catalogues distincts : Animes, Séries et Films, chacun avec ses propres filtres et métadonnées.

**Q : Faut-il une connexion internet ?**  
R : Seulement pour la synchronisation des métadonnées. Le streaming local fonctionne hors ligne.

**Q : Puis-je utiliser Hoshimi sur Linux/macOS ?**  
R : Oui via Docker. Les chemins dans `docker-compose.yml` sont à adapter (format Unix).

**Q : Les sous-titres externes sont-ils supportés ?**  
R : Pas pour le moment.

**Q : Quelle est la consommation réseau ?**  
R : ~10-50 MB pour les métadonnées initiales. Le streaming est 100% local (0 MB internet).

**Q : Pourquoi mes genres animes sont-ils peu nombreux ?**  
R : Relancez `fetch_metadata.py --force` pour enrichir avec les genres AniList, puis videz le cache PHP.

---

<div align="center">

**Développé avec ❤️ pour la communauté anime**

[⬆ Retour en haut](#-hoshimi-星見)

</div>
