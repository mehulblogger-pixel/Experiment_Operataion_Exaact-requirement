<?php
// ===========================================================================
//  TOSRM — TPIA Operations & Scheduling Resource Management  (Phase 9)
//  SLICE A — Service-Request lifecycle.
//
//  This is an ADDITIVE operations layer over the EXISTING calls spine. It does
//  NOT rename `calls`, does NOT introduce a second request store, and does NOT
//  touch the forward/allocate/close flow. It adds, on top of what a call already
//  is, the things a generic service request needs and the call did not have:
//    • a first-class STATUS LIFECYCLE (received → … → closed / cancelled),
//      kept alongside the legacy `status` so nothing existing breaks;
//    • PRIORITY, CRITICALITY and SOURCE / channel of the request;
//    • a VALIDATION gate (mandatory fields before scheduling) that an
//      authorised user may OVERRIDE with a recorded reason;
//    • a CLARIFICATION thread (raise to client / vendor / internal, track the
//      answer) so an incomplete request never silently enters scheduling.
//
//  Every service type already lives on `calls.inspection_type` (37 values incl.
//  audit / assessment / expediting / deputation / witness), so the request is
//  generic today — nothing here assumes "inspection". All lists are lookups, so
//  an administrator can add statuses / priorities / sources without code.
// ===========================================================================

// A DEFAULT lifecycle. The actual path is not enforced — a service may skip
// states (audit: REQUEST → ASSIGN → AUDIT → REPORT). Configurable via lookup.
const CALL_STATUSES = [
    'RECEIVED'          => 'Received',
    'DRAFT'             => 'Draft',
    'UNDER_REVIEW'      => 'Under review',
    'CLARIFICATION'     => 'Clarification required',
    'ACCEPTED'          => 'Accepted',
    'REJECTED'          => 'Rejected',
    'ON_HOLD'           => 'On hold',
    'READY_TO_SCHEDULE' => 'Ready for scheduling',
    'SCHEDULED'         => 'Scheduled',
    'ASSIGNED'          => 'Assigned',
    'IN_PROGRESS'       => 'In progress',
    'COMPLETED'         => 'Completed',
    'REPORT_PENDING'    => 'Report pending',
    'CLOSED'            => 'Closed',
    'CANCELLED'         => 'Cancelled',
];
// Urgency of handling — separate from criticality.
const SERVICE_PRIORITIES = ['CRITICAL'=>'Critical','HIGH'=>'High','NORMAL'=>'Normal','LOW'=>'Low'];
// Why the work matters — deliberately NOT the same axis as priority.
const SERVICE_CRITICALITY = [
    'PROJECT'=>'Project impact','PRODUCT'=>'Product criticality','SCHEDULE'=>'Schedule impact',
    'REGULATORY'=>'Regulatory impact','CUSTOMER'=>'Customer impact','SAFETY'=>'Safety',
    'COMMERCIAL'=>'Commercial impact','OTHER'=>'Other',
];
// How the request reached us — captured, never used to replace email/WhatsApp.
const CALL_SOURCES = [
    'EMAIL'=>'Email','PORTAL'=>'Client portal','PHONE'=>'Phone','WHATSAPP'=>'WhatsApp / message',
    'MANUAL'=>'Manual entry','API'=>'API','CONTRACT'=>'Contract','PO'=>'Purchase order',
    'RECURRING'=>'Recurring schedule','INTERNAL'=>'Internal request','OTHER'=>'Other',
];
const TOSRM_CLAR_TO     = ['CLIENT'=>'Client','VENDOR'=>'Vendor','INTERNAL'=>'Internal','OTHER'=>'Other'];
const TOSRM_CLAR_STATUS = ['OPEN'=>'Open','ANSWERED'=>'Answered','CLOSED'=>'Closed'];

// ---------------------------------------------------------------------------
//  Schema — additive only.
// ---------------------------------------------------------------------------
function tosrm_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('call_status',        'Service-request status',  CALL_STATUSES,       'tosrm');
        lk_ensure_type_map('service_priority',   'Service priority',        SERVICE_PRIORITIES,  'tosrm');
        lk_ensure_type_map('service_criticality','Service criticality',     SERVICE_CRITICALITY, 'tosrm');
        lk_ensure_type_map('call_source',        'Request source / channel',CALL_SOURCES,        'tosrm');
        lk_ensure_type_map('clarification_to',   'Clarification raised to', TOSRM_CLAR_TO,       'tosrm');
        lk_ensure_type_map('clarification_status','Clarification status',   TOSRM_CLAR_STATUS,   'tosrm');
    }
    if (function_exists('ensure_column')) {
        // The new lifecycle status lives in its OWN column so the legacy
        // `status` (OPEN/FORWARDED/ALLOCATED/CLOSED) and everything reading it
        // are untouched. Empty op_status => fall back to a derived value.
        // NOTE (R10 — vestigial value): in normal app flow legacy `calls.status`
        // only ever reaches OPEN → FORWARDED → ALLOCATED. 'CLOSED' is READ as a
        // guard in a few places but is never WRITTEN by app code (only by seed
        // data); the real "is this call finished" signal is its jobs being closed
        // and op_status. Treat calls.status='CLOSED' as legacy/seed-only.
        ensure_column('calls', 'op_status',   "VARCHAR(24) DEFAULT ''");
        ensure_column('calls', 'priority',    "VARCHAR(20) DEFAULT ''");
        ensure_column('calls', 'criticality', "VARCHAR(20) DEFAULT ''");
        ensure_column('calls', 'source',      "VARCHAR(24) DEFAULT ''");
        // Validation override (authorised user may let an incomplete request
        // through, with a recorded reason).
        ensure_column('calls', 'val_override_reason', "VARCHAR(400) DEFAULT ''");
        ensure_column('calls', 'val_override_by',     "VARCHAR(120) DEFAULT ''");
        ensure_column('calls', 'val_override_at',     "VARCHAR(30) DEFAULT ''");
    }
    $pk = pk_clause();
    // Status history — every transition kept, never overwritten.
    db()->exec("CREATE TABLE IF NOT EXISTS call_status_events (
        id $pk, call_id INT DEFAULT 0, old_status VARCHAR(24) DEFAULT '', new_status VARCHAR(24) DEFAULT '',
        reason VARCHAR(400) DEFAULT '', actor VARCHAR(120) DEFAULT '', at VARCHAR(30) DEFAULT '')");
    // Clarification thread against a call.
    db()->exec("CREATE TABLE IF NOT EXISTS call_clarifications (
        id $pk, call_id INT DEFAULT 0, subject VARCHAR(200) DEFAULT '', detail VARCHAR(1000) DEFAULT '',
        raised_to VARCHAR(24) DEFAULT 'CLIENT', raised_by VARCHAR(120) DEFAULT '', raised_at VARCHAR(30) DEFAULT '',
        due_on VARCHAR(20) DEFAULT '', response VARCHAR(1000) DEFAULT '', responded_by VARCHAR(120) DEFAULT '',
        responded_at VARCHAR(30) DEFAULT '', status VARCHAR(16) DEFAULT 'OPEN', created_at VARCHAR(30) DEFAULT '')");
}

// ---------------------------------------------------------------------------
//  Small helpers.
// ---------------------------------------------------------------------------
function tosrm_actor() {
    if (function_exists('user_name') && function_exists('current_user')) { $n = user_name(current_user()); if ($n) return $n; }
    return 'system';
}
function tosrm_now() { return date('c'); }
function tosrm_status_options()      { return function_exists('lk_options_or') ? lk_options_or('call_status', CALL_STATUSES) : CALL_STATUSES; }
function tosrm_priority_options()    { return function_exists('lk_options_or') ? lk_options_or('service_priority', SERVICE_PRIORITIES) : SERVICE_PRIORITIES; }
function tosrm_criticality_options() { return function_exists('lk_options_or') ? lk_options_or('service_criticality', SERVICE_CRITICALITY) : SERVICE_CRITICALITY; }
function tosrm_source_options()      { return function_exists('lk_options_or') ? lk_options_or('call_source', CALL_SOURCES) : CALL_SOURCES; }
function tosrm_clar_to_options()     { return function_exists('lk_options_or') ? lk_options_or('clarification_to', TOSRM_CLAR_TO) : TOSRM_CLAR_TO; }

// When op_status has not been set yet, derive a sensible lifecycle value from
// the legacy status + whether the call already carries jobs — so the panel
// reads correctly on a call created before this slice existed.
function tosrm_derive_status($call) {
    $legacy = strtoupper((string)($call['status'] ?? ''));
    if ($legacy === 'CLOSED')    return 'CLOSED';
    if ($legacy === 'CANCELLED') return 'CANCELLED';
    if ($legacy === 'ALLOCATED') return 'ASSIGNED';
    if ($legacy === 'FORWARDED') return 'READY_TO_SCHEDULE';
    return 'RECEIVED';
}
function tosrm_call_status($call) {
    $op = trim((string)($call['op_status'] ?? ''));
    return $op !== '' ? $op : tosrm_derive_status($call);
}

// Phase 2 §46 — "do not allow two statuses to silently disagree." A call carries a
// legacy `status` and a canonical `op_status`; once op_status is set the legacy value
// is ignored, so the two can drift (one system says CLOSED while the other says OPEN).
// A genuine disagreement is a TERMINALITY mismatch: op_status is set, and exactly one
// of the two says the call is finished (CLOSED/CANCELLED). That is the record to flag
// for repair — a benign in-progress difference is not flagged.
function tosrm_status_terminal($s) { return in_array(strtoupper((string)$s), ['CLOSED', 'CANCELLED'], true); }
function call_status_disagrees($call) {
    $op = trim((string)($call['op_status'] ?? ''));
    if ($op === '') return false;                                  // no canonical value → nothing to disagree with
    return tosrm_status_terminal($op) !== tosrm_status_terminal($call['status'] ?? '');
}
function calls_status_disagreement_count() {
    try {
        $n = 0;
        foreach (ops_all("SELECT status, op_status FROM calls WHERE COALESCE(op_status,'') <> ''") ?: [] as $c)
            if (call_status_disagrees($c)) $n++;
        return $n;
    } catch (Throwable $e) { return 0; }
}

// Module 04 — the ONE user-facing lifecycle label for a call, over the two status
// systems: op_status when set, else derived from legacy. This is what normal users
// should see instead of the raw legacy string. Returns ['key','label','tone'].
function call_status_label($call) {
    $key = tosrm_call_status($call);
    $labels = function_exists('lk_options_or') ? lk_options_or('call_status', CALL_STATUSES) : CALL_STATUSES;
    $label = $labels[$key] ?? (CALL_STATUSES[$key] ?? $key);
    // Tone by phase: terminal-bad / on-hold / done / in-flight / intake.
    $tone = 'p-info';
    if (in_array($key, ['REJECTED', 'CANCELLED'], true)) $tone = 'p-bad';
    elseif ($key === 'ON_HOLD')                          $tone = 'p-warn';
    elseif ($key === 'CLOSED' || $key === 'COMPLETED')   $tone = 'p-ok';
    elseif (in_array($key, ['RECEIVED', 'DRAFT', 'UNDER_REVIEW', 'CLARIFICATION'], true)) $tone = 'p-mut';
    return ['key' => $key, 'label' => $label, 'tone' => $tone];
}

// R6 — the lifecycle a call may actually walk. The op_status picker used to let any
// editor jump to ANY of the 15 statuses (validated only for set-membership), so a
// call could be CLOSED then moved back to RECEIVED, or a cancelled one silently
// revived. The active statuses have a forward RANK: a call may move forward along
// it (or within a phase), pause (ON_HOLD) or abort (CANCELLED) from any live status,
// and be REJECTED only while still in intake. The three terminal states have no exit.
// A manager may still OVERRIDE with a reason for the genuine exception.
function tosrm_status_rank() {
    return ['RECEIVED'=>1,'DRAFT'=>1,'UNDER_REVIEW'=>2,'CLARIFICATION'=>2,'ACCEPTED'=>3,
        'READY_TO_SCHEDULE'=>4,'SCHEDULED'=>5,'ASSIGNED'=>6,'IN_PROGRESS'=>7,
        'COMPLETED'=>8,'REPORT_PENDING'=>8,'CLOSED'=>9];
}
// The statuses that may legally follow $from.
function tosrm_allowed_next($from) {
    $from = strtoupper(trim((string)$from));
    $rank = tosrm_status_rank();
    if (in_array($from, ['REJECTED','CLOSED','CANCELLED'], true)) return [];   // terminal — no exit
    if ($from === 'ON_HOLD') {                                                 // resume anywhere active, or abort
        return array_values(array_unique(array_merge(array_keys($rank), ['CANCELLED'])));
    }
    $next = [];
    foreach ($rank as $s => $r) if ($s !== $from && $r >= ($rank[$from] ?? 0)) $next[] = $s;  // forward / same phase
    $next[] = 'ON_HOLD'; $next[] = 'CANCELLED';                                // pause / abort from any live status
    if (($rank[$from] ?? 99) <= 2) $next[] = 'REJECTED';                       // reject only while in intake
    return array_values(array_unique($next));
}
function tosrm_can_transition($from, $to) {
    $from = strtoupper(trim((string)$from)); $to = strtoupper(trim((string)$to));
    if ($from === $to) return true;                        // a no-op is always fine
    return in_array($to, tosrm_allowed_next($from), true);
}

// Move a call to a new lifecycle status, recording the transition. Returns false
// for an unknown target OR a disallowed transition (unless $force — a manager
// override, which is still recorded with its reason). Never changes legacy `status`.
function tosrm_set_status($callId, $to, $reason = '', $actor = '', $force = false) {
    $to = strtoupper(trim((string)$to));
    if ($to === '' || !array_key_exists($to, tosrm_status_options())) return false;
    $call = ops_one("SELECT * FROM calls WHERE id=?", [(int)$callId]);
    if (!$call) return false;
    $from = tosrm_call_status($call);
    if (!$force && !tosrm_can_transition($from, $to)) return false;   // R6 — not a legal step
    db()->prepare("UPDATE calls SET op_status=? WHERE id=?")->execute([$to, (int)$callId]);
    $note = ($force && !tosrm_can_transition($from, $to)) ? trim('[override] ' . $reason) : (string)$reason;
    db()->prepare("INSERT INTO call_status_events (call_id, old_status, new_status, reason, actor, at) VALUES (?,?,?,?,?,?)")
        ->execute([(int)$callId, $from, $to, $note, $actor !== '' ? $actor : tosrm_actor(), tosrm_now()]);
    if (function_exists('idems_log')) { try { idems_log('call', (int)$callId, 'OP_STATUS', ['field'=>'op_status','old'=>$from,'new'=>$to,'reason'=>$note]); } catch (Throwable $e) {} }
    return true;
}
function tosrm_status_history($callId) {
    return ops_all("SELECT * FROM call_status_events WHERE call_id=? ORDER BY id DESC", [(int)$callId]) ?: [];
}

// ---------------------------------------------------------------------------
//  Validation — a call should not silently enter scheduling half-defined.
//  A sensible core set is mandatory; an authorised user may OVERRIDE with a
//  reason. (Which fields are mandatory can be refined per service later.)
// ---------------------------------------------------------------------------
function tosrm_mandatory() {
    // key => [human label, test(callRow):bool present]
    return [
        'client'   => ['Client',        fn($c) => (int)($c['client_id'] ?? 0) > 0],
        'service'  => ['Service type',  fn($c) => trim((string)($c['inspection_type'] ?? '')) !== ''],
        'required' => ['Required date', fn($c) => trim((string)($c['inspection_required_date'] ?? '')) !== ''],
        'location' => ['Location / site',fn($c) => (int)($c['site_address_id'] ?? 0) > 0 || (int)($c['vendor_id'] ?? 0) > 0],
        'deliver'  => ['Deliverable',   fn($c) => trim((string)($c['deliverables'] ?? '')) !== ''],
    ];
}
// Returns the list of missing mandatory-field labels (empty => complete).
function tosrm_validate_call($call) {
    $missing = [];
    foreach (tosrm_mandatory() as $k => [$label, $test]) { if (!$test($call)) $missing[] = $label; }
    return $missing;
}
// Overall readiness for scheduling: complete, or overridden-with-reason.
function tosrm_call_ready($call) {
    $missing = tosrm_validate_call($call);
    $overridden = trim((string)($call['val_override_reason'] ?? '')) !== '';
    return ['ok' => empty($missing) || $overridden, 'missing' => $missing, 'overridden' => $overridden,
            'override_by' => (string)($call['val_override_by'] ?? ''), 'override_reason' => (string)($call['val_override_reason'] ?? '')];
}
function tosrm_override_validation($callId, $reason, $actor = '') {
    $reason = trim((string)$reason); if ($reason === '') return false;
    db()->prepare("UPDATE calls SET val_override_reason=?, val_override_by=?, val_override_at=? WHERE id=?")
        ->execute([$reason, $actor !== '' ? $actor : tosrm_actor(), tosrm_now(), (int)$callId]);
    if (function_exists('idems_log')) { try { idems_log('call', (int)$callId, 'VALIDATION_OVERRIDE', ['reason'=>$reason]); } catch (Throwable $e) {} }
    return true;
}

// ---------------------------------------------------------------------------
//  Clarifications.
// ---------------------------------------------------------------------------
function tosrm_clar_create($callId, $data) {
    $subject = trim((string)($data['subject'] ?? '')); if ($subject === '') return 0;
    db()->prepare("INSERT INTO call_clarifications (call_id, subject, detail, raised_to, raised_by, raised_at, due_on, status, created_at)
        VALUES (?,?,?,?,?,?,?, 'OPEN', ?)")->execute([
        (int)$callId, $subject, (string)($data['detail'] ?? ''), strtoupper((string)($data['raised_to'] ?? 'CLIENT')),
        tosrm_actor(), tosrm_now(), (string)($data['due_on'] ?? ''), tosrm_now()]);
    $id = (int)db()->lastInsertId();
    if (function_exists('idems_log')) { try { idems_log('call', (int)$callId, 'CLARIFICATION', ['new'=>$subject]); } catch (Throwable $e) {} }
    return $id;
}
function tosrm_clar_respond($id, $response, $actor = '') {
    $response = trim((string)$response); if ($response === '') return false;
    db()->prepare("UPDATE call_clarifications SET response=?, responded_by=?, responded_at=?, status='ANSWERED' WHERE id=?")
        ->execute([$response, $actor !== '' ? $actor : tosrm_actor(), tosrm_now(), (int)$id]);
    return true;
}
function tosrm_clar_set_status($id, $status) {
    $status = strtoupper(trim((string)$status));
    if (!array_key_exists($status, TOSRM_CLAR_STATUS)) return false;
    db()->prepare("UPDATE call_clarifications SET status=? WHERE id=?")->execute([$status, (int)$id]);
    return true;
}
function tosrm_clar_list($callId) { return ops_all("SELECT * FROM call_clarifications WHERE call_id=? ORDER BY id DESC", [(int)$callId]) ?: []; }
function tosrm_clar_open_count($callId) { $r = ops_one("SELECT COUNT(*) n FROM call_clarifications WHERE call_id=? AND status='OPEN'", [(int)$callId]); return (int)($r['n'] ?? 0); }

