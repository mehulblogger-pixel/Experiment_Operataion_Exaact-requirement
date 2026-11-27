<?php
// Pipeline board (owner, 21 Nov 2026: Stage 1 of the Sales redesign). The same
// cards as before — the open leads (first 25), then the deal pipeline's own OPEN
// stages (opp_board) — now with a Leads / Deals switch, a box per stage with its
// count · value · win %, and on a phone one stage at a time. Won and lost stay
// on the Deals screen; a card still opens its record, where the stage is
// changed. The old page is kept in tests/fixtures/pre_stage1/.
require __DIR__ . '/_sxw_ui.php';
$who2 = (int) $who;
$q = sx_who_carry($whoKey) ? '&who=' . rawurlencode((string) $whoKey) : '';
$cardTone = function ($r) { $st = sx_due_state($r['next_action_on'] ?? ''); return $st === 'overdue' || $st === 'none' ? 'r' : ($st === 'today' ? 'o' : ($st === 'week' ? 'y' : '')); };
$cardNote = function ($r) { $st = sx_due_state($r['next_action_on'] ?? ''); return $st === 'none' ? 'no next step' : ($st === 'overdue' ? 'overdue' : ($st === 'today' ? 'today' : '')); };
//  The deal columns exactly as before: the pipeline's OPEN stages, open deals, this person's when one is chosen.
$cols = [];
foreach (($board['columns'] ?? []) as $col) { $s = $col['stage']; if (($s['kind'] ?? 'OPEN') !== 'OPEN') continue;
    $rows = array_values(array_filter($col['rows'], fn($o) => $o['status'] === 'OPEN' && (!$who2 || (int) $o['owner_user_id'] === $who2)));
    $cols[] = ['s' => $s, 'rows' => $rows, 'v' => array_sum(array_map(fn($o) => (float) $o['value'], $rows))]; }
$totN = array_sum(array_map(fn($c) => count($c['rows']), $cols)); $totV = array_sum(array_map(fn($c) => $c['v'], $cols));
//  The open leads as before (the first 25 cards, then "+ more"), grouped by each lead's own stage.
$leadV = array_sum(array_map(fn($l) => (float) $l['value'], $leads));
$lg = [];
//  Every open stage of the lead pipeline(s) gets its column, empty ones too, as on the
//  deals board (owner test PB-01, 21 Nov 2026: only stages holding a lead were drawn).
$lpIds = array_unique(array_filter(array_map(fn($l) => (int) ($l['pipeline_id'] ?? 0), $leads)));
if (!$lpIds && function_exists('pipeline_default') && ($dp = pipeline_default('LEAD'))) $lpIds = [(int) $dp['id']];
if (function_exists('pipeline_stages')) foreach ($lpIds as $lpId) foreach ((pipeline_stages($lpId) ?: []) as $st)
    if (($st['kind'] ?? 'OPEN') === 'OPEN') $lg[(int) $st['seq'] . '|' . (string) $st['name']] = $lg[(int) $st['seq'] . '|' . (string) $st['name']] ?? [];
