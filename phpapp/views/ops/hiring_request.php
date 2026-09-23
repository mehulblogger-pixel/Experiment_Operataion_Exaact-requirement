<?php
// One hiring request. Data: $req (null = new), $mayRaise, $mayDecide,
// $requisitions, $remaining, $people, $offices, $positions.
//
// Laid out as the questions a person actually answers — who, what, where, how
// many, when, why — rather than every recruitment field at once (§47). The
// detail that only matters later lives on the requisition.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$r = $req ?? null; $mayRaise = $mayRaise ?? false; $mayDecide = $mayDecide ?? false;
$requisitions = $requisitions ?? []; $remaining = $remaining ?? 0;
$people = $people ?? []; $offices = $offices ?? []; $positions = $positions ?? [];
$v = fn($k, $d = '') => $e($r[$k] ?? $d);
$csrf = fn() => function_exists('csrf_field') ? csrf_field() : '';
// M4 correction §1 — the locked words. A bare "Requirement" is never printed on
// a recruitment screen; hreq_label() qualifies the workspace's own word if it is
// one of the ambiguous ones.
$L  = fn($k, $pl = false) => function_exists('hreq_label') ? hreq_label($k, $pl) : ucfirst($k);
$status = strtoupper((string) ($r['status'] ?? 'DRAFT'));
$editable = !$r || in_array($status, ['DRAFT'], true);
$depts = function_exists('dept_form_options') ? dept_form_options('') : [];
$deptSel = function ($field, $cur) use ($depts, $e) {
    $h = '<select class="form-control searchable" name="' . $e($field) . '"><option value="">—</option>';
    foreach ($depts as $code => $label) {
        $id = function_exists('dept_of') && ($row = dept_of($code)) ? (int) $row['id'] : 0;
        $h .= '<option value="' . $id . '"' . ((int) $cur === $id ? ' selected' : '') . '>' . $e($label) . '</option>';
    }
    return $h . '</select>';
};
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/hiring-requests">Hiring requests</a> › <?= $r ? $e($r['req_no']) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $r ? $e($r['req_no']) : 'New hiring request' ?>
    <?php if ($r): ?><span class="pill <?= $status === 'APPROVED' ? 'p-ok' : ($status === 'DRAFT' ? 'p-mut' : 'p-info') ?>" style="vertical-align:middle;font-size:12px"><?= $e((function_exists('hreq_statuses') ? hreq_statuses() : [])[$status] ?? $status) ?></span><?php endif; ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= $r ? $e($r['job_title']) . ' · ' . (int) $r['quantity'] . ' needed' : 'Tell us what you need. Once it is approved it becomes a ' . strtolower($L('requisition')) . ' and recruitment starts.' ?></p>
    <?php //  B4 — the definition, where the word is. It had one before this: the
          //  admin rename screen, which is the one place nobody is confused. ?>
    <?= function_exists('T_NOTE') ? T_NOTE('hiring_request') : '' ?></div>
</div>

<?php //  B3 — what happens next to THIS request. Read out of HREQ_STATUS and the
      //  same gates the buttons below already use; it decides nothing and it
      //  offers no action this user is not already allowed. $remaining is the
      //  screen's own number, handed over rather than recomputed. ?>
<?php if ($r && function_exists('na_html')) echo na_html('hiring_request', $r + ['__remaining' => (int) $remaining]); ?>

<?php if ($r && $status === 'APPROVED'): ?>
  <div class="panel" id="na-recruit" style="border-left:3px solid #1a7f37">
    <strong>Approved<?= $r['decided_by'] ? ' by ' . $e($r['decided_by']) : '' ?><?= $r['decided_at'] ? ' on ' . $e(substr($r['decided_at'], 0, 10)) : '' ?>.</strong>
    <?php if ($remaining > 0 && $mayRaise): ?>
      <form method="post" style="display:inline-flex;gap:6px;align-items:center;margin-left:10px">
        <?= $csrf() ?><input type="hidden" name="do" value="raise-requisition"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <input class="form-control" type="number" name="qty" min="1" max="<?= (int) $remaining ?>" value="<?= (int) $remaining ?>" style="width:74px">
        <button class="btn small" type="submit">Start recruiting</button>
      </form>
      <span class="muted"><?= (int) $remaining ?> of <?= (int) $r['quantity'] ?> not yet being recruited</span>
    <?php elseif ($remaining <= 0): ?>
      <span class="muted">All <?= (int) $r['quantity'] ?> are being recruited.</span>
    <?php endif; ?>
  </div>
