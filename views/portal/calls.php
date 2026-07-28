<h2 class="ptitle">Your <?= e(Tlp('call')) ?></h2>
<p class="plead">Every inspection you have asked us for, newest first, and where each one has got to.</p>

<?php if (!$rows): ?>
  <p class="pempty">Nothing here yet. <a href="/portal/request">Ask us for an inspection</a> and it will appear
    once a coordinator has raised it.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr>
    <th>Reference</th><th>Type</th><th>Product</th><th>Contract</th>
    <th>Received</th><th>Required by</th><th>Where it has got to</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><a href="/portal/call?id=<?= (int)$c['id'] ?>"><b><?= e($c['call_code']) ?></b></a></td>
      <td><?= e(($c['inspection_type'] ?? '') === 'OTHER'
                ? ($c['inspection_type_other'] ?: 'Other')
                : (lk_options_or('inspection_type', INSPECTION_TYPES)[$c['inspection_type']] ?? '—')) ?></td>
      <td><?= e((lk_options_or('product', PRODUCT_CATS)[$c['product_category']] ?? '') ?: '—') ?></td>
      <td><?= e($c['contract_number'] ?: '—') ?></td>
      <td><?= e(fdate($c['call_received_date'])) ?></td>
      <td><?= e(fdate($c['inspection_required_date'])) ?></td>
      <td><?= e(portal_call_state($c)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
