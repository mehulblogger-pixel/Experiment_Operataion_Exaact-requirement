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
      <?php $holds = function_exists('job_hold_reasons') ? job_hold_reasons($j) : []; if ($holds): ?>
      <div style="margin:6px 0;padding:8px 10px;border:1px solid var(--bad);border-radius:8px;background:color-mix(in srgb,var(--bad) 10%,transparent);font-size:12.5px">
        🚫 <b>HOLD — do not issue the report/deliverable:</b> <?= e(implode('; ', $holds)) ?>.
      </div>
      <?php endif; ?>
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

<?php // §WO-5 — week & month calendar of this engineer's scheduled visits
  if (($sched ?? null) !== null):
    $ym = ($ym ?? '') ?: date('Y-m');
    $ymTs = strtotime($ym . '-01');
    $monthLabel = date('F Y', $ymTs);
    $prevYm = date('Y-m', strtotime($ym . '-01 -1 month'));
    $nextYm = date('Y-m', strtotime($ym . '-01 +1 month'));
    $daysIn = (int)date('t', $ymTs);
    $lead   = ((int)date('N', $ymTs)) - 1;              // Mon=0 … Sun=6
    $todayStr = date('Y-m-d');
    $chip = function($e){
      $tone = $e['closed'] ? 'p-ok' : ($e['done'] ? 'p-ok' : 'p-warn');
      $lab = $e['code'] . ' · ' . ($e['client'] ?: '—');
      return '<a class="cal-chip ' . $tone . '" href="/job?id=' . (int)$e['job_id'] . '" title="' . htmlspecialchars($lab, ENT_QUOTES) . ($e['site'] ? ' @ ' . htmlspecialchars($e['site'], ENT_QUOTES) : '') . '">' . htmlspecialchars(mb_strimwidth($lab, 0, 22, '…'), ENT_QUOTES) . '</a>';
    };
?>
<div class="panel" style="margin-top:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3 class="tab-sub" style="margin:0">My schedule</h3>
    <div style="display:flex;gap:6px;align-items:center">
      <a class="btn small secondary" href="/my-jobs?ym=<?= e($prevYm) ?>">←</a>
      <b><?= e($monthLabel) ?></b>
      <a class="btn small secondary" href="/my-jobs?ym=<?= e($nextYm) ?>">→</a>
      <?php if ($ym !== date('Y-m')): ?><a class="btn small" href="/my-jobs">This month</a><?php endif; ?>
    </div>
  </div>

  <?php // This week (today → +6 days)
    $weekStart = strtotime('today'); $weekAny = false; ?>
  <div style="margin-top:10px">
    <div class="muted" style="font-size:12px;margin-bottom:4px">This week</div>
    <div class="cal-week">
      <?php for ($i = 0; $i < 7; $i++): $d = date('Y-m-d', strtotime("+$i day", $weekStart)); $es = $sched[$d] ?? []; if ($es) $weekAny = true; ?>
        <div class="cal-wday<?= $d===$todayStr?' is-today':'' ?>">
          <div class="cal-wd-h"><?= date('D', strtotime($d)) ?> <span class="muted"><?= date('j/n', strtotime($d)) ?></span></div>
          <?php foreach ($es as $e) echo $chip($e); ?>
          <?php if (!$es): ?><span class="muted" style="font-size:11px">—</span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <?php if (!$weekAny): ?><p class="muted" style="font-size:12.5px;margin:6px 0 0">Nothing scheduled for you in the next 7 days.</p><?php endif; ?>
  </div>

  <?php // Month grid ?>
  <div class="cal-grid" style="margin-top:14px">
    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dn): ?><div class="cal-head"><?= $dn ?></div><?php endforeach; ?>
    <?php for ($b = 0; $b < $lead; $b++): ?><div class="cal-cell is-blank"></div><?php endfor; ?>
    <?php for ($day = 1; $day <= $daysIn; $day++):
      $d = sprintf('%s-%02d', $ym, $day); $es = $sched[$d] ?? []; ?>
      <div class="cal-cell<?= $d===$todayStr?' is-today':'' ?>">
        <div class="cal-daynum"><?= $day ?></div>
        <?php foreach (array_slice($es, 0, 4) as $e) echo $chip($e); ?>
        <?php if (count($es) > 4): ?><span class="muted" style="font-size:11px">+<?= count($es)-4 ?> more</span><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
  <small class="muted">Green = completed/closed · amber = still to do. Tap a job to open it.</small>
</div>
<?php endif; ?>

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
  /* §WO-5 calendar */
  .cal-week{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
  .cal-wday{border:1px solid var(--line);border-radius:8px;padding:6px;min-height:64px;background:var(--card);display:flex;flex-direction:column;gap:4px}
  .cal-wday.is-today{border-color:var(--brand);box-shadow:0 0 0 1px var(--brand) inset}
  .cal-wd-h{font-size:12px;font-weight:700}
  .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
  .cal-head{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);text-align:center;padding:2px 0}
  .cal-cell{border:1px solid var(--line);border-radius:8px;padding:5px;min-height:78px;background:var(--card);display:flex;flex-direction:column;gap:3px}
  .cal-cell.is-blank{border:0;background:transparent}
  .cal-cell.is-today{border-color:var(--brand);box-shadow:0 0 0 1px var(--brand) inset}
  .cal-daynum{font-size:12px;font-weight:700;color:var(--muted)}
  .cal-chip{display:block;font-size:11px;padding:2px 6px;border-radius:6px;text-decoration:none;line-height:1.35;
    background:color-mix(in srgb,var(--warn) 16%,transparent);color:var(--ink);border:1px solid color-mix(in srgb,var(--warn) 40%,transparent)}
  .cal-chip.p-ok{background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 40%,transparent)}
  @media(max-width:820px){.cal-week{grid-template-columns:repeat(7,1fr)}.cal-chip{font-size:10px}.cal-cell{min-height:60px}}
</style>
