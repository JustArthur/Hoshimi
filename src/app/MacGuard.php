<?php

declare(strict_types=1);

class MacGuard
{
    private const COOKIE_NAME = 'hoshimi_access';

    private static function token(): string
    {
        return $_ENV['ACCESS_TOKEN'] ?? '';
    }

    public static function protect(): void
    {
        $token = self::token();

        // Si pas de token configuré, accès libre (dev)
        if ($token === '') {
            return;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($cookie !== '' && hash_equals($token, $cookie)) {
            return;
        }

        http_response_code(404);
        include __DIR__ . '/../public/404.php';
        exit;
    }

    public static function hasAccess(): bool
    {
        $token = self::token();
        if ($token === '') {
            return true;
        }
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        return $cookie !== '' && hash_equals($token, $cookie);
    }

    public static function unlock(): void
    {
        $token = self::token();

        // Pas de token configuré → unlock désactivé
        if ($token === '') {
            http_response_code(404);
            include __DIR__ . '/../public/404.php';
            exit;
        }

        $k = $_GET['k'] ?? '';
        if ($k === '' || !hash_equals($token, $k)) {
            http_response_code(404);
            include __DIR__ . '/../public/404.php';
            exit;
        }

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + 60 * 60 * 24 * 365 * 10,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
        echo "<!doctype html><html><body><script>
localStorage.setItem('hoshimi_access'," . json_encode($token) . ");
location.href='/';
</script></body></html>";
        exit;
    }
}
