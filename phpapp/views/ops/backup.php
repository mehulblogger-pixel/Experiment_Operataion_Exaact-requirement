<?php
// Backup & Restore — shown to each workspace admin. Data: $backups, $safe, $dir, $storage.
$backups = $backups ?? []; $safe = $safe ?? false; $storage = $storage ?? null;
$fmtWhen = function ($s) { $t = strtotime((string) $s); return $t ? date('d M Y, g:i a', $t) : e((string) $s); };
$reasonLabel = ['manual' => 'You clicked Back up now', 'daily' => 'Automatic daily', 'pre_restore' => 'Safety copy before a restore',
                'clientimport' => 'Uploaded backup', 'pre_import_safesnap' => 'Safety copy before an import'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Backup &amp; restore</div>
<div class="master-head">
  <div><h1>Backup &amp; restore</h1>
    <p class="sub">A complete, dated copy of this workspace's data — to download, keep safe, and restore from in two clicks.</p></div>
  <div>
    <form method="post" style="display:inline"><input type="hidden" name="do" value="backup_now"><button class="btn">💾 Back up now</button></form>
  </div>
</div>

<?php if ($safe): ?>
<div class="msg-success" style="display:flex;gap:10px;align-items:flex-start">
  <span style="font-size:18px;line-height:1.1">🛡️</span>
  <div><strong>Your backups are stored safely outside the app folder.</strong>
    They are <em>not</em> deleted when you update the app by uploading files — they survive uploads, and this workspace can be restored from any of them.</div>
</div>
<?php else: ?>
<div class="msg-warning" style="display:flex;gap:10px;align-items:flex-start">
  <span style="font-size:18px;line-height:1.1">⚠️</span>
  <div><strong>Backups are currently kept inside the app folder.</strong>
    They work for download and restore, but an update that deletes every file would remove them too.
    <strong>Download the newest backup to your computer</strong> for full safety — and ask your administrator to set a backup folder above the web root.</div>
</div>
<?php endif; ?>

<?php if ($storage && ($storage['type'] ?? '') === 'sqlite' && !empty($storage['at_risk'])): ?>
<div class="msg-warning">
  <strong>This workspace stores its live data as a file inside the app folder.</strong>
  Backups here protect you, but for the live data itself the permanent fix is to move it to MySQL — an administrator can do that from the Companies console.
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Saved backups <span class="muted" style="font-weight:400">(newest first — the last <?= (int) BACKUP_MAX_KEEP ?> are kept)</span></h3>
  <?php if (!$backups): ?>
    <p class="muted">No backups yet. Click <strong>Back up now</strong> above to create the first one. After that, one is taken automatically each day you use the workspace.</p>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>When</th><th>Why</th><th style="width:100px">Records</th><th style="width:90px">Size</th><th style="width:230px">Actions</th></tr>
    <?php foreach ($backups as $b): ?>
    <tr>
      <td><strong><?= $fmtWhen($b['created_at']) ?></strong></td>
      <td class="muted"><?= e($reasonLabel[$b['reason']] ?? ucfirst(str_replace('_', ' ', (string) $b['reason']))) ?></td>
      <td><?= number_format((int) $b['total_rows']) ?></td>
      <td class="muted"><?= e((string) $b['size_kb']) ?> KB</td>
      <td>
        <a class="btn xs ghost" href="/backup?download=<?= e(rawurlencode($b['id'])) ?>">⬇️ Download</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Restore this workspace to how it was on <?= e($fmtWhen($b['created_at'])) ?>?\n\nEverything since then will be replaced. A safety copy of the current state is saved first, so this is reversible.');">
          <input type="hidden" name="do" value="restore"><input type="hidden" name="id" value="<?= e($b['id']) ?>">
          <button class="btn xs" style="background:#b45309">↺ Restore</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<details class="panel">
  <summary style="cursor:pointer;font-weight:600">📤 Restore from a file on my computer</summary>
  <p class="sub" style="margin-top:10px">Have a backup you downloaded earlier (a <code>.json</code> or <code>.json.gz</code> file)? Upload it here to restore this workspace from it. A safety copy of the current state is saved first.</p>
  <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Restore this workspace from the uploaded file? The current data will be replaced (a safety copy is saved first).');">
    <input type="hidden" name="do" value="import">
    <input type="file" name="file" accept=".json,.gz,application/json,application/gzip" required>
    <button class="btn" style="margin-left:8px;background:#b45309">↺ Upload &amp; restore</button>
  </form>
</details>

<p class="muted" style="font-size:12px;margin-top:10px">Backups include every register in this workspace. Restoring replaces the current data with the backup's — the previous state is always saved first, so you can undo a restore by restoring the “Safety copy” it created.</p>
