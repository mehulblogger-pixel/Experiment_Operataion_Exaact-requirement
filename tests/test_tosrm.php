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

// ============================================================================
//  SLICE B — Assignment lifecycle on the jobs spine.
// ============================================================================
tosrm_migrate_b();
// Two resources + a job to move around.
db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Asha Rao','ACTIVE')")->execute(); $inspA = (int)db()->lastInsertId();
db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Bala Nair','ACTIVE')")->execute(); $inspB = (int)db()->lastInsertId();
db()->prepare("INSERT INTO jobs (call_id, inspector_id, scheduled_date, stage, created_at) VALUES (?,?,?,?,?)")
    ->execute([$cid, $inspA, date('Y-m-d', strtotime('+3 days')), 'ALLOCATED', date('c')]);
$jid = (int)db()->lastInsertId();
$J = fn() => ops_one("SELECT * FROM jobs WHERE id=?", [$jid]);

head('8. Additive assignment columns; legacy allocation reads as CONFIRMED');
$job = $J();
ok(array_key_exists('assign_state', $job) && array_key_exists('accept_state', $job), 'jobs.assign_state / accept_state columns added');
ok(tosrm_assign_state($job) === 'CONFIRMED', 'a legacy allocation (inspector set, no state) reads as CONFIRMED');

head('9. Tentative → Confirmed hold (no auto-move)');
ok(tosrm_assign_hold($jid, 'TENTATIVE', 'pencilled in') === true, 'a resource can be held TENTATIVE');
ok(tosrm_assign_state($J()) === 'TENTATIVE', 'the hold state is TENTATIVE');
ok(tosrm_assign_hold($jid, 'BOGUS') === false, 'an unknown hold state is rejected');
ok(tosrm_assign_hold($jid, 'CONFIRMED') === true && tosrm_assign_state($J()) === 'CONFIRMED', 'it can be confirmed');

head('10. Acceptance — decline requires a reason');
ok(tosrm_assign_accept($jid, 'DECLINED', '') === false, 'a decline with no reason is refused');
ok(tosrm_assign_accept($jid, 'DECLINED', 'On another site that day') === true, 'a decline with a reason is accepted');
ok($J()['accept_state'] === 'DECLINED' && trim($J()['accept_reason']) !== '', 'the decision + reason are stored');
ok(tosrm_assign_accept($jid, 'ACCEPTED') === true && $J()['accept_state'] === 'ACCEPTED', 'an acceptance needs no reason');

head('11. Reassignment PRESERVES the original + resets acceptance');
$before = $J()['inspector_id'];
ok(tosrm_reassign($jid, $inspB, 'A unavailable', 'Ops Mgr') === true, 'reassign to a different resource succeeds');
ok((int)$J()['inspector_id'] === $inspB, 'the job now carries the new resource');
ok($J()['accept_state'] === 'PENDING', 'acceptance resets to PENDING for the new resource');
ok(tosrm_reassign($jid, $inspB) === false, 'reassigning to the SAME resource is a no-op');
$ev = ops_one("SELECT * FROM assignment_events WHERE job_id=? AND kind='REASSIGN' ORDER BY id DESC", [$jid]);
ok((int)$ev['old_inspector_id'] === (int)$before && (int)$ev['new_inspector_id'] === $inspB, 'the ORIGINAL resource is preserved in history (never overwritten)');
ok($ev['approver'] === 'Ops Mgr', 'the approver is recorded on the reassignment');

head('12. Reschedule keeps the original date');
$oldDate = $J()['scheduled_date'];
$newDate = date('Y-m-d', strtotime('+10 days'));
ok(tosrm_reschedule($jid, $newDate, 'client shifted', 'Coordinator') === true, 'reschedule to a new date succeeds');
ok($J()['scheduled_date'] === $newDate, 'the job carries the new date');
$rev = ops_one("SELECT * FROM assignment_events WHERE job_id=? AND kind='RESCHEDULE' ORDER BY id DESC", [$jid]);
ok($rev['old_date'] === $oldDate && $rev['new_date'] === $newDate, 'the original date is kept in history');
ok(tosrm_reschedule($jid, $newDate) === false, 'rescheduling to the same date is a no-op');

head('13. No-show (no auto-blame) + cancellation with reason');
ok(tosrm_assign_noshow($jid, 'VENDOR', 'Gate closed, works not ready') === true, 'a no-show is recorded against a party');
ok(tosrm_assign_noshow($jid, 'NOBODY') === false, 'an unknown party is rejected');
ok(tosrm_assign_cancel($jid, '') === false, 'cancellation without a reason is refused');
ok(tosrm_assign_cancel($jid, 'Client withdrew the order') === true, 'cancellation with a reason is accepted');
ok($J()['stage'] === 'CANCELLED', 'the job stage moves to CANCELLED (an existing stage value)');

