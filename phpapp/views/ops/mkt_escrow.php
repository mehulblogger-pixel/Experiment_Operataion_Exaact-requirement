<?php
  // Marketplace escrow desk — hold the client's money, release it to the professional
  // when the report is approved, refund on cancellation, park on dispute. Gateway-OFF
  // for now (no real money moves); the switch keeps it invisible until turned on.
  $rows = $rows ?? []; $totals = $totals ?? []; $enabled = !empty($enabled);
  $cur = $currency ?? '₹'; $commissionPct = (float)($commissionPct ?? 0);
  $money = fn($n) => e($cur) . number_format((float)$n, 2);
  $badge = function ($s) {
      $s = strtoupper((string)$s);
      $c = ['HELD' => '#0a5c5c', 'DISPUTED' => '#8a5a00', 'RELEASED' => '#2f7a34', 'REFUNDED' => '#9a2a2a'][$s] ?? '#667';
      return '<span style="display:inline-block;font-size:11px;font-weight:700;letter-spacing:.03em;color:#fff;background:' . $c . ';border-radius:999px;padding:2px 9px">' . e($s) . '</span>';
  };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/super-admin">Super Admin</a> › Escrow</div>
<div class="master-head">
  <div><h1>Marketplace escrow</h1>
    <p class="sub">Hold the client’s money when a job is booked; release it to the professional when the report is approved. Refund on cancellation, park on a dispute.</p></div>
  <a class="btn secondary" href="/super-admin">← Super Admin</a>
</div>

<?php if (!$enabled): ?>
  <div class="panel" style="border-left:4px solid var(--teal,#0a5c5c);max-width:900px">
    <p style="margin:0"><b>Escrow is currently OFF.</b> Nothing changes for anyone — no holds are created and the marketplace behaves exactly as today. Turn it on only when your payment aggregator (e.g. Razorpay Route) is approved and your CA has confirmed the tax position. <b>No real money moves in this build yet</b> — this desk models the hold → release/refund lifecycle so the flow is ready.</p>
  </div>
<?php endif; ?>

<?php // ---- Settings ---- ?>
<div class="panel" style="max-width:900px">
  <h3 class="tab-sub" style="margin-top:0">Settings</h3>
  <form method="post" action="/marketplace-escrow" class="form-grid" style="align-items:end">
    <input type="hidden" name="action" value="toggle">
    <div class="ff" style="grid-column:1/-1">
      <label style="display:flex;gap:10px;align-items:center;background:var(--soft,#f6faf9);border:1px solid var(--line);border-radius:10px;padding:10px 12px">
        <input type="checkbox" name="escrow_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
        <span><b>Turn escrow on</b> — hold client funds and release on report approval.
          <span class="muted" style="display:block;font-size:12px"><?= $enabled ? 'ON — new bookings can be funded into escrow and released when the work is proven done.' : 'OFF — the marketplace is unchanged. This is the safe default.' ?></span></span>
      </label>
    </div>
    <div class="ff" style="display:flex;gap:8px;align-items:end"><button class="btn" type="submit">Save</button></div>
  </form>
  <form method="post" action="/marketplace-escrow" class="form-grid" style="align-items:end;margin-top:8px">
    <input type="hidden" name="action" value="settings">
    <div class="ff"><label>Platform commission at release (%)</label>
      <input class="form-control" name="escrow_commission_pct" type="number" min="0" max="100" step="0.1" value="<?= $commissionPct ?>">
      <small class="muted">Taken from the hold when funds are released; the rest goes to the professional.</small></div>
    <div class="ff" style="display:flex;gap:8px;align-items:end"><button class="btn secondary" type="submit">Save commission</button></div>
  </form>
</div>

