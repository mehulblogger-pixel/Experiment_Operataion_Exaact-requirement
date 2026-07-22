<div class="master-head">
  <div><h1>Job <?= e($job['job_code']) ?></h1>
    <p class="sub"><?= e($job['client_disp'] ?: $job['client_name'] ?: '—') ?> · <?= e($job['inspector_name'] ?: 'Unassigned') ?></p></div>
  <div class="row-actions">
    <?php if (!$job['closed_flag']): ?><a class="btn" href="/job-close?id=<?= (int)$job['id'] ?>">Close job</a><?php endif; ?>
    <?php if (is_coordinator_level() && !$job['closed_flag']): ?><a class="btn secondary" href="/job-edit?id=<?= (int)$job['id'] ?>">Edit</a><?php endif; ?>
    <?php if ($job['call_id']): ?><a class="btn secondary" href="/call?id=<?= (int)$job['call_id'] ?>">View call</a><?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="kv-grid">
    <div><span class="k">Call</span><?= e($job['call_code'] ?: '—') ?></div>
    <div><span class="k">Executing office</span><?= e($job['office_name'] ?: '—') ?></div>
    <div><span class="k">Inspector</span><?= e($job['inspector_name'] ?: '—') ?></div>
    <div><span class="k">Sub-con</span><?= e($job['subcon_agency'] ?: '—') ?></div>
    <div><span class="k">BOSS no.</span><?= e($job['boss_number'] ?: '—') ?></div>
    <div><span class="k">Scheduled</span><?= e($job['scheduled_date'] ?: '—') ?></div>
    <div><span class="k">Inspection</span><?= e(($job['inspection_start_date'] ?: '?') . ' → ' . ($job['inspection_end_date'] ?: '?')) ?></div>
    <div><span class="k">Type of inspection</span><?= e(INSPECTION_TYPES[$job['inspection_type']] ?? ($job['inspection_type'] ?: '—')) ?></div>
    <div><span class="k">Activity</span><?= e(($job['activity_id']??null) ? lk_value_path($job['activity_id']) : '—') ?></div>
    <div><span class="k">Reporting</span><?= e(REPORT_FREQ[$job['reporting_frequency']] ?? '—') ?><?= ($job['reporting_frequency']==='CUSTOM' && !empty($job['report_custom_days'])) ? ' (every '.(int)$job['report_custom_days'].' days)' : '' ?></div>
    <div><span class="k">Credit direction</span><?= e(CREDIT_DIRECTIONS[$job['credit_direction']] ?? '—') ?></div>
    <div><span class="k">Expected credit</span><?= fmoney($job['expected_credit']) ?></div>
    <div><span class="k">Report uploaded</span><?= e($job['report_upload_date'] ?: '—') ?></div>
    <div><span class="k">TAT</span><?= $job['tat_days']===null?'—':(int)$job['tat_days'].' day(s)' ?></div>
    <div class="kv-wide"><span class="k">Report folder</span><?php if ($job['folder_link']): ?><a href="<?= e($job['folder_link']) ?>" target="_blank" rel="noopener"><?= e($job['folder_link']) ?></a><?php else: ?>—<?php endif; ?></div>
    <div class="kv-wide"><span class="k">Deliverables required</span><?php
      $dl = $job['deliverables'] !== '' ? explode(',', $job['deliverables']) : [];
      $map = lk_options_or('deliverable', DELIVERABLES);
      echo $dl ? e(implode(', ', array_map(fn($c) => $map[$c] ?? $c, $dl))) : '—';
    ?></div>
    <?php foreach (custom_display('job', $job['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value']) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel-split">
  <div class="panel">
    <h3 class="tab-sub">Expenses</h3>
    <table class="grid">
      <tr><th>Date</th><th>SBU</th><th>Travel</th><th>Local</th><th>Food</th><th>Lodging</th><th>Misc</th><th>Total</th></tr>
      <?php $etot=0; foreach ($expenses as $x): $rt=$x['travel']+$x['local']+$x['food']+$x['lodging']+$x['misc']; $etot+=$rt; ?>
      <tr><td><?= e($x['exp_date']) ?></td><td><?= e(OPS_SBUS[$x['sbu']] ?? $x['sbu']) ?></td>
        <td><?= fmoney($x['travel']) ?></td><td><?= fmoney($x['local']) ?></td><td><?= fmoney($x['food']) ?></td>
        <td><?= fmoney($x['lodging']) ?></td><td><?= fmoney($x['misc']) ?></td><td><strong><?= fmoney($rt) ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$expenses): ?><tr><td colspan="8">No expenses recorded (entered at closure).</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="panel">
    <h3 class="tab-sub">Profitability<?= can_see_salary() ? '' : ' (summary)' ?></h3>
    <div class="kv-grid">
      <div><span class="k">Man-days</span><?= e($profit['mandays']) ?></div>
      <?php if (can_see_salary()): ?>
        <div><span class="k">Daily cost</span><?= fmoney($profit['daily_cost']) ?></div>
        <div><span class="k">Labour cost</span><?= fmoney($profit['labour']) ?></div>
      <?php endif; ?>
      <div><span class="k">Expenses</span><?= fmoney($profit['expenses']) ?></div>
      <div><span class="k">Sub-con cost</span><?= fmoney($profit['subcon']) ?></div>
      <div><span class="k">Expected credit</span><?= fmoney($profit['credit']) ?></div>
      <?php if (can_see_salary()): ?>
        <div class="kv-wide"><span class="k">Net profit</span><strong style="color:<?= $profit['profit']>=0?'var(--good,#1f8a4c)':'#c0392b' ?>"><?= fmoney($profit['profit']) ?></strong></div>
      <?php else: ?>
        <div class="kv-wide"><span class="k">Net profit</span><em class="muted">Visible to Master Admin only</em></div>
      <?php endif; ?>
    </div>
  </div>
</div>
