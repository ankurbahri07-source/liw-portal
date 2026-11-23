<?php
// Live numbers for the home-page tiles. The portal asks each app (server to server, signed) and caches 60 s.
require __DIR__ . '/inc/boot.php';
$u = me();
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!$u) { http_response_code(401); echo '{}'; exit; }
$acc = access_for($u);
$base = (string)(cfg()['public_url'] ?? ((is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '')));
$cache = __DIR__ . '/storage/summary_cache.json';
$cached = is_file($cache) ? (json_decode((string)file_get_contents($cache), true) ?: []) : [];
$out = []; $mh = curl_multi_init(); $hs = [];
foreach ($acc as $k => $_) {
    if (!empty($cached[$k]) && time() - $cached[$k]['t'] < 60) { $out[$k] = $cached[$k]['d']; continue; }
    try { $ts = time(); $sig = summary_sig($k, $ts); } catch (Throwable $e) { $out[$k] = null; continue; }
    $ch = curl_init($base . apps()[$k]['summary'] . (str_contains(apps()[$k]['summary'], '?') ? '&' : '?') . 'ts=' . $ts . '&sig=' . $sig);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false]);
    curl_multi_add_handle($mh, $ch); $hs[$k] = $ch;
}
do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1); } while ($run && $st === CURLM_OK);
foreach ($hs as $k => $ch) {
    $j = json_decode((string)curl_multi_getcontent($ch), true);
    $out[$k] = (is_array($j) && isset($j['chips'])) ? ['chips' => array_slice(array_map(fn($c) => ['text' => (string)($c['text'] ?? ''), 'tone' => in_array($c['tone'] ?? '', ['ok', 'bad', 'warn'], true) ? $c['tone'] : ''], $j['chips']), 0, 5)] : null;
    if ($out[$k]) $cached[$k] = ['t' => time(), 'd' => $out[$k]];
    curl_multi_remove_handle($mh, $ch); curl_close($ch);
}
curl_multi_close($mh);
@file_put_contents($cache, json_encode($cached), LOCK_EX);
echo json_encode(['apps' => $out]);
