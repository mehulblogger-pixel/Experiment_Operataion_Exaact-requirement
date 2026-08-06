<?php
$v = fn($k, $d = '') => e($r[$k] ?? $d);
$sel = function ($opts, $cur) { foreach ($opts as $code => $label) {
    echo '<option value="' . e($code) . '"' . ((string)$cur === (string)$code ? ' selected' : '') . '>' . e($label) . '</option>'; } };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/drules">Decision rules</a> › <?= $r ? e($r['rule_code']) : 'Add a rule' ?></div>
<div class="master-head"><div><h1><?= $r ? 'Edit ' . e($r['rule_code']) : 'Add a decision rule' ?></h1>
  <p class="sub">State exactly how the accept / reject call is made — the report cites this, so it is not left to opinion.</p></div></div>

<form method="post" action="<?= $r ? '/drule-edit?id=' . (int)$r['id'] : '/drule-new' ?>" class="settings-form">
  <div class="form-grid">
    <div class="ff" style="grid-column:1/-1"><label>Title *</label>
      <input class="form-control" name="title" value="<?= $v('title') ?>" required
             placeholder="e.g. Minimum wall thickness — process pipework"></div>

    <div class="ff"><label>Belongs to method</label><select class="form-control" name="method_id">
      <option value="">— none —</option><?php $sel($methods, $r['method_id'] ?? ''); ?></select></div>
    <div class="ff"><label>Characteristic judged</label>
      <input class="form-control" name="characteristic" value="<?= $v('characteristic') ?>" placeholder="e.g. wall thickness (mm)"></div>

    <div class="ff" style="grid-column:1/-1"><label>Acceptance criteria</label>
      <textarea class="form-control" name="accept_criteria" rows="2" placeholder="e.g. measured value ≥ 6.0 mm"><?= $v('accept_criteria') ?></textarea></div>
    <div class="ff" style="grid-column:1/-1"><label>Rejection criteria</label>
      <textarea class="form-control" name="reject_criteria" rows="2" placeholder="e.g. measured value &lt; 6.0 mm"><?= $v('reject_criteria') ?></textarea></div>

    <div class="ff"><label>Decision basis</label><select class="form-control" name="decision_basis">
      <option value="">— select —</option><?php $sel($bases, $r['decision_basis'] ?? ''); ?></select></div>
    <div class="ff"><label>How uncertainty is applied</label>
      <input class="form-control" name="uncertainty_rule" value="<?= $v('uncertainty_rule') ?>"
             placeholder="e.g. guard band w = U; accept if x − U ≥ limit"></div>

    <div class="ff"><label>Owner / custodian</label>
      <input class="form-control" name="owner" value="<?= $v('owner') ?>"></div>

    <div class="ff" style="grid-column:1/-1"><label>Notes</label>
      <textarea class="form-control" name="notes" rows="2"><?= $v('notes') ?></textarea></div>
  </div>

  <?php if (function_exists('render_custom_fields') && !empty($cfields)): ?>
    <h3 style="margin-top:22px">Additional details</h3>
    <?= render_custom_fields('decision_rule', $cvals) ?>
  <?php endif; ?>

  <div style="margin-top:16px"><button class="btn" type="submit"><?= $r ? 'Save changes' : 'Add as draft' ?></button>
    <a class="btn secondary" href="<?= $r ? '/drule?id=' . (int)$r['id'] : '/drules' ?>">Cancel</a></div>
</form>
