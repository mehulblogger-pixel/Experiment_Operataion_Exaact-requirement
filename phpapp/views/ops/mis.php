<?php
$tot = $S['tot'];
$sal = $S['seeSalary'];
$money = function ($v) { return ($v < 0 ? '−₹' : '₹') . number_format(abs((float)$v), 0); };
$pill  = function ($v) { return $v > 0 ? 'p-ok' : ($v < 0 ? 'p-bad' : 'p-mut'); };
$avg   = function ($r) { return $r['delayCounted'] ? round($r['delayDays'] / $r['delayCounted'], 1) : null; };
$period = $F['m'] !== '' ? ($F['months'][$F['m']] ?? $F['m']) : 'FY ' . $F['fy'];
$LY = $S['lastYear'];
// "up 18% on last year" only means something if there WAS a last year.
$vs = function ($now, $then, $money = false) use ($LY) {
    $pct = mis_change($now, $then);
    if ($pct === null) return '<span class="muted">no figure a year ago</span>';
    $cls = $pct > 0 ? 'p-ok' : ($pct < 0 ? 'p-bad' : 'p-mut');
    return '<span class="pill ' . $cls . '">' . ($pct > 0 ? '+' : '') . $pct . '%</span>'
         . ' <span class="muted">vs ' . ($money ? '₹' . number_format((float)$then, 0) : (float)$then) . ' last year</span>';
};
$qs = function ($extra = []) use ($F) {
    $q = ['fy'=>$F['fy'], 'm'=>$F['m'], 'sbu'=>$F['sbu'], 'activity'=>$F['activity'],
          'office'=>$F['office'] ?: '', 'ibo'=>$F['ibo'] ?: '', 'inspector'=>$F['inspector'] ?: '',
          'client'=>$F['client'] ?: ''];
    return '?' . http_build_query(array_filter(array_merge($q, $extra), function ($v) { return $v !== '' && $v !== null; }));
};
// One table renderer — eight tables that disagreed about their own columns is
// exactly the inconsistency this screen exists to remove.
$table = function ($rows, $head, $exportKey) use ($money, $pill, $avg, $sal, $qs) {
    ?>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="grid">
      <tr><th><?= e($head) ?></th><th style="text-align:right">Inspections</th>
          <th style="text-align:right">Closed</th><th style="text-align:right">Man-days</th>
          <th style="text-align:right">Revenue</th>
          <?php if ($sal): ?><th style="text-align:right">Direct cost</th>
          <th style="text-align:right">Profit</th><th style="width:80px">Margin</th><?php endif; ?>
          <th style="text-align:right">Late</th><th style="text-align:right">Avg delay</th></tr>
      <?php foreach ($rows as $r): $a = $avg($r); ?>
      <tr>
        <td><strong><?= e($r['label']) ?></strong></td>
        <td style="text-align:right"><?= (int)$r['jobs'] ?></td>
        <td style="text-align:right"><?= (int)$r['closed'] ?><?= $r['open'] ? ' <span class="muted">+' . (int)$r['open'] . ' open</span>' : '' ?></td>
        <td style="text-align:right"><?= round($r['mandays'], 1) ?></td>
        <td style="text-align:right"><?= $money($r['revenue']) ?></td>
        <?php if ($sal): ?>
          <td style="text-align:right"><?= $money($r['cost']) ?></td>
          <td style="text-align:right"><strong><?= $money($r['profit']) ?></strong></td>
          <td><span class="pill <?= $pill($r['profit']) ?>"><?= $r['revenue'] > 0 ? round($r['profit'] / $r['revenue'] * 100, 1) . '%' : '—' ?></span></td>
        <?php endif; ?>
        <td style="text-align:right"><?= $r['late'] ? '<span class="pill p-warn">' . (int)$r['late'] . '</span>' : '<span class="muted">0</span>' ?></td>
        <td style="text-align:right"><?= $a === null ? '<span class="muted">—</span>' : ($a > 0 ? '+' : '') . $a ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="<?= $sal ? 10 : 7 ?>">Nothing in this period.</td></tr><?php endif; ?>
    </table>
    </div>
    <p style="margin-top:8px"><a class="btn small ghost" href="/mis<?= e($qs(['export' => $exportKey])) ?>">⬇ Download as CSV</a></p>
    <?php
};
?>
<div class="crumbs"><a href="/">Home</a> › Management dashboard</div>
<div class="master-head">
  <div><h1>Management dashboard</h1>
    <p class="sub">Every figure below is counted off one set of <?= e(Tlp('job')) ?>: the ones whose inspection fell inside <strong><?= e($period) ?></strong>. Narrow it any way you like — the numbers stay consistent with each other.</p></div>
