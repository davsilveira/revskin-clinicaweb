# Laravel + React environment (wp-agent)

Run commands inside DDEV:
- `ddev composer …`  `ddev artisan …`  `ddev npm …` (composer runs in the app dir automatically)
- Build assets for preview: `ddev exec -d /var/www/html npm run build` (served as static build over LAN/tunnel)
- Tests: `ddev exec -d /var/www/html php artisan test`
Database: per the app's `.env` (DDEV's `db` service is available as host `db`). Local URL: `ddev describe`.
