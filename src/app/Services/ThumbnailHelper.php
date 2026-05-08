<?php
// ============================================================
//  HOSHIMI — ThumbnailHelper
//  Retourne l'URL d'une miniature générée par FFmpeg
//  ou null si elle n'existe pas encore
// ============================================================

declare(strict_types=1);

class ThumbnailHelper
{
    private static string $thumbDir  = '';
    private static string $thumbBase = '/images/thumbnails/';
    private static ?array $index     = null;

    // ----------------------------------------------------------------
    //  Retourne l'URL de la miniature d'un épisode
    //  $filePath = chemin absolu du fichier vidéo
    // ----------------------------------------------------------------
    public static function getUrl(string $filePath): ?string
    {
        $key      = md5($filePath);
        $thumbDir = self::getThumbDir();
        $file     = $thumbDir . '/' . $key . '.jpg';

        return file_exists($file)
            ? self::$thumbBase . $key . '.jpg'
            : null;
    }

    // ----------------------------------------------------------------
    //  Retourne l'URL ou un placeholder si la miniature n'existe pas
    // ----------------------------------------------------------------
    public static function getUrlOrPlaceholder(string $filePath): ?string
    {
        return self::getUrl($filePath);
        // null = pas de miniature → afficher le placeholder CSS
    }

    // ----------------------------------------------------------------
    //  Vérifie si une miniature existe
    // ----------------------------------------------------------------
    public static function exists(string $filePath): bool
    {
        return self::getUrl($filePath) !== null;
    }

    // ----------------------------------------------------------------
    //  Charge l'index JSON pour accès groupé (évite les file_exists)
    // ----------------------------------------------------------------
    public static function loadIndex(): void
    {
        $indexFile = self::getThumbDir() . '/index.json';
        if (file_exists($indexFile)) {
            self::$index = json_decode(file_get_contents($indexFile), true) ?? [];
        } else {
            self::$index = [];
        }
    }

    public static function getUrlFromIndex(string $filePath): ?string
    {
        if (self::$index === null) {
            self::loadIndex();
        }
        $key = md5($filePath);
        return self::$index[$key] ?? null;
    }

    // ----------------------------------------------------------------
    private static function getThumbDir(): string
    {
        if (self::$thumbDir === '') {
            $base = $_ENV['IMAGES_PATH'] ?? '/var/www/html/public/images';
            self::$thumbDir = rtrim($base, '/') . '/thumbnails';
        }
        return self::$thumbDir;
    }
}
