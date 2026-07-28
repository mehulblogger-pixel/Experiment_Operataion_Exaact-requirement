<?php
  $totalIn = (float)$r['amount'] + (float)$r['tds_amount'];
  $leftCash = round((float)$r['amount'] - $allocCash, 2);
  $leftTds  = round((float)$r['tds_amount'] - $allocTds, 2);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/receipts">Money in</a> › <?= e($r['receipt_no']) ?></div>
<div class="master-head">
  <div><h1><?= e($r['receipt_no']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><a href="/ledger?id=<?= (int)$r['partner_id'] ?>"><?= e($r['partner_name']) ?></a>
    · <?= e(fdate($r['receipt_date'])) ?> · <?= e(RECEIPT_MODES[$r['mode']] ?? $r['mode']) ?><?= $r['reference'] ? ' · ' . e($r['reference']) : '' ?></p></div>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-ok"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney_short($r['amount']) ?></div><div class="ql">In the bank</div></div>
  <div class="qcard tone-info"><div class="qic">%</div><div class="qn" style="font-size:20px"><?= fmoney_short($r['tds_amount']) ?></div><div class="ql">TDS withheld</div></div>
  <div class="qcard <?= ($leftCash+$leftTds) > 0.01 ? 'tone-warn' : '' ?>"><div class="qic">⇄</div><div class="qn" style="font-size:20px"><?= fmoney_short($leftCash + $leftTds) ?></div><div class="ql">Still to match</div></div>
</div>

<?php if ($allocs): ?>
<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)"><b style="font-size:13.5px">Matched to</b></div>
  <div class="dt-scroll"><table class="dt">
    <caption class="sr-only">What this receipt settles</caption>
    <thead><tr><th scope="col">Invoice</th><th scope="col">Invoice date</th><th scope="col">Type</th><th scope="col" class="num">Amount</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($allocs as $a): ?>
      <tr><td><a href="/invoice?id=<?= (int)$a['invoice_id'] ?>"><?= e($a['invoice_no'] ?: '#' . (int)$a['invoice_id']) ?></a></td>
          <td><?= e(fdate($a['invoice_date'])) ?></td>
          <td><?= $a['kind']==='TDS' ? 'TDS' : 'Cash' ?></td>
          <td class="num"><?= e(fmoney($a['amount'])) ?></td>
          <td><form method="post" action="/receipt-unallocate" style="display:inline">
            <input type="hidden" name="receipt_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="alloc_id" value="<?= (int)$a['id'] ?>">
            <button class="btn small secondary" onclick="return confirm('Unmatch this?')">Unmatch</button>
          </form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if (($leftCash + $leftTds) > 0.01): ?>
  <?php if (!$open): ?>
    <div class="panel" style="margin-top:16px">
      <p style="margin:0">This customer has no open invoices, so there is nothing to match against yet.</p>
      <p class="muted" style="font-size:13px;margin:8px 0 0">That is not a problem. The money is already in their ledger as a credit, so their balance is right. Match it when the invoice is raised.</p>
    </div>
  <?php else: ?>
  <form method="post" action="/receipt-allocate" class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <input type="hidden" name="receipt_id" value="<?= (int)$r['id'] ?>">
    <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
      <b style="font-size:13.5px">Match it to invoices</b>
      <span class="muted" style="font-size:12.5px"> — <?= e(fmoney($leftCash)) ?> cash and <?= e(fmoney($leftTds)) ?> TDS left</span>
    </div>
    <div class="dt-scroll"><table class="dt">
      <caption class="sr-only">Open invoices to allocate against</caption>
      <thead><tr><th scope="col">Invoice</th><th scope="col">Due</th><th scope="col" class="num">Outstanding</th>
        <th scope="col" class="num">Cash</th><th scope="col" class="num">TDS</th></tr></thead>
      <tbody>
      <?php foreach ($open as $o): ?>
        <tr>
          <td><a href="/invoice?id=<?= (int)$o['id'] ?>"><?= e($o['invoice_no']) ?></a></td>
          <td><?= $o['due_date'] ? e(fdate($o['due_date'])) : '—' ?></td>
          <td class="num"><?= e(fmoney($o['outstanding'])) ?></td>
          <td class="num"><label class="sr-only" for="c-<?= (int)$o['id'] ?>">Cash against <?= e($o['invoice_no']) ?></label>
            <input id="c-<?= (int)$o['id'] ?>" name="cash[<?= (int)$o['id'] ?>]" type="number" step="0.01" min="0" style="width:110px;text-align:right" placeholder="0.00"></td>
          <td class="num"><label class="sr-only" for="t-<?= (int)$o['id'] ?>">TDS against <?= e($o['invoice_no']) ?></label>
            <input id="t-<?= (int)$o['id'] ?>" name="tds[<?= (int)$o['id'] ?>]" type="number" step="0.01" min="0" style="width:110px;text-align:right" placeholder="0.00"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="padding:12px 16px;border-top:1px solid var(--line)">
      <button class="btn">Match it</button>
      <span class="muted" style="font-size:12.5px;margin-left:10px">Nothing can be matched beyond what arrived, or beyond what an invoice still owes — both are refused rather than rounded away.</span>
    </div>
  </form>
  <?php endif; ?>
<?php endif; ?>
