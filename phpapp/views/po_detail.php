<div class="crumbs"><a href="/">Home</a> › <a href="/partner?id=<?= (int)$po['partner_id'] ?>&tab=purchase_orders">Purchase Orders</a> › <?= e($po['po_number'] ?: 'Open Order') ?></div>
<div class="master-head">
  <div><h1><?= e($po['po_number'] ?: 'Open order') ?></h1>
    <p class="sub"><?= e($po['pdn'] ?: $po['pn']) ?> · <?= e(lk_options_or('po_type', PO_TYPES)[$po['po_type']] ?? $po['po_type']) ?><?php if ($po['value']!==null): ?> · <strong><?= e(cur_sym()) ?><?= number_format((float)$po['value'],0) ?></strong> (from line items)<?php endif; ?>
      <?php $psb = array_filter(explode(',', $po['sbu'] ?? '')); if ($psb): ?> · SBU: <?= e(implode(', ', array_map(fn($s)=>lk_options_or('sbu',OPS_SBUS)[$s]??$s, $psb))) ?><?php endif; ?></p></div>
  <a class="btn secondary" href="/partner?id=<?= (int)$po['partner_id'] ?>&tab=purchase_orders">← Back</a>
</div>
<h2>Line items</h2>
<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid">
  <tr><th>Description</th><th>Trade / skill</th><th>Site</th><th>Men</th><th>Qty</th><th>Consumed</th><th>Bal</th><th>Rate</th><th>Base</th><th>GST%</th><th>Tax</th><th>Total</th></tr>
  <?php $gt=0; foreach ($items as $li): $bal = (float)$li['quantity'] - (float)$li['consumed']; $gt += (float)$li['total_amount']; ?>
  <tr>
    <td><?= e($li['description']) ?><br><span class="muted" style="font-size:11px"><?= e(lk_options_or('charge_unit', PO_ITEM_TYPES)[$li['item_type']] ?? $li['item_type']) ?><?= $li['activity_label']?' · '.e($li['activity_label']):'' ?></span></td>
    <td><?= e($li['trade_label'] ?: '—') ?><?= $li['skill_label']?'<br><span class="muted" style="font-size:11px">'.e($li['skill_label']).'</span>':'' ?></td>
    <td><?= e($li['site'] ?: '—') ?></td>
    <td><?= (int)$li['manpower'] ?: '—' ?></td>
    <td><?= e($li['quantity']) ?></td><td><?= e($li['consumed']) ?></td><td><strong class="<?= $bal<=0?'':'' ?>"><?= e($bal) ?></strong></td>
    <td><?= $li['rate']!==null?cur_sym().e($li['rate']):'—' ?></td>
    <td><?= e(cur_sym()) ?><?= number_format((float)$li['base_amount'],0) ?></td>
    <td><?= e($li['gst_pct']) ?>%</td>
    <td><?= e(cur_sym()) ?><?= number_format((float)$li['tax_amount'],0) ?></td>
    <td><strong><?= e(cur_sym()) ?><?= number_format((float)$li['total_amount'],0) ?></strong></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="12">No line items yet.</td></tr>
  <?php else: ?><tr><td colspan="11" style="text-align:right"><strong>PO total</strong></td><td><strong><?= e(cur_sym()) ?><?= number_format($gt,0) ?></strong></td></tr><?php endif; ?>
</table>
</div>

<h3 class="tab-sub">Add line item</h3>
<form method="post" action="/po?id=<?= (int)$po['id'] ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Description</label><input class="form-control" name="description" required></div>
    <div class="ff"><label>Type</label><select class="form-control" name="item_type"><?php foreach (lk_options_or('charge_unit', PO_ITEM_TYPES) as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Trade</label>
      <select class="form-control searchable" id="trade_sel" name="trade_id"><option value="">—</option>
        <?php foreach ($trades as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['label']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Sub-category (skill)</label>
      <select class="form-control" id="skill_sel" name="skill_id"><option value="">— pick trade —</option></select>
      <small class="muted">Not listed? Add it under <a href="/lookup?key=skill">Skill</a>.</small></div>
    <div class="ff"><label>Activity (per PO's SBU)</label>
      <select class="form-control searchable" name="activity_id"><option value="">—</option>
        <?php foreach (($poActivities ?? []) as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['label']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Site of deployment</label><input class="form-control" name="site"></div>
    <div class="ff"><label>Manpower required</label><input class="form-control" type="number" name="manpower" value="0"></div>
    <div class="ff"><label>Quantity</label><input class="form-control" type="number" step="0.01" name="quantity"></div>
    <div class="ff"><label>Rate (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="rate"></div>
    <div class="ff"><label>Consumed</label><input class="form-control" type="number" step="0.01" name="consumed" value="0"></div>
    <div class="ff"><label>GST %</label><input class="form-control" type="number" step="0.01" name="gst_pct" value="18"></div>
  </div>
  <p class="muted" style="margin:4px 2px">Base = Qty × Rate; Tax = Base × GST%; the PO (and its contract) value updates to the sum of line totals.</p>
  <div style="margin-top:8px;"><button class="btn small" type="submit">Add line item</button></div>
</form>
<script>window.SKILLS = <?= json_encode($skillsByTrade) ?>; window.SKILLS_SELECTED = [];</script>
