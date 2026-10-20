<?php
// The owner, 20 Oct 2026: "project costing is mandatory, but how this will be
// done and tagged with that inquiry or lead. And approval level as of quotation
// shall be applicable."
t_section('project costing: tagged with its enquiry or lead, approved by the quotation levels');

$pdo = db();
pc_migrate(); if (function_exists('crm_migrate')) crm_migrate();
$pdo->prepare("INSERT INTO offices (name) VALUES ('CL-T Indore')")->execute(); $oA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name) VALUES ('CL-T Pune')")->execute();   $oB = (int)$pdo->lastInsertId();
$mkU = function ($u, $role, $home, $offs = '', $sbus = '') use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, home_office_id, scope_offices, scope_sbus, permissions, is_active) VALUES (?,?,?,?,?,?,'',1)")
        ->execute([$u, $u, $role, $home, $offs, $sbus]);
    return (int)$pdo->lastInsertId();
};
$author = $mkU('clt_kam', 'KEY_ACCOUNTS_MANAGER', $oA);
$bmA    = $mkU('clt_bm_a', 'BRANCH_MANAGER', $oA);
$bmB    = $mkU('clt_bm_b', 'BRANCH_MANAGER', $oB);
$sbuMfg = $mkU('clt_sbu_mfg', 'SBU_HEAD', $oA, '', 'MFG');
$sbuPow = $mkU('clt_sbu_pow', 'SBU_HEAD', $oA, '', 'POWER');
$saved = ops_all("SELECT * FROM quote_approval_rules") ?: [];
$pdo->exec("DELETE FROM quote_approval_rules");
$rule = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,active,created_at) VALUES (?,?,?,?,?,?,?,1,?)");
$rule->execute(['L1', 'ANY', '', 0, 0, 1, 'BRANCH_MANAGER', date('c')]);
$rule->execute(['L2', 'ANY', '', 500000, 0, 2, 'SBU_HEAD', date('c')]);

// --- tagging --------------------------------------------------------------------
$pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_name, subject, sbu, status, office_id, created_at) VALUES ('CLT-ENQ','C','Plant manpower','MFG','OPEN',?,?)")->execute([$oA, date('c')]);
$enq = (int)$pdo->lastInsertId();
t_as_admin();
$src = pc_link_source($enq, 0);
t_ok(($src['office_id'] ?? 0) === $oA && ($src['sbu'] ?? '') === 'MFG' && ($src['title'] ?? '') === 'Plant manpower', 'an enquiry fills in branch, business unit and title');
$crud = file_get_contents(__DIR__ . '/../lib/projcosting.php');
t_ok(strpos($crud, 'Say which enquiry or lead this costing is for') !== false, 'a costing tagged to nothing is refused');

// --- levels ---------------------------------------------------------------------
$mkC = function ($sbu, $office, $monthly) use ($pdo, $author, $enq) {
    $pdo->prepare("INSERT INTO project_costings (code, title, office_id, sbu, basis, status, inquiry_id, created_by, created_by_user_id, created_at) VALUES (?,?,?,?, 'LONG','SUBMITTED',?, 'clt_kam', ?, ?)")
        ->execute(['CLT-' . mt_rand(1000, 9999), 'T', $office, $sbu, $enq, $author, date('c')]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin) VALUES (?,1,'Engineer',?,?,20)")
        ->execute([$id, json_encode(['PERSONNEL' => $monthly]), 12]);
    return $id;
};
$big = $mkC('MFG', $oA, 60000);     // 12 months × 60,000 plus margin: above 5 lakh
t_ok(pc_value(pc_get($big)) > 500000, 'arranged: a costing above the level-2 value');
pc_build_approvals(pc_get($big));
t_eq(array_map(fn($s) => $s['approver_role'], pc_steps($big)), ['BRANCH_MANAGER', 'SBU_HEAD'], 'submitting builds the quotation levels for its value');
$small = $mkC('MFG', $oA, 2000);
pc_build_approvals(pc_get($small));
t_eq(count(pc_steps($small)), 1, 'a small costing needs level 1 only');

$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$h = pc_get($big);
$as($author); t_ok(!pc_can_act($h), 'its author never signs');
$as($bmB);    t_ok(!pc_can_act($h), 'the branch manager of another branch cannot sign');
$as($sbuMfg); t_ok(!pc_can_act($h), 'level 2 cannot sign before level 1');
$as($bmA);    t_ok(pc_can_act($h), 'the branch manager over its branch signs level 1');
t_eq(pc_sign_level($h), 'NEXT', '…and it moves to level 2');
$as($sbuPow); t_ok(!pc_can_act(pc_get($big)), 'the SBU Head of another unit cannot sign level 2');
$as($sbuMfg); t_ok(pc_can_act(pc_get($big)), 'the SBU Head of its unit can');
t_eq(pc_sign_level(pc_get($big)), 'APPROVED', '…and the costing is approved');
t_eq((string)pc_get($big)['status'], 'APPROVED', 'its status reads approved');

// A level only its author holds goes to anyone else who may approve.
$mm = $mkU('clt_mm', 'MARKETING_MANAGER', $oA);
$pdo->prepare("UPDATE project_costings SET created_by_user_id=? WHERE id=?")->execute([$mm, $small]);
$pdo->prepare("UPDATE project_costing_approvals SET approver_role='MARKETING_MANAGER' WHERE costing_id=?")->execute([$small]);
$ppl = array_map(fn($u) => (int)$u['id'], pc_step_people(pc_steps($small)[0], pc_get($small)));
t_ok($ppl && !in_array($mm, $ppl, true), 'a level only its author holds goes to someone else who may approve');
$pdo->prepare("UPDATE project_costings SET created_by_user_id=? WHERE id=?")->execute([$author, $small]);
$pdo->prepare("UPDATE project_costing_approvals SET approver_role='BRANCH_MANAGER' WHERE costing_id=?")->execute([$small]);

// The inbox lists what waits on the person.
$as($bmA);
t_ok(in_array($small, array_map(fn($r) => (int)$r['id'], pc_waiting_on_me()), true), 'Approvals lists a costing waiting at your level');

// The buttons sit in a row app.css does not shrink: .bar is its 8px progress
// strip, which had hidden Submit / Approve / Make a quotation on this screen.
$pcv = file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
t_ok(strpos($pcv, 'class="bar"') === false && strpos($pcv, 'class="pc-actions"') !== false, 'the costing\'s action buttons are not inside a progress-bar strip');

unset($_SESSION['uid']); current_user(true); ua(true);
$pdo->exec("DELETE FROM quote_approval_rules");
foreach ($saved as $s) { $cols = array_keys($s); $pdo->prepare('INSERT INTO quote_approval_rules (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($s)); }
foreach ([$big, $small] as $id) { $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$id]); $pdo->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$id]); $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$id]); }
$pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$enq]);
$pdo->exec("DELETE FROM users WHERE username LIKE 'clt_%'");
$pdo->exec("DELETE FROM offices WHERE name LIKE 'CL-T %'");
