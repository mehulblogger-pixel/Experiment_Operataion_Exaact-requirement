<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › <a href="/lookups">Masters</a> › <?= e($t['label']) ?></div>
<div class="master-head">
  <div><h1><?= e($t['label']) ?></h1>
    <p class="sub">Values in this master list<?= $parentType ? ' · each belongs under a <strong>' . e($parentType['label']) . '</strong>' : '' ?></p></div>
  <a class="btn secondary" href="/lookups">← All master lists</a>
</div>

<?php // Where this list appears. Tick a form to add it as a dropdown there; untick
      // to take it off (values people already chose stay saved, just stop showing). ?>
<details class="panel" <?= empty($shownOn) ? '' : 'open' ?> style="margin-bottom:14px">
  <summary style="cursor:pointer;font-weight:600">🖥️ Appears on these forms<?= $shownOn ? ' — ' . e(implode(', ', $shownOn)) : ' — not on any form yet' ?></summary>
  <form method="post" action="/lookup?key=<?= e($t['type_key']) ?>" style="margin-top:12px">
    <input type="hidden" name="set_forms" value="1">
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <?php foreach (lk_form_targets() as $en => $lbl): ?>
        <label class="chk" style="font-weight:400"><input type="checkbox" name="forms[]" value="<?= e($en) ?>" <?= isset($shownOn[$en]) ? 'checked' : '' ?>> <?= e($lbl) ?> form</label>
      <?php endforeach; ?>
    </div>
    <small class="muted">The list shows as a dropdown on each ticked form. For other forms, or to make a field required, use <a href="/custom-fields">Custom fields</a>.</small>
    <div style="margin-top:12px"><button class="btn small" type="submit">Save where it appears</button></div>
  </form>
</details>

<table class="grid">
  <tr><th>Value</th><?php if ($parentType): ?><th>Under (<?= e($parentType['label']) ?>)</th><?php endif; ?><th>Code</th><th>Active</th><th>Actions</th></tr>
  <?php foreach ($values as $v): ?>
  <tr>
    <td><strong><?= e($v['label']) ?></strong></td>
    <?php if ($parentType): ?><td><?= e($v['parent_value_id'] ? lk_value($v['parent_value_id'])['label'] ?? '—' : '—') ?></td><?php endif; ?>
    <td><?= $v['code'] !== '' ? '<code>' . e($v['code']) . '</code>' : '—' ?></td>
    <td><?= $v['active'] ? 'Yes' : 'No' ?></td>
    <td class="row-actions">
      <a class="btn small" href="/lookup?key=<?= e($t['type_key']) ?>&edit=<?= (int)$v['id'] ?>">Edit</a>
      <a class="btn small danger" href="/lookup?key=<?= e($t['type_key']) ?>&del=<?= (int)$v['id'] ?>" onclick="return confirm('Remove this value?')">Delete</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$values): ?><tr><td colspan="<?= $parentType ? 5 : 4 ?>">No values yet — add one below.</td></tr><?php endif; ?>
</table>

<h3 class="tab-sub"><?= $editRow ? 'Edit value' : 'Add a value' ?></h3>
<form method="post" action="/lookup?key=<?= e($t['type_key']) ?>" class="panel">
  <?php if ($editRow): ?><input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
  <div class="form-grid">
    <div class="ff"><label>Value *</label><input class="form-control" name="label" required value="<?= e($editRow['label'] ?? '') ?>" placeholder="e.g. Premium"></div>
    <?php if ($parentType): ?>
    <div class="ff"><label>Under which <?= e($parentType['label']) ?>? *</label>
      <select class="form-control searchable" name="parent_value_id" required><option value="">—</option>
        <?php foreach ($parentValues as $pv): ?><option value="<?= (int)$pv['id'] ?>" <?= ($editRow && (int)$editRow['parent_value_id']===(int)$pv['id'])?'selected':'' ?>><?= e($pv['label']) ?><?= $pv['parent_value_id'] ? ' (' . e(lk_value($pv['parent_value_id'])['label'] ?? '') . ')' : '' ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="ff"><label>Code (optional)</label><input class="form-control" name="code" value="<?= e($editRow['code'] ?? '') ?>" placeholder="short code, if you use one"></div>
  </div>
  <div style="margin-top:14px;">
    <button class="btn" type="submit"><?= $editRow ? 'Save changes' : 'Add value' ?></button>
    <?php if ($editRow): ?><a class="btn secondary" href="/lookup?key=<?= e($t['type_key']) ?>">Cancel</a><?php endif; ?>
  </div>
</form>
