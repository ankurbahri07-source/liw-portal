<?php
// One-time setup. Works only while there are no users, and only with the setup_key from config.php.
require __DIR__ . '/inc/boot.php';
if (!cfg()) { http_response_code(503); exit('Create config.php first (copy config.example.php).'); }
if ((int)val('SELECT COUNT(*) FROM portal_users') > 0) { http_response_code(404); exit('Already set up.'); }
$key = (string)(cfg()['setup_key'] ?? '');
if (strlen($key) < 12) exit('Put a long random setup_key in config.php first.');
$err = ''; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $un = strtolower(trim((string)($_POST['username'] ?? ''))); $nm = trim((string)($_POST['name'] ?? '')); $pw = (string)($_POST['password'] ?? '');
    $mob = substr(preg_replace('/\D+/', '', (string)($_POST['mobile'] ?? '')), -10);
    if (!hash_equals($key, (string)($_POST['key'] ?? ''))) $err = 'Wrong setup key.';
    elseif (!preg_match('/^[a-z0-9._-]{3,40}$/', $un) || $nm === '') $err = 'Enter a name and a username (3-40 letters/numbers).';
    elseif ($e = password_ok($pw)) $err = $e;
    else {
        $id = insert('portal_users', ['username' => $un, 'name' => $nm, 'mobile' => $mob ?: null, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
            'is_owner' => 1, 'active' => 1, 'must_change' => 0, 'sv' => 0, 'created_at' => now_str()]);
        set_setting('company_name', 'Loan in Wallet'); audit($id, 'owner_created', $un); $done = true;
    }
}
page_head('Setup');
if ($done) { echo '<div class="login"><h1>Done</h1><div class="okm">Owner account created.</div><p><a class="btn" href="' . esc(url('login.php')) . '">Sign in</a></p><p class="mut sm">Now delete setup.php from the server.</p></div>'; page_foot(); exit; }
echo '<div class="login"><h1>First-time setup</h1>';
if ($err) echo '<div class="err">' . esc($err) . '</div>';
echo '<form method="post" class="card">' . csrf_field()
   . '<label>Setup key (from config.php)</label><input type="password" name="key" required>'
   . '<label>Your name</label><input type="text" name="name" required>'
   . '<label>Your username</label><input type="text" name="username" required autocapitalize="none">'
   . '<label>Your mobile</label><input type="tel" name="mobile">'
   . '<label>Password (8+ characters)</label><input type="password" name="password" autocomplete="new-password" required>'
   . '<p><button class="btn" style="width:100%">Create owner account</button></p></form></div>';
page_foot();
