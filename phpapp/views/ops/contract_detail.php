<?php
  $os = (string)($c['open_status'] ?? 'OPEN');
  $osLbl = defined('CONTRACT_OPEN_STATES') ? (CONTRACT_OPEN_STATES[$os] ?? $os) : $os;
  $osTone = ['PENDING'=>'p-warn','OPEN'=>'p-ok','REJECTED'=>'p-bad','CLOSED'=>'p-mut'][$os] ?? 'p-mut';
  $clientName = $c['display_name'] ?: $c['legal_name'] ?: '—';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/partner?id=<?= (int)$c['partner_id'] ?>&tab=contracts"><?= e($clientName) ?></a> › Contract <?= e($c['contract_number'] ?: '#'.(int)$c['id']) ?></div>
<div class="master-head">
  <div><h1>Contract <?= e($c['contract_number'] ?: '#'.(int)$c['id']) ?> <span class="pill <?= $osTone ?>" style="font-size:12px;vertical-align:middle"><?= e($osLbl) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><a href="/partner?id=<?= (int)$c['partner_id'] ?>"><?= e($clientName) ?></a><?= $c['title'] ? ' · '.e($c['title']) : '' ?><?= $c['branch_name'] ? ' · '.e($c['branch_name']) : '' ?></p></div>
  <a class="btn secondary" href="/partner?id=<?= (int)$c['partner_id'] ?>&tab=contracts">← Contracts</a>
</div>

<?php // ---- Going-idle heads-up: show the same idle rule the cron enforces, BEFORE
      // it acts, so an auto-close is never the first anyone hears of it. ----------
  if (function_exists('contract_idle_status')) { $idle = contract_idle_status($c);
  if (!empty($idle['due']) && (int)$idle['days_left'] >= 0): ?>
  <div class="msg msg-warning" style="margin-top:14px">
    <b>Going idle.</b> No activity since <?= e(fdate($idle['last'])) ?>. Unless a <?= e(Tlp('call')) ?> or <?= e(Tlp('job')) ?>
    is raised against it, this contract auto-closes on <b><?= e(fdate($idle['close_on'])) ?></b>
    (<?= (int)$idle['days_left'] ?> day<?= (int)$idle['days_left'] === 1 ? '' : 's' ?> away).
    <?php if (!empty($idle['pending'])): ?><br>Still pending: <?= e($idle['pending']) ?>.<?php endif; ?>
    Raise work to keep it open, or let it close.
  </div>
  <?php endif; } ?>

<?php // ---- Live expiry / quantity verdict: the SAME state the scheduling gate reads,
      //      shown where the contract is actually looked at. Read-only. ------------
  $st = $state ?? null;
  if ($st && function_exists('contract_state_label') && ($st['state'] ?? 'NONE') !== 'NONE'):
    [$tone, $lbl, $desc] = contract_state_label($st['state']);
    $cls = ['bad'=>'msg-error','warn'=>'msg-warning','ok'=>'msg-ok','mut'=>'msg'][$tone] ?? 'msg'; ?>
  <div class="msg <?= $cls ?>" style="margin-top:14px">
    <b><?= e($lbl) ?>.</b> <?= e($desc) ?>
    <?php $bits = [];
      if ($st['days_left'] !== null) $bits[] = $st['days_left'] < 0
          ? 'ended ' . abs((int)$st['days_left']) . ' day' . (abs((int)$st['days_left']) === 1 ? '' : 's') . ' ago'
          : (int)$st['days_left'] . ' day' . ((int)$st['days_left'] === 1 ? '' : 's') . ' left';
      if ($st['qty_total'] !== null) $bits[] = 'quantity ' . rtrim(rtrim(number_format((float)$st['qty_used'], 2, '.', ''), '0'), '.')
          . ' of ' . rtrim(rtrim(number_format((float)$st['qty_total'], 2, '.', ''), '0'), '.') . ' used';
      if ($bits): ?><br><span class="muted"><?= e(implode(' · ', $bits)) ?></span><?php endif; ?>
    <?php if (contract_state_blocks($st['state'])): ?>
      <br><a href="/contract-overrides">Request an override →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php // ---- Commercial ---------------------------------------------------- ?>
