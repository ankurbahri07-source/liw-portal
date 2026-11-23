<?php
declare(strict_types=1);

function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/')); }

function sso_secret(string $app): string
{
    $s = (string)(cfg()['sso_secrets'][$app] ?? '');
    if (strlen($s) < 32) throw new RuntimeException("sso secret for '$app' missing or too short in config.php");
    return $s;
}
/** Short-lived signed pass the portal hands to one app. Valid 45 seconds. */
function sso_token(array $u, string $app, string $role, array $perms): string
{
    $payload = b64u(json_encode([
        'v' => 1, 'app' => $app, 'uid' => (int)$u['id'], 'u' => $u['username'], 'n' => $u['name'], 'm' => (string)($u['mobile'] ?? ''),
        'r' => $role, 'p' => implode(',', $perms), 'iat' => time(), 'exp' => time() + 45, 'j' => bin2hex(random_bytes(8)),
    ]));
    return $payload . '.' . b64u(hash_hmac('sha256', $payload, sso_secret($app), true));
}
/** Signature for portal -> app server calls (tile stats). */
function summary_sig(string $app, int $ts): string { return hash_hmac('sha256', "summary|$app|$ts", sso_secret($app)); }
