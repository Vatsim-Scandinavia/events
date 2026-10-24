#!/usr/bin/env bash
# serve.sh [fresh|keep|stop]
#   fresh (default): new SQLite database, migrate, roles:sync, seed, then serve on :8123
#   keep: serve the existing database again
#   stop: stop the server
# The database lives in $RUN_EVENTS_HOME, never database/database.sqlite.
set -euo pipefail
HOME_DIR="${RUN_EVENTS_HOME:-$HOME/.cache/run-events}"
UNIT="$(cd "$(dirname "$0")/../../.." && pwd)"
PORT="${PORT:-8123}"
mode="${1:-fresh}"
mkdir -p "$HOME_DIR"
cd "$UNIT"

lsof -ti:"$PORT" -sTCP:LISTEN | xargs -r kill || true
[ "$mode" = stop ] && exit 0

export DB_CONNECTION=sqlite DB_DATABASE="$HOME_DIR/dev.sqlite" AUTH_DEV_LOGIN=true \
    APP_URL="http://127.0.0.1:$PORT" DEBUGBAR_ENABLED=false
php artisan config:clear -q

if [ "$mode" = fresh ]; then
    rm -f "$DB_DATABASE" && touch "$DB_DATABASE"
    php artisan migrate:fresh --force -q
    php artisan db:seed --force -q
fi

# Rebuild when anything under resources/ is newer than the last build.
if [ ! -f public/build/manifest.json ] || [ -n "$(find resources vite.config.js -newer public/build/manifest.json -print -quit)" ]; then
    npm run build
fi
# public/hot makes pages load assets from Vite. Vite leaves it behind when it's killed, so drop
# it unless Vite is really answering.
if [ -f public/hot ] && ! curl -sf -o /dev/null --max-time 3 "$(cat public/hot)/@vite/client"; then
    echo "removing stale public/hot ($(cat public/hot) is not answering)"
    rm public/hot
fi

# What `php artisan serve` runs, plus opcache: on /mnt/c (WSL) every request otherwise
# re-reads the framework from the Windows drive and takes ~6s. With timestamps off, PHP
# edits need a restart (serve.sh keep).
cd public
nohup php -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 \
    -S "127.0.0.1:$PORT" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php \
    > "$HOME_DIR/serve.log" 2>&1 &
cd ..
timeout 60 bash -c "until curl -sf -o /dev/null http://127.0.0.1:$PORT/up; do sleep 1; done"
curl -sf -o /dev/null "http://127.0.0.1:$PORT/login"
echo "serving http://127.0.0.1:$PORT (db $DB_DATABASE, log $HOME_DIR/serve.log)"
