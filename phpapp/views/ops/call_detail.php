<?= function_exists('chain_strip') ? chain_strip('CALL', (int)$call['id'], 'CALL', (int)$call['id']) : '' ?>
<?php // The join between selling and doing. It was empty on all 160 orders,
      // because the field existed and no screen ever asked about it. Saying so
      // here is how it stops being empty on the next one. ?>
<?php if (empty($call['quotation_id'])): ?>
  <div class="msg msg-warning" style="margin-bottom:12px">
    <b>No quotation is linked to this order.</b>
    Nobody can check the rate that was agreed, and the sale never joins up with the work or the invoice.
    <a href="/call-edit?id=<?= (int)$call['id'] ?>"><b>Set the quotation</b></a>
    — or type the contract number there if this was a direct order under a running contract.
  </div>
<?php endif; ?>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('call', $call['call_code'])) ?></h1>
    <p class="sub"><?= e($call['client_disp'] ?: $call['client_name'] ?: 'No client') ?> · <?= e(OPS_REGIONS[$call['region']] ?? '') ?></p></div>
<?php
  // "Open in Outlook" — mailto to the executing branch's coordinator (+ manager if ticked).
  $mailTo = $call['coordinator_email'] ?? '';
  $mailCc = (!empty($call['notify_manager']) && !empty($call['manager_email'])) ? $call['manager_email'] : '';
  $clientNm = $call['client_disp'] ?: $call['client_name'];
  $subj = rawurlencode(TH('call') . " {$call['call_code']} — {$clientNm}");
  $bodyLines = "Call: {$call['call_code']}\nClient: {$clientNm}\nVendor/Site: " . ($call['vendor_name'] ?: '-') .
    "\n" . T("sbu") . ": " . (OPS_SBUS[$call['sbu']] ?? $call['sbu']) . "\nActivity: " . ($call['activity_id'] ? lk_value_path($call['activity_id']) : '-') .
    "\nClient required date: " . ($call['inspection_required_date'] ?: '-') .
    "\nCredit to executing branch: " . fmoney($call['expected_credit']) .
    "\n\n(Attach the original client inspection-request email before sending.)";
  $mailBody = rawurlencode($bodyLines);
  $mailtoHref = 'mailto:' . rawurlencode($mailTo) . '?' . ($mailCc ? 'cc=' . rawurlencode($mailCc) . '&' : '') . 'subject=' . $subj . '&body=' . $mailBody;
?>
  <div class="row-actions">
    <?php if ($mailTo): ?><a class="btn secondary" href="<?= e($mailtoHref) ?>">✉️ Open in Outlook</a><?php endif; ?>
    <?php $canAllocTop = function_exists('call_can_allocate') ? call_can_allocate($call) : is_coordinator_level(); ?>
    <?php if (is_coordinator_level()): ?>
      <a class="btn secondary" href="/call-edit?id=<?= (int)$call['id'] ?>">Edit call</a>
      <?php if ($canAllocTop): ?><a class="btn" href="/job-new?call=<?= (int)$call['id'] ?>">+ Allocate Job</a><?php endif; ?>
      <?php if (can('mod.idems.edit') || is_master()): ?><a class="btn secondary" href="/document-new?call=<?= (int)$call['id'] ?>" title="Create an inspection report — all known details are filled in">📑 New report</a><?php endif; ?>
    <?php endif; ?>
    <?php if (is_master() || can('ops.call.delete')): ?>
      <form method="post" action="/call-delete?id=<?= (int)$call['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this call and its jobs? This cannot be undone.')">
        <button class="btn danger" type="submit">Delete call</button></form>
    <?php endif; ?>
  </div>
</div>

<?php // ---- Where this order stands, and the one next thing ------------------ ?>
<?php
  $jobsN = count($jobs);
  $schedN = 0; $closedN = 0;
  foreach ($jobs as $j) {
    if (!empty($j['scheduled_date'])) $schedN++;
    if (!empty($j['closed_flag'])) $closedN++;
  }
  $callDone = strtoupper((string)($call['status'] ?? '')) === 'CLOSED'
              || ($jobsN > 0 && $closedN === $jobsN);
  $canAlloc = function_exists('call_can_allocate') ? call_can_allocate($call) : is_coordinator_level();
  // A contracting-office coordinator sees the call but cannot allocate it — the
  // executing office does that. Say so, rather than silently hiding the button.
  $contractOnly = is_coordinator_level() && !$canAlloc;
  $execName = $contractOnly ? ops_val("SELECT name FROM offices WHERE id=?", [call_exec_office($call)]) : '';
