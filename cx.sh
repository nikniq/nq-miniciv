#!/bin/bash
#================================================================
# Copyright © 2026 Mini Civilisation
#
# LOCAL command-line assistant — cx.sh
#
#   ./cx.sh            # interactive menu
#   ./cx.sh 20         # run an option directly
#   ./cx.sh 91 "note"  # …with a second parameter (backup description)
#
# ⚠ Runs on your LOCAL machine only — never copy this to the server.
#   Its counterpart on the server is deploy.sh (pull + migrate + caches).
#
# Needs no settings file: defaults are built in, and the server login is
# an ssh alias 'miniciv' (see Settings below). vars.sh is optional.
#
# Version 1.0 (Mini Civilisation)
# - adapted from the ApiSpi cx.sh 2.4; the billing, pricing and connector
#   options that belonged to that app are gone
# - operations: deploy, deploy+seed, rollback, what's-live, migration
#   status, cache refresh/clear, composer install, asset build+commit (20-28)
# - diagnostics: health, log tail, recent errors, artisan, config readout,
#   disk, log sizes, versions, PHP/OPcache status, slow-request grep (30-39)
# - data: seed one class, database summary, table sizes, tinker-free SQL
#   query via db:show/db:table (40-43)
# - queue and schedule (50-54)
# - admins: list, grant, revoke, sync from ADMIN_EMAILS, open /admin (60-64)
# - maintenance: verify, maintenance mode on/off, log rotate/truncate,
#   clear expired sessions and cache, prune old backups, fix permissions,
#   regenerate storage link (70-78)
# - backups: database + uploads on the server, pull them here, restore
#   a database dump (90-93)
# - every option that drops the site, discards data or rewrites state
#   goes through one confirm()
#================================================================
clear

# Settings. Everything has a working default; no vars.sh is needed.
# Override any value from the environment (CX_REMOTE=user@host ./cx.sh 20),
# or keep personal overrides in an optional, gitignored vars.sh.
#
# The server login comes from an SSH host alias, so no account names live
# in the repo. Add this once to ~/.ssh/config (SiteGround > Devs > SSH Keys
# Manager shows the username; the port is 18765):
#
#   Host miniciv
#     HostName miniciv.nikniq.com
#     User u123-yourusername
#     Port 18765
#     IdentityFile ~/.ssh/id_ed25519
[ -f ./vars.sh ] && source ./vars.sh

PROJECT="${PROJECT:-miniciv.nikniq.com}"
REMOTE="${REMOTE:-${CX_REMOTE:-miniciv}}"   # ssh alias or user@host
REMOTE_HOST="${REMOTE_HOST:-${REMOTE#*@}}"
SITES=("${SITES[@]:-$PROJECT}")
REMOTE_APP="${REMOTE_APP:-www/$PROJECT}"     # app root on the server, relative to ~
APP_URL="${APP_URL:-https://$PROJECT}"       # what the health options check
LOG_OUTPUT="${LOG_OUTPUT:-./log/remote.out}"
LOCAL_BACKUPS="${LOCAL_BACKUPS:-$HOME/backups/$PROJECT}"
# The port lives in ~/.ssh/config with the alias; set REMOTE_PORT only for a user@host REMOTE.
if [ -n "${REMOTE_PORT:-}" ]; then
  APP_SSH="${APP_SSH:-ssh -p $REMOTE_PORT}"
  APP_SCP="${APP_SCP:-scp -P $REMOTE_PORT}"
  APP_RSYNC="${APP_RSYNC:-rsync -e 'ssh -p $REMOTE_PORT'}"
else
  APP_SSH="${APP_SSH:-ssh}"
  APP_SCP="${APP_SCP:-scp}"
  APP_RSYNC="${APP_RSYNC:-rsync -e ssh}"
fi

# An alias with no ~/.ssh/config entry would fail with a confusing DNS error.
if [[ "$REMOTE" != *@* ]] && [ "$(ssh -G "$REMOTE" 2>/dev/null | awk '/^hostname /{print $2}')" = "$REMOTE" ]; then
  echo "⚠ No ssh alias '$REMOTE' in ~/.ssh/config. Add the Host block shown at the top of cx.sh,"
  echo "  or run with CX_REMOTE=user@host REMOTE_PORT=18765 ./cx.sh"
  echo
fi

# This script drives the server from outside. Run on the server it would
# try to ssh to itself.
if [ "$(hostname -s 2>/dev/null)" = "${REMOTE_HOST%%.*}" ]; then
  echo "⚠ This looks like $REMOTE_HOST — cx.sh is the LOCAL script. Use deploy.sh here."
  exit 1