<?php // ---- Totals ---- ?>
<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));max-width:900px;margin:14px 0">
  <?php foreach (['HELD' => 'In escrow now', 'RELEASED' => 'Released to pros', 'REFUNDED' => 'Refunded', 'DISPUTED' => 'In dispute'] as $k => $lbl): ?>
    <div class="panel" style="margin:0;padding:14px 16px">
      <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em"><?= e($lbl) ?></div>
      <div style="font-size:20px;font-weight:800;color:var(--ink)"><?= $money($totals[$k] ?? 0) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php // ---- Holds ---- ?>
<div class="panel" style="max-width:1000px">
  <h3 style="margin-top:0">Escrow holds</h3>
  <?php if (!$rows): ?>
    <p class="muted">No escrow holds yet. When escrow is on, a booking funds a hold here.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
    <table class="grid" style="margin:0"><thead><tr>
      <th>#</th><th>Client → Professional</th><th>Amount</th><th>Commission</th><th>To pro</th><th>Status</th><th>Actions</th>
    </tr></thead><tbody>
    <?php foreach ($rows as $r): $st = strtoupper((string)$r['status']); ?>
      <tr>
        <td class="muted" style="font-size:12px">E-<?= (int)$r['id'] ?><br><span style="font-size:11px">eng <?= (int)$r['engagement_id'] ?></span></td>
        <td style="font-size:13px"><b><?= e($r['client_name'] ?: ('Party ' . (int)$r['client_party_id'])) ?></b><br><span class="muted">→ <?= e($r['pro_name'] ?: ('Pro ' . (int)$r['pro_id'])) ?></span>
          <?php if (!empty($r['dispute_reason']) && $st === 'DISPUTED'): ?><br><span style="color:#8a5a00;font-size:12px">⚠ <?= e($r['dispute_reason']) ?></span><?php endif; ?></td>
        <td><?= $money($r['amount']) ?></td>
        <td class="muted"><?= $money($r['commission']) ?></td>
        <td><?= $money($r['net_to_pro']) ?></td>
        <td><?= $badge($st) ?></td>
        <td style="white-space:nowrap">
          <?php if ($st === 'HELD'): ?>
            <form method="post" action="/marketplace-escrow" style="display:inline" onsubmit="return confirm('Release these funds to the professional?')">
              <input type="hidden" name="action" value="release"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small" type="submit">Release</button></form>
            <form method="post" action="/marketplace-escrow" style="display:inline" onsubmit="return confirm('Refund these funds to the client?')">
              <input type="hidden" name="action" value="refund"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small secondary" type="submit">Refund</button></form>
            <form method="post" action="/marketplace-escrow" style="display:inline" onsubmit="return this.reason.value=prompt('Reason for the dispute?')||false">
              <input type="hidden" name="action" value="dispute"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="reason" value="">
              <button class="btn small" style="background:#8a5a00" type="submit">Dispute</button></form>
          <?php elseif ($st === 'DISPUTED'): ?>
            <form method="post" action="/marketplace-escrow" style="display:inline" onsubmit="return confirm('Resolve in favour of the professional and release?')">
              <input type="hidden" name="action" value="resolve"><input type="hidden" name="outcome" value="RELEASE"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small" type="submit">Resolve → Release</button></form>
            <form method="post" action="/marketplace-escrow" style="display:inline" onsubmit="return confirm('Resolve in favour of the client and refund?')">
              <input type="hidden" name="action" value="resolve"><input type="hidden" name="outcome" value="REFUND"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small secondary" type="submit">Resolve → Refund</button></form>
          <?php else: ?>
            <span class="muted" style="font-size:12px"><?= $st === 'RELEASED' ? 'Paid out ' . e(substr((string)$r['released_at'],0,10)) : 'Refunded ' . e(substr((string)$r['refunded_at'],0,10)) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    </div>
  <?php endif; ?>
</div>
<p class="muted" style="font-size:12px;max-width:900px;margin-top:10px">Release is meant to fire automatically when an inspection report is approved — the platform’s built-in “work is proven done” signal. Real money movement (holding &amp; splitting via a licensed aggregator) is wired in Step 2.</p>
