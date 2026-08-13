<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>Management review</span></div>
<div class="master-head"><div>
  <h1>Management review</h1>
  <p class="sub" style="margin:2px 0 0"><?= e($review['period']['from'] ?: 'all time') ?> — <?= e($review['period']['to'] ?: 'now') ?> · generated <?= e(date('d M Y H:i', strtotime($review['generated']))) ?></p>
</div><div style="display:flex;gap:8px">
  <a class="btn secondary" href="/analytics-export?fmt=xlsx<?= e(tapi_qs($filters)) ?>">Export XLSX</a>
  <a class="btn secondary" href="/analytics-export?fmt=csv<?= e(tapi_qs($filters)) ?>">CSV</a>
  <a class="btn secondary" onclick="window.print()">Print / PDF</a>
</div></div>

<?php if ($ai_on): ?>
<div class="panel" style="border-left:4px solid var(--info)">
  <b>Factual summary</b>
  <?php if ($ai): ?><div style="white-space:pre-wrap;line-height:1.6;margin-top:8px"><?= e($ai) ?></div>
  <?php elseif ($aierr): ?><div class="muted" style="margin-top:6px"><?= e($aierr) ?></div>
  <?php else: ?><p class="muted" style="margin:6px 0 0">Generated from the KPI figures only — no invented cause. <a href="/analytics-review?ai=1<?= e(tapi_qs($filters)) ?>">Generate summary</a></p><?php endif; ?>
</div>
<?php endif; ?>

<h3 style="font-size:15px;margin:18px 0 8px">Key performance indicators</h3>
<div class="panel" style="padding:0;overflow:hidden"><div class="tbl-scroll" style="overflow-x:auto"><table class="dt">
  <thead><tr><th>KPI</th><th>Value</th><th>Change</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($review['kpis'] as $k): $c=$k['card']; $cmp=$k['cmp']; $tone=tapi_status_tone($c['status']); ?>
    <tr>
      <td><b><?= e($k['def']['name']) ?></b></td>
      <td><?= e(($c['no_data']??false)?'No data':tapi_fmt_value($c['value'],$k['def']['unit'])) ?></td>
      <td><?= $cmp['variance_pct']===null ? '<span class="muted">—</span>' : e(($cmp['variance_pct']>=0?'+':'').$cmp['variance_pct'].'% '.$cmp['label']) ?></td>
      <td><span class="pill p-<?= $tone==='ok'?'ok':($tone==='bad'?'bad':($tone==='warn'?'warn':'mut')) ?>"><?= e($c['status']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<?php if ($review['scorecard'] && $review['scorecard']['total']!==null): ?>
<h3 style="font-size:15px;margin:18px 0 8px">Scorecard</h3>
<div class="panel"><b style="font-size:22px"><?= e($review['scorecard']['total']) ?></b> <span class="muted">/ 100 overall</span></div>
<?php endif; ?>

<?php if ($review['alerts']): ?>
<h3 style="font-size:15px;margin:18px 0 8px">Alerts firing</h3>
<div class="panel"><ul style="margin:0"><?php foreach ($review['alerts'] as $a): ?><li><?= e($a['message']) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<h3 style="font-size:15px;margin:18px 0 8px">Data quality</h3>
<div class="panel"><?= (int)$review['quality']['total'] ?> checks · <b style="color:var(--<?= $review['quality']['failed']?'bad':'ok' ?>)"><?= (int)$review['quality']['failed'] ?> failing</b></div>

<p class="muted" style="font-size:12px;margin-top:16px;border-top:1px solid var(--line);padding-top:10px"><?= e($review['caveat']) ?></p>
