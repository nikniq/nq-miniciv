#!/usr/bin/env bash
# Copyright © 2026 Mini Civilisation
#
# Production deploy for shared hosting. Run ON the server, from the app root
# (the directory that holds artisan and public_html/):
#
#   ./deploy.sh                     # pull + deps + migrate + caches + verify
#   ./deploy.sh GameSeeder          # …and re-run one specific seeder
#   ./deploy.sh --seed              # …and re-run EVERY seeder (confirms first)
#   ./deploy.sh --rollback          # check out the previous deploy and rebuild
#   ./deploy.sh --verify            # run only the post-deploy checks
#   ./deploy.sh --no-down           # deploy without maintenance mode
#
# The web root is public_html/, not public/ (see bootstrap/app.php). Nothing
# here should ever write to a public/ directory.
#
# Sequence, so the steps cannot drift apart:
#   1. refuse to run with local edits on the server (they would be lost)
#   2. record the current commit so --rollback has somewhere to go
#   3. maintenance mode (bypass secret printed so you can still test)
#   4. git pull --ff-only
#   5. composer install, only when composer.lock moved
#   6. npm build, only when package-lock.json or resources/ moved and npm exists
#   7. migrate --force
#   8. optional seeding
#   9. storage:link, rebuild caches
#  10. verify, then leave maintenance mode
#
# If verify fails the site is left in maintenance mode on purpose: a 503 is
# better than a broken page. Fix, then `php artisan up` or re-run deploy.

set -euo pipefail
cd "$(dirname "$0")"

# Any failure after maintenance mode is switched on would otherwise leave a
# silent 503. Say what broke and how to recover.
on_error() {
    local code=$? line=$1
    echo
    echo "✗ deploy.sh failed at line $line (exit $code)."
    if [ -f storage/framework/down ]; then
        echo "  The site is still in MAINTENANCE MODE (503)."
        echo "  Bring it back with:  php artisan up"
        echo "  Then fix the step above and re-run ./deploy.sh, or ./deploy.sh --rollback"
    fi
    exit "$code"
}
trap 'on_error $LINENO' ERR

LAST_DEPLOY_FILE="storage/app/.last-deploy"
NO_DOWN=0
ACTION=deploy
SEEDER=""

for arg in "$@"; do
    case "$arg" in
        --rollback) ACTION=rollback ;;
        --verify)   ACTION=verify ;;
        --seed)     SEEDER="--all" ;;
        --no-down)  NO_DOWN=1 ;;
        --*)        echo "Unknown flag: $arg"; exit 2 ;;
        *)          SEEDER="$arg" ;;
    esac
done

say()  { printf '→ %s\n' "$*"; }
warn() { printf '⚠ %s\n' "$*"; }
ok()   { printf '✓ %s\n' "$*"; }
fail() { printf '✗ %s\n' "$*"; FAILED=1; }

#---------------------------------------------------------------
# verify: the checks that would otherwise be discovered by a visitor
#---------------------------------------------------------------
verify() {
    FAILED=0
    echo "--- verify ---"

    # Pending migrations are the single most common cause of a 500 after a pull.
    if php artisan migrate:status 2>/dev/null | grep -qE '\|\s*Pending\s*\|'; then
        fail "pending migrations"
    else
        ok "migrations up to date"
    fi

    if php artisan db:show >/dev/null 2>&1; then
        ok "database reachable"
    else
        fail "database not reachable"
    fi

    for d in storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache; do
        if [ -w "$d" ]; then :; else fail "$d not writable"; fi
    done
    ok "storage and bootstrap/cache writable (or reported above)"

    if [ -f public_html/build/manifest.json ]; then
        ok "vite manifest present"
    else
        fail "public_html/build/manifest.json missing — run npm run build (locally and commit, or here)"
    fi

    if [ -L public_html/storage ]; then
        ok "public_html/storage link present"
    else
        warn "public_html/storage link missing (php artisan storage:link)"
    fi

    # Production safety: debug pages leak .env values.
    if grep -qE '^APP_ENV=production' .env && grep -qE '^APP_DEBUG=true' .env; then
        fail "APP_DEBUG=true in production"
    else
        ok "debug mode safe"
    fi

    if grep -qE '^APP_KEY=base64:' .env; then
        ok "APP_KEY set"
    else
        fail "APP_KEY missing — php artisan key:generate --force"
    fi

    # Hits the framework health route through PHP directly, no web server
    # involved, so a failure here is the app and not DNS/TLS.
    APP_URL=$(grep -E '^APP_URL=' .env | cut -d= -f2- | tr -d '"' || true)
    if [ -n "$APP_URL" ] && command -v curl >/dev/null 2>&1; then
        code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$APP_URL/up" 2>/dev/null || true)
        code=${code:-000}
        case "$code" in
            200) ok "$APP_URL/up → 200" ;;
            503) warn "$APP_URL/up → 503 (maintenance mode or component down)" ;;
            *)   fail "$APP_URL/up → $code" ;;
        esac
    fi

    if [ "$FAILED" = "1" ]; then
        echo "--- verify FAILED ---"
        return 1
    fi
    echo "--- verify passed ---"
}

#---------------------------------------------------------------
# verify only
#---------------------------------------------------------------
if [ "$ACTION" = "verify" ]; then
    trap - ERR
    verify
    exit $?
fi

#---------------------------------------------------------------
# guards
#---------------------------------------------------------------
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    warn "The server checkout has local modifications; a pull would conflict or lose them:"
    git status --short --untracked-files=no
    echo "  Resolve on the server (git stash / git checkout -- <file>) and re-run."
    exit 1
