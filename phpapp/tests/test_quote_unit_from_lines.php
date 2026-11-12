<?php
// Owner, 12 Nov 2026 — "I have added Level 4 in approval from 0 to 0 with only
// industrial business unit that goes to the Business Unit Head, but when I
// prepared quotation above 1.0 lacs under the Industrial Business Head, instead
// of going to business head it went to branch manager."
// Reproduced: the quotation's primary business unit was blank and Industrial was
// on its lines; the levels read the primary unit only, so Level 4 never applied.
t_section('Quotation approval — a level for one business unit sees the lines too');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$keepUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('crm_migrate')) crm_migrate();
    t_as_admin();
    $pdo->exec("DELETE FROM quote_approval_rules");
    $ins = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,?,?,?,?,?,?,?,1,?)");
    foreach ([[1, 0, 500000, 'BRANCH_MANAGER', 'ANY', ''], [2, 500000, 2500000, 'MARKETING_MANAGER', 'ANY', ''], [3, 2500000, 0, 'BUSINESS_DIRECTOR', 'ANY', ''], [4, 0, 0, 'SBU_HEAD', 'SBU', 'IND']] as [$l, $mn, $mx, $role, $mt, $sbu])
        $ins->execute(["Level $l", $mt, $sbu, $mn, $mx, $l, $role, null, date('c')]);

    $mkQ = function ($sbu, array $lines) use ($pdo) {
        $tot = array_sum(array_column($lines, 1));
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, client_name, status, is_current, sbu, subtotal, total_amount, created_at) VALUES (?,0,'C','DRAFT',1,?,?,?,?)")
            ->execute(['QUL-' . getmypid() . '-' . mt_rand(1000, 9999), $sbu, $tot, $tot * 1.18, date('c')]);
        $id = (int) $pdo->lastInsertId(); $n = 0;
        foreach ($lines as [$ls, $amt]) $pdo->prepare("INSERT INTO quote_lines (quote_id,line_no,sbu,description,qty,unit,rate,amount) VALUES (?,?,?,'x',1,'LS',?,?)")->execute([$id, ++$n, $ls, $amt, $amt]);
        return $id;
    };
    $roles = fn($qid) => array_map(fn($r) => $r['approver_role'], crm_quote_rule_steps(crm_quote_get($qid)));

    $blank = $mkQ('', [['IND', 150000]]);
    t_eq($roles($blank), ['BRANCH_MANAGER', 'SBU_HEAD'], 'primary unit blank, Industrial on the line, ₹1,50,000: Level 1 Branch Manager, then Level 4 Business Unit Head');
    t_eq(crm_quote_fill_unit_from_lines($blank), 'IND', 'saving fills the blank primary unit from its lines');
    t_eq((string) ops_val("SELECT sbu FROM quotations WHERE id=?", [$blank]), 'IND', '… so the quotation is Industrial everywhere it is counted');
    t_eq(crm_quote_fill_unit_from_lines($blank), '', 'a unit already chosen is never overwritten');
    $big = $mkQ('', [['OGC', 40000], ['IND', 90000]]);
    crm_quote_fill_unit_from_lines($big);
    t_eq((string) ops_val("SELECT sbu FROM quotations WHERE id=?", [$big]), 'IND', 'with lines in two units, the biggest line\'s unit is taken');

    t_eq($roles($mkQ('IND', [['IND', 150000]])), ['BRANCH_MANAGER', 'SBU_HEAD'], 'primary unit Industrial: the same chain, as test book SAL-005 says');
    t_eq($roles($mkQ('OGC', [['OGC', 150000]])), ['BRANCH_MANAGER'], 'an Oil & Gas quotation with no Industrial line: Level 1 only');

    //  Owner, 12 Nov 2026 — "Hope this is applicable to all the Business Units …
    //  not limited to industrial only right?" Any unit, as soon as it has a level.
    $ins->execute(['Level 5', 'SBU', 'OGC', 0, 0, 5, 'BUSINESS_DIRECTOR', null, date('c')]);
    t_eq($roles($mkQ('', [['OGC', 150000]])), ['BRANCH_MANAGER', 'BUSINESS_DIRECTOR'], 'the same works for any unit: an Oil & Gas-only level is reached from an Oil & Gas line');
    t_eq($roles($mkQ('', [['MIN', 150000]])), ['BRANCH_MANAGER'], 'a unit with no level of its own adds nobody');
    $pdo->exec("DELETE FROM quote_approval_rules WHERE level=5");

    t_section('Quotation approval — a unit\'s level goes to THAT unit\'s head');
    $mkU = function ($u, $sbus) use ($pdo) {
        $pdo->prepare("INSERT INTO users (username, first_name, role, scope_sbus, permissions, is_active) VALUES (?,?,'SBU_HEAD',?,'',1)")->execute([$u, $u, $sbus]);
        return (int) $pdo->lastInsertId();
    };
    $headInd = $mkU('qul_head_ind_' . getmypid(), 'IND'); $headOgc = $mkU('qul_head_ogc_' . getmypid(), 'OGC');
    $mixed = $mkQ('OGC', [['OGC', 100000], ['IND', 50000]]);
    t_eq($roles($mixed), ['BRANCH_MANAGER', 'SBU_HEAD'], 'an Oil & Gas quotation with an Industrial line also needs the Industrial level');
    crm_build_approvals(crm_quote_get($mixed));
    $step = ops_one("SELECT * FROM quote_approvals WHERE quote_id=? AND level=4", [$mixed]);
    t_eq((string) $step['sbu'], 'IND', 'the step remembers it is the Industrial level');
    $who = array_map(fn($u) => (int) $u['id'], crm_step_approvers_direct($step));
    t_ok(in_array($headInd, $who, true) && !in_array($headOgc, $who, true), 'it waits for the Industrial head — not the Oil & Gas head, though the quotation\'s primary unit is Oil & Gas');
    $_SESSION['uid'] = $headOgc; current_user(true); ua(true);
    t_ok(!crm_role_step_covers_me($step, crm_quote_get($mixed)), 'the Oil & Gas head cannot sign the Industrial level');
    $_SESSION['uid'] = $headInd; current_user(true); ua(true);
    t_ok(crm_role_step_covers_me($step, crm_quote_get($mixed)), 'the Industrial head can');

    t_ok(strpos((string) file_get_contents(__DIR__ . '/../views/ops/crm/quote_detail.php'), 'Then: ') !== false, 'the quotation says who comes after the person it is waiting with');
} finally {
    if ($keepUid !== null) { $_SESSION['uid'] = $keepUid; current_user(true); ua(true); }
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
