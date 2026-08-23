<?php $isDraft = $inv['status'] === 'DRAFT'; $isLive = in_array($inv['status'], ['ISSUED','PART_PAID','PAID'], true); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/invoices">Invoices</a> › <?= e($inv['invoice_no'] ?: 'Draft') ?></div>
<?= function_exists('chain_strip') ? chain_strip('INVOICE', (int)$inv['id'], 'INVOICE', (int)$inv['id']) : '' ?>
<?php $co = function_exists('company_profile') ? company_profile() : []; ?>
<?php if (!empty($co['legal_name']) || !empty($co['brand'])): ?>
<div class="panel" style="margin-top:12px;padding:12px 16px">
  <b style="font-size:14px"><?= e($co['legal_name'] ?: $co['brand']) ?></b>
  <?php if (!empty($co['address'])): ?><div class="muted" style="font-size:12.5px;white-space:pre-line"><?= e($co['address']) ?></div><?php endif; ?>
  <div class="muted" style="font-size:12.5px">
    <?php if (!empty($co['gstin'])): ?>GSTIN: <?= e($co['gstin']) ?><?php endif; ?>
    <?php if (!empty($co['state'])): ?> · <?= e($co['state']) ?><?php endif; ?>
    <?php $cl = function_exists('company_contact_line') ? company_contact_line() : ''; if ($cl !== ''): ?> · <?= e($cl) ?><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<div class="master-head">
  <div>
    <h1><?= e($inv['invoice_no'] ?: 'Draft invoice') ?>
      <span class="pill <?= $inv['status']==='PAID'?'p-ok':($inv['status']==='CANCELLED'?'p-mut':($isDraft?'p-mut':'p-warn')) ?>"><?= e(INV_STATUS[$inv['status']] ?? $inv['status']) ?></span></h1>
    <p class="sub" style="margin:2px 0 0">
      <a href="/ledger?id=<?= (int)$inv['partner_id'] ?>"><?= e($inv['partner_name']) ?></a>
      · <?= e(fdate($inv['invoice_date'])) ?><?= $inv['due_date'] ? ' · due ' . e(fdate($inv['due_date'])) : '' ?>
      <?= $inv['office_name'] ? ' · ' . e($inv['office_name']) : '' ?>
    </p>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/invoice-print?id=<?= (int)$inv['id'] ?>" target="_blank" rel="noopener">🖨 Print / PDF</a>
  </div>
</div>

<?php // ---- In MGH Books (when this invoice has been sent there) ----------- ?>
<?php if (!empty($booksConnected) && !empty($booksStatus)): $bs = $booksStatus; ?>
<div class="panel" style="margin-top:12px;border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 6%,transparent)">
  <b style="font-size:14px">📗 In MGH Books</b>
  <div class="muted" style="font-size:13px;margin-top:4px;line-height:1.7">
    Sent to Books as <code><?= e($bs['books_ref']) ?></code><?php if (!empty($bs['books_irn'])): ?> · IRN <code><?= e($bs['books_irn']) ?></code><?php endif; ?>.
    <?php if (trim((string)$bs['books_synced_at']) !== ''): ?>
      Books reports
      <?php if ($bs['books_status'] !== ''): ?><span class="pill <?= $bs['books_status']==='PAID'?'p-ok':'p-warn' ?>"><?= e($bs['books_status']) ?></span><?php endif; ?>
      — paid <?= e(fmoney((float)$bs['books_paid'])) ?>, outstanding <?= e(fmoney((float)$bs['books_outstanding'])) ?>
      <span style="font-size:11.5px">(as of <?= e(substr((string)$bs['books_synced_at'],0,10)) ?>)</span>.
      Books is the system of record for this figure.
    <?php else: ?>
      Waiting for Books to report its status back.
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php // ---- Where this invoice stands, and the one next thing -------------- ?>
<?php $ivOut = isset($settled['outstanding']) ? (float)$settled['outstanding'] : (float)$inv['total']; ?>
<div class="nowband" style="margin-top:14px">
  <?php if ($inv['status'] === 'CANCELLED'): ?>
    <div class="step">Cancelled.</div>
    <p class="next">This invoice was cancelled — the number is kept so the GST series has no gaps. Nothing to do here.</p>
  <?php elseif ($inv['status'] === 'PAID'): ?>
    <div class="step">Paid in full — done.</div>
    <p class="next">The whole <?= e(fmoney($inv['total'])) ?> has settled. Nothing outstanding.</p>
  <?php elseif ($isLive): ?>
    <div class="step">Issued — waiting on payment.</div>
    <p class="next"><b><?= e(fmoney($ivOut)) ?></b> is still outstanding. <b>Next:</b> record the money when it comes in, below.</p>
  <?php elseif ($isDraft && $missing): ?>
    <div class="step">Draft — not ready to issue yet.</div>
    <p class="next"><b>Next:</b> fix the points listed just below, add the lines being charged, then issue it.</p>
  <?php else: /* draft, ready */ ?>
    <div class="step">Draft — ready to issue.</div>
    <p class="next"><b>Next:</b> check the lines, then press <b>Issue this invoice</b> below. Issuing takes the next number in the branch's series.</p>
  <?php endif; ?>
