<?php
  $fmt = fn($n) => number_format((float)$n, 0);
  $cols = array_keys(array_filter($sum['heads'], fn($x) => (float)$x != 0)); // heads that have any value
  $bal = $sum['grand'] - (float)$v['advance'] - (float)$v['office_incurred'];
  $natureMap = ['REVENUE'=>'Revenue','NONREV'=>'Non-Revenue','SUPPORT'=>'Support function','MD'=>'MD'];
  $byDate = [];
  foreach ($entries as $ee) $byDate[$ee['entry_date']][] = $ee;
  ksort($byDate);
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Statement of Travelling Expenses — <?= e($v['inspector_name']) ?> — <?= e($v['month']) ?></title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px;font-size:12px}
  h1{font-size:16px;text-align:center;margin:0 0 2px;letter-spacing:.5px}
  .loc{text-align:right;font-size:11px;margin-bottom:4px}
  .hdr{width:100%;border-collapse:collapse;margin:8px 0}
  .hdr td{padding:2px 6px;font-size:12px}
  table.grid{width:100%;border-collapse:collapse;margin-top:8px}
  table.grid th,table.grid td{border:1px solid #999;padding:3px 5px;font-size:11px;text-align:center}
  table.grid td.l{text-align:left} table.grid td.r{text-align:right}
  .tot td{font-weight:bold;background:#f0f0f0}
  .sumtbl{width:48%;border-collapse:collapse;margin-top:10px}
  .sumtbl td{border:1px solid #999;padding:3px 6px}
  .sign{display:flex;justify-content:space-between;margin-top:34px}
  .sign div{width:30%;border-top:1px solid #333;padding-top:4px;text-align:center;font-size:11px}
  .noprint{margin:10px 0} @media print{.noprint{display:none}}
  .muted{color:#555}
</style></head><body>
<div class="noprint"><button onclick="window.print()">🖨 Print</button> <a href="/voucher?id=<?= (int)$v['id'] ?>">← back</a></div>
<div class="loc">Location: <strong>AHMEDABAD</strong></div>
<h1>STATEMENT OF TRAVELLING EXPENSES</h1>
<table class="hdr">
  <tr><td>Name: <strong><?= e($v['inspector_name']) ?></strong></td><td>Employee code: <strong><?= e($v['emp_code'] ?: '—') ?></strong></td><td><?= e(T("sbu")) ?>: <strong><?= e(lk_options_or('sbu',OPS_SBUS)[$v['sbu']] ?? $v['sbu'] ?: '—') ?></strong></td></tr>
  <tr><td>Month: <strong><?= e($v['month']) ?></strong></td><td>Nature of spend: <strong><?= e($natureMap[$v['nature']] ?? $v['nature'] ?: '—') ?></strong></td><td>Status: <strong><?= e($v['status']) ?></strong></td></tr>
</table>

<table class="grid">
  <tr>
    <th>Date</th><th>Details / Site / Attendance</th><th>File &amp; Line</th><th>Hrs</th><th>KM</th><th>Travel <?= e(cur_sym()) ?></th>
    <?php foreach ($cols as $c): ?><th><?= e($headLabels[$c] ?? $c) ?></th><?php endforeach; ?>
    <th>Row <?= e(cur_sym()) ?></th>
  </tr>
  <?php $tKm=0; $tTravel=0; $tHead=array_fill_keys($cols,0);
  foreach ($byDate as $date=>$rows): foreach ($rows as $e):
    $amt = expense_extra_decode($e['amounts'] ?? '');
    $att = $e['day_type']==='WORK' ? ($e['site_label'] ?: '(site)') : ($e['day_type']==='LEAVE' ? ('Leave '.$e['leave_code']) : ($e['day_type']==='OFFICE' ? ('Office '.$e['office_code']) : ucfirst(strtolower($e['day_type']))));
    $tKm+=(float)$e['km']; $tTravel+=(float)$e['travel_amount'];
    foreach ($cols as $c) $tHead[$c]+=(float)($amt[$c]??0); ?>
    <tr>
      <td><?= e(date('d-M', strtotime($date))) ?></td>
      <td class="l"><?= e($att) ?></td>
      <td><?= e(trim(($e['file_no']?:'').($e['line_no']?' / '.$e['line_no']:''),' /')) ?: '—' ?></td>
      <td><?= e(rtrim(rtrim((string)$e['hours'],'0'),'.') ?: '0') ?></td>
      <td><?= (float)$e['km']>0 ? e(rtrim(rtrim((string)$e['km'],'0'),'.')) : '' ?></td>
      <td class="r"><?= (float)$e['travel_amount']>0?cur_sym().$fmt($e['travel_amount']):'' ?></td>
      <?php foreach ($cols as $c): ?><td class="r"><?= isset($amt[$c])&&$amt[$c]!=0?cur_sym().$fmt($amt[$c]):'' ?></td><?php endforeach; ?>
      <td class="r"><?= (float)$e['row_total']>0?cur_sym().$fmt($e['row_total']):'' ?></td>
    </tr>
  <?php endforeach; endforeach; ?>
  <tr class="tot">
    <td colspan="4">TOTAL</td>
    <td><?= $tKm>0?e(rtrim(rtrim((string)$tKm,'0'),'.')):'' ?></td>
    <td class="r"><?= e(cur_sym()) ?><?= $fmt($tTravel) ?></td>
    <?php foreach ($cols as $c): ?><td class="r"><?= e(cur_sym()) ?><?= $fmt($tHead[$c]) ?></td><?php endforeach; ?>
    <td class="r"><?= e(cur_sym()) ?><?= $fmt($sum['grand']) ?></td>
  </tr>
</table>

<table class="sumtbl">
  <tr><td>Grand Total</td><td style="text-align:right"><strong><?= e(cur_sym()) ?><?= $fmt($sum['grand']) ?></strong></td></tr>
  <tr><td>Less Advance</td><td style="text-align:right"><?= e(cur_sym()) ?><?= $fmt($v['advance']) ?></td></tr>
  <tr><td>Less Expenses incurred by Office</td><td style="text-align:right"><?= e(cur_sym()) ?><?= $fmt($v['office_incurred']) ?></td></tr>
  <tr><td><strong>Balance to be paid / (recovered)</strong></td><td style="text-align:right"><strong><?= e(cur_sym()) ?><?= $fmt($bal) ?></strong></td></tr>
</table>

<p class="muted" style="margin-top:14px">I declare that the details provided above are true and correct.</p>
<div class="sign">
  <div>Checked by<br><br><?= e($v['checked_by'] ?: '') ?></div>
  <div>Approved by<br><br><?= e($v['approved_by'] ?: '') ?></div>
  <div>Authorized by<br><br><?= e($v['authorized_by'] ?: '') ?></div>
</div>
<?php if ($v['supporting_file']): ?><p class="muted" style="margin-top:16px">Supporting bills attached (1 file).</p><?php endif; ?>
</body></html>
