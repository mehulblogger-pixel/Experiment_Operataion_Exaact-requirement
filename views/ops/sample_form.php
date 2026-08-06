<?php
$v = fn($k, $d = '') => e($s[$k] ?? $d);
$sel = function ($opts, $cur) { foreach ($opts as $code => $label) {
    echo '<option value="' . e($code) . '"' . ($cur === $code ? ' selected' : '') . '>' . e($label) . '</option>'; } };
// Optional client link — the party the item belongs to. Queried here so the form
// stays self-contained; an empty choice keeps it optional.
$clients = ops_all("SELECT id, COALESCE(display_name, legal_name) nm FROM business_partners WHERE is_client=1 AND status='ACTIVE' ORDER BY nm") ?: [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/samples">Items &amp; samples</a> › <?= $s ? e($s['item_code']) : 'Receive an item' ?></div>
<div class="master-head"><div><h1><?= $s ? 'Edit ' . e($s['item_code']) : 'Receive an item' ?></h1>
  <p class="sub">Log what came in, in what condition, and where it is kept.</p></div></div>

<form method="post" action="<?= $s ? '/sample-edit?id=' . (int)$s['id'] : '/sample-new' ?>" class="settings-form">
  <div class="form-grid">
    <div class="ff" style="grid-column:1/-1"><label>Description of the item *</label>
      <input class="form-control" name="description" value="<?= $v('description') ?>" required
             placeholder="e.g. 6&quot; carbon-steel pipe spool, weld sample WS-14"></div>

    <div class="ff"><label>Type</label><select class="form-control" name="item_type">
      <option value="">— select —</option><?php $sel($types, $s['item_type'] ?? ''); ?></select></div>
    <div class="ff"><label>Maker / heat / batch ref</label>
      <input class="form-control" name="maker_ref" value="<?= $v('maker_ref') ?>"></div>

    <div class="ff"><label>Quantity</label>
      <input class="form-control" name="quantity" value="<?= $v('quantity') ?>" placeholder="e.g. 1"></div>
    <div class="ff"><label>Unit</label>
      <input class="form-control" name="unit" value="<?= $v('unit') ?>" placeholder="e.g. pcs / kg / m"></div>

    <div class="ff"><label>Received on</label>
      <input class="form-control" type="date" name="received_on" value="<?= $v('received_on', date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Received by</label>
      <input class="form-control" name="received_by" value="<?= $v('received_by') ?>"></div>

    <div class="ff"><label>Condition on receipt</label><select class="form-control" name="condition_code">
      <option value="">— select —</option><?php $sel($conditions, $s['condition_code'] ?? ''); ?></select></div>
    <div class="ff"><label>Condition note</label>
      <input class="form-control" name="condition_note" value="<?= $v('condition_note') ?>"
             placeholder="Any damage / marking noted at receipt"></div>

    <div class="ff"><label>Storage location</label><select class="form-control" name="storage_code">
      <option value="">— select —</option><?php $sel($storages, $s['storage_code'] ?? ''); ?></select></div>
    <div class="ff"><label>Client / owner (optional)</label><select class="form-control" name="partner_id">
      <option value="">— none —</option>
      <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)($s['partner_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['nm']) ?></option><?php endforeach; ?>
    </select></div>

    <div class="ff" style="grid-column:1/-1"><label>Notes</label>
      <textarea class="form-control" name="notes" rows="2"><?= $v('notes') ?></textarea></div>
  </div>

  <?php if (function_exists('render_custom_fields') && !empty($cfields)): ?>
    <h3 style="margin-top:22px">Additional details</h3>
    <?= render_custom_fields('sample', $cvals) ?>
  <?php endif; ?>

  <div style="margin-top:16px"><button class="btn" type="submit"><?= $s ? 'Save changes' : 'Receive &amp; log item' ?></button>
    <a class="btn secondary" href="<?= $s ? '/sample?id=' . (int)$s['id'] : '/samples' ?>">Cancel</a></div>
</form>
