<?php
// Simple Sales — Dashboard (the owner's Excel DASHBOARD): pick a person and a
// period and every figure updates. The 8 key numbers are the Excel's.
require __DIR__ . '/_sx_ui.php';
$k = $dd['k'];
$periods = ['fy' => 'This financial year', 'quarter' => 'This quarter', 'month' => 'This month', '90' => 'Last 90 days'];
$wq = '&who=' . rawurlencode((string) $whoKey);
$bars = function (array $rows, $vKey, $labKey, $color, $fmt) {
    $max = max(array_map(fn($r) => (float) $r[$vKey], $rows) ?: [0]) ?: 1;
    echo '<div class="sxbars">';
    foreach ($rows as $r) echo '<div class="sxbar"><span>' . e($r[$labKey]) . '</span><i style="width:' . max(1, round(100 * (float) $r[$vKey] / $max)) . '%;background:' . e($color) . '"></i><em>' . e($fmt($r[$vKey])) . '</em></div>';
    echo '</div>';
};
?>
<div class="sx">
  <div class="sxhead"><h1>📊 Sales dashboard</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <nav class="sxchips" aria-label="Period"><?php foreach ($periods as $pk => $pl): ?><a class="sxchip<?= $p === $pk ? ' on' : '' ?>" href="/sales-dashboard?p=<?= e($pk . $wq) ?>"><?= e($pl) ?></a><?php endforeach; ?></nav>
      <?php sx_person_chips('sales-dashboard', $whoKey, $people, $seeAll, '&p=' . $p); ?>
    </div></div>
  <p class="sxwhy"><b>Why you're here:</b> the health of sales in 30 seconds. <?= e($dd['label']) ?>: <?= e(fdate($dd['from'])) ?> – <?= e(fdate($dd['to'])) ?>.</p>
  <div class="sxkpis">
    <?php sx_kpi('blue', '🧲 New leads', (string) $k['leads'], $k['deals_new'] . ' deals started', '/leads');
          sx_kpi('green', '🏆 Won value', sx_short($k['won_v']), $k['won_n'] . ' deals won', '/opportunities?status=WON');
          sx_kpi('teal', '🎯 Win rate', $k['rate'] === null ? '—' : $k['rate'] . '%', $k['won_n'] . ' won · ' . $k['lost_n'] . ' lost');
          sx_kpi('purple', '💵 Avg deal size', $k['avg'] === null ? '—' : sx_short($k['avg']), 'per won deal');
          sx_kpi('slate', '💼 Open pipeline', sx_short($k['open_v']), $k['open_n'] . ' open deals (today)', '/sales-board');
          sx_kpi('orange', '📈 Weighted forecast', sx_short($k['weighted']), 'pipeline × win %');
          sx_kpi('pink', '🎯 Target', $k['target'] > 0 ? sx_short($k['target']) : 'Not set', $k['target'] > 0 ? 'for this period' : 'set monthly targets');
          sx_kpi('brown', '✅ Achievement', $k['ach'] === null ? '—' : $k['ach'] . '%', 'won ÷ target'); ?>
  </div>

  <div class="sxgrid2" style="margin-top:12px">
    <div class="sxcard"><h2>💼 Pipeline by stage · open deals today</h2>
      <?php if (!$dd['stages']): ?><p class="sxempty">No open deals.</p><?php else: ?>
      <div class="tbl"><table class="sxt"><tr><th>Stage</th><th class="n">Deals</th><th class="n">Value</th><th class="n">Win %</th><th class="n">Weighted</th></tr>
        <?php foreach ($dd['stages'] as $s): ?><tr><td><?= e($s['name']) ?></td><td class="n"><?= (int) $s['n'] ?></td><td class="n"><?= e(sx_money($s['v'])) ?></td><td class="n"><?= (int) $s['p'] ?>%<?php $ep = $s['v'] > 0 ? (int) round($s['w'] * 100 / $s['v']) : 0; if ($s['v'] > 0 && $ep !== (int) $s['p']): ?><br><small class="muted" title="Some deals here have their own chance of winning, set on the deal">deals: <?= $ep ?>%</small><?php endif; ?></td><td class="n"><?= e(sx_money($s['w'])) ?></td></tr><?php endforeach; ?>
      </table></div>
      <p class="muted" style="font-size:12px;margin:6px 0 0">Weighted = value × chance of winning. A deal's own chance (set on the deal) is used where there is one; otherwise the stage's win %.</p><?php endif; ?></div>
    <div class="sxcard"><h2>📅 Won vs target, by month</h2>
      <?php $mx = max(array_map(fn($m) => max($m['won'], $m['target']), $dd['months']) ?: [0]) ?: 1; ?>
      <div class="sxbars">
        <?php foreach ($dd['months'] as $ym => $m): ?>
          <div class="sxbar"><span><?= e(date('M Y', strtotime($ym . '-01'))) ?></span>
            <i style="width:<?= max(1, round(100 * $m['won'] / $mx)) ?>%;background:var(--sx-green)" title="Won"></i>
            <em><?= e(sx_short($m['won'])) ?><?= $m['target'] > 0 ? ' / ' . e(sx_short($m['target'])) : '' ?></em></div>
        <?php endforeach; ?>
      </div>
      <p class="sxempty" style="margin-top:6px">Green = won. "/ figure" = that month's target, once targets are set.</p></div>
  </div>

  <div class="sxgrid2" style="margin-top:12px">
    <div class="sxcard"><h2>🧲 Lead source performance</h2>
      <?php if (!$dd['sources']): ?><p class="sxempty">No new leads in this period.</p><?php else: ?>
      <div class="tbl"><table class="sxt"><tr><th>Source</th><th class="n">Leads</th><th class="n">Deals</th><th class="n">Won</th><th class="n">Conversion</th></tr>
        <?php foreach ($dd['sources'] as $s): $cv = $s['leads'] ? round(100 * $s['deals'] / $s['leads']) : 0; ?>
          <tr><td><?= e($s['label']) ?></td><td class="n"><?= (int) $s['leads'] ?></td><td class="n"><?= (int) $s['deals'] ?></td><td class="n"><?= e(sx_short($s['won'])) ?></td><td class="n"><span class="sxp <?= $cv >= 40 ? 'p-ok' : ($cv >= 20 ? 'p-warn' : 'p-bad') ?>"><?= $cv ?>%</span></td></tr>
        <?php endforeach; ?>
      </table></div><?php endif; ?></div>
    <div class="sxcard"><h2>❌ Why we lose</h2>
      <?php if (!$dd['lost']): ?><p class="sxempty">No lost deals in this period.</p><?php else: $bars($dd['lost'], 'n', 'label', 'var(--sx-red)', fn($v) => (int) $v . ' deal' . ((int) $v === 1 ? '' : 's')); endif; ?>
      <?php if ($dd['ageing'] !== null): ?><h2 style="margin-top:14px">💰 Collections ageing (outstanding)</h2>
        <?php $ag = array_map(fn($a) => $a, array_values($dd['ageing'])); $bars($ag, 'v', 'label', 'var(--sx-brown)', fn($v) => sx_short($v)); endif; ?></div>
  </div>

  <?php if ($dd['team']): ?>
  <div class="sxcard"><h2>🏅 Team leaderboard</h2><div class="tbl"><table class="sxt">
    <tr><th>Salesperson</th><th class="n">Open pipeline</th><th class="n">Won</th><th class="n">Activities (30 days)</th><th class="n">Target</th><th class="n">Achievement</th></tr>
    <?php foreach ($dd['team'] as $r): $a = $r['target'] > 0 ? round(100 * $r['won'] / $r['target']) : null; ?>
      <tr><td><a href="/sales-dashboard?p=<?= e($p) ?>&who=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td><td class="n"><?= e(sx_short($r['open'])) ?></td><td class="n"><?= e(sx_short($r['won'])) ?></td><td class="n"><?= (int) $r['acts'] ?></td>
        <td class="n"><?= $r['target'] > 0 ? e(sx_short($r['target'])) : '—' ?></td><td class="n"><?= $a === null ? '—' : '<span class="sxp ' . ($a >= 90 ? 'p-ok' : ($a >= 60 ? 'p-warn' : 'p-bad')) . '">' . $a . '%</span>' ?></td></tr>
    <?php endforeach; ?>
  </table></div></div>
  <?php endif; ?>

  <div class="sxcard"><h2>🔝 Top 10 open deals</h2>
    <?php if (!$dd['top']): ?><p class="sxempty">No open deals.</p><?php else: ?>
    <div class="tbl"><table class="sxt"><tr><th>Deal</th><th>Customer</th><th>Stage</th><th class="n">Value</th><th class="n">Win %</th><th>Owner</th><th>Next step</th></tr>
      <?php foreach ($dd['top'] as $o): [$lab, $cls] = sx_due_label($o['next_action_on'] ?? ''); ?>
        <tr><td><a href="/opportunity?id=<?= (int) $o['id'] ?>"><?= e($o['ref']) ?></a> <?= e($o['name']) ?></td><td><?= e($o['client_name'] ?: $o['partner_name']) ?></td><td><?= e($o['stage_name']) ?></td>
          <td class="n"><?= e(sx_money($o['value'])) ?></td><td class="n"><?= (int) ($o['probability'] ?: $o['stage_prob']) ?>%</td><td><?= e($o['owner_name'] ?: '—') ?></td><td><span class="sxp <?= e($cls) ?>"><?= e($lab) ?></span></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?></div>
</div>
