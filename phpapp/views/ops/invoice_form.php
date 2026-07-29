<div class="crumbs"><a href="/">Home</a> › <a href="/invoices">Invoices</a> › New</div>
<div class="master-head"><div><h1>New invoice</h1>
  <p class="sub" style="margin:2px 0 0">Start a draft. Nothing is numbered until it is issued, so an abandoned draft leaves no gap in the series.</p></div></div>

<form method="post" action="/invoice-new" class="panel" style="margin-top:16px">
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Customer *</label>
      <select name="partner_id" required class="searchable" id="inv-partner">
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($partner && (int)$partner['id']===(int)$c['id'])?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Branch *</label>
      <select name="office_id" required>
        <option value="">— choose —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">Decides the numbering series.</span></div>
    <div><label>Invoice date</label><input type="date" name="invoice_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div><label>Credit days</label><input name="credit_days" type="number" min="0" value="30">
      <span class="muted" style="font-size:12px">Sets the due date, which is what the ageing report counts from.</span></div>
    <div><label>Payment terms</label>
      <?php if ($terms): ?>
        <select name="payment_terms"><option value="">—</option>
          <?php foreach ($terms as $k=>$v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
      <?php else: ?><input name="payment_terms" maxlength="120"><?php endif; ?></div>
    <div><label>PO number</label><input name="po_number" maxlength="80"></div>
    <div><label>Contract number</label><input name="contract_number" maxlength="80"></div>
    <div class="ff-wide"><label>Notes on the invoice</label><textarea name="notes" rows="2"></textarea></div>
  </div>

  <?php if ($billable): ?>
    <h3 style="margin:18px 0 8px">Unbilled work for this customer</h3>
    <p class="muted" style="font-size:13px;margin:0 0 10px">Tick what this invoice covers. One invoice across several <?= e(Tlp('job')) ?> is normal for a monthly account, and each line keeps its link to the work.</p>
    <div class="dt-scroll" style="border:1px solid var(--line);border-radius:10px">
      <table class="dt">
        <caption class="sr-only">Unbilled <?= e(Tlp('job')) ?></caption>
        <thead><tr><th scope="col" style="width:34px"><span class="sr-only">Include</span></th>
          <th scope="col"><?= e(TH('job')) ?></th><th scope="col">Closed</th><th scope="col" class="num">Value</th></tr></thead>
        <tbody>
        <?php foreach ($billable as $j): ?>
          <tr><td><label class="sr-only" for="nj-<?= (int)$j['id'] ?>">Include <?= e($j['job_code']) ?></label>
                  <input type="checkbox" id="nj-<?= (int)$j['id'] ?>" name="jobs[]" value="<?= (int)$j['id'] ?>" checked></td>
              <td><?= e($j['job_code']) ?></td>
              <td><?= $j['closed_at'] ? e(fdate(substr((string)$j['closed_at'],0,10))) : '—' ?></td>
              <td class="num"><?= (float)($j['billable_value'] ?: 0) ? e(fmoney($j['billable_value'])) : '<span class="muted">no value</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php elseif ($partner): ?>
    <p class="muted" style="margin-top:14px">This customer has no closed, unbilled <?= e(Tlp('job')) ?>. You can still add lines by hand once the draft exists.</p>
  <?php endif; ?>

  <button class="btn" style="margin-top:14px">Start the draft</button>
  <a class="btn secondary" href="/invoices" style="margin-left:8px">Cancel</a>
</form>
