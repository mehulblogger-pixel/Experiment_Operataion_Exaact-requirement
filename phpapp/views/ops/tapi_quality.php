<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>Data quality</span></div>
<div class="master-head"><div>
  <h1>Data quality</h1>
  <p class="sub" style="margin:2px 0 0">Weak data distorts every KPI. These checks surface it rather than letting analytics quietly mislead.</p>
</div><div><form method="post" action="/analytics-quality"><button class="btn">Re-run checks</button></form></div></div>

<div class="tapi-kpi-row" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:18px">
  <div class="panel"><div class="muted" style="font-size:12.5px">Checks</div><div style="font-size:26px;font-weight:600"><?= (int)$summary['total'] ?></div></div>
  <div class="panel" style="border-left:4px solid var(--<?= $summary['failed']?'bad':'ok' ?>)"><div class="muted" style="font-size:12.5px">Failing</div><div style="font-size:26px;font-weight:600"><?= (int)$summary['failed'] ?></div></div>
  <div class="panel"><div class="muted" style="font-size:12.5px">Skipped</div><div style="font-size:26px;font-weight:600"><?= (int)$summary['skipped'] ?></div></div>
</div>

<div class="panel" style="padding:0;overflow:hidden"><div class="tbl-scroll" style="overflow-x:auto">
<table class="dt">
  <thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead>
  <tbody>
  <?php foreach ($checks as $c): ?>
    <tr>
      <td><?= e($c['label'] ?? ($c['name'] ?? '—')) ?></td>
      <td><?php if (!empty($c['skipped'])): ?><span class="pill p-mut">skipped</span>
          <?php elseif (!empty($c['ok'])): ?><span class="pill p-ok">ok</span>
          <?php else: ?><span class="pill p-bad">failing</span><?php endif; ?></td>
      <td class="muted" style="font-size:13px"><?= e($c['detail'] ?? ($c['note'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$checks): ?><tr><td colspan="3" class="muted" style="text-align:center;padding:22px">No checks registered.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
