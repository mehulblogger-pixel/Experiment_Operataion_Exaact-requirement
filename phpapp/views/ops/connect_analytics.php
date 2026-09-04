<?php
  // Connect K19 / backlog #8 — labour-market analytics, read-only. Supply vs
  // demand, the fill funnel, time-to-award, rate benchmarks, pool growth and the
  // verification mix, over the marketplace's own cx_* tables. Zero-Training: one
  // insight up top, then scannable tiles and bars; plain words, semantic colour.
  $head = $head ?? []; $demand = $demand ?? []; $funnel = $funnel ?? []; $rates = $rates ?? [];
  $growth = $growth ?? []; $verif = $verif ?? []; $locs = $locs ?? []; $insight = $insight ?? '';
  $inr = fn($n) => '₹' . number_format((int)$n);
  $dsMax = 1; foreach ($demand as $d) { $dsMax = max($dsMax, (int)$d['demand'], (int)$d['supply']); }
  $fMax = 1; foreach ($funnel as $f) { $fMax = max($fMax, (int)$f['value']); }
  $gMax = 1; foreach ($growth as $g) { $gMax = max($gMax, (int)$g['value']); }
  $locMax = 1; foreach ($locs as $l) { $locMax = max($locMax, (int)$l['value']); }
  $vMax = 0; foreach ($verif as $v) { $vMax += (int)$v['value']; } $vMax = max(1, $vMax);
?>
<div class="crumbs"><a href="/">Home</a> › Market analytics</div>
<div class="master-head">
  <div><h1>Labour-market analytics</h1>
    <p class="sub" style="margin:2px 0 0">Where demand meets supply across the marketplace — the intelligence a talent network runs on.
      Computed live from your own data. Read-only.</p></div>
</div>

