<div class="crumbs"><a href="/">Home</a> › <a href="/profitability"><?= e(TP('boss')) ?></a> › <?= e($boss['boss_number']) ?></div>
<div class="master-head">
  <div><h1><?= e(T('boss')) ?> <?= e($boss['boss_number']) ?>
      <?php if (!empty($boss['superseded_by'])): ?><span class="pill p-warn" style="vertical-align:middle;font-size:12px">Renewed</span><?php endif; ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= e($boss['client_disp'] ?: $boss['client_name'] ?: '—') ?> · <?= (int)$p['jobs'] ?> job(s)
      <?php if (!empty($boss['prev_no'])): ?> · <span class="muted">continues from <a href="/profitability?boss=<?= (int)$boss['prev_id'] ?>"><?= e($boss['prev_no']) ?></a></span><?php endif; ?>
      <?php if (!empty($boss['next_no'])): ?> · <span class="muted">renewed as <a href="/profitability?boss=<?= (int)$boss['next_id'] ?>"><?= e($boss['next_no']) ?></a></span><?php endif; ?></p></div>
  <a class="btn secondary" href="/profitability">← Back</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic"><?= e(cur_sym()) ?></span><div class="k">Revenue</div><div class="v"><?= fmoney_short($p['revenue']) ?></div><div class="d"><?= (int)$p['jobs'] ?> job(s)</div></div>
  <div class="kpi"><span class="kic">🧾</span><div class="k">Expenses</div><div class="v"><?= fmoney_short($p['expenses']) ?></div><div class="d">voucher + closure</div></div>
  <div class="kpi"><span class="kic">🤝</span><div class="k">Sub-con</div><div class="v"><?= fmoney_short($p['subcon']) ?></div><div class="d">agency / outsourced</div></div>
  <?php if ($seeSal): ?>
    <div class="kpi"><span class="kic">👷</span><div class="k">Loaded labour</div><div class="v"><?= fmoney_short($p['labour']) ?></div><div class="d">salary + agency + OH</div></div>
    <?php if (($p['contingency'] ?? 0) > 0): ?><div class="kpi"><span class="kic">➕</span><div class="k">Contingency</div><div class="v"><?= fmoney_short($p['contingency']) ?></div><div class="d">office %</div></div><?php endif; ?>
    <div class="kpi" style="border-color:color-mix(in srgb,var(--<?= $p['margin']>=0?'ok':'bad' ?>) 45%,var(--line))">
      <span class="kic"><?= $p['margin']>=0?'📈':'📉' ?></span><div class="k">Margin</div>
      <div class="v <?= $p['margin']>=0?'up':'down' ?>"><?= fmoney_short($p['margin']) ?></div>
      <div class="d"><?= $p['pct']!==null ? e($p['pct']).'% of revenue' : '—' ?></div></div>
  <?php else: ?>
    <div class="kpi"><span class="kic">📊</span><div class="k">Contribution</div><div class="v"><?= fmoney_short($p['revenue']-$p['expenses']-$p['subcon']) ?></div><div class="d">labour hidden</div></div>
  <?php endif; ?>
</div>

<?php // Module 20 — did this contract make its bid margin? Estimated (the pre-award
      // costing) vs Actual (boss_profit — the one canonical actuals engine).