<?php if ($canSeeMoney): ?>
<div class="kpi-row" style="margin:16px 0">
  <div class="kpi"><div class="k">Contract value</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['value'],0) ?></div></div>
  <div class="kpi"><div class="k">Invoiced</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['invoiced'],0) ?></div></div>
  <div class="kpi"><div class="k">Received</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['received'],0) ?></div></div>
  <div class="kpi"><div class="k">Outstanding</div><div class="v"><?= e(cur_sym()) ?><?= number_format($money['outstanding'],0) ?></div></div>
  <?php // Module 18 — value minus invoiced: is the contract under- or over-billed?
    if ((float)$money['value'] > 0): $rem = (float)($money['remaining'] ?? 0); ?>
  <div class="kpi"><div class="k"><?= $rem < 0 ? 'Over-billed' : 'Left to invoice' ?></div>
    <div class="v <?= $rem < 0 ? 'down' : '' ?>"><?= e(cur_sym()) ?><?= number_format(abs($rem),0) ?></div>
    <div class="d"><?= $rem < 0 ? 'billed beyond value' : 'of contract value' ?></div></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel"><div class="kv-grid">
  <div><span class="k">Contract number</span><b><?= e($c['contract_number'] ?: '—') ?></b></div>
  <div><span class="k">Status</span><span class="pill <?= $osTone ?>"><?= e($osLbl) ?></span></div>
  <?php if ($c['start_date'] || $c['end_date']): ?><div><span class="k">Period</span><?= e(($c['start_date'] ?: '?').' → '.($c['end_date'] ?: '?')) ?></div><?php endif; ?>
  <?php if ($canSeeMoney && (float)($c['value'] ?? 0) > 0): ?><div><span class="k">Value</span><?= e(cur_sym()) ?><?= number_format((float)$c['value'],0) ?></div><?php endif; ?>
  <div><span class="k">Billing branch</span><?= e($c['branch_name'] ?: '—') ?></div>
  <?php if (!empty($c['requested_by'])): ?><div><span class="k">Requested</span><?= e($c['requested_by']) ?><?= $c['requested_at']?' · '.e(substr($c['requested_at'],0,10)):'' ?></div><?php endif; ?>
  <?php if (!empty($c['mgr_endorsed_at'])): ?><div><span class="k">Endorsed (manager)</span><?= e($c['mgr_endorsed_by']) ?> · <?= e(substr($c['mgr_endorsed_at'],0,10)) ?></div><?php endif; ?>
  <?php if (!empty($c['bm_approved_at'])): ?><div><span class="k">Approved (branch mgr)</span><?= e($c['bm_approved_by']) ?> · <?= e(substr($c['bm_approved_at'],0,10)) ?></div><?php endif; ?>
  <?php if (!empty($c['notes'])): ?><div class="kv-wide"><span class="k">Notes</span><?= e($c['notes']) ?></div><?php endif; ?>
</div></div>

<?php // ---- Field #3 — Quantity sold, as line items ----------------------- ?>
<?php $lines = $lines ?? []; ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Quantity sold</h3>
  <?php if ($lines): ?>
    <table class="kv"><tr><th>Item</th><th class="num">Qty</th><th>Unit</th></tr>
      <?php $tot = 0.0; foreach ($lines as $ln): $tot += (float)$ln['quantity']; ?>
        <tr><td><?= e($ln['description'] ?: '—') ?></td>
          <td class="num"><?= e(rtrim(rtrim(number_format((float)$ln['quantity'], 2, '.', ''), '0'), '.')) ?></td>
          <td><?= e(lk_options_or('charge_unit', CHARGE_UNITS)[$ln['unit']] ?? $ln['unit']) ?></td></tr>
      <?php endforeach; ?>
      <tr><th>Total</th><th class="num"><?= e(rtrim(rtrim(number_format($tot, 2, '.', ''), '0'), '.')) ?></th><th></th></tr>
    </table>
  <?php elseif (($c['qty_total'] ?? null) !== null && $c['qty_total'] !== ''): ?>
    <p class="muted" style="margin:0"><?= e(rtrim(rtrim(number_format((float)$c['qty_total'], 2, '.', ''), '0'), '.')) ?>
      <?= e(lk_options_or('charge_unit', CHARGE_UNITS)[$c['qty_unit'] ?? ''] ?? ($c['qty_unit'] ?: 'units')) ?> — recorded as a single total.</p>
  <?php else: ?>
    <p class="muted" style="margin:0">Not tracked by quantity.</p>
  <?php endif; ?>
</div>

