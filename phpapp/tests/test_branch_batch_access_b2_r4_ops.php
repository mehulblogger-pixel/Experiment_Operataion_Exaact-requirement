<?php
// ============================================================================
//  Branch batch (owner, 16 Nov 2026 — QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md)
//
//  B2 — DEF-OPS-046 (P1). A coordinator of another branch could change a work
//       order's status, priority and criticality: the change was SAVED, and only
//       then did the page say "outside your scope". Scope is now asked before
//       anything is written, on every such action.
//  R4 — DEF-OPS-004: the Business Development Manager opened the Operations home
//       and its work lists. DEF-OPS-020: the Branch Application Manager allocated
//       jobs. Both refused, as docs/02-permission-matrix.md already says.
// ============================================================================
require_once __DIR__ . '/_branch_batch_helpers.php';
$prevSession = $_SESSION ?? [];
try {
    $A = bb_office('Ahmedabad'); $M = bb_office('Mumbai');
    $t = strtoupper(bb_tag());
    $cA  = bb_call("$t-A", $A, $A);
    $cFw = bb_call("$t-FW", $M, $A);              // Ahmedabad sold it, Mumbai carries it out
    $jA  = bb_job("$t-JA", $cA, $A);
    $amd = bb_user('COORDINATOR', $A, '');
    $mum = bb_user('COORDINATOR', $M, '');
    $row = fn($id) => ops_one("SELECT op_status, priority, criticality FROM calls WHERE id=?", [$id]);

    // ------------------------------------------------------------------------
    t_section('B2 — another branch is refused BEFORE anything is saved (DEF-OPS-046)');
    $before = $row($cA);
    $r = bb_drive($mum, 'call-status', 'POST', [], ['call_id' => $cA, 'op_status' => 'ON_HOLD', 'reason' => 'probe']);
    t_eq($row($cA)['op_status'], $before['op_status'], '*** a Mumbai coordinator\'s "On hold" on an Ahmedabad work order is NOT saved');
    t_ok(strpos(bb_said($r), 'outside your office / branch scope') !== false && strpos(bb_said($r), 'Status updated') === false,
         '*** …they are told it is outside their scope, and never "Status updated" (' . bb_said($r) . ')');
    $r = bb_drive($mum, 'call-attrs', 'POST', [], ['call_id' => $cA, 'priority' => 'URGENT', 'criticality' => 'CRITICAL', 'source' => '']);
    $now = $row($cA);
    t_ok($now['priority'] === $before['priority'] && $now['criticality'] === $before['criticality'],
         '*** priority and criticality are NOT saved either (' . bb_said($r) . ')');
    $r = bb_drive($mum, 'call-clar-new', 'POST', [], ['call_id' => $cA, 'subject' => 'probe', 'raised_to' => 'CLIENT']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM call_clarifications WHERE call_id=?", [$cA]), 0, '*** …nor a clarification raised on it');
    $r = bb_drive($mum, 'assign-hold', 'POST', [], ['job_id' => $jA, 'state' => 'TENTATIVE', 'reason' => 'probe']);
    t_eq((string) ops_val("SELECT COALESCE(assign_state,'') FROM jobs WHERE id=?", [$jA]), '', '*** …nor a hold set on its job (' . bb_said($r) . ')');
    $r = bb_drive($mum, 'comm-add', 'POST', [], ['entity_kind' => 'CALL', 'entity_id' => $cA, 'kind' => 'NOTE', 'subject' => "$t probe note"]);
    t_ok(strpos(bb_said($r), 'Communication logged') === false, '*** …nor a note logged against it (' . bb_said($r) . ')');

    //  The contracting office may OPEN a forwarded order (B3) but does not run it.
    $bf = $row($cFw);
    bb_drive($amd, 'call-attrs', 'POST', [], ['call_id' => $cFw, 'priority' => 'URGENT', 'criticality' => 'CRITICAL', 'source' => '']);
    t_eq($row($cFw)['priority'], $bf['priority'], 'the contracting office cannot change the priority of an order another office carries out');

    //  The office that carries it out still can — nothing was taken from it.
    $r = bb_drive($amd, 'call-attrs', 'POST', [], ['call_id' => $cA, 'priority' => 'URGENT', 'criticality' => 'CRITICAL', 'source' => '']);
    t_eq([$row($cA)['priority'], $row($cA)['criticality']], ['URGENT', 'CRITICAL'], 'the Ahmedabad coordinator still saves their own order\'s priority (' . bb_said($r) . ')');
    $r = bb_drive($mum, 'call-attrs', 'POST', [], ['call_id' => $cFw, 'priority' => 'HIGH', 'criticality' => 'MAJOR', 'source' => '']);
    t_eq($row($cFw)['priority'], 'HIGH', 'Mumbai still saves the order it was asked to carry out (' . bb_said($r) . ')');

    // ------------------------------------------------------------------------
    t_section('R4 — Business Development Manager does not open the Operations home (DEF-OPS-004)');
    $bdm = bb_user('BUSINESS_DEV_MANAGER', $A, '');
    bb_as($bdm);
    t_ok(can('dash.operations'), 'the BDM keeps the operations dashboard tick its dashboard tiles use');
    t_ok(!tosrm_ops_desk_can(), '*** …but that tick no longer opens the operations work lists');
    $r = bb_drive($bdm, 'operations', 'GET', [], [], ["$t-A"]);
    t_ok(empty($r['has']["$t-A"]), '*** /operations shows the BDM no work order (' . bb_said($r) . ')');
    foreach (['KEY_ACCOUNTS_MANAGER', 'MARKETING_MANAGER'] as $role) {
        bb_as(bb_user($role, $A, ''));
        t_ok(!tosrm_ops_desk_can(), "the $role (a sales role holding the dashboard tick) is refused too");
    }
    foreach (['COORDINATOR', 'ASST_MANAGER', 'OPERATION_MANAGER', 'BRANCH_MANAGER', 'BRANCH_APP_MANAGER', 'QUALITY_HEAD', 'FINANCE'] as $role) {
        bb_as(bb_user($role, $A, ''));
        t_ok(tosrm_ops_desk_can(), "the $role (holds the work-order or job module) still opens the Operations home");
    }

    // ------------------------------------------------------------------------
    t_section('R4 — Branch Application Manager does not allocate jobs (DEF-OPS-020)');
    $bam = bb_user('BRANCH_APP_MANAGER', $A, '');
    bb_as($bam);
    t_ok(!job_allocate_role_can(), '*** the Branch Application Manager may not allocate (matrix: Jobs = View)');
    t_ok(!call_can_allocate(ops_one("SELECT * FROM calls WHERE id=?", [$cA])), '*** …not even on its own branch\'s work order');
    $before = (int) ops_val("SELECT COUNT(*) FROM jobs WHERE call_id=?", [$cA]);
    $r = bb_drive($bam, 'job-new', 'GET', ['call' => $cA], [], ['name="inspector_id"']);
    t_ok(empty($r['has']['name="inspector_id"']), '*** /job-new does not offer the allocation form to the Branch Application Manager (' . bb_said($r) . ')');
    $r = bb_drive($bam, 'job-new', 'POST', ['call' => $cA], ['inspector_id' => 1, 'scheduled_date' => date('Y-m-d')]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM jobs WHERE call_id=?", [$cA]), $before, '*** …and a posted allocation creates no job (' . bb_said($r) . ')');
    //  The company can still choose otherwise, by ticking the right on purpose.
    $bamTicked = bb_user('BRANCH_APP_MANAGER', $A, '', ['permissions' => implode(',', array_merge(role_perms('BRANCH_APP_MANAGER'), ['ops.job.allocate']))]);
    bb_as($bamTicked);
    t_ok(job_allocate_role_can(), 'a Branch Application Manager the company deliberately gave "Allocate / edit jobs" may allocate');
    foreach (['COORDINATOR', 'ASST_MANAGER', 'OPERATION_MANAGER', 'BRANCH_MANAGER', 'SBU_HEAD', 'BUSINESS_DIRECTOR'] as $role) {
        bb_as(bb_user($role, $A, ''));
        t_ok(job_allocate_role_can(), "the $role still allocates, as the matrix says");
    }
    foreach (['FINANCE', 'BUSINESS_DEV_MANAGER', 'QUALITY_HEAD', 'INSPECTOR'] as $role) {
        bb_as(bb_user($role, $A, ''));
        t_ok(!job_allocate_role_can(), "the $role does not allocate (unchanged)");
    }
} finally {
    try { db()->exec("DELETE FROM call_clarifications WHERE subject='probe'"); } catch (Throwable $e) {}
    bb_cleanup();
    $_SESSION = $prevSession; current_user(true); ua(true);
}
