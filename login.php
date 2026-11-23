<?php
require __DIR__ . '/inc/boot.php';
if (!cfg()) { http_response_code(503); exit('Not configured: config.php is missing.'); }
if (me()) redirect(url());
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $un = strtolower(trim((string)($_POST['username'] ?? ''))); $pw = (string)($_POST['password'] ?? '');
    if (login_blocked($un)) $err = 'Too many attempts. Try again in 15 minutes.';
    else {
        $u = row('SELECT * FROM portal_users WHERE (username=? OR mobile=?) AND active=1', [$un, $un]);
        if (!$u || !password_verify($pw, $u['password_hash'])) {
            insert('portal_login_attempts', ['username' => $un, 'ip' => client_ip(), 'created_at' => now_str()]);
            $err = 'Wrong username or password.';
        } else {
            q('DELETE FROM portal_login_attempts WHERE username=?', [$un]);
            sess_start(); session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id']; $_SESSION['sv'] = (int)$u['sv'];
            q('UPDATE portal_users SET last_login=? WHERE id=?', [now_str(), $u['id']]);
            audit((int)$u['id'], 'login');
            $next = (string)($_GET['next'] ?? '');
            redirect(preg_match('#^[a-z]+\.php(\?[a-z=&0-9_]*)?$#', $next) ? url($next) : url());
        }
    }
}
page_head('Sign in');
echo '<div class="login"><h1>Sign in</h1><p class="mut">One login for all Loan in Wallet apps.</p>';
if ($err) echo '<div class="err">' . esc($err) . '</div>';
echo '<form method="post" class="card" autocomplete="on">' . csrf_field()
   . '<label for="u">Username or mobile</label><input id="u" name="username" type="text" autocomplete="username" autofocus required>'
   . '<label for="p">Password</label><input id="p" name="password" type="password" autocomplete="current-password" required>'
   . '<p><button class="btn" style="width:100%">Sign in</button></p></form></div>';
page_foot();
