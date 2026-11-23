<?php
require __DIR__ . '/inc/boot.php';
$u = need_login(); $msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $old = (string)($_POST['old'] ?? ''); $new = (string)($_POST['new'] ?? '');
    if (!password_verify($old, $u['password_hash'])) $err = 'Current password is wrong.';
    elseif ($e = password_ok($new)) $err = $e;
    elseif ($new === $old) $err = 'Choose a different password.';
    else {
        q('UPDATE portal_users SET password_hash=?, must_change=0, sv=sv+1 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        audit((int)$u['id'], 'password_changed');
        sess_start(); $_SESSION['sv'] = (int)$u['sv'] + 1;
        if (!empty($_GET['first'])) redirect(url());
        $msg = 'Password changed.';
    }
}
page_head('My account', $u);
echo '<h1>' . esc($u['name']) . '</h1><p class="mut">Username: ' . esc($u['username']) . '</p>';
if (!empty($_GET['first'])) echo '<div class="okm">Please set your own password to continue.</div>';
if ($msg) echo '<div class="okm">' . esc($msg) . '</div>'; if ($err) echo '<div class="err">' . esc($err) . '</div>';
echo '<form method="post" class="card" style="max-width:420px">' . csrf_field()
   . '<label>Current password</label><input type="password" name="old" autocomplete="current-password" required>'
   . '<label>New password (8+ characters)</label><input type="password" name="new" autocomplete="new-password" required>'
   . '<p><button class="btn">Change password</button></p></form>';
page_foot();
