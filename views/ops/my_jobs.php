<?php
  $today = date('Y-m-d');
  $f = $f ?? '';
  // optional focused filter from a dashboard card
  $filters = [
    'open'    => ['Open jobs to do',   fn($j)=>!$j['closed_flag']],
    'overdue' => ['Overdue jobs',       fn($j)=>!$j['closed_flag'] && ($j['inspection_end_date']?:$j['scheduled_date']) && ($j['inspection_end_date']?:$j['scheduled_date'])<$today],
    'reports' => ['Reports pending',    fn($j)=>!$j['closed_flag'] && ($j['reporting_frequency']??'')!=='NOREPORT' && ($j['reporting_frequency']??'')!=='' && ($j['report_upload_date']??'')===''],
    'closed'  => ['Completed jobs',     fn($j)=>(bool)$j['closed_flag']],
  ];
  if ($f && isset($filters[$f])) $rows = array_values(array_filter($rows, $filters[$f][1]));
  $open = array_values(array_filter($rows, fn($j)=>!$j['closed_flag']));
  $closed = array_values(array_filter($rows, fn($j)=>$j['closed_flag']));
  // sort open by urgency (earliest scheduled / overdue first)
  usort($open, function($a,$b){ $ea=$a['scheduled_date']?:'9999'; $eb=$b['scheduled_date']?:'9999'; return strcmp($ea,$eb); });
  $jobCard = function($j) use ($today) {
    $end = $j['inspection_end_date'] ?: $j['scheduled_date'];
    $overdue = !$j['closed_flag'] && $end && $end < $today;
    $sched = $j['scheduled_date'] ?: '';
    ob_start(); ?>
    <div class="jobcard <?= $j['closed_flag']?'is-closed':($overdue?'is-late':'') ?>">
      <div class="jc-top">
        <div class="jc-client"><?= e($j['client_disp'] ?: $j['client_name'] ?: 'Client') ?></div>
        <?php if ($j['closed_flag']): ?><span class="pill p-ok">Closed</span>
        <?php elseif ($overdue): ?><span class="pill p-bad">Overdue</span>
        <?php else: ?><span class="pill p-warn">Open</span><?php endif; ?>
      </div>
      <div class="jc-meta">
        <span class="jc-code"><?= e($j['job_code']) ?></span>
        <?php if ($sched): ?><span>📅 <?= e($sched) ?></span><?php endif; ?>
        <?php if (($j['reporting_frequency'] ?? '') && ($j['reporting_frequency']!=='NOREPORT')): ?><span>📄 <?= e(REPORT_FREQ[$j['reporting_frequency']] ?? '') ?></span><?php endif; ?>
      </div>
      <div class="jc-actions">
        <a class="btn secondary" href="/job?id=<?= (int)$j['id'] ?>">Open</a>
        <?php if (!$j['closed_flag']): ?><a class="btn" href="/job-close?id=<?= (int)$j['id'] ?>">Upload &amp; Close</a><?php endif; ?>
      </div>
    </div>
    <?php return ob_get_clean();
  };
?>
<div class="home-hero"><div>
  <h1><?= ($f && isset($filters[$f])) ? e($filters[$f][0]) : 'My Jobs' ?></h1>
  <p class="sub" style="margin:0"><?php if ($f && isset($filters[$f])): ?><a href="/my-jobs">← All my jobs</a><?php else: ?>Your assigned inspections. Open a job to see details, or upload the report and close it.<?php endif; ?></p></div>
  <span class="scope-tag"><?= count($rows) ?> shown</span>
</div>

<?php if (!$rows): ?>
  <div class="panel" style="text-align:center;padding:34px">
    <div style="font-size:34px">🗂</div>
    <p class="muted" style="margin:8px 0 0">No jobs assigned to you yet.</p>
  </div>
<?php else: ?>

<?php if ($open): ?>
  <h3 class="tab-sub" style="margin-top:18px">To do <span class="muted">(<?= count($open) ?>)</span></h3>
  <div class="jobcards">
    <?php foreach ($open as $j) echo $jobCard($j); ?>
  </div>
<?php endif; ?>

<?php if ($closed): ?>
  <h3 class="tab-sub" style="margin-top:22px">Completed <span class="muted">(<?= count($closed) ?>)</span></h3>
  <div class="jobcards">
    <?php foreach (array_slice($closed,0,20) as $j) echo $jobCard($j); ?>
  </div>
<?php endif; ?>

<?php endif; ?>

<style>
  .jobcards{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;margin-top:10px}
  .jobcard{background:var(--card);border:1px solid var(--line);border-left:5px solid var(--warn);
    border-radius:var(--radius);padding:14px 16px;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:10px}
  .jobcard.is-late{border-left-color:var(--bad)}
  .jobcard.is-closed{border-left-color:var(--ok);opacity:.9}
  .jc-top{display:flex;justify-content:space-between;align-items:center;gap:10px}
  .jc-client{font-weight:700;font-size:16px;line-height:1.2}
  .jc-meta{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:13px;color:var(--muted)}
  .jc-code{font-weight:700;color:var(--brand)}
  .jc-actions{display:flex;gap:8px;margin-top:2px}
  .jc-actions .btn{flex:1;text-align:center;padding:11px}
  @media(max-width:820px){.jobcards{grid-template-columns:1fr}.jc-client{font-size:17px}}
</style>
