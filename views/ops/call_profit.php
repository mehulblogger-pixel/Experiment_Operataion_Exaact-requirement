<?php
$money = function ($v) { return ($v < 0 ? '−' : '') . cur_sym() . number_format(abs((float)$v), 0); };
$pill  = function ($v) { return $v > 0 ? 'p-ok' : ($v < 0 ? 'p-bad' : 'p-mut'); };
?>
<div class="crumbs"><a href="/">Home</a> › Profit by <?= e(Tl('call')) ?></div>
<div class="master-head">
  <div><h1>Profit by <?= e(Tl('call')) ?></h1>
    <p class="sub">What each inspection actually made, one line at a time. Revenue less the
      <?= e(Tl('engineer')) ?>'s time, the overhead on it, everything claimed against it and the contingency.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn ghost" href="/mis">Management dashboard</a>
    <a class="btn ghost" href="/sbu-pl"><?= e(T('sbu')) ?> profit &amp; loss</a>
  </div>
</div>

<div class="panel">
  <form method="get" action="/call-profit" class="inline-add" style="align-items:flex-end;flex-wrap:wrap">
    <div class="ff"><label>Financial year</label>
      <select class="form-control" name="fy" onchange="this.form.submit()">
        <?php foreach ($F['fyOpts'] as $o): ?><option value="<?= e($o) ?>" <?= $F['fy'] === $o ? 'selected' : '' ?>>FY <?= e($o) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Month</label>
      <select class="form-control" name="m" onchange="this.form.submit()">
        <option value="">Whole year</option>
        <?php foreach ($F['months'] as $k => $v): ?><option value="<?= e($k) ?>" <?= $F['m'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(T('office')) ?></label>
      <select class="form-control searchable" name="office" onchange="this.form.submit()">
        <option value="">All my <?= e(Tlp('office')) ?></option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$F['office'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(T('engineer')) ?></label>
      <select class="form-control searchable" name="inspector" onchange="this.form.submit()">
        <option value="">Everyone</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (int)$F['inspector'] === (int)$i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(T('sbu')) ?></label>
      <select class="form-control" name="sbu" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k => $v): ?><option value="<?= e($k) ?>" <?= $F['sbu'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn ghost" type="submit">Show</button>
  </form>
  <p class="muted" style="margin-top:6px">
    <?= $scopeOffice
      ? 'Narrowed to one ' . e(Tl('office')) . ', so the revenue is that branch\'s share and the costs are the ones it bore.'
      : 'Across every ' . e(Tl('office')) . ' you can see. On work shared between branches, each branch\'s share is counted once.' ?>
  </p>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="k-lab"><?= e(TP('job')) ?></span><span class="k-val"><?= (int)$tot['jobs'] ?></span></div>
  <div class="kpi"><span class="k-lab">Revenue</span><span class="k-val"><?= $money($tot['revenue']) ?></span></div>
  <div class="kpi"><span class="k-lab">Total cost</span><span class="k-val"><?= $money($tot['cost']) ?></span></div>
  <div class="kpi"><span class="k-lab">Profit</span>
    <span class="k-val" style="color:<?= $tot['profit'] >= 0 ? 'var(--ok,#1f8a4c)' : 'var(--bad,#c0392b)' ?>"><?= $money($tot['profit']) ?></span></div>
  <div class="kpi"><span class="k-lab">Margin</span>
    <span class="k-val"><?= $tot['margin'] === null ? '—' : $tot['margin'] . '%' ?></span></div>
</div>

<?php if ($losses): ?>
<div class="msg-warning">
  <strong>Worth a look —
    <?= count($losses) ?> <?= e(Tlp('job')) ?> lost money:</strong>
  <?php foreach ($losses as $l): ?>
    <div style="margin-top:4px"><a href="/job?id=<?= (int)$l['job']['id'] ?>"><?= e($l['job']['job_code']) ?></a>
      <?= e($l['job']['display_name'] ?: $l['job']['legal_name'] ?: '—') ?> —
      revenue <?= $money($l['p']['revenue']) ?>, cost <?= $money($l['p']['cost']) ?>,
      <strong><?= $money($l['p']['profit']) ?></strong>
      <?php
        // Say WHICH line took it under, because that is the thing to act on.
        $c = ['the ' . Tl('engineer') . '\'s time' => $l['p']['labour'], 'expenses at close' => $l['p']['expenses'],
              'voucher claims' => $l['p']['voucher'], 'sub-contractor' => $l['p']['subcon'], 'other costs' => $l['p']['other']];
        arsort($c); $biggest = key($c);
      ?>
      <span class="muted">— mostly <?= e($biggest) ?>.</span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">Every <?= e(Tl('job')) ?>, line by line</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <thead><tr>
      <th><?= e(ucfirst(Tl('job'))) ?></th><th><?= e(T('client')) ?></th>
      <th><?= e(T('engineer')) ?></th><th><?= e(T('office')) ?></th>
      <th class="num">Days</th>
      <th class="num">Revenue</th>
      <th class="num">Salary</th>
      <th class="num" title="The branch percentage on the salary">Overhead</th>
      <th class="num" title="Booked against the job when it closed">Expenses</th>
      <th class="num" title="Claimed by the engineer on the monthly voucher">Voucher</th>
      <th class="num">Sub-con</th><th class="num">Other</th>
      <th class="num">Contingency</th>
      <th class="num">Total cost</th><th class="num">Profit</th><th>Margin</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $j = $r['job']; $p = $r['p']; ?>
      <tr>
        <td><a href="/job?id=<?= (int)$j['id'] ?>"><b><?= e($j['job_code']) ?></b></a>
          <?php if (!empty($j['call_code'])): ?><div class="muted" style="font-size:11px"><?= e($j['call_code']) ?></div><?php endif; ?></td>
        <td><?= e($j['display_name'] ?: $j['legal_name'] ?: '—') ?>
          <?php if (!empty($j['boss_number'])): ?><div class="muted" style="font-size:11px"><?= e($j['boss_number']) ?></div><?php endif; ?></td>
        <td><?= e($j['inspector_name'] ?: '—') ?></td>
        <td class="muted"><?= e($j['office_name'] ?: '—') ?></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$p['mandays'], 1), '0'), '.') ?></td>
        <td class="num"><?= $money($p['revenue']) ?></td>
        <td class="num"><?= can_see_salary() ? $money($p['labour']) : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= can_see_salary() ? $money($p['overhead']) : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $money($p['expenses']) ?></td>
        <td class="num"><?= $money($p['voucher']) ?></td>
        <td class="num"><?= $money($p['subcon']) ?></td>
        <td class="num"><?= $money($p['other']) ?></td>
        <td class="num"><?= $money($p['contingency']) ?></td>
        <td class="num"><?= $money($p['cost']) ?></td>
        <td class="num"><strong><?= $money($p['profit']) ?></strong></td>
        <td><span class="pill <?= $pill($p['profit']) ?>"><?= $p['margin'] === null ? '—' : $p['margin'] . '%' ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="16">Nothing was executed in this period for the filters chosen.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($rows): ?>
    <tfoot><tr style="font-weight:700;background:var(--soft)">
      <td colspan="4">Total — <?= (int)$tot['jobs'] ?> <?= e(Tlp('job')) ?></td>
      <td class="num"><?= rtrim(rtrim(number_format((float)$tot['mandays'], 1), '0'), '.') ?></td>
      <td class="num"><?= $money($tot['revenue']) ?></td>
      <td class="num"><?= can_see_salary() ? $money($tot['labour']) : '—' ?></td>
      <td class="num"><?= can_see_salary() ? $money($tot['overhead']) : '—' ?></td>
      <td class="num"><?= $money($tot['expenses']) ?></td>
      <td class="num"><?= $money($tot['voucher']) ?></td>
      <td class="num"><?= $money($tot['subcon']) ?></td>
      <td class="num"><?= $money($tot['other']) ?></td>
      <td class="num"><?= $money($tot['contingency']) ?></td>
      <td class="num"><?= $money($tot['cost']) ?></td>
      <td class="num"><?= $money($tot['profit']) ?></td>
      <td><span class="pill <?= $pill($tot['profit']) ?>"><?= $tot['margin'] === null ? '—' : $tot['margin'] . '%' ?></span></td>
    </tr></tfoot>
    <?php endif; ?>
  </table>
  </div>
  <?php if (!can_see_salary()): ?>
    <p class="muted" style="margin-top:8px">Salary and the overhead on it are not shown to your role, so the
      total cost and the profit include them without breaking them out.</p>
  <?php endif; ?>
