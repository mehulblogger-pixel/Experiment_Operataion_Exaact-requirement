<?php
// Owner, 9 Nov 2026 — add a contract from the client's record when the client
// sends a purchase order directly. Opens as a pop-up from the Contract Numbers
// tab (or from "+ New contract" on the PO form); filled in from this client, with
// the next contract number offered. Saving closes it and the record reloads with
// the new contract listed. Posts to the same /partner-add door as before, so the
// same permission, duplicate and two-signature rules apply.
$id = (int)$p['id'];
$contracts = $contracts ?? []; $awaiting = $awaiting ?? []; $branches = $branches ?? [];
$branchId = $branchId ?? null; $nextNo = $nextNo ?? ''; $back = $back ?? '';
$preTitle = $preTitle ?? '';
$liveContracts = array_values(array_filter($contracts, function ($c) {
    $os = strtoupper((string)($c['open_status'] ?? ''));
    return $os === '' ? ((int)($c['is_active'] ?? 1) === 1) : in_array($os, ['OPEN', 'PENDING'], true);
}));
$nlc = count($liveContracts);
?>
<?php if (!is_embed()): ?><div class="master-head"><div><h1>New contract — <?= e(partner_name($p)) ?></h1></div>
  <a class="btn secondary" href="/partner?id=<?= $id ?>&amp;tab=<?= e($back !== '' ? $back : 'contracts') ?>">← Back to <?= e(partner_name($p)) ?></a></div><?php endif; ?>
<p class="muted" style="margin-top:0">Filled in from <b><?= e(partner_name($p)) ?></b>. It is saved as <b>awaiting endorsement</b> — a manager checks it, then the branch manager approves it, before it goes live.</p>
<?php if ($nlc): ?>
  <div class="msg msg-warning" style="margin:0 0 12px">
    This <?= e(Tl('client')) ?> already has <?= $nlc ?> live contract<?= $nlc > 1 ? 's' : '' ?>:
    <?php $i = 0; foreach ($liveContracts as $lc): $i++; $os = strtoupper((string)($lc['open_status'] ?? '')); ?>
      <b><?= e($lc['contract_number']) ?></b><?= $os !== '' ? ' (' . e(strtolower(CONTRACT_OPEN_STATES[$os] ?? $os)) . ')' : '' ?><?= $i < $nlc ? ',' : '' ?>
    <?php endforeach; ?>.
    Add another <b>only</b> for a genuinely separate agreement — a new project, a new year, or a group company.
  </div>
<?php else: ?>
  <div class="muted" style="margin:6px 0 10px">A contract for a <b>won <?= e(Tl('quote')) ?></b> is normally registered from the <?= e(Tl('quote')) ?>, which carries the value and scope across. If the order came with one of the <?= e(Tlp('quote')) ?> listed below, pick it here and the same happens.</div>
