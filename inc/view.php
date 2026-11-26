<?php
declare(strict_types=1);

function page_head(string $title, ?array $u = null): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    $company = esc(setting('company_name', 'Loan in Wallet'));
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . esc($title) . ' · ' . $company . '</title>'
        . '<link rel="stylesheet" href="' . esc(url('assets/portal.css')) . '?v=1"></head><body>';
    echo '<header class="top"><a class="brand" href="' . esc(url()) . '"><img src="' . esc(url('assets/logo-icon.png')) . '" alt=""> ' . $company . '</a>';
    if ($u) {
        echo '<nav>';
        if ((int)$u['is_owner']) echo '<a href="' . esc(url('upload.php')) . '">Upload CRM file</a><a href="' . esc(url('users.php')) . '">Users</a><a href="' . esc(url('audit.php')) . '">Activity log</a>';
        echo '<a href="' . esc(url('account.php')) . '">' . esc($u['name']) . '</a>'
            . '<form method="post" action="' . esc(url('logout.php')) . '" class="inl">' . csrf_field() . '<button class="link">Sign out</button></form></nav>';
    }
    echo '</header><main>';
}
function page_foot(): void { echo '</main></body></html>'; }
