<?php
  // Slice 3 — the professional's marketplace membership. Free during the launch promo,
  // then a plan (Free / Plus / Top-Rank).
  $me = $me ?? []; $plans = $plans ?? []; $current = $current ?? null; $packs = $packs ?? [];
  $free = !empty($free); $freeUntil = (string)($freeUntil ?? ''); $enforce = !empty($enforce); $payOn = !empty($payOn);
  $cur = $currency ?? '₹'; $am = (int)($annualMonths ?? 10); $meId = (int)($me['id'] ?? 0);
  $money = fn($n) => e($cur) . number_format((float)$n);
  $limLabel = function_exists('mkt_limit_keys') ? mkt_limit_keys() : [];
?>
<h1>Membership &amp; plans</h1>
<p class="muted" style="margin:0 0 12px">Get seen by more clients and apply without limits. Pay monthly, or yearly for <?= $am ?> months' price.</p>

<?php if ($free): ?>
  <div class="card" style="border-left:4px solid var(--teal)">
    <p style="margin:0">🎉 <b>Free for you right now</b> — you can use the marketplace free until <b><?= e($freeUntil) ?></b> (launch offer). Upgrade to <b>Top-Rank</b> any time to appear at the top of client searches.</p>
  </div>
<?php elseif (!$enforce): ?>
  <div class="card" style="border-left:4px solid var(--teal)">
    <p style="margin:0"><b>The marketplace is currently open &amp; free.</b> You can apply to jobs without a plan. You can still subscribe below to lock in Top-Rank visibility.</p>
  </div>
<?php endif; ?>

<?php if ($current): ?>
  <div class="card"><div class="muted" style="font-size:12px">Your current plan</div>
    <div style="font-size:18px;font-weight:700"><?= e($current['name']) ?></div>
    <?php if (function_exists('mkt_limit')): $lim = mkt_limit('PRO',$meId,'applications'); $used = function_exists('mkt_usage_used') ? mkt_usage_used('PRO',$meId,'applications') : 0; if ($lim !== 0): ?>
      <div style="font-size:13px;color:var(--muted)">Applications: <b style="color:var(--ink)"><?= (int)$used ?></b> / <?= $lim < 0 ? '∞' : (int)$lim ?> this month</div>
    <?php endif; endif; ?>
    <form method="post" action="/pro/plans" style="margin-top:8px" onsubmit="return confirm('Cancel — keep access until the period ends?')">
      <input type="hidden" name="action" value="cancel_sub"><input type="hidden" name="mode" value="END">
      <button class="btn sec" type="submit" style="font-size:12.5px">Cancel plan (at period end)</button>
    </form>
  </div>
<?php endif; ?>

<div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:6px">
  <?php foreach ($plans as $p): if (empty($p['is_active'])) continue; $lim = function_exists('mkt_plan_limits') ? mkt_plan_limits($p) : []; $isCur = $current && (int)$current['id']===(int)$p['id']; $isFreePlan = (float)$p['price_month'] <= 0; ?>
    <div class="card" style="<?= $isCur ? 'border:2px solid var(--teal)' : '' ?>">
      <div style="font-weight:700;font-size:17px"><?= e($p['name']) ?><?php if ($isCur): ?> <span class="chip" style="border-color:var(--teal);color:var(--teal);font-size:11px">Current</span><?php endif; ?></div>
      <div style="font-size:22px;font-weight:800;color:var(--teal);margin:6px 0"><?= $isFreePlan ? 'Free' : $money($p['price_month']).'<span style="font-size:13px;font-weight:500;color:var(--muted)">/mo</span>' ?></div>
      <?php if (!$isFreePlan): ?><div class="muted" style="font-size:12.5px;margin-bottom:8px">or <?= $money($p['price_annual']) ?>/year</div><?php endif; ?>
      <ul style="margin:0 0 12px;padding-left:18px;font-size:13px;color:var(--muted)">
        <?php if (!empty($lim['applications'])): ?><li><?= (int)$lim['applications'] ?> applications/mo</li><?php elseif (!$isFreePlan): ?><li>Unlimited applications</li><?php endif; ?>
        <?php if (!empty($lim['featured'])): ?><li>★ Top-ranked in searches</li><?php endif; ?>
      </ul>
      <form method="post" action="/pro/plans" style="display:flex;gap:6px;flex-wrap:wrap">
        <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
        <?php if (!$isFreePlan): ?><input name="coupon" placeholder="Coupon (optional)" style="flex:1;min-width:110px;padding:6px 8px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:12.5px;text-transform:uppercase"><?php endif; ?>
        <button class="btn" name="period" value="MONTH" type="submit"><?= $isFreePlan ? 'Choose Free' : 'Monthly' ?></button>
        <?php if (!$isFreePlan): ?><button class="btn sec" name="period" value="YEAR" type="submit">Yearly</button><?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php if ($packs): ?>
  <h2 style="margin:26px 0 4px">Top-up credits</h2>
  <p class="muted" style="margin:0 0 12px">Out of applications this month? Add a credit pack — the credits go into your wallet and are used automatically after your plan's monthly quota, and they carry over.</p>
  <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <?php foreach ($packs as $p): $mk = (string)$p['metric']; $lbl = strtolower(explode(' /', $limLabel[$mk] ?? $mk)[0]);
            $bal = function_exists('mkt_credits_balance') ? mkt_credits_balance('PRO',$meId,$mk) : 0; ?>
      <div class="card">
        <div style="font-weight:700;font-size:16px"><?= e($p['name']) ?></div>
        <div style="font-size:20px;font-weight:800;color:var(--teal);margin:6px 0"><?= $money($p['price']) ?></div>
        <div class="muted" style="font-size:13px;margin-bottom:8px">Adds <b style="color:var(--ink)"><?= (int)$p['credits'] ?></b> <?= e($lbl) ?>.<?php if ($bal>0): ?><br>Wallet now: <b style="color:var(--ink)"><?= (int)$bal ?></b> <?= e($lbl) ?>.<?php endif; ?></div>
        <form method="post" action="/pro/plans">
          <input type="hidden" name="action" value="buy_pack"><input type="hidden" name="pack_id" value="<?= (int)$p['id'] ?>">
          <button class="btn" type="submit">Buy pack</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<p class="muted" style="font-size:12px;margin-top:14px"><?php if ($payOn): ?>Payment is by secure Razorpay checkout — your plan or credits activate the moment the payment is confirmed.<?php else: ?>Subscribing and buying credits records your plan/purchase and its period. (Online payment is not switched on yet — for now this activates it directly.)<?php endif; ?></p>
