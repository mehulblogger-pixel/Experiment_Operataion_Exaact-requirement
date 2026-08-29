<?php
  // A professional's own files — profile photo, CV, certificates. Reuses the
  // shared upload guards; ownership-scoped serve at /pro/file?id=.
  $me = $me ?? []; $files = $files ?? []; $kinds = $kinds ?? [];
  $avatarId = function_exists('connect_pro_avatar_id') ? connect_pro_avatar_id((int)($me['id'] ?? 0)) : 0;
  $kb = fn($n) => $n >= 1048576 ? round($n / 1048576, 1) . ' MB' : max(1, (int)round($n / 1024)) . ' KB';
?>
<h1>Documents &amp; photo</h1>
<p class="muted" style="margin:0 0 14px">Add a profile photo, your CV and certificates. They strengthen your profile and can back up your verification.</p>

<div class="card">
  <h2>Upload</h2>
  <?php if ($avatarId): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
      <img src="/pro/file?id=<?= (int)$avatarId ?>" alt="Your photo" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid var(--line)">
      <span class="muted" style="font-size:13px">Your current profile photo</span>
    </div>
  <?php endif; ?>
  <form method="post" action="/pro/documents" enctype="multipart/form-data">
    <label>Type</label>
    <select name="kind">
      <?php foreach ($kinds as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
    <label style="margin-top:10px">File</label>
    <input type="file" name="file" required>
    <button class="btn" type="submit" style="margin-top:14px;width:100%">Upload</button>
  </form>
</div>

<div class="card">
  <h2>Your files</h2>
  <?php if (!$files): ?>
    <p class="muted" style="margin:0">Nothing uploaded yet.</p>
  <?php else: foreach ($files as $f): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--line)">
      <div>
        <strong><?= e($kinds[$f['kind']] ?? $f['kind']) ?></strong>
        <div class="muted" style="font-size:13px"><?= e($f['file_name']) ?> · <?= $kb((int)$f['size']) ?></div>
      </div>
      <div style="display:flex;gap:6px;align-items:center">
        <a class="btn sec" href="/pro/file?id=<?= (int)$f['id'] ?>" target="_blank" rel="noopener" style="font-size:13px;padding:7px 12px">View</a>
        <form method="post" action="/pro/documents" style="display:inline" onsubmit="return confirm('Remove this file?')">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
          <button class="btn sec" type="submit" style="font-size:13px;padding:7px 12px;color:var(--bad);border-color:var(--bad)">Remove</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<p class="muted"><a href="/pro/profile">← Back to profile</a></p>