</div>

<?php if ($inv['status'] === 'CANCELLED'): ?>
  <div class="msg msg-error" style="margin-top:14px">
    Cancelled <?= e(fdate(substr((string)$inv['cancelled_at'],0,10))) ?> by <?= e($inv['cancelled_by']) ?>.
    <?= e($inv['cancel_reason']) ?>
    <br><span style="font-size:12.5px">The number is kept deliberately — a GST series may not have gaps.</span>
  </div>
<?php endif; ?>

<?php if ($isDraft && $missing): ?>
  <div class="msg msg-warning" style="margin-top:14px">
    <b>Not ready to issue.</b>
    <ul style="margin:6px 0 0 18px;padding:0"><?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php // What this invoice is FOR — the interlinks in one place, so the bill is
      // clearly tied to its customer, contract, PO, business unit and the jobs it
      // charges, instead of those being scattered down the page. Each is clickable.
  $invJobs = [];
  foreach ($lines as $ln) { $jc = trim((string)($ln['job_code'] ?? '')); if ($jc !== '' && !isset($invJobs[$jc])) $invJobs[$jc] = (int)($ln['job_id'] ?? 0); }
  $sbuLbl = trim((string)($inv['sbu'] ?? '')) !== '' ? (OPS_SBUS[$inv['sbu']] ?? $inv['sbu']) : '';
