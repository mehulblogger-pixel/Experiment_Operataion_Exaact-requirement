<?php $grand = 0; foreach ($counts as $c) $grand += $c['total']; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Clear records</div>
<div class="master-head">
  <div><h1>Clear records</h1>
    <p class="sub">For setting up and testing. Tick what to remove, see how many records that is, and type DELETE to confirm.</p></div>
  <div><a class="btn ghost" href="/settings">Back to settings</a></div>
</div>

<div class="msg-warning">
  <strong>This cannot be undone.</strong> There is no recycle bin and no restore.
  Take a backup of the database first if there is anything here you would miss.
  Your own login is never deleted, your settings and terminology are kept, and the
  standard master lists are put back straight away so the system still works.
</div>

<form method="post" action="/reset-data">
<div class="panel">
  <h3 class="tab-sub">What should go?</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th style="width:40px"></th><th>Group</th><th style="width:120px">Records</th><th>What is in it</th></tr>
    <?php foreach ($groups as $key => $g): $n = $counts[$key]['total']; ?>
    <tr>
      <td><input type="checkbox" name="groups[]" value="<?= e($key) ?>" id="g_<?= e($key) ?>" <?= $n ? '' : 'disabled' ?>></td>
      <td><label for="g_<?= e($key) ?>"><strong><?= e($g['label']) ?></strong></label>
          <?php if (!empty($g['danger'])): ?><div><span class="pill p-bad">think twice</span></div><?php endif; ?></td>
      <td><?= $n ? '<strong>' . number_format($n) . '</strong>' : '<span class="muted">empty</span>' ?></td>
      <td class="muted"><?= e($g['note']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr><td></td><td><strong>Everything above</strong></td>
        <td><strong><?= number_format($grand) ?></strong></td>
        <td><button class="btn small ghost" type="button" onclick="document.querySelectorAll('input[name=\'groups[]\']:not([disabled])').forEach(function(c){c.checked=true});return false;">Tick them all</button></td></tr>
  </table>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub">Confirm</h3>
  <div class="form-grid">
    <div class="ff"><label>Type <strong>DELETE</strong> to allow it</label>
      <input class="form-control" type="text" name="confirm_word" autocomplete="off" placeholder="DELETE" required></div>
    <?php // Phase 2 §54 — a second, distinct phrase specifically for erasing the sealed
          // audit trail. It is the evidence the system was used properly; it must never be
          // wiped by ticking a box in passing. Only needed if the Audit group is selected. ?>
    <div class="ff" id="ff_audit" style="display:none">
      <label style="color:var(--bad)">Also type <strong>ERASE AUDIT</strong> to erase the audit trail</label>
      <input class="form-control" type="text" name="confirm_audit" autocomplete="off" placeholder="ERASE AUDIT"></div>
  </div>
  <script>(function(){var a=document.getElementById('g_audit'),f=document.getElementById('ff_audit');
    if(a&&f){var t=function(){f.style.display=a.checked?'':'none';};a.addEventListener('change',t);t();}})();</script>
  <div style="margin-top:12px">
    <button class="btn danger" type="submit"
      onclick="return confirm('Last check. The records you ticked will be permanently removed. Continue?')">Delete the ticked records</button>
    <a class="btn ghost" href="/settings">Cancel</a>
  </div>
</div>
</form>

<div class="panel">
  <h3 class="tab-sub">What is kept, whatever you tick</h3>
  <ul class="muted" style="margin:6px 0 0 18px">
    <li>Your own login, so you can never lock yourself out.</li>
    <li>Settings: company name, logo, colours, terminology, e-mail, working hours, security rules.</li>
    <li>The starter master lists — they are rebuilt the moment the page reloads, so the app is usable again immediately.</li>
    <li>An entry in the audit trail recording that this was done, by whom and when.</li>
  </ul>
</div>
