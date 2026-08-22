<div class="crumbs"><a href="/">Home</a> › Operations desk</div>
<div class="master-head">
  <div><h1>Operations desk</h1>
    <p class="sub" style="margin:2px 0 0">The control centre — what is waiting, what is scheduled, what is at risk.
      Figures are scoped to your branches. Nothing here decides anything for you.</p></div>
  <div class="row-actions">
    <a class="btn secondary" href="/schedule">Scheduling board</a>
    <a class="btn secondary" href="/recurring">Recurring</a>
    <a class="btn secondary" href="/sla-targets">SLA targets</a>
  </div>
</div>

<?php $m = $metrics; ?>
<section class="card" style="margin-bottom:14px">
  <p style="margin:0 0 10px;font-size:14px"><strong>Today:</strong> <?= e($summary['text']) ?>
    <?php if (!empty($summary['ai'])): ?><span class="badge" title="Phrased by the AI assistant from these figures — advisory only">AI</span><?php endif; ?></p>
  <div class="kpi-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
    <?php
      $cards = [
        ['New requests', $m['new'], '/calls'],
        ['Unscheduled', $m['unscheduled'], '/schedule'],
        ['Clarification open', $m['clarification'], '/calls'],
        ['Ready to schedule', $m['ready'], '/schedule'],
        ['Today’s services', $m['today'], '#schedule'],
        ['Overdue', $m['overdue'], '#backlog'],
        ['Critical', $m['critical'], '/calls'],
        ['Report pending', $m['report_pending'], '/jobs'],
      ];
      foreach ($cards as [$lbl,$val,$href]):
        $tone = ($lbl==='Overdue' || $lbl==='Critical') && $val>0 ? 'color:#b4232b' : (($lbl==='Clarification open'||$lbl==='Unscheduled') && $val>0 ? 'color:#b45309' : '');
    ?>
      <a href="<?= e($href) ?>" class="kpi" style="border:1px solid var(--hair,#e4e1d8);border-radius:9px;padding:10px 12px;text-decoration:none;color:inherit">
        <div style="font-size:22px;font-weight:700;<?= $tone ?>"><?= (int)$val ?></div>
        <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.3px"><?= e($lbl) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($dataquality): ?>
<section class="card" style="margin-bottom:14px">
  <h2 style="margin:0 0 8px">Data-quality flags <span class="muted" style="font-size:13px">(advisory — complete or override on the call)</span></h2>
  <table class="grid">
    <tr><th>Call</th><th>Missing before scheduling</th></tr>
    <?php foreach ($dataquality as $dq): ?>
      <tr><td><a href="/call?id=<?= (int)$dq['call_id'] ?>#ops"><?= e($dq['call_code'] ?: ('#'.$dq['call_id'])) ?></a></td><td><?= e(implode(', ', $dq['flags'])) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>

<section class="card" style="margin-bottom:14px" id="backlog">
  <h2 style="margin:0 0 8px">Backlog</h2>
  <table class="grid">
    <tr><th>Call</th><th>Client</th><th>Service</th><th>Required</th><th>Age (d)</th><th>Pending on</th><th>Priority</th></tr>
    <?php foreach ($backlog as $b): ?>
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
    <?php if (!$backlog): ?><tr><td colspan="7">Nothing in the backlog — all caught up.</td></tr><?php endif; ?>
  </table>
</section>

<section class="card" style="margin-bottom:14px" id="schedule">
  <h2 style="margin:0 0 8px">Schedule register <span class="muted" style="font-size:13px"><?= e($from) ?> → <?= e($to) ?></span></h2>
  <table class="grid">
    <tr><th>Date</th><th>Job</th><th>Client</th><th>Service</th><th>Resource</th><th>Hold</th><th>Acceptance</th><th>Stage</th></tr>
    <?php foreach ($schedule as $s): ?>
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
    <?php if (!$schedule): ?><tr><td colspan="8">No scheduled work in this window.</td></tr><?php endif; ?>
  </table>
</section>

<section class="card" id="assignments">
  <h2 style="margin:0 0 8px">Assignment register</h2>
  <table class="grid">
    <tr><th>Job</th><th>Resource</th><th>Client</th><th>Service</th><th>Date</th><th>Hold</th><th>Acceptance</th></tr>
    <?php foreach ($assignments as $a): ?>
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
    <?php if (!$assignments): ?><tr><td colspan="7">No assignments yet.</td></tr><?php endif; ?>
  </table>
</section>
