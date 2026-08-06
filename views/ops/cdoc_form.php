<?php
$v = fn($k, $d = '') => e($d[$k] ?? '');
$doc = $d;
$val = fn($k, $dv = '') => e($doc[$k] ?? $dv);
$sel = function ($opts, $cur) { foreach ($opts as $code => $label) {
    echo '<option value="' . e($code) . '"' . ($cur === $code ? ' selected' : '') . '>' . e($label) . '</option>'; } };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/cdocs">Controlled documents</a> › <?= $doc ? e($doc['doc_code']) : 'Add a document' ?></div>
<div class="master-head"><div><h1><?= $doc ? 'Edit ' . e($doc['doc_code']) : 'Add a controlled document' ?></h1>
  <p class="sub">A new document is a draft; record its approval and set it Current when it is in force.</p></div></div>

<form method="post" action="<?= $doc ? '/cdoc-edit?id=' . (int)$doc['id'] : '/cdoc-new' ?>" enctype="multipart/form-data" class="settings-form">
  <div class="form-grid">
    <div class="ff" style="grid-column:1/-1"><label>Title *</label>
      <input class="form-control" name="title" value="<?= $val('title') ?>" required
             placeholder="e.g. Quality Manual / Inspection Reporting Procedure"></div>

    <div class="ff"><label>Type</label><select class="form-control" name="doc_type">
      <option value="">— select —</option><?php $sel($types, $doc['doc_type'] ?? ''); ?></select></div>
    <div class="ff"><label>Revision</label>
      <input class="form-control" name="revision" value="<?= $val('revision') ?>" placeholder="e.g. Rev 2 / Issue 3"></div>

    <div class="ff"><label>Owner / custodian</label>
      <input class="form-control" name="owner" value="<?= $val('owner') ?>"></div>
    <div class="ff"><label>Approved by</label>
      <input class="form-control" name="approved_by" value="<?= $val('approved_by') ?>"></div>

    <div class="ff"><label>Approved on</label>
      <input class="form-control" type="date" name="approved_on" value="<?= $val('approved_on') ?>"></div>
    <div class="ff"><label>Effective date</label>
      <input class="form-control" type="date" name="effective_date" value="<?= $val('effective_date') ?>"></div>

    <div class="ff"><label>Review due</label>
      <input class="form-control" type="date" name="review_due" value="<?= $val('review_due') ?>"></div>
    <div class="ff"><label>Document file (optional)</label>
      <input class="form-control" type="file" name="cdoc_file">
      <?php if ($doc && $doc['file_name']): ?><small class="muted">On file: <a href="/cdoc-file?id=<?= (int)$doc['id'] ?>"><?= e($doc['file_name']) ?></a> — upload to replace.</small><?php endif; ?></div>

    <div class="ff" style="grid-column:1/-1"><label>Distribution (who holds a copy)</label>
      <input class="form-control" name="distribution" value="<?= $val('distribution') ?>" placeholder="e.g. All offices / Quality team / Client on request"></div>
    <div class="ff" style="grid-column:1/-1"><label>Description / purpose</label>
      <textarea class="form-control" name="description" rows="3"><?= $val('description') ?></textarea></div>
  </div>

  <?php if (function_exists('render_custom_fields') && !empty($cfields)): ?>
    <h3 style="margin-top:22px">Additional details</h3>
    <?= render_custom_fields('controlled_doc', $cvals) ?>
  <?php endif; ?>

  <div style="margin-top:16px"><button class="btn" type="submit"><?= $doc ? 'Save changes' : 'Add as draft' ?></button>
    <a class="btn secondary" href="<?= $doc ? '/cdoc?id=' . (int)$doc['id'] : '/cdocs' ?>">Cancel</a></div>
</form>