<?php // ---- Field #3 — Edit / Delete this contract ------------------------ ?>
<?php if (!empty($canEditContract)): ?>
<details class="panel" style="margin-top:0">
  <summary class="btn small secondary" style="display:inline-block">✎ Edit contract</summary>
  <form method="post" action="/contract-edit" class="inline-add" style="margin-top:12px">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Contract number <span class="muted">— fixed, it identifies the contract</span></label>
      <input class="form-control" value="<?= e($c['contract_number'] ?: '—') ?>" disabled></div>
    <div class="ff"><label>Title</label><input class="form-control" name="title" value="<?= e($c['title'] ?? '') ?>"></div>
    <div class="ff"><label><?= e(T('sbu')) ?></label><select class="form-control searchable" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (string)($c['sbu'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" step="0.01" name="value" value="<?= e((string)($c['value'] ?? '')) ?>"></div>
    <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date" value="<?= e($c['start_date'] ?? '') ?>"></div>
    <div class="ff"><label>End date</label><input class="form-control" type="date" name="end_date" value="<?= e($c['end_date'] ?? '') ?>"></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($c['notes'] ?? '') ?>"></div>
    <div class="ff ff-wide"><label>Quantity sold <span class="muted">— one line per item; edit, add or remove</span></label>
      <table class="grid cl-table"><thead><tr><th>Description</th><th style="width:110px">Qty</th><th style="width:160px">Unit</th><th style="width:34px"></th></tr></thead>
        <tbody data-cl-rows="edit">
          <?php $editRows = $lines ?: [['description'=>'','quantity'=>'','unit'=>'MANDAY']]; foreach ($editRows as $ln): ?>
            <tr class="cl-row">
              <td><input class="form-control" name="cl_desc[]" value="<?= e($ln['description'] ?? '') ?>" placeholder="e.g. Third-party inspection"></td>
              <td><input class="form-control" type="number" step="0.01" min="0" name="cl_qty[]" value="<?= e((string)($ln['quantity'] ?? '')) ?: '' ?>"></td>
              <td><select class="form-control" name="cl_unit[]"><?php foreach (lk_options_or('charge_unit', CHARGE_UNITS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (string)($ln['unit'] ?? 'MANDAY')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
              <td><button type="button" class="btn small secondary cl-del" title="Remove line">✕</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <button type="button" class="btn small secondary cl-add" data-cl-rows="edit">+ Add line</button>
    </div>
    <button class="btn small" type="submit">Save changes</button>
  </form>
  <form method="post" action="/contract-delete" style="margin-top:12px" onsubmit="return confirm('Delete contract <?= e($c['contract_number'] ?: '') ?>? This cannot be undone. (Only possible while no calls/jobs or POs are under it.)')">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <button class="btn small danger" type="submit">Delete contract</button>
    <span class="muted" style="font-size:12px;margin-left:6px">Blocked once work or a PO is recorded under it — close it instead.</span>
  </form>
</details>
<script>
(function () {
  document.addEventListener('click', function (e) {
    var add = e.target.closest && e.target.closest('.cl-add');
    if (add) {
      var body = document.querySelector('tbody[data-cl-rows="' + add.getAttribute('data-cl-rows') + '"]');
      if (!body) return; var last = body.querySelector('.cl-row:last-child'); if (!last) return;
      var row = last.cloneNode(true);
      Array.prototype.forEach.call(row.querySelectorAll('input'), function (i) { i.value = ''; });
      body.appendChild(row); return;
    }
    var del = e.target.closest && e.target.closest('.cl-del');
    if (del) { var tb = del.closest('tbody');
      if (tb && tb.querySelectorAll('.cl-row').length > 1) del.closest('.cl-row').remove();
      else { var r = del.closest('.cl-row'); if (r) Array.prototype.forEach.call(r.querySelectorAll('input'), function (i) { i.value = ''; }); } }
  });
})();
</script>
<?php endif; ?>

<?php // ---- Source quotation ---------------------------------------------- ?>
<?php if ($quote): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Quotation</h3>
  <table class="kv"><tr><th>Quote</th><th>Status</th><?php if ($canSeeMoney): ?><th class="num">Value</th><?php endif; ?><th></th></tr>
    <tr><td><b><?= e($quote['quote_no']) ?><?= (int)$quote['rev']?' r'.(int)$quote['rev']:'' ?></b><?= $quote['subject']?' — '.e($quote['subject']):'' ?></td>
      <td><span class="pill p-mut"><?= e($quote['status']) ?></span></td>
      <?php if ($canSeeMoney): ?><td class="num"><?= e(cur_sym()) ?><?= number_format((float)$quote['total_amount'],0) ?></td><?php endif; ?>
      <td class="num"><a class="btn small secondary" href="/quote?id=<?= (int)$quote['id'] ?>">Open →</a></td></tr>
  </table>
</div>
<?php endif; ?>

<?php // ---- Purchase orders ----------------------------------------------- ?>
<?php if ($pos): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0">Purchase orders <span class="muted">(<?= count($pos) ?>)</span></h3>
  <table class="dt"><thead><tr><th>PO number</th><th>Title</th><?php if ($canSeeMoney): ?><th class="num">Value</th><?php endif; ?><th>Period</th><th></th></tr></thead><tbody>
  <?php foreach ($pos as $p): ?>
    <tr><td><b><?= e($p['po_number'] ?: '—') ?></b></td><td><?= e($p['title'] ?: '—') ?></td>
      <?php if ($canSeeMoney): ?><td class="num"><?= (float)$p['value'] ? e(cur_sym()).number_format((float)$p['value'],0) : '—' ?></td><?php endif; ?>
      <td class="muted"><?= e(trim(($p['start_date'] ?: '').' → '.($p['end_date'] ?: ''), ' →')) ?: '—' ?></td>
      <td class="num"><a class="btn small secondary" href="/po?id=<?= (int)$p['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php // ---- Inspection calls ---------------------------------------------- ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0"><?= e(ucfirst(Tlp('call'))) ?> raised under this contract <span class="muted">(<?= count($calls) ?>)</span></h3>
  <?php if ($calls): ?>
  <table class="dt"><thead><tr><th><?= e(ucfirst(Tl('call'))) ?></th><th>Status</th><th>Type</th><th class="num"><?= e(ucfirst(Tlp('job'))) ?></th><?php if ($canSeeMoney): ?><th class="num">Invoiced</th><?php endif; ?><th></th></tr></thead><tbody>
  <?php foreach ($calls as $cl): ?>
    <tr><td><b><?= e($cl['call_code']) ?></b> <span class="muted"><?= e($cl['created_at'] ? substr($cl['created_at'],0,10) : '') ?></span></td>
      <td><span class="pill p-mut"><?= e($cl['status']) ?></span></td>
      <td><?= e($cl['inspection_type'] ? (INSPECTION_TYPES[$cl['inspection_type']] ?? $cl['inspection_type']) : '—') ?></td>
      <td class="num"><?= (int)$cl['job_count'] ?></td>
      <?php if ($canSeeMoney): ?><td class="num"><?= (float)$cl['invoiced'] ? e(cur_sym()).number_format((float)$cl['invoiced'],0) : '—' ?></td><?php endif; ?>
      <td class="num"><a class="btn small secondary" href="/call?id=<?= (int)$cl['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="muted" style="margin:0"><?= $os==='OPEN' ? 'No '.e(Tlp('call')).' raised yet — raise one from the contract.' : 'This contract is not open yet, so no '.e(Tlp('call')).' can be raised.' ?></p><?php endif; ?>
</div>

<?php // ---- Jobs ----------------------------------------------------------- ?>
<?php if ($jobs): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0"><?= e(ucfirst(Tlp('job'))) ?> <span class="muted">(<?= count($jobs) ?>)</span></h3>
  <table class="dt"><thead><tr><th><?= e(ucfirst(Tl('job'))) ?></th><th><?= e(ucfirst(Tl('call'))) ?></th><th><?= e(ucfirst(Tl('engineer'))) ?></th><th>Stage</th><th class="num"><?= e(ucfirst(Tlp('report'))) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($jobs as $j): ?>
    <tr><td><b><?= e($j['job_code']) ?></b><?= !empty($j['closed_flag']) ? ' <span class="pill p-ok">closed</span>' : '' ?></td>
      <td class="muted"><?= e($j['call_code'] ?: '—') ?></td>
      <td><?= e($j['inspector_name'] ?: 'Unassigned') ?></td>
      <td><?= e(lk_options_or('job_stage', JOB_STAGES)[$j['stage'] ?? 'ALLOCATED'] ?? ($j['stage'] ?: '—')) ?></td>
      <td class="num"><?= (int)$j['report_count'] ?></td>
      <td class="num"><a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php // ---- Reports -------------------------------------------------------- ?>
<?php if ($reports): ?>
<div class="panel"><h3 class="tab-sub" style="margin-top:0"><?= e(ucfirst(Tlp('report'))) ?> <span class="muted">(<?= count($reports) ?>)</span></h3>
  <table class="dt"><thead><tr><th>IRN</th><th>Format</th><th><?= e(ucfirst(Tl('job'))) ?></th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($reports as $r): ?>
    <tr><td><b><?= e($r['irn'] ?: '—') ?></b></td>
      <td><?= e(deliverable_options()[$r['type_code']] ?? $r['type_code']) ?></td>
      <td class="muted"><?= e($r['job_code'] ?: '—') ?></td>
      <td><span class="pill <?= !empty($r['finalized']) ? 'p-ok' : 'p-mut' ?>"><?= e(!empty($r['finalized']) ? 'issued' : strtolower((string)($r['status'] ?: 'draft'))) ?></span></td>
      <td class="num"><a class="btn small secondary" href="/document?id=<?= (int)$r['id'] ?>">Open →</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php // Phase 2 §25 — the whole engagement (quotes → calls → jobs → reports → invoices) under this contract_number. ?>
<?php if (function_exists('engagement_render')) engagement_render($c['contract_number'] ?? '', (int)($c['partner_id'] ?? 0)); ?>
