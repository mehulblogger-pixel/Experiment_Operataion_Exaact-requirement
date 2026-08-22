<?php
  // Universal Issue register + dashboard. NCR is one type among many. Reuses the
  // home dashboard's kpi-row/kpi language and links each row to the existing NCR
  // detail (/ncr-item) — it adds nothing to how a nonconformity is created.
  $sev = fn($s) => $s==='MAJOR'?'p-bad':($s==='MINOR'?'p-warn':'p-mut');
  $fmt = fn($s) => $s ? date('d M Y', strtotime($s)) : '—';
  $today = date('Y-m-d');
?>
<div class="crumbs"><a href="/">Home</a> › Issues</div>
<?php if (function_exists('nc_tabs')) nc_tabs('issues'); ?>
<div class="master-head">
  <div><h1>Issues</h1>
    <p class="sub" style="margin:2px 0 0">One register for every issue — nonconformity, observation, audit / inspection finding,
      deviation, discrepancy, complaint. An NCR is <em>one type</em> of issue, not the whole system. Deviations,
      concessions and waivers have their own controlled register.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/departures">Deviations &amp; concessions</a>
    <a class="btn secondary" href="/ncr">NCR register</a>
    <a class="btn secondary" href="/documents?cat=ISSUE">Issue reports</a>
  </div>
</div>

<!-- KPI row -->
<div class="kpi-row" style="margin-top:18px">
  <div class="kpi"><span class="kic">📋</span><div class="k">All issues</div>
    <div class="v"><a href="/issues?status=all"><?= (int)$stats['total'] ?></a></div>
    <div class="d"><?= (int)$stats['open'] ?> open</div></div>
  <div class="kpi"><span class="kic">⚠</span><div class="k">Major open</div>
    <div class="v"><a href="/issues?status=major"><?= (int)$stats['major'] ?></a></div>
    <div class="d"><?= (int)$stats['ncr'] ?> NCRs total</div></div>
  <div class="kpi"><span class="kic">⏰</span><div class="k">Overdue</div>
    <div class="v"><?= (int)$stats['overdue'] ? '<a href="/issues?status=overdue" class="down">'.(int)$stats['overdue'].'</a>' : '0' ?></div>
    <div class="d">Past their date</div></div>
  <div class="kpi"><span class="kic">🔀</span><div class="k">Deviations / concessions</div>
    <div class="v"><a href="/departures"><?= (int)$stats['deviation'] + (int)$stats['concession'] + (int)$stats['waiver'] ?></a></div>
    <div class="d"><?= (int)$stats['dep_pending'] ? '<span class="down">'.(int)$stats['dep_pending'].' awaiting approval</span>' : 'None pending' ?></div></div>
</div>

<!-- Type breakdown chips -->
<?php if ($breakdown): ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 4px">
  <a class="pill <?= $type===''?'p-info':'p-mut' ?>" href="/issues?status=<?= e($status) ?>" style="text-decoration:none">All types</a>
  <?php foreach ($breakdown as $code => $b): ?>
    <a class="pill <?= $type===$code?'p-info':'p-mut' ?>" href="/issues?type=<?= e($code) ?>&status=<?= e($status) ?>" style="text-decoration:none"><?= e($b['label']) ?> · <?= (int)$b['count'] ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Register -->
<section class="card" style="margin-top:14px">
  <form method="get" action="/issues" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <label>Show
      <select name="status" onchange="this.form.submit()">
        <?php foreach (['open'=>'Open','all'=>'All','major'=>'Major open','overdue'=>'Overdue','closed'=>'Closed'] as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $status===$k?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="flex:1;min-width:200px">Search <input type="search" name="q" value="<?= e($q) ?>" placeholder="reference, title, owner…"></label>
    <button class="btn secondary" type="submit">Filter</button>
  </form>

  <?php if (!$rows): ?>
    <p class="muted" style="margin:0">No issues<?= $type!==''||$q!==''?' match this filter':'' ?>. Raise one from an inspection, audit,
      expediting or deputation record, or directly from the <a href="/ncr">NCR register</a>.</p>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Reference</th><th>Type</th><th>Title</th><th>Vendor</th><th>Severity</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $od = $r['status']!=='CLOSED' && $r['due_on'] && $r['due_on']<$today; ?>
          <tr>
            <td><a href="/ncr-item?id=<?= (int)$r['id'] ?>"><b><?= e($r['ref']) ?></b></a></td>
            <td><span class="pill p-mut"><?= e($types[$r['issue_type'] ?? 'NCR'] ?? ($r['issue_type'] ?: 'NCR')) ?></span></td>
            <td><?= e($r['title']) ?></td>
            <td><?= e(($r['display_name'] ?? '') !== '' ? $r['display_name'] : ($r['legal_name'] ?? '')) ?: '—' ?></td>
            <td><span class="pill <?= $sev($r['severity']) ?>"><?= e(ucfirst(strtolower($r['severity']))) ?></span></td>
            <td style="white-space:nowrap"><?= $od ? '<span class="down">'.e($fmt($r['due_on'])).'</span>' : e($fmt($r['due_on'])) ?></td>
            <td><span class="pill <?= $r['status']==='CLOSED'?'p-ok':'p-warn' ?>"><?= e(function_exists('ncr_status_label')?ncr_status_label($r['status']):(NCR_STATUS[$r['status']]??$r['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
