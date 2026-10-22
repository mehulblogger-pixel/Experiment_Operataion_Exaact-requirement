<?php
// A GST tax invoice (when the payment carried GST) or a payment receipt, for
// one of the company's own plan payments. Standalone and printable. The
// figures are the ones recorded on the order when it was paid — never
// recomputed from today's prices. Data: $o (saas_orders row), $t (its tax_json).
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$t = is_array($t ?? null) ? $t : [];
$money = fn($n) => '₹' . number_format((int) $n);
$isTax = !empty($t['on']) && (string) ($o['invoice_no'] ?? '') !== '';
$sup = (array) ($t['supplier'] ?? []); $buy = (array) ($t['buyer'] ?? []);
$state = fn($c) => (defined('GST_STATES') && isset(GST_STATES[$c])) ? GST_STATES[$c] . ' (' . $c . ')' : (string) $c;
$pct = fn($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.') . '%';
$lines = (array) ($t['lines'] ?? []); $credit = (int) ($t['credit'] ?? 0);
$date = (string) ($o['invoiced_at'] ?: substr((string) $o['paid_at'], 0, 10));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $isTax ? 'Tax invoice ' . $e($o['invoice_no']) : 'Payment receipt' ?></title>
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
  .bar button{font:inherit;padding:8px 14px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer}
  @media (max-width:600px){.two{grid-template-columns:1fr}.doc{padding:18px}}
  @media print{body{background:#fff;padding:0}.doc{border:0}.bar{display:none}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Print / save as PDF</button></div>
<div class="doc">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div><h1><?= $isTax ? 'Tax invoice' : 'Payment receipt' ?></h1>
      <div class="muted"><?= $isTax ? 'Invoice no. <b>' . $e($o['invoice_no']) . '</b> · ' : '' ?>Date <?= $e(function_exists('fdate') ? fdate($date) : $date) ?></div></div>
    <div class="muted" style="text-align:right">Payment reference<br><b><?= $e($o['payment_id']) ?></b></div>
  </div>

  <div class="two">
    <div class="box"><h3>From</h3>
      <b><?= $e(($sup['name'] ?? '') !== '' ? $sup['name'] : (function_exists('app_name') ? app_name() : '')) ?></b><br>
      <?= nl2br($e($sup['address'] ?? '')) ?>
      <?php if (!empty($sup['gstin'])): ?><br>GSTIN <b><?= $e($sup['gstin']) ?></b><?php endif; ?></div>
    <div class="box"><h3>To</h3>
      <b><?= $e($buy['name'] ?? '') ?></b><br><?= nl2br($e($buy['address'] ?? '')) ?>
      <br><?= !empty($buy['gstin']) ? 'GSTIN <b>' . $e($buy['gstin']) . '</b>' : 'Not GST-registered' ?>
      <?php if ($isTax): ?><br>Place of supply: <b><?= $e($state((string) ($t['pos'] ?? ''))) ?></b><?php endif; ?></div>
  </div>

  <table>
    <tr><th>Description</th><?php if ($isTax): ?><th>SAC</th><?php endif; ?><th class="r">Amount</th></tr>
    <?php foreach ($lines as $l): if ((int) ($l['amount'] ?? 0) <= 0) continue; ?>
      <tr><td><?= $e($l['label']) ?> — <?= ($o['period'] ?? '') === 'year' ? 'one year' : 'one month' ?></td>
        <?php if ($isTax): ?><td><?= $e($sup['sac'] ?? '') ?></td><?php endif; ?><td class="r"><?= $money($l['amount']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($credit > 0): ?><tr><td>Less: unused value of the previous plan</td><?php if ($isTax): ?><td></td><?php endif; ?><td class="r">− <?= $money($credit) ?></td></tr><?php endif; ?>
    <?php if ($isTax): $span = 2; ?>
      <tr><td colspan="<?= $span ?>">Taxable value</td><td class="r"><?= $money($t['net']) ?></td></tr>
      <?php if ((int) $t['igst'] > 0): ?>
        <tr><td colspan="<?= $span ?>">IGST @ <?= $pct($t['rate']) ?></td><td class="r"><?= $money($t['igst']) ?></td></tr>
      <?php else: ?>
        <tr><td colspan="<?= $span ?>">CGST @ <?= $pct($t['rate'] / 2) ?></td><td class="r"><?= $money($t['cgst']) ?></td></tr>
        <tr><td colspan="<?= $span ?>">SGST @ <?= $pct($t['rate'] / 2) ?></td><td class="r"><?= $money($t['sgst']) ?></td></tr>
      <?php endif; ?>
    <?php endif; ?>
    <tr class="tot"><td colspan="<?= $isTax ? 2 : 1 ?>">Total paid</td><td class="r"><?= $money($o['amount']) ?></td></tr>
  </table>

  <p class="muted" style="margin-top:16px"><?= $isTax ? 'Tax payable on reverse charge: No. ' : '' ?>This is a computer-generated <?= $isTax ? 'invoice' : 'receipt' ?> and needs no signature.</p>
</div>
</body></html>
