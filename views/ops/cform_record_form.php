<?php $form = $form ?? []; $rec = $rec ?? null; $hasFields = $hasFields ?? false; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/cform?f=<?= e($form['slug']) ?>"><?= e($form['name']) ?></a> › <?= $rec ? 'Edit' : 'New' ?></div>
<div class="master-head"><div>
  <h1><?= $rec ? 'Edit' : 'New' ?> — <?= e($form['name']) ?></h1>
</div></div>

<?php if (!$hasFields): ?>
  <div class="msg msg-warning" style="margin-top:14px">This form has no fields yet.
    <?php if (cform_can_manage()): ?><a href="/custom-fields?entity=<?= e($form['slug']) ?>">Add its fields</a> first.<?php endif; ?></div>
<?php else: ?>
<div class="panel settings-form" style="max-width:720px;margin-top:14px">
  <form method="post" action="/cform-save?f=<?= e($form['slug']) ?>">
    <?php if ($rec): ?><input type="hidden" name="id" value="<?= (int)$rec['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="ff ff-wide"><label>Title / reference <span class="muted">— what to call this entry</span></label>
        <input class="form-control" name="_title" value="<?= e($rec['title'] ?? '') ?>" placeholder="e.g. a name, number or short description"></div>
    </div>
    <div class="form-grid"><?php render_custom_fields($form['slug'], $cfvals ?? []); ?></div>
    <button class="btn" type="submit" style="margin-top:12px"><?= $rec ? 'Save' : 'Add entry' ?></button>
    <a class="btn secondary" href="/cform?f=<?= e($form['slug']) ?>">Cancel</a>
  </form>
</div>
<?php endif; ?>
