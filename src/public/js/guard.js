(function () {
    const KEY = 'hoshimi_access';
    const token = localStorage.getItem(KEY);

    if (!token) return;

    const hasCookie = document.cookie.split(';').some(c => c.trim().startsWith(KEY + '='));
    if (hasCookie) return;

    if (sessionStorage.getItem('guard_reloaded')) return;
    sessionStorage.setItem('guard_reloaded', '1');

    const expires = new Date(Date.now() + 10 * 365 * 24 * 3600 * 1000).toUTCString();
    document.cookie = KEY + '=' + token + '; path=/; expires=' + expires + '; SameSite=Lax';
    location.reload();
})();
