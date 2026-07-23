<?php
  $statusBadge = ['DRAFT'=>'AMBER','SUBMITTED'=>'AMBER','APPROVED'=>'GREEN','PAID'=>'GREEN'];
  // group entries by date for per-day hour subtotals
  $byDate = [];
  foreach ($entries as $e) $byDate[$e['entry_date']][] = $e;
  ksort($byDate);
  $monthHours = 0;
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/vouchers">Vouchers</a> › <?= e($v['month']) ?></div>
<div class="master-head">
  <div><h1>Statement of Travelling Expenses
      <span class="badge <?= $statusBadge[$v['status']] ?? 'AMBER' ?>" style="vertical-align:middle"><?= e($v['status']) ?></span></h1>
    <p class="sub"><strong><?= e($v['inspector_name']) ?></strong><?= $v['emp_code']?' · '.e($v['emp_code']):'' ?> · Month <?= e($v['month']) ?> · SBU <?= e(lk_options_or('sbu',OPS_SBUS)[$v['sbu']] ?? $v['sbu'] ?: '—') ?></p></div>
  <a class="btn secondary" href="/vouchers">← Back</a>
</div>

<?php if ($canEdit): ?>
<div class="panel" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
  <form method="post" action="/voucher-generate?id=<?= (int)$v['id'] ?>">
    <button class="btn" type="submit">↻ Pull working days from jobs</button>
    <p class="muted" style="margin:6px 2px 0;max-width:280px">Auto-fills each inspection day (date, site, File No/BOSS, SBU) from the jobs allotted to this inspector. Safe to click again — it won't duplicate.</p>
  </form>
  <form method="post" action="/voucher-entry?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
    <input type="hidden" name="_do" value="add">
    <div class="ff"><label>Add a non-inspection day</label><input class="form-control" type="date" name="entry_date" required></div>
    <div class="ff"><label>Type</label>
      <select class="form-control" name="day_type" id="add_daytype">
        <option value="OFFICE">In office</option><option value="LEAVE">Leave</option>
        <option value="HOLIDAY">Holiday</option><option value="WEEKOFF">Week-off</option>
      </select></div>
    <div class="ff" id="add_office"><label>Office code</label>
      <select class="form-control" name="office_code"><option value="">—</option><?php foreach ($dayOpts as $k=>$vv): ?><option value="<?= e($k) ?>"><?= e($vv) ?></option><?php endforeach; ?></select></div>
    <div class="ff" id="add_leave" style="display:none"><label>Leave code</label>
      <select class="form-control" name="leave_code"><option value="">—</option><?php foreach ($leaveOpts as $k=>$vv): ?><option value="<?= e($k) ?>"><?= e($vv) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Hours</label><input class="form-control" style="width:90px" type="number" step="0.25" name="hours" value="0"></div>
    <button class="btn small" type="submit">Add day</button>
  </form>
</div>
<?php endif; ?>

<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid">
  <tr><th>Date</th><th>Attendance / Site</th><th>File No (BOSS)</th><th>Line No</th><th>SBU</th><th>Hours</th><?php if ($canEdit): ?><th>Save / remove</th><?php endif; ?></tr>
  <?php foreach ($byDate as $date => $rows): $dayHours = 0; ?>
    <?php foreach ($rows as $e): $dayHours += (float)$e['hours']; $monthHours += (float)$e['hours'];
      $isWork = $e['day_type']==='WORK';
      $att = $isWork ? ($e['site_label'] ?: '(site)') : ($e['day_type']==='LEAVE' ? ('Leave'.($e['leave_code']?' · '.($leaveOpts[$e['leave_code']]??$e['leave_code']):'')) : ($e['day_type']==='OFFICE' ? ('Office'.($e['office_code']?' · '.($dayOpts[$e['office_code']]??$e['office_code']):'')) : ucfirst(strtolower($e['day_type'])))); ?>
    <tr>
      <?php if ($canEdit): $fid = 've_'.(int)$e['id']; ?>
      <td><form id="<?= $fid ?>" method="post" action="/voucher-entry?id=<?= (int)$v['id'] ?>"><input type="hidden" name="_do" value="update"><input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>"></form><?= e(date('d-M', strtotime($date))) ?><?= $e['is_auto']?' <span class="badge GREEN" style="font-size:10px">auto</span>':'' ?></td>
      <td><?= e($att) ?></td>
      <td><input form="<?= $fid ?>" class="form-control" style="width:120px" name="file_no" value="<?= e($e['file_no']) ?>" <?= $isWork?'':'readonly' ?>></td>
      <td><input form="<?= $fid ?>" class="form-control" style="width:90px" name="line_no" value="<?= e($e['line_no']) ?>" placeholder="from accounts"></td>
      <td><?= e(lk_options_or('sbu',OPS_SBUS)[$e['sbu']] ?? $e['sbu'] ?: '—') ?></td>
      <td><input form="<?= $fid ?>" class="form-control" style="width:80px" type="number" step="0.25" name="hours" value="<?= e(rtrim(rtrim((string)$e['hours'],'0'),'.') ?: '0') ?>"></td>
      <td class="row-actions">
        <button form="<?= $fid ?>" class="btn small" type="submit">Save</button>
        <form method="post" action="/voucher-entry?id=<?= (int)$v['id'] ?>" style="display:inline" onsubmit="return confirm('Remove this row?')">
          <input type="hidden" name="_do" value="del"><input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
          <button class="btn small danger" type="submit">✕</button>
        </form>
      </td>
      <?php else: ?>
      <td><?= e(date('d-M', strtotime($date))) ?></td>
      <td><?= e($att) ?></td>
      <td><?= e($e['file_no'] ?: '—') ?></td>
      <td><?= e($e['line_no'] ?: '—') ?></td>
      <td><?= e(lk_options_or('sbu',OPS_SBUS)[$e['sbu']] ?? $e['sbu'] ?: '—') ?></td>
      <td><?= e(rtrim(rtrim((string)$e['hours'],'0'),'.') ?: '0') ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (count($rows) > 1): ?><tr class="muted" style="font-size:12px"><td></td><td colspan="<?= $canEdit?6:5 ?>">↳ <?= e($date) ?> total hours: <strong><?= e(rtrim(rtrim((string)$dayHours,'0'),'.') ?: '0') ?></strong></td></tr><?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$entries): ?><tr><td colspan="<?= $canEdit?7:6 ?>">No days yet. <?= $canEdit?'Click “Pull working days from jobs”.':'' ?></td></tr><?php endif; ?>
  <?php if ($entries): ?><tr><td colspan="5" style="text-align:right"><strong>Total hours (month)</strong></td><td><strong><?= e(rtrim(rtrim((string)$monthHours,'0'),'.') ?: '0') ?></strong></td><?php if ($canEdit): ?><td></td><?php endif; ?></tr><?php endif; ?>
</table>
</div>
<p class="muted" style="margin-top:8px">KM, travel charges and the expense-head columns (bus, train, hotel, food…) come next — you'll enter km and bills per row and the money totals compute automatically.</p>

<script>
  (function(){
    var t=document.getElementById('add_daytype'), o=document.getElementById('add_office'), l=document.getElementById('add_leave');
    if(!t) return;
    function sync(){ var v=t.value; o.style.display=(v==='OFFICE')?'':'none'; l.style.display=(v==='LEAVE')?'':'none'; }
    t.addEventListener('change', sync); sync();
  })();
</script>
