<?php $form = $form ?? []; $rows = $rows ?? []; $q = $q ?? ''; $canEdit = $canEdit ?? false; $fields = $fields ?? [];
  // Show up to two field columns beyond the title, for a useful list.
  $preview = array_slice(array_values(array_filter($fields, fn($f)=>!in_array($f['field_type'],[],true))), 0, 2);
?>
<div class="crumbs"><a href="/">Home</a> › <?= e($form['name']) ?></div>
<div class="master-head"><div>
  <h1><?= e($form['icon']) ?> <?= e($form['name']) ?></h1>
  <?php if ($form['help']): ?><p class="sub" style="margin:2px 0 0"><?= e($form['help']) ?></p><?php endif; ?></div>
  <div style="display:flex;gap:8px">
    <?php if (cform_can_manage()): ?><a class="btn secondary" href="/custom-fields?entity=<?= e($form['slug']) ?>">Fields</a><?php endif; ?>
    <?php if ($canEdit): ?><a class="btn" href="/cform-new?f=<?= e($form['slug']) ?>">+ New</a><?php endif; ?>
  </div>
</div>

<?php if (empty($fields)): ?>
  <div class="msg msg-warning" style="margin-top:14px">This form has no fields yet.
    <?php if (cform_can_manage()): ?><a href="/custom-fields?entity=<?= e($form['slug']) ?>">Add its fields</a> to start using it.<?php else: ?>Ask an administrator to add its fields.<?php endif; ?></div>
<?php endif; ?>

<form method="get" action="/cform" class="panel" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:14px">
  <input type="hidden" name="f" value="<?= e($form['slug']) ?>">
  <div class="ff" style="margin:0;flex:1;min-width:220px"><label>Find</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search the title"></div>
  <button class="btn small secondary" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:12px">
  <div class="dt-scroll"><table class="dt">
    <thead><tr><th>Title</th><?php foreach ($preview as $pf): ?><th><?= e($pf['label']) ?></th><?php endforeach; ?><th>Added</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $vals = custom_values_map($form['slug'], $r['id']); ?>
      <tr>
        <td><a href="/cform-view?id=<?= (int)$r['id'] ?>"><strong><?= e($r['title'] ?: ('#' . $r['id'])) ?></strong></a></td>
        <?php foreach ($preview as $pf): $row = $vals[$pf['field_key']] ?? null;
              $v = !$row ? '' : (in_array($pf['field_type'],['select','dependent'],true) ? lk_value_path($row['value_id']) : (string)$row['value_text']); ?>
          <td class="muted"><?= e($v) ?></td>
        <?php endforeach; ?>
        <td class="muted"><?= e(fdate(substr((string)$r['created_at'],0,10))) ?></td>
        <td class="num"><a class="btn small secondary" href="/cform-view?id=<?= (int)$r['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted" style="padding:16px">No entries yet.<?php if ($canEdit && $fields): ?> Click <strong>+ New</strong> to add the first.<?php endif; ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
