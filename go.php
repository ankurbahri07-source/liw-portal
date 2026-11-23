<?php
// Hands the signed-in person to one app with a 45-second signed pass.
require __DIR__ . '/inc/boot.php';
$u = need_login();
$k = (string)($_GET['app'] ?? '');
$acc = access_for($u);
if (!isset(apps()[$k]) || !isset($acc[$k])) { http_response_code(403); exit('You do not have access to this app.'); }
$app = apps()[$k];
$tok = sso_token($u, $k, $acc[$k]['role'], $acc[$k]['perms']);
audit((int)$u['id'], 'open_app', $k);
header('Cache-Control: no-store');
redirect(url(ltrim($app['path'], '/')) . '?sso=' . rawurlencode($tok));
