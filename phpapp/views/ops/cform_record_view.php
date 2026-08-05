<?php $form = $form ?? []; $rec = $rec ?? []; $values = $values ?? []; $canEdit = $canEdit ?? false; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/cform?f=<?= e($form['slug']) ?>"><?= e($form['name']) ?></a> › <?= e($rec['title'] ?: ('#' . $rec['id'])) ?></div>
<div class="master-head"><div>
  <h1><?= e($rec['title'] ?: ($form['name'] . ' #' . $rec['id'])) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($form['name']) ?> · added <?= e(fdate(substr((string)$rec['created_at'],0,10))) ?><?= $rec['created_by'] ? ' by ' . e($rec['created_by']) : '' ?></p></div>
  <div style="display:flex;gap:8px">
    <?php if ($canEdit): ?><a class="btn" href="/cform-edit?id=<?= (int)$rec['id'] ?>">Edit</a><?php endif; ?>
    <a class="btn secondary" href="/cform?f=<?= e($form['slug']) ?>">← Back</a>
  </div>
</div>

<div class="panel" style="margin-top:14px"><div class="kv-grid">
  <?php foreach ($values as $cf): ?>
    <div class="<?= in_array($cf['type'] ?? '', ['textarea'], true) ? 'kv-wide' : '' ?>"><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value'] ?: '—') ?></div>
  <?php endforeach; ?>
  <?php if (!$values): ?><div class="muted">No values recorded.</div><?php endif; ?>
</div></div>

<?php if ($canEdit): ?>
<form method="post" action="/cform-del?id=<?= (int)$rec['id'] ?>" style="margin-top:14px" onsubmit="return confirm('Delete this entry permanently?')">
  <button class="btn small danger" type="submit">Delete entry</button>
</form>
<?php endif; ?>
