<?php
$openCalls = (int)ops_val("SELECT COUNT(*) FROM calls WHERE status<>'CLOSED'");
$openJobs  = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE closed_flag=0");
$closedJobs= (int)ops_val("SELECT COUNT(*) FROM jobs WHERE closed_flag=1");
$inspectors= (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE status='ACTIVE'");
?>
<h1>Operations Dashboard</h1>
<p class="sub">SGS Ahmedabad Inspection Management · running on your MilesWeb hosting (PHP + MySQL).</p>

<?php if (is_inspector()): ?>
  <div class="panel"><h2>Welcome</h2><p class="sub">Head to <a href="/my-jobs">My Jobs</a> to see your assigned inspections, upload reports and close jobs.</p></div>
<?php else: ?>
<div class="stats">
  <div class="tile"><div class="n"><a href="/calls"><?= $openCalls ?></a></div><div class="l">Open calls</div></div>
  <div class="tile"><div class="n"><a href="/jobs?status=open"><?= $openJobs ?></a></div><div class="l">Open jobs</div></div>
  <div class="tile"><div class="n"><a href="/jobs?status=closed"><?= $closedJobs ?></a></div><div class="l">Closed jobs</div></div>
  <div class="tile"><div class="n"><a href="/clients"><?= (int)$clients ?></a></div><div class="l">Clients</div></div>
  <div class="tile"><div class="n"><a href="/vendors"><?= (int)$vendors ?></a></div><div class="l">Vendors</div></div>
  <div class="tile"><div class="n"><a href="/m/inspectors"><?= $inspectors ?></a></div><div class="l">Inspectors</div></div>
</div>

<div class="card-grid" style="margin-top:22px;">
  <a class="master-card" href="/call-new"><strong>➕ New Call</strong><span class="muted">Log an inspection call</span></a>
  <a class="master-card" href="/jobs"><strong>🗂 Jobs</strong><span class="muted">Allocate, schedule, close</span></a>
  <a class="master-card" href="/masters"><strong>📋 Master data</strong><span class="muted">Inspectors, BOSS, holidays…</span></a>
  <?php if (is_admin_level()): ?><a class="master-card" href="/reports"><strong>📊 Dashboards</strong><span class="muted">Profit, TAT, utilization</span></a><?php endif; ?>
</div>
<?php endif; ?>
