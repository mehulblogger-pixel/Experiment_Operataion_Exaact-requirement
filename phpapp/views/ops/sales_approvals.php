<?php
//  ADMIN → APPROVALS → SALES. One tab for each approval in Sales (owner, 13 Nov
//  2026). Each tab says the rule in force in plain sentences, then lets it be
//  changed right there. The same sections make up "Set up Sales" — one copy of
//  every rule, two ways in.
?>
<div class="master-head">
  <div>
    <h1>Sales approvals</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px">Who approves what in Sales. Pick a tab, read the rule in force, change it if your company works differently. A change applies from now on.</p>
  </div>
</div>

<?= $strip ?>

<nav class="appr-tabs" aria-label="Sales approvals" style="margin-top:10px">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="appr-tab<?= $k === $tab ? ' on' : '' ?>" href="/sales-approvals?tab=<?= e($k) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php
  $hasAnswers = false;
  foreach ($set['sections'] as $sec) if ((!isset($sec['can']) || ($sec['can'])()) && (!empty($sec['questions']) || !empty($sec['save']))) $hasAnswers = true;
?>
<form method="post" action="/sales-approvals?tab=<?= e($tab) ?>" style="max-width:860px">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php $sections = $set['sections']; $levelsBack = 'sales-approvals'; include __DIR__ . '/_setup_sections.php'; ?>
  <?php if ($hasAnswers): ?>
  <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;position:sticky;bottom:0;background:var(--bg,#fff);padding:10px 0">
    <button class="btn" type="submit">Save this rule</button>
  </div>
  <?php endif; ?>
</form>
<?php //  The level cards' own forms — outside the answers form (see the partial).
      foreach ($set['sections'] as $sec) if (($sec['editor'] ?? '') === 'quote_levels') { $mode = 'forms'; $levelsBack = 'sales-approvals'; include __DIR__ . '/crm/approval_levels_inline.php'; } ?>
