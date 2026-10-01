#!/usr/bin/env bash
# ============================================================================
#  GATE 6B — one command: throwaway workspace, seed the workforce activation scenario, boot,
#  drive it in a real browser at desktop and three phone widths.
#
#    bash tools/g5-browser-run.sh
#
#  Never touches the real database or any app code.
# ============================================================================
set -uo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8852}"
DB="${SQLITE_PATH:-/tmp/g6b-browser.sqlite}"
BASE="http://127.0.0.1:${PORT}"
export DB_DRIVER=sqlite SQLITE_PATH="$DB"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"
if [ -z "${NODE_PATH:-}" ]; then
  for c in /opt/node22/lib/node_modules "$(npm root -g 2>/dev/null)" ./node_modules; do
    [ -n "$c" ] && [ -d "$c/playwright" ] && export NODE_PATH="$c" && break
  done
fi
say(){ printf '\n\033[1;36m== %s\033[0m\n' "$1"; }

#  Never talk to a server this run did not start — a held port means the browser
#  reads a previous run's database while the seeder reports the new one.
if curl -s -o /dev/null --max-time 2 "${BASE}/login" 2>/dev/null; then
  echo "  port ${PORT} is already serving something — refusing to run against it."
  exit 1
fi

say "1/4  Throwaway workspace"
rm -f "$DB" "$DB-wal" "$DB-shm"
php tools/seed-scenario-s01.php >/dev/null 2>&1 && echo "  base scenario loaded"

say "2/4  Seeding the Gate 6B R1-UI / R2 scenario"
IDS="$(php tools/g6b-seed.php 2>&1 | tail -1)"
echo "  $IDS"
case "$IDS" in TAG=*) ;; *) echo "  SEED FAILED"; exit 1;; esac
case "$IDS" in *PENDCOUNT=0*) echo "  SEED PRODUCED NO JOINING-PENDING PERSON — aborting"; exit 1;; esac

say "3/4  Booting the app on ${BASE}"
php -S 127.0.0.1:"$PORT" tools/smoke-router.php >/tmp/g6b-server.log 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null' EXIT
for i in $(seq 1 25); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login" 2>/dev/null || true)
  [ "$code" = "200" ] && break; sleep 0.3
done
echo "  server up (HTTP ${code:-?})"

say "4/4  Driving the trigger screen and the utilisation breakdown in Chromium"
node tools/g6b-browser-check.js "$BASE" admin "$ADMIN_PASSWORD" "$IDS"
rc=$?
kill "$SERVER" 2>/dev/null
for i in $(seq 1 20); do curl -s -o /dev/null --max-time 1 "${BASE}/login" 2>/dev/null || break; sleep 0.2; done
exit "$rc"