fi

#---------------------------------------------------------------
# Helpers
#---------------------------------------------------------------

# Append a line to the history log, creating it if needed.
log_history() {
  mkdir -p ./log
  touch ./log/history.log
  printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M')" "$1" > tmpfile
  cat ./log/history.log >> tmpfile && mv tmpfile ./log/history.log
}

# Run a command on the server, in the app directory.
remote() {
  $APP_SSH "$REMOTE" "cd ~/$REMOTE_APP && $*"
}

# Same, with a terminal attached — for anything interactive or long-running.
remote_tty() {
  $APP_SSH -t "$REMOTE" "cd ~/$REMOTE_APP && $*"
}

# Ask for PARAM2 only when the chosen option needs it.
need_param() {
  if [ -z "$PARAM2" ]; then
    read -r -p "$1: " PARAM2
  fi
}

banner() {
  echo ===========================================================
  echo "$1"
  echo ===========================================================
}

# Gate for anything that drops the site, discards data, or is hard to undo.
confirm() {
  echo "⚠ $1"
  read -r -p "Type 'yes' to continue: " reply
  [ "$reply" = "yes" ] || { echo "Skipped $SELECTION."; exit 0; }
}

# Refuse to deploy what is not on the remote branch. The server deploys by
# pulling, so a local-only commit silently deploys the previous one.
check_pushed() {
  if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
    echo "⚠ Uncommitted local changes — these will NOT deploy:"
    git status --short
    echo
  fi
  UNPUSHED=$(git log --oneline @{u}.. 2>/dev/null)
  if [ -n "$UNPUSHED" ]; then
    echo "⚠ Commits not pushed — these will NOT deploy:"
    echo "$UNPUSHED"
    echo
    read -r -p "Deploy anyway? Type 'yes' to continue: " reply
    [ "$reply" = "yes" ] || { echo "Skipped $SELECTION"; exit 0; }
  fi
  echo "Local HEAD : $(git log --oneline -1)"
  echo
}

# One shell snippet, run on the server, that dumps the database to
# backup/<stamp>-<note>.sql.gz whatever the driver. It reads .env itself so
# no credentials travel over the command line from here.
# shellcheck disable=SC2016
DB_DUMP_SNIPPET='
set -e
mkdir -p backup && chmod 700 backup
stamp=$(date +%Y%m%d-%H%M%S)
note=$(printf "%s" "$1" | tr -cs "A-Za-z0-9_-" "-" | cut -c1-40)
out="backup/${stamp}${note:+-$note}.sql.gz"
env_val() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e "s/^\"//" -e "s/\"$//" -e "s/^'"'"'//" -e "s/'"'"'$//"; }
conn=$(env_val DB_CONNECTION); conn=${conn:-sqlite}
case "$conn" in
  sqlite)
    db=$(env_val DB_DATABASE); db=${db:-database/database.sqlite}
    [ -f "$db" ] || { echo "sqlite file $db not found"; exit 1; }
    if command -v sqlite3 >/dev/null 2>&1; then sqlite3 "$db" .dump | gzip > "$out"
    else gzip -c "$db" > "${out%.sql.gz}.sqlite.gz"; out="${out%.sql.gz}.sqlite.gz"; fi ;;
  mysql|mariadb)
    MYSQL_PWD=$(env_val DB_PASSWORD) mysqldump --single-transaction --quick --routines \
      -h "$(env_val DB_HOST)" -P "${DB_PORT:-$(env_val DB_PORT)}" -u "$(env_val DB_USERNAME)" "$(env_val DB_DATABASE)" | gzip > "$out" ;;
  pgsql)
    PGPASSWORD=$(env_val DB_PASSWORD) pg_dump -h "$(env_val DB_HOST)" -p "$(env_val DB_PORT)" \
      -U "$(env_val DB_USERNAME)" "$(env_val DB_DATABASE)" | gzip > "$out" ;;
  *) echo "unsupported DB_CONNECTION: $conn"; exit 1 ;;
esac
ls -lh "$out"
'

