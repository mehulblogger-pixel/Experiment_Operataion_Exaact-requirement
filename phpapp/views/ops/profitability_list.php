<?php
  $tRev = 0; $tExp = 0; $tSub = 0; $tLab = 0; $tMar = 0; $tInv = 0; $tPaid = 0; $active = 0;
  foreach ($rows as $r) { $p = $r['p'];
    $tRev += (float)$p['revenue']; $tExp += (float)$p['expenses']; $tSub += (float)$p['subcon'];
    $tLab += (float)$p['labour']; $tMar += (float)$p['margin'];
    $tInv += (float)$p['invoiced']; $tPaid += (float)$p['paid'];
    if (($r['status'] ?? '') === 'ACTIVE') $active++;
  }
  $bstatus = ['ACTIVE'=>'p-ok','CLOSED'=>'p-mut','HOLD'=>'p-warn'];
  $fdate = function($d){ return $d ? date('d M Y', strtotime($d)) : '—'; };
?>
<div class="master-head">
  <div><h1><?= e(T_REG('boss')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Every <?= e(Tl("boss")) ?> with its dates, renewal chain, invoicing, expenses<?= $seeSal?', salary cost and profit':'' ?>. Click one for the line-by-line drill-down.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/profitability?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ Download CSV</a>
    <a class="btn ghost" href="/mis">Management dashboard</a>
    <a class="btn ghost" href="/sbu-pl"><?= e(T('sbu')) ?> P&amp;L</a>
  </div>
</div>

<?php if ($seeSal): ?>
<div class="msg-info">
  <strong>What this screen shows, and what it does not.</strong>
  A <?= e(T('boss')) ?> number is judged on what it <em>directly caused</em> — the <?= e(Tl('engineer')) ?>'s time on it,
  its expenses and its sub-contractor — plus the single overhead % set for the <?= e(Tl('office')) ?>.
  No share of the branch manager, the coordinator or the rent is pushed onto an order.
  Those sit on the <a href="/sbu-pl"><?= e(Tl('sbu')) ?> line</a>, from the month-end run.
  Read this screen when pricing work; read the <?= e(Tl('sbu')) ?> P&amp;L when asking whether the branch is ahead.
</div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab"><?= e(TP("boss")) ?></div><div class="k-val"><?= count($rows) ?></div><div class="k-sub"><?= $active ?> active</div></div>
  <div class="kpi"><div class="k-lab">Invoiced</div><div class="k-val"><?= fmoney_short($tInv) ?></div><div class="k-sub">Received <?= fmoney_short($tPaid) ?></div></div>
  <div class="kpi"><div class="k-lab">Expenses booked</div><div class="k-val"><?= fmoney_short($tExp) ?></div><div class="k-sub">travel + closure</div></div>
  <?php if ($seeSal): ?>
    <div class="kpi"><div class="k-lab">Salary costing</div><div class="k-val"><?= fmoney_short($tLab) ?></div><div class="k-sub">loaded labour</div></div>
    <div class="kpi"><div class="k-lab">Profit</div><div class="k-val <?= $tMar>=0?'up':'down' ?>"><?= fmoney_short($tMar) ?></div><div class="k-sub"><?= $tRev? round($tMar/$tRev*100,1).'% margin':'—' ?></div></div>
  <?php endif; ?>
</div>

<?php // Module 32 — profit-engine consistency: does every screen's profit reconcile to
      // the canonical job_profit() engine? Read-only; changes no displayed figure.
  if (!empty($reconcile)): $rc = $reconcile; $om = $rc['omitted']; $overstate = (float)$rc['overstatement']; $uni = !empty($unified); ?>
<div class="msg <?= ($uni || $rc['consistent']) ? 'msg-ok' : 'msg-warning' ?>" style="margin-bottom:14px">
  <?php if ($uni): ?>
    <b>Unified financial truth is ON (§28).</b> Every screen — the <a href="/mis">Management dashboard</a>, the
    <a href="/sbu-pl"><?= e(T('sbu')) ?> P&amp;L</a> contract table and the owner/<?= e(T('boss')) ?> view — now reads the one
    canonical <code>job_profit()</code> engine (overhead, vouchers, contingency, other cost and client-recovered credit all
    included, frozen per §30). A job shows the same profit everywhere.
  <?php elseif ($rc['consistent']): ?>
    <b>Profit figures reconcile.</b> Across <?= (int)$rc['jobs'] ?> job<?= $rc['jobs']===1?'':'s' ?>, every screen's profit matches the canonical <code>job_profit()</code> engine.
  <?php else: ?>
    <b>Profit figures differ between screens.</b>
    The <a href="/mis">Management dashboard</a> and the <a href="/sbu-pl"><?= e(T('sbu')) ?> P&amp;L</a> contract table compute
    profit as <em>revenue − labour − expenses − sub-contractor</em> — which omits overhead, vouchers, contingency, other cost
    and client-recovered credit. Across <?= (int)$rc['drifting'] ?> of <?= (int)$rc['jobs'] ?> job<?= $rc['jobs']===1?'':'s' ?>
    that <strong>overstates profit by <?= fmoney_short($overstate) ?></strong> versus the canonical engine this screen and the
    management-report total use.
    <table class="tbl" style="max-width:560px;margin-top:8px;background:var(--card)">
      <tr><td>Canonical profit — the figure every screen <em>will show</em></td><td style="text-align:right"><b><?= fmoney_short($rc['canonical_profit']) ?></b></td></tr>
      <tr><td>Partial profit — what MIS / <?= e(T('sbu')) ?>-P&amp;L <em>show today</em></td><td style="text-align:right"><?= fmoney_short($rc['partial_profit']) ?></td></tr>
      <tr><td class="muted">— omitted: overhead</td><td class="muted" style="text-align:right"><?= fmoney_short($om['overhead']) ?></td></tr>
      <tr><td class="muted">— omitted: vouchers</td><td class="muted" style="text-align:right"><?= fmoney_short($om['voucher']) ?></td></tr>
      <tr><td class="muted">— omitted: contingency</td><td class="muted" style="text-align:right"><?= fmoney_short($om['contingency']) ?></td></tr>
      <tr><td class="muted">— omitted: other cost</td><td class="muted" style="text-align:right"><?= fmoney_short($om['other']) ?></td></tr>
      <tr><td class="muted">— less client-recovered</td><td class="muted" style="text-align:right">(<?= fmoney_short($om['recovered']) ?>)</td></tr>
      <tr style="border-top:1px solid var(--line)"><td><b>Change when unified turns on</b></td><td style="text-align:right"><b>−<?= fmoney_short($overstate) ?></b></td></tr>
    </table>
    <span class="muted" style="font-size:12px">This is the <b>before/after preview</b> for §28. Turning on <em>Unified financial truth</em> in
      <a href="/settings">Settings</a> makes those screens show the canonical figure above — profit drops to the true value. Default is OFF; nothing has changed yet.</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">📄</div>
      <p class="muted" style="margin:8px 0 0">No <?= e(Tlp("boss")) ?> yet. They are created on their own when a <?= e(Tl("quote")) ?> carrying one is allocated, and can also be added under <a href="/m/boss"><?= e(Tlp("boss")) ?></a>.</p></div>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr>
      <th class="num">Sr</th><th><?= e(T("boss")) ?></th><th>Client</th><th>Status</th>
      <th>Created on</th><th>Expires on</th><th>Renewed into</th>
      <th class="num">Jobs</th><th class="num">Invoicing done</th><th class="num">Expenses booked</th>
      <?php if ($seeSal): ?><th class="num">Salary costing</th><th class="num">Profit INR</th><th class="num">Profit %</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php $sr = 0; foreach ($rows as $r): $p = $r['p']; $pos = $p['margin'] >= 0; $sr++;
      $exp = $r['end_date'] ? (int)round((strtotime($r['end_date']) - time())/86400) : null; ?>
    <tr>
      <td class="num muted"><?= $sr ?></td>
      <td>
        <a href="/profitability?boss=<?= (int)$r['id'] ?>"><b><?= e($r['boss_number']) ?></b></a>
        <?php if (!empty($r['prev_no'])): ?><div class="muted" style="font-size:11px">↳ renews <?= e($r['prev_no']) ?></div><?php endif; ?>
      </td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td><span class="pill <?= $bstatus[$r['status']] ?? 'p-mut' ?>"><?= e(lk_options_or('boss_status', BOSS_STATUS)[$r['status']] ?? $r['status'] ?: '—') ?></span></td>
      <td><?= e($fdate($r['start_date'])) ?></td>
      <td><?= e($fdate($r['end_date'])) ?>
        <?php if ($exp !== null && ($r['status'] ?? '') !== 'CLOSED'): ?>
          <?php if ($exp < 0): ?><span class="pill p-bad" style="margin-left:4px">expired</span>
          <?php elseif ($exp <= 30): ?><span class="pill p-warn" style="margin-left:4px"><?= $exp ?>d</span><?php endif; ?>
        <?php endif; ?>
      </td>
      <td><?php if (!empty($r['next_no'])): ?><a href="/profitability?boss=<?= (int)$r['superseded_by'] ?>"><?= e($r['next_no']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td class="num"><?= (int)$p['jobs'] ?></td>
      <td class="num"><?= (float)$p['invoiced']>0 ? fmoney($p['invoiced']) : '<span class="pill p-warn">pending</span>' ?></td>
      <td class="num"><?= fmoney($p['expenses']) ?></td>
      <?php if ($seeSal): ?>
        <td class="num"><?= fmoney($p['labour']) ?></td>
        <td class="num"><strong class="<?= $pos?'up':'down' ?>"><?= fmoney($p['margin']) ?></strong></td>
        <td class="num"><?php if ($p['pct']===null): ?>—<?php else: ?><span class="pill <?= $p['pct']<0?'p-bad':($p['pct']<10?'p-warn':'p-ok') ?>"><?= e($p['pct']) ?>%</span><?php endif; ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<p class="muted" style="margin-top:10px">"Renewed into" links a <?= e(Tl("boss")) ?> to the newer number that continues it (renewal hierarchy). "Invoicing done" is the invoiced amount against it; "Expenses booked" is inspector voucher costs (travel + bills) plus job-closure expenses<?= $seeSal?'; "Salary costing" is the loaded labour cost of the inspectors deployed':'' ?>.</p>
