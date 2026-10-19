#!/bin/bash
# quote.sh BASE DB LABEL CASE GST QTY1 RATE1 QTY2 RATE2
BASE=$1; DB=$2; LABEL=$3; CASE=$4; GST=$5; J=$(mktemp)
tok() { grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/'; }
T=$(curl -s -c $J -b $J "$BASE/login" | tok); curl -s -o /dev/null -c $J -b $J -X POST "$BASE/login" --data-urlencode "_csrf=$T" --data-urlencode "username=live.admin" --data-urlencode "password=demo12345"
T=$(curl -s -c $J -b $J "$BASE/quote-new" | tok)
BEFORE=$(php -r '$p=new PDO("sqlite:".$argv[1]); echo $p->query("SELECT COUNT(*) FROM quotations")->fetchColumn();' "$DB")
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -c $J -b $J -X POST "$BASE/quote-new" --data-urlencode "_csrf=$T" \
  --data-urlencode "client_name=Live Probe Co" --data-urlencode "subject=$CASE" --data-urlencode "gst_pct=$GST" \
  --data-urlencode "l_desc[]=Inspection" --data-urlencode "l_qty[]=$6" --data-urlencode "l_rate[]=$7" \
  --data-urlencode "l_desc[]=Second line" --data-urlencode "l_qty[]=$8" --data-urlencode "l_rate[]=$9")
ROW=$(php -r '$p=new PDO("sqlite:".$argv[1]); $q=$p->query("SELECT COUNT(*) n FROM quotations")->fetchColumn(); if ($q > $argv[2]) { $r=$p->query("SELECT subtotal, gst_pct, total_amount FROM quotations ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC); echo "SAVED subtotal ".$r["subtotal"]." @ ".$r["gst_pct"]."% → total ".$r["total_amount"]; } else echo "not saved";' "$DB" "$BEFORE")
MSG=""; [ -n "$LOC" ] && MSG=$(curl -s -c $J -b $J "$BASE${LOC#$BASE}" | grep -oE "(GST %|The quantity|The rate|The lines total)[^<]{0,150}" | head -1)
printf "  %-6s %-34s → %-44s %s\n" "$LABEL" "$CASE" "$ROW" "${MSG:+| $MSG}"
rm -f $J
