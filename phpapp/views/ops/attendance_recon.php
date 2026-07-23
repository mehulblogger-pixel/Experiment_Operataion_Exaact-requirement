<div class="master-head">
  <div><h1>Attendance reconciliation</h1>
    <p class="sub">Cross-check the inspector vouchers against your HR payroll export. The uploaded file is read <strong>in memory only and never stored</strong> — we keep just the comparison result.</p></div>
</div>

<?php if ($error): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="/attendance-recon" enctype="multipart/form-data" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Month</label><input class="form-control" type="month" name="month" value="<?= e($month) ?>"></div>
    <div class="ff ff-wide"><label>HR export (save the payroll Leave &amp; Attendance report as <strong>.csv</strong>)</label>
      <input class="form-control" type="file" name="hr" accept=".csv,text/csv" required></div>
  </div>
  <p class="muted" style="margin:4px 2px">Needs an <strong>Employee Code</strong> column and a <strong>Present</strong> and/or <strong>Leave</strong> days column. We match by Employee Code to the inspector master.</p>
  <div style="margin-top:10px"><button class="btn" type="submit">Reconcile</button></div>
</form>

<?php if ($result): ?>
<div class="panel">
  <h3 class="tab-sub">Result — <?= e($month) ?> <span class="muted">(HR: <?= (int)$result['hrCount'] ?> · App: <?= (int)$result['oursCount'] ?>)</span></h3>
  <?php $mm = array_filter($result['rows'], fn($r)=>$r['flag']!=='OK'); ?>
  <?php if (!$mm): ?><div class="msg msg-success">All matched employees agree between HR and the vouchers. ✅</div><?php endif; ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>Emp code</th><th>Name</th><th>HR present</th><th>App present</th><th>HR leave</th><th>App leave</th><th>Status</th></tr>
    <?php foreach ($result['rows'] as $r):
      $badge = $r['flag']==='OK' ? 'GREEN' : ($r['flag']==='MISMATCH' ? 'RED' : 'AMBER'); ?>
    <tr>
      <td><?= e($r['code']) ?></td>
      <td><?= e($r['name'] ?: '—') ?></td>
      <td><?= $r['hrP']===null?'—':e(rtrim(rtrim((string)$r['hrP'],'0'),'.') ?: '0') ?></td>
      <td<?= ($r['flag']==='MISMATCH' && (float)$r['hrP']!==(float)$r['ourP'])?' style="background:#fdecea"':'' ?>><?= $r['ourP']===null?'—':(int)$r['ourP'] ?></td>
      <td><?= $r['hrL']===null?'—':e(rtrim(rtrim((string)$r['hrL'],'0'),'.') ?: '0') ?></td>
      <td<?= ($r['flag']==='MISMATCH' && (float)$r['hrL']!==(float)$r['ourL'])?' style="background:#fdecea"':'' ?>><?= $r['ourL']===null?'—':(int)$r['ourL'] ?></td>
      <td><span class="badge <?= $badge ?>"><?= e($r['flag']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$result['rows']): ?><tr><td colspan="7">No employees to compare.</td></tr><?php endif; ?>
  </table>
  </div>
  <p class="muted" style="margin-top:8px">“App present” counts distinct days an inspector logged Work/Office/WFH/Training on their voucher; “App leave” counts distinct Leave days. Red cells show where HR and the voucher disagree.</p>
</div>
<?php endif; ?>
