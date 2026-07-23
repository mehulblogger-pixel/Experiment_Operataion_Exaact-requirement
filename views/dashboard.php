<?php
  $u = current_user();
  $role = user_role();
  $first = trim(explode(' ', trim($u['first_name'] ?? '') !== '' ? $u['first_name'] : user_name($u))[0]) ?: user_name($u);
  $h = (int)date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
  $office = ($u['home_office_id'] ?? null) ? ops_val("SELECT name FROM offices WHERE id=?", [$u['home_office_id']]) : '';
  $today = date('Y-m-d');
  $scopeAll = scope_offices() === 'ALL';
?>
<div class="home-hero">
  <div>
    <h1><?= e($greet) ?>, <?= e($first) ?> 👋</h1>
    <p class="sub" style="margin:0"><?= e(role_label($u)) ?><?= $office ? ' · ' . e($office) : '' ?> · <?= e(date('l, d M Y')) ?></p>
  </div>
  <span class="scope-tag"><?= $scopeAll ? 'All offices' : ($office ? e($office) : 'Your scope') ?></span>
</div>

<?php if (is_inspector()): ?>
  <?php $myId = $u['inspector_id'] ?? 0;
    $myOpen = $myId ? (int)ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=? AND closed_flag=0", [$myId]) : 0;
    $myOverdue = $myId ? (int)ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=? AND closed_flag=0 AND ((inspection_end_date<>'' AND inspection_end_date<?) OR (inspection_end_date='' AND scheduled_date<>'' AND scheduled_date<?))", [$myId,$today,$today]) : 0; ?>
  <div class="kpi-row" style="grid-template-columns:repeat(2,1fr);margin-top:18px">
    <div class="kpi"><span class="kic">🗂</span><div class="k">My open jobs</div><div class="v"><a href="/my-jobs"><?= $myOpen ?></a></div><div class="d"><?= $myOverdue ? '<span class="down">'.$myOverdue.' overdue</span>' : 'All on time' ?></div></div>
    <div class="kpi"><span class="kic">🧾</span><div class="k">This month</div><div class="v"><a href="/vouchers">Voucher →</a></div><div class="d">Enter km &amp; expenses</div></div>
  </div>
  <div class="qcards" style="grid-template-columns:repeat(2,1fr)">
    <a class="qcard tone-info" href="/my-jobs"><div class="qic">🗂</div><div class="qn">My Jobs</div><div class="ql">Assignments · upload reports · close</div></a>
    <a class="qcard tone-ok" href="/vouchers"><div class="qic">🧾</div><div class="qn">My Voucher</div><div class="ql">Km &amp; expenses for the month</div></a>
  </div>