head('14. Full history retained + job panel renders');
$hist = tosrm_assign_history($jid);
ok(count($hist) >= 7, 'every assignment transition is retained in history (' . count($hist) . ')');
ob_start(); tosrm_render_job_panel($J()); $jh = ob_get_clean();
ok(strpos($jh, 'assignment lifecycle') !== false, 'the assignment-lifecycle panel renders on a job');
ok(strpos($jh, 'Assignment history') !== false && strpos($jh, 'Reassign') !== false, 'the panel shows history + the reassign control');

// ============================================================================
//  SLICE C — Readiness, client/vendor confirmation, competence-at-allocation.
// ============================================================================
tosrm_migrate_c();
db()->prepare("INSERT INTO jobs (call_id, inspector_id, scheduled_date, stage, created_at) VALUES (?,?,?,?,?)")
    ->execute([$cid, $inspA, date('Y-m-d'), 'ALLOCATED', date('c')]);
$jc = (int)db()->lastInsertId();
$JC = fn() => ops_one("SELECT * FROM jobs WHERE id=?", [$jc]);

head('15. Additive readiness/confirmation schema');
$job = $JC();
ok(array_key_exists('client_confirmed', $job) && array_key_exists('vendor_confirmed', $job) && array_key_exists('client_confirm_required', $job), 'confirmation columns added to jobs');
ok(is_array(tosrm_readiness_list($jc)), 'job_readiness table is queryable');

head('16. Generic readiness checklist (seed, set, rollup)');
ok(tosrm_readiness_seed($jc) === count(TOSRM_READY_ITEMS), 'seeding adds the default readiness items');
ok(tosrm_readiness_seed($jc) === 0, 'seeding again is idempotent (no duplicates)');
$items = tosrm_readiness_list($jc);
$roll0 = tosrm_readiness_rollup($jc);
ok($roll0['ready_bool'] === false && $roll0['open'] === count($items), 'a freshly-seeded checklist is NOT ready (all items open)');
foreach ($items as $it) tosrm_readiness_set((int)$it['id'], 'READY');
$roll1 = tosrm_readiness_rollup($jc);
ok($roll1['ready_bool'] === true && $roll1['open'] === 0, 'marking every item READY makes it ready');
tosrm_readiness_set((int)$items[0]['id'], 'BLOCKED', 'material not at works');
$roll2 = tosrm_readiness_rollup($jc);
ok($roll2['ready_bool'] === false && $roll2['blocked'] === 1, 'a blocked item drops readiness and is counted');
ok(tosrm_readiness_set((int)$items[0]['id'], 'BOGUS') === false, 'an unknown readiness status is rejected');

head('17. Client / vendor pre-execution confirmation (optional, gated only when required)');
$gate0 = tosrm_confirm_gate($JC());
ok($gate0['required'] === false && $gate0['blocks'] === false, 'confirmation is NOT required by default (never mandatory for every service)');
ok(tosrm_confirm_set($jc, 'CLIENT', true, 'Email 12-Aug from QA') === true, 'a client confirmation can be recorded with a reference');
ok((int)$JC()['client_confirmed'] === 1 && trim($JC()['client_confirm_ref']) !== '', 'the confirmation + reference are stored');
ok(tosrm_confirm_set($jc, 'NOBODY', true) === false, 'an unknown confirmation party is rejected');
tosrm_confirm_set($jc, 'CLIENT', false);            // clear it
tosrm_confirm_set_required($jc, true);              // now require it
$gate1 = tosrm_confirm_gate($JC());
ok($gate1['required'] === true && $gate1['blocks'] === true, 'when required-but-not-confirmed, the gate blocks (TEST 13)');
tosrm_confirm_set($jc, 'CLIENT', true, 'Confirmed on call');
ok(tosrm_confirm_gate($JC())['blocks'] === false, 'confirming clears the gate (TEST 14 path when not required also proceeds)');

head('18. Competence-at-allocation — advisory, reuses the competence engine, never blocks');
$warnNone = tosrm_competence_warn($JC());
ok(count($warnNone) === 1 && $warnNone[0]['level'] === 'ok', 'a resource with no cert concerns reads clean');
// A lapsed MANDATORY certificate and an expiring one.
db()->prepare("INSERT INTO inspector_certs (inspector_id, name, number, valid_to, is_mandatory) VALUES (?,?,?,?,1)")
    ->execute([$inspA, 'CSWIP 3.1', 'C-100', date('Y-m-d', strtotime('-10 days'))]);