?>
<?php if ($contractOnly): ?>
<div class="nowband" style="border-left:4px solid var(--info,#1c5a86)">
  <div class="step">Carried out by <?= e($execName ?: 'another ' . Tlp('office')) ?>.</div>
  <p class="next">Your <?= e(Tl('office')) ?> contracts this <?= e(Tl('call')) ?> and will invoice it, so you can
    see and track it here — but allocating the <?= e(Tl('engineer')) ?> is the executing <?= e(Tl('office')) ?>'s
    responsibility, and so is the <?= e(Tl('report')) ?>.</p>
</div>
<?php endif; ?>
<div class="nowband">
  <?php if ($callDone): ?>
    <div class="step">Done — the work is finished.</div>
    <p class="next">All <?= $jobsN > 1 ? 'jobs on this order have' : 'work on this order has' ?> been carried out and closed. Nothing more to do here.</p>
  <?php elseif ($jobsN === 0): ?>
    <div class="step">Nothing has been given out yet.</div>
    <p class="next">The order is in, but no engineer has the work. <b>Next:</b> allocate a job so someone can be sent.</p>
    <?php if ($canAlloc): ?><div class="cta"><a class="btn small" href="/job-new?call=<?= (int)$call['id'] ?>">Allocate a job →</a></div><?php endif; ?>
  <?php elseif ($schedN === 0): ?>
    <div class="step"><?= (int)$jobsN ?> <?= $jobsN > 1 ? 'jobs' : 'job' ?> given out — no date set yet.</div>
    <p class="next">The work has gone to a branch but no visit date is fixed. <b>Next:</b> open the job below and set the scheduled date so the engineer knows when to go.</p>
  <?php else: ?>
    <div class="step">Work is under way — <?= (int)$schedN ?> of <?= (int)$jobsN ?> <?= $jobsN > 1 ? 'jobs' : 'job' ?> scheduled.</div>
    <p class="next">Track each visit in the <a href="#jobs">jobs list below</a>. It closes here once every job is done.</p>
    <?php if ($canAlloc): ?><div class="cta"><a class="btn small secondary" href="/job-new?call=<?= (int)$call['id'] ?>">Allocate another job</a></div><?php endif; ?>
  <?php endif; ?>
</div>

<?php
  // Contract cover behind this call: dates and quantity. Shown before the
  // coordinator tries to allocate, not after they have filled a form.
  $cqid  = (int)($call['quotation_id'] ?? 0);
  $cgate = ($cqid && function_exists('contract_gate')) ? contract_gate($cqid) : null;
  $cinfo = $cgate['info'] ?? null;
  $fmtq  = fn($n) => rtrim(rtrim(number_format((float)$n, 2), '0'), '.');
?>
<?php if ($cinfo && in_array($cinfo['state'], ['EXPIRING','QTY_LOW','EXPIRED','EXHAUSTED'], true)):
        $bad = in_array($cinfo['state'], ['EXPIRED','EXHAUSTED'], true); ?>
