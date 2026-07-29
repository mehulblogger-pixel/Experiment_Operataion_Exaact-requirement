<?php
$ym    = sprintf('%04d-%02d', $yr, $mon);
$mName = date('F Y', mktime(0, 0, 0, $mon, 1, $yr));
$qs    = function ($t) use ($sel, $ym) { return '/office-finance?tab=' . $t . '&office=' . (int)$sel . '&m=' . $ym; };
$selOff = null;
foreach ($offices as $o) if ((int)$o['id'] === (int)$sel) $selOff = $o;
?>
<div class="crumbs"><a href="/">Home</a> › <?= e(T('office')) ?> costs</div>
<div class="master-head">
  <div><h1><?= e(TH('office')) ?> costs &amp; overheads</h1>
    <p class="sub">What each <?= e(Tl('office')) ?> spends, so profit by <?= e(Tl('sbu')) ?>, activity code and <?= e(Tl("boss")) ?> is a real number rather than a percentage.</p></div>
  <div><a class="btn ghost" href="/m/office-expense-heads">Edit expense heads</a></div>
</div>

<div class="chip-row">
  <a class="ct<?= $tab === 'actual' ? ' on' : '' ?>" href="<?= e($qs('actual')) ?>">Actual costs, month by month</a>
  <a class="ct<?= $tab === 'sbus'   ? ' on' : '' ?>" href="<?= e($qs('sbus')) ?>"><?= e(TP('sbu')) ?> in this <?= e(Tl('office')) ?></a>
  <a class="ct<?= $tab === 'pct'    ? ' on' : '' ?>" href="<?= e($qs('pct')) ?>">Overhead % (fallback)</a>
</div>

<?php if (!$offices): ?>
  <div class="msg-warning">No <?= e(Tl('office')) ?> is linked to your account. Ask an admin to set your home <?= e(Tl('office')) ?>.</div>
<?php else: ?>

<?php if ($tab !== 'pct'): ?>
<div class="panel">
  <form method="get" action="/office-finance" class="inline-add" style="align-items:flex-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="ff"><label><?= e(T('office')) ?></label>
      <select class="form-control searchable" name="office" onchange="this.form.submit()">
        <?php foreach ($offices as $o): ?>
          <option value="<?= (int)$o['id'] ?>"<?= (int)$o['id'] === (int)$sel ? ' selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['code']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <?php if ($tab === 'actual'): ?>
      <div class="ff"><label>Month</label><input class="form-control" type="month" name="m" value="<?= e($ym) ?>" onchange="this.form.submit()"></div>
    <?php endif; ?>
    <button class="btn ghost" type="submit">Show</button>
  </form>
  <p class="muted" style="margin-top:6px"><?= e($basisText) ?></p>
</div>
<?php endif; ?>

<?php if ($tab === 'actual'): ?>
  <?php if ($frozen): ?>
    <div class="msg-warning"><strong><?= e($mName) ?> is frozen.</strong> The costs for this month have been calculated and closed. Reopen it on the month-end run if a figure has to change.</div>
  <?php endif; ?>
  <?php if (!$heads): ?>
    <div class="msg-warning">No expense heads are set up yet. <a href="/m/office-expense-heads/new">Add the first one</a> — rent, electricity, laptops, contingency, whatever this business actually pays for.</div>
  <?php else: ?>
  <div class="panel">
    <div class="master-head" style="margin-bottom:6px">
      <div><h3 class="tab-sub"><?= e($mName) ?> — <?= e($selOff['name'] ?? '') ?></h3>
        <p class="sub">Only what the <?= e(Tl('office')) ?> pays out. <strong>Do not enter salary here</strong> — salaries come off each person's own record, and entering them twice would double the cost.</p></div>
      <div class="kpi-row" style="margin:0"><div class="kpi"><span class="k-lab">Entered this month</span><span class="k-val">₹<?= number_format((float)$spent, 2) ?></span></div></div>
    </div>
    <form method="post" action="/office-finance">
      <input type="hidden" name="_do" value="expenses">
      <input type="hidden" name="office_id" value="<?= (int)$sel ?>">
      <input type="hidden" name="m" value="<?= e($ym) ?>">
      <div class="tbl-scroll" style="overflow-x:auto">
      <table class="grid">
        <tr><th>Expense head</th><th style="width:170px">Amount (₹)</th><th>Spreads across <?= e(TP('sbu')) ?> by</th><th>Note</th></tr>
        <?php foreach ($heads as $h): $r = $entered[(int)$h['id']] ?? null; ?>
        <tr>
          <td><strong><?= e($h['label']) ?></strong> <span class="muted"><?= e($h['code']) ?></span>
              <?php if (!empty($h['retired'])): ?><span class="pill p-warn">no longer in use</span><?php endif; ?>
              <?php if (!empty($h['notes'])): ?><div class="muted"><?= e($h['notes']) ?></div><?php endif; ?></td>
          <td><input class="form-control" type="number" step="0.01" min="0" inputmode="decimal"
                     name="amt[<?= (int)$h['id'] ?>]" value="<?= $r ? e(rtrim(rtrim(number_format((float)$r['amount'], 2, '.', ''), '0'), '.')) : '' ?>"
                     <?= $frozen ? 'disabled' : '' ?> placeholder="—"></td>
          <td><?= e(ALLOC_BASES[$h['alloc_basis']] ?? ALLOC_BASES['EQUAL']) ?></td>
          <td><input class="form-control" type="text" name="note[<?= (int)$h['id'] ?>]"
                     value="<?= e($r['notes'] ?? '') ?>" <?= $frozen ? 'disabled' : '' ?> placeholder="optional"></td>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <?php if (!$frozen): ?>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn" type="submit">Save <?= e($mName) ?></button>
        <button class="btn ghost" type="submit" name="_do" value="copy"
                onclick="return confirm('Copy last month’s figures into <?= e($mName) ?>? Anything already typed here will be replaced.')">Copy last month</button>
      </div>
      <?php endif; ?>
    </form>
    <p class="muted" style="margin-top:10px">Leave a head blank if nothing was spent on it. A blank is not a zero — it simply means nothing was entered, and the head is left out of the month.</p>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'sbus'): ?>
  <div class="panel">
    <h3 class="tab-sub"><?= e(TP('sbu')) ?> running in <?= e($selOff['name'] ?? '') ?></h3>
    <p class="sub">This is the list the cost screens use: tick a <?= e(Tl('sbu')) ?> here and it gets a box on every split, a column in the month-end run and a line in the <?= e(Tl('sbu')) ?> P&amp;L. Tick nothing and the <?= e(Tl('office')) ?> is treated as running all of them.</p>
    <form method="post" action="/office-finance">
      <input type="hidden" name="_do" value="sbus">
      <input type="hidden" name="office_id" value="<?= (int)$sel ?>">
      <div class="checkgrid">
        <?php foreach ($allSbus as $code => $label): ?>
          <label class="ff-check"><input type="checkbox" name="sbus[]" value="<?= e($code) ?>"
            <?= ($offSbuRaw !== '' && in_array($code, $offSbus, true)) ? 'checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
      <?php if ($offSbuRaw === ''): ?>
        <p class="muted">Nothing ticked yet, so all <?= count($allSbus) ?> <?= e(Tlp('sbu')) ?> are in play for this <?= e(Tl('office')) ?>.</p>
      <?php endif; ?>
      <h3 class="tab-sub" style="margin-top:18px">A <?= e(Tl('engineer')) ?> with no chargeable day at all that month</h3>
      <p class="sub">Rare, but it has to land somewhere. You choose where.</p>
      <div class="form-grid">
        <div class="ff-wide"><label>Spread their cost</label>
          <select class="form-control" name="idle_basis">
            <option value="">Use the company default (<?= e(IDLE_BASES[$idleUsed] ?? '') ?>)</option>
            <?php foreach (IDLE_BASES as $k => $lab): ?>
              <option value="<?= e($k) ?>"<?= $idleBasis === $k ? ' selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div style="margin-top:12px"><button class="btn" type="submit">Save</button></div>
    </form>
  </div>

