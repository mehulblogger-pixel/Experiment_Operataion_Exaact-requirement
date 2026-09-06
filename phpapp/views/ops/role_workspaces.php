<?php
// Role workspaces — per-role landing + curated launchpad. Data: $roles,$sel,$cfg,$catalog,$config.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$roles = $roles ?? []; $sel = $sel ?? ''; $cfg = $cfg ?? ['landing'=>'','tiles'=>[]];
$catalog = $catalog ?? []; $config = $config ?? [];
$selTiles = array_flip((array)($cfg['tiles'] ?? []));
// Group the catalogue by area for a tidy picker.
$byArea = [];
foreach ($catalog as $c) { $byArea[$c['area'] ?: 'Other'][] = $c; }
?>
<div class="crumbs"><a href="/">Home</a> › Role workspaces</div>
<div class="master-head">
  <div><h1>Role workspaces</h1>
    <p class="sub" style="margin:2px 0 0">Choose where each role lands after sign-in and the quick-access screens on their home. Everything here stays permission-safe — a role only ever sees screens it is already allowed to open.</p></div>
</div>

<div style="display:grid;grid-template-columns:250px 1fr;gap:18px;align-items:start">
  <div class="panel" style="padding:0">
    <div style="padding:12px 15px;border-bottom:1px solid var(--line,#e5e7eb);font-weight:700;font-size:14px">Roles</div>
    <?php foreach ($roles as $rk => $rl): $on = $rk === $sel; $has = !empty($config[$rk]); ?>
      <a href="/role-workspaces?role=<?= urlencode($rk) ?>" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 15px;border-bottom:1px solid var(--line,#eef1f5);color:inherit;text-decoration:none;<?= $on?'background:var(--brand,#1e40af);color:#fff':'' ?>">
        <span style="font-weight:600;font-size:13px"><?= $e($rl) ?></span>
        <?php if ($has): ?><span class="pill <?= $on?'':'p-ok' ?>" style="font-size:9.5px;<?= $on?'background:rgba(255,255,255,.25);color:#fff':'' ?>">set</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="post" class="panel">
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="role" value="<?= $e($sel) ?>">
    <h3 class="tab-sub" style="margin-top:0">Workspace for <?= $e($roles[$sel] ?? $sel) ?></h3>

    <div class="ff" style="max-width:420px">
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Lands on after sign-in</label>
      <select class="form-control" name="landing">
        <option value="">Dashboard (default)</option>
        <?php foreach ($catalog as $c): ?>
          <option value="<?= $e($c['url']) ?>" <?= $cfg['landing']===$c['url']?'selected':'' ?>><?= $e($c['icon']) ?> <?= $e($c['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="muted" style="font-size:11.5px;margin:5px 0 0">A user can still override this with their own “start page”.</p>
    </div>

    <h3 class="tab-sub" style="margin-top:20px">Quick-access launchpad</h3>
    <p class="muted" style="font-size:12.5px;margin:0 0 12px">Tick the screens this role uses every day. They appear as cards at the top of the home page.</p>

    <?php foreach ($byArea as $area => $items): ?>
      <div style="margin-bottom:14px">
        <div style="font-family:inherit;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted,#64748b);margin-bottom:7px"><?= $e($area) ?></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:7px">
          <?php foreach ($items as $c): $ck = isset($selTiles[$c['url']]); ?>
            <label style="display:flex;align-items:center;gap:9px;padding:8px 11px;border:1px solid var(--line,#e5e9f0);border-radius:10px;cursor:pointer;<?= $ck?'border-color:var(--brand,#1e40af);background:var(--soft,#eef2ff)':'' ?>">
              <input type="checkbox" name="tiles[]" value="<?= $e($c['url']) ?>" <?= $ck?'checked':'' ?> style="width:auto">
              <span style="font-size:15px"><?= $e($c['icon']) ?></span>
              <span style="font-size:12.5px;font-weight:500"><?= $e($c['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:12px"><button class="btn">Save workspace</button></div>
  </form>
</div>