?>
<div class="panel" style="margin-top:14px">
  <h3 style="margin-top:0">This invoice bills</h3>
  <div class="kv-grid">
    <div><span class="k"><?= e(TH('client')) ?></span><a href="/ledger?id=<?= (int)$inv['partner_id'] ?>"><?= e($inv['partner_name']) ?></a></div>
    <div><span class="k">Branch</span><?= e($inv['office_name'] ?: '—') ?></div>
    <div><span class="k">Contract</span><?php if (trim((string)($inv['contract_number'] ?? '')) !== ''): ?><a href="/calls?contract=<?= e(urlencode((string)$inv['contract_number'])) ?>"><?= e($inv['contract_number']) ?></a><?php else: ?>—<?php endif; ?></div>
    <div><span class="k">PO</span><?= e($inv['po_number'] ?: '—') ?></div>
    <?php if ($sbuLbl !== ''): ?><div><span class="k"><?= e(T('sbu')) ?></span><?= e($sbuLbl) ?></div><?php endif; ?>
  </div>
  <?php if ($invJobs): ?>
    <div style="margin-top:10px"><span class="k" style="display:block;margin-bottom:4px">Jobs on this invoice</span>
      <?php foreach ($invJobs as $jc => $jid): ?><a class="pill p-info" style="text-decoration:none;margin:0 6px 6px 0;display:inline-block" href="/job?id=<?= (int)$jid ?>">📋 <?= e($jc) ?></a><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="panel-split" style="margin-top:16px">
  <div class="panel">
    <h3 style="margin-top:0">Money</h3>
    <div class="kv-grid">
      <div><span class="k">Taxable</span><span><?= e(fmoney($inv['subtotal'])) ?></span></div>
      <?php if ((int)$inv['is_igst']): ?>
        <div><span class="k">IGST</span><span><?= e(fmoney($inv['igst'])) ?></span></div>
      <?php else: ?>
        <div><span class="k">CGST</span><span><?= e(fmoney($inv['cgst'])) ?></span></div>
        <div><span class="k">SGST</span><span><?= e(fmoney($inv['sgst'])) ?></span></div>
      <?php endif; ?>
      <?php if (abs((float)$inv['round_off']) > 0.001): ?>
        <div><span class="k">Rounding</span><span><?= e(fmoney($inv['round_off'])) ?></span></div>
      <?php endif; ?>
      <div><span class="k">Invoice total</span><span><b><?= e(fmoney($inv['total'])) ?></b></span></div>
    </div>
    <?php if ($isLive): ?>
      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
      <div class="kv-grid">
        <div><span class="k">Cash received</span><span><?= e(fmoney($settled['cash'])) ?></span></div>
        <div><span class="k">TDS withheld</span><span><?= e(fmoney($settled['tds'])) ?></span></div>
        <div><span class="k">Credit notes</span><span><?= e(fmoney($settled['credit'])) ?></span></div>
        <div><span class="k">Still outstanding</span><span><b class="<?= $settled['outstanding'] > 1 ? '' : 'muted' ?>"><?= e(fmoney($settled['outstanding'])) ?></b></span></div>
      </div>
      <p class="muted" style="font-size:12.5px;margin:10px 0 0">TDS the customer withheld settles the invoice just as cash does. Counting only the cash is what makes an ageing report chase somebody who has paid in full.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 style="margin-top:0">Tax treatment</h3>
    <div class="kv-grid">
      <div><span class="k">Place of supply</span><span><?= e($inv['place_of_supply'] !== '' ? (GST_STATE_CODES[$inv['place_of_supply']] ?? $inv['place_of_supply']) : 'not set') ?></span></div>
      <div><span class="k">Our state</span><span><?= e($inv['supplier_state'] !== '' ? (GST_STATE_CODES[$inv['supplier_state']] ?? $inv['supplier_state']) : 'not set') ?></span></div>
      <div><span class="k">Charged as</span><span><?= (int)$inv['is_igst'] ? 'IGST (inter-state)' : 'CGST + SGST (same state)' ?></span></div>
      <div><span class="k">Customer GSTIN</span><span><?= e($inv['gstin'] ?: '—') ?></span></div>
    </div>
    <p class="muted" style="font-size:12.5px;margin:10px 0 0">Decided once, when the invoice was created, and stored. A customer who later moves state does not silently rewrite last year's tax. (PO &amp; contract are shown under “This invoice bills” above.)</p>
  </div>
</div>

