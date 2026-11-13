<?php
// Billing by milestones — one panel for the quotation and the contract
// (owner, 12 Nov 2026). Expects:
//   $msMode 'quote'|'contract', $msRows (with 'amount'), $msBase, $msRet,
//   $msCanEdit, $msAction, $msHidden (array), $msShowMoney
//   contract only: $msCanMark, $msOpen, $msRetSummary, $msAmend (bool: a change is an amendment),
//                  $msPendingAmend (bool)
$msMode = $msMode ?? 'quote';
$isC = $msMode === 'contract';
$pc = fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
$money = fn($v) => e(cur_sym()) . number_format((float)$v, 0);
$planned = array_values(array_filter($msRows, fn($r) => ($r['status'] ?? 'PLANNED') === 'PLANNED'));
$fixedPct = array_sum(array_map(fn($r) => (float)$r['pct'], array_filter($msRows, fn($r) => ($r['status'] ?? '') === 'ACHIEVED')));
$relTitle = ''; foreach ($msRows as $r) if (!empty($r['releases_retention'])) $relTitle = $r['title'];
$hidden = '';
foreach (($msHidden ?? []) as $k => $v) $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
?>
<style>
  .ms-tbl td,.ms-tbl th{vertical-align:top}
  .ms-ed td{padding:4px 6px}.ms-ed input.form-control{height:34px}
  .ms-sum{font-size:13px;margin:6px 0 0}.ms-sum.bad{color:var(--bad)}.ms-sum.ok{color:var(--ok)}
  .ms-reach{margin-top:6px}.ms-reach summary{cursor:pointer;display:inline-block}
  .ms-reach .ff{margin-top:8px}
  .ms-name{text-align:left}
  @media (max-width:640px){.ms-tbl .ms-hide-sm{display:none}}
