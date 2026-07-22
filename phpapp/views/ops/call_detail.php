<div class="master-head">
  <div><h1>Call <?= e($call['call_code']) ?></h1>
    <p class="sub"><?= e($call['client_disp'] ?: $call['client_name'] ?: 'No client') ?> · <?= e(OPS_REGIONS[$call['region']] ?? '') ?></p></div>
  <div class="row-actions">
    <?php if (is_coordinator_level()): ?>
      <a class="btn secondary" href="/call-edit?id=<?= (int)$call['id'] ?>">Edit call</a>
      <a class="btn" href="/job-new?call=<?= (int)$call['id'] ?>">+ Allocate Job</a>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="kv-grid">
    <div><span class="k">Client</span><?= e($call['client_disp'] ?: $call['client_name'] ?: '—') ?></div>
    <div><span class="k">Vendor / Site</span><?= e($call['vendor_name'] ?: '—') ?></div>
    <div><span class="k">IBO / Office</span><?= e($call['ibo_name'] ?: 'Ahmedabad (own)') ?></div>
    <div><span class="k">SBU</span><?= e(OPS_SBUS[$call['sbu']] ?? '—') ?></div>
    <div><span class="k">Product</span><?= e(PRODUCT_CATS[$call['product_category']] ?? ($call['product_other'] ?: '—')) ?></div>
    <div><span class="k">Received</span><?= e($call['call_received_date'] ?: '—') ?></div>
    <div><span class="k">Required by</span><?= e($call['inspection_required_date'] ?: '—') ?></div>
    <div><span class="k">Status</span><?= e($call['status']) ?></div>
    <div class="kv-wide"><span class="k">Notes</span><?= e($call['notes'] ?: '—') ?></div>
    <?php foreach (custom_display('call', $call['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value']) ?></div>
    <?php endforeach; ?>
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
