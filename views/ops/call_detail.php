<div class="master-head">
  <div><h1>Call <?= e($call['call_code']) ?></h1>
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
    <?php endif; ?>
    <?php if (is_master() || can('ops.call.delete')): ?>
      <form method="post" action="/call-delete?id=<?= (int)$call['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this call and its jobs? This cannot be undone.')">
        <button class="btn danger" type="submit">Delete call</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="kv-grid">
    <div><span class="k">Client</span><?= e($call['client_disp'] ?: $call['client_name'] ?: '—') ?></div>
    <div><span class="k">Vendor / Site</span><?= e($call['vendor_name'] ?: '—') ?></div>
    <div><span class="k">Managing / IBO</span><?= e($call['ibo_name'] ?: 'Ahmedabad (own)') ?></div>
    <div><span class="k">Executing branch</span><?= e($call['exec_name'] ?: 'Ahmedabad executes') ?><?= $call['coordinator_name'] ? '<br><small class="muted">Coord: '.e($call['coordinator_name']).'</small>' : '' ?></div>
    <div><span class="k">SBU</span><?= e(lk_options_or('sbu', OPS_SBUS)[$call['sbu']] ?? '—') ?></div>
    <div><span class="k">Activity</span><?= e($call['activity_id'] ? lk_value_path($call['activity_id']) : '—') ?></div>
    <div><span class="k">Product</span><?= e((lk_options_or('product', PRODUCT_CATS)[$call['product_category']] ?? '') ?: ($call['product_other'] ?: '—')) ?></div>
    <div><span class="k">Deputation</span><?= e($call['deputation_type'] ?: '—') ?></div>
    <div><span class="k">Credit to branch</span><?= fmoney($call['expected_credit']) ?><?= $call['credit_type'] ? ' <small class="muted">('.e(CREDIT_TYPES[$call['credit_type']] ?? '').')</small>' : '' ?></div>
    <div><span class="k">Status</span><?= e($call['status']) ?></div>
    <div class="kv-wide"><span class="k">Notes</span><?= e($call['notes'] ?: '—') ?></div>
    <?php foreach (custom_display('call', $call['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value']) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub">Timing &amp; lead time</h3>
  <div class="kv-grid">
    <div><span class="k">Call received</span><?= e($call['call_received_date'] ?: '—') ?></div>
    <div><span class="k">Client expected date</span><?= e($call['inspection_required_date'] ?: '—') ?></div>
    <div><span class="k">Forwarded to branch</span><?= e($call['forwarded_at'] ? substr($call['forwarded_at'],0,16) : '—') ?></div>
    <div><span class="k">Inspector allocated</span><?= e($lead['alloc_date'] ?: '—') ?></div>
    <div><span class="k">Lead time (client → SGS)</span><?= $lead['client_to_sgs']===null?'—':(int)$lead['client_to_sgs'].' day(s)' ?></div>
    <div><span class="k">Lead time (to executing)</span><?= $lead['to_executing']===null?'—':(int)$lead['to_executing'].' day(s)' ?></div>
    <div><span class="k">Scheduling delay</span><?php if ($lead['sched_delay']===null): ?>—<?php else: ?><span class="badge <?= $lead['sched_delay']<=1?'GREEN':($lead['sched_delay']<=3?'AMBER':'RED') ?>"><?= (int)$lead['sched_delay'] ?> day(s)</span><?php endif; ?></div>
  </div>
</div>

<h3 class="tab-sub">Jobs allocated from this call</h3>
<table class="grid">
  <tr><th>Job</th><th>Inspector</th><th>Scheduled</th><th>Expected credit</th><th>Closed</th><th></th></tr>
  <?php foreach ($jobs as $j): ?>
  <tr>
    <td><a href="/job?id=<?= (int)$j['id'] ?>"><?= e($j['job_code']) ?></a></td>
    <td><?= e($j['inspector_name'] ?: '—') ?></td>
    <td><?= e($j['scheduled_date'] ?: '—') ?></td>
    <td><?= fmoney($j['expected_credit']) ?></td>
    <td><?= $j['closed_flag'] ? '<span class="badge GREEN">Closed</span>' : '<span class="badge AMBER">Open</span>' ?></td>
    <td class="row-actions"><a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$jobs): ?><tr><td colspan="6">No jobs yet. <?php if (is_coordinator_level()): ?><a href="/job-new?call=<?= (int)$call['id'] ?>">Allocate one</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
