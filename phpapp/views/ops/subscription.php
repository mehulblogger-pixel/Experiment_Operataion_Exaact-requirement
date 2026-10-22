<?php
// A company's own self-service subscription — review the plan, and buy extra
// modules / seats à la carte with a live quote, paying online. Runs inside the
// company's OWN workspace. Uses the app's standard panel / kpi / msg styles.
$sub = $sub ?? []; $pb = $pb ?? []; $cfg = $cfg ?? []; $modules = $modules ?? []; $history = $history ?? [];
$metered = !empty($metered);
$self = !empty($self); $access = $access ?? null; $current = $current ?? null; $next = $next ?? null; $offer = $offer ?? null; $storage = $storage ?? null;
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$cur = $pb['currency'] ?? ($cfg['currency'] ?? 'INR');
$sym = $cur === 'INR' ? '₹' : ($cur . ' ');
$payOn = !empty($cfg['enabled']);
$onMods = $sub['modules'] ?? []; $addable = $sub['addable'] ?? [];
$modLabel = fn($k) => $modules[$k][0] ?? ucfirst($k);
$fmt = fn($n) => $sym . number_format((int) $n);
?>
<div class="master-head">
  <div><h1>Your subscription</h1>
    <p class="sub" style="margin:2px 0 0">See what your plan includes, and add exactly the modules or seats you need — you pay only for what you use.</p></div>
</div>

<?php if ($self && $access): $st = $access['mode']; ?>
  <div class="kpi-row" style="margin-top:14px">
    <div class="kpi <?= $st === 'locked' ? 'tone-bad' : ($st === 'trial' ? '' : 'tone-ok') ?>"><span class="k-lab">Your workspace</span>
      <span class="k-val" style="font-size:18px"><?= $st === 'trial' ? 'Free trial' : ($st === 'paid' ? 'Plan active' : 'View-only') ?></span></div>
    <div class="kpi"><span class="k-lab"><?= $st === 'locked' ? 'Ended on' : 'Until' ?></span>
      <span class="k-val" style="font-size:18px"><?= $access['until'] !== '' ? $e(fdate($access['until'])) : '—' ?></span></div>
    <?php if ($st !== 'locked'): ?><div class="kpi"><span class="k-lab">Days left</span><span class="k-val"><?= (int) $access['days_left'] ?></span></div><?php endif; ?>
  </div>
  <?php if ($st === 'locked'): ?>
    <div class="msg msg-error" style="margin-top:8px">Your <?= ($access['was'] ?? '') === 'paid' ? 'plan' : 'free trial' ?> has ended, so your workspace is view-only.
      Everything you recorded is safe and can be opened, printed and exported. Choose a plan below to carry on saving new work.</div>
  <?php elseif ($st === 'trial'): ?>
    <div class="msg msg-info" style="margin-top:8px">You are trying every module free until <?= $e(fdate($access['until'])) ?>.
      Choose the modules you want to keep below — you pay only for those.</div>
  <?php endif; ?>
  <?php if ($next): ?>
    <div class="msg msg-info" style="margin-top:8px">Booked: from <?= $e(fdate($next['starts'])) ?> your plan becomes
      <?= $e(implode(', ', array_map($modLabel, (array) $next['mods']))) ?> for <?= (int) $next['seats'] ?> <?= (int) $next['seats'] === 1 ? 'person' : 'people' ?>
      (<?= !empty($next['paid']) ? 'already paid' : 'to be paid then' ?>).</div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$self): ?>
<div class="kpi-row" style="margin-top:14px">
  <div class="kpi"><span class="k-lab">People active now</span><span class="k-val"><?= (int) ($sub['seats_used'] ?? 0) ?></span></div>
  <div class="kpi <?= ($sub['seat_limit'] ?? 0) ? 'tone-ok' : '' ?>"><span class="k-lab">Seats in your plan</span>
    <span class="k-val"><?= ($sub['seat_limit'] ?? 0) ? (int) $sub['seat_limit'] : 'unlimited' ?></span></div>
  <div class="kpi"><span class="k-lab">Active until</span>
    <span class="k-val" style="font-size:18px"><?= !empty($sub['paid_until']) ? $e(fdate($sub['paid_until'])) : '—' ?></span></div>
