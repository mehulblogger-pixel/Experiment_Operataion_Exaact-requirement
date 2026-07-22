<h1>Dashboards</h1>
<p class="sub">Live figures across all jobs. Replaces Power BI — no separate licenses.</p>

<div class="stat-row">
  <div class="stat-card"><div class="sc-num"><?= fmoney($totCredit) ?></div><div class="sc-lbl">Total expected credit</div></div>
  <?php if (can_see_salary()): ?>
  <div class="stat-card"><div class="sc-num"><?= fmoney($totCost) ?></div><div class="sc-lbl">Labour cost (salary+8%)</div></div>
  <?php endif; ?>
  <div class="stat-card"><div class="sc-num"><?= fmoney($totExp) ?></div><div class="sc-lbl">Expenses</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($totSub) ?></div><div class="sc-lbl">Sub-con cost</div></div>
  <?php if (can_see_salary()): ?>
  <div class="stat-card"><div class="sc-num" style="color:<?= $totProfit>=0?'#1f8a4c':'#c0392b' ?>"><?= fmoney($totProfit) ?></div><div class="sc-lbl">Net profit</div></div>
  <?php endif; ?>
</div>

<div class="panel-split">
  <div class="panel">
    <h3 class="tab-sub">Internal credit</h3>
    <table class="grid">
      <tr><th>Direction</th><th>Expected (jobs)</th><th>Actual (recon)</th><th>Difference</th></tr>
      <tr><td>Received (IBO → Ahmedabad)</td><td><?= fmoney($creditRecv) ?></td><td><?= fmoney($reconRecv) ?></td><td><?= fmoney($reconRecv-$creditRecv) ?></td></tr>
      <tr><td>Given (Ahmedabad → IBO)</td><td><?= fmoney($creditGiven) ?></td><td><?= fmoney($reconGiven) ?></td><td><?= fmoney($reconGiven-$creditGiven) ?></td></tr>
    </table>
    <p class="muted" style="margin-top:8px;">Enter actual figures under <a href="/m/credit-recon">Credit reconciliation</a>.</p>
  </div>
  <div class="panel">
    <h3 class="tab-sub">TAT (turnaround)</h3>
    <table class="grid">
      <tr><th>On-time (≤3d)</th><th>Delayed (&gt;3d)</th><th>Closed total</th></tr>
      <tr><td><span class="badge GREEN"><?= (int)$tatOn ?></span></td><td><span class="badge <?= $tatLate?'RED':'GREEN' ?>"><?= (int)$tatLate ?></span></td><td><?= (int)$tatTotal ?></td></tr>
    </table>
    <?php $pct = $tatTotal ? round($tatOn/$tatTotal*100) : 0; ?>
    <div class="bar"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
    <p class="muted"><?= $pct ?>% of closed jobs on time.</p>
  </div>
</div>

<?php if (can_see_salary()): ?>
<div class="panel">
  <h3 class="tab-sub">Inspector profitability</h3>
  <table class="grid">
    <tr><th>Inspector</th><th>Jobs</th><th>Man-days</th><th>Credit</th><th>Labour</th><th>Expenses</th><th>Net</th></tr>
    <?php foreach ($byInspector as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td><?= (int)$r['jobs'] ?></td><td><?= e($r['mandays']) ?></td>
      <td><?= fmoney($r['credit']) ?></td><td><?= fmoney($r['cost']) ?></td><td><?= fmoney($r['exp']) ?></td>
      <td><strong style="color:<?= $r['profit']>=0?'#1f8a4c':'#c0392b' ?>"><?= fmoney($r['profit']) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if (!$byInspector): ?><tr><td colspan="7">No job data yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">Utilization (this month · <?= (int)($util[0]['working'] ?? 0) ?> working days)</h3>
  <table class="grid">
    <tr><th>Inspector</th><th>Utilized man-days</th><th>Utilization</th></tr>
    <?php foreach ($util as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td><?= e($r['mandays']) ?></td>
      <td><div class="bar"><div class="bar-fill" style="width:<?= min(100,(int)$r['pct']) ?>%"></div></div><?= (int)$r['pct'] ?>%</td></tr>
    <?php endforeach; ?>
    <?php if (!$util): ?><tr><td colspan="3">Add inspectors to see utilization.</td></tr><?php endif; ?>
  </table>
</div>
