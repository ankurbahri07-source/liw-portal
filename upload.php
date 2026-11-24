<?php
// One CRM Excel upload that updates BOTH Mailer and Ops Room. Owner only.
require __DIR__ . '/inc/boot.php';
$u = need_owner();
$secret = (string)(cfg()['sync_secret'] ?? '');
$base = (string)(cfg()['public_url'] ?? ((is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '')));
$res = null; $err = '';

function sign_headers(string $secret, string $bodyHash): array {
    $ts = time();
    return ['X-Sync-Ts: ' . $ts, 'X-Sync-Sig: ' . hash_hmac('sha256', $ts . '.' . $bodyHash, $secret)];
}
function call(string $url, $post, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HTTPHEADER => $headers]);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$r, true)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $f = $_FILES['file'] ?? null;
    if (strlen($secret) < 32) $err = 'sync_secret is not set in the portal config.php yet.';
    elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) $err = 'Choose an .xls or .xlsx file first.';
    elseif (!in_array(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), ['xls', 'xlsx'], true)) $err = 'Only .xls or .xlsx files are accepted.';
    else {
        $cf = new CURLFile($f['tmp_name'], 'application/octet-stream', $f['name']);
        [$c1, $m] = call($base . '/mailer/api.php?action=portal_upload', ['file' => $cf], sign_headers($secret, hash_file('sha256', $f['tmp_name'])));
        if ($c1 !== 200 || !is_array($m) || empty($m['ok'])) {
            $err = 'Mailer did not accept the file' . (is_array($m) && !empty($m['msg']) ? ': ' . $m['msg'] : ' (code ' . $c1 . ')') . '. Nothing was changed in Ops Room.';
        } else {
            $body = json_encode(['rows' => $m['ops_rows'] ?? []], JSON_UNESCAPED_UNICODE);
            [$c2, $o] = call($base . '/ops/api.php?action=portal_import', $body, array_merge(['Content-Type: application/json'], sign_headers($secret, hash('sha256', $body))));
            $res = ['mailer' => $m, 'ops' => ($c2 === 200 && is_array($o) && isset($o['inserted'])) ? $o : null, 'ops_code' => $c2];
            audit((int)$u['id'], 'crm_upload', $f['name'] . ' · Mailer ' . ($m['inserted'] ?? 0) . ' new/' . ($m['updated'] ?? 0) . ' upd · Ops ' . ($res['ops'] ? ($o['inserted'] . ' new/' . $o['updated'] . ' upd') : 'FAILED'));
        }
    }
}

page_head('Upload CRM file', $u);
echo '<h1>Upload CRM file</h1><p class="muted">One file updates both Mailer and Ops Room.</p>';
if ($err) echo '<p class="err">' . esc($err) . '</p>';
if ($res) {
    $m = $res['mailer']; $o = $res['ops'];
    echo '<div class="card"><b>Mailer</b><br>' . esc($m['inserted']) . ' new, ' . esc($m['updated']) . ' updated, ' . esc($m['skipped']) . ' skipped'
        . (!empty($m['now_zero']) ? '<br>⚠️ ' . esc($m['now_zero']) . ' active account(s) now show ₹0 overdue. Check if they should be marked Settled or Closed.' : '') . '</div>';
    if ($o) echo '<div class="card"><b>Ops Room</b><br>' . esc($o['inserted']) . ' new cases, ' . esc($o['updated']) . ' updated, ' . esc($o['skipped']) . ' skipped</div>';
    else echo '<div class="card"><b>Ops Room</b><br>❌ Ops Room did not take the data (code ' . esc($res['ops_code']) . '). Mailer is updated. Upload the same file again once Ops Room is fixed; nothing doubles up.</div>';
    if (!empty($m['skipped_rows'])) {
        echo '<div class="card"><b>Skipped rows</b> (no due date and ₹0 overdue)<ul>';
        foreach (array_slice($m['skipped_rows'], 0, 30) as $r) echo '<li>' . esc($r['name'] . ' · ' . $r['loan_no']) . '</li>';
        echo '</ul></div>';
    }
}
echo '<form method="post" enctype="multipart/form-data" class="card">' . csrf_field()
    . '<label>CRM Excel file (.xls / .xlsx)<input type="file" name="file" accept=".xls,.xlsx" required></label>'
    . '<button class="btn" type="submit">Upload to both apps</button></form>';
page_foot();
