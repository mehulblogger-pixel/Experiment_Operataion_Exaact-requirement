<?php
  // Super-Admin — Marketplace subscription plans & limits. Everything here is
  // editable; nothing is hard-coded. Master-only (enforced in the route).
  $clientPlans = $clientPlans ?? []; $proPlans = $proPlans ?? [];
  $edit = $edit ?? null; $limitKeys = $limitKeys ?? []; $audiences = $audiences ?? [];
  $annualMonths = (int)($annualMonths ?? 10); $proFreeUntil = (string)($proFreeUntil ?? ''); $cur = $currency ?? '₹';
  $eLim = $edit ? (function_exists('mkt_plan_limits') ? mkt_plan_limits($edit) : []) : [];
  $money = fn($n) => e($cur) . number_format((float)$n);
  $planRow = function ($p) use ($cur, $money, $limitKeys) {
      $lim = function_exists('mkt_plan_limits') ? mkt_plan_limits($p) : [];
      $bits = [];
      foreach ($lim as $k => $v) if ((int)$v > 0) $bits[] = $v . ' ' . strtolower(explode(' /', $limitKeys[$k] ?? $k)[0]);
      echo '<tr' . (empty($p['is_active']) ? ' style="opacity:.55"' : '') . '>';
      echo '<td><b>' . e($p['name']) . '</b><br><span class="muted" style="font-size:11.5px">' . e($p['code']) . (empty($p['is_active']) ? ' · inactive' : '') . '</span></td>';
      echo '<td>' . $money($p['price_month']) . '/mo<br><span class="muted" style="font-size:11.5px">' . $money($p['price_annual']) . '/yr</span></td>';
      echo '<td class="muted" style="font-size:12.5px">' . ($bits ? e(implode(' · ', $bits)) : '<i>unlimited / none</i>') . '</td>';
      echo '<td style="white-space:nowrap;text-align:right">'
         . '<a class="btn small secondary" href="/marketplace-plans?edit=' . (int)$p['id'] . '">Edit</a> '
         . '<form method="post" action="/marketplace-plans" style="display:inline" onsubmit="return confirm(\'Remove this plan?\')">'
         . '<input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="' . (int)$p['id'] . '">'
         . '<button class="btn small" style="background:#9a2a2a" type="submit">×</button></form></td>';
      echo '</tr>';
  };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/super-admin">Super Admin</a> › Marketplace plans</div>
<div class="master-head">
  <div><h1>Marketplace plans &amp; limits</h1>
    <p class="sub">The subscription plans, prices, limits and launch promo for the Connect marketplace. Everything here is yours to change — nothing is fixed in code.</p></div>
  <a class="btn secondary" href="/super-admin">← Super Admin</a>
</div>

<?php // ---- Global knobs ---- ?>
<div class="panel" style="max-width:820px">
  <h3 class="tab-sub" style="margin-top:0">Global settings</h3>
  <form method="post" action="/marketplace-plans" class="form-grid" style="align-items:end">
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="mkt_settings_form" value="1">
    <div class="ff" style="grid-column:1/-1">
      <label style="display:flex;gap:10px;align-items:center;background:var(--soft,#f6faf9);border:1px solid var(--line);border-radius:10px;padding:10px 12px">
        <input type="checkbox" name="mkt_enforce" value="1" <?= !empty($enforce) ? 'checked' : '' ?>>
        <span><b>Charge for the marketplace</b> — enforce subscriptions &amp; limits.
          <span class="muted" style="display:block;font-size:12px"><?= !empty($enforce) ? 'ON — a plan is required to post/apply (freelancers free during the promo).' : 'OFF — the marketplace is open &amp; free for everyone right now. Turn this on when you launch paid access.' ?></span></span>
      </label>
    </div>
    <div class="ff"><label>Annual = how many months?</label>
      <input class="form-control" name="mkt_annual_months" type="number" min="1" max="12" value="<?= $annualMonths ?>">
      <small class="muted">Pay this many months for a full year (e.g. 10 = 2 months free).</small></div>
    <div class="ff"><label>Freelancers free until</label>
      <input class="form-control" name="mkt_pro_free_until" type="date" value="<?= e($proFreeUntil) ?>">
      <small class="muted">Launch promo — professionals pay nothing until this date. Blank = no promo.</small></div>
    <div class="ff"><label>Currency symbol</label>
      <input class="form-control" name="mkt_currency" value="<?= e($cur) ?>" maxlength="4"></div>
    <div class="ff" style="display:flex;gap:8px;align-items:end">
      <button class="btn" type="submit">Save settings</button>
    </div>
  </form>
  <form method="post" action="/marketplace-plans" style="margin-top:8px">
    <input type="hidden" name="action" value="save_settings"><input type="hidden" name="mkt_pro_free_until" value="+6">
    <button class="btn small secondary" type="submit">＋ Set free-until to 6 months from today</button>
  </form>
