<?php
  $today = date('Y-m-d');
  $seeCredit = can('data.credit');
  // quick counts over the shown set
  $nOpen = 0; $nOverdue = 0; $nClosed = 0;
  foreach ($rows as $j) {
    if ($j['closed_flag']) { $nClosed++; }
    else { $nOpen++; $end = $j['inspection_end_date'] ?: $j['scheduled_date']; if ($end && $end < $today) $nOverdue++; }
  }
?>
<div class="master-head">
  <div><h1>Job Register</h1>
  <p class="sub" style="margin:2px 0 0">Allocated inspection jobs — open one to schedule, record the report, or close it.</p></div>
  <a class="btn secondary" href="/jobs?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ Download CSV</a>
</div>

<div class="chip-row">
  <span class="ct">Showing <b><?= count($rows) ?></b></span>
  <span class="ct"><span class="dot dot-warn"></span><?= $nOpen ?> open</span>
  <?php if ($nOverdue): ?><span class="ct"><span class="dot dot-bad"></span><?= $nOverdue ?> overdue</span><?php endif; ?>
  <span class="ct"><span class="dot dot-ok"></span><?= $nClosed ?> closed</span>
</div>

<form method="get" action="/jobs" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="🔍 Search job code or client…">
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <option value="open" <?= $filter==='open'?'selected':'' ?>>Open only</option>
    <option value="closed" <?= $filter==='closed'?'selected':'' ?>>Closed only</option>
  </select>
  <button class="btn secondary" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">🗂</div>
      <p class="muted" style="margin:8px 0 0">No jobs yet. Allocate one from a <a href="/calls">call</a>.</p></div>
  <?php else: ?>
  <table class="dt">
    <thead><tr>
      <th>Job</th><th>Client</th><th>Inspector</th><th>Scheduled</th>
      <?php if ($seeCredit): ?><th class="num">Expected credit</th><?php endif; ?>
      <th class="num">TAT</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $j):
      $end = $j['inspection_end_date'] ?: $j['scheduled_date'];
      $overdue = !$j['closed_flag'] && $end && $end < $today;
      // money sub-status (only meaningful once closed)
      $money = '';
      if ($j['closed_flag']) {
        if (empty($j['invoice_raised'])) $money = ['Unbilled','p-warn'];
        elseif (empty($j['payment_received'])) $money = ['Awaiting pay','p-info'];
        else $money = ['Paid','p-ok'];
      }
    ?>
      <tr>
        <td><a href="/job?id=<?= (int)$j['id'] ?>"><b><?= e($j['job_code']) ?></b></a></td>
        <td><?= e($j['client_disp'] ?: $j['client_name'] ?: '—') ?></td>
        <td><?= $j['inspector_name'] ? e($j['inspector_name']) : '<span class="muted">—</span>' ?></td>
        <td><?= $j['scheduled_date'] ? ($overdue ? '<span class="down" style="font-weight:600">'.e($j['scheduled_date']).'</span>' : e($j['scheduled_date'])) : '<span class="muted">—</span>' ?></td>
        <?php if ($seeCredit): ?><td class="num"><?= $j['expected_credit'] ? fmoney($j['expected_credit']) : '<span class="muted">—</span>' ?></td><?php endif; ?>
        <td class="num"><?= $j['tat_days']===null ? '<span class="muted">—</span>' : (int)$j['tat_days'].'d' ?></td>
        <td>
          <?php if ($j['closed_flag']): ?><span class="pill p-ok">Closed</span>
          <?php elseif ($overdue): ?><span class="pill p-bad">Overdue</span>
          <?php else: ?><span class="pill p-warn">Open</span><?php endif; ?>
          <?php if ($seeCredit && $money): ?><span class="pill <?= $money[1] ?>" style="margin-left:4px"><?= $money[0] ?></span><?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open</a>
          <?php if (!$j['closed_flag']): ?><a class="btn small" href="/job-close?id=<?= (int)$j['id'] ?>">Close</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
