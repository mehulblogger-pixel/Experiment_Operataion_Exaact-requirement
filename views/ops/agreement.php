<?php $rec = $rec ?? []; $sections = $sections ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Licence agreement</div>
<div class="master-head"><div>
  <h1>Licence agreement</h1>
  <p class="sub" style="margin:2px 0 0">The contract accepted when this installation was set up, and the record of who accepted it.</p>
</div></div>

<?php if ($rec): ?>
<div class="panel" style="margin-top:14px;max-width:720px">
  <h3 class="tab-sub" style="margin-top:0">Accepted</h3>
  <div class="kv-grid">
    <div><span class="k">Name (signature)</span><span><?= e($rec['name'] ?? '—') ?></span></div>
    <div><span class="k">Designation</span><span><?= e($rec['designation'] ?? '—') ?></span></div>
    <div><span class="k">Organisation</span><span><?= e($rec['organisation'] ?? '—') ?></span></div>
    <div><span class="k">E-mail</span><span><?= e($rec['email'] ?? '—') ?></span></div>
    <div><span class="k">Accepted at</span><span><?= e($rec['accepted_at'] ?? '—') ?></span></div>
    <div><span class="k">From address</span><span><?= e($rec['ip'] ?? '—') ?></span></div>
    <div><span class="k">Version</span><span><?= e($rec['version'] ?? '—') ?></span></div>
  </div>
</div>
<?php else: ?>
<div class="panel" style="margin-top:14px;max-width:720px">
  <p class="muted" style="margin:0">No acceptance is recorded on this installation. That is expected on the provider's own
    server and on development copies, which are exempt from the installation agreement.</p>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">The agreement <span class="muted">— version <?= e($version ?? '') ?></span></h3>
  <div style="font-size:13.5px;line-height:1.6;max-width:820px">
    <?php foreach ($sections as $s): ?>
      <h4 style="margin:14px 0 4px;color:var(--ink)"><?= $s[0] ?></h4>
      <?= $s[1] ?>
    <?php endforeach; ?>
    <p class="muted" style="font-size:12px">This document is a template and does not constitute legal advice.</p>
  </div>
</div>
