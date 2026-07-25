<div class="master-head">
  <div><h1><?= e(T_DETAIL('job', $job['job_code'])) ?></h1>
    <p class="sub"><?= e($job['client_disp'] ?: $job['client_name'] ?: '—') ?> · <?= e($job['inspector_name'] ?: 'Unassigned') ?></p></div>
  <div class="row-actions">
    <?php if (!$job['closed_flag']): ?><a class="btn" href="/job-close?id=<?= (int)$job['id'] ?>">Close job</a><?php endif; ?>
    <?php if (can('mod.idems.edit') || is_master()): ?><a class="btn secondary" href="/document-new?job=<?= (int)$job['id'] ?><?= $job['call_id'] ? '&call='.(int)$job['call_id'] : '' ?>" title="Create an inspection report — all known details are filled in">📑 New report</a><?php endif; ?>
    <?php if (is_coordinator_level() && !$job['closed_flag']): ?><a class="btn secondary" href="/job-edit?id=<?= (int)$job['id'] ?>">Edit</a><?php endif; ?>
    <?php if ($job['call_id']): ?><a class="btn secondary" href="/call?id=<?= (int)$job['call_id'] ?>">View call</a><?php endif; ?>
  </div>
</div>

<?php $holds = function_exists('job_hold_reasons') ? job_hold_reasons($job) : []; if ($holds): ?>
<div class="panel" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 8%,transparent)">
  <b style="color:var(--bad)">🚫 HOLD — do not issue the report / deliverable to the client:</b> <?= e(implode('; ', $holds)) ?>.
  <?php if (!empty($job['adv_required']) && empty($job['adv_received']) && (is_coordinator_level() || can('data.credit') || can('finance.reconcile'))): ?>
    <form method="post" action="/job-advance?id=<?= (int)$job['id'] ?>" style="margin-top:8px"><input type="hidden" name="adv_received" value="1"><button class="btn small" type="submit">Mark advance received</button></form>
  <?php endif; ?>
</div>
<?php elseif (!empty($job['quotation_id']) && (!empty($job['adv_required']) || !empty($job['report_hold']))): ?>
<div class="panel" style="border:1px solid var(--ok)"><b style="color:var(--ok)">✓ Payment conditions cleared</b> — advance/payment received; the deliverable may be issued.</div>
<?php endif; ?>

