<?php
  $entLabels = ['call'=>'Call','job'=>'Job','partner'=>'Client / Vendor'];
  foreach (ops_masters() as $mk=>$mc) $entLabels[$mk] = $mc['label'];
  $curLabel = $entLabels[$entity] ?? $entity;
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › Custom fields</div>
<div class="master-head">
  <div><h1>Custom fields — <?= e($curLabel) ?></h1>
    <p class="sub">Add your own fields to this form. They appear automatically. A dropdown field can use any master list — pick a dependent list to get cascading selects.</p></div>
  <form method="get" action="/custom-fields" class="row-actions">
    <label class="muted" style="align-self:center">Form:</label>
    <select class="form-control searchable" name="entity" onchange="this.form.submit()">
      <?php foreach ($entities as $en): ?><option value="<?= e($en) ?>" <?= $entity===$en?'selected':'' ?>><?= e($entLabels[$en] ?? $en) ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<?php $ef = $editField ?? null; $typeLabels = ['text'=>'Text','number'=>'Number','date'=>'Date','select'=>'Dropdown','dependent'=>'Dependent dropdown']; ?>
<table class="grid">
  <tr><th>Order</th><th>Field</th><th>Type</th><th>Master list</th><th>Required</th><th></th></tr>
  <?php foreach ($fields as $f): $lt = $f['lookup_type_id'] ? lk_type_by_id($f['lookup_type_id']) : null; ?>
  <tr<?= ($ef && (int)$ef['id']===(int)$f['id']) ? ' style="background:var(--soft)"' : '' ?>>
    <td class="muted"><?= (int)($f['sort_order'] ?? 99) ?></td>
    <td><strong><?= e($f['label']) ?></strong></td>
    <td><?= e($typeLabels[$f['field_type']] ?? $f['field_type']) ?></td>
    <td><?= $lt ? e($lt['label']) : '—' ?></td>
    <td><?= $f['required'] ? 'Yes' : 'No' ?></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/custom-fields?entity=<?= e($entity) ?>&edit=<?= (int)$f['id'] ?>#cf-form">Edit</a>
      <a class="btn small danger" href="/custom-fields?entity=<?= e($entity) ?>&del=<?= (int)$f['id'] ?>" onclick="return confirm('Remove this field? Any data saved in it stays but stops showing.')">Delete</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$fields): ?><tr><td colspan="6">No custom fields yet.</td></tr><?php endif; ?>
</table>

<h3 class="tab-sub" id="cf-form"><?= $ef ? 'Edit field' : 'Add a field' ?></h3>
<form method="post" action="/custom-fields?entity=<?= e($entity) ?>" class="panel">
  <?php if ($ef): ?><input type="hidden" name="edit_id" value="<?= (int)$ef['id'] ?>"><?php endif; ?>
  <div class="form-grid">
    <div class="ff"><label>Field label *</label><input class="form-control" name="label" required value="<?= e($ef['label'] ?? '') ?>" placeholder="e.g. Product line, Rig number, Certificate type"></div>
    <div class="ff"><label>Field type<?= $ef ? ' <span class="muted">— fixed after creation</span>' : '' ?></label>
      <?php if ($ef): ?>
        <input class="form-control" value="<?= e($typeLabels[$ef['field_type']] ?? $ef['field_type']) ?>" readonly style="background:var(--soft)">
      <?php else: ?>
      <select class="form-control" name="field_type" id="cf_type">
        <option value="text">Text</option><option value="number">Number</option><option value="date">Date</option>
        <option value="select">Dropdown (from a master list)</option>
        <option value="dependent">Dependent dropdown (cascading)</option>
      </select>
      <?php endif; ?></div>
    <div class="ff"><label>Master list (for dropdown types)</label>
      <select class="form-control searchable" name="lookup_type_id"><option value="">—</option>
        <?php foreach ($types as $t): $p = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null; ?>
          <option value="<?= (int)$t['id'] ?>" <?= ($ef && (int)($ef['lookup_type_id'] ?? 0)===(int)$t['id']) ? 'selected' : '' ?>><?= e($t['label']) ?><?= $p ? ' (under ' . e($p['label']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">For a cascading field, pick the deepest list (e.g. Tier) — it will show Product → Wax type → Tier automatically.</small></div>
    <div class="ff"><label>Order <span class="muted">— lower shows first</span></label>
      <input class="form-control" type="number" name="sort_order" value="<?= (int)($ef['sort_order'] ?? 99) ?>"></div>
    <div class="ff ff-check"><input type="checkbox" name="required" <?= ($ef && $ef['required']) ? 'checked' : '' ?>><label>Required</label></div>
  </div>
  <div style="margin-top:14px;">
    <button class="btn" type="submit"><?= $ef ? 'Save changes' : 'Add field' ?></button>
    <?php if ($ef): ?><a class="btn secondary" href="/custom-fields?entity=<?= e($entity) ?>">Cancel</a><?php endif; ?>
  </div>
</form>
