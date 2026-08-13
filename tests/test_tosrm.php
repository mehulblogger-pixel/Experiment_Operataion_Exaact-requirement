<?php
// ============================================================================
//  TOSRM Phase 9 — Slice A: Service-Request lifecycle over the calls spine.
//  Proves the additive layer works WITHOUT touching the legacy call flow:
//  status lifecycle + history, validation gate + override, clarification thread,
//  generic (non-inspection) service requests, and the call-detail panel render.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

tosrm_migrate();

// A tiny helper to spin up a call row of a given service type with chosen fields.
$mkCall = function(array $f) {
    $cols = array_keys($f); $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare("INSERT INTO calls (" . implode(',', $cols) . ") VALUES ($ph)")->execute(array_values($f));
    return (int)db()->lastInsertId();
};

head('1. Additive schema — new columns exist, legacy status untouched');
$cid = $mkCall(['status'=>'OPEN', 'client_id'=>0, 'inspection_type'=>'', 'created_at'=>date('c')]);
$row = ops_one("SELECT * FROM calls WHERE id=?", [$cid]);
ok(array_key_exists('op_status', $row), 'calls.op_status added (lifecycle lives beside legacy status)');
ok(array_key_exists('priority', $row) && array_key_exists('criticality', $row) && array_key_exists('source', $row), 'priority / criticality / source columns added');
ok($row['status'] === 'OPEN', 'the legacy status column is left exactly as it was');

head('2. Status lifecycle + history (never overwrites, always logs)');
ok(tosrm_call_status($row) === 'RECEIVED', 'an OPEN call derives lifecycle status RECEIVED before any op_status set');
ok(tosrm_set_status($cid, 'UNDER_REVIEW', 'triage') === true, 'set to UNDER_REVIEW succeeds');
ok(tosrm_set_status($cid, 'READY_TO_SCHEDULE') === true, 'advance to READY_TO_SCHEDULE succeeds');
ok(tosrm_set_status($cid, 'BOGUS_STATUS') === false, 'an unknown status is rejected');
$row = ops_one("SELECT * FROM calls WHERE id=?", [$cid]);
ok($row['op_status'] === 'READY_TO_SCHEDULE', 'op_status holds the current lifecycle value');
ok($row['status'] === 'OPEN', 'setting the lifecycle status did NOT touch legacy status');
$hist = tosrm_status_history($cid);
ok(count($hist) === 2, 'each transition is recorded (2 events)');
ok($hist[0]['new_status'] === 'READY_TO_SCHEDULE' && $hist[0]['old_status'] === 'UNDER_REVIEW', 'history keeps from→to, newest first');

head('3. Validation gate + authorised override');
$bare = $mkCall(['status'=>'OPEN', 'client_id'=>0, 'inspection_type'=>'', 'created_at'=>date('c')]);
$bareRow = ops_one("SELECT * FROM calls WHERE id=?", [$bare]);
$missing = tosrm_validate_call($bareRow);
ok(in_array('Client', $missing, true) && in_array('Service type', $missing, true), 'an empty call reports its missing mandatory fields');
$ready = tosrm_call_ready($bareRow);
ok($ready['ok'] === false, 'an incomplete call is NOT ready for scheduling');
ok(tosrm_override_validation($bare, 'Client on phone, details to follow') === true, 'an authorised override with a reason is accepted');
ok(tosrm_override_validation($bare, '') === false, 'override without a reason is refused');
$bareRow = ops_one("SELECT * FROM calls WHERE id=?", [$bare]);
$ready = tosrm_call_ready($bareRow);
ok($ready['ok'] === true && $ready['overridden'] === true, 'after override the call may proceed, and the override is visible');

// A complete call needs no override.
$full = $mkCall(['status'=>'OPEN', 'client_id'=>5, 'inspection_type'=>'INSPECTION', 'inspection_required_date'=>date('Y-m-d'),
                 'site_address_id'=>3, 'deliverables'=>'IR', 'created_at'=>date('c')]);
ok(empty(tosrm_validate_call(ops_one("SELECT * FROM calls WHERE id=?", [$full]))), 'a fully-specified call has no missing fields');

head('4. Generic service — not inspection-only (audit / assessment / expediting / deputation)');
foreach (['VENDOR_AUDIT'=>'audit', 'VENDOR_ASSESS'=>'assessment', 'EXPEDITING'=>'expediting', 'DEPUTATION'=>'deputation'] as $svc=>$word) {
    $id = $mkCall(['status'=>'OPEN', 'client_id'=>7, 'inspection_type'=>$svc, 'inspection_required_date'=>date('Y-m-d'),
                   'vendor_id'=>9, 'deliverables'=>'REP', 'created_at'=>date('c')]);
    $r = ops_one("SELECT * FROM calls WHERE id=?", [$id]);
    $ok = tosrm_set_status($id, 'ASSIGNED') && empty(tosrm_validate_call($r));
    ok($ok, "a $word service request works with no inspection dependency ($svc)");
}
// TEST 6 — a generic service with no linked operational module still validates & advances.
$generic = $mkCall(['status'=>'OPEN', 'client_id'=>7, 'inspection_type'=>'OTHER', 'inspection_required_date'=>date('Y-m-d'),
                    'site_address_id'=>2, 'deliverables'=>'Note', 'created_at'=>date('c')]);
ok(empty(tosrm_validate_call(ops_one("SELECT * FROM calls WHERE id=?", [$generic]))) && tosrm_set_status($generic, 'COMPLETED'),
   'a generic "Other" service request remains fully usable (no module required)');

head('5. Clarification thread');
$clarId = tosrm_clar_create($cid, ['subject'=>'Confirm heat-treatment scope', 'detail'=>'Is PWHT in scope?', 'raised_to'=>'CLIENT']);
ok($clarId > 0, 'a clarification is raised against the call');
ok(tosrm_clar_open_count($cid) === 1, 'it counts as OPEN until answered');
ok(tosrm_clar_create($cid, ['subject'=>'']) === 0, 'a clarification with no subject is refused');
ok(tosrm_clar_respond($clarId, 'Yes, PWHT included') === true, 'a response can be recorded');
$clar = ops_one("SELECT * FROM call_clarifications WHERE id=?", [$clarId]);
ok($clar['status'] === 'ANSWERED' && trim($clar['response']) !== '', 'answering flips status to ANSWERED and stores the reply');
ok(tosrm_clar_open_count($cid) === 0, 'no open clarifications remain');

head('6. Options are lookup-driven (admin can extend)');
ok(count(tosrm_status_options()) >= 10 && isset(tosrm_status_options()['CANCELLED']), 'status options resolve from the lookup master');
ok(isset(tosrm_priority_options()['CRITICAL']) && isset(tosrm_criticality_options()['SAFETY']) && isset(tosrm_source_options()['WHATSAPP']),
   'priority / criticality / source options resolve');

head('7. The call-detail panel renders');
$call = ops_one("SELECT * FROM calls WHERE id=?", [$cid]);
ob_start(); tosrm_render_call_panel($call); $html = ob_get_clean();
ok(strpos($html, 'Operations — service request') !== false, 'the operations panel renders on a call');
ok(strpos($html, 'Clarifications') !== false && strpos($html, 'Confirm heat-treatment scope') !== false, 'the panel shows the clarification thread');
ok(strpos($html, 'Status history') !== false, 'the panel shows status history');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TOSRM: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
