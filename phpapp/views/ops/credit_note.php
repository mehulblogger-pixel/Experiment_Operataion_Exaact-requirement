<?php
// A GST credit note — a refund against a tax invoice (lib/commercial.php,
// commercial_credit_note_issue_row). Standalone and printable, shown to the
// platform owner (GST invoices) and to the company (Payment history), from the
// register line. Data: $inv (the credit note).
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$inv = is_array($inv ?? null) ? $inv : [];
$money = fn($n) => '₹' . number_format((int) $n);
$sup = (array) ($inv['supplier'] ?? []); $buy = (array) ($inv['buyer'] ?? []);
$state = fn($c) => (defined('GST_STATES') && isset(GST_STATES[$c])) ? GST_STATES[$c] . ' (' . $c . ')' : (string) $c;
$pct = fn($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.') . '%';
$fd = fn($d) => function_exists('fdate') ? fdate($d) : $d;
$logo = function_exists('commercial_logo_data_uri') ? commercial_logo_data_uri() : '';
$words = function_exists('amount_in_words_inr') ? amount_in_words_inr((int) ($inv['total'] ?? 0)) : '';
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Credit note <?= $e($inv['invoice_no'] ?? '') ?></title>
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
  .logo{max-height:56px;max-width:220px;display:block;margin-bottom:14px}
  .tag{display:inline-block;border:2px solid #b45309;color:#b45309;border-radius:8px;padding:3px 12px;font-weight:800;letter-spacing:.08em;font-size:13px}
  .ref{margin-top:14px;padding:10px 12px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;font-size:13px}
  @media (max-width:600px){.two{grid-template-columns:1fr}.doc{padding:18px}}
  @media print{body{background:#fff;padding:0}.doc{border:0}.bar{display:none}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Print / save as PDF</button></div>
<div class="doc">
  <?php if ($logo !== ''): ?><img class="logo" src="<?= $e($logo) ?>" alt="<?= $e($sup['name'] ?? 'Logo') ?>"><?php endif; ?>
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div><h1>Credit note</h1>
      <div class="muted">Credit note no. <b><?= $e($inv['invoice_no'] ?? '') ?></b> · Date <?= $e($fd((string) ($inv['date'] ?? ''))) ?></div>
      <div class="muted">Against tax invoice <b><?= $e($inv['against'] ?? '') ?></b> dated <?= $e($fd((string) ($inv['against_date'] ?? ''))) ?></div></div>
    <div style="text-align:right"><span class="tag">REFUND</span><div class="muted">via Razorpay</div></div>
  </div>
  <div class="two">
    <div class="box"><h3>From</h3>
      <b><?= $e(($sup['name'] ?? '') !== '' ? $sup['name'] : (function_exists('app_name') ? app_name() : '')) ?></b><br>
      <?= nl2br($e($sup['address'] ?? '')) ?><?php if (!empty($sup['gstin'])): ?><br>GSTIN <b><?= $e($sup['gstin']) ?></b><?php endif; ?></div>
    <div class="box"><h3>To</h3>
      <b><?= $e($buy['name'] ?? '') ?></b><br><?= nl2br($e($buy['address'] ?? '')) ?>
      <br><?= !empty($buy['gstin']) ? 'GSTIN <b>' . $e($buy['gstin']) . '</b>' : 'Not GST-registered' ?>
      <br>Place of supply: <b><?= $e($state((string) ($inv['pos'] ?? ''))) ?></b></div>
  </div>
  <table>
    <tr><th>Description</th><th>SAC</th><th class="r">Amount</th></tr>
    <tr><td>Refund against tax invoice <?= $e($inv['against'] ?? '') ?><?= ($inv['reason'] ?? '') !== '' ? ' — ' . $e($inv['reason']) : '' ?></td><td><?= $e($inv['sac'] ?? '') ?></td><td class="r"><?= $money($inv['net'] ?? 0) ?></td></tr>
    <tr><td colspan="2">Taxable value reduced by</td><td class="r"><?= $money($inv['net'] ?? 0) ?></td></tr>
    <?php if ((int) ($inv['igst'] ?? 0) > 0): ?>
      <tr><td colspan="2">IGST @ <?= $pct($inv['rate'] ?? 0) ?></td><td class="r"><?= $money($inv['igst']) ?></td></tr>
    <?php else: ?>
      <tr><td colspan="2">CGST @ <?= $pct(($inv['rate'] ?? 0) / 2) ?></td><td class="r"><?= $money($inv['cgst'] ?? 0) ?></td></tr>
      <tr><td colspan="2">SGST @ <?= $pct(($inv['rate'] ?? 0) / 2) ?></td><td class="r"><?= $money($inv['sgst'] ?? 0) ?></td></tr>
    <?php endif; ?>
    <tr class="tot"><td colspan="2">Total refunded</td><td class="r"><?= $money($inv['total'] ?? 0) ?></td></tr>
    <?php if ($words !== ''): ?><tr><td colspan="3" class="muted">Amount in words: <b><?= $e($words) ?></b></td></tr><?php endif; ?>
  </table>
  <div class="ref">Refunded via <b>Razorpay</b> to the card / UPI the payment came from · Refund reference <b><?= $e($inv['refund_id'] ?? '') ?></b> · Original payment <b><?= $e($inv['payment_id'] ?? '') ?></b></div>
  <p class="muted" style="margin-top:16px">Issued under section 34 of the CGST Act, 2017. This is a computer-generated credit note and needs no signature.</p>
</div>
</body></html>
