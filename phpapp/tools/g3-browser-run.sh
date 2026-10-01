#!/usr/bin/env bash
# ============================================================================
#  GATE 3 — one command: build a throwaway workspace, seed the review scenario,
#  boot the app, drive it in a real browser at desktop and three phone widths.
#
#    bash tools/g3-browser-run.sh
#
#  Never touches the real database or any app code.
# ============================================================================
set -uo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8846}"
DB="${SQLITE_PATH:-/tmp/g3-browser.sqlite}"
BASE="http://127.0.0.1:${PORT}"
export DB_DRIVER=sqlite SQLITE_PATH="$DB"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"
if [ -z "${NODE_PATH:-}" ]; then
  for c in /opt/node22/lib/node_modules "$(npm root -g 2>/dev/null)" ./node_modules; do
    [ -n "$c" ] && [ -d "$c/playwright" ] && export NODE_PATH="$c" && break
  done
fi
say(){ printf '\n\033[1;36m== %s\033[0m\n' "$1"; }

#  NEVER TALK TO A SERVER THIS RUN DID NOT START.
#
#  php -S fails quietly when the port is already held, the script carries on, and
#  the browser then reads a PREVIOUS run's database while the seeder reports the new
#  one. That produced a run whose numbers disagreed with its own seed, which is
#  worse than a failure: it is evidence that cannot be trusted. So the port must be
#  free before anything else happens.
if curl -s -o /dev/null --max-time 2 "${BASE}/login" 2>/dev/null; then
  echo "  port ${PORT} is already serving something — refusing to run against it."
  echo "  stop it first (pkill -f 'php -S 127.0.0.1:${PORT}') or set PORT=…"
  exit 1
fi

say "1/4  Throwaway workspace"
rm -f "$DB" "$DB-wal" "$DB-shm"
php tools/seed-scenario-s01.php >/dev/null 2>&1 && echo "  base scenario loaded"

say "2/4  Seeding the Gate 3 review scenario"
IDS="$(php tools/g3-seed.php 2>&1 | tail -1)"
echo "  $IDS"
case "$IDS" in REQ=*) ;; *) echo "  SEED FAILED"; exit 1;; esac
#  THE SCENARIO IS WHAT IT CLAIMS TO BE, or this run says nothing. Four of the six
#  candidates must be in review: the two that must NOT be (an issued offer, and
#  somebody already closed) are the whole point of the A2 and §18 checks below.
case "$IDS" in *OPEN=4*) ;; *) echo "  SEED PRODUCED THE WRONG SCENARIO — aborting"; exit 1;; esac

say "3/4  Booting the app on ${BASE}"
php -S 127.0.0.1:"$PORT" tools/smoke-router.php >/tmp/g3-server.log 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null' EXIT
for i in $(seq 1 25); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login" 2>/dev/null || true)
  [ "$code" = "200" ] && break; sleep 0.3
done
echo "  server up (HTTP ${code:-?})"

say "4/4  Driving the review workflow in Chromium"
node tools/g3-browser-check.js "$BASE" admin "$ADMIN_PASSWORD" "$IDS"
rc=$?
#  And the server this run started goes away with it, so the next run starts clean.
kill "$SERVER" 2>/dev/null
for i in $(seq 1 20); do curl -s -o /dev/null --max-time 1 "${BASE}/login" 2>/dev/null || break; sleep 0.2; done
exit "$rc"
