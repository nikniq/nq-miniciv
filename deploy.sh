#!/usr/bin/env bash
# Production deploy for nq-miniciv. Run ON the server:
#
#   ./deploy.sh                # pull + migrate + rebuild caches
#   ./deploy.sh GamesSeeder    # …and re-run one specific seeder
#   ./deploy.sh --seed         # …and re-run EVERY seeder (confirms first)
#
# Makes the full pull → migrate → cache sequence one command so the
# steps can't drift apart (pull-without-migrate causes "Base table or
# view not found" 500s).
#
# Default seeding is a specific class, not a blanket `db:seed`: a full
# re-seed overwrites any field a seeder sets back to its hardcoded
# value, silently clobbering admin edits made in the DB. --seed runs it
# anyway, but only after you confirm.

set -euo pipefail
cd "$(dirname "$0")"

echo "→ git pull"
git pull

echo "→ migrate"
php artisan migrate --force

if [ "${1:-}" = "--seed" ]; then
    echo "⚠ This re-runs EVERY seeder and overwrites any admin-edited field a seeder also sets."
    read -r -p "Type 'yes' to continue: " confirm
    if [ "$confirm" = "yes" ]; then
        echo "→ db:seed (all)"
        php artisan db:seed --force
    else
        echo "→ skipped seeding"
    fi
elif [ "${1:-}" != "" ]; then
    echo "→ db:seed --class=$1"
    php artisan db:seed --class="$1" --force
fi

echo "→ cache config/routes/views"
php artisan optimize

echo "✓ deployed $(git log --oneline -1)"