<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)"><b style="font-size:13.5px">What is being charged</b></div>
  <div class="dt-scroll">
    <table class="dt">
      <caption class="sr-only">Invoice lines</caption>
      <thead><tr><th scope="col">#</th><th scope="col">Description</th><th scope="col">Work</th>
        <th scope="col">SAC</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Rate</th>
        <th scope="col" class="num">Amount</th><th scope="col" class="num">GST</th><th scope="col" class="num">Line total</th>
        <?php if ($isDraft): ?><th scope="col"></th><?php endif; ?></tr></thead>
      <tbody>
      <?php
        // A combined monthly invoice carries several projects; when the lines
        // span more than one contract, break them under a contract sub-header so
        // the bill reads project by project. A single-contract invoice shows none.
        $contractsOnBill = [];
        foreach ($lines as $ln) $contractsOnBill[trim((string)($ln['contract_number'] ?? ''))] = true;
        $multiContract = count($contractsOnBill) > 1;
        $lastContract = null;
        $lineCols = $isDraft ? 10 : 9;
      ?>
      <?php if (!$lines): ?>
        <tr><td colspan="<?= $lineCols ?>" class="muted" style="padding:18px;text-align:center">No lines yet.</td></tr>
      <?php else: foreach ($lines as $ln):
        $lc = trim((string)($ln['contract_number'] ?? ''));
        if ($multiContract && $lc !== $lastContract):
          $lastContract = $lc; ?>
          <tr><td colspan="<?= $lineCols ?>" style="background:var(--soft);font-weight:600;font-size:12.5px;padding:7px 10px">
            <?= $lc !== '' ? '▤ Contract ' . e($lc) : 'Other work (no contract)' ?></td></tr>
        <?php endif; ?>
        <tr>
          <td><?= (int)$ln['seq'] ?></td>
          <td><?= e($ln['description']) ?></td>
          <td><?= $ln['job_code'] ? '<a href="/job?id=' . (int)$ln['job_id'] . '">' . e($ln['job_code']) . '</a>' : '<span class="muted">—</span>' ?></td>
          <td><?= e($ln['hsn_sac'] ?: '—') ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float)$ln['qty'], 3, '.', ''), '0'), '.') ?></td>
          <td class="num"><?= e(fmoney($ln['rate'])) ?></td>
          <td class="num"><?= e(fmoney($ln['amount'])) ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float)$ln['gst_pct'], 2, '.', ''), '0'), '.') ?>%</td>
          <td class="num"><b><?= e(fmoney($ln['line_total'])) ?></b></td>
          <?php if ($isDraft): ?>
            <td><form method="post" action="/invoice-line-delete" style="display:inline">
              <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
              <input type="hidden" name="line_id" value="<?= (int)$ln['id'] ?>">
              <button class="btn small danger" onclick="return confirm('Remove this line?')">Remove</button>
            </form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($isDraft): ?>
<form method="post" action="/invoice-line-add" class="panel" style="margin-top:14px">
  <h3 style="margin-top:0">Add a line</h3>
  <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Bill a finished <?= e(Tl('job')) ?></label>
      <select class="form-control" name="job_id">
        <option value="">— or describe it below —</option>
        <?php foreach ($billable as $j): ?>
          <option value="<?= (int)$j['id'] ?>"><?= e($j['job_code']) ?><?= (float)($j['billable_value'] ?: 0) ? ' — ' . e(fmoney($j['billable_value'])) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">Picking one takes its rate and quantity, so the figure is not typed twice.</span></div>
    <div><label>Description</label><input class="form-control" name="description" maxlength="500" placeholder="e.g. Third-party inspection — May 2026"></div>
    <div><label>SAC</label><input class="form-control" name="hsn_sac" maxlength="20" value="<?= e(books_default_sac()) ?>"></div>
    <div><label>Unit</label><input class="form-control" name="unit" maxlength="20" placeholder="e.g. manday, visit"></div>
    <div><label>Quantity</label><input class="form-control" name="qty" type="number" step="0.001" value="1"></div>
    <div><label>Rate</label><input class="form-control" name="rate" type="number" step="0.01" placeholder="0.00"></div>
    <div><label>GST %</label><input class="form-control" name="gst_pct" type="number" step="0.01" value="<?= e(books_default_gst()) ?>"></div>
  </div>
  <button class="btn" style="margin-top:12px">Add the line</button>
</form>

<?php if ($canIssue): ?>
<form method="post" action="/invoice-issue" class="panel" style="margin-top:14px">
  <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
  <h3 style="margin-top:0">Issue it</h3>
  <p class="muted" style="font-size:13px;margin:0 0 10px">Issuing allocates the next number in the branch's series. A draft that is never issued burns no number, and an issued invoice is never deleted — that is what keeps the series whole.</p>
  <button class="btn" <?= $missing ? 'disabled' : '' ?> onclick="return confirm('Issue this invoice for <?= e(fmoney($inv['total'])) ?>?')">Issue this invoice</button>
  <?php if ($missing): ?><span class="muted" style="font-size:12.5px;margin-left:8px">Fix the points above first.</span><?php endif; ?>
</form>
<?php endif; ?>
<?php endif; ?>

