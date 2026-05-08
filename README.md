# 🎌 Hoshimi (星見)

Hoshimi est une interface web légère et moderne conçue pour transformer votre dossier d'animes local en une plateforme de streaming personnelle. Le système repose sur un scan dynamique de vos fichiers et une intégration avec l'API AniList.

**Zéro SQL :** Tout fonctionne via le système de fichiers et les métadonnées JSON.

---

## 🚀 Fonctionnalités

- **Scan Dynamique** : Analyse instantanée de vos dossiers, saisons et épisodes.
- **Métadonnées AniList** : Récupération des synopsis, scores, genres et bannières.
- **Lecteur Premium** : Intégration de **Video.js** pour une expérience de lecture fluide (MP4 pour le moment).
- **Génération de Thumbnails** : Création automatique d'aperçus pour chaque épisode via FFmpeg.
- **Recommandations** : Affichage d'animes similaires basés sur vos fichiers locaux.

---

## 📋 Prérequis

- **Windows 10/11** avec WSL2 activé.
- **Docker Desktop** configuré pour démarrer avec Windows.
- Un dossier contenant vos médias (par défaut : `Y:\Animes`).

---

## ⚙️ Installation & Configuration

### 1. Variables d'environnement
Créez un fichier `.env` à la racine en vous basant sur `.env.example` :
```bash
# API Keys
ANILIST_API_URL=[https://graphql.anilist.co](https://graphql.anilist.co)

# Chemins internes (ne pas modifier sauf besoin spécifique)
ANIMES_PATH=/media/animes
IMAGES_PATH=/var/www/html/public/images
```

### 2. Configuration du dossier Source
Dans votre `docker-compose.yml`, assurez-vous que le chemin vers votre disque d'animes est correct :
```yaml
volumes:
  animes_data:
    driver_opts:
      device: "Y:\\Animes"  # 👈 Modifiez la lettre si nécessaire
```

### 3. Lancement
Ouvrez un terminal dans le dossier du projet :
```bash
docker compose up -d --build
```
L'application est accessible sur : **http://localhost:8080**

---

## 🎞️ Structure des Médias

Pour que le scanner identifie correctement vos contenus, suivez cette structure :
```
Y:\Animes\
├── Nom de l'Anime\
│   ├── cover.jpg               # Image de couverture
│   ├── metadata.json           # Données AniList (si présentes)
│   ├── Season 01\
│   │   ├── Anime S01E01 1080p VOSTFR.mp4
│   │   └── Anime S01E02 1080p VOSTFR.mp4
└── ...
```

---

## 🖼️ Génération des Miniatures (Thumbnails)

Le projet utilise FFmpeg pour générer des aperçus d'épisodes. Pour lancer la génération sur vos nouveaux fichiers, utilisez la commande suivante :

```bash
docker compose exec php php /var/www/html/cli/generate-thumbnails.php
```
*Note : Les miniatures sont stockées dans `src/public/images/thumbnails/`.*

---

## 🛠️ Maintenance & Commandes Utiles

| Action | Commande |
| :--- | :--- |
| **Démarrer Hoshimi** | `docker compose up -d` |
| **Arrêter Hoshimi** | `docker compose down` |
| **Reconstruire (Maj)** | `docker compose up -d --build` |
| **Scanner les métadonnées** | `docker compose exec php php /var/www/html/cli/fetch-metadata.php` |
| **Voir les erreurs** | `docker compose logs -f php` |

---

## 🌐 Accès Réseau Local

Pour regarder vos animes sur votre TV ou tablette :
1. Récupérez l'IP de votre PC (`ipconfig` dans un terminal).
2. Connectez-vous sur `http://VOTRE_IP:8080`.

---
*Développé avec ❤️ pour les fans d'animation.*