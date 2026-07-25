<?php // Standard engineering phrase library (editable master) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/writing-assistant">Writing assistant</a> › Phrase library</div>
<div class="master-head"><div><h1>Phrase library</h1>
  <p class="sub" style="margin:2px 0 0">The standard engineering sentences used by the writing assistant. The <strong>shorthand</strong> is what an inspector types; the <strong>phrase</strong> is what goes into the report. Add unlimited phrases of your own.</p></div>
  <a class="btn secondary" href="/writing-assistant">← Writing assistant</a>
</div>

<div class="chip-row" style="margin-bottom:12px">
  <a class="ct" href="/phrase-library" style="<?= $cat===''?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>">All</a>
  <?php foreach ($cats as $k=>$v): ?><a class="ct" href="/phrase-library?cat=<?= e($k) ?>" style="<?= $cat===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= e($v) ?></a><?php endforeach; ?>
</div>

<form method="post" action="/phrase-library" class="panel">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <h3 class="tab-sub" style="margin-top:0"><?= $edit ? 'Edit phrase' : 'Add a phrase' ?></h3>
  <div class="form-grid">
    <div class="ff"><label>Category</label>
      <select class="form-control" name="category"><?php foreach ($cats as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['category'] ?? 'OBSERVATION')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Shorthand <span class="muted">— what the inspector types</span></label><input class="form-control" name="shorthand" value="<?= e($edit['shorthand'] ?? '') ?>" placeholder="e.g. dimension ok"></div>
    <div class="ff"><label>Discipline (optional)</label><input class="form-control" name="discipline" value="<?= e($edit['discipline'] ?? '') ?>" placeholder="e.g. Welding"></div>
    <div class="ff ff-check"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>><label>Active</label></div>
    <div class="ff ff-wide"><label>Phrase *</label><textarea class="form-control" name="phrase" rows="3" required><?= e($edit['phrase'] ?? '') ?></textarea></div>
  </div>
  <div style="margin-top:10px"><button class="btn" type="submit"><?= $edit ? 'Save phrase' : 'Add phrase' ?></button><?php if ($edit): ?> <a class="btn secondary" href="/phrase-library">Cancel</a><?php endif; ?></div>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Category</th><th>Shorthand</th><th>Phrase</th><th class="num">Used</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:14px">No phrases in this category.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted"><?= e($cats[$r['category']] ?? $r['category']) ?></td>
        <td><?= $r['shorthand'] ? '<code>'.e($r['shorthand']).'</code>' : '<span class="muted">—</span>' ?></td>
        <td><?= e(mb_strimwidth($r['phrase'], 0, 130, '…')) ?><?= $r['is_system'] ? ' <span class="muted" style="font-size:11px">(standard)</span>' : '' ?></td>
        <td class="num"><?= (int)$r['usage_count'] ?></td>
        <td><?= $r['active'] ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-mut">Off</span>' ?></td>
        <td style="white-space:nowrap"><a class="btn small secondary" href="/phrase-edit?id=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" action="/phrase-library" style="display:inline" onsubmit="return confirm('<?= $r['is_system']?'Deactivate this standard phrase?':'Remove this phrase?' ?>')"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary"><?= $r['is_system']?'Off':'✕' ?></button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
