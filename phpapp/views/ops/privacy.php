<div class="crumbs"><a href="/">Home</a> › <span>Privacy notice</span></div>

<div class="master-head">
  <div><h1>Privacy notice</h1>
    <p class="sub" style="margin:2px 0 0">What this system holds about people, why, and who to speak to about it.</p></div>
</div>

<?php if (trim($notice) === ''): ?>
  <div class="msg msg-warning">
    No notice has been written yet. The DPDP Act requires one wherever personal data is collected.
    An administrator can write it under <a href="/settings">Settings → Compliance</a>, where a starting draft is offered.
  </div>
<?php else: ?>
  <div class="panel"><div style="font-size:14.5px;line-height:1.75;white-space:pre-wrap"><?= e($notice) ?></div></div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Who to speak to</h3></div>
  <?php if ($g['name'] && $g['email']): ?>
    <p style="font-size:14.5px;line-height:1.8">
      <strong><?= e($g['name']) ?></strong><br>
      <a href="mailto:<?= e($g['email']) ?>"><?= e($g['email']) ?></a>
      <?= $g['phone'] ? '<br>' . e($g['phone']) : '' ?>
    </p>
    <p class="muted" style="font-size:13.5px">
      You can ask for a copy of what is held about you, ask for something wrong to be corrected, ask for it to be
      deleted, or withdraw consent you gave earlier. Write to the address above and it will be logged and answered.
    </p>
  <?php else: ?>
    <div class="msg msg-warning">No grievance officer has been named. The DPDP Act requires one to be published.</div>
  <?php endif; ?>
</div>
