<?php
// The owner's decision, 24 Oct 2026 (test book SAL-003): quotation approval is an
// ESCALATING CHAIN. A quotation is signed by the level its value falls in and by
// every level below it, in order; levels may not overlap; a higher range needs a
// higher level number.
t_section('Quotation approval — the escalating chain (owner, SAL-003)');

$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM quote_approval_rules");
    $ins = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $add = function ($lvl, $min, $max, $role, $mt = 'ANY', $sbu = '') use ($ins) {
        $ins->execute(["Level $lvl — $role", $mt, $sbu, $min, $max, $lvl, $role, null, 1, date('c')]);
    };
    $add(1, 0, 500000, 'BRANCH_MANAGER');
    $add(2, 500000, 2500000, 'MARKETING_MANAGER');
    $add(3, 2500000, 0, 'BUSINESS_DIRECTOR');
    $chain = fn($amt, $sbu = '') => array_map(fn($r) => $r['approver_role'], crm_quote_rule_steps(['subtotal' => $amt, 'sbu' => $sbu]));

    t_eq($chain(200000), ['BRANCH_MANAGER'], '₹2,00,000: the Branch Manager alone');
    t_eq($chain(1000000), ['BRANCH_MANAGER', 'MARKETING_MANAGER'], '₹10,00,000: Branch Manager, then Marketing Manager');
    t_eq($chain(3000000), ['BRANCH_MANAGER', 'MARKETING_MANAGER', 'BUSINESS_DIRECTOR'], '₹30,00,000: Branch Manager → Marketing Manager → Business Director');
    t_eq($chain(500000), ['BRANCH_MANAGER'], 'exactly ₹5,00,000 belongs to level 1 only — the shared boundary is not counted twice');
    t_eq($chain(2500000), ['BRANCH_MANAGER', 'MARKETING_MANAGER'], 'exactly ₹25,00,000 stops at level 2');

    // Saving: overlaps and out-of-order levels are refused, in plain words.
    $p = crm_approval_band_problem(['level' => 4, 'min_amount' => 30000, 'max_amount' => 100000, 'match_type' => 'ANY', 'sbu' => '', 'active' => 1]);
    t_ok(strpos($p, 'may not overlap') !== false, 'a range inside level 1 is refused: "' . $p . '"');
    $p = crm_approval_band_problem(['level' => 1, 'min_amount' => 3000000, 'max_amount' => 0, 'match_type' => 'ANY', 'sbu' => '', 'active' => 1]);
    t_ok($p !== '', 'a higher range with a lower level number is refused');
    $p = crm_approval_band_problem(['level' => 2, 'min_amount' => 30000, 'max_amount' => 100000, 'match_type' => 'ANY', 'sbu' => '', 'active' => 0]);
    t_eq($p, '', 'a level that is not in use cannot clash');
    $r2 = ops_one("SELECT * FROM quote_approval_rules WHERE level=2");
    t_eq(crm_approval_band_problem($r2 + ['active' => 1], (int) $r2['id']), '', 'saving an existing level unchanged is accepted (it does not clash with itself)');
    // A level for one business unit adds a signatory over the company-wide levels (SAL-005).
    t_eq(crm_approval_band_problem(['level' => 4, 'min_amount' => 0, 'max_amount' => 0, 'match_type' => 'SBU', 'sbu' => 'IND', 'active' => 1]), '',
         'a level for one business unit may sit over the company-wide levels');
    $add(4, 0, 0, 'SBU_HEAD', 'SBU', 'IND');
    t_eq($chain(100000, 'IND'), ['BRANCH_MANAGER', 'SBU_HEAD'], 'an Industrial ₹1,00,000 quotation: Level 1, then the unit\'s Level 4 (SAL-005 step 3)');
    t_eq($chain(100000, 'OGC'), ['BRANCH_MANAGER'], 'another unit\'s ₹1,00,000 quotation: Level 1 only (SAL-005 step 2)');
    t_ok(crm_approval_band_problem(['level' => 5, 'min_amount' => 50000, 'max_amount' => 0, 'match_type' => 'SBU', 'sbu' => 'IND', 'active' => 1]) !== '',
         "…but two levels for the SAME unit may not overlap");
    $pdo->exec("DELETE FROM quote_approval_rules WHERE level=4");

    // Two different units' levels never meet.
    $pdo->exec("DELETE FROM quote_approval_rules");
    $add(1, 0, 0, 'BRANCH_MANAGER', 'SBU', 'OGC');
    t_eq(crm_approval_band_problem(['level' => 1, 'min_amount' => 0, 'max_amount' => 0, 'match_type' => 'SBU', 'sbu' => 'PHARMA', 'active' => 1]), '',
         'two business units may each have their own level 1 over the same values');
    $prevPol = (string) setting_get('quote_unmatched_approval', '');
    setting_set('quote_unmatched_approval', 'none');
    t_eq($chain(100, 'PHARMA'), [], "another unit's level does not sign this unit's quotation");
    setting_set('quote_unmatched_approval', 'top');
    t_eq($chain(100, 'PHARMA'), ['BRANCH_MANAGER'], '…unless the company chose "the highest level" for values no level covers (D-03)');
    setting_set('quote_unmatched_approval', $prevPol);

    // Source: the save handler asks the question.
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../lib/crm.php'), '$problem = crm_approval_band_problem(') !== false, 'the level save refuses an overlap before storing anything');
} finally { if ($own) $pdo->rollBack(); }