<?php if ($isLive): ?>
  <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)"><b style="font-size:13.5px">What has settled it</b></div>
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only">Receipts and credit notes against this invoice</caption>
        <thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Reference</th><th scope="col" class="num">Amount</th></tr></thead>
        <tbody>
        <?php $any = false; foreach ($receipts as $a): $any = true; ?>
          <tr><td><?= e(fdate($a['receipt_date'])) ?></td>
              <td><?= $a['kind']==='TDS' ? 'TDS withheld' : e(RECEIPT_MODES[$a['mode']] ?? 'Receipt') ?></td>
              <td><a href="/receipt?id=<?= (int)$a['receipt_id'] ?>"><?= e($a['receipt_no']) ?></a><?= $a['reference'] ? ' <span class="muted" style="font-size:12px">' . e($a['reference']) . '</span>' : '' ?></td>
              <td class="num"><?= e(fmoney($a['amount'])) ?></td></tr>
        <?php endforeach; foreach ($notes as $n): $any = true; ?>
          <tr><td><?= e(fdate($n['cn_date'])) ?></td><td>Credit note</td>
              <td><b><?= e($n['cn_no']) ?></b> <span class="muted" style="font-size:12px"><?= e(CN_REASONS[$n['reason']] ?? $n['reason']) ?><?= $n['reason_note'] ? ' — ' . e($n['reason_note']) : '' ?></span></td>
              <td class="num"><?= e(fmoney($n['total'])) ?></td></tr>
        <?php endforeach; if (!$any): ?>
          <tr><td colspan="4" class="muted" style="padding:18px;text-align:center">Nothing yet — this invoice is fully outstanding.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 16px;border-top:1px solid var(--line);display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn small secondary" href="/receipt-new?partner=<?= (int)$inv['partner_id'] ?>">Record money received</a>
      <a class="btn small secondary" href="/ledger?id=<?= (int)$inv['partner_id'] ?>">Customer ledger</a>
    </div>
  </div>

  <?php if ($canIssue && $settled['outstanding'] > 1 && $inv['status'] !== 'CANCELLED'): ?>
  <form method="post" action="/credit-note-new" class="panel" style="margin-top:14px">
    <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
    <h3 style="margin-top:0">Raise a credit note</h3>
    <p class="muted" style="font-size:13px;margin:0 0 10px">The way to reduce an issued invoice. Editing it would leave two versions of a document the customer already has, which under GST is not a correction.</p>
    <div class="form-grid" style="gap:12px 16px">
      <div><label>Why *</label><select class="form-control" name="reason" required>
        <option value="">— choose —</option>
        <?php foreach ($reasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Date</label><input class="form-control" type="date" name="cn_date" value="<?= e(date('Y-m-d')) ?>"></div>
      <div><label>Amount before tax *</label><input class="form-control" name="subtotal" type="number" step="0.01" required placeholder="0.00"></div>
      <div><label>GST %</label><input class="form-control" name="gst_pct" type="number" step="0.01" value="<?= e(books_default_gst()) ?>"></div>
      <div class="ff-wide"><label>Note</label><input class="form-control" name="reason_note" maxlength="500" placeholder="What was agreed, and with whom"></div>
    </div>
    <button class="btn" style="margin-top:12px">Raise the credit note</button>
  </form>
  <?php endif; ?>
<?php endif; ?>

<?php if ($canCancel && $inv['status'] !== 'CANCELLED' && $settled['settled'] <= 0): ?>
<form method="post" action="/invoice-cancel" class="panel" style="margin-top:14px;border-left:3px solid var(--bad)">
  <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
  <h3 style="margin-top:0">Cancel it</h3>
  <p class="muted" style="font-size:13px;margin:0 0 10px">For an invoice that should never have existed. The row and the number stay; only the status changes. Once anything has settled against it, this is refused and a credit note is the only route.</p>
  <input class="form-control" name="reason" maxlength="400" required placeholder="Why is it being cancelled? *" style="max-width:520px">
  <button class="btn danger" style="margin-top:12px" onclick="return confirm('Cancel this invoice?')">Cancel this invoice</button>
</form>
<?php endif; ?>