</div>
<?php endif; ?>
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

<?php if ($self): ?>
  <?php if (!$payOn): ?>
    <div class="msg" style="margin-top:14px">Online payment is not switched on for your workspace yet. To choose a plan, please contact your provider.</div>
  <?php else: ?>
  <div class="panel" style="margin-top:14px">
    <h2 style="margin:0 0 4px;font-size:15px"><?= ($access['mode'] ?? '') === 'paid' ? 'Change or renew your plan' : 'Choose your plan' ?></h2>
    <p class="sub" style="margin:0 0 12px">You pay securely online and the plan starts the moment the payment clears.</p>
    <?php include __DIR__ . '/_subscription_plan.php'; ?>
  </div>
  <?php endif; ?>
<?php elseif (!$metered): ?>
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

<?php if ($storage): $lim = (int) $storage['limit']; $usedB = (int) $storage['used'];
      $pct = $lim > 0 ? min(100, (int) floor(100 * $usedB / $lim)) : null; $sc = $storage['cfg']; ?>
  <div class="panel" style="margin-top:14px">
    <h2 style="margin:0 0 6px;font-size:15px">Storage</h2>
    <?php if ($pct !== null): ?>
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:14px;margin-bottom:6px">
        <span><b><?= $e(saas_bytes_label($usedB)) ?></b> of <?= $e(saas_bytes_label($lim)) ?> used</span><span class="sub"><?= (int) $pct ?>%</span></div>
      <div role="progressbar" aria-valuenow="<?= (int) $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Storage used"
           style="height:10px;border-radius:999px;background:var(--soft,#eef2f7);overflow:hidden">
        <div style="height:100%;width:<?= (int) $pct ?>%;background:<?= $pct >= 95 ? 'var(--bad,#dc2626)' : ($pct >= 80 ? 'var(--warn,#d97706)' : 'var(--ok,#16a34a)') ?>"></div></div>
      <?php if ($pct >= 100): ?><div class="msg msg-error" style="margin-top:10px">Storage is full: new files cannot be added. Everything already stored is safe, and records without files still save.</div><?php endif; ?>
      <p class="sub" style="margin:8px 0 0;font-size:12.5px">
        <?php if (!empty($storage['trial'])): ?>Your free trial includes <?= $e(saas_bytes_label($lim)) ?>.
        <?php else: $parts = [];
          if ($sc['base_gb'] > 0) $parts[] = $sc['base_gb'] . ' GB with your plan';
          if ($sc['per_login_gb'] > 0) $parts[] = $sc['per_login_gb'] . ' GB × ' . (int) $storage['logins'] . ' logins';
          if ($sc['packs'] > 0 && $sc['pack_gb'] > 0) $parts[] = (int) $sc['packs'] . ' extra pack' . ($sc['packs'] === 1 ? '' : 's') . ' × ' . $sc['pack_gb'] . ' GB';
          if ($sc['extra_gb'] > 0) $parts[] = $sc['extra_gb'] . ' GB added by your provider'; ?>
          Your allowance: <?= $e(implode(' + ', $parts)) ?>.
        <?php endif; ?></p>
    <?php else: ?>
      <p style="margin:0 0 4px;font-size:14px"><b><?= $e(saas_bytes_label($usedB)) ?></b> used — no storage limit is set for your workspace.</p>
    <?php endif; ?>
    <details style="margin-top:10px"><summary style="cursor:pointer;font-size:13px;font-weight:600">What is using the space</summary>
      <table class="tbl" style="width:100%;border-collapse:collapse;margin-top:6px">
        <?php foreach ($storage['breakdown'] as $lab => $n): ?>
          <tr style="border-top:1px solid var(--line,#eef1f5)"><td style="padding:6px 8px;font-size:13px"><?= $e($lab) ?></td>
            <td style="padding:6px 8px;font-size:13px;text-align:right"><?= $e(saas_bytes_label($n)) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <p class="sub" style="margin:6px 0 0;font-size:12px">Photos are made smaller automatically when they are uploaded. Files are never deleted for being over the allowance.</p>
    </details>
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
