<?php
// Super-Admin — Companies console. Every company on the platform in one place:
// plan, purchased seats, modules and status, with the levers to change them and
// to log in as a company. Data: $companies, $plans, $modules, $sel, $base_domain.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$companies = $companies ?? []; $plans = $plans ?? []; $modules = $modules ?? [];
$sel = $sel ?? null; $base_domain = $base_domain ?? '';
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

<?php if (!$companies): ?>
  <div class="sc-card"><div style="padding:18px" class="sc-note">
    No companies in the directory yet. A company is registered from <a href="/tenants">Cloud workspaces</a> (its database) and appears here for plan, seat and module management. Auto “add a company in one form” is the next increment.
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
      <div class="sc-note">À-la-carte: tick exactly what they pay for. (Pushing a change to the company's live database + the price book are on the roadmap in <code>docs/pending.md</code>.)</div>
      <button class="btn">Save modules</button>
    </div>
  </form>
</div>
<?php endif; ?>
