<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>KPI library</span></div>
<div class="master-head"><div>
  <h1>KPI library</h1>
  <p class="sub" style="margin:2px 0 0">Every KPI is a configurable definition — name, formula, unit, target, direction. Edit one and the change flows to every dashboard.</p>
</div><div><a class="btn" href="/analytics-kpi-edit">+ New KPI</a></div></div>

<div class="panel" style="padding:0;overflow:hidden"><div class="tbl-scroll" style="overflow-x:auto">
<table class="dt">
  <thead><tr><th>KPI</th><th>Category</th><th>Formula</th><th>Now</th><th>Target</th><th>Dir</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $d=$r['def']; $c=$r['card']; $tone=tapi_status_tone($c['status']); ?>
    <tr>
      <td><b><?= e($d['name']) ?></b><div class="muted" style="font-size:11.5px"><?= e($d['kpi_key']) ?></div></td>
      <td><?= e(TAPI_CATEGORIES[$d['category']] ?? $d['category']) ?></td>
      <td><code style="font-size:12px"><?= e($d['formula']) ?></code></td>
      <td><b><?= e(($c['no_data'] ?? false) ? 'No data' : tapi_fmt_value($c['value'], $d['unit'])) ?></b></td>
      <td><?= $d['target']!==null && $d['target']!=='' ? e(tapi_fmt_value($d['target'],$d['unit'])) : '<span class="muted">—</span>' ?></td>
      <td><span class="muted" style="font-size:12px"><?= e(['HIGHER'=>'↑','LOWER'=>'↓','RANGE'=>'↔','TARGET'=>'◎','INFO'=>'ℹ'][$d['direction']] ?? '') ?></span></td>
      <td><span class="pill p-<?= $tone==='ok'?'ok':($tone==='bad'?'bad':($tone==='warn'?'warn':'mut')) ?>"><?= e($c['status']) ?></span></td>
      <td><a class="btn btn-sm secondary" href="/analytics-kpi-edit?key=<?= e($d['kpi_key']) ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<p class="muted" style="font-size:12.5px">Available metrics a formula may reference: <code><?= e(implode('  ', $metrics)) ?></code></p>
