<?php
$val = fn($k, $dv = '') => e($r[$k] ?? $dv);
$sel = function ($opts, $cur) { foreach ($opts as $code => $label) {
    echo '<option value="' . e($code) . '"' . ((string)$cur === (string)$code ? ' selected' : '') . '>' . e($label) . '</option>'; } };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/risks">Risks &amp; opportunities</a> › <?= $r ? e($r['risk_code']) : 'Add an entry' ?></div>
<div class="master-head"><div><h1><?= $r ? 'Edit ' . e($r['risk_code']) : 'Add a risk or opportunity' ?></h1>
  <p class="sub">Rate the likelihood and impact, and say how it is being addressed.</p></div></div>

<form method="post" action="<?= $r ? '/risk-edit?id=' . (int)$r['id'] : '/risk-new' ?>" class="settings-form">
  <div class="form-grid">
    <div class="ff"><label>This is a…</label><select class="form-control" name="kind">
      <option value="RISK"<?= ($r['kind'] ?? 'RISK') === 'RISK' ? ' selected' : '' ?>>Risk</option>
      <option value="OPPORTUNITY"<?= ($r['kind'] ?? '') === 'OPPORTUNITY' ? ' selected' : '' ?>>Opportunity</option>
    </select></div>
    <div class="ff"><label>Category</label><select class="form-control" name="category">
      <option value="">— select —</option><?php $sel($cats, $r['category'] ?? ''); ?></select></div>

    <div class="ff" style="grid-column:1/-1"><label>Title *</label>
      <input class="form-control" name="title" value="<?= $val('title') ?>" required
             placeholder="e.g. Key NDT inspector may leave — single point of failure"></div>
    <div class="ff" style="grid-column:1/-1"><label>Area / context</label>
      <input class="form-control" name="context" value="<?= $val('context') ?>" placeholder="Where this applies"></div>
    <div class="ff" style="grid-column:1/-1"><label>Description</label>
      <textarea class="form-control" name="description" rows="2"><?= $val('description') ?></textarea></div>

    <div class="ff"><label>Likelihood</label><select class="form-control" name="likelihood">
      <option value="">—</option><?php $sel($levels, $r['likelihood'] ?? ''); ?></select></div>
    <div class="ff"><label>Impact</label><select class="form-control" name="impact">
      <option value="">—</option><?php $sel($levels, $r['impact'] ?? ''); ?></select></div>

    <div class="ff" style="grid-column:1/-1"><label>Treatment — how it is addressed</label>
      <textarea class="form-control" name="treatment" rows="2" placeholder="Mitigation / action taken, or how the opportunity is pursued"><?= $val('treatment') ?></textarea></div>

    <div class="ff"><label>Owner</label>
      <input class="form-control" name="owner" value="<?= $val('owner') ?>"></div>
    <div class="ff"><label>Review due</label>
      <input class="form-control" type="date" name="review_due" value="<?= $val('review_due') ?>"></div>
  </div>

  <?php if (function_exists('render_custom_fields') && !empty($cfields)): ?>
    <h3 style="margin-top:22px">Additional details</h3>
    <?= render_custom_fields('risk', $cvals) ?>
  <?php endif; ?>

  <div style="margin-top:16px"><button class="btn" type="submit"><?= $r ? 'Save changes' : 'Add to register' ?></button>
    <a class="btn secondary" href="<?= $r ? '/risk?id=' . (int)$r['id'] : '/risks' ?>">Cancel</a></div>
</form>
