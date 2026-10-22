<?php
// GST invoices — the platform owner's register of every tax invoice issued to
// every company (lib/commercial.php). Data: $rows (this month), $month, $months, $sel.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$sum = fn($k) => array_sum(array_map(fn($r) => (int) $r[$k], $rows));
$money = fn($n) => '₹' . number_format((int) $n);
$st = fn($c) => (defined('GST_STATES') && isset(GST_STATES[$c])) ? GST_STATES[$c] : (string) $c;
$b2b = count(array_filter($rows, fn($r) => $r['buyer']['gstin'] !== ''));
$mLabel = fn($m) => date('F Y', strtotime($m . '-01'));
?>
<style>
  .gi-wrap{max-width:1040px}
  .gi-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:14px 0}
  .gi-kpi{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:12px 14px}
  .gi-kpi b{display:block;font-size:20px}.gi-kpi span{font-size:12px;color:var(--muted,#656e7a)}
  table.gi{width:100%;border-collapse:collapse;min-width:820px}
  table.gi th,table.gi td{padding:8px 10px;border-bottom:1px solid var(--line,#eef1f5);font-size:13px;text-align:left;vertical-align:top}
  table.gi th{font-size:11px;text-transform:uppercase;color:var(--muted,#656e7a)}
  .gi-r{text-align:right}.gi-pill{font-size:11px;padding:1px 7px;border-radius:99px;background:#eef2ff;color:#3730a3}
  .gi-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
  .gi-form label{font-size:12px;font-weight:600;display:block;margin-bottom:3px}
  .gi-form input,.gi-form select,.gi-form textarea{width:100%;padding:8px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px;font:inherit;box-sizing:border-box}
</style>
<div class="gi-wrap">
<div class="master-head"><div><h1>GST invoices</h1>
  <p class="sub" style="margin:2px 0 0">Every tax invoice issued to every company, for your GST return. Download a month for your accountant.</p></div></div>

<form method="get" class="ps-row" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px">
  <label for="gi-m" style="font-weight:600;font-size:13px">Month</label>
  <select id="gi-m" name="m" onchange="this.form.submit()" style="padding:8px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px">
    <?php foreach ($months as $m): ?><option value="<?= $e($m) ?>" <?= $m === $month ? 'selected' : '' ?>><?= $e($mLabel($m)) ?></option><?php endforeach; ?>
  </select>
  <a class="btn ghost" href="/gst-invoices?m=<?= $e($month) ?>&amp;csv=1">Download for the accountant (CSV)</a>
</form>

<div class="gi-kpis">
  <div class="gi-kpi"><b><?= count($rows) ?></b><span>invoices · <?= $b2b ?> to GST-registered buyers</span></div>
  <div class="gi-kpi"><b><?= $money($sum('net')) ?></b><span>taxable value</span></div>
  <div class="gi-kpi"><b><?= $money($sum('igst')) ?></b><span>IGST</span></div>
  <div class="gi-kpi"><b><?= $money($sum('cgst') + $sum('sgst')) ?></b><span>CGST + SGST</span></div>
  <div class="gi-kpi"><b><?= $money($sum('total')) ?></b><span>invoiced in total</span></div>
</div>

<div class="panel" style="padding:0;overflow-x:auto">
  <table class="gi">
    <tr><th>Invoice</th><th>Date</th><th>Bill to</th><th>Place of supply</th><th class="gi-r">Taxable</th><th class="gi-r">GST</th><th class="gi-r">Total</th><th></th></tr>
    <?php if (!$rows): ?><tr><td colspan="8">No invoices in <?= $e($mLabel($month)) ?>.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr><td><b><?= $e($r['invoice_no']) ?></b><?= $r['amendments'] ? ' <span class="gi-pill">corrected</span>' : '' ?></td>
        <td><?= $e(function_exists('fdate') ? fdate($r['date']) : $r['date']) ?></td>
        <td><?= $e($r['buyer']['name']) ?><br><span class="sub" style="font-size:12px"><?= $r['buyer']['gstin'] !== '' ? 'GSTIN ' . $e($r['buyer']['gstin']) : 'not registered' ?> · <?= $e($r['company_key']) ?></span></td>
        <td><?= $e($st($r['pos'])) ?></td>
        <td class="gi-r"><?= $money($r['net']) ?></td><td class="gi-r"><?= $money($r['tax']) ?><br><span class="sub" style="font-size:11px"><?= (int) $r['igst'] > 0 ? 'IGST' : 'CGST+SGST' ?></span></td>
        <td class="gi-r"><?= $money($r['total']) ?></td>
        <td style="white-space:nowrap"><a href="/gst-invoices?no=<?= $e(rawurlencode($r['invoice_no'])) ?>&amp;print=1" target="_blank" rel="noopener">Open</a> ·
          <a href="/gst-invoices?m=<?= $e($month) ?>&amp;no=<?= $e(rawurlencode($r['invoice_no'])) ?>#fix">Correct</a></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($sel): $b = $sel['buyer']; ?>
<form method="post" class="panel" id="fix" style="margin-top:16px">
  <input type="hidden" name="do" value="amend"><input type="hidden" name="no" value="<?= $e($sel['invoice_no']) ?>">
  <h2 style="margin:0 0 4px;font-size:15px">Correct the buyer on <?= $e($sel['invoice_no']) ?></h2>
  <p class="sub" style="margin:0 0 12px;font-size:12.5px">For example, a customer who paid without its GSTIN and now needs it on the bill. The amount and the tax stay exactly as charged;
    only the buyer details and, if the state changes, the CGST/SGST or IGST split follow. Show it as an amendment in your next GST return.</p>
  <div class="gi-form">
    <div><label for="gi-n">Registered business name</label><input id="gi-n" name="name" value="<?= $e($b['name']) ?>" required maxlength="200"></div>
    <div><label for="gi-g">GSTIN</label><input id="gi-g" name="gstin" value="<?= $e($b['gstin']) ?>" maxlength="15" style="text-transform:uppercase" placeholder="blank if not registered"></div>
    <div><label for="gi-s">State <span style="font-weight:400">— taken from the GSTIN when given</span></label>
      <select id="gi-s" name="state"><option value="">— choose —</option>
        <?php foreach ((defined('GST_STATES') ? GST_STATES : []) as $c => $nm): ?><option value="<?= $e($c) ?>" <?= (string) $b['state'] === (string) $c ? 'selected' : '' ?>><?= $e($nm) ?></option><?php endforeach; ?>
      </select></div>
    <div style="grid-column:1/-1"><label for="gi-a">Billing address</label><textarea id="gi-a" name="address" rows="2" maxlength="500"><?= $e($b['address']) ?></textarea></div>
    <div style="grid-column:1/-1"><label for="gi-r">Why is it being corrected?</label><input id="gi-r" name="reason" required maxlength="300" placeholder="e.g. customer sent its GSTIN after paying"></div>
  </div>
  <div style="margin-top:12px"><button class="btn">Save the correction</button></div>
  <?php if ($sel['amendments']): ?>
    <details style="margin-top:12px"><summary style="cursor:pointer;font-size:13px;font-weight:600">Earlier corrections (<?= count($sel['amendments']) ?>)</summary>
      <?php foreach ($sel['amendments'] as $a): ?>
        <p class="sub" style="font-size:12.5px;margin:6px 0"><?= $e(substr((string) $a['at'], 0, 16)) ?> by <?= $e($a['by']) ?> — <?= $e($a['reason']) ?>.
          Was: <?= $e($a['was']['name'] ?? '') ?>, <?= ($a['was']['gstin'] ?? '') !== '' ? 'GSTIN ' . $e($a['was']['gstin']) : 'no GSTIN' ?>.</p>
      <?php endforeach; ?></details>
  <?php endif; ?>
</form>
<?php endif; ?>
</div>
