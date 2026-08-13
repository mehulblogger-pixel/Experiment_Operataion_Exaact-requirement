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

// Move a call to a new lifecycle status, recording the transition. Returns
// false for an unknown target. Never changes the legacy `status` column.
function tosrm_set_status($callId, $to, $reason = '', $actor = '') {
    $to = strtoupper(trim((string)$to));
    if ($to === '' || !array_key_exists($to, tosrm_status_options())) return false;
    $call = ops_one("SELECT * FROM calls WHERE id=?", [(int)$callId]);
    if (!$call) return false;
    $from = tosrm_call_status($call);
    db()->prepare("UPDATE calls SET op_status=? WHERE id=?")->execute([$to, (int)$callId]);
    db()->prepare("INSERT INTO call_status_events (call_id, old_status, new_status, reason, actor, at) VALUES (?,?,?,?,?,?)")
        ->execute([(int)$callId, $from, $to, (string)$reason, $actor !== '' ? $actor : tosrm_actor(), tosrm_now()]);
    if (function_exists('idems_log')) { try { idems_log('call', (int)$callId, 'OP_STATUS', ['field'=>'op_status','old'=>$from,'new'=>$to,'reason'=>$reason]); } catch (Throwable $e) {} }
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
    if (function_exists('can') && (can('mod.calls.edit') || can('ops.job.allocate'))) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    if (function_exists('is_master') && is_master()) return true;
    return false;
}

// ---------------------------------------------------------------------------
//  Panel — rendered on the call detail page. Self-contained (echoes HTML), so
//  hosting it is a one-line include and it stays fully unit-testable.
// ---------------------------------------------------------------------------
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
          <form method="post" action="/call-override" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
            <input type="text" name="reason" placeholder="Reason to override and proceed" style="flex:1;min-width:220px" required>
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
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <form method="post" action="/call-status">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
          <label style="display:block;font-size:12px;color:#666">Change status</label>
          <div style="display:flex;gap:6px">
            <select name="op_status"><?php foreach ($statuses as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$cur?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
            <input type="text" name="reason" placeholder="Reason (optional)" style="flex:1">
            <button class="btn" type="submit">Set</button>
          </div>
        </form>
        <form method="post" action="/call-attrs">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
          <label style="display:block;font-size:12px;color:#666">Priority · Criticality · Source</label>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <select name="priority"><option value="">Priority…</option><?php foreach ($prioOpt as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$prio?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
            <select name="criticality"><option value="">Criticality…</option><?php foreach ($critOpt as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$crit?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
            <select name="source"><option value="">Source…</option><?php foreach ($srcOpt as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$src?'selected':''?>><?=$esc($v)?></option><?php endforeach; ?></select>
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
                  <form method="post" action="/call-clar-respond" style="display:flex;gap:4px">
                    <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>"><input type="hidden" name="id" value="<?=$esc($c['id'])?>">
                    <input type="text" name="response" placeholder="Record the answer" style="flex:1" required>
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
        <form method="post" action="/call-clar-new" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
          <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>"><input type="hidden" name="call_id" value="<?=$cid?>">
          <select name="raised_to"><?php foreach (tosrm_clar_to_options() as $k=>$v): ?><option value="<?=$esc($k)?>"><?=$esc($v)?></option><?php endforeach; ?></select>
          <input type="text" name="subject" placeholder="What needs clarifying?" style="flex:1;min-width:200px" required>
          <input type="text" name="detail" placeholder="Detail (optional)" style="flex:1;min-width:160px">
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

    switch ($route) {
        case 'call-status':
            if (tosrm_set_status($callId, (string)($_POST['op_status'] ?? ''), (string)($_POST['reason'] ?? ''))) {
                flash('Status updated.');
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
