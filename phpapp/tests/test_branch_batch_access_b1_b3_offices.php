<?php
// ============================================================================
//  Branch batch (owner, 16 Nov 2026 — QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md)
//
//  B1 — DEF-ADM-002 / DEF-OPS-001 (P1). A branch-limited login whose home office
//       was left empty saw EVERY branch's work orders in /calls, under the banner
//       telling them their lists were empty. call_office_clause() read an empty
//       office list as "every office". Such a person now sees nothing; Master
//       Admin and the company-wide roles are unaffected.
//  B3 — DEF-OPS-002: the Operations home and the scheduling board showed every
//       order whose executing office is blank to EVERY branch.
//       DEF-OPS-011: the CONTRACTING office could not open a work order it had
//       forwarded to another office. It now can; any other office still cannot.
// ============================================================================
require_once __DIR__ . '/_branch_batch_helpers.php';
$prevSession = $_SESSION ?? [];
$pdo = db();
try {
    $AHM = (int) (ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
    $A = bb_office('Alpha'); $M = bb_office('Mumbai'); $D = bb_office('Delhi'); $P = bb_office('Pune');
    $t = strtoupper(bb_tag());
    $cA   = bb_call("$t-A",   $A, $A);       // Alpha's own work
    $cM   = bb_call("$t-M",   $M, $M);       // Mumbai's own work
    $cFwd = bb_call("$t-FWD", $M, $A);       // sold by Alpha, carried out by Mumbai
    $cBlk = bb_call("$t-BLK", null, $A);     // sold by Alpha, executing office left blank

    // ------------------------------------------------------------------------
    t_section('B1 — a branch login with no home office sees no work order (DEF-ADM-002 / DEF-OPS-001)');
    $lost = bb_user('COORDINATOR', null, '');
    bb_as($lost);
    t_ok(scope_needs_home(), 'the coordinator with no branch is recognised as needing one (the banner case)');
    [$w] = call_office_clause('c.executing_office_id', 'c.ibo_office_id');
    t_eq($w, '1=0', '*** the work-order register clause returns NO row for them (it returned 1=1 — every branch)');
    $seen = array_column(ops_all("SELECT call_code FROM calls c WHERE call_code LIKE ? AND $w", ["$t-%"]) ?: [], 'call_code');
    t_eq($seen, [], '*** …so neither Alpha\'s nor Mumbai\'s work orders are listed');
    t_ok(!call_alloc_allowed($A, [], true), '*** a person with no branch allocates nothing (an empty office list is not "every office")');
    t_eq(partner_view_level(['home_branch_id' => $A]), 'BASIC', '*** …and may not change another office\'s client (partner_view_level)');

    //  End to end: the real register, through the dispatcher, as that person.
    $r = bb_drive($lost, 'calls', 'GET', [], [], ["$t-A", "$t-M"]);
    t_ok(empty($r['has']["$t-A"]) && empty($r['has']["$t-M"]), '*** /calls as the no-branch coordinator lists neither branch\'s work order (' . bb_said($r) . ')');

    //  Unaffected: properly placed people, company-wide roles, and runs with nobody signed in.
    $amdCo = bb_user('COORDINATOR', $A, '');
    bb_as($amdCo);
    [$w] = call_office_clause('c.executing_office_id', 'c.ibo_office_id');
    $seen = array_column(ops_all("SELECT call_code FROM calls c WHERE call_code LIKE ? AND $w ORDER BY call_code", ["$t-%"]) ?: [], 'call_code');
    t_eq($seen, ["$t-A", "$t-BLK", "$t-FWD"], 'an Alpha coordinator still sees Alpha\'s work and what Alpha contracted, not Mumbai\'s own');
    $bd = bb_user('BUSINESS_DIRECTOR', null, '');
    bb_as($bd);
    t_ok(!scope_needs_home(), 'the Business Director needs no office (company-wide by role)');
    t_eq(call_office_clause('c.executing_office_id', 'c.ibo_office_id')[0], '1=1', 'the Business Director still sees every work order');
    t_eq(partner_view_level(['home_branch_id' => $A]), 'FULL', '…and every client in full');
    t_ok(call_alloc_allowed($A, 'ALL', true), 'all-office scope still allocates');
    bb_as_nobody();
    t_eq(call_office_clause('c.executing_office_id', 'c.ibo_office_id')[0], '1=1', 'a run with nobody signed in (export, CLI) stays unscoped, as before');

    // ------------------------------------------------------------------------
    t_section('B3 — Operations home and scheduling board: a blank executing office is not everybody\'s (DEF-OPS-002)');
    $codes = fn($rows) => array_values(array_filter(array_map(fn($r) => (string) ($r['call_code'] ?? ''), $rows), fn($c) => strpos($c, "$t-") === 0));
    $del = bb_user('COORDINATOR', $D, '');
    bb_as($del);
    $offD = tosrm_office_scope();
    t_eq($codes(tosrm_ops_backlog($offD, 500)), [], '*** a Delhi coordinator (no Delhi work) sees no other branch\'s order in the Backlog');
    t_eq($codes(sched_unallocated_calls($offD, 500)), [], '*** …nothing under "Needs a person" on the scheduling board');
    $m = tosrm_ops_metrics($offD);
    $wD = tosrm_call_office_sql($offD, 'c');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls c WHERE call_code LIKE ? AND $wD", ["$t-%"]), 0, '*** …and the Operations home counters count none of them');
    bb_as($amdCo);
    $offA = tosrm_office_scope();
    $back = $codes(tosrm_ops_backlog($offA, 500)); sort($back);
    t_eq($back, ["$t-A", "$t-BLK"], 'Alpha\'s desk shows its own order and the one it took with no executing office (it carries that out)');
    $un = $codes(sched_unallocated_calls($offA, 500)); sort($un);
    t_eq($un, ["$t-A", "$t-BLK"], '…the board agrees, and the order Alpha forwarded to Mumbai stays on Mumbai\'s desk');
    $mum = bb_user('COORDINATOR', $M, '');
    bb_as($mum);
    $mb = $codes(tosrm_ops_backlog(tosrm_office_scope(), 500)); sort($mb);
    t_eq($mb, ["$t-FWD", "$t-M"], 'Mumbai\'s desk shows its own order and the one it was asked to carry out');
    $cNone = bb_call("$t-NONE", null, null);
    if ($AHM) {
        $ahmCo = bb_user('COORDINATOR', $AHM, '');
        bb_as($ahmCo);
        t_ok(in_array("$t-NONE", $codes(tosrm_ops_backlog(tosrm_office_scope(), 500)), true), 'an order with neither office set falls to the managing office, as every register reads it');
        bb_as($del);
        t_ok(!in_array("$t-NONE", $codes(tosrm_ops_backlog(tosrm_office_scope(), 500)), true), '…not to every branch');
    }

    // ------------------------------------------------------------------------
    t_section('B3 — the contracting office can open a work order it forwarded (DEF-OPS-011)');
    $callFwd = ops_one("SELECT * FROM calls WHERE id=?", [$cFwd]);
    bb_as($amdCo);
    t_ok(call_view_scope_allows($callFwd), '*** Alpha (contracting) may open the order it forwarded to Mumbai');
    t_ok(!call_exec_scope_allows($callFwd), '…but may not run it (status, priority, allocation stay with Mumbai)');
    bb_as($mum);
    t_ok(call_view_scope_allows($callFwd) && call_exec_scope_allows($callFwd), 'Mumbai (executing) may open and run it');
    $pune = bb_user('COORDINATOR', $P, '');
    bb_as($pune);
    t_ok(!call_view_scope_allows($callFwd), '*** Pune — neither office — is still refused');
    bb_as($lost);
    t_ok(!call_view_scope_allows($callFwd), '…and so is a person with no branch');

    $r = bb_drive($amdCo, 'call', 'GET', ['id' => $cFwd], [], ["$t-FWD"]);
    t_ok(!empty($r['has']["$t-FWD"]) && strpos(bb_said($r), 'outside your office') === false,
         '*** /call?id= opens for the contracting office (' . bb_said($r) . ')');
    $r = bb_drive($pune, 'call', 'GET', ['id' => $cFwd], [], ["$t-FWD"]);
    t_ok(empty($r['has']["$t-FWD"]) && strpos(bb_said($r), 'outside your office / branch scope') !== false,
         '*** /call?id= refuses an office that neither sold nor carries it out (' . bb_said($r) . ')');
} finally {
    bb_cleanup();
    $_SESSION = $prevSession; current_user(true); ua(true);
}
