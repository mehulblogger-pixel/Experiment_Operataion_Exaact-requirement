<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › <a href="/m/<?= e($key) ?>"><?= e($cfg['label']) ?></a> › <?= $row ? 'Edit' : 'Add' ?></div>
<h1><?= $row ? 'Edit' : 'Add' ?> — <?= e($cfg['label']) ?></h1>
<form method="post" action="/m/<?= e($key) ?>/<?= $row ? 'edit?id=' . (int)$row['id'] : 'new' ?>" class="panel">
  <div class="form-grid">
    <?php foreach ($cfg['fields'] as $f): [$name,$label,$type,$meta] = $f;
      if (($meta['salary'] ?? 0) && !can_see_salary()) continue;
      $val = $row[$name] ?? ''; ?>
      <div class="ff <?= $type==='textarea'?'ff-wide':'' ?>">
        <label><?= e($label) ?><?= ($meta['req'] ?? 0) ? ' *' : '' ?></label>
        <?php if ($type === 'select'): ?>
          <select class="form-control searchable" name="<?= e($name) ?>"><option value="">—</option>
            <?php foreach ($meta['opts'] as $k=>$v): ?><option value="<?= e($k) ?>" <?= (string)$val===(string)$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select>
        <?php elseif ($type === 'ref'): ?>
          <select class="form-control searchable" name="<?= e($name) ?>"><option value="">—</option>
            <?php foreach (option_rows($meta) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$val===(string)$o['id']?'selected':'' ?>><?= e($o['text']) ?></option><?php endforeach; ?>
          </select>
        <?php elseif ($type === 'check'): ?>
          <label class="chk"><input type="checkbox" name="<?= e($name) ?>" <?= $val?'checked':(($row?'':'checked')) ?>> Yes</label>
        <?php elseif ($type === 'date'): ?>
          <input class="form-control" type="date" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= ($meta['req'] ?? 0)?'required':'' ?>>
        <?php elseif ($type === 'money' || $type === 'number'): ?>
          <input class="form-control" type="number" step="<?= $type==='money'?'0.01':'0.1' ?>" name="<?= e($name) ?>" value="<?= e($val) ?>">
        <?php elseif ($type === 'textarea'): ?>
          <input class="form-control" name="<?= e($name) ?>" value="<?= e($val) ?>">
        <?php else: ?>
          <input class="form-control" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= ($meta['req'] ?? 0)?'required':'' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save</button>
    <a class="btn secondary" href="/m/<?= e($key) ?>">Cancel</a>
  </div>
</form>
