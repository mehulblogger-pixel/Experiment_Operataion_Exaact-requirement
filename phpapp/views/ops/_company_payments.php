<?php
// Super Admin → Companies → Manage → Payments & refunds (lib/saas_payments.php).
// The company's payments read from its own workspace, each with a Refund form
// that follows the owner's rules (8 Nov 2026): no refunds as a rule; bought by
// mistake or paid twice — within the refund window; our own error — any time.
// Data: $pay (saas_company_payments()), $sk (company key, escaped), $e, $modules.
$pay = is_array($pay ?? null) ? $pay : ['ok' => false];
$money = fn($n) => '₹' . number_format((int) $n);
$fd = fn($d) => function_exists('fdate') ? fdate(substr((string) $d, 0, 10)) : substr((string) $d, 0, 10);
$days = function_exists('saas_refund_days') ? saas_refund_days() : 3;
$reasons = function_exists('saas_refund_reasons') ? saas_refund_reasons() : [];
$modName = fn($k) => isset($modules[$k]) ? ($modules[$k][0] ?? $k) : $k;
$stLabel = ['PAID' => ['Paid', '#047857', '#ecfdf5'], 'PART_REFUNDED' => ['Part refunded', '#92400e', '#fffbeb'], 'REFUNDED' => ['Refunded', '#6b7280', '#f3f4f6']];
?>
<div id="payments" style="padding:16px;border-top:1px solid var(--line,#eef1f5);scroll-margin-top:70px">
  <label style="font-size:12px;font-weight:600">Payments &amp; refunds</label>
  <div class="sc-note" style="margin-top:4px">Your rule: <b>no refunds</b> — every company has a free trial first.
    Exceptions: <b>bought by mistake</b> or <b>paid twice</b> — within <?= (int) $days ?> day<?= $days === 1 ? '' : 's' ?> of paying;
    <b>our own error</b> (a wrong price, or it could not be switched on) — any time. A full refund ends what that payment bought
    (if it started the paid plan, the company keeps its data only, view-only); a part refund ends just the part refunded, at once.
    The money goes back through Razorpay to the card / UPI it came from, and a GST credit note is issued when the payment had a tax invoice.</div>

  <?php if (empty($pay['ok'])): ?>
    <div class="sc-note">This company's workspace could not be opened just now, so its payments cannot be shown.</div>
  <?php else: ?>

  <?php foreach ((array) ($pay['stuck'] ?? []) as $sr): ?>
    <div style="margin:8px 0;padding:10px 12px;border:1px solid #fcd34d;background:#fffbeb;border-radius:10px;font-size:13.5px">
      <b>A refund did not finish:</b> <?= $money($sr['amount']) ?> on payment <?= $e($sr['payment_id']) ?>, started <?= $e(substr((string) $sr['created_at'], 0, 16)) ?> by <?= $e($sr['by_name']) ?>.
      Check the payment in your Razorpay Dashboard before refunding it again — the money may already have been returned.
    </div>
  <?php endforeach; ?>
  <?php foreach ((array) $pay['extras'] as $x): if ($x['status'] !== 'OPEN') continue; ?>
    <div style="margin:8px 0;padding:10px 12px;border:1px solid #fca5a5;background:#fef2f2;border-radius:10px;font-size:13.5px">
      <b>Paid twice:</b> a second payment of <?= $money($x['amount']) ?> (<?= $e($x['payment_id']) ?>) arrived on <?= $e($fd($x['found_at'])) ?> for an order that was already paid.
      Nothing was given for it, so it is refunded in full — any time.
      <form method="post" style="display:inline-block;margin-left:6px" onsubmit="return confirm('Refund <?= $money($x['amount']) ?> to the customer now?')">
        <input type="hidden" name="do" value="company_refund_extra"><input type="hidden" name="key" value="<?= $sk ?>"><input type="hidden" name="extra_id" value="<?= (int) $x['id'] ?>">
        <button class="btn xs" style="background:#b91c1c">Refund <?= $money($x['amount']) ?></button></form>
    </div>
  <?php endforeach; ?>

  <?php if (!$pay['orders']): ?>
    <div class="sc-note">No payments yet.</div>
  <?php else: ?>
  <div style="overflow-x:auto"><table class="sc" style="min-width:640px;margin-top:6px">
    <tr><th>Paid</th><th>What</th><th style="text-align:right">Amount</th><th>Invoice</th><th>State</th><th></th></tr>
    <?php foreach ($pay['orders'] as $o): $st = $stLabel[$o['status']] ?? [$o['status'], '#374151', '#f3f4f6'];
          $inWindow = (int) $o['age'] <= $days; $oid = $e($o['order_id']); ?>
    <tr>
      <td><?= $e($fd($o['paid_at'])) ?><div class="sc-meta"><?= (int) $o['age'] === 0 ? 'today' : (int) $o['age'] . ' day' . ((int) $o['age'] === 1 ? '' : 's') . ' ago' ?></div></td>
      <td style="max-width:300px"><?= $e($o['label']) ?><div class="sc-key"><?= $e($o['payment_id']) ?></div></td>
      <td style="text-align:right"><?= $money($o['amount']) ?><?php if ((int) $o['refunded_amount'] > 0): ?><div class="sc-meta">refunded <?= $money($o['refunded_amount']) ?></div><?php endif; ?></td>
      <td><?= $o['invoice_no'] !== '' ? $e($o['invoice_no']) : '<span class="sc-meta">receipt</span>' ?></td>
      <td><span class="pill" style="background:<?= $st[2] ?>;color:<?= $st[1] ?>"><?= $e($st[0]) ?></span></td>
      <td style="min-width:120px"><?php if ((int) $o['left'] > 0): ?>
        <details><summary class="btn xs ghost" style="cursor:pointer;list-style:none">Refund…</summary>
          <form method="post" style="margin-top:8px;padding:12px;border:1px solid var(--line,#e5e7eb);border-radius:10px;background:var(--card,#fff);min-width:300px;max-width:520px"
                onsubmit="return confirm('Send this refund through Razorpay now? It cannot be undone.')">
            <input type="hidden" name="do" value="company_refund"><input type="hidden" name="key" value="<?= $sk ?>">
            <input type="hidden" name="order_id" value="<?= $oid ?>"><input type="hidden" name="nonce" value="<?= $e(bin2hex(random_bytes(16))) ?>">
            <div class="sc-note" style="margin:0 0 8px"><?= $inWindow
                ? 'Paid ' . (int) $o['age'] . ' day' . ((int) $o['age'] === 1 ? '' : 's') . ' ago — within the ' . (int) $days . '-day window.'
                : '<b style="color:#b91c1c">Paid ' . (int) $o['age'] . ' days ago — outside the ' . (int) $days . '-day window.</b> Only "our own error" can be refunded now.' ?>
              Left to refund: <b><?= $money($o['left']) ?></b>.</div>
            <div class="ff" style="margin-bottom:8px"><label style="font-size:12px;font-weight:600">Why</label>
              <?php foreach ($reasons as $rk => $r): $allowed = $inWindow || !empty($r['anytime']); ?>
                <label style="display:flex;gap:6px;font-size:13px;<?= $allowed ? '' : 'color:#9ca3af' ?>"><input type="radio" name="reason" value="<?= $e($rk) ?>" <?= $allowed ? '' : 'disabled' ?> required> <?= $e($r['label']) ?><?= !empty($r['anytime']) ? ' <span class="sc-key">(any time)</span>' : '' ?></label>
              <?php endforeach; ?></div>
            <div class="ff" style="margin-bottom:8px"><label style="font-size:12px;font-weight:600">How much</label>
              <label style="display:flex;gap:6px;font-size:13px"><input type="radio" name="scope" value="full" checked onchange="this.form.querySelector('.rf-part').style.display='none'"> The whole payment (<?= $money($o['left']) ?>)<?= !empty($o['later']) && $o['kind'] !== 'ai' ? ' <span class="sc-key">— a later payment changed the plan; refund that first</span>' : '' ?></label>
              <?php if ($o['kind'] !== 'ai'): ?>
              <label style="display:flex;gap:6px;font-size:13px"><input type="radio" name="scope" value="part" onchange="this.form.querySelector('.rf-part').style.display='block'"> Part — take back only what was bought by mistake</label>
              <?php endif; ?></div>
            <?php if ($o['kind'] !== 'ai'): ?>
            <div class="rf-part" style="display:none;margin:0 0 8px 20px">
              <?php if ($o['mods_added']): ?><div style="font-size:12px;font-weight:600">Modules to take back</div>
                <?php foreach ($o['mods_added'] as $mk): ?><label style="display:inline-flex;gap:4px;font-size:13px;margin-right:10px"><input type="checkbox" name="mods_remove[]" value="<?= $e($mk) ?>"> <?= $e($modName($mk)) ?></label><?php endforeach; ?>
              <?php endif; ?>
              <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
                <div class="ff"><label style="font-size:12px">Logins to take back</label><input type="number" name="seats_remove" min="0" value="0" style="width:110px"></div>
                <div class="ff"><label style="font-size:12px">Storage packs to take back</label><input type="number" name="packs_remove" min="0" value="0" style="width:110px"></div>
                <div class="ff"><label style="font-size:12px">Amount to refund (₹)</label><input type="number" name="amount" min="1" max="<?= (int) $o['left'] ?>" placeholder="worked out for you" style="width:150px"></div>
              </div>
              <div class="sc-note" style="margin:4px 0 0">Leave the amount empty and it is worked out from the share of this payment that is taken back.
                For <b>our own error</b> (a wrong price) you may take nothing back and just type the amount overcharged. <?= (int) $pay['users'] ?> people are active now — logins cannot go below that.</div>
            </div>
            <?php endif; ?>
            <div class="ff" style="margin-bottom:8px"><label style="font-size:12px;font-weight:600">Note (kept with the refund and on the credit note)</label>
              <input name="note" maxlength="300" placeholder="e.g. customer meant 5 logins, bought 50"></div>
            <label style="display:flex;gap:6px;font-size:13px;margin-bottom:8px"><input type="checkbox" name="confirm" value="1" required> I understand the money goes back to the customer and what is refunded stops at once.</label>
            <button class="btn" style="background:#b91c1c">Refund through Razorpay</button>
          </form></details>
      <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
  <?php endif; ?>
</div>
