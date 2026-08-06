<?php
$v = fn($k, $d = '') => e($m[$k] ?? $d);
$sel = function ($opts, $cur) { foreach ($opts as $code => $label) {
    echo '<option value="' . e($code) . '"' . ($cur === $code ? ' selected' : '') . '>' . e($label) . '</option>'; } };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/methods">Method library</a> › <?= $m ? e($m['method_code']) : 'Add a method' ?></div>
<div class="master-head"><div><h1><?= $m ? 'Edit ' . e($m['method_code']) : 'Add a method' ?></h1>
  <p class="sub">A new method is saved as a draft; validate it and set it Current when it is in force.</p></div></div>

<form method="post" action="<?= $m ? '/method-edit?id=' . (int)$m['id'] : '/method-new' ?>" enctype="multipart/form-data" class="settings-form">
  <div class="form-grid">
    <div class="ff" style="grid-column:1/-1"><label>Title *</label>
      <input class="form-control" name="title" value="<?= $v('title') ?>" required
             placeholder="e.g. Ultrasonic thickness measurement of pipework"></div>

    <div class="ff"><label>Standard reference</label>
      <input class="form-control" name="standard_ref" value="<?= $v('standard_ref') ?>" placeholder="e.g. ASME B31.3 / ASTM E797"></div>
    <div class="ff"><label>Revision</label>
      <input class="form-control" name="revision" value="<?= $v('revision') ?>" placeholder="e.g. Rev 3 / 2024"></div>

    <div class="ff"><label>Category</label><select class="form-control" name="category">
      <option value="">— select —</option><?php $sel($categories, $m['category'] ?? ''); ?></select></div>
    <div class="ff"><label>Discipline / SBU</label>
      <input class="form-control" name="discipline" value="<?= $v('discipline') ?>"></div>

    <div class="ff"><label>Effective date</label>
      <input class="form-control" type="date" name="effective_date" value="<?= $v('effective_date') ?>"></div>
    <div class="ff"><label>Review due</label>
      <input class="form-control" type="date" name="review_due" value="<?= $v('review_due') ?>"></div>

    <div class="ff"><label>Owner / custodian</label>
      <input class="form-control" name="owner" value="<?= $v('owner') ?>"></div>
    <div class="ff"><label>Method document (optional)</label>
      <input class="form-control" type="file" name="method_file">
      <?php if ($m && $m['file_name']): ?><small class="muted">On file: <a href="/method-file?id=<?= (int)$m['id'] ?>"><?= e($m['file_name']) ?></a> — upload to replace.</small><?php endif; ?></div>

    <div class="ff" style="grid-column:1/-1"><label>Description / scope</label>
      <textarea class="form-control" name="description" rows="3"><?= $v('description') ?></textarea></div>
  </div>

  <?php if (function_exists('render_custom_fields') && !empty($cfields)): ?>
    <h3 style="margin-top:22px">Additional details</h3>
    <?= render_custom_fields('method', $cvals) ?>
  <?php endif; ?>

  <div style="margin-top:16px"><button class="btn" type="submit"><?= $m ? 'Save changes' : 'Add as draft' ?></button>
    <a class="btn secondary" href="<?= $m ? '/method?id=' . (int)$m['id'] : '/methods' ?>">Cancel</a></div>
</form>
