<?php
// Super Admin — Control Panel. Read-mostly; actions link to existing handlers.
$d = $d ?? [];
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$lic = $d['lic'] ?? []; $bill = $d['bill'] ?? []; $C = $d['counts'] ?? [];
$sym = function_exists('cur_sym') ? cur_sym() : '₹';
$seats = (int)($d['seats'] ?? 0); $used = (int)($d['used'] ?? 0);
$seatPct = $seats > 0 ? min(100, round($used / $seats * 100)) : 0;
// Module 36 — key on the states lk_state() actually emits (VALID/READONLY/…), not the
// never-emitted legacy names, so a healthy licence shows green and an expired
// read-only one shows red on the operator's panel.
$stateTone = ['OPEN'=>'p-info','TRIAL'=>'p-info','VALID'=>'p-ok','GRACE'=>'p-warn','READONLY'=>'p-bad','INVALID'=>'p-bad','MISSING'=>'p-warn'];
?>
<style>
  .sa{--band:var(--soft,#f1f5f9)}
  .sa .tnum{font-variant-numeric:tabular-nums}
  .sa h1{margin:0;font-size:22px;font-weight:800}
  .sa .sub{color:var(--muted);font-size:12.5px;margin:3px 0 0}
  .sa .band{background:var(--band);border:1px solid var(--line);border-radius:9px;padding:7px 13px;margin:20px 0 12px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
  .sa .band h2{margin:0;font-size:13px;font-weight:800;letter-spacing:.4px;text-transform:uppercase}
  .sa .band .bd{font-size:12px;color:var(--muted)}
  .sa .kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-top:14px}
  .sa .kpi{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--brand);border-radius:12px;padding:12px 13px}
  .sa .kpi .l{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700}
  .sa .kpi .v{font-size:23px;font-weight:800;line-height:1.05;margin-top:5px}
  .sa .kpi .dd{font-size:11px;color:var(--muted);margin-top:3px}
  .sa .kpi.ok{border-left-color:var(--ok)} .sa .kpi.ok .v{color:var(--ok)}
  .sa .kpi.bad{border-left-color:var(--bad)} .sa .kpi.bad .v{color:var(--bad)}
  .sa .kpi.warn{border-left-color:var(--warn)} .sa .kpi.warn .v{color:var(--warn)}
  .sa .g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
  .sa .g3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;align-items:start}
  .sa .panel{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .sa .panel .ph{display:flex;align-items:baseline;justify-content:space-between;gap:10px;padding:13px 16px 6px}
  .sa .panel h3{margin:0;font-size:14.5px;font-weight:700}
  .sa .panel .note{font-size:11.5px;color:var(--muted)}
  .sa .panel .pb{padding:8px 16px 16px}
  .sa .kv{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid var(--line);font-size:13px}
  .sa .kv:last-child{border-bottom:none} .sa .kv .k{color:var(--muted)}
  .sa .pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;border-radius:20px;padding:2px 9px}
  .sa .p-ok{background:var(--ok-soft,#e7f6ec);color:var(--ok)} .sa .p-warn{background:var(--warn-soft,#fdf1e3);color:var(--warn)}
  .sa .p-bad{background:var(--bad-soft,#fdecec);color:var(--bad)} .sa .p-info{background:var(--info-soft,#e6f2fb);color:var(--info)} .sa .p-mut{background:var(--soft);color:var(--muted)}
  .sa .track{height:9px;border-radius:5px;background:var(--soft);overflow:hidden;margin-top:8px}
  .sa .track>i{display:block;height:100%;border-radius:5px;background:var(--brand)}
  .sa .track.warn>i{background:var(--warn)} .sa .track.bad>i{background:var(--bad)}
  .sa .mods{display:flex;flex-direction:column;gap:6px}
  .sa .mod{display:flex;align-items:center;gap:8px;font-size:13px;padding:5px 0;border-bottom:1px solid var(--line)}
  .sa .mod:last-child{border-bottom:none} .sa .mod .mn{font-weight:600} .sa .mod .md{color:var(--muted);font-size:11.5px}
  .sa .actions{display:flex;flex-wrap:wrap;gap:8px}
  .sa .actions a{display:inline-flex;align-items:center;gap:6px;background:var(--card);border:1px solid var(--line);border-radius:9px;padding:8px 12px;text-decoration:none;color:var(--ink,inherit);font-size:13px;font-weight:600}
  .sa .actions a:hover{border-color:var(--brand);color:var(--brand)}
  .sa table{width:100%;border-collapse:collapse;font-size:13px}
  .sa th{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700;text-align:left;padding:8px 10px;border-bottom:1px solid var(--line)}
  .sa td{padding:8px 10px;border-bottom:1px solid var(--line)} .sa tr:last-child td{border-bottom:none}
  .sa .muted{color:var(--muted)} .sa .warnbox{background:var(--warn-soft,#fdf1e3);border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:12.5px;margin-top:8px}
  @media (max-width:1000px){.sa .kpis{grid-template-columns:repeat(2,1fr)}.sa .g2,.sa .g3{grid-template-columns:1fr}}
</style>

<div class="sa">
  <h1>Super Admin — Control Panel</h1>
  <p class="sub">Licence, seats, modules, subscription, tenant workspaces and system tools — one place. Every action opens the underlying screen.</p>

  <!-- OVERVIEW -->
  <div class="kpis">
    <div class="kpi <?= ($lic['state'] ?? 'OPEN')==='VALID' ? 'ok' : (in_array($lic['state'] ?? '', ['READONLY','INVALID'],true)?'bad':'') ?>">
      <div class="l">Licence</div><div class="v" style="font-size:18px"><?= $e($lic['label'] ?? 'Open (unlicensed)') ?></div>
      <div class="dd"><?= isset($lic['days_left']) && $lic['days_left']!==null ? (int)$lic['days_left'].' days left' : 'no expiry set' ?></div></div>
    <div class="kpi <?= $seats>0 && $seatPct>=90 ? 'warn' : '' ?>"><div class="l">Seats used</div><div class="v tnum"><?= $used ?><?= $seats>0 ? '<span style="font-size:14px;color:var(--muted)">/'.$seats.'</span>' : '' ?></div><div class="dd"><?= $seats>0 ? $seatPct.'% of licensed' : (int)$d['active_users'].' active users' ?></div></div>
    <div class="kpi <?= !empty($bill['enabled']) ? 'ok' : 'warn' ?>"><div class="l">Subscription</div><div class="v" style="font-size:18px"><?= !empty($bill['enabled']) ? 'Configured' : 'Not set up' ?></div><div class="dd"><?= !empty($bill['price_month']) ? $sym.number_format((float)$bill['price_month']).'/seat·mo' : 'set price + gateway' ?></div></div>
    <div class="kpi"><div class="l">Delivery</div><div class="v" style="font-size:18px"><?= !empty($d['saas']) ? 'Cloud (SaaS)' : (!empty($d['self']) ? 'Self-hosted' : 'Dev') ?></div><div class="dd"><?= count($d['tenants']) ?> tenant<?= count($d['tenants'])===1?'':'s' ?> · <?= $e(strtoupper($d['db_driver'])) ?></div></div>
    <div class="kpi <?= !empty($d['books']['connected']) ? 'ok' : '' ?>"><div class="l">Books link</div><div class="v" style="font-size:18px"><?= !empty($d['books']['connected']) ? 'Connected' : (!empty($d['books']['licensed']) ? 'Licensed' : 'Off') ?></div><div class="dd">accounting sync</div></div>
  </div>

  <!-- LICENCE & MODULES -->
  <div class="band"><h2>Licence, seats &amp; modules</h2><span class="bd">what this installation is entitled to</span></div>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>Licence &amp; seats</h3><span class="note"><a href="/licence">Manage ›</a></span></div>
      <div class="pb">
        <div class="kv"><span class="k">State</span><span><span class="pill <?= $stateTone[$lic['state'] ?? 'OPEN'] ?? 'p-mut' ?>"><?= $e($lic['label'] ?? 'Open') ?></span></span></div>
        <?php if (!empty($lic['customer'])): ?><div class="kv"><span class="k">Licensed to</span><span><?= $e($lic['customer']) ?></span></div><?php endif; ?>
        <?php if (!empty($lic['ref'])): ?><div class="kv"><span class="k">Reference</span><span><?= $e($lic['ref']) ?></span></div><?php endif; ?>
        <?php if (!empty($lic['expires'])): ?><div class="kv"><span class="k">Expires</span><span><?= $e(substr((string)$lic['expires'],0,10)) ?><?= isset($lic['days_left'])&&$lic['days_left']!==null?' ('.(int)$lic['days_left'].'d)':'' ?></span></div><?php endif; ?>
        <div class="kv"><span class="k">Seats</span><span class="tnum"><?= $used ?> used <?= $seats>0 ? 'of '.$seats.' licensed' : '(unlimited in dev)' ?></span></div>
        <?php if ($seats>0): ?><div class="track <?= $seatPct>=90?'bad':($seatPct>=75?'warn':'') ?>"><i style="width:<?= $seatPct ?>%"></i></div><?php endif; ?>
        <?php if ((int)($lic['field_seats'] ?? 0) > 0): ?>
        <div class="kv"><span class="k">Field seats</span><span class="tnum"><?= (int)($lic['field_used'] ?? 0) ?> used of <?= (int)$lic['field_seats'] ?> <span class="muted">(inspectors, capped separately)</span></span></div>
        <?php endif; ?>
        <div class="kv"><span class="k">Enforcement</span><span><?= !empty($lic['enforcing']) ? '<span class="pill p-ok">Enforced</span>' : '<span class="pill p-mut">Not enforced (dev/open)</span>' ?></span></div>
        <?php if (!empty($lic['err'])): ?><div class="warnbox">⚠ <?= $e($lic['err']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="panel"><div class="ph"><h3>Product modules</h3><span class="note">what's switched on</span></div>
      <div class="pb"><div class="mods">
        <?php foreach (($lic['modules'] ?? []) as $k=>$mo): ?>
          <div class="mod"><span class="pill <?= !empty($mo['on']) ? 'p-ok' : 'p-mut' ?>"><?= !empty($mo['on']) ? 'On' : 'Off' ?></span>
            <span><span class="mn"><?= $e($mo['label']) ?></span><?= !empty($mo['core']) ? ' <span class="md">· core</span>' : '' ?><?= !empty($mo['tier']) ? ' <span class="md">· '.$e($mo['tier']).'</span>' : '' ?><br><span class="md"><?= $e($mo['desc']) ?></span></span>
          </div>
        <?php endforeach; ?>
        <?php if (empty($lic['modules'])): ?><p class="muted" style="font-size:12.5px">All modules available (unlicensed / dev). A licence key gates these per tier.</p><?php endif; ?>
      </div></div>
    </div>
  </div>

  <!-- BILLING + TENANTS -->
  <div class="band"><h2>Subscription &amp; workspaces</h2><span class="bd">how it earns and who's on it</span></div>
  <div class="g2">
    <div class="panel" style="border-left:3px solid var(--brand)"><div class="ph"><h3>Marketplace plans &amp; limits</h3><span class="note"><a href="/marketplace-plans">Manage ›</a></span></div>
      <div class="pb">
        <div class="kv"><span class="k">What it is</span><span>Connect subscription plans, prices &amp; limits for clients &amp; freelancers</span></div>
        <div class="kv"><span class="k">Plans</span><span class="tnum"><?= function_exists('mkt_plans_all') ? count(mkt_plans_all()) : 0 ?> configured</span></div>
        <div class="kv"><span class="k">Freelancer promo</span><span><?= (function_exists('mkt_pro_is_free') && mkt_pro_is_free()) ? '<span class="pill p-ok">Free until '.$e(mkt_pro_free_until()).'</span>' : '—' ?></span></div>
        <div class="kv"><span class="k">Annual</span><span>pay <?= function_exists('mkt_annual_months') ? (int)mkt_annual_months() : 10 ?> months / year</span></div>
      </div>
    </div>
    <div class="panel" style="border-left:3px solid var(--brand)"><div class="ph"><h3>Marketplace escrow</h3><span class="note"><a href="/marketplace-escrow">Manage ›</a></span></div>
      <div class="pb">
        <div class="kv"><span class="k">What it is</span><span>Hold client funds; release to the professional when the report is approved</span></div>
        <div class="kv"><span class="k">Status</span><span><?= (function_exists('mkt_escrow_enabled') && mkt_escrow_enabled()) ? '<span class="pill p-ok">On</span>' : '<span class="pill">Off (safe default)</span>' ?></span></div>
        <div class="kv"><span class="k">Held now</span><span class="tnum"><?= function_exists('mkt_escrow_totals') ? e(($currency ?? '₹')) . number_format((float)(mkt_escrow_totals()['HELD'] ?? 0)) : '—' ?></span></div>
      </div>
    </div>
    <div class="panel" style="border-left:3px solid var(--brand)"><div class="ph"><h3>Compliance rules &amp; fees</h3><span class="note"><a href="/compliance-rules">Manage ›</a></span></div>
      <div class="pb">
        <div class="kv"><span class="k">What it is</span><span>Versioned, effective-dated GST / SAC / RCM / TDS / TCS rules + the marketplace fee engine</span></div>
        <div class="kv"><span class="k">Live rules</span><span class="tnum"><?= function_exists('mkt_rules_current') ? count(mkt_rules_current()) : 0 ?> in force</span></div>
        <div class="kv"><span class="k">Nature</span><span>Config only · no hard-coded tax · history immutable</span></div>
      </div>
    </div>
    <div class="panel"><div class="ph"><h3>Subscription &amp; billing</h3><span class="note"><a href="/billing">Manage ›</a></span></div>
      <div class="pb">
        <div class="kv"><span class="k">Gateway</span><span><?= !empty($bill['enabled']) ? '<span class="pill p-ok">Razorpay set</span>' : '<span class="pill p-warn">Not configured</span>' ?></span></div>
        <div class="kv"><span class="k">Price / seat</span><span class="tnum"><?= !empty($bill['price_month']) ? $sym.number_format((float)$bill['price_month']).' /mo' : '—' ?><?= !empty($bill['price_year']) ? ' · '.$sym.number_format((float)$bill['price_year']).' /yr' : '' ?></span></div>
        <div class="kv"><span class="k">Currency</span><span><?= $e($bill['currency'] ?? 'INR') ?></span></div>
        <div class="kv"><span class="k">Model</span><span>Per active seat · monthly or yearly</span></div>
        <?php if (empty($bill['enabled'])): ?><div class="warnbox">To start charging: set the Razorpay keys and per‑seat price under <a href="/billing">Billing</a>.</div><?php endif; ?>
      </div>
    </div>
    <div class="panel"><div class="ph"><h3>Tenant workspaces</h3><span class="note"><?= $e($d['base_domain'] ?: 'single install') ?> · <a href="/tenants">Manage ›</a></span></div>
      <div class="pb">
        <?php if (!empty($d['tenants'])): ?>
        <table><thead><tr><th>Workspace</th><th>Company</th><th>Status</th></tr></thead><tbody>
          <?php foreach (array_slice($d['tenants'],0,8) as $sub=>$t): $st=strtoupper($t['status'] ?? 'ACTIVE'); ?>
          <tr><td><b><?= $e($sub) ?></b></td><td><?= $e($t['company'] ?? '') ?></td><td><span class="pill <?= $st==='ACTIVE'?'p-ok':($st==='SUSPENDED'?'p-bad':'p-mut') ?>"><?= $e(ucfirst(strtolower($st))) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <?php else: ?>
        <p class="muted" style="font-size:12.5px">No cloud tenants — this is a single install. Turn on multi‑tenant cloud (auto‑provisioned per customer) from <a href="/tenants">Tenants</a>.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- PLANS & PACKAGING -->
  <?php $tiers = $d['tiers'] ?? []; $ml = $d['module_labels'] ?? []; $price = (float)($bill['price_month'] ?? 0);
    $mult = ['STARTER'=>1, 'PRO'=>2.0, 'ENTERPRISE'=>3.9]; // ratios to the base seat price (₹899 → 1,799 → 3,499) ?>
  <div class="band"><h2>Plans &amp; packaging</h2><span class="bd">what each tier grants when you issue a licence key · prices set under Billing</span></div>
  <div class="g3">
    <?php foreach ($tiers as $tk=>$t): ?>
    <div class="panel" style="<?= $tk==='PRO' ? 'border:2px solid var(--brand)' : '' ?>">
      <div class="ph"><h3><?= $e($t['label']) ?><?php if ($tk==='PRO'): ?> <span class="pill p-info" style="margin-left:4px">Popular</span><?php endif; ?></h3></div>
      <div class="pb">
        <div style="font-size:20px;font-weight:800"><?= $price>0 ? $sym.number_format($price*$mult[$tk]).' <span style="font-size:12px;font-weight:600;color:var(--muted)">/seat·mo</span>' : '<span class="muted" style="font-size:14px;font-weight:600">price in Billing</span>' ?></div>
        <p class="muted" style="font-size:12.5px;margin:4px 0 8px"><?= $e($t['pitch']) ?></p>
        <div class="mods">
          <?php foreach ($t['mods'] as $mk): $lbl = $ml[$mk] ?? ucfirst($mk); ?>
            <div class="mod"><span class="pill p-ok">＋</span> <span class="mn"><?= $e($lbl) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="sub" style="margin-top:8px">These are the recommended presets — the licence key's <code>mods</code> list is what actually gates a customer. Prices shown scale a base seat price from Billing (illustrative until you set your own). <a href="/licence">Issue / manage licence ›</a></p>

  <!-- SEAT CLASSES / COST BREAKDOWN -->
  <?php $sc = $d['seat_classes'] ?? null; if ($sc): $cls = $sc['classes']; $cur = $sc['currency'] ?? 'INR';
    $fmt = fn($n) => $sym.number_format((float)$n);
    $classTone = ['FULL'=>'p-info','FIELD'=>'p-ok','PORTAL'=>'p-mut']; ?>
  <div class="band"><h2>Seat classes &amp; monthly cost</h2><span class="bd">roles are priced by class, so a field inspector never costs what a manager does · <a href="/billing">set prices ›</a></span></div>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>What this installation would bill</h3><span class="note">active seats × class price</span></div>
      <div class="pb">
        <table>
          <thead><tr><th>Seat class</th><th style="text-align:right">Seats</th><th style="text-align:right">Billable</th><th style="text-align:right">Price / mo</th><th style="text-align:right">Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($cls as $ck=>$c): ?>
            <tr>
              <td><span class="pill <?= $classTone[$ck] ?? 'p-mut' ?>"><?= $e($c['label']) ?></span><br><span class="md muted" style="font-size:11px"><?= $e($c['desc']) ?></span></td>
              <td style="text-align:right" class="tnum"><?= (int)$c['count'] ?></td>
              <td style="text-align:right" class="tnum"><?= (int)$c['billable'] ?><?= $c['free']>0 ? '<br><span class="md muted" style="font-size:10.5px">'.(int)$c['free'].' free</span>' : '' ?></td>
              <td style="text-align:right" class="tnum"><?= $c['price']>0 ? $fmt($c['price']) : '<span class="muted">free</span>' ?></td>
              <td style="text-align:right" class="tnum"><b><?= $fmt($c['cost']) ?></b></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="border-top:2px solid var(--line)"><td colspan="4" style="text-align:right;font-weight:700;padding-top:10px">Monthly total (role-based)</td><td style="text-align:right;padding-top:10px" class="tnum"><b style="font-size:16px;color:var(--brand)"><?= $fmt($sc['total']) ?></b></td></tr>
          </tfoot>
        </table>
        <?php if (($sc['saving'] ?? 0) > 0): ?>
        <div class="warnbox" style="background:var(--ok-soft,#e7f6ec);margin-top:12px">
          ✅ A flat per-seat price would charge <b><?= $fmt($sc['flat_total']) ?></b>/mo for the same staff.
          Role-based pricing saves the customer <b><?= $fmt($sc['saving']) ?></b>/mo (<?= $sc['flat_total']>0 ? round($sc['saving']/$sc['flat_total']*100) : 0 ?>% less) — the fairness argument that closes field-heavy TPIAs.
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="panel"><div class="ph"><h3>Who is in each class</h3><span class="note">active staff by role · <a href="/access">roles ›</a></span></div>
      <div class="pb">
        <?php if (!empty($sc['by_role'])): ?>
        <table><thead><tr><th>Role</th><th>Class</th><th style="text-align:right">Active</th></tr></thead><tbody>
          <?php foreach ($sc['by_role'] as $r): ?>
          <tr><td><?= $e($r['label']) ?></td><td><span class="pill <?= $classTone[$r['class']] ?? 'p-mut' ?>"><?= $e($cls[$r['class']]['label'] ?? $r['class']) ?></span></td><td style="text-align:right" class="tnum"><?= (int)$r['count'] ?></td></tr>
          <?php endforeach; ?>
          <?php if (($cls['PORTAL']['count'] ?? 0) > 0): ?>
          <tr><td class="muted">External portal logins</td><td><span class="pill p-mut">Portal seat</span></td><td style="text-align:right" class="tnum"><?= (int)$cls['PORTAL']['count'] ?></td></tr>
          <?php endif; ?>
        </tbody></table>
        <?php else: ?><p class="muted" style="font-size:12.5px">No active staff yet. Add users under <a href="/users">Users</a> and the class mix appears here.</p><?php endif; ?>
        <div class="warnbox" style="margin-top:12px">
          Seat prices — full, field and portal, plus the free portal bundle — are set on the <a href="/billing">Billing</a> screen under “Pricing &amp; payment keys”. The field roles themselves (<code>seat_field_roles</code>) are configured under <a href="/settings">Settings</a>.
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- SYSTEM & DATA -->
  <div class="band"><h2>System &amp; data</h2></div>
  <div class="g2">
    <div class="panel"><div class="ph"><h3>This installation</h3></div>
      <div class="pb">
        <div class="kv"><span class="k">Database</span><span><?= $e(strtoupper($d['db_driver'])) ?></span></div>
        <div class="kv"><span class="k">Financial year</span><span><?= $e($d['fy'] ?: '—') ?></span></div>
        <div class="kv"><span class="k">Users</span><span class="tnum"><?= (int)$d['active_users'] ?> active / <?= (int)$d['all_users'] ?> total</span></div>
        <div class="kv"><span class="k">Demo data</span><span><?= !empty($d['demo_loaded']) ? '<span class="pill p-info">Loaded</span>' : '<span class="pill p-mut">Not loaded</span>' ?></span></div>
        <div style="margin-top:10px" class="actions">
          <a href="/settings">⚙ Settings</a>
          <a href="/access">🔑 Roles &amp; access</a>
          <a href="/users">👥 Users</a>
          <?php if (!empty($d['books']['url'])): ?><a href="<?= $e($d['books']['url']) ?>" target="_blank" rel="noopener">📚 Open Books ↗</a><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="panel"><div class="ph"><h3>Data at a glance</h3><span class="note">what's in this install</span></div>
      <div class="pb">
        <div class="kv"><span class="k">Offices</span><span class="tnum"><?= (int)($C['offices']??0) ?></span></div>
        <div class="kv"><span class="k">Clients &amp; vendors</span><span class="tnum"><?= (int)($C['partners']??0) ?></span></div>
        <div class="kv"><span class="k">Calls / Jobs</span><span class="tnum"><?= (int)($C['calls']??0) ?> / <?= (int)($C['jobs']??0) ?></span></div>
        <div class="kv"><span class="k">Requirements / Candidates</span><span class="tnum"><?= (int)($C['reqs']??0) ?> / <?= (int)($C['candidates']??0) ?></span></div>
        <div class="kv"><span class="k">Quotations / Invoices</span><span class="tnum"><?= (int)($C['quotes']??0) ?> / <?= (int)($C['invoices']??0) ?></span></div>
        <div style="margin-top:10px" class="actions">
          <form method="post" action="/seed-demo" style="display:inline"><button class="btn secondary" type="submit" style="padding:8px 12px;border-radius:9px">＋ Load demo data</button></form>
          <form method="post" action="/seed-demo-remove" style="display:inline" onsubmit="return confirm('Remove all demo data?')"><button class="btn btn-ghost" type="submit" style="padding:8px 12px;border-radius:9px">Remove demo data</button></form>
        </div>
      </div>
    </div>
  </div>

  <p class="sub" style="margin-top:20px">Master Admin only. This panel reads the licence, billing, tenant and Books modules already in the app — each “Manage ›” opens the full screen for that area.</p>
</div>
