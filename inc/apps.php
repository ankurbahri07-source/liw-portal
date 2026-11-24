<?php
declare(strict_types=1);

/** Registry of the apps behind the portal. `path` is where the app lives under the domain. */
function apps(): array
{
    return [
        'ops' => [
            'name' => 'Collections Ops Room', 'path' => '/ops/', 'sso' => '/ops/api.php?action=sso',
            'summary' => '/ops/api.php?action=portal_summary', 'icon' => '📞',
            'desc' => 'Cases, calls, collections and team activity',
            'roles' => ['agent' => 'Agent (own cases)', 'admin' => 'Admin (everything)'],
            'perms' => [],
        ],
        'attendance' => [
            'name' => 'Attendance', 'path' => '/attendance/', 'sso' => '/attendance/api/index.php?r=auth/sso',
            'summary' => '/attendance/api/index.php?r=portal/summary', 'icon' => '🕘',
            'desc' => 'Check-in, leave, WFH and reports',
            'roles' => ['employee' => 'Employee', 'admin' => 'Admin (with the permissions ticked)', 'super_admin' => 'Super admin (everything)'],
            'perms' => ['alerts' => 'Alerts', 'approvals' => 'Approvals', 'edit_attendance' => 'Edit or remove anyone\'s attendance', 'reports' => 'Reports',
                        'employees' => 'Employees', 'settings' => 'Settings', 'locations' => 'Locations'],
        ],
        'mailer' => [
            'name' => 'Collections Mailer', 'path' => '/mailer/', 'sso' => '/mailer/sso.php',
            'summary' => '/mailer/api.php?action=portal_summary', 'icon' => '✉️',
            'desc' => 'Tiered borrower emails, daily auto-run',
            'roles' => ['viewer' => 'Viewer (read only)', 'admin' => 'Admin (everything)'],
            'perms' => [],
        ],
    ];
}
/** Top role of each app, used for the owner. */
function owner_role(string $app): string { return ['ops' => 'admin', 'attendance' => 'super_admin', 'mailer' => 'admin'][$app] ?? 'admin'; }

/** What a person may open: [app => ['role'=>..,'perms'=>[..]]]. The owner gets everything. */
function access_for(array $u): array
{
    $out = [];
    if ((int)$u['is_owner']) {
        foreach (apps() as $k => $a) $out[$k] = ['role' => owner_role($k), 'perms' => array_keys($a['perms'])];
        return $out;
    }
    foreach (rows('SELECT app, role, perms FROM portal_access WHERE user_id=?', [$u['id']]) as $r) {
        if (!isset(apps()[$r['app']]) || !isset(apps()[$r['app']]['roles'][$r['role']])) continue;
        $out[$r['app']] = ['role' => $r['role'], 'perms' => array_values(array_filter(explode(',', (string)$r['perms'])))];
    }
    return $out;
}
