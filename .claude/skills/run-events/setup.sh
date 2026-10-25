#!/usr/bin/env bash
# One-time: Playwright + Chromium + the system libraries Chromium needs, all under
# $RUN_EVENTS_HOME (default ~/.cache/run-events) so neither the repo nor the system changes.
set -euo pipefail
mkdir -p "${RUN_EVENTS_HOME:-$HOME/.cache/run-events}"
HOME_DIR="$(cd "${RUN_EVENTS_HOME:-$HOME/.cache/run-events}" && pwd)"
export PLAYWRIGHT_BROWSERS_PATH="$HOME_DIR/browsers"
mkdir -p "$HOME_DIR/libs/debs" "$HOME_DIR/libs/root"
cd "$HOME_DIR"

[ -f package.json ] || npm init -y >/dev/null
[ -d node_modules/playwright ] || npm install --silent playwright@1.49.1
PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS=1 npx playwright install chromium >/dev/null

# Without sudo, missing .so files are fetched with apt-get download and unpacked locally.
shell=$(ls -d "$PLAYWRIGHT_BROWSERS_PATH"/chromium_headless_shell-*/chrome-linux/headless_shell | tail -1)
export LD_LIBRARY_PATH="$HOME_DIR/libs/root/usr/lib/x86_64-linux-gnu"
missing=$(ldd "$shell" | awk '/not found/ {print $1}')
if [ -n "$missing" ]; then
    echo "missing: $missing"
    cd libs/debs
    pkgs=""
    for lib in $missing; do
        case "$lib" in
            libnss3*|libnssutil3*|libsmime3*) pkgs="$pkgs libnss3" ;;
            libnspr4*|libplc4*|libplds4*) pkgs="$pkgs libnspr4" ;;
            libasound*) pkgs="$pkgs libasound2t64" ;;
            *) echo "no package mapped for $lib; add it to setup.sh" >&2; exit 1 ;;
        esac
    done
    apt-get download $(echo $pkgs | tr ' ' '\n' | sort -u)
    for deb in *.deb; do dpkg -x "$deb" ../root; done
    cd ../..
    still=$(ldd "$shell" | awk '/not found/ {print $1}')
    [ -z "$still" ] || { echo "still missing: $still" >&2; exit 1; }
fi
echo "ready: $HOME_DIR"
