#!/bin/sh
# EXAACT audit — when an upload is REFUSED in the professional portal, is the
# professional told?
#
# upload_reject_reason() (lib/security.php) is strong: a size cap, banned
# extensions including the double-extension trick, <?php and shebang detection,
# a declared picture that must really be a picture, SVG refused outright and
# markup refused under any name. All three portal upload paths call it.
#
# The question this answers is the one after that: the /pro routes DISCARDED
# what those functions returned, so a refusal left the professional looking at
# a page with their file simply missing and no reason given -- on the documents
# screen, on a voucher attachment, and on the REPORT DELIVERABLE the client pays
# to receive.
#
# Usage (against a THROWAWAY seeded server — never production):
#   sh docs/audit-2026-10/evidence/pro-upload-feedback.sh http://127.0.0.1:8844 /tmp/pu.sqlite
BASE="${1:-http://127.0.0.1:8844}"
DB="${2:-/tmp/pu.sqlite}"
J=$(mktemp); D=$(mktemp -d)
say() { printf '%s\n' "$1"; }

# Three files the validator should refuse, and one it should accept.
printf '<?php echo "hi"; ?>' > "$D/notes.php"
printf '%%PDF-1.4 a real looking pdf' > "$D/ok.pdf"
printf '<!doctype html><html><script>alert(1)</script></html>' > "$D/page.html"
# One over php.ini's per-file cap but under the app's own, and one over the
# whole-POST cap -- two different failures that used to give two WRONG messages.
printf '%%PDF-1.4 ' > "$D/mid.pdf"; head -c 3000000 /dev/zero | tr '\0' 'A' >> "$D/mid.pdf"
head -c 14000000 /dev/zero | tr '\0' 'B' > "$D/huge.pdf"

say "PROFESSIONAL PORTAL — IS A REFUSED UPLOAD EXPLAINED?"
say "=============================================================================="

TOK=$(curl -s -c "$J" "$BASE/pro/login" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$J" -c "$J" -o /dev/null -X POST \
  -d "_csrf=$TOK&email=ajay.s02@demo.test&password=demo12345" "$BASE/pro/login"
say "  signed in as ajay.s02@demo.test"
say ""
printf '  %-30s %-10s %s\n' 'FILE OFFERED' 'STORED?' 'WHAT THE PROFESSIONAL IS TOLD'
say "  ----------------------------------------------------------------------------"

for f in notes.php page.html mid.pdf huge.pdf ok.pdf; do
  PTOK=$(curl -s -b "$J" -c "$J" "$BASE/pro/documents" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  # Counted from the DATABASE, not by grepping a redirected page: the first
  # version of this script counted links on the page and reported a successful
  # upload as refused.
  BEFORE=$(python3 -c "import sqlite3,sys;print(sqlite3.connect(sys.argv[1]).execute('SELECT COUNT(*) FROM cx_pro_files').fetchone()[0])" "$DB" 2>/dev/null || echo 0)
  OUT=$(curl -s -b "$J" -c "$J" -X POST -F "_csrf=$PTOK" -F "kind=OTHER" \
        -F "file=@$D/$f" "$BASE/pro/documents" -L)
  AFTER=$(python3 -c "import sqlite3,sys;print(sqlite3.connect(sys.argv[1]).execute('SELECT COUNT(*) FROM cx_pro_files').fetchone()[0])" "$DB" 2>/dev/null || echo 0)
  MSG=$(printf '%s' "$OUT" | grep -oE '<div class="msg[^"]*"[^>]*>[^<]*|<p class="muted"[^>]*>[^<]*' \
        | head -1 | sed 's/.*>//' | sed 's/^ *//;s/ *$//')
  [ "$AFTER" -gt "$BEFORE" ] && ST='yes' || ST='refused'
  printf '  %-30s %-10s %s\n' "$f" "$ST" "${MSG:-(nothing said)}"
done

say ""
say "A refusal with nothing said is the defect: the professional sees a page with"
say "their file missing and no reason, so they try again with the same file. The"
say "same discarded-result pattern applied to the voucher attachment and to the"
say "report deliverable the client pays to receive."
rm -rf "$J" "$D"
