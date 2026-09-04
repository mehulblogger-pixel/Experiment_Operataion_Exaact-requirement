<?php
  // Connect K21 — the freelancer's engagement vouchers. Raise a claim against a
  // booking; the rate model (all-inclusive vs fee-only) decides whether expense
  // heads are claimable.
  $me = $me ?? []; $rows = $rows ?? []; $engagements = $engagements ?? [];
  // Bookings you can still claim against (not cancelled).
  $claimable = array_filter($engagements, fn($e) => strtoupper((string)$e['status']) !== 'CANCELLED');
  $statusPill = function ($s) {
      $s = strtoupper((string)$s);
      $m = ['DRAFT' => ['Draft', '#5b6b6a', 'rgba(0,0,0,.06)'],
            'SUBMITTED' => ['Submitted', '#1858a8', 'rgba(24,88,168,.12)'],
            'APPROVED' => ['Approved', '#0f7d5a', 'var(--okbg)'],
            'PAID' => ['Paid', '#0f7d5a', 'var(--okbg)'],
            'REJECTED' => ['Sent back', '#9a2a2a', '#f6e7e6']];
      [$l, $c, $bg] = $m[$s] ?? $m['DRAFT'];
      return '<span style="display:inline-block;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:700;color:' . $c . ';background:' . $bg . '">' . e($l) . '</span>';
  };
  $inr = fn($n) => '₹' . number_format((int)round((float)$n));
?>
<h1>My vouchers</h1>
<p class="muted" style="margin:0 0 14px">Claim your days — and, when the rate is quoted <strong>fee&nbsp;only</strong>, your travel, hotel, local conveyance and allowances against receipts. An <strong>all-inclusive</strong> rate needs no expense claim.</p>

<?php // Raise a voucher against a booking. ?>
<?php if ($claimable): ?>
<div class="card">
  <h2>Raise a voucher</h2>
  <?php foreach ($claimable as $e):
    $model = strtoupper((string)($e['rate_inclusive'] ?? 'INCLUSIVE'));
    $cad = function_exists('connect_engage_voucher_cadence_label') ? connect_engage_voucher_cadence_label($e['voucher_cadence'] ?? 'PER_DEPLOYMENT') : '';
    $basis = function_exists('connect_engage_basis_label') ? connect_engage_basis_label($e['basis']) : $e['basis'];
    $rate = (float)($e['rate'] ?? 0); $unit = (string)($e['rate_unit'] ?? 'day');
  ?>
  <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 0;border-top:1px solid var(--line)">
    <div>
      <strong><?= e($e['req_title'] ?: 'Engagement') ?></strong>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:5px">
        <span class="chip"><?= e($basis) ?></span>
        <span class="chip" style="<?= $model === 'EXCLUSIVE' ? 'border-color:var(--gold);color:var(--gold)' : 'border-color:var(--teal);color:var(--teal)' ?>"><?= $model === 'EXCLUSIVE' ? 'Fee + expenses' : 'All-inclusive' ?></span>
        <?php if ($rate > 0): ?><span class="chip"><?= e($inr($rate)) ?>/<?= e($unit) ?></span><?php endif; ?>
        <?php if ($cad): ?><span class="chip">🧾 <?= e($cad) ?></span><?php endif; ?>
      </div>
    </div>
    <form method="post" action="/pro/vouchers" style="margin:0">
      <input type="hidden" name="action" value="raise">
      <input type="hidden" name="engagement_id" value="<?= (int)$e['id'] ?>">
      <button class="btn" type="submit">Raise →</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
  <?php if (!$claimable): ?>
  <div class="card"><p class="muted" style="margin:0">No bookings yet, so nothing to claim. When a hiring desk books you, raise a voucher here. <a href="/pro/jobs">Browse open jobs →</a></p></div>
  <?php else: ?>
  <p class="muted" style="margin:14px 0 0">No vouchers yet — raise one above.</p>
  <?php endif; ?>
<?php else: ?>
<h2 style="margin:22px 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)">Your vouchers</h2>
<?php foreach ($rows as $v): $exclusive = strtoupper((string)$v['rate_inclusive']) === 'EXCLUSIVE'; ?>
  <a class="card" href="/pro/voucher?id=<?= (int)$v['id'] ?>" style="display:block;text-decoration:none;color:inherit">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:start">
      <div>
        <strong><?= e($v['req_title'] ?: 'Engagement') ?></strong>
        <div class="muted" style="font-size:13px"><?= e($v['period_label'] ?: '') ?> · <?= $exclusive ? 'Fee + expenses' : 'All-inclusive' ?></div>
      </div>
      <?= $statusPill($v['status']) ?>
    </div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:13px">
      <span>Fee <strong><?= e($inr($v['fee_total'])) ?></strong></span>
      <?php if ($exclusive): ?><span class="muted">Expenses <strong style="color:var(--ink)"><?= e($inr($v['reimb_total'])) ?></strong></span><?php endif; ?>
      <span>Total <strong style="color:var(--teal)"><?= e($inr($v['grand_total'])) ?></strong></span>
    </div>
  </a>
<?php endforeach; endif; ?>
