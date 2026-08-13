<?php $d = $def ?: ['kpi_key'=>'','name'=>'','category'=>'OPERATIONS','formula'=>'','unit'=>'count','period'=>'MONTH','direction'=>'INFO','target'=>'','threshold'=>'','status'=>'ACTIVE','description'=>'']; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/analytics-kpis">KPI library</a> › <span><?= e($d['name'] ?: 'New KPI') ?></span></div>
<div class="master-head"><div><h1><?= e($d['name'] ?: 'New KPI') ?></h1></div></div>
<form method="post" action="/analytics-kpi-edit" class="panel" style="max-width:720px">
  <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr">
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Key (stable id)</label>
      <input class="form-control" name="kpi_key" value="<?= e($d['kpi_key']) ?>" <?= $def?'readonly':'' ?> required></div>
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Name</label>
      <input class="form-control" name="name" value="<?= e($d['name']) ?>" required></div>
  </div>
  <label class="muted" style="display:block;font-size:12.5px;margin:12px 0 4px">Formula <span style="opacity:.7">(compose the metrics below; e.g. <code>round(reports.issued / reports.total * 100, 1)</code>)</span></label>
  <input class="form-control" name="formula" value="<?= e($d['formula']) ?>" style="font-family:monospace">
  <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr 1fr 1fr;margin-top:12px">
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Category</label>
      <select class="form-control" name="category"><?php foreach (TAPI_CATEGORIES as $k=>$l): ?><option value="<?= e($k) ?>" <?= $d['category']===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Unit</label>
      <select class="form-control" name="unit"><?php foreach (['count','%','days','hours','money'] as $u): ?><option <?= $d['unit']===$u?'selected':'' ?>><?= e($u) ?></option><?php endforeach; ?></select></div>
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Direction</label>
      <select class="form-control" name="direction"><?php foreach (TAPI_DIRECTIONS as $k=>$l): ?><option value="<?= e($k) ?>" <?= $d['direction']===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Period</label>
      <select class="form-control" name="period"><?php foreach (TAPI_PERIODS as $k=>$l): ?><option value="<?= e($k) ?>" <?= $d['period']===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  </div>
  <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr 1fr;margin-top:12px">
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Target <span style="opacity:.7">(blank = informational)</span></label>
      <input class="form-control" name="target" value="<?= e($d['target']) ?>"></div>
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Warn threshold</label>
      <input class="form-control" name="threshold" value="<?= e($d['threshold']) ?>"></div>
    <div><label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Status</label>
      <select class="form-control" name="status"><?php foreach (['ACTIVE','INACTIVE'] as $s): ?><option <?= $d['status']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
  </div>
  <div style="margin-top:18px;display:flex;gap:10px"><button class="btn">Save</button><a class="btn secondary" href="/analytics-kpis">Cancel</a></div>
</form>
<div class="panel"><b>Metrics you can reference</b>
  <div class="muted" style="font-size:12.5px;margin-top:8px;line-height:1.9">
  <?php foreach ($metrics as $k=>$m): ?><code><?= e($k) ?></code> — <?= e($m['label']) ?> <span style="opacity:.6">(<?= e($m['source']) ?>)</span><br><?php endforeach; ?>
  </div>
</div>
