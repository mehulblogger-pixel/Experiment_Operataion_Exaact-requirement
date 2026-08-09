<div class="crumbs"><a href="/">Home</a> › <a href="/invoices">Invoices</a> › New</div>
<div class="master-head"><div><h1>New invoice</h1>
  <p class="sub mt-0">Start a draft. Nothing is numbered until it is issued, so an abandoned draft leaves no gap in the series.</p></div></div>

<?php
// Everything this invoice can already know. A value only appears when the work
// agrees on it — see books_carry_for(). $carry is [] when no customer has been
// chosen yet, which is the ordinary first visit.
$carry = $carry ?? [];
$from  = $carry['from'] ?? [];
$clash = $carry['conflict'] ?? [];
// "Where did this come from", under a prefilled box. A silent prefill is worse
// than an empty one: nobody can check a number whose source they cannot see.
$src = function ($k) use ($from) {
    return isset($from[$k])
        ? '<div class="ff-help">Carried from ' . e($from[$k]) . ' — change it if this invoice differs.</div>' : '';
};
?>

<form method="post" action="/invoice-new" class="panel mt-4">

  <div class="form-sec"><h3>Who it is for</h3>
    <p>Pick the customer first — the terms, the contract and the unbilled work all follow from it.</p></div>
  <div class="form-grid">
    <div class="ff ff-req"><label>Customer <a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a></label>
      <select name="partner_id" required class="form-control searchable" id="inv-partner"
              onchange="if(this.value) location.href='/invoice-new?partner='+encodeURIComponent(this.value)">
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($partner && (int)$partner['id']===(int)$c['id'])?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="ff-help">Choosing one reloads this form with everything already known about them.</div></div>

    <div class="ff ff-req"><label>Branch</label>
      <select name="office_id" required class="form-control">
        <option value="">— choose —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
      </select>
      <div class="ff-help">Decides the numbering series.</div></div>

    <div class="ff"><label>Invoice date</label>
      <input type="date" class="form-control" name="invoice_date" value="<?= e(date('Y-m-d')) ?>"></div>
  </div>

  <div class="form-sec"><h3>Terms</h3>
    <p>Taken from what was agreed with this customer and from the work being billed. Anything here can be
      overruled for this one invoice.</p></div>
  <div class="form-grid">
    <div class="ff"><label>Payment terms</label>
      <?php $pt = (string)($carry['payment_terms'] ?? ''); ?>
      <?php if ($terms): ?>
        <select name="payment_terms" class="form-control"><option value="">—</option>
          <?php foreach ($terms as $k=>$v): ?>
            <option value="<?= e($v) ?>" <?= ($pt !== '' && $pt === $v) ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
          <?php if ($pt !== '' && !in_array($pt, array_values($terms), true)): ?>
            <option value="<?= e($pt) ?>" selected><?= e($pt) ?></option>
          <?php endif; ?>
        </select>
      <?php else: ?>
        <input name="payment_terms" class="form-control" maxlength="120" value="<?= e($pt) ?>">
      <?php endif; ?>
      <?= $src('payment_terms') ?></div>

    <div class="ff"><label>Credit days</label>
      <?php $cd = ($carry['credit_days'] ?? '') !== '' ? (string)$carry['credit_days'] : '30'; ?>
      <input name="credit_days" type="number" min="0" class="form-control" value="<?= e($cd) ?>">
      <?= $src('credit_days') ?: '<div class="ff-help">Sets the due date, which is what the ageing report counts from.</div>' ?></div>

    <div class="ff"><label>PO number</label>
      <input name="po_number" class="form-control" maxlength="80" value="<?= e((string)($carry['po_number'] ?? '')) ?>">
      <?php if (isset($clash['po_number'])): ?>
        <div class="ff-err">The ticked work sits under more than one purchase order. Type the right one, or raise
          a separate invoice per order.</div>
      <?php else: ?><?= $src('po_number') ?><?php endif; ?></div>

    <div class="ff"><label>Contract number</label>
      <input name="contract_number" class="form-control" maxlength="80" value="<?= e((string)($carry['contract_number'] ?? '')) ?>">
      <?php if (isset($clash['contract_number'])): ?>
        <div class="ff-err">The ticked work spans <?= (int)count($clash['contract_number']) ?> contracts
          (<?= e(implode(', ', array_slice($clash['contract_number'], 0, 4))) ?>). Nothing has been filled in —
          pick one, or untick until a single contract is left.</div>
      <?php else: ?><?= $src('contract_number') ?><?php endif; ?></div>

    <div class="ff ff-wide"><label>Notes on the invoice</label>
      <textarea name="notes" class="form-control" rows="2"></textarea></div>
  </div>

  <?php if ($billable): ?>
    <div class="form-sec"><h3>What it covers</h3>
      <p>Tick what this invoice is for. One invoice across several <?= e(Tlp('job')) ?> is normal on a monthly
        account, and every line keeps its link back to the work.</p></div>
    <div class="dt-scroll" style="border:1px solid var(--line);border-radius:var(--radius-sm)">
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
    <div class="form-sec"><h3>What it covers</h3></div>
    <div class="empty">
      <div class="empty-ic">📋</div>
      <div class="empty-t">No closed, unbilled <?= e(Tlp('job')) ?> for this customer</div>
      <div class="empty-d">Work only appears here once it is closed. You can still start the draft and add lines
        by hand — or close the work first, so every line keeps its link back to the job.</div>
      <a class="btn secondary" href="/jobs">Open the <?= e(THP('job')) ?> register</a>
    </div>
  <?php endif; ?>

  <div class="form-actions">
    <button class="btn" type="submit">Start the draft</button>
    <a class="btn secondary" href="/invoices">Cancel</a>
  </div>
</form>
