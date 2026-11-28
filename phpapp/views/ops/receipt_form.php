<div class="master-head"><div><h1>Record money received</h1>
  <p class="sub" style="margin:2px 0 0">What arrived, and what the customer withheld. The next screen decides which invoices it settles.</p></div></div>

<form method="post" action="/receipt-new" class="panel" style="margin-top:16px;max-width:820px">
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Customer * <a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a></label>
      <select name="partner_id" required class="searchable" id="rc-partner">
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($partner && (int)$partner['id']===(int)$c['id'])?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php //  Owner, 28 Nov 2026: the branch starts on the one that invoiced this customer's unpaid bills.
          [$rcOff, $rcWhy] = $officeDefault ?? [0, '']; ?>
    <div><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$rcOff === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select>
      <?php if ($rcOff): ?><span class="muted" style="font-size:12px"><?= $rcWhy === 'invoices' ? 'Filled in from the branch that raised this customer\'s unpaid invoices' : ($partner ? 'Filled in from this customer\'s billing branch (or yours)' : 'Your own branch') ?> — change it if another branch received the money.</span><?php endif; ?></div>
    <div><label>Date received</label><input class="form-control" type="date" name="receipt_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div><label>How it came</label>
      <select class="form-control" name="mode"><?php foreach (RECEIPT_MODES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label>Amount in the bank *</label><input class="form-control" name="amount" type="number" step="0.01" required placeholder="0.00">
      <span class="muted" style="font-size:12px">What actually arrived, after any deduction.</span></div>
    <div><label>TDS the customer withheld</label><input class="form-control" name="tds_amount" type="number" step="0.01" placeholder="0.00">
      <span class="muted" style="font-size:12px">This settles the invoice too. Leaving it out is why an ageing report chases a customer who paid in full.</span></div>
    <div><label>Bank charges</label><input class="form-control" name="bank_charges" type="number" step="0.01" min="0" placeholder="0.00">
      <span class="muted" style="font-size:12px">What the bank kept. These are the customer's to bear, so they do <b>not</b> settle the invoice — the shortfall stays owed on it.</span></div>
    <div><label>Bank</label><input class="form-control" name="bank" maxlength="120"></div>
    <div class="ff-wide"><label>Reference</label><input class="form-control" name="reference" maxlength="120" placeholder="UTR, cheque number, or whatever the statement shows"></div>
    <div class="ff-wide"><label>Notes</label><input class="form-control" name="notes" maxlength="500"></div>
  </div>

  <?php // Owner, 28 Nov 2026: "there must be option to select the invoice number against which the
        // money received is to be booked". Tick the invoices here and the money is matched on Save —
        // the same matching as the next screen (books_allocate_report), so the same checks apply.
        $preInv = (int)($_GET['invoice'] ?? 0); ?>
  <?php if ($open): ?>
    <h3 style="margin:18px 0 4px">Book this money against</h3>
    <p class="muted" style="margin:0 0 8px;font-size:12.5px">Tick the invoice(s) it pays — the amount fills in (change it if the customer paid part). Put the TDS on the invoice it was deducted from. Leave all unticked to match it on the next screen.</p>
    <div style="overflow-x:auto">
    <table class="dt" id="rc-book">
      <caption class="sr-only">Unpaid invoices of this customer</caption>
      <thead><tr><th scope="col">Book</th><th scope="col">Invoice</th><th scope="col">Date</th><th scope="col">Due</th><th scope="col" class="num">Outstanding</th><th scope="col" class="num">Cash booked</th><th scope="col" class="num">TDS booked</th></tr></thead>
      <tbody>
      <?php $tot=0; foreach ($open as $o): $tot += (float)$o['outstanding']; $oid = (int)$o['id']; ?>
        <tr data-out="<?= e(number_format((float)$o['outstanding'], 2, '.', '')) ?>">
            <td><input type="checkbox" name="pick[<?= $oid ?>]" value="1" aria-label="Book against <?= e($o['invoice_no']) ?>" <?= $preInv === $oid ? 'checked' : '' ?>></td>
            <td><?= e($o['invoice_no']) ?></td><td><?= e(fdate($o['invoice_date'])) ?></td>
            <td><?= $o['due_date'] ? e(fdate($o['due_date'])) : '—' ?></td>
            <td class="num"><?= e(fmoney($o['outstanding'])) ?></td>
            <td class="num"><input class="form-control" style="max-width:130px;margin-left:auto" type="number" step="0.01" min="0" name="cash[<?= $oid ?>]" aria-label="Cash booked on <?= e($o['invoice_no']) ?>"></td>
            <td class="num"><input class="form-control" style="max-width:120px;margin-left:auto" type="number" step="0.01" min="0" name="tds[<?= $oid ?>]" aria-label="TDS booked on <?= e($o['invoice_no']) ?>"></td></tr>
      <?php endforeach; ?>
        <tr><td colspan="4"><b>Total owed</b></td><td class="num"><b><?= e(fmoney($tot)) ?></b></td><td class="num" id="rc-bk-cash">—</td><td class="num" id="rc-bk-tds">—</td></tr>
      </tbody>
    </table></div>
  <?php elseif ($partner): ?>
    <p class="muted" style="margin-top:14px">This customer has no unpaid invoice — the money is recorded and can be matched later.</p>
  <?php endif; ?>

  <?php // Form Designer — render any field this company added, and apply their
        // label / order / hide / required overrides to the built-in ones.
        if (function_exists('fd_extra_fields')) echo fd_extra_fields('receipt', 0); ?>
  <button class="btn" style="margin-top:14px">Record it</button>
  <a class="btn secondary" href="/receipts" style="margin-left:8px">Cancel</a>
</form>
<script>(function(){ var s=document.getElementById('rc-partner');
  if (s) s.addEventListener('change', function(){ if (s.value) window.location = '/receipt-new?partner=' + encodeURIComponent(s.value); });
  var t=document.getElementById('rc-book'); if(!t) return;
  var amt=document.querySelector('input[name="amount"]'), tds=document.querySelector('input[name="tds_amount"]');
  var num=function(v){ v=parseFloat(v); return isNaN(v)?0:v; };
  function sum(k){ var x=0; t.querySelectorAll('input[name^="'+k+'["]').forEach(function(i){ x+=num(i.value); }); return x; }
  function totals(){ var c=document.getElementById('rc-bk-cash'), d=document.getElementById('rc-bk-tds');
    if(c) c.textContent = sum('cash') ? sum('cash').toFixed(2) : '—'; if(d) d.textContent = sum('tds') ? sum('tds').toFixed(2) : '—'; }
  function fill(row){ var out=num(row.getAttribute('data-out')), c=row.querySelector('input[name^="cash["]'), d=row.querySelector('input[name^="tds["]');
    if (c.value !== '') return;
    var left = num(amt && amt.value) + num(tds && tds.value) - sum('cash') - sum('tds');
    var v = Math.max(0, Math.min(out, left > 0 ? left : out)); c.value = v ? v.toFixed(2) : ''; }
  t.querySelectorAll('tbody tr[data-out]').forEach(function(row){
    var cb=row.querySelector('input[type=checkbox]');
    cb.addEventListener('change', function(){ if (cb.checked) fill(row); else row.querySelectorAll('input[type=number]').forEach(function(i){ i.value=''; }); totals(); });
    row.querySelectorAll('input[type=number]').forEach(function(i){ i.addEventListener('input', function(){ if (i.value !== '') cb.checked = true; totals(); }); });
    if (cb.checked) fill(row);
  });
  totals();
})();</script>
