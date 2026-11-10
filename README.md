# Mini Civilisation

Game portal for Mini Civilisation, built on Laravel.

## The game

A turn-based, single-player empire builder against rival AI empires on a
shared, seeded map. Found cities, grow borders through culture, build
granaries, libraries, markets and wonders, and research thirteen
technologies from Agriculture to Banking. Win by controlling 55% of the
land, or by having the highest score after 120 turns.

Code lives in `app/Game/`:

- `Rules.php` is all static data: terrain, resources, buildings, techs, yields.
- `MapGenerator.php` and `GameSetup.php` create a game.
- `Economy.php` computes city yields and build options.
- `Actions.php` enforces player and AI actions (found, build, buy, research).
- `TurnProcessor.php` advances a turn; `AiPlayer.php` drives rivals.
- `Presenter.php` shapes everything the game screen needs.

Military is abstract: units add to an empire-wide army, and a bordering
enemy city can be attacked when the army beats its defence (size, walls,
capital bonus, garrison). Capturing takes the city and its land. Happiness
(base + per city + monuments/temples) must cover citizens beyond the first
two in each city or growth stops; buildings and the army cost gold upkeep.

Options when starting a game: map size (small, normal, large), number of
rivals, and difficulty (Settler to Emperor, which scales AI production and
science). Diplomacy is war or peace between each pair of empires; AI rivals
declare war a turn before attacking and sue for peace when losing or tired.
Barbarian camps pillage nearby cities until cleared by an army. Cities have a
focus (growth, production, gold, science). Victory by domination, culture or
score. A public Hall of Fame lists the best finished games, and /how-to-play
documents every rule, tech and building from the data in `Rules.php`.

Accounts: "Play now" creates a guest account on the fly so visitors start
immediately; registering later (or linking Google) converts that account
and keeps every game. Also email and password, plus Google sign-in through Laravel Socialite
when `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are set in `.env`. The
authorised redirect URI in Google Cloud must be `APP_URL/auth/google/callback`.

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
