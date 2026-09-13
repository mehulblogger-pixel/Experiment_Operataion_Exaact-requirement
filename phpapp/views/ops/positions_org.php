<?php
// Org chart — reporting hierarchy from the position master, colour-banded by
// department, with a "by department" grouping beneath. Data: $tree.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$tree = $tree ?? [];
// A stable colour per department name (same department = same hue everywhere).
$deptColor = function ($d) {
    $d = strtolower(trim((string) $d));
    if ($d === '') return 'hsl(215,12%,62%)';
    return 'hsl(' . (crc32($d) % 360) . ',52%,52%)';
};
$groups = function_exists('dept_org_groups') ? dept_org_groups() : [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/positions">Positions</a> › Org chart</div>
<div class="master-head">
  <div><h1>Organisation chart</h1>
    <p class="sub" style="margin:2px 0 0">The reporting hierarchy built from the position master (who reports to whom), coloured by department.</p></div>
  <div class="row-actions"><a class="btn secondary" href="/departments">🏛️ Departments</a> <a class="btn secondary" href="/positions-import">⬆ Import organogram</a> <a class="btn secondary" href="/positions">← Positions</a></div>
</div>

<?php // Department legend. ?>
<?php if ($groups): ?>
<div class="panel" style="padding:11px 14px">
  <div style="display:flex;gap:8px 16px;flex-wrap:wrap;align-items:center">
    <span class="muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em">Departments</span>
    <?php foreach ($groups as $name => $g): ?>
      <span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px">
        <span style="width:11px;height:11px;border-radius:3px;background:<?= $e($deptColor($name)) ?>;display:inline-block"></span>
        <?= $e($name) ?> <span class="muted">(<?= (int) $g['occupied'] ?>/<?= (int) $g['sanctioned'] ?>)</span>
      </span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Reporting tree</h3>
  <?php if (!$tree): ?>
    <p class="muted">No positions yet. Add positions and set “Reports to” to build the tree, or import an organogram.</p>
  <?php else: ?>
    <?php foreach ($tree as $node): $p = $node['pos']; $d = (int) $node['depth'];
      $vac = max(0, (int) $p['sanctioned_headcount'] - (int) $p['occupied_headcount']);
      $dept = trim((string) ($p['department'] ?? '')); $col = $deptColor($dept); ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 6px 9px 10px;border-bottom:1px solid var(--line,#eef1f5);border-left:4px solid <?= $e($col) ?>;margin-left:<?= $d * 26 ?>px">
        <?php if ($d > 0): ?><span style="color:var(--muted,#94a3b8)">└─</span><?php endif; ?>
        <a href="/positions?id=<?= (int) $p['id'] ?>" style="font-weight:600;color:inherit;text-decoration:none"><?= $e($p['name']) ?></a>
        <?php if ($p['code']): ?><span class="muted" style="font-size:11.5px"><?= $e($p['code']) ?></span><?php endif; ?>
        <?php if ($p['grade']): ?><span class="pill p-info" style="font-size:10.5px"><?= $e($p['grade']) ?></span><?php endif; ?>
        <?php if ($dept !== ''): ?><span style="font-size:10.5px;padding:1px 7px;border-radius:12px;color:#fff;background:<?= $e($col) ?>"><?= $e($dept) ?></span><?php endif; ?>
        <span class="muted" style="font-size:11.5px;margin-left:auto"><?= $vac ?> vac / <?= (int) $p['sanctioned_headcount'] ?> sanctioned<?= $p['hod_name'] ? ' · HOD ' . $e($p['hod_name']) : '' ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($groups): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">By department</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;align-items:start">
    <?php foreach ($groups as $name => $g): $col = $deptColor($name); ?>
      <div style="border:1px solid var(--line,#e5e9f0);border-top:3px solid <?= $e($col) ?>;border-radius:10px;padding:11px 13px">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
          <b><?= $e($name) ?></b>
          <span class="muted" style="font-size:12px"><?= (int) $g['occupied'] ?>/<?= (int) $g['sanctioned'] ?> · <?= (int) $g['vacant'] ?> vacant</span>
        </div>
        <div style="margin-top:6px">
          <?php foreach ($g['positions'] as $p): $occ = (int) ($p['occupied_headcount'] ?? 0); $sanc = (int) ($p['sanctioned_headcount'] ?? 0); ?>
            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;padding:2px 0">
              <a href="/positions?id=<?= (int) $p['id'] ?>" style="color:inherit;text-decoration:none"><?= $e($p['name']) ?></a>
              <span class="muted"><?= $occ ?>/<?= $sanc ?: 1 ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
