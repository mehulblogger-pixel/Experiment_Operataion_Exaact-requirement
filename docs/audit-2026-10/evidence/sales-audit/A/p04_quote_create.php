<?php
// QUOTATION creation: empty submit, duplicate submit, external register status, who may create.
require __DIR__ . '/plib.php';
H('1. empty submit of /quote-new');
$b = lastid('quotations');
$r = R('MM', 'quote-new', 'POST', [], ['client_id' => '', 'client_name' => '', 'subject' => '', 'gst_pct' => '18', 'validity_days' => '30']);
show('empty POST', $r);
$id = lastid('quotations');
if ($id > $b) { $x = ops_one("SELECT * FROM quotations WHERE id=?", [$id]);
    printf("    STORED: #%d %s status=%s client_id=%s client_name='%s' subject='%s' sbu='%s' total=%s lines=%d\n", $id, $x['quote_no'], $x['status'], var_export($x['client_id'], true), $x['client_name'], $x['subject'], $x['sbu'], $x['total_amount'], cnt("SELECT COUNT(*) FROM quote_lines WHERE quote_id=?", [$id])); }
else echo "    nothing stored\n";
$r = R('MM', 'quote-new', 'POST', [], []); show('POST with no fields at all', $r); echo "    last: " . qline(lastid('quotations')) . "\n";
H('1b. can the empty draft go forward?');
show('submit empty draft for approval', R('MM', 'quote-status', 'POST', ['id' => $id], ['to' => 'PENDING_APPROVAL']));
echo "    " . qline($id) . " steps=" . cnt("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$id]) . "\n";
$st = (int)ops_val("SELECT id FROM quote_approvals WHERE quote_id=? AND status='PENDING' ORDER BY level LIMIT 1", [$id]);
show('MM2 approves it', R('MM2', 'quote-approve', 'POST', [], ['step' => $st, 'decision' => 'approve']));
show('MM sends it', R('MM', 'quote-status', 'POST', ['id' => $id], ['to' => 'SENT']));
show('MM marks it ACCEPTED', R('MM', 'quote-status', 'POST', ['id' => $id], ['to' => 'ACCEPTED']));
echo "    " . qline($id) . "\n";

H('2. the same /quote-new POST submitted twice (double click / resubmit)');
$n0 = cnt("SELECT COUNT(*) FROM quotations");
$p = quote_post(['subject' => 'Double submit probe']);
show('submit #1', R('MM', 'quote-new', 'POST', [], $p)); show('submit #2 (identical)', R('MM', 'quote-new', 'POST', [], $p));
printf("    quotations +%d with subject 'Double submit probe': %s\n", cnt("SELECT COUNT(*) FROM quotations") - $n0, json_encode(array_column(ops_all("SELECT quote_no FROM quotations WHERE subject='Double submit probe'"), 'quote_no')));
echo "    concurrent identical submits (barrier):\n";
$rs = RACE('MM', 'quote-new', 'POST', [], quote_post(['subject' => 'Race submit probe']), 2); foreach ($rs as $j => $r) show("race worker$j", $r);
echo "    rows: " . json_encode(ops_all("SELECT id, quote_no, rev FROM quotations WHERE subject='Race submit probe'")) . "\n";
echo "    idempotency/nonce field in the form? " . (preg_match('/idem|nonce|once_token|form_token/i', file_get_contents('/home/user/Experiment_Operataion_Exaact-requirement/phpapp/views/ops/crm/quote_form.php')) ? 'yes' : 'no') . "\n";

H('3. /quote-external takes status from the form');
foreach (['ACCEPTED', 'APPROVED', 'BOGUS'] as $s) {
    $b = lastid('quotations');
    $r = R('MM', 'quote-external', 'POST', [], ['origin' => 'CLIENT_PORTAL', 'client_id' => (string)$GLOBALS['CLIENT'], 'subject' => "External $s", 'total_amount' => '100000', 'status' => $s, 'sbu' => 'INSP', 'office_id' => (string)$GLOBALS['OFF']]);
    show("external status=$s", $r); $id = lastid('quotations'); echo "    " . ($id > $b ? qline($id) . " approvals=" . cnt("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$id]) : 'nothing stored') . "\n";
}
show('MEX (create only, no approve/send) registers an external quote straight to ACCEPTED',
    R('MEX', 'quote-external', 'POST', [], ['origin' => 'CLIENT_PORTAL', 'client_id' => (string)$GLOBALS['CLIENT'], 'subject' => 'MEX external', 'total_amount' => '900000', 'status' => 'ACCEPTED']));
echo "    " . qline(lastid('quotations')) . "\n";

H('4. who may create a quotation');
foreach (['FIN','BM','BD','COORD','MEX','BDM'] as $w) { [$id, $r] = new_quote($w); printf("    %-5s %s  %s\n", $w, $id ? 'created ' . qline($id) : 'refused', implode('|', $r['flash'])); }

H('5. a quote line pointing at a site of ANOTHER quotation (E-3)');
[$qa] = new_quote('MM', ['loc_line1' => ['Plant A road'], 'loc_city' => ['Vadodara'], 'loc_label' => ['Plant A']]);
$locA = (int)ops_val("SELECT id FROM quote_locations WHERE quote_id=?", [$qa]);
[$qb] = new_quote('MM', ['l_location' => [(string)$locA]]);
echo "    quote B line location_id=" . var_export(ops_val("SELECT location_id FROM quote_lines WHERE quote_id=?", [$qb]), true) . " location text='" . ops_val("SELECT location FROM quote_lines WHERE quote_id=?", [$qb]) . "' (site belongs to quote A #$qa)\n";