echo =============================================================
echo "Hi $USER@$HOSTNAME — acting on $PROJECT ($REMOTE)"
echo "Other sites configured: ${SITES[*]}"
echo What do you want to do?
echo -------------------------------------------------------------
echo "ROUTINE — day to day"
echo "20 : DEPLOY: pull + migrate + caches + verify on $PROJECT"
echo "21 : DEPLOY + re-run one seeder class"
echo "22 : WHAT'S LIVE: compare the server's HEAD with local"
echo "23 : MIGRATIONS: status (what is pending on the server)"
echo "24 : CACHES: refresh config/routes/views"
echo "25 : CACHES: clear everything (when a cached value looks stale)"
echo "26 : COMPOSER: install production dependencies"
echo "27 : ASSETS: build locally and commit public_html/build"
echo "28 : ROLLBACK: return the server to the previous deploy  [confirms]"
echo ----------------------------------------------
echo DIAGNOSE
echo "30 : HEALTH: $APP_URL — response, TLS expiry, /up"
echo "31 : LOGS: tail the Laravel log"
echo "32 : LOGS: last errors with their messages"
echo "33 : ARTISAN: run a command (e.g. 'migrate:status')"
echo "34 : CONFIG: environment and key settings on the server"
echo "35 : DISK: what is using the account quota"
echo "36 : LOGS: file sizes, and what is growing"
echo "37 : VERSIONS: PHP, Laravel, composer, node on the server"
echo "38 : PHP: loaded extensions, limits, OPcache"
echo "39 : PROCESSES: what is running under the account"
echo ----------------------------------------------
echo "DATA"
echo "40 : SEED: run one seeder class (no pull, no migrate)"
echo "41 : DATABASE: connection summary and table list"
echo "42 : DATABASE: inspect one table (columns, indexes, size)"
echo "43 : DATABASE: row counts for every table"
echo ----------------------------------------------
echo "QUEUE & SCHEDULE"
echo "50 : QUEUE: list failed jobs"
echo "51 : QUEUE: retry all failed jobs"
echo "52 : QUEUE: drain the queue now"
echo "53 : QUEUE: flush failed jobs  [discards them]"
echo "54 : SCHEDULE: what is registered, and run it once now"
echo ----------------------------------------------
echo "ADMINS"
echo "60 : ADMINS: list administrators (and ADMIN_EMAILS)"
echo "61 : ADMINS: grant admin to an email (account must exist)"
echo "62 : ADMINS: revoke admin from an email  [confirms]"
echo "63 : ADMINS: sync from ADMIN_EMAILS in the server .env"
echo "64 : ADMINS: open the admin area in the browser"
echo ----------------------------------------------
echo MAINTENANCE
echo "70 : VERIFY: run the post-deploy checks on their own"
echo "71 : MAINTENANCE MODE: on with a bypass secret  [site goes down]"
echo "72 : MAINTENANCE MODE: off"
echo "73 : LOGS: rotate (gzip today's log, keep 14)"
echo "74 : LOGS: truncate the Laravel log  [discards log history]"
echo "75 : PRUNE: expired sessions, stale cache, old failed jobs"
echo "76 : PERMISSIONS: fix storage/ and bootstrap/cache"
echo "77 : STORAGE LINK: regenerate public_html/storage"
echo "78 : KEY ROTATE: new APP_KEY  [logs everyone out]"
echo ----------------------------------------------
echo BACKUP
echo "90 : BACKUP: list what is on the server"
echo "91 : BACKUP: database + uploads on the server"
echo "92 : BACKUP: pull all server backups to $LOCAL_BACKUPS"
echo "93 : RESTORE: a database dump on the server  [overwrites data]"
echo "94 : BACKUP: prune server backups older than N days"
echo ----------------------------------------------
echo ACCESS
echo "00 : CONNECT: to $REMOTE"
echo "01 : SETUP: copy deploy.sh to the server and make it executable"
echo "02 : CRON: show the account crontab (scheduler + queue lines)"
echo "03 : CRON: install the scheduler + queue cron lines"
echo "05 : MONITOR: remote top"
echo "qq : Exit [Quit]"
echo Enter [Selection] to continue
echo =============================================================
# Command line selection
if [ -n "$1" ]; then
  SELECTION=$1
else
  read -n 2 SELECTION
fi
PARAM2="${2:-}"
echo
echo "Your selection is : $SELECTION."


