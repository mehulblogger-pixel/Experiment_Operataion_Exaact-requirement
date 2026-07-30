<div class="crumbs"><a href="/">Home</a> › <a href="/receipts">Money in</a> › New</div>
<div class="master-head"><div><h1>Record money received</h1>
  <p class="sub" style="margin:2px 0 0">What arrived, and what the customer withheld. The next screen decides which invoices it settles.</p></div></div>

<form method="post" action="/receipt-new" class="panel" style="margin-top:16px;max-width:820px">
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Customer *</label>
      <select name="partner_id" required class="searchable">
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($partner && (int)$partner['id']===(int)$c['id'])?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Date received</label><input class="form-control" type="date" name="receipt_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div><label>How it came</label>
      <select class="form-control" name="mode"><?php foreach (RECEIPT_MODES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label>Amount in the bank *</label><input class="form-control" name="amount" type="number" step="0.01" required placeholder="0.00">
      <span class="muted" style="font-size:12px">What actually arrived, after any deduction.</span></div>
    <div><label>TDS the customer withheld</label><input class="form-control" name="tds_amount" type="number" step="0.01" placeholder="0.00">
      <span class="muted" style="font-size:12px">This settles the invoice too. Leaving it out is why an ageing report chases a customer who paid in full.</span></div>
    <div><label>Bank charges</label><input class="form-control" name="bank_charges" type="number" step="0.01" placeholder="0.00"></div>
    <div><label>Bank</label><input class="form-control" name="bank" maxlength="120"></div>
    <div class="ff-wide"><label>Reference</label><input class="form-control" name="reference" maxlength="120" placeholder="UTR, cheque number, or whatever the statement shows"></div>
    <div class="ff-wide"><label>Notes</label><input class="form-control" name="notes" maxlength="500"></div>
  </div>

  <?php if ($open): ?>
    <h3 style="margin:18px 0 8px">What this customer currently owes</h3>
    <table class="dt">
      <caption class="sr-only">Open invoices</caption>
      <thead><tr><th scope="col">Invoice</th><th scope="col">Date</th><th scope="col">Due</th><th scope="col" class="num">Outstanding</th></tr></thead>
      <tbody>
      <?php $tot=0; foreach ($open as $o): $tot += (float)$o['outstanding']; ?>
        <tr><td><?= e($o['invoice_no']) ?></td><td><?= e(fdate($o['invoice_date'])) ?></td>
            <td><?= $o['due_date'] ? e(fdate($o['due_date'])) : '—' ?></td>
            <td class="num"><?= e(fmoney($o['outstanding'])) ?></td></tr>
      <?php endforeach; ?>
        <tr><td colspan="3"><b>Total</b></td><td class="num"><b><?= e(fmoney($tot)) ?></b></td></tr>
      </tbody>
    </table>
  <?php endif; ?>

  <button class="btn" style="margin-top:14px">Record it</button>
  <a class="btn secondary" href="/receipts" style="margin-left:8px">Cancel</a>
</form>
