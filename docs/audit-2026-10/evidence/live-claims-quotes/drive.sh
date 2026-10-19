#!/bin/bash
# drive.sh BASE DBFILE LABEL HOURS1 HOURS2 — log in, POST the claim sheet, read back what was stored
BASE=$1; DB=$2; LABEL=$3; H1=$4; H2=$5; J=$(mktemp)
tok() { grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/'; }
T=$(curl -s -c $J -b $J "$BASE/login" | tok)
curl -s -o /dev/null -c $J -b $J -X POST "$BASE/login" --data-urlencode "_csrf=$T" --data-urlencode "username=live.admin" --data-urlencode "password=demo12345"
T=$(curl -s -c $J -b $J "$BASE/voucher?id=1" | tok)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -c $J -b $J -X POST "$BASE/voucher-save?id=1" --data-urlencode "_csrf=$T" \
   --data-urlencode "rows[1][hours]=$H1" --data-urlencode "rows[2][hours]=$H2")
PAGE=$(curl -s -c $J -b $J "$BASE/voucher?id=1")
MSG=$(echo "$PAGE" | grep -oE 'role="(alert|status)"[^>]*>[^<]{0,220}' | sed -E 's/.*>//' | head -2 | tr '\n' ' ')
STORED=$(php -r '$p=new PDO("sqlite:".$argv[1]); $r=$p->query("SELECT hours FROM voucher_entries WHERE voucher_id=1 AND entry_date=\x272097-07-05\x27 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN); echo implode(" + ",array_map("floatval",$r))." = ".array_sum($r);' "$DB")
printf "  %-7s posted %5s + %5s → stored %-18s | message: %s\n" "$LABEL" "$H1" "$H2" "$STORED" "${MSG:-(none)}"
rm -f $J
