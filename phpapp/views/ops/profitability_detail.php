<div class="crumbs"><a href="/">Home</a> › <a href="/profitability">Profitability</a> › <?= e($boss['boss_number']) ?></div>
<div class="master-head">
  <div><h1>BOSS <?= e($boss['boss_number']) ?>
      <?php if (!empty($boss['superseded_by'])): ?><span class="badge AMBER" style="vertical-align:middle">Renewed</span><?php endif; ?></h1>
    <p class="sub"><?= e($boss['client_disp'] ?: $boss['client_name'] ?: '—') ?> · <?= (int)$p['jobs'] ?> job(s)
      <?php if (!empty($boss['prev_no'])): ?> · <span class="muted">continues from <a href="/profitability?boss=<?= (int)$boss['prev_id'] ?>"><?= e($boss['prev_no']) ?></a></span><?php endif; ?>
      <?php if (!empty($boss['next_no'])): ?> · <span class="muted">renewed as <a href="/profitability?boss=<?= (int)$boss['next_id'] ?>"><?= e($boss['next_no']) ?></a></span><?php endif; ?></p></div>
  <a class="btn secondary" href="/profitability">← Back</a>
</div>

<div class="stat-row">
  <div class="stat-card"><div class="sc-num"><?= fmoney($p['revenue']) ?></div><div class="sc-lbl">Revenue</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($p['expenses']) ?></div><div class="sc-lbl">Expenses (voucher + closure)</div></div>
  <div class="stat-card"><div class="sc-num"><?= fmoney($p['subcon']) ?></div><div class="sc-lbl">Sub-con</div></div>
  <?php if ($seeSal): ?>
  <div class="stat-card"><div class="sc-num"><?= fmoney($p['labour']) ?></div><div class="sc-lbl">Loaded labour</div></div>
  <div class="stat-card"><div class="sc-num" style="color:<?= $p['margin']>=0?'#1f8a4c':'#c0392b' ?>"><?= fmoney($p['margin']) ?></div><div class="sc-lbl">Margin<?= $p['pct']!==null?' ('.$p['pct'].'%)':'' ?></div></div>
  <?php else: ?>
  <div class="stat-card"><div class="sc-num"><?= fmoney($p['revenue']-$p['expenses']-$p['subcon']) ?></div><div class="sc-lbl">Contribution (labour hidden)</div></div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3 class="tab-sub">Expense lines — which inspector visited which vendor, and the cost</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th></th><th>Date</th><th>Inspector</th><th>Vendor / Site</th><th>Hrs</th><th>Line No</th><th>Travel ₹</th><th>Bills ₹</th><th>Line total</th></tr>
    <?php $tot=0; foreach ($lines as $i=>$l): $amt = expense_extra_decode($l['amounts'] ?? ''); $bills = array_sum($amt); $tot += (float)$l['row_total']; ?>
    <tr>
      <td><button type="button" class="btn small secondary xpand" data-t="dl<?= $i ?>" title="Show expense breakdown">+</button></td>
      <td><?= e(date('d-M-Y', strtotime($l['entry_date']))) ?></td>
      <td><?= e($l['inspector_name'] ?: '—') ?></td>
      <td><?= e($l['vendor_disp'] ?: $l['vendor_leg'] ?: '—') ?></td>
      <td><?= e(rtrim(rtrim((string)$l['hours'],'0'),'.') ?: '0') ?></td>
      <td><?= e($l['line_no'] ?: '—') ?></td>
      <td><?= fmoney($l['travel_amount']) ?></td>
      <td><?= fmoney($bills) ?></td>
      <td><strong><?= fmoney($l['row_total']) ?></strong></td>
    </tr>
    <tr id="dl<?= $i ?>" class="drill" style="display:none;background:#faf8f4">
      <td></td>
      <td colspan="8" class="muted" style="font-size:12px">
        <?= (float)$l['km']>0 ? 'KM '.e(rtrim(rtrim((string)$l['km'],'0'),'.')).' → Travel '.fmoney($l['travel_amount']) : '' ?>
        <?php foreach ($amt as $code=>$a): if ($a==0) continue; ?> · <?= e($headLabels[$code] ?? $code) ?>: <?= fmoney($a) ?><?php endforeach; ?>
        <?php if (!$amt && (float)$l['travel_amount']==0): ?>No breakdown.<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$lines): ?><tr><td colspan="9">No inspector expenses recorded against this BOSS yet.</td></tr><?php endif; ?>
    <?php if ($lines): ?><tr style="background:#f7f6f4"><td colspan="8" style="text-align:right"><strong>Total voucher expenses</strong></td><td><strong><?= fmoney($tot) ?></strong></td></tr><?php endif; ?>
  </table>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub">Invoices / jobs</h3>
  <table class="grid">
    <tr><th>Job</th><th>Inspector</th><th>Invoice No</th><th>Invoice ₹</th><th>Invoice date</th><th>Payment</th></tr>
    <?php foreach ($invLines as $j): ?>
    <tr>
      <td><?= e($j['job_code']) ?></td>
      <td><?= e($j['inspector_name'] ?: '—') ?></td>
      <td><?= e($j['invoice_number'] ?: '—') ?></td>
      <td><?= (float)$j['invoice_amount']>0?fmoney($j['invoice_amount']):'—' ?></td>
      <td><?= e($j['invoice_date'] ?: '—') ?></td>
      <td><?= !empty($j['payment_received'])?'<span class="badge GREEN">Paid '.fmoney($j['payment_amount']).'</span>':'<span class="badge AMBER">Pending</span>' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$invLines): ?><tr><td colspan="6">No jobs linked to this BOSS.</td></tr><?php endif; ?>
  </table>
</div>

<?php if (empty($boss['superseded_by'])): ?>
<div class="panel">
  <h3 class="tab-sub">Renew / change contract number (ARC / Open order)</h3>
  <p class="sub">Creates a new BOSS/contract number, carries the <strong>open jobs</strong> forward to it, and keeps this old number visible in the chain.</p>
  <form method="post" action="/boss-renew" class="inline-add" style="align-items:flex-end" onsubmit="return confirm('Renew this contract? Open jobs will move to the new number.')">
    <input type="hidden" name="old_id" value="<?= (int)$boss['id'] ?>">
    <div class="ff"><label>New BOSS / contract number *</label><input class="form-control" name="new_number" required></div>
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
