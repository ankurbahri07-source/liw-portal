<?php
require __DIR__ . '/inc/boot.php';
$u = need_login();
$acc = access_for($u);
page_head('Home', $u);
echo '<h1>Hello, ' . esc(explode(' ', $u['name'])[0]) . '</h1><p class="mut">Choose an app. Numbers on the tiles are live for today.</p>';
if (!$acc) echo '<div class="card">You do not have access to any app yet. Ask Ankur to give you access.</div>';
echo '<div class="grid">';
foreach ($acc as $k => $a) {
    $app = apps()[$k];
    echo '<a class="tile" href="' . esc(url('go.php?app=' . $k)) . '"><div class="ic">' . $app['icon'] . '</div><h3>' . esc($app['name']) . '</h3>'
       . '<div class="mut sm">' . esc($app['desc']) . '</div><div class="stats" data-app="' . esc($k) . '"><span class="chip">…</span></div></a>';
}
echo '</div>';
?>
<script>
fetch(<?= json_encode(url('summary.php')) ?>, {credentials: 'same-origin'}).then(r => r.json()).then(j => {
  document.querySelectorAll('.stats').forEach(el => {
    const s = (j.apps || {})[el.dataset.app]; el.innerHTML = '';
    if (!s || !s.chips) { el.innerHTML = '<span class="chip">no data</span>'; return; }
    s.chips.forEach(c => { const x = document.createElement('span'); x.className = 'chip ' + (c.tone || ''); x.textContent = c.text; el.appendChild(x); });
  });
}).catch(() => document.querySelectorAll('.stats').forEach(el => el.innerHTML = ''));
</script>
<?php page_foot();
