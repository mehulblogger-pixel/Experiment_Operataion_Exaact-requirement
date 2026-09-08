<?php
// A company's own self-service subscription — review the plan, and buy extra
// modules / seats à la carte with a live quote, paying online. Runs inside the
// company's OWN workspace. Uses the app's standard panel / kpi / msg styles.
$sub = $sub ?? []; $pb = $pb ?? []; $cfg = $cfg ?? []; $modules = $modules ?? []; $history = $history ?? [];
$metered = !empty($metered);
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$cur = $pb['currency'] ?? ($cfg['currency'] ?? 'INR');
$sym = $cur === 'INR' ? '₹' : ($cur . ' ');
$payOn = !empty($cfg['enabled']);
$onMods = $sub['modules'] ?? []; $addable = $sub['addable'] ?? [];
$modLabel = fn($k) => $modules[$k][0] ?? ucfirst($k);
$fmt = fn($n) => $sym . number_format((int) $n);
?>
<div class="crumbs"><a href="/">Home</a> › Subscription</div>
<div class="master-head">
  <div><h1>Your subscription</h1>
    <p class="sub" style="margin:2px 0 0">See what your plan includes, and add exactly the modules or seats you need — you pay only for what you use.</p></div>
</div>

<div class="kpi-row" style="margin-top:14px">
  <div class="kpi"><span class="k-lab">People active now</span><span class="k-val"><?= (int) ($sub['seats_used'] ?? 0) ?></span></div>
  <div class="kpi <?= ($sub['seat_limit'] ?? 0) ? 'tone-ok' : '' ?>"><span class="k-lab">Seats in your plan</span>
    <span class="k-val"><?= ($sub['seat_limit'] ?? 0) ? (int) $sub['seat_limit'] : 'unlimited' ?></span></div>
  <div class="kpi"><span class="k-lab">Active until</span>
    <span class="k-val" style="font-size:18px"><?= !empty($sub['paid_until']) ? $e(fdate($sub['paid_until'])) : '—' ?></span></div>
</div>
<?php if (($sub['seat_limit'] ?? 0) && ($sub['seats_used'] ?? 0) > $sub['seat_limit']): ?>
  <div class="msg msg-warning" style="margin-top:8px"><?= (int) $sub['seats_used'] ?> people are active but your plan covers <?= (int) $sub['seat_limit'] ?> seats. Add seats below, or deactivate people who have left.</div>
<?php endif; ?>

<div class="panel" style="margin-top:14px">
  <h2 style="margin:0 0 8px;font-size:15px">What your plan includes today</h2>
  <?php if ($onMods): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($onMods as $k): ?><span class="pill" style="background:var(--soft,#eef2f7);border:1px solid var(--line,#e5e7eb);border-radius:999px;padding:4px 11px;font-size:12.5px;font-weight:600">✓ <?= $e($modLabel($k)) ?></span><?php endforeach; ?>
      <span class="pill" style="background:var(--soft,#eef2f7);border:1px solid var(--line,#e5e7eb);border-radius:999px;padding:4px 11px;font-size:12.5px;font-weight:600">✓ Administration</span>
    </div>
  <?php else: ?>
    <p class="sub" style="margin:0">Your plan currently includes the core Administration only.</p>
  <?php endif; ?>
</div>

<?php if (!$metered): ?>
  <div class="msg" style="margin-top:14px">This workspace is managed directly by your provider, so there is nothing to buy here. Contact your provider to change your plan.</div>
<?php elseif (!$payOn): ?>
  <div class="msg" style="margin-top:14px">Online payment is not switched on for your workspace yet. To add modules or seats, please contact your provider.</div>
<?php elseif (!$addable && true): ?>
  <div class="panel" style="margin-top:14px">
    <h2 style="margin:0 0 8px;font-size:15px">Add more seats</h2>
    <p class="sub" style="margin:0 0 10px">You already have every module. Need more people? Add seats below.</p>
    <?php $addable = []; /* fall through to the builder, modules list simply empty */ ?>
    <?php include __DIR__ . '/_subscription_builder.php'; ?>
  </div>
<?php else: ?>
  <div class="panel" style="margin-top:14px">
    <h2 style="margin:0 0 4px;font-size:15px">Add modules or seats</h2>
    <p class="sub" style="margin:0 0 10px">Tick what you want and set how many seats to add. The price updates live; you pay securely online and it activates the moment the payment clears.</p>
    <?php include __DIR__ . '/_subscription_builder.php'; ?>
  </div>
<?php endif; ?>

<?php if ($history): ?>
  <div class="panel" style="margin-top:14px">
    <h2 style="margin:0 0 8px;font-size:15px">Payment history</h2>
    <div style="overflow-x:auto"><table class="tbl" style="width:100%;border-collapse:collapse;min-width:560px">
      <thead><tr>
        <th style="text-align:left;font-size:11px;text-transform:uppercase;color:var(--muted,#6b7280);padding:6px 8px">Date</th>
        <th style="text-align:left;font-size:11px;text-transform:uppercase;color:var(--muted,#6b7280);padding:6px 8px">What</th>
        <th style="text-align:right;font-size:11px;text-transform:uppercase;color:var(--muted,#6b7280);padding:6px 8px">Amount</th>
        <th style="text-align:left;font-size:11px;text-transform:uppercase;color:var(--muted,#6b7280);padding:6px 8px">Active until</th>
      </tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr style="border-top:1px solid var(--line,#eef1f5)">
          <td style="padding:7px 8px;font-size:13px"><?= $e(function_exists('fdate') ? fdate(substr((string) ($h['created_at'] ?? ''), 0, 10)) : substr((string) ($h['created_at'] ?? ''), 0, 10)) ?></td>
          <td style="padding:7px 8px;font-size:13px"><?= $e($h['note'] ?? (((int) ($h['seats'] ?? 0)) . ' seats')) ?></td>
          <td style="padding:7px 8px;font-size:13px;text-align:right"><?= $e($fmt($h['amount'] ?? 0)) ?></td>
          <td style="padding:7px 8px;font-size:13px"><?= $e(!empty($h['paid_until']) ? (function_exists('fdate') ? fdate($h['paid_until']) : $h['paid_until']) : '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
