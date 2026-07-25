#!/bin/sh
# Lint EVERY PHP file in the app, not just the ones that changed.
#
# Why every file: a find/replace sweep across the codebase can inject a parse
# error into a file that is not part of the change you are thinking about, and
# `git diff --name-only` returns nothing once files are staged. Always sweep.
#
# Usage:  sh tools/lint.sh          (run from the phpapp directory)
# Exit 0 = all files parse, exit 1 = at least one file is broken.

cd "$(dirname "$0")/.." || exit 1

bad=0
count=0
for f in $(find . -name '*.php' -not -path './data/*'); do
    count=$((count + 1))
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "BROKEN: $f"
        php -l "$f" 2>&1 | head -3
        bad=$((bad + 1))
    fi
done

if [ "$bad" -eq 0 ]; then
    echo "OK - $count PHP files parse cleanly."
    exit 0
fi

echo "FAILED - $bad of $count PHP files have syntax errors."
exit 1