</style>
<div class="panel" id="milestones"<?= $isC ? '' : ' data-tab="Overview"' ?>>
  <h3 class="tab-sub" style="margin-top:0">Billing by milestones</h3>
  <?php if (!$msRows): ?>
    <p class="muted" style="margin:0 0 8px">Not billed by milestones<?= $isC ? '' : ' yet' ?>. Use this when the customer pays in stages — for example 10% with the order, 40% when the design is approved, 40% on delivery and 10% after the warranty period. Each stage is billed only after someone confirms it is reached.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px">Each share is of the price before GST (<?= $msShowMoney ? $money($msBase) : 'the quoted price' ?>); GST is added on each bill.
      <?= $isC ? 'A milestone is billed only after the coordinator or a manager marks it reached; Finance then finds it under Billing → Due to bill.' : 'The schedule goes to the contract when this ' . e(Tl('quote')) . ' is won.' ?></p>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="dt ms-tbl">
      <thead><tr><th>#</th><th>Milestone</th><th class="num">Share</th><?php if ($msShowMoney): ?><th class="num">Amount</th><?php endif; ?><?php if ($isC): ?><th>Where it stands</th><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($msRows as $i => $r): $st = $isC ? ms_bill_state($r) : null; ?>
        <tr>
          <td class="muted"><?= $i + 1 ?></td>
          <td><div class="ms-name"><b><?= e($r['title']) ?></b>
            <?php if (!empty($r['releases_retention'])): ?><div class="muted" style="font-size:12px">Releases the retention</div><?php endif; ?>
            <?php if ($isC && ($r['status'] ?? '') === 'ACHIEVED'): ?>
              <div class="muted" style="font-size:12px">Reached <?= e(fdate($r['achieved_on'])) ?> · <?= e($r['achieved_by']) ?> — <?= e($r['achieved_note']) ?>
                <?php if (!empty($r['file_name'])): ?> · <a href="/milestone-file?id=<?= (int)$r['id'] ?>">📎 <?= e($r['file_name']) ?></a><?php endif; ?></div>
            <?php endif; ?></div></td>
          <td class="num"><?= e($pc($r['pct'])) ?>%</td>
          <?php if ($msShowMoney): ?><td class="num"><?= $money($r['amount'] ?? 0) ?></td><?php endif; ?>
          <?php if ($isC): ?>
            <td><span class="pill <?= e($st[1]) ?>"><?= e($st[0]) ?></span>
              <?php if ($st[2]): ?> <a href="/invoice?id=<?= (int)$st[2] ?>" style="font-size:12px">open</a><?php endif; ?></td>
            <td style="min-width:150px">
              <?php if (!empty($msCanMark) && !empty($msOpen) && ($r['status'] ?? '') === 'PLANNED'): ?>
                <details class="ms-reach"><summary class="btn small">✓ Mark reached</summary>
                  <form method="post" action="/contract-milestones" enctype="multipart/form-data">
                    <?= $hidden ?>
                    <input type="hidden" name="act" value="reach"><input type="hidden" name="milestone_id" value="<?= (int)$r['id'] ?>">
                    <div class="ff"><label>Reached on</label><input class="form-control" type="date" name="achieved_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></div>
                    <div class="ff"><label>What shows it is reached?</label><input class="form-control" name="note" minlength="5" maxlength="600" required placeholder="e.g. Design approved — client's letter of 10 Nov"></div>
                    <div class="ff"><label>Proof (optional)</label><input class="form-control" type="file" name="proof"></div>
                    <button class="btn small" style="margin-top:8px">Confirm — ready to bill</button>
                  </form></details>
              <?php elseif (!empty($msCanMark) && ($r['status'] ?? '') === 'ACHIEVED' && !$st[2]): ?>
                <details class="ms-reach"><summary class="muted" style="font-size:12px">Undo</summary>
                  <form method="post" action="/contract-milestones">
                    <?= $hidden ?>
                    <input type="hidden" name="act" value="undo"><input type="hidden" name="milestone_id" value="<?= (int)$r['id'] ?>">
                    <div class="ff"><label>Why?</label><input class="form-control" name="reason" minlength="5" required placeholder="e.g. Marked by mistake — approval still awaited"></div>
                    <button class="btn small secondary" style="margin-top:8px">Set back to planned</button>
                  </form></details>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php if ((float)$msRet > 0): ?>
      <p style="margin:10px 0 0;font-size:13.5px"><b>Retention <?= e($pc($msRet)) ?>%</b> — the customer holds back <?= e($pc($msRet)) ?>% of each milestone bill and pays it when “<?= e($relTitle) ?>” is reached. GST is charged on the full value of each bill.
      <?php if ($isC && !empty($msRetSummary) && $msShowMoney && $msRetSummary['held'] > 0): ?>
        <br><span class="muted">Held so far: <?= $money($msRetSummary['held']) ?> · still to collect: <?= $money($msRetSummary['owed']) ?> · <?= $msRetSummary['payable'] ? 'payable since ' . e(fdate($msRetSummary['payable_on'])) : 'not payable yet' ?></span>
      <?php endif; ?></p>
    <?php endif; ?>
    <?php if ($isC && ($nOther = ms_contract_other_billing($cMs ?? [])) > 0): ?>
      <p style="margin:10px 0 0;font-size:13px;color:var(--bad)"><b>Check before billing:</b> this contract also has <?= (int)$nOther ?> man-day, job or invoice item<?= $nOther === 1 ? '' : 's' ?> billed separately. Make sure the same work is not charged twice — through a milestone and through a <?= e(Tl('job')) ?>.</p>
    <?php endif; ?>
    <?php if ($isC && empty($msOpen)): ?><p class="muted" style="font-size:12.5px;margin:8px 0 0">Milestones can be marked reached once the contract is open (both signatures given).</p><?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($msCanEdit)): ?>
  <details class="fold" style="margin-top:12px"<?= !$msRows ? '' : '' ?>>
    <summary><?= $msRows ? 'Change the schedule' : 'Set up milestones' ?></summary>
    <?php if (!empty($msPendingAmend)): ?>
      <p class="muted" style="margin:8px 0 0">An amendment to this contract is waiting for its signatures (<a href="#amendments">see it</a>) — the schedule can be changed again once it is decided.</p>
    <?php else: ?>
    <form method="post" action="<?= e($msAction) ?>" class="ms-form" style="margin-top:8px">
      <?= $hidden ?>
      <?php if ($isC): ?><input type="hidden" name="act" value="save"><?php endif; ?>
      <?php if ($isC && $fixedPct > 0): ?><p class="muted" style="margin:0 0 6px;font-size:12.5px">Milestones already reached (<?= e($pc($fixedPct)) ?>%) stay as they are; below are the ones still to come.</p><?php endif; ?>
      <table class="ms-ed" style="width:100%">
        <thead><tr><th style="text-align:left">Milestone — what has to happen</th><th style="width:90px;text-align:left">Share %</th><th style="width:110px;text-align:left" class="ms-hide-sm">Releases retention</th><th style="width:30px"></th></tr></thead>
        <tbody data-ms-rows>
        <?php $edRows = $isC ? $planned : $msRows; if (!$edRows) $edRows = [['title' => '', 'pct' => '', 'releases_retention' => 0]];
        foreach ($edRows as $i => $r): ?>
          <tr class="ms-row">
            <td><input class="form-control" name="ms_title[<?= $i ?>]" value="<?= e($r['title']) ?>" maxlength="200" placeholder="e.g. Design approved by the client"></td>
            <td><input class="form-control" type="number" step="0.01" min="0" max="100" name="ms_pct[<?= $i ?>]" value="<?= $r['pct'] === '' ? '' : e($pc($r['pct'])) ?>" data-ms-pct></td>
            <td class="ms-hide-sm" style="text-align:center"><input type="radio" name="ms_release" value="<?= $i ?>" <?= !empty($r['releases_retention']) ? 'checked' : '' ?> aria-label="This milestone releases the retention"></td>
            <td><button type="button" class="btn small secondary" data-ms-del aria-label="Remove this milestone">✕</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
        <button type="button" class="btn small secondary" data-ms-add>+ Add a milestone</button>
        <?php if (!$msRows): ?><button type="button" class="btn small secondary" data-ms-example>Fill a common example</button><?php endif; ?>
      </div>
      <p class="ms-sum" data-ms-sum data-ms-fixed="<?= e((string)$fixedPct) ?>"></p>
      <div class="form-grid" style="margin-top:8px">
        <div class="ff"><label>Retention % (optional)</label><input class="form-control" type="number" step="0.01" min="0" max="99" name="ms_retention" value="<?= (float)$msRet > 0 ? e($pc($msRet)) : '' ?>" placeholder="0">
          <div class="ff-help">Held back by the customer from each bill and paid at the milestone ticked “Releases retention”.</div></div>
        <?php if ($isC): ?>
          <div class="ff"><label>Price before GST</label><input class="form-control" type="number" step="0.01" min="0" name="ms_base" value="<?= e(number_format((float)$msBase, 2, '.', '')) ?>">
            <div class="ff-help">The shares are of this figure.</div></div>
          <?php if (!empty($msAmend)): ?>
            <div class="ff ff-wide"><label>Reason for the change</label><input class="form-control" name="amend_reason" maxlength="400" placeholder="e.g. Client agreed to split delivery into two stages">
              <div class="ff-help">This contract is open, so a change is an amendment: <?= e(contract_rule_sentence(true)) ?>. Until then the current schedule stays.</div></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <button class="btn"><?= !empty($msAmend) ? 'Request the amendment' : 'Save the schedule' ?></button>
        <?php if ($msRows && (!$isC || $fixedPct <= 0)): ?><button class="btn secondary" name="ms_clear" value="1" onclick="return confirm('Remove the billing schedule?')">Remove milestones</button><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </details>
  <?php endif; ?>
