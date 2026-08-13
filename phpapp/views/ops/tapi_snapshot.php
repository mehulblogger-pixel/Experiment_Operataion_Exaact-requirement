<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>Periods &amp; snapshots</span></div>
<div class="master-head"><div>
  <h1>Periods &amp; snapshots</h1>
  <p class="sub" style="margin:2px 0 0">Freeze a period's figures so a finalised management report stays reproducible even as live data moves on.</p>
</div></div>

<div class="panel">
  <h3 style="margin:0 0 10px;font-size:15px">Snapshot a period</h3>
  <form method="post" action="/analytics-snapshot" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="act" value="snapshot">
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">Month (YYYY-MM)</label>
      <input class="form-control" name="period_key" value="<?= e($thisMonth) ?>" pattern="[0-9]{4}-[0-9]{2}" required></div>
    <div><button class="btn" type="submit">Take snapshot</button></div>
    <div class="muted" style="font-size:12.5px;max-width:44ch">Every active KPI is computed and stored with the version of its definition in effect. A locked period cannot be re-snapshotted.</div>
  </form>
</div>

<div class="panel" style="padding:0;overflow:hidden"><div style="padding:14px 16px 0"><h3 style="margin:0;font-size:15px">Period status</h3></div>
<div class="tbl-scroll" style="overflow-x:auto"><table class="dt">
  <thead><tr><th>Period</th><th>Snapshots</th><th>Last taken</th><th>State</th><th>Change state</th></tr></thead>
  <tbody>
  <?php
    $byKey = []; foreach ($recent as $r) $byKey[$r['period_key']] = $r;
    $stateByKey = []; foreach ($periods as $p) $stateByKey[$p['period_key']] = $p['state'];
    $keys = array_unique(array_merge(array_keys($byKey), array_keys($stateByKey))); rsort($keys);
  ?>
  <?php foreach ($keys as $k): $st=$stateByKey[$k]??'OPEN'; $sn=$byKey[$k]??null; ?>
    <tr>
      <td><b><?= e($k) ?></b></td>
      <td><?= $sn ? (int)$sn['n'] : 0 ?></td>
      <td><?= $sn && $sn['taken_at'] ? e(date('d M Y H:i', strtotime($sn['taken_at']))) : '<span class="muted">—</span>' ?></td>
      <td><span class="pill p-<?= in_array($st,['FINAL','LOCKED'])?'ok':'warn' ?>"><?= e($states[$st] ?? $st) ?></span></td>
      <td><form method="post" action="/analytics-snapshot" style="display:flex;gap:6px">
        <input type="hidden" name="act" value="state"><input type="hidden" name="period_key" value="<?= e($k) ?>">
        <select class="form-control" name="state" style="max-width:150px"><?php foreach ($states as $sk=>$sl): ?><option value="<?= e($sk) ?>" <?= $st===$sk?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm secondary" type="submit">Set</button>
      </form></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$keys): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:22px">No snapshots yet.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
