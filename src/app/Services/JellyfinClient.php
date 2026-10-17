<?php
// ============================================================
//  JellyfinClient — Wrapper HTTP minimal pour l'API Jellyfin
//  Utilisé uniquement comme moteur de transcodage/streaming
//  (mapping fichier → item, construction d'URL HLS)
// ============================================================

declare(strict_types=1);

class JellyfinClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? ($_ENV['JELLYFIN_URL'] ?? ''), '/');
        $this->apiKey  = $apiKey ?? ($_ENV['JELLYFIN_API_KEY'] ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    // ----------------------------------------------------------------
    //  Mapping catalogue — chemin fichier → ItemId Jellyfin
    //  Retourne un tableau [path_absolu => item_id]
    // ----------------------------------------------------------------
    public function getPathToItemIdMap(): array
    {
        $map = [];

        if (!$this->isConfigured()) {
            return $map;
        }

        $response = $this->request('GET', '/Items', [
            'Recursive'      => 'true',
            'IncludeItemTypes' => 'Movie,Episode',
            'Fields'         => 'Path',
        ]);

        if ($response === null || empty($response['Items'])) {
            return $map;
        }

        foreach ($response['Items'] as $item) {
            if (!empty($item['Path']) && !empty($item['Id'])) {
                $map[$item['Path']] = $item['Id'];
            }
        }

        return $map;
    }

    // ----------------------------------------------------------------
    //  Id de la bibliothèque Jellyfin dont le dossier racine correspond
    //  au chemin donné (ex: /media/films → Id de la bibliothèque "Films")
    // ----------------------------------------------------------------
    public function getLibraryIdForPath(string $path): ?string
    {
        $response = $this->request('GET', '/Library/VirtualFolders');
        if (!is_array($response)) {
            return null;
        }

        foreach ($response as $lib) {
            foreach ($lib['Locations'] ?? [] as $location) {
                if (rtrim($location, '/') === rtrim($path, '/')) {
                    return $lib['ItemId'] ?? null;
                }
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    //  Déclenche un scan ciblé (asynchrone côté Jellyfin) d'une seule
    //  bibliothèque — bien plus rapide qu'un scan de tout le serveur.
    // ----------------------------------------------------------------
    public function refreshLibrary(string $libraryId): bool
    {
        $url = $this->baseUrl . '/Items/' . rawurlencode($libraryId) . '/Refresh'
            . '?' . http_build_query([
                'Recursive'           => 'true',
                'ImageRefreshMode'    => 'None',
                'MetadataRefreshMode' => 'Default',
            ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-Emby-Token: ' . $this->apiKey],
            CURLOPT_TIMEOUT        => 10,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode > 0 && $httpCode < 400;
    }

    // ----------------------------------------------------------------
    //  Id du premier utilisateur (instance mono-utilisateur / admin)
    // ----------------------------------------------------------------
    private function getUserId(): ?string
    {
        $response = $this->request('GET', '/Users');
        return $response[0]['Id'] ?? null;
    }

    // ----------------------------------------------------------------
    //  Ouvre une session de lecture pour un ItemId et retourne l'URL
    //  HLS relative (déjà réglée par Jellyfin : bitrate, codecs, etc.)
    //  DirectPlayProfiles volontairement vide : on force Jellyfin à
    //  toujours passer par le pipeline de transcodage HLS (même pour
    //  du H.264 déjà compatible, où il fera un simple "copy" de flux,
    //  quasi gratuit en CPU). Nécessaire car le mimetype de la balise
    //  <source> est fixé côté player/index.php avant l'appel Jellyfin :
    //  on a besoin d'un seul type de réponse (HLS) de façon uniforme.
    // ----------------------------------------------------------------
    public function getTranscodingUrl(string $itemId, ?int $audioStreamIndex = null): ?string
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return null;
        }

        $deviceProfile = [
            'DirectPlayProfiles' => [],
            'TranscodingProfiles' => [
                [
                    'Container'        => 'ts',
                    'Type'             => 'Video',
                    'VideoCodec'       => 'h264',
                    'AudioCodec'       => 'aac',
                    'Protocol'         => 'hls',
                    'MaxAudioChannels' => '2',
                ],
            ],
        ];

        $body = ['DeviceProfile' => $deviceProfile];
        if ($audioStreamIndex !== null) {
            // MediaSourceId est requis en plus de AudioStreamIndex, sinon Jellyfin
            // ignore silencieusement la piste demandée et reprend la piste par défaut.
            $body['MediaSourceId']      = $itemId;
            $body['AudioStreamIndex']   = $audioStreamIndex;
        }

        $response = $this->request(
            'POST',
            '/Items/' . rawurlencode($itemId) . '/PlaybackInfo',
            ['UserId' => $userId],
            $body
        );

        $url = $response['MediaSources'][0]['TranscodingUrl'] ?? null;
        return $url !== null ? $this->disableSubtitles($url) : null;
    }

    // ----------------------------------------------------------------
    //  Désactive la sélection auto de sous-titres de Jellyfin (héritée
    //  des préférences utilisateur, pas du DeviceProfile) — Hoshimi
    //  garde son propre système VTT, on ne veut jamais de burn-in.
    // ----------------------------------------------------------------
    private function disableSubtitles(string $url): string
    {
        $url = preg_replace('/([?&])SubtitleStreamIndex=[^&]*/', '$1SubtitleStreamIndex=-1', $url) ?? $url;
        $url = preg_replace('/[?&]SubtitleMethod=[^&]*/', '', $url) ?? $url;
        return $url;
    }

    // ----------------------------------------------------------------
    //  Requête HTTP interne
    // ----------------------------------------------------------------
    private function request(string $method, string $path, array $query = [], ?array $jsonBody = null): ?array
    {
        $url = $this->baseUrl . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        $headers = ['X-Emby-Token: ' . $this->apiKey];

        $options = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ];

        if ($jsonBody !== null) {
            $headers[]                 = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode >= 400) {
            return null;
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
