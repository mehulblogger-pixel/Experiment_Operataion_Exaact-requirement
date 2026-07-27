#!/bin/sh
# ---------------------------------------------------------------------------
#  Build the shippable package.
#
#  "Copy whatever git has" is not a release: it ships whatever happened to be
#  in the folder, has no version on it, and gives the customer nothing to check
#  the download against. This produces one dated, versioned archive plus a
#  checksum, from the tracked files only — no data.sqlite, no logs, no local
#  router.php.
#
#  Usage:  sh tools/release.sh  [output-dir]
# ---------------------------------------------------------------------------
set -e
cd "$(dirname "$0")/.."
OUT=${1:-../dist}

# The version is the one the app itself reports, so a package can never be
# labelled differently from what it will tell a support caller.
# Refuse to build from a dirty tree. The copy below takes TRACKED files only,
# so an uncommitted new file is silently left out and the customer receives a
# package that dies on its first page load with "a program file is missing".
# That was not hypothetical — it happened the first time this script was run.
DIRTY=$(git status --porcelain -- . | grep -v '^?? \.\./' || true)
if [ -n "$DIRTY" ]; then
    echo "REFUSING TO BUILD — the working tree has changes that are not committed."
    echo "Only tracked files go into the package, so anything below would be MISSING"
    echo "from the customer's copy:"
    echo "$DIRTY" | sed 's/^/    /'
    echo
    echo "Commit (or stash) first, then run this again."
    exit 1
fi

VER=$(php -r 'require "lib/preflight.php"; echo APP_VERSION;')
NAME="exaact-$VER"
STAGE="$OUT/$NAME"

echo "Building $NAME"
rm -rf "$STAGE" "$OUT/$NAME.zip" "$OUT/$NAME.tar.gz"
mkdir -p "$STAGE"

# Tracked files only. Anything not in git is, by definition, not part of the
# product — that is what keeps a stray test database out of a customer's server.
git ls-files . | while read -r f; do
    mkdir -p "$STAGE/$(dirname "$f")"
    cp "$f" "$STAGE/$f"
done

# The one file every installation must edit, shipped as a template so an
# upgrade never overwrites the customer's own credentials.
cp "$STAGE/config.php" "$STAGE/config.sample.php"

cat > "$STAGE/VERSION.txt" <<EOF
$NAME
Built: $(date -u '+%Y-%m-%d %H:%M UTC')

Install:   see INSTALL.md
Upgrade:   copy these files over the existing ones and open any page.
           Take a database dump first. Do NOT overwrite config.php.
Check:     sign in, then open Settings -> Server check.
EOF

( cd "$OUT" && tar -czf "$NAME.tar.gz" "$NAME" )
if command -v zip >/dev/null 2>&1; then ( cd "$OUT" && zip -qr "$NAME.zip" "$NAME" ); fi

# A checksum the customer can verify before trusting the upload.
( cd "$OUT" && for f in "$NAME".tar.gz "$NAME".zip; do
    [ -f "$f" ] && sha256sum "$f" >> "$NAME.sha256"
  done ) || true

rm -rf "$STAGE"
echo
echo "Wrote:"
ls -la "$OUT" | grep "$NAME" || true
echo
echo "Give the customer the archive AND the .sha256 line for it."