<style>
  .an-num{font-variant-numeric:tabular-nums}
  .an-kpi{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0}
  .an-kpi .k{flex:1 1 130px;padding:13px 15px;border:1px solid var(--line,#e5e7eb);border-radius:13px;background:var(--card,#fff)}
  .an-kpi .lab{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .an-kpi .v{font-size:26px;font-weight:700;margin-top:2px}
  .an-kpi .d{font-size:12px;color:var(--muted,#888)}
  .an-insight{margin:4px 0 14px;padding:13px 16px;border:1px solid rgba(15,125,125,.3);border-left:4px solid #0f7d7d;border-radius:10px;background:rgba(15,125,125,.05);font-weight:600}
  .an-card{border:1px solid var(--line,#e5e7eb);border-radius:14px;background:var(--card,#fff);padding:16px;margin-top:14px}
  .an-card h2{margin:0 0 10px;font-size:17px}
  .an-tbl{width:100%;border-collapse:collapse;font-size:14px}
  .an-tbl th,.an-tbl td{text-align:left;padding:8px;border-bottom:1px solid var(--line,#eee);vertical-align:middle}
  .an-tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .an-bar{height:9px;border-radius:5px;min-width:2px}
  .an-bar.dem{background:#0f7d7d}
  .an-bar.sup{background:#c9a227}
  .an-bars{display:flex;flex-direction:column;gap:3px;min-width:120px}
  .an-gap{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700}
  .an-gap.short{background:rgba(185,28,28,.12);color:#b91c1c}
  .an-gap.ok{background:rgba(16,122,74,.14);color:#0b7a4a}
  .an-legend{font-size:12px;color:var(--muted,#777);margin-bottom:8px}
  .an-legend .sw{display:inline-block;width:10px;height:10px;border-radius:2px;vertical-align:middle;margin:0 3px 0 10px}
  .an-funnel{display:flex;flex-direction:column;gap:8px}
  .an-frow{display:grid;grid-template-columns:150px 1fr 54px;align-items:center;gap:10px}
  .an-fbar{height:26px;border-radius:6px;background:linear-gradient(90deg,#0f7d7d,#1aa)}
  .an-grow{display:flex;align-items:flex-end;gap:10px;height:110px;padding-top:8px}
  .an-gcol{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;justify-content:flex-end}
  .an-gbar{width:60%;max-width:34px;background:#0f7d7d;border-radius:4px 4px 0 0;min-height:2px}
  .an-gcap{font-size:12px;color:var(--muted,#777)}
  .an-mix{display:flex;height:16px;border-radius:8px;overflow:hidden;border:1px solid var(--line,#eee)}
  .an-mixseg{height:100%}
  .an-two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:760px){.an-two{grid-template-columns:1fr}.an-frow{grid-template-columns:110px 1fr 44px}}
  .an-range{position:relative;height:9px;background:var(--line,#eef2f2);border-radius:5px;min-width:120px}
  .an-rfill{position:absolute;height:100%;border-radius:5px;background:rgba(201,162,39,.5)}
</style>

<?php if ($insight): ?><div class="an-insight">💡 <?= e($insight) ?></div><?php endif; ?>

<div class="an-kpi">
  <div class="k"><div class="lab">Fill rate</div><div class="v an-num"><?= (int)($head['fill_rate'] ?? 0) ?>%</div><div class="d"><?= (int)($head['awarded'] ?? 0) ?> of <?= (int)($head['posted'] ?? 0) ?> posted</div></div>
  <div class="k"><div class="lab">Open now</div><div class="v an-num"><?= (int)($head['open'] ?? 0) ?></div><div class="d"><?= (int)($head['applications'] ?? 0) ?> applications</div></div>
  <div class="k"><div class="lab">Avg days to award</div><div class="v an-num"><?= (float)($head['avg_days_to_award'] ?? 0) ?></div></div>
  <div class="k"><div class="lab">Talent supply</div><div class="v an-num"><?= (int)($head['supply'] ?? 0) ?></div><div class="d"><?= (int)($head['pool'] ?? 0) ?> pool · <?= (int)($head['bench'] ?? 0) ?> bench</div></div>
</div>

<!-- Supply vs demand -->
<div class="an-card">
  <h2>Supply vs demand by discipline</h2>
  <div class="an-legend"><span class="sw" style="background:#0f7d7d"></span>Demand (open)<span class="sw" style="background:#c9a227"></span>Available supply</div>
  <?php if (!$demand): ?>
    <p class="cxmeta" style="margin:0;color:var(--muted,#777)">No demand or supply recorded yet.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
    <table class="an-tbl">
      <thead><tr><th>Discipline</th><th>Demand vs supply</th><th class="an-num">Open</th><th class="an-num">Avail.</th><th>Gap</th></tr></thead>
      <tbody>
        <?php foreach ($demand as $d): $short = $d['gap'] < 0 && $d['demand'] > 0; ?>
          <tr>
            <td><strong><?= e($d['name']) ?></strong></td>
            <td><div class="an-bars">
              <div class="an-bar dem" style="width:<?= max(2, (int)round($d['demand'] / $dsMax * 100)) ?>%"></div>
              <div class="an-bar sup" style="width:<?= max(2, (int)round($d['supply'] / $dsMax * 100)) ?>%"></div>
            </div></td>
            <td class="an-num"><?= (int)$d['demand'] ?></td>
            <td class="an-num"><?= (int)$d['supply'] ?></td>
            <td><span class="an-gap <?= $short ? 'short' : 'ok' ?>"><?= $d['gap'] > 0 ? '+' : '' ?><?= (int)$d['gap'] ?><?= $short ? ' short' : '' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="an-two">
  <!-- Fill funnel -->
  <div class="an-card">
    <h2>Hiring funnel</h2>
    <div class="an-funnel">
      <?php foreach ($funnel as $f): ?>
        <div class="an-frow">
          <div style="font-size:14px"><?= e($f['label']) ?></div>
          <div class="an-fbar" style="width:<?= max(3, (int)round($f['value'] / $fMax * 100)) ?>%"></div>
          <div class="an-num" style="text-align:right;font-weight:700"><?= (int)$f['value'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Pool growth -->
  <div class="an-card">
    <h2>Pool growth (new professionals / month)</h2>
    <div class="an-grow">
      <?php foreach ($growth as $g): ?>
        <div class="an-gcol">
          <div class="an-num" style="font-size:12px;font-weight:700"><?= (int)$g['value'] ?></div>
          <div class="an-gbar" style="height:<?= (int)round($g['value'] / $gMax * 82) ?>px"></div>
          <div class="an-gcap"><?= e($g['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Rate benchmarks -->
<div class="an-card">
  <h2>Rate benchmarks by discipline</h2>
  <?php if (!$rates): ?>
    <p class="cxmeta" style="margin:0;color:var(--muted,#777)">No rates recorded yet — add rate ranges to requirements or day-rates to profiles.</p>
  <?php else:
    $rMax = 1; foreach ($rates as $r) { $rMax = max($rMax, (int)$r['ask_max'], (int)$r['pool_max']); } ?>
    <div style="overflow-x:auto">
    <table class="an-tbl">
      <thead><tr><th>Discipline</th><th>Requirement ask (₹/day)</th><th class="an-num">Pool avg</th><th>Pool range</th></tr></thead>
      <tbody>
        <?php foreach ($rates as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td><?php if ($r['ask_n']): ?><span class="an-num"><?= $inr($r['ask_min']) ?> – <?= $inr($r['ask_max']) ?></span> <span class="cxmeta" style="color:var(--muted,#999)">(<?= (int)$r['ask_n'] ?>)</span><?php else: ?><span class="cxmeta" style="color:var(--muted,#aaa)">—</span><?php endif; ?></td>
            <td class="an-num"><?= $r['pool_n'] ? $inr($r['pool_avg']) : '—' ?></td>
            <td>
              <?php if ($r['pool_n']): $lo = (int)round($r['pool_min'] / $rMax * 100); $wd = max(3, (int)round(($r['pool_max'] - $r['pool_min']) / $rMax * 100)); ?>
                <div class="an-range"><div class="an-rfill" style="left:<?= $lo ?>%;width:<?= $wd ?>%"></div></div>
                <span class="cxmeta" style="font-size:12px"><?= $inr($r['pool_min']) ?>–<?= $inr($r['pool_max']) ?> · <?= (int)$r['pool_n'] ?> pro</span>
              <?php else: ?><span class="cxmeta" style="color:var(--muted,#aaa)">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="an-two">
  <!-- Verification mix -->
  <div class="an-card">
    <h2>Trust mix of the pool</h2>
    <?php $vcol = ['registered' => '#9aa', 'id_verified' => '#c9a227', 'credential_verified' => '#1a9', 'proven' => '#0b7a4a']; ?>
    <div class="an-mix">
      <?php foreach ($verif as $v): if ((int)$v['value'] === 0) continue; ?>
        <div class="an-mixseg" title="<?= e($v['label']) ?>: <?= (int)$v['value'] ?>" style="width:<?= (int)round($v['value'] / $vMax * 100) ?>%;background:<?= $vcol[$v['key']] ?? '#9aa' ?>"></div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:10px;display:flex;flex-direction:column;gap:5px">
      <?php foreach ($verif as $v): ?>
        <div style="display:flex;justify-content:space-between;font-size:13px">
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= $vcol[$v['key']] ?? '#9aa' ?>;vertical-align:middle;margin-right:6px"></span><?= e($v['label']) ?></span>
          <strong class="an-num"><?= (int)$v['value'] ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Demand by location -->
  <div class="an-card">
    <h2>Where the work is</h2>
    <?php if (!$locs): ?>
      <p class="cxmeta" style="margin:0;color:var(--muted,#777)">No locations recorded on requirements yet.</p>
    <?php else: foreach ($locs as $l): ?>
      <div style="display:grid;grid-template-columns:130px 1fr 34px;align-items:center;gap:10px;margin:6px 0">
        <div style="font-size:14px"><?= e($l['location']) ?></div>
        <div class="an-bar dem" style="width:<?= max(3, (int)round($l['value'] / $locMax * 100)) ?>%;height:12px"></div>
        <div class="an-num" style="text-align:right;font-weight:700"><?= (int)$l['value'] ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
