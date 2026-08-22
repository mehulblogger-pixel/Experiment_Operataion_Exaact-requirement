<?php
  $t = $totals;
  $pct = fn($v) => $t['total'] > 0 ? round($v * 100 / $t['total']) : 0;
  $link = fn($over = []) => '/receivables?' . http_build_query(array_merge(['basis' => $basis], $over));
?>
<div class="crumbs"><a href="/">Home</a> › Billing › Receivables ageing</div>
<?php billing_tabs('receivables'); ?>
<div class="master-head">
  <div><h1>Receivables ageing</h1>
  <p class="sub" style="margin:2px 0 0">Who owes us, and for how long. Aged <?= $basis === 'INVOICE' ? 'from the invoice date' : 'from the due date — an invoice on 60-day terms is not late on day 45' ?>.</p></div>
  <a class="btn secondary" href="<?= e($link(['export' => 'csv'])) ?>">⬇ Download CSV</a>
</div>

<div class="panel" style="margin-top:16px;display:flex;gap:16px;flex-wrap:wrap;align-items:center">
  <span class="muted">Age by</span>
  <a class="btn small<?= $basis === 'DUE' ? '' : ' secondary' ?>" href="<?= e($link(['basis' => 'DUE'])) ?>">Due date</a>
  <a class="btn small<?= $basis === 'INVOICE' ? '' : ' secondary' ?>" href="<?= e($link(['basis' => 'INVOICE'])) ?>">Invoice date</a>
  <span class="muted" style="font-size:13px">As at <?= e(fdate($today)) ?>. Raised and unpaid only — <?= (int)$t['n'] ?> invoice(s).</span>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-info"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney($t['total']) ?></div><div class="ql">Outstanding</div></div>
  <div class="qcard tone-warn"><div class="qic">◷</div><div class="qn" style="font-size:20px"><?= fmoney($t['overdue']) ?></div><div class="ql">Past due (<?= $pct($t['overdue']) ?>%)</div></div>
  <div class="qcard <?= $t['b90p'] > 0 ? 'tone-bad' : '' ?>"><div class="qic">!</div><div class="qn" style="font-size:20px"><?= fmoney($t['b90p']) ?></div><div class="ql">90+ days (<?= $pct($t['b90p']) ?>%)</div></div>
  <div class="qcard tone-ok"><div class="qic">▦</div><div class="qn" style="font-size:20px"><?= fmoney($t['notdue']) ?></div><div class="ql">Not yet due</div></div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0">
    <h3>By client <span class="muted">(<?= count($clients) ?>)</span></h3>
  </div>
  <?php if (!$clients): ?>
    <p class="muted" style="padding:18px">Nothing outstanding. 🎉</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="dt" style="margin-top:8px">
    <thead><tr>
      <th>Client</th><th class="num">Invoices</th>
      <?php foreach (AR_BUCKETS as $b): ?><th class="num"><?= e($b['label']) ?></th><?php endforeach; ?>
      <th class="num">Total</th><th class="num">Oldest</th>
    </tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr<?= $client === $c['client_id'] ? ' style="outline:2px solid var(--brand);outline-offset:-2px"' : '' ?>>
        <td><a href="<?= e($link(['client' => $c['client_id']])) ?>#detail"><b><?= e($c['name']) ?></b></a></td>
        <td class="num"><?= (int)$c['n'] ?></td>
        <?php foreach (AR_BUCKETS as $b): ?>
          <td class="num"<?= $b['key'] === 'b90p' && $c['b90p'] > 0 ? ' style="color:var(--bad);font-weight:700"' : '' ?>><?= $c[$b['key']] > 0 ? fmoney($c[$b['key']]) : '—' ?></td>
        <?php endforeach; ?>
        <td class="num"><b><?= fmoney($c['total']) ?></b></td>
        <td class="num"><?= $c['oldest'] > 0 ? (int)$c['oldest'] . ' d' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr style="font-weight:700;border-top:2px solid var(--line)">
      <td>Total</td><td class="num"><?= (int)$t['n'] ?></td>
      <?php foreach (AR_BUCKETS as $b): ?><td class="num"><?= $t[$b['key']] > 0 ? fmoney($t[$b['key']]) : '—' ?></td><?php endforeach; ?>
      <td class="num"><?= fmoney($t['total']) ?></td><td></td>
    </tr></tfoot>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($client): ?>
<div class="panel" id="detail" style="margin-top:16px;padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0">
    <h3><?= e($clientName ?: 'Client') ?> <span class="muted">(<?= count($detail) ?> invoice<?= count($detail) === 1 ? '' : 's' ?>)</span></h3>
    <a href="<?= e($link()) ?>">Clear →</a>
  </div>
  <?php if (!$detail): ?>
    <p class="muted" style="padding:18px">Nothing outstanding for that client.</p>
  <?php else: ?>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Invoice</th><th>Job</th><th>Invoiced</th><th>Due</th><th class="num">Age</th><th>Bucket</th><th class="num">Amount</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($detail as $r):
      $lbl = '';
      foreach (AR_BUCKETS as $b) if ($b['key'] === $r['bucket']) $lbl = $b['label'];
    ?>
      <tr>
        <td><b><?= e($r['invoice_number'] ?: '—') ?></b></td>
        <td><?= e($r['job_code']) ?><?php if ($r['office_name']): ?><br><span class="muted" style="font-size:12px"><?= e($r['office_name']) ?></span><?php endif; ?></td>
        <td><?= e(fdate($r['invoice_date'])) ?></td>
        <td><?= $r['invoice_due_date'] ? e(fdate($r['invoice_due_date'])) : '<span class="muted">not set</span>' ?></td>
        <td class="num"><?= (int)$r['age_days'] ?> d<?php if (!empty($r['estimated'])): ?><br><span class="muted" style="font-size:11px">from invoice date</span><?php endif; ?></td>
        <td><span class="pill <?= $r['bucket'] === 'b90p' ? 'p-bad' : ($r['bucket'] === 'notdue' ? 'p-mut' : 'p-warn') ?>"><?= e($lbl) ?></span></td>
        <td class="num"><b><?= fmoney($r['amount']) ?></b></td>
        <td><a class="btn small" href="/job?id=<?= (int)$r['id'] ?>#invoice">Open →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
