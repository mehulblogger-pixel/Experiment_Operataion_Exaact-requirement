<div class="crumbs"><a href="/">Home</a> › Dashboards</div>
<h1>Dashboards</h1>
<p class="sub">Live figures within your access. <?= role_label() ?> · scope applied automatically.</p>

<form method="get" action="/reports" class="filter-bar" style="flex-wrap:wrap;gap:8px;">
  <select class="form-control" name="fy" onchange="this.form.submit()"><?php foreach ($fyOpts as $fy): ?><option value="<?= e($fy) ?>" <?= $F['fy']===$fy?'selected':'' ?>>FY <?= e($fy) ?></option><?php endforeach; ?></select>
  <select class="form-control" name="month" onchange="this.form.submit()"><option value="">All months</option>
    <?php $mn=['1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun','7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
    foreach ($mn as $k=>$v): ?><option value="<?= $k ?>" <?= (string)$F['month']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
  <select class="form-control" name="office" onchange="this.form.submit()"><option value="">All offices</option>
    <?php foreach ($offOpts as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$F['office']===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
  <select class="form-control" name="sbu" onchange="this.form.submit()"><option value="">All SBUs</option>
    <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $F['sbu']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <select class="form-control" name="insp" onchange="this.form.submit()"><option value="">All inspectors</option>
    <?php foreach ($inspOpts as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (string)$F['insp']===(string)$i['id']?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?></select>
  <?php if ($actType): ?><select class="form-control" name="act" onchange="this.form.submit()"><option value="">All activities</option>
    <?php foreach (lk_all_values($actType['id']) as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (string)$F['act']===(string)$a['id']?'selected':'' ?>><?= e($a['label']) ?></option><?php endforeach; ?></select><?php endif; ?>
  <select class="form-control" name="itype" onchange="this.form.submit()"><option value="">All types</option>
    <?php foreach ($itypeOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $F['itype']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <a class="btn small secondary" href="/reports">Reset</a>
</form>

<?php if ($canOps): ?>
<h3 class="tab-sub">🛠️ Operations</h3>
<div class="stat-row">
  <div class="stat-card"><div class="sc-num"><?= (int)$op['calls'] ?></div><div class="sc-lbl">Calls</div></div>
  <div class="stat-card"><div class="sc-num" style="color:<?= $op['pending']?'#b8791a':'#1f8a4c' ?>"><?= (int)$op['pending'] ?></div><div class="sc-lbl">Pending scheduling</div></div>
  <div class="stat-card"><div class="sc-num"><?= (int)$op['open'] ?></div><div class="sc-lbl">Open jobs</div></div>
  <div class="stat-card"><div class="sc-num"><?= (int)$op['closed'] ?></div><div class="sc-lbl">Closed jobs</div></div>
  <div class="stat-card"><div class="sc-num" style="color:<?= $op['overdue']?'#c0392b':'#1f8a4c' ?>"><?= (int)$op['overdue'] ?></div><div class="sc-lbl">Overdue closure</div></div>
  <div class="stat-card"><div class="sc-num"><?= $op['tatTotal']?round($op['tatOn']/$op['tatTotal']*100):0 ?>%</div><div class="sc-lbl">TAT on-time (≤<?= (int)$tatThresh ?>d)</div></div>
  <div class="stat-card"><div class="sc-num"><?= $op['tatTotal']?round($op['tatSum']/$op['tatTotal'],1):0 ?></div><div class="sc-lbl">Avg TAT (days)</div></div>
</div>
<?php endif; ?>

<?php if ($seeFin): ?>
<h3 class="tab-sub">💰 Financial</h3>
<div class="stat-row">
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['credit']) ?></div><div class="sc-lbl">Total credit</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['recv']) ?></div><div class="sc-lbl">Received (IBO→branch)</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['given']) ?></div><div class="sc-lbl">Given (branch→IBO)</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['exp']) ?></div><div class="sc-lbl">Expenses</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['subcon']) ?></div><div class="sc-lbl">Sub-con cost</div></div>
  <?php if ($seeSalary): ?>
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['labour']) ?></div><div class="sc-lbl">Loaded labour</div></div>
  <div class="stat-card"><div class="sc-num" style="color:<?= $fin['profit']>=0?'#1f8a4c':'#c0392b' ?>"><?= fmoney($fin['profit']) ?></div><div class="sc-lbl">Net profit</div></div>
  <?php endif; ?>
</div>
<div class="panel-split">
  <div class="panel"><h3 class="tab-sub">Credit by SBU</h3><table class="grid"><tr><th>SBU</th><th>Credit</th></tr>
    <?php foreach ($fin['bySbu'] as $k=>$v): ?><tr><td><?= e(lk_options_or('sbu',OPS_SBUS)[$k]??$k) ?></td><td><?= fmoney($v) ?></td></tr><?php endforeach; ?>
    <?php if(!$fin['bySbu']):?><tr><td colspan="2">No data.</td></tr><?php endif;?></table></div>
  <div class="panel"><h3 class="tab-sub">Expenses by heading</h3><table class="grid"><tr><th>Heading</th><th>Amount</th></tr>
    <?php foreach (['travel'=>'Travel','local'=>'Local conveyance','food'=>'Food','lodging'=>'Lodging','misc'=>'Misc'] as $k=>$v): ?><tr><td><?= $v ?></td><td><?= fmoney($fin['expHead'][$k]) ?></td></tr><?php endforeach; ?></table>
    <p class="muted" style="margin-top:6px;">Headings are configurable under <a href="/lookup?key=expense_heading">Expense headings</a>.</p></div>
