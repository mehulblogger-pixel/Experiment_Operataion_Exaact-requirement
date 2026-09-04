<?php
  // Slice 3/4 — the client's marketplace subscription. Pick a plan (monthly or annual),
  // and top up with credit packs when a monthly limit runs out.
  $plans = $plans ?? []; $current = $current ?? null; $enforce = !empty($enforce);
  $packs = $packs ?? []; $payOn = !empty($payOn);
  $cur = $currency ?? '₹'; $am = (int)($annualMonths ?? 10); $party = (int)($party ?? 0);
  $money = fn($n) => e($cur) . number_format((float)$n);
  $limLabel = function_exists('mkt_limit_keys') ? mkt_limit_keys() : [];
?>
<h2 class="ptitle">Marketplace plans</h2>
<p class="plead" style="margin:0 0 12px">Subscribe to post work and reach the professional pool. Pay monthly, or yearly for <?= $am ?> months' price (2 months free).</p>

<?php if (!$enforce): ?>
  <div class="pcard" style="max-width:760px;border-left:4px solid var(--teal,#0f7d7d)">
    <p style="margin:0"><b>The marketplace is currently open &amp; free</b> — you can post and hire without a plan right now. Subscriptions become required when we launch paid access; you can subscribe early below.</p>
  </div>
<?php endif; ?>

<?php if ($current): ?>
  <div class="pcard" style="max-width:760px">
    <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Your current plan</div>
    <div style="font-size:18px;font-weight:700;margin:2px 0 6px"><?= e($current['name']) ?></div>
    <?php // this month's usage against the plan ?>
    <?php foreach (['posts','unlocks','reports'] as $mk):
            if (!function_exists('mkt_limit')) break;
            $lim = mkt_limit('CLIENT',$party,$mk); if ($lim === 0) continue;
            $used = function_exists('mkt_usage_used') ? mkt_usage_used('CLIENT',$party,$mk) : 0; ?>
      <div style="font-size:13px;color:var(--muted)"><?= e($limLabel[$mk] ?? $mk) ?>: <b style="color:var(--ink)"><?= (int)$used ?></b> / <?= $lim < 0 ? '∞' : (int)$lim ?> this month</div>
    <?php endforeach; ?>
    <form method="post" action="/portal/plans" style="margin-top:8px" onsubmit="return confirm('Cancel — keep access until the period ends?')">
      <input type="hidden" name="action" value="cancel_sub"><input type="hidden" name="mode" value="END">
      <button class="btn sec" type="submit" style="font-size:12.5px">Cancel plan (at period end)</button>
    </form>
  </div>
<?php endif; ?>

<div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));max-width:900px;margin-top:6px">
  <?php foreach ($plans as $p): if (empty($p['is_active'])) continue; $lim = function_exists('mkt_plan_limits') ? mkt_plan_limits($p) : []; $isCur = $current && (int)$current['id']===(int)$p['id']; ?>
    <div class="pcard" style="<?= $isCur ? 'border:2px solid var(--teal,#0f7d7d)' : '' ?>">
      <div style="font-weight:700;font-size:17px"><?= e($p['name']) ?><?php if ($isCur): ?> <span class="ppill ok" style="font-size:11px">Current</span><?php endif; ?></div>
      <div style="font-size:22px;font-weight:800;color:#0a5c5c;margin:6px 0"><?= $money($p['price_month']) ?><span style="font-size:13px;font-weight:500;color:var(--muted)">/month</span></div>
      <div class="muted" style="font-size:12.5px;margin-bottom:8px">or <?= $money($p['price_annual']) ?>/year</div>
      <ul style="margin:0 0 12px;padding-left:18px;font-size:13px;color:var(--ink-2)">
        <?php foreach ($lim as $k=>$v): if ((int)$v<=0) continue; ?><li><?= (int)$v ?> <?= e(strtolower(explode(' /', $limLabel[$k] ?? $k)[0])) ?>/mo</li><?php endforeach; ?>
        <?php if (!array_filter($lim)): ?><li>Unlimited use</li><?php endif; ?>
      </ul>
      <form method="post" action="/portal/plans" style="display:flex;gap:6px;flex-wrap:wrap">
        <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
        <input name="coupon" placeholder="Coupon (optional)" style="flex:1;min-width:120px;padding:6px 8px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:12.5px;text-transform:uppercase">
        <button class="btn" name="period" value="MONTH" type="submit">Monthly</button>
        <button class="btn sec" name="period" value="YEAR" type="submit">Yearly</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php if ($packs): ?>
  <h3 class="ptitle" style="margin-top:26px">Top-up credits</h3>
  <p class="plead" style="margin:0 0 12px">Run out of a monthly limit? Add a credit pack — the credits sit in your wallet and are used automatically after your plan's monthly quota, and they don't expire at month-end.</p>
  <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));max-width:900px">
    <?php foreach ($packs as $p): $mk = (string)$p['metric']; $lbl = strtolower(explode(' /', $limLabel[$mk] ?? $mk)[0]);
            $bal = function_exists('mkt_credits_balance') ? mkt_credits_balance('CLIENT',$party,$mk) : 0; ?>
      <div class="pcard">
        <div style="font-weight:700;font-size:16px"><?= e($p['name']) ?></div>
        <div style="font-size:20px;font-weight:800;color:#0a5c5c;margin:6px 0"><?= $money($p['price']) ?></div>
        <div class="muted" style="font-size:13px;margin-bottom:8px">Adds <b style="color:var(--ink)"><?= (int)$p['credits'] ?></b> <?= e($lbl) ?>.<?php if ($bal>0): ?><br>Wallet now: <b style="color:var(--ink)"><?= (int)$bal ?></b> <?= e($lbl) ?>.<?php endif; ?></div>
        <form method="post" action="/portal/plans">
          <input type="hidden" name="action" value="buy_pack"><input type="hidden" name="pack_id" value="<?= (int)$p['id'] ?>">
          <button class="btn" type="submit">Buy pack</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<p class="muted" style="font-size:12px;margin-top:14px;max-width:760px"><?php if ($payOn): ?>Payment is by secure Razorpay checkout — your plan or credits activate the moment the payment is confirmed.<?php else: ?>Subscribing and buying credits records your plan/purchase and its period. (Online payment is not switched on yet — for now this activates it directly.)<?php endif; ?></p>
