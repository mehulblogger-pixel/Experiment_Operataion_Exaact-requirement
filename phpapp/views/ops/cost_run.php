<?php
$ym    = sprintf('%04d-%02d', $yr, $mon);
$mName = date('F Y', mktime(0, 0, 0, $mon, 1, $yr));
$selOff = null;
foreach ($offices as $o) if ((int)$o['id'] === (int)$sel) $selOff = $o;
$kindLabel = [
    'INSPECTOR_WORKED' => 'Days worked on a job',
    'INSPECTOR_IDLE'   => 'Non-chargeable days',
    'STAFF_SALARY'     => 'Salary, split by percentage',
    'SUBCON'           => 'Sub-contractor',
    'OFFICE_EXPENSE'   => 'Office costs',
];
$basisLabel = [
    'DAYS_WORKED' => 'the days actually worked',
    'OWN_MIX'     => 'their own mix of work that month',
    'PERSON_SPLIT'=> 'the percentages on their record',
    'OFFICE_MIX'  => 'the office’s overall mix',
    'OWN_SPLIT'   => 'the percentages on their record',
    'EQUAL'       => 'equally',
    'MANDAYS'     => 'man-days worked',
    'REVENUE'     => 'revenue earned',
    'HEADCOUNT'   => 'headcount',
    'THE_JOB_IT_WAS_FOR' => 'the job it was paid for',
];
?>
<div class="crumbs"><a href="/">Home</a> › Month-end cost run</div>
<div class="master-head">
  <div><h1>Month-end cost run</h1>
    <p class="sub">Turns one month for one <?= e(Tl('office')) ?> into a cost against each <?= e(Tl('sbu')) ?>, activity code and <?= e(T('boss')) ?> number. Look at it first; nothing is stored until you say so.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn ghost" href="/office-finance?tab=actual&amp;office=<?= (int)$sel ?>&amp;m=<?= e($ym) ?>">Enter <?= e(Tl('office')) ?> costs</a>
    <a class="btn ghost" href="/sbu-pl?office=<?= (int)$sel ?>&amp;m=<?= e($ym) ?>"><?= e(T('sbu')) ?> P&amp;L</a>
    <a class="btn ghost" href="/mis?fy=<?= e(fy_of($ym . '-01')) ?>&amp;m=<?= e($ym) ?>&amp;office=<?= (int)$sel ?>">Management dashboard</a>
  </div>
</div>

<div class="panel">
  <form method="get" action="/cost-run" class="inline-add" style="align-items:flex-end">
    <div class="ff"><label><?= e(T('office')) ?></label>
      <select class="form-control searchable" name="office" onchange="this.form.submit()">
        <?php foreach ($offices as $o): ?>
          <option value="<?= (int)$o['id'] ?>"<?= (int)$o['id'] === (int)$sel ? ' selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['code']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Month</label>
      <input class="form-control" type="month" name="m" value="<?= e($ym) ?>" onchange="this.form.submit()"></div>
    <button class="btn ghost" type="submit">Show</button>
  </form>
</div>

<?php if (!$sel): ?>
  <div class="msg-warning">No <?= e(Tl('office')) ?> to run. Add one under Organisation &amp; people first.</div>
<?php else: ?>

<?php if ($run['status'] === 'FROZEN'): ?>
  <div class="msg-success"><strong><?= e($mName) ?> is closed.</strong>
    Calculated by <?= e($run['run_by']) ?> on <?= e(substr((string)$run['run_at'], 0, 10)) ?> and frozen, so nothing can change it now.
    <?= e(Tlp('sbu')) ?> and profitability read these figures.</div>
<?php elseif ($run['status'] === 'OPEN'): ?>
  <div class="msg-info"><strong><?= e($mName) ?> has been calculated but is not closed.</strong>
    The figures below are stored and can still be recalculated. Close the month when you are satisfied.</div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi"><span class="k-lab">Total cost for <?= e($mName) ?></span><span class="k-val">₹<?= number_format($preview['total'], 2) ?></span></div>
  <div class="kpi"><span class="k-lab">Inspection <?= e(Tlp('engineer')) ?></span><span class="k-val">₹<?= number_format($byKind['INSPECTOR_WORKED'] + $byKind['INSPECTOR_IDLE'], 2) ?></span></div>
  <div class="kpi"><span class="k-lab">Everybody else</span><span class="k-val">₹<?= number_format($byKind['STAFF_SALARY'], 2) ?></span></div>
  <div class="kpi"><span class="k-lab">Sub-contractors</span><span class="k-val">₹<?= number_format($byKind['SUBCON'], 2) ?></span></div>
  <div class="kpi"><span class="k-lab"><?= e(TH('office')) ?> costs</span><span class="k-val">₹<?= number_format($byKind['OFFICE_EXPENSE'], 2) ?></span></div>
</div>