db()->prepare("INSERT INTO inspector_certs (inspector_id, name, number, valid_to, is_mandatory) VALUES (?,?,?,?,0)")
    ->execute([$inspA, 'ASNT NDT L-II', 'A-200', date('Y-m-d', strtotime('+20 days'))]);
$warn = tosrm_competence_warn($JC());
$levels = array_column($warn, 'level');
ok(in_array('error', $levels, true), 'a lapsed mandatory certificate raises an ERROR-level warning at allocation');
ok(in_array('warn', $levels, true), 'a soon-expiring certificate raises a WARN-level advisory');
$roll = tosrm_readiness_rollup($jc);   // competence warning did NOT touch readiness or block anything
ok(is_array($roll), 'competence warnings are advisory only — nothing was blocked or changed');

head('19. The readiness/confirmation/competence panel renders');
ob_start(); tosrm_render_readiness_panel($JC()); $rh = ob_get_clean();
ok(strpos($rh, 'readiness &amp; confirmation') !== false, 'the readiness panel renders on a job');
ok(strpos($rh, 'Competence &amp; certification') !== false && strpos($rh, 'CSWIP 3.1') !== false, 'the panel shows the competence advisory');
ok(strpos($rh, 'Readiness checklist') !== false && strpos($rh, 'Pre-execution confirmation') !== false, 'the panel shows readiness + confirmation');

// ============================================================================
//  SLICE D — SLA, full-chain TAT, delay, recurring, capacity horizon.
// ============================================================================
tosrm_migrate_d();
$d = fn($n) => date('Y-m-d', strtotime($n . ' days'));
// A call with a full, dated lifecycle + a closed job.
$sd = $mkCall(['status'=>'OPEN', 'client_id'=>77, 'inspection_type'=>'INSPECTION',
    'call_received_date'=>$d(-20), 'forwarded_at'=>$d(-18), 'inspection_required_date'=>$d(-12), 'created_at'=>date('c')]);
db()->prepare("INSERT INTO jobs (call_id, inspector_id, scheduled_date, inspection_end_date, closed_at, closed_flag, created_at) VALUES (?,?,?,?,?,1,?)")
    ->execute([$sd, $inspA, $d(-10), $d(-8), $d(-6), date('c')]);
$sdJob = (int)db()->lastInsertId();
// The report the TAT chain reads comes from report_docs (issued at −6).
db()->prepare("INSERT INTO report_docs (job_id, irn, status, finalized, issue_date, created_at, updated_at) VALUES (?,?,?,1,?,?,?)")
    ->execute([$sdJob, 'TAT/26/0001', 'ISSUED', $d(-6), date('c'), date('c')]);

head('20. Full-chain TAT is computed from existing data');
$tat = tosrm_tat(ops_one("SELECT * FROM calls WHERE id=?", [$sd]));
ok($tat['stages']['received_to_scheduled'] === 10, 'received → scheduled = 10 days');
ok($tat['stages']['scheduled_to_executed'] === 2, 'scheduled → executed = 2 days');
ok($tat['stages']['executed_to_report'] === 2, 'executed → report = 2 days');
ok($tat['stages']['received_to_closure'] === 14, 'received → closure = 14 days (whole chain)');

head('21. SLA targets (default + per-client override) and evaluation');
tosrm_sla_set(0, 'RESPONSE', 3); tosrm_sla_set(0, 'SCHEDULING', 5); tosrm_sla_set(0, 'REPORT', 1);
$tg = tosrm_sla_targets(0);
ok($tg['SCHEDULING'] === 5, 'a default SLA target is stored and read');
tosrm_sla_set(77, 'SCHEDULING', 15);   // client 77 gets a laxer scheduling SLA
$tgC = tosrm_sla_targets(77);
ok($tgC['SCHEDULING'] === 15 && $tgC['RESPONSE'] === 3, 'a per-client override wins; other stages fall back to default');
$eval = tosrm_sla_eval(ops_one("SELECT * FROM calls WHERE id=?", [$sd]));
ok($eval['RESPONSE']['status'] === 'WITHIN', 'response 2d within 3d = WITHIN');
ok($eval['SCHEDULING']['status'] === 'WITHIN', 'scheduling 10d within the client override 15d = WITHIN');
ok($eval['REPORT']['status'] === 'BREACHED', 'report 2d over 1d target = BREACHED');
ok($eval['EXECUTION']['status'] === 'NA', 'a stage with no target reads N/A (never invented)');
ok(tosrm_sla_set(0, 'BOGUS', 4) === false, 'an unknown SLA stage is rejected');

