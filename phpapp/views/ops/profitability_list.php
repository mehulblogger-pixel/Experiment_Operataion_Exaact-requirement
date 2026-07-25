<?php
  $tRev = 0; $tExp = 0; $tSub = 0; $tLab = 0; $tMar = 0; $tInv = 0; $tPaid = 0; $active = 0;
  foreach ($rows as $r) { $p = $r['p'];
    $tRev += (float)$p['revenue']; $tExp += (float)$p['expenses']; $tSub += (float)$p['subcon'];
    $tLab += (float)$p['labour']; $tMar += (float)$p['margin'];
    $tInv += (float)$p['invoiced']; $tPaid += (float)$p['paid'];
    if (($r['status'] ?? '') === 'ACTIVE') $active++;
  }
  $bstatus = ['ACTIVE'=>'p-ok','CLOSED'=>'p-mut','HOLD'=>'p-warn'];
  $fdate = function($d){ return $d ? date('d M Y', strtotime($d)) : '—'; };
?>
<div class="master-head">
  <div><h1><?= e(T_REG('boss')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Every BOSS / contract with its dates, renewal chain, invoicing, expenses<?= $seeSal?', salary cost and profit':'' ?>. Click a BOSS for the line-by-line drill-down.</p></div>
  <a class="btn secondary" href="/profitability?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ Download CSV</a>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">BOSS numbers</div><div class="k-val"><?= count($rows) ?></div><div class="k-sub"><?= $active ?> active</div></div>
  <div class="kpi"><div class="k-lab">Invoiced</div><div class="k-val"><?= fmoney_short($tInv) ?></div><div class="k-sub">Received <?= fmoney_short($tPaid) ?></div></div>
  <div class="kpi"><div class="k-lab">Expenses booked</div><div class="k-val"><?= fmoney_short($tExp) ?></div><div class="k-sub">travel + closure</div></div>
  <?php if ($seeSal): ?>
    <div class="kpi"><div class="k-lab">Salary costing</div><div class="k-val"><?= fmoney_short($tLab) ?></div><div class="k-sub">loaded labour</div></div>
    <div class="kpi"><div class="k-lab">Profit</div><div class="k-val <?= $tMar>=0?'up':'down' ?>"><?= fmoney_short($tMar) ?></div><div class="k-sub"><?= $tRev? round($tMar/$tRev*100,1).'% margin':'—' ?></div></div>
  <?php endif; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">📄</div>
      <p class="muted" style="margin:8px 0 0">No BOSS numbers yet. Add them under <a href="/m/boss">BOSS numbers</a>.</p></div>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr>
      <th class="num">Sr</th><th>BOSS number</th><th>Client</th><th>Status</th>
      <th>Created on</th><th>Expires on</th><th>Renewed into</th>
      <th class="num">Jobs</th><th class="num">Invoicing done</th><th class="num">Expenses booked</th>
      <?php if ($seeSal): ?><th class="num">Salary costing</th><th class="num">Profit INR</th><th class="num">Profit %</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php $sr = 0; foreach ($rows as $r): $p = $r['p']; $pos = $p['margin'] >= 0; $sr++;
      $exp = $r['end_date'] ? (int)round((strtotime($r['end_date']) - time())/86400) : null; ?>
    <tr>
      <td class="num muted"><?= $sr ?></td>
      <td>
        <a href="/profitability?boss=<?= (int)$r['id'] ?>"><b><?= e($r['boss_number']) ?></b></a>
        <?php if (!empty($r['prev_no'])): ?><div class="muted" style="font-size:11px">↳ renews <?= e($r['prev_no']) ?></div><?php endif; ?>
      </td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><span class="pill <?= $bstatus[$r['status']] ?? 'p-mut' ?>"><?= e(BOSS_STATUS[$r['status']] ?? $r['status'] ?: '—') ?></span></td>
      <td><?= e($fdate($r['start_date'])) ?></td>
      <td><?= e($fdate($r['end_date'])) ?>
        <?php if ($exp !== null && ($r['status'] ?? '') !== 'CLOSED'): ?>
          <?php if ($exp < 0): ?><span class="pill p-bad" style="margin-left:4px">expired</span>
          <?php elseif ($exp <= 30): ?><span class="pill p-warn" style="margin-left:4px"><?= $exp ?>d</span><?php endif; ?>
        <?php endif; ?>
      </td>
      <td><?php if (!empty($r['next_no'])): ?><a href="/profitability?boss=<?= (int)$r['superseded_by'] ?>"><?= e($r['next_no']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td class="num"><?= (int)$p['jobs'] ?></td>
      <td class="num"><?= (float)$p['invoiced']>0 ? fmoney($p['invoiced']) : '<span class="pill p-warn">pending</span>' ?></td>
      <td class="num"><?= fmoney($p['expenses']) ?></td>
      <?php if ($seeSal): ?>
        <td class="num"><?= fmoney($p['labour']) ?></td>
        <td class="num"><strong class="<?= $pos?'up':'down' ?>"><?= fmoney($p['margin']) ?></strong></td>
        <td class="num"><?php if ($p['pct']===null): ?>—<?php else: ?><span class="pill <?= $p['pct']<0?'p-bad':($p['pct']<10?'p-warn':'p-ok') ?>"><?= e($p['pct']) ?>%</span><?php endif; ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<p class="muted" style="margin-top:10px">"Renewed into" links a BOSS to the newer number that continues it (renewal hierarchy). "Invoicing done" is the invoiced amount against the BOSS; "Expenses booked" is inspector voucher costs (travel + bills) plus job-closure expenses<?= $seeSal?'; "Salary costing" is the loaded labour cost of the inspectors deployed':'' ?>.</p>
