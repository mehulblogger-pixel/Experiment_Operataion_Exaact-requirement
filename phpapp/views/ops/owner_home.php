<?php
// Owner Home — the calm landing. Data: $mods, $mods_on, $market_on, $companies,
// $seats_used, $cloud. Uses the app's standard master-head / kpi / panel / btn.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$mods = $mods ?? []; $market_on = !empty($market_on);
$owner = function_exists('current_user') ? (current_user()['name'] ?? current_user()['username'] ?? '') : '';
$app = function_exists('app_name') ? app_name() : 'your platform';
?>
<style>
  .oh-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-top:16px}
  .oh-card{display:flex;gap:13px;align-items:flex-start;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);
    border-radius:14px;padding:16px 16px;text-decoration:none;color:inherit;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));
    transition:transform .12s ease,box-shadow .12s ease}
  .oh-card:hover{transform:translateY(-2px);box-shadow:0 8px 22px -12px rgba(18,32,60,.28);border-color:var(--brand,#1e40af)}
  .oh-ic{flex:none;width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:22px;background:var(--soft,#eef2f8)}
  .oh-card h3{margin:0;font-size:15.5px}
  .oh-card p{margin:3px 0 0;font-size:13px;color:var(--muted,#5a6474);line-height:1.45}
  .oh-panel{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));margin-top:18px;overflow:hidden}
  .oh-panel h2{margin:0;padding:14px 18px;font-size:15px;border-bottom:1px solid var(--line,#e5e7eb);background:var(--soft,#f6f8fb)}
  .oh-panel .oh-body{padding:6px 18px 16px}
  .oh-row{display:flex;gap:14px;align-items:center;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line,#eef1f5)}
  .oh-row:first-child{border-top:0}
  .oh-row .oh-txt b{font-size:14.5px} .oh-row .oh-txt p{margin:2px 0 0;font-size:12.5px;color:var(--muted,#5a6474)}
  .oh-sw{position:relative;flex:none;width:50px;height:28px}
  .oh-sw input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}
  .oh-sw .tr{position:absolute;inset:0;border-radius:999px;background:var(--line-strong,#cbd2de);transition:.18s}
  .oh-sw .tr:after{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:.18s}
  .oh-sw input:checked + .tr{background:var(--ok,#1c7d5b)}
  .oh-sw input:checked + .tr:after{transform:translateX(22px)}
  .oh-sw input:disabled + .tr{opacity:.55}
  .oh-sw input:focus-visible + .tr{outline:2px solid var(--brand,#1e40af);outline-offset:2px}
  .oh-lock{font-size:11.5px;color:var(--muted,#6b7280)}
  .oh-adv{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--muted,#5a6474);text-decoration:none;border:1px solid var(--line,#e5e7eb);border-radius:9px;padding:8px 12px}
</style>

<div class="master-head">
  <div>
    <h1>Home<?= $owner ? ' — welcome, ' . $e(explode(' ', trim($owner))[0]) : '' ?> 👋</h1>
    <p class="sub" style="margin:3px 0 0">The few things you run day to day, in one calm place. The full control panel is tucked away under Advanced.</p>
  </div>
  <a class="oh-adv" href="/super-admin">⚙️ Advanced settings</a>
</div>

<div class="kpi-row" style="margin-top:14px">
  <div class="kpi"><span class="k-lab">People active now</span><span class="k-val"><?= (int) ($seats_used ?? 0) ?></span></div>
  <?php if (!empty($cloud) || (int)($companies ?? 0) > 0): ?>
  <div class="kpi"><span class="k-lab">Client companies</span><span class="k-val"><?= (int) ($companies ?? 0) ?></span></div>
  <?php endif; ?>
  <div class="kpi tone-ok"><span class="k-lab">Modules switched on</span><span class="k-val"><?= (int) ($mods_on ?? 0) ?></span></div>
</div>

<div class="oh-actions">
  <a class="oh-card" href="/companies">
    <span class="oh-ic">🏢</span>
    <span><h3>Companies</h3><p>Add a client, set their plan, seats and modules, suspend or log in as them.</p></span>
  </a>
  <a class="oh-card" href="/users">
    <span class="oh-ic">👥</span>
    <span><h3>Users &amp; seats</h3><p>Add your own people, set their roles, and manage how many seats you use.</p></span>
  </a>
  <a class="oh-card" href="/companies?panel=pricing">
    <span class="oh-ic">💰</span>
    <span><h3>Pricing</h3><p>Set the price of each module and each seat — what your clients are billed.</p></span>
  </a>
  <a class="oh-card" href="/super-admin">
    <span class="oh-ic">⚙️</span>
    <span><h3>Advanced settings</h3><p>Licence, billing, escrow, compliance, seat costing — the full engine room.</p></span>
  </a>
</div>

<form class="oh-panel" method="post" action="/owner">
  <input type="hidden" name="do" value="modules_save">
  <h2>Modules &amp; Marketplace — turn features on or off</h2>
  <div class="oh-body">
    <?php foreach ($mods as $k => $m): ?>
      <div class="oh-row">
        <span class="oh-txt"><b><?= $e($m['label']) ?></b><p><?= $e($m['desc']) ?></p></span>
        <?php if (!empty($m['core'])): ?>
          <label class="oh-sw" title="Always on — every install needs this">
            <input type="checkbox" checked disabled>
            <span class="tr"></span>
          </label>
        <?php else: ?>
          <label class="oh-sw">
            <input type="checkbox" name="mods[]" value="<?= $e($k) ?>" <?= !empty($m['on']) ? 'checked' : '' ?>>
            <span class="tr"></span>
          </label>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="oh-row">
      <span class="oh-txt"><b>🧑‍🏭 Marketplace</b><p>The technical-manpower marketplace — post requirements, search and match professionals. Turn this on to show it in your left-hand menu.</p></span>
      <label class="oh-sw">
        <input type="checkbox" name="marketplace" value="1" <?= $market_on ? 'checked' : '' ?>>
        <span class="tr"></span>
      </label>
    </div>
    <div style="display:flex;align-items:center;gap:12px;margin-top:14px">
      <button class="btn" type="submit">Save changes</button>
      <span class="sub" style="font-size:12.5px">Turning something off just hides it from the menu — your data stays safe and comes back when you turn it on again.</span>
    </div>
  </div>
</form>
