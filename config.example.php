<?php
// Copy to config.php ON THE SERVER ONLY (never commit it). Fill the values from hPanel.
return [
  'db_host' => 'localhost',
  'db_name' => 'uXXXX_portal',
  'db_user' => 'uXXXX_portal',
  'db_pass' => 'PUT_DB_PASSWORD_HERE',
  'base_path' => '',                          // '' when the portal is at https://loaninwallet.in/
  'public_url' => 'https://loaninwallet.in',  // used for the tile stats calls
  'setup_key' => 'PUT_A_LONG_RANDOM_TEXT_HERE', // only for the first-time setup page; remove after
  // One secret per app, 40+ random characters each. The SAME value goes in that app's config.php as 'sso_secret'.
  'sso_secrets' => [
    'ops'        => 'PUT_40+_RANDOM_CHARS_FOR_OPS',
    'attendance' => 'PUT_40+_RANDOM_CHARS_FOR_ATTENDANCE',
    'mailer'     => 'PUT_40+_RANDOM_CHARS_FOR_MAILER',
  ],
];
