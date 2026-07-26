<?php
$ym    = sprintf('%04d-%02d', $yr, $mon);
$mName = date('F Y', mktime(0, 0, 0, $mon, 1, $yr));
$money = function ($v) { return ($v < 0 ? '−₹' : '₹') . number_format(abs((float)$v), 0); };
$pill  = function ($v) { return $v > 0 ? 'p-ok' : ($v < 0 ? 'p-bad' : 'p-mut'); };
?>
<div class="crumbs"><a href="/">Home</a> › <?= e(T('sbu')) ?> profit &amp; loss</div>
<div class="master-head">
  <div><h1><?= e(T('sbu')) ?> profit &amp; loss — <?= e($mName) ?></h1>
    <p class="sub">Revenue billed against the cost that landed on it. Real money on both sides.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn ghost" href="/mis?fy=<?= e(fy_of($ym . '-01')) ?>&amp;m=<?= e($ym) ?>&amp;office=<?= (int)$sel ?>">Management dashboard</a>
    <a class="btn ghost" href="/cost-run?office=<?= (int)$sel ?>&amp;m=<?= e($ym) ?>">Month-end run</a>
    <a class="btn ghost" href="/office-finance?tab=actual&amp;office=<?= (int)$sel ?>&amp;m=<?= e($ym) ?>">Enter <?= e(Tl('office')) ?> costs</a>
  </div>
</div>

<div class="panel">
  <form method="get" action="/sbu-pl" class="inline-add" style="align-items:flex-end">
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

<?php if (!$stored): ?>
  <div class="msg-warning"><strong><?= e($mName) ?> has not been calculated yet.</strong>
    Costs are only shared out across <?= e(Tlp('sbu')) ?> when the month-end run has been stored.
    <a href="/cost-run?office=<?= (int)$sel ?>&amp;m=<?= e($ym) ?>">Open the month-end run</a> — you can look at it
    before storing anything. Revenue below is what has been billed; the cost columns stay empty until then.</div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi"><span class="k-lab">Revenue billed</span><span class="k-val"><?= $money($tot['revenue']) ?></span></div>
  <div class="kpi"><span class="k-lab">Cost allocated</span><span class="k-val"><?= $money($tot['cost']) ?></span></div>
  <div class="kpi"><span class="k-lab"><?= e(TH('office')) ?> profit</span>
    <span class="k-val"><?= $money($tot['revenue'] - $tot['cost']) ?></span></div>
  <div class="kpi"><span class="k-lab">Margin</span>
    <span class="k-val"><?= $tot['revenue'] > 0 ? round(($tot['revenue'] - $tot['cost']) / $tot['revenue'] * 100, 1) . '%' : '—' ?></span></div>
</div>

<div class="panel">
  <h3 class="tab-sub">By <?= e(Tl('sbu')) ?></h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th><?= e(T('sbu')) ?></th><th style="text-align:right">Revenue</th>
        <th style="text-align:right">Engineers</th><th style="text-align:right">Other salary</th>
        <th style="text-align:right">Sub-contractor</th>
        <th style="text-align:right"><?= e(TH('office')) ?> costs</th>
        <th style="text-align:right">Total cost</th><th style="text-align:right">Profit</th><th style="width:90px">Margin</th></tr>
    <?php foreach ($rows as $sbu => $r): $p = $r['revenue'] - $r['cost']; ?>
    <tr>
      <td><strong><?= e($sbuLabels[$sbu] ?? $sbu) ?></strong></td>
      <td style="text-align:right"><?= $money($r['revenue']) ?></td>
      <td style="text-align:right"><?= $money($r['engineers']) ?></td>
      <td style="text-align:right"><?= $money($r['staff']) ?></td>
      <td style="text-align:right"><?= $money($r['subcon'] ?? 0) ?></td>
      <td style="text-align:right"><?= $money($r['office']) ?></td>
      <td style="text-align:right"><?= $money($r['cost']) ?></td>
      <td style="text-align:right"><strong><?= $money($p) ?></strong></td>
      <td><span class="pill <?= $pill($p) ?>"><?= $r['revenue'] > 0 ? round($p / $r['revenue'] * 100, 1) . '%' : '—' ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9">Nothing billed and nothing costed for <?= e($mName) ?>.</td></tr><?php endif; ?>
  </table>
  </div>
  <p class="muted" style="margin-top:8px">A <?= e(Tl('sbu')) ?> can show a loss while the branch as a whole is profitable — that is the point of the split. Revenue counts a <?= e(Tl('job')) ?> in the month its inspection finished.</p>
