#!/bin/bash
# drive.sh BASE DB LABEL STARTER_ID
BASE=$1; DB=$2; LABEL=$3; SID=$4
tok() { grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/'; }
# --- Finance and the client register
J=$(mktemp)
T=$(curl -s -c $J -b $J "$BASE/login" | tok); curl -s -o /dev/null -c $J -b $J -X POST "$BASE/login" --data-urlencode "_csrf=$T" --data-urlencode "username=live.fin" --data-urlencode "password=demo12345"
CL=$(curl -s -o /dev/null -w '%{http_code}' -c $J -b $J "$BASE/clients"); NEWP=$(curl -s -o /dev/null -w '%{http_code}' -c $J -b $J "$BASE/partner-new")
printf "  %-6s Finance: /clients %s · /partner-new (add a client) %s\n" "$LABEL" "$CL" "$NEWP"; rm -f $J
# --- client portal: choose a cheaper plan mid-period
J=$(mktemp)
T=$(curl -s -c $J -b $J "$BASE/portal/login" | tok); curl -s -o /dev/null -c $J -b $J -X POST "$BASE/portal/login" --data-urlencode "_csrf=$T" --data-urlencode "email=buyer@live.test" --data-urlencode "password=demo12345"
P=$(curl -s -c $J -b $J "$BASE/portal/plans"); HTTP=$(curl -s -o /dev/null -w '%{http_code}' -c $J -b $J "$BASE/portal/plans")
HINT=$(echo "$P" | grep -oE "Monthly — Downgrade:[^<]{0,140}" | head -1)
T=$(echo "$P" | tok)
curl -s -o /dev/null -c $J -b $J -X POST "$BASE/portal/plans" --data-urlencode "_csrf=$T" --data-urlencode "plan_id=$SID" --data-urlencode "period=MONTH"
P2=$(curl -s -c $J -b $J "$BASE/portal/plans")
NOTE=$(echo "$P2" | sed -E 's/<[^>]+>//g' | grep -oE "Your plan changes to[^.]{0,80}\.|Subscribed to[^.]{0,80}\." | head -1 | tr -s ' ')
ROWS=$(php -r '$p=new PDO("sqlite:".$argv[1]); foreach ($p->query("SELECT plan_code,status,started_at,expires_at,pending_plan_id FROM mkt_subscriptions ORDER BY id")->fetchAll(PDO::FETCH_NUM) as $r) echo implode("/", array_map("strval",$r))."  "; ' "$DB" 2>/dev/null || php -r '$p=new PDO("sqlite:".$argv[1]); foreach ($p->query("SELECT plan_code,status,started_at,expires_at FROM mkt_subscriptions ORDER BY id")->fetchAll(PDO::FETCH_NUM) as $r) echo implode("/",$r)."  ";' "$DB")
printf "  %-6s Client plans page %s · card says: %s\n         after choosing Starter → page says: %s\n         subscriptions now: %s\n" "$LABEL" "$HTTP" "${HINT:-(no explanation shown)}" "${NOTE:-(nothing)}" "$ROWS"
rm -f $J
