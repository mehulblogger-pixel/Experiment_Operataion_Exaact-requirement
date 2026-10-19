#!/bin/bash
# role.sh BASE DB LABEL USER — what this login can do with nonconformities and vendors
BASE=$1; DB=$2; LABEL=$3; U=$4; J=$(mktemp)
tok() { grep -oE 'name="_csrf" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/'; }
code() { curl -s -o /dev/null -w '%{http_code}' -c $J -b $J "$BASE$1"; }
T=$(curl -s -c $J -b $J "$BASE/login" | tok); curl -s -o /dev/null -c $J -b $J -X POST "$BASE/login" --data-urlencode "_csrf=$T" --data-urlencode "username=$U" --data-urlencode "password=demo12345"
REG=$(code /ncr); FORM=$(code /ncr-new); VEN=$(code /vendors)
N0=$(php -r '$p=new PDO("sqlite:".$argv[1]); try { echo $p->query("SELECT COUNT(*) FROM nonconformities")->fetchColumn(); } catch (Throwable $e) { echo 0; }' "$DB")
T=$(curl -s -c $J -b $J "$BASE/ncr-new" | tok)
curl -s -o /dev/null -c $J -b $J -X POST "$BASE/ncr-new" --data-urlencode "_csrf=$T" --data-urlencode "title=Weld undercut beyond tolerance ($U)" --data-urlencode "severity=MINOR"
N1=$(php -r '$p=new PDO("sqlite:".$argv[1]); try { echo $p->query("SELECT COUNT(*) FROM nonconformities")->fetchColumn(); } catch (Throwable $e) { echo 0; }' "$DB")
ID=$(php -r '$p=new PDO("sqlite:".$argv[1]); try { echo (int)$p->query("SELECT MAX(id) FROM nonconformities")->fetchColumn(); } catch (Throwable $e) { echo 0; }' "$DB")
CONT="n/a"
if [ "$ID" != "0" ]; then
  T=$(curl -s -c $J -b $J "$BASE/ncr-item?id=$ID" | tok)
  CC=$(curl -s -o /dev/null -w '%{http_code}' -c $J -b $J -X POST "$BASE/ncr-contain" --data-urlencode "_csrf=$T" --data-urlencode "id=$ID" --data-urlencode "containment=Quarantined the lot")
  CS=$(php -r '$p=new PDO("sqlite:".$argv[1]); echo $p->query("SELECT status FROM nonconformities WHERE id=".(int)$argv[2])->fetchColumn();' "$DB" "$ID")
  CONT="http $CC, status now $CS"
fi
printf "  %-6s %-11s register %s · form %s · recorded %s · then contain: %-26s · vendors %s\n" "$LABEL" "$U" "$REG" "$FORM" "$([ $N1 -gt $N0 ] && echo YES || echo no)" "$CONT" "$VEN"
rm -f $J
