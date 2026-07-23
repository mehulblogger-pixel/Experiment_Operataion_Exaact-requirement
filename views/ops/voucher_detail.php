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

<?php
  $fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.') ?: '0';
  // running column totals
  $tTravel = 0; $tHead = []; foreach ($heads as $h) $tHead[$h['code']] = 0; $grand = 0;
  $ncol = 8 + count($heads) + ($canEdit ? 2 : 1); // Date,Site,File,Line,Hrs,Mode,KM,Travel + heads + Row(+✕)
?>
<?php if ($canEdit): ?><form method="post" action="/voucher-save?id=<?= (int)$v['id'] ?>" id="vform"><?php endif; ?>
<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid" id="vgrid">
  <tr>
    <th>Date</th><th>Attendance / Site</th><th>File No (BOSS)</th><th>Line No</th><th>Hrs</th>
    <th>Mode</th><th>KM</th><th>Travel ₹</th>
    <?php foreach ($heads as $h): ?><th title="<?= e($h['code']) ?>"><?= e($h['label']) ?></th><?php endforeach; ?>
    <th>Row ₹</th><?php if ($canEdit): ?><th></th><?php endif; ?>
  </tr>
  <?php foreach ($byDate as $date => $rows): $dayHours = 0; ?>
    <?php foreach ($rows as $e):
      $dayHours += (float)$e['hours']; $monthHours += (float)$e['hours'];
      $isWork = $e['day_type']==='WORK';
      $att = $isWork ? ($e['site_label'] ?: '(site)') : ($e['day_type']==='LEAVE' ? ('Leave'.($e['leave_code']?' · '.($leaveOpts[$e['leave_code']]??$e['leave_code']):'')) : ($e['day_type']==='OFFICE' ? ('Office'.($e['office_code']?' · '.($dayOpts[$e['office_code']]??$e['office_code']):'')) : ucfirst(strtolower($e['day_type']))));
      $amt = expense_extra_decode($e['amounts'] ?? '');
      // km / mode prefill from vendor memory when not yet filled
      $mem = ((float)$e['km']>0 || !$isWork) ? null : vendor_km_lookup($v['inspector_id'], $e['vendor_id']);
      $kmVal = (float)$e['km']>0 ? $e['km'] : ($mem['km'] ?? '');
      $modeVal = $e['mode_code'] ?: ($mem['mode_code'] ?? '');
      $tTravel += (float)$e['travel_amount']; $grand += (float)$e['row_total'];
      foreach ($heads as $h) $tHead[$h['code']] += (float)($amt[$h['code']] ?? 0);
      $eid = (int)$e['id']; $P = "rows[$eid]";
    ?>
    <tr data-eid="<?= $eid ?>">
      <td><?= e(date('d-M', strtotime($date))) ?><?= $e['is_auto']?' <span class="badge GREEN" style="font-size:10px">auto</span>':'' ?><?= $mem?' <span class="muted" title="km remembered for this vendor" style="font-size:11px">↺</span>':'' ?></td>
      <td><?= e($att) ?></td>
      <?php if ($canEdit): ?>
      <td><input form="vform" class="form-control" style="width:110px" name="<?= $P ?>[file_no]" value="<?= e($e['file_no']) ?>" <?= $isWork?'':'readonly' ?>></td>
      <td><input form="vform" class="form-control" style="width:80px" name="<?= $P ?>[line_no]" value="<?= e($e['line_no']) ?>" placeholder="acct"></td>
      <td><input form="vform" class="form-control v-hours" style="width:64px" type="number" step="0.25" name="<?= $P ?>[hours]" value="<?= e($fmt($e['hours'])) ?>"></td>
      <td><select form="vform" class="form-control v-mode" style="width:110px" name="<?= $P ?>[mode]"><option value="">—</option>
        <?php foreach ($modes as $m): ?><option value="<?= e($m['code']) ?>" <?= $modeVal===$m['code']?'selected':'' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select></td>
      <td><input form="vform" class="form-control v-km" style="width:70px" type="number" step="0.1" name="<?= $P ?>[km]" value="<?= e($kmVal!==''?$fmt($kmVal):'') ?>"></td>
      <td class="v-travel" data-eid="<?= $eid ?>">₹<?= $fmt($e['travel_amount']) ?></td>
      <?php foreach ($heads as $h): ?>
        <td><input form="vform" class="form-control v-amt" data-code="<?= e($h['code']) ?>" style="width:80px" type="number" step="0.01" name="<?= $P ?>[amt][<?= e($h['code']) ?>]" value="<?= e(isset($amt[$h['code']])?$fmt($amt[$h['code']]):'') ?>" <?= $h['head_type']==='BILL'?'title="actual bill"':'' ?>></td>
      <?php endforeach; ?>
      <td class="v-rowtotal" data-eid="<?= $eid ?>"><strong>₹<?= $fmt($e['row_total']) ?></strong></td>
      <td class="row-actions"><button form="del_<?= $eid ?>" class="btn small danger" type="submit" onclick="return confirm('Remove this row?')">✕</button></td>
      <?php else: ?>
      <td><?= e($e['file_no'] ?: '—') ?></td>
      <td><?= e($e['line_no'] ?: '—') ?></td>
      <td><?= e($fmt($e['hours'])) ?></td>
      <td><?= e($e['mode_code'] ?: '—') ?></td>
      <td><?= (float)$e['km']>0 ? e($fmt($e['km'])) : '—' ?></td>
      <td>₹<?= $fmt($e['travel_amount']) ?></td>
      <?php foreach ($heads as $h): ?><td><?= isset($amt[$h['code']]) ? '₹'.$fmt($amt[$h['code']]) : '—' ?></td><?php endforeach; ?>
      <td><strong>₹<?= $fmt($e['row_total']) ?></strong></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (count($rows) > 1): ?><tr class="muted" style="font-size:12px"><td colspan="<?= $ncol ?>">↳ <?= e($date) ?> — day total hours: <strong><?= e($fmt($dayHours)) ?></strong></td></tr><?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$entries): ?><tr><td colspan="<?= $ncol ?>">No days yet. <?= $canEdit?'Click “Pull working days from jobs”.':'' ?></td></tr><?php endif; ?>
  <?php if ($entries): ?>
  <tr style="background:#f7f6f4">
    <td colspan="4" style="text-align:right"><strong>TOTAL</strong></td>
    <td><strong class="tot-hours"><?= e($fmt($monthHours)) ?></strong></td>
    <td></td><td></td>
    <td id="tot-travel"><strong>₹<?= $fmt($tTravel) ?></strong></td>
    <?php foreach ($heads as $h): ?><td id="tot-amt-<?= e($h['code']) ?>"><strong>₹<?= $fmt($tHead[$h['code']]) ?></strong></td><?php endforeach; ?>
    <td id="tot-grand"><strong>₹<?= $fmt($grand) ?></strong></td>
    <?php if ($canEdit): ?><td></td><?php endif; ?>
  </tr>
  <tr><td colspan="<?= $ncol - 1 ?>" style="text-align:right"><strong>Grand Total</strong></td><td id="tot-grand2"><strong>₹<?= $fmt($grand) ?></strong></td></tr>
  <?php endif; ?>
