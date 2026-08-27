<?php
  // Per-job Deputation & site-operations panel. Included from job_detail.php only
  // when the job is a deputation. $dep, $job, $depStatuses, $depMobStatuses,
  // $depLogKinds, $depApprovalStatuses, $depCanEdit are in scope. Reuses the job
  // detail's .panel / .ctitle / .pill / .grid conventions.
  $jid = (int)$job['id'];
  $depName = $depStatuses[$dep['status']] ?? ($dep['status'] ?: 'Not set');
  $statusTone = in_array($dep['status'], ['SUSPENDED','REPLACEMENT_REQ','CANCELLED'], true) ? 'p-bad'
              : (in_array($dep['status'], ['ACTIVE','MOBILIZED'], true) ? 'p-ok' : 'p-mut');
  $fmt = fn($s) => $s ? date('d M Y', strtotime($s)) : '—';
  $clientId = (int)($job['client_id'] ?? ($jcall['client_id'] ?? 0));
?>
<div class="panel" id="deputation">
  <div class="ctitle" style="margin-top:0"><h3>👷 Deputation &amp; site operations
    <span class="pill <?= $statusTone ?>" style="margin-left:6px"><?= e($depName) ?></span></h3></div>

  <?php if ($dep['conflicts']): ?>
    <div class="msg msg-warning" style="margin-top:0"><strong>Double-booked:</strong> this person is on
      <?= count($dep['conflicts']) ?> overlapping posting(s). Overlap
      <?= e($fmt($dep['conflicts'][0]['from'])) ?> – <?= e($fmt($dep['conflicts'][0]['to'])) ?>. Nobody has been moved.</div>
  <?php endif; ?>

  <?php // Slice P2 — the "what is blocking this person from mobilizing?" summary.
    if (function_exists('mobilization_readiness_render')) mobilization_readiness_render($jid); ?>

  <!-- Lifecycle -->
  <div class="ctitle"><h3 style="font-size:14px">Lifecycle</h3></div>
  <?php if ($depCanEdit): ?>
    <form method="post" action="/dep-status" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:8px">
      <input type="hidden" name="job_id" value="<?= $jid ?>">
      <label>Set status
        <select name="dep_status">
          <?php foreach ($depStatuses as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $dep['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="flex:1;min-width:180px">Reason (optional) <input type="text" name="reason" placeholder="e.g. client approval received"></label>
      <button class="btn" type="submit">Update status</button>
    </form>
  <?php endif; ?>
  <?php if ($dep['history']): ?>
    <details style="margin-bottom:10px"><summary class="muted">Status history (<?= count($dep['history']) ?>)</summary>
      <table class="grid" style="margin-top:6px">
        <tr><th>When</th><th>From → to</th><th>Reason</th><th>By</th></tr>
        <?php foreach ($dep['history'] as $h): ?>
          <tr><td><?= e(substr((string)$h['at'],0,10)) ?></td>
            <td><?= e($depStatuses[$h['old_status']] ?? $h['old_status'] ?: '—') ?> → <strong><?= e($depStatuses[$h['new_status']] ?? $h['new_status']) ?></strong></td>
            <td><?= e($h['reason']) ?: '—' ?></td><td><?= e($h['actor']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </details>
  <?php endif; ?>

  <!-- Mobilization checklist -->
  <div class="ctitle"><h3 style="font-size:14px">Mobilization checklist
    <?php if ($dep['mob']): $r = $dep['mob_readiness']; ?>
      <span class="pill <?= $r['ready'] ? 'p-ok' : 'p-mut' ?>" style="margin-left:6px"><?= (int)$r['required_done'] ?>/<?= (int)$r['required'] ?> required done</span>
    <?php endif; ?></h3></div>
  <?php if (!$dep['mob']): ?>
    <?php if ($depCanEdit): ?>
      <form method="post" action="/dep-check-seed" style="margin-bottom:10px"><input type="hidden" name="job_id" value="<?= $jid ?>"><input type="hidden" name="phase" value="MOB">
        <button class="btn secondary" type="submit">Add a mobilization checklist</button>
        <span class="muted" style="margin-left:6px">A starting set of items, all editable — none is mandatory.</span></form>
    <?php else: ?><p class="muted">No mobilization checklist yet.</p><?php endif; ?>
  <?php else: ?>
    <table class="grid" style="margin-bottom:10px">
      <tr><th>Item</th><th>Category</th><th>Required</th><th>Status</th></tr>
      <?php foreach ($dep['mob'] as $c): ?>
        <tr>
          <td><?= e($c['item']) ?></td><td class="muted"><?= e($c['category']) ?></td>
          <td><?= (int)$c['required'] === 1 ? 'Yes' : 'No' ?></td>
          <td>
            <?php if ($depCanEdit): ?>
              <form method="post" action="/dep-check-set" style="display:inline"><input type="hidden" name="job_id" value="<?= $jid ?>"><input type="hidden" name="item_id" value="<?= (int)$c['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                  <?php foreach ($depMobStatuses as $sc => $sl): ?><option value="<?= e($sc) ?>" <?= $c['status']===$sc?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                </select></form>
            <?php else: ?><?= e($depMobStatuses[$c['status']] ?? $c['status']) ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <!-- Site registers -->
  <div class="ctitle"><h3 style="font-size:14px">Site register
    <span class="muted">(observation / issue / instruction / meeting)</span></h3></div>
  <?php if ($dep['site_log']): ?>
    <table class="grid" style="margin-bottom:8px">
      <tr><th>Date</th><th>Kind</th><th>Detail</th><th>Action / due</th><th>Status</th></tr>
      <?php foreach ($dep['site_log'] as $l): ?>
        <tr>
          <td style="white-space:nowrap"><?= e($fmt($l['log_date'])) ?></td>
          <td><span class="pill p-mut"><?= e($depLogKinds[$l['kind']] ?? $l['kind']) ?></span></td>
          <td><?= e($l['detail']) ?><?= $l['risk'] ? ' <span class="muted">· risk: '.e($l['risk']).'</span>' : '' ?></td>
          <td><?= e($l['action']) ?: '—' ?><?= $l['due_on'] ? '<br><span class="muted">due '.e($fmt($l['due_on'])).'</span>' : '' ?></td>
          <td>
            <?php $lt = in_array($l['status'],['CLOSED','CANCELLED'],true)?'p-ok':'p-mut'; ?>
            <span class="pill <?= $lt ?>"><?= e($l['status']) ?></span>
            <?php if ($depCanEdit && !in_array($l['status'],['CLOSED','CANCELLED'],true)): ?>
              <form method="post" action="/dep-site-log-close" style="display:inline"><input type="hidden" name="job_id" value="<?= $jid ?>"><input type="hidden" name="log_id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="status" value="CLOSED">
                <button class="btn sm" type="submit">Close</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted">Nothing logged yet.</p><?php endif; ?>
  <?php if ($depCanEdit): ?>
    <details style="margin-bottom:10px"><summary>➕ Add a site register entry</summary>
      <form method="post" action="/dep-site-log" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;max-width:720px">
        <input type="hidden" name="job_id" value="<?= $jid ?>"><input type="hidden" name="client_id" value="<?= $clientId ?>">
        <label>Kind <select name="kind"><?php foreach ($depLogKinds as $kc=>$kl): ?><option value="<?= e($kc) ?>"><?= e($kl) ?></option><?php endforeach; ?></select></label>
        <label>Date <input type="date" name="log_date" value="<?= e(date('Y-m-d')) ?>"></label>
        <label style="grid-column:1/3">Detail <textarea name="detail" rows="2" required></textarea></label>
        <label>Risk <input type="text" name="risk" placeholder="Low / Medium / High"></label>
        <label>Responsible <input type="text" name="responsible"></label>
        <label style="grid-column:1/3">Action <input type="text" name="action"></label>
        <label>Due <input type="date" name="due_on"></label>
        <label style="display:flex;align-items:center;gap:6px;margin-top:22px"><input type="checkbox" name="client_visible" checked> Client-visible</label>
        <div style="grid-column:1/3"><button class="btn" type="submit">Add entry</button></div>
      </form>
    </details>
  <?php endif; ?>

  <!-- Activity timesheet -->
  <div class="ctitle"><h3 style="font-size:14px">Activity timesheet
    <span class="muted">(<?= rtrim(rtrim(number_format($dep['ts_totals']['hours'],1),'0'),'.') ?>h + <?= rtrim(rtrim(number_format($dep['ts_totals']['ot_hours'],1),'0'),'.') ?> OT over <?= (int)$dep['ts_totals']['days'] ?> day(s))</span></h3></div>
  <?php if ($dep['timesheet']): ?>
    <table class="grid" style="margin-bottom:8px">
      <tr><th>Date</th><th>Activity</th><th>Hours</th><th>OT</th><th>Billable</th></tr>
      <?php foreach (array_slice($dep['timesheet'], -12) as $t): ?>
        <tr><td style="white-space:nowrap"><?= e($fmt($t['ts_date'])) ?></td><td><?= e($t['activity']) ?></td>
          <td><?= e(rtrim(rtrim(number_format((float)$t['hours'],1),'0'),'.')) ?></td>
          <td><?= (float)$t['ot_hours'] ? e(rtrim(rtrim(number_format((float)$t['ot_hours'],1),'0'),'.')) : '—' ?></td>
          <td><?= (int)$t['billable'] === 1 ? 'Yes' : 'No' ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted">No timesheet entries yet.</p><?php endif; ?>
  <?php if ($depCanEdit): ?>
    <details style="margin-bottom:10px"><summary>➕ Add a timesheet entry</summary>
      <form method="post" action="/dep-timesheet" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;max-width:720px">
        <input type="hidden" name="job_id" value="<?= $jid ?>">
        <label>Date <input type="date" name="ts_date" value="<?= e(date('Y-m-d')) ?>"></label>
        <label>Activity <input type="text" name="activity" required></label>
        <label>Hours <input type="number" step="0.25" name="hours" value="8"></label>
        <label>OT hours <input type="number" step="0.25" name="ot_hours" value="0"></label>
        <label style="grid-column:1/3">Description <input type="text" name="description"></label>
        <label style="display:flex;align-items:center;gap:6px;margin-top:6px"><input type="checkbox" name="billable" checked> Billable</label>
        <div style="grid-column:1/3"><button class="btn" type="submit">Add entry</button></div>
      </form>
    </details>
  <?php endif; ?>

  <!-- Client attendance approval → billing sign-off -->
  <div class="ctitle"><h3 style="font-size:14px">Attendance approval &amp; billing sign-off</h3></div>
  <?php if ($dep['approvals']): ?>
    <table class="grid" style="margin-bottom:8px">
      <tr><th>Period</th><th>Basis</th><th>Billable</th><th>Status</th><th>Client</th><?php if ($depCanEdit): ?><th></th><?php endif; ?></tr>
      <?php foreach ($dep['approvals'] as $ap): ?>
        <tr>
          <td style="white-space:nowrap"><?= e($fmt($ap['period_from'])) ?> – <?= e($fmt($ap['period_to'])) ?></td>
          <td><?= e($ap['basis']) ?: '—' ?></td>
          <td><?= (float)$ap['billable_days'] ? e(rtrim(rtrim(number_format((float)$ap['billable_days'],1),'0'),'.')).' d' : '' ?><?= (float)$ap['billable_hours'] ? ' '.e(rtrim(rtrim(number_format((float)$ap['billable_hours'],1),'0'),'.')).' h' : '' ?></td>
          <td><?php $at = $ap['status']==='APPROVED'?'p-ok':($ap['status']==='REJECTED'?'p-bad':'p-mut'); ?><span class="pill <?= $at ?>"><?= e($depApprovalStatuses[$ap['status']] ?? $ap['status']) ?></span></td>
          <td><?= e($ap['client_rep']) ?: '—' ?><?= $ap['approved_on'] ? '<br><span class="muted">'.e($fmt($ap['approved_on'])).'</span>' : '' ?></td>
          <?php if ($depCanEdit): ?>
          <td>
            <?php if (!in_array($ap['status'],['APPROVED','LOCKED'],true)): ?>
              <form method="post" action="/dep-approval-status" style="display:flex;gap:4px">
                <input type="hidden" name="job_id" value="<?= $jid ?>"><input type="hidden" name="ap_id" value="<?= (int)$ap['id'] ?>">
                <input type="text" name="client_rep" placeholder="Client rep" style="width:110px">
                <select name="status"><?php foreach ($depApprovalStatuses as $sc=>$sl): ?><option value="<?= e($sc) ?>" <?= $ap['status']===$sc?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?></select>
                <button class="btn sm" type="submit">Save</button>
              </form>
            <?php else: ?><span class="muted">locked</span><?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?><p class="muted">No attendance periods submitted yet.</p><?php endif; ?>
  <?php if ($depCanEdit): ?>
    <details><summary>➕ Submit an attendance period for client approval</summary>
      <form method="post" action="/dep-approval" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;max-width:720px">
        <input type="hidden" name="job_id" value="<?= $jid ?>"><input type="hidden" name="client_id" value="<?= $clientId ?>">
        <label>Period from <input type="date" name="period_from"></label>
        <label>Period to <input type="date" name="period_to"></label>
        <label>Basis <select name="basis"><option value="MANDAY">Man-day</option><option value="MANMONTH">Man-month</option><option value="HOURLY">Hourly</option><option value="SHIFT">Shift</option></select></label>
        <label>Billable days <input type="number" step="0.5" name="billable_days" value="0"></label>
        <div style="grid-column:1/3"><button class="btn" type="submit">Submit for approval</button>
          <span class="muted" style="margin-left:6px">This supports billing; it is not an invoice. The client confirms the quantity.</span></div>
      </form>
    </details>
  <?php endif; ?>
</div>