<?php else: ?>
  <?php
    [$jw, $ja] = scope_clause('j.executing_office_id', 'j.sbu');
    [$cw, $ca] = scope_clause('c.executing_office_id', 'c.sbu');
    $openCalls  = (int)ops_val("SELECT COUNT(*) FROM calls c WHERE c.status<>'CLOSED' AND $cw", $ca);
    $openJobs   = (int)ops_val("SELECT COUNT(*) FROM jobs j WHERE j.closed_flag=0 AND $jw", $ja);
    $closedJobs = (int)ops_val("SELECT COUNT(*) FROM jobs j WHERE j.closed_flag=1 AND $jw", $ja);
    $overdue    = (int)ops_val("SELECT COUNT(*) FROM jobs j WHERE j.closed_flag=0 AND ((j.inspection_end_date<>'' AND j.inspection_end_date<?) OR (j.inspection_end_date='' AND j.scheduled_date<>'' AND j.scheduled_date<?)) AND $jw", array_merge([$today,$today], $ja));
    $status = ['Open'=>max(0,$openJobs-$overdue), 'Overdue'=>$overdue, 'Closed'=>$closedJobs];

    $showMoney  = can('data.credit') || can('finance.reconcile');
    $showProfit = can('data.profitability');
    $showCharts = can('dash.operations') || can('dash.financial') || can('dash.utilization') || can('dash.people');
    $canSched   = can('ops.job.allocate') || is_coordinator_level();
    $moneyFirst = in_array($role, ['FINANCE'], true);
    $schedFirst = in_array($role, ['COORDINATOR','ASST_MANAGER'], true);

    $mc = $showMoney ? ops_invoicing_counts() : null;

    // ---------- section: KPI row ----------
    ob_start(); ?>
    <div class="kpi-row" style="margin-top:18px">
      <div class="kpi"><span class="kic">☎️</span><div class="k">Open calls</div><div class="v"><a href="/calls"><?= $openCalls ?></a></div><div class="d">To schedule / in progress</div></div>
      <div class="kpi"><span class="kic">🗂</span><div class="k">Open jobs</div><div class="v"><a href="/jobs?status=open"><?= $openJobs ?></a></div><div class="d"><?= $overdue ? '<span class="down">'.$overdue.' overdue</span>' : 'All on time' ?></div></div>
      <?php if ($showMoney): ?>
        <div class="kpi"><span class="kic">💳</span><div class="k">Unbilled</div><div class="v"><a href="/invoicing?f=pending"><?= fmoney_short($mc['unbilled']) ?></a></div><div class="d"><?= (int)$mc['pending'] ?> job(s) to invoice</div></div>
        <div class="kpi"><span class="kic">₹</span><div class="k">Outstanding</div><div class="v"><a href="/invoicing?f=awaiting"><?= fmoney_short($mc['outstanding']) ?></a></div><div class="d"><?= $mc['overdue'] ? '<span class="down">'.$mc['overdue'].' overdue</span>' : (int)$mc['awaiting'].' awaiting' ?></div></div>
      <?php else: ?>
        <div class="kpi"><span class="kic">✅</span><div class="k">Closed jobs</div><div class="v"><a href="/jobs?status=closed"><?= $closedJobs ?></a></div><div class="d">Completed</div></div>
        <div class="kpi"><span class="kic">🏢</span><div class="k">Clients</div><div class="v"><a href="/clients"><?= (int)($clients ?? 0) ?></a></div><div class="d"><?= (int)($vendors ?? 0) ?> vendors</div></div>
      <?php endif; ?>
    </div>
    <?php $secKpi = ob_get_clean();

    // ---------- section: money desk ----------
    ob_start();
    if ($showMoney): ?>
    <div class="ctitle"><h3>Money desk</h3><a href="/invoicing">Open invoicing →</a></div>
    <div class="qcards">
      <a class="qcard tone-info" href="/invoicing?f=pending"><div class="qic">▦</div><div class="qn"><?= (int)$mc['pending'] ?></div><div class="ql">Invoice pending</div></a>
      <a class="qcard tone-warn" href="/invoicing?f=awaiting"><div class="qic">◷</div><div class="qn"><?= (int)$mc['awaiting'] ?></div><div class="ql">Awaiting payment</div></a>
      <a class="qcard tone-bad" href="/invoicing?f=overdue"><div class="qic">!</div><div class="qn"><?= (int)$mc['overdue'] ?></div><div class="ql">Overdue</div></a>
      <a class="qcard tone-ok" href="/invoicing?f=credit"><div class="qic">⇄</div><div class="qn"><?= (int)$mc['credit'] ?></div><div class="ql">Credit not received</div></a>
    </div>
    <?php endif; $secMoney = ob_get_clean();

    // ---------- section: charts (job status + by-office) ----------
    ob_start();
    if ($showCharts):
      $byOffice = ops_all("SELECT COALESCE(o.name,'Ahmedabad') office, COALESCE(SUM(j.expected_credit),0) v
        FROM jobs j LEFT JOIN offices o ON o.id=j.executing_office_id WHERE $jw GROUP BY office ORDER BY v DESC", $ja);
      $byOffice = array_values(array_filter($byOffice, fn($r)=>(float)$r['v']>0));
      $maxv = $byOffice ? max(array_map(fn($r)=>(float)$r['v'], $byOffice)) : 0;
    ?>
    <div class="dash-2col">
      <div class="panel">
        <div class="ctitle"><h3><?= $scopeAll ? 'Expected credit by office' : 'Expected credit (your scope)' ?></h3><?php if ($showProfit): ?><a href="/profitability">Profitability →</a><?php endif; ?></div>
        <?php if ($byOffice && $maxv>0): ?>
          <div class="hbars">
            <?php foreach (array_slice($byOffice,0,6) as $b): $w = $maxv ? round((float)$b['v']/$maxv*100) : 0; ?>
              <div class="hbar"><span><?= e($b['office']) ?></span><span class="track"><span class="fill" style="width:<?= $w ?>%"></span></span><span class="val"><?= fmoney_short($b['v']) ?></span></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="muted">No credit booked yet in your scope.</p><?php endif; ?>
      </div>
      <div class="panel" style="text-align:center">
        <div class="ctitle" style="justify-content:center"><h3>Job status</h3></div>
        <?= svg_donut($status) ?>
      </div>
    </div>
    <?php endif; $secCharts = ob_get_clean();

    // ---------- section: quick actions ----------
    ob_start(); ?>
    <div class="qcards">
      <?php if (can('ops.call.create')): ?><a class="qcard tone-info" href="/call-new"><div class="qic">➕</div><div class="qn" style="font-size:18px">New Call</div><div class="ql">Log an inspection call</div></a><?php endif; ?>
      <a class="qcard" href="/jobs"><div class="qic">🗂</div><div class="qn" style="font-size:18px">Jobs</div><div class="ql">Allocate · schedule · close</div></a>
      <?php if (is_coordinator_level()): ?><a class="qcard" href="/vouchers"><div class="qic">🧾</div><div class="qn" style="font-size:18px">Vouchers</div><div class="ql">Travelling expenses</div></a><?php endif; ?>
      <?php if ($showProfit): ?><a class="qcard" href="/profitability"><div class="qic">💹</div><div class="qn" style="font-size:18px">Profitability</div><div class="ql">Margin by BOSS / contract</div></a><?php endif; ?>
    </div>
    <?php $secQuick = ob_get_clean();

    // ---------- section: pending scheduling ----------
    ob_start();
    if ($canSched):
      $pending = ops_all("SELECT c.id, c.call_code, c.inspection_required_date, c.inspection_type, c.sbu,
          bp.legal_name client_name, bp.display_name client_disp
          FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
          WHERE c.status <> 'CLOSED' AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.call_id=c.id AND j.scheduled_date <> '') AND $cw
          ORDER BY c.inspection_required_date", $ca);
    ?>
    <h3 class="tab-sub" style="margin-top:26px;">Pending scheduling <span class="muted">(<?= count($pending) ?>)</span></h3>
    <?php if ($pending): ?>
    <div class="card-grid">
      <?php foreach (array_slice($pending, 0, 9) as $c):
        $req = $c['inspection_required_date']; $days = $req ? (int)round((strtotime($req)-time())/86400) : null; ?>
        <a class="master-card" href="/job-new?call=<?= (int)$c['id'] ?>">
          <strong><?= e($c['client_disp'] ?: $c['client_name'] ?: $c['call_code']) ?></strong>
          <span class="muted"><?= e($c['call_code']) ?> · <?= e(INSPECTION_TYPES[$c['inspection_type']] ?? '—') ?> · <?= e(lk_options_or('sbu', OPS_SBUS)[$c['sbu']] ?? '') ?></span>
          <span class="muted">Needed by <?= e($req ?: '—') ?><?php if ($days!==null): ?> · <span class="badge <?= $days<0?'RED':($days<=2?'AMBER':'GREEN') ?>"><?= $days<0?abs($days).'d overdue':$days.'d' ?></span><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="muted">Nothing pending — all calls are scheduled. 🎉</p><?php endif; ?>
    <?php endif; $secSched = ob_get_clean();

    // ---------- role-based ordering ----------
    echo $secKpi;
    if ($moneyFirst)      { echo $secMoney; echo $secCharts; echo $secSched; echo $secQuick; }
    elseif ($schedFirst)  { echo $secSched; echo $secMoney; echo $secCharts; echo $secQuick; }
    else                  { echo $secMoney; echo $secCharts; echo $secQuick; echo $secSched; }
  ?>
<?php endif; ?>

<style>
  .home-hero{margin:6px 0 4px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
  .home-hero h1{font-size:26px;margin:0 0 3px}
  .scope-tag{font-size:12px;font-weight:700;color:var(--brand);background:color-mix(in srgb,var(--brand) 10%,transparent);padding:6px 12px;border-radius:20px;white-space:nowrap}
</style>
