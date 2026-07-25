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
    $mc = fn($sql, $extra = []) => $myId ? (int)ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=? AND $sql", array_merge([$myId], $extra)) : 0;
    $myOpen    = $mc("closed_flag=0");
    $myOverdue = $mc("closed_flag=0 AND ((inspection_end_date<>'' AND inspection_end_date<?) OR (inspection_end_date='' AND scheduled_date<>'' AND scheduled_date<?))", [$today, $today]);
    $myReports = $mc("closed_flag=0 AND reporting_frequency<>'NOREPORT' AND (report_upload_date IS NULL OR report_upload_date='')");
    $myDone    = $mc("closed_flag=1");
  ?>
  <div class="qcards" style="margin-top:18px">
    <a class="qcard tone-info" href="/my-jobs?f=open"><div class="qic">🗂</div><div class="qn"><?= $myOpen ?></div><div class="ql">Open jobs to do</div></a>
    <a class="qcard tone-warn" href="/my-jobs?f=reports"><div class="qic">📄</div><div class="qn"><?= $myReports ?></div><div class="ql">Reports pending</div></a>
    <a class="qcard tone-bad" href="/my-jobs?f=overdue"><div class="qic">⏰</div><div class="qn"><?= $myOverdue ?></div><div class="ql">Overdue</div></a>
    <a class="qcard tone-ok" href="/vouchers"><div class="qic">🧾</div><div class="qn"><?= e(cur_sym()) ?></div><div class="ql">This month's voucher</div></a>
  </div>
  <div class="qcards" style="grid-template-columns:repeat(2,1fr)">
    <a class="qcard" href="/my-jobs"><div class="qic">🗂</div><div class="qn" style="font-size:18px">All my jobs</div><div class="ql"><?= $myOpen ?> open · <?= $myDone ?> completed</div></a>
    <a class="qcard" href="/vouchers"><div class="qic">🧾</div><div class="qn" style="font-size:18px">My Voucher</div><div class="ql">Enter km &amp; expenses</div></a>
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
        <div class="kpi"><span class="kic"><?= e(cur_sym()) ?></span><div class="k">Outstanding</div><div class="v"><a href="/invoicing?f=awaiting"><?= fmoney_short($mc['outstanding']) ?></a></div><div class="d"><?= $mc['overdue'] ? '<span class="down">'.$mc['overdue'].' overdue</span>' : (int)$mc['awaiting'].' awaiting' ?></div></div>
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

    // ---------- section: inspector availability (coordinator / manager / branch) ----------
    $secAvail = '';
    if (function_exists('can_manage_availability') && can_manage_availability()) {
      $avRows = inspector_availability(scope_offices(), $today);
      if ($avRows) {
        $av = ['AVAILABLE'=>0,'ON_JOB'=>0,'LEAVE'=>0,'OTHER'=>0];
        foreach ($avRows as $r) { $s=$r['eff_status']; if($s==='AVAILABLE')$av['AVAILABLE']++; elseif($s==='ON_JOB')$av['ON_JOB']++; elseif($s==='LEAVE')$av['LEAVE']++; else $av['OTHER']++; }
        ob_start(); ?>
        <div class="ctitle" style="margin-top:22px"><h3>Inspector availability — today</h3><a href="/availability">Open board →</a></div>
        <div class="qcards">
          <a class="qcard tone-ok" href="/availability"><div class="qic">🟢</div><div class="qn"><?= (int)$av['AVAILABLE'] ?></div><div class="ql">Available / free</div></a>
          <a class="qcard tone-info" href="/availability"><div class="qic">🧭</div><div class="qn"><?= (int)$av['ON_JOB'] ?></div><div class="ql">On job today</div></a>
          <a class="qcard tone-bad" href="/availability"><div class="qic">🌴</div><div class="qn"><?= (int)$av['LEAVE'] ?></div><div class="ql">On leave</div></a>
          <a class="qcard tone-warn" href="/availability"><div class="qic">📚</div><div class="qn"><?= (int)$av['OTHER'] ?></div><div class="ql">Training / office / other</div></a>
        </div>
        <?php $secAvail = ob_get_clean();
      }
    }

    // ---------- section: reports awaiting my approval (reporting managers) ----------
    $secRepAppr = '';
    if (function_exists('jobs_awaiting_report_approval') && (can('ops.job.close') || is_admin_level() || is_master())) {
      $ra = jobs_awaiting_report_approval(12);
      if ($ra) { ob_start(); ?>
        <h3 class="tab-sub" style="margin-top:26px;">Reports awaiting your approval <span class="muted">(<?= count($ra) ?>)</span></h3>
        <div class="card-grid">
          <?php foreach ($ra as $j): ?>
            <a class="master-card" href="/job?id=<?= (int)$j['id'] ?>">
              <strong><?= e($j['job_code']) ?></strong>
              <span class="muted"><?= e($j['inspector_name'] ?: '—') ?><?= $j['sbu'] ? ' · '.e(lk_options_or('sbu', OPS_SBUS)[$j['sbu']] ?? $j['sbu']) : '' ?></span>
              <span class="muted">Report <?= e($j['report_upload_date'] ?: '—') ?> · <span class="badge AMBER">Approve</span></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php $secRepAppr = ob_get_clean(); }
    }

    // ---------- section: Sales / CRM pipeline ----------
    $crmView = can('mod.quotes.view'); $secCrm = ''; $isExec = in_array($role, ['BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER'], true) || is_master();
    if ($crmView) {
      [$qw, $qa] = scope_clause('q.office_id', 'q.sbu');
      $qs = ops_one("SELECT
          SUM(CASE WHEN status IN ('DRAFT','PENDING_APPROVAL','APPROVED','SENT') THEN 1 ELSE 0 END) open_n,
          SUM(CASE WHEN status IN ('DRAFT','PENDING_APPROVAL','APPROVED','SENT') THEN total_amount ELSE 0 END) open_val,
          SUM(CASE WHEN status='SENT' THEN 1 ELSE 0 END) sent_n,
          SUM(CASE WHEN status='ACCEPTED' THEN total_amount ELSE 0 END) won_val,
          SUM(CASE WHEN status='ACCEPTED' THEN 1 ELSE 0 END) won_n,
          SUM(CASE WHEN status IN ('LOST','EXPIRED') THEN 1 ELSE 0 END) lost_n
          FROM quotations q WHERE is_current=1 AND $qw", $qa) ?: [];
      $fuDue = (int)ops_val("SELECT COUNT(*) FROM quote_followups f JOIN quotations q ON q.id=f.quote_id WHERE f.status='PENDING' AND f.due_date<=? AND q.status='SENT' AND $qw", array_merge([$today], $qa));
      $pendApprove = (can('crm.quote.approve') || is_master()) ? (int)ops_val("SELECT COUNT(*) FROM quote_approvals a JOIN quotations q ON q.id=a.quote_id WHERE a.status='PENDING' AND q.status='PENDING_APPROVAL' AND $qw", $qa) : 0;
      $winRate = ((int)$qs['won_n'] + (int)$qs['lost_n']) > 0 ? round((int)$qs['won_n'] / ((int)$qs['won_n'] + (int)$qs['lost_n']) * 100) : 0;
      // Won, but the contract number has not been raised yet. Accounts see it as
      // work to do; everyone else sees it as something outstanding on an order
      // they may already be scheduling against.
      $noContract = function_exists('quotes_awaiting_contract') ? quotes_awaiting_contract() : [];
      $canRegisterContract = can('crm.contract.register') || is_master();
      ob_start(); ?>
      <?php if ($noContract): ?>
      <div class="panel" style="border:1px solid var(--warn);margin-top:22px">
        <b><?= count($noContract) ?> won <?= e(count($noContract) === 1 ? Tl('quote') : Tlp('quote')) ?>
          <?= count($noContract) === 1 ? 'has' : 'have' ?> no contract number yet</b>
        <p class="sub" style="margin:6px 0 8px">
          <?= $canRegisterContract
              ? 'Register the ' . e(Tl('client')) . ' and the contract number so operations can quote it back to the client.'
              : 'Accounts have been notified. ' . e(THP('call')) . ' can still be raised — they will keep showing the contract number as outstanding.' ?>
        </p>
        <table class="dt">
          <thead><tr><th><?= e(TH('quote')) ?></th><th><?= e(TH('client')) ?></th><th>Won on</th><th class="num">Waiting</th><th></th></tr></thead>
          <tbody>
          <?php foreach (array_slice($noContract, 0, 8) as $nc): $wd = contract_wait_days($nc); ?>
            <tr>
              <td><a href="/quote?id=<?= (int)$nc['id'] ?>"><?= e(quote_label($nc)) ?></a></td>
              <td><?= e($nc['client_name'] ?: '—') ?></td>
              <td><?= e(fdate($nc['accepted_date'], '—')) ?></td>
              <td class="num"><?= $wd === null ? '—' : '<span class="pill ' . ($wd > 7 ? 'p-bad' : 'p-warn') . '">' . (int)$wd . 'd</span>' ?></td>
              <td class="num"><?php if ($canRegisterContract): ?><a class="btn small" href="/quote?id=<?= (int)$nc['id'] ?>#contract">Register</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($noContract) > 8): ?><p class="muted" style="margin:6px 0 0">+<?= count($noContract) - 8 ?> more.</p><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="ctitle" style="margin-top:22px"><h3>Sales pipeline</h3><a href="/quotes"><?= e(THP('quote')) ?> →</a><?php if (can('mod.crm_reports.view')): ?> <a href="/crm-reports" style="margin-left:8px">Sales dashboard →</a><?php endif; ?></div>
      <div class="qcards">
        <a class="qcard tone-info" href="/quotes?v=open"><div class="qic">📝</div><div class="qn"><?= (int)$qs['open_n'] ?></div><div class="ql">Open quotes</div></a>
        <a class="qcard tone-warn" href="/quotes?v=pending"><div class="qic">◷</div><div class="qn"><?= (int)$qs['sent_n'] ?></div><div class="ql">Awaiting reply</div></a>
        <?php if ($pendApprove): ?><a class="qcard tone-bad" href="/quotes?v=pending"><div class="qic">✔</div><div class="qn"><?= $pendApprove ?></div><div class="ql">Pending your approval</div></a>
        <?php elseif ($fuDue): ?><a class="qcard tone-bad" href="/quotes?v=pending"><div class="qic">✉</div><div class="qn"><?= $fuDue ?></div><div class="ql">Follow-ups due</div></a>
        <?php else: ?><a class="qcard tone-ok" href="/quotes?v=closed"><div class="qic">🏆</div><div class="qn"><?= (int)$qs['won_n'] ?></div><div class="ql">Won this FY</div></a><?php endif; ?>
        <a class="qcard tone-ok" href="/quotes?v=open"><div class="qic">💰</div><div class="qn" style="font-size:16px"><?= fmoney_short($qs['open_val']) ?></div><div class="ql">Pipeline value<?= $isExec ? ' · '.$winRate.'% win' : '' ?></div></a>
      </div>
      <?php $secCrm = ob_get_clean();
    }

    // ---------- section: executive strategic board (directors / SBU heads / branch mgr) ----------
    $secExec = '';
    if ($isExec && can('data.credit')) {
      [$ew, $ea] = scope_clause('j.executing_office_id', 'j.sbu');
      $fyCur = current_fy(); [$cf, $ct] = fy_range($fyCur);
      $nf = date('Y-m-d', strtotime($cf . ' +1 year'));           // start of next FY (half-open upper bound)
      $fyPrev = fy_of(date('Y-m-d', strtotime($cf . ' -1 day'))); [$pf, ] = fy_range($fyPrev);
      // revenue date = inspection end, else scheduled, else creation
      $rd = "COALESCE(NULLIF(j.inspection_end_date,''), NULLIF(j.scheduled_date,''), j.created_at)";
      $revCur  = (float)ops_val("SELECT COALESCE(SUM(j.expected_credit),0) FROM jobs j WHERE $rd>=? AND $rd<? AND $ew", array_merge([$cf, $nf], $ea));
      $revPrev = (float)ops_val("SELECT COALESCE(SUM(j.expected_credit),0) FROM jobs j WHERE $rd>=? AND $rd<? AND $ew", array_merge([$pf, $cf], $ea));
      $yoy = $revPrev > 0 ? round(($revCur - $revPrev) / $revPrev * 100) : null;
      $target = (float)(setting_get('fy_revenue_target', '') ?: 0);
      $closedFy = (int)ops_val("SELECT COUNT(*) FROM jobs j WHERE j.closed_flag=1 AND $rd>=? AND $rd<? AND $ew", array_merge([$cf, $nf], $ea));
      $topClients = ops_all("SELECT COALESCE(bp.display_name, bp.legal_name, '—') client, COALESCE(SUM(j.expected_credit),0) v
          FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
          WHERE $rd>=? AND $rd<? AND $ew GROUP BY client ORDER BY v DESC", array_merge([$cf, $nf], $ea));
      $topClients = array_values(array_filter($topClients, fn($r)=>(float)$r['v']>0));
      $tcMax = $topClients ? max(array_map(fn($r)=>(float)$r['v'], $topClients)) : 0;
      ob_start(); ?>
      <div class="ctitle" style="margin-top:20px"><h3>Business overview — FY <?= e($fyCur) ?></h3><a href="/reports">Insights →</a></div>
      <div class="kpi-row">
        <div class="kpi"><span class="kic">📈</span><div class="k">Revenue booked (FY)</div><div class="v"><?= fmoney_short($revCur) ?></div><div class="d"><?php if ($yoy!==null): ?><span class="<?= $yoy>=0?'up':'down' ?>"><?= $yoy>=0?'▲':'▼' ?> <?= abs($yoy) ?>% YoY</span><?php else: ?>vs <?= fmoney_short($revPrev) ?> last FY<?php endif; ?></div></div>
        <?php if ($target>0): $prog = min(100, round($revCur/$target*100)); ?>
        <div class="kpi"><span class="kic">🎯</span><div class="k">Against target</div><div class="v"><?= $prog ?>%</div><div class="d"><?= fmoney_short($target) ?> target</div></div>
        <?php else: ?>
        <div class="kpi"><span class="kic">✅</span><div class="k">Jobs delivered (FY)</div><div class="v"><?= $closedFy ?></div><div class="d">closed this FY</div></div>
        <?php endif; ?>
        <?php if ($crmView): ?>
        <div class="kpi"><span class="kic">🏆</span><div class="k">Sales won (FY)</div><div class="v"><?= fmoney_short($qs['won_val'] ?? 0) ?></div><div class="d"><?= (int)($qs['won_n'] ?? 0) ?> quote(s) · <?= $winRate ?? 0 ?>% win</div></div>
        <?php endif; ?>
        <div class="kpi"><span class="kic">🗂</span><div class="k">Open jobs now</div><div class="v"><a href="/jobs?status=open"><?= $openJobs ?></a></div><div class="d"><?= $overdue ? '<span class="down">'.$overdue.' overdue</span>' : 'on track' ?></div></div>
      </div>
      <?php if ($topClients): ?>
      <div class="panel" style="margin-top:12px">
        <div class="ctitle"><h3>Top <?= e(Tlp('client')) ?> by revenue — FY <?= e($fyCur) ?></h3><a href="/reports">All →</a></div>
        <div class="hbars">
          <?php foreach (array_slice($topClients,0,6) as $b): $w = $tcMax ? round((float)$b['v']/$tcMax*100) : 0; ?>
            <div class="hbar"><span><?= e($b['client']) ?></span><span class="track"><span class="fill" style="width:<?= $w ?>%"></span></span><span class="val"><?= fmoney_short($b['v']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif;
      $secExec = ob_get_clean();
    }

    // ---------- role-based ordering ----------
    echo $secKpi;
    if ($isExec)          { echo $secExec; echo $secCrm; echo $secMoney; echo $secCharts; echo $secAvail; echo $secRepAppr; echo $secQuick; echo $secSched; }
    elseif (in_array($role, ['BUSINESS_DEV_MANAGER','KEY_ACCOUNTS_MANAGER','MARKETING_MANAGER','MARKETING_EXECUTIVE'], true))
                          { echo $secCrm; echo $secQuick; echo $secMoney; echo $secCharts; }
    elseif ($moneyFirst)  { echo $secMoney; echo $secCharts; echo $secSched; echo $secAvail; echo $secRepAppr; echo $secQuick; echo $secCrm; }
    elseif ($schedFirst)  { echo $secSched; echo $secAvail; echo $secRepAppr; echo $secMoney; echo $secCharts; echo $secCrm; echo $secQuick; }
    else                  { echo $secAvail; echo $secRepAppr; echo $secMoney; echo $secCharts; echo $secCrm; echo $secQuick; echo $secSched; }
  ?>
  <?php
    $deskAdmin = is_coordinator_level() || is_admin_level();
    if ($deskAdmin) confirm_lapsed_placement_fees(); // flip provisional→confirmed once guarantees lapse
    $pf = $deskAdmin ? placement_fee_summary(30) : null;
    if ($pf && ($pf['prov_n'] || $pf['conf_n'])): ?>
    <h3 class="tab-sub" style="margin-top:26px;">Recruitment placement fees</h3>
    <div class="kpi-row" style="grid-template-columns:repeat(2,1fr)">
      <div class="kpi"><span class="kic">⏳</span><div class="k">Provisional (within guarantee)</div><div class="v"><?= fmoney_short($pf['prov_amt']) ?></div><div class="d"><?= (int)$pf['prov_n'] ?> hire(s)<?= $pf['lapsing'] ? ' · '.(int)$pf['lapsing'].' lapsing ≤30d' : '' ?></div></div>
      <div class="kpi"><span class="kic">✅</span><div class="k">Confirmed (payable)</div><div class="v"><?= fmoney_short($pf['conf_amt']) ?></div><div class="d"><?= (int)$pf['conf_n'] ?> past guarantee</div></div>
    </div>
  <?php endif; ?>
  <?php $openReqs = $deskAdmin ? ops_all("SELECT id, req_code, designation, req_type, project_site, status FROM requisitions WHERE status IN ('OPEN','PROPOSED','OFFERED') ORDER BY id DESC") : [];
    if ($openReqs): ?>
    <h3 class="tab-sub" style="margin-top:26px;">Open manpower requisitions <span class="muted">(<?= count($openReqs) ?>)</span></h3>
    <div class="card-grid">
      <?php foreach (array_slice($openReqs, 0, 9) as $rq): ?>
        <a class="master-card" href="/requisition?id=<?= (int)$rq['id'] ?>">
          <strong><?= e($rq['req_code']) ?> · <?= e(DESIGNATIONS[$rq['designation']] ?? $rq['designation']) ?></strong>
          <span class="muted"><?= $rq['req_type']==='REPLACEMENT'?'Replacement':'New' ?><?= $rq['project_site'] ? ' · '.e($rq['project_site']) : '' ?></span>
          <span class="muted"><span class="badge AMBER"><?= e(lk_options_or('requisition_status', REQ_STATUS)[$rq['status']] ?? $rq['status']) ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php $agRenew = $deskAdmin ? agencies_renewing(30) : [];
    if ($agRenew): ?>
    <h3 class="tab-sub" style="margin-top:26px;">Agency contracts renewing soon <span class="muted">(<?= count($agRenew) ?>)</span></h3>
    <div class="card-grid">
      <?php foreach ($agRenew as $a): $dl = (int)$a['days_left']; ?>
        <a class="master-card" href="/m/agencies/edit?id=<?= (int)$a['id'] ?>">
          <strong><?= e($a['name']) ?></strong>
          <span class="muted"><?= e(lk_options_or('agency_type', AGENCY_TYPES)[$a['agency_type']] ?? '') ?><?= $a['contract_number'] ? ' · '.e($a['contract_number']) : '' ?></span>
          <span class="muted">Renewal <?= e($a['contract_end']) ?> · <span class="badge <?= $dl<0?'RED':($dl<=15?'AMBER':'GREEN') ?>"><?= $dl<0 ? abs($dl).'d overdue' : $dl.'d left' ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<style>
  .home-hero{margin:6px 0 4px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
  .home-hero h1{font-size:26px;margin:0 0 3px}
  .scope-tag{font-size:12px;font-weight:700;color:var(--brand);background:color-mix(in srgb,var(--brand) 10%,transparent);padding:6px 12px;border-radius:20px;white-space:nowrap}
</style>