<?php if (($job['report_approval'] ?? '') !== ''): ?>
  <?php $ra = $job['report_approval']; $canAppr = function_exists('can_approve_report') && can_approve_report($job); ?>
  <div class="panel" style="border:1px solid <?= $ra==='APPROVED'?'var(--ok)':($ra==='REJECTED'?'var(--bad)':'var(--warn,#c90)') ?>">
    <?php if ($ra==='PENDING'): ?>
      <b>🕓 Report awaiting approval</b> from <?= e($job['inspector_name'] ?: 'the inspector') ?>'s reporting manager.
      <?php if ($canAppr): ?>
        <form method="post" action="/report-approve?id=<?= (int)$job['id'] ?>" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <input class="form-control" name="note" placeholder="Optional remark" style="max-width:280px">
          <button class="btn small" type="submit" name="decision" value="approve">Approve report</button>
          <button class="btn small secondary" type="submit" name="decision" value="reject">Send back</button>
        </form>
      <?php endif; ?>
    <?php elseif ($ra==='APPROVED'): ?>
      <b style="color:var(--ok)">✓ Report approved</b> by <?= e($job['report_approved_by'] ?: '—') ?><?= $job['report_approved_at']?' on '.e(date('d M Y', strtotime($job['report_approved_at']))):'' ?>.<?= $job['report_approval_note']?' — '.e($job['report_approval_note']):'' ?>
    <?php else: ?>
      <b style="color:var(--bad)">↩ Report sent back</b> by <?= e($job['report_approved_by'] ?: '—') ?><?= $job['report_approval_note']?' — '.e($job['report_approval_note']):'' ?>.
      <?php if ($canAppr): ?>
        <form method="post" action="/report-approve?id=<?= (int)$job['id'] ?>" style="margin-top:8px"><input type="hidden" name="decision" value="approve"><button class="btn small" type="submit">Approve now</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="kv-grid">
    <?php if (!empty($job['quotation_id'])): $lq = ops_one("SELECT quote_no, rev, contract_number FROM quotations WHERE id=?", [$job['quotation_id']]); ?>
    <div><span class="k">Against <?= e(Tl('quote')) ?></span><?= $lq ? e($lq['quote_no'].((int)$lq['rev']>0?' R'.$lq['rev']:'')) : '—' ?><?= ($lq && $lq['contract_number'])?' · '.e($lq['contract_number']):'' ?></div>
    <?php endif; ?>
    <?php if (!empty($job['adv_required'])): ?><div><span class="k">Advance</span><?= rtrim(rtrim(number_format((float)$job['adv_pct'],2),'0'),'.') ?>% · <?= !empty($job['adv_received'])?'<span style="color:var(--ok)">received</span>':'<span style="color:var(--bad)">pending</span>' ?></div><?php endif; ?>
    <div><span class="k">Call</span><?= e($job['call_code'] ?: '—') ?></div>
    <div><span class="k">Job type</span><?= e(lk_options_or('job_type', JOB_TYPES)[$job['job_type'] ?? 'INSPECTION'] ?? '—') ?></div>
    <div><span class="k">Stage</span><?= e(lk_options_or('job_stage', JOB_STAGES)[$job['stage'] ?? 'ALLOCATED'] ?? '—') ?></div>
    <div><span class="k">Executing office</span><?= e($job['office_name'] ?: '—') ?></div>
    <div><span class="k">Inspector</span><?= e($job['inspector_name'] ?: '—') ?></div>
    <div><span class="k">Sub-con</span><?= e($job['subcon_agency'] ?: '—') ?></div>
    <div><span class="k">BOSS no.</span><?= e($job['boss_number'] ?: '—') ?></div>
    <div><span class="k">Scheduled</span><?= e($job['scheduled_date'] ?: '—') ?></div>
    <div><span class="k">Inspection</span><?= e(($job['inspection_start_date'] ?: '?') . ' → ' . ($job['inspection_end_date'] ?: '?')) ?></div>
    <div><span class="k">Type of inspection</span><?= e(INSPECTION_TYPES[$job['inspection_type']] ?? ($job['inspection_type'] ?: '—')) ?></div>
    <div><span class="k">Activity</span><?= e(($job['activity_id']??null) ? lk_value_path($job['activity_id']) : '—') ?></div>
    <div><span class="k">Reporting</span><?= e(REPORT_FREQ[$job['reporting_frequency']] ?? '—') ?><?= ($job['reporting_frequency']==='CUSTOM' && !empty($job['report_custom_days'])) ? ' (every '.(int)$job['report_custom_days'].' days)' : '' ?></div>
    <div><span class="k">Credit direction</span><?= e(CREDIT_DIRECTIONS[$job['credit_direction']] ?? '—') ?></div>
    <div><span class="k">Expected credit</span><?= fmoney($job['expected_credit']) ?></div>
    <div><span class="k">Report uploaded</span><?= e($job['report_upload_date'] ?: '—') ?></div>
    <div><span class="k">TAT</span><?= $job['tat_days']===null?'—':(int)$job['tat_days'].' day(s)' ?></div>
    <div class="kv-wide"><span class="k">Report folder</span><?php if ($job['folder_link']): ?><a href="<?= e($job['folder_link']) ?>" target="_blank" rel="noopener"><?= e($job['folder_link']) ?></a><?php else: ?>—<?php endif; ?></div>
    <div class="kv-wide"><span class="k">Deliverables required</span><?php
      $dl = $job['deliverables'] !== '' ? explode(',', $job['deliverables']) : [];
      $map = deliverable_options();
      echo $dl ? e(implode(', ', array_map(fn($c) => $map[$c] ?? $c, $dl))) : '—';
    ?></div>
    <?php foreach (custom_display('job', $job['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value']) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($quoteDocs)): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0"><?= e(T('client')) ?> documents <span class="muted">— filed with the order, for this <?= e(Tl('job')) ?></span></h3>
  <table class="dt">
    <thead><tr><th>Document</th><th>Kind</th><th>Note</th><th>Added</th></tr></thead>
    <tbody>
    <?php foreach ($quoteDocs as $d): ?>
      <tr>
        <td><a href="/quote-file?id=<?= (int)$d['id'] ?>">⬇ <?= e($d['file_name']) ?></a>
            <span class="muted" style="font-size:11px">(<?= e(number_format(((int)$d['b64len']) * 3 / 4 / 1024, 0)) ?> KB)</span></td>
        <td><?= e(QUOTE_FILE_KINDS[$d['kind']] ?? $d['kind']) ?></td>
        <td class="muted"><?= e($d['note'] ?: '—') ?></td>
        <td class="muted"><?= e(fdate(substr((string)$d['uploaded_at'],0,10))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="margin-top:6px">The purchase order, specification, drawing and QAP the <?= e(Tl('client')) ?> sent when the <?= e(Tl('quote')) ?> was accepted.</p>
</div>
<?php endif; ?>

<div class="panel-split">
  <div class="panel">
    <h3 class="tab-sub">Expenses</h3>
    <table class="grid">
      <tr><th>Date</th><th>SBU</th><th>Travel</th><th>Local</th><th>Food</th><th>Lodging</th><th>Misc</th><th>Total</th></tr>
      <?php $etot=0; $extraLbls=expense_extra_headings(); foreach ($expenses as $x): $ex=expense_extra_decode($x['extra']??''); $rt=$x['travel']+$x['local']+$x['food']+$x['lodging']+$x['misc']+array_sum($ex); $etot+=$rt; ?>
      <tr><td><?= e($x['exp_date']) ?></td><td><?= e(OPS_SBUS[$x['sbu']] ?? $x['sbu']) ?></td>
        <td><?= fmoney($x['travel']) ?></td><td><?= fmoney($x['local']) ?></td><td><?= fmoney($x['food']) ?></td>
        <td><?= fmoney($x['lodging']) ?></td><td><?= fmoney($x['misc']) ?></td><td><strong><?= fmoney($rt) ?></strong></td></tr>
      <?php if ($ex): ?><tr><td colspan="8" class="muted" style="font-size:12px">+ <?= e(implode(', ', array_map(fn($c,$a)=>($extraLbls[$c]??$c).': '.fmoney($a), array_keys($ex), array_values($ex)))) ?></td></tr><?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$expenses): ?><tr><td colspan="8">No expenses recorded (entered at closure).</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="panel">
    <h3 class="tab-sub">Profitability<?= can_see_salary() ? '' : ' (summary)' ?></h3>
    <div class="kv-grid">
      <div><span class="k">Man-days</span><?= e($profit['mandays']) ?></div>
      <?php if (can_see_salary()): ?>
        <div><span class="k">Daily cost</span><?= fmoney($profit['daily_cost']) ?></div>
        <div><span class="k">Labour cost</span><?= fmoney($profit['labour']) ?></div>
      <?php endif; ?>
      <div><span class="k">Expenses</span><?= fmoney($profit['expenses']) ?></div>
      <div><span class="k">Sub-con cost</span><?= fmoney($profit['subcon']) ?></div>
      <div><span class="k">Expected credit</span><?= fmoney($profit['credit']) ?></div>
      <?php if (can_see_salary()): ?>
        <div class="kv-wide"><span class="k">Net profit</span><strong style="color:<?= $profit['profit']>=0?'var(--good,#1f8a4c)':'#c0392b' ?>"><?= fmoney($profit['profit']) ?></strong></div>
      <?php else: ?>
        <div class="kv-wide"><span class="k">Net profit</span><em class="muted">Visible to Master Admin only</em></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (can('data.credit') || can('finance.reconcile')): ?>
