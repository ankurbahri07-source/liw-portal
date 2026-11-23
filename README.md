# Loan in Wallet portal (loaninwallet.in)

One login + app tiles for Ops Room (/ops), Attendance (/attendance), Mailer (/mailer).
Each app keeps its own code and database; the portal hands the signed-in person over with a 45-second HMAC-signed pass.

## Files
- `index.php` tiles + live numbers · `login.php` · `users.php` owner-only access control · `audit.php` activity log
- `go.php` issues the pass and redirects into the app · `summary.php` tile numbers (signed server-to-server calls)
- `setup.php` one-time owner creation (needs `setup_key`; delete after use) · `config.example.php` → `config.php` (server only)

## Secrets
Each app has one secret (40+ random characters). The SAME text goes in the portal `config.php` (`sso_secrets`) and in that app's `config.php` (`sso_secret`). Never commit them.

## Go-live order (nothing live changes until step 6)
1. Back up each app's config.php + data folders.
2. Portal: create MySQL DB, copy config.example.php to config.php, fill it, upload, open /setup.php, create owner, delete setup.php.
3. Test copies of each app under /ops-test etc. (own DB), with `sso_secret` set; check sign-in from the portal.
4. Add users + access in the portal Users screen (same username as the app; mobile for Attendance).
5. Point each app's deploy at its new folder.
6. Switch: old subdomains 301 to the new paths; set `sso_only` once everyone uses the portal.
