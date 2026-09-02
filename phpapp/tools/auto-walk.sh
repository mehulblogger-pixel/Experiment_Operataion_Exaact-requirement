#!/usr/bin/env bash
# ============================================================================
#  auto-walk.sh — one command that drives the WHOLE app as every role and
#  reports any broken screen. The "find failures automatically" layer.
#
#    bash tools/auto-walk.sh
#
#  It: builds a throwaway seeded SQLite DB (never your real data), boots the
#  app on it, crawls the internal ops app (smoke.js, as admin) AND the
#  portal/professional surfaces (portal-crawl.js, as every demo user), then
#  tears the server down. Exit 0 = every screen renders; exit 1 = something is
#  broken, and the URL + error text is printed above the summary.
#
#  Nothing here touches the real database, the default branch, or any app code
#  — it only reads the app through a browser.
# ============================================================================
set -uo pipefail
cd "$(dirname "$0")/.."                      # -> phpapp/

PORT="${PORT:-8811}"
DB="${SQLITE_PATH:-/tmp/auto-walk.sqlite}"
BASE="http://127.0.0.1:${PORT}"
export DB_DRIVER=sqlite
export SQLITE_PATH="$DB"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"

# Locate a node_modules that has playwright (env override wins).
if [ -z "${NODE_PATH:-}" ]; then
  for c in /opt/node22/lib/node_modules "$(npm root -g 2>/dev/null)" ./node_modules; do
    if [ -n "$c" ] && [ -d "$c/playwright" ]; then export NODE_PATH="$c"; break; fi
  done
fi

say(){ printf '\n\033[1;36m== %s\033[0m\n' "$1"; }

say "1/4  Building throwaway seeded database"
rm -f "$DB"
php tools/seed-scenario-s01.php >/dev/null 2>&1 && echo "  S01 loaded"
php tools/seed-scenario-s02.php >/dev/null 2>&1 && echo "  S02 loaded"
php tools/seed-scenario-s03.php >/dev/null 2>&1 && echo "  S03 loaded"
php tools/seed-scenario-s04.php >/dev/null 2>&1 && echo "  S04 loaded"

say "2/4  Booting app on ${BASE}"
php -S 127.0.0.1:"$PORT" tools/smoke-router.php >/tmp/auto-walk-server.log 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null' EXIT
for i in $(seq 1 20); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login" 2>/dev/null || true)
  [ "$code" = "200" ] && break; sleep 0.3
done
echo "  server up (HTTP ${code:-?})"

rc=0
say "3/4  Crawling internal ops app (as admin)"
node tools/smoke.js "$BASE" admin "$ADMIN_PASSWORD" || rc=1

say "4/4  Crawling portal + professional surfaces (as every demo user)"
node tools/portal-crawl.js "$BASE" || rc=1

say "RESULT"
if [ "$rc" = "0" ]; then
  echo -e "\033[1;32m  ALL SCREENS RENDER CLEANLY across every role.\033[0m"
else
  echo -e "\033[1;31m  BROKEN SCREENS FOUND — see the URLs and error text above.\033[0m"
fi
exit "$rc"