head('22. Delay on the core job (auto days; links to Phase 6 for expediting)');
$delId = tosrm_delay_add($sdJob, ['reason'=>'RESOURCE', 'responsibility'=>'TPIA', 'impact'=>'slipped 2 days']);
ok($delId > 0, 'a delay is recorded on the job');
$del = ops_one("SELECT * FROM job_delays WHERE id=?", [$delId]);
ok((int)$del['delay_days'] === 2, 'delay days auto-computed from required (−12) vs scheduled (−10) = 2');
ok((int)$del['is_expediting'] === 0, 'a non-expediting job does not carry the Phase-6 link flag');
// An expediting job marks the Phase-6 link instead of duplicating.
db()->prepare("INSERT INTO jobs (call_id, inspection_type, scheduled_date, created_at) VALUES (?,?,?,?)")->execute([$sd, 'EXPEDITING', $d(-9), date('c')]);
$expJob = (int)db()->lastInsertId();
$expDel = tosrm_delay_add($expJob, ['reason'=>'VENDOR']);
ok((int)ops_one("SELECT is_expediting FROM job_delays WHERE id=?", [$expDel])['is_expediting'] === 1, 'an expediting delay is flagged to link to Phase 6 (not duplicated)');
ok(count(tosrm_delay_list_for_call($sd)) === 2, 'delays list for the call');

head('23. Recurring services generate future calls, clearly marked');
ok(tosrm_recur_next('WEEKLY', 1, '2026-01-01') === '2026-01-08', 'weekly recurrence advances by 7 days');
ok(tosrm_recur_next('MONTHLY', 1, '2026-01-15') === '2026-02-15', 'monthly recurrence advances by a month');
$recId = tosrm_recurring_create(['client_id'=>77, 'title'=>'Monthly vendor audit', 'frequency'=>'MONTHLY',
    'start_date'=>date('Y-m-d'), 'template'=>['inspection_type'=>'VENDOR_AUDIT', 'deliverables'=>'VAR']]);
ok($recId > 0, 'a recurring schedule is created');
ok(tosrm_recurring_create(['title'=>'']) === 0, 'a schedule with no title is refused');
$due = tosrm_recurring_due(date('Y-m-d'));
ok(count(array_filter($due, fn($r)=>(int)$r['id']===$recId)) === 1, 'a schedule starting today is due');
$before = (int)ops_one("SELECT COUNT(*) n FROM calls")['n'];
$newCall = tosrm_recurring_generate(ops_one("SELECT * FROM recurring_services WHERE id=?", [$recId]));
$after = (int)ops_one("SELECT COUNT(*) n FROM calls")['n'];
ok($after === $before + 1, 'generating creates exactly one call');
$gc = ops_one("SELECT * FROM calls WHERE id=?", [$newCall]);
ok($gc['source'] === 'RECURRING' && (int)$gc['generated_by_recurring_id'] === $recId, 'the generated call is clearly tagged as generated (source + link)');
ok($gc['inspection_type'] === 'VENDOR_AUDIT', 'the generated call carries the template service type');
$rec2 = ops_one("SELECT * FROM recurring_services WHERE id=?", [$recId]);
ok((int)$rec2['generated_count'] === 1 && $rec2['next_run'] > date('Y-m-d'), 'the schedule advanced its next run and counted the generation');
ok(tosrm_run_recurring($d(-1)) === 0, 'the cron entry generates nothing when nothing is due');

head('24. Capacity horizon (reuses the availability engine)');
$hz = tosrm_capacity_horizon('ALL', date('Y-m-d'), $d(13));
ok(is_array($hz) && count($hz) >= 1, 'the capacity outlook produces weekly buckets (' . count($hz) . ')');
$b0 = $hz[0];
ok(array_key_exists('supply', $b0) && array_key_exists('demand', $b0) && array_key_exists('gap', $b0), 'each bucket carries supply, demand and gap');
ok($b0['gap'] === $b0['demand'] - $b0['supply'], 'the gap is a plain, traceable demand − supply');

head('25. SLA/TAT/delay panel renders on the call');
ob_start(); tosrm_render_call_sla(ops_one("SELECT * FROM calls WHERE id=?", [$sd])); $sh = ob_get_clean();
ok(strpos($sh, 'turnaround &amp; SLA') !== false, 'the SLA/TAT panel renders');
ok(strpos($sh, 'Received → Closure') !== false && strpos($sh, 'Delays') !== false, 'the panel shows the TAT chain and delays');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TOSRM: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
