<h2 class="ptitle"><?= e($c['call_code']) ?></h2>
<p class="plead"><?= e(portal_call_state($c)) ?>.</p>

<div class="pcard">
  <div class="pscroll"><table class="ptable" style="margin:0">
    <tr><th style="width:38%">Type of inspection</th>
        <td><?= e(($c['inspection_type'] ?? '') === 'OTHER'
                  ? ($c['inspection_type_other'] ?: 'Other')
                  : (lk_options_or('inspection_type', INSPECTION_TYPES)[$c['inspection_type']] ?? '—')) ?></td></tr>
    <tr><th>Product</th><td><?= e((lk_options_or('product', PRODUCT_CATS)[$c['product_category']] ?? '') ?: '—') ?></td></tr>
    <tr><th>Contract</th><td><?= e($c['contract_number'] ?: 'Not yet recorded') ?></td></tr>
    <tr><th>We received it</th><td><?= e(fdate($c['call_received_date'])) ?></td></tr>
    <tr><th>You need it by</th><td><?= e(fdate($c['inspection_required_date'])) ?></td></tr>
    <?php if (!empty($c['inspection_dates'])): ?>
      <tr><th>Visit dates</th><td><?= e(implode(', ', array_map('fdate',
            array_filter(array_map('trim', explode(',', (string)$c['inspection_dates']))))) ) ?></td></tr>
    <?php endif; ?>
  </table></div>
</div>

<h3 class="ptitle" style="font-size:16px">Visits</h3>
<?php if (!$jobs): ?>
  <p class="pempty">No engineer assigned yet. Our coordinators are arranging it.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Reference</th><th>Engineer</th><th>Scheduled</th><th>On site</th><th>Where it has got to</th></tr></thead>
  <tbody>
  <?php foreach ($jobs as $j): ?>
    <tr>
      <td><b><?= e($j['job_code']) ?></b></td>
      <td><?= e($j['inspector_name'] ?: 'Being assigned') ?></td>
      <td><?= e(fdate($j['scheduled_date'])) ?></td>
      <td><?= $j['inspection_start_date']
            ? e(fdate($j['inspection_start_date'])
                . ($j['inspection_end_date'] && $j['inspection_end_date'] !== $j['inspection_start_date']
                   ? ' to ' . fdate($j['inspection_end_date']) : ''))
            : '—' ?></td>
      <td><?= e(portal_job_state($j)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<p class="pnote">Reports for this work appear under <a href="/portal/reports">Reports</a> as soon as they are
  issued. A report that is still being written is not shown, because a draft is not a report.</p>