<?php if ($preview['warnings']): ?>
<div class="panel">
  <h3 class="tab-sub">Worth looking at before you close the month</h3>
  <ul style="margin:6px 0 0 18px">
    <?php foreach ($preview['warnings'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
  </ul>
  <p class="muted" style="margin-top:8px">None of these stop the run. They are the places where the system had to make a choice, or where a figure is missing.</p>
</div>
<?php endif; ?>

<?php if (!$preview['rows']): ?>
  <div class="msg-warning">There is nothing to cost for <?= e($mName) ?>. Add salaries on people's records and
    <?= e(Tl('office')) ?> costs for the month, then come back.</div>
<?php else: ?>

<div class="panel">
  <h3 class="tab-sub">By <?= e(Tl('sbu')) ?></h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th><?= e(T('sbu')) ?></th>
        <?php foreach ($kindLabel as $k => $lab): ?><th style="text-align:right"><?= e($lab) ?></th><?php endforeach; ?>
        <th style="text-align:right">Total</th><th style="width:120px">Share</th></tr>
    <?php foreach ($bySbu as $sbu => $cells): ?>
    <tr>
      <td><strong><?= e($sbuLabels[$sbu] ?? $sbu) ?></strong></td>
      <?php foreach ($kindLabel as $k => $lab): ?>
        <td style="text-align:right"><?= $cells[$k] ? '₹' . number_format($cells[$k], 2) : '<span class="muted">—</span>' ?></td>
      <?php endforeach; ?>
      <td style="text-align:right"><strong>₹<?= number_format($cells['total'], 2) ?></strong></td>
      <td><?= $preview['total'] ? round($cells['total'] / $preview['total'] * 100, 1) : 0 ?>%</td>
    </tr>
    <?php endforeach; ?>
    <tr><td><strong>All <?= e(Tlp('sbu')) ?></strong></td>
      <?php foreach ($kindLabel as $k => $lab): ?><td style="text-align:right"><strong>₹<?= number_format($byKind[$k], 2) ?></strong></td><?php endforeach; ?>
      <td style="text-align:right"><strong>₹<?= number_format($preview['total'], 2) ?></strong></td><td></td></tr>
  </table>
  </div>
  <p class="muted" style="margin-top:8px">Every rupee the <?= e(Tl('office')) ?> spends appears once, in exactly one of these columns. The row total and the column total are the same number read two ways.</p>
</div>

<div class="panel">
  <h3 class="tab-sub">Line by line <span class="muted">— where each figure came from</span></h3>
  <div class="tbl-scroll" style="overflow-x:auto;max-height:520px">
  <table class="grid">
    <tr><th>What</th><th>Who / which head</th><th><?= e(T('sbu')) ?></th><th>Activity</th><th><?= e(T('boss')) ?></th><th>Contract</th><th>Spread by</th><th style="text-align:right">₹</th></tr>
    <?php foreach ($preview['rows'] as $r): ?>
    <tr>
      <td><?= e($kindLabel[$r['source_kind']] ?? $r['source_kind']) ?></td>
      <td><?= e($r['source_label']) ?></td>
      <td><?= e($sbuLabels[$r['sbu']] ?? $r['sbu']) ?></td>
      <td class="muted"><?= $r['activity'] !== '' ? e(lk_value_path($r['activity'])) : '—' ?></td>
      <td class="muted"><?= $r['boss_id'] ? e($bossCodes[(int)$r['boss_id']] ?? ('#' . (int)$r['boss_id'])) : '—' ?></td>
      <td class="muted"><?= ($r['contract_number'] ?? '') !== '' ? e($r['contract_number']) : '—' ?></td>
      <td class="muted"><?= e($basisLabel[$r['basis']] ?? strtolower(str_replace('_', ' ', $r['basis']))) ?></td>
      <td style="text-align:right">₹<?= number_format($r['amount'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>

<?php if ($canRun): ?>
<div class="panel">
  <h3 class="tab-sub">Store it</h3>
  <form method="post" action="/cost-run" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="office_id" value="<?= (int)$sel ?>">
    <input type="hidden" name="m" value="<?= e($ym) ?>">
    <?php if ($run['status'] === 'FROZEN'): ?>
      <button class="btn ghost" type="submit" name="do" value="reopen"
        onclick="return confirm('Reopen <?= e($mName) ?>? The figures stay as they are until you calculate again.')">Reopen the month</button>
      <span class="muted">Closed on <?= e(substr((string)$run['run_at'], 0, 10)) ?>. Reopen it if a salary or a cost has to be corrected.</span>
    <?php else: ?>
      <button class="btn" type="submit" name="do" value="commit">Calculate and store <?= e($mName) ?></button>
      <?php if ($run['status'] === 'OPEN'): ?>
        <button class="btn secondary" type="submit" name="do" value="freeze"
          onclick="return confirm('Close <?= e($mName) ?>? After this the figures cannot change unless the month is reopened.')">Close the month</button>
      <?php endif; ?>
      <span class="muted">Storing replaces whatever was stored for this month before — it never adds to it.</span>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>