// ---------------------------------------------------------------------------
//  Permission — who may drive the operations lifecycle on a call.
// ---------------------------------------------------------------------------
function tosrm_can_edit() {
    // A master admin may do anything.
    if (function_exists('is_master') && is_master()) return true;
    // An inspector is a field role, never an operations manager. The assignment
    // console (hold/reassign/reschedule/cancel), readiness and the service-request
    // panel are not theirs to see or change — their work is the schedule, site
    // check-in and the report. Refuse here even if the account happens to carry a
    // stray edit permission, so the console can never leak to the field.
    if (function_exists('is_inspector') && is_inspector()) return false;
    if (function_exists('can') && (can('mod.calls.edit') || can('ops.job.allocate'))) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

// ---------------------------------------------------------------------------
//  Panel — rendered on the call detail page. Self-contained (echoes HTML), so
//  hosting it is a one-line include and it stays fully unit-testable.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  Order playbook — the coordinator's forward path on a call (the order), the
//  sibling of the job playbook. Same shape: pure computation, one 'do this now'
//  step, a real blocker when the order is not ready to schedule.
//    details complete -> allocate the work -> set the dates -> track to close
// ---------------------------------------------------------------------------
function tosrm_call_playbook($call) {
    $cid   = (int)($call['id'] ?? 0);
    $ready = function_exists('tosrm_call_ready') ? tosrm_call_ready($call) : ['missing'=>[], 'overridden'=>false];
    $missing    = $ready['missing'] ?? [];
    $overridden = !empty($ready['overridden']);
    $detailsOk  = empty($missing) || $overridden;

    $rows = $cid ? ops_all("SELECT scheduled_date, closed_flag FROM jobs WHERE call_id=?", [$cid]) : [];
    $jobsN = count($rows); $schedN = 0; $closedN = 0;
    foreach ($rows as $j) {
        if (trim((string)($j['scheduled_date'] ?? '')) !== '') $schedN++;
        if (!empty($j['closed_flag'])) $closedN++;
    }
    $callDone  = strtoupper((string)($call['status'] ?? '')) === 'CLOSED' || ($jobsN > 0 && $closedN === $jobsN);
    $canAlloc  = function_exists('call_can_allocate') ? call_can_allocate($call)
                 : (function_exists('is_coordinator_level') && is_coordinator_level());
    $openClars = function_exists('tosrm_clar_open_count') ? (int) tosrm_clar_open_count($cid) : 0;
    $newJob    = '/job-new?call=' . $cid;

    $steps = [];

    // 1 — Order details complete (or explicitly overridden).
    $steps[] = ['key'=>'details', 'label'=>'Complete the order details', 'actionable'=>true,
        'done'=>$detailsOk, 'blocker'=>(!$detailsOk),
        'line'=> $detailsOk
            ? ($overridden && $missing ? 'Proceeding on an override — still missing: ' . implode(', ', $missing) . '.'
                                       : 'All mandatory details are present.')
            : 'Missing: ' . implode(', ', $missing) . ' — fill these in, or override to proceed.',
        'href'=>'#ops', 'cta'=>'Complete details'];

    // 2 — Allocate the work to an engineer (this creates the job).
    $steps[] = ['key'=>'allocate', 'label'=>'Allocate the work', 'actionable'=>true,
        'done'=>$jobsN > 0,
        'line'=> $jobsN > 0 ? ($jobsN . ' job' . ($jobsN > 1 ? 's' : '') . ' allocated.')
               : ($canAlloc ? 'Assign an engineer so someone can be sent to site.'
                            : 'The executing office allocates this — you can see and track it here.'),
        'href'=> $canAlloc ? $newJob : '', 'cta'=> $jobsN > 0 ? 'Allocate another' : 'Allocate a job'];

    // 3 — Every allocated job has a visit date.
    $schedDone = $jobsN > 0 && $schedN === $jobsN;
    $steps[] = ['key'=>'schedule', 'label'=>'Set the visit dates', 'actionable'=>true,
        'done'=>$schedDone,
        'line'=> $jobsN === 0 ? 'Set once the work is allocated.'
               : ($schedDone ? 'Every allocated job has a date.'
                  : (($jobsN - $schedN) . ' allocated job(s) still have no date — open them to set it.')),
        'href'=>'#jobs', 'cta'=>'Open jobs'];

    // 4 — Track to close (informational — closes when every job is done).
    $steps[] = ['key'=>'close', 'label'=>'Track to close', 'actionable'=>false,
        'done'=>$callDone,
        'line'=> $callDone ? 'All work carried out and closed — nothing more to do here.'
               : ($schedN > 0 ? 'Work is under way — each visit is tracked on the Jobs tab. The order closes once every job is done.'
                  : 'Once scheduled, the engineer goes to site, writes the report, and the job closes.'),
        'href'=>'#jobs', 'cta'=>'Jobs'];

    $firstOpen = -1;
    foreach ($steps as $i => $s) if (!empty($s['actionable']) && empty($s['done'])) { $firstOpen = $i; break; }
    foreach ($steps as $i => &$s) {
        if (empty($s['actionable']))   { $s['state'] = 'info'; continue; }
        if (!empty($s['done']))        { $s['state'] = 'done'; continue; }
        if ($i === $firstOpen)         { $s['state'] = !empty($s['blocker']) ? 'blocked' : 'now'; }
        else                           { $s['state'] = 'todo'; }
    }
    unset($s);

    $openCount = 0; foreach ($steps as $s) if (!empty($s['actionable']) && empty($s['done'])) $openCount++;
    return ['steps'=>$steps, 'open'=>$openCount, 'all_done'=>($openCount === 0), 'closed'=>$callDone,
            'open_clars'=>$openClars, 'can_alloc'=>$canAlloc, 'jobs'=>$jobsN, 'scheduled'=>$schedN];
}

// Renders the order playbook card at the top of the call Overview tab.
function tosrm_render_call_playbook($call) {
    if (!$call || !tosrm_can_edit()) return;
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $pb  = tosrm_call_playbook($call);
    ob_start();
    echo tosrm_playbook_css(); ?>
    <div class="card tosrm-pb" style="margin-top:16px">
      <h3>Order playbook — this <?= e(Tl('call')) ?></h3>
      <p class="pb-sub">The path from order to done: complete the details, allocate an engineer, fix the dates, then track it to close. The service-request panel below holds the full detail and any exceptions.</p>
      <?php if (!empty($pb['open_clars'])): ?>
        <p class="pb-note">⚠ <?= (int)$pb['open_clars'] ?> open clarification<?= $pb['open_clars'] > 1 ? 's' : '' ?> — answer <?= $pb['open_clars'] > 1 ? 'them' : 'it' ?> in the service-request panel before dispatch.</p>
      <?php endif; ?>
      <?php if ($pb['closed']): ?>
        <div class="pb-done-banner"><b style="color:var(--ok)">✓ Order closed.</b> All work has been carried out and closed.</div>
      <?php elseif ($pb['all_done']): ?>
        <div class="pb-done-banner"><b style="color:var(--ok)">✓ Scheduled and under way.</b> Every job is allocated and dated. Track each visit on the <a href="#jobs">Jobs</a> tab; the order closes once they are all done.</div>
      <?php else: ?>
      <ol class="pb-steps">
        <?php $n = 0; foreach ($pb['steps'] as $s): $n++; $st = $s['state']; $num = $st === 'done' ? '✓' : $n; ?>
          <li class="pb-step is-<?= $st ?>">
            <span class="pb-num"><?= $esc($num) ?></span>
            <div class="pb-body">
              <div class="pb-label"><?= $esc($s['label']) ?>
                <?php if ($st === 'now'): ?><span class="pb-tag t-now">Do this now</span>
                <?php elseif ($st === 'blocked'): ?><span class="pb-tag t-blocked">Needed first</span>
                <?php elseif ($st === 'done'): ?><span class="pb-tag t-done">Done</span><?php endif; ?>
              </div>
              <div class="pb-line"><?= $s['line'] /* may contain markup */ ?></div>
            </div>
            <?php if (!empty($s['href']) && !empty($s['cta']) && in_array($st, ['now','blocked','todo','info'], true)): ?>
              <a class="btn small <?= ($st === 'now' || $st === 'blocked') ? '' : 'secondary' ?> pb-cta" href="<?= $esc($s['href']) ?>"><?= $esc($s['cta']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
}

function tosrm_render_call_panel($call) {
    if (!$call) return;
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $cid = (int)($call['id'] ?? 0);
    $cur = tosrm_call_status($call);
    $statuses = tosrm_status_options();
    $ready = tosrm_call_ready($call);
    $clars = tosrm_clar_list($cid);
    $hist = tosrm_status_history($cid);
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $curLabel = $statuses[$cur] ?? $cur;
    $prio = (string)($call['priority'] ?? ''); $crit = (string)($call['criticality'] ?? ''); $src = (string)($call['source'] ?? '');
    $prioOpt = tosrm_priority_options(); $critOpt = tosrm_criticality_options(); $srcOpt = tosrm_source_options();
    $canEdit = tosrm_can_edit();
    ob_start(); ?>
    <style>
      .tosrm-panel .sr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}
      .tosrm-panel .sr-field{border:1px solid var(--line);border-radius:10px;padding:12px 13px;background:var(--soft);margin:0}
      .tosrm-panel .sr-field>label{display:block;font-size:11px;font-weight:700;color:var(--muted);
        text-transform:uppercase;letter-spacing:.4px;margin:0 0 9px}
      .tosrm-panel .sr-field>label .sr-hint{font-weight:400;text-transform:none;letter-spacing:0}
      .tosrm-panel .sr-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
      .tosrm-panel .sr-row>.form-control{flex:1 1 140px;min-width:0}
      .tosrm-panel .sr-row>.btn{flex:0 0 auto}
      .tosrm-panel .sr-clar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}
      .tosrm-panel .sr-clar>.form-control{flex:1 1 160px;min-width:0}
      .tosrm-panel table.tbl .form-control{min-width:0}
      @media(max-width:640px){.tosrm-panel .sr-grid{grid-template-columns:1fr}
        .tosrm-panel .sr-row>.form-control,.tosrm-panel .sr-clar>.form-control{flex-basis:100%}}
    </style>
    <div class="card tosrm-panel" style="margin-top:16px">
      <h3 style="margin:0 0 10px">Operations — service request</h3>
      <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px">
        <span class="badge">Status: <strong><?=$esc($curLabel)?></strong></span>
        <?php if ($prio !== ''): ?><span class="badge">Priority: <?=$esc($prioOpt[$prio] ?? $prio)?></span><?php endif; ?>
        <?php if ($crit !== ''): ?><span class="badge">Criticality: <?=$esc($critOpt[$crit] ?? $crit)?></span><?php endif; ?>
        <?php if ($src !== ''): ?><span class="badge">Source: <?=$esc($srcOpt[$src] ?? $src)?></span><?php endif; ?>
      </div>

      <?php // Validation banner ?>
      <?php if (!empty($ready['missing']) && !$ready['overridden']): ?>
        <div class="notice notice-warn" style="margin-bottom:12px">
          <strong>Not ready for scheduling.</strong> Missing: <?=$esc(implode(', ', $ready['missing']))?>.
          <?php if ($canEdit): ?>
          <form method="post" action="/call-override" class="sr-clar" style="margin-top:8px">
            <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
            <input class="form-control" type="text" name="reason" placeholder="Reason to override and proceed" required>
            <button class="btn" type="submit">Override &amp; proceed</button>
          </form>
          <?php endif; ?>
        </div>
      <?php elseif ($ready['overridden'] && !empty($ready['missing'])): ?>
        <div class="notice" style="margin-bottom:12px">Validation overridden by <?=$esc($ready['override_by'])?> — <?=$esc($ready['override_reason'])?> (still missing: <?=$esc(implode(', ', $ready['missing']))?>).</div>
      <?php else: ?>
        <div class="notice notice-ok" style="margin-bottom:12px">All mandatory fields present — ready for scheduling.</div>
      <?php endif; ?>

      <?php if ($canEdit): ?>
      <div class="sr-grid">
        <form method="post" action="/call-status" class="sr-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
          <label>Change status <span class="sr-hint">(only the valid next steps are listed)</span></label>
          <?php // R6 — the picker offers the current status plus the steps that may
                // legally follow it. A manager additionally sees every other status
                // under an "Override" group; choosing one ticks the override box, and
                // the server requires a reason for it. ?>
          <?php $allowedNext = tosrm_allowed_next($cur); $isMgr = function_exists('is_admin_level') && is_admin_level(); ?>
          <div class="sr-row">
            <select class="form-control sr-status" name="op_status">
              <option value="<?=$esc($cur)?>" selected><?=$esc($statuses[$cur] ?? $cur)?> (current)</option>
              <?php foreach ($allowedNext as $k): if ($k===$cur) continue; ?><option value="<?=$esc($k)?>"><?=$esc($statuses[$k] ?? $k)?></option><?php endforeach; ?>
              <?php if ($isMgr): $override = array_diff(array_keys($statuses), $allowedNext, [$cur]); if ($override): ?>
                <optgroup label="Override — needs a reason">
                  <?php foreach ($override as $k): ?><option value="<?=$esc($k)?>" data-override="1"><?=$esc($statuses[$k] ?? $k)?></option><?php endforeach; ?>
                </optgroup>
              <?php endif; endif; ?>
            </select>
            <input class="form-control" type="text" name="reason" placeholder="Reason<?= $isMgr ? ' (required for an override)' : ' (optional)' ?>">
            <?php if ($isMgr): ?><label class="sr-hint" style="display:flex;align-items:center;gap:5px"><input type="checkbox" name="override" class="sr-override"> Override</label><?php endif; ?>
            <button class="btn" type="submit">Set</button>
          </div>
          <?php if ($isMgr): ?><script>(function(){var s=document.currentScript.closest('form').querySelector('.sr-status'),o=document.currentScript.closest('form').querySelector('.sr-override');if(!s||!o)return;s.addEventListener('change',function(){var op=s.options[s.selectedIndex];o.checked=!!(op&&op.dataset.override);});})();</script><?php endif; ?>
        </form>
        <form method="post" action="/call-attrs" class="sr-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
          <label>Priority · Criticality · Source</label>
          <div class="sr-row">
            <select class="form-control" name="priority"><option value="">Priority…</option><?php foreach ($prioOpt as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$prio?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
            <select class="form-control" name="criticality"><option value="">Criticality…</option><?php foreach ($critOpt as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$crit?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
            <select class="form-control" name="source"><option value="">Source…</option><?php foreach ($srcOpt as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$src?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
            <button class="btn" type="submit">Save</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php // Clarifications ?>
      <div style="margin-bottom:10px">
        <strong>Clarifications</strong> <?php if ($clars): ?><span class="muted">(<?=count($clars)?>, <?=tosrm_clar_open_count($cid)?> open)</span><?php endif; ?>
        <?php if ($clars): ?>
        <table class="tbl" style="width:100%;margin-top:6px;font-size:13px">
          <thead><tr><th>To</th><th>Subject</th><th>Response</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($clars as $c): $to = tosrm_clar_to_options()[$c['raised_to']] ?? $c['raised_to']; ?>
            <tr>
              <td><?=$esc($to)?></td>
              <td><?=$esc($c['subject'])?><?php if ($c['detail']): ?><br><span class="muted"><?=$esc($c['detail'])?></span><?php endif; ?></td>
              <td>
                <?php if (trim((string)$c['response']) !== ''): ?><?=$esc($c['response'])?>
                <?php elseif ($canEdit): ?>
                  <form method="post" action="/call-clar-respond" style="display:flex;gap:6px;align-items:center">
                    <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>"><input type="hidden" name="id" value="<?=$esc($c['id'])?>">
                    <input class="form-control" type="text" name="response" placeholder="Record the answer" style="flex:1" required>
                    <button class="btn btn-sm" type="submit">Save</button>
                  </form>
                <?php else: ?><span class="muted">awaiting</span><?php endif; ?>
              </td>
              <td><span class="badge"><?=$esc(TOSRM_CLAR_STATUS[$c['status']] ?? $c['status'])?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <form method="post" action="/call-clar-new" class="sr-clar">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
          <select class="form-control" name="raised_to" style="flex:0 0 130px"><?php foreach (tosrm_clar_to_options() as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
          <input class="form-control" type="text" name="subject" placeholder="What needs clarifying?" required>
          <input class="form-control" type="text" name="detail" placeholder="Detail (optional)">
          <button class="btn" type="submit">Raise</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($hist): ?>
      <details style="margin-top:6px"><summary class="muted">Status history (<?=count($hist)?>)</summary>
        <table class="tbl" style="width:100%;margin-top:6px;font-size:12px">
          <tbody><?php foreach ($hist as $h): ?>
            <tr><td><?=$esc(substr((string)$h['at'],0,16))?></td><td><?=$esc($statuses[$h['old_status']] ?? $h['old_status'])?> → <strong><?=$esc($statuses[$h['new_status']] ?? $h['new_status'])?></strong></td><td><?=$esc($h['actor'])?></td><td><?=$esc($h['reason'])?></td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </details>
      <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
}

// ---------------------------------------------------------------------------
//  Handler — Operations actions on a call.  Routes registered in lib/ops.php.
// ---------------------------------------------------------------------------
function ops_tosrm_action($route, $method) {
    $callId = (int)($_POST['call_id'] ?? $_GET['id'] ?? 0);
    $call = $callId ? ops_one("SELECT * FROM calls WHERE id=?", [$callId]) : null;
    if (!$call) { flash('Call not found.', 'error'); redirect('/calls'); }
    $back = '/call?id=' . $callId . '#ops';
    if ($method !== 'POST') redirect($back);
    if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
    ops_require(tosrm_can_edit(), 'You do not have permission to change this service request.');
    tosrm_migrate();
    tosrm_migrate_b();

    switch ($route) {
        case 'call-status':
            $to     = (string)($_POST['op_status'] ?? '');
            $reason = (string)($_POST['reason'] ?? '');
            // R6 — a manager may force a non-standard transition, but only with a reason.
            $force  = !empty($_POST['override']) && function_exists('is_admin_level') && is_admin_level();
            if ($force && trim($reason) === '') { flash('An override needs a reason.', 'error'); break; }
            if (tosrm_set_status($callId, $to, $reason, '', $force)) {
                flash('Status updated.');
            } elseif ($to !== '' && array_key_exists(strtoupper(trim($to)), tosrm_status_options())) {
                $from = tosrm_call_status($call);
                flash('“' . (tosrm_status_options()[strtoupper(trim($to))] ?? $to) . '” is not a valid next step from “'
                    . (tosrm_status_options()[strtoupper(trim($from))] ?? $from) . '”.'
                    . (is_admin_level() ? ' A manager can override with a reason.' : ''), 'error');
            } else flash('Please pick a valid status.', 'error');
            break;
        case 'call-attrs':
            db()->prepare("UPDATE calls SET priority=?, criticality=?, source=? WHERE id=?")->execute([
                strtoupper((string)($_POST['priority'] ?? '')), strtoupper((string)($_POST['criticality'] ?? '')),
                strtoupper((string)($_POST['source'] ?? '')), $callId]);
            flash('Priority / criticality / source saved.');
            break;
        case 'call-override':
            if (tosrm_override_validation($callId, (string)($_POST['reason'] ?? ''))) flash('Validation overridden — the request may proceed.');
            else flash('A reason is required to override.', 'error');
            break;
        case 'call-clar-new':
            if (tosrm_clar_create($callId, [
                'subject'=>(string)($_POST['subject'] ?? ''), 'detail'=>(string)($_POST['detail'] ?? ''),
                'raised_to'=>(string)($_POST['raised_to'] ?? 'CLIENT'), 'due_on'=>(string)($_POST['due_on'] ?? '')])) {
                flash('Clarification raised.');
            } else flash('Give the clarification a subject.', 'error');
            break;
        case 'call-clar-respond':
            if (tosrm_clar_respond((int)($_POST['id'] ?? 0), (string)($_POST['response'] ?? ''))) flash('Response recorded.');
            else flash('Write the response.', 'error');
            break;
        case 'call-clar-status':
            tosrm_clar_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
            flash('Clarification updated.');
            break;
    }
    redirect($back);
}

// ===========================================================================
//  SLICE B — Assignment lifecycle over the EXISTING jobs / job_visits spine.
//  An assignment is still jobs.inspector_id (+ per-date job_visits). This adds,
//  additively, what an assignment did not carry:
//    • a TENTATIVE / CONFIRMED / RELEASED hold state (pencil-in before commit);
//    • ACCEPTANCE by the resource (accept / decline / clarify / replace) with a
//      recorded reason;
//    • REASSIGNMENT, RESCHEDULE, CANCELLATION and NO-SHOW that PRESERVE history
//      (the original is never overwritten — it is kept in assignment_events).
//  Nothing here changes how allocation, the availability board or job close
//  work; the legacy /job-reassign path still functions untouched.
// ===========================================================================

const TOSRM_ASSIGN_STATES = ['TENTATIVE'=>'Tentative (held)','CONFIRMED'=>'Confirmed','RELEASED'=>'Released'];
const TOSRM_ACCEPT_STATES = ['PENDING'=>'Awaiting acceptance','ACCEPTED'=>'Accepted','DECLINED'=>'Declined','CLARIFY'=>'Clarification requested','REPLACE'=>'Replacement requested'];
const TOSRM_NOSHOW_PARTIES = ['CLIENT'=>'Client','VENDOR'=>'Vendor','RESOURCE'=>'Resource','OTHER'=>'Other'];
// Who called a cancellation off — so client-driven and office-driven cancellations
// can be told apart on the disruptions scoreboard.
const TOSRM_CANCEL_PARTIES = ['CLIENT'=>'Client','OFFICE'=>'Executing office','VENDOR'=>'Vendor','INTERNAL'=>'Internal'];
// Kinds recorded in the assignment history.
const TOSRM_ASSIGN_KINDS = [
    'HOLD'=>'Hold changed','ACCEPT'=>'Acceptance','REASSIGN'=>'Reassigned','RESCHEDULE'=>'Rescheduled',
    'CANCEL'=>'Cancelled','NOSHOW'=>'No-show',
];

function tosrm_migrate_b() {
    static $done = false; if ($done) return; $done = true;
    tosrm_migrate();
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('assign_state',   'Assignment hold state',   TOSRM_ASSIGN_STATES,  'tosrm');
        lk_ensure_type_map('accept_state',    'Assignment acceptance',   TOSRM_ACCEPT_STATES,  'tosrm');
        lk_ensure_type_map('noshow_party',    'No-show party',           TOSRM_NOSHOW_PARTIES, 'tosrm');
    }
    if (function_exists('ensure_column')) {
        ensure_column('jobs', 'assign_state',  "VARCHAR(16) DEFAULT ''");
        ensure_column('jobs', 'accept_state',  "VARCHAR(16) DEFAULT ''");
        ensure_column('jobs', 'accept_reason', "VARCHAR(400) DEFAULT ''");
        ensure_column('jobs', 'accept_by',     "VARCHAR(120) DEFAULT ''");
        ensure_column('jobs', 'accept_at',     "VARCHAR(30) DEFAULT ''");
    }
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS assignment_events (
        id $pk, job_id INT DEFAULT 0, kind VARCHAR(16) DEFAULT '',
        old_inspector_id INT DEFAULT 0, new_inspector_id INT DEFAULT 0,
        old_date VARCHAR(20) DEFAULT '', new_date VARCHAR(20) DEFAULT '',
        party VARCHAR(16) DEFAULT '', reason VARCHAR(600) DEFAULT '',
        actor VARCHAR(120) DEFAULT '', approver VARCHAR(120) DEFAULT '', at VARCHAR(30) DEFAULT '')");
}

// The current hold state — a legacy allocation (inspector set, no state) reads
// as CONFIRMED so old jobs behave exactly as before.
function tosrm_assign_state($job) {
    $s = trim((string)($job['assign_state'] ?? ''));
    if ($s !== '') return $s;
    return (int)($job['inspector_id'] ?? 0) > 0 ? 'CONFIRMED' : '';
}
function tosrm_accept_state($job) { return trim((string)($job['accept_state'] ?? '')) ?: ((int)($job['inspector_id'] ?? 0) > 0 ? 'PENDING' : ''); }

function tosrm_assign_event($jobId, $kind, $data = []) {
    db()->prepare("INSERT INTO assignment_events (job_id, kind, old_inspector_id, new_inspector_id, old_date, new_date, party, reason, actor, approver, at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([
        (int)$jobId, (string)$kind, (int)($data['old_inspector_id'] ?? 0), (int)($data['new_inspector_id'] ?? 0),
        (string)($data['old_date'] ?? ''), (string)($data['new_date'] ?? ''), (string)($data['party'] ?? ''),
        (string)($data['reason'] ?? ''), tosrm_actor(), (string)($data['approver'] ?? ''), tosrm_now()]);
    if (function_exists('idems_log')) { try { idems_log('job', (int)$jobId, 'ASSIGN_' . $kind, ['reason'=>$data['reason'] ?? '']); } catch (Throwable $e) {} }
    return (int)db()->lastInsertId();
}
function tosrm_assign_history($jobId) { return ops_all("SELECT * FROM assignment_events WHERE job_id=? ORDER BY id DESC", [(int)$jobId]) ?: []; }

// Tentative / Confirmed / Released hold. Does NOT auto-move anyone.
function tosrm_assign_hold($jobId, $state, $reason = '') {
    $state = strtoupper(trim((string)$state));
    if (!array_key_exists($state, TOSRM_ASSIGN_STATES)) return false;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return false;
    db()->prepare("UPDATE jobs SET assign_state=? WHERE id=?")->execute([$state, (int)$jobId]);
    tosrm_assign_event($jobId, 'HOLD', ['reason'=>($state . ($reason !== '' ? ' — ' . $reason : ''))]);
    return true;
}

// Resource acceptance decision. A decline (or replacement request) requires a
// reason; blame is never assigned automatically.
function tosrm_assign_accept($jobId, $decision, $reason = '', $actor = '') {
    $decision = strtoupper(trim((string)$decision));
    if (!array_key_exists($decision, TOSRM_ACCEPT_STATES)) return false;
    if (in_array($decision, ['DECLINED','REPLACE'], true) && trim((string)$reason) === '') return false;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return false;
    db()->prepare("UPDATE jobs SET accept_state=?, accept_reason=?, accept_by=?, accept_at=? WHERE id=?")
        ->execute([$decision, (string)$reason, $actor !== '' ? $actor : tosrm_actor(), tosrm_now(), (int)$jobId]);
    tosrm_assign_event($jobId, 'ACCEPT', ['reason'=>($decision . ($reason !== '' ? ' — ' . $reason : ''))]);
    return true;
}

// Reassign to a new resource, KEEPING the original in history. Resets the
// acceptance to PENDING for the new person.
function tosrm_reassign($jobId, $newInspectorId, $reason = '', $approver = '') {
    $newInspectorId = (int)$newInspectorId; if ($newInspectorId <= 0) return false;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return false;
    $old = (int)($job['inspector_id'] ?? 0);
    if ($old === $newInspectorId) return false;
    db()->prepare("UPDATE jobs SET inspector_id=?, accept_state='PENDING', accept_reason='', accept_by='', accept_at='' WHERE id=?")
        ->execute([$newInspectorId, (int)$jobId]);
    tosrm_assign_event($jobId, 'REASSIGN', ['old_inspector_id'=>$old, 'new_inspector_id'=>$newInspectorId, 'reason'=>$reason, 'approver'=>$approver]);
    return true;
}

// Move the scheduled date, keeping the original in history.
function tosrm_reschedule($jobId, $newDate, $reason = '', $approver = '') {
    $newDate = trim((string)$newDate); if ($newDate === '') return false;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return false;
    $oldDate = (string)($job['scheduled_date'] ?? '');
    if ($oldDate === $newDate) return false;
    db()->prepare("UPDATE jobs SET scheduled_date=? WHERE id=?")->execute([$newDate, (int)$jobId]);
    tosrm_assign_event($jobId, 'RESCHEDULE', ['old_date'=>$oldDate, 'new_date'=>$newDate, 'reason'=>$reason, 'approver'=>$approver]);
    return true;
}

// Cancel the assignment — records a reason + history, and marks the job stage
// CANCELLED (an existing JOB_STAGES value) unless it is already closed.
function tosrm_assign_cancel($jobId, $reason = '', $party = '') {
    if (trim((string)$reason) === '') return false;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return false;
    $party = strtoupper(trim((string)$party));
    if (!array_key_exists($party, TOSRM_CANCEL_PARTIES)) $party = 'INTERNAL';
    if (empty($job['closed_flag'])) db()->prepare("UPDATE jobs SET stage='CANCELLED' WHERE id=?")->execute([(int)$jobId]);
    tosrm_assign_event($jobId, 'CANCEL', ['reason'=>$reason, 'party'=>$party]);
    return true;
}

// Record a no-show — party + reason, no automatic blame.
function tosrm_assign_noshow($jobId, $party, $reason = '') {
    $party = strtoupper(trim((string)$party));
    if (!array_key_exists($party, TOSRM_NOSHOW_PARTIES)) return false;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return false;
    tosrm_assign_event($jobId, 'NOSHOW', ['party'=>$party, 'reason'=>$reason]);
    return true;
}

// ---------------------------------------------------------------------------
//  Job assignment-lifecycle panel (rendered on the job detail page).
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  Coordinator playbook — the forward, guided path for a freshly-allocated job.
//
//  The job screen is the coordinator's desk for one assignment. Landing on it
//  cold — with every exception tool (reassign, no-show, cancel…) laid out at
//  once — hid the one thing that actually matters right after allocation: the
//  short, ordered path to getting the job ready to run. This computes that path.
//
//  Pure computation, no output, so it is testable and reusable. Returns an
//  ordered list of steps. Each: key, label, line (a human status/next line),
//  state, href, cta, actionable. State is one of done | now | todo | blocked |
//  info. Exactly one actionable step is 'now' (the first not-yet-done, or
//  'blocked' if that step is held up); steps ahead of it are 'todo'.
// ---------------------------------------------------------------------------
function tosrm_job_playbook($job) {
    $jid    = (int)($job['id'] ?? 0);
    $closed = !empty($job['closed_flag']);
    $inspId = (int)($job['inspector_id'] ?? 0);
    $assign = tosrm_assign_state($job);                 // '', TENTATIVE, CONFIRMED, RELEASED
    $accept = tosrm_accept_state($job);                 // '', PENDING, ACCEPTED, …
    $staffKind = $inspId ? (string)ops_val("SELECT staff_kind FROM inspectors WHERE id=?", [$inspId]) : '';
    $isNonEmp  = in_array($staffKind, ['FREELANCER', 'SUBCON'], true);
    $decWho    = $staffKind === 'SUBCON' ? 'sub-contractor' : 'freelancer';
    $scheduled = trim((string)($job['scheduled_date'] ?? '')) !== '';
    $gate      = tosrm_confirm_gate($job);
    $clientOk  = (int)($job['client_confirmed'] ?? 0) === 1;
    $roll      = tosrm_readiness_rollup($jid);
    $compErr   = false;
    foreach (tosrm_competence_warn($job) as $w) if (($w['level'] ?? '') === 'error') $compErr = true;
    $reports   = (int) ops_val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND deleted=0", [$jid]);

    $steps = [];

    // 1 — Inspector assigned (normally already done by the allocation itself).
    $steps[] = ['key'=>'assigned', 'label'=>'Inspector assigned', 'actionable'=>true,
        'done'=> $inspId > 0,
        'line'=> $inspId > 0 ? (string)($job['inspector_name'] ?? 'Assigned')
                             : 'No one is assigned yet — pick who goes, under “Something changed?” below.',
        'href'=>'#assign', 'cta'=>'Assign'];

    // 2 — Commit (hold = Confirmed; a freelancer / sub-contractor must also accept).
    $committed = ($assign === 'CONFIRMED') && (!$isNonEmp || $accept === 'ACCEPTED');
    if ($committed) {
        $line = $isNonEmp ? "Confirmed — the {$decWho} accepted." : 'Confirmed.';
    } elseif ($assign !== 'CONFIRMED') {
        $line = 'Move it from tentative to <b>Confirmed</b>'
              . ($isNonEmp ? ", and record the {$decWho}’s acceptance." : '.');
    } else { // confirmed but the non-employee has not accepted
        $line = "Record the {$decWho}’s acceptance ("
              . strtolower(TOSRM_ACCEPT_STATES[$accept] ?? 'awaiting acceptance') . ').';
    }
    $steps[] = ['key'=>'commit', 'label'=>'Commit the assignment', 'actionable'=>true,
        'done'=>$committed, 'line'=>$line, 'href'=>'#assign', 'cta'=>'Confirm assignment'];

    // 3 — Visit date, so the engineer knows when to go.
    $steps[] = ['key'=>'date', 'label'=>'Set the visit date', 'actionable'=>true,
        'done'=>$scheduled,
        'line'=>$scheduled ? ('Visit on ' . substr((string)$job['scheduled_date'],0,10) . '.')
                           : 'Set the date the engineer goes to site.',
        'href'=>'/job-edit?id=' . $jid, 'cta'=>'Set date'];

    // 4 — Client / vendor confirmation (only mandatory when the job asks for it).
    $confDone = $clientOk || !$gate['required'];
    $steps[] = ['key'=>'confirm', 'label'=>'Confirm with the client', 'actionable'=>true,
        'done'=>$confDone, 'blocker'=>($gate['required'] && !$clientOk),
        'line'=> $clientOk
                   ? ('Client confirmed' . (trim((string)($job['client_confirm_ref'] ?? '')) !== '' ? ' · ' . $job['client_confirm_ref'] : '') . '.')
                   : ($gate['required'] ? 'This job needs the client’s go-ahead before work starts — record it.'
                                        : 'Optional for this job — record it if the client confirmed.'),
        'href'=>'#ready', 'cta'=>'Record confirmation'];

    // 5 — Readiness & competence (advisory; a competence error is a real hold).
    $readyOk = !$compErr && ($roll['total'] === 0 || !empty($roll['ready_bool']));
    if ($compErr) {
        $line = 'A competence / certificate problem needs a decision — see the advisory below.';
    } elseif ($roll['total'] > 0) {
        $line = $roll['ready_bool'] ? "All {$roll['total']} items ready." : "{$roll['open']} readiness item(s) still open.";
    } else {
        $line = 'No blockers. Add a readiness checklist if this service needs one.';
    }
    $steps[] = ['key'=>'ready', 'label'=>'Check readiness & competence', 'actionable'=>true,
        'done'=>$readyOk, 'blocker'=>$compErr, 'line'=>$line, 'href'=>'#ready', 'cta'=>'Open readiness'];

    // 6 — Hand off to the field (informational; not the coordinator’s action).
    $steps[] = ['key'=>'handoff', 'label'=>'Hand off to the field', 'actionable'=>false,
        'done'=>$closed || $reports > 0,
        'line'=> $closed ? 'Job closed.'
               : ($reports > 0 ? 'Report written — issue it, then close the job.'
                  : 'The engineer records arrival at <b>Site check-in</b>, writes the report, then you close the job.'),
        'href'=> ($scheduled && !$closed) ? '#checkin' : '', 'cta'=>'Site check-in'];

    // Mark the single 'now' step: the first actionable step not yet done.
    $firstOpen = -1;
    foreach ($steps as $i => $s) {
        if (!empty($s['actionable']) && empty($s['done'])) { $firstOpen = $i; break; }
    }
    foreach ($steps as $i => &$s) {
        if (empty($s['actionable']))      { $s['state'] = 'info'; continue; }
        if (!empty($s['done']))           { $s['state'] = 'done'; continue; }
        if ($i === $firstOpen)            { $s['state'] = (!empty($s['blocker'])) ? 'blocked' : 'now'; }
        else                              { $s['state'] = 'todo'; }
    }
    unset($s);

    $openCount = 0; foreach ($steps as $s) if (!empty($s['actionable']) && empty($s['done'])) $openCount++;
    return ['steps'=>$steps, 'open'=>$openCount, 'all_done'=>($openCount === 0), 'closed'=>$closed];
}

// The stepper's CSS, emitted at most once per request (both the job and the call
// playbook share the .tosrm-pb look).
function tosrm_playbook_css() {
    static $done = false; if ($done) return ''; $done = true;
    return <<<CSS
    <style>
      .tosrm-pb>h3{margin:0 0 4px}
      .tosrm-pb .pb-sub{color:var(--muted);font-size:13px;margin:0 0 14px}
      .tosrm-pb ol.pb-steps{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
      .tosrm-pb .pb-step{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--line);
        border-radius:10px;padding:11px 13px;background:var(--soft)}
      .tosrm-pb .pb-num{flex:0 0 26px;width:26px;height:26px;border-radius:50%;display:grid;place-items:center;
        font-size:13px;font-weight:700;background:var(--card);border:1px solid var(--line);color:var(--muted)}
      .tosrm-pb .pb-body{flex:1 1 auto;min-width:0}
      .tosrm-pb .pb-label{font-weight:700;font-size:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
      .tosrm-pb .pb-line{color:var(--muted);font-size:13px;margin-top:2px}
      .tosrm-pb .pb-line b{color:var(--fg,inherit);font-weight:700}
      .tosrm-pb .pb-cta{flex:0 0 auto;align-self:center}
      .tosrm-pb .pb-tag{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
        padding:1px 7px;border-radius:999px}
      .tosrm-pb .is-done .pb-num{background:var(--ok);border-color:var(--ok);color:#fff}
      .tosrm-pb .is-now{border-color:var(--brand);background:color-mix(in srgb,var(--brand) 8%,var(--card))}
      .tosrm-pb .is-now .pb-num{background:var(--brand);border-color:var(--brand);color:#fff}
      .tosrm-pb .is-blocked{border-color:var(--bad);background:color-mix(in srgb,var(--bad) 7%,var(--card))}
      .tosrm-pb .is-blocked .pb-num{background:var(--bad);border-color:var(--bad);color:#fff}
      .tosrm-pb .is-todo{opacity:.72}
      .tosrm-pb .is-info{background:transparent;border-style:dashed}
      .tosrm-pb .pb-tag.t-now{background:var(--brand);color:#fff}
      .tosrm-pb .pb-tag.t-blocked{background:var(--bad);color:#fff}
      .tosrm-pb .pb-tag.t-done{color:var(--ok);border:1px solid var(--ok)}
      .tosrm-pb .pb-note{font-size:12.5px;margin:0 0 12px;padding:8px 11px;border-radius:8px;
        background:color-mix(in srgb,var(--warn) 10%,var(--card));border:1px solid var(--warn);color:var(--warn)}
      .tosrm-pb .pb-done-banner{border:1px solid var(--ok);border-radius:10px;padding:11px 13px;
        background:color-mix(in srgb,var(--ok) 8%,var(--card));font-size:13.5px}
      @media(max-width:560px){.tosrm-pb .pb-step{flex-wrap:wrap}.tosrm-pb .pb-cta{flex-basis:100%;margin-top:6px}}
    </style>
    CSS;
}

// Renders the coordinator playbook card at the top of the job Overview tab.
function tosrm_render_playbook($job) {
    if (!$job || !tosrm_can_edit()) return;   // it is the coordinator's guide; read-only viewers don't act on it
    tosrm_migrate_c();
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $pb  = tosrm_job_playbook($job);
    ob_start();
    echo tosrm_playbook_css(); ?>
    <div class="card tosrm-pb" style="margin-top:16px">
      <h3>Coordinator playbook — this assignment</h3>
      <p class="pb-sub">Your desk for getting this <?= e(Tl('job')) ?> ready to run. Work down the steps; the field engineer takes over at site check-in, and you close it at the end. Everything else on this page is detail and exceptions.</p>
      <?php if ($pb['all_done'] && !$pb['closed']): ?>
        <div class="pb-done-banner"><b style="color:var(--ok)">✓ Ready to run.</b> The assignment is committed, the date is set and there are no open blockers. Next it moves to the field — the engineer records arrival at <a href="#checkin">Site check-in</a>.</div>
      <?php elseif ($pb['closed']): ?>
        <div class="pb-done-banner"><b style="color:var(--ok)">✓ Closed.</b> This <?= e(Tl('job')) ?> is done. The report and photographs can still be uploaded if something was missed.</div>
      <?php else: ?>
      <ol class="pb-steps">
        <?php $n = 0; foreach ($pb['steps'] as $s): $n++; $st = $s['state'];
          $cls = 'is-' . $st;
          $num = $st === 'done' ? '✓' : $n; ?>
          <li class="pb-step <?= $cls ?>">
            <span class="pb-num"><?= $esc($num) ?></span>
            <div class="pb-body">
              <div class="pb-label"><?= $esc($s['label']) ?>
                <?php if ($st === 'now'): ?><span class="pb-tag t-now">Do this now</span>
                <?php elseif ($st === 'blocked'): ?><span class="pb-tag t-blocked">Blocked</span>
                <?php elseif ($st === 'done'): ?><span class="pb-tag t-done">Done</span><?php endif; ?>
              </div>
              <div class="pb-line"><?= $s['line'] /* may contain <b> */ ?></div>
            </div>
            <?php if (in_array($st, ['now','blocked','todo'], true) && !empty($s['href']) && !empty($s['cta'])): ?>
              <a class="btn small <?= $st === 'now' || $st === 'blocked' ? '' : 'secondary' ?> pb-cta" href="<?= $esc($s['href']) ?>"><?= $esc($s['cta']) ?></a>
            <?php elseif ($st === 'info' && !empty($s['href']) && !empty($s['cta'])): ?>
              <a class="btn small secondary pb-cta" href="<?= $esc($s['href']) ?>"><?= $esc($s['cta']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
}

function tosrm_render_job_panel($job) {
    if (!$job) return;
    tosrm_migrate_b();
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $jid = (int)($job['id'] ?? 0);
    $canEdit = tosrm_can_edit();
    $hold = tosrm_assign_state($job); $accept = tosrm_accept_state($job);
    $hist = tosrm_assign_history($jid);
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $insps = ops_all("SELECT id, name FROM inspectors WHERE COALESCE(status,'')<>'INACTIVE' ORDER BY name") ?: [];
    $curInsp = (int)($job['inspector_id'] ?? 0);
    $curDate = (string)($job['scheduled_date'] ?? '');
    // §resource-decision — accept/decline only matters for a non-employee
    //  (freelancer / sub-contractor). For an own TPIA engineer it is not a step,
    //  so the box is not shown at all.
    $staffKind = $curInsp ? (string)ops_val("SELECT staff_kind FROM inspectors WHERE id=?", [$curInsp]) : '';
    $isNonEmp  = in_array($staffKind, ['FREELANCER', 'SUBCON'], true);
    $decWho    = $staffKind === 'SUBCON' ? 'sub-contractor' : 'freelancer';
    ob_start(); ?>
    <style>
      /* Assignment lifecycle — proper fields, aligned, on the app's form styling. */
      .tosrm-assign>h3{margin:0 0 4px}
      .tosrm-assign .ta-sub{color:var(--muted);font-size:13px;margin:0 0 12px}
      .tosrm-assign .ta-badges{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
      .tosrm-assign .ta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
      .tosrm-assign .ta-field{border:1px solid var(--line);border-radius:10px;padding:12px 13px;background:var(--soft);margin:0}
      .tosrm-assign .ta-field>label{display:block;font-size:11px;font-weight:700;color:var(--muted);
        text-transform:uppercase;letter-spacing:.4px;margin:0 0 9px}
      .tosrm-assign .ta-field>label .ta-hint{font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)}
      .tosrm-assign .ta-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
      .tosrm-assign .ta-row>.form-control{flex:1 1 140px;min-width:0}
      .tosrm-assign .ta-row>.btn{flex:0 0 auto}
      @media(max-width:640px){.tosrm-assign .ta-grid{grid-template-columns:1fr}.tosrm-assign .ta-row>.form-control{flex-basis:100%}}
    </style>
    <div class="card tosrm-assign" style="margin-top:16px">
      <h3>Operations — assignment lifecycle</h3>
      <p class="ta-sub">Commit the assignment here. Reassignment, reschedule, no-show and cancellation are tucked under “Something changed?” — every change is kept in the history below.</p>
      <div class="ta-badges">
        <?php if ($hold !== ''): ?><span class="pill p-info">Hold: <?=$esc(TOSRM_ASSIGN_STATES[$hold] ?? $hold)?></span><?php endif; ?>
        <?php if ($isNonEmp && $accept !== ''): ?><span class="pill p-mut">Acceptance: <?=$esc(TOSRM_ACCEPT_STATES[$accept] ?? $accept)?><?php if (trim((string)($job['accept_reason'] ?? '')) !== ''): ?> — <?=$esc($job['accept_reason'])?><?php endif; ?></span><?php endif; ?>
      </div>
      <?php if ($canEdit): ?>
      <div class="ta-grid">
        <form method="post" action="/assign-hold" class="ta-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Hold state <span class="ta-hint">(pencil-in vs commit)</span></label>
          <div class="ta-row"><select class="form-control" name="state"><?php foreach (TOSRM_ASSIGN_STATES as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$hold?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select><button class="btn" type="submit">Set</button></div>
        </form>
        <?php // Only shown when the assigned resource is a freelancer / sub-contractor. ?>
        <?php if ($isNonEmp): ?>
        <form method="post" action="/assign-accept" class="ta-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Did the <?=$esc($decWho)?> accept? <span class="ta-hint">(required for non-employees)</span></label>
          <div class="ta-row">
            <select class="form-control" name="decision"><?php foreach (TOSRM_ACCEPT_STATES as $k=>$v): if ($k==='PENDING') continue; ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
            <input class="form-control" type="text" name="reason" placeholder="Reason (required to decline)">
            <button class="btn" type="submit">Save</button>
          </div>
        </form>
        <?php endif; ?>
      </div><!-- /ta-grid: the normal commit controls -->

      <details class="fold ta-fold" style="margin-top:12px">
        <summary><b>Something changed?</b> <span class="ta-hint" style="font-weight:400">Reassign, reschedule, record a no-show, cancel, or raise an issue — each is kept in the history.</span></summary>
        <div class="ta-grid" style="margin-top:12px">
        <form method="post" action="/assign-reassign" class="ta-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Reassign <span class="ta-hint">(original kept in history)</span></label>
          <div class="ta-row">
            <select class="form-control" name="inspector_id"><option value="">Choose resource…</option><?php foreach ($insps as $ip): ?><option value="<?=(int)$ip['id']?>" <?=(int)$ip['id']===$curInsp?'selected':''?>><?=$esc($ip['name'])?></option><?php endforeach; ?></select>
            <input class="form-control" type="text" name="reason" placeholder="Reason">
            <input class="form-control" type="text" name="approver" placeholder="Approved by">
            <button class="btn" type="submit">Reassign</button>
          </div>
        </form>
        <form method="post" action="/assign-reschedule" class="ta-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Reschedule <span class="ta-hint">(original date kept)</span></label>
          <div class="ta-row">
            <input class="form-control" type="date" name="new_date" value="<?=$esc($curDate)?>">
            <input class="form-control" type="text" name="reason" placeholder="Reason">
            <input class="form-control" type="text" name="approver" placeholder="Approved by">
            <button class="btn" type="submit">Reschedule</button>
          </div>
        </form>
        <form method="post" action="/assign-noshow" class="ta-field">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Record a no-show <span class="ta-hint">(no automatic blame)</span></label>
          <div class="ta-row">
            <select class="form-control" name="party"><?php foreach (TOSRM_NOSHOW_PARTIES as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
            <input class="form-control" type="text" name="reason" placeholder="What happened">
            <button class="btn" type="submit">Record</button>
          </div>
        </form>
        <form method="post" action="/assign-cancel" class="ta-field" onsubmit="return confirm('Cancel this assignment?');">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Cancel assignment <span class="ta-hint">(who &amp; why — kept on record)</span></label>
          <div class="ta-row">
            <select class="form-control" name="cancel_by" style="flex:0 0 150px"><?php foreach (TOSRM_CANCEL_PARTIES as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
            <input class="form-control" type="text" name="reason" placeholder="Reason to cancel" required><button class="btn secondary" type="submit" style="color:var(--bad);border-color:var(--bad)">Cancel</button></div>
        </form>
        <?php if (function_exists('tosrm_issue_from_event')): ?>
        <form method="post" action="/assign-issue" class="ta-field" style="grid-column:1/-1">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
          <label>Raise an operational issue <span class="ta-hint">(no-show, competence, access… — links to the issue register, no duplicate)</span></label>
          <div class="ta-row">
            <input class="form-control" type="text" name="title" placeholder="Describe the operational issue">
            <select class="form-control" name="severity" style="flex:0 0 130px"><option value="MINOR">Minor</option><option value="MAJOR">Major</option><option value="OBSERVATION">Observation</option></select>
            <button class="btn" type="submit">Raise issue</button>
          </div>
        </form>
        <?php endif; ?>
        </div><!-- /ta-grid inside the fold -->
      </details>
      <?php endif; ?>
      <?php if ($hist): $iname = []; foreach ($insps as $ip) $iname[(int)$ip['id']] = $ip['name']; ?>
      <details style="margin-top:12px" open><summary class="muted">Assignment history (<?=count($hist)?>)</summary>
        <table class="grid" style="width:100%;margin-top:8px">
          <thead><tr><th>When</th><th>Event</th><th>Detail</th><th>By</th></tr></thead>
          <tbody><?php foreach ($hist as $ev):
            $detail = $esc($ev['reason']);
            if ($ev['kind']==='REASSIGN') $detail = $esc(($iname[(int)$ev['old_inspector_id']] ?? '—') . ' → ' . ($iname[(int)$ev['new_inspector_id']] ?? '—')) . ($ev['reason']?' · '.$esc($ev['reason']):'');
            if ($ev['kind']==='RESCHEDULE') $detail = $esc(($ev['old_date']?:'—') . ' → ' . ($ev['new_date']?:'—')) . ($ev['reason']?' · '.$esc($ev['reason']):'');
            if ($ev['kind']==='NOSHOW') $detail = $esc((TOSRM_NOSHOW_PARTIES[$ev['party']] ?? $ev['party']) . ($ev['reason']?' · '.$ev['reason']:''));
            if ($ev['kind']==='CANCEL' && trim((string)($ev['party']??''))!=='') $detail = $esc((TOSRM_CANCEL_PARTIES[$ev['party']] ?? $ev['party']) . ($ev['reason']?' · '.$ev['reason']:''));
          ?>
            <tr><td><?=$esc(substr((string)$ev['at'],0,16))?></td><td><strong><?=$esc(TOSRM_ASSIGN_KINDS[$ev['kind']] ?? $ev['kind'])?></strong></td><td><?=$detail?><?php if ($ev['approver']): ?> <span class="muted">(appr: <?=$esc($ev['approver'])?>)</span><?php endif; ?></td><td><?=$esc($ev['actor'])?></td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </details>
      <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
}

// ---------------------------------------------------------------------------
//  Handler — assignment-lifecycle actions on a job.
// ---------------------------------------------------------------------------
function ops_tosrm_job_action($route, $method) {
    $jobId = (int)($_POST['job_id'] ?? $_GET['id'] ?? 0);
    $job = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
    if (!$job) { flash('Job not found.', 'error'); redirect('/jobs'); }
    $back = '/job?id=' . $jobId . '#assign';
    if ($method !== 'POST') redirect($back);
    if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
    ops_require(tosrm_can_edit(), 'You do not have permission to change this assignment.');
    tosrm_migrate_b();

    switch ($route) {
        case 'assign-hold':
            if (tosrm_assign_hold($jobId, (string)($_POST['state'] ?? ''), (string)($_POST['reason'] ?? ''))) flash('Hold state updated.');
            else flash('Pick a valid hold state.', 'error');
            break;
        case 'assign-accept':
            if (tosrm_assign_accept($jobId, (string)($_POST['decision'] ?? ''), (string)($_POST['reason'] ?? '')))
                flash('Resource decision recorded.');
            else flash('A reason is required to decline or request replacement.', 'error');
            break;
        case 'assign-reassign':
            if (tosrm_reassign($jobId, (int)($_POST['inspector_id'] ?? 0), (string)($_POST['reason'] ?? ''), (string)($_POST['approver'] ?? '')))
                flash('Reassigned — the original is kept in the history.');
            else flash('Choose a different resource to reassign to.', 'error');
            break;
        case 'assign-reschedule':
            if (tosrm_reschedule($jobId, (string)($_POST['new_date'] ?? ''), (string)($_POST['reason'] ?? ''), (string)($_POST['approver'] ?? '')))
                flash('Rescheduled — the original date is kept in the history.');
            else flash('Pick a new date different from the current one.', 'error');
            break;
        case 'assign-cancel':
            if (tosrm_assign_cancel($jobId, (string)($_POST['reason'] ?? ''), (string)($_POST['cancel_by'] ?? ''))) flash('Assignment cancelled (who & reason recorded).');
            else flash('A reason is required to cancel.', 'error');
            break;
        case 'assign-noshow':
            if (tosrm_assign_noshow($jobId, (string)($_POST['party'] ?? ''), (string)($_POST['reason'] ?? ''))) flash('No-show recorded.');
            else flash('Pick who did not show.', 'error');
            break;
    }
    redirect($back);
}

// ===========================================================================
//  SLICE C — Readiness, client/vendor confirmation, and competence-at-allocation.
//  All ADDITIVE on the job. Competence checks REUSE the existing competence /
//  certificate engine (lib/competence.php, inspector_certs) and are ADVISORY at
//  allocation — they warn, they never silently block (override stays with the
//  authorised user, exactly as the spec requires). The readiness checklist is
//  GENERIC (any service, not only deputation, which keeps its own MOB checklist).
// ===========================================================================

const TOSRM_READY_STATUS = ['NOT_CHECKED'=>'Not checked','READY'=>'Ready','PARTIAL'=>'Partially ready','NOT_READY'=>'Not ready','BLOCKED'=>'Blocked','NA'=>'Not applicable'];
// Default pre-execution readiness items — not every service needs every one.
const TOSRM_READY_ITEMS = [
    ['Scope available','Scope'], ['Location confirmed','Site'], ['Vendor confirmed','Vendor'],
    ['Documents available','Documents'], ['Material ready','Material'], ['Personnel available','Personnel'],
    ['Equipment available','Equipment'], ['Test / inspection facility ready','Facility'],
    ['Access ready','Access'], ['Travel ready','Travel'], ['Client contact confirmed','Contact'],
];
const TOSRM_CONFIRM_PARTIES = ['CLIENT'=>'Client','VENDOR'=>'Vendor'];

function tosrm_migrate_c() {
    static $done = false; if ($done) return; $done = true;
    tosrm_migrate_b();
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('readiness_status', 'Readiness status', TOSRM_READY_STATUS, 'tosrm');
    }
    if (function_exists('ensure_column')) {
        // Pre-execution confirmation to proceed (optional; only a gate when the
        // per-job "required" flag is set — never mandatory for every service).
        ensure_column('jobs', 'client_confirmed',    'INT DEFAULT 0');
        ensure_column('jobs', 'client_confirm_by',   "VARCHAR(120) DEFAULT ''");
        ensure_column('jobs', 'client_confirm_at',   "VARCHAR(30) DEFAULT ''");
        ensure_column('jobs', 'client_confirm_ref',  "VARCHAR(200) DEFAULT ''");
        ensure_column('jobs', 'client_confirm_required', 'INT DEFAULT 0');
        ensure_column('jobs', 'vendor_confirmed',    'INT DEFAULT 0');
        ensure_column('jobs', 'vendor_confirm_by',   "VARCHAR(120) DEFAULT ''");
        ensure_column('jobs', 'vendor_confirm_at',   "VARCHAR(30) DEFAULT ''");
        ensure_column('jobs', 'vendor_readiness',    "VARCHAR(200) DEFAULT ''");
    }
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS job_readiness (
        id $pk, job_id INT DEFAULT 0, item VARCHAR(200) DEFAULT '', category VARCHAR(80) DEFAULT '',
        required INT DEFAULT 1, status VARCHAR(16) DEFAULT 'NOT_CHECKED', note VARCHAR(400) DEFAULT '',
        sort_order INT DEFAULT 0, updated_by VARCHAR(120) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
}

// ---- Competence & certification advisory (REUSES lib/competence.php) --------
// Returns a list of ['level'=>'error'|'warn'|'ok', 'text'=>…] — never blocks.
function tosrm_competence_warn($job) {
    tosrm_migrate_c();
    $out = [];
    $inspId = (int)($job['inspector_id'] ?? 0);
    if ($inspId <= 0) return $out;
    $date = substr((string)($job['scheduled_date'] ?? ''), 0, 10) ?: date('Y-m-d');
    // Context — inspection type / activity / client (from the job, else its call).
    $itype = trim((string)($job['inspection_type'] ?? ''));
    $activity = (int)($job['activity_id'] ?? 0);
    $clientId = (int)($job['client_id'] ?? 0);
    if (($itype === '' || $clientId === 0) && (int)($job['call_id'] ?? 0) > 0) {
        $call = ops_one("SELECT client_id, inspection_type, activity_id FROM calls WHERE id=?", [(int)$job['call_id']]);
        if ($call) { if ($itype === '') $itype = (string)$call['inspection_type']; if ($activity === 0) $activity = (int)$call['activity_id']; if ($clientId === 0) $clientId = (int)$call['client_id']; }
    }
    // 1) Lapsed MANDATORY certificate on the work date (reuse competence_lapsed).
    if (function_exists('competence_lapsed')) {
        foreach (competence_lapsed($inspId, $date) as $c)
            $out[] = ['level'=>'error', 'text'=>'Required certificate lapsed: ' . $c['name'] . ($c['number'] ? ' (' . $c['number'] . ')' : '') . ' expired ' . $c['valid_to']];
    }
    // 2) Certificates EXPIRING soon around the work date (reuse inspector_cert_state).
    if (function_exists('inspector_cert_state')) {
        foreach (ops_all("SELECT name, number, valid_to FROM inspector_certs WHERE inspector_id=? AND valid_to<>''", [$inspId]) ?: [] as $c) {
            $st = inspector_cert_state($c['valid_to'], 45);
            $lbl = is_array($st) ? ($st['label'] ?? '') : (string)$st;
            if ($lbl === 'expiring') $out[] = ['level'=>'warn', 'text'=>'Certificate expiring soon: ' . $c['name'] . ' (valid to ' . $c['valid_to'] . ')'];
        }
    }
    // 3) Authorisation coverage for THIS scope (reuse auth_covers) — advisory even
    //    when enforcement is switched off, so the coordinator sees the gap.
    if (function_exists('auth_covers') && ($itype !== '' || $activity || $clientId)) {
        if (!auth_covers($inspId, $itype, $activity, $clientId, $date)) {
            $enf = function_exists('auth_enforced') && auth_enforced();
            $out[] = ['level'=>$enf ? 'error' : 'warn', 'text'=>'Not on the authorisation record for this scope' . ($enf ? ' (enforcement is ON — issuance will be blocked)' : ' (advisory — enforcement is off)')];
        }
    }
    if (!$out) $out[] = ['level'=>'ok', 'text'=>'No competence or certificate concerns for this assignment.'];
    return $out;
}

// ---- Generic readiness checklist -------------------------------------------
function tosrm_readiness_seed($jobId) {
    tosrm_migrate_c();
    $have = ops_one("SELECT COUNT(*) n FROM job_readiness WHERE job_id=?", [(int)$jobId]);
    if ((int)($have['n'] ?? 0) > 0) return 0;
    $i = 0; $n = 0;
    foreach (TOSRM_READY_ITEMS as [$item, $cat]) {
        db()->prepare("INSERT INTO job_readiness (job_id, item, category, required, status, sort_order, updated_at) VALUES (?,?,?,1,'NOT_CHECKED',?,?)")
            ->execute([(int)$jobId, $item, $cat, $i++, tosrm_now()]);
        $n++;
    }
    return $n;
}
function tosrm_readiness_set($itemId, $status, $note = '') {
    $status = strtoupper(trim((string)$status));
    if (!array_key_exists($status, TOSRM_READY_STATUS)) return false;
    db()->prepare("UPDATE job_readiness SET status=?, note=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([$status, (string)$note, tosrm_actor(), tosrm_now(), (int)$itemId]);
    return true;
}
function tosrm_readiness_list($jobId) { return ops_all("SELECT * FROM job_readiness WHERE job_id=? ORDER BY sort_order, id", [(int)$jobId]) ?: []; }
function tosrm_readiness_rollup($jobId) {
    $rows = tosrm_readiness_list($jobId);
    $r = ['total'=>count($rows), 'ready'=>0, 'open'=>0, 'blocked'=>0];
    foreach ($rows as $x) {
        $st = $x['status'];
        if ($st === 'READY' || $st === 'NA') $r['ready']++;
        if ($st === 'BLOCKED') $r['blocked']++;
        if ((int)$x['required'] === 1 && !in_array($st, ['READY','NA'], true)) $r['open']++;
    }
    $r['ready_bool'] = $r['total'] > 0 && $r['open'] === 0;
    return $r;
}

// ---- Client / vendor pre-execution confirmation ----------------------------
function tosrm_confirm_set($jobId, $party, $on, $ref = '') {
    tosrm_migrate_c();
    $party = strtoupper(trim((string)$party));
    if (!array_key_exists($party, TOSRM_CONFIRM_PARTIES)) return false;
    $on = $on ? 1 : 0;
    if ($party === 'CLIENT') {
        db()->prepare("UPDATE jobs SET client_confirmed=?, client_confirm_by=?, client_confirm_at=?, client_confirm_ref=? WHERE id=?")
            ->execute([$on, $on ? tosrm_actor() : '', $on ? tosrm_now() : '', (string)$ref, (int)$jobId]);
    } else {
        db()->prepare("UPDATE jobs SET vendor_confirmed=?, vendor_confirm_by=?, vendor_confirm_at=?, vendor_readiness=? WHERE id=?")
            ->execute([$on, $on ? tosrm_actor() : '', $on ? tosrm_now() : '', (string)$ref, (int)$jobId]);
    }
    if (function_exists('idems_log')) { try { idems_log('job', (int)$jobId, 'CONFIRM_' . $party, ['new'=>$on ? 'confirmed' : 'cleared']); } catch (Throwable $e) {} }
    return true;
}
function tosrm_confirm_set_required($jobId, $required) {
    tosrm_migrate_c();
    db()->prepare("UPDATE jobs SET client_confirm_required=? WHERE id=?")->execute([$required ? 1 : 0, (int)$jobId]);
    return true;
}
// Advisory gate — only bites when the per-job "required" flag is set. Never
// mandatory for every service.
function tosrm_confirm_gate($job) {
    $required = (int)($job['client_confirm_required'] ?? 0) === 1;
    $confirmed = (int)($job['client_confirmed'] ?? 0) === 1;
    return ['required'=>$required, 'confirmed'=>$confirmed, 'blocks'=>($required && !$confirmed)];
}

// A combined "ready to execute?" view (advisory) — used by dashboards later.
function tosrm_job_ready_to_execute($job) {
    $roll = tosrm_readiness_rollup((int)($job['id'] ?? 0));
    $gate = tosrm_confirm_gate($job);
    return ['ok'=>($roll['ready_bool'] && !$gate['blocks']), 'readiness'=>$roll, 'confirm'=>$gate];
}

// ---------------------------------------------------------------------------
//  Readiness / confirmation / competence panel (job detail page).
// ---------------------------------------------------------------------------
function tosrm_render_readiness_panel($job) {
    if (!$job) return;
    tosrm_migrate_c();
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $jid = (int)($job['id'] ?? 0);
    $canEdit = tosrm_can_edit();
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $warn = tosrm_competence_warn($job);
    $items = tosrm_readiness_list($jid);
    $roll = tosrm_readiness_rollup($jid);
    $gate = tosrm_confirm_gate($job);
    ob_start(); ?>
    <style>
      .tosrm-ready>h3{margin:0 0 4px}
      .tosrm-ready .tr-sub{color:var(--muted);font-size:13px;margin:0 0 14px}
      .tosrm-ready .tr-sec{margin-top:18px}
      .tosrm-ready .tr-sec:first-of-type{margin-top:0}
      .tosrm-ready .tr-h{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:0 0 9px}
      .tosrm-ready ul.tr-list{margin:0;padding-left:2px;list-style:none;display:flex;flex-direction:column;gap:5px}
      .tosrm-ready ul.tr-list li{font-size:13.5px;padding-left:18px;position:relative}
      .tosrm-ready ul.tr-list li::before{content:"•";position:absolute;left:4px;top:-1px}
      .tosrm-ready .tr-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px}
      .tosrm-ready .tr-actions form{background:var(--soft);border:1px solid var(--line);border-radius:10px;padding:11px 12px;margin:0}
      .tosrm-ready .tr-actions .tr-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
      .tosrm-ready .tr-actions .tr-row>.form-control{flex:1 1 150px;min-width:0}
      @media(max-width:640px){.tosrm-ready .tr-actions{grid-template-columns:1fr}.tosrm-ready .tr-actions .tr-row>.form-control{flex-basis:100%}}
    </style>
    <div class="card tosrm-ready" style="margin-top:16px">
      <h3>Operations — readiness &amp; confirmation</h3>
      <p class="tr-sub">Competence advisories, client / vendor confirmation, and the pre-execution readiness checklist.</p>

      <?php // Competence & certification advisory (reuses the competence engine).
            //   Shown when it matters: a real blocker (a lapsed/error item), or
            //   when enforcement is switched on. When enforcement is OFF and there
            //   is no blocker, it is noise for the coordinator, so it is hidden.
            $compErr = false; foreach ($warn as $w) if (($w['level'] ?? '') === 'error') $compErr = true;
            $showComp = $compErr || (function_exists('auth_enforced') && auth_enforced());
            if ($showComp): ?>
      <div class="tr-sec">
        <div class="tr-h">Competence &amp; certification</div>
        <ul class="tr-list">
          <?php foreach ($warn as $w): if (($w['level'] ?? '') === 'ok') continue; $col = $w['level']==='error' ? 'var(--bad)' : ($w['level']==='warn' ? 'var(--warn)' : 'var(--ok)'); ?>
            <li style="color:<?=$col?>"><?=$esc($w['text'])?></li>
          <?php endforeach; ?>
        </ul>
        <p class="muted" style="font-size:12px;margin:6px 0 0">Advisory only — the coordinator decides; issuance-time enforcement is unchanged.</p>
      </div>
      <?php endif; ?>

      <?php // Client / vendor confirmation ?>
      <div class="tr-sec">
        <div class="tr-h">Pre-execution confirmation</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <span class="pill <?=((int)($job['client_confirmed']??0)===1)?'p-ok':'p-mut'?>"><?=((int)($job['client_confirmed']??0)===1)?'✓ Client confirmed':'Client not confirmed'?><?php if (trim((string)($job['client_confirm_ref']??''))!==''): ?> · <?=$esc($job['client_confirm_ref'])?><?php endif; ?></span>
          <span class="pill <?=((int)($job['vendor_confirmed']??0)===1)?'p-ok':'p-mut'?>"><?=((int)($job['vendor_confirmed']??0)===1)?'✓ Vendor confirmed':'Vendor not confirmed'?><?php if (trim((string)($job['vendor_readiness']??''))!==''): ?> · <?=$esc($job['vendor_readiness'])?><?php endif; ?></span>
          <?php if ($gate['required']): ?><span class="pill p-bad">Client confirmation required<?=$gate['blocks']?' — not yet given':''?></span><?php endif; ?>
        </div>
        <?php if ($canEdit): ?>
        <div class="tr-actions">
          <form method="post" action="/job-confirm">
            <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>"><input type="hidden" name="party" value="CLIENT">
            <input type="hidden" name="on" value="<?=((int)($job['client_confirmed']??0)===1)?'0':'1'?>">
            <div class="tr-row"><input class="form-control" type="text" name="ref" placeholder="Client confirm ref / contact"><button class="btn" type="submit"><?=((int)($job['client_confirmed']??0)===1)?'Clear':'Mark confirmed'?></button></div>
          </form>
          <form method="post" action="/job-confirm">
            <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>"><input type="hidden" name="party" value="VENDOR">
            <input type="hidden" name="on" value="<?=((int)($job['vendor_confirmed']??0)===1)?'0':'1'?>">
            <div class="tr-row"><input class="form-control" type="text" name="ref" placeholder="Vendor readiness note"><button class="btn" type="submit"><?=((int)($job['vendor_confirmed']??0)===1)?'Clear':'Mark confirmed'?></button></div>
          </form>
          <form method="post" action="/job-confirm-req" style="grid-column:1/-1">
            <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
            <input type="hidden" name="required" value="<?=$gate['required']?'0':'1'?>">
            <button class="btn secondary" type="submit"><?=$gate['required']?'Make client confirmation optional':'Require client confirmation'?></button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <?php // Readiness checklist ?>
      <div class="tr-sec">
        <div class="tr-h">Readiness checklist
          <?php if ($items): ?><span class="muted" style="font-weight:600;text-transform:none;letter-spacing:0"> — <?=$roll['ready']?>/<?=$roll['total']?> ready<?php if ($roll['open']>0): ?>, <?=$roll['open']?> open<?php endif; ?><?php if ($roll['blocked']>0): ?>, <span style="color:var(--bad)"><?=$roll['blocked']?> blocked</span><?php endif; ?></span><?php endif; ?>
        </div>
        <?php if (!$items && $canEdit): ?>
          <form method="post" action="/job-ready-seed">
            <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>">
            <button class="btn secondary" type="submit">Add readiness checklist</button>
            <span class="muted" style="font-size:12px;margin-left:8px">Optional — not every service needs it. Items are editable afterwards.</span>
          </form>
        <?php elseif ($items): ?>
        <table class="grid" style="width:100%;margin-top:4px">
          <thead><tr><th>Item</th><th>Status</th><?php if ($canEdit): ?><th style="width:1%">Update</th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($items as $it): $st=$it['status']; $tone = $st==='READY'?'p-ok':($st==='BLOCKED'?'p-bad':($st==='NA'?'p-mut':'p-warn')); ?>
            <tr>
              <td><?=$esc($it['item'])?> <span class="muted">· <?=$esc($it['category'])?></span><?php if (trim((string)$it['note'])!==''): ?><br><span class="muted"><?=$esc($it['note'])?></span><?php endif; ?></td>
              <td><span class="pill <?=$tone?>"><?=$esc(TOSRM_READY_STATUS[$it['status']] ?? $it['status'])?></span></td>
              <?php if ($canEdit): ?>
              <td>
                <form method="post" action="/job-ready-set" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                  <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="job_id" value="<?=$jid?>"><input type="hidden" name="item_id" value="<?=$esc($it['id'])?>">
                  <select class="form-control" name="status" style="width:auto"><?php foreach (TOSRM_READY_STATUS as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$it['status']?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
                  <input class="form-control" type="text" name="note" placeholder="Note" value="<?=$esc($it['note'])?>" style="width:140px">
                  <button class="btn" type="submit">Set</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <?php
    echo ob_get_clean();
}

// ---------------------------------------------------------------------------
//  Handler — readiness / confirmation actions on a job.
// ---------------------------------------------------------------------------
function ops_tosrm_ready_action($route, $method) {
    $jobId = (int)($_POST['job_id'] ?? $_GET['id'] ?? 0);
    $job = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
    if (!$job) { flash('Job not found.', 'error'); redirect('/jobs'); }
    $back = '/job?id=' . $jobId . '#ready';
    if ($method !== 'POST') redirect($back);
    if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
    ops_require(tosrm_can_edit(), 'You do not have permission to change this job.');
    tosrm_migrate_c();

    switch ($route) {
        case 'job-ready-seed':
            $n = tosrm_readiness_seed($jobId);
            flash($n > 0 ? "Readiness checklist added ($n items — edit or mark N/A freely)." : 'A readiness checklist is already present.');
            break;
        case 'job-ready-set':
            if (tosrm_readiness_set((int)($_POST['item_id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['note'] ?? ''))) flash('Readiness item updated.');
            else flash('Pick a valid readiness status.', 'error');
            break;
        case 'job-confirm':
            tosrm_confirm_set($jobId, (string)($_POST['party'] ?? ''), (int)($_POST['on'] ?? 0) === 1, (string)($_POST['ref'] ?? ''));
            flash('Confirmation updated.');
            break;
        case 'job-confirm-req':
            tosrm_confirm_set_required($jobId, (int)($_POST['required'] ?? 0) === 1);
            flash('Client-confirmation requirement updated.');
            break;
    }
    redirect($back);
}

// ===========================================================================
//  SLICE D — SLA, full-chain TAT, delay, recurring services, capacity horizon.
//  Additive. TAT/SLA are COMPUTED from data the call/job already carry (plus the
//  Slice-A status history) — no new bookkeeping needed. Delay on the core job is
//  captured here (expediting delay stays in Phase 6 — this links, never
//  duplicates). Recurring generates future calls, each clearly marked. Capacity
//  outlook REUSES the availability engine.
// ===========================================================================

const TOSRM_SLA_STAGES = ['RESPONSE'=>'Response','SCHEDULING'=>'Scheduling','EXECUTION'=>'Execution','REPORT'=>'Report submission','CLOSURE'=>'Closure'];
const TOSRM_SLA_STATUS = ['WITHIN'=>'Within SLA','AT_RISK'=>'At risk','BREACHED'=>'Breached','NA'=>'Not applicable'];
const TOSRM_DELAY_REASONS = [
    'RESOURCE'=>'Resource unavailable','CLIENT'=>'Client change','VENDOR'=>'Vendor change','SCOPE'=>'Scope change',
    'LOCATION'=>'Location issue','ACCESS'=>'Access issue','TRAVEL'=>'Travel','WEATHER'=>'Weather',
    'TECHNICAL'=>'Technical requirement','DOCS'=>'Documentation','READINESS'=>'Inspection readiness',
    'APPROVAL'=>'Approval','CAPACITY'=>'TPIA capacity','OTHER'=>'Other',
];
const TOSRM_DELAY_RESP = ['TPIA'=>'TPIA','CLIENT'=>'Client','VENDOR'=>'Vendor','EXTERNAL'=>'External','NEUTRAL'=>'No fault / neutral'];
const TOSRM_RECUR_FREQ = ['DAILY'=>'Daily','WEEKLY'=>'Weekly','FORTNIGHTLY'=>'Fortnightly','MONTHLY'=>'Monthly','QUARTERLY'=>'Quarterly','CUSTOM'=>'Custom (every N days)'];

function tosrm_migrate_d() {
    static $done = false; if ($done) return; $done = true;
    tosrm_migrate_c();
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('sla_stage',   'SLA stage',        TOSRM_SLA_STAGES,   'tosrm');
        lk_ensure_type_map('sla_status',   'SLA status',       TOSRM_SLA_STATUS,   'tosrm');
        lk_ensure_type_map('delay_reason', 'Delay reason',     TOSRM_DELAY_REASONS,'tosrm');
        lk_ensure_type_map('delay_resp',   'Delay responsibility', TOSRM_DELAY_RESP,'tosrm');
        lk_ensure_type_map('recur_freq',   'Recurrence',       TOSRM_RECUR_FREQ,   'tosrm');
    }
    if (function_exists('ensure_column')) {
        // A generated call remains identifiable as a generated record (§77).
        ensure_column('calls', 'generated_by_recurring_id', 'INT DEFAULT 0');
    }
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS sla_targets (
        id $pk, client_id INT DEFAULT 0, stage VARCHAR(20) DEFAULT '', target_days INT DEFAULT 0,
        note VARCHAR(200) DEFAULT '', updated_by VARCHAR(120) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS job_delays (
        id $pk, job_id INT DEFAULT 0, call_id INT DEFAULT 0, requested_date VARCHAR(20) DEFAULT '',
        scheduled_date VARCHAR(20) DEFAULT '', execution_date VARCHAR(20) DEFAULT '', delay_days INT DEFAULT 0,
        reason VARCHAR(24) DEFAULT '', category VARCHAR(24) DEFAULT '', responsibility VARCHAR(16) DEFAULT '',
        impact VARCHAR(300) DEFAULT '', action VARCHAR(300) DEFAULT '', is_expediting INT DEFAULT 0,
        created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS recurring_services (
        id $pk, client_id INT DEFAULT 0, title VARCHAR(200) DEFAULT '', template TEXT,
        frequency VARCHAR(16) DEFAULT 'MONTHLY', interval_n INT DEFAULT 1, start_date VARCHAR(20) DEFAULT '',
        end_date VARCHAR(20) DEFAULT '', next_run VARCHAR(20) DEFAULT '', active INT DEFAULT 1,
        last_generated VARCHAR(20) DEFAULT '', generated_count INT DEFAULT 0,
        created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
}

// ---- Full-chain TAT (computed) ---------------------------------------------
function tosrm_tat($call) {
    tosrm_migrate_d();
    $cid = (int)($call['id'] ?? 0);
    $received = substr((string)($call['call_received_date'] ?? ''), 0, 10);
    $forwarded = substr((string)($call['forwarded_at'] ?? ''), 0, 10);
    $jobs = ops_all("SELECT id, scheduled_date, inspection_end_date, closed_at, closed_flag FROM jobs WHERE call_id=?", [$cid]) ?: [];
    $minNonEmpty = function($rows, $k) { $vals = array_filter(array_map(fn($r)=>substr((string)($r[$k] ?? ''),0,10), $rows)); return $vals ? min($vals) : ''; };
    $maxNonEmpty = function($rows, $k) { $vals = array_filter(array_map(fn($r)=>substr((string)($r[$k] ?? ''),0,10), $rows)); return $vals ? max($vals) : ''; };
    $scheduled = $minNonEmpty($jobs, 'scheduled_date');
    $executed  = $maxNonEmpty($jobs, 'inspection_end_date') ?: $maxNonEmpty($jobs, 'scheduled_date');
    // Report — the earliest issued report tied to this call's jobs (report_docs).
    $report = '';
    $jids = array_values(array_filter(array_map(fn($j)=>(int)$j['id'], $jobs)));
    if ($jids) {
        $in = implode(',', $jids);
        try { $rr = ops_one("SELECT issue_date FROM report_docs WHERE job_id IN ($in) AND COALESCE(issue_date,'')<>'' AND COALESCE(deleted,0)=0 ORDER BY issue_date LIMIT 1"); if ($rr) $report = substr((string)$rr['issue_date'], 0, 10); }
        catch (Throwable $e) {}
    }
    // Closure — the CLOSED status event, else the latest closed-job timestamp.
    $closure = '';
    $ce = ops_one("SELECT at FROM call_status_events WHERE call_id=? AND new_status='CLOSED' ORDER BY id DESC", [$cid]);
    if ($ce && $ce['at']) $closure = substr((string)$ce['at'], 0, 10);
    if ($closure === '') { $closedJobs = array_filter($jobs, fn($j)=>!empty($j['closed_flag'])); if ($closedJobs) $closure = $maxNonEmpty($closedJobs, 'closed_at'); }
    $db = fn($a,$b) => ($a !== '' && $b !== '' && function_exists('days_between')) ? days_between($a, $b) : null;
    return [
        'dates' => compact('received','forwarded','scheduled','executed','report','closure'),
        'stages' => [
            'received_to_scheduled' => $db($received, $scheduled),
            'scheduled_to_executed' => $db($scheduled, $executed),
            'executed_to_report'    => $db($executed, $report),
            'received_to_closure'   => $db($received, $closure),
        ],
    ];
}

// ---- SLA targets + evaluation ----------------------------------------------
function tosrm_sla_set($clientId, $stage, $days, $note = '') {
    tosrm_migrate_d();
    $stage = strtoupper(trim((string)$stage)); if (!array_key_exists($stage, TOSRM_SLA_STAGES)) return false;
    $ex = ops_one("SELECT id FROM sla_targets WHERE client_id=? AND stage=?", [(int)$clientId, $stage]);
    if ($ex) db()->prepare("UPDATE sla_targets SET target_days=?, note=?, updated_by=?, updated_at=? WHERE id=?")->execute([(int)$days, (string)$note, tosrm_actor(), tosrm_now(), (int)$ex['id']]);
    else db()->prepare("INSERT INTO sla_targets (client_id, stage, target_days, note, updated_by, updated_at) VALUES (?,?,?,?,?,?)")->execute([(int)$clientId, $stage, (int)$days, (string)$note, tosrm_actor(), tosrm_now()]);
    return true;
}
function tosrm_sla_delete($id) { db()->prepare("DELETE FROM sla_targets WHERE id=?")->execute([(int)$id]); return true; }
function tosrm_sla_targets($clientId = 0) {
    tosrm_migrate_d();
    $out = [];
    foreach (ops_all("SELECT stage, target_days FROM sla_targets WHERE client_id=0") ?: [] as $r) $out[$r['stage']] = (int)$r['target_days'];
    if ($clientId > 0) foreach (ops_all("SELECT stage, target_days FROM sla_targets WHERE client_id=?", [(int)$clientId]) ?: [] as $r) $out[$r['stage']] = (int)$r['target_days'];
    return $out;
}
// Per-stage target vs actual for a call. Stage endpoint reached → within/breached;
// not reached → pending / at-risk / breached against elapsed time.
function tosrm_sla_eval($call) {
    $targets = tosrm_sla_targets((int)($call['client_id'] ?? 0));
    $tat = tosrm_tat($call);
    $d = $tat['dates'];
    $today = date('Y-m-d');
    $dbn = fn($a,$b) => ($a !== '' && $b !== '' && function_exists('days_between')) ? days_between($a, $b) : null;
    // stage => [startDate, endDate]
    $map = [
        'RESPONSE'   => [$d['received'], $d['forwarded']],
        'SCHEDULING' => [$d['received'], $d['scheduled']],
        'EXECUTION'  => [$d['scheduled'], $d['executed']],
        'REPORT'     => [$d['executed'], $d['report']],
        'CLOSURE'    => [$d['received'], $d['closure']],
    ];
    $out = [];
    foreach (TOSRM_SLA_STAGES as $stage => $label) {
        $target = $targets[$stage] ?? null;
        [$start, $end] = $map[$stage];
        if ($target === null) { $out[$stage] = ['label'=>$label, 'target'=>null, 'actual'=>null, 'status'=>'NA']; continue; }
        if ($end !== '' && $start !== '') {                    // completed
            $actual = $dbn($start, $end);
            $status = ($actual !== null && $actual <= $target) ? 'WITHIN' : 'BREACHED';
        } elseif ($start !== '') {                             // in flight
            $elapsed = $dbn($start, $today); $actual = $elapsed;
            if ($elapsed !== null && $elapsed > $target) $status = 'BREACHED';
            elseif ($elapsed !== null && $elapsed >= 0.8 * $target) $status = 'AT_RISK';
            else $status = 'WITHIN';
        } else { $actual = null; $status = 'NA'; }
        $out[$stage] = ['label'=>$label, 'target'=>$target, 'actual'=>$actual, 'status'=>$status];
    }
    return $out;
}

// ---- Delay on the core job (links to Phase 6 for expediting) ----------------
function tosrm_delay_auto_days($job, $call = null) {
    $req = substr((string)($call['inspection_required_date'] ?? ''), 0, 10);
    $sch = substr((string)($job['scheduled_date'] ?? ''), 0, 10);
    if ($req === '' || $sch === '' || !function_exists('days_between')) return null;
    return days_between($req, $sch);   // + => scheduled after required (a delay)
}
function tosrm_delay_add($jobId, $data) {
    tosrm_migrate_d();
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]); if (!$job) return 0;
    $call = (int)($job['call_id'] ?? 0) ? ops_one("SELECT * FROM calls WHERE id=?", [(int)$job['call_id']]) : null;
    $isExp = false;
    $itype = (string)($job['inspection_type'] ?? ($call['inspection_type'] ?? ''));
    if (strtoupper($itype) === 'EXPEDITING') $isExp = true;
    $days = $data['delay_days'] ?? tosrm_delay_auto_days($job, $call);
    db()->prepare("INSERT INTO job_delays (job_id, call_id, requested_date, scheduled_date, execution_date, delay_days, reason, category, responsibility, impact, action, is_expediting, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        (int)$jobId, (int)($job['call_id'] ?? 0), (string)($call['inspection_required_date'] ?? ''), (string)($job['scheduled_date'] ?? ''),
        (string)($data['execution_date'] ?? ''), (int)($days ?? 0), strtoupper((string)($data['reason'] ?? '')), strtoupper((string)($data['category'] ?? '')),
        strtoupper((string)($data['responsibility'] ?? '')), (string)($data['impact'] ?? ''), (string)($data['action'] ?? ''),
        $isExp ? 1 : 0, tosrm_actor(), tosrm_now()]);
    return (int)db()->lastInsertId();
}
function tosrm_delay_list_for_call($callId) { return ops_all("SELECT * FROM job_delays WHERE call_id=? ORDER BY id DESC", [(int)$callId]) ?: []; }
function tosrm_delay_list_for_job($jobId) { return ops_all("SELECT * FROM job_delays WHERE job_id=? ORDER BY id DESC", [(int)$jobId]) ?: []; }

// ---- Recurring services (auto-generate future calls) ------------------------
function tosrm_recur_next($frequency, $intervalN, $from) {
    $from = substr((string)$from, 0, 10) ?: date('Y-m-d');
    $n = max(1, (int)$intervalN);
    switch (strtoupper((string)$frequency)) {
        case 'DAILY':       return date('Y-m-d', strtotime("$from +$n day"));
        case 'WEEKLY':      return date('Y-m-d', strtotime("$from +" . (7 * $n) . " day"));
        case 'FORTNIGHTLY': return date('Y-m-d', strtotime("$from +14 day"));
        case 'MONTHLY':     return date('Y-m-d', strtotime("$from +$n month"));
        case 'QUARTERLY':   return date('Y-m-d', strtotime("$from +3 month"));
        case 'CUSTOM':      return date('Y-m-d', strtotime("$from +$n day"));
    }
    return date('Y-m-d', strtotime("$from +1 month"));
}
function tosrm_recurring_create($data) {
    tosrm_migrate_d();
    $title = trim((string)($data['title'] ?? '')); if ($title === '') return 0;
    $start = substr((string)($data['start_date'] ?? date('Y-m-d')), 0, 10);
    db()->prepare("INSERT INTO recurring_services (client_id, title, template, frequency, interval_n, start_date, end_date, next_run, active, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,1,?,?)")->execute([
        (int)($data['client_id'] ?? 0), $title, json_encode($data['template'] ?? []), strtoupper((string)($data['frequency'] ?? 'MONTHLY')),
        (int)($data['interval_n'] ?? 1), $start, substr((string)($data['end_date'] ?? ''),0,10), $start, tosrm_actor(), tosrm_now()]);
    return (int)db()->lastInsertId();
}
function tosrm_recurring_due($today = null) {
    tosrm_migrate_d();
    $today = $today ?: date('Y-m-d');
    return ops_all("SELECT * FROM recurring_services WHERE active=1 AND next_run<>'' AND next_run<=? AND (end_date='' OR end_date>=?) ORDER BY next_run", [$today, $today]) ?: [];
}
// Generate ONE call from a recurring template, mark it as generated, advance.
function tosrm_recurring_generate($rec, $today = null) {
    tosrm_migrate_d();
    $today = $today ?: date('Y-m-d');
    $tpl = json_decode((string)$rec['template'], true) ?: [];
    $cols = ['client_id'=>(int)$rec['client_id'], 'inspection_type'=>(string)($tpl['inspection_type'] ?? ''),
             'vendor_id'=>(int)($tpl['vendor_id'] ?? 0), 'site_address_id'=>(int)($tpl['site_address_id'] ?? 0),
             'sbu'=>(string)($tpl['sbu'] ?? ''), 'deliverables'=>(string)($tpl['deliverables'] ?? ''),
             'executing_office_id'=>(int)($tpl['executing_office_id'] ?? 0), 'notes'=>(string)($tpl['notes'] ?? ''),
             'status'=>'OPEN', 'op_status'=>'RECEIVED', 'source'=>'RECURRING',
             'call_received_date'=>$today, 'inspection_required_date'=>(string)$rec['next_run'],
             'generated_by_recurring_id'=>(int)$rec['id'], 'created_at'=>tosrm_now()];
    $keys = array_keys($cols); $ph = implode(',', array_fill(0, count($keys), '?'));
    db()->prepare("INSERT INTO calls (" . implode(',', $keys) . ") VALUES ($ph)")->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    $next = tosrm_recur_next($rec['frequency'], $rec['interval_n'], $rec['next_run']);
    db()->prepare("UPDATE recurring_services SET next_run=?, last_generated=?, generated_count=generated_count+1 WHERE id=?")
        ->execute([$next, $today, (int)$rec['id']]);
    if (function_exists('idems_log')) { try { idems_log('call', $newId, 'GENERATED', ['new'=>'recurring #' . $rec['id']]); } catch (Throwable $e) {} }
    return $newId;
}
// Cron entry — generate everything due. Returns the count generated.
function tosrm_run_recurring($today = null) {
    $n = 0;
    foreach (tosrm_recurring_due($today) as $rec) { if (tosrm_recurring_generate($rec, $today)) $n++; }
    return $n;
}

// ---- Capacity horizon (reuses the availability engine) ----------------------
// Weekly buckets of free inspector-days (supply) vs unallocated demand calls.
function tosrm_capacity_horizon($offices, $from, $to) {
    tosrm_migrate_d();
    if (!function_exists('availability_matrix')) return [];
    [$matrix, $days, $people] = availability_matrix($offices, $from, $to);
    // Free inspector-days per day (working days only).
    $freePerDay = [];
    foreach ($days as $day) {
        $free = 0;
        foreach ($people as $p) {
            $office = (int)($p['home_office_id'] ?? 0) ?: null;
            $working = function_exists('is_working_day') ? is_working_day($day, $office) : (date('N', strtotime($day)) < 6);
            if (!$working) continue;
            if (($matrix[(int)$p['id']][$day] ?? 'AVAILABLE') === 'AVAILABLE') $free++;
        }
        $freePerDay[$day] = $free;
    }
    // Unallocated demand calls by required date.
    $demand = [];
    foreach (sched_unallocated_calls($offices, 500) as $c) {
        $d = substr((string)($c['inspection_required_date'] ?? ''), 0, 10);
        if ($d !== '') $demand[$d] = ($demand[$d] ?? 0) + 1;
    }
    // Bucket into ISO weeks.
    $buckets = [];
    foreach ($days as $day) {
        $wk = date('o-\WW', strtotime($day));
        if (!isset($buckets[$wk])) $buckets[$wk] = ['label'=>$wk, 'from'=>$day, 'to'=>$day, 'supply'=>0, 'demand'=>0];
        $buckets[$wk]['to'] = $day;
        $buckets[$wk]['supply'] += $freePerDay[$day] ?? 0;
        $buckets[$wk]['demand'] += $demand[$day] ?? 0;
    }
    foreach ($buckets as &$b) { $b['gap'] = $b['demand'] - $b['supply']; $b['tight'] = $b['demand'] > $b['supply']; }
    return array_values($buckets);
}

// ---------------------------------------------------------------------------
//  SLA / TAT / delay panel (call detail page).
// ---------------------------------------------------------------------------
function tosrm_render_call_sla($call) {
    if (!$call) return;
    tosrm_migrate_d();
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $cid = (int)($call['id'] ?? 0);
    $canEdit = tosrm_can_edit();
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $tat = tosrm_tat($call); $sla = tosrm_sla_eval($call);
    $delays = tosrm_delay_list_for_call($cid);
    $jobs = ops_all("SELECT id, job_code, scheduled_date FROM jobs WHERE call_id=? ORDER BY id DESC", [$cid]) ?: [];
    $tatLabels = ['received_to_scheduled'=>'Received → Scheduled','scheduled_to_executed'=>'Scheduled → Executed','executed_to_report'=>'Executed → Report','received_to_closure'=>'Received → Closure'];
    $slaTone = fn($s) => $s==='BREACHED' ? '#b4232b' : ($s==='AT_RISK' ? '#b45309' : ($s==='WITHIN' ? '#15803d' : '#6b7480'));
    ob_start(); ?>
    <div class="card tosrm-sla" style="margin-top:16px">
      <h3 style="margin:0 0 10px">Operations — turnaround &amp; SLA</h3>
      <div style="display:flex;flex-wrap:wrap;gap:16px">
        <div style="flex:1;min-width:240px">
          <strong>Turnaround (days)</strong>
          <table class="tbl" style="width:100%;margin-top:6px;font-size:13px">
            <?php foreach ($tatLabels as $k=>$lbl): $v = $tat['stages'][$k]; ?>
              <tr><td><?=$esc($lbl)?></td><td style="text-align:right;font-variant-numeric:tabular-nums"><?= $v===null ? '<span class="muted">—</span>' : (int)$v ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
        <div style="flex:1;min-width:240px">
          <strong>SLA (target vs actual)</strong>
          <table class="tbl" style="width:100%;margin-top:6px;font-size:13px">
            <thead><tr><th>Stage</th><th>Target</th><th>Actual</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($sla as $st=>$row): ?>
              <tr><td><?=$esc($row['label'])?></td><td><?= $row['target']===null?'<span class="muted">—</span>':(int)$row['target'].'d' ?></td><td><?= $row['actual']===null?'—':(int)$row['actual'].'d' ?></td><td style="color:<?=$slaTone($row['status'])?>"><?=$esc(TOSRM_SLA_STATUS[$row['status']] ?? $row['status'])?></td></tr>
            <?php endforeach; ?></tbody>
          </table>
          <div class="muted" style="font-size:12px">Set targets in <a href="/sla-targets">SLA targets</a> (default + per-client).</div>
        </div>
      </div>

      <div style="margin-top:12px">
        <strong>Delays</strong> <?php if ($delays): ?><span class="muted">(<?=count($delays)?>)</span><?php endif; ?>
        <?php if ($delays): ?>
        <table class="tbl" style="width:100%;margin-top:6px;font-size:12.5px">
          <thead><tr><th>Days</th><th>Reason</th><th>Responsibility</th><th>Impact / action</th></tr></thead>
          <tbody><?php foreach ($delays as $d): ?>
            <tr><td><?= (int)$d['delay_days'] ?></td><td><?=$esc(TOSRM_DELAY_REASONS[$d['reason']] ?? $d['reason'])?><?php if ((int)$d['is_expediting']===1): ?> <span class="badge" title="Expediting delay detail lives in Phase 6">↔ Phase 6</span><?php endif; ?></td><td><?=$esc(TOSRM_DELAY_RESP[$d['responsibility']] ?? $d['responsibility'])?></td><td><?=$esc(trim($d['impact'].' '.$d['action']))?></td></tr>
          <?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
        <?php if ($canEdit && $jobs): ?>
        <form method="post" action="/delay-add" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;align-items:center">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>">
          <select name="job_id"><?php foreach ($jobs as $j): ?><option value="<?=(int)$j['id']?>"><?=$esc($j['job_code'])?><?=$j['scheduled_date']?' · '.$esc($j['scheduled_date']):''?></option><?php endforeach; ?></select>
          <select name="reason"><?php foreach (TOSRM_DELAY_REASONS as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
          <select name="responsibility"><?php foreach (TOSRM_DELAY_RESP as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
          <input type="text" name="impact" placeholder="Impact / action" style="flex:1;min-width:160px">
          <button class="btn btn-sm" type="submit">Record delay</button>
        </form>
        <div class="muted" style="font-size:12px">Delay days auto-computed from required vs scheduled; expediting delays link to Phase 6 rather than duplicating it.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    echo ob_get_clean();
}

// ---------------------------------------------------------------------------
//  Handlers.
// ---------------------------------------------------------------------------
function ops_tosrm_delay_action($route, $method) {
    $jobId = (int)($_POST['job_id'] ?? 0);
    $job = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
    $back = $job ? '/call?id=' . (int)$job['call_id'] . '#sla' : '/calls';
    if ($method !== 'POST') redirect($back);
    if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
    ops_require(tosrm_can_edit(), 'You do not have permission to record a delay.');
    if (!$job) { flash('Job not found.', 'error'); redirect('/calls'); }
    tosrm_delay_add($jobId, ['reason'=>$_POST['reason'] ?? '', 'category'=>$_POST['category'] ?? '', 'responsibility'=>$_POST['responsibility'] ?? '', 'impact'=>$_POST['impact'] ?? '', 'action'=>$_POST['action'] ?? '']);
    flash('Delay recorded.');
    redirect($back);
}

// Admin — SLA targets, recurring services, capacity outlook.
function ops_tosrm_admin($route, $method) {
    ops_require((function_exists('can') && can('mod.calls.edit')) || (function_exists('is_coordinator_level') && is_coordinator_level()) || (function_exists('is_master') && is_master()), 'You do not have access to this screen.');
    tosrm_migrate_d();
    if ($route === 'sla-targets') {
        if ($method === 'POST' && csrf_ok($_POST['_csrf'] ?? '')) {
            if (($_POST['do'] ?? '') === 'del') tosrm_sla_delete((int)($_POST['id'] ?? 0));
            else tosrm_sla_set((int)($_POST['client_id'] ?? 0), (string)($_POST['stage'] ?? ''), (int)($_POST['target_days'] ?? 0), (string)($_POST['note'] ?? ''));
            flash('SLA targets updated.'); redirect('/sla-targets');
        }
        $rows = ops_all("SELECT s.*, bp.display_name client_name FROM sla_targets s LEFT JOIN business_partners bp ON bp.id=s.client_id ORDER BY s.client_id, s.stage") ?: [];
        $clients = function_exists('clients_list') ? clients_list() : (ops_all("SELECT id, display_name FROM business_partners WHERE COALESCE(is_client,0)=1 ORDER BY display_name") ?: []);
        view('ops/sla_targets', ['rows'=>$rows, 'clients'=>$clients, 'stages'=>TOSRM_SLA_STAGES]);
        return true;
    }
    if ($route === 'recurring') {
        if ($method === 'POST' && csrf_ok($_POST['_csrf'] ?? '')) {
            $do = $_POST['do'] ?? 'new';
            if ($do === 'toggle') { $r = ops_one("SELECT active FROM recurring_services WHERE id=?", [(int)($_POST['id'] ?? 0)]); if ($r) db()->prepare("UPDATE recurring_services SET active=? WHERE id=?")->execute([$r['active'] ? 0 : 1, (int)$_POST['id']]); flash('Schedule updated.'); }
            elseif ($do === 'run') { $n = tosrm_run_recurring(); flash($n > 0 ? "$n call(s) generated from due schedules." : 'Nothing was due to generate.'); }
            else {
                tosrm_recurring_create(['client_id'=>(int)($_POST['client_id'] ?? 0), 'title'=>(string)($_POST['title'] ?? ''),
                    'frequency'=>(string)($_POST['frequency'] ?? 'MONTHLY'), 'interval_n'=>(int)($_POST['interval_n'] ?? 1),
                    'start_date'=>(string)($_POST['start_date'] ?? date('Y-m-d')), 'end_date'=>(string)($_POST['end_date'] ?? ''),
                    'template'=>['inspection_type'=>(string)($_POST['inspection_type'] ?? ''), 'sbu'=>(string)($_POST['sbu'] ?? ''),
                                 'deliverables'=>(string)($_POST['deliverables'] ?? ''), 'vendor_id'=>(int)($_POST['vendor_id'] ?? 0),
                                 'site_address_id'=>(int)($_POST['site_address_id'] ?? 0), 'executing_office_id'=>(int)($_POST['executing_office_id'] ?? 0)]]);
                flash('Recurring schedule created.');
            }
            redirect('/recurring');
        }
        $rows = ops_all("SELECT r.*, bp.display_name client_name FROM recurring_services r LEFT JOIN business_partners bp ON bp.id=r.client_id ORDER BY r.active DESC, r.next_run") ?: [];
        $clients = function_exists('clients_list') ? clients_list() : [];
        view('ops/recurring', ['rows'=>$rows, 'clients'=>$clients, 'freq'=>TOSRM_RECUR_FREQ, 'itypes'=>INSPECTION_TYPES]);
        return true;
    }
    if ($route === 'capacity-outlook') {
        $offices = function_exists('availability_scope_offices') ? availability_scope_offices() : 'ALL';
        $from = trim((string)($_GET['from'] ?? '')) ?: date('Y-m-d');
        $to   = trim((string)($_GET['to'] ?? '')) ?: date('Y-m-d', strtotime($from . ' +55 days'));
        $buckets = tosrm_capacity_horizon($offices, $from, $to);
        view('ops/capacity_outlook', ['buckets'=>$buckets, 'from'=>$from, 'to'=>$to]);
        return true;
    }
    return false;
}

// ===========================================================================
//  SLICE E — Operations surfaces: the ops desk, backlog + registers, the comms
//  log on a call/job, the operational-event → Phase 8 issue link, rule-based
//  data-quality flags and an optional (advisory) AI operations summary.
//  Everything REUSES existing engines — the activity spine (lib/activity.php),
//  the Phase-8 issue creator (ncr_create), the availability engine and the AI
//  seam (lib/ai.php). No second CRM, NCR, report builder or scheduler.
// ===========================================================================

// Office scope predicate for the calls table (respects the user's branch scope).
function tosrm_office_scope() {
    if (function_exists('availability_scope_offices')) { $o = availability_scope_offices(); if ($o !== 'ALL' && is_array($o)) return $o; return 'ALL'; }
    if (function_exists('scope_offices')) { $o = scope_offices(); return is_array($o) ? $o : 'ALL'; }
    return 'ALL';
}
function tosrm_call_office_sql($offices, $alias = 'c') {
    if (!is_array($offices)) return '1=1';
    if (!$offices) return '1=0';
    $in = implode(',', array_map('intval', $offices));
    return "($alias.executing_office_id IN ($in) OR $alias.executing_office_id IS NULL)";
}

// ---- Ops desk metrics ------------------------------------------------------
function tosrm_ops_metrics($offices = 'ALL') {
    tosrm_migrate_d();
    $w = tosrm_call_office_sql($offices, 'c');
    $today = date('Y-m-d');
    $one = fn($sql, $a = []) => (int)(ops_one($sql, $a)['n'] ?? 0);
    $m = [];
    $m['unscheduled'] = count(function_exists('sched_unallocated_calls') ? sched_unallocated_calls($offices, 999) : []);
    $m['clarification'] = $one("SELECT COUNT(DISTINCT c.id) n FROM calls c JOIN call_clarifications cc ON cc.call_id=c.id AND cc.status='OPEN' WHERE $w");
    $m['ready'] = $one("SELECT COUNT(*) n FROM calls c WHERE c.op_status='READY_TO_SCHEDULE' AND $w");
    $m['new'] = $one("SELECT COUNT(*) n FROM calls c WHERE (c.op_status IN ('RECEIVED','DRAFT','UNDER_REVIEW') OR (COALESCE(c.op_status,'')='' AND c.status='OPEN')) AND $w");
    $m['today'] = $one("SELECT COUNT(*) n FROM jobs j JOIN calls c ON c.id=j.call_id WHERE j.scheduled_date=? AND $w", [$today]);
    $m['overdue'] = $one("SELECT COUNT(*) n FROM calls c WHERE c.status<>'CLOSED' AND COALESCE(c.op_status,'')<>'CLOSED' AND c.inspection_required_date<>'' AND c.inspection_required_date<? AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.call_id=c.id AND j.closed_flag=1) AND $w", [$today]);
    // NOTE (R10): jobs.stage is vestigial (never advanced past the default/CANCELLED),
    // so this count is effectively always 0. Left as-is to avoid a silent behaviour
    // change; drive it from a real signal (closed job + report_approval='PENDING') when
    // this metric is actually needed.
    $m['report_pending'] = $one("SELECT COUNT(*) n FROM jobs j JOIN calls c ON c.id=j.call_id WHERE j.stage='REPORT_PENDING' AND $w");
    $m['critical'] = $one("SELECT COUNT(*) n FROM calls c WHERE c.priority='CRITICAL' AND c.status<>'CLOSED' AND $w");
    return $m;
}

// ---- Backlog (received → not yet done), oldest / most-urgent first ---------
function tosrm_ops_backlog($offices = 'ALL', $limit = 100) {
    tosrm_migrate_d();
    $w = tosrm_call_office_sql($offices, 'c');
    $today = date('Y-m-d');
    $rows = ops_all("SELECT c.id, c.call_code, c.op_status, c.status, c.priority, c.inspection_type,
                            c.call_received_date, c.inspection_required_date,
                            bp.display_name client_name,
                            (SELECT COUNT(*) FROM call_clarifications cc WHERE cc.call_id=c.id AND cc.status='OPEN') open_clar,
                            (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id AND j.inspector_id IS NOT NULL) has_res
                     FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
                     WHERE c.status<>'CLOSED' AND COALESCE(c.op_status,'')<>'CLOSED' AND COALESCE(c.op_status,'')<>'CANCELLED'
                       -- Field-finding #25 — a call already allocated AND scheduled for a FUTURE date is in
                       -- hand (it shows under the Schedule / Assignment registers), not backlog. The backlog
                       -- is work needing attention: unscheduled, unassigned, awaiting clarification, or
                       -- overdue. Overdue (scheduled in the past) and today's stay; only future-scheduled,
                       -- assigned work is excluded here.
                       AND NOT EXISTS (SELECT 1 FROM jobs j2 WHERE j2.call_id=c.id
                                       AND j2.inspector_id IS NOT NULL AND COALESCE(j2.closed_flag,0)=0
                                       AND COALESCE(j2.scheduled_date,'')<>'' AND j2.scheduled_date > '$today')
                       AND $w
                     ORDER BY CASE WHEN c.inspection_required_date='' THEN 1 ELSE 0 END, c.inspection_required_date, c.id
                     LIMIT " . (int)$limit) ?: [];
    foreach ($rows as &$r) {
        $r['age_days'] = ($r['call_received_date'] ?? '') !== '' && function_exists('days_between') ? days_between($r['call_received_date'], $today) : null;
        $r['overdue'] = ($r['inspection_required_date'] ?? '') !== '' && $r['inspection_required_date'] < $today;
        $r['pending_reason'] = (int)$r['open_clar'] > 0 ? 'Clarification open'
            : ((int)$r['has_res'] === 0 ? 'No resource assigned' : (($r['op_status'] ?: 'RECEIVED')));
    }
    return $rows;
}

// ---- Registers -------------------------------------------------------------
function tosrm_schedule_register($offices = 'ALL', $from = null, $to = null) {
    tosrm_migrate_d();
    $from = $from ?: date('Y-m-d'); $to = $to ?: date('Y-m-d', strtotime($from . ' +13 days'));
    $w = tosrm_call_office_sql($offices, 'c');
    return ops_all("SELECT j.id job_id, j.job_code, j.scheduled_date, j.stage, j.assign_state, j.accept_state,
                           c.id call_id, c.call_code, c.inspection_type, bp.display_name client_name,
                           i.name inspector_name
                    FROM jobs j JOIN calls c ON c.id=j.call_id
                    LEFT JOIN business_partners bp ON bp.id=c.client_id
                    LEFT JOIN inspectors i ON i.id=j.inspector_id
                    WHERE j.scheduled_date>=? AND j.scheduled_date<=? AND $w
                    ORDER BY j.scheduled_date, j.id", [$from, $to]) ?: [];
}
function tosrm_assignment_register($offices = 'ALL', $limit = 200) {
    tosrm_migrate_d();
    $w = tosrm_call_office_sql($offices, 'c');
    return ops_all("SELECT j.id job_id, j.job_code, j.scheduled_date, j.assign_state, j.accept_state, j.accept_reason,
                           c.call_code, c.inspection_type, bp.display_name client_name, i.name inspector_name
                    FROM jobs j JOIN calls c ON c.id=j.call_id
                    LEFT JOIN business_partners bp ON bp.id=c.client_id
                    LEFT JOIN inspectors i ON i.id=j.inspector_id
                    WHERE j.inspector_id IS NOT NULL AND $w
                    ORDER BY j.id DESC LIMIT " . (int)$limit) ?: [];
}

// ---- Operational-event → Phase 8 issue (REUSES ncr_create) -----------------
function tosrm_issue_from_event($jobId, $title, $description = '', $severity = 'MINOR') {
    if (!function_exists('ncr_create')) return 0;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]);
    try {
        $id = ncr_create([
            'job_id' => (int)$jobId, 'source' => 'INTERNAL', 'source_note' => 'Operations (Phase 9) event',
            'title' => substr((string)$title, 0, 255), 'description' => (string)$description,
            'severity' => in_array($severity, ['MAJOR','MINOR','OBSERVATION'], true) ? $severity : 'MINOR',
            'partner_id' => (int)($job['client_id'] ?? 0) ?: 0,
        ]);
        return is_numeric($id) ? (int)$id : 1;
    } catch (Throwable $e) { return 0; }
}

// ---- Rule-based data-quality flags (advisory; no AI needed) -----------------
function tosrm_ops_dataquality($offices = 'ALL', $limit = 60) {
    $w = tosrm_call_office_sql($offices, 'c');
    $rows = ops_all("SELECT c.* FROM calls c WHERE c.status<>'CLOSED' AND $w ORDER BY c.id DESC LIMIT " . (int)$limit) ?: [];
    $out = [];
    foreach ($rows as $c) {
        $missing = tosrm_validate_call($c);
        $overridden = trim((string)($c['val_override_reason'] ?? '')) !== '';
        if ($missing && !$overridden) $out[] = ['call_id'=>(int)$c['id'], 'call_code'=>$c['call_code'], 'flags'=>$missing];
    }
    return $out;
}

// ---- Operations summary (deterministic; AI-wrapped only when enabled) -------
function tosrm_ops_summary($metrics) {
    $base = sprintf('Backlog: %d unscheduled, %d awaiting clarification, %d ready to schedule. %d overdue, %d critical, %d report(s) pending. %d service(s) today.',
        $metrics['unscheduled'], $metrics['clarification'], $metrics['ready'], $metrics['overdue'], $metrics['critical'], $metrics['report_pending'], $metrics['today']);
    // AI may PHRASE it more helpfully, but never invents numbers and never acts.
    if (function_exists('ai_enabled') && ai_enabled() && function_exists('ai_chat')) {
        try {
            $sys = 'You are an operations assistant for a third-party inspection agency. Summarise the day\'s workload in 2-3 short sentences for a coordinator. Use ONLY the figures given. Do not invent data, do not assign or schedule anyone, do not make decisions.';
            $r = ai_chat($sys, $base, 200);
            if (is_string($r) && trim($r) !== '') return ['text'=>trim($r), 'ai'=>true];
        } catch (Throwable $e) {}
    }
    return ['text'=>$base, 'ai'=>false];
}

// ---- Comms log panel (REUSES the activity spine) ---------------------------
function tosrm_render_comms($entityKind, $entityId, $backAnchor = 'comms') {
    if (!function_exists('act_for_entity') || !function_exists('act_log')) return;
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $rows = act_for_entity($entityKind, (int)$entityId, 50) ?: [];
    $canEdit = tosrm_can_edit();
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $kinds = defined('ACT_KINDS') ? ACT_KINDS : ['NOTE'=>'Note','CALL'=>'Call','EMAIL'=>'Email','WHATSAPP'=>'WhatsApp','MEETING'=>'Meeting'];
    ob_start(); ?>
    <div class="card tosrm-comms" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Communication log</h3>
      <p class="muted" style="font-size:13px;margin:0 0 12px">The significant calls, e-mails and meetings on this <?= function_exists('Tl') ? e(Tl('job')) : 'job' ?> — this does not replace e-mail or WhatsApp.</p>
      <?php if ($rows): ?>
      <table class="grid" style="width:100%">
        <thead><tr><th>When</th><th>Kind</th><th>Subject</th><th>By</th></tr></thead>
        <tbody><?php foreach ($rows as $a): ?>
          <tr><td><?=$esc(substr((string)($a['occurred_at'] ?? $a['created_at'] ?? ''),0,16))?></td>
              <td><span class="pill p-mut"><?=$esc($kinds[$a['kind']] ?? $a['kind'])?></span></td>
              <td><?=$esc($a['subject'])?><?php if (trim((string)($a['body']??''))!==''): ?><br><span class="muted"><?=$esc($a['body'])?></span><?php endif; ?></td>
              <td><?=$esc($a['owner'] ?: $a['created_by'])?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
      <?php else: ?><p class="muted" style="margin:0 0 4px">Nothing logged yet.</p><?php endif; ?>
      <?php if ($canEdit): ?>
      <form method="post" action="/comm-add" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px">
        <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>">
        <input type="hidden" name="entity_kind" value="<?=$esc($entityKind)?>"><input type="hidden" name="entity_id" value="<?=(int)$entityId?>">
        <select class="form-control" name="kind" style="width:auto"><?php foreach ($kinds as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
        <input class="form-control" type="text" name="subject" placeholder="What was communicated?" style="flex:1 1 220px;min-width:0" required>
        <input class="form-control" type="text" name="body" placeholder="Detail (optional)" style="flex:1 1 160px;min-width:0">
        <button class="btn" type="submit">Log</button>
      </form>
      <?php endif; ?>
    </div>
    <?php echo ob_get_clean();
}

// ---------------------------------------------------------------------------
//  Handlers.
// ---------------------------------------------------------------------------
function tosrm_ops_desk_can() {
    return (function_exists('can') && (can('dash.operations') || can('mod.calls.view') || can('mod.jobs.view')))
        || (function_exists('is_coordinator_level') && is_coordinator_level())
        || (function_exists('is_master') && is_master());
}
function ops_tosrm_desk($method) {
    ops_require(tosrm_ops_desk_can(), 'You do not have access to the operations desk.');
    tosrm_migrate_d();
    $offices = tosrm_office_scope();
    $metrics = tosrm_ops_metrics($offices);
    $from = trim((string)($_GET['from'] ?? '')) ?: date('Y-m-d');
    $to   = trim((string)($_GET['to'] ?? '')) ?: date('Y-m-d', strtotime($from . ' +13 days'));
    view('ops/ops_desk', [
        'metrics' => $metrics, 'backlog' => tosrm_ops_backlog($offices, 60),
        'schedule' => tosrm_schedule_register($offices, $from, $to),
        'assignments' => tosrm_assignment_register($offices, 60),
        'dataquality' => tosrm_ops_dataquality($offices, 40),
        'summary' => tosrm_ops_summary($metrics), 'from' => $from, 'to' => $to,
    ]);
    return true;
}
// ---------------------------------------------------------------------------
//  OPERATIONS HOME — the area landing that replaces the folding "Operations"
//  menu group. Every screen that used to hide inside that dropdown is laid out
//  on one page, grouped, with its live state. Nothing is removed.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  Disruptions & changes — a roll-up of the churn already logged on jobs:
//  client-driven cancellations, executing-office cancellations, engineer
//  changes (reassignments) and no-shows, each with its recorded reason.
//  Scoped to the caller's branches and, by default, the current financial year.
// ---------------------------------------------------------------------------
function tosrm_disruptions($offices = null, $fromDate = '', $toDate = '') {
    tosrm_migrate_b();
    $out = ['client_cancel'=>0,'office_cancel'=>0,'engineer_change'=>0,'noshow'=>0,'from'=>$fromDate,'to'=>$toDate,'recent'=>[]];
    $where = '1=1'; $args = [];
    if (is_array($offices)) {
        if (!$offices) return $out;                    // scoped to no branch → nothing
        $in = implode(',', array_fill(0, count($offices), '?'));
        $where .= " AND j.executing_office_id IN ($in)";
        foreach ($offices as $o) $args[] = (int)$o;
    }
    if ($fromDate !== '') { $where .= " AND e.at >= ?"; $args[] = $fromDate; }
    if ($toDate   !== '') { $where .= " AND e.at <= ?"; $args[] = $toDate . ' 23:59:59'; }
    try {
        $rows = ops_all("SELECT e.kind, e.party, e.reason, e.at, e.actor, e.old_inspector_id, e.new_inspector_id,
                                j.id job_id, j.job_code
                         FROM assignment_events e JOIN jobs j ON j.id=e.job_id
                         WHERE $where AND e.kind IN ('CANCEL','REASSIGN','NOSHOW')
                         ORDER BY e.at DESC", $args) ?: [];
    } catch (Throwable $ex) { return $out; }
    foreach ($rows as $r) {
        if ($r['kind'] === 'REASSIGN') $out['engineer_change']++;
        elseif ($r['kind'] === 'NOSHOW') $out['noshow']++;
        elseif ($r['kind'] === 'CANCEL') {
            $p = strtoupper((string)($r['party'] ?? ''));
            if ($p === 'CLIENT') $out['client_cancel']++;
            elseif ($p === 'OFFICE') $out['office_cancel']++;
        }
    }
    $out['recent'] = array_slice($rows, 0, 8);
    return $out;
}

// A date-range key → [from, to, label]. Used by the disruptions filter chips.
function tosrm_disruptions_range($key) {
    $key = preg_replace('/[^a-z]/', '', strtolower((string)$key));
    switch ($key) {
        case 'month':     return [date('Y-m-01'), date('Y-m-d'), 'this month'];
        case 'lastmonth': $f = date('Y-m-01', strtotime('first day of last month'));
                          return [$f, date('Y-m-t', strtotime($f)), 'last month'];
        case 'all':       return ['', '', 'all time'];
        case 'fy':
        default:          if (function_exists('current_fy') && function_exists('fy_range')) { $r = fy_range(current_fy()); return [$r[0] ?? '', $r[1] ?? '', 'this financial year']; }
                          return [date('Y-01-01'), date('Y-m-d'), 'this year'];
    }
}
const TOSRM_DISRUPT_RANGES = ['month'=>'This month','lastmonth'=>'Last month','fy'=>'This FY','all'=>'All time'];

// Self-contained card (tiles + recent table) — reused on the Operations
// dashboard and the management (Analytics) dashboards.
function tosrm_disruptions_html($d, $rangeLabel = '') {
    if (!$d) return '';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $any = ((int)$d['client_cancel'] + (int)$d['office_cancel'] + (int)$d['engineer_change'] + (int)$d['noshow']) > 0 || !empty($d['recent']);
    static $css = false; $h = '';
    if (!$css) { $css = true; $h .= '<style>'
        . '.dz-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:0 0 12px}'
        . '.dz{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-left:4px solid var(--muted,#94a3b8);border-radius:12px;padding:12px 14px}'
        . '.dz .v{font-size:24px;font-weight:700;line-height:1.1}.dz .l{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a);margin-top:2px}.dz .h{font-size:11px;color:#9aa3af;margin-top:3px}'
        . '.dz.bad{border-left-color:var(--bad,#dc2626)}.dz.bad .v{color:var(--bad,#dc2626)}'
        . '.dz.warn{border-left-color:var(--warn,#d97706)}.dz.warn .v{color:var(--warn,#d97706)}'
        . '@media(max-width:820px){.dz-row{grid-template-columns:repeat(2,1fr)}}'
        . '</style>'; }
    $h .= '<div class="dz-row">'
        . '<div class="dz bad"><div class="v">'  . (int)$d['client_cancel']   . '</div><div class="l">Client cancelled</div><div class="h">the call was called off</div></div>'
        . '<div class="dz warn"><div class="v">' . (int)$d['engineer_change'] . '</div><div class="l">Engineer changes</div><div class="h">reassigned to another</div></div>'
        . '<div class="dz bad"><div class="v">'  . (int)$d['office_cancel']   . '</div><div class="l">Office cancelled</div><div class="h">executing branch pulled it</div></div>'
        . '<div class="dz warn"><div class="v">' . (int)$d['noshow']          . '</div><div class="l">No-shows</div><div class="h">client / vendor no-show</div></div>'
        . '</div>';
    if (!empty($d['recent'])) {
        $jobHdr = function_exists('TH') ? TH('job') : 'Job';
        $h .= '<div class="panel" style="padding:0;overflow-x:auto"><table class="grid" style="width:100%;margin:0"><thead><tr><th>When</th><th>' . $e($jobHdr) . '</th><th>What happened</th><th>By whom</th><th>Reason recorded</th></tr></thead><tbody>';
        foreach ($d['recent'] as $r) {
            $kind = $r['kind']; $party = strtoupper((string)($r['party'] ?? ''));
            if ($kind === 'REASSIGN')      { $label = 'Engineer changed'; $tone = 'p-warn'; $who = 'Coordinator'; }
            elseif ($kind === 'NOSHOW')    { $label = 'No-show';          $tone = 'p-warn'; $who = TOSRM_NOSHOW_PARTIES[$party] ?? '—'; }
            elseif ($party === 'CLIENT')   { $label = 'Client cancelled'; $tone = 'p-bad';  $who = 'Client'; }
            elseif ($party === 'OFFICE')   { $label = 'Office cancelled'; $tone = 'p-bad';  $who = 'Executing office'; }
            else                           { $label = 'Cancelled';        $tone = 'p-mut';  $who = TOSRM_CANCEL_PARTIES[$party] ?? '—'; }
            $h .= '<tr><td class="muted" style="white-space:nowrap">' . $e(substr((string)$r['at'], 0, 10)) . '</td>'
                . '<td><a href="/job?id=' . (int)$r['job_id'] . '">' . $e($r['job_code'] ?: ('#' . (int)$r['job_id'])) . '</a></td>'
                . '<td><span class="pill ' . $tone . '">' . $e($label) . '</span></td><td>' . $e($who) . '</td>'
                . '<td class="muted">' . $e($r['reason'] ?: '—') . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
    } elseif (!$any) {
        $h .= '<p class="muted" style="margin:8px 2px 0">No cancellations, engineer changes or no-shows recorded' . ($rangeLabel ? ' ' . $e($rangeLabel) : '') . ' — a clean run.</p>';
    }
    return $h;
}

// ===========================================================================
//  CROSS-OFFICE CALL TRACKING  (contracting office ↔ executing office)
//
//  A call is RAISED by the CONTRACTING office (ibo_office_id — it owns the
//  client / PO). When the field work belongs to another branch, the call is
//  FORWARDED to an EXECUTING office (executing_office_id). The rule is firm and
//  already enforced by call_can_allocate():
//    · only the EXECUTING office allocates the engineer;
//    · the CONTRACTING office may WATCH and CHASE, never allocate.
//  Both offices therefore need to see the SAME call so nobody loses the TAT
//  clock. This gives each side its own live view of the forwarded call:
//    · executing office  → "Inbound — calls to allocate" (Allocate button)
//    · contracting office → "Sent out — calls we're tracking" (Nudge only)
//  and, once a forwarded call sits unallocated past its response target, an
//  automatic escalation flag to the EXECUTING office's manager.
// ===========================================================================

// Default days a forwarded call may sit unallocated before it escalates to the
// executing office's manager. A client SLA "SCHEDULING" target overrides this.
const TOSRM_XO_ESCALATE_DAYS = 2;

function tosrm_xo_migrate() {
    static $done = false; if ($done) return; $done = true;
    tosrm_migrate();
    // Stamped the first time a forwarded call is auto-escalated to the executing
    // office's manager, so the mail goes out once and not on every scan.
    if (function_exists('ensure_column')) ensure_column('calls', 'xo_escalated_at', "VARCHAR(30) DEFAULT ''");
    $pk = pk_clause();
    // A nudge = a reminder the contracting office sends the executing office to
    // act on a forwarded call. Kept as its own log so we can show "last chased".
    db()->exec("CREATE TABLE IF NOT EXISTS call_nudges (
        id $pk, call_id INT DEFAULT 0, from_office_id INT DEFAULT 0,
        to_office_id INT DEFAULT 0, note VARCHAR(600) DEFAULT '',
        actor VARCHAR(120) DEFAULT '', at VARCHAR(30) DEFAULT '')");
}

// Per-call TAT snapshot for the cross-office view: where the call is on the
// Received → Forwarded → Allocated → Scheduled chain, how long it has waited,
// and whether it has crossed the escalation threshold.
function tosrm_xo_tat($r) {
    $today = date('Y-m-d');
    $recv = substr((string)($r['call_received_date'] ?? ''), 0, 10);
    $fwd  = substr((string)($r['forwarded_at'] ?? ''), 0, 10);
    $sched = substr((string)($r['sched_date'] ?? ''), 0, 10);
    $reqd  = substr((string)($r['inspection_required_date'] ?? ''), 0, 10);
    $alloc = (int)($r['alloc_n'] ?? 0) > 0;
    $db = fn($a, $b) => ($a !== '' && $b !== '' && function_exists('days_between')) ? days_between($a, $b) : null;
    if ($sched !== '')   { $stage = 'Scheduled'; $tone = 'ok'; }
    elseif ($alloc)      { $stage = 'Allocated'; $tone = 'ok'; }
    elseif ($fwd !== '') { $stage = 'Forwarded'; $tone = 'warn'; }
    else                 { $stage = 'Received';  $tone = 'mut'; }
    // Waiting = days unallocated since forwarded (fallback received).
    $waitFrom = $fwd !== '' ? $fwd : $recv;
    $waiting = (!$alloc && $waitFrom !== '') ? $db($waitFrom, $today) : null;
    $thr = TOSRM_XO_ESCALATE_DAYS;
    if (function_exists('tosrm_sla_targets')) { $t = tosrm_sla_targets((int)($r['client_id'] ?? 0)); if (!empty($t['SCHEDULING'])) $thr = (int)$t['SCHEDULING']; }
    $overdueReqd = ($reqd !== '' && !$alloc && $reqd < $today);
    $escalated = (!$alloc) && (($waiting !== null && $waiting > $thr) || $overdueReqd);
    return [
        'recv' => $recv, 'fwd' => $fwd, 'sched' => $sched, 'reqd' => $reqd, 'alloc' => $alloc,
        'stage' => $stage, 'tone' => $tone, 'waiting' => $waiting, 'thr' => $thr,
        'escalated' => $escalated, 'overdueReqd' => $overdueReqd,
        'recv_to_fwd'   => $db($recv, $fwd),
        'fwd_to_sched'  => $db($fwd !== '' ? $fwd : $recv, $sched),
    ];
}

// The forwarded (cross-office) calls that touch the caller's branches, split
// into what my office must ALLOCATE (inbound) and what my office is TRACKING
// (sent out). $offices is 'ALL' (head office) or an int[] of branch ids.
function tosrm_xo_calls($offices, $limit = 120) {
    tosrm_xo_migrate();
    $base = "c.status<>'CLOSED' AND COALESCE(c.op_status,'') NOT IN ('CLOSED','CANCELLED')
             AND COALESCE(c.executing_office_id,0)>0 AND COALESCE(c.ibo_office_id,0)>0
             AND c.executing_office_id <> c.ibo_office_id";
    try {
        $rows = ops_all("SELECT c.id, c.call_code, c.ibo_office_id, c.executing_office_id, c.client_id,
                                c.call_received_date, c.forwarded_at, c.inspection_required_date,
                                c.inspection_type, c.priority, c.op_status, c.status, c.xo_escalated_at,
                                bp.display_name client_name,
                                io.name ibo_name, io.manager_name ibo_mgr,
                                eo.name exec_name, eo.coordinator_name exec_coord,
                                eo.coordinator_email exec_coord_email, eo.manager_name exec_mgr,
                                eo.manager_email exec_mgr_email,
                                (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id AND j.inspector_id IS NOT NULL) alloc_n,
                                (SELECT MIN(j.scheduled_date) FROM jobs j WHERE j.call_id=c.id AND COALESCE(j.scheduled_date,'')<>'') sched_date,
                                (SELECT MAX(n.at) FROM call_nudges n WHERE n.call_id=c.id) last_nudge,
                                (SELECT COUNT(*) FROM call_nudges n WHERE n.call_id=c.id) nudge_n
                         FROM calls c
                         LEFT JOIN business_partners bp ON bp.id=c.client_id
                         LEFT JOIN offices io ON io.id=c.ibo_office_id
                         LEFT JOIN offices eo ON eo.id=c.executing_office_id
                         WHERE $base
                         ORDER BY CASE WHEN c.inspection_required_date='' THEN 1 ELSE 0 END,
                                  c.inspection_required_date, c.id
                         LIMIT " . (int)$limit) ?: [];
    } catch (Throwable $e) { return ['inbound' => [], 'sentout' => [], 'all_scope' => false]; }
    $inScope = fn($o) => $offices === 'ALL' || (is_array($offices) && in_array((int)$o, array_map('intval', $offices), true));
    $inbound = []; $sentout = [];
    foreach ($rows as $r) {
        $r['tat'] = tosrm_xo_tat($r);
        if ($inScope($r['executing_office_id'])) $inbound[] = $r;   // my office must allocate
        if ($inScope($r['ibo_office_id']))       $sentout[] = $r;   // my office is tracking
    }
    return ['inbound' => $inbound, 'sentout' => $sentout, 'all_scope' => ($offices === 'ALL')];
}

// Every call MY branch still has to put an engineer on — same-office AND
// cross-office inbound together, so this one panel answers "what needs
// scheduling". A call is pending when it is open and no job carries a date yet.
// Scope: calls this branch executes (or raised with no executing branch set yet).
function tosrm_pending_scheduling($offices, $limit = 200) {
    $w = ["c.status<>'CLOSED'",
          "COALESCE(c.op_status,'') NOT IN ('CLOSED','CANCELLED')",
          "NOT EXISTS (SELECT 1 FROM jobs j WHERE j.call_id=c.id AND COALESCE(j.scheduled_date,'')<>'')"];
    if (is_array($offices)) {
        if (!$offices) return [];
        $in = implode(',', array_map('intval', $offices));
        $w[] = "(c.executing_office_id IN ($in) OR (COALESCE(c.executing_office_id,0)=0 AND c.ibo_office_id IN ($in)))";
    }
    try {
        return ops_all("SELECT c.id, c.call_code, c.client_id, c.inspection_type, c.sbu,
                               c.call_received_date, c.inspection_required_date,
                               c.executing_office_id, c.ibo_office_id,
                               COALESCE(NULLIF(bp.display_name,''), bp.legal_name) client_name,
                               eo.name exec_name
                        FROM calls c
                        LEFT JOIN business_partners bp ON bp.id=c.client_id
                        LEFT JOIN offices eo ON eo.id=c.executing_office_id
                        WHERE " . implode(' AND ', $w) . "
                        ORDER BY CASE WHEN c.inspection_required_date='' THEN 1 ELSE 0 END,
                                 c.inspection_required_date, c.id
                        LIMIT " . (int)$limit) ?: [];
    } catch (Throwable $e) { return []; }
}

// Record + notify a nudge from the contracting office to the executing office.
function tosrm_xo_nudge($callId, $note = '') {
    tosrm_xo_migrate();
    $c = ops_one("SELECT c.*, eo.name exec_name, eo.coordinator_name exec_coord,
                         eo.coordinator_email exec_coord_email, eo.manager_email exec_mgr_email,
                         io.name ibo_name
                  FROM calls c LEFT JOIN offices eo ON eo.id=c.executing_office_id
                  LEFT JOIN offices io ON io.id=c.ibo_office_id WHERE c.id=?", [(int)$callId]);
    if (!$c) return false;
    $note = trim((string)$note);
    if ($note === '') $note = 'Please allocate and schedule this ' . (function_exists('Tl') ? Tl('call') : 'call') . ' — TAT is running.';
    db()->prepare("INSERT INTO call_nudges (call_id, from_office_id, to_office_id, note, actor, at)
                   VALUES (?,?,?,?,?,?)")
        ->execute([(int)$callId, (int)($c['ibo_office_id'] ?? 0), (int)($c['executing_office_id'] ?? 0),
                   substr($note, 0, 600), tosrm_actor(), tosrm_now()]);
    if (function_exists('act_log')) act_log('CALL', (int)$callId, 'SYSTEM',
        'Nudge sent to ' . ($c['exec_name'] ?: 'executing office') . ' — ' . $note, ['auto' => 1]);
    // A courtesy e-mail to the executing coordinator (cc its manager) if we have one.
    $to = trim((string)($c['exec_coord_email'] ?? ''));
    $cc = trim((string)($c['exec_mgr_email'] ?? ''));
    if ($to !== '' && function_exists('ops_mail')) {
        $subj = 'Reminder: allocate ' . ($c['call_code'] ?? ('call #' . (int)$callId)) . ' (' . ($c['ibo_name'] ?: 'contracting office') . ')';
        $body = ($c['exec_coord'] ?: 'Team') . ",\n\n" . $note . "\n\nCall: " . ($c['call_code'] ?? ('#' . (int)$callId))
              . "\nRaised by: " . ($c['ibo_name'] ?: '—') . "\nRequired by: " . (substr((string)($c['inspection_required_date'] ?? ''), 0, 10) ?: '—')
              . "\n\n— " . tosrm_actor();
        try { ops_mail($to, $subj, $body, $cc, 'nudge'); } catch (Throwable $e) {}
    }
    return true;
}

// Automatic escalation: e-mail the EXECUTING office's manager the first time a
// forwarded call has sat unallocated past its response target (SLA SCHEDULING
// target, else TOSRM_XO_ESCALATE_DAYS). Idempotent — each call is mailed once
// (xo_escalated_at is stamped), so it is safe to run from cron AND lazily when
// someone opens Operations. $offices limits the sweep ('ALL' or int[]).
function tosrm_xo_escalate_scan($offices = 'ALL', $today = null) {
    tosrm_xo_migrate();
    $xo = tosrm_xo_calls($offices, 300);
    // Union of both lists (a call can be inbound for one branch, sent-out for
    // another); de-duplicate on call id so we never mail twice in one sweep.
    $seen = []; $sent = 0;
    foreach (array_merge($xo['inbound'] ?? [], $xo['sentout'] ?? []) as $r) {
        $cid = (int)$r['id'];
        if (isset($seen[$cid])) continue; $seen[$cid] = true;
        $t = $r['tat'];
        if (empty($t['escalated']) || !empty($t['alloc'])) continue;      // only unallocated + overdue
        if (trim((string)($r['xo_escalated_at'] ?? '')) !== '') continue;  // already mailed
        $mgr = trim((string)($r['exec_mgr_email'] ?? ''));
        $stamp = tosrm_now();
        // Stamp first so a second concurrent/lazy sweep cannot double-send even
        // if the mail step is slow. (No manager e-mail on file → stamp + skip.)
        db()->prepare("UPDATE calls SET xo_escalated_at=? WHERE id=?")->execute([$stamp, $cid]);
        if (function_exists('act_log')) act_log('CALL', $cid, 'SYSTEM',
            'Auto-escalated to ' . ($r['exec_mgr'] ?: ($r['exec_name'] . ' manager')) . ' — unallocated past target ('
            . ((int)($t['waiting'] ?? 0)) . 'd, target ' . (int)$t['thr'] . 'd)', ['auto' => 1]);
        if ($mgr === '' || !function_exists('ops_mail')) continue;
        $wait = (int)($t['waiting'] ?? 0);
        $subj = 'ESCALATION: ' . ($r['call_code'] ?? ('call #' . $cid)) . ' unallocated ' . $wait . 'd at ' . ($r['exec_name'] ?: 'your branch');
        $body = ($r['exec_mgr'] ?: 'Manager') . ",\n\n"
              . "A call forwarded to " . ($r['exec_name'] ?: 'your branch') . " by " . ($r['ibo_name'] ?: 'another branch')
              . " has been waiting " . $wait . " day(s) without an engineer allocated — past the " . (int)$t['thr'] . "-day target.\n\n"
              . "Call: " . ($r['call_code'] ?? ('#' . $cid)) . "\nClient: " . ($r['client_name'] ?: '—')
              . "\nRaised by: " . ($r['ibo_name'] ?: '—') . "\nRequired by: " . ($t['reqd'] ?: '—')
              . "\n\nPlease have your coordinator allocate it.\n\n" . (function_exists('app_name') ? app_name() : 'Operations');
        $cc = trim((string)($r['exec_coord_email'] ?? ''));   // keep the coordinator in the loop
        try { ops_mail($mgr, $subj, $body, $cc, 'escalation'); $sent++; } catch (Throwable $e) {}
    }
    return $sent;
}

// Self-contained cross-office panels (inbound + sent-out) + shared TAT strip.
// Reused on the Operations home. $csrf lets the nudge form submit safely.
function tosrm_xo_html($xo, $csrf = '') {
    if (!$xo) return '';
    $inbound = $xo['inbound'] ?? []; $sentout = $xo['sentout'] ?? []; $allScope = !empty($xo['all_scope']);
    if (!$inbound && !$sentout) return '';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $callWord = function_exists('THP') ? THP('call') : 'Calls';
    static $css = false; $h = '';
    if (!$css) { $css = true; $h .= '<style>'
        . '.xo-wrap{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:0 0 8px}'
        . '@media(max-width:900px){.xo-wrap{grid-template-columns:1fr}}'
        . '.xo-card{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;overflow:hidden}'
        . '.xo-card.in{border-top:3px solid var(--brand,#1e40af)} .xo-card.out{border-top:3px solid var(--warn,#d97706)}'
        . '.xo-h{padding:12px 15px 4px} .xo-h .t{font-weight:700;font-size:14.5px;display:flex;align-items:center;gap:8px}'
        . '.xo-h .d{color:var(--muted,#656e7a);font-size:12px;margin-top:2px}'
        . '.xo-h .n{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted,#656e7a)}'
        . '.xo-list{padding:6px 10px 12px}'
        . '.xo-row{border:1px solid var(--line,#eef0f3);border-radius:10px;padding:10px 12px;margin:6px 4px}'
        . '.xo-row .top{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}'
        . '.xo-row .cc{font-weight:700} .xo-row .cl{color:var(--muted,#656e7a);font-size:12.5px}'
        . '.xo-row .meta{color:var(--muted,#656e7a);font-size:12px;margin-top:2px}'
        . '.xo-strip{display:flex;align-items:center;gap:0;margin:9px 0 8px;flex-wrap:wrap}'
        . '.xo-step{display:flex;flex-direction:column;align-items:center;min-width:52px}'
        . '.xo-step .dt{width:11px;height:11px;border-radius:50%;background:#cbd5e1}'
        . '.xo-step.done .dt{background:var(--green,#16a34a)} .xo-step.now .dt{background:var(--brand,#1e40af);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand,#1e40af) 22%,transparent)}'
        . '.xo-step .sl{font-size:9.5px;text-transform:uppercase;letter-spacing:.3px;color:var(--muted,#656e7a);margin-top:3px}'
        . '.xo-step .sd{font-size:10px;color:#9aa3af}'
        . '.xo-line{flex:1;height:2px;background:#e2e8f0;min-width:14px;margin:0 1px 16px}'
        . '.xo-line.done{background:var(--green,#16a34a)}'
        . '.xo-foot{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px}'
        . '.xo-btn{background:var(--brand,#1e40af);color:#fff;border:0;border-radius:8px;padding:7px 13px;font-size:12.5px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-block}'
        . '.xo-btn.g{background:#fff;color:var(--warn,#b45309);border:1px solid #f0d492}'
        . '.xo-esc{font-size:11px;font-weight:700;color:var(--bad,#b91c1c);background:var(--bad-bg,#fef2f2);border:1px solid #f6caca;border-radius:20px;padding:2px 9px}'
        . '.xo-chased{font-size:11px;color:var(--muted,#656e7a)}'
        . '.xo-empty{color:var(--muted,#656e7a);font-size:12.5px;padding:6px 8px 12px}'
        . '</style>'; }

    // One call's Received→Forwarded→Allocated→Scheduled strip.
    $strip = function ($t) use ($e) {
        $steps = [
            ['Received',  $t['recv'],  true,        null],
            ['Forwarded', $t['fwd'],   $t['fwd'] !== '',      $t['recv_to_fwd']],
            ['Allocated', $t['alloc'] ? '✓' : '', $t['alloc'], null],
            ['Scheduled', $t['sched'], $t['sched'] !== '',    $t['fwd_to_sched']],
        ];
        // Which step is "now" (first not-done).
        $nowIdx = -1;
        foreach ($steps as $i => $s) { if (!$s[2]) { $nowIdx = $i; break; } }
        $out = '<div class="xo-strip">';
        foreach ($steps as $i => $s) {
            [$lab, $val, $done, $days] = $s;
            $cls = $done ? 'done' : ($i === $nowIdx ? 'now' : '');
            $sub = $days !== null ? ($days . 'd') : ($val && $val !== '✓' ? $e(substr((string)$val, 5)) : '');
            $out .= '<div class="xo-step ' . $cls . '"><span class="dt"></span><span class="sl">' . $e($lab) . '</span><span class="sd">' . $e($sub) . '</span></div>';
            if ($i < count($steps) - 1) $out .= '<span class="xo-line' . ($done ? ' done' : '') . '"></span>';
        }
        return $out . '</div>';
    };

    // INBOUND — my office must allocate (or, at head office, everything crossing).
    $inTitle = $allScope ? ('All cross-branch ' . strtolower($callWord)) : 'Inbound — to allocate';
    $inDesc  = $allScope ? 'Every call raised by one branch and executed by another.' : 'Raised by another branch, executed by yours. You allocate the engineer.';
    $h .= '<div class="xo-wrap">';
    $h .= '<div class="xo-card in"><div class="xo-h"><div class="t">📥 ' . $e($inTitle) . ' <span class="n">' . count($inbound) . '</span></div><div class="d">' . $e($inDesc) . '</div></div><div class="xo-list">';
    if (!$inbound) $h .= '<div class="xo-empty">Nothing waiting — no calls from other branches to allocate.</div>';
    foreach ($inbound as $r) {
        $t = $r['tat'];
        $other = $allScope ? ($r['ibo_name'] . ' → ' . $r['exec_name']) : ('from ' . ($r['ibo_name'] ?: '—'));
        $escOn = trim((string)($r['xo_escalated_at'] ?? '')) !== '' ? ' · manager e-mailed ' . $e(substr((string)$r['xo_escalated_at'], 0, 10)) : '';
        $h .= '<div class="xo-row"><div class="top"><a class="cc" href="/call?id=' . (int)$r['id'] . '">' . $e($r['call_code'] ?: ('#' . (int)$r['id'])) . '</a>'
            . '<span class="cl">' . $e($r['client_name'] ?: '—') . '</span>'
            . ($t['escalated'] ? '<span class="xo-esc" title="Past target — flagged to ' . $e($r['exec_mgr'] ?: 'the branch manager') . $escOn . '">⚠ Escalated to manager</span>' : '')
            . '</div>'
            . '<div class="meta">' . $e($other) . ($r['inspection_type'] ? ' · ' . $e($r['inspection_type']) : '')
            . ($t['reqd'] !== '' ? ' · required ' . $e($t['reqd']) : '')
            . ($t['waiting'] !== null ? ' · waiting <b>' . (int)$t['waiting'] . 'd</b>' : '') . '</div>'
            . $strip($t)
            . '<div class="xo-foot">';
        // Allocate only when this viewer's office may allocate the call.
        $canAlloc = function_exists('call_can_allocate') ? call_can_allocate($r) : false;
        if ($canAlloc && !$t['alloc']) $h .= '<a class="xo-btn" href="/job-new?call=' . (int)$r['id'] . '">Allocate engineer</a>';
        elseif ($t['alloc']) $h .= '<span class="pill p-ok" style="font-size:11.5px">Allocated</span>';
        $h .= '</div></div>';
    }
    $h .= '</div></div>';

    // SENT OUT — my office is tracking; read-only + Nudge. (Hidden at head-office
    // ALL scope, where the inbound list already shows every crossing call once.)
    if (!$allScope) {
        $h .= '<div class="xo-card out"><div class="xo-h"><div class="t">📤 Sent out — tracking <span class="n">' . count($sentout) . '</span></div><div class="d">Raised by your branch, executed elsewhere. You follow up; you do not allocate.</div></div><div class="xo-list">';
        if (!$sentout) $h .= '<div class="xo-empty">Nothing outstanding — no calls you sent to other branches.</div>';
        foreach ($sentout as $r) {
            $t = $r['tat'];
            $chased = '';
            if (!empty($r['last_nudge'])) $chased = 'Chased ' . (int)($r['nudge_n'] ?? 1) . '× · last ' . $e(substr((string)$r['last_nudge'], 0, 10));
            $escOn = trim((string)($r['xo_escalated_at'] ?? '')) !== '' ? ' · their manager e-mailed ' . $e(substr((string)$r['xo_escalated_at'], 0, 10)) : '';
            $h .= '<div class="xo-row"><div class="top"><a class="cc" href="/call?id=' . (int)$r['id'] . '">' . $e($r['call_code'] ?: ('#' . (int)$r['id'])) . '</a>'
                . '<span class="cl">' . $e($r['client_name'] ?: '—') . '</span>'
                . ($t['escalated'] ? '<span class="xo-esc" title="Past target at ' . $e($r['exec_name']) . $escOn . '">⚠ Escalated to ' . $e($r['exec_name'] ?: 'branch') . ' mgr</span>' : '')
                . '</div>'
                . '<div class="meta">with ' . $e($r['exec_name'] ?: '—') . ($r['inspection_type'] ? ' · ' . $e($r['inspection_type']) : '')
                . ($t['reqd'] !== '' ? ' · required ' . $e($t['reqd']) : '')
                . ($t['waiting'] !== null ? ' · waiting <b>' . (int)$t['waiting'] . 'd</b>' : '') . '</div>'
                . $strip($t)
                . '<div class="xo-foot">';
            if (!$t['alloc']) {
                $h .= '<form method="post" action="/xo-nudge" style="display:inline">'
                    . '<input type="hidden" name="_csrf" value="' . $e($csrf) . '">'
                    . '<input type="hidden" name="call_id" value="' . (int)$r['id'] . '">'
                    . '<button class="xo-btn g" type="submit" title="Send a reminder to ' . $e($r['exec_name'] ?: 'the executing branch') . '">🔔 Nudge</button></form>';
            } else {
                $h .= '<span class="pill p-ok" style="font-size:11.5px">Allocated at ' . $e($r['exec_name']) . '</span>';
            }
            if ($chased) $h .= '<span class="xo-chased">' . $chased . '</span>';
            $h .= '</div></div>';
        }
        $h .= '</div></div>';
    }
    $h .= '</div>';
    return $h;
}

function ops_operations_home($method) {
    ops_require(can('mod.calls.view') || can('mod.jobs.view') || (function_exists('tosrm_ops_desk_can') && tosrm_ops_desk_can()),
        'You do not have access to Operations.');
    tosrm_migrate_d();
    $offices = tosrm_office_scope();
    $metrics = tosrm_ops_metrics($offices);
    $w = tosrm_call_office_sql($offices, 'c');
    // Every count is best-effort: a missing table or column must never take the
    // landing down, so each query is wrapped and falls back to null (the tile
    // then simply shows no number).
    $safe = function ($sql, $args = []) { try { return (int)(ops_one($sql, $args)['n'] ?? 0); } catch (Throwable $e) { return null; } };
    $counts = [
        'calls_open'  => $safe("SELECT COUNT(*) n FROM calls c WHERE c.status<>'CLOSED' AND COALESCE(c.op_status,'')<>'CLOSED' AND $w"),
        'jobs_active' => $safe("SELECT COUNT(*) n FROM jobs j JOIN calls c ON c.id=j.call_id WHERE COALESCE(j.closed_flag,0)=0 AND $w"),
        'recurring'   => $safe("SELECT COUNT(*) n FROM calls c WHERE COALESCE(c.is_recurring,0)=1 AND c.status<>'CLOSED' AND $w"),
        'overrides'   => $safe("SELECT COUNT(*) n FROM contract_overrides WHERE status IN ('PENDING','ENDORSED')"),
        'vouchers'    => $safe("SELECT COUNT(*) n FROM vouchers WHERE status IN ('DRAFT','SUBMITTED')"),
        'requisitions'=> $safe("SELECT COUNT(*) n FROM requisitions WHERE COALESCE(status,'') NOT IN ('CLOSED','FILLED','CANCELLED')"),
    ];
    // The TPIA service lines, straight from the Service Scope Engine — only the
    // services switched on for this install appear.
    $services = [];
    if (function_exists('svc_catalog')) {
        foreach (svc_catalog() as $s) {
            if (function_exists('svc_globally_active') && !svc_globally_active($s['code'])) continue;
            $services[] = $s;
        }
    }
    $offLabel = '';
    if (is_array($offices)) {
        $n = count($offices);
        $offLabel = $n ? ('scoped to ' . $n . ' branch' . ($n === 1 ? '' : 'es')) : '';
    }
    // Disruptions & changes — date-range filtered, scoped to the same branches.
    $dr = (string)($_GET['dr'] ?? 'fy');
    if (!array_key_exists(preg_replace('/[^a-z]/', '', strtolower($dr)), TOSRM_DISRUPT_RANGES)) $dr = 'fy';
    [$dFrom, $dTo, $dLabel] = tosrm_disruptions_range($dr);
    $disrupt = tosrm_disruptions($offices, $dFrom, $dTo);
    // Fire the auto-escalation the moment a coordinator opens Operations, so a
    // freshly-overdue call reaches the executing manager without waiting for the
    // nightly cron. Idempotent (mailed once per call) and best-effort.
    try { tosrm_xo_escalate_scan($offices); } catch (Throwable $e) {}
    $xo = tosrm_xo_calls($offices);   // cross-office: inbound to allocate + sent-out to track
    $pending = tosrm_pending_scheduling($offices);   // every call still awaiting a person (same-office + cross-office)
    // The registers that used to live on the separate Operations desk, folded in
    // here as a tab so there is one Operations screen, not two overlapping ones.
    $deskFrom = trim((string)($_GET['from'] ?? '')) ?: date('Y-m-d');
    $deskTo   = trim((string)($_GET['to'] ?? '')) ?: date('Y-m-d', strtotime($deskFrom . ' +13 days'));
    view('ops/operations_home', [
        'metrics' => $metrics, 'counts' => $counts, 'services' => $services, 'scopeLabel' => $offLabel,
        'disrupt' => $disrupt, 'drKey' => preg_replace('/[^a-z]/', '', strtolower($dr)), 'drLabel' => $dLabel,
        'xo' => $xo, 'pending' => $pending, 'csrf' => function_exists('csrf_token') ? csrf_token() : '',
        'backlog' => tosrm_ops_backlog($offices, 60),
        'schedule' => tosrm_schedule_register($offices, $deskFrom, $deskTo),
        'assignments' => tosrm_assignment_register($offices, 60),
        'dataquality' => tosrm_ops_dataquality($offices, 40),
        'from' => $deskFrom, 'to' => $deskTo,
    ]);
    return true;
}
function ops_tosrm_comm_action($route, $method) {
    if ($route === 'comm-add') {
        $ek = strtoupper((string)($_POST['entity_kind'] ?? '')); $eid = (int)($_POST['entity_id'] ?? 0);
        $back = $ek === 'JOB' ? '/job?id=' . $eid . '#comms' : '/call?id=' . $eid . '#comms';
        if ($method !== 'POST') redirect($back);
        if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
        ops_require(tosrm_can_edit(), 'You do not have permission to log a communication.');
        if (function_exists('act_log') && trim((string)($_POST['subject'] ?? '')) !== '') {
            act_log($ek, $eid, (string)($_POST['kind'] ?? 'NOTE'), (string)($_POST['subject'] ?? ''), ['body'=>(string)($_POST['body'] ?? '')]);
            flash('Communication logged.');
        } else flash('Give the entry a subject.', 'error');
        redirect($back);
    }
    if ($route === 'xo-nudge') {
        $callId = (int)($_POST['call_id'] ?? 0);
        $back = '/operations';
        if ($method !== 'POST') redirect($back);
        if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
        ops_require(can('mod.calls.view') || (function_exists('is_coordinator_level') && is_coordinator_level()),
            'You do not have permission to chase this ' . (function_exists('Tl') ? Tl('call') : 'call') . '.');
        // The contracting office may nudge; it must be a call their branch raised
        // and someone else executes. (Allocation stays with the executing office.)
        $call = $callId ? ops_one("SELECT * FROM calls WHERE id=?", [$callId]) : null;
        if (!$call) { flash('That ' . (function_exists('Tl') ? Tl('call') : 'call') . ' was not found.', 'error'); redirect($back); }
        $scope = tosrm_office_scope();
        $ibo = (int)($call['ibo_office_id'] ?? 0);
        $ownsIt = $scope === 'ALL' || (is_array($scope) && in_array($ibo, array_map('intval', $scope), true));
        if (!$ownsIt) { flash('You can only chase calls your own branch raised.', 'error'); redirect($back); }
        $ok = tosrm_xo_nudge($callId, (string)($_POST['note'] ?? ''));
        flash($ok ? 'Reminder sent to the executing branch — logged against the call.' : 'Could not send the reminder.', $ok ? 'success' : 'error');
        redirect($back);
    }
    if ($route === 'assign-issue') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $job = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
        $back = $job ? '/job?id=' . $jobId . '#assign' : '/jobs';
        if ($method !== 'POST') redirect($back);
        if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
        ops_require(tosrm_can_edit(), 'You do not have permission to raise an issue.');
        $title = trim((string)($_POST['title'] ?? '')); if ($title === '') { flash('Describe the operational issue.', 'error'); redirect($back); }
        $id = tosrm_issue_from_event($jobId, $title, (string)($_POST['description'] ?? ''), (string)($_POST['severity'] ?? 'MINOR'));
        flash($id ? 'Operational issue raised in the Phase 8 register (linked to this job).' : 'Could not raise the issue.', $id ? 'success' : 'error');
        redirect($back);
    }
    redirect('/');
}
