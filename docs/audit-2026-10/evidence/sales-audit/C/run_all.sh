#!/bin/sh
# Re-runs every sales-C probe, in order, on a fresh throwaway SQLite DB in this folder.
# Mail is disabled (-d sendmail_path=/bin/true); Books runs in DRY RUN; Ads Pro is a
# stub on 127.0.0.1:8765 (adstub/router.php). Nothing leaves this machine.
S=$(cd "$(dirname "$0")" && pwd)
cd /home/user/Experiment_Operataion_Exaact-requirement/phpapp || exit 1
P="php -d sendmail_path=/bin/true"
$P $S/setup.php > $S/setup.out 2>&1
for p in p1_quote_status p2_contract p2b_endorse p3_chain p3e_job p5_customer; do
  $P $S/$p.php > $S/$(echo $p | cut -d_ -f1).out 2>&1
done
php -S 127.0.0.1:8765 $S/adstub/router.php > $S/adstub/server.log 2>&1 &
SRV=$!; sleep 1
$P $S/p6_adspro.php > $S/p6.out 2>&1
kill $SRV
for p in p7_repeat p8_chain_bypass p9_misc p10_closed_contract; do
  $P $S/$p.php > $S/$(echo $p | cut -d_ -f1).out 2>&1
done
ls -1 $S/*.out
