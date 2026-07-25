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

if [ "$bad" -ne 0 ]; then
    echo "FAILED - $bad of $count PHP files have syntax errors."
    exit 1
fi
echo "OK - $count PHP files parse cleanly."

# A file can parse perfectly and still be broken: a find/replace sweep can drop
# a `<?= ... ?>` inside a quoted string, where it becomes literal text in a SQL
# query or an e-mail body. php -l cannot see that, so check it separately.
php tools/check-strings.php || exit 1

# And a file can parse perfectly, contain no stray tags, and still take a screen
# down: two files declaring the same function name fatal with "Cannot redeclare"
# the moment both are loaded. php -l never sees it, because each file is fine on
# its own — which is exactly how views/detail.php shipped with its own fdate()
# and killed the client and vendor pages while every other screen worked.
php tools/check-dupes.php || exit 1
