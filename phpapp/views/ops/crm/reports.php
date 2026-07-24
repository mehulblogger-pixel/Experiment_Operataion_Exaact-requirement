<div class="crumbs"><a href="/">Home</a> › Sales dashboard</div>
<div class="master-head">
  <div><h1>Sales / CRM dashboard</h1>
    <p class="sub" style="margin:2px 0 0">Pipeline, win/loss and monthly performance · FY <?= e($fy) ?> · scope applied.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="get" action="/crm-reports"><select class="form-control" name="fy" onchange="this.form.submit()"><?php foreach ($fyOpts as $f): ?><option value="<?= e($f) ?>" <?= $fy===$f?'selected':'' ?>>FY <?= e($f) ?></option><?php endforeach; ?></select></form>
    <a class="btn secondary" href="/crm-reports?<?= e(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">⬇ Download CSV</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Quotations</div><div class="k-val"><?= (int)$total ?></div><div class="k-sub">this FY</div></div>
  <div class="kpi"><div class="k-lab">Pipeline value (open)</div><div class="k-val"><?= fmoney_short($openVal) ?></div><div class="k-sub">of <?= fmoney_short($totVal) ?> quoted</div></div>
  <div class="kpi"><div class="k-lab">Won value</div><div class="k-val up"><?= fmoney_short($wonVal) ?></div><div class="k-sub"><?= (int)$nWon ?> won</div></div>
  <div class="kpi"><div class="k-lab">Win rate</div><div class="k-val <?= $winRate>=50?'up':'' ?>"><?= (int)$winRate ?>%</div><div class="k-sub"><?= (int)$nWon ?> won · <?= (int)$nLost ?> lost</div></div>
</div>

<div class="panel-split">
  <div class="panel"><h4 class="tab-sub" style="margin-top:0">Quotes by status</h4><?= svg_donut($byStatus) ?></div>
  <div class="panel"><h4 class="tab-sub" style="margin-top:0">Quoted value by SBU</h4><?= svg_hbars($bySbu, true) ?></div>
</div>
<div class="panel-split">
  <div class="panel"><h4 class="tab-sub" style="margin-top:0">Top customers by quoted value</h4><?= svg_hbars($topClients, true) ?></div>
  <div class="panel"><h4 class="tab-sub" style="margin-top:0">Why we lost (win/loss)</h4><?= $lost ? svg_hbars($lost) : '<p class="muted">No lost quotes in this period. 🎉</p>' ?></div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <h4 class="tab-sub" style="margin:12px 14px 0">Monthly performance</h4>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Month</th><th class="num">Raised</th><th class="num">Won</th><th class="num">Lost</th><th class="num">Won value</th></tr></thead>
    <tbody>
    <?php foreach ($months as $mo=>$m): ?>
    <tr>
      <td><b><?= e(date('M Y', strtotime($mo.'-01'))) ?></b></td>
      <td class="num"><?= (int)$m['raised'] ?></td>
      <td class="num up"><?= (int)$m['won'] ?></td>
      <td class="num <?= $m['lost']?'down':'' ?>"><?= (int)$m['lost'] ?></td>
      <td class="num"><?= (float)$m['wonVal']>0?'₹'.number_format((float)$m['wonVal'],0):'—' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$months): ?><tr><td colspan="5" style="text-align:center;padding:20px" class="muted">No quotations in this FY yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($wonClients): ?>
<div class="panel"><h4 class="tab-sub" style="margin-top:0">Top customers by WON value</h4><?= svg_hbars($wonClients, true) ?></div>
<?php endif; ?>
