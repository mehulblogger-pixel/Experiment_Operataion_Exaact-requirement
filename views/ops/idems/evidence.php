<?php
  // Evidence gallery — photos & attachments organised by report section, with
  // captions, capture time, GPS and compression savings.
  $bySection = [];
  foreach ($files as $f) {
    $m = $fieldMeta[$f['field_key']] ?? ['label'=>$f['field_key'] ?: 'Unlinked', 'section'=>'Other'];
    $bySection[$m['section']][$m['label']][] = $f;
  }
  $kb = fn($b) => $b >= 1048576 ? round($b/1048576, 1) . ' MB' : max(1, round($b/1024)) . ' KB';
  $editable = idems_can_edit_doc($doc);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents">Documents</a> › <a href="/document?id=<?= (int)$doc['id'] ?>"><?= e($doc['irn']) ?></a> › Evidence</div>
<div class="master-head">
  <div><h1>Evidence gallery</h1>
    <p class="sub" style="margin:2px 0 0">Photos and attachments for <?= e($doc['irn']) ?>, organised by the section they belong to. Images are compressed on upload and duplicates are skipped automatically.</p></div>
  <div style="display:flex;gap:6px">
    <?php if ($editable): ?><a class="btn" href="/document-fill?id=<?= (int)$doc['id'] ?>">Add evidence</a><?php endif; ?>
    <a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">← Back to report</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab">Items</div><div class="k-val"><?= (int)$stats['n'] ?></div><div class="k-sub">photos &amp; files</div></div>
  <div class="kpi"><div class="k-lab">Stored size</div><div class="k-val"><?= $kb($stats['bytes']) ?></div><div class="k-sub">after compression</div></div>
  <div class="kpi"><div class="k-lab">Space saved</div><div class="k-val up"><?= $kb(max(0, $stats['orig'] - $stats['bytes'])) ?></div><div class="k-sub">vs original</div></div>
  <div class="kpi"><div class="k-lab">With GPS</div><div class="k-val"><?= (int)$stats['gps'] ?></div><div class="k-sub">location tagged</div></div>
</div>

<?php if (!$files): ?>
  <div class="panel"><p class="muted">No evidence attached yet. Use <strong>Add evidence</strong> to capture photos while filling the report — the camera, GPS and timestamp are captured together.</p></div>
<?php endif; ?>

<?php foreach ($bySection as $sec => $groups): ?>
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3><?= e($sec) ?></h3></div>
    <?php foreach ($groups as $label => $items): ?>
      <div style="margin-bottom:12px">
        <div style="font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand);margin-bottom:6px"><?= e($label) ?> <span class="muted">(<?= count($items) ?>)</span></div>
        <div class="ev-grid">
          <?php foreach ($items as $f): $isImg = strpos($f['mime'], 'image/') === 0; ?>
            <div class="ev-card">
              <?php if ($isImg): ?>
                <a href="/report-file?id=<?= (int)$f['id'] ?>" target="_blank"><img src="/report-file?id=<?= (int)$f['id'] ?>" alt="evidence"></a>
              <?php else: ?>
                <a class="ev-file" href="/report-file?id=<?= (int)$f['id'] ?>" target="_blank">📎<br><span><?= e(mb_strimwidth($f['file_name'], 0, 26, '…')) ?></span></a>
              <?php endif; ?>
              <div class="ev-meta">
                <?php if ($f['taken_at']): ?><div>🕒 <?= e(date('d M Y H:i', strtotime($f['taken_at']))) ?></div><?php endif; ?>
                <?php if (trim((string)$f['gps']) !== ''): ?><div>📍 <a href="https://maps.google.com/?q=<?= e(urlencode($f['gps'])) ?>" target="_blank" rel="noopener"><?= e($f['gps']) ?></a></div><?php endif; ?>
                <div class="muted"><?= $kb((int)($f['bytes'] ?: 0)) ?><?= ((int)$f['orig_bytes'] > (int)$f['bytes']) ? ' · from ' . $kb((int)$f['orig_bytes']) : '' ?></div>
              </div>
              <?php if ($editable): ?>
                <form method="post" action="/document-evidence?id=<?= (int)$doc['id'] ?>" class="ev-cap">
                  <input type="hidden" name="_do" value="caption"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
                  <input class="form-control" name="caption" value="<?= e($f['caption'] ?? '') ?>" placeholder="Add a caption…">
                  <button class="btn small secondary" type="submit">Save</button>
                </form>
                <form method="post" action="/document-evidence?id=<?= (int)$doc['id'] ?>" onsubmit="return confirm('Remove this evidence?')" style="margin-top:4px">
                  <input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
                  <button class="btn small secondary" type="submit">Remove</button>
                </form>
              <?php elseif ($f['caption']): ?>
                <div style="font-size:12px;margin-top:4px"><?= e($f['caption']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<style>
  .ev-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}
  .ev-card{border:1px solid var(--line);border-radius:10px;padding:8px;background:var(--card)}
  .ev-card img{width:100%;height:130px;object-fit:cover;border-radius:8px;display:block}
  .ev-file{display:block;text-align:center;height:130px;line-height:1.3;padding-top:40px;background:var(--soft);border-radius:8px;font-size:12px}
  .ev-meta{font-size:11px;margin-top:5px;line-height:1.5}
  .ev-cap{display:flex;gap:4px;margin-top:5px}
  .ev-cap .form-control{font-size:12px;padding:4px 6px}
</style>
