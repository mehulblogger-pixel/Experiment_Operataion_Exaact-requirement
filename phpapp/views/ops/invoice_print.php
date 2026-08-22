<?php
  $m  = fn($n) => cur_sym() . number_format((float)$n, 2);
  $qn = fn($n) => rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
  $company = function_exists('app_name') ? app_name() : 'Company';
  $coGstin = function_exists('setting_get') ? (string)setting_get('company_gstin', '') : '';
  $coAddr  = function_exists('setting_get') ? (string)setting_get('company_address', '') : '';
  $office  = $office ?? null;
  $isDraft = ($inv['status'] ?? '') === 'DRAFT';
  $intra   = (float)($inv['cgst'] ?? 0) + (float)($inv['sgst'] ?? 0) > 0;
  // Group the lines by contract; a sub-header shows only when the bill spans more
  // than one contract (a combined monthly invoice). Single-project bills show none.
  $byContract = [];
  foreach ($lines as $ln) { $k = trim((string)($ln['contract_number'] ?? '')); $byContract[$k][] = $ln; }
  $multi = count($byContract) > 1;
  $cols  = 8; // #, description, SAC, qty, rate, amount, gst, line total
?><!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tax Invoice <?= e($inv['invoice_no'] ?: ('draft #' . (int)$inv['id'])) ?><?= $inv['client_name'] ? ' — ' . e($inv['client_name']) : '' ?></title>
<style>
  *{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px;font-size:12px}
  .noprint{margin:0 0 12px} @media print{.noprint{display:none} body{margin:0}}
  .draft{color:#b45309;font-weight:bold;border:1px dashed #b45309;padding:2px 8px;border-radius:4px;font-size:11px}
  h1{font-size:18px;text-align:center;margin:0;letter-spacing:1px}
  .sub{text-align:center;color:#555;font-size:11px;margin:2px 0 10px}
  .parties{width:100%;border-collapse:collapse;margin:10px 0;table-layout:fixed}
  .parties td{border:1px solid #999;padding:8px 10px;vertical-align:top;width:50%;font-size:11.5px}
  .parties .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#666;margin-bottom:3px}
  .parties b{font-size:13px}
  .meta{width:100%;border-collapse:collapse;margin-bottom:2px}
  .meta td{border:1px solid #999;padding:5px 8px;font-size:11.5px}
  .meta td.k{background:#f4f4f4;font-weight:bold;width:16%}
  table.grid{width:100%;border-collapse:collapse;margin-top:8px}
  table.grid th,table.grid td{border:1px solid #999;padding:4px 6px;font-size:11px;vertical-align:top}
  table.grid th{background:#f0f0f0;text-align:center}
  table.grid td.l{text-align:left} table.grid td.r{text-align:right;font-variant-numeric:tabular-nums}
  .ctr{background:#eef2f7;font-weight:bold;font-size:11px}
  .subtot td{background:#fafafa;font-weight:bold}
  .tot td{font-weight:bold;background:#f0f0f0}
  .words{margin-top:8px;font-size:11.5px}
  .words b{text-transform:capitalize}
  .foot{display:flex;justify-content:space-between;margin-top:28px;gap:20px}
  .foot div{width:40%;border-top:1px solid #333;padding-top:4px;text-align:center;font-size:11px}
  .muted{color:#666}
</style></head><body>
<div class="noprint">
  <button onclick="window.print()">🖨 Print / Save as PDF</button>
  <a href="/invoice?id=<?= (int)$inv['id'] ?>">← back to the invoice</a>
  <?php if ($isDraft): ?><span class="draft">DRAFT — not yet issued</span><?php endif; ?>
</div>

<h1>TAX INVOICE</h1>
<div class="sub"><?= e($company) ?><?= $office && $office['name'] ? ' · ' . e($office['name']) . ' branch' : '' ?></div>

<table class="parties">
  <tr>
    <td>
      <div class="lbl">Seller</div>
      <b><?= e($company) ?></b>
      <?php if ($office && trim((string)($office['name'] ?? '')) !== ''): ?><div><?= e($office['name']) ?><?= !empty($office['city']) ? ', ' . e($office['city']) : '' ?></div><?php endif; ?>
      <?php if ($office && trim((string)($office['address'] ?? '')) !== ''): ?><div class="muted"><?= nl2br(e($office['address'])) ?></div><?php elseif ($coAddr !== ''): ?><div class="muted"><?= nl2br(e($coAddr)) ?></div><?php endif; ?>
      <?php if ($coGstin !== ''): ?><div>GSTIN: <b><?= e($coGstin) ?></b></div><?php endif; ?>
    </td>
    <td>
      <div class="lbl">Bill to</div>
      <b><?= e($inv['client_name'] ?: 'Customer') ?></b>
      <?php if (!empty($buyerAddr)): ?>
        <div class="muted"><?= e(trim(($buyerAddr['line1'] ?? '') . ' ' . ($buyerAddr['city'] ?? '') . ' ' . ($buyerAddr['state'] ?? '') . ' ' . ($buyerAddr['pincode'] ?? ''))) ?></div>
      <?php endif; ?>
      <?php if (trim((string)($inv['client_gstin'] ?? '')) !== ''): ?><div>GSTIN: <b><?= e($inv['client_gstin']) ?></b></div><?php endif; ?>
    </td>
  </tr>
</table>

<table class="meta">
  <tr>
    <td class="k">Invoice no</td><td><b><?= e($inv['invoice_no'] ?: ('draft #' . (int)$inv['id'])) ?></b></td>
    <td class="k">Date</td><td><?= e(fdate($inv['invoice_date'])) ?></td>
    <td class="k">Due</td><td><?= $inv['due_date'] ? e(fdate($inv['due_date'])) : '—' ?></td>
  </tr>
  <tr>
    <td class="k">PO</td><td><?= e($inv['po_number'] ?: '—') ?></td>
    <td class="k">Contract</td><td><?= $multi ? '<span class="muted">several — see below</span>' : e($inv['contract_number'] ?: '—') ?></td>
    <td class="k">Status</td><td><?= e(INV_STATUS[$inv['status']] ?? $inv['status']) ?></td>
  </tr>
</table>

<table class="grid">
  <thead><tr>
    <th style="width:26px">#</th><th>Description</th><th style="width:64px">SAC</th>
    <th style="width:52px">Qty</th><th style="width:80px">Rate</th><th style="width:90px">Amount</th>
    <th style="width:52px">GST</th><th style="width:96px">Line total</th>
  </tr></thead>
  <tbody>
  <?php if (!$lines): ?>
    <tr><td colspan="<?= $cols ?>" class="muted" style="text-align:center;padding:14px">No lines.</td></tr>
  <?php else:
    $n = 0;
    foreach ($byContract as $cn => $group):
      if ($multi): ?>
        <tr class="ctr"><td colspan="<?= $cols ?>"><?= $cn !== '' ? '▤ Contract ' . e($cn) : 'Other work (no contract)' ?></td></tr>
      <?php endif;
      $grpTot = 0;
      foreach ($group as $ln): $n++; $grpTot += (float)$ln['line_total']; ?>
        <tr>
          <td class="r"><?= $n ?></td>
          <td class="l"><?= e($ln['description']) ?><?= $ln['job_code'] ? ' <span class="muted">(' . e($ln['job_code']) . ')</span>' : '' ?></td>
          <td><?= e($ln['hsn_sac'] ?: '—') ?></td>
          <td class="r"><?= $qn($ln['qty']) ?></td>
          <td class="r"><?= $m($ln['rate']) ?></td>
          <td class="r"><?= $m($ln['amount']) ?></td>
          <td class="r"><?= $qn($ln['gst_pct']) ?>%</td>
          <td class="r"><?= $m($ln['line_total']) ?></td>
        </tr>
      <?php endforeach;
      if ($multi): ?>
        <tr class="subtot"><td colspan="7" class="r">Subtotal — <?= $cn !== '' ? e($cn) : 'no contract' ?></td><td class="r"><?= $m($grpTot) ?></td></tr>
      <?php endif;
    endforeach; ?>
  <?php endif; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="7" class="r">Taxable value</td><td class="r"><?= $m($inv['subtotal']) ?></td></tr>
    <?php if ($intra): ?>
      <tr><td colspan="7" class="r">CGST</td><td class="r"><?= $m($inv['cgst']) ?></td></tr>
      <tr><td colspan="7" class="r">SGST</td><td class="r"><?= $m($inv['sgst']) ?></td></tr>
    <?php else: ?>
      <tr><td colspan="7" class="r">IGST</td><td class="r"><?= $m($inv['igst']) ?></td></tr>
    <?php endif; ?>
    <?php if ((float)($inv['round_off'] ?? 0) != 0): ?>
      <tr><td colspan="7" class="r">Round off</td><td class="r"><?= $m($inv['round_off']) ?></td></tr>
    <?php endif; ?>
    <tr class="tot"><td colspan="7" class="r">Total</td><td class="r"><?= $m($inv['total']) ?></td></tr>
  </tfoot>
</table>

<?php if (function_exists('amount_in_words_inr')): ?>
  <p class="words">Amount in words: <b><?= e(amount_in_words_inr((float)$inv['total'])) ?></b></p>
<?php endif; ?>

<?php $out = round((float)$inv['total'] - (float)($settled ?? 0), 2); if (!$isDraft && $out > 0): ?>
  <p class="words muted">Settled so far: <?= $m($settled ?? 0) ?> · Outstanding: <b><?= $m($out) ?></b></p>
<?php endif; ?>

<div class="foot">
  <div>Customer's acknowledgement</div>
  <div>For <?= e($company) ?></div>
</div>
</body></html>
