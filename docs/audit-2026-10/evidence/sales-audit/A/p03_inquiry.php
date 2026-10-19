<?php
// ENQUIRY (crm_inquiries) lifecycle.
require __DIR__ . '/plib.php';
function I($id) { return json_encode(ops_one("SELECT id,inquiry_no,status,client_id,subject FROM crm_inquiries WHERE id=?", [$id])); }
function inq($extra = []) {
    $b = lastid('crm_inquiries');
    $r = R('MM', 'inquiry-new', 'POST', [], array_merge(['client_id' => (string)$GLOBALS['CLIENT'], 'contact_name' => 'Ravi', 'contact_email' => 'r@probe.invalid', 'sbu' => 'INSP', 'subject' => 'Need TPI'], $extra));
    $id = lastid('crm_inquiries'); return [$id > $b ? $id : 0, $r];
}
H('1. empty submit of /inquiry-new');
$n0 = cnt("SELECT COUNT(*) FROM crm_inquiries");
$r = R('MM', 'inquiry-new', 'POST', [], ['client_id' => '', 'client_name' => '', 'subject' => '', 'status' => 'OPEN']); show('empty', $r);
printf("    rows +%d\n", cnt("SELECT COUNT(*) FROM crm_inquiries") - $n0);
[$a, $r] = inq(); show('valid', $r); echo "    " . I($a) . "  (number consumed by the refused empty submit? first number is the one above)\n";

H('2. status is taken from the form as typed');
[$b, $r] = inq(['status' => 'QUOTED']); show('new with status=QUOTED (no quote exists)', $r); echo "    " . I($b) . "\n";
[$c, $r] = inq(['status' => 'BANANA']); show('new with status=BANANA', $r); echo "    " . I($c) . "\n";

H('3. raising a quotation sets QUOTED; a DROPPED inquiry can be quoted; two quotes per inquiry');
[$d] = inq();
[$q1] = new_quote('MM', ['inquiry_id' => (string)$d]); echo "    after quote 1: " . I($d) . "  " . qline($q1) . "\n";
[$q2] = new_quote('MM', ['inquiry_id' => (string)$d]); echo "    after quote 2 (alternative offer): " . I($d) . "  " . qline($q2) . "  quotes on inquiry=" . cnt("SELECT COUNT(*) FROM quotations WHERE inquiry_id=?", [$d]) . "\n";
[$e] = inq();
show('inquiry-edit -> DROPPED', R('MM', 'inquiry-edit', 'POST', ['id' => $e], ['client_id' => (string)$GLOBALS['CLIENT'], 'contact_name' => 'R', 'contact_email' => 'r@x.invalid', 'sbu' => 'INSP', 'subject' => 'S', 'status' => 'DROPPED']));
echo "    " . I($e) . "\n";
[$q3] = new_quote('MM', ['inquiry_id' => (string)$e]); echo "    quote raised on the DROPPED inquiry: " . qline($q3) . " -> inquiry " . I($e) . "\n";
show('inquiry-edit QUOTED -> OPEN while 2 quotes exist', R('MM', 'inquiry-edit', 'POST', ['id' => $d], ['client_id' => (string)$GLOBALS['CLIENT'], 'contact_name' => 'R', 'contact_email' => 'r@x.invalid', 'sbu' => 'INSP', 'subject' => 'S', 'status' => 'OPEN']));
echo "    " . I($d) . "\n";
[$f] = inq();
[$q4] = new_quote('MM', ['inquiry_id' => (string)$f]); setq($q4, ['status' => 'SENT', 'sent_at' => date('c')]);
show('the only quote is LOST', R('MM', 'quote-status', 'POST', ['id' => $q4], ['to' => 'LOST', 'lost_reason' => 'PRICE']));
echo "    inquiry after its only quote was lost: " . I($f) . "\n";
[$q5] = new_quote('MM', ['inquiry_id' => '999999']); echo "    quote with inquiry_id=999999 (no such inquiry): " . qline($q5) . " inquiry_id=" . q($q5)['inquiry_id'] . "\n";

H('4. delete');
show('inquiry-delete with quotes (MM)', R('MM', 'inquiry-delete', 'POST', [], ['id' => $d]));
show('inquiry-delete with quotes (ADMIN)', R('ADMIN', 'inquiry-delete', 'POST', [], ['id' => $d]));
show('inquiry-delete, no quote (BM, admin-level)', R('BM', 'inquiry-delete', 'POST', [], ['id' => $c]));
echo "    is there any quote-delete route? " . (strpos(file_get_contents('/home/user/Experiment_Operataion_Exaact-requirement/phpapp/lib/crm.php'), "'quote-delete'") !== false ? 'yes' : 'no') . "\n";

H('5. history for inquiry edits');
echo "    activities for INQUIRY: " . json_encode(array_column(ops_all("SELECT subject FROM activities WHERE entity_kind='INQUIRY'") ?: [], 'subject')) . "\n";
