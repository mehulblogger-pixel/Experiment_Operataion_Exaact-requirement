<?php $ed = $edit; ?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Retention schedule</div>
<div class="master-head"><div><h1>Record-retention schedule</h1>
  <p class="sub">How long each kind of record is kept, and what happens to it at the end (ISO §8.4)</p></div></div>

<table class="grid">
  <tr><th>Record type</th><th>Kept for</th><th>Basis</th><th>Disposal</th><th>Owner</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><strong><?= e($r['record_type']) ?></strong><?php if ($r['notes']): ?><br><span class="muted" style="font-size:12px"><?= e($r['notes']) ?></span><?php endif; ?></td>
    <td><?= e($r['retention_period'] ?: '—') ?></td>
    <td class="muted" style="font-size:13px"><?= e($r['basis'] ?: '—') ?></td>
    <td><?= e($r['disposal_action'] ?: '—') ?></td>
    <td><?= e($r['owner'] ?: '—') ?></td>
    <?php if ($canEdit): ?><td class="row-actions">
      <a class="btn small secondary" href="/retention?edit=<?= (int)$r['id'] ?>">Edit</a>
      <form method="post" action="/retention-del" style="display:inline" onsubmit="return confirm('Remove this row?')">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn small danger" type="submit">Remove</button>
      </form>
    </td><?php endif; ?>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= $canEdit ? 6 : 5 ?>" class="muted" style="padding:16px">No retention rules yet.</td></tr><?php endif; ?>
</table>

<?php if ($canEdit): ?>
<div class="panel" style="margin-top:22px;padding:18px">
  <h3 style="margin-top:0"><?= $ed ? 'Edit row' : 'Add a record type' ?></h3>
  <form method="post" action="<?= $ed ? '/retention-edit?id=' . (int)$ed['id'] : '/retention-new' ?>" class="settings-form">
    <div class="form-grid">
      <div class="ff" style="grid-column:1/-1"><label>Record type *</label>
        <input class="form-control" name="record_type" value="<?= e($ed['record_type'] ?? '') ?>" required placeholder="e.g. Inspection reports & certificates"></div>
      <div class="ff"><label>Kept for</label>
        <input class="form-control" name="retention_period" value="<?= e($ed['retention_period'] ?? '') ?>" placeholder="e.g. 7 years"></div>
      <div class="ff"><label>Disposal action</label>
        <input class="form-control" name="disposal_action" value="<?= e($ed['disposal_action'] ?? '') ?>" placeholder="e.g. Archive, then secure delete"></div>
      <div class="ff" style="grid-column:1/-1"><label>Basis (legal / standard)</label>
        <input class="form-control" name="basis" value="<?= e($ed['basis'] ?? '') ?>" placeholder="e.g. ISO/IEC 17020 §7.3 / Income-tax Act"></div>
      <div class="ff"><label>Owner</label>
        <input class="form-control" name="owner" value="<?= e($ed['owner'] ?? '') ?>"></div>
      <div class="ff" style="grid-column:1/-1"><label>Notes</label>
        <input class="form-control" name="notes" value="<?= e($ed['notes'] ?? '') ?>"></div>
    </div>
    <div style="margin-top:12px"><button class="btn" type="submit"><?= $ed ? 'Save row' : 'Add row' ?></button>
      <?php if ($ed): ?><a class="btn secondary" href="/retention">Cancel</a><?php endif; ?></div>
  </form>
</div>
<?php endif; ?>
