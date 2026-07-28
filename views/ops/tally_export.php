<?php
  $kind = $kind ?? 'SALES';
  $isRcp = $kind === 'RECEIPT';
  $qs = fn($over = []) => http_build_query(array_merge(['kind'=>$kind,'from'=>$from,'to'=>$to] + ($showDone?['done'=>1]:[]), $over));
  $readyTotal = 0.0;
  foreach ($ready as $r) $readyTotal += $isRcp ? $r['receipt_amount'] : $r['total'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/invoicing">Invoicing</a> › Tally export</div>
<div class="master-head">
  <div><h1>Tally export</h1>
  <p class="sub" style="margin:2px 0 0">Hand the invoices over to Tally as vouchers it can import — with the party, the bill reference and the tax split already worked out. Nothing is guessed: a row that cannot be decided is refused and says why.</p></div>
</div>

<form method="get" action="/tally" class="panel" style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
  <div><label>What</label>
    <select name="kind">
      <option value="SALES"<?= !$isRcp?' selected':'' ?>>Sales vouchers (invoices raised)</option>
      <option value="RECEIPT"<?= $isRcp?' selected':'' ?>>Receipt vouchers (payments received)</option>
    </select></div>
  <div><label><?= $isRcp ? 'Payment from' : 'Invoice from' ?></label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label>to</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <label style="display:flex;gap:6px;align-items:center;margin-bottom:8px">
    <input type="checkbox" name="done" value="1"<?= $showDone?' checked':'' ?>> <span>Show what has already gone</span></label>
  <button class="btn">Show</button>
</form>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-ok"><div class="qic">✓</div><div class="qn"><?= count($ready) ?></div><div class="ql">Ready to export</div></div>
  <div class="qcard tone-info"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney($readyTotal) ?></div><div class="ql"><?= $isRcp ? 'Receipts' : 'Invoice value' ?></div></div>
  <div class="qcard <?= $blocked ? 'tone-bad' : '' ?>"><div class="qic">!</div><div class="qn"><?= count($blocked) ?></div><div class="ql">Cannot go yet</div></div>
</div>

<?php if ($ready): ?>
<form method="post" action="/tally-export" class="panel" style="margin-top:16px">
  <input type="hidden" name="kind" value="<?= e($kind) ?>">
  <input type="hidden" name="from" value="<?= e($from) ?>">
  <input type="hidden" name="to" value="<?= e($to) ?>">
  <div class="ctitle"><h3>Download the batch</h3></div>
  <p class="muted" style="margin:0 0 10px">The <?= count($ready) ?> voucher(s) below are recorded as exported when you download, so they are not offered again and cannot be imported into Tally twice. If the import fails at the other end, undo the batch and it comes back.</p>
  <label style="display:flex;gap:6px;align-items:center;margin-bottom:10px">
    <input type="checkbox" name="masters" value="1"> <span>Also create the party ledgers (tick for a fresh Tally company; on an established one Tally will report the names it already has)</span></label>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn" name="format" value="xml">⬇ Tally XML (import this)</button>
    <button class="btn secondary" name="format" value="csv">⬇ CSV (to look at first)</button>
  </div>
</form>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden;margin-top:16px">
  <div class="ctitle" style="padding:14px 18px 0">
    <h3><?= $isRcp ? 'Receipts' : 'Invoices' ?> in this window <span class="muted">(<?= count($rows) ?>)</span></h3>
  </div>
  <?php if (!$rows): ?>
    <p class="muted" style="padding:18px">Nothing <?= $isRcp ? 'was paid' : 'was invoiced' ?> in that window<?= $showDone ? '' : ', or everything in it has already been exported' ?>.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="dt" style="margin-top:8px">
    <thead><tr>
      <th>Date</th><th><?= $isRcp ? 'Against invoice' : 'Invoice' ?></th><th>Party</th><th>GSTIN / place of supply</th>
      <?php if (!$isRcp): ?><th class="num">Taxable</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><?php endif; ?>
      <th class="num"><?= $isRcp ? 'Received' : 'Total' ?></th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr<?= $r['ok'] ? '' : ' style="background:rgba(220,60,60,.06)"' ?>>
        <td><?= e($isRcp ? $r['payment_date'] : $r['invoice_date']) ?: '—' ?><br><span class="muted" style="font-size:12px"><?= e($r['job_code']) ?></span></td>
        <td><b><?= e($r['invoice_number'] ?: '—') ?></b></td>
        <td><?= e(tally_party($r)) ?><?php if ($r['office_name']): ?><br><span class="muted" style="font-size:12px"><?= e($r['office_name']) ?></span><?php endif; ?></td>
        <td><?= e($r['gstin'] ?: '—') ?><br><span class="muted" style="font-size:12px"><?= e($r['place_of_supply'] ?: 'state unknown') ?><?= $r['ok'] && !$isRcp ? ($r['interstate'] ? ' · inter-state' : ' · within state') : '' ?></span></td>
        <?php if (!$isRcp): ?>
          <td class="num"><?= fmoney($r['taxable']) ?><br><span class="muted" style="font-size:12px"><?= e(rtrim(rtrim(number_format($r['gst_pct'],2,'.',''),'0'),'.')) ?>%</span></td>
          <td class="num"><?= $r['cgst'] ? fmoney($r['cgst']) : '—' ?></td>
          <td class="num"><?= $r['sgst'] ? fmoney($r['sgst']) : '—' ?></td>
          <td class="num"><?= $r['igst'] ? fmoney($r['igst']) : '—' ?></td>
        <?php endif; ?>
        <td class="num"><b><?= fmoney($isRcp ? ($r['receipt_amount'] ?? 0) : $r['total']) ?></b></td>
        <td><?php
          if (!$r['ok']) echo '<span class="pill p-bad">Cannot go</span>';
          elseif ($r['done_batch']) echo '<span class="pill p-mut">Exported</span><br><span class="muted" style="font-size:11px">' . e($r['done_batch']) . '</span>';
          else echo '<span class="pill p-ok">Ready</span>';
        ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($blocked): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--bad,#c62828)">
  <div class="ctitle"><h3>Cannot go yet <span class="muted">(<?= count($blocked) ?>)</span></h3></div>
  <p class="muted" style="margin:0 0 10px">These are left out of the file rather than exported with a guessed figure. Fix the reason and they appear above.</p>
  <table class="dt">
    <thead><tr><th>Job</th><th>Party</th><th>Why</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($blocked as $r): ?>
      <tr>
        <td><b><?= e($r['job_code']) ?></b><br><span class="muted" style="font-size:12px"><?= e($r['invoice_number'] ?: 'no invoice number') ?></span></td>
        <td><?= e(tally_party($r)) ?></td>
        <td><?= e(implode(' · ', $r['issues'])) ?></td>
        <td><a class="btn small" href="/job?id=<?= (int)$r['id'] ?>#invoice">Open job →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<details class="panel" style="margin-top:16px"<?= $cfg['state_code'] === '' ? ' open' : '' ?>>
  <summary style="cursor:pointer;font-weight:600">Ledger names &amp; tax defaults</summary>
  <p class="muted" style="margin:10px 0">These are the names in <em>your</em> Tally company. The defaults are what Tally ships with, so a first export usually works without changing anything — except our own state, which decides IGST against CGST+SGST and has no sensible default.</p>
  <form method="post" action="/tally-settings" class="form-grid" style="gap:12px 16px">
    <div><label>Tally company name <span class="muted">(optional)</span></label>
      <input name="tally_company" value="<?= e($cfg['tally_company']) ?>" placeholder="leave blank to import into whichever company is open"></div>
    <div><label>Our state <span class="muted">(place of supply we sell from)</span></label>
      <select name="tally_state">
        <option value="">— not set —</option>
        <?php foreach ($states as $code => $label): $code = tally_code_str($code); ?>
          <option value="<?= e($label) ?>"<?= $cfg['state_code'] === $code ? ' selected' : '' ?>><?= e($label) ?> (<?= e($code) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Sales ledger</label><input name="tally_sales_ledger" value="<?= e($cfg['tally_sales_ledger']) ?>"></div>
    <div><label>Bank / cash ledger <span class="muted">(receipts)</span></label><input name="tally_bank_ledger" value="<?= e($cfg['tally_bank_ledger']) ?>"></div>
    <div><label>TDS ledger <span class="muted">(tax the customer withheld)</span></label><input name="tally_tds_ledger" value="<?= e($cfg['tally_tds_ledger']) ?>">
      <span class="muted" style="font-size:12px">Without this, a receipt short by the deduction leaves the party ledger short for ever.</span></div>
    <div><label>CGST ledger</label><input name="tally_cgst_ledger" value="<?= e($cfg['tally_cgst_ledger']) ?>"></div>
    <div><label>SGST ledger</label><input name="tally_sgst_ledger" value="<?= e($cfg['tally_sgst_ledger']) ?>"></div>
    <div><label>IGST ledger</label><input name="tally_igst_ledger" value="<?= e($cfg['tally_igst_ledger']) ?>"></div>
    <div><label>Debtors group <span class="muted">(where new party ledgers go)</span></label><input name="tally_debtors_group" value="<?= e($cfg['tally_debtors_group']) ?>"></div>
    <div><label>Sales voucher type</label><input name="tally_voucher_type" value="<?= e($cfg['tally_voucher_type']) ?>"></div>
    <div><label>Receipt voucher type</label><input name="tally_receipt_type" value="<?= e($cfg['tally_receipt_type']) ?>"></div>
    <div><label>Default GST % <span class="muted">(used when the quotation does not say)</span></label>
      <input name="tally_gst_pct" type="number" step="0.01" min="0" max="100" value="<?= e($cfg['tally_gst_pct']) ?>"></div>
    <div><label>The invoice amount recorded here is…</label>
      <select name="tally_amount_basis">
        <option value="EXCL"<?= $cfg['tally_amount_basis']==='EXCL'?' selected':'' ?>>before GST (tax is added)</option>
        <option value="INCL"<?= $cfg['tally_amount_basis']==='INCL'?' selected':'' ?>>after GST (tax is inside it)</option>
      </select></div>
    <div><label>SAC code <span class="muted">(for your reference)</span></label><input name="tally_sac" value="<?= e($cfg['tally_sac']) ?>"></div>
    <div style="align-self:end"><button class="btn">Save</button></div>
  </form>
</details>
<?php endif; ?>

<?php if ($batches): ?>
<div class="panel" style="margin-top:16px">
  <div class="ctitle"><h3>Batches already handed over</h3></div>
  <table class="dt">
    <thead><tr><th>Reference</th><th>What</th><th class="num">Vouchers</th><th class="num">Value</th><th>By</th><th>When</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): ?>
      <tr>
        <td><b><?= e($b['batch_ref']) ?></b></td>
        <td><?= $b['kind'] === 'RECEIPT' ? 'Receipts' : 'Sales' ?></td>
        <td class="num"><?= (int)$b['n'] ?></td>
        <td class="num"><?= fmoney($b['total']) ?></td>
        <td><?= e($b['exported_by'] ?: '—') ?></td>
        <td><?= e(fdate(substr((string)$b['exported_at'], 0, 10))) ?></td>
        <?php if ($canManage): ?>
        <td><form method="post" action="/tally-undo" onsubmit="return confirm('Undo this batch? Only do this if the import into Tally did NOT happen — otherwise you will import the same vouchers twice.')">
          <input type="hidden" name="batch_ref" value="<?= e($b['batch_ref']) ?>">
          <button class="btn small secondary">Undo</button></form></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
