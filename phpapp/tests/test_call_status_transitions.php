<?php
// R6 — the call op_status picker used to accept ANY of the 15 statuses with no
// transition rules, so a call could be moved to a nonsensical state (COMPLETED with
// no work, or CLOSED then back to RECEIVED). op_status now follows a forward-rank
// lifecycle: forward/same-phase moves are allowed, a live call may be put ON_HOLD or
// CANCELLED, REJECTED only during intake, and the terminal states have no exit
// (a manager may still override with a reason).
t_section('call op_status follows transition rules');

// --- the transition predicate ------------------------------------------------
t_ok(tosrm_can_transition('RECEIVED', 'UNDER_REVIEW') === true, 'RECEIVED → UNDER_REVIEW is allowed');
t_ok(tosrm_can_transition('UNDER_REVIEW', 'READY_TO_SCHEDULE') === true, 'a forward jump within the flow is allowed');
t_ok(tosrm_can_transition('RECEIVED', 'COMPLETED') === true, 'moving forward to COMPLETED is allowed (rank-forward)');
t_ok(tosrm_can_transition('COMPLETED', 'SCHEDULED') === false, 'a backward jump (COMPLETED → SCHEDULED) is refused');
t_ok(tosrm_can_transition('CLOSED', 'RECEIVED') === false, 'a CLOSED call cannot be moved back to RECEIVED');
t_ok(tosrm_can_transition('CANCELLED', 'IN_PROGRESS') === false, 'a CANCELLED call has no exit');
t_ok(tosrm_can_transition('REJECTED', 'ACCEPTED') === false, 'a REJECTED call has no exit');
t_ok(tosrm_can_transition('IN_PROGRESS', 'ON_HOLD') === true, 'a live call can be put on hold');
t_ok(tosrm_can_transition('ON_HOLD', 'IN_PROGRESS') === true, 'an on-hold call can resume');
t_ok(tosrm_can_transition('SCHEDULED', 'CANCELLED') === true, 'a live call can be cancelled');
t_ok(tosrm_can_transition('UNDER_REVIEW', 'REJECTED') === true, 'a call in intake can be rejected');
t_ok(tosrm_can_transition('SCHEDULED', 'REJECTED') === false, 'a scheduled call is past the point it can be rejected');
t_ok(tosrm_can_transition('SCHEDULED', 'SCHEDULED') === true, 'a no-op (same status) is allowed');

// ON_HOLD / CANCELLED never appear as a "next" for a terminal status.
t_ok(tosrm_allowed_next('CLOSED') === [], 'a CLOSED call offers no next status');

// --- tosrm_set_status enforces it, and $force overrides with an [override] note --
$pdo = db();
$pdo->prepare("INSERT INTO calls (call_code, status, op_status, created_at) VALUES ('R6-1','OPEN','SCHEDULED',?)")->execute([date('c')]);
$cid = (int)$pdo->lastInsertId();

t_ok(tosrm_set_status($cid, 'RECEIVED') === false, 'a disallowed transition is rejected by tosrm_set_status');
$after = ops_one("SELECT op_status FROM calls WHERE id=?", [$cid]);
t_ok($after['op_status'] === 'SCHEDULED', 'the rejected transition did not change op_status');

t_ok(tosrm_set_status($cid, 'IN_PROGRESS') === true, 'an allowed forward transition succeeds');

// A manager override can force a non-standard step, and it is recorded as an override.
t_ok(tosrm_set_status($cid, 'RECEIVED', 'client re-opened the request', 'Mgr', true) === true,
    'a forced override transition succeeds');
$hist = tosrm_status_history($cid);
t_ok(strpos((string)$hist[0]['reason'], '[override]') === 0, 'the override is recorded with an [override] marker');
t_ok($hist[0]['new_status'] === 'RECEIVED' && $hist[0]['old_status'] === 'IN_PROGRESS', 'the override transition is in history');

// --- the handler wires the manager override (needs a reason) ------------------
$tosrm = file_get_contents(__DIR__ . '/../lib/tosrm.php');
t_ok(strpos($tosrm, "\$force  = !empty(\$_POST['override']) && function_exists('is_admin_level') && is_admin_level();") !== false,
    'only a manager may set the override flag');
t_ok(strpos($tosrm, "if (\$force && trim(\$reason) === '') { flash('An override needs a reason.'") !== false,
    'an override without a reason is refused');
t_ok(strpos($tosrm, 'is not a valid next step from') !== false, 'a disallowed pick is explained to the user');
