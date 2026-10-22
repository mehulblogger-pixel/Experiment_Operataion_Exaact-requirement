<?php
// A GST tax invoice (when the payment carried GST) or a payment receipt, for a
// plan payment. Standalone and printable. Shown to the company (Subscription →
// Payment history) and to the platform owner (GST invoices) from the SAME
// record — commercial_invoice_row(), or the register's latest line for the
// number, which carries any correction. Never recomputed from today's prices.
// The platform's logo is printed on top, the total in words, and how it was
// paid (Razorpay, with the payment reference). Data: $inv.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$inv = is_array($inv ?? null) ? $inv : [];
$money = fn($n) => '₹' . number_format((int) $n);
$isTax = (string) ($inv['invoice_no'] ?? '') !== '';
$sup = (array) ($inv['supplier'] ?? []); $buy = (array) ($inv['buyer'] ?? []);
$state = fn($c) => (defined('GST_STATES') && isset(GST_STATES[$c])) ? GST_STATES[$c] . ' (' . $c . ')' : (string) $c;
$pct = fn($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.') . '%';
$lines = (array) ($inv['lines'] ?? []); $credit = (int) ($inv['credit'] ?? 0);
$date = (string) ($inv['date'] ?? '');
$amend = (array) ($inv['amendments'] ?? []);
$logo = function_exists('commercial_logo_data_uri') ? commercial_logo_data_uri() : '';
$words = function_exists('amount_in_words_inr') ? amount_in_words_inr((int) ($inv['total'] ?? 0)) : '';
$fd = fn($d) => function_exists('fdate') ? fdate($d) : $d;
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $isTax ? 'Tax invoice ' . $e($inv['invoice_no']) : 'Payment receipt' ?></title>
<style>
  body{font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#111827;background:#f3f4f6;margin:0;padding:24px 12px}
  .doc{max-width:780px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:28px}
  h1{font-size:20px;margin:0 0 2px}.muted{color:#6b7280;font-size:12.5px}
  .two{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:18px 0}
  .box h3{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin:0 0 4px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  td,th{padding:8px 6px;border-bottom:1px solid #eef1f5;text-align:left;vertical-align:top}
  th{font-size:11px;text-transform:uppercase;color:#6b7280}
  .r{text-align:right}.tot td{font-weight:700;border-top:2px solid #111827}
  .bar{max-width:780px;margin:0 auto 10px;display:flex;justify-content:flex-end}
  .logo{max-height:56px;max-width:220px;display:block;margin-bottom:14px}
  .paid{display:inline-block;border:2px solid #15803d;color:#15803d;border-radius:8px;padding:3px 12px;font-weight:800;letter-spacing:.08em;font-size:13px;margin-bottom:6px}
  .words td{font-size:13px;color:#374151;border-bottom:0;padding-top:4px}
  .paybox{margin-top:14px;padding:10px 12px;border:1px solid #d1fae5;background:#f0fdf4;border-radius:8px;font-size:13px}
  .bar button{font:inherit;padding:8px 14px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer}
  @media (max-width:600px){.two{grid-template-columns:1fr}.doc{padding:18px}}
  @media print{body{background:#fff;padding:0}.doc{border:0}.bar{display:none}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Print / save as PDF</button></div>
<div class="doc">
  <?php if ($logo !== ''): ?><img class="logo" src="<?= $e($logo) ?>" alt="<?= $e($sup['name'] ?? 'Logo') ?>"><?php endif; ?>
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div><h1><?= $isTax ? 'Tax invoice' : 'Payment receipt' ?></h1>
      <div class="muted"><?= $isTax ? 'Invoice no. <b>' . $e($inv['invoice_no']) . '</b> · ' : '' ?>Date <?= $e(function_exists('fdate') ? fdate($date) : $date) ?></div>
      <?php if ($amend): $last = end($amend); ?><div class="muted">Revised <?= $e(function_exists('fdate') ? fdate(substr((string) $last['at'], 0, 10)) : substr((string) $last['at'], 0, 10)) ?> — billing details corrected</div><?php endif; ?></div>
    <div style="text-align:right"><span class="paid">✓ PAID</span><div class="muted">via Razorpay</div></div>
  </div>

  <div class="two">
    <div class="box"><h3>From</h3>
      <b><?= $e(($sup['name'] ?? '') !== '' ? $sup['name'] : (function_exists('app_name') ? app_name() : '')) ?></b><br>
      <?= nl2br($e($sup['address'] ?? '')) ?>
      <?php if (!empty($sup['gstin'])): ?><br>GSTIN <b><?= $e($sup['gstin']) ?></b><?php endif; ?></div>
    <div class="box"><h3>Bill to</h3>
      <b><?= $e($buy['name'] ?? '') ?></b><br><?= nl2br($e($buy['address'] ?? '')) ?>
      <br><?= !empty($buy['gstin']) ? 'GSTIN <b>' . $e($buy['gstin']) . '</b>' : 'Not GST-registered' ?>
      <?php if ($isTax): ?><br>Place of supply: <b><?= $e($state((string) ($inv['pos'] ?? ''))) ?></b><?php endif; ?></div>
  </div>

  <table>
    <tr><th>Description</th><?php if ($isTax): ?><th>SAC</th><?php endif; ?><th class="r">Amount</th></tr>
    <?php foreach ($lines as $l): if ((int) ($l['amount'] ?? 0) <= 0) continue; ?>
      <tr><td><?= $e($l['label']) ?> — <?= ($inv['period'] ?? '') === 'year' ? 'one year' : 'one month' ?></td>
        <?php if ($isTax): ?><td><?= $e($inv['sac'] ?? '') ?></td><?php endif; ?><td class="r"><?= $money($l['amount']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($credit > 0): ?><tr><td>Less: unused value of the previous plan</td><?php if ($isTax): ?><td></td><?php endif; ?><td class="r">− <?= $money($credit) ?></td></tr><?php endif; ?>
    <?php if ($isTax): ?>
      <tr><td colspan="2">Taxable value</td><td class="r"><?= $money($inv['net']) ?></td></tr>
      <?php if ((int) $inv['igst'] > 0): ?>
        <tr><td colspan="2">IGST @ <?= $pct($inv['rate']) ?></td><td class="r"><?= $money($inv['igst']) ?></td></tr>
      <?php else: ?>
        <tr><td colspan="2">CGST @ <?= $pct($inv['rate'] / 2) ?></td><td class="r"><?= $money($inv['cgst']) ?></td></tr>
        <tr><td colspan="2">SGST @ <?= $pct($inv['rate'] / 2) ?></td><td class="r"><?= $money($inv['sgst']) ?></td></tr>
      <?php endif; ?>
    <?php endif; ?>
    <tr class="tot"><td colspan="<?= $isTax ? 2 : 1 ?>">Total paid</td><td class="r"><?= $money($inv['total'] ?? 0) ?></td></tr>
    <?php if ($words !== ''): ?><tr class="words"><td colspan="<?= $isTax ? 3 : 2 ?>">Amount in words: <b><?= $e($words) ?></b></td></tr><?php endif; ?>
  </table>

  <div class="paybox">Paid in full via <b>Razorpay</b> on <?= $e($fd($date)) ?> · Payment reference <b><?= $e($inv['payment_id'] ?? '') ?></b></div>
  <p class="muted" style="margin-top:16px"><?= $isTax ? 'Tax payable on reverse charge: No. ' : '' ?>This is a computer-generated <?= $isTax ? 'invoice' : 'receipt' ?> and needs no signature.</p>
  <?php if ($isTax && empty($buy['gstin'])): ?><p class="muted">Need your GSTIN on this invoice? Ask your provider — it can be corrected.</p><?php endif; ?>
</div>
</body></html>
