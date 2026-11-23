<?php
declare(strict_types=1);

function sess_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    $dir = __DIR__ . '/../storage/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 14));
    session_name('liw_sid');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 14, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    session_start();
}
function me(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    sess_start();
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $r = row('SELECT * FROM portal_users WHERE id=? AND active=1', [(int)$_SESSION['uid']]);
        if ($r && (int)($_SESSION['sv'] ?? -1) === (int)$r['sv']) $u = $r; else $_SESSION = [];
    }
    return $u;
}
function need_login(): array
{
    $u = me();
    if (!$u) redirect(url('login.php'));
    if ((int)$u['must_change'] && basename($_SERVER['SCRIPT_NAME']) !== 'account.php') redirect(url('account.php?first=1'));
    return $u;
}
function need_owner(): array
{
    $u = need_login();
    if (!(int)$u['is_owner']) { http_response_code(403); exit('Owner only.'); }
    return $u;
}
function csrf(): string { sess_start(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . esc(csrf()) . '">'; }
function check_csrf(): void
{
    sess_start();
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Session expired. Go back and reload the page.'); }
}
function login_blocked(string $username): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    return (int)val('SELECT COUNT(*) FROM portal_login_attempts WHERE username=? AND ip=? AND created_at>?', [$username, client_ip(), $since]) >= 8;
}
function password_ok(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    return null;
}