<?php elseif ($r && $status === 'REJECTED'): ?>
  <div class="panel" style="border-left:3px solid #b42318">
    <strong>Rejected<?= $r['decided_by'] ? ' by ' . $e($r['decided_by']) : '' ?><?= $r['decided_at'] ? ' on ' . $e(substr($r['decided_at'], 0, 10)) : '' ?>.</strong>
    <?php if (trim((string) ($r['decision_note'] ?? '')) !== ''): ?><span class="muted">— <?= $e($r['decision_note']) ?></span><?php endif; ?>
    <div class="muted" style="margin-top:4px">Recruitment cannot start from a rejected request. Raise a new one — changing this one back is not built yet.</div>
  </div>
<?php elseif ($r && in_array($status, ['SUBMITTED', 'UNDER_REVIEW'], true)): ?>
  <div class="panel" style="border-left:3px solid #0969da">
    <?php // M1 — where the request actually is. A chain running means its
          // approvers decide it, from My approvals, not from this screen.
          $appr = function_exists('hreq_approval') ? hreq_approval($r['id']) : null;
          $chainOpen = $appr && strtoupper((string) $appr['status']) === 'PENDING'; ?>
    <?php if ($chainOpen): ?>
      <strong>Awaiting approval</strong> — with its approvers<?= trim((string) ($appr['rule_name'] ?? '')) !== '' ? ' (' . $e($appr['rule_name']) . ')' : '' ?>.
      Recruitment cannot start until this is approved.
      <div class="muted" style="margin-top:4px">Approvers decide this from <a href="/my-approvals">My approvals</a>.</div>
    <?php else: ?>
    Waiting for a decision. Recruitment cannot start until this is approved.
    <?php if (!$mayDecide && function_exists('hreq_is_own_request') && hreq_is_own_request($r)): ?>
      <span class="muted" style="margin-left:10px">You raised this request, so somebody else has to decide it.</span>
    <?php endif; ?>
    <?php if ($mayDecide): ?>
      <form method="post" id="na-decide" style="display:inline-flex;gap:6px;align-items:center;margin-left:10px">
        <?= $csrf() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <input class="form-control" name="note" placeholder="Note (optional)" style="width:200px">
        <button class="btn small" type="submit" name="decision" value="approve">Approve</button>
        <button class="btn small secondary" type="submit" name="decision" value="reject">Reject</button>
      </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php // M1 — approval history. Read from the EXISTING approval steps; there is
      // no second history store. Shown only where a chain exists.
