#!/usr/bin/env bash
# ============================================================================
#  cold-start.sh — test the ACTUAL first-run scenario a brand-new customer hits,
#  from an EMPTY database:
#    1. the public first-run SCREENS render for a stranger (login, company join,
#       professional register/login) — no data seeded;
#    2. the onboarding LOGIC works end-to-end (install detection → wizard →
#       multi-capability company → professional self-register → first requirement).
#
#    bash tools/cold-start.sh
#
#  The demo seeds inject data directly and auto-walk crawls a SEEDED database, so
#  neither exercises this. Uses a throwaway /tmp database; never touches real data.
# ============================================================================
set -uo pipefail
cd "$(dirname "$0")/.."                      # -> phpapp/

PORT="${PORT:-8815}"
DB="${SQLITE_PATH:-/tmp/cold-start.sqlite}"
BASE="http://127.0.0.1:${PORT}"
export DB_DRIVER=sqlite
export SQLITE_PATH="$DB"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"

say(){ printf '\n\033[1;36m== %s\033[0m\n' "$1"; }
rc=0

say "1/3  Empty database (no seed) — a genuine first boot"
rm -f "$DB"

say "2/3  Do the first-run SCREENS render for a stranger?"
php -S 127.0.0.1:"$PORT" tools/smoke-router.php >/tmp/cold-server.log 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null' EXIT
for i in $(seq 1 20); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login" 2>/dev/null || true)
  [ "$code" = "200" ] && break; sleep 0.3
done
FATAL='Fatal error|Parse error|Uncaught|SQLSTATE|Undefined variable|Undefined array key|call to a member|The app hit an error'
for path in /login /join /pro/register /pro/login /pro/forgot; do
  body=$(curl -s "$BASE$path" 2>/dev/null)
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$path" 2>/dev/null || echo 000)
  if [ "$code" -ge 500 ] 2>/dev/null; then echo "  ✗ $path -> HTTP $code"; rc=1
  elif echo "$body" | grep -qE "$FATAL"; then echo "  ✗ $path -> render error"; rc=1
  else echo "  ✓ $path -> HTTP $code, renders"; fi
done
kill "$SERVER" 2>/dev/null; trap - EXIT

say "3/3  Does onboarding WORK end-to-end from empty?"
rm -f "$DB"
php tools/cold-start.php || rc=1

say "RESULT"
if [ "$rc" = "0" ]; then
  echo -e "\033[1;32m  COLD START OK — a new company can land, onboard and start work from scratch.\033[0m"
else
  echo -e "\033[1;31m  COLD START BROKEN — see the failures above.\033[0m"
fi
rm -f "$DB"
exit "$rc"
