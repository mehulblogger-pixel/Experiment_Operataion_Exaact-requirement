<?php
// Leads register (owner, 21 Nov 2026: Sales redesign Stage 2, approved design
// s2-leads). The same board and list as before — the same cards, columns, year,
// pipeline, search, bulk actions and CSV — in the Sales workspace look, with the
// controls that already existed on the server made visible: the status filter,
// "Preview first" for a bulk action, and the "Not pursued" count. Every figure,
// row and refusal still comes from ops_leads() in lib/leads.php. The old page is
// kept in tests/fixtures/pre_stage2/leads.php.
require_once __DIR__ . '/_sxw_ui.php';
$c = $counts; $view = $view ?? 'board';
//  The year every card counts — said on the card, so a number is never read for another year.
$isYear = ($fy ?? '') !== '' && strtoupper((string)($fy ?? '')) !== 'ALL';
$fyTag = $isYear ? ' (FY ' . $fy . ')' : ' (all years)';
//  Open leads carry forward from earlier years unless "only this year" is ticked.
$openTag = $isYear ? (!empty($onlyYear) ? ' (created in FY ' . $fy . ')' : ' (FY ' . $fy . ' + carried forward)') : ' (all years)';
$dueCount = (int)($dueCount ?? 0);
$status = (string)($_GET['status'] ?? '');
$canCsv = !function_exists('leads_can_export') || leads_can_export();
$csvHref = '/leads?' . http_build_query(array_merge($_GET, ['export' => 'csv']));
$right = '<div class="sxw-ctl">'
       . ($canEdit ? '<a class="sxw-bt" href="/sales-lead">' . rec_icon('bolt', 's') . ' Quick lead</a><a class="sxw-bt p" href="/lead-new">+ New lead</a>' : '')
       . '</div>';
