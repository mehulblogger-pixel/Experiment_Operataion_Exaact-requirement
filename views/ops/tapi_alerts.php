<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>Alerts</span></div>
<div class="master-head"><div>
  <h1>Alerts</h1>
  <p class="sub" style="margin:2px 0 0">Rule-based watches on any KPI. Breaches are e-mailed by the nightly job, once per rule per day.</p>
</div></div>

<?php if ($firing): ?>
<div class="panel" style="border-left:4px solid var(--bad)">
  <b>Firing now (<?= count($firing) ?>)</b>
  <ul style="margin:8px 0 0">
    <?php foreach ($firing as $f): ?><li><span class="pill p-<?= $f['alert']['severity']==='CRITICAL'||$f['alert']['severity']==='HIGH'?'bad':'warn' ?>"><?= e($f['alert']['severity']) ?></span> <?= e($f['alert']['name']) ?> — <?= e($f['message']) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php else: ?>
<div class="panel" style="border-left:4px solid var(--ok)"><b>Nothing firing right now.</b></div>
<?php endif; ?>

<div class="panel">
  <h3 style="margin:0 0 10px;font-size:15px">New / edit a rule</h3>
  <form method="post" action="/analytics-alerts" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end">
    <input type="hidden" name="act" value="save"><input type="hidden" name="id" value="0">
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">Name</label><input class="form-control" name="name" required></div>
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">KPI</label>
      <select class="form-control" name="kpi_key"><?php foreach ($kpis as $k): ?><option value="<?= e($k['kpi_key']) ?>"><?= e($k['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">Condition</label>
      <select class="form-control" name="op"><?php foreach ($ops as $k=>$l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">Threshold</label><input class="form-control" name="threshold" required></div>
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">Severity</label>
      <select class="form-control" name="severity"><?php foreach ($sev as $k=>$l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="muted" style="display:block;font-size:12px;margin-bottom:3px">Email to</label><input class="form-control" name="recipients" placeholder="a@x.com, b@y.com"></div>
    <div><label style="font-size:13px"><input type="checkbox" name="enabled" value="1" checked> Enabled</label></div>
    <div><button class="btn" type="submit">Save rule</button></div>
  </form>
</div>

<div class="panel" style="padding:0;overflow:hidden"><div class="tbl-scroll" style="overflow-x:auto">
<table class="dt">
  <thead><tr><th>Rule</th><th>KPI</th><th>Condition</th><th>Severity</th><th>Recipients</th><th>State</th></tr></thead>
  <tbody>
  <?php foreach ($alerts as $a): ?>
    <tr>
      <td><b><?= e($a['name']) ?></b></td><td><?= e($a['kpi_key']) ?></td>
      <td><?= e($ops[$a['op']] ?? $a['op']) ?> <?= e($a['threshold']) ?></td>
      <td><?= e($sev[$a['severity']] ?? $a['severity']) ?></td>
      <td class="muted" style="font-size:12.5px"><?= e($a['recipients'] ?: '—') ?></td>
      <td><?php if ($a['enabled']): ?><span class="pill p-ok">on</span><?php else: ?><span class="pill p-mut">off</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$alerts): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:22px">No rules yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