</div>
<script>
(function () {
  var f = document.querySelector('#milestones .ms-form'); if (!f) return;
  var body = f.querySelector('[data-ms-rows]'), sum = f.querySelector('[data-ms-sum]');
  var fixed = parseFloat(sum.getAttribute('data-ms-fixed') || '0') || 0;
  function renum() {
    Array.prototype.forEach.call(body.querySelectorAll('.ms-row'), function (tr, i) {
      tr.querySelector('input[name^="ms_title"]').name = 'ms_title[' + i + ']';
      tr.querySelector('input[name^="ms_pct"]').name = 'ms_pct[' + i + ']';
      tr.querySelector('input[name="ms_release"]').value = i;
    });
  }
  function total() {
    var t = fixed;
    Array.prototype.forEach.call(body.querySelectorAll('[data-ms-pct]'), function (i) { t += parseFloat(i.value || '0') || 0; });
    t = Math.round(t * 1000) / 1000;
    var ok = Math.abs(t - 100) < 0.001, any = t > fixed;
    sum.textContent = any ? (ok ? 'Adds up to 100% ✓' : 'Adds up to ' + t + '% — it must add up to 100%.') : '';
    sum.className = 'ms-sum ' + (any ? (ok ? 'ok' : 'bad') : '');
  }
  function addRow(t, p) {
    var tr = body.querySelector('.ms-row').cloneNode(true);
    tr.querySelector('input[name^="ms_title"]').value = t || '';
    tr.querySelector('input[name^="ms_pct"]').value = p || '';
    tr.querySelector('input[name="ms_release"]').checked = false;
    body.appendChild(tr); renum(); total(); return tr;
  }
  f.addEventListener('input', total);
  f.addEventListener('click', function (e) {
    if (e.target.closest('[data-ms-add]')) { addRow(); }
    var d = e.target.closest('[data-ms-del]');
    if (d) { var rows = body.querySelectorAll('.ms-row');
      if (rows.length > 1) d.closest('.ms-row').remove();
      else Array.prototype.forEach.call(d.closest('.ms-row').querySelectorAll('input[type=text],input:not([type]),input[type=number]'), function (i) { i.value = ''; });
      renum(); total(); }
    if (e.target.closest('[data-ms-example]')) {
      var ex = [['Advance — with the order', 10], ['Design / drawings approved', 40], ['Delivery / completion', 40], ['After the warranty period', 10]];
      var rows = body.querySelectorAll('.ms-row'); for (var k = 1; k < rows.length; k++) rows[k].remove();
      var first = body.querySelector('.ms-row');
      first.querySelector('input[name^="ms_title"]').value = ex[0][0]; first.querySelector('input[name^="ms_pct"]').value = ex[0][1];
      for (var j = 1; j < ex.length; j++) addRow(ex[j][0], ex[j][1]);
      renum(); total();
    }
  });
  total();
})();
</script>