case "$SELECTION" in

  #-------------------------------------------------------------
  # ROUTINE
  #-------------------------------------------------------------

  "20" )
  banner "DEPLOY: $PROJECT"
  check_pushed
  remote_tty "./deploy.sh"
  log_history "Deployed $PROJECT"
  ;;


  "21" )
  banner "DEPLOY + SEEDER: $PROJECT"
  need_param "Seeder class (e.g. GameSeeder)"
  check_pushed
  remote_tty "./deploy.sh $PARAM2"
  log_history "Deployed $PROJECT with seeder $PARAM2"
  ;;


  "22" )
  banner "WHAT'S LIVE on $PROJECT"
  echo "--- server ---"
  remote "git log --oneline -3 && git status --short"
  echo
  echo "--- local ---"
  git log --oneline -3
  echo
  LOCAL_HEAD=$(git rev-parse HEAD 2>/dev/null)
  SERVER_HEAD=$(remote "git rev-parse HEAD" 2>/dev/null | tr -d '\r')
  if [ "$LOCAL_HEAD" = "$SERVER_HEAD" ]; then
    echo "✓ server matches local HEAD"
  else
    echo "✗ server is NOT on your local HEAD"
    echo "  local : $LOCAL_HEAD"
    echo "  server: $SERVER_HEAD"
    echo
    echo "--- commits local has that the server does not ---"
    git log --oneline "${SERVER_HEAD}..${LOCAL_HEAD}" 2>/dev/null || echo "(server commit unknown locally — fetch first)"
  fi
  ;;


  "23" )
  banner "MIGRATIONS: status on $PROJECT"
  remote "php artisan migrate:status"
  ;;


  "24" )
  banner "CACHES: refresh on $PROJECT"
  remote "php artisan optimize"
  log_history "Refreshed caches on $PROJECT"
  ;;


  "25" )
  banner "CACHES: clear everything on $PROJECT"
  # A stale config cache is why an .env change can appear to be ignored.
  # deploy.sh rebuilds them, so run 24 after this.
  remote "php artisan optimize:clear"
  log_history "Cleared all caches on $PROJECT"
  ;;


  "26" )
  banner "COMPOSER: install production dependencies"
  remote_tty "composer install --no-dev --optimize-autoloader --no-interaction"
  log_history "Installed composer dependencies on $PROJECT"
  ;;


  "27" )
  banner "ASSETS: build locally and commit public_html/build"
  # Shared hosts rarely have node, so built assets travel in git. The
  # .gitignore excludes public_html/build by default; this force-adds it so
  # the ignore keeps dev machines clean while production gets the files.
  npm run build || { echo "✗ build failed"; exit 1; }
  git add -f public_html/build
  if git diff --cached --quiet; then
    echo "Built assets unchanged — nothing to commit."
  else
    git commit -m "Build frontend assets"
    echo "Committed. Push, then run 20 to deploy."
  fi
  log_history "Built and committed assets"
  ;;


  "28" )
  banner "ROLLBACK: $PROJECT"
  echo "--- server now ---"
  remote "git log --oneline -1; echo; echo 'previous deploy:'; cat storage/app/.last-deploy 2>/dev/null | xargs -I{} git log --oneline -1 {} || echo '(none recorded)'"
  echo
  confirm "This checks out the previous deploy on $PROJECT. Migrations are not reversed."
  remote_tty "./deploy.sh --rollback"
  log_history "Rolled back $PROJECT"
  ;;


  #-------------------------------------------------------------
  # DIAGNOSE
  #-------------------------------------------------------------

  "30" )
  banner "HEALTH: $APP_URL"
  # From here rather than on the server so it exercises the real path in:
  # DNS, TLS and the web server, not just PHP.
  curl -sS -o /dev/null -w 'status   : %{http_code}\ntotal    : %{time_total}s\nttfb     : %{time_starttransfer}s\nredirect : %{redirect_url}\n' \
    --max-time 25 "$APP_URL" || echo "✗ $APP_URL did not respond"
  echo
  echo "--- TLS certificate ---"
  HOST_ONLY=${APP_URL#*://}; HOST_ONLY=${HOST_ONLY%%/*}
  echo | openssl s_client -servername "$HOST_ONLY" -connect "$HOST_ONLY:443" 2>/dev/null \
    | openssl x509 -noout -subject -enddate 2>/dev/null || echo "(no TLS info)"
  echo
  echo "--- /up ---"
  curl -sS -o /dev/null -w 'status   : %{http_code}\n' --max-time 25 "$APP_URL/up" || echo "✗ no response"
  echo
  echo "--- maintenance mode? ---"
  remote "test -f storage/framework/down && echo 'DOWN (php artisan up to restore)' || echo 'up'"
  ;;


  "31" )
  banner "LOGS: tail on $PROJECT (Ctrl-C to stop)"
  remote_tty "tail -n 40 -f storage/logs/laravel.log"
  ;;


  "32" )
  banner "LOGS: recent errors on $PROJECT"
  remote "grep -nE '\.(ERROR|CRITICAL|ALERT|EMERGENCY):' storage/logs/laravel.log | tail -n 25" \
    || echo "No matching lines (or no log file yet)."
  echo
  echo "--- error counts in the last 1000 lines ---"
  remote "tail -n 1000 storage/logs/laravel.log 2>/dev/null | grep -oE '\.(ERROR|CRITICAL|ALERT|EMERGENCY): [^{]{0,80}' | sort | uniq -c | sort -rn | head -10"
  ;;


  "33" )
  banner "ARTISAN on $PROJECT"
  need_param "artisan command (without the word 'artisan')"
  case "$PARAM2" in
    tinker*) echo "⚠ tinker needs an interactive PHP CLI; on many shared hosts SSH php is cgi-fcgi and it dies. Prefer 41/42 or a dedicated command." ;;
  esac
  remote_tty "php artisan $PARAM2"
  log_history "Ran artisan $PARAM2 on $PROJECT"
  ;;


  "34" )
  banner "CONFIG on $PROJECT"
  # Values are deliberately not printed for anything that looks like a
  # secret — this runs in a terminal that scrolls back.
  echo "--- environment ---"
  remote "grep -E '^(APP_ENV|APP_DEBUG|APP_URL|APP_TIMEZONE|SESSION_DRIVER|CACHE_STORE|QUEUE_CONNECTION|MAIL_MAILER|DB_CONNECTION|LOG_CHANNEL|LOG_LEVEL|FILESYSTEM_DISK)=' .env | sort"
  echo
  echo "--- keys present (values hidden) ---"
  remote "grep -oE '^[A-Z_]*(KEY|SECRET|TOKEN|PASSWORD)[A-Z_]*=' .env | tr -d '=' | sort | sed 's/^/  /'"
  echo
  echo "--- cached? (a cached config ignores .env edits until 24) ---"
  remote "for f in config routes-v7 events; do test -f bootstrap/cache/\$f.php && echo \"  \$f: cached\" || echo \"  \$f: not cached\"; done"
  ;;


  "35" )
  banner "DISK: quota and the biggest directories"
  remote "du -sh . 2>/dev/null; echo; echo 'largest directories:'; du -sh */ storage/*/ 2>/dev/null | sort -rh | head -15"
  echo
  echo "--- account quota (if the host reports it) ---"
  $APP_SSH "$REMOTE" "quota -s 2>/dev/null || df -h ~ 2>/dev/null | tail -n +1"
  ;;


  "36" )
  banner "LOGS: sizes on $PROJECT"
  remote "ls -lh storage/logs/ 2>/dev/null | tail -n +2; echo; echo 'total:'; du -sh storage/logs 2>/dev/null"
  ;;


  "37" )
  banner "VERSIONS on $PROJECT"
  remote "php -v | head -1; php artisan --version; composer --version 2>/dev/null | head -1 || echo 'composer: not on PATH'; node -v 2>/dev/null || echo 'node: not installed'; npm -v 2>/dev/null >/dev/null && echo \"npm \$(npm -v)\" || true; git --version"
  echo
  echo "--- local ---"
  php -v | head -1; composer --version 2>/dev/null | head -1; node -v 2>/dev/null
  ;;


  "38" )
  banner "PHP on $PROJECT"
  remote "php -r 'echo \"memory_limit=\", ini_get(\"memory_limit\"), \"  max_execution_time=\", ini_get(\"max_execution_time\"), \"  upload_max_filesize=\", ini_get(\"upload_max_filesize\"), \"  post_max_size=\", ini_get(\"post_max_size\"), PHP_EOL; echo \"opcache: \", (function_exists(\"opcache_get_status\") && opcache_get_status(false) ? \"enabled\" : \"off/unavailable\"), PHP_EOL;'"
  echo
  echo "--- extensions Laravel needs ---"
  remote "php -m | grep -iE '^(bcmath|ctype|curl|dom|fileinfo|filter|hash|mbstring|openssl|pcre|pdo|pdo_mysql|pdo_sqlite|session|tokenizer|xml|zip|gd|intl|redis)$' | tr '\n' ' '; echo"
  ;;


  "39" )
  banner "PROCESSES under the account"
  # Shared hosts cap concurrent processes; a stuck artisan run can exhaust
  # the limit and stop the cron silently.
  $APP_SSH "$REMOTE" "ps -o pid,etime,pcpu,rss,args -u \$(whoami) --sort=-etime 2>/dev/null | head -25 || ps aux | grep \$(whoami) | head -25"
  echo
  echo "Kill a hung one with: ./cx.sh 33 and a custom command, or ssh in (00) and kill <pid>."
  ;;


  #-------------------------------------------------------------
  # DATA
  #-------------------------------------------------------------

  "40" )
  banner "SEED: one class on $PROJECT"
  need_param "Seeder class (e.g. GameSeeder)"
  # A blanket db:seed is deliberately NOT offered here: it overwrites any
  # field a seeder also sets, silently clobbering admin edits.
  remote_tty "php artisan db:seed --class=$PARAM2 --force"
  log_history "Seeded $PARAM2 on $PROJECT"
  ;;


  "41" )
  banner "DATABASE: summary on $PROJECT"
  remote "php artisan db:show --counts"
  ;;


  "42" )
  banner "DATABASE: inspect one table"
  need_param "Table name (e.g. users)"
  remote "php artisan db:table $PARAM2"
  ;;


  "43" )
  banner "DATABASE: row counts"
  remote "php artisan db:show --counts | sed -n '/Table/,\$p'"
  ;;


  #-------------------------------------------------------------
  # QUEUE & SCHEDULE
  #-------------------------------------------------------------

  "50" )
  banner "QUEUE: failed jobs"
  remote "php artisan queue:failed"
  ;;


  "51" )
  banner "QUEUE: retry all failed jobs"
  remote_tty "php artisan queue:retry all"
  log_history "Retried failed jobs on $PROJECT"
  ;;


  "52" )
  banner "QUEUE: drain now"
  # --stop-when-empty so it exits instead of daemonising.
  remote_tty "php artisan queue:work --stop-when-empty --max-time=120"
  ;;


  "53" )
  banner "QUEUE: flush failed jobs"
  confirm "This deletes every failed job record on $PROJECT. Run 50 first and read them."
  remote "php artisan queue:flush"
  log_history "Flushed failed jobs on $PROJECT"
  ;;


  "54" )
  banner "SCHEDULE on $PROJECT"
  remote "php artisan schedule:list"
  echo
  read -r -p "Run the scheduler once now? [y/N] " runit
  if [ "$runit" = "y" ]; then
    remote_tty "php artisan schedule:run -v"
    log_history "Ran schedule:run on $PROJECT"
  fi
  ;;


  #-------------------------------------------------------------
  # MAINTENANCE
  #-------------------------------------------------------------

  "70" )
  banner "VERIFY: post-deploy checks on $PROJECT"
  remote "./deploy.sh --verify"
  ;;


  "71" )
  banner "MAINTENANCE MODE: ON"
  confirm "$APP_URL will return 503 to everyone until you run 72."
  SECRET=$(LC_ALL=C tr -dc 'a-z0-9' </dev/urandom | head -c 16)
  # --retry sets Retry-After so crawlers come back rather than de-indexing.
  # --secret lets you still browse the site at APP_URL/<secret>.
  remote_tty "php artisan down --retry=60 --secret=$SECRET"
  echo
  echo "Bypass for you: $APP_URL/$SECRET  (sets a cookie; then browse normally)"
  log_history "Maintenance mode ON for $PROJECT (secret $SECRET)"
  ;;


  "72" )
  banner "MAINTENANCE MODE: OFF"
  remote_tty "php artisan up"
  log_history "Maintenance mode OFF for $PROJECT"
  ;;


  "73" )
  banner "LOGS: rotate on $PROJECT"
  # Copy-then-truncate so the running PHP keeps its file handle valid.
  # Keeps 14 archives; older ones go.
  remote "set -e; cd storage/logs; if [ -s laravel.log ]; then cp laravel.log laravel-\$(date +%Y%m%d-%H%M%S).log && : > laravel.log && gzip laravel-*.log 2>/dev/null; fi; ls -t laravel-*.log.gz 2>/dev/null | tail -n +15 | xargs -r rm -f; ls -lh"
  log_history "Rotated logs on $PROJECT"
  ;;


  "74" )
  banner "LOGS: truncate the Laravel log"
  confirm "This discards the Laravel log on $PROJECT. Prefer 73 (rotate) unless you are out of disk."
  # Truncated in place rather than deleted: removing the file loses the
  # permissions the web user needs to recreate it.
  remote "test -f storage/logs/laravel.log && : > storage/logs/laravel.log && echo 'laravel.log truncated.' || echo 'No laravel.log to truncate.'"
  log_history "Truncated the Laravel log on $PROJECT"
  ;;


  "75" )
  banner "PRUNE: sessions, cache, failed jobs"
  # Each command is harmless if its driver is not file/database; they just
  # report nothing to do. The failed-job prune keeps the last 7 days.
  remote "php artisan cache:prune-stale-tags 2>/dev/null || true; php artisan model:prune 2>/dev/null || true; php artisan queue:prune-failed --hours=168; php artisan queue:prune-batches --hours=168 2>/dev/null || true; echo; echo 'file sessions older than 2 days:'; find storage/framework/sessions -type f -mtime +2 -print -delete 2>/dev/null | wc -l; echo 'compiled views cleared:'; php artisan view:clear"
  log_history "Pruned sessions/cache/failed jobs on $PROJECT"
  ;;


  "76" )
  banner "PERMISSIONS: storage/ and bootstrap/cache"
  # Shared hosting runs PHP as the account user, so owner-writable is enough;
  # 775 covers hosts where the web server runs as the account's group.
  remote "find storage bootstrap/cache -type d -exec chmod 775 {} + && find storage bootstrap/cache -type f -exec chmod 664 {} + && chmod 755 deploy.sh artisan && echo done && ls -ld storage storage/logs bootstrap/cache"
  log_history "Fixed permissions on $PROJECT"
  ;;


  "77" )
  banner "STORAGE LINK: public_html/storage"
  remote "rm -f public_html/storage && php artisan storage:link && ls -l public_html/storage"
  log_history "Regenerated storage link on $PROJECT"
  ;;


  "78" )
  banner "KEY ROTATE: new APP_KEY"
  confirm "A new APP_KEY invalidates every session and anything encrypted with the old key (encrypted casts, signed cookies). Back up .env first (91 copies it)."
  remote "cp .env .env.bak-\$(date +%Y%m%d-%H%M%S) && php artisan key:generate --force && php artisan config:cache && echo 'rotated; previous .env kept as .env.bak-*'"
  log_history "Rotated APP_KEY on $PROJECT"
  ;;


  #-------------------------------------------------------------
  # BACKUP
  #-------------------------------------------------------------

  "90" )
  banner "BACKUP: on the server"
  remote "ls -lh backup/ 2>/dev/null || echo 'No backup/ directory yet — run 91.'; echo; du -sh backup 2>/dev/null"
  ;;


  "91" )
  banner "BACKUP: database + uploads on $PROJECT"
  need_param "Backup description"
  echo "--- database ---"
  $APP_SSH "$REMOTE" "cd ~/$REMOTE_APP && bash -s -- '$PARAM2' <<'EOF'
