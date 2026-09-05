#!/bin/sh
# ---------------------------------------------------------------------------
#  MGH Hire — build the shippable package.
#
#  Produces one dated, versioned zip (+ tar.gz) and a sha256 checksum from the
#  TRACKED files only — no data.sqlite, no config.local.php, no logs. Refuses
#  to build from a dirty tree so a customer never receives a half-committed app.
#
#  Usage:  sh tools/release.sh  [output-dir]     (default ../dist)
# ---------------------------------------------------------------------------
set -e
cd "$(dirname "$0")/.."
OUT=${1:-../dist}

DIRTY=$(git status --porcelain -- . | grep -v '^?? \.\./' || true)
if [ -n "$DIRTY" ]; then
    echo "REFUSING TO BUILD — uncommitted changes would be MISSING from the package:"
    echo "$DIRTY" | sed 's/^/    /'
    echo "Commit (or stash) first, then run again."
    exit 1
fi

VER=$(php -r 'require "lib/version.php"; echo MGHHIRE_VERSION;')
NAME="mgh-hire-$VER"
STAGE="$OUT/$NAME"
echo "Building $NAME"
rm -rf "$STAGE" "$OUT/$NAME.zip" "$OUT/$NAME.tar.gz" "$OUT/$NAME.sha256"
mkdir -p "$STAGE"

# Tracked files only.
git ls-files . | while read -r f; do
    mkdir -p "$STAGE/$(dirname "$f")"
    cp "$f" "$STAGE/$f"
done

cat > "$STAGE/VERSION.txt" <<EOF
$NAME
Built: $(date -u '+%Y-%m-%d %H:%M UTC')

Install:  see INSTALL.md  (laptop = double-click start; server = upload + open)
Upgrade:  copy these files over the old ones and open any page. Back up first.
          Never overwrite config.local.php or data.sqlite.
EOF

( cd "$OUT" && tar -czf "$NAME.tar.gz" "$NAME" )
if command -v zip >/dev/null 2>&1; then ( cd "$OUT" && zip -qr "$NAME.zip" "$NAME" ); fi
( cd "$OUT" && for f in "$NAME".tar.gz "$NAME".zip; do [ -f "$f" ] && sha256sum "$f" >> "$NAME.sha256"; done ) || true
rm -rf "$STAGE"

echo
echo "Wrote:"; ls -la "$OUT" | grep "$NAME" || true
echo
echo "Give the customer the archive AND the .sha256 line for it."