</div>
<div class="stat-row">
  <div class="stat-card"><div class="sc-num"><?= fmoney($fin['invoiced']) ?></div><div class="sc-lbl">Invoiced</div></div>
  <div class="stat-card"><div class="sc-num" style="color:#1f8a4c"><?= fmoney($fin['paid']) ?></div><div class="sc-lbl">Payment received</div></div>
  <div class="stat-card"><div class="sc-num" style="color:<?= ($fin['invoiced']-$fin['paid'])>0?'#b8791a':'#1f8a4c' ?>"><?= fmoney($fin['invoiced']-$fin['paid']) ?></div><div class="sc-lbl">Outstanding</div></div>
  <div class="stat-card"><div class="sc-num" style="color:<?= $fin['overdue']?'#c0392b':'#1f8a4c' ?>"><?= (int)$fin['overdue'] ?></div><div class="sc-lbl">Invoices overdue</div></div>
  <div class="stat-card"><div class="sc-num"><?= (int)$fin['creditRecvCnt'] ?>/<?= (int)($fin['creditRecvCnt']+$fin['creditPendCnt']) ?></div><div class="sc-lbl">Inter-office credit received</div></div>
</div>
<div class="panel"><h3 class="tab-sub">Credit received vs actual (reconciliation)</h3>
  <table class="grid"><tr><th>Direction</th><th>Expected</th><th>Actual</th><th>Difference</th></tr>
    <tr><td>Received</td><td><?= fmoney($fin['recv']) ?></td><td><?= fmoney($fin['reconRecv']) ?></td><td><?= fmoney($fin['reconRecv']-$fin['recv']) ?></td></tr>
    <tr><td>Given</td><td><?= fmoney($fin['given']) ?></td><td><?= fmoney($fin['reconGiven']) ?></td><td><?= fmoney($fin['reconGiven']-$fin['given']) ?></td></tr></table></div>
<?php if ($seeSalary): ?>
<div class="panel"><h3 class="tab-sub">Inspector profitability</h3>
  <table class="grid"><tr><th>Inspector</th><th>Jobs</th><th>Man-days</th><th>Credit</th><th>Labour</th><th>Expenses</th><th>Net</th></tr>
    <?php foreach ($byInspector as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int)$r['jobs'] ?></td><td><?= e($r['mandays']) ?></td><td><?= fmoney($r['credit']) ?></td><td><?= fmoney($r['cost']) ?></td><td><?= fmoney($r['exp']) ?></td><td><strong style="color:<?= $r['profit']>=0?'#1f8a4c':'#c0392b' ?>"><?= fmoney($r['profit']) ?></strong></td></tr><?php endforeach; ?>
    <?php if(!$byInspector):?><tr><td colspan="7">No job data.</td></tr><?php endif;?></table></div>
<?php endif; ?>
<?php elseif ($canOps): ?>
<p class="muted">Financial figures are hidden for your role.</p>
<?php endif; ?>

<?php if ($canUtil): ?>
<h3 class="tab-sub">📈 Utilization <span class="muted">(this month · <?= (int)($util[0]['working'] ?? 0) ?> working days)</span></h3>
<div class="panel-split">
  <div class="panel"><table class="grid"><tr><th>Inspector</th><th>Man-days</th><th>Utilization</th></tr>
    <?php foreach ($util as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['mandays']) ?></td><td><div class="bar"><div class="bar-fill" style="width:<?= min(100,(int)$r['pct']) ?>%"></div></div><?= (int)$r['pct'] ?>%</td></tr><?php endforeach; ?>
    <?php if(!$util):?><tr><td colspan="3">No inspectors.</td></tr><?php endif;?></table></div>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0;">Man-days by SBU</h3>
    <table class="grid"><tr><th>SBU</th><th>Man-days</th></tr><?php foreach ($mdBySbu as $k=>$v): ?><tr><td><?= e(lk_options_or('sbu',OPS_SBUS)[$k]??$k) ?></td><td><?= e($v) ?></td></tr><?php endforeach; ?><?php if(!$mdBySbu):?><tr><td colspan="2">—</td></tr><?php endif;?></table>
    <p class="muted" style="margin-top:8px;">Deputation: <?= e($depMd) ?> md · Day-based: <?= e($inspMd) ?> md · Sub-con: <?= e($subMd) ?> md</p>
  </div>
</div>
<?php endif; ?>

<?php if ($canPeople): ?>
<h3 class="tab-sub">🧑‍🔧 People &amp; Compliance</h3>
<div class="panel-split">
  <div class="panel"><h3 class="tab-sub" style="margin-top:0;">Certificates expiring (≤90 days)</h3>
    <table class="grid"><tr><th>Inspector</th><th>Certificate</th><th>Valid to</th><th>Left</th></tr>
      <?php foreach ($certSoon as $c): ?><tr><td><?= e($c['inspector_name']) ?></td><td><?= e($c['name']) ?></td><td><?= e($c['valid_to']) ?></td><td><?php $d=$c['days']; ?><span class="badge <?= $d<0?'RED':($d<=30?'AMBER':'GREEN') ?>"><?= $d<0?abs($d).'d over':$d.'d' ?></span></td></tr><?php endforeach; ?>
      <?php if(!$certSoon):?><tr><td colspan="4">No certificates due.</td></tr><?php endif;?></table></div>
  <div class="panel"><h3 class="tab-sub" style="margin-top:0;">Inspectors by trade</h3>
    <table class="grid"><tr><th>Trade</th><th>Count</th></tr><?php foreach ($byTrade as $k=>$v): ?><tr><td><?= e($k) ?></td><td><?= (int)$v ?></td></tr><?php endforeach; ?><?php if(!$byTrade):?><tr><td colspan="2">—</td></tr><?php endif;?></table></div>
</div>
<?php endif; ?>
