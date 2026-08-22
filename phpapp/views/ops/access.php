<?php
  $has = fn($p) => in_array($p, $current, true);
  $rec = fn($p) => in_array($p, $recommended, true);
  $allPerm = all_permissions();
  // short plain-English description of each role (who they are)
  $roleBlurb = [
    'BUSINESS_DIRECTOR' => 'Top management. Sees everything across all offices and ' . Tlp('sbu') . ' — all dashboards, revenue, salary and profitability — but does not run day-to-day operations.',
    'SBU_HEAD' => 'Heads one ' . Tl('sbu') . ' across all offices. Full dashboards & figures for their ' . Tl('sbu') . '; approves inspection reports for their people.',
    'BRANCH_MANAGER' => 'Runs one office end-to-end: calls, jobs, availability, report approvals, masters, and users in that office.',
    'BRANCH_APP_MANAGER' => 'Application manager in a branch — masters, overheads and branch users; limited operational view.',
    'OPERATION_MANAGER' => 'Under the branch manager — allocates & closes jobs, runs the availability board, approves reports.',
    'ASST_MANAGER' => 'Assists operations — creates calls, allocates jobs, manages availability. No money figures.',
    'COORDINATOR' => 'Day-to-day desk — schedules and closes jobs, availability board, sees revenue but not salary/profit.',
    'BUSINESS_DEV_MANAGER' => 'Sales — creates & sends ' . Tlp('quote') . ' and runs follow-ups across offices.',
    'KEY_ACCOUNTS_MANAGER' => 'Sales — owns key accounts; creates & sends ' . Tlp('quote') . ' and follow-ups.',
    'MARKETING_MANAGER' => 'Senior sales — everything BDM/KAM can do plus approve ' . Tlp('quote') . ' and manage templates.',
    'MARKETING_EXECUTIVE' => 'Junior sales — drafts ' . Tlp('quote') . ' and manages follow-ups (no send/approve).',
    'FINANCE' => 'Accounts — invoicing, credit reconciliation, register clients/contracts; sees all figures.',
    'INSPECTOR' => 'Field engineer — only their own My Jobs / My Voucher; no admin screens.',
  ];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Roles &amp; access</div>
<div class="master-head">
  <div><h1>Roles &amp; permissions</h1>
    <p class="sub" style="margin:2px 0 0">Pick a role, apply the recommended set in one click, then fine-tune. Applies to everyone in that role without a personal override. <strong>Master Admin</strong> always has everything.</p></div>
  <a class="btn secondary" href="/settings">← Settings</a>
</div>

<div class="chip-row" style="margin-bottom:14px">
  <?php foreach ($roles as $k => $lbl): ?>
    <a class="ct" href="/access?role=<?= e($k) ?>" style="<?= $sel===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel" style="margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start">
    <div style="flex:1;min-width:260px">
      <h3 style="margin:0 0 4px"><?= e($roles[$sel]) ?></h3>
      <p class="muted" style="margin:0"><?= e($roleBlurb[$sel] ?? '') ?></p>
      <p class="muted" style="margin:6px 0 0">Recommended data scope: <strong>Offices <?= e($scope['offices'] ?? 'OWN') ?></strong> · <strong><?= e(TP('sbu')) ?> <?= e($scope['sbus'] ?? 'OWN') ?></strong></p>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <form method="post" action="/access" onsubmit="return confirm('Apply the recommended permission set for <?= e($roles[$sel]) ?>? This replaces the current ticks below (you can still adjust before saving).')">
        <input type="hidden" name="role" value="<?= e($sel) ?>"><input type="hidden" name="_do" value="preset">
        <button class="btn" type="submit">✨ Apply recommended set</button>
      </form>
      <form method="post" action="/access" onsubmit="return confirm('Reset <?= e($roles[$sel]) ?> to the built-in default (removes your custom override)?')">
        <input type="hidden" name="role" value="<?= e($sel) ?>"><input type="hidden" name="_do" value="reset">
        <button class="btn secondary" type="submit">Reset to default</button>
      </form>
    </div>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:12px">Rows marked <span class="pill p-ok" style="padding:1px 6px">recommended</span> are in the suggested set for this role.</p>
</div>