<div class="panel" id="invoice">
  <h3 class="tab-sub">Invoice &amp; payment / credit</h3>
  <?php $isInter = ($job['credit_direction'] ?? '') === 'GIVEN'; ?>
  <form method="post" action="/job-invoice?id=<?= (int)$job['id'] ?>">
    <div class="form-grid">
      <div class="ff ff-check"><input type="checkbox" name="invoice_raised" <?= !empty($job['invoice_raised'])?'checked':'' ?>><label>Invoice raised</label></div>
      <div class="ff"><label>Invoice number</label><input class="form-control" name="invoice_number" value="<?= e($job['invoice_number'] ?? '') ?>"></div>
      <div class="ff"><label>Invoice date</label><input class="form-control" type="date" name="invoice_date" value="<?= e($job['invoice_date'] ?? '') ?>"></div>
      <div class="ff"><label>Due date</label><input class="form-control" type="date" name="invoice_due_date" value="<?= e($job['invoice_due_date'] ?? '') ?>"></div>
      <div class="ff"><label>Invoice amount (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="invoice_amount" value="<?= e($job['invoice_amount'] ?? '') ?>"></div>
      <div class="ff ff-check"><input type="checkbox" name="payment_received" <?= !empty($job['payment_received'])?'checked':'' ?>><label>Payment received</label></div>
      <div class="ff"><label>Payment date</label><input class="form-control" type="date" name="payment_date" value="<?= e($job['payment_date'] ?? '') ?>"></div>
      <div class="ff"><label>Payment amount (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="payment_amount" value="<?= e($job['payment_amount'] ?? '') ?>"></div>
      <div class="ff ff-check"><input type="checkbox" name="credit_received" <?= !empty($job['credit_received'])?'checked':'' ?>><label>Inter-office credit received<?= $isInter?' (this job is credit-given)':'' ?></label></div>
    </div>
    <p class="muted" style="margin:4px 2px">For a local client (same contracting &amp; executing office) use invoice + payment. When the executing branch is different, use the inter-office credit received flag.</p>
    <div style="margin-top:8px"><button class="btn small" type="submit">Save invoice / payment</button></div>
  </form>
</div>
<?php endif; ?>