</div>

<form method="get" action="/mis" class="filter-bar" style="flex-wrap:wrap;gap:8px">
  <div class="ff" style="margin:0"><label>Financial year</label>
    <select class="form-control" name="fy" onchange="this.form.m.value='';this.form.submit()">
      <?php foreach ($F['fyOpts'] as $o): ?><option value="<?= e($o) ?>"<?= $F['fy'] === $o ? ' selected' : '' ?>>FY <?= e($o) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label>Month</label>
    <select class="form-control" name="m" onchange="this.form.submit()">
      <option value="">Whole year</option>
      <?php foreach ($F['months'] as $k => $v): ?><option value="<?= e($k) ?>"<?= $F['m'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label><?= e(T('sbu')) ?></label>
    <select class="form-control searchable" name="sbu" onchange="this.form.submit()">
      <option value="">Every <?= e(Tl('sbu')) ?></option>
      <?php foreach ($sbuOpts as $k => $v): ?><option value="<?= e($k) ?>"<?= $F['sbu'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label>Activity code</label>
    <select class="form-control searchable" name="activity" onchange="this.form.submit()">
      <option value="">Every activity</option>
      <?php foreach ($activityOpts as $k => $v): ?><option value="<?= e($k) ?>"<?= $F['activity'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label>Executing <?= e(Tl('office')) ?></label>
    <select class="form-control searchable" name="office" onchange="this.form.submit()">
      <option value="">Every <?= e(Tl('office')) ?></option>
      <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"<?= $F['office'] === (int)$o['id'] ? ' selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label>Contracting <?= e(Tl('office')) ?></label>
    <select class="form-control searchable" name="ibo" onchange="this.form.submit()">
      <option value="">Every <?= e(Tl('office')) ?></option>
      <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"<?= $F['ibo'] === (int)$o['id'] ? ' selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label><?= e(TH('engineer')) ?></label>
    <select class="form-control searchable" name="inspector" onchange="this.form.submit()">
      <option value="">Everybody</option>
      <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>"<?= $F['inspector'] === (int)$i['id'] ? ' selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label><?= e(T('client')) ?></label>
    <select class="form-control searchable" name="client" onchange="this.form.submit()">
      <option value="">Every <?= e(Tl('client')) ?></option>
      <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $F['client'] === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option><?php endforeach; ?>
    </select></div>
  <div style="display:flex;gap:6px;align-items:flex-end">
    <button class="btn secondary" type="submit">Apply</button>
    <a class="btn secondary" href="/mis">Clear</a>
  </div>
</form>

<div class="kpi-row">
  <div class="kpi"><span class="k-lab">Inspections</span><span class="k-val"><?= (int)$tot['jobs'] ?></span>
    <span class="k-sub"><?= (int)$tot['closed'] ?> closed · <?= (int)$tot['open'] ?> still open</span>
    <span class="k-sub"><?= $vs($tot['jobs'], $LY['jobs']) ?></span></div>
  <div class="kpi"><span class="k-lab">Man-days</span><span class="k-val"><?= round($tot['mandays'], 1) ?></span>
    <span class="k-sub"><?= $tot['jobs'] ? round($tot['mandays'] / $tot['jobs'], 1) : 0 ?> per inspection</span>
    <span class="k-sub"><?= $vs(round($tot['mandays'], 1), round($LY['mandays'], 1)) ?></span></div>
  <div class="kpi"><span class="k-lab">Utilisation</span>
    <span class="k-val"><?= $S['utilisation'] === null ? '—' : $S['utilisation'] . '%' ?></span>
    <span class="k-sub"><?= $S['available'] ? round($tot['mandays'], 1) . ' worked of ' . (int)$S['available'] . ' available' : 'no engineers in this filter' ?></span>
    <span class="k-sub"><?= e($S['available'] ? 'working days less holidays, across every engineer in the filter' : '') ?></span></div>
  <div class="kpi"><span class="k-lab">Revenue</span><span class="k-val"><?= $money($tot['revenue']) ?></span>
    <span class="k-sub"><?= $money($tot['invoiced']) ?> invoiced · <?= $money($tot['paid']) ?> received</span>
    <span class="k-sub"><?= $vs($tot['revenue'], $LY['revenue'], true) ?></span></div>
  <?php if ($sal): ?>
  <div class="kpi"><span class="k-lab">Direct cost</span><span class="k-val"><?= $money($tot['cost']) ?></span>
    <span class="k-sub"><?= $money($tot['labour']) ?> time · <?= $money($tot['expenses']) ?> expenses · <?= $money($tot['subcon']) ?> sub-con</span></div>
  <div class="kpi"><span class="k-lab">Profit before branch costs</span><span class="k-val"><?= $money($tot['profit']) ?></span>
    <span class="k-sub"><?= $tot['revenue'] > 0 ? round($tot['profit'] / $tot['revenue'] * 100, 1) . '% margin' : 'no revenue' ?></span>
    <span class="k-sub"><?= $vs($tot['profit'], $LY['profit'], true) ?></span></div>
  <div class="kpi"><span class="k-lab">Branch costs allocated</span><span class="k-val"><?= $money($S['sharedTotal']) ?></span>
    <span class="k-sub"><?= $S['sharedTotal'] > 0 ? 'from the month-end run' : 'month-end run not stored yet' ?></span></div>
  <div class="kpi"><span class="k-lab">Profit after branch costs</span>
    <span class="k-val"><?= $money($tot['profit'] - $S['sharedTotal']) ?></span>
    <span class="k-sub"><?= $tot['revenue'] > 0 ? round(($tot['profit'] - $S['sharedTotal']) / $tot['revenue'] * 100, 1) . '% margin' : '—' ?></span></div>
  <?php endif; ?>
  <div class="kpi"><span class="k-lab">Scheduled late</span><span class="k-val"><?= (int)$tot['late'] ?></span>
    <span class="k-sub"><?= $tot['delayCounted'] ? round($tot['late'] / $tot['delayCounted'] * 100, 1) . '% of those with a wanted date' : 'no dates to compare' ?></span></div>
  <div class="kpi"><span class="k-lab">Average delay</span>
    <span class="k-val"><?= $tot['delayCounted'] ? (($tot['delayDays'] / $tot['delayCounted'] > 0 ? '+' : '') . round($tot['delayDays'] / $tot['delayCounted'], 1)) : '—' ?></span>
    <span class="k-sub">days against the <?= e(Tl('client')) ?>'s wanted date<?= $tot['delayCounted'] ? ', over ' . (int)$tot['delayCounted'] . ' inspections' : '' ?></span></div>
</div>

<?php if ($sal): ?>
<div class="msg-info">
  <strong>Two profit figures, on purpose.</strong>
  <em>Profit before branch costs</em> is what these inspections directly caused — engineer time, expenses, sub-contractor.
  <em>After branch costs</em> subtracts this <?= e(Tl('office')) ?>'s share of salaries and overheads from the
  <a href="/cost-run">month-end run</a>. Read the first when pricing a <?= e(Tl('job')) ?>, the second when asking whether the branch is ahead.
  <?php if ($S['sharedTotal'] <= 0): ?><br><strong>Nothing has been allocated yet for this period</strong> — the two figures are the same until the month-end run has been stored.<?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">By <?= e(Tl('sbu')) ?></h3>
  <?php $table($S['bySbu'], T('sbu'), 'sbu'); ?>
  <?php if ($sal && $S['shared']): ?>
    <h3 class="tab-sub" style="margin-top:18px">Branch costs allocated to each <?= e(Tl('sbu')) ?></h3>
    <p class="sub">From the month-end run. These are the salaries and overheads that belong to an <?= e(Tl('sbu')) ?> but not to any one <?= e(Tl('job')) ?>, so they are shown here rather than pushed onto the table above.</p>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="grid">
      <tr><th><?= e(T('sbu')) ?></th><th style="text-align:right">Revenue</th><th style="text-align:right">Direct cost</th>
          <th style="text-align:right">Branch costs</th><th style="text-align:right">Profit after everything</th><th style="width:80px">Margin</th></tr>
      <?php foreach ($S['bySbu'] as $code => $r): $sh = $S['shared'][$code]['amount'] ?? 0; $p = $r['profit'] - $sh; ?>
      <tr>
        <td><strong><?= e($r['label']) ?></strong></td>
        <td style="text-align:right"><?= $money($r['revenue']) ?></td>
        <td style="text-align:right"><?= $money($r['cost']) ?></td>
        <td style="text-align:right"><?= $money($sh) ?></td>
        <td style="text-align:right"><strong><?= $money($p) ?></strong></td>
        <td><span class="pill <?= $pill($p) ?>"><?= $r['revenue'] > 0 ? round($p / $r['revenue'] * 100, 1) . '%' : '—' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3 class="tab-sub">By activity code</h3>
  <?php $table($S['byActivity'], 'Activity code', 'activity'); ?>
</div>

<div class="panel">
  <h3 class="tab-sub">By <?= e(TH('engineer')) ?></h3>
  <?php $table($S['byInspector'], TH('engineer'), 'inspector'); ?>
</div>

<div class="panel">
  <h3 class="tab-sub">By executing <?= e(Tl('office')) ?></h3>
  <?php $table($S['byOffice'], T('office'), 'office'); ?>
</div>

<div class="panel">
  <h3 class="tab-sub">By contracting <?= e(Tl('office')) ?></h3>
  <p class="sub">Who sold the work, as against who did it. The two tables differ wherever one branch books work that another executes.</p>
  <?php $table($S['byIbo'], 'Contracting ' . Tl('office'), 'ibo'); ?>
</div>

<div class="panel">
  <h3 class="tab-sub">By <?= e(T('boss')) ?></h3>
  <?php $table($S['byBoss'], T('boss'), 'boss'); ?>
</div>

<div class="panel">
  <h3 class="tab-sub">By <?= e(Tl('client')) ?></h3>
  <?php $table($S['byClient'], T('client'), 'client'); ?>
</div>

<?php if ($F['m'] === ''): ?>
<div class="panel">
  <h3 class="tab-sub">Month by month across FY <?= e($F['fy']) ?></h3>
  <?php $table($S['byMonth'], 'Month', 'month'); ?>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">How these are counted</h3>
  <ul class="muted" style="margin:6px 0 0 18px">
    <li>An inspection belongs to the month it <strong>finished</strong> — falling back to when it started, then to when it was scheduled. Every table on this screen uses that one rule.</li>
    <li><strong>Revenue</strong> is the invoice where one has been raised, otherwise the expected credit on the <?= e(Tl('job')) ?>.</li>
    <li><strong>Delay</strong> is the days between the date the <?= e(Tl('client')) ?> asked for and the date actually scheduled. A <?= e(Tl('job')) ?> missing either date is left out of the average rather than counted as nil.</li>
    <li><strong>Direct cost</strong> is the <?= e(Tl('engineer')) ?>'s time on the <?= e(Tl('job')) ?>, its expenses and its sub-contractor. Branch salaries and overheads are not pushed onto a <?= e(Tl('job')) ?> — see <a href="/sbu-pl"><?= e(T('sbu')) ?> profit &amp; loss</a>.</li>
    <li><strong>Utilisation</strong> is man-days worked against days available — working days in the period, less holidays, across every <?= e(Tl('engineer')) ?> the filter covers.</li>
    <li><strong>Against last year</strong> compares the same slice of the calendar a year earlier (<?= e(fdate($LY['from'])) ?> to <?= e(fdate($LY['to'])) ?>), with every other filter unchanged. A period with nothing a year ago says so rather than showing a made-up percentage.</li>
    <li>You only see the <?= e(Tlp('office')) ?> your account is scoped to, exactly as on every register.</li>
  </ul>
</div>
