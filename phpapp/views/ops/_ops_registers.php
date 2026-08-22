<?php // Operations registers — backlog, schedule & assignment — folded into the
      //  Operations home (formerly the separate Operations desk). Expects
      //  $backlog, $schedule, $assignments, $dataquality, $from, $to. ?>
<div class="row-actions" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
  <?php if (function_exists('sched_board_can') && sched_board_can()): ?><a class="btn secondary" href="/schedule">Scheduling board</a><?php endif; ?>
  <a class="btn secondary" href="/recurring">Recurring</a>
  <a class="btn secondary" href="/sla-targets">SLA targets</a>
</div>

<?php if (!empty($dataquality)): ?>
<section class="card" style="margin-bottom:14px">
  <h2 style="margin:0 0 8px">Data-quality flags <span class="muted" style="font-size:13px">(advisory — complete or override on the call)</span></h2>
  <div style="overflow-x:auto"><table class="grid">
    <tr><th>Call</th><th>Missing before scheduling</th></tr>
    <?php foreach ($dataquality as $dq): ?>
      <tr><td><a href="/call?id=<?= (int)$dq['call_id'] ?>#ops"><?= e($dq['call_code'] ?: ('#'.$dq['call_id'])) ?></a></td><td><?= e(implode(', ', $dq['flags'])) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>
<?php endif; ?>

<section class="card" style="margin-bottom:14px" id="backlog">
  <h2 style="margin:0 0 8px">Backlog</h2>
  <div style="overflow-x:auto"><table class="grid">
    <tr><th>Call</th><th>Client</th><th>Service</th><th>Required</th><th>Age (d)</th><th>Pending on</th><th>Priority</th></tr>
    <?php foreach (($backlog ?? []) as $b): ?>
      <tr<?= !empty($b['overdue']) ? ' style="background:rgba(180,35,43,.06)"' : '' ?>>
        <td><a href="/call?id=<?= (int)$b['id'] ?>#ops"><?= e($b['call_code'] ?: ('#'.$b['id'])) ?></a></td>
        <td><?= e($b['client_name'] ?: '—') ?></td>
        <td><?= e(INSPECTION_TYPES[$b['inspection_type']] ?? $b['inspection_type']) ?></td>
        <td><?= e($b['inspection_required_date'] ?: '—') ?></td>
        <td style="text-align:right"><?= $b['age_days']===null ? '—' : (int)$b['age_days'] ?></td>
        <td><?= e($b['pending_reason']) ?></td>
        <td><?= $b['priority'] ? '<span class="badge">'.e($b['priority']).'</span>' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($backlog)): ?><tr><td colspan="7">Nothing in the backlog — all caught up.</td></tr><?php endif; ?>
  </table></div>
</section>

<section class="card" style="margin-bottom:14px" id="schedule">
  <h2 style="margin:0 0 8px">Schedule register <span class="muted" style="font-size:13px"><?= e($from ?? '') ?> → <?= e($to ?? '') ?></span></h2>
  <div style="overflow-x:auto"><table class="grid">
    <tr><th>Date</th><th>Job</th><th>Client</th><th>Service</th><th>Resource</th><th>Hold</th><th>Acceptance</th><th>Stage</th></tr>
    <?php foreach (($schedule ?? []) as $s): ?>
      <tr>
        <td><?= e($s['scheduled_date'] ?: '—') ?></td>
        <td><a href="/job?id=<?= (int)$s['job_id'] ?>#assign"><?= e($s['job_code']) ?></a></td>
        <td><?= e($s['client_name'] ?: '—') ?></td>
        <td><?= e(INSPECTION_TYPES[$s['inspection_type']] ?? $s['inspection_type']) ?></td>
        <td><?= e($s['inspector_name'] ?: 'Unassigned') ?></td>
        <td><?= e($s['assign_state'] ?: '—') ?></td>
        <td><?= e($s['accept_state'] ?: '—') ?></td>
        <td><span class="badge"><?= e(lk_options_or('job_stage', JOB_STAGES)[$s['stage'] ?? ''] ?? ($s['stage'] ?? '')) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($schedule)): ?><tr><td colspan="8">No scheduled work in this window.</td></tr><?php endif; ?>
  </table></div>
</section>

<section class="card" id="assignments">
  <h2 style="margin:0 0 8px">Assignment register</h2>
  <div style="overflow-x:auto"><table class="grid">
    <tr><th>Job</th><th>Resource</th><th>Client</th><th>Service</th><th>Date</th><th>Hold</th><th>Acceptance</th></tr>
    <?php foreach (($assignments ?? []) as $a): ?>
      <tr>
        <td><a href="/job?id=<?= (int)$a['job_id'] ?>#assign"><?= e($a['job_code']) ?></a></td>
        <td><?= e($a['inspector_name'] ?: '—') ?></td>
        <td><?= e($a['client_name'] ?: '—') ?></td>
        <td><?= e(INSPECTION_TYPES[$a['inspection_type']] ?? $a['inspection_type']) ?></td>
        <td><?= e($a['scheduled_date'] ?: '—') ?></td>
        <td><?= e($a['assign_state'] ?: '—') ?></td>
        <td><?= e($a['accept_state'] ?: '—') ?><?= trim((string)$a['accept_reason'])!=='' ? ' · '.e($a['accept_reason']) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($assignments)): ?><tr><td colspan="7">No assignments yet.</td></tr><?php endif; ?>
  </table></div>
</section>
