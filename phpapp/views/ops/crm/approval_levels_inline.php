<?php
//  QUOTATION APPROVAL LEVELS, EDITED WHERE THEY ARE ASKED ABOUT.
//
//  Set up Sales asks "who approves, and up to what value" — and used to answer
//  with a link to another screen (owner, SAL-002: "we must have option to set
//  the applicable rules from that screen directly"). Each level is a card here
//  with its own Save and Remove; "Add a level" is pre-filled with the next
//  level and the value the last one stops at.
//
//  Set up Sales is itself one form (the answers). A form inside a form is not
//  allowed, so each card's inputs name their own form with the `form`
//  attribute, and those forms are printed after the answers form ($mode =
//  'forms'). They post to the rule routes the separate screen has always used
//  (same permission, same checks) with back=sales-setup, which returns here.
$L = setupqa_quote_levels_data();
$mode = $mode ?? 'rows';
$rows = $L['rows'];
if ($mode === 'forms') {
    if (!$L['can']) return;
    foreach ($rows as $r): ?>
      <form method="post" id="ql-<?= (int) $r['id'] ?>" action="/quote-approval-rule-edit?id=<?= (int) $r['id'] ?>&amp;back=sales-setup"></form>
      <form method="post" id="qld-<?= (int) $r['id'] ?>" action="/quote-approval-rule-delete?id=<?= (int) $r['id'] ?>&amp;back=sales-setup"
            onsubmit="return confirm('Remove this approval level? Quotations already approved keep their approval.')"></form>
    <?php endforeach; ?>
    <form method="post" id="ql-new" action="/quote-approval-rule-new?back=sales-setup"></form>
    <?php return;
}

$nextLevel = 1; $nextFrom = 0.0; $openTop = false;
foreach ($rows as $r) {
    $nextLevel = max($nextLevel, (int) $r['level'] + 1);
    if ((float) $r['max_amount'] > 0) $nextFrom = max($nextFrom, (float) $r['max_amount']); else $openTop = true;
}
$cur = function_exists('cur_sym') ? cur_sym() : '₹';
$unit = function_exists('Tl') ? Tl('sbu') : 'business unit';
$card = 'border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:12px;margin:10px 0;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:end';
$field = function ($label, $html) { return '<div class="ff" style="margin:0"><label style="font-size:12.5px">' . e($label) . '</label>' . $html . '</div>'; };
$levelCard = function ($r, $fid, $isNew) use ($L, $cur, $unit, $card, $field) {
    $isUnit = $r && crm_rule_is_unit($r);
    $v = fn($k, $d = '') => e((string) ($r[$k] ?? $d));
    ob_start(); ?>
    <div style="<?= $card ?><?= $isNew ? ';border-style:dashed' : '' ?>" <?= $isNew ? 'id="ql-add"' : '' ?>>
      <?= $field('Level', '<input class="form-control" form="' . $fid . '" type="number" min="1" name="level" required value="' . $v('level', 1) . '">') ?>
      <?= $field('From value (' . $cur . ', before tax)', '<input class="form-control" form="' . $fid . '" type="number" step="0.01" min="0" name="min_amount" value="' . $v('min_amount', 0) . '">') ?>
      <?= $field('Up to value (0 = no limit)', '<input class="form-control" form="' . $fid . '" type="number" step="0.01" min="0" name="max_amount" value="' . $v('max_amount', 0) . '">') ?>
      <?php
        $roleOpts = '<option value="">Anyone who may approve</option><option value="REPORTS_TO"' . (($r['approver_role'] ?? '') === 'REPORTS_TO' ? ' selected' : '') . '>The preparer\'s reporting manager</option>';
        foreach ($L['roles'] as $k => $lbl) $roleOpts .= '<option value="' . e($k) . '"' . (($r['approver_role'] ?? '') === $k ? ' selected' : '') . '>' . e($lbl) . '</option>';
        $userOpts = '<option value="">— nobody in particular —</option>';
        foreach ($L['users'] as $u) {
            $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
            $userOpts .= '<option value="' . (int) $u['id'] . '"' . ((string) ($r['approver_user_id'] ?? '') === (string) $u['id'] ? ' selected' : '') . '>' . e($nm) . '</option>';
        }
        $unitOpts = '<option value="">Every ' . e($unit) . '</option>';
        foreach ($L['sbuOpts'] as $k => $lbl) $unitOpts .= '<option value="' . e($k) . '"' . ($isUnit && ($r['sbu'] ?? '') === (string) $k ? ' selected' : '') . '>' . e($lbl) . ' only</option>';
      ?>
      <?= $field('Approved by (role)', '<select class="form-control" form="' . $fid . '" name="approver_role">' . $roleOpts . '</select>') ?>
      <?= $field('…or one named person', '<select class="form-control" form="' . $fid . '" name="approver_user_id">' . $userOpts . '</select>') ?>
      <?= $field('For', '<select class="form-control" form="' . $fid . '" name="sbu">' . $unitOpts . '</select>'
               . '<input type="hidden" form="' . $fid . '" name="match_type" value="SBU">') ?>
      <?php // A name typed on the full rules screen is kept; one this editor wrote
            //  ("Level 2 — Marketing Manager") is rewritten from the new facts. ?>
      <input type="hidden" form="<?= $fid ?>" name="name" value="<?= $isNew || preg_match('/^Level \d+ — /u', (string) ($r['name'] ?? '')) ? '' : $v('name') ?>">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <label class="chk" style="margin:0"><input type="checkbox" form="<?= $fid ?>" name="active" value="1" <?= ($r['active'] ?? 1) ? 'checked' : '' ?>> In use</label>
        <button class="btn<?= $isNew ? '' : ' secondary' ?>" type="submit" form="<?= $fid ?>"><?= $isNew ? 'Add this level' : 'Save' ?></button>
        <?php if (!$isNew): ?><button class="btn secondary" type="submit" form="qld-<?= (int) $r['id'] ?>" aria-label="Remove level <?= (int) $r['level'] ?>">Remove</button><?php endif; ?>
      </div>
    </div>
    <?php return ob_get_clean();
};
?>
<div id="levels" style="margin:4px 0 6px">
  <h3 style="font-size:14.5px;margin:10px 0 2px">Who approves, and up to what value</h3>
  <?php if (!$L['can']): ?>
    <p class="muted" style="margin:4px 0 0;font-size:13px">Only someone who may approve <?= e(function_exists('Tlp') ? Tlp('quote') : 'quotations') ?> can change the levels.</p>
  <?php else: ?>
    <p class="muted" style="margin:2px 0 0;font-size:13px">A <?= e(function_exists('Tl') ? Tl('quote') : 'quotation') ?> goes through every level its value falls in, level 1 first. Pick a role or one named person. "For" limits a level to one <?= e($unit) ?>.</p>
    <?php foreach ($rows as $r) echo $levelCard($r, 'ql-' . (int) $r['id'], false); ?>
    <?php if (!$rows): ?><p style="margin:10px 0 0">No levels yet. Add the first one:</p><?php endif; ?>
    <?= $levelCard(['level' => $nextLevel, 'min_amount' => $openTop ? '' : $nextFrom, 'max_amount' => 0, 'active' => 1], 'ql-new', true) ?>
    <?php if ($openTop): ?><p class="muted" style="margin:0;font-size:12.5px">One of your levels already has no upper limit; a new level beside it is for a different <?= e($unit) ?> or a second signature.</p><?php endif; ?>
  <?php endif; ?>
</div>