</div>

<?php // ---- Plans lists ---- ?>
<div class="panel-split" style="margin-top:16px">
  <div>
    <div class="panel" style="margin-bottom:14px">
      <h3 style="margin-top:0">🏢 Client plans</h3>
      <?php if ($clientPlans): ?>
        <table class="grid" style="margin:0"><thead><tr><th>Plan</th><th>Price</th><th>Limits</th><th></th></tr></thead>
          <tbody><?php foreach ($clientPlans as $p) $planRow($p); ?></tbody></table>
      <?php else: ?><p class="muted">No client plans yet.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h3 style="margin-top:0">🧑‍🔧 Professional plans</h3>
      <?php if ($proPlans): ?>
        <table class="grid" style="margin:0"><thead><tr><th>Plan</th><th>Price</th><th>Limits</th><th></th></tr></thead>
          <tbody><?php foreach ($proPlans as $p) $planRow($p); ?></tbody></table>
      <?php else: ?><p class="muted">No professional plans yet.</p><?php endif; ?>
      <p class="muted" style="font-size:12px;margin:10px 0 0"><?php if (function_exists('mkt_pro_is_free') && mkt_pro_is_free()): ?>✅ Professionals are currently <b>free</b> (launch promo active until <?= e($proFreeUntil) ?>).<?php else: ?>Professionals are on paid plans (no active free promo).<?php endif; ?></p>
    </div>
  </div>

  <?php // ---- Add / edit form ---- ?>
  <div>
    <div class="panel">
      <h3 style="margin-top:0"><?= $edit ? 'Edit plan' : 'Add a plan' ?></h3>
      <form method="post" action="/marketplace-plans">
        <input type="hidden" name="action" value="save_plan">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="ff"><label>Who is it for?</label>
          <select class="form-control" name="audience">
            <?php foreach ($audiences as $k => $lbl): ?><option value="<?= e($k) ?>" <?= (($edit['audience'] ?? 'CLIENT') === $k) ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label>Plan name *</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Client · Growth"></div>
        <div class="form-grid">
          <div class="ff"><label>Price / month (<?= e($cur) ?>)</label><input class="form-control" name="price_month" type="number" min="0" step="1" value="<?= e($edit['price_month'] ?? '') ?>"></div>
          <div class="ff"><label>Price / year (<?= e($cur) ?>)</label><input class="form-control" name="price_annual" type="number" min="0" step="1" value="<?= e($edit['price_annual'] ?? '') ?>" placeholder="blank = month × <?= $annualMonths ?>"></div>
        </div>
        <p class="muted" style="font-size:12px;margin:2px 0 8px">Usage limits — <b>0 or blank = unlimited / not used</b>.</p>
        <div class="form-grid">
          <?php foreach ($limitKeys as $k => $lbl): ?>
            <div class="ff"><label><?= e($lbl) ?></label><input class="form-control" name="lim_<?= e($k) ?>" type="number" min="0" step="1" value="<?= e($eLim[$k] ?? '') ?>"></div>
          <?php endforeach; ?>
        </div>
        <div class="form-grid">
          <div class="ff"><label>Sort order</label><input class="form-control" name="sort" type="number" value="<?= e($edit['sort'] ?? 0) ?>"></div>
          <div class="ff"><label>Active?</label><label style="display:flex;gap:8px;align-items:center;margin-top:8px"><input type="checkbox" name="is_active" value="1" <?= (!$edit || !empty($edit['is_active'])) ? 'checked' : '' ?>> Shown to customers</label></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="btn" type="submit"><?= $edit ? 'Save plan' : 'Add plan' ?></button>
          <?php if ($edit): ?><a class="btn secondary" href="/marketplace-plans">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
    <p class="muted" style="font-size:12px;margin:10px 4px 0">These plans and limits are read by the marketplace at subscribe/enforce time (next build slices). Editing them here changes what customers can buy and use.</p>
  </div>
</div>