<?php else: ?>
  <?php if ($canGlobal): ?>
  <div class="panel">
    <h3 class="tab-sub">Global default <span class="muted">(used when an <?= e(Tl('office')) ?> leaves its own blank)</span></h3>
    <form method="post" action="/office-finance" class="inline-add" style="align-items:flex-end">
      <input type="hidden" name="global_default" value="1">
      <div class="ff"><label>Overhead %</label><input class="form-control" style="width:110px" type="number" step="0.01" name="overhead_pct" value="<?= e($defOh) ?>"></div>
      <div class="ff"><label>Contingency %</label><input class="form-control" style="width:110px" type="number" step="0.01" name="contingency_pct" value="<?= e($defCg) ?>"></div>
      <button class="btn" type="submit">Save default</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h3 class="tab-sub">Per-<?= e(Tl('office')) ?> percentages</h3>
    <p class="sub">The older method: one multiplier standing in for rent, the back office and everything else. Kept so an <?= e(Tl('office')) ?> that has not entered real figures still gets a cost. Once real salaries and <?= e(Tl('office')) ?> costs are entered, that <?= e(Tl('office')) ?> uses those instead and these boxes stop being read.</p>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="grid">
      <tr><th><?= e(T('office')) ?></th><th>Overhead %</th><th>Contingency %</th><th>Now using</th><th></th></tr>
      <?php foreach ($offices as $o): $fid = 'of_' . (int)$o['id']; $real = office_uses_real_costs($o['id']); ?>
      <tr>
        <td><form id="<?= $fid ?>" method="post" action="/office-finance"><input type="hidden" name="office_id" value="<?= (int)$o['id'] ?>"></form>
          <strong><?= e($o['name']) ?></strong> <span class="muted">(<?= e($o['code']) ?>)</span></td>
        <td><input form="<?= $fid ?>" class="form-control" style="width:110px" type="number" step="0.01" name="overhead_pct" value="<?= $o['overhead_pct'] === null ? '' : e($o['overhead_pct']) ?>" placeholder="<?= e($defOh) ?>"></td>
        <td><input form="<?= $fid ?>" class="form-control" style="width:110px" type="number" step="0.01" name="contingency_pct" value="<?= $o['contingency_pct'] === null ? '' : e($o['contingency_pct']) ?>" placeholder="<?= e($defCg) ?>"></td>
        <td><span class="pill <?= $real ? 'p-ok' : 'p-mut' ?>"><?= $real ? 'Real costs' : 'Percentage' ?></span></td>
        <td><button form="<?= $fid ?>" class="btn small" type="submit">Save</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="muted" style="margin-top:8px">Loaded labour = (CTC ÷ 12 × (1 + Overhead %)) ÷ working days. Contingency % is added on top of (labour + expenses + sub-con) as a buffer, reducing margin.</p>
  </div>
<?php endif; ?>

<?php endif; ?>