foreach (array_slice($leads, 0, 25) as $l) { $k = (int) ($l['stage_seq'] ?? 0) . '|' . (string) ($l['stage_name'] ?? ''); $lg[$k][] = $l; }
ksort($lg, SORT_NATURAL);
$pipeQ = isset($_GET['pipeline']) ? '&pipeline=' . (int) $_GET['pipeline'] : '';
$curPipe = null; foreach ($pipes as $p) if ((int) $p['id'] === (int) ($board['pipeline'] ?? 0)) $curPipe = $p;
$card = function ($href, $title, $value, $owner, $r) use ($cardTone, $cardNote) {
    $n = $cardNote($r);
    return '<a class="sxw-cd ' . e($cardTone($r)) . '" href="' . e($href) . '"><b>' . e($title) . '</b><span>' . e(sx_short($value)) . ' · ' . e($owner ?: '—') . '</span>'
         . ($n !== '' ? '<span class="n">' . e($n) . '</span>' : '') . '</a>';
};
$right = '<div class="sxw-ctl">';
if (count($pipes) > 1) {
    $right .= '<details class="sxw-pp"><summary>' . rec_icon('flow', 's') . '<span>Pipeline: ' . e($curPipe['name'] ?? '') . '</span>' . rec_icon('down', 's') . '</summary><div>';
    foreach ($pipes as $p) $right .= '<a href="/sales-board?pipeline=' . (int) $p['id'] . e($q) . '"' . ((int) $p['id'] === (int) ($board['pipeline'] ?? 0) ? ' class="on" aria-current="true"' : '') . '>' . e($p['name']) . '</a>';
    $right .= '</div></details>';
}
$right .= sxw_person('sales-board', $whoKey, $people, $seeAll, $pipeQ) . (function_exists('sx_branch_select') ? sx_branch_select('sales-board') : '') . '</div>';   // R-05
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-board'); ?>
  <?php sxw_head('Pipeline board', 'Why you\'re here: the whole pipeline at a glance. Red edge = overdue or no next step · amber = due today. Open a card to change its stage, and the card moves.', 'flow', $right); ?>

  <div class="sxw-strip" role="group" aria-label="Confirmed orders"><b><?= rec_icon('trophy', 's') ?> Confirmed orders</b>
    <span><?= e(function_exists('THP') ? THP('quote') : 'Quotes') ?> accepted · this financial year <b class="v"><?= (int) $orders['n'] ?></b></span>
    <span>Their value · before GST <b class="v"><?= e(sx_short($orders['v'])) ?></b></span>
    <a class="rec-lnk" href="/quotes?status=ACCEPTED" style="margin-left:auto">See them ›</a></div>

  <section class="rec-card">
    <div class="sxw-tabs" data-sxw-tabs style="border:0;margin:0 0 10px">
      <span class="sxw-sw"><button type="button" data-for="sxb-leads">Open leads (<?= count($leads) ?>)</button><button type="button" data-for="sxb-deals" class="on"><?= e(function_exists('TP') ? TP('opportunity') : 'Deals') ?> (<?= (int) $totN ?>)</button></span>
    </div>

    <div id="sxb-deals" data-sxw-panel>
      <?php $n = count($cols) + 1; ?>
      <div class="sxw-sb" data-sxw-stages="sxb-dcols" style="--n:<?= max(1, $n) ?>">
        <?php foreach ($cols as $i => $c): ?><button type="button" data-col="<?= $i ?>"><span><?= e($c['s']['name']) ?></span><b><?= count($c['rows']) ?></b><small><?= e(sx_short($c['v'])) ?> · <?= (int) $c['s']['probability'] ?>% win</small></button><?php endforeach; ?>
        <div class="tot"><span>Total open</span><b><?= (int) $totN ?></b><small><?= e(sx_short($totV)) ?></small></div>
      </div>
      <?php if (!$cols): ?><p class="sxw-empty">This pipeline has no open stages.</p><?php endif; ?>
      <div class="sxw-bd" id="sxb-dcols" style="--n:<?= max(1, count($cols)) ?>">
        <?php foreach ($cols as $i => $c): $s = $c['s']; ?>
          <section class="sxw-col" data-col="<?= $i ?>"><header><b><?= e($s['name']) ?></b><small><?= count($c['rows']) ?> · <?= e(sx_short($c['v'])) ?> · <?= (int) $s['probability'] ?>% win</small></header>
            <?php foreach ($c['rows'] as $o) echo $card('/opportunity?id=' . (int) $o['id'], $o['client_name'] ?: $o['partner_name'] ?: $o['name'], $o['value'], $o['owner_name'] ?? '', $o); ?>
            <?php if (!$c['rows']): ?><p class="sxw-empty" style="padding:4px 6px">—</p><?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>
      <p class="rec-note">Your pipeline's own stages. Won and lost deals stay on the <a href="/opportunities">Deals screen</a>; the won summary is "Confirmed orders" above.</p>
    </div>

    <div id="sxb-leads" data-sxw-panel>
      <?php $ln = count($lg) + 1; ?>
      <div class="sxw-sb" data-sxw-stages="sxb-lcols" style="--n:<?= max(1, $ln) ?>">
        <?php $j = 0; foreach ($lg as $k => $rows): ?><button type="button" data-col="<?= $j++ ?>"><span><?= e(explode('|', $k, 2)[1] ?: 'Open') ?></span><b><?= count($rows) ?></b><small><?= e(sx_short(array_sum(array_map(fn($l) => (float) $l['value'], $rows)))) ?> est.</small></button><?php endforeach; ?>
        <div class="tot"><span>Open leads</span><b><?= count($leads) ?></b><small><?= e(sx_short($leadV)) ?> est.</small></div>
      </div>
      <?php if (!$leads): ?><p class="sxw-empty">No open leads.</p><?php endif; ?>
      <div class="sxw-bd" id="sxb-lcols" style="--n:<?= max(1, count($lg)) ?>">
        <?php $j = 0; foreach ($lg as $k => $rows): ?>
          <section class="sxw-col" data-col="<?= $j++ ?>"><header style="border-top-color:var(--info)"><b><?= e(explode('|', $k, 2)[1] ?: 'Open') ?></b><small><?= count($rows) ?> · <?= e(sx_short(array_sum(array_map(fn($l) => (float) $l['value'], $rows)))) ?> est.</small></header>
            <?php foreach ($rows as $l) echo $card('/lead?id=' . (int) $l['id'], $l['company_name'], $l['value'], $l['owner_name'] ?? '', $l); ?>
            <?php if (!$rows): ?><p class="sxw-empty" style="padding:4px 6px">—</p><?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>
      <?php if (count($leads) > 25): ?><a class="rec-lnk" href="/leads">+ <?= count($leads) - 25 ?> more ›</a><?php endif; ?>
      <p class="rec-note">Open leads <?= count($leads) ?> · <?= e(sx_short($leadV)) ?> est. — the first 25 are shown, by each lead's own stage.</p>
    </div>
  </section>
</div>
<?php sxw_bottom('/sales-board'); sxw_script(); ?>
<script>
// Phone: tap a stage box to see that stage's cards (the columns sit side by side on a wider page).
(function () {
  document.querySelectorAll('[data-sxw-stages]').forEach(function (sb) {
    var bd = document.getElementById(sb.getAttribute('data-sxw-stages')); if (!bd) return;
    var boxes = sb.querySelectorAll('[data-col]'), cols = bd.querySelectorAll('.sxw-col');
    if (!cols.length) return;
    sb.classList.add('js'); bd.classList.add('js');
    function show(i) { boxes.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-col') === i); });
                       cols.forEach(function (c) { c.classList.toggle('on', c.getAttribute('data-col') === i); }); }
    boxes.forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-col')); }); });
    show('0');
  });
})();
</script>