<?php endif; ?>
  <form method="post" action="/partner-add?id=<?= $id ?>&amp;kind=contract" class="inline-add">
    <?php if ($back !== ''): ?><input type="hidden" name="back_tab" value="<?= e($back) ?>"><?php endif; ?>
    <div class="ff"><label for="ct_no">Contract number <span class="muted">— leave blank for the next number</span></label>
      <input class="form-control" id="ct_no" name="contract_number" maxlength="80" placeholder="<?= e($nextNo) ?>">
      <small class="muted">Blank gives <b><?= e($nextNo) ?></b> (the branch below decides the first part). Type the <?= e(Tl('client')) ?>'s own number only if the contract must carry it.</small></div>
    <div class="ff"><label for="ct_branch">Branch <span class="muted">— from the <?= e(Tl('client')) ?></span></label>
      <select class="form-control" id="ct_branch" name="branch_id"><option value="">—</option>
        <?php foreach ($branches as $o): ?><option value="<?= (int)$o['id'] ?>"<?= (int)$o['id'] === (int)$branchId ? ' selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Against <?= e(Tl('quote')) ?> <span class="muted">— only those with no contract number</span></label>
      <select class="form-control searchable" id="ct_quote" name="quotation_id">
        <option value="">— none / recorded directly —</option>
        <?php foreach ($awaiting as $aq): ?>
          <option value="<?= (int)$aq['id'] ?>" data-value="<?= e((string)$aq['total_amount']) ?>" data-title="<?= e($aq['subject'] ?? '') ?>">
            <?= e($aq['quote_no']) ?><?= (int)$aq['rev'] ? ' R' . (int)$aq['rev'] : '' ?>
            · <?= e(cur_sym()) ?><?= number_format((float)$aq['total_amount'], 0) ?>
            · <?= e(lk_options_or('quote_status', QUOTE_STATUS)[$aq['status']] ?? $aq['status']) ?>
            <?= $aq['subject'] ? ' — ' . e(mb_strimwidth($aq['subject'], 0, 40, '…')) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!$awaiting): ?><small class="muted">Every <?= e(Tl('quote')) ?> for this <?= e(Tl('client')) ?> already has a contract number.</small><?php endif; ?></div>
    <div class="ff"><label>Title</label><input class="form-control" id="ct_title" name="title" value="<?= e($preTitle) ?>"></div>
    <div class="ff"><label><?= e(T("sbu")) ?></label><select class="form-control searchable" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" step="0.01" id="ct_value" name="value"></div>
    <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <?php // Without an end date a contract never expires, and the expiry warnings
          // and the scheduling gate have nothing to work from. It was missing here. ?>
    <div class="ff"><label>End date <span class="muted">— when cover stops</span></label><input class="form-control" type="date" name="end_date"></div>
    <?php // Field #3 — "Quantity sold" is a LIST: one line per item (man-days / man-months
          //   / other). Name a quotation above and its line items are carried across on save;
          //   otherwise type them here. All blank = untracked. qty_total becomes their sum. ?>
    <div class="ff ff-wide"><label>Quantity sold <span class="muted">— one line per item (man-days / man-months / other); leave all blank for untracked. Naming a <?= e(Tl('quote')) ?> above carries its line items across.</span></label>
      <table class="grid cl-table"><thead><tr><th>Description</th><th style="width:110px">Qty</th><th style="width:160px">Unit</th><th style="width:34px"></th></tr></thead>
        <tbody data-cl-rows="add">
          <tr class="cl-row">
            <td><input class="form-control" name="cl_desc[]" placeholder="e.g. Third-party inspection"></td>
            <td><input class="form-control" type="number" step="0.01" min="0" name="cl_qty[]"></td>
            <td><select class="form-control" name="cl_unit[]"><?php foreach (lk_options_or('charge_unit', CHARGE_UNITS) as $k=>$v): ?><option value="<?= e($k) ?>"<?= $k==='MANDAY'?' selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
            <td><button type="button" class="btn small secondary cl-del" title="Remove line">✕</button></td>
          </tr>
        </tbody></table>
      <button type="button" class="btn small secondary cl-add" data-cl-rows="add">+ Add line</button>
    </div>
    <button class="btn" type="submit">Add contract</button>
  </form>
  <script>
  (function () {
    // Field #3 — clone/remove quantity line rows (works for the add form and any edit form).
    document.addEventListener('click', function (e) {
      var add = e.target.closest && e.target.closest('.cl-add');
      if (add) {
        var key = add.getAttribute('data-cl-rows');
        var body = document.querySelector('tbody[data-cl-rows="' + key + '"]');
        if (!body) return;
        var last = body.querySelector('.cl-row:last-child'); if (!last) return;
        var row = last.cloneNode(true);
        Array.prototype.forEach.call(row.querySelectorAll('input'), function (i) { i.value = ''; });
        body.appendChild(row);
        return;
      }
      var del = e.target.closest && e.target.closest('.cl-del');
      if (del) {
        var tb = del.closest('tbody');
        if (tb && tb.querySelectorAll('.cl-row').length > 1) del.closest('.cl-row').remove();
        else { var r = del.closest('.cl-row'); if (r) Array.prototype.forEach.call(r.querySelectorAll('input'), function (i) { i.value = ''; }); }
      }
    });
  })();
  </script>
  <script>
  (function () {
    // Naming the quotation offers its value and subject, so the two records do
    // not drift apart over a typo.
    var q = document.getElementById('ct_quote');
    if (!q) return;
    q.addEventListener('change', function () {
      var o = q.options[q.selectedIndex]; if (!o || !o.value) return;
      var v = document.getElementById('ct_value'), t = document.getElementById('ct_title');
      if (v && !v.value && o.dataset.value) v.value = o.dataset.value;
      if (t && !t.value && o.dataset.title) t.value = o.dataset.title;
    });
  })();
  </script>

