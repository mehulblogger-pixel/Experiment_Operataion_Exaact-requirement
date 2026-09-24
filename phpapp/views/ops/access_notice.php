<?php
// ============================================================================
//  ACCESS NOTICE — what a user sees instead of being bounced to the dashboard.
//
//  B9-4. Asking for an area you cannot open used to flash a message and
//  redirect to "/", so a field inspector who tapped "Money" in the rail simply
//  found themselves back on the home screen. The permission decision is
//  unchanged — this only explains the outcome and offers somewhere to go.
//
//  It deliberately says nothing about WHY in system terms: no permission names,
//  no capability keys, no counts, no record identifiers, nothing about whether
//  anything exists behind the door. Only that this area is not part of this
//  person's work, or is not switched on for the workspace.
//
//  Vars: $noticeTitle, $noticeWhy, $noticeLinks — never a key called "name",
//  which view() would extract over its own view identifier.
// ============================================================================
?>
<div class="crumbs"><a href="/">Home</a> › <?= e($noticeTitle) ?></div>
<div class="master-head">
  <div>
    <h1><?= e($noticeTitle) ?></h1>
  </div>
</div>

<div class="panel" style="margin-top:14px;max-width:640px">
  <p style="margin:0 0 4px;font-size:15px;font-weight:600"><?= e($noticeWhy) ?></p>
  <?php //  B10 — a caller that already has a precise explanation (for example the
        //  inspector-link message) supplies its own sub-line; everybody else gets
        //  the original reassurance. Never both. ?>
  <p class="muted" style="margin:0 0 14px;font-size:13.5px">
    <?= isset($noticeSub) && $noticeSub !== '' ? e($noticeSub)
        : 'Nothing is wrong with your account — this part of the system simply isn’t part of your work.
           If you think you should have it, ask your administrator.' ?>
  </p>
  <div class="row-actions" style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($noticeLinks as $lnk): ?>
      <a class="btn<?= !empty($lnk['primary']) ? ' primary' : ' secondary' ?>" href="<?= e($lnk['href']) ?>"><?= e($lnk['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
