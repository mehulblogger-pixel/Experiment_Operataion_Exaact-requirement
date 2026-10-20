<?php
// The owner's organisation chart (20 Oct 2026): a Key Accounts Manager's
// quotation is approved by the Branch Manager over that branch, then the SBU
// Head of that business unit, then the Business Director, then the MD. A level
// addressed to a role used to mean everyone in the role, company-wide — every
// Branch Manager and every SBU Head was e-mailed and could sign.
t_section('approval by role goes to the people over that quotation');

$pdo = db();
if (function_exists('crm_migrate')) crm_migrate();
$mkOff = function ($name) use ($pdo) {
    $pdo->prepare("INSERT INTO offices (name) VALUES (?)")->execute([$name]); return (int)$pdo->lastInsertId();
};
$oA = $mkOff('ORG-T Indore'); $oB = $mkOff('ORG-T Bhopal'); $oC = $mkOff('ORG-T Gurgaon');
$mkU = function ($u, $role, $home, $offs = '', $sbus = '') use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, home_office_id, scope_offices, scope_sbus, permissions, is_active) VALUES (?,?,?,?,?,?,'',1)")
        ->execute([$u, $u, $role, $home, $offs, $sbus]);
    return (int)$pdo->lastInsertId();
};
// One Branch Manager over Indore AND Bhopal (as in the chart: one manager, several branches); another over Gurgaon.
$bmCentral = $mkU('orgt_bm_central', 'BRANCH_MANAGER', $oA, "$oA,$oB");
$bmNorth   = $mkU('orgt_bm_north', 'BRANCH_MANAGER', $oC);
$sbuMfg    = $mkU('orgt_sbu_mfg', 'SBU_HEAD', $oA, '', 'MFG');
$sbuPower  = $mkU('orgt_sbu_power', 'SBU_HEAD', $oA, '', 'POWER');
$pdo->prepare("INSERT INTO quotations (quote_no, rev, client_name, status, is_current, office_id, sbu, subtotal, total_amount, created_at) VALUES ('ORGT-1',0,'C','PENDING_APPROVAL',1,?,?,100,118,?)")
    ->execute([$oB, 'MFG', date('c')]);
$qid = (int)$pdo->lastInsertId();
$ids = fn($people) => array_map(fn($u) => (int)$u['id'], $people);
$bmStep  = ['quote_id' => $qid, 'approver_role' => 'BRANCH_MANAGER', 'status' => 'PENDING', 'level' => 1];
$sbuStep = ['quote_id' => $qid, 'approver_role' => 'SBU_HEAD', 'status' => 'PENDING', 'level' => 2];

$bmPeople = $ids(crm_step_approvers_direct($bmStep));
t_ok(in_array($bmCentral, $bmPeople, true) && !in_array($bmNorth, $bmPeople, true), 'a Bhopal quotation waits for the manager over Bhopal, not the Gurgaon one');
$sbuPeople = $ids(crm_step_approvers_direct($sbuStep));
t_ok(in_array($sbuMfg, $sbuPeople, true) && !in_array($sbuPower, $sbuPeople, true), 'a Manufacturing quotation waits for the Manufacturing SBU Head only');

$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$as($bmNorth);   t_ok(!crm_can_act_approval($bmStep), 'the Gurgaon branch manager cannot sign a Bhopal quotation');
$as($bmCentral); t_ok(crm_can_act_approval($bmStep), 'the branch manager over Bhopal can');
$as($sbuPower);  t_ok(!crm_can_act_approval($sbuStep), 'the Power SBU Head cannot sign a Manufacturing quotation');
$as($sbuMfg);    t_ok(crm_can_act_approval($sbuStep), 'the Manufacturing SBU Head can');

// Nobody covers it → everyone in the role, so a quotation is never stuck.
$pdo->prepare("UPDATE quotations SET sbu='MINING' WHERE id=?")->execute([$qid]);
$fallback = $ids(crm_step_approvers_direct($sbuStep));
t_ok(in_array($sbuMfg, $fallback, true) && in_array($sbuPower, $fallback, true), 'a unit no SBU Head covers falls back to every SBU Head');
$as($sbuPower); t_ok(crm_can_act_approval($sbuStep), '…and any of them may then sign it');

// A named person is unaffected by scope.
$named = ['quote_id' => $qid, 'approver_user_id' => $bmNorth, 'status' => 'PENDING', 'level' => 3];
$as($bmNorth); t_ok(crm_can_act_approval($named), 'a person named on a level signs it wherever the quotation is');

// user_scope_of() is the same answer ua() gives.
$u = ops_one("SELECT * FROM users WHERE id=?", [$bmCentral]); $as($bmCentral);
t_ok(user_scope_of($u)['offices'] == ua()['offices'], 'user_scope_of() agrees with ua() for the person signed in');

// Set up Sales names the gaps.
$saved = ops_all("SELECT * FROM quote_approval_rules") ?: [];
$pdo->exec("DELETE FROM quote_approval_rules");
$pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,active,created_at) VALUES ('L1','ANY','',0,0,1,'BRANCH_MANAGER',1,?)")->execute([date('c')]);
$facts = implode(' ', setupqa_sales_level_facts());
t_ok(strpos($facts, 'No Branch Manager covers') !== false, 'Set up Sales warns about a branch no Branch Manager covers');
t_ok(strpos($facts, 'ORG-T Bhopal') === false || strpos($facts, 'covers ORG-T Bhopal') === false, 'a covered branch is not listed as a gap');

unset($_SESSION['uid']); current_user(true); ua(true);
$pdo->exec("DELETE FROM quote_approval_rules");
foreach ($saved as $s) { $cols = array_keys($s); $pdo->prepare('INSERT INTO quote_approval_rules (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($s)); }
$pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$qid]);
$pdo->exec("DELETE FROM users WHERE username LIKE 'orgt_%'");
$pdo->exec("DELETE FROM offices WHERE name LIKE 'ORG-T %'");