<div class="panel" style="border:1px solid var(--<?= $bad ? 'bad' : 'warn' ?>);background:color-mix(in srgb,var(--<?= $bad ? 'bad' : 'warn' ?>) 7%,transparent)">
  <b style="color:var(--<?= $bad ? 'bad' : 'warn' ?>)">
    <?= $bad ? '⛔' : '⚠' ?> <?= e(CONTRACT_STATES[$cinfo['state']] ?? $cinfo['state']) ?></b>
  <div class="muted" style="margin-top:4px">
    <?php if ($cinfo['end_date'] !== ''): ?>
      Contract ends <?= e(fdate($cinfo['end_date'])) ?><?php
        if ($cinfo['days_left'] !== null) echo $cinfo['days_left'] < 0
          ? ' — <b>' . abs((int)$cinfo['days_left']) . ' day(s) ago</b>'
          : ' — ' . (int)$cinfo['days_left'] . ' day(s) left'; ?>.
    <?php endif; ?>
    <?php if ($cinfo['qty_total'] !== null): ?>
      Quantity <?= e($fmtq($cinfo['qty_used'])) ?> of <?= e($fmtq($cinfo['qty_total'])) ?> used<?php
        if ($cinfo['qty_left'] !== null) echo ', ' . e($fmtq(max(0, $cinfo['qty_left']))) . ' left'; ?>.
    <?php endif; ?>
    <?php if ($bad): ?>
      <br><b>No further <?= e(Tlp('job')) ?> can be allocated against this order</b> until an exception is granted.
    <?php else: ?>
      <br>Nothing is blocked — this is the time to get it renewed or extended.
    <?php endif; ?>
  </div>

  <?php if ($bad): ?>
    <?php if (!empty($cgate['override'])): ?>
      <div style="margin-top:8px"><span class="pill p-ok">Exception granted</span>
        <span class="muted">by <?= e($cgate['override']['decided_by']) ?> —
          <?= (int)$cgate['override']['uses_taken'] ?> of <?= (int)$cgate['override']['uses_allowed'] ?> allocation(s) used.</span></div>
    <?php elseif (!empty($cgate['pending'])): ?>
      <div style="margin-top:8px"><span class="pill p-warn"><?= e(override_status_text($cgate['pending'])) ?></span>
        <span class="muted">requested by <?= e($cgate['pending']['requested_by']) ?>.</span>
        <a class="btn small secondary" href="/contract-overrides" style="margin-left:6px">See the request</a></div>
    <?php elseif (is_coordinator_level() || is_master()): ?>
      <form method="post" action="/contract-override" style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="do" value="request">
        <input type="hidden" name="quotation_id" value="<?= (int)$cqid ?>">
        <input type="hidden" name="call_id" value="<?= (int)$call['id'] ?>">
        <input type="hidden" name="kind" value="<?= $cinfo['state'] === 'EXPIRED' ? 'EXPIRED' : 'EXHAUSTED' ?>">
        <input type="hidden" name="back" value="/call?id=<?= (int)$call['id'] ?>">
        <div class="ff" style="margin:0;flex:1;min-width:260px"><label>Why must this go ahead anyway?</label>
          <input class="form-control" name="reason" required placeholder="e.g. renewal signed, paperwork follows next week"></div>
        <div class="ff" style="margin:0"><label>Allocations needed</label>
          <input class="form-control" type="number" name="uses_allowed" value="1" min="1" max="99" style="width:120px"></div>
        <button class="btn small" type="submit">Request an exception</button>
      </form>
      <p class="muted" style="margin:6px 0 0"><?= e(override_flow_text($cinfo['state'] === 'EXPIRED' ? 'EXPIRED' : 'EXHAUSTED')) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php $cgap = function_exists('call_contract_gap') ? call_contract_gap($call) : null; ?>
