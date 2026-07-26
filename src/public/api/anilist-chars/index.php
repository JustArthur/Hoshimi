<?php
// Endpoint désactivé — les appels AniList se font directement côté client (CORS supporté).
http_response_code(410);
echo json_encode(['error' => 'Unused — client-side fetch']);