fi

PREVIOUS=$(git rev-parse HEAD)

#---------------------------------------------------------------
# maintenance mode
#---------------------------------------------------------------
bring_up() {
    if [ "$NO_DOWN" = "0" ]; then
        say "php artisan up"
        php artisan up
    fi
}

if [ "$NO_DOWN" = "0" ]; then
    SECRET=$(php -r 'echo bin2hex(random_bytes(8));')
    say "maintenance mode on (bypass: ?secret… see below)"
    # --retry so crawlers come back instead of de-indexing; --secret so you
    # can still load the site yourself at APP_URL/<secret> while it is down.
    php artisan down --retry=60 --secret="$SECRET"
    echo "  bypass URL: $(grep -E '^APP_URL=' .env | cut -d= -f2- | tr -d '"')/$SECRET"
fi

#---------------------------------------------------------------
# code
#---------------------------------------------------------------
if [ "$ACTION" = "rollback" ]; then
    if [ ! -f "$LAST_DEPLOY_FILE" ]; then
        warn "No previous deploy recorded in $LAST_DEPLOY_FILE — nothing to roll back to."
        bring_up
        exit 1
    fi
    TARGET=$(cat "$LAST_DEPLOY_FILE")
    say "rollback: $(git log --oneline -1) → $(git log --oneline -1 "$TARGET")"
    echo "  Migrations are NOT reversed automatically. If the bad deploy added a"
    echo "  migration the old code cannot live with, run migrate:rollback by hand."
    read -r -p "Type 'yes' to continue: " confirm
    if [ "$confirm" != "yes" ]; then
        echo "→ rollback skipped"
        bring_up
        exit 0
    fi
    LOCK_BEFORE=$(git rev-parse HEAD:composer.lock 2>/dev/null || echo none)
    git checkout --detach "$TARGET"
    LOCK_AFTER=$(git rev-parse HEAD:composer.lock 2>/dev/null || echo none)
    BUILD_CHANGED=1
else
    say "git pull --ff-only"
    LOCK_BEFORE=$(git rev-parse HEAD:composer.lock 2>/dev/null || echo none)
    BUILD_BEFORE=$(git rev-parse HEAD:package-lock.json 2>/dev/null || echo none)$(git rev-parse HEAD:resources 2>/dev/null || echo none)
    # If a rollback left HEAD detached, go back to the branch first.
    if ! git symbolic-ref -q HEAD >/dev/null; then
        say "re-attaching to main after a rollback"
        git checkout main
    fi
    git pull --ff-only
    LOCK_AFTER=$(git rev-parse HEAD:composer.lock 2>/dev/null || echo none)
    BUILD_AFTER=$(git rev-parse HEAD:package-lock.json 2>/dev/null || echo none)$(git rev-parse HEAD:resources 2>/dev/null || echo none)
    BUILD_CHANGED=$([ "$BUILD_BEFORE" != "$BUILD_AFTER" ] && echo 1 || echo 0)
    printf '%s\n' "$PREVIOUS" > "$LAST_DEPLOY_FILE"
fi

#---------------------------------------------------------------
# dependencies
#---------------------------------------------------------------
if [ "$LOCK_BEFORE" != "$LOCK_AFTER" ] || [ ! -d vendor ]; then
    if command -v composer >/dev/null 2>&1; then
        say "composer install (lockfile changed)"
        composer install --no-dev --optimize-autoloader --no-interaction
    else
        # Not fatal: the pull has landed and stopping here would also leave the
        # tree un-migrated. Say so loudly instead; verify will catch the rest.
        warn "composer.lock changed but composer is not on PATH."
        echo "  Dependencies are now out of date and requests may 500."
        echo "  Try:  php /usr/local/bin/composer install --no-dev  (or wherever composer lives)"
        false
    fi
fi

# Built assets are usually committed from a dev machine because shared hosts
# rarely have node. If npm is here and the inputs moved, rebuild; otherwise
# verify checks that a manifest exists at all.
if [ "$BUILD_CHANGED" = "1" ] && command -v npm >/dev/null 2>&1; then
    say "npm ci && npm run build (frontend inputs changed)"
    npm ci --no-audit --no-fund && npm run build
fi

#---------------------------------------------------------------
# database
#---------------------------------------------------------------
say "migrate"
php artisan migrate --force

if [ "$SEEDER" = "--all" ]; then
    warn "This re-runs EVERY seeder and overwrites any admin-edited field a seeder also sets."
    read -r -p "Type 'yes' to continue: " confirm
    if [ "$confirm" = "yes" ]; then
        say "db:seed (all)"
        php artisan db:seed --force
    else
        say "skipped seeding"
    fi
elif [ -n "$SEEDER" ]; then
    say "db:seed --class=$SEEDER"
    php artisan db:seed --class="$SEEDER" --force
fi

#---------------------------------------------------------------
# filesystem and caches
#---------------------------------------------------------------
if [ ! -L public_html/storage ]; then
    say "storage:link"
    php artisan storage:link || warn "storage:link failed (check public_html permissions)"
fi

say "cache config/routes/views/events"
php artisan optimize

# Queue workers hold old code in memory until told to restart.
say "queue:restart"
php artisan queue:restart >/dev/null 2>&1 || true

#---------------------------------------------------------------
# verify, then reopen
#---------------------------------------------------------------
if verify; then
    bring_up
    ok "deployed $(git log --oneline -1)"
else
    warn "verify failed — site left in maintenance mode. Fix, then: php artisan up"
    echo "  or roll back: ./deploy.sh --rollback"
    exit 1
fi
