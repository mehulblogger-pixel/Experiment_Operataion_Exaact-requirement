<?php
// ============================================================================
//  MILESTONE 12 — the one screen a person reaches when a module will not open.
//
//  One screen, whatever the reason, because a customer who meets four different
//  refusal designs learns nothing from any of them. The REASON changes the
//  words and the next action; the shape stays the same.
//
//  Built from the application's existing panel / msg styles — no new framework,
//  no new stylesheet (§23). It inherits the responsive layout every other panel
//  already has.
//
//  Accessibility (§24): a real <h1>, a real <a> for the action so it is
//  keyboard-reachable and shows the normal focus ring, and the lock is carried
//  by the WORDS as well as the icon — never by colour alone.
// ============================================================================
$a = $a ?? [];
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$msg  = $a['message'] ?? 'This isn’t available in this workspace.';
$hint = $a['hint'] ?? '';
$act  = $a['action'] ?? ['label' => null, 'route' => null];
$isPerm = !empty($a['is_permission']);
// Found by the M12 walkthrough: the heading said "not available" from the
// is_permission flag alone, so a module that is perfectly available read as
// locked if this screen was ever rendered for it. The heading now follows the
// state, and the icon with it.
$avail = !empty($a['available']);
$suffix = $avail ? 'available' : ($isPerm ? 'restricted' : 'not available');
$icon   = $avail ? '✅' : ($isPerm ? '🔐' : '🔒');
?>
<div class="panel" style="max-width:640px;margin:28px auto;text-align:center">
  <div style="font-size:40px;line-height:1" aria-hidden="true"><?= $icon ?></div>

  <h1 style="margin:10px 0 6px;font-size:22px">
    <?= $e($a['label'] ?? 'This feature') ?>
    <span class="muted" style="font-size:14px;font-weight:400">
      — <?= $e($suffix) ?>
    </span>
  </h1>

  <p style="margin:0 auto 10px;max-width:52ch"><?= $e($msg) ?></p>

  <?php if ($hint !== ''): ?>
    <p class="muted" style="margin:0 auto 16px;max-width:52ch"><?= $e($hint) ?></p>
  <?php endif; ?>

  <p style="margin-top:18px">
    <?php if (!empty($act['route']) && !empty($act['label'])): ?>
      <a class="btn" href="<?= $e($act['route']) ?>"><?= $e($act['label']) ?></a>
    <?php endif; ?>
    <a class="btn secondary" href="/">Back to your dashboard</a>
  </p>
</div>
