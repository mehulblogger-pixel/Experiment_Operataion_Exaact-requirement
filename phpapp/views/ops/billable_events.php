<?php
  // Slice P4 — the Billable Events board (the Commercial cockpit). Read-first;
  // manage actions gated by billable_can_manage() (finance.reconcile / master).
  $rows = $rows ?? []; $roll = $roll ?? []; $statuses = $statuses ?? []; $status = $status ?? ''; $canManage = $canManage ?? false;
  $m = fn($n) => function_exists('fmoney_short') ? fmoney_short($n) : number_format((float)$n);
  $tone = ['PENDING' => 'p-warn', 'APPROVED' => 'p-info', 'BILLED' => 'p-ok', 'CANCELLED' => 'p-mut', 'DISPUTED' => 'p-bad'];
?>
<div class="master-head">
  <div><h1>Billable events</h1>
    <p class="sub" style="margin:2px 0 0">Approved operational work on its way to an invoice — so nothing done is lost before it is billed.
      The books ledger stays the money truth; a billed event is reconciled to its invoice.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($canManage): ?>
      <form method="post" action="/billable-sync" style="margin:0"><button class="btn secondary" type="submit">⟳ Sync from closed work</button></form>
    <?php endif; ?>
    <a class="btn secondary" href="/money">← Money</a>
  </div>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">🧾</span><div class="k">Unbilled (pending + approved)</div>
    <div class="v"><?= e($m($roll['unbilled_amt'] ?? 0)) ?></div>
    <div class="d"><?= (int)($roll['pending'] ?? 0) ?> pending · <?= (int)($roll['approved'] ?? 0) ?> approved</div></div>
  <div class="kpi"><span class="kic">✅</span><div class="k">Approved to bill</div>
    <div class="v"><?= e($m($roll['approved_amt'] ?? 0)) ?></div></div>
  <div class="kpi"><span class="kic">📗</span><div class="k">Billed</div>
    <div class="v"><?= e($m($roll['billed_amt'] ?? 0)) ?></div><div class="d"><?= (int)($roll['billed'] ?? 0) ?> invoiced</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">Disputed</div>
    <div class="v"><?= e($m($roll['disputed_amt'] ?? 0)) ?></div><div class="d"><?= (int)($roll['disputed'] ?? 0) ?> open</div></div>
</div>

<?php // Stage 7 — billing-mismatch flag. A billed event whose invoiced amount
      // drifted from what the work earned (derived_amount) is surfaced, not erased,
      // so finance can reconcile it. The books ledger stays the money truth.
      $mismatch = function_exists('billable_mismatch') ? billable_mismatch() : [];
      if ($mismatch): ?>
  <div class="panel" style="margin:10px 0;background:#fbeceb;border:1px solid #e6b3ae;border-radius:11px;padding:12px 15px">
    <strong style="color:#9a2a2a">⚠ <?= count($mismatch) ?> billed event(s) disagree with their invoice.</strong>
    <span style="font-size:13px;color:#7a2420"> The invoiced figure differs from what the operation earned — reconcile before it becomes revenue leakage.</span>
    <div style="margin-top:6px;font-size:12.5px;color:#7a2420">
      <?php foreach (array_slice($mismatch, 0, 4) as $mm): ?>
        <div><?= e($mm['service_type'] ?: 'Event') ?> #<?= (int)$mm['source_id'] ?> — earned <?= e($m($mm['earned'])) ?>, billed <?= e($m($mm['billed'])) ?> (<?= $mm['variance'] > 0 ? '+' : '' ?><?= e($m($mm['variance'])) ?>)</div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php // Universal man-day / man-month billing — everything due to bill now, by client.
      $dueGroups = function_exists('billunit_due_groups') ? billunit_due_groups() : [];
      $canInvoice = function_exists('billunit_can_invoice') && billunit_can_invoice();
      if ($dueGroups): ?>
