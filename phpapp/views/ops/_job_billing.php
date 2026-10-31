<?php
// Universal man-day / man-month billing — the job's Billing panel (Money tab).
// Data: $job. Shown only for a work order billed by the man-day or man-month.
if (!function_exists('billunit_job')) return;
if (function_exists('billable_money_live') && !billable_money_live()) return;   // no Money module, no billing panel
$bj = billunit_job((int)$job['id']);
if (!$bj || billunit_basis($bj) === '') return;
billunit_on_job_changed((int)$job['id']);              // counts anything newly earned before showing it
$bj = billunit_job((int)$job['id']);
$rc = billunit_reconciliation((int)$job['id']);
$tr = $rc['triggers'];
$basis = billunit_basis($bj);
$m = fn($n) => e(cur_sym()) . number_format((float)$n, 0);
$qty = fn($q) => rtrim(rtrim(number_format((float)$q, 4), '0'), '.');
$canInv = billunit_can_invoice(); $canFin = billunit_can_adjust(); $canTerms = billunit_can_set_job_terms(); $canEnd = billunit_can_end_early();
$trLabels = array_map(fn($t) => strtolower(strtok(BILLUNIT_TRIGGERS[$t], '(')), $tr['triggers']);
$today = date('Y-m-d');
$waitWhy = function ($u) use ($tr) {
    if (($u['due_on'] ?? '') !== '') return 'due ' . fdate($u['due_on']);
    $w = ['at closure'];
    if (in_array('PERIOD', $tr['triggers'], true)) $w[] = 'at the ' . strtolower(BILLUNIT_PERIODS[$tr['period']] ?? 'period') . ' cut-off';
    if (in_array('FIR', $tr['triggers'], true)) $w[] = 'when the Final Inspection Report is issued';
    if (in_array('RN', $tr['triggers'], true)) $w[] = 'when the Release Note is issued';
    return 'waiting — due ' . implode(' or ', $w);
};
?>
<div class="panel" id="billing" data-tab="Money">
  <h3 class="tab-sub" style="margin-top:0">Billing — <?= $basis === 'MANDAY' ? 'man-days' : 'man-months' ?></h3>
  <p class="muted" style="margin:0 0 10px;font-size:12.5px">
    Billed by the <?= $basis === 'MANDAY' ? 'man-day' : 'man-month' ?> at <b><?= $m($bj['billable_rate'] ?? 0) ?></b>.
    When: <b>at closure</b><?= $trLabels ? ', and ' . e(implode(', ', $trLabels)) : '' ?> — set by <?= e($tr['source']) ?>.
    <?php if ($rc['stop'] !== ''): ?><br><b style="color:var(--bad)">Earning ended <?= e(fdate($rc['stop'])) ?></b> — <?= e(BILLUNIT_STOP_KINDS[$bj['billing_stop_kind'] ?? ''] ?? '') ?>: <?= e($bj['billing_stop_reason'] ?? '') ?><?php endif; ?>
  </p>
  <div class="kpi-row" style="margin:0 0 12px">
    <div class="kpi"><div class="k"><?= $basis === 'MANDAY' ? 'Man-days earned' : 'Man-months earned' ?></div><div class="v"><?= e($qty($basis === 'MANDAY' ? $rc['mandays'] : $rc['months'])) ?></div><div class="d">worth <?= $m($rc['earned']) ?><?= $rc['adjust'] > 0 ? ' incl. ' . $m($rc['adjust']) . ' charges' : '' ?></div></div>
    <div class="kpi"><div class="k">Billed</div><div class="v"><?= $m($rc['billed']) ?></div></div>
    <div class="kpi"><div class="k">Due to bill now</div><div class="v" style="<?= $rc['due'] > 0 ? 'color:var(--warn)' : '' ?>"><?= $m($rc['due']) ?></div></div>
    <div class="kpi"><div class="k">Earned, not yet due</div><div class="v"><?= $m($rc['not_due']) ?></div><div class="d">by the billing terms</div></div>
    <?php if ($rc['disputed'] > 0): ?><div class="kpi"><div class="k">Disputed</div><div class="v" style="color:var(--bad)"><?= $m($rc['disputed']) ?></div></div><?php endif; ?>
    <div class="kpi"><div class="k">Still to bill</div><div class="v"><?= $m($rc['outstanding']) ?></div><?php if ($rc['order_value'] > 0): ?><div class="d">work-order value <?= $m($rc['order_value']) ?></div><?php endif; ?></div>
  </div>
  <?php if ($rc['due'] > 0 && $canInv && function_exists('books_invoice_create')): ?>
    <form method="post" action="/job-bill?id=<?= (int)$job['id'] ?>" style="margin:0 0 12px">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <button class="btn" type="submit">🧾 Raise invoice for what is due (<?= $m($rc['due']) ?>) →</button>
      <span class="muted" style="font-size:12.5px;margin-left:6px">One line per day / month; you review and issue it.</span>
    </form>
  <?php endif; ?>

  <?php if ($rc['units']): ?>
  <form method="post" action="/job-billing" style="margin:0">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="act" value="release">
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><?php if ($canFin): ?><th style="width:28px"></th><?php endif; ?><th>When</th><th>What</th><th class="num">Qty</th><th class="num">Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rc['units'] as $u): $st = (string)$u['status']; $isDue = ($u['due_on'] ?? '') !== '' && $u['due_on'] <= $today; ?>
        <tr<?= $st === 'CANCELLED' ? ' style="opacity:.55"' : '' ?>>
          <?php if ($canFin): ?><td><?php if (in_array($st, ['PENDING','APPROVED'], true) && !$isDue && !$u['on_draft']): ?><input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" aria-label="Release for billing"><?php endif; ?></td><?php endif; ?>
          <td><?= e(fdate($u['period_from'])) ?><?= ($u['period_to'] && $u['period_to'] !== $u['period_from']) ? ' – ' . e(fdate($u['period_to'])) : '' ?></td>
          <td><?= e(BILLUNIT_KINDS[$u['source_kind']] ?? $u['source_kind']) ?><?php if ($u['source_kind'] === 'ADJUSTMENT'): ?> <span class="muted" style="font-size:12px">— <?= e($u['evidence']) ?></span><?php endif; ?></td>
          <td class="num"><?= e($qty($u['qty'])) ?></td>
          <td class="num"><?= $m($u['amount']) ?></td>
          <td style="font-size:12.5px">
            <?php if ($st === 'BILLED'): ?><span class="pill p-ok">Billed</span> <a href="/invoice?id=<?= (int)$u['invoice_id'] ?>"><?= e($u['invoice_no'] ?: 'invoice') ?></a>
            <?php elseif ($st === 'CANCELLED'): ?><span class="pill p-mut">Not billable</span> <span class="muted"><?= e($u['status_reason']) ?></span>
            <?php elseif ($st === 'DISPUTED'): ?><span class="pill p-bad">Disputed</span> <span class="muted"><?= e($u['status_reason']) ?></span>
            <?php elseif ($u['on_draft']): ?><span class="pill p-info">On a draft</span> <a href="/invoice?id=<?= (int)$u['on_draft'] ?>">review &amp; issue</a>
            <?php elseif ($isDue): ?><span class="pill p-warn">Due</span> <span class="muted"><?= e($u['due_reason']) ?></span>
            <?php else: ?><span class="muted"><?= e($waitWhy($u)) ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($canFin): ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px">
        <input class="form-control" name="reason" placeholder="Why bill these now? (milestone, partial completion, agreed with client)" style="flex:1;min-width:220px">
        <button class="btn small secondary" type="submit">Release ticked for billing now</button>
      </div>
    <?php endif; ?>
  </form>
  <?php else: ?>
    <p class="muted" style="margin:0">Nothing earned yet. <?= $basis === 'MANDAY' ? 'Each visit day closed with its report counts as a man-day; a single-day ' . e(Tl('job')) . ' counts its man-days when it is closed.' : 'Each month of the deployment counts once it is over.' ?></p>
  <?php endif; ?>

  <details class="fold" style="margin-top:12px"><summary>Billing terms — when this <?= e(Tl('job')) ?> may be billed</summary>
    <div style="padding:10px 0 0"><?= billunit_terms_form_html('/job-billing', ['job_id' => (int)$job['id'], 'act' => 'terms'], (string)($bj['billing_triggers'] ?? ''), (string)(($bj['billing_period'] ?? '') ?: 'MONTHLY'), $canTerms,
          trim((string)($bj['billing_triggers'] ?? '')) === '' ? 'Nothing ticked here: this ' . Tl('job') . ' follows ' . $tr['source'] . '.' : 'Untick everything and save to follow the work order / contract again.') ?></div>
  </details>
  <?php if ($canEnd && empty($job['closed_flag'])): ?>
  <details class="fold"><summary>End early — terminated, completed early or demobilised</summary>
    <form method="post" action="/job-billing" style="padding:10px 0 0;margin:0">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="act" value="end">
      <div class="form-grid">
        <div class="ff"><label>Last day of work</label><input class="form-control" type="date" name="date" value="<?= e($rc['stop'] ?: $today) ?>" required></div>
        <div class="ff"><label>What happened</label><select class="form-control" name="kind"><?php foreach (BILLUNIT_STOP_KINDS as $k => $v): ?><option value="<?= e($k) ?>" <?= ($bj['billing_stop_kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff" style="grid-column:1/-1"><label>Why (the record)</label><input class="form-control" name="reason" required placeholder="e.g. client terminated — project put on hold (letter of 12 Oct)"></div>
      </div>
      <p class="muted" style="font-size:12.5px;margin:6px 0">Everything earned up to that day becomes due at once (a part month is pro-rated). Nothing already billed changes. This does not close the <?= e(Tl('job')) ?>.</p>
      <button class="btn small" type="submit" onclick="return confirm('Stop earning on this date? Everything earned up to it becomes due now.')">End early</button>
    </form>
  </details>
  <?php endif; ?>
  <?php if ($canFin): ?>
  <details class="fold"><summary>Add a charge — termination, notice period, minimum commitment, demobilisation</summary>
    <form method="post" action="/job-billing" style="padding:10px 0 0;margin:0">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="act" value="charge">
      <div class="form-grid">
        <div class="ff"><label>Amount (<?= e(cur_sym()) ?>, before tax)</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
        <div class="ff" style="grid-column:span 2"><label>What it is, and the contract clause</label><input class="form-control" name="reason" required placeholder="e.g. Notice-period charge per clause 14.2"></div>
      </div>
      <button class="btn small" type="submit" style="margin-top:6px">Add charge</button>
    </form>
  </details>
  <?php if ($basis === 'MANMONTH'): ?>
  <details class="fold"><summary>Suspension — <?= !empty($bj['billing_charge_suspension']) ? 'suspended days ARE charged' : 'suspended days are not charged' ?></summary>
    <form method="post" action="/job-billing" style="padding:10px 0 0;margin:0">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="act" value="suspension">
      <label class="chk" style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" name="charge" value="1" <?= !empty($bj['billing_charge_suspension']) ? 'checked' : '' ?>> The contract charges for days the deployment is suspended</label>
      <button class="btn small secondary" type="submit" style="margin-top:6px">Save</button>
    </form>
  </details>
  <?php endif; ?>
  <?php endif; ?>
</div>
