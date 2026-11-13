<?php
//  Setup in plain questions — one screen per module (lib/setupqa.php).
//  Each question is a set of choices; the current answer is the one selected.
$set = $set ?? ['title' => '', 'intro' => '', 'route' => '', 'sections' => []];
?>
<div class="master-head">
  <div>
    <h1><?= e($set['title']) ?> — set up in a few questions</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px"><?= e($set['intro']) ?></p>
    <?php if (($set['route'] ?? '') === 'sales-setup'): ?><p class="sub" style="margin:4px 0 0">Looking for one approval only? <a href="/sales-approvals">Sales approvals — one tab each →</a></p><?php endif; ?>
  </div>
</div>

<form method="post" action="/<?= e($set['route']) ?>" style="max-width:820px">
  <?php $sections = $set['sections']; include __DIR__ . '/_setup_sections.php'; ?>
  <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="submit">Save these answers</button>
    <a class="btn secondary" href="<?= e($set['home'] ?? '/') ?>">Back to <?= e($set['title']) ?></a>
  </div>
</form>
<?php //  The level cards' own forms — outside the answers form (see the partial).
      foreach ($set['sections'] as $sec) if (($sec['editor'] ?? '') === 'quote_levels') { $mode = 'forms'; include __DIR__ . '/crm/approval_levels_inline.php'; } ?>
