<?php
  $has = fn($p) => in_array($p, $current, true);
  $rec = fn($p) => in_array($p, $recommended, true);
  $allPerm = all_permissions();
  // short plain-English description of each role — role_blurb() (lib/whocan.php),
  // shared with "Who can do what" so both screens say the same thing.
  $roleBlurb = []; foreach (array_keys($roles) as $rk) $roleBlurb[$rk] = role_blurb($rk);
?>
<div class="master-head">
  <div><h1>Roles &amp; permissions</h1>
    <p class="sub" style="margin:2px 0 0">Pick a role, apply the recommended set in one click, then fine-tune. Applies to everyone in that role without a personal override. <strong>Master Admin</strong> always has everything.</p></div>
  <a class="btn secondary" href="/settings">← Settings</a>
</div>
<div class="note-inline" role="note" style="margin:0 0 12px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--info-bg,#eff6ff)">
  <b>This is the old layout.</b> The same switches, with people, branches and a plain-English preview on one page, are on
  <a href="/who-can?tab=roles&amp;role=<?= e($sel) ?>"><b>Who can do what</b></a>. Both save to the same place.
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
    <h3 class="tab-sub" style="margin:0">What they can do, module by module</h3>
    <label class="chk" style="margin-left:auto;font-weight:600"><input type="checkbox" id="perm_all_toggle"> Select <b style="margin:0 3px">everything</b> for this role</label>
  </div>
  <p class="muted" style="margin:2px 0 10px;font-size:12px">
    Tick a column heading to give that one thing everywhere. Tick a module name for full control of it.
    Stronger ticks fill in the weaker ones — <b>Delete</b> also ticks <b>Edit</b> and <b>View</b>, because
    nobody should be able to destroy something they cannot see.
  </p>
  <?php
    //  The grid itself lives in _perm_verb_grid.php, shared with the per-user
    //  editor. R-14 happened because two permission screens drifted apart; one
    //  implementation is the fix that holds.
    $pvHas   = $has;
    $pvRec   = $rec;
    $pvAllow = fn($k) => true;                                  // master admin edits roles
    $pvField = fn($k) => 'name="perms[' . e($k) . ']" value="1"';
    foreach ($moduleGroups as $grp => $keys):
      $pvModules = array_values(array_filter($keys, fn($k) => isset(ACCESS_MODULES[$k])));
      if (!$pvModules) continue;
      $pvGroup = $grp;
      include __DIR__ . '/_perm_verb_grid.php';
    endforeach;
  ?>

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

<p class="muted" style="margin-top:10px">Tip: <strong>Archive</strong> covers taking something out of use <em>and</em> putting it back, so nobody can undo only half of their own mistake. <strong>Approve</strong> is kept separate from Edit on purpose — the person who prepares a report should not be the person who signs it off. Individual exceptions per person are set under <a href="/users">Users</a>. See the whole reporting tree in <a href="/hierarchy">Org hierarchy</a>.</p>

<style>.checkgrid .chk.rec{border-left:3px solid var(--ok);padding-left:7px}</style>
<?php include __DIR__ . '/_perm_verb_grid_assets.php'; ?>

<script>
(function(){
  // The page's own toggles: "all of <group>" and "select everything". They set
  // the boxes, then hand back to the grid so the column and row headings
  // re-derive themselves rather than each toggle tracking the others.
  var master = document.getElementById('perm_all_toggle');
  var all = [].slice.call(document.querySelectorAll('.role-perm'));
  var groups = [].slice.call(document.querySelectorAll('.mgroup, .permgroup'));
  function sync(el, boxes){
    if(!el) return;
    var on = boxes.filter(function(b){return b.checked;}).length;
    el.checked = on === boxes.length && boxes.length > 0;
    el.indeterminate = on > 0 && on < boxes.length;
  }
  function refresh(){
    groups.forEach(function(g){ sync(g.querySelector('.grp-all'), [].slice.call(g.querySelectorAll('.role-perm'))); });
    sync(master, all);
    if (window.permGridRefresh) window.permGridRefresh();
  }
  groups.forEach(function(g){
    var t = g.querySelector('.grp-all');
    var boxes = [].slice.call(g.querySelectorAll('.role-perm'));
    if(t) t.addEventListener('change', function(){
      boxes.forEach(function(b){
        b.checked = t.checked;
        if (b.classList.contains('vbox') && window.permGridCascade) window.permGridCascade(b);
      });
      refresh();
    });
  });
  if(master) master.addEventListener('change', function(){
    all.forEach(function(b){ b.checked = master.checked; });
    refresh();
  });
  all.forEach(function(b){ b.addEventListener('change', refresh); });
  refresh();
})();
</script>