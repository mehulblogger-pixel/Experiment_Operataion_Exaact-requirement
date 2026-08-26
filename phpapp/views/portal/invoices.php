<?php
$out = 0.0; $overdue = 0;
foreach ($rows as $i) {
    if (!empty($i['payment_received'])) continue;
    $out += (float)($i['outstanding'] ?? $i['invoice_amount']);   // true remaining balance (Module 10)
    if (portal_invoice_overdue($i)) $overdue++;
}
?>
<h2 class="ptitle">Your invoices</h2>
<p class="plead">What we have billed and what is still outstanding. If any line is in query with us, say so on the
  <a href="/portal/request">request form</a> and it will reach the same desk.</p>

<div class="ptiles">
  <div class="ptile"><div class="n"><?= (int)count($rows) ?></div><div class="l">invoices raised</div></div>
  <div class="ptile"><div class="n"><?= e(fmoney_short($out)) ?></div><div class="l">outstanding</div></div>
  <div class="ptile"><div class="n"><?= (int)$overdue ?></div><div class="l">past their due date</div></div>
</div>

<?php if (!$rows): ?>
  <p class="pempty">Nothing invoiced yet.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr>
    <th>Invoice</th><th>Against</th><th>Dated</th><th>Due</th>
    <th style="text-align:right">Amount</th><th>Settled</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $i): $late = portal_invoice_overdue($i); ?>
    <tr>
      <td><b><?= e($i['invoice_number']) ?></b></td>
      <td><?= e($i['call_code'] ?: $i['job_code']) ?></td>
      <td><?= e(fdate($i['invoice_date'])) ?></td>
      <td><?= e(fdate($i['invoice_due_date'])) ?><?= $late ? ' <span class="pill p-warn">overdue</span>' : '' ?></td>
      <td style="text-align:right"><?= e(fmoney($i['invoice_amount'])) ?></td>
      <td><?php
        $outst = (float)($i['outstanding'] ?? ($i['invoice_amount'] ?? 0));
        if (!empty($i['payment_received'])) {
            echo 'Yes' . ($i['payment_date'] ? ' · ' . e(fdate($i['payment_date'])) : '');
        } elseif ($outst > 1 && $outst < (float)$i['invoice_amount'] - 1) {
            // A part-payment: show what is still outstanding, not just "not yet".
            echo '<span class="pill p-info">Part-paid</span> <span class="muted">' . e(fmoney($outst)) . ' outstanding</span>';
        } else {
            echo '<span class="muted">Not yet</span>';
        } ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<p class="pnote">These are our records. If a payment you have made is not shown, it may not have been applied yet —
  tell us and we will check rather than chase you.</p>
<?php endif; ?>
