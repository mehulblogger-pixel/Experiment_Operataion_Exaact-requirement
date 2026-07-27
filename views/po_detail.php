<div class="crumbs"><a href="/">Home</a> › <a href="/partner?id=<?= (int)$po['partner_id'] ?>&tab=purchase_orders">Purchase Orders</a> › <?= e($po['po_number'] ?: 'Open Order') ?></div>
<div class="master-head">
  <div><h1><?= e($po['po_number'] ?: 'Open order') ?></h1>
    <p class="sub"><?= e($po['pdn'] ?: $po['pn']) ?> · <?= e(lk_options_or('po_type', PO_TYPES)[$po['po_type']] ?? $po['po_type']) ?><?php if ($po['value']!==null): ?> · <strong><?= e(cur_sym()) ?><?= number_format((float)$po['value'],0) ?></strong> (from line items)<?php endif; ?>
      <?php $psb = array_filter(explode(',', $po['sbu'] ?? '')); if ($psb): ?> · <?= e(T("sbu")) ?>: <?= e(implode(', ', array_map(fn($s)=>lk_options_or('sbu',OPS_SBUS)[$s]??$s, $psb))) ?><?php endif; ?></p></div>
  <a class="btn secondary" href="/partner?id=<?= (int)$po['partner_id'] ?>&tab=purchase_orders">← Back</a>
</div>

<?php // An order raised against a quotation that has since been revised is the
      // quiet way two records drift apart: the customer is working off R02 and
      // the order still holds R01's quantities. Say so, and offer the fix. ?>
<?php if (!empty($quoteState)): $qs = $quoteState; ?>
  <?php if ($qs['stale']): ?>
  <div class="msg-warning">
    <strong>The quotation behind this order has been revised.</strong>
    This order was taken from <?= e($qs['from']['quote_no']) ?><?= (int)$qs['from']['rev'] ? ' R' . (int)$qs['from']['rev'] : '' ?>,
    and the current revision is now <?= e($qs['current']['quote_no']) ?><?= (int)$qs['current']['rev'] ? ' R' . (int)$qs['current']['rev'] : '' ?>.
    <form method="post" action="/po?id=<?= (int)$po['id'] ?>" style="margin-top:8px;display:inline">
      <input type="hidden" name="do" value="pull-quote">
      <input type="hidden" name="quotation_id" value="<?= (int)$qs['current']['id'] ?>">
      <button class="btn small" type="submit"
        onclick="return confirm('Replace the line items on this order with the ones from the current revision?')">Take the lines from the current revision</button>
    </form>
    <a class="btn small ghost" href="/quote?id=<?= (int)$qs['current']['id'] ?>">Open the quotation</a>
    <p class="muted" style="margin:8px 0 0">Refused if any line has already been used — the balances were measured against the old quantities, and moving them silently would make those balances wrong.</p>
  </div>
  <?php else: ?>
  <p class="muted">Taken from <a href="/quote?id=<?= (int)$qs['from']['id'] ?>"><?= e($qs['from']['quote_no']) ?><?= (int)$qs['from']['rev'] ? ' R' . (int)$qs['from']['rev'] : '' ?></a><?= $qs['synced'] !== '' ? ' on ' . e(fdate(substr($qs['synced'], 0, 10))) : '' ?>, which is still the current revision.</p>
  <?php endif; ?>
<?php endif; ?>
<?php // An order with no lines on it is a dead dropdown everywhere downstream —
      // the inspection call cannot pick a line, and nothing tracks how much of
      // the order is left. Nearly always the lines already exist on the
      // quotation and nobody realised they had to be brought across, so the
      // offer to do it sits here rather than being something you must know.
      $liveQuotes = array_values(array_filter($poQuotes ?? [], function ($q) { return (int)$q['line_count'] > 0; }));
?>
<?php if (!$items && $liveQuotes): ?>
<div class="msg-warning">
  <strong>This order has no line items yet.</strong>
  Until it has some, no <?= e(Tl('call')) ?> can be booked against a line of it and nothing tracks how much
  of the order is left. <?= count($liveQuotes) === 1 ? 'This' : 'One' ?> <?= e(Tl('quote')) ?> for this
  <?= e(Tl('client')) ?> already carries the lines — take them across rather than typing them again.
  <form method="post" action="/po?id=<?= (int)$po['id'] ?>" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="do" value="pull-quote">
    <div class="ff" style="margin:0;min-width:280px"><label><?= e(T('quote')) ?> to take the lines from</label>
      <select class="form-control searchable" name="quotation_id">
        <?php foreach ($liveQuotes as $pq): ?>
          <option value="<?= (int)$pq['id'] ?>"<?= (int)$pq['id'] === (int)($po['quotation_id'] ?? 0) ? ' selected' : '' ?>>
            <?= e($pq['quote_no']) ?><?= (int)$pq['rev'] ? ' R' . (int)$pq['rev'] : '' ?>
            · <?= (int)$pq['line_count'] ?> line(s)
            · <?= e(cur_sym()) ?><?= number_format((float)$pq['total_amount'], 0) ?>
            <?= $pq['subject'] ? ' — ' . e(mb_strimwidth($pq['subject'], 0, 40, '…')) : '' ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <button class="btn small" type="submit">Take the lines across</button>
  </form>
</div>
<?php elseif (!$items): ?>
<div class="msg-warning">
  <strong>This order has no line items yet.</strong>
  Until it has some, a <?= e(Tl('call')) ?> raised against this order cannot pick a line, and nothing tracks
  how much of the order is left. Add them below — or record the order against the <?= e(Tl('quote')) ?> it
  answers, and every quoted line is copied across on its own.
  <?php if ((($po['po_type'] ?? '') === 'REGULAR') && (float)($po['value'] ?? 0) > 0): ?>
    <p class="muted" style="margin:8px 0 0">This is a fixed-value order of <?= e(fmoney((float)$po['value'])) ?>.
      If it was never meant to be broken into lines, leave it — the <?= e(Tl('call')) ?> is priced on its own
      rate and quantity, and only the balance tracking is lost.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
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
    <div class="ff"><label>Activity (per PO's <?= e(Tl("sbu")) ?>)</label>
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
