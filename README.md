# Mini Civilisation

Game portal for Mini Civilisation, built on Laravel.

## Layout

The web root is `public_html/` instead of Laravel's default `public/`, so the
project can be deployed to shared hosting that serves `public_html` directly.
This is wired up in two places:

- `bootstrap/app.php` calls `$app->usePublicPath(... '/public_html')`.
- `vite.config.js` sets `publicDirectory: 'public_html'` so built assets land there.

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

## Operations

Two scripts, mirroring the layout used by the other sites:

- `deploy.sh` runs **on the server** from the app root. It puts the site in
  maintenance mode with a bypass secret, pulls with fast-forward only,
  installs composer deps when the lockfile changed, rebuilds assets if npm
  exists and the inputs moved, migrates, optionally seeds, refreshes the
  storage link and caches, restarts queue workers, then verifies before
  bringing the site back up. If verification fails the site stays in
  maintenance mode. Flags: `--rollback`, `--verify`, `--seed`, `--no-down`,
  or a seeder class name.
- `cx.sh` runs **locally** and drives the server over SSH: deploy, what's
  live, caches, health and TLS expiry, logs, config readout, disk, versions,
  database inspection, queue and schedule, maintenance mode, log rotation,
  pruning, permissions, key rotation, backups (dump, pull, restore, prune)
  and cron setup. Copy `vars.sh.example` to `vars.sh` first; it is
  gitignored.

Built assets are expected to be committed (`./cx.sh 27`) because shared
hosts rarely have node.
