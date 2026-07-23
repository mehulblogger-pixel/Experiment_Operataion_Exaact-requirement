<?php $has = fn($p) => in_array($p, $current, true); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Roles &amp; access</div>
<div class="master-head">
  <div><h1>Roles &amp; access</h1>
    <p class="sub" style="margin:2px 0 0">Choose a role, then tick which modules and features it can use. Applies to everyone in that role who doesn’t have a personal override. <strong>Master Admin</strong> always has everything.</p></div>
  <a class="btn secondary" href="/settings">← Settings</a>
</div>

<div class="chip-row" style="margin-bottom:14px">
  <?php foreach ($roles as $k => $lbl): ?>
    <a class="ct" href="/access?role=<?= e($k) ?>" style="<?= $sel===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" action="/access" class="panel">
  <input type="hidden" name="role" value="<?= e($sel) ?>">
  <h3 class="tab-sub" style="margin-top:0;">Modules — <?= e($roles[$sel]) ?></h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Module</th><th style="text-align:center">View</th><th style="text-align:center">Add / edit</th></tr></thead>
    <tbody>
    <?php foreach (ACCESS_MODULES as $k => $lbl): ?>
      <tr>
        <td><b><?= e($lbl) ?></b></td>
        <td style="text-align:center"><input type="checkbox" name="perms[mod.<?= e($k) ?>.view]" value="1" <?= $has("mod.$k.view")?'checked':'' ?>></td>
        <td style="text-align:center"><input type="checkbox" name="perms[mod.<?= e($k) ?>.edit]" value="1" <?= $has("mod.$k.edit")?'checked':'' ?>></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h3 class="tab-sub">Data &amp; feature permissions</h3>
  <p class="sub" style="margin:0 0 8px">Cross-cutting rights (what figures they may see, special actions).</p>
  <div class="checkgrid">
    <?php foreach (PERMISSIONS as $k => $lbl): ?>
      <label class="chk"><input type="checkbox" name="perms[<?= e($k) ?>]" value="1" <?= $has($k)?'checked':'' ?>> <?= e($lbl) ?></label>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save access for <?= e($roles[$sel]) ?></button>
    <a class="btn secondary" href="/access?role=<?= e($sel) ?>">Reset</a>
  </div>
</form>

<p class="muted" style="margin-top:10px">Tip: <strong>Add / edit</strong> also grants <strong>View</strong>. Leaving a role untouched here keeps its built-in defaults. Individual exceptions can be set per person under <a href="/users">Users</a>.</p>
