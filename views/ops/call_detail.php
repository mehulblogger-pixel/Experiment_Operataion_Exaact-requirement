<div class="master-head">
  <div><h1><?= e(T_DETAIL('call', $call['call_code'])) ?></h1>
    <p class="sub"><?= e($call['client_disp'] ?: $call['client_name'] ?: 'No client') ?> · <?= e(OPS_REGIONS[$call['region']] ?? '') ?></p></div>
<?php
  // "Open in Outlook" — mailto to the executing branch's coordinator (+ manager if ticked).
  $mailTo = $call['coordinator_email'] ?? '';
  $mailCc = (!empty($call['notify_manager']) && !empty($call['manager_email'])) ? $call['manager_email'] : '';
  $clientNm = $call['client_disp'] ?: $call['client_name'];
  $subj = rawurlencode("Inspection call {$call['call_code']} — {$clientNm}");
  $bodyLines = "Call: {$call['call_code']}\nClient: {$clientNm}\nVendor/Site: " . ($call['vendor_name'] ?: '-') .
    "\nSBU: " . (OPS_SBUS[$call['sbu']] ?? $call['sbu']) . "\nActivity: " . ($call['activity_id'] ? lk_value_path($call['activity_id']) : '-') .
    "\nClient required date: " . ($call['inspection_required_date'] ?: '-') .
    "\nCredit to executing branch: " . fmoney($call['expected_credit']) .
    "\n\n(Attach the original client inspection-request email before sending.)";
  $mailBody = rawurlencode($bodyLines);
  $mailtoHref = 'mailto:' . rawurlencode($mailTo) . '?' . ($mailCc ? 'cc=' . rawurlencode($mailCc) . '&' : '') . 'subject=' . $subj . '&body=' . $mailBody;
?>
  <div class="row-actions">
    <?php if ($mailTo): ?><a class="btn secondary" href="<?= e($mailtoHref) ?>">✉️ Open in Outlook</a><?php endif; ?>
    <?php if (is_coordinator_level()): ?>
      <a class="btn secondary" href="/call-edit?id=<?= (int)$call['id'] ?>">Edit call</a>
      <a class="btn" href="/job-new?call=<?= (int)$call['id'] ?>">+ Allocate Job</a>
      <?php if (can('mod.idems.edit') || is_master()): ?><a class="btn secondary" href="/document-new?call=<?= (int)$call['id'] ?>" title="Create an inspection report — all known details are filled in">📑 New report</a><?php endif; ?>
    <?php endif; ?>
    <?php if (is_master() || can('ops.call.delete')): ?>
      <form method="post" action="/call-delete?id=<?= (int)$call['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this call and its jobs? This cannot be undone.')">
        <button class="btn danger" type="submit">Delete call</button></form>
    <?php endif; ?>
  </div>
</div>

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
    <div><span class="k">Managing / IBO</span><?= e($call['ibo_name'] ?: 'Ahmedabad (own)') ?></div>
    <div><span class="k">Executing branch</span><?= e($call['exec_name'] ?: 'Ahmedabad executes') ?><?= $call['coordinator_name'] ? '<br><small class="muted">Coord: '.e($call['coordinator_name']).'</small>' : '' ?></div>
    <div><span class="k">SBU</span><?= e(lk_options_or('sbu', OPS_SBUS)[$call['sbu']] ?? '—') ?></div>
    <div><span class="k">Activity</span><?= e($call['activity_id'] ? lk_value_path($call['activity_id']) : '—') ?></div>
    <div><span class="k">Type of inspection</span><?= e(($call['inspection_type']??'')==='OTHER' ? ($call['inspection_type_other'] ?: 'Other') : (INSPECTION_TYPES[$call['inspection_type']??''] ?? '—')) ?></div>
    <?php if (($call['site_address_id']??null)): $sa = ops_one("SELECT * FROM partner_addresses WHERE id=?", [$call['site_address_id']]); ?>
      <div><span class="k">Deputation site</span><?= e($sa ? trim(($sa['label']?:'Site').' '.$sa['town_village'].' '.$sa['city'].' '.$sa['state']) : '—') ?></div><?php endif; ?>
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

<div class="panel" id="credit">
  <h3 class="tab-sub" style="margin-top:0">Credit / billing &amp; cost</h3>
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

<div class="panel">
  <h3 class="tab-sub">Timing &amp; lead time</h3>
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

<?php
  // Assignment confirmation banner (executing branch has allocated an inspector)
  $engKind = ['ASSET'=>'own employee','FREELANCER'=>'freelancer','SUBCON'=>'sub-contractor'];
  foreach ($jobs as $aj) { if ($aj['inspector_name'] && $aj['scheduled_date']): ?>
    <div class="msg msg-success">✅ Call assigned to <strong><?= e($aj['inspector_name']) ?></strong> for <strong><?= e($aj['scheduled_date']) ?></strong> — engineer is <?= e($engKind[$aj['staff_kind'] ?? 'ASSET'] ?? 'own employee') ?><?= $aj['subcon_agency'] ? ' (' . e($aj['subcon_agency']) . ')' : '' ?>. Job <?= e($aj['job_code']) ?>, stage: <?= e(lk_options_or('job_stage', JOB_STAGES)[$aj['stage'] ?? 'ALLOCATED'] ?? '') ?>.</div>
  <?php endif; } ?>
<h3 class="tab-sub">Jobs allocated from this call</h3>
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
