<?php
// Org chart — the reporting hierarchy from the position master. Data: $tree.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$tree = $tree ?? [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/positions">Positions</a> › Org chart</div>
<div class="master-head">
  <div><h1>Organisation chart</h1>
    <p class="sub" style="margin:2px 0 0">The reporting hierarchy built from the position master (who reports to whom).</p></div>
  <div class="row-actions"><a class="btn secondary" href="/positions-import">⬆ Import org chart</a> <a class="btn secondary" href="/positions">← Positions</a></div>
</div>

<div class="panel">
  <?php if (!$tree): ?>
    <p class="muted">No positions yet. Add positions and set “Reports to” to build the tree.</p>
  <?php else: ?>
    <?php foreach ($tree as $node): $p = $node['pos']; $d = (int)$node['depth'];
      $vac = max(0, (int)$p['sanctioned_headcount'] - (int)$p['occupied_headcount']); ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 6px;border-bottom:1px solid var(--line,#eef1f5);margin-left:<?= $d * 26 ?>px">
        <?php if ($d > 0): ?><span style="color:var(--muted,#94a3b8)">└─</span><?php endif; ?>
        <a href="/positions?id=<?= (int)$p['id'] ?>" style="font-weight:600;color:inherit;text-decoration:none"><?= $e($p['name']) ?></a>
        <?php if ($p['code']): ?><span class="muted" style="font-size:11.5px"><?= $e($p['code']) ?></span><?php endif; ?>
        <?php if ($p['grade']): ?><span class="pill p-info" style="font-size:10.5px"><?= $e($p['grade']) ?></span><?php endif; ?>
        <span class="muted" style="font-size:11.5px;margin-left:auto"><?= $e($p['department'] ?: '') ?> · <?= $vac ?> vac / <?= (int)$p['sanctioned_headcount'] ?> sanctioned<?= $p['hod_name'] ? ' · HOD ' . $e($p['hod_name']) : '' ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
