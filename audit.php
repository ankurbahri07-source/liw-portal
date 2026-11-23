<?php
require __DIR__ . '/inc/boot.php';
$u = need_owner();
page_head('Activity log', $u);
echo '<h1>Activity log</h1><p class="mut">Last 200 events: sign-ins, access changes, password resets.</p><div class="card"><table><tr><th>When</th><th>Who</th><th>What</th><th>Detail</th></tr>';
foreach (rows('SELECT a.*, u.name FROM portal_audit a LEFT JOIN portal_users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 200') as $r)
    echo '<tr><td class="sm">' . esc($r['created_at']) . '</td><td>' . esc($r['name'] ?? '—') . '</td><td>' . esc($r['action']) . '</td><td class="sm mut">' . esc($r['detail']) . '</td></tr>';
echo '</table></div>';
page_foot();