?>
<div class="rec sxw has-bn sxl">
  <?php sxw_nav('/leads'); ?>
  <?php sxw_head('Leads', 'People we are chasing before they are customers. When one says yes it becomes a ' . Tl('client') . ' and an ' . Tl('inquiry') . ' by itself — nothing is typed twice.', 'users', $right); ?>

  <div class="sxw-kp" style="--n:6">
    <?php sxw_kpi('teal', 'users', 'Open' . $openTag, 'Open' . ($isYear ? ' · FY' : ''), (string)(int)$c['open'], $isYear ? (!empty($onlyYear) ? 'created in FY ' . $fy : 'FY ' . $fy . ' + carried forward') : 'all years');
          sxw_kpi('info', 'rupee', 'Open pipeline value' . $openTag, 'Pipeline value', fmoney_short($c['value']), (int)$c['open'] . ' open ' . ((int)$c['open'] === 1 ? 'lead' : 'leads'));
          sxw_kpi($c['stalled'] ? 'warn' : 'grey', 'hour', "Past their stage's service level", 'Past stage time', (string)(int)$c['stalled'], 'stalled');
          sxw_kpi($dueCount ? 'bad' : 'grey', 'alert', 'Need attention now', 'Need attention', (string)$dueCount, 'any year');
          sxw_kpi('ok', 'trophy', 'Won — now customers' . $fyTag, 'Won' . ($isYear ? ' · FY' : ''), (string)(int)$c['converted'], 'now ' . Tlp('client'));
          sxw_kpi('grey', 'x', 'Not pursued' . $fyTag, 'Not pursued' . ($isYear ? ' · FY' : ''), (string)(int)($c['lost'] ?? 0), $isYear ? 'FY ' . $fy : 'all years'); ?>
  </div>

  <?php if ($dueCount): ?>
  <div class="sxl-ban warn" role="status">
    <span class="ic"><?= rec_icon('alert') ?></span>
    <div class="tx"><b class="t"><?= $dueCount ?> lead<?= $dueCount === 1 ? '' : 's' ?> need attention now</b>
      Past their stage's service level, or with a follow-up date that has passed. A lead left silent is the cheapest deal in the funnel to lose.
      Sort the list by <em>In stage</em> to see the oldest first, or see the ranked list on <a href="/advisor">What to fix</a>.</div>
    <a class="sxw-bt" href="/leads?<?= e(http_build_query(['v' => 'list', 'fy' => $fy, 'status' => 'OPEN', 'sort' => 'instage', 'dir' => 'asc'])) ?>">Show the oldest first</a>
  </div>
  <?php endif; ?>

  <div class="sxl-ctl">
    <nav class="sxw-seg" aria-label="Board or list">
      <a href="/leads?v=board"<?= $view === 'board' ? ' class="on" aria-current="true"' : '' ?>><?= rec_icon('grid', 's') ?> Board</a>
      <a href="/leads?v=list"<?= $view === 'list' ? ' class="on" aria-current="true"' : '' ?>><?= rec_icon('list', 's') ?> List</a>
    </nav>
    <?php // Financial-year filter (owner, 12 Nov 2026). The list shows leads created in
          // that year. The board and the cards also carry forward the open leads of
          // earlier years, marked, unless "Only leads created in FY …" is ticked.
          // Changing the year keeps the pipeline and the tick. ?>
    <form method="get" action="/leads" class="sxl-f">
      <input type="hidden" name="v" value="<?= e($view) ?>">
      <?php if ($view === 'board' && !empty($board['pipeline'])): ?><input type="hidden" name="pipeline" value="<?= (int)$board['pipeline'] ?>"><?php endif; ?>
      <?php if (!empty($onlyYear)): ?><input type="hidden" name="only" value="1"><?php endif; ?>
      <?php if (($_GET['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
      <?php if ($view === 'list' && $status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <label class="lab">Year</label><?= fy_select_html($fy ?? current_fy()) ?>
    </form>
    <?php if ($view === 'list'): //  The status filter the server always honoured, now with a control. ?>
    <form method="get" action="/leads" class="sxl-f">
      <input type="hidden" name="v" value="list"><input type="hidden" name="fy" value="<?= e($fy ?? current_fy()) ?>">
      <?php if (($_GET['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
      <label class="lab" for="lead-status">Status</label>
      <select class="form-control" id="lead-status" name="status" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach (LEAD_STATUS as $sk => $sv): ?><option value="<?= e($sk) ?>"<?= $status === (string)$sk ? ' selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
      </select>
      <noscript><button class="btn small secondary">Show</button></noscript>
    </form>
    <?php endif; ?>
    <?php // Which pipeline the board shows. Offered whenever more than one exists,
          // so leads on a non-default pipeline are one click away rather than lost. ?>
    <?php if ($view === 'board' && count($pipelines ?? []) > 1): $curPipe = (int)($board['pipeline'] ?? 0); ?>
      <form method="get" action="/leads" class="sxl-f">
        <input type="hidden" name="v" value="board">
        <input type="hidden" name="fy" value="<?= e($fy ?? current_fy()) ?>">
        <?php if (!empty($onlyYear)): ?><input type="hidden" name="only" value="1"><?php endif; ?>
        <label class="lab" for="lead-pipe">Pipeline</label>
        <select class="form-control" id="lead-pipe" name="pipeline" onchange="this.form.submit()">
          <?php foreach ($pipelines as $pp): ?>
            <option value="<?= (int)$pp['id'] ?>" <?= $curPipe === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['name']) ?><?= !empty($pp['is_default']) ? ' (default)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
    <?php if ($view !== 'list'): ?>
      <form method="get" action="/leads" class="sxl-f sxl-find" role="search">
        <input type="hidden" name="v" value="list">
        <label class="sr-only" for="lead-find">Find a lead</label>
        <input class="sxw-srch" id="lead-find" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Find a company or reference">
        <button class="sxw-bt">Find</button>
      </form>
      <?php if ($canCsv): ?><a class="sxw-ib" href="<?= e($csvHref) ?>"><?= rec_icon('dl', 's') ?><span>CSV</span></a><?php endif; ?>
    <?php endif; ?>
  </div>
  <p class="sxl-note"><?= $view === 'board'
      ? 'The board shows the year’s leads and the open ones carried forward from earlier years, each marked. Finding a lead opens it in the list.'
      : 'The list shows the leads created in the year chosen.' ?></p>

<?php if ($view === 'board'): ?>
  <?php // Named apart from $cols, which is the register's column definitions.
        // Two different things under one name is how a page 500s a screen later. ?>
  <?php $boardCols = $board['columns']; ?>
  <?php if ($isYear && (!empty($board['other_years_open']) || !empty($onlyYear))): $n = (int)$board['other_years_open'];
        $q = fn($only) => http_build_query(array_filter(['v' => 'board', 'fy' => $fy, 'pipeline' => (int)($board['pipeline'] ?? 0), 'only' => $only ? 1 : null])); ?>
    <div class="sxl-ban info">
      <span class="ic"><?= rec_icon('swap') ?></span>
      <?php if (empty($onlyYear)): ?>
        <div class="tx">↻ <?= $n ?> open lead<?= $n === 1 ? '' : 's' ?> carried forward from earlier years — still being chased, marked on the board.</div>
        <a class="sxw-bt" href="/leads?<?= e($q(true)) ?>">Only leads created in FY <?= e($fy) ?></a>
      <?php else: ?>
        <div class="tx">Showing only leads created in FY <?= e($fy) ?><?= $n ? ' — ' . $n . ' open lead' . ($n === 1 ? '' : 's') . ' carried forward from earlier years ' . ($n === 1 ? 'is' : 'are') . ' not shown' : '' ?>.</div>
        <a class="sxw-bt" href="/leads?<?= e($q(false)) ?>">Include carried-forward leads</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="sxl-board">
    <div class="sxw-bd" style="--n:<?= max(1, count($board['stages'])) ?>">
      <?php foreach ($board['stages'] as $s): $col = $boardCols[(int)$s['id']] ?? ['leads'=>[],'value'=>0]; ?>
        <section class="sxw-col" aria-label="<?= e($s['name']) ?>">
          <header><b><?= e($s['name']) ?></b>
            <small><?= (int)($col['total'] ?? count($col['leads'])) ?> · <?= $col['value'] ? fmoney_short($col['value']) : '—' ?><?= (int)$s['probability'] ? ' · ' . (int)$s['probability'] . '%' : '' ?></small></header>
          <?php foreach ($col['leads'] as $l):
                if (!empty($l['_closed'])): // a won / not-pursued lead: no score, no "late" edge ?>
            <a class="sxw-cd sxl-cl" href="/lead?id=<?= (int)$l['id'] ?>">
              <b><?= e($l['company_name']) ?></b>
              <span><?= e($l['ref']) ?><?= $l['value'] ? ' · ' . fmoney_short($l['value']) : '' ?></span>
              <span class="sxl-pills"><?= $l['_closed'] === 'CONVERTED'
                ? '<span class="pill p-ok">✓ Won — customer</span>'
                : '<span class="pill p-mut">Not pursued</span>' ?></span>
            </a>
          <?php continue; endif; $sc = lead_score($l); $st = lead_stalled($l); ?>
            <a class="sxw-cd<?= $st ? ' r' : '' ?>" href="/lead?id=<?= (int)$l['id'] ?>">
              <b><?= e($l['company_name']) ?></b>
              <span><?= e($l['ref']) ?><?= $l['value'] ? ' · ' . fmoney_short($l['value']) : '' ?></span>
              <?php if (!empty($l['_carried_from'])): ?><span class="sxl-cf" title="Created in FY <?= e($l['_carried_from']) ?> and still open">↻ Carried forward from FY <?= e($l['_carried_from']) ?></span><?php endif; ?>
              <span class="sxl-pills">
                <span class="pill <?= $sc['score']>=60?'p-ok':($sc['score']>=35?'p-warn':'p-mut') ?>" title="How promising, out of 100 — the reasons are on the lead"><?= (int)$sc['score'] ?></span>
                <?php if ($st): ?><span class="pill p-bad"><?= lead_days_in_stage($l) ?>d</span><?php endif; ?>
                <?php if (lead_close_overdue($l)): ?><span class="pill p-bad" title="Expected to close by <?= e(fdate(substr((string)$l['expected_close'], 0, 10))) ?> — that date has passed">⏰ close date passed</span><?php endif; ?>
                <?php // Field #5 — this lead has been worked as a deal; the card opens the lead, which carries "Open the deal". ?>
                <?php if (!empty($l['deal_id'])): ?><span class="pill p-info" title="A deal has been opened from this lead — open the lead to jump to it">◆ deal</span><?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
          <?php if (!empty($col['more'])): ?><a class="sxl-more" href="/leads?v=list&amp;status=<?= e($col['more_status']) ?>">+<?= (int)$col['more'] ?> more — see the list</a><?php endif; ?>
          <?php if (!$col['leads']): ?><p class="sxw-empty" style="margin:2px 4px">—</p><?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="sxl-note">A red edge means it has sat in that stage longer than the stage allows. The number is a score with its reasoning on the lead — it is a rules engine, not a guess.</p>

<?php else: ?>
<?php
  // The controls the bulk actions need, each hidden until the action that uses it
  // is reached for (the register component shows them). The same names as before.
  $bulkExtra = '';
  if ($canEdit) {
      $bulkExtra = '<span class="bulk-ctl" data-for="assign" hidden>'
        . '<label class="sr-only" for="lead-assign-to">Allocate to</label>'
        . '<select class="form-control" id="lead-assign-to" name="assign_to" style="min-width:11rem">'
        . '<option value="">— choose a person —</option>';
      foreach (($users ?? []) as $u)
          $bulkExtra .= '<option value="' . (int)$u['id'] . '">'
            . e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) . '</option>';
      $bulkExtra .= '</select>'
        . '<label class="sr-only" for="lead-assign-why">Why (for leads that already have an owner)</label>'
        . '<input class="form-control" id="lead-assign-why" name="reassign_reason" maxlength="500" placeholder="Why — needed if they already have an owner" style="min-width:14rem"></span>';
      // Taking leads that belong to someone else is a reassignment too.
      $bulkExtra .= '<span class="bulk-ctl" data-for="mine" hidden>'
        . '<label class="sr-only" for="lead-mine-why">Why (for leads that already have an owner)</label>'
        . '<input class="form-control" id="lead-mine-why" name="reassign_reason_mine" maxlength="500" placeholder="Why — needed if they already have an owner" style="min-width:14rem"></span>';

      $bulkExtra .= '<span class="bulk-ctl" data-for="lost" hidden>'
        . '<label class="sr-only" for="lead-lost-reason">Why they were lost</label>'
        . '<select class="form-control" id="lead-lost-reason" name="lost_reason" style="min-width:11rem">';
      foreach (($lostReasons ?: ['NO_RESPONSE' => 'No response']) as $k => $v)
          $bulkExtra .= '<option value="' . e($k) . '">' . e($v) . '</option>';
      $bulkExtra .= '</select>'
        . '<label class="sr-only" for="lead-lost-note">Remarks</label>'
        . '<input class="form-control" id="lead-lost-note" name="lost_note" maxlength="500" placeholder="Remarks — required" style="min-width:14rem"></span>';
      //  The dry run the server always had (§48), now reachable: tick it, then press an action.
      $bulkExtra .= '<label class="sxl-prev"><input type="checkbox" name="preview" value="1"> ' . rec_icon('eye', 's')
        . ' <span><b>Preview first</b> — show what would change and what would be left alone, and change nothing yet</span></label>'
        . '<span class="sxl-hint">Allocating someone else’s lead asks their manager unless you are that manager. Only a lead’s own handler marks it lost.</span>';
  }
?>
<section class="rec-card sxl-reg">
  <?= dt_render($dt, $rows, $total, [
        'caption'     => 'Lead register',
        'search'      => true,
        'search_hint' => 'Company, contact, reference or e-mail…',
        'export'      => $canCsv,
        'empty'       => 'Nothing yet. Leads land here from the website form, a phone call, or by typing one in.',
        'bulk_action' => '/leads-bulk',
        'bulk_extra'  => $bulkExtra,
        'bulk'        => !$canEdit ? [] : [
          'assign' => ['label' => 'Allocate to…', 'confirm' => 'Allocate the ticked leads to the person chosen beside this button?'],
          'mine'   => ['label' => 'Take these myself', 'confirm' => 'Take ownership of these leads?'],
          'lost'   => ['label' => 'Mark lost', 'danger' => true,
                       'confirm' => 'Mark these leads lost, with the reason chosen beside this button?'],
        ],
      ]) ?>
  <p class="sxl-note" style="margin:8px 2px 0">Columns you can add with <b>Columns</b>: Branch, Source (in words) and Expected close. Every row is limited to your branches — the same rule as before.</p>
</section>
<?php endif; ?>
</div>
<?php sxw_bottom('/leads'); ?>