</div>

<div class="panel">
  <h3 class="tab-sub">By activity code</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>Activity</th><th><?= e(T('sbu')) ?></th><th style="text-align:right">Revenue</th>
        <th style="text-align:right">Cost booked directly</th><th style="text-align:right">Profit</th></tr>
    <?php foreach ($byActivity as $key => $a): $p = $a['revenue'] - $a['cost']; ?>
    <tr>
      <td><strong><?= e($a['label']) ?></strong></td>
      <td class="muted"><?= e($sbuLabels[$a['sbu']] ?? $a['sbu']) ?></td>
      <td style="text-align:right"><?= $money($a['revenue']) ?></td>
      <td style="text-align:right"><?= $money($a['cost']) ?></td>
      <td style="text-align:right"><strong><?= $money($p) ?></strong></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$byActivity): ?><tr><td colspan="5">No activity code carried any work in <?= e($mName) ?>.</td></tr><?php endif; ?>
  </table>
  </div>
  <p class="muted" style="margin-top:8px">Only the cost that names an activity code appears here — an engineer's days on that work. Shared costs stop at the <?= e(Tl('sbu')) ?> line and are not pushed down onto activity codes or <?= e(T('boss')) ?> numbers.</p>
</div>

<div class="panel">
  <h3 class="tab-sub">By <?= e(T('boss')) ?> number <span class="muted">— what each order directly caused</span></h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th><?= e(T('boss')) ?></th><th><?= e(T('client')) ?></th><th><?= e(T('sbu')) ?></th>
        <th style="text-align:right">Revenue</th><th style="text-align:right">Engineer time</th>
        <th style="text-align:right">Expenses</th><th style="text-align:right">Sub-con</th>
        <th style="text-align:right">Profit</th><th style="width:90px">Margin</th></tr>
    <?php foreach ($byBoss as $b): $p = $b['revenue'] - $b['labour'] - $b['expenses'] - $b['subcon']; ?>
    <tr>
      <td><strong><?= e($b['boss_number']) ?></strong></td>
      <td class="muted"><?= e($b['client']) ?></td>
      <td class="muted"><?= e($sbuLabels[$b['sbu']] ?? $b['sbu']) ?></td>
      <td style="text-align:right"><?= $money($b['revenue']) ?></td>
      <td style="text-align:right"><?= $money($b['labour']) ?></td>
      <td style="text-align:right"><?= $money($b['expenses']) ?></td>
      <td style="text-align:right"><?= $money($b['subcon']) ?></td>
      <td style="text-align:right"><strong><?= $money($p) ?></strong></td>
      <td><span class="pill <?= $pill($p) ?>"><?= $b['revenue'] > 0 ? round($p / $b['revenue'] * 100, 1) . '%' : '—' ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$byBoss): ?><tr><td colspan="9">No <?= e(T('boss')) ?> number was worked in <?= e($mName) ?>.</td></tr><?php endif; ?>
  </table>
  </div>
  <p class="muted" style="margin-top:8px"><?= e($basisText) ?>
    An order is judged on what it directly caused — no share of the branch manager or the rent is pushed onto it.
    Those sit at the <?= e(Tl('sbu')) ?> line above, which is where you see whether the branch as a whole is ahead.</p>
</div>
