<?php
//  Record a payment against ONE invoice (owner, 27 Nov 2026 — "list of invoices with record payment
//  details to directly feed the payment"). The receipt is written by the same code as Money in and
//  matched to this invoice in the same step. A payment that covers several invoices still goes
//  through Money in → + Record money received.
$no = $inv['invoice_no'] !== '' ? $inv['invoice_no'] : 'draft #' . (int)$inv['id'];
$out = (float)$settled['outstanding'];
?>
<div class="master-head"><div><h1>Record payment — <?= e($no) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($inv['partner_name']) ?> · invoice <?= e(fmoney($inv['total'])) ?> · still owed <b><?= e(fmoney($out)) ?></b></p></div></div>

<form method="post" action="/invoice-pay" class="panel ipay" style="margin-top:16px;max-width:720px">
  <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
  <div class="form-grid" style="gap:12px 16px">
    <div><label for="ipay-amt">Amount received in the bank *</label>
      <input class="form-control" id="ipay-amt" name="amount" type="number" step="0.01" min="0" required value="<?= e(number_format($out, 2, '.', '')) ?>">
      <span class="muted" style="font-size:12px">What actually arrived, after any TDS the customer kept.</span></div>
    <div><label for="ipay-tds">TDS the customer withheld</label>
      <input class="form-control" id="ipay-tds" name="tds_amount" type="number" step="0.01" min="0" placeholder="0.00">
      <span class="muted" style="font-size:12px">Only if they deducted it — e.g. 10% of this invoice = <?= e(fmoney(round((float)$inv['total'] * 0.10, 2))) ?>. It settles the invoice too.</span></div>
    <div><label for="ipay-date">Date received</label><input class="form-control" id="ipay-date" type="date" name="receipt_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div><label for="ipay-mode">How it came</label>
      <select class="form-control" id="ipay-mode" name="mode"><?php foreach (RECEIPT_MODES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff-wide"><label for="ipay-ref">Reference</label><input class="form-control" id="ipay-ref" name="reference" maxlength="120" placeholder="UTR, cheque number, or whatever the statement shows"></div>
  </div>
  <p class="muted" id="ipay-sum" style="font-size:13px;margin:12px 0 0" aria-live="polite"></p>
  <div class="ipay-act" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn">Record and match it</button>
    <a class="btn secondary" href="/invoice?id=<?= (int)$inv['id'] ?>">Cancel</a>
  </div>
  <p class="muted" style="font-size:12.5px;margin:10px 0 0">Paid more than this invoice owes, or one payment for several invoices? Use
    <a href="/receipt-new?partner=<?= (int)$inv['partner_id'] ?>">Money in → + Record money received</a> — the extra stays on the receipt to match later.</p>
</form>
<script>
(function () {
  var out = <?= json_encode(round($out, 2)) ?>, a = document.getElementById('ipay-amt'), t = document.getElementById('ipay-tds'),
      s = document.getElementById('ipay-sum'), touched = false;
  function inr(v) { return '₹' + (Math.round(v * 100) / 100).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
  function show() {
    var av = parseFloat(a.value) || 0, tv = parseFloat(t.value) || 0, left = Math.round((out - av - tv) * 100) / 100;
    s.textContent = 'Bank ' + inr(av) + (tv > 0 ? ' + TDS ' + inr(tv) : '') + ' = ' + inr(av + tv) + ' · '
      + (left > 0.01 ? inr(left) + ' will still be owed' : (left < -0.01 ? inr(-left) + ' more than it owes — refused' : 'the invoice will be fully paid'));
  }
  a.addEventListener('input', function () { touched = true; show(); });
  //  Typing the TDS lowers the bank amount by the same figure, until the bank amount is typed by hand.
  t.addEventListener('input', function () { if (!touched) a.value = Math.max(0, Math.round((out - (parseFloat(t.value) || 0)) * 100) / 100).toFixed(2); show(); });
  show();
})();
</script>
<style>@media (max-width:560px){.ipay .form-grid{grid-template-columns:1fr!important}.ipay-act .btn{flex:1 1 100%;text-align:center}}</style>