</table>
</div>
<?php if ($canEdit): ?>
  <div style="margin-top:12px"><button class="btn" type="submit">💾 Save all rows &amp; totals</button></div>
  </form>
  <?php foreach ($entries as $e): ?>
    <form id="del_<?= (int)$e['id'] ?>" method="post" action="/voucher-entry?id=<?= (int)$v['id'] ?>"><input type="hidden" name="_do" value="del"><input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>"></form>
  <?php endforeach; ?>
  <p class="muted" style="margin-top:8px">KM auto-fills from what you last entered for that vendor (↺) and stays editable. Travel ₹ = KM × your rate; the bottom row totals every column. Only the heads &amp; modes you're entitled to appear.</p>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div class="panel">
    <h3 class="tab-sub">Summary — particulars</h3>
    <table class="grid">
      <tr><th>Particular</th><th style="text-align:right">Amount</th></tr>
      <tr><td>Travel charges (KM × rate)</td><td style="text-align:right">₹<?= number_format($sum['travel'],0) ?></td></tr>
      <?php foreach ($sum['heads'] as $code=>$amt): if ($amt==0) continue; ?>
        <tr><td><?= e($headLabels[$code] ?? $code) ?></td><td style="text-align:right">₹<?= number_format($amt,0) ?></td></tr>
      <?php endforeach; ?>
      <tr style="background:#f7f6f4"><td><strong>Grand Total</strong></td><td style="text-align:right"><strong>₹<?= number_format($sum['grand'],0) ?></strong></td></tr>
      <tr><td>Less: Advance</td><td style="text-align:right">₹<?= number_format((float)$v['advance'],0) ?></td></tr>
      <tr><td>Less: Expenses incurred by Office</td><td style="text-align:right">₹<?= number_format((float)$v['office_incurred'],0) ?></td></tr>
      <?php $bal = $sum['grand'] - (float)$v['advance'] - (float)$v['office_incurred']; ?>
      <tr style="background:#f7f6f4"><td><strong>Balance to be paid / (recovered)</strong></td><td style="text-align:right"><strong>₹<?= number_format($bal,0) ?></strong></td></tr>
    </table>
    <div style="margin-top:10px"><a class="btn secondary" href="/voucher-print?id=<?= (int)$v['id'] ?>" target="_blank">🖨 Print statement</a></div>
  </div>

  <div class="panel">
    <h3 class="tab-sub">Voucher details &amp; approval</h3>
    <?php if ($canEdit): ?>
    <form method="post" action="/voucher-header?id=<?= (int)$v['id'] ?>" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="ff"><label>Nature of spend</label>
          <select class="form-control" name="nature"><option value="">—</option><?php foreach ($natureOpts as $k=>$vv): ?><option value="<?= e($k) ?>" <?= $v['nature']===$k?'selected':'' ?>><?= e($vv) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Less Advance (₹)</label><input class="form-control" type="number" step="0.01" name="advance" value="<?= e($v['advance']) ?>"></div>
        <div class="ff"><label>Less Office-incurred (₹)</label><input class="form-control" type="number" step="0.01" name="office_incurred" value="<?= e($v['office_incurred']) ?>"></div>
        <div class="ff ff-wide"><label>Supporting file — one file for all bills (PDF/JPG, max 6 MB)</label><input class="form-control" type="file" name="support"></div>
      </div>
      <div style="margin-top:8px"><button class="btn small" type="submit">Save details / upload</button></div>
    </form>
    <?php else: ?>
      <div class="kv-grid">
        <div><span class="k">Nature</span><span class="v"><?= e($natureOpts[$v['nature']] ?? $v['nature'] ?: '—') ?></span></div>
        <div><span class="k">Advance</span><span class="v">₹<?= number_format((float)$v['advance'],0) ?></span></div>
        <div><span class="k">Office-incurred</span><span class="v">₹<?= number_format((float)$v['office_incurred'],0) ?></span></div>
      </div>
    <?php endif; ?>
    <?php if ($v['supporting_file']): ?><p style="margin-top:8px">📎 Supporting: <a href="/voucher-file?id=<?= (int)$v['id'] ?>" target="_blank"><?= e($v['supporting_name'] ?: 'file') ?></a></p><?php endif; ?>

    <hr style="margin:12px 0;border:none;border-top:1px solid #eee">
    <p><strong>Status:</strong> <span class="badge <?= $statusBadge[$v['status']] ?? 'AMBER' ?>"><?= e($v['status']) ?></span>
      <?php if ($v['approved_by']): ?><span class="muted"> · approved by <?= e($v['approved_by']) ?></span><?php endif; ?></p>
    <div class="row-actions" style="flex-wrap:wrap;gap:6px">
      <?php if ($v['status']==='DRAFT' && ($canApprove || voucher_owner_is_me($v))): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="submit"><button class="btn" type="submit">Submit for approval</button></form>
      <?php endif; ?>
      <?php if ($v['status']==='SUBMITTED' && $canApprove): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
          <input type="hidden" name="action" value="approve">
          <div class="ff"><label>Checked by</label><input class="form-control" name="checked_by"></div>
          <div class="ff"><label>Approved by</label><input class="form-control" name="approved_by" value="<?= e(user_name(current_user())) ?>"></div>
          <div class="ff"><label>Authorized by</label><input class="form-control" name="authorized_by"></div>
          <button class="btn" type="submit">Approve</button>
        </form>
      <?php endif; ?>
      <?php if ($v['status']==='APPROVED' && $canApprove): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="paid"><button class="btn" type="submit">Mark paid</button></form>
      <?php endif; ?>
      <?php if ($v['status']!=='DRAFT' && $canApprove): ?>
        <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" onsubmit="return confirm('Reopen for editing?')"><input type="hidden" name="action" value="reopen"><button class="btn secondary" type="submit">Reopen</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function(){
    var t=document.getElementById('add_daytype'), o=document.getElementById('add_office'), l=document.getElementById('add_leave');
    if(t){ function sync(){ var v=t.value; o.style.display=(v==='OFFICE')?'':'none'; l.style.display=(v==='LEAVE')?'':'none'; } t.addEventListener('change', sync); sync(); }

    var RATES = <?= json_encode($rates) ?>;
    var grid = document.getElementById('vgrid');
    if (!grid) return;
    function money(n){ return '₹' + (Math.round(n*100)/100).toLocaleString('en-IN'); }
    function recalc(){
      var totTravel=0, totGrand=0, totHead={};
      grid.querySelectorAll('tr[data-eid]').forEach(function(tr){
        var km = parseFloat(tr.querySelector('.v-km') ? tr.querySelector('.v-km').value : 0) || 0;
        var mode = tr.querySelector('.v-mode') ? tr.querySelector('.v-mode').value : '';
        var travel = km * (RATES[mode] || 0);
        var row = travel;
        tr.querySelectorAll('.v-amt').forEach(function(inp){
          var val = parseFloat(inp.value)||0; row += val;
          totHead[inp.dataset.code] = (totHead[inp.dataset.code]||0) + val;
        });
        var tCell = tr.querySelector('.v-travel'); if (tCell) tCell.innerHTML = money(travel);
        var rCell = tr.querySelector('.v-rowtotal'); if (rCell) rCell.innerHTML = '<strong>'+money(row)+'</strong>';
        totTravel += travel; totGrand += row;
      });
      var tt=document.getElementById('tot-travel'); if(tt) tt.innerHTML='<strong>'+money(totTravel)+'</strong>';
      Object.keys(totHead).forEach(function(c){ var el=document.getElementById('tot-amt-'+c); if(el) el.innerHTML='<strong>'+money(totHead[c])+'</strong>'; });
      var g=document.getElementById('tot-grand'); if(g) g.innerHTML='<strong>'+money(totGrand)+'</strong>';
      var g2=document.getElementById('tot-grand2'); if(g2) g2.innerHTML='<strong>'+money(totGrand)+'</strong>';
    }
    grid.addEventListener('input', recalc); grid.addEventListener('change', recalc); recalc();
  })();
</script>