</div>

<div class="panel">
  <h3 class="tab-sub">How the profit on one <?= e(Tl('job')) ?> is worked out</h3>
  <table class="grid" style="max-width:640px">
    <tr><td>Revenue</td><td class="muted">the invoice value, less any credit passed to the branch that did the work</td></tr>
    <tr><td>− <?= e(ucfirst(T('engineer'))) ?>'s salary</td><td class="muted">their annual cost ÷ 12 ÷ the working days in the month, × the days on this <?= e(Tl('job')) ?></td></tr>
    <tr><td>− Overhead</td><td class="muted">the <?= e(Tl('office')) ?>'s percentage on that salary — set per <?= e(Tl('office')) ?>, or company-wide in Settings</td></tr>
    <tr><td>− Expenses</td><td class="muted">what was booked against the <?= e(Tl('job')) ?> when it was closed</td></tr>
    <tr><td>− Voucher claims</td><td class="muted">what the <?= e(Tl('engineer')) ?> claimed on their monthly <?= e(Tl('voucher')) ?> against this <?= e(Tl('job')) ?></td></tr>
    <tr><td>− Sub-contractor</td><td class="muted">paid to somebody else's engineer for this work</td></tr>
    <tr><td>− Anything else</td><td class="muted">a hired instrument, a permit, a courier — entered on the <?= e(Tl('job')) ?></td></tr>
    <tr><td>− Contingency</td><td class="muted">the <?= e(Tl('office')) ?>'s percentage on all of the above</td></tr>
    <tr style="font-weight:700"><td>= Profit</td><td class="muted">and the margin is that profit as a percentage of the revenue</td></tr>
  </table>
  <p class="muted" style="margin-top:8px">The shared cost of running the branch — rent, the manager's own salary, the
    back office — is <strong>not</strong> pushed onto individual <?= e(Tlp('job')) ?>. A <?= e(Tl('job')) ?> is judged on
    what it directly caused; the branch is judged separately on the
    <a href="/sbu-pl"><?= e(T('sbu')) ?> profit &amp; loss</a>, where those costs are shared out on the month-end run.</p>
</div>
