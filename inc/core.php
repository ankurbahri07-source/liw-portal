<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/../config.php';
        $c = is_file($f) ? (require $f) : [];
    }
    return $c;
}
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg();
    if (!$c) throw new RuntimeException('config.php is missing on the server.');
    if (($c['db_driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['db_path']);
    } else {
        $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass']);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r === false ? null : $r[0]; }
function insert(string $t, array $d): int
{
    q("INSERT INTO $t (" . implode(',', array_keys($d)) . ') VALUES (' . implode(',', array_fill(0, count($d), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}
function now_str(): string { return date('Y-m-d H:i:s'); }
function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function base_path(): string { return rtrim((string)(cfg()['base_path'] ?? ''), '/'); }
function url(string $p = ''): string { return base_path() . '/' . ltrim($p, '/'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function setting(string $k, string $d = ''): string { $v = val('SELECT v FROM portal_settings WHERE k=?', [$k]); return $v === null ? $d : (string)$v; }
function set_setting(string $k, string $v): void
{
    if (val('SELECT COUNT(*) FROM portal_settings WHERE k=?', [$k])) q('UPDATE portal_settings SET v=? WHERE k=?', [$v, $k]);
    else insert('portal_settings', ['k' => $k, 'v' => $v]);
}
function audit(?int $actor, string $action, string $detail = ''): void
{
    insert('portal_audit', ['actor_id' => $actor, 'action' => $action, 'detail' => mb_substr($detail, 0, 500), 'ip' => client_ip(), 'created_at' => now_str()]);
}
