<?php
require __DIR__ . '/inc/boot.php';
$me = need_owner(); $msg = ''; $err = ''; $shown = null;

function norm_mobile(string $m): string { $d = preg_replace('/\D+/', '', $m); return strlen($d) >= 10 ? substr($d, -10) : ''; }
function temp_password(): string { $c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $p = ''; for ($i = 0; $i < 10; $i++) $p .= $c[random_int(0, strlen($c) - 1)]; return $p; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = (string)($_POST['act'] ?? '');
    $uid = (int)($_POST['uid'] ?? 0);
    $t = $uid ? row('SELECT * FROM portal_users WHERE id=?', [$uid]) : null;
    if ($act === 'add') {
        $un = strtolower(trim((string)($_POST['username'] ?? ''))); $nm = trim((string)($_POST['name'] ?? '')); $mob = norm_mobile((string)($_POST['mobile'] ?? ''));
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $un)) $err = 'Username: 3-40 letters, numbers, dot, dash.';
        elseif ($nm === '') $err = 'Name is required.';
        elseif (val('SELECT COUNT(*) FROM portal_users WHERE username=?', [$un])) $err = 'That username already exists.';
        elseif ($mob !== '' && val('SELECT COUNT(*) FROM portal_users WHERE mobile=?', [$mob])) $err = 'That mobile is already used by another person.';
        else {
            $pw = temp_password();
            $id = insert('portal_users', ['username' => $un, 'name' => $nm, 'mobile' => $mob ?: null, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                'is_owner' => 0, 'active' => 1, 'must_change' => 1, 'sv' => 0, 'created_at' => now_str()]);
            audit((int)$me['id'], 'user_added', "$un ($nm)");
            $shown = ['name' => $nm, 'username' => $un, 'pw' => $pw]; $msg = 'User added. Now tick the apps they can open below.';
        }
    } elseif ($t && $act === 'save') {
        $nm = trim((string)($_POST['name'] ?? '')); $mob = norm_mobile((string)($_POST['mobile'] ?? ''));
        if ($nm === '') $err = 'Name is required.';
        elseif ($mob !== '' && val('SELECT COUNT(*) FROM portal_users WHERE mobile=? AND id<>?', [$mob, $t['id']])) $err = 'That mobile is used by another person.';
        else {
            $active = (int)$t['is_owner'] ? 1 : (empty($_POST['active']) ? 0 : 1);
            q('UPDATE portal_users SET name=?, mobile=?, active=? WHERE id=?', [$nm, $mob ?: null, $active, $t['id']]);
            $log = [];
            if (!(int)$t['is_owner']) {
                foreach (apps() as $k => $app) {
                    $on = !empty($_POST["app_$k"]); $role = (string)($_POST["role_$k"] ?? '');
                    $perms = array_values(array_intersect(array_keys($app['perms']), (array)($_POST["perm_$k"] ?? [])));
                    $had = row('SELECT * FROM portal_access WHERE user_id=? AND app=?', [$t['id'], $k]);
                    if ($on && isset($app['roles'][$role])) {
                        $ps = $role === 'admin' ? implode(',', $perms) : '';
                        if ($had) q('UPDATE portal_access SET role=?, perms=? WHERE id=?', [$role, $ps, $had['id']]);
                        else insert('portal_access', ['user_id' => $t['id'], 'app' => $k, 'role' => $role, 'perms' => $ps]);
                        if (!$had || $had['role'] !== $role || $had['perms'] !== $ps) $log[] = "$k=$role" . ($ps ? "[$ps]" : '');
                    } elseif ($had) { q('DELETE FROM portal_access WHERE id=?', [$had['id']]); $log[] = "$k removed"; }
                }
                // access changed or switched off: end their portal sessions so it takes effect now
                q('UPDATE portal_users SET sv=sv+1 WHERE id=?', [$t['id']]);
            }
            audit((int)$me['id'], 'user_saved', $t['username'] . ($log ? ': ' . implode(', ', $log) : '') . ($active ? '' : ' (disabled)'));
            $msg = 'Saved ' . $nm . '.';
        }
    } elseif ($t && $act === 'reset') {
        $pw = temp_password();
        q('UPDATE portal_users SET password_hash=?, must_change=1, sv=sv+1 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $t['id']]);
        audit((int)$me['id'], 'password_reset', $t['username']);
        $shown = ['name' => $t['name'], 'username' => $t['username'], 'pw' => $pw];
    } elseif ($t && $act === 'signout') {
        q('UPDATE portal_users SET sv=sv+1 WHERE id=?', [$t['id']]);
        audit((int)$me['id'], 'force_signout', $t['username']); $msg = $t['name'] . ' is signed out of the portal.';
    }
}