$steps = ($r && function_exists('hreq_approval_steps')) ? hreq_approval_steps($r['id']) : [];
if ($r && ($steps || !empty($r['submitted_at']))): ?>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Approval history</h3>
    <?php // Phase 3 · M3 §28 — the approval status and the SLA status are two
          // different facts, so the screen states them as two, in one sentence,
          // rather than inventing a third status that blurs them.
    $slaState = fn($x) => function_exists('appr_sla_state') ? appr_sla_state($x) : '';
    $slaText  = fn($x) => function_exists('appr_sla_sentence') ? appr_sla_sentence($x) : '';
    $live = null;
    foreach ($steps as $st) if (strtoupper((string) $st['status']) === 'PENDING') { $live = $st; break; }
    if ($live):
      $ls = $slaState($live);
      $bad = in_array($ls, ['OVERDUE', 'ESCALATED', 'DUE'], true); ?>
      <p class="muted" style="margin:-4px 0 10px;font-size:13px">
        Approval: <b>Pending</b><?= $live['label'] ? ' with ' . $e($live['label']) : '' ?> ·
        SLA: <b style="<?= $bad ? 'color:#dc2626' : '' ?>"><?= $e($slaText($live)) ?></b>
        <?php if (!empty($live['sla_due'])): ?> (due <?= $e(substr((string) $live['sla_due'], 0, 10)) ?>)<?php endif; ?>
        <?php if ((int) ($live['escalated'] ?? 0) === 1): ?>
          <br><span style="font-size:12px">An escalation notice has gone out. It does not change who may approve — this step still belongs to its approver.</span>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <table class="table" style="font-size:13px">
      <tr><th>What</th><th>When</th><th>Who</th><th>Decision</th><th>SLA</th><th>Reason</th></tr>
      <?php if (!empty($r['submitted_at'])): ?>
      <tr><td>Submitted</td><td><?= $e(substr((string) $r['submitted_at'], 0, 10)) ?></td>
          <td><?= $e($r['requested_by_name'] ?: $r['created_by']) ?></td><td>—</td><td>—</td><td>—</td></tr>
      <?php endif; ?>
      <?php foreach ($steps as $st): $sv = strtoupper((string) $st['status']); ?>
      <tr><td><?= $e($st['label'] ?: 'Approval') ?></td>
          <td><?= $e($st['acted_at'] ? substr((string) $st['acted_at'], 0, 10) : '—') ?></td>
          <td><?= $e($st['acted_by'] ?: '—') ?></td>
          <td><span class="pill <?= $sv === 'APPROVED' ? 'p-ok' : ($sv === 'REJECTED' ? 'p-bad' : 'p-mut') ?>" style="font-size:11px"><?= $e($sv === 'PENDING' ? 'Awaiting' : ucfirst(strtolower($sv))) ?></span></td>
          <td class="muted"><?= $e($slaText($st)) ?><?php if (!empty($st['sla_due']) && $sv === 'PENDING'): ?><br><span style="font-size:11px">due <?= $e(substr((string) $st['sla_due'], 0, 10)) ?></span><?php endif; ?></td>
          <td><?= $e($st['remarks'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$steps && !empty($r['decided_at'])): ?>
      <tr><td>Decision</td><td><?= $e(substr((string) $r['decided_at'], 0, 10)) ?></td>
          <td><?= $e($r['decided_by']) ?></td>
          <td><span class="pill <?= $status === 'APPROVED' ? 'p-ok' : 'p-bad' ?>" style="font-size:11px"><?= $e(ucfirst(strtolower($status))) ?></span></td>
          <td class="muted">—</td>
          <td><?= $e($r['decision_note'] ?: '—') ?></td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php endif; ?>

<?php if ($mayRaise && $editable): ?>
<div class="panel">
  <form method="post">
    <?= $csrf() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">

    <h3 class="tab-sub" style="margin-top:0">Who is asking?</h3>
    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
      <div class="ff"><label>Requested by *</label>
        <select class="form-control searchable" name="requested_by_id"><option value="">—</option>
          <?php foreach ($people as $pid => $pname): ?><option value="<?= (int) $pid ?>" <?= (int) ($r['requested_by_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>><?= $e($pname) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Requesting department <span class="muted">— who needs the person</span></label>
        <?= $deptSel('requesting_department_id', (int) ($r['requesting_department_id'] ?? 0)) ?></div>
    </div>

    <h3 class="tab-sub">What is required?</h3>
    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
      <div class="ff"><label>Job title *</label><input class="form-control" name="job_title" required value="<?= $v('job_title') ?>" placeholder="e.g. Mechanical Engineer"></div>
      <div class="ff"><label>Designation</label>
        <select class="form-control searchable" name="designation"><option value="">—</option>
          <?php foreach (lk_options_or('designation', DESIGNATIONS) as $k => $lbl): ?><option value="<?= $e($k) ?>" <?= ($r['designation'] ?? '') === $k ? 'selected' : '' ?>><?= $e($lbl) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Which department will they join?</label><?= $deptSel('hiring_department_id', (int) ($r['hiring_department_id'] ?? 0)) ?></div>
      <div class="ff"><label>Against which position? <span class="muted">— optional</span></label>
        <select class="form-control searchable" name="position_id"><option value="">— none / a new one is needed —</option>
          <?php foreach ($positions as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($r['position_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= $e($p['name']) ?><?= $p['department'] ? ' · ' . $e($p['department']) : '' ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff ff-wide" style="grid-column:1/-1"><label>Job description <span class="muted">— what this person will actually do</span></label>
        <textarea class="form-control" name="job_description" rows="3"><?= $e($r['job_description'] ?? '') ?></textarea></div>
    </div>

    <h3 class="tab-sub">Where, how many, and when?</h3>
    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <div class="ff"><label>How many people? *</label><input class="form-control" type="number" min="1" name="quantity" required value="<?= (int) ($r['quantity'] ?? 1) ?>"></div>
      <div class="ff"><label>Branch</label>
        <select class="form-control" name="office_id"><option value="">—</option>
          <?php foreach ($offices as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) ($r['office_id'] ?? 0) === (int) $o['id'] ? 'selected' : '' ?>><?= $e($o['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Work location</label><input class="form-control" name="work_location" value="<?= $v('work_location') ?>" placeholder="e.g. ABC Refinery, Jamnagar"></div>
      <div class="ff"><label>Project / contract reference</label><input class="form-control" name="project_ref" value="<?= $v('project_ref') ?>"></div>
      <div class="ff"><label>Needed by</label><input class="form-control" type="date" name="required_by" value="<?= $e(substr((string) ($r['required_by'] ?? ''), 0, 10)) ?>"></div>
      <div class="ff"><label>Employment type</label>
        <select class="form-control" name="employment_type"><option value="">—</option>
          <?php foreach (hreq_employment_types() as $k => $lbl): ?><option value="<?= $e($k) ?>" <?= ($r['employment_type'] ?? '') === $k ? 'selected' : '' ?>><?= $e($lbl) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Priority</label>
        <select class="form-control" name="priority">
          <?php foreach (hreq_priorities() as $k => $lbl): ?><option value="<?= $e($k) ?>" <?= ($r['priority'] ?? 'NORMAL') === $k ? 'selected' : '' ?>><?= $e($lbl) ?></option><?php endforeach; ?>
        </select></div>
    </div>

    <h3 class="tab-sub">Why?</h3>
    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
      <div class="ff"><label>Kind of request</label>
        <select class="form-control" name="request_type"><option value="">—</option>
          <?php foreach (hreq_request_types() as $k => $lbl): ?><option value="<?= $e($k) ?>" <?= ($r['request_type'] ?? '') === $k ? 'selected' : '' ?>><?= $e($lbl) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff ff-wide" style="grid-column:1/-1"><label>Reason</label><input class="form-control" name="reason" value="<?= $v('reason') ?>" placeholder="e.g. New contract awarded; existing team fully committed"></div>
    </div>
    <?php if (function_exists('custom_fields_for') && custom_fields_for('hiring_request')): ?>
      <h3 class="tab-sub">More details</h3>
      <div class="form-grid"><?php render_custom_fields('hiring_request', $cfvals ?? []); ?></div>
    <?php endif; ?>

    <div style="margin-top:14px;display:flex;gap:8px">
      <button class="btn" type="submit"><?= $r ? 'Save draft' : 'Create request' ?></button>
      <?php if ($r): ?><a class="btn secondary" href="/hiring-requests">Back</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if ($r && $status === 'DRAFT'): ?>
  <div class="panel" id="na-submit" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <form method="post" style="display:inline"><?= $csrf() ?>
      <input type="hidden" name="do" value="submit"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="btn" type="submit">Submit for approval</button></form>
    <form method="post" style="display:inline"><?= $csrf() ?>
      <input type="hidden" name="do" value="cancel"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="btn secondary small" type="submit">Cancel this request</button></form>
    <span class="muted">Nothing is recruited until this is approved.</span>
  </div>
<?php endif; ?>

<?php elseif ($r): ?>
  <div class="panel">
    <table class="grid">
      <tr><th style="width:30%">Requested by</th><td><?= $v('requested_by_name') ?: $v('created_by') ?></td></tr>
      <tr><th>What</th><td><?= $v('job_title') ?><?= $r['job_description'] ? '<div class="muted" style="font-size:11.5px">' . $e($r['job_description']) . '</div>' : '' ?></td></tr>
      <tr><th>How many</th><td><?= (int) $r['quantity'] ?></td></tr>
      <tr><th>Needed by</th><td><?= $v('required_by') ?: '—' ?></td></tr>
      <tr><th>Where</th><td><?= $v('work_location') ?: '—' ?></td></tr>
      <tr><th>Why</th><td><?= $v('reason') ?: '—' ?></td></tr>
    </table>
  </div>
<?php endif; ?>

<?php if ($r && $requisitions): ?>
  <h3 class="tab-sub">Recruitment raised from this request</h3>
  <div class="panel">
    <table class="grid">
      <tr><th><?= $e($L('requisition')) ?></th><th>How many</th><th>Status</th></tr>
      <?php foreach ($requisitions as $rq): ?>
      <tr><td><a href="/requisition?id=<?= (int) $rq['id'] ?>"><?= $e($rq['req_code']) ?></a></td>
        <td><?= (int) $rq['quantity'] ?></td>
        <td><?= $e((function_exists('lk_options_or') ? lk_options_or('requisition_status', REQ_STATUS) : REQ_STATUS)[$rq['status']] ?? $rq['status']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
