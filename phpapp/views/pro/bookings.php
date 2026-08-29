<?php
  // Connect K20 — the freelancer's own bookings/engagements. Once a job is booked,
  // this shows the real commitment: man-days, man-months, deputation, continuous,
  // or a regular frequency — with dates, rate and status.
  $me = $me ?? []; $rows = $rows ?? [];
  $statusPill = function ($s) {
      $s = strtoupper((string)$s);
      $m = ['BOOKED' => ['Booked', '#1858a8', 'rgba(24,88,168,.12)'],
            'ACTIVE' => ['Active', '#0f7d5a', 'var(--okbg)'],
            'COMPLETED' => ['Completed', '#5b6b6a', 'rgba(0,0,0,.06)'],
            'CANCELLED' => ['Cancelled', '#9a2a2a', '#f6e7e6']];
      [$l, $c, $bg] = $m[$s] ?? $m['BOOKED'];
      return '<span style="display:inline-block;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:700;color:' . $c . ';background:' . $bg . '">' . e($l) . '</span>';
  };
  $dates = function ($e) {
      $s = substr((string)($e['start_date'] ?? ''), 0, 10); $en = substr((string)($e['end_date'] ?? ''), 0, 10);
      if ($s && $en) return $s . ' → ' . $en;
      if ($s) return 'from ' . $s;
      if ($en) return 'until ' . $en;
      return '';
  };
?>
<h1>My bookings</h1>
<p class="muted" style="margin:0 0 14px">Jobs you've been booked for — man-days, man-months, deputation, continuous or a regular frequency.</p>

<?php if (!$rows): ?>
  <div class="card"><p class="muted" style="margin:0">No bookings yet. When a hiring desk awards you a job, it appears here with your commitment and rate. <a href="/pro/jobs">Browse open jobs →</a></p></div>
<?php else: foreach ($rows as $e):
  $d = function_exists('connect_engage_describe') ? connect_engage_describe($e) : ['commitment' => '', 'rate' => '', 'total' => null];
  $dt = $dates($e); ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:start">
      <div>
        <strong><?= e($e['req_title'] ?: 'Engagement') ?></strong>
        <div class="muted" style="font-size:13px"><?= e($e['ref_code'] ?? '') ?><?php if (!empty($e['location'])): ?> · <?= e($e['location']) ?><?php endif; ?><?php if (!empty($e['poster_name'])): ?> · <?= e($e['poster_name']) ?><?php endif; ?></div>
      </div>
      <?= $statusPill($e['status']) ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <span class="chip" style="border-color:var(--teal);color:var(--teal)"><?= e(function_exists('connect_engage_basis_label') ? connect_engage_basis_label($e['basis']) : $e['basis']) ?></span>
      <?php if (!empty($d['commitment'])): ?><span class="chip"><?= e($d['commitment']) ?></span><?php endif; ?>
      <?php if (!empty($d['rate'])): ?><span class="chip"><?= e($d['rate']) ?></span><?php endif; ?>
      <?php if ($dt !== ''): ?><span class="chip">📅 <?= e($dt) ?></span><?php endif; ?>
    </div>
    <?php if ($d['total'] !== null): ?><div class="muted" style="font-size:13px;margin-top:8px">Estimated value: <strong style="color:var(--ink)">₹<?= number_format((int)$d['total']) ?></strong></div><?php endif; ?>
    <?php if (!empty($e['notes'])): ?><div class="muted" style="font-size:13px;margin-top:6px"><?= e($e['notes']) ?></div><?php endif; ?>
  </div>
<?php endforeach; endif; ?>
