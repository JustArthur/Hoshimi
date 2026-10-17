<?php

declare(strict_types=1);

final class MediaProbe
{
    // ffprobe renvoie des codes ISO 639-2 (3 lettres) ; on normalise vers
    // le format 2 lettres utilisé partout ailleurs dans l'app (VTT, labels).
    private const LANG_ALIASES = [
        'fre' => 'fr', 'fra' => 'fr',
        'eng' => 'en',
        'jpn' => 'ja',
        'spa' => 'es',
        'ger' => 'de', 'deu' => 'de',
        'ita' => 'it',
        'por' => 'pt',
    ];

    public static function normalizeLang(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        $code = strtolower($code);
        return self::LANG_ALIASES[$code] ?? $code;
    }

    /**
     * Liste les pistes audio d'un fichier média via ffprobe.
     * Chaque entrée fournit deux index distincts :
     * - relativeIndex : position parmi les pistes audio seules (0..N-1),
     *   celui attendu par ffmpeg `-map 0:a:N` (stream/index.php)
     * - absoluteIndex : position globale dans le conteneur (vidéo+audio+subs),
     *   celui attendu par l'API Jellyfin `AudioStreamIndex` (stream/jellyfin.php)
     */
    public static function probeAudioTracks(string $path): array
    {
        $cmd = 'ffprobe -v error -select_streams a'
             . ' -show_entries stream=index:stream_tags=language,title'
             . ' -of json ' . escapeshellarg($path) . ' 2>/dev/null';

        $output = shell_exec($cmd);
        $data   = $output ? json_decode($output, true) : null;
        $streams = $data['streams'] ?? [];

        $tracks = [];
        foreach (array_values($streams) as $i => $stream) {
            $tracks[] = [
                'relativeIndex' => $i,
                'absoluteIndex' => (int) ($stream['index'] ?? $i),
                'lang'          => $stream['tags']['language'] ?? null,
                'title'         => $stream['tags']['title'] ?? null,
            ];
        }

        return $tracks;
    }
}