$DB_DUMP_SNIPPET
EOF"
  echo
  echo "--- uploads and .env ---"
  NOTE=$(printf '%s' "$PARAM2" | tr -cs 'A-Za-z0-9_-' '-' | cut -c1-40)
  remote "stamp=\$(date +%Y%m%d-%H%M%S); tar -czf backup/\${stamp}-files${NOTE:+-$NOTE}.tar.gz .env storage/app 2>/dev/null && ls -lh backup/\${stamp}-files*"
  log_history "Backup database and files: $PARAM2"
  ;;


  "92" )
  banner "BACKUP: pull to $LOCAL_BACKUPS"
  mkdir -p "$LOCAL_BACKUPS"
  eval "$APP_RSYNC -avz --progress \"$REMOTE:~/$REMOTE_APP/backup/\" \"$LOCAL_BACKUPS/\""
  echo
  ls -lh "$LOCAL_BACKUPS" | tail -n +2
  log_history "Pulled backups to $LOCAL_BACKUPS"
  ;;


  "93" )
  banner "RESTORE: database dump on $PROJECT"
  remote "ls -1 backup/*.sql.gz backup/*.sqlite.gz 2>/dev/null || echo 'No dumps found.'"
  echo
  need_param "Dump filename (from the list above, without backup/)"
  confirm "This OVERWRITES the live database on $PROJECT with backup/$PARAM2. Take a fresh backup (91) first."
  # shellcheck disable=SC2016
  remote "set -e; test -f 'backup/$PARAM2'; php artisan down --retry=60;
    env_val() { grep -E \"^\$1=\" .env | head -1 | cut -d= -f2- | tr -d '\"'; }
    conn=\$(env_val DB_CONNECTION); conn=\${conn:-sqlite};
    case \"\$conn\" in
      sqlite) db=\$(env_val DB_DATABASE); db=\${db:-database/database.sqlite}; cp \"\$db\" \"\$db.pre-restore\";
        case 'backup/$PARAM2' in *.sqlite.gz) gunzip -c 'backup/$PARAM2' > \"\$db\" ;; *) rm -f \"\$db\" && gunzip -c 'backup/$PARAM2' | sqlite3 \"\$db\" ;; esac ;;
      mysql|mariadb) gunzip -c 'backup/$PARAM2' | MYSQL_PWD=\$(env_val DB_PASSWORD) mysql -h \"\$(env_val DB_HOST)\" -P \"\$(env_val DB_PORT)\" -u \"\$(env_val DB_USERNAME)\" \"\$(env_val DB_DATABASE)\" ;;
      pgsql) gunzip -c 'backup/$PARAM2' | PGPASSWORD=\$(env_val DB_PASSWORD) psql -q -h \"\$(env_val DB_HOST)\" -p \"\$(env_val DB_PORT)\" -U \"\$(env_val DB_USERNAME)\" \"\$(env_val DB_DATABASE)\" ;;
      *) echo unsupported; exit 1 ;;
    esac; php artisan optimize:clear >/dev/null; php artisan up; echo 'restored.'"
  log_history "Restored database from $PARAM2 on $PROJECT"
  ;;


  "94" )
  banner "BACKUP: prune on the server"
  need_param "Keep backups newer than how many days"
  remote "echo 'would remove:'; find backup -type f -mtime +$PARAM2 -print 2>/dev/null"
  confirm "Delete the files listed above from $PROJECT (older than $PARAM2 days)?"
  remote "find backup -type f -mtime +$PARAM2 -delete 2>/dev/null; du -sh backup"
  log_history "Pruned server backups older than $PARAM2 days"
  ;;


  #-------------------------------------------------------------
  # ACCESS
  #-------------------------------------------------------------

  "00" )
  banner "CONNECT: to $REMOTE"
  $APP_SSH "$REMOTE"
  log_history "Connect to remote"
  ;;


  "01" )
  banner "SETUP: deploy.sh on the server"
  # deploy.sh is in git so a pull brings it, but on a brand-new server
  # nothing has been pulled yet, and after a pull the exec bit may be lost
  # on hosts that strip it.
  $APP_SCP ./deploy.sh "$REMOTE:~/$REMOTE_APP/deploy.sh"
  remote "chmod 755 deploy.sh && ls -l deploy.sh && git remote -v | head -1"
  log_history "Copied deploy.sh to $PROJECT"
  ;;


  "02" )
  banner "CRON: current crontab on $REMOTE"
  $APP_SSH "$REMOTE" "crontab -l 2>/dev/null || echo '(no crontab)'"
  ;;


  "03" )
  banner "CRON: install scheduler and queue lines"
  PHP_BIN=$(remote "command -v php" | tr -d '\r')
  echo "Will add, if not already present:"
  echo "  * * * * * cd ~/$REMOTE_APP && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
  echo "  * * * * * cd ~/$REMOTE_APP && $PHP_BIN artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1"
  confirm "Modify the crontab on $REMOTE?"
  $APP_SSH "$REMOTE" "( crontab -l 2>/dev/null | grep -v 'artisan schedule:run' | grep -v 'artisan queue:work';
    echo '* * * * * cd ~/$REMOTE_APP && $PHP_BIN artisan schedule:run >> /dev/null 2>&1';
    echo '* * * * * cd ~/$REMOTE_APP && $PHP_BIN artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1' ) | crontab - && crontab -l"
  log_history "Installed cron lines on $PROJECT"
  ;;


  "05" )
  banner "MONITOR: Remote Top"
  $APP_SSH -t "$REMOTE" 'top'
  ;;


  #-------------------------------------------------------------
  # ADMINS
  #-------------------------------------------------------------

  "60" )
  banner "ADMINS on $PROJECT"
  remote "./deploy.sh --admins"
  ;;


  "61" )
  banner "ADMINS: grant on $PROJECT"
  need_param "email of the account to make an administrator"
  [[ "$PARAM2" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+$ ]] || { echo "That does not look like an email address."; exit 1; }
  remote "php artisan admin:grant '$PARAM2'"
  log_history "Granted admin to $PARAM2 on $PROJECT"
  ;;


  "62" )
  banner "ADMINS: revoke on $PROJECT"
  need_param "email of the administrator to demote"
  [[ "$PARAM2" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+$ ]] || { echo "That does not look like an email address."; exit 1; }
  confirm "Remove admin rights from $PARAM2 on $PROJECT? If it is listed in ADMIN_EMAILS, the next deploy grants it again."
  remote "php artisan admin:revoke '$PARAM2'"
  log_history "Revoked admin from $PARAM2 on $PROJECT"
  ;;


  "63" )
  banner "ADMINS: sync on $PROJECT"
  remote "php artisan config:clear >/dev/null && php artisan admin:sync && php artisan optimize >/dev/null"
  log_history "Synced admins on $PROJECT"
  ;;


  "64" )
  banner "ADMINS: open $APP_URL/admin"
  open "$APP_URL/admin" 2>/dev/null || xdg-open "$APP_URL/admin" 2>/dev/null || echo "$APP_URL/admin"
  ;;


  "qq" | "" | $'\e' )
  echo Quit
  exit 0
  ;;


  * )
  echo
  echo "Not a recognized option."
  ;;

esac
