<?php
  // Connect K13 / backlog #2 — the ITI→MBA qualification & role taxonomy,
  // read-only. Sits beside the K0 industry taxonomy and generalises the pool
  // beyond inspection. Zero-Training: counts up top, everything else revealed on
  // tap (progressive disclosure), large tap targets, plain words. Changes no
  // existing screen or number.
  $summary = $summary ?? []; $families = $families ?? []; $levels = $levels ?? [];
  $trades = $trades ?? []; $certs = $certs ?? [];
  $chip = fn($t) => '<span class="chip">' . e($t) . '</span>';

  // Qualification levels grouped by band, in a sensible ladder order.
  $bandOrder = ['SCHOOL','ITI','APPRENTICE','VOCATIONAL','DIPLOMA','DEGREE','PG','DOCTORATE','PROFESSIONAL'];
  $byBand = [];
  foreach ($levels as $l) { $byBand[strtoupper((string)($l['band'] ?? ''))][] = $l; }
?>
<div class="crumbs"><a href="/">Home</a> › Qualification &amp; role taxonomy</div>
<div class="master-head">
  <div><h1>Qualification &amp; role taxonomy</h1>
    <p class="sub" style="margin:2px 0 0">One ladder for everyone in technical services — from <strong>ITI</strong> and
      apprentices to <strong>diploma</strong>, <strong>engineers</strong>, <strong>MBA</strong> and doctorate — mapped to job
      families, roles and professional certifications. Inspection is one vertical here, not the whole world.
      Anchored on India's NSQF. Read-only reference.
      <?php if (!empty($summary['version'])): ?>Version <?= e($summary['version']) ?>.<?php endif; ?></p></div>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">🧭</span><div class="k">Job families</div><div class="v"><?= (int)($summary['families'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">👷</span><div class="k">Roles</div><div class="v"><?= (int)($summary['roles'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🎓</span><div class="k">Qualification levels</div><div class="v"><?= (int)($summary['levels'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🔧</span><div class="k">ITI trades</div><div class="v"><?= (int)($summary['iti_trades'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🎖️</span><div class="k">Certifications</div><div class="v"><?= (int)($summary['certifications'] ?? 0) ?></div></div>
</div>

<style>
  .cx-sec{margin-top:12px}
  .cx-sec > summary{cursor:pointer;font-weight:600;font-size:16px;padding:14px;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;list-style:none}
  .cx-sec > summary::-webkit-details-marker{display:none}
  .cx-sec[open] > summary{border-bottom-left-radius:0;border-bottom-right-radius:0}
  .cx-body{border:1px solid var(--line,#e5e7eb);border-top:0;border-radius:0 0 12px 12px;padding:12px}
  .chip{display:inline-block;margin:3px;padding:7px 12px;border-radius:999px;background:rgba(0,128,128,.08);border:1px solid rgba(0,128,128,.2);font-size:14px;line-height:1.3}
  .cx-row{padding:10px 4px;border-bottom:1px solid var(--line,#eee)}
  .cx-row:last-child{border-bottom:0}
  .cx-code{display:inline-block;min-width:80px;font-weight:600;color:var(--muted,#555)}
  .cx-detail{color:var(--muted,#666);font-size:13px}
  .cx-aka{color:var(--muted,#888);font-size:13px}
  .cx-band{display:inline-block;padding:2px 9px;border-radius:999px;background:rgba(201,162,39,.12);border:1px solid rgba(201,162,39,.35);font-size:12px;font-weight:600;color:#8a6d12}
  .cx-nsqf{display:inline-block;min-width:58px;font-size:12px;color:var(--muted,#777);font-weight:600}
  .cx-role{padding:8px 4px 8px 14px;border-bottom:1px dashed var(--line,#eee)}
  .cx-role:last-child{border-bottom:0}
  .cx-min{display:inline-block;padding:1px 8px;border-radius:6px;background:rgba(0,128,128,.08);font-size:12px;color:#0f7d7d;font-weight:600}
  .ladder-band{margin:8px 0;padding:10px 12px;border:1px solid var(--line,#e5e7eb);border-radius:10px}
  .ladder-band h4{margin:0 0 6px;font-size:14px}
</style>

<!-- The qualification ladder — the spine of the whole taxonomy -->
<details class="cx-sec" open>
  <summary>🎓 The qualification ladder — school → ITI → diploma → degree → MBA → doctorate (<?= count($levels) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($bandOrder as $band): if (empty($byBand[$band])) continue; ?>
      <div class="ladder-band">
        <h4><span class="cx-band"><?= e(connect_qtx_band_label($band)) ?></span></h4>
        <?php foreach ($byBand[$band] as $l): ?>
          <div class="cx-row"><span class="cx-nsqf">NSQF <?= (int)$l['nsqf_level'] ?></span>
            <strong><?= e($l['name']) ?></strong>
            <?php if (!empty($l['detail'])): ?><div class="cx-detail"><?= e($l['detail']) ?></div><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</details>

<!-- Job families → roles -->
<details class="cx-sec">
  <summary>🧭 Job families &amp; roles (<?= count($families) ?> families)</summary>
  <div class="cx-body">
    <?php foreach ($families as $f): $roles = connect_qtx_roles_for_family($f['code'] ?? ''); ?>
      <div class="cx-row">
        <span class="cx-code"><?= e($f['code']) ?></span> <strong><?= e($f['name']) ?></strong>
        <?php if ((int)($f['nsqf_min'] ?? 0) > 0): ?><span class="cx-nsqf"> · NSQF <?= (int)$f['nsqf_min'] ?>–<?= (int)$f['nsqf_max'] ?></span><?php endif; ?>
        <?php if (!empty($f['detail'])): ?><div class="cx-detail"><?= e($f['detail']) ?></div><?php endif; ?>
        <?php foreach ($roles as $r): ?>
          <div class="cx-role">
            <strong><?= e($r['name']) ?></strong>
            <?php if (!empty($r['min_qual_band'])): ?><span class="cx-min"><?= e(connect_qtx_band_label($r['min_qual_band'])) ?>+</span><?php endif; ?>
            <?php if (!empty($r['aka'])): ?><div class="cx-aka"><?= e($r['aka']) ?></div><?php endif; ?>
            <?php if (!empty($r['typical_certs'])): ?><div class="cx-detail">Typical: <?= e($r['typical_certs']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</details>

<!-- ITI trades — the blue-collar end, made concrete -->
<details class="cx-sec">
  <summary>🔧 ITI trades (<?= count($trades) ?>)</summary>
  <div class="cx-body">
    <?php
      $byCat = [];
      foreach ($trades as $t) { $byCat[(string)($t['category'] ?? 'Other')][] = $t; }
      foreach ($byCat as $cat => $rows): ?>
        <div class="cx-row"><strong><?= e($cat) ?></strong>
          <div><?php foreach ($rows as $t) echo $chip($t['name'] . (!empty($t['duration']) ? ' · ' . $t['duration'] : '')); ?></div>
        </div>
    <?php endforeach; ?>
  </div>
</details>

<!-- Professional certifications — the full spectrum, not inspection-only -->
<details class="cx-sec">
  <summary>🎖️ Professional certifications (<?= count($certs) ?>)</summary>
  <div class="cx-body">
    <?php
      $byDomain = [];
      foreach ($certs as $c) { $byDomain[(string)($c['domain'] ?? 'Other')][] = $c; }
      foreach ($byDomain as $domain => $rows): ?>
        <div class="cx-row"><strong><?= e($domain) ?></strong>
          <?php foreach ($rows as $c): ?>
            <div class="cx-role"><strong><?= e($c['name']) ?></strong>
              <?php if (!empty($c['body'])): ?><span class="cx-aka"> — <?= e($c['body']) ?></span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
  </div>
</details>
