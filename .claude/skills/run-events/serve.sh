#!/usr/bin/env bash
# serve.sh [fresh|keep|stop]
#   fresh (default): new SQLite database, migrate, roles:sync, seed, then serve on :$PORT (8123)
#   keep: serve the existing database again
#   stop: stop the server
# The database lives in $RUN_EVENTS_HOME, never database/database.sqlite.
set -euo pipefail
mkdir -p "${RUN_EVENTS_HOME:-$HOME/.cache/run-events}"
HOME_DIR="$(cd "${RUN_EVENTS_HOME:-$HOME/.cache/run-events}" && pwd)"
UNIT="$(cd "$(dirname "$0")/../../.." && pwd)"
PORT="${PORT:-8123}"
PID_FILE="$HOME_DIR/server-$PORT.pid"
mode="${1:-fresh}"
cd "$UNIT"

# One start or stop at a time, so two fresh starts can't seed the same database at once.
exec 9>"$HOME_DIR/serve.lock"
flock 9

listening() { (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null; }

# Only stop the server this script started; anything else on the port is left alone.
if [ -f "$PID_FILE" ]; then
    pid=$(cat "$PID_FILE")
    if grep -qs 'Foundation/resources/server.php' "/proc/$pid/cmdline"; then
        kill "$pid"
        timeout 10 bash -c "while kill -0 $pid 2>/dev/null; do sleep 0.2; done"
    fi
    rm -f "$PID_FILE"
fi
[ "$mode" = stop ] && exit 0
if listening; then
    echo "port $PORT is in use by something serve.sh didn't start; stop it or use PORT=<other>" >&2
    exit 1
fi

# AUTH_DEV_LOGIN only works with APP_ENV local or testing.
export APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="$HOME_DIR/dev.sqlite" AUTH_DEV_LOGIN=true \
    APP_URL="http://127.0.0.1:$PORT" DEBUGBAR_ENABLED=false
php artisan config:clear -q

if [ "$mode" = fresh ]; then
    rm -f "$DB_DATABASE" && touch "$DB_DATABASE"
    php artisan migrate:fresh --force -q
    php artisan db:seed --force -q
fi

# Rebuild when the frontend or its dependencies are newer than the last build.
if [ ! -f public/build/manifest.json ] || [ -n "$(find resources vite.config.js package.json package-lock.json -newer public/build/manifest.json -print -quit)" ]; then
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
    > "$HOME_DIR/serve.log" 2>&1 9>&- &
pid=$!
echo "$pid" > "$PID_FILE"
cd ..
timeout 60 bash -c "until curl -sf -o /dev/null http://127.0.0.1:$PORT/up; do kill -0 $pid || exit 1; sleep 1; done" || {
    echo "server didn't start; see $HOME_DIR/serve.log" >&2
    exit 1
}
curl -sf -o /dev/null "http://127.0.0.1:$PORT/login"
echo "serving http://127.0.0.1:$PORT (pid $pid, db $DB_DATABASE, log $HOME_DIR/serve.log)"
