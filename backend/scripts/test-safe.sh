#!/usr/bin/env bash

set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 1

CACHE="$ROOT/bootstrap/cache/config.php"
BACKUP=""
HAD_CACHE=0

restore_cache() {
    if [ "$HAD_CACHE" -eq 1 ] && [ -n "$BACKUP" ] && [ -f "$BACKUP" ]; then
        mv -f "$BACKUP" "$CACHE"
        echo
        echo "===== PRODUCTION CONFIG CACHE RESTORED ====="
    fi
}

trap restore_cache EXIT INT TERM HUP

echo "===== CRYPTICX SAFE TEST RUNNER ====="

if [ -f "$CACHE" ]; then
    BACKUP="$(mktemp /tmp/crypticx-config.XXXXXX.php)" || exit 1
    cp -a "$CACHE" "$BACKUP" || exit 1
    HAD_CACHE=1

    rm -f "$CACHE" || exit 1
    echo "Production config cache temporarily isolated."
else
    echo "No production config cache present."
fi

echo
echo "===== SAFETY PRECHECK ====="

APP_ENV=testing php artisan env || exit 1

echo
echo "===== RUNNING TESTS ====="

APP_ENV=testing php artisan test "$@"
TEST_EXIT=$?

echo
echo "===== TEST EXIT CODE: $TEST_EXIT ====="

exit "$TEST_EXIT"
