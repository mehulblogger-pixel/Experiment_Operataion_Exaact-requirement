<div class="master-head">
  <div><h1>Job Register</h1><p class="sub">Allocated inspection jobs · <?= count($rows) ?> shown</p></div>
</div>
<form method="get" action="/jobs" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search job / client…">
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">All</option>
    <option value="open" <?= $filter==='open'?'selected':'' ?>>Open only</option>
    <option value="closed" <?= $filter==='closed'?'selected':'' ?>>Closed only</option>
  </select>
  <button class="btn secondary" type="submit">Search</button>
</form>
<table class="grid">
  <tr><th>Job</th><th>Client</th><th>Inspector</th><th>Scheduled</th><th>Freq.</th><th>Expected credit</th><th>TAT</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $j): ?>
  <tr>
    <td><a href="/job?id=<?= (int)$j['id'] ?>"><?= e($j['job_code']) ?></a></td>
    <td><?= e($j['client_disp'] ?: $j['client_name'] ?: '—') ?></td>
    <td><?= e($j['inspector_name'] ?: '—') ?></td>
    <td><?= e($j['scheduled_date'] ?: '—') ?></td>
    <td><?= e(REPORT_FREQ[$j['reporting_frequency']] ?? '—') ?></td>
    <td><?= fmoney($j['expected_credit']) ?></td>
    <td><?= $j['tat_days']===null?'—':(int)$j['tat_days'].'d' ?></td>
    <td><?= $j['closed_flag'] ? '<span class="badge GREEN">Closed</span>' : '<span class="badge AMBER">Open</span>' ?></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open</a>
      <?php if (!$j['closed_flag']): ?><a class="btn small" href="/job-close?id=<?= (int)$j['id'] ?>">Close</a><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9">No jobs. Allocate from a <a href="/calls">call</a>.</td></tr><?php endif; ?>
</table>