<div class="panel" id="due" style="margin-top:12px">
  <h3 class="tab-sub" style="margin-top:0">Due to bill now <span class="muted" style="font-weight:400">— man-days, man-months and charges whose billing terms have been met</span></h3>
  <?php foreach ($dueGroups as $g): ?>
    <form method="post" action="/billable-invoice" style="margin:0 0 14px">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
        <b><?= e($g['party_name']) ?></b>
        <span class="muted"><?= count($g['units']) ?> unit(s) · <?= e($m($g['total'])) ?></span>
      </div>
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><?php if ($canInvoice): ?><th style="width:28px"><input type="checkbox" checked onclick="this.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(function(c){c.checked=this.checked}.bind(this))" aria-label="Tick all"></th><?php endif; ?><th>Contract</th><th><?= e(TH('job')) ?></th><th>What</th><th>When</th><th class="num">Qty</th><th class="num">Amount</th><th>Due because</th></tr></thead>
        <tbody>
        <?php foreach ($g['units'] as $u): ?>
          <tr>
            <?php if ($canInvoice): ?><td><input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" checked></td><?php endif; ?>
            <td><?= e($u['contract_number'] ?: '—') ?></td>
            <td><?php if ((int)$u['job_id']): ?><a href="/job?id=<?= (int)$u['job_id'] ?>#billing"><?= e($u['job_code'] ?: '#' . (int)$u['job_id']) ?></a><?php endif; ?></td>
            <td><?= e(BILLUNIT_KINDS[$u['source_kind']] ?? $u['source_kind']) ?></td>
            <td><?= e(fdate($u['period_from'])) ?><?= ($u['period_to'] && $u['period_to'] !== $u['period_from']) ? ' – ' . e(fdate($u['period_to'])) : '' ?></td>
            <td class="num"><?= e(rtrim(rtrim(number_format((float)$u['qty'], 4), '0'), '.')) ?></td>
            <td class="num"><?= e($m($u['amount'])) ?></td>
            <td class="muted" style="font-size:12.5px"><?= e($u['due_reason']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($canInvoice): ?><button class="btn small" type="submit" style="margin-top:6px">🧾 Raise one invoice for the ticked units →</button><?php endif; ?>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:12px">
  <form method="get" action="/billable-events" style="margin:0 0 10px">
    <label>Status
      <select name="status" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach ($statuses as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <?php if (!$rows): ?>
    <p class="muted" style="margin:0">No billable events<?= $status !== '' ? ' with this status' : '' ?> yet.
      <?php if ($canManage): ?>Run <strong>Sync from closed work</strong> to derive candidates from closed, not-yet-invoiced jobs.<?php endif; ?></p>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Source</th><th>Client</th><th>Contract</th><th>Service</th><th style="text-align:right">Amount</th><th>Status</th><?php if ($canManage): ?><th>Action</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $st = (string)$r['status']; ?>
          <tr>
            <?php $srcLabel = (defined('BILLABLE_SOURCES') && isset(BILLABLE_SOURCES[$r['source_kind']])) ? BILLABLE_SOURCES[$r['source_kind']] : $r['source_kind']; ?>
            <td><?php if ($r['source_module'] === 'job'): ?><a href="/job?id=<?= (int)$r['source_id'] ?>"><?= e($srcLabel) ?> #<?= (int)$r['source_id'] ?></a><?php elseif ($r['source_module'] === 'unit'): ?><a href="/job?id=<?= (int)($r['job_id'] ?? 0) ?>#billing"><?= e($srcLabel) ?></a> <span class="muted" style="font-size:11.5px"><?= e(substr((string)($r['period_from'] ?? ''), 0, 10)) ?><?= !empty($r['period_to']) && $r['period_to'] !== $r['period_from'] ? ' – ' . e($r['period_to']) : '' ?></span><?php else: ?><?= e($srcLabel) ?> #<?= (int)$r['source_id'] ?><?php endif; ?></td>
            <td><?= e($r['party_name'] ?: '—') ?></td>
            <td><?= e($r['contract_number'] ?: '—') ?></td>
            <td><?= e($r['service_type'] ?: '—') ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= e($m($r['amount'])) ?>
              <?php if ($st === 'BILLED' && (int)$r['invoice_id']): ?><a href="/invoice?id=<?= (int)$r['invoice_id'] ?>" class="muted" style="font-size:11px">· inv</a>
              <?php elseif ($st === 'BILLED' && !empty($r['bill_ref'])): ?><span class="muted" style="font-size:11px">· <?= e($r['bill_ref']) ?></span><?php endif; ?>
              <?php // billing-mismatch flag on the row itself
                    if ($st === 'BILLED' && (float)($r['derived_amount'] ?? 0) > 0 && abs((float)$r['amount'] - (float)$r['derived_amount']) > 1): ?>
                <span style="font-size:11px;font-weight:600;color:#9a2a2a" title="Earned <?= e($m($r['derived_amount'])) ?>, billed <?= e($m($r['amount'])) ?>">⚠ differs</span>
              <?php endif; ?></td>
            <td><span class="pill <?= $tone[$st] ?? 'p-mut' ?>"><?= e($statuses[$st] ?? $st) ?></span>
              <?php if ($r['source_module'] === 'unit' && $st !== 'BILLED' && function_exists('billunit_on_invoice') && ($dr = billunit_on_invoice((int)$r['id']))): ?><a href="/invoice?id=<?= (int)$dr ?>" class="muted" style="font-size:11px">· on a draft invoice</a><?php endif; ?>
              <?php if (!empty($r['status_reason'])): ?><span class="muted" style="font-size:11px" title="<?= e($r['status_reason']) ?>">ⓘ</span><?php endif; ?></td>
            <?php if ($canManage): ?>
              <td>
                <?php if ($st === 'PENDING'): ?>
                  <form method="post" action="/billable-approve" style="display:inline;margin:0"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small" type="submit">Approve</button></form>
                <?php elseif ($st === 'APPROVED'): ?>
                  <?php if (!in_array($r['source_module'], ['job', 'unit'], true)): ?>
                    <form method="post" action="/billable-bill" style="display:inline-flex;gap:4px;margin:0 0 4px"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input name="invoice_ref" placeholder="invoice #" style="width:100px"><button class="btn small" type="submit">Mark billed</button></form>
                  <?php else: ?>
                    <span class="muted" style="font-size:11px;display:block;margin-bottom:4px" title="A job-sourced event is marked billed automatically when its books invoice is issued.">bills via invoice</span>
                  <?php endif; ?>
                  <form method="post" action="/billable-dispute" style="display:inline-flex;gap:4px;margin:0"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input name="reason" placeholder="reason" style="width:100px"><button class="btn small secondary" type="submit">Dispute</button></form>
                <?php elseif ($st === 'DISPUTED'): ?>
                  <form method="post" action="/billable-approve" style="display:inline;margin:0"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small" type="submit">Re-approve</button></form>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
