<?php
// P4 — approval-band matching. Every quote is made through quote-new (GST 0 unless stated, so total = rate)
// and submitted through quote-status to=PENDING_APPROVAL by its BDM creator. The chain built is read back.
putenv('OPS_MAIL_ENABLED');
require __DIR__ . '/harness.php';
$mm  = mkuser('mm1', 'MARKETING_MANAGER');
$bd  = mkuser('bd1', 'BUSINESS_DIRECTOR');
$bdm = mkuser('bdm1', 'BUSINESS_DEV_MANAGER');
function reset_rules() { db()->exec("DELETE FROM quote_approval_rules"); }
function band_case($label, $bdm, $amount, $gst = '0', $sbu = '') {
    $q = new_quote($bdm, [['Work', '1', (string) $amount]], ['gst_pct' => $gst, 'sbu' => $sbu]);
    $needs = crm_quote_needs_chain(crm_quote_get($q)) ? 'needs_chain=Y' : 'needs_chain=N';
    $r = status($bdm, $q, 'PENDING_APPROVAL');
    $st = array_map(fn($s) => 'L' . $s['level'] . ':' . ($s['approver_role'] ?: ($s['approver_user_id'] ? 'user#' . $s['approver_user_id'] : 'ANY-APPROVER')), steps($q));
    show($label . " (total " . qrow($q)['total_amount'] . ")", $needs . ' | steps=' . implode(', ', $st) . ' | ' . qrow($q)['status']);
    return $q;
}
echo "\n== 0. no rules at all ==\n";
reset_rules();
band_case('no rules', $bdm, 50000);
$q = new_quote($bdm, [['Work', '1', '50000000']], ['gst_pct' => '0']);
$r = status($mm, $q, 'APPROVED');
show('no rules: MM sets 5 crore quote APPROVED directly', flashes($r) . ' -> ' . qrow($q)['status'] . ' steps=' . count(steps($q)));

echo "\n== 1. single band L1 0..100000 -> MARKETING_MANAGER ==\n";
reset_rules(); rule('L1 up to 1L', 0, 100000, 1, 'MARKETING_MANAGER');
band_case('exactly on max 100000', $bdm, 100000);
band_case('max + 0.01 = 100000.01', $bdm, 100000.01);
band_case('exactly on min 0 (zero-value quote)', $bdm, 0);

echo "\n== 2. adjacent bands sharing a boundary (L1 0..100000 MM, L2 100000..500000 BD) ==\n";
reset_rules(); rule('L1', 0, 100000, 1, 'MARKETING_MANAGER'); rule('L2', 100000, 500000, 2, 'BUSINESS_DIRECTOR');
band_case('value 50000', $bdm, 50000);
band_case('value exactly 100000 (in both)', $bdm, 100000);
band_case('value 300000', $bdm, 300000);
band_case('value exactly 500000 (L2 max)', $bdm, 500000);
band_case('value 500001 (above highest max)', $bdm, 500001);
$q = new_quote($bdm, [['Work', '1', '900000']], ['gst_pct' => '0']);
$r = status($mm, $q, 'APPROVED');
show('above highest max: MM approves 9L directly', flashes($r) . ' -> ' . qrow($q)['status']);

echo "\n== 3. gap between bands (L1 0..100000, L2 200000..500000) ==\n";
reset_rules(); rule('L1', 0, 100000, 1, 'MARKETING_MANAGER'); rule('L2', 200000, 500000, 2, 'BUSINESS_DIRECTOR');
band_case('value 150000 (in the gap)', $bdm, 150000);
$q = new_quote($bdm, [['Work', '1', '150000']], ['gst_pct' => '0']);
$r = status($mm, $q, 'APPROVED');
show('gap value: MM approves directly', flashes($r) . ' -> ' . qrow($q)['status']);

echo "\n== 4. overlapping / cumulative ladder (L1 0..unlimited MM, L2 100000..unlimited BD) ==\n";
reset_rules(); rule('L1 all', 0, 0, 1, 'MARKETING_MANAGER'); rule('L2 >=1L', 100000, 0, 2, 'BUSINESS_DIRECTOR');
band_case('value 50000', $bdm, 50000);
band_case('value 5000000 (max=0 means no upper limit)', $bdm, 5000000);

echo "\n== 5. two rules at the SAME level overlapping ==\n";
reset_rules(); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER'); rule('L1 BD', 0, 0, 1, 'BUSINESS_DIRECTOR');
$q = band_case('value 10000', $bdm, 10000);
$s = steps($q);
$r = approve_step($mm, $s[0]['id']);
show('  MM approves the MM step', flashes($r) . ' -> ' . qrow($q)['status']);

echo "\n== 6. SBU-only rule (rule for SBU INSP only, 0..unlimited) ==\n";
reset_rules(); rule('INSP only', 0, 0, 1, 'BUSINESS_DIRECTOR', null, 'INSP');
band_case('quote SBU INSP', $bdm, 10000, '0', 'INSP');
band_case('quote SBU OTHER', $bdm, 10000, '0', 'OTHER');
band_case('quote with no SBU', $bdm, 10000, '0', '');

echo "\n== 7. pre-tax or post-tax? pre- or post-discount? (rule 0..100000 MM, rule 100000.01..0 BD) ==\n";
reset_rules(); rule('L1', 0, 100000, 1, 'MARKETING_MANAGER'); rule('L2', 100000.01, 0, 2, 'BUSINESS_DIRECTOR');
band_case('subtotal 90000 + 18% GST = 106200', $bdm, 90000, '18');
$q = new_quote($bdm, [['Work', '1', '150000'], ['Discount', '1', '-60000']], ['gst_pct' => '0']);
status($bdm, $q, 'PENDING_APPROVAL');
show('gross 150000 less discount line 60000 = 90000', 'steps=' . json_encode(array_map(fn($s) => 'L' . $s['level'] . ':' . $s['approver_role'], steps($q))));

echo "\n== 8. inactive rule and malformed rules ==\n";
reset_rules(); rule('inactive', 0, 0, 1, 'BUSINESS_DIRECTOR', null, '', 0);
band_case('only an inactive rule', $bdm, 10000);
reset_rules();
$r = route($mm, 'quote-approval-rule-new', 'POST', [], ['name' => 'min>max', 'level' => '1', 'active' => '1', 'match_type' => 'ANY', 'min_amount' => '500000', 'max_amount' => '100000', 'approver_role' => 'BUSINESS_DIRECTOR']);
show('rule form accepts min 500000 > max 100000?', flashes($r) . ' rows=' . ops_val("SELECT COUNT(*) FROM quote_approval_rules"));
band_case('value 300000 vs the inverted rule', $bdm, 300000);
$r = route($mm, 'quote-approval-rule-new', 'POST', [], ['name' => '', 'level' => '-3', 'active' => '1', 'match_type' => 'ANY', 'min_amount' => '-100', 'max_amount' => '0', 'approver_role' => '']);
show('rule form: blank name, level -3, min -100, no approver at all', flashes($r) . ' ' . json_encode(ops_one("SELECT name,level,min_amount,approver_role,approver_user_id FROM quote_approval_rules ORDER BY id DESC LIMIT 1")));
$r = route($bdm, 'quote-approval-rule-new', 'POST', [], ['name' => 'bdm rule', 'level' => '1', 'active' => '1', 'min_amount' => '0', 'max_amount' => '0']);
show('BDM (no approve right) tries to add a rule', flashes($r));
