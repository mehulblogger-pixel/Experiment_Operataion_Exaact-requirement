<?php
// ============================================================================
//  Branch batch (owner, 16 Nov 2026 — QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md)
//
//  0.1 / C1 — Finance and the Marketing Manager become OFFICE-BASED. They used
//  to see every office by role; they now see the offices ticked on their record
//  ("All offices" ticked = company-wide), else their home office. Their rights
//  are unchanged.
//
//  C1 — so that nobody who holds these roles today loses anything, every active
//  Finance / Marketing Manager (custom roles built on them included) whose
//  offices are empty is given "All offices", ONCE per database.
//
//  0.1 (owner's words) — the existing quotation and costing approval chains
//  (N+1, N+2 … up to the SBU Head and Director; the Marketing Manager is level 2
//  in the test data) must keep working: an office-scoped Marketing Manager
//  approves the quotations of the offices they cover, one with all offices
//  approves all.
// ============================================================================
require_once __DIR__ . '/_branch_batch_helpers.php';
$prevSession = $_SESSION ?? [];
$pdo = db();

t_section('0.1 — Finance and Marketing Manager are office-based by role');
foreach (['FINANCE', 'MARKETING_MANAGER'] as $role) {
    t_eq(role_defaults($role)['offices'], 'OWN', "*** $role reaches the offices on the person's record, not every office by role");
}
t_ok(in_array('crm.quote.approve', role_perms('MARKETING_MANAGER'), true), 'the Marketing Manager still approves quotations (rights unchanged)');
t_ok(in_array('finance.reconcile', role_perms('FINANCE'), true) && in_array('crm.contract.register', role_perms('FINANCE'), true),
     'Finance keeps its finance rights (unchanged)');
t_eq(role_defaults('BUSINESS_DIRECTOR')['offices'], 'ALL', 'the Business Director is still company-wide with nothing to set');

// ---------------------------------------------------------------------------
t_section('C1 — everyone in these roles today keeps every office (once)');
$prevFlag  = (string) setting_get(ACCESS_CENTRAL_SCOPE_FLAG, '');
$prevRoles = (string) setting_get('custom_roles', '');
$own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    $A = bb_office('Ahmedabad'); $M = bb_office('Mumbai');
    $custom = custom_role_add('BB Regional Sales Head', 'MARKETING_MANAGER');
    $finNo    = bb_user('FINANCE', null, '');                 // no office at all
    $finHome  = bb_user('FINANCE', $A, '');                   // a home office, nothing ticked
    $mmHome   = bb_user('MARKETING_MANAGER', $M, '');
    $custMM   = bb_user($custom, $A, '');                     // a custom role built on Marketing Manager
    $finSet   = bb_user('FINANCE', $A, (string) $M);          // already given an office on purpose
    $finGone  = bb_user('FINANCE', null, '', ['active' => 0]);
    $coord    = bb_user('COORDINATOR', null, '');
    $sc = fn($id) => (string) ops_val("SELECT COALESCE(scope_offices,'') FROM users WHERE id=?", [$id]);

    setting_set(ACCESS_CENTRAL_SCOPE_FLAG, '');                // as on the day the update is installed
    $n = access_migrate_central_roles_scope(true);
    t_ok($n >= 4, "the update gave \"All offices\" to the people holding these roles ($n)");
    t_eq([$sc($finNo), $sc($finHome), $sc($mmHome), $sc($custMM)], ['ALL', 'ALL', 'ALL', 'ALL'],
         '*** every active Finance / Marketing Manager with no offices ticked — custom role included — now has "All offices"');
    t_eq($sc($finSet), (string) $M, '*** someone already given offices keeps exactly those');
    t_eq($sc($finGone), '', 'a deactivated account is left alone');
    t_eq($sc($coord), '', 'other roles are untouched (a Coordinator with no branch still sees nothing)');
    bb_as($finHome);
    t_eq(scope_offices(), 'ALL', '*** so the Finance person sees every office, as before the update');
    t_ok(!scope_needs_home(), '…and is not flagged as missing a branch');

    //  Once: an administrator who later narrows somebody is not overruled.
    $pdo->prepare("UPDATE users SET scope_offices=? WHERE id=?")->execute([(string) $A, $finHome]);
    t_eq(access_migrate_central_roles_scope(true), 0, '*** it runs once: a second pass changes nothing');
    t_eq($sc($finHome), (string) $A, '…and a narrowing made after it is kept');

    //  New people must be given their offices.
    $newFin = bb_user('FINANCE', null, '');
    bb_as($newFin);
    t_eq(scope_offices(), [], '*** a NEW Finance person with no office sees no office');
    t_ok(scope_needs_home(), '*** …and is flagged as needing a branch (scope_needs_home), like any branch role');
    t_ok(in_array($newFin, array_map('intval', array_column(scope_unplaced_users(), 'id')), true), '…so the User register lists them for an administrator');
    $newMM = bb_user('MARKETING_MANAGER', $M, '');
    bb_as($newMM);
    t_eq(scope_offices(), [$M], 'a new Marketing Manager with a home office sees that office');
    t_ok(!scope_needs_home(), '…and is not flagged');
    $wide = bb_user('MARKETING_MANAGER', null, 'ALL');
    bb_as($wide);
    t_eq(scope_offices(), 'ALL', '"All offices" ticked is company-wide');
    t_ok(!scope_needs_home(), '…and needs no branch');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    $GLOBALS['__bb_keep'] = ['offices' => [], 'users' => [], 'calls' => [], 'jobs' => [], 'partners' => [], 'other' => []];
    setting_set(ACCESS_CENTRAL_SCOPE_FLAG, $prevFlag);
    setting_set('custom_roles', $prevRoles);
    $_SESSION = $prevSession; current_user(true); ua(true);
}

