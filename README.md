# 🎌 Hoshimi (星見)

<div align="center">

**Une médiathèque personnelle Zero-DB pour vos animes locaux**

Transformez vos dossiers d'animes en une plateforme de streaming élégante et moderne.  
Pas de base de données, pas de complexité — juste vos fichiers et une interface soignée.

[Installation](#-installation-rapide) • [Fonctionnalités](#-fonctionnalités) • [Configuration](#%EF%B8%8F-configuration) • [FAQ](#-faq)

</div>

---

## ✨ Fonctionnalités

### 📚 Bibliothèque Intelligente
- **Scanner automatique** — détecte vos saisons, épisodes et fichiers sans configuration
- **Métadonnées AniList** — synopsis, scores, genres, studios et bannières synchronisés automatiquement
- **Miniatures d'épisodes** — aperçus visuels générés via FFmpeg pour chaque épisode
- **Multi-format** — supporte MP4, MKV, WebM, AVI avec extraction automatique des métadonnées

### 🎬 Lecteur Vidéo Avancé
- **Video.js** — lecteur HTML5 fluide avec contrôles tactiles
- **Reprise de lecture** — reprend exactement là où vous vous êtes arrêté
- **Épisode suivant automatique** — countdown de 10s à la fin d'un épisode
- **Raccourcis clavier** — Espace (play/pause), J/L (±10s), F (plein écran), M (mute)
- **Playlist dynamique** — navigation rapide entre les épisodes d'une saison

### 🎨 Interface Moderne
- **Design adaptatif** — responsive mobile, tablette et desktop
- **Couleurs dynamiques** — accent personnalisé par anime (extrait depuis AniList)
- **Mode sombre natif** — interface sombre optimisée pour les longues sessions
- **Recherche et filtres** — par genre, année, studio, statut
- **Progression visuelle** — barres de progression sur chaque carte anime

### 💾 Stockage Local (Zero-DB)
- **Favoris** — système de favoris géré via localStorage du navigateur
- **Listes personnalisées** — créez vos propres collections ("À voir", "En cours", etc.)
- **Historique de visionnage** — sauvegarde automatique de votre progression
- **Aucune base de données** — tout fonctionne avec vos fichiers locaux

### 🚀 Performances
- **Docker optimisé** — consommation RAM/CPU minimale (< 200 MB au repos)
- **Cache intelligent** — métadonnées mises en cache pour éviter les requêtes API
- **Streaming efficace** — support des range requests pour le seek instantané

---

## 🎯 Pourquoi Hoshimi ?

| Critère | Hoshimi | Plex/Jellyfin |
|---------|---------|---------------|
| **Installation** | 5 min avec Docker | 30+ min + configuration |
| **Base de données** | ❌ Aucune | ✅ PostgreSQL/SQLite |
| **RAM utilisée** | ~150 MB | 500 MB - 2 GB |
| **Métadonnées** | AniList (spécialisé anime) | TMDB/TVDB (généraliste) |
| **Transcoding** | ❌ Lecture directe | ✅ (gourmand en CPU) |
| **Interface** | Moderne, épurée | Complète, complexe |
| **Idéal pour** | Collection anime locale | Médiathèque complète (films/séries) |

---

## 📋 Prérequis

- **Windows 10/11** avec WSL2 activé ([guide Microsoft](https://learn.microsoft.com/fr-fr/windows/wsl/install))
- **Docker Desktop** ([télécharger](https://www.docker.com/products/docker-desktop/))
- **PowerShell 5.1+** (inclus dans Windows)
- **Dossier d'animes** organisé en saisons/épisodes

---

## 🚀 Installation Rapide

### 1️⃣ Télécharger le projet
```bash
git clone https://github.com/JustArthur/Hoshimi.git
cd Hoshimi
```

### 2️⃣ Configuration initiale
```bash
# Copie le fichier de configuration
cp .env.example .env

# Édite le .env avec ton éditeur préféré
notepad .env
```

**Variables importantes à modifier :**
```env
# Client AniDB (optionnel) → https://wiki.anidb.net/HTTP_API_Definition
ANIDB_CLIENT=hoshimi
```

### 3️⃣ Pointer vers vos animes
Éditez `docker-compose.yml` ligne **~30** :
```yaml
volumes:
  animes_data:
    driver_opts:
      device: "Y:\\Animes"  # ← Remplacez par votre chemin
```

### 4️⃣ Démarrage
```bash
docker compose up -d
```

**🎉 C'est tout !** Ouvrez `http://localhost:8080`

---

## 📂 Structure des Fichiers Attendue

Pour que Hoshimi détecte correctement vos animes, organisez-les ainsi :

```
Y:\Animes\
├── One Piece\
│   ├── Season 01\
│   │   ├── One Piece S01E01 720p VOSTFR.mp4
│   │   ├── One Piece S01E02 720p VOSTFR.mp4
│   │   └── ...
│   ├── Season 02\
│   │   └── ...
│   ├── One Piece-metadata.json       # ← Généré automatiquement
│   └── One Piece-cover.jpg           # ← Téléchargé depuis AniList
│
├── Attack on Titan\
│   ├── Season 01\
│   ├── Season 02\
│   ├── Attack on Titan-metadata.json
│   └── Attack on Titan-cover.jpg
│
└── Jujutsu Kaisen\
    ├── Season 00\                     # ← Films / OAV
    │   └── Jujutsu Kaisen 0.mp4
    ├── Season 01\
    └── ...
```

### 📝 Règles de Nommage

#### ✅ Formats supportés
- `Anime S01E01 720p VOSTFR.mp4`
- `Anime - 01 - Titre [1080p].mp4`
- `[Fansub] Anime - 01 (720p).mp4`

#### 🏷️ Tags détectés automatiquement
| Tag | Affiché dans | Exemple |
|-----|--------------|---------|
| **Qualité** | Carte épisode | `720p`, `1080p`, `4K` |
| **Langue** | Badge couleur | `VOSTFR`, `VF`, `MULTI` |
| **Fansub** | Ignoré | `[Team]`, `(Group)` |

#### 🎬 Saisons spéciales
- **Season 00** → Films / OAV / Spéciaux
- **Season 01, 02...** → Saisons normales

---

## ⚙️ Configuration Avancée

### 🔄 Synchronisation des Métadonnées

Hoshimi peut récupérer automatiquement les informations depuis AniList.

**Option A : Via Docker (recommandé)**
```bash
docker compose exec php php /var/www/html/cli/fetch-metadata.php
```

**Option B : Via PowerShell (si problèmes de permissions)**
```powershell
.\Get-AnimeData.ps1 -AnimePath "Y:\Animes"
```

**Options du script PowerShell :**
```powershell
# Forcer le re-téléchargement même si déjà présent
.\Get-AnimeData.ps1 -Force

# Scanner un autre dossier
.\Get-AnimeData.ps1 -AnimePath "D:\Mes Animes"
```

### 🖼️ Génération des Miniatures

Créez des aperçus visuels pour chaque épisode :

```bash
# Générer toutes les miniatures
docker compose exec php php /var/www/html/cli/generate-thumbnails.php

# Générer pour un seul anime
docker compose exec php php /var/www/html/cli/generate-thumbnails.php --anime=One+Piece

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

---

## 🔐 Résolution des Problèmes

### ❌ Permission Denied sur Windows

**Symptôme :** Le script ne peut pas écrire les métadonnées ou thumbnails.

**Solution :**
1. Clic droit sur `Y:\Animes` → Propriétés → Sécurité
2. Modifier → Ajouter → Tapez "Tout le monde" → OK
3. Cochez "Contrôle total" → Appliquer

**Alternative :** Utilisez le script PowerShell qui hérite de vos permissions Windows.

### ❌ "No compatible source was found for this media"

**Causes possibles :**
1. Le fichier n'existe pas au chemin indiqué
2. Le volume Docker n'est pas monté correctement
3. Codec vidéo non supporté (H.265/HEVC)

**Vérification :**
```bash
# Vérifie que Docker voit tes fichiers
docker compose exec php ls /media/animes/

# Teste un fichier précis
docker compose exec php ffprobe "/media/animes/One Piece/Season 01/..."
```

### ❌ Les miniatures ne se génèrent pas

**Vérifications :**
1. FFmpeg est installé : `docker compose exec php ffmpeg -version`
2. Permissions en écriture : `docker compose exec php ls -la /var/www/html/public/images/thumbnails/`
3. Espace disque suffisant

---

## 📱 Accès Multi-Appareils

### Sur le Réseau Local

1. **Trouvez l'IP de votre PC :**
   ```bash
   ipconfig
   # Cherchez "Adresse IPv4" → ex: 192.168.1.42
   ```

2. **Depuis mobile/tablette :**
   ```
   http://192.168.1.42:8080
   ```

### Sur Internet (Avancé)

⚠️ **Non recommandé sans VPN** — exposerait vos fichiers en ligne.

Si vous souhaitez un accès externe sécurisé :
- Utilisez **Tailscale** (VPN mesh gratuit)
- Ou **Cloudflare Tunnel** (gratuit, aucun port forwarding)

---

## 🔑 Clés API

### AniList
**Gratuit • Aucune clé requise**

L'API GraphQL d'AniList ne nécessite pas d'authentification pour la lecture.

### AniDB (Optionnel)
**Gratuit • Validation manuelle**

1. Enregistrez votre client sur [anidb.net](https://anidb.net/software/add)
2. Attendez validation (1-3 jours)
3. Ajoutez dans `.env` → `ANIDB_CLIENT=hoshimi`

⚠️ **Rate-limit strict** : 1 requête toutes les 2 secondes maximum.

---

## 🎨 Personnalisation

### Couleurs d'Accent

Hoshimi extrait automatiquement la couleur dominante de chaque anime depuis AniList et l'applique à toute l'interface de détail. Aucune configuration nécessaire !

### Styles Custom

Éditez `src/public/css/hoshimi_base.css` pour personnaliser :
- Variables CSS (couleurs, typographie)
- Bordures et coins arrondis
- Espacements

---

## 📊 Spécifications Techniques

| Composant | Technologie | Version |
|-----------|-------------|---------|
| **Frontend** | HTML5, CSS3, JavaScript | Native |
| **Backend** | PHP | 8.3 |
| **Serveur Web** | Nginx | 1.25 |
| **Lecteur Vidéo** | Video.js | 8.10 |
| **Métadonnées** | FFmpeg, FFprobe | 8.0 |
| **APIs** | AniList GraphQL, TMDB REST | - |
| **Container** | Docker, Alpine Linux | - |

---

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour proposer des améliorations :

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

**Q : Hoshimi peut-il gérer des films ?**  
R : Actuellement optimisé pour les séries anime. Support films prévu dans une future version.

**Q : Faut-il une connexion internet ?**  
R : Seulement pour la synchronisation des métadonnées. Le streaming local fonctionne hors ligne.

**Q : Puis-je utiliser Hoshimi sur Linux/macOS ?**  
R : Oui via Docker, mais les scripts PowerShell nécessitent une adaptation (bash).

**Q : Les sous-titres externes sont-ils supportés ?**  
R : Pas pour le moment.

**Q : Quelle est la consommation réseau ?**  
R : ~10-50 MB pour les métadonnées initiales. Le streaming est 100% local (0 MB internet).

---

<div align="center">

**Développé avec ❤️ pour la communauté anime**

[⬆ Retour en haut](#-hoshimi-星見)

</div>