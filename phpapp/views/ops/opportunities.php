<?php
// Deals register (owner, 21 Nov 2026: Sales redesign Stage 3, approved design
// s3-deals). The same board, list, figures, filters, CSV and "why deals were lost"
// as before, in the Sales workspace look, with the controls that already existed
// on the server made visible: the pipeline (?pipeline=) and the status filter.
// The year picker shows on the list only — the board always shows the live
// pipeline, as before, and now says so. On a phone the board stacks one stage
// under another instead of scrolling sideways. Every figure, row and refusal
// still comes from ops_opportunities() in lib/opportunities.php. The old page is
// kept in tests/fixtures/pre_stage3/opportunities.php.
require_once __DIR__ . '/_sxw_ui.php';
$c = $counts; $view = $view ?? 'board';
$canCsv = !function_exists('opp_can_export') || opp_can_export();
$status = (string)($_GET['status'] ?? '');
$csvHref = '/opportunities?' . http_build_query(array_merge($_GET, ['export' => 'csv']));
$right = '<div class="sxw-ctl">' . ($canEdit ? '<a class="sxw-bt p" href="/opportunity-new">+ New ' . e(Tl('opportunity')) . '</a>' : '') . '</div>';
?>
<div class="rec sxw has-bn sxl sxd">
  <?php sxw_nav('/opportunities'); ?>
  <?php sxw_head(THP('opportunity'), 'Pieces of business you are trying to win, with a value and a stage. Kept apart from ' . Tlp('quote') . ' on purpose: one deal often carries three ' . Tlp('quote') . ', and counting ' . Tlp('quote') . ' makes the forecast three times too big. A deal can also be lost before anyone quotes — that loss is the one worth recording.', 'brief', $right); ?>

  <?php //  R-03 (owner, 26 Nov 2026): every card opens the list of exactly the deals it
        //  counted (lib/opportunities.php opp_tile_filter()). ?>
  <?php $tileHref = fn($t) => '/opportunities?' . http_build_query(['v' => 'list', 'tile' => $t]); ?>
  <div class="sxw-kp" style="--n:6">
    <?php sxw_kpi('teal', 'brief', 'Open', 'Open', (string)(int)$c['open'], 'right now', $tileHref('open'));
          sxw_kpi('info', 'rupee', 'Pipeline estimate', 'Pipeline estimate', fmoney_short($c['value']), 'open ' . Tlp('opportunity'), $tileHref('open'));
          sxw_kpi('info', 'chart', 'Weighted forecast', 'Weighted forecast', fmoney_short($c['weighted']), 'value × stage win %', $tileHref('open'));
          sxw_kpi($c['stalled'] ? 'warn' : 'grey', 'hour', "Past their stage's service level", 'Past stage time', (string)(int)$c['stalled'], 'stalled', $tileHref('stalled'));
          sxw_kpi('ok', 'target', 'Win rate, 12 months', 'Win rate', $win['rate'] === null ? '—' : (int)$win['rate'] . '%', 'last 12 months', $tileHref('closed'));
          sxw_kpi('grey', 'trophy', 'Won · Lost, 12 months', 'Won · Lost', (int)$win['won'] . ' · ' . (int)$win['lost'], 'last 12 months', $tileHref('closed')); ?>
  </div>
  <?php if (($tile ?? '') !== ''): ?>
  <p class="sxl-show" role="status">Showing: <b><?= (int)$total ?></b> · <?= e($tileWords) ?><?= $tile === 'closed' ? ' (' . (int)$win['won'] . ' won · ' . (int)$win['lost'] . ' lost)' : '' ?> · <a href="/opportunities?v=list">Show all</a></p>
  <?php endif; ?>

  <div class="sxl-ctl">
    <nav class="sxw-seg" aria-label="Board or list">
      <a href="/opportunities?v=board"<?= $view === 'board' ? ' class="on" aria-current="true"' : '' ?>><?= rec_icon('grid', 's') ?> Board</a>
      <a href="/opportunities?v=list"<?= $view === 'list' ? ' class="on" aria-current="true"' : '' ?>><?= rec_icon('list', 's') ?> List</a>
    </nav>
    <?php if ($view === 'board' && count($pipelines ?? []) > 1): $curPipe = (int)($board['pipeline'] ?? 0); //  ?pipeline= — always honoured by the server, now with a control. ?>
      <form method="get" action="/opportunities" class="sxl-f">
        <input type="hidden" name="v" value="board">
        <label class="lab" for="opp-pipe">Pipeline</label>
        <select class="form-control" id="opp-pipe" name="pipeline" onchange="this.form.submit()">
          <?php foreach ($pipelines as $pp): ?>
            <option value="<?= (int)$pp['id'] ?>" <?= $curPipe === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['name']) ?><?= !empty($pp['is_default']) ? ' (default)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn small secondary">Show</button></noscript>
      </form>
    <?php endif; ?>
    <?= function_exists('sx_branch_select') ? sx_branch_select('opportunities') : '' //  R-05 — only for a person who sees more than one branch ?>
    <?php // Year filter for the List (the Board shows the live pipeline regardless). ?>
    <?php if ($view === 'list'): ?>
    <form method="get" action="/opportunities" class="sxl-f">
      <input type="hidden" name="v" value="list">
      <?php if (($_GET['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <label class="lab">Year</label><?= fy_select_html($fy ?? current_fy()) ?>
    </form>
    <form method="get" action="/opportunities" class="sxl-f">
      <input type="hidden" name="v" value="list"><input type="hidden" name="fy" value="<?= e($fy ?? current_fy()) ?>">
      <?php if (($_GET['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
      <label class="lab" for="opp-status">Status</label>
      <select class="form-control" id="opp-status" name="status" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach (OPP_STATUS as $sk => $sv): ?><option value="<?= e($sk) ?>"<?= $status === (string)$sk ? ' selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
      </select>
      <noscript><button class="btn small secondary">Show</button></noscript>
    </form>
    <?php else: ?>
      <form method="get" action="/opportunities" class="sxl-f sxl-find" role="search">
        <input type="hidden" name="v" value="list">
        <label class="sr-only" for="opp-find">Find <?= e(Tla('opportunity')) ?></label>
        <input class="sxw-srch" id="opp-find" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Find a deal or a customer">
        <button class="sxw-bt">Find</button>
      </form>
      <?php if ($canCsv): ?><a class="sxw-ib" href="<?= e($csvHref) ?>"><?= rec_icon('dl', 's') ?><span>CSV</span></a><?php endif; ?>
    <?php endif; ?>
  </div>
  <p class="sxl-note"><?= $view === 'board'
      ? 'The board always shows the live pipeline — every open ' . e(Tl('opportunity')) . ' — whatever the year; the year picker is on the list, where it applies. Won and lost are the last 90 days.'
      : 'The list shows the ' . e(Tlp('opportunity')) . ' opened in the year chosen.' ?></p>

<?php if ($view === 'board'): ?>
  <?php $bc = $board['columns']; ?>
  <div class="sxl-board">
    <div class="sxw-bd" style="--n:<?= max(1, count($board['stages'])) ?>">
      <?php foreach ($board['stages'] as $s): $col = $bc[(int)$s['id']] ?? ['rows' => [], 'value' => 0, 'weighted' => 0]; $closed = in_array($s['kind'], ['WON', 'LOST'], true); ?>
        <section class="sxw-col" aria-label="<?= e($s['name']) ?>">
          <header><b><?= e($s['name']) ?></b>
            <small><?= count($col['rows']) ?> · <?= $col['value'] ? fmoney_short($col['value']) : '—' ?><?= (int)$s['probability'] ? ' · ' . (int)$s['probability'] . '%' : '' ?><?= $closed ? ' · last 90 days' : '' ?></small>
            <?php if ($col['weighted']): ?><small>weighted <?= fmoney_short($col['weighted']) ?></small><?php endif; ?></header>
          <?php foreach ($col['rows'] as $o): $st = opp_stalled($o); ?>
            <a class="sxw-cd<?= $st ? ' r' : '' ?><?= $closed ? ' sxl-cl' : '' ?>" href="/opportunity?id=<?= (int)$o['id'] ?>">
              <b><?= e($o['name']) ?></b>
              <span><?= e($o['client_name'] ?: $o['partner_name']) ?></span>
              <span><?= e($o['ref']) ?><?= $o['value'] ? ' · ' . fmoney_short($o['value']) : '' ?></span>
              <?php if ($st): ?><span class="sxl-pills"><span class="pill p-bad"><?= opp_days_in_stage($o) ?>d</span></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
          <?php if (!empty($col['hidden'])): ?>
            <a class="sxl-more" href="/opportunities?v=list&amp;status=<?= e($s['kind']) ?><?= $board['pipeline'] ? '&amp;pipeline=' . (int)$board['pipeline'] : '' ?>">+<?= (int)$col['hidden'] ?> older — show all <?= (int)$col['total'] ?></a>
          <?php elseif (!$col['rows']): ?><p class="sxw-empty" style="margin:2px 4px">—</p><?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="sxl-note">A red edge means it has sat in that stage longer than the stage allows. On a phone the stages stack one under another.</p>

  <?php if ($losses): ?>
    <?php rec_card_open('Why deals were lost — last 12 months', 'alert', '', '', 'panel'); ?>
      <p class="sxl-note" style="margin:-6px 0 10px">Unanswerable before <?= e(Tlp('opportunity')) ?> existed: a lost quotation had a reason, but a deal lost before anybody quoted had nowhere to record one.</p>
      <div class="rec-scroll"><table class="dt">
        <caption class="sr-only">Loss reasons</caption>
        <thead><tr><th scope="col">Reason</th><th scope="col" class="num">Deals</th><th scope="col" class="num">Estimated value</th></tr></thead>
        <tbody>
        <?php foreach ($losses as $l): ?>
          <tr><td><?= e($l['reason']) ?></td><td class="num"><?= (int)$l['n'] ?></td><td class="num"><?= e(fmoney($l['value'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="sxl-note" style="margin:10px 0 0">
        Won <?= (int)$win['won'] ?> (<?= e(fmoney($win['won_value'])) ?>) against lost <?= (int)$win['lost'] ?> (<?= e(fmoney($win['lost_value'])) ?>).
      </p>
    <?php rec_card_close(); ?>
  <?php endif; ?>

<?php else: ?>
  <section class="rec-card sxl-reg">
    <?= dt_render($dt, $rows, $total, [
          'caption'     => TH('opportunity') . ' register',
          'search'      => true,
          'search_hint' => 'Deal, customer or reference…',
          'export'      => $canCsv,
          'empty'       => 'Nothing yet. ' . ucfirst(Tla('opportunity')) . ' opens from a lead, or straight from here when a customer tells you what they are planning.',
        ]) ?>
    <p class="sxl-note" style="margin:8px 2px 0">Columns you can add with <b>Columns</b>: %, Branch and Against. Every row is limited to your branches — the same rule as before.</p>
  </section>
<?php endif; ?>
</div>
<?php sxw_bottom('/opportunities'); ?>
