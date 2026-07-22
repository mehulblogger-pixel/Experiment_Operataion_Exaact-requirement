<div class="master-head">
  <div><h1>Custom fields — <?= e(custom_entity_label($entity)) ?> form</h1>
    <p class="sub">Add your own text boxes or dropdowns to any form — they appear automatically. A dropdown field can use any master list; pick a dependent list to get cascading selects.</p></div>
</div>
<div class="ff" style="max-width:420px;">
  <label>Choose the form to customise</label>
  <select class="form-control searchable" onchange="location.href='/custom-fields?entity='+encodeURIComponent(this.value)">
    <?php foreach (custom_entities() as $ek=>$el): ?><option value="<?= e($ek) ?>" <?= $entity===$ek?'selected':'' ?>><?= e($el) ?></option><?php endforeach; ?>
  </select>
</div>

<table class="grid">
  <tr><th>Field</th><th>Type</th><th>Master list</th><th>Required</th><th></th></tr>
  <?php foreach ($fields as $f): $lt = $f['lookup_type_id'] ? lk_type_by_id($f['lookup_type_id']) : null; ?>
  <tr>
    <td><strong><?= e($f['label']) ?></strong></td>
    <td><?= e(['text'=>'Text','number'=>'Number','date'=>'Date','select'=>'Dropdown','dependent'=>'Dependent dropdown'][$f['field_type']] ?? $f['field_type']) ?></td>
    <td><?= $lt ? e($lt['label']) : '—' ?></td>
    <td><?= $f['required'] ? 'Yes' : 'No' ?></td>
    <td class="row-actions"><a class="btn small danger" href="/custom-fields?entity=<?= e($entity) ?>&del=<?= (int)$f['id'] ?>" onclick="return confirm('Remove this field?')">Delete</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$fields): ?><tr><td colspan="5">No custom fields yet.</td></tr><?php endif; ?>
</table>

<h3 class="tab-sub">Add a field</h3>
<form method="post" action="/custom-fields?entity=<?= e($entity) ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Field label *</label><input class="form-control" name="label" required placeholder="e.g. Product line, Rig number, Certificate type"></div>
    <div class="ff"><label>Field type</label>
      <select class="form-control" name="field_type" id="cf_type">
        <option value="text">Text</option><option value="number">Number</option><option value="date">Date</option>
        <option value="select">Dropdown (from a master list)</option>
        <option value="dependent">Dependent dropdown (cascading)</option>
      </select></div>
    <div class="ff"><label>Master list (for dropdown types)</label>
      <select class="form-control searchable" name="lookup_type_id"><option value="">—</option>
        <?php foreach ($types as $t): $p = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null; ?>
          <option value="<?= (int)$t['id'] ?>"><?= e($t['label']) ?><?= $p ? ' (under ' . e($p['label']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">For a cascading field, pick the deepest list (e.g. Tier) — it will show Product → Wax type → Tier automatically.</small></div>
    <div class="ff ff-check"><input type="checkbox" name="required"><label>Required</label></div>
  </div>
  <div style="margin-top:14px;"><button class="btn" type="submit">Add field</button></div>
</form>
