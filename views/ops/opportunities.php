<?php $c = $counts; $view = $view ?? 'board'; ?>
<div class="crumbs"><a href="/">Home</a> › Opportunities</div>
<div class="master-head">
  <div><h1>Opportunities</h1>
  <p class="sub" style="margin:2px 0 0">Pieces of business you are trying to win. Kept apart from quotations on purpose: one deal often carries three quotations, and counting quotations makes the forecast three times too big. A deal can also be lost before anyone quotes — that loss is the one worth recording.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($canEdit): ?><a class="btn" href="/opportunity-new">+ New opportunity</a><?php endif; ?>
    <?php if ($view !== 'list'): ?>
      <a class="btn secondary" href="/opportunities?<?= e(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">⬇ CSV</a>
    <?php endif; ?>
  </div>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-info"><div class="qic">◎</div><div class="qn"><?= (int)$c['open'] ?></div><div class="ql">Open</div></div>
  <div class="qcard tone-ok"><div class="qic">Σ</div><div class="qn" style="font-size:20px"><?= fmoney_short($c['value']) ?></div><div class="ql">Pipeline estimate</div></div>
  <div class="qcard tone-warn"><div class="qic">⌀</div><div class="qn" style="font-size:20px"><?= fmoney_short($c['weighted']) ?></div><div class="ql">Weighted forecast</div></div>
  <div class="qcard <?= $c['stalled'] ? 'tone-bad' : '' ?>"><div class="qic">◷</div><div class="qn"><?= (int)$c['stalled'] ?></div><div class="ql">Past their stage's service level</div></div>
  <div class="qcard"><div class="qic">%</div><div class="qn"><?= $win['rate'] === null ? '—' : (int)$win['rate'] . '%' ?></div><div class="ql">Win rate, 12 months</div></div>
</div>

<div class="panel" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <a class="btn small<?= $view==='board'?'':' secondary' ?>" href="/opportunities?v=board">Board</a>
  <a class="btn small<?= $view==='list'?'':' secondary' ?>" href="/opportunities?v=list">List</a>
  <?php if ($view !== 'list'): ?>
    <form method="get" action="/opportunities" style="display:flex;gap:8px;margin-left:auto" role="search">
      <input type="hidden" name="v" value="list">
      <label class="sr-only" for="opp-find">Find an opportunity</label>
      <input class="form-control" id="opp-find" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Find a deal or a customer">
      <button class="btn small secondary">Find</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($view === 'board'): ?>
  <?php $bc = $board['columns']; ?>
  <div style="overflow-x:auto;margin-top:16px">
    <div style="display:flex;gap:12px;min-width:min-content;align-items:flex-start">
      <?php foreach ($board['stages'] as $s): $col = $bc[(int)$s['id']] ?? ['rows'=>[],'value'=>0,'weighted'=>0]; ?>
        <div class="panel" style="min-width:250px;max-width:250px;padding:12px">
          <div style="display:flex;justify-content:space-between;align-items:baseline">
            <b style="font-size:13.5px"><?= e($s['name']) ?></b>
            <span class="muted" style="font-size:12px"><?= count($col['rows']) ?></span>
          </div>
          <div class="muted" style="font-size:12px;margin-bottom:8px">
            <?= $col['value'] ? fmoney_short($col['value']) : '—' ?>
            <?= (int)$s['probability'] ? ' · ' . (int)$s['probability'] . '%' : '' ?>
            <?= $col['weighted'] ? '<br>weighted ' . fmoney_short($col['weighted']) : '' ?>
          </div>
          <?php foreach ($col['rows'] as $o): $st = opp_stalled($o); ?>
            <a href="/opportunity?id=<?= (int)$o['id'] ?>" style="display:block;border:1px solid var(--line);border-radius:8px;padding:9px;margin-bottom:8px;text-decoration:none<?= $st ? ';border-left:3px solid var(--bad)' : '' ?>">
              <b style="font-size:13px"><?= e($o['name']) ?></b>
              <div class="muted" style="font-size:11.5px;margin-top:2px"><?= e($o['client_name'] ?: $o['partner_name']) ?></div>
              <div class="muted" style="font-size:11.5px"><?= e($o['ref']) ?><?= $o['value'] ? ' · ' . fmoney_short($o['value']) : '' ?></div>
              <?php if ($st): ?><div style="margin-top:4px"><span class="pill p-bad" style="font-size:10.5px"><?= opp_days_in_stage($o) ?>d</span></div><?php endif; ?>
            </a>
          <?php endforeach; ?>
          <?php if (!$col['rows']): ?><p class="muted" style="font-size:12px;margin:0">—</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($losses): ?>
    <div class="panel" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Why deals were lost <span class="muted" style="font-weight:400;font-size:13px">— last 12 months</span></h3>
      <p class="muted" style="font-size:13px;margin:0 0 10px">Unanswerable before opportunities existed: a lost quotation had a reason, but a deal lost before anybody quoted had nowhere to record one.</p>
      <table class="dt">
        <caption class="sr-only">Loss reasons</caption>
        <thead><tr><th scope="col">Reason</th><th scope="col" class="num">Deals</th><th scope="col" class="num">Estimated value</th></tr></thead>
        <tbody>
        <?php foreach ($losses as $l): ?>
          <tr><td><?= e($l['reason']) ?></td><td class="num"><?= (int)$l['n'] ?></td><td class="num"><?= e(fmoney($l['value'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="font-size:12.5px;margin:10px 0 0">
        Won <?= (int)$win['won'] ?> (<?= e(fmoney($win['won_value'])) ?>) against lost <?= (int)$win['lost'] ?> (<?= e(fmoney($win['lost_value'])) ?>).
      </p>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div style="margin-top:16px">
    <?= dt_render($dt, $rows, $total, [
          'caption'     => 'Opportunity register',
          'search'      => true,
          'search_hint' => 'Deal, customer or reference…',
          'export'      => true,
          'empty'       => 'Nothing yet. An opportunity opens from a lead, or straight from here when a customer tells you what they are planning.',
        ]) ?>
  </div>
<?php endif; ?>
