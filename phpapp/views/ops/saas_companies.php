<?php
// Super-Admin — Companies console. Every company on the platform in one place:
// plan, purchased seats, modules and status, with the levers to change them and
// to log in as a company. Data: $companies, $plans, $modules, $sel, $base_domain.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$companies = $companies ?? []; $plans = $plans ?? []; $modules = $modules ?? [];
$sel = $sel ?? null; $base_domain = $base_domain ?? ''; $can_autocreate = $can_autocreate ?? false;
$modLabel = fn($k) => $modules[$k][0] ?? ucfirst($k);
?>
<style>
  .sc-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px}
  .sc-head h1{margin:0;font-size:21px}
  .sc-sub{color:var(--muted,#656e7a);font-size:13px;margin:4px 0 0}
  .sc-card{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));overflow:hidden;margin-bottom:16px}
  .sc-card h2{margin:0;padding:13px 16px;font-size:14px;border-bottom:1px solid var(--line,#e5e7eb);background:var(--soft,#f6f8fb)}
  .sc-scroll{overflow-x:auto}
  table.sc{width:100%;border-collapse:collapse;min-width:760px}
  table.sc th,table.sc td{padding:10px 12px;border-bottom:1px solid var(--line,#eef1f5);text-align:left;font-size:13px;vertical-align:middle}
  table.sc th{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a)}
  .sc-co{font-weight:600} .sc-key{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;color:var(--muted,#656e7a)}
  .pill{display:inline-block;padding:1px 9px;border-radius:20px;font-size:11px;font-weight:700}
  .pill.on{background:#ecfdf5;color:#047857}.pill.off{background:#fee2e2;color:#dc2626}
  .pill.mod{background:#eef2ff;color:#4338ca;margin:1px 2px 1px 0;font-weight:600}
  .sc-actions{display:flex;gap:6px;flex-wrap:wrap}
  .btn.xs{padding:4px 9px;font-size:12px}
  .sc-edit{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px}
  .sc-edit .ff{display:flex;flex-direction:column;gap:5px}
  .sc-edit label{font-size:12px;font-weight:600;color:var(--ink,#28313f)}
  .sc-edit input,.sc-edit select{padding:8px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px;font:inherit;width:100%}
  .sc-mods{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
  .sc-mods label{display:flex;align-items:center;gap:6px;font-weight:500;font-size:13px}
  .sc-note{font-size:12px;color:var(--muted,#656e7a);margin:2px 2px 10px}
  @media(max-width:820px){.sc-edit{grid-template-columns:1fr}}
</style>

<div class="sc-head">
  <div>
    <h1>Companies</h1>
    <div class="sc-sub">Every company on the platform<?= $base_domain ? ' · <b>' . $e($base_domain) . '</b>' : '' ?> — plan, seats, modules and status. <a href="/super-admin">‹ Control panel</a></div>
  </div>
</div>

<details class="sc-card" style="padding:0">
  <summary style="cursor:pointer;padding:13px 16px;font-size:14px;font-weight:600;background:var(--soft,#f6f8fb);border-bottom:1px solid var(--line,#e5e7eb)">＋ Add a company</summary>
  <form method="post" style="padding:16px">
    <input type="hidden" name="do" value="company_add">
    <div class="sc-edit" style="padding:0">
      <div class="ff"><label>Company name *</label><input name="company" required placeholder="Asme Pharmaceutical Private Limited"></div>
      <div class="ff"><label>Workspace key <span class="sc-key">(optional — made from the name)</span></label><input name="new_key" placeholder="asme"></div>
      <div class="ff"><label>Owner name</label><input name="owner_name" placeholder="Asme Admin"></div>
      <div class="ff"><label>Owner email * <span class="sc-key">(their login)</span></label><input type="email" name="owner_email" required placeholder="admin@asmehr.com"></div>
      <div class="ff"><label>Temporary password <span class="sc-key">(blank = auto)</span></label><input name="owner_pass" placeholder="leave blank to auto-generate"></div>
      <div class="ff"><label>Plan</label><select name="new_plan"><?php foreach ($plans as $pk => $p): ?><option value="<?= $e($pk) ?>" <?= $pk === 'RECRUITMENT' ? 'selected' : '' ?>><?= $e($p['label']) ?> — <?= implode(', ', array_map($modLabel, $p['mods'])) ?></option><?php endforeach; ?></select></div>
    </div>
    <div style="margin-top:12px;border-top:1px dashed var(--line,#e5e7eb);padding-top:12px">
      <label style="font-size:12px;font-weight:600">Where the company's data lives</label>
      <div style="display:flex;gap:16px;align-items:center;margin:6px 0 8px;flex-wrap:wrap">
        <?php if ($can_autocreate): ?>
        <label style="display:flex;gap:6px;align-items:center;font-weight:500"><input type="radio" name="db_kind" value="auto" checked> ✨ Create its MySQL database automatically <span class="sc-key">(recommended)</span></label>
        <?php endif; ?>
        <label style="display:flex;gap:6px;align-items:center;font-weight:500"><input type="radio" name="db_kind" value="sqlite" <?= $can_autocreate ? '' : 'checked' ?>> Its own file (simplest — no setup)</label>
        <label style="display:flex;gap:6px;align-items:center;font-weight:500"><input type="radio" name="db_kind" value="mysql"> MySQL database I created myself</label>
      </div>
      <div class="sc-edit" style="padding:0">
        <div class="ff"><label>MySQL host</label><input name="db_host" value="localhost"></div>
        <div class="ff"><label>MySQL database name</label><input name="db_name" placeholder="prefix_asme"></div>
        <div class="ff"><label>MySQL user</label><input name="db_user" placeholder="prefix_asme"></div>
        <div class="ff"><label>MySQL password</label><input type="password" name="db_pass" autocomplete="new-password"></div>
      </div>
      <div class="sc-note">
        <?php if ($can_autocreate): ?>“Create automatically” builds a fresh, isolated MySQL database for this company on this server — no manual step. <?php endif; ?>
        The MySQL fields below are used only when “I created myself” is chosen. Either way the company's data stays fully separate from every other company, and the owner then completes their own onboarding (company profile) on first sign-in.
      </div>
    </div>
    <button class="btn" style="margin-top:6px">Create company</button>
  </form>
</details>

<?php if (!$companies): ?>
  <div class="sc-card"><div style="padding:18px" class="sc-note">
    No companies in the directory yet. Use <strong>＋ Add a company</strong> above to create one in a single step — the app builds its database and the owner signs in and completes their own onboarding. (Existing workspaces registered from <a href="/tenants">Cloud workspaces</a> also appear here for plan, seat and module management.)
  </div></div>
<?php else: ?>
<div class="sc-card">
  <h2><?= count($companies) ?> compan<?= count($companies) === 1 ? 'y' : 'ies' ?></h2>
  <div class="sc-scroll">
  <table class="sc">
    <tr><th>Company</th><th>Plan</th><th>Seats</th><th>Modules</th><th>Status</th><th></th></tr>
    <?php foreach ($companies as $c): $k = $e($c['tenant_key']);
      $active = ($c['status'] ?? 'active') !== 'suspended' && ($c['route_status'] ?? '') !== 'suspended'; ?>
    <tr>
      <td><div class="sc-co"><?= $e($c['company'] ?: $c['tenant_key']) ?></div>
          <div class="sc-key"><?= $k ?><?= $base_domain ? ' · ' . $e($c['owner_email'] ?? '') : '' ?></div></td>
      <td><?= $e($plans[$c['plan']]['label'] ?? $c['plan']) ?></td>
      <td><b><?= (int) $c['seat_limit'] ?></b> <span class="sc-key">(<?= (int) $c['base_logins'] ?> + <?= (int) ($c['extra_user_seats'] ?? 0) ?>)</span></td>
      <td><?php foreach ($c['mods_list'] as $m): ?><span class="pill mod"><?= $e($modLabel($m)) ?></span><?php endforeach; ?></td>
      <td><?= $active ? '<span class="pill on">Active</span>' : '<span class="pill off">Suspended</span>' ?></td>
      <td><div class="sc-actions">
        <a class="btn xs ghost" href="/companies?key=<?= $k ?>">Manage</a>
        <form method="post" style="display:inline"><input type="hidden" name="do" value="company_login_as"><input type="hidden" name="key" value="<?= $k ?>"><input type="hidden" name="company" value="<?= $e($c['company']) ?>"><button class="btn xs">Log in as ›</button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('<?= $active ? 'Suspend' : 'Reactivate' ?> <?= $e($c['company']) ?>?')"><input type="hidden" name="do" value="company_status"><input type="hidden" name="key" value="<?= $k ?>"><input type="hidden" name="status" value="<?= $active ? 'suspended' : 'active' ?>"><button class="btn xs ghost" style="color:<?= $active ? '#dc2626' : '#047857' ?>"><?= $active ? 'Suspend' : 'Activate' ?></button></form>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php endif; ?>

<?php if ($sel): $sk = $e($sel['tenant_key']); $selMods = json_decode((string) ($sel['enabled_modules'] ?? '[]'), true) ?: []; ?>
<div class="sc-card">
  <h2>Manage — <?= $e($sel['company'] ?: $sel['tenant_key']) ?></h2>

  <form method="post">
    <input type="hidden" name="do" value="company_plan"><input type="hidden" name="key" value="<?= $sk ?>">
    <div class="sc-edit" style="border-bottom:1px solid var(--line,#eef1f5)">
      <div class="ff"><label>Plan</label>
        <select name="plan"><?php foreach ($plans as $pk => $p): ?><option value="<?= $e($pk) ?>" <?= ($sel['plan'] ?? '') === $pk ? 'selected' : '' ?>><?= $e($p['label']) ?> — <?= (int) $p['base'] ?> base logins</option><?php endforeach; ?></select>
        <div class="sc-note">Sets which modules this company gets and its base seat count. (Applies to the company's live database in the next increment.)</div>
      </div>
      <div class="ff"><label>Plan valid until (optional)</label><input name="plan_expiry" value="<?= $e($sel['plan_expiry'] ?? '') ?>" placeholder="YYYY-MM-DD">
        <div style="margin-top:8px"><button class="btn">Save plan</button></div></div>
    </div>
  </form>

  <form method="post">
    <input type="hidden" name="do" value="company_seats"><input type="hidden" name="key" value="<?= $sk ?>">
    <div class="sc-edit" style="border-bottom:1px solid var(--line,#eef1f5)">
      <div class="ff"><label>Purchased seats (over the plan base)</label>
        <input type="number" name="extra_user_seats" min="0" value="<?= (int) ($sel['extra_user_seats'] ?? 0) ?>">
        <div class="sc-note">Total logins allowed = plan base (<?= (int) saas_plan_logins($sel['plan'] ?? '') ?>) + purchased. Enforcement at sign-in lands in Increment 4.</div></div>
      <div class="ff" style="justify-content:flex-end"><div><button class="btn">Save seats</button></div></div>
    </div>
  </form>

  <form method="post">
    <input type="hidden" name="do" value="company_modules"><input type="hidden" name="key" value="<?= $sk ?>">
    <div style="padding:16px">
      <label style="font-size:12px;font-weight:600">Modules this company has bought</label>
      <div class="sc-mods">
        <?php foreach ($modules as $mk => $m): $core = !empty($m[3]); $on = $core || in_array($mk, $selMods, true); ?>
          <label><input type="checkbox" name="mods[]" value="<?= $e($mk) ?>" <?= $on ? 'checked' : '' ?> <?= $core ? 'disabled' : '' ?>><?= $e($m[0] ?? $mk) ?><?= $core ? ' <span class="sc-key">(always on)</span>' : '' ?></label>
        <?php endforeach; ?>
      </div>
      <div class="sc-note">À-la-carte: tick exactly what they pay for. Saving applies the change to the company's live workspace.</div>
      <button class="btn">Save modules</button>
    </div>
  </form>

  <?php // ---- Live à-la-carte quote: modules + seats -> price, monthly / yearly ----
    $pb = $price_book ?? ['seat'=>['month'=>0,'year'=>0],'modules'=>[],'currency'=>'INR'];
    $cur = ($pb['currency'] === 'INR') ? '₹' : ($e($pb['currency']) . ' ');
    $base = function_exists('saas_plan_logins') ? saas_plan_logins($sel['plan'] ?? '') : 0;
  ?>
  <div style="padding:16px;border-top:1px solid var(--line,#eef1f5);background:var(--soft,#f6f8fb)">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <label style="font-size:12px;font-weight:600">Price for this configuration</label>
      <label style="font-size:12.5px">Bill: <select id="scq-period" style="padding:4px 8px;border:1px solid var(--line,#d7dde5);border-radius:7px"><option value="month">Monthly</option><option value="year">Annual</option></select></label>
    </div>
    <div id="scq-lines" style="margin:8px 0 6px;font-size:13px"></div>
    <div id="scq-total" style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:20px"></div>
    <div class="sc-note">Seats counted = plan base (<?= (int) $base ?>) + purchased (the box above). Set per-module prices in <b>Pricing</b> below. A customer pays this via your existing Razorpay checkout.</div>
  </div>
  <script>
  (function(){
    var PB=<?= json_encode($pb, JSON_UNESCAPED_SLASHES) ?>, BASE=<?= (int) $base ?>, CUR=<?= json_encode($cur) ?>;
    function money(n){ return CUR + Number(n||0).toLocaleString(); }
    function calc(){
      var per=document.getElementById('scq-period').value==='year'?'year':'month';
      var mods=[].slice.call(document.querySelectorAll('input[name="mods[]"]:checked')).map(function(c){return c.value;});
      var extra=parseInt((document.querySelector('input[name=extra_user_seats]')||{}).value||'0',10)||0;
      var seats=Math.max(0,BASE+extra), lines=[], total=0;
      Object.keys(PB.modules).forEach(function(k){ if(mods.indexOf(k)>=0){ var a=PB.modules[k][per]; total+=a; lines.push([PB.modules[k].label, a]); } });
      var sa=seats*PB.seat[per]; total+=sa; lines.push([seats+' seat'+(seats===1?'':'s'), sa]);
      document.getElementById('scq-lines').innerHTML=lines.map(function(l){return '<div style="display:flex;justify-content:space-between"><span>'+l[0]+'</span><span>'+money(l[1])+'</span></div>';}).join('');
      document.getElementById('scq-total').textContent=money(total)+' / '+(per==='year'?'year':'month');
    }
    document.getElementById('scq-period').addEventListener('change',calc);
    document.querySelectorAll('input[name="mods[]"]').forEach(function(c){c.addEventListener('change',calc);});
    var sb=document.querySelector('input[name=extra_user_seats]'); if(sb) sb.addEventListener('input',calc);
    calc();
  })();
  </script>
</div>
<?php endif; ?>

<?php // ---- Pricing panel (super-admin): the price book ---------------------------
  $pb = $price_book ?? saas_price_book(); $cur = ($pb['currency'] === 'INR') ? '₹' : ($e($pb['currency']) . ' '); ?>
<details class="sc-card" style="padding:0">
  <summary style="cursor:pointer;padding:13px 16px;font-size:14px;font-weight:600;background:var(--soft,#f6f8fb);border-bottom:1px solid var(--line,#e5e7eb)">💳 Pricing — what each module &amp; seat costs</summary>
  <form method="post" style="padding:16px">
    <input type="hidden" name="do" value="price_save">
    <div class="sc-scroll"><table class="sc" style="min-width:520px">
      <tr><th>Item</th><th>Per month (<?= $cur ?>)</th><th>Per year (<?= $cur ?>)</th></tr>
      <tr><td class="sc-co">Each seat (user)</td>
        <td><input type="number" name="seat_month" min="0" value="<?= (int) $pb['seat']['month'] ?>" style="width:110px;padding:6px 8px;border:1px solid var(--line,#d7dde5);border-radius:7px"></td>
        <td><input type="number" name="seat_year" min="0" value="<?= (int) $pb['seat']['year'] ?>" style="width:110px;padding:6px 8px;border:1px solid var(--line,#d7dde5);border-radius:7px"></td></tr>
      <?php foreach ($pb['modules'] as $mk => $m): ?>
      <tr><td class="sc-co"><?= $e($m['label']) ?> <span class="sc-key">module</span></td>
        <td><input type="number" name="price_<?= $e($mk) ?>_month" min="0" value="<?= (int) $m['month'] ?>" style="width:110px;padding:6px 8px;border:1px solid var(--line,#d7dde5);border-radius:7px"></td>
        <td><input type="number" name="price_<?= $e($mk) ?>_year" min="0" value="<?= (int) $m['year'] ?>" style="width:110px;padding:6px 8px;border:1px solid var(--line,#d7dde5);border-radius:7px"></td></tr>
      <?php endforeach; ?>
    </table></div>
    <div class="sc-note">Administration is always included and never priced. These prices drive the live quote on each company's Manage panel.</div>
    <button class="btn">Save prices</button>
  </form>
</details>