// ---------------------------------------------------------------------------
t_section('0.1 — the quotation and costing chains still reach the Marketing Manager');
$own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    $A = bb_office('Ahmedabad'); $M = bb_office('Mumbai');
    //  The owner's test data: level 1 Branch Manager, level 2 Marketing Manager (₹5–25 lakh), level 3 Director.
    $pdo->exec("DELETE FROM quote_approval_rules");
    $ins = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ([[1, 0, 500000, 'BRANCH_MANAGER'], [2, 500000, 2500000, 'MARKETING_MANAGER'], [3, 2500000, 0, 'BUSINESS_DIRECTOR']] as [$lvl, $min, $max, $role])
        $ins->execute(["Level $lvl", 'ANY', '', $min, $max, $lvl, $role, null, 1, date('c')]);
    //  Only this test's Marketing Managers, so "who is asked" is exactly measurable.
    $pdo->exec("UPDATE users SET is_active=0 WHERE role='MARKETING_MANAGER'");
    $mmAll = bb_user('MARKETING_MANAGER', $A, 'ALL');         // a C1 person: every office
    $mmMum = bb_user('MARKETING_MANAGER', $M, '');            // office-scoped: Mumbai

    $qA = ['subtotal' => 1000000, 'sbu' => '', 'office_id' => $A];
    $qM = ['subtotal' => 1000000, 'sbu' => '', 'office_id' => $M];
    $steps = crm_quote_rule_steps($qA);
    t_eq(array_map(fn($s) => $s['approver_role'], $steps), ['BRANCH_MANAGER', 'MARKETING_MANAGER'], 'a ₹10 lakh quotation still goes Branch Manager → Marketing Manager');
    $lvl2 = $steps[1] ?? ['approver_role' => 'MARKETING_MANAGER'];
    $ids = fn($q) => array_map('intval', array_column(crm_role_approvers_for([(string) $lvl2['approver_role']], $q), 'id'));

    t_ok(in_array($mmAll, $ids($qA), true), '*** an Ahmedabad quotation reaches the Marketing Manager with all offices');
    t_ok(!in_array($mmMum, $ids($qA), true), '*** …and NOT the Mumbai-only Marketing Manager');
    t_ok(in_array($mmAll, $ids($qM), true) && in_array($mmMum, $ids($qM), true), '*** a Mumbai quotation reaches both');
    bb_as($mmMum);
    t_ok(crm_role_step_covers_me($lvl2, $qM), '*** the Mumbai Marketing Manager may sign level 2 of a Mumbai quotation');
    t_ok(!crm_role_step_covers_me($lvl2, $qA), '*** …but not of an Ahmedabad one');
    bb_as($mmAll);
    t_ok(crm_role_step_covers_me($lvl2, $qA) && crm_role_step_covers_me($lvl2, $qM), 'the all-offices Marketing Manager signs both');

    //  Costings use the same chain and the same lookup.
    $pc = fn($office) => array_map('intval', array_column(pc_step_people(['approver_role' => 'MARKETING_MANAGER', 'approver_user_id' => null, 'level' => 2],
        ['id' => 0, 'office_id' => $office, 'sbu' => '', 'exp_revenue' => 1000000, 'created_by_user_id' => 0]), 'id'));
    if (function_exists('pc_step_people')) {
        t_ok(in_array($mmAll, $pc($A), true) && !in_array($mmMum, $pc($A), true), 'an Ahmedabad costing reaches the all-offices Marketing Manager, not the Mumbai one');
        t_ok(in_array($mmMum, $pc($M), true), 'a Mumbai costing reaches the Mumbai Marketing Manager');
    }

    //  Never stuck: an office nobody in the role covers still has someone to approve.
    $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$mmAll]);
    t_eq($ids($qA), [$mmMum], 'with no Marketing Manager covering Ahmedabad, its quotation still reaches one (the existing fall-back), so it is never stuck');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    $GLOBALS['__bb_keep'] = ['offices' => [], 'users' => [], 'calls' => [], 'jobs' => [], 'partners' => [], 'other' => []];
    $_SESSION = $prevSession; current_user(true); ua(true);
}