$eva = function_exists('pc_estimate_vs_actual') ? pc_estimate_vs_actual((int)$boss['id']) : null; ?>
<?php if ($eva): $v = $eva['var']; ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--<?= ($seeSal && $v['pct']!==null) ? ($v['pct']>=0?'ok':'bad') : 'line' ?>)">
  <h3 class="tab-sub" style="margin-top:0">Estimated vs actual — did it make its bid margin?</h3>
  <p class="sub" style="margin-top:0">The pre-award costing<?= !empty($eva['costing']['code']) ? ' (' . e($eva['costing']['code']) . ')' : '' ?> against what the contract actually delivered.
    Actuals are the same figures as the tiles above; the estimate is the approved bid.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th></th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin %</th></tr></thead>
    <tbody>
      <tr><td><b>Estimated (bid)</b></td>
        <td class="num"><?= fmoney_short($eva['est']['revenue']) ?></td>
        <td class="num"><?= fmoney_short($eva['est']['cost']) ?></td>
        <td class="num"><?= fmoney_short($eva['est']['profit']) ?></td>
        <td class="num"><?= $eva['est']['pct']!==null ? e($eva['est']['pct']).'%' : '—' ?></td></tr>
      <tr><td><b>Actual</b> <?= $seeSal ? '' : '<span class="muted" style="font-size:11px">(labour hidden)</span>' ?></td>
        <td class="num"><?= fmoney_short($eva['act']['revenue']) ?></td>
        <td class="num"><?= $seeSal ? fmoney_short($eva['act']['cost']) : '—' ?></td>
        <td class="num"><?= $seeSal ? fmoney_short($eva['act']['profit']) : '—' ?></td>
        <td class="num"><?= ($seeSal && $eva['act']['pct']!==null) ? e($eva['act']['pct']).'%' : '—' ?></td></tr>
      <?php if ($seeSal): ?>
      <tr style="font-weight:600;border-top:2px solid var(--line)"><td>Variance (actual − bid)</td>
        <td class="num">—</td><td class="num">—</td>
        <td class="num <?= $v['profit']>=0?'up':'down' ?>"><?= fmoney_short($v['profit']) ?></td>
        <td class="num"><?= $v['pct']!==null ? (($v['pct']>=0?'+':'').e($v['pct'])).' pts' : '—' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($seeSal && $eva['est']['pct']!==null && $eva['act']['pct']!==null): ?>
    <p style="margin:8px 0 0"><b><?= $v['pct']>=0 ? '✓ Beat' : '⚠ Missed' ?></b> its bid margin by <?= e(abs($v['pct'])) ?> point(s) —
      bid <?= e($eva['est']['pct']) ?>%, actual <?= e($eva['act']['pct']) ?>%. <span class="muted">Actuals still accrue until the contract closes.</span></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden;margin-top:16px">
  <h3 class="tab-sub" style="padding:14px 18px 0;margin-top:0">Expense lines — who visited which vendor, and the cost</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th></th><th>Date</th><th>Inspector</th><th>Vendor / Site</th><th class="num">Hrs</th><th>Line No</th><th class="num">Travel <?= e(cur_sym()) ?></th><th class="num">Bills <?= e(cur_sym()) ?></th><th class="num">Line total</th></tr></thead>
    <tbody>
    <?php $tot=0; foreach ($lines as $i=>$l): $amt = expense_extra_decode($l['amounts'] ?? ''); $bills = array_sum($amt); $tot += (float)$l['row_total']; ?>
    <tr>
      <td><button type="button" class="btn small secondary xpand" data-t="dl<?= $i ?>" title="Show expense breakdown">+</button></td>
      <td><?= e(date('d-M-Y', strtotime($l['entry_date']))) ?></td>
      <td><?= e($l['inspector_name'] ?: '—') ?></td>
      <td><?= e($l['vendor_disp'] ?: $l['vendor_leg'] ?: '—') ?></td>
      <td class="num"><?= e(rtrim(rtrim((string)$l['hours'],'0'),'.') ?: '0') ?></td>
      <td><?= e($l['line_no'] ?: '—') ?></td>
      <td class="num"><?= fmoney($l['travel_amount']) ?></td>
      <td class="num"><?= fmoney($bills) ?></td>
      <td class="num"><strong><?= fmoney($l['row_total']) ?></strong></td>
    </tr>
    <tr id="dl<?= $i ?>" class="drill" style="display:none;background:var(--soft)">
      <td></td>
      <td colspan="8" class="muted" style="font-size:12px">
        <?= (float)$l['km']>0 ? 'KM '.e(rtrim(rtrim((string)$l['km'],'0'),'.')).' → Travel '.fmoney($l['travel_amount']) : '' ?>
        <?php foreach ($amt as $code=>$a): if ($a==0) continue; ?> · <?= e($headLabels[$code] ?? $code) ?>: <?= fmoney($a) ?><?php endforeach; ?>
        <?php if (!$amt && (float)$l['travel_amount']==0): ?>No breakdown.<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$lines): ?><tr><td colspan="9" class="muted" style="padding:16px">No inspector expenses recorded against this <?= e(Tl("boss")) ?> yet.</td></tr><?php endif; ?>
    <?php if ($lines): ?><tr style="background:var(--soft)"><td colspan="8" style="text-align:right"><strong>Total voucher expenses</strong></td><td class="num"><strong><?= fmoney($tot) ?></strong></td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden;margin-top:16px">
  <h3 class="tab-sub" style="padding:14px 18px 0;margin-top:0">Invoices / jobs</h3>
  <table class="dt">
    <thead><tr><th>Job</th><th>Inspector</th><th>Invoice No</th><th class="num">Invoice <?= e(cur_sym()) ?></th><th>Invoice date</th><th>Payment</th></tr></thead>
    <tbody>
    <?php foreach ($invLines as $j): ?>
    <tr>
      <td><b><?= e($j['job_code']) ?></b></td>
      <td><?= e($j['inspector_name'] ?: '—') ?></td>
      <td><?= e($j['invoice_number'] ?: '—') ?></td>
      <td class="num"><?= (float)$j['invoice_amount']>0?fmoney($j['invoice_amount']):'—' ?></td>
      <td><?= e($j['invoice_date'] ?: '—') ?></td>
      <td><?= !empty($j['payment_received'])?'<span class="pill p-ok">Paid '.fmoney($j['payment_amount']).'</span>':'<span class="pill p-warn">Pending</span>' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$invLines): ?><tr><td colspan="6" class="muted" style="padding:16px">No jobs linked to this <?= e(Tl("boss")) ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (empty($boss['superseded_by'])): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Renew / change contract number (ARC / Open order)</h3>
  <p class="sub">Creates a new <?= e(Tl("boss")) ?>, carries the <strong>open jobs</strong> forward to it, and keeps this old number visible in the chain.</p>
  <form method="post" action="/boss-renew" class="inline-add" style="align-items:flex-end" onsubmit="return confirm('Renew this contract? Open jobs will move to the new number.')">
    <input type="hidden" name="old_id" value="<?= (int)$boss['id'] ?>">
    <div class="ff"><label>New <?= e(T("boss")) ?> *</label><input class="form-control" name="new_number" required></div>
    <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date"></div>
    <div class="ff"><label>End date</label><input class="form-control" type="date" name="end_date"></div>
    <button class="btn" type="submit">Renew &amp; carry forward</button>
  </form>
</div>
<?php endif; ?>

<script>
  document.querySelectorAll('.xpand').forEach(function(b){
    b.addEventListener('click', function(){
      var r=document.getElementById(b.dataset.t); if(!r) return;
      var on = r.style.display==='none'; r.style.display = on?'table-row':'none'; b.textContent = on?'−':'+';
    });
  });
</script>