<form method="post" action="/access" class="panel">
  <input type="hidden" name="role" value="<?= e($sel) ?>">

  <div style="display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-top:0">
    <h3 class="tab-sub" style="margin:0">Modules — what screens they can open</h3>
    <label class="chk" style="margin-left:auto;font-weight:600"><input type="checkbox" id="perm_all_toggle"> Select <b style="margin:0 3px">everything</b> for this role</label>
  </div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Module</th><th style="text-align:center">View</th><th style="text-align:center">Add / edit</th></tr></thead>
    <?php foreach ($moduleGroups as $grp => $keys):
      $mods = array_values(array_filter($keys, fn($k) => isset(ACCESS_MODULES[$k])));
      if (!$mods) continue; ?>
    <tbody class="mgroup">
      <tr><td colspan="3" style="background:var(--soft);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.03em">
        <span style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span><?= e($grp) ?></span>
          <label class="chk" style="margin-left:auto;font-weight:600;font-size:11px;text-transform:none;letter-spacing:0;color:var(--accent,#234e70)"><input type="checkbox" class="grp-all"> All of <?= e($grp) ?></label>
        </span>
      </td></tr>
      <?php foreach ($mods as $k): ?>
      <tr>
        <td><b><?= e(access_module_label($k)) ?></b> <?= $rec("mod.$k.view")?'<span class="pill p-ok" style="padding:0 5px;font-size:10px">recommended</span>':'' ?></td>
        <td style="text-align:center"><input type="checkbox" class="role-perm" name="perms[mod.<?= e($k) ?>.view]" value="1" <?= $has("mod.$k.view")?'checked':'' ?>></td>
        <td style="text-align:center"><input type="checkbox" class="role-perm" name="perms[mod.<?= e($k) ?>.edit]" value="1" <?= $has("mod.$k.edit")?'checked':'' ?>></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
  </table>
  </div>

  <h3 class="tab-sub">Data &amp; feature permissions — what they can do &amp; see</h3>
  <?php foreach ($permGroups as $grp => $keys):
    $pk = array_values(array_filter($keys, fn($k) => isset($allPerm[$k])));
    if (!$pk) continue; ?>
    <div class="permgroup">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:12px 0 4px">
        <span style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--brand)"><?= e($grp) ?></span>
        <?php if (count($pk) > 1): ?>
          <label class="chk" style="margin-left:auto;font-weight:600;font-size:11px;color:var(--accent,#234e70)"><input type="checkbox" class="grp-all"> All of <?= e($grp) ?></label>
        <?php endif; ?>
      </div>
      <div class="checkgrid">
        <?php foreach ($pk as $k): ?>
          <label class="chk<?= $rec($k)?' rec':'' ?>"><input type="checkbox" class="role-perm" name="perms[<?= e($k) ?>]" value="1" <?= $has($k)?'checked':'' ?>> <?= e($allPerm[$k]) ?><?= $rec($k)?' <span class="pill p-ok" style="padding:0 5px;font-size:10px">rec</span>':'' ?></label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save access for <?= e($roles[$sel]) ?></button>
    <a class="btn secondary" href="/access?role=<?= e($sel) ?>">Cancel changes</a>
  </div>
</form>

<p class="muted" style="margin-top:10px">Tip: <strong>Add / edit</strong> also grants <strong>View</strong>. Individual exceptions per person are set under <a href="/users">Users</a>. See the whole reporting tree in <a href="/hierarchy">Org hierarchy</a>.</p>

<style>.checkgrid .chk.rec{border-left:3px solid var(--ok);padding-left:7px}</style>

<script>
(function(){
  // "Select all" for each module/permission group, plus a master toggle for the
  // whole role. Client-side only — the same perms[...] checkboxes still post.
  var groups = [].slice.call(document.querySelectorAll('.mgroup, .permgroup'));
  var master = document.getElementById('perm_all_toggle');
  var all = [].slice.call(document.querySelectorAll('.role-perm'));
  function sync(el, boxes){
    if(!el) return;
    var on = boxes.filter(function(b){return b.checked;}).length;
    el.checked = on === boxes.length && boxes.length > 0;
    el.indeterminate = on > 0 && on < boxes.length;
  }
  function syncMaster(){ sync(master, all); }
  groups.forEach(function(g){
    var t = g.querySelector('.grp-all');
    var boxes = [].slice.call(g.querySelectorAll('.role-perm'));
    if(t) t.addEventListener('change', function(){ boxes.forEach(function(b){b.checked=t.checked;}); syncMaster(); });
    boxes.forEach(function(b){ b.addEventListener('change', function(){ sync(t, boxes); syncMaster(); }); });
    sync(t, boxes);
  });
  if(master) master.addEventListener('change', function(){
    all.forEach(function(b){ b.checked = master.checked; });
    groups.forEach(function(g){ sync(g.querySelector('.grp-all'), [].slice.call(g.querySelectorAll('.role-perm'))); });
  });
  syncMaster();
})();
</script>
