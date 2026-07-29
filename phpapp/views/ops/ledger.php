<div class="crumbs"><a href="/">Home</a> › <a href="/clients">Customers</a> › <a href="/partner?id=<?= (int)$p['id'] ?>"><?= e($p['display_name'] ?: $p['legal_name']) ?></a> › Ledger</div>
<div class="master-head">
  <div><h1>Ledger — <?= e($p['display_name'] ?: $p['legal_name']) ?></h1>
  <p class="sub" style="margin:2px 0 0">Every invoice, receipt, TDS deduction and credit note against this customer, in date order, with a running balance. This is the answer to "what do they actually owe us".</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn secondary" href="/customer?id=<?= (int)$p['id'] ?>">Customer 360</a>
    <a class="btn secondary" href="/receipt-new?partner=<?= (int)$p['id'] ?>">Record money received</a>
    <a class="btn secondary" href="/invoice-new?partner=<?= (int)$p['id'] ?>">New invoice</a>
  </div>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-info"><div class="qic">Σ</div><div class="qn" style="font-size:20px"><?= fmoney_short($sum['billed']) ?></div><div class="ql">Billed and still open</div></div>
  <div class="qcard tone-ok"><div class="qic">✓</div><div class="qn" style="font-size:20px"><?= fmoney_short($sum['settled']) ?></div><div class="ql">Settled against it</div></div>
  <div class="qcard <?= $sum['outstanding'] > 0 ? 'tone-bad' : '' ?>"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney_short($sum['outstanding']) ?></div><div class="ql">Outstanding</div></div>
  <?php if ($sum['on_account'] > 0.01): ?>
    <div class="qcard tone-warn"><div class="qic">⇄</div><div class="qn" style="font-size:20px"><?= fmoney_short($sum['on_account']) ?></div><div class="ql">Received, not yet matched</div></div>
  <?php endif; ?>
</div>

<form method="get" action="/ledger" class="panel" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:end">
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="ff"><label for="lf">From</label><input id="lf" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="ff"><label for="lt">To</label><input id="lt" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn small">Apply</button>
  <?php if ($from !== '' || $to !== ''): ?><a class="btn small secondary" href="/ledger?id=<?= (int)$p['id'] ?>">Whole history</a><?php endif; ?>
  <a class="btn small secondary" style="margin-left:auto" href="/ledger?<?= e(http_build_query(array_merge($_GET,['id'=>$p['id'],'export'=>'csv']))) ?>">⬇ CSV</a>
</form>

<div class="panel" style="margin-top:14px;padding:0;overflow:hidden">
  <div class="dt-scroll">
    <table class="dt">
      <caption class="sr-only">Customer ledger, <?= count($led['rows']) ?> entries, closing balance <?= e(fmoney($led['closing'])) ?></caption>
      <thead><tr><th scope="col">Date</th><th scope="col">Entry</th><th scope="col">Reference</th>
        <th scope="col" class="num">Charged</th><th scope="col" class="num">Settled</th><th scope="col" class="num">Balance</th></tr></thead>
      <tbody>
      <?php if (!$led['rows']): ?>
        <tr><td colspan="6" class="muted" style="padding:20px;text-align:center">Nothing on this customer's ledger<?= ($from!==''||$to!=='') ? ' in that period' : ' yet' ?>.</td></tr>
      <?php else: foreach ($led['rows'] as $r): ?>
        <tr>
          <td style="white-space:nowrap"><?= e(fdate($r['date'])) ?></td>
          <td><?= e($r['note']) ?></td>
          <td><a href="<?= e($r['url']) ?>"><?= e($r['ref']) ?></a></td>
          <td class="num"><?= $r['debit'] > 0 ? e(fmoney($r['debit'])) : '<span class="muted">—</span>' ?></td>
          <td class="num"><?= $r['credit'] > 0 ? e(fmoney($r['credit'])) : '<span class="muted">—</span>' ?></td>
          <td class="num"><b><?= e(fmoney($r['balance'])) ?></b></td>
        </tr>
      <?php endforeach; ?>
        <tr style="background:var(--soft)">
          <td colspan="5"><b>Closing balance<?= ($from!==''||$to!=='') ? ' for the period shown' : '' ?></b></td>
          <td class="num"><b><?= e(fmoney($led['closing'])) ?></b></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="muted" style="margin-top:10px;font-size:12.5px">A positive balance is money they owe us. TDS appears as a separate settling entry rather than being folded into the receipt, because that is how it has to be reconciled against Form 26AS at the year end.</p>
