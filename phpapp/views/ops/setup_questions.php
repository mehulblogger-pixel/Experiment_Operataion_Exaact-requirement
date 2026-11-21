<?php
//  Setup in plain questions — one screen per module (lib/setupqa.php).
//  Each question is a set of choices; the current answer is the one selected.
//
//  Stage 6 of the Sales redesign (owner, 21 Nov 2026): "Set up Sales" opens in the
//  Sales workspace look — the Sales bar, a row of jump links to each part of the
//  page (Quotations, costing, contract opening, deal stages, leads …) and the
//  record-page cards. Every question, choice, level card, link and save is the one
//  it had (old page: tests/fixtures/pre_stage6/setup_questions.php). The other
//  modules' set-up pages keep their look.
$set = $set ?? ['title' => '', 'intro' => '', 'route' => '', 'sections' => []];
$__sales = ($set['route'] ?? '') === 'sales-setup' && !(function_exists('is_embed') && is_embed());
if ($__sales) require_once __DIR__ . '/_sxw_ui.php';
?>
<?php if ($__sales): ?><div class="rec sxw has-bn sxq4 sxq6"><?php sxw_nav('/sales-setup'); ?><?php endif; ?>
<div class="master-head">
  <div>
    <h1><?= e($set['title']) ?> — set up in a few questions</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px"><?= e($set['intro']) ?></p>
    <?php if (($set['route'] ?? '') === 'sales-setup'): ?><p class="sub" style="margin:4px 0 0">Looking for one approval only? <a href="/sales-approvals">Sales approvals — one tab each →</a></p><?php endif; ?>
  </div>
</div>
<?php if ($__sales && count($set['sections']) > 1): ?>
  <nav class="sxq6-jump" aria-label="Parts of this page">
    <?php $__j = 0; foreach ($set['sections'] as $sec): ?><a href="#sq-<?= ++$__j ?>"><?= e($sec['title']) ?></a><?php endforeach; ?>
  </nav>
<?php endif; ?>

<form method="post" action="/<?= e($set['route']) ?>" style="max-width:<?= $__sales ? '980px' : '820px' ?>">
  <?php $sections = $set['sections']; include __DIR__ . '/_setup_sections.php'; ?>
  <div class="<?= $__sales ? 'sxs6-bar' : '' ?>" style="<?= $__sales ? '' : 'margin-top:14px;display:flex;gap:8px;flex-wrap:wrap' ?>">
    <?php if ($__sales): ?><span>Each changed answer is written to History.</span><?php endif; ?>
    <button class="btn" type="submit">Save these answers</button>
    <a class="btn secondary" href="<?= e($set['home'] ?? '/') ?>">Back to <?= e($set['title']) ?></a>
  </div>
</form>
<?php //  The level cards' own forms — outside the answers form (see the partial).
      foreach ($set['sections'] as $sec) if (($sec['editor'] ?? '') === 'quote_levels') { $mode = 'forms'; include __DIR__ . '/crm/approval_levels_inline.php'; } ?>
<?php if ($__sales): ?></div><?php sxw_bottom('/sales-setup'); ?><?php endif; ?>
