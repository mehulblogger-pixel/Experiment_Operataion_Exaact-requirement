<?php $c = $counts; $view = $view ?? 'board'; ?>
<div class="crumbs"><a href="/">Home</a> › Leads</div>
<div class="master-head">
  <div><h1>Leads</h1>
  <p class="sub" style="margin:2px 0 0">People we are chasing before they are customers. Winning one <b>converts</b> it — it becomes a customer and an inquiry, and nothing is typed twice.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($canEdit): ?><a class="btn" href="/lead-new">+ New lead</a><?php endif; ?>
    <?php // The list carries its own export, inside the register, so it can say
          // it is exporting what the filters match. Only the board needs one here. ?>
    <?php if ($view !== 'list'): ?>
      <a class="btn secondary" href="/leads?<?= e(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">⬇ CSV</a>
    <?php endif; ?>
  </div>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-info"><div class="qic">◎</div><div class="qn"><?= (int)$c['open'] ?></div><div class="ql">Open</div></div>
  <div class="qcard tone-ok"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney_short($c['value']) ?></div><div class="ql">Open pipeline value</div></div>
  <div class="qcard <?= $c['stalled'] ? 'tone-bad' : '' ?>"><div class="qic">◷</div><div class="qn"><?= (int)$c['stalled'] ?></div><div class="ql">Past their stage's service level</div></div>
  <div class="qcard tone-warn"><div class="qic">✓</div><div class="qn"><?= (int)$c['converted'] ?></div><div class="ql">Converted</div></div>
</div>

<div class="panel" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <a class="btn small<?= $view==='board'?'':' secondary' ?>" href="/leads?v=board">Board</a>
  <a class="btn small<?= $view==='list'?'':' secondary' ?>" href="/leads?v=list">List</a>
  <?php // Financial-year filter for the List. The Board shows the live pipeline
        // whatever year is picked, so this narrows the list of leads by when they
        // were created. ?>
  <form method="get" action="/leads" style="display:inline-flex;gap:6px;align-items:center">
    <input type="hidden" name="v" value="<?= e($view) ?>">
    <?php if (($_GET['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
    <label class="muted" style="font-size:12px">Year</label><?= fy_select_html($fy ?? current_fy()) ?>
  </form>
  <?php // Which pipeline the board shows. Offered whenever more than one exists,
        // so leads on a non-default pipeline are one click away rather than lost. ?>
  <?php if ($view === 'board' && count($pipelines ?? []) > 1): $curPipe = (int)($board['pipeline'] ?? 0); ?>
    <form method="get" action="/leads" style="display:inline-flex;gap:6px;align-items:center">
      <input type="hidden" name="v" value="board">
      <label class="muted" style="font-size:12px">Pipeline</label>
      <select class="form-control" name="pipeline" onchange="this.form.submit()" style="height:30px;padding:2px 8px;font-size:13px">
        <?php foreach ($pipelines as $pp): ?>
          <option value="<?= (int)$pp['id'] ?>" <?= $curPipe === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['name']) ?><?= !empty($pp['is_default']) ? ' (default)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
  <?php if ($view !== 'list'): ?>
    <form method="get" action="/leads" style="display:flex;gap:8px;margin-left:auto">
      <input type="hidden" name="v" value="list">
      <label class="sr-only" for="lead-find">Find a lead</label>
      <input class="form-control" id="lead-find" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Find a company or reference">
      <button class="btn small secondary">Find</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($view === 'board'): ?>
  <?php // Named apart from $cols, which is the register's column definitions.
        // Two different things under one name is how a page 500s a screen later. ?>
  <?php $boardCols = $board['columns']; ?>
  <div class="board-wrap mt-4">
    <div class="board">
      <?php foreach ($board['stages'] as $s): $col = $boardCols[(int)$s['id']] ?? ['leads'=>[],'value'=>0]; ?>
        <div class="panel board-col">
          <div style="display:flex;justify-content:space-between;align-items:baseline">
            <b style="font-size:13.5px"><?= e($s['name']) ?></b>
            <span class="muted" style="font-size:12px"><?= count($col['leads']) ?></span>
          </div>
          <div class="muted" style="font-size:12px;margin-bottom:8px">
            <?= $col['value'] ? fmoney_short($col['value']) : '—' ?>
            <?= (int)$s['probability'] ? ' · ' . (int)$s['probability'] . '%' : '' ?>
          </div>
          <?php foreach ($col['leads'] as $l): $sc = lead_score($l); $st = lead_stalled($l); ?>
            <a href="/lead?id=<?= (int)$l['id'] ?>" style="display:block;border:1px solid var(--line);border-radius:8px;padding:9px;margin-bottom:8px;text-decoration:none<?= $st ? ';border-left:3px solid var(--bad)' : '' ?>">
              <b style="font-size:13px"><?= e($l['company_name']) ?></b>
              <div class="muted" style="font-size:11.5px;margin-top:2px"><?= e($l['ref']) ?><?= $l['value'] ? ' · ' . fmoney_short($l['value']) : '' ?></div>
              <div style="font-size:11.5px;margin-top:4px">
                <span class="pill <?= $sc['score']>=60?'p-ok':($sc['score']>=35?'p-warn':'p-mut') ?>" style="font-size:10.5px"><?= (int)$sc['score'] ?></span>
                <?php if ($st): ?><span class="pill p-bad" style="font-size:10.5px"><?= lead_days_in_stage($l) ?>d</span><?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$col['leads']): ?><p class="muted" style="font-size:12px;margin:0">—</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="muted" style="margin-top:10px;font-size:12.5px">A red edge means it has sat in that stage longer than the stage allows. The number is a score with its reasoning on the lead — it is a rules engine, not a guess.</p>

<?php else: ?>
<?php
  // The two controls the bulk actions need, hidden until the action that uses
  // them is chosen. Reported from a real test: a lost-reason dropdown sat on the
  // register permanently, next to a button called "Assign to me", and read as
  // one confusing control rather than two unrelated ones.
  $bulkExtra = '';
  if ($canEdit) {
      $bulkExtra = '<span class="bulk-ctl" data-for="assign" hidden>'
        . '<label class="sr-only" for="lead-assign-to">Allocate to</label>'
        . '<select class="form-control" id="lead-assign-to" name="assign_to" style="min-width:11rem">'
        . '<option value="">— choose a person —</option>';
      foreach (($users ?? []) as $u)
          $bulkExtra .= '<option value="' . (int)$u['id'] . '">'
            . e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) . '</option>';
      $bulkExtra .= '</select></span>';

      $bulkExtra .= '<span class="bulk-ctl" data-for="lost" hidden>'
        . '<label class="sr-only" for="lead-lost-reason">Why they were lost</label>'
        . '<select class="form-control" id="lead-lost-reason" name="lost_reason" style="min-width:11rem">';
      foreach (($lostReasons ?: ['NO_RESPONSE' => 'No response']) as $k => $v)
          $bulkExtra .= '<option value="' . e($k) . '">' . e($v) . '</option>';
      $bulkExtra .= '</select></span>';
  }
?>
<div style="margin-top:16px">
  <?= dt_render($dt, $rows, $total, [
        'caption'     => 'Lead register',
        'search'      => true,
        'search_hint' => 'Company, contact or reference…',
        'export'      => true,
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
</div>
<?php endif; ?>
