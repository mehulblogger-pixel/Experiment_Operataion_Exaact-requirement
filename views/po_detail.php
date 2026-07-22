<?php
$sbuOpts = lk_options_or('sbu', OPS_SBUS);
// Activities available for this PO's SBU(s), flattened for the line-item dropdown.
$poSbus = array_filter(explode(',', $po['sbu'] ?? ''));
$actList = [];
foreach ($poSbus as $code) foreach (($activities[$code] ?? []) as $a) $actList[] = $a;
// Totals for reflecting against the PO / contract value.
$sumBare = 0; $sumTax = 0; $sumTotal = 0;
foreach ($items as $li) { $b = (float)$li['quantity'] * (float)($li['rate'] ?? 0); $t = $b * (float)($li['gst_pct'] ?? 0) / 100; $sumBare += $b; $sumTax += $t; $sumTotal += $b + $t; }
?>
<div class="master-head">
  <div><h1><?= e($po['po_number'] ?: 'Open Order') ?></h1>
    <p class="sub"><?= e($po['pdn'] ?: $po['pn']) ?> · <?= e(PO_TYPES[$po['po_type']] ?? $po['po_type']) ?><?php if ($po['value']!==null): ?> · PO value ₹<?= e($po['value']) ?><?php endif; ?><?php if (!empty($po['sbu'])): ?> · SBU: <?= e(sbu_csv_labels($po['sbu'], $sbuOpts)) ?><?php endif; ?><?php if ($contract): ?> · Contract <?= e($contract['contract_number']) ?><?php endif; ?></p></div>
  <a class="btn secondary" href="/partner?id=<?= (int)$po['partner_id'] ?>&tab=purchase_orders">← Back</a>
</div>
<h2>Line items</h2>
<div style="overflow-x:auto;">
<table class="grid">
  <tr><th>Description</th><th>Manpower</th><th>Site</th><th>Trade</th><th>Sub-category</th><th>Activity</th><th>Type</th><th>Qty</th><th>Cons.</th><th>Bal.</th><th>Rate</th><th>Bare</th><th>GST%</th><th>Tax</th><th>Total</th></tr>
  <?php foreach ($items as $li):
      $bal = (float)$li['quantity'] - (float)$li['consumed'];
      $bare = $li['rate']!==null ? (float)$li['quantity'] * (float)$li['rate'] : 0;
      $tax = $bare * (float)($li['gst_pct'] ?? 0) / 100; $tot = $bare + $tax;
      $sub = !empty($li['subcategory_id']) ? lk_value((int)$li['subcategory_id']) : null;
      $act = !empty($li['activity_id']) ? lk_value((int)$li['activity_id']) : null;
      $trd = !empty($li['trade_id']) ? lk_value((int)$li['trade_id']) : null; ?>
  <tr><td><?= e($li['description']) ?></td>
    <td><?= $li['manpower_count']!==null ? e($li['manpower_count']) : '—' ?></td>
    <td><?= e($li['site_text'] ?: '—') ?></td>
    <td><?= e($trd ? $trd['label'] : '—') ?></td>
    <td><?= e($sub ? $sub['label'] : ($li['subcategory_other'] ?: '—')) ?></td>
    <td><?= e($act ? $act['label'] : '—') ?></td>
    <td><?= e(PO_ITEM_TYPES[$li['item_type']] ?? $li['item_type']) ?></td>
    <td><?= e($li['quantity']) ?></td><td><?= e($li['consumed']) ?></td><td><strong><?= e($bal) ?></strong></td>
    <td><?= $li['rate']!==null?'₹'.e($li['rate']):'—' ?></td>
    <td><?= $li['rate']!==null?'₹'.number_format($bare,0):'—' ?></td>
    <td><?= e($li['gst_pct'] ?? 0) ?></td>
    <td><?= $li['rate']!==null?'₹'.number_format($tax,0):'—' ?></td>
    <td><strong><?= $li['rate']!==null?'₹'.number_format($tot,0):'—' ?></strong></td></tr>
  <?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="15">No line items yet.</td></tr><?php endif; ?>
  <?php if ($items): ?><tr><td colspan="11" style="text-align:right;"><strong>Totals</strong></td><td><strong>₹<?= number_format($sumBare,0) ?></strong></td><td></td><td><strong>₹<?= number_format($sumTax,0) ?></strong></td><td><strong>₹<?= number_format($sumTotal,0) ?></strong></td></tr><?php endif; ?>
</table>
</div>
<?php if ($items && $po['value']!==null): $poVal=(float)$po['value']; $diff=$poVal-$sumTotal; ?>
  <p class="muted">Line items total <strong>₹<?= number_format($sumTotal,0) ?></strong> against PO value <strong>₹<?= number_format($poVal,0) ?></strong> —
    <?= $diff==0 ? 'fully allocated' : ($diff>0 ? '₹'.number_format($diff,0).' un-allocated' : '<span style="color:#c0392b">over by ₹'.number_format(abs($diff),0).'</span>') ?>.
    <?php if ($contract && $contract['value']!==null): ?> Contract value ₹<?= number_format((float)$contract['value'],0) ?>.<?php endif; ?></p>
<?php endif; ?>

<h3 class="tab-sub">Add line item</h3>
<form method="post" action="/po?id=<?= (int)$po['id'] ?>" class="inline-add">
  <div class="ff"><label>Description</label><input class="form-control" name="description" required></div>
  <div class="ff"><label>Man-power (nos.)</label><input class="form-control" type="number" name="manpower_count"></div>
  <div class="ff"><label>Deployment site</label><input class="form-control" name="site_text" placeholder="Where deployment is required"></div>
  <div class="ff"><label>Trade</label>
    <select class="form-control" id="po_trade" name="trade_id"><option value="">—</option><?php foreach (($trades ?? []) as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['label']) ?></option><?php endforeach; ?></select></div>
  <div class="ff"><label>Sub-category</label>
    <select class="form-control other-toggle" id="po_subcat" name="subcategory_id" data-other="po_subother_wrap"><option value="">— pick trade first —</option><option value="__other__">Other (type it)…</option></select>
    <div id="po_subother_wrap" style="margin-top:6px;display:none;"><input class="form-control" name="subcategory_other" placeholder="New sub-category — added under the trade"></div></div>
  <div class="ff"><label>Activity code</label>
    <select class="form-control searchable" name="activity_id"><option value="">—</option><?php foreach ($actList as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['label']) ?></option><?php endforeach; ?></select></div>
  <div class="ff"><label>Type</label><select class="form-control" name="item_type"><?php foreach (PO_ITEM_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="ff"><label>Quantity</label><input class="form-control" type="number" step="0.01" name="quantity"></div>
  <div class="ff"><label>Rate (bare)</label><input class="form-control" type="number" step="0.01" name="rate"></div>
  <div class="ff"><label>GST %</label><input class="form-control" type="number" step="0.01" name="gst_pct" value="18"></div>
  <div class="ff"><label>Consumed</label><input class="form-control" type="number" step="0.01" name="consumed"></div>
  <button class="btn small" type="submit">Add line item</button>
</form>
<script>window.WORKSUB = <?= json_encode($worksub ?? []) ?>;</script>
