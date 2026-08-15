<?php
// Project costing — printable sheet (Save as PDF from the browser).
$h = $roll['header']; $lines = $roll['lines']; $T = $roll['totals']; $byHead = $roll['by_head'];
$heads = $roll['heads'] ?? []; $client = $roll['client'] ?? null;
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$sym = function_exists('cur_sym') ? cur_sym() : '₹';
$money = fn($n) => $sym . ' ' . number_format((float)$n, 0);
$basis = (string)$h['basis'];
$qtyLabel = $basis === 'DAILY' ? 'Man-days' : ($basis === 'FIXED' ? 'Units' : 'Man-months');
$rateLabel = $basis === 'DAILY' ? 'Rate/man-day' : ($basis === 'FIXED' ? 'Lump' : 'Rate/man-month');
$shown = array_filter($heads, fn($l, $k) => isset($byHead[$k]) && (float)$byHead[$k] != 0, ARRAY_FILTER_USE_BOTH);
$company = function_exists('app_name') ? app_name() : 'Company';
$basisName = defined('PC_BASES') ? (PC_BASES[$basis] ?? $basis) : $basis;
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project costing — <?= $e($h['code']) ?> — <?= $e($h['title']) ?></title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:22px;font-size:12px}
  h1{font-size:17px;margin:0 0 2px} .sub{color:#555;font-size:12px;margin:0 0 10px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e40af;padding-bottom:8px;margin-bottom:10px}
  .co{font-weight:bold;color:#1e40af;font-size:15px}
  .meta{width:100%;border-collapse:collapse;margin:6px 0 12px}
  .meta td{padding:2px 6px;font-size:12px;vertical-align:top} .meta td.k{color:#555;white-space:nowrap;width:1%}
  table.grid{width:100%;border-collapse:collapse;margin-top:6px}
  table.grid th,table.grid td{border:1px solid #999;padding:3px 5px;font-size:10.5px;text-align:right}
  table.grid th{background:#eef2f8;text-align:right} table.grid td.l,table.grid th.l{text-align:left}
  .tot td{font-weight:bold;background:#f0f0f0}
  .grp td{background:#f7f7f7;font-weight:bold;text-align:left;color:#333}
  .box{border:1px solid #ccc;border-radius:6px;padding:8px 10px;margin-top:10px;font-size:11px}
  .kpis{display:flex;gap:10px;margin:10px 0}
  .kpi{flex:1;border:1px solid #ccc;border-radius:6px;padding:7px 9px}
  .kpi .l{color:#555;font-size:10px;text-transform:uppercase} .kpi .v{font-size:16px;font-weight:bold}
  .noprint{margin:10px 0} @media print{.noprint{display:none} body{margin:8mm}}
  .muted{color:#666}
</style></head><body>
<div class="noprint"><button onclick="window.print()">🖨 Print / Save as PDF</button> <a href="/project-costing?id=<?= (int)$h['id'] ?>">← back</a></div>

<div class="top">
  <div><div class="co"><?= $e($company) ?></div><div class="muted">Project costing sheet</div></div>
  <div style="text-align:right"><div><b><?= $e($h['code']) ?></b></div><div class="muted"><?= $e(defined('PC_STATUS') && isset(PC_STATUS[$h['status']]) ? PC_STATUS[$h['status']] : ($h['status'] ?? '')) ?></div></div>
</div>

<h1><?= $e($h['title']) ?></h1>
<div class="sub"><?= $e($basisName) ?><?= !empty($h['site']) ? ' · '.$e($h['site']) : '' ?></div>

<table class="meta">
  <tr><td class="k">Client</td><td><?= $e($client['display_name'] ?? $client['legal_name'] ?? '—') ?></td>
      <td class="k">Loadings</td><td>Overhead <?= $e($h['overhead_pct']) ?>% · Insurance <?= $e($h['insurance_pct']) ?>% · Bad debt <?= $e($h['baddebt_pct']) ?>% · Contingency <?= $e($h['contingency_pct']) ?>% <span class="muted">(on <?= strtoupper((string)$h['load_on'])==='RATE'?'sell rate':'cost' ?>)</span></td></tr>
  <tr><td class="k">Negotiation</td><td><?= $e($h['negotiation_pct']) ?>%</td>
      <td class="k">Prepared</td><td><?= $e(substr((string)($h['created_at'] ?? ''),0,10)) ?> by <?= $e($h['created_by'] ?? '') ?></td></tr>
</table>

<div class="kpis">
  <div class="kpi"><div class="l">Project revenue</div><div class="v"><?= $money($T['revenue']) ?></div></div>
  <div class="kpi"><div class="l">Project cost</div><div class="v"><?= $money($T['cost']) ?></div></div>
  <div class="kpi"><div class="l">Project profit</div><div class="v"><?= $money($T['profit']) ?></div></div>
  <div class="kpi"><div class="l">Margin</div><div class="v"><?= number_format($T['margin'],1) ?>%</div></div>
</div>

<table class="grid">
  <thead><tr><th class="l">Role</th>
    <?php foreach ($shown as $hk=>$hl): ?><th title="<?= $e($hl) ?>"><?= $e($hl) ?></th><?php endforeach; ?>
    <th>Direct/mo</th><th>Loaded/mo</th><th><?= $e($rateLabel) ?></th><th><?= $e($qtyLabel) ?></th><th>Revenue</th></tr></thead>
  <tbody>
  <?php $lastGrp = null; $ncol = count($shown) + 6; foreach ($lines as $ln): $c = pc_line_calc($ln, $h); $lh = $c['heads'];
    if (($ln['group_label'] ?? '') !== $lastGrp && ($ln['group_label'] ?? '') !== '') { $lastGrp = $ln['group_label']; echo '<tr class="grp"><td class="l" colspan="'.$ncol.'">'.$e($lastGrp).'</td></tr>'; } ?>
    <tr><td class="l"><?= $e($ln['role_label']) ?></td>
      <?php foreach ($shown as $hk=>$hl): ?><td><?= isset($lh[$hk]) ? number_format((float)$lh[$hk]) : '—' ?></td><?php endforeach; ?>
      <td><?= number_format($c['direct']) ?></td><td><?= number_format($c['loaded']) ?></td>
      <td><?= number_format($c['proposed']) ?></td>
      <td><?= $basis==='FIXED' ? '1' : number_format((float)$ln['boq_qty'], $basis==='DAILY'?0:1) ?></td>
      <td><?= number_format($c['revenue']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="tot"><td class="l">Total</td>
    <?php foreach ($shown as $hk=>$hl): ?><td><?= number_format((float)($byHead[$hk] ?? 0)) ?></td><?php endforeach; ?>
    <td><?= number_format($T['direct']) ?></td><td></td><td></td><td></td><td><?= number_format($T['revenue']) ?></td></tr></tfoot>
</table>

<?php if (!empty($h['inclusions'])): ?><div class="box"><b>Inclusions:</b> <?= nl2br($e($h['inclusions'])) ?></div><?php endif; ?>
<?php if (!empty($h['exclusions'])): ?><div class="box"><b>Exclusions / not included:</b> <?= nl2br($e($h['exclusions'])) ?></div><?php endif; ?>
<?php if (!empty($h['notes'])): ?><div class="box"><b>Notes:</b> <?= $e($h['notes']) ?></div><?php endif; ?>

<p class="muted" style="margin-top:16px;font-size:10px">Man-month / man-day rates are per person; loadings and negotiation margin are applied as configured above. This is an internal costing sheet.</p>
</body></html>
