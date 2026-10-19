#!/bin/sh
# EXAACT audit — is the PROFESSIONAL portal protected against a forged
# cross-site request, as the client and vendor portals are?
#
# The three portals are dispatched BEFORE require_login(), so nothing
# downstream applies the application's CSRF gate. portal.php and cvp.php each
# carry their own (portal_csrf_or_die / cvp_vendor_csrf_or_die, and both stamp a
# token into every POST form). connect_pro.php carried NEITHER -- its forms had
# no token and its routes checked for none.
#
# This drives the real application over HTTP: sign in as a seeded professional,
# then POST a profile change with NO token and a hostile Referer, exactly as a
# malicious page in another tab would. Then repeat WITH a token to prove the
# fix did not simply break the portal.
#
# Usage (against a THROWAWAY seeded server — never production):
#   sh docs/audit-2026-10/evidence/pro-csrf-probe.sh http://127.0.0.1:8843 /tmp/pv.sqlite
BASE="${1:-http://127.0.0.1:8843}"
DB="${2:-/tmp/pv.sqlite}"
USER='ajay.s02@demo.test'
J=$(mktemp)

# NOT named `head`: an earlier version of this script defined a shell function
# by that name, which shadowed the `head` COMMAND used in `| head -1` when
# extracting the tokens. Both tokens came out as garbage, so the legitimate
# request was refused too and the script reported the fix as having broken the
# portal. It had not -- the same steps by hand returned 200.
say() { printf '%s\n' "$1"; }
show() { python3 - "$DB" <<'PY'
import sqlite3, sys
c = sqlite3.connect(sys.argv[1])
print('      headline now:', repr(c.execute(
  "SELECT headline FROM cx_professionals WHERE email='ajay.s02@demo.test'").fetchone()[0]))
PY
}

say "PROFESSIONAL PORTAL — CROSS-SITE REQUEST FORGERY"
say "=============================================================================="

TOK=$(curl -s -c "$J" "$BASE/pro/login" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
say "  login form carries a token : $([ -n "$TOK" ] && echo yes || echo 'NO — forms are unstamped')"
curl -s -b "$J" -c "$J" -o /dev/null -X POST \
  -d "_csrf=$TOK&email=$USER&password=demo12345" "$BASE/pro/login"
say "  signed in as $USER"
show

say ""
say "  (1) FORGED: a POST with no _csrf field, Referer from another site"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' -X POST \
  -H 'Referer: https://evil.example/attack' \
  -d 'name=Ajay&headline=FORGED-NO-TOKEN&mobile=9000000000' "$BASE/pro/profile")
say "      HTTP $CODE"
show

say ""
say "  (2) LEGITIMATE: the same change, with the token the page supplies"
PTOK=$(curl -s -b "$J" -c "$J" "$BASE/pro/profile" | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
say "      profile form carries a token : $([ -n "$PTOK" ] && echo yes || echo 'NO')"
CODE2=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' -X POST \
  -d "_csrf=$PTOK&name=Ajay&headline=LEGITIMATE-WITH-TOKEN&mobile=9000000000" "$BASE/pro/profile")
say "      HTTP $CODE2"
show

say ""
say "A protected portal REFUSES (1) and ACCEPTS (2). If (1) lands, every POST"
say "route here is forgeable: rates and contact details, document deletion,"
say "withdrawing applications, revealing contact details to a client, and"
say "confirming a payment was received -- the handshake that releases the"
say "deliverable and settles the engagement."
rm -f "$J"
