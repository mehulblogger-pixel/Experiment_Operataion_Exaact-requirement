<div class="crumbs"><a href="/">Home</a> › <a href="/invoices">Invoices</a> › Money in</div>
<div class="master-head">
  <div><h1>Money in</h1>
  <p class="sub" style="margin:2px 0 0">Every payment received. A receipt is recorded when the money arrives; matching it to invoices is a separate step, because a customer sends one payment against four invoices and sometimes sends one nobody can place yet.</p></div>
  <a class="btn" href="/receipt-new">+ Record money received</a>
</div>

<div class="panel" style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <a class="btn small<?= $f===''?'':' secondary' ?>" href="/receipts">All</a>
  <a class="btn small<?= $f==='unallocated'?'':' secondary' ?>" href="/receipts?f=unallocated">Not yet matched</a>
  <form method="get" action="/receipts" style="display:flex;gap:8px;margin-left:auto" role="search">
    <?php if ($f !== ''): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?>
    <label class="sr-only" for="rq">Find a receipt</label>
    <input id="rq" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Receipt, customer or bank reference">
    <button class="btn small secondary">Find</button>
  </form>
  <a class="btn small secondary" href="/receipts?<?= e(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">⬇ CSV</a>
</div>

<div class="panel" style="margin-top:14px;padding:0;overflow:hidden">
  <div class="dt-scroll">
    <table class="dt">
      <caption class="sr-only">Receipts, <?= count($rows) ?> rows</caption>
      <thead><tr><th scope="col">Receipt</th><th scope="col">Customer</th><th scope="col">How</th>
        <th scope="col" class="num">Received</th><th scope="col" class="num">TDS</th>
        <th scope="col" class="num">Matched</th><th scope="col" class="num">Left to match</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted" style="padding:20px;text-align:center">Nothing here.</td></tr>
      <?php else: foreach ($rows as $r):
        $left = round((float)$r['amount'] + (float)$r['tds_amount'] - (float)$r['allocated'], 2); ?>
        <tr>
          <td><a href="/receipt?id=<?= (int)$r['id'] ?>"><b><?= e($r['receipt_no']) ?></b></a>
              <br><span class="muted" style="font-size:12px"><?= e(fdate($r['receipt_date'])) ?></span></td>
          <td><a href="/ledger?id=<?= (int)$r['partner_id'] ?>"><?= e($r['partner_name']) ?></a></td>
          <td><?= e(RECEIPT_MODES[$r['mode']] ?? $r['mode']) ?><?= $r['reference'] ? '<br><span class="muted" style="font-size:12px">' . e($r['reference']) . '</span>' : '' ?></td>
          <td class="num"><?= e(fmoney($r['amount'])) ?></td>
          <td class="num"><?= (float)$r['tds_amount'] ? e(fmoney($r['tds_amount'])) : '<span class="muted">—</span>' ?></td>
          <td class="num"><?= e(fmoney($r['allocated'])) ?></td>
          <td class="num"><?= $left > 0.01 ? '<span class="pill p-warn">' . e(fmoney($left)) . '</span>' : '<span class="muted">—</span>' ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="muted" style="margin-top:10px;font-size:12.5px">Money that has arrived but is not yet matched to an invoice is a normal state, not a mistake. It shows in the customer's ledger as a credit, so their balance is right even before anybody has decided what it settles.</p>
