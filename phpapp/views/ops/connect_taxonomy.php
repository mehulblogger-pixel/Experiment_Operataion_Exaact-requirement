<?php
  // Connect K0 — the marketplace's industry taxonomy, read-only. Adopted from the
  // MGH Inspect Connect blueprint (Part B). Changes no existing screen or number.
  // Zero-Training: counts up top, everything else revealed on tap (progressive
  // disclosure), large tap targets, plain words.
  $summary = $summary ?? []; $sectors = $sectors ?? []; $groups = $groups ?? [];
  $materials = $materials ?? []; $disc = $disc ?? []; $stages = $stages ?? [];
  $standards = $standards ?? []; $certs = $certs ?? [];
  $chip = fn($t) => '<span class="chip">' . e($t) . '</span>';
?>
<div class="crumbs"><a href="/">Home</a> › Industry taxonomy</div>
<div class="master-head">
  <div><h1>Industry taxonomy</h1>
    <p class="sub" style="margin:2px 0 0">The shared vocabulary the manpower marketplace matches on —
      sector, equipment, material, discipline, stage, standard, certification. Read-only reference.
      <?php if (!empty($summary['version'])): ?>Version <?= e($summary['version']) ?>.<?php endif; ?></p></div>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">🏭</span><div class="k">Sectors</div><div class="v"><?= (int)($summary['sectors'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🛠️</span><div class="k">Equipment</div><div class="v"><?= (int)($summary['equipment_groups'] ?? 0) ?></div><div class="d"><?= (int)($summary['equipment_types'] ?? 0) ?> types</div></div>
  <div class="kpi"><span class="kic">🧱</span><div class="k">Materials</div><div class="v"><?= (int)($summary['materials'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🔎</span><div class="k">Disciplines</div><div class="v"><?= (int)($summary['disciplines'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">📋</span><div class="k">Stages</div><div class="v"><?= (int)($summary['stages'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">📐</span><div class="k">Standards</div><div class="v"><?= (int)($summary['standards'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🎖️</span><div class="k">Certifications</div><div class="v"><?= (int)($summary['certifications'] ?? 0) ?></div></div>
</div>

<style>
  .cx-sec{margin-top:12px}
  .cx-sec > summary{cursor:pointer;font-weight:600;font-size:16px;padding:14px;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;list-style:none}
  .cx-sec > summary::-webkit-details-marker{display:none}
  .cx-sec[open] > summary{border-bottom-left-radius:0;border-bottom-right-radius:0}
  .cx-body{border:1px solid var(--line,#e5e7eb);border-top:0;border-radius:0 0 12px 12px;padding:12px}
  .chip{display:inline-block;margin:3px;padding:7px 12px;border-radius:999px;background:rgba(0,128,128,.08);border:1px solid rgba(0,128,128,.2);font-size:14px;line-height:1.3}
  .cx-row{padding:8px 4px;border-bottom:1px solid var(--line,#eee)}
  .cx-row:last-child{border-bottom:0}
  .cx-code{display:inline-block;min-width:66px;font-weight:600;color:var(--muted,#555)}
  .cx-detail{color:var(--muted,#666);font-size:13px}
</style>

<details class="cx-sec" open>
  <summary>🏭 Sectors (<?= count($sectors) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($sectors as $r): ?>
      <div class="cx-row"><span class="cx-code"><?= e($r['code']) ?></span> <strong><?= e($r['name']) ?></strong>
        <?php if (!empty($r['detail'])): ?><div class="cx-detail"><?= e($r['detail']) ?></div><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
</details>

<details class="cx-sec">
  <summary>🛠️ Equipment groups &amp; types (<?= count($groups) ?> groups)</summary>
  <div class="cx-body">
    <?php foreach ($groups as $g): $types = connect_tx_rows('cx_equipment_types', 'sort_order, id'); ?>
      <div class="cx-row"><span class="cx-code"><?= e($g['code']) ?></span> <strong><?= e($g['name']) ?></strong>
        <div><?php foreach ($types as $t) if (($t['group_code'] ?? '') === ($g['code'] ?? '')) echo $chip($t['name']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</details>

<details class="cx-sec">
  <summary>🧱 Materials (<?= count($materials) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($materials as $r): ?>
      <div class="cx-row"><span class="cx-code"><?= e($r['code']) ?></span> <strong><?= e($r['name']) ?></strong>
        <?php if (!empty($r['grades'])): ?><span class="cx-detail"> — <?= e($r['grades']) ?></span><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
</details>

<details class="cx-sec">
  <summary>🔎 Disciplines (<?= count($disc) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($disc as $r): ?>
      <div class="cx-row"><span class="cx-code"><?= e($r['code']) ?></span> <strong><?= e($r['name']) ?></strong>
        <?php if (!empty($r['methods'])): ?><div><?php foreach (explode(', ', $r['methods']) as $mth) echo $chip($mth); ?></div><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
</details>

<details class="cx-sec">
  <summary>📋 Inspection stages (<?= count($stages) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($stages as $r): ?>
      <div class="cx-row"><span class="cx-code"><?= (int)$r['seq'] ?>. <?= e($r['code']) ?></span> <?= e($r['name']) ?></div>
    <?php endforeach; ?>
  </div>
</details>

<details class="cx-sec">
  <summary>📐 Standard families (<?= count($standards) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($standards as $r): ?>
      <div class="cx-row"><span class="cx-code"><?= e($r['family']) ?></span>
        <div><?php foreach (explode(', ', (string)$r['codes']) as $c) if ($c !== '') echo $chip($c); ?></div></div>
    <?php endforeach; ?>
  </div>
</details>

<details class="cx-sec">
  <summary>🎖️ Certifications (<?= count($certs) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($certs as $r): ?>
      <div class="cx-row"><span class="cx-code"><?= e($r['code']) ?></span> <strong><?= e($r['name']) ?></strong>
        <div class="cx-detail"><?= e($r['issuer']) ?><?php if (!empty($r['verify_route'])): ?> · verify: <?= e(str_replace('_',' ',$r['verify_route'])) ?><?php endif; ?><?php if (!empty($r['register'])): ?> · <?= e($r['register']) ?><?php endif; ?></div></div>
    <?php endforeach; ?>
  </div>
</details>
