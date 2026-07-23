<?php
  $u = current_user();
  $first = trim(explode(' ', trim($u['first_name'] ?? '') !== '' ? $u['first_name'] : user_name($u))[0]) ?: user_name($u);
  $h = (int)date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
  $office = ($u['home_office_id'] ?? null) ? ops_val("SELECT name FROM offices WHERE id=?", [$u['home_office_id']]) : '';
?>
<div class="home-hero">
  <div>
    <h1><?= e($greet) ?>, <?= e($first) ?> 👋</h1>
    <p class="sub" style="margin:0"><?= e(role_label($u)) ?><?= $office ? ' · ' . e($office) : '' ?> · <?= e(date('l, d M Y')) ?></p>
  </div>
</div>

<?php if (is_inspector()): ?>
  <?php $myId = $u['inspector_id'] ?? 0;
    $myOpen = $myId ? (int)ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=? AND closed_flag=0", [$myId]) : 0; ?>
  <div class="stat-row" style="margin-top:20px">
    <div class="stat-card"><div class="sc-num"><a href="/my-jobs"><?= $myOpen ?></a></div><div class="sc-lbl">My open jobs</div></div>
    <div class="stat-card"><div class="sc-num"><a href="/vouchers">→</a></div><div class="sc-lbl">This month's voucher</div></div>
  </div>
  <div class="quick">
    <a class="qa" href="/my-jobs"><span class="qi">🗂</span><b>My Jobs</b><i>See assignments, upload reports, close</i></a>
    <a class="qa" href="/vouchers"><span class="qi">🧾</span><b>My Voucher</b><i>Enter km &amp; expenses for the month</i></a>
  </div>

<?php else: ?>
  <?php
    $openCalls  = (int)ops_val("SELECT COUNT(*) FROM calls WHERE status<>'CLOSED'");
    $openJobs   = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE closed_flag=0");
    $closedJobs = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE closed_flag=1");
    $today = date('Y-m-d');
    $overdue = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE closed_flag=0 AND ((inspection_end_date<>'' AND inspection_end_date<?) OR (inspection_end_date='' AND scheduled_date<>'' AND scheduled_date<?))", [$today, $today]);
    $status = ['Open'=>max(0,$openJobs-$overdue), 'Overdue'=>$overdue, 'Closed'=>$closedJobs];
  ?>
  <div class="quick" style="margin-top:20px">
    <?php if (is_coordinator_level()): ?><a class="qa accent" href="/call-new"><span class="qi">➕</span><b>New Call</b><i>Log an inspection call</i></a><?php endif; ?>
    <a class="qa" href="/jobs"><span class="qi">🗂</span><b>Jobs</b><i>Allocate, schedule, close</i></a>
    <?php if (is_coordinator_level()): ?><a class="qa" href="/vouchers"><span class="qi">🧾</span><b>Vouchers</b><i>Inspector travelling expenses</i></a><?php endif; ?>
    <?php if (can('data.profitability')): ?><a class="qa" href="/profitability"><span class="qi">💹</span><b>Profitability</b><i>Margin by BOSS / contract</i></a><?php endif; ?>
    <?php if (can('dash.operations')||can('dash.financial')||can('dash.utilization')||can('dash.people')): ?><a class="qa" href="/reports"><span class="qi">📊</span><b>Dashboards</b><i>Revenue, TAT, utilization</i></a><?php endif; ?>
    <?php if (is_coordinator_level()): ?><a class="qa" href="/masters"><span class="qi">📋</span><b>Masters</b><i>Inspectors, BOSS, holidays…</i></a><?php endif; ?>
  </div>

  <div class="panel-split" style="margin-top:20px">
    <div>
      <div class="stat-row" style="margin:0">
        <div class="stat-card"><div class="sc-num"><a href="/calls"><?= $openCalls ?></a></div><div class="sc-lbl">Open calls</div></div>
        <div class="stat-card"><div class="sc-num"><a href="/jobs?status=open"><?= $openJobs ?></a></div><div class="sc-lbl">Open jobs</div></div>
        <div class="stat-card"><div class="sc-num" style="color:<?= $overdue?'#c0392b':'var(--ink)' ?>"><?= $overdue ?></div><div class="sc-lbl">Overdue</div></div>
        <div class="stat-card"><div class="sc-num"><a href="/jobs?status=closed"><?= $closedJobs ?></a></div><div class="sc-lbl">Closed jobs</div></div>
        <div class="stat-card"><div class="sc-num"><a href="/clients"><?= (int)$clients ?></a></div><div class="sc-lbl">Clients</div></div>
        <div class="stat-card"><div class="sc-num"><a href="/vendors"><?= (int)$vendors ?></a></div><div class="sc-lbl">Vendors</div></div>
      </div>
    </div>
    <div class="panel" style="text-align:center">
      <h4 class="tab-sub" style="margin-top:0">Job status</h4>
      <?= svg_donut($status) ?>
    </div>
  </div>

  <?php
  $pending = ops_all("SELECT c.id, c.call_code, c.inspection_required_date, c.inspection_type, c.sbu,
      bp.legal_name client_name, bp.display_name client_disp
      FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
      WHERE c.status <> 'CLOSED' AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.call_id=c.id AND j.scheduled_date <> '')
      ORDER BY c.inspection_required_date");
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
<?php endif; ?>

<style>
  .home-hero{margin:6px 0 4px}
  .home-hero h1{font-size:26px;margin:0 0 3px}
  .quick{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;margin-bottom:6px}
  .qa{display:flex;flex-direction:column;gap:2px;padding:15px 16px;border:1px solid var(--line);border-radius:var(--radius);
    background:var(--card);box-shadow:var(--shadow-sm);text-decoration:none;color:var(--ink);
    transition:transform .12s,box-shadow .16s,border-color .16s}
  .qa:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--brand);text-decoration:none}
  .qa .qi{font-size:22px;line-height:1}
  .qa b{font-size:15px;margin-top:6px}
  .qa i{font-style:normal;font-size:12.5px;color:var(--muted)}
  .qa.accent{background:linear-gradient(180deg,color-mix(in srgb,var(--brand) 10%,var(--card)),var(--card));border-color:color-mix(in srgb,var(--brand) 30%,var(--line))}
  @media(max-width:720px){.panel-split{grid-template-columns:1fr}}
</style>