page_head('Users', $me);
echo '<h1>Users and access</h1><p class="mut">You decide who can open which app, and what an admin may do. Changes take effect the next time that person opens an app.</p>';
if ($msg) echo '<div class="okm">' . esc($msg) . '</div>'; if ($err) echo '<div class="err">' . esc($err) . '</div>';
if ($shown) echo '<div class="okm"><b>' . esc($shown['name']) . '</b> — username <b>' . esc($shown['username']) . '</b>, temporary password <b>' . esc($shown['pw']) . '</b><br><span class="sm">Shown only once. Send it to them privately; they must choose their own password at first sign-in.</span></div>';

echo '<details class="card"><summary><b>+ Add a person</b></summary><form method="post">' . csrf_field() . '<input type="hidden" name="act" value="add">'
   . '<label>Full name</label><input type="text" name="name" required maxlength="120">'
   . '<label>Username (they type this to sign in)</label><input type="text" name="username" required maxlength="40" autocapitalize="none">'
   . '<label>Mobile (10 digits — needed for Attendance)</label><input type="tel" name="mobile" maxlength="15">'
   . '<p><button class="btn">Add person</button></p></form></details>';

foreach (rows('SELECT * FROM portal_users ORDER BY is_owner DESC, active DESC, name') as $t) {
    $acc = access_for($t);
    $open = ($shown && $shown['username'] === $t['username']) || (($_POST['uid'] ?? 0) == $t['id']);
    echo '<details class="card"' . ($open ? ' open' : '') . '><summary><b>' . esc($t['name']) . '</b> <span class="mut sm">@' . esc($t['username']) . ($t['mobile'] ? ' · ' . esc($t['mobile']) : '') . '</span> '
       . ((int)$t['is_owner'] ? '<span class="chip ok">Owner</span>' : (!(int)$t['active'] ? '<span class="chip bad">Disabled</span>' : ''))
       . ' <span class="mut sm">' . esc(implode(', ', array_map(fn($k) => apps()[$k]['name'], array_keys($acc))) ?: 'no apps') . '</span></summary>';
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="save"><input type="hidden" name="uid" value="' . (int)$t['id'] . '">'
       . '<div class="row"><div style="flex:2;min-width:180px"><label>Name</label><input type="text" name="name" value="' . esc($t['name']) . '" required></div>'
       . '<div style="flex:1;min-width:150px"><label>Mobile</label><input type="tel" name="mobile" value="' . esc($t['mobile']) . '"></div></div>';
    if ((int)$t['is_owner']) echo '<p class="mut sm">The owner always has every app and every permission.</p>';
    else {
        echo '<label class="perms"><span><input type="checkbox" name="active" value="1"' . ((int)$t['active'] ? ' checked' : '') . '> Active (can sign in)</span></label>';
        foreach (apps() as $k => $app) {
            $a = $acc[$k] ?? null;
            echo '<div class="approw"><label class="perms" style="margin:0"><span><input type="checkbox" name="app_' . $k . '" value="1"' . ($a ? ' checked' : '') . '> <b>' . esc($app['name']) . '</b></span></label>'
               . '<div class="row" style="margin-top:6px"><select name="role_' . $k . '" style="max-width:320px">';
            foreach ($app['roles'] as $rk => $rl) echo '<option value="' . $rk . '"' . (($a['role'] ?? '') === $rk ? ' selected' : '') . '>' . esc($rl) . '</option>';
            echo '</select></div>';
            if ($app['perms']) {
                echo '<div class="perms sm"><span class="mut">If Admin, they may:</span>';
                foreach ($app['perms'] as $pk => $pl) echo '<label><input type="checkbox" name="perm_' . $k . '[]" value="' . $pk . '"' . (in_array($pk, $a['perms'] ?? [], true) ? ' checked' : '') . '> ' . esc($pl) . '</label>';
                echo '</div>';
            }
            echo '</div>';
        }
    }
    echo '<p class="row"><button class="btn">Save</button></p></form>';
    if (!(int)$t['is_owner']) echo '<form method="post" class="row" style="margin-top:6px" onsubmit="return confirm(\'Reset the password for ' . esc(addslashes($t['name'])) . '?\')">' . csrf_field()
       . '<input type="hidden" name="uid" value="' . (int)$t['id'] . '"><button class="btn sec sm" name="act" value="reset">Reset password</button> '
       . '<button class="btn sec sm" name="act" value="signout">Sign out everywhere</button></form>';
    echo '</details>';
}
page_foot();
