#!/bin/bash
# replay.sh PORT LABEL — sign in as Acme's client at acme.exaact.test, then send THAT session id to beta.exaact.test
P=$1; LABEL=$2; J=$(mktemp); B=http://127.0.0.1:$P
tok() { grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/'; }
T=$(curl -s -c $J -b $J -H "Host: acme.exaact.test" "$B/portal/login" | tok)
curl -s -o /dev/null -c $J -b $J -H "Host: acme.exaact.test" -X POST "$B/portal/login" --data-urlencode "_csrf=$T" --data-urlencode "email=acme.buyer@test" --data-urlencode "password=acme-secret"
SID=$(grep PHPSESSID $J | awk '{print $7}')
show() { curl -s -L -H "Host: $1" -H "Cookie: PHPSESSID=$SID" "$B/portal" | sed -E 's/<[^>]+>/ /g' | tr -s ' ' | grep -oE "(Acme|Beta) (CONFIDENTIAL CLIENT|Buyer)|Sign in to|Client portal sign" | head -1; }
printf "  %-6s Acme's session at acme.exaact.test (its own company) → %s\n" "$LABEL" "$(show acme.exaact.test)"
printf "  %-6s the SAME session sent to beta.exaact.test             → %s\n" "$LABEL" "$(show beta.exaact.test)"
printf "  %-6s ...and Beta's invoices page with it: HTTP %s\n" "$LABEL" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: beta.exaact.test' -H "Cookie: PHPSESSID=$SID" "$B/portal/invoices")"
rm -f $J
