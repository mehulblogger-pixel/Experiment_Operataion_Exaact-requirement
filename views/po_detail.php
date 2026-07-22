<div class="master-head">
  <div><h1><?= e($po['po_number'] ?: 'Open Order') ?></h1>
    <p class="sub"><?= e($po['pdn'] ?: $po['pn']) ?> · <?= e(PO_TYPES[$po['po_type']] ?? $po['po_type']) ?><?php if ($po['value']!==null): ?> · ₹<?= e($po['value']) ?><?php endif; ?></p></div>
  <a class="btn secondary" href="/partner?id=<?= (int)$po['partner_id'] ?>&tab=purchase_orders">← Back</a>
</div>
<h2>Line items</h2>
<table class="grid">
  <tr><th>Description</th><th>Type</th><th>Quantity</th><th>Consumed</th><th>Balance</th><th>Rate</th><th>Amount</th></tr>
  <?php foreach ($items as $li): $bal = (float)$li['quantity'] - (float)$li['consumed']; $amt = $li['rate']!==null ? (float)$li['quantity'] * (float)$li['rate'] : null; ?>
  <tr><td><?= e($li['description']) ?></td><td><?= e(PO_ITEM_TYPES[$li['item_type']] ?? $li['item_type']) ?></td><td><?= e($li['quantity']) ?></td><td><?= e($li['consumed']) ?></td><td><strong><?= e($bal) ?></strong></td><td><?= $li['rate']!==null?'₹'.e($li['rate']):'—' ?></td><td><?= $amt!==null?'₹'.e($amt):'—' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="7">No line items yet.</td></tr><?php endif; ?>
</table>
<h3 class="tab-sub">Add line item</h3>
<form method="post" action="/po?id=<?= (int)$po['id'] ?>" class="inline-add">
  <div class="ff"><label>Description</label><input class="form-control" name="description" required></div>
  <div class="ff"><label>Type</label><select class="form-control" name="item_type"><?php foreach (PO_ITEM_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="ff"><label>Quantity</label><input class="form-control" type="number" step="0.01" name="quantity"></div>
  <div class="ff"><label>Rate</label><input class="form-control" type="number" step="0.01" name="rate"></div>
  <div class="ff"><label>Consumed</label><input class="form-control" type="number" step="0.01" name="consumed"></div>
  <button class="btn small" type="submit">Add line item</button>
</form>
