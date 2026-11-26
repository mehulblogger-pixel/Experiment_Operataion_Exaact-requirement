<?php
// Sales dashboard (owner, 21 Nov 2026: Stage 1 of the Sales redesign). The same
// eight figures and six panels as before, from the same sx_dash_data(): only the
// look changes — clearer labels (won value is deals won, not invoices; collections
// are Accounts' outstanding invoices), a column chart for won vs target and a ring
// for the pipeline by stage, every number written out so nothing needs a hover.
// The old page is kept in tests/fixtures/pre_stage1/.
require __DIR__ . '/_sxw_ui.php';
$k = $dd['k'];
$periods = ['fy' => 'This financial year', 'quarter' => 'This quarter', 'month' => 'This month', '90' => 'Last 90 days'];
$wq = '&who=' . rawurlencode((string) $whoKey);
$bars = function (array $rows, $vKey, $labKey, $color, $fmt) {
    $max = max(array_map(fn($r) => (float) $r[$vKey], $rows) ?: [0]) ?: 1;
    echo '<div class="sxw-bars">';
    foreach ($rows as $r) echo '<div class="b"><span>' . e($r[$labKey]) . '</span><i style="width:' . max(1, round(100 * (float) $r[$vKey] / $max)) . '%;background:' . e($color) . '"></i><em>' . e($fmt($r[$vKey])) . '</em></div>';
    echo '</div>';
};
$per = '<nav class="sxw-seg" aria-label="Period">';
foreach ($periods as $pk => $pl) $per .= '<a href="/sales-dashboard?p=' . e($pk . $wq) . '"' . ($p === $pk ? ' class="on" aria-current="true"' : '') . '>' . e($pl) . '</a>';
$per .= '</nav>';
$ring = ['var(--info)', 'var(--brand)', 'var(--warn)', '#7a5bb5', 'var(--ok)', '#8a6a3a', '#c95b5b', 'var(--muted)'];
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-dashboard'); ?>
  <?php sxw_head('Sales dashboard', 'Why you\'re here: the health of sales in 30 seconds. ' . $dd['label'] . ': ' . fdate($dd['from']) . ' – ' . fdate($dd['to']) . '.', 'chart',
        '<div class="sxw-ctl">' . $per . sxw_person('sales-dashboard', $whoKey, $people, $seeAll, '&p=' . $p)
        . (function_exists('sx_branch_select') ? sx_branch_select('sales-dashboard') : '') . '</div>'); //  R-05 ?>

  <div class="sxw-kp" style="--n:4">
    <?php sxw_kpi('info', 'users', 'New leads', 'New leads', (string) $k['leads'], $k['deals_new'] . ' deals started', '/leads');
          sxw_kpi('ok', 'trophy', 'Won value', 'Won value', sx_short($k['won_v']), $k['won_n'] . ' deals won — deals marked won, not invoices', '/opportunities?status=WON');
          sxw_kpi('teal', 'target', 'Win rate', 'Win rate', $k['rate'] === null ? '—' : $k['rate'] . '%', $k['won_n'] . ' won · ' . $k['lost_n'] . ' lost');
          sxw_kpi('grey', 'brief', 'Avg deal size', 'Avg deal size', $k['avg'] === null ? '—' : sx_short($k['avg']), 'per won deal');
          sxw_kpi('info', 'flow', 'Open pipeline', 'Open pipeline', sx_short($k['open_v']), $k['open_n'] . ' open deals (today)', '/sales-board');
          sxw_kpi('grey', 'chart', 'Weighted forecast', 'Weighted forecast', sx_short($k['weighted']), 'pipeline × win %');
          sxw_kpi('grey', 'target', 'Target', 'Target', $k['target'] > 0 ? sx_short($k['target']) : 'Not set', $k['target'] > 0 ? 'for the whole ' . preg_replace('/^this /', '', strtolower((string) ($dd['label'] ?? 'period'))) : 'set monthly targets');
          sxw_kpi('warn', 'task', 'Achievement', 'Achievement', $k['ach'] === null ? '—' : $k['ach'] . '%', 'won ÷ target'); ?>
  </div>

  <div class="sxw-g2">
    <?php rec_card_open('Won vs target, by month', 'cal'); ?>
      <?php $mx = max(array_map(fn($m) => max($m['won'], $m['target']), $dd['months']) ?: [0]) ?: 1; ?>
      <div class="sxw-cc" role="img" aria-label="Won against target for each month">
        <?php foreach ($dd['months'] as $ym => $m): ?>
          <div title="<?= e(date('M Y', strtotime($ym . '-01')) . ': won ' . sx_short($m['won']) . ($m['target'] > 0 ? ' / target ' . sx_short($m['target']) : '')) ?>">
            <i style="height:<?= max(1, round(100 * $m['won'] / $mx)) ?>%"></i><?php if ($m['target'] > 0): ?><u style="bottom:<?= round(100 * $m['target'] / $mx, 1) ?>%"></u><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
      <div class="sxw-cx"><?php foreach ($dd['months'] as $ym => $m): ?><span><?= e(date('M', strtotime($ym . '-01'))) ?></span><?php endforeach; ?></div>
      <div class="sxw-cv">
        <?php foreach ($dd['months'] as $ym => $m): ?><div><span><?= e(date('M Y', strtotime($ym . '-01'))) ?></span><b><?= e(sx_short($m['won'])) ?><?= $m['target'] > 0 ? ' / ' . e(sx_short($m['target'])) : '' ?></b></div><?php endforeach; ?>
      </div>
      <p class="rec-note">Green = won. Black mark and "/ figure" = that month's target, once targets are set.</p>
    <?php rec_card_close(); ?>
    <?php rec_card_open('Pipeline by stage · open deals today', 'flow'); ?>
      <?php if (!$dd['stages']): ?><p class="sxw-empty">No open deals.</p><?php else:
            $tv = array_sum(array_map(fn($s) => (float) $s['v'], $dd['stages'])); $a = 0; $seg = [];
            foreach ($dd['stages'] as $i => $s) { $st = $a; $a += $tv > 0 ? 360 * (float) $s['v'] / $tv : 0; $seg[] = $ring[$i % count($ring)] . ' ' . round($st, 2) . 'deg ' . round($a, 2) . 'deg'; } ?>
        <div class="sxw-ring">
          <div class="d" style="background:<?= $tv > 0 ? 'conic-gradient(' . e(implode(',', $seg)) . ')' : 'var(--line)' ?>" role="img" aria-label="Open pipeline by stage"><div><b><?= e(sx_short($tv)) ?></b><span><?= array_sum(array_map(fn($s) => (int) $s['n'], $dd['stages'])) ?> deals</span></div></div>
          <div class="sxw-lg">
            <?php foreach ($dd['stages'] as $i => $s): $ep = $s['v'] > 0 ? (int) round($s['w'] * 100 / $s['v']) : 0; ?>
              <div class="r"><i style="background:<?= e($ring[$i % count($ring)]) ?>"></i><span><?= e($s['name']) ?> · <?= (int) $s['n'] ?></span><b><?= e(sx_money($s['v'])) ?></b>
                <span class="m"><?= (int) $s['p'] ?>% win<?= ($s['v'] > 0 && $ep !== (int) $s['p']) ? ' (deals: ' . $ep . '%)' : '' ?> · weighted <?= e(sx_money($s['w'])) ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
        <p class="rec-note">Weighted = value × chance of winning. A deal's own chance (set on the deal) is used where there is one; otherwise the stage's win %.</p>
      <?php endif; ?>
    <?php rec_card_close(); ?>
  </div>

  <div class="sxw-g2">
    <?php rec_card_open('Lead source performance', 'trend'); ?>
      <?php if (!$dd['sources']): ?><p class="sxw-empty">No new leads in this period.</p><?php else: ?>
      <div class="rec-scroll"><table class="sxw-fu"><thead><tr><th>Source</th><th class="r">Leads</th><th class="r">Deals</th><th class="r">Won</th><th class="r">Conversion</th></tr></thead><tbody>
        <?php foreach ($dd['sources'] as $s): $cv = $s['leads'] ? round(100 * $s['deals'] / $s['leads']) : 0; ?>
          <tr><td><?= e($s['label']) ?></td><td class="r"><span class="k">Leads</span><?= (int) $s['leads'] ?></td><td class="r"><span class="k">Deals</span><?= (int) $s['deals'] ?></td><td class="r"><span class="k">Won</span><?= e(sx_short($s['won'])) ?></td><td class="r"><span class="k">Conversion</span><span class="sxw-due <?= $cv >= 40 ? 'p-ok' : ($cv >= 20 ? 'p-warn' : 'p-bad') ?>"><?= $cv ?>%</span></td></tr>
        <?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    <?php rec_card_close(); ?>
    <?php rec_card_open('Why we lose', 'alert'); ?>
      <?php if (!$dd['lost']): ?><p class="sxw-empty">No lost deals in this period.</p><?php else: $bars($dd['lost'], 'n', 'label', 'var(--bad)', fn($v) => (int) $v . ' deal' . ((int) $v === 1 ? '' : 's')); endif; ?>
    <?php rec_card_close(); ?>
  </div>

  <div class="sxw-g2">
    <?php if ($dd['ageing'] !== null): rec_card_open('Collections ageing (outstanding)', 'rupee'); ?>
      <?php $ag = array_map(fn($a) => $a, array_values($dd['ageing'])); $bars($ag, 'v', 'label', '#8a6a3a', fn($v) => sx_short($v)); ?>
      <p class="rec-note">Accounts' outstanding invoices — shown only to people who may see receivables.</p>
    <?php rec_card_close(); endif; ?>
    <?php if ($dd['team']): rec_card_open('Team leaderboard', 'trophy'); ?>
      <div class="rec-scroll"><table class="sxw-fu"><thead><tr><th>Salesperson</th><th class="r">Open pipeline</th><th class="r">Won</th><th class="r">Activities (30 days)</th><th class="r">Target</th><th class="r">Achievement</th></tr></thead><tbody>
      <?php foreach ($dd['team'] as $r): $a = $r['target'] > 0 ? round(100 * $r['won'] / $r['target']) : null; ?>
        <tr><td><a href="/sales-dashboard?p=<?= e($p) ?>&who=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td><td class="r"><span class="k">Open pipeline</span><?= e(sx_short($r['open'])) ?></td><td class="r"><span class="k">Won</span><?= e(sx_short($r['won'])) ?></td><td class="r"><span class="k">Activities (30 days)</span><?= (int) $r['acts'] ?></td>
          <td class="r"><span class="k">Target</span><?= $r['target'] > 0 ? e(sx_short($r['target'])) : '—' ?></td><td class="r"><span class="k">Achievement</span><?= $a === null ? '—' : '<span class="sxw-due ' . ($a >= 90 ? 'p-ok' : ($a >= 60 ? 'p-warn' : 'p-bad')) . '">' . $a . '%</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php rec_card_close(); endif; ?>
  </div>

  <?php rec_card_open('Top 10 open deals', 'list'); ?>
    <?php if (!$dd['top']): ?><p class="sxw-empty">No open deals.</p><?php else: ?>
    <div class="rec-scroll"><table class="sxw-fu"><thead><tr><th>Deal</th><th>Customer</th><th>Stage</th><th class="r">Value</th><th class="r">Win %</th><th>Owner</th><th>Next step</th></tr></thead><tbody>
      <?php foreach ($dd['top'] as $o): ?>
        <tr><td><a href="/opportunity?id=<?= (int) $o['id'] ?>"><?= e($o['ref']) ?></a> <?= e($o['name']) ?></td><td><span class="k">Customer</span><?= e($o['client_name'] ?: $o['partner_name']) ?></td><td><span class="k">Stage</span><?= e($o['stage_name']) ?></td>
          <td class="r"><span class="k">Value</span><?= e(sx_money($o['value'])) ?></td><td class="r"><span class="k">Win %</span><?= (int) ($o['probability'] ?: $o['stage_prob']) ?>%</td><td><span class="k">Owner</span><?= e($o['owner_name'] ?: '—') ?></td><td><span class="k">Next step</span><?= sxw_due($o['next_action_on'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  <?php rec_card_close(); ?>
</div>
<?php sxw_bottom('/sales-dashboard'); ?>
