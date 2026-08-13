<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>Details</span></div>
<?php if (($mode ?? '')==='kpi' && !empty($def)): ?>
  <div class="master-head"><div><h1><?= e($def['name']) ?></h1></div></div>
  <div class="panel">
    <div style="font-size:30px;font-weight:600"><?= e(($card['no_data']??false)?'No data':tapi_fmt_value($card['value'],$def['unit'])) ?></div>
    <div class="muted" style="margin-top:6px">Formula: <code><?= e($def['formula']) ?></code></div>
    <div class="muted" style="margin-top:10px"><b>Data lineage</b></div>
    <ul class="muted" style="font-size:13px">
      <?php foreach (($card['lineage']??[]) as $l): ?><li><code><?= e($l['metric']) ?></code> — <?= e($l['source']) ?> · <?= e($l['method']) ?></li><?php endforeach; ?>
    </ul>
    <div class="muted" style="font-size:12px">Computed <?= e(date('d M Y H:i', strtotime($card['refreshed_at']??'now'))) ?></div>
  </div>
<?php elseif (($mode ?? '')==='pick'): ?>
  <div class="master-head"><div><h1>Breakdown — <?= e($bd) ?></h1><p class="sub">Pick a slice to see the records behind it.</p></div></div>
  <div class="panel" style="padding:0;overflow:hidden"><table class="dt"><thead><tr><th><?= e($bd) ?></th><th>Count</th><th></th></tr></thead><tbody>
    <?php foreach (($data??[]) as $k=>$v): ?><tr><td><?= e($k) ?></td><td><?= (int)$v ?></td>
      <td><a class="btn btn-sm secondary" href="/analytics-drill?bd=<?= e($bd) ?>&key=<?= urlencode($k) ?><?= e(tapi_qs($filters)) ?>">Records →</a></td></tr><?php endforeach; ?>
    <?php if (empty($data)): ?><tr><td colspan="3" class="muted" style="text-align:center;padding:22px">No data for this period.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php else: ?>
  <div class="master-head"><div><h1><?= e($bd) ?> — <?= e($key ?? '') ?></h1><p class="sub"><?= count($rows ?? []) ?> record(s) · these are exactly the rows the KPI counted</p></div></div>
  <div class="panel" style="padding:0;overflow:hidden"><div class="tbl-scroll" style="overflow-x:auto"><table class="dt">
    <thead><tr><?php foreach (($cols??[]) as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach (($rows??[]) as $r): ?><tr><?php foreach ($r as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="<?= max(1,count($cols??[])) ?>" class="muted" style="text-align:center;padding:22px">No records.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
<?php endif; ?>
