<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › Inspectors</div>
<div class="master-head">
  <div><h1><?= e(T_REG('engineer')) ?></h1><p class="sub"><?= count($rows) ?> inspector(s)</p></div>
  <a class="btn" href="/m/inspectors/new">+ Add inspector</a>
</div>
<form method="get" action="/m/inspectors" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / code / skill…">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q): ?><a class="btn secondary" href="/m/inspectors">Clear</a><?php endif; ?>
</form>
<table class="grid">
  <tr><th>Name</th><th>Emp code</th><th>Trade</th><th>SBUs</th><th>Skills</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="/m/inspectors/edit?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name'] ?: '—') ?></strong></a></td>
    <td><?= e($r['emp_code'] ?: '—') ?></td>
    <td><?= e(trade_label($r['trade_id'] ?? null)) ?></td>
    <td><?= e(sbu_labels($r['sbus'] ?? '')) ?></td>
    <td class="muted" style="font-size:13px;"><?= e(skill_labels($r['skill_ids'] ?? '')) ?></td>
    <td><span class="badge <?= ($r['status']??'')==='ACTIVE'?'GREEN':'AMBER' ?>"><?= e($r['status'] ?: '—') ?></span></td>
    <td class="row-actions">
      <a class="btn small" href="/m/inspectors/edit?id=<?= (int)$r['id'] ?>">Edit</a>
      <form method="post" action="/m/inspectors/delete?id=<?= (int)$r['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this inspector?')">
        <button class="btn small danger" type="submit">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">No inspectors yet. <a href="/m/inspectors/new">Add one</a>.</td></tr><?php endif; ?>
</table>