<?php if ($cgap): ?>
  <?php // Shown on every visit until it is fixed. Scheduling is deliberately NOT
        // blocked — the work does not wait for the paperwork, it just has to stay
        // visible that the paperwork is outstanding. ?>
  <div class="panel" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
    <b style="color:var(--warn)">⚠ Contract number not available</b>
    <div class="muted" style="margin-top:4px"><?= e($cgap['text']) ?></div>
    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
      <?php if (is_coordinator_level()): ?>
        <a class="btn small secondary" href="/call-edit?id=<?= (int)$call['id'] ?>">Add it on this <?= e(Tl('call')) ?></a>
      <?php endif; ?>
      <?php if (!empty($cgap['quote_id'])): ?>
        <a class="btn small secondary" href="/quote?id=<?= (int)$cgap['quote_id'] ?>">Open <?= e($cgap['quote_no']) ?></a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="kv-grid">
    <div><span class="k">Client</span><?= e($call['client_disp'] ?: $call['client_name'] ?: '—') ?></div>
    <div><span class="k">Vendor / Site</span><?= e($call['vendor_name'] ?: '—') ?></div>
    <div><span class="k">Contracting <?= e(Tl('office')) ?></span><?= e($call['ibo_name'] ?: 'Ahmedabad (own)') ?></div>
    <div><span class="k">Executing branch</span><?= e($call['exec_name'] ?: 'Ahmedabad executes') ?><?= $call['coordinator_name'] ? '<br><small class="muted">Coord: '.e($call['coordinator_name']).'</small>' : '' ?></div>
    <div><span class="k"><?= e(T("sbu")) ?></span><?= e(lk_options_or('sbu', OPS_SBUS)[$call['sbu']] ?? '—') ?></div>
    <div><span class="k">Charged to the <?= e(Tl('client')) ?></span><?php $cl = chargeable_head_labels($call);
      echo $cl ? e(implode(', ', $cl)) . ' <span class="muted">— bills required</span>' : '<span class="muted">nothing beyond the fee</span>'; ?></div>
    <div><span class="k">Activity</span><?= e($call['activity_id'] ? lk_value_path($call['activity_id']) : '—') ?></div>
    <div><span class="k">Type of inspection</span><?= e(($call['inspection_type']??'')==='OTHER' ? ($call['inspection_type_other'] ?: 'Other') : (INSPECTION_TYPES[$call['inspection_type']??''] ?? '—')) ?></div>
    <?php if (($call['site_address_id']??null)): $sa = ops_one("SELECT * FROM partner_addresses WHERE id=?", [$call['site_address_id']]); ?>
      <div><span class="k"><?= e(TH('job')) ?> site</span><?= e($sa ? trim(($sa['label']?:'Site').' '.$sa['town_village'].' '.$sa['city'].' '.$sa['state']) : '—') ?></div><?php endif; ?>
    <?php if (($call['po_id']??null)): $po = ops_one("SELECT po_number FROM partner_purchase_orders WHERE id=?", [$call['po_id']]); $liw = ($call['po_line_item_id']??null) ? ops_one("SELECT description FROM po_line_items WHERE id=?", [$call['po_line_item_id']]) : null; ?>
      <div><span class="k">Against PO</span><?= e($po['po_number'] ?? '—') ?><?= $liw ? ' · '.e($liw['description']) : '' ?></div><?php endif; ?>
    <div><span class="k">Product</span><?= e((lk_options_or('product', PRODUCT_CATS)[$call['product_category']] ?? '') ?: ($call['product_other'] ?: '—')) ?></div>
    <div><span class="k">Engagement</span><?= e($call['deputation_type'] ?: '—') ?></div>
    <?php if (!empty($sameOffice)): ?>
      <div><span class="k">Billable (ex-GST)</span><?= fmoney($call['billable_value']) ?><?= ($call['billable_basis']??'') ? ' <small class="muted">('.e(CREDIT_TYPES[$call['billable_basis']] ?? '').')</small>' : '' ?></div>
    <?php else: ?>
      <div><span class="k">Credit to executing</span><?= fmoney($call['expected_credit']) ?><?= $call['credit_type'] ? ' <small class="muted">('.e(CREDIT_TYPES[$call['credit_type']] ?? '').')</small>' : '' ?></div>
    <?php endif; ?>
    <div><span class="k">Status</span><?= e($call['status']) ?></div>
    <div class="kv-wide"><span class="k">Notes</span><?= e($call['notes'] ?: '—') ?></div>
    <?php foreach (custom_display('call', $call['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value']) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<details class="fold" id="credit">
  <summary>Money — credit, billing &amp; cost so far <span class="sub">who bills whom, and what it has cost</span></summary>
  <div class="fold-body">
  <div class="kv-grid">
    <div><span class="k">Managing / contracting office</span><?= e($call['ibo_name'] ?: '—') ?></div>
    <div><span class="k">Executing office</span><?= e($call['exec_name'] ?: ($call['ibo_name'] ?: 'Same office')) ?></div>
    <div><span class="k">Cost incurred so far</span><strong><?= fmoney($costIncurred ?? 0) ?></strong> <small class="muted">(vouchers + expenses)</small></div>
  </div>
  <?php if (!empty($sameOffice)): ?>
    <p class="sub" style="margin-top:10px"><span class="pill p-mut">Same office</span> No inter-office credit — the client-billable value (ex-GST) is <strong><?= fmoney($call['billable_value']) ?></strong><?= ($call['billable_basis']??'') ? ' ('.e(CREDIT_TYPES[$call['billable_basis']] ?? '').')' : '' ?>.</p>
  <?php else: ?>
    <div class="kv-grid" style="margin-top:10px">
      <div><span class="k">Credit proposed (to executing)</span><strong><?= fmoney($call['expected_credit']) ?></strong><?= $call['credit_type'] ? ' <small class="muted">('.e(CREDIT_TYPES[$call['credit_type']] ?? '').')</small>' : '' ?></div>
      <div><span class="k">Credit required by executing</span><?= ($call['credit_required']??0)>0 ? '<strong>'.fmoney($call['credit_required']).'</strong>' : '<span class="muted">not set</span>' ?><?= ($call['credit_status']??'') ? ' <span class="pill '.(($call['credit_status']==='AGREED')?'p-ok':'p-warn').'">'.e(ucfirst(strtolower($call['credit_status']))).'</span>' : '' ?></div>
    </div>
    <?php if (can('mod.calls.edit') || is_coordinator_level()): ?>
    <form method="post" action="/call-credit?id=<?= (int)$call['id'] ?>" class="inline-add" style="align-items:flex-end;margin-top:10px">
      <div class="ff"><label>Executing office — credit you require (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="credit_required" value="<?= e($call['credit_required'] ?: '') ?>"></div>
      <div class="ff"><label>Status</label><select class="form-control" name="credit_status">
        <option value="COUNTERED" <?= ($call['credit_status']??'')==='COUNTERED'?'selected':'' ?>>Counter — revert to contracting</option>
        <option value="AGREED" <?= ($call['credit_status']??'')==='AGREED'?'selected':'' ?>>Agreed</option>
      </select></div>
      <button class="btn small" type="submit">Save required credit</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</details>

<details class="fold">
  <summary>Timing &amp; lead time <span class="sub">when it came in, and how fast it moved</span></summary>
  <div class="fold-body">
  <div class="kv-grid">
    <div><span class="k">Call received</span><?= e($call['call_received_date'] ?: '—') ?></div>
    <div><span class="k">Client expected date</span><?= e($call['inspection_required_date'] ?: '—') ?></div>
    <div><span class="k">Forwarded to branch</span><?= e($call['forwarded_at'] ? substr($call['forwarded_at'],0,16) : '—') ?></div>
    <div><span class="k">Inspector allocated</span><?= e($lead['alloc_date'] ?: '—') ?></div>
    <div><span class="k">Lead time (<?= e(Tl('client')) ?> → us)</span><?= $lead['client_to_sgs']===null?'—':(int)$lead['client_to_sgs'].' day(s)' ?></div>
    <div><span class="k">Lead time (to executing)</span><?= $lead['to_executing']===null?'—':(int)$lead['to_executing'].' day(s)' ?></div>
    <div><span class="k">Scheduling delay</span><?php if ($lead['sched_delay']===null): ?>—<?php else: ?><span class="badge <?= $lead['sched_delay']<=1?'GREEN':($lead['sched_delay']<=3?'AMBER':'RED') ?>"><?= (int)$lead['sched_delay'] ?> day(s)</span><?php endif; ?></div>
  </div>
  </div>
</details>

<?php
  // Assignment confirmation banner (executing branch has allocated an inspector)
  $engKind = ['ASSET'=>'own employee','FREELANCER'=>'freelancer','SUBCON'=>'sub-contractor'];
  foreach ($jobs as $aj) { if ($aj['inspector_name'] && $aj['scheduled_date']): ?>
    <div class="msg msg-success">✅ Call assigned to <strong><?= e($aj['inspector_name']) ?></strong> for <strong><?= e($aj['scheduled_date']) ?></strong> — engineer is <?= e($engKind[$aj['staff_kind'] ?? 'ASSET'] ?? 'own employee') ?><?= $aj['subcon_agency'] ? ' (' . e($aj['subcon_agency']) . ')' : '' ?>. Job <?= e($aj['job_code']) ?>, stage: <?= e(lk_options_or('job_stage', JOB_STAGES)[$aj['stage'] ?? 'ALLOCATED'] ?? '') ?>.</div>
  <?php endif; } ?>
<h3 class="tab-sub" id="jobs">Jobs allocated from this call</h3>
<table class="grid">
  <tr><th>Job</th><th>Inspector</th><th>Engineer</th><th>Scheduled</th><th>Stage</th><th>Expected credit</th><th>Closed</th><th></th></tr>
  <?php foreach ($jobs as $j): ?>
  <tr>
    <td><a href="/job?id=<?= (int)$j['id'] ?>"><?= e($j['job_code']) ?></a></td>
    <td><?= e($j['inspector_name'] ?: ($j['subcon_agency'] ?: '—')) ?></td>
    <td><?= e($engKind[$j['staff_kind'] ?? 'ASSET'] ?? '—') ?></td>
    <td><?= e($j['scheduled_date'] ?: '—') ?></td>
    <td><span class="badge <?= ($j['stage']??'')==='CLOSED'?'GREEN':'AMBER' ?>"><?= e(lk_options_or('job_stage', JOB_STAGES)[$j['stage'] ?? 'ALLOCATED'] ?? '') ?></span></td>
    <td><?= fmoney($j['expected_credit']) ?></td>
    <td><?= $j['closed_flag'] ? '<span class="badge GREEN">Closed</span>' : '<span class="badge AMBER">Open</span>' ?></td>
    <td class="row-actions"><a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$jobs): ?><tr><td colspan="8">No jobs yet. <?php if (is_coordinator_level()): ?><a href="/job-new?call=<?= (int)$call['id'] ?>">Allocate one</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
