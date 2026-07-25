<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › <?= e($cfg['label']) ?></div>
<div class="master-head">
  <div><h1><?= e($cfg['label']) ?></h1><p class="sub"><?= count($rows) ?> record(s)</p></div>
  <div style="display:flex;gap:6px">
    <?php // Offices are also editable as a nested structure — same table, same
          // rows, arranged as a tree with heads and head-counts. ?>
    <?php if ($key === 'offices'): ?><a class="btn secondary" href="/hierarchy?tab=offices">Edit as a structure</a><?php endif; ?>
    <a class="btn" href="/m/<?= e($key) ?>/new">+ Add</a>
  </div>
</div>
<form method="get" action="/m/<?= e($key) ?>" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search…">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q): ?><a class="btn secondary" href="/m/<?= e($key) ?>">Clear</a><?php endif; ?>
</form>
<table class="grid">
  <tr><?php foreach ($cfg['list'] as $col => $lbl): ?><th><?= e($lbl) ?></th><?php endforeach; ?><th>Actions</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <?php foreach ($cfg['list'] as $col => $lbl): $v = $r[$col] ?? '';
        if (isset($cfg['list_labels'][$col])) $v = $cfg['list_labels'][$col][$v] ?? $v;
        elseif (isset($cfg['ref_cols'][$col])) $v = ref_label($col, $r[$col], $cfg);
        elseif (in_array($col, $cfg['money_cols'] ?? [])) $v = fmoney($r[$col]); ?>
        <td><?= e($v === '' || $v === null ? '—' : $v) ?></td>
      <?php endforeach; ?>
      <td class="row-actions">
        <a class="btn small" href="/m/<?= e($key) ?>/edit?id=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" action="/m/<?= e($key) ?>/delete?id=<?= (int)$r['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this record?')">
          <button class="btn small danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($cfg['list'])+1 ?>">No records yet. <a href="/m/<?= e($key) ?>/new">Add one</a>.</td></tr><?php endif; ?>
</table>
