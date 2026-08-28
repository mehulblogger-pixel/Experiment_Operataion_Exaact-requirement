<?php
// Connect K1 — staff screen to get/copy a professional's public passport link,
// see its QR, preview the public page, and regenerate (revoke) the link.
$inspector = $inspector ?? null; $token = $token ?? ''; $url = $url ?? '';
$data = $data ?? null; $inspectors = $inspectors ?? [];
$qr = ($url !== '' && function_exists('qr_svg')) ? qr_svg($url, 160, 'M', 3) : '';
?>
<div class="crumbs"><a href="/">Home</a> › Passport link</div>
<div class="master-head">
  <div><h1>Professional passport</h1>
    <p class="sub" style="margin:2px 0 0">The public, shareable page for a professional — verified credentials, live status and reputation.
      Share the link or the QR; anyone can open it without an account. It shows nothing confidential.</p></div>
</div>

<?php if (!$inspector): ?>
  <div class="panel" style="margin-top:12px">
    <p class="muted" style="margin:0 0 10px">Choose a professional to get their passport link:</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($inspectors as $i): ?>
        <a class="btn secondary" href="/passport-share?id=<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a>
      <?php endforeach; ?>
      <?php if (!$inspectors): ?><span class="muted">No active professionals on record.</span><?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="kpi-row" style="margin-top:12px">
    <div class="kpi"><span class="kic">🪪</span><div class="k">Professional</div><div class="v" style="font-size:18px"><?= e($inspector['name']) ?></div></div>
    <div class="kpi"><span class="kic">🎖️</span><div class="k">Credentials</div><div class="v"><?= (int)($data['cred_total'] ?? 0) ?></div><div class="d"><?= (int)($data['verified_count'] ?? 0) ?> verified</div></div>
    <div class="kpi"><span class="kic">✅</span><div class="k">Live now</div><div class="v"><?= (int)($data['live_count'] ?? 0) ?></div></div>
  </div>

  <div class="panel" style="margin-top:12px">
    <h3 style="margin:0 0 8px">Share link</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="text" readonly value="<?= e($url) ?>" onclick="this.select()" style="flex:1;min-width:260px;padding:12px;border:1px solid var(--line);border-radius:10px;font-size:15px">
      <a class="btn" href="<?= e($url) ?>" target="_blank" rel="noopener">Open public page ↗</a>
    </div>
    <p class="muted" style="margin:10px 0 0;font-size:13px">Anyone with this link (or the QR below) can view the passport. Regenerating the link revokes the old one.</p>
  </div>

  <?php if ($qr !== ''): ?>
  <div class="panel" style="margin-top:12px;text-align:center">
    <h3 style="margin:0 0 8px">QR code</h3>
    <div style="display:inline-block"><?= $qr ?></div>
    <p class="muted" style="margin:8px 0 0;font-size:13px">Print on a card or CV; scanning opens the verified passport.</p>
  </div>
  <?php endif; ?>

  <form method="post" action="/passport-share" style="margin-top:12px" onsubmit="return confirm('Generate a new link? The current link will stop working.');">
    <input type="hidden" name="id" value="<?= (int)$inspector['id'] ?>">
    <input type="hidden" name="action" value="regenerate">
    <button type="submit" class="btn secondary">↻ Regenerate link (revoke current)</button>
  </form>
<?php endif; ?>
