<?php $statusBadge = ['DRAFT'=>'AMBER','SUBMITTED'=>'AMBER','APPROVED'=>'GREEN','PAID'=>'GREEN']; ?>
<div class="master-head">
  <div><h1><?= $mine ? 'My travelling-expense vouchers' : 'Inspector vouchers' ?></h1>
    <p class="sub">Monthly "Statement of Travelling Expenses" · <?= count($rows) ?> shown</p></div>
</div>

<?php if ($mine): ?>
  <form method="get" action="/voucher" class="filter-bar">
    <label class="muted" style="align-self:center">Open a month:</label>
    <input class="form-control" type="month" name="month" value="<?= e(date('Y-m')) ?>" required>
    <button class="btn" type="submit">Open / create</button>
  </form>
<?php else: ?>
  <form method="get" action="/voucher" class="filter-bar">
    <label class="muted" style="align-self:center">Open a voucher:</label>
    <select class="form-control searchable" name="ins" required style="min-width:220px"><option value="">— inspector —</option>
      <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?>
    </select>
    <input class="form-control" type="month" name="month" value="<?= e(date('Y-m')) ?>" required>
    <button class="btn" type="submit">Open / create</button>
  </form>
<?php endif; ?>

<table class="grid">
  <tr><th>Month</th><?php if (!$mine): ?><th>Inspector</th><?php endif; ?><th>Status</th><th>Total</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><strong><?= e($r['month']) ?></strong></td>
    <?php if (!$mine): ?><td><?= e($r['inspector_name'] ?? '—') ?></td><?php endif; ?>
    <td><span class="badge <?= $statusBadge[$r['status']] ?? 'AMBER' ?>"><?= e($r['status']) ?></span></td>
    <td><?= $r['total']>0 ? '₹'.number_format((float)$r['total'],0) : '—' ?></td>
    <td class="row-actions"><a class="btn small" href="/voucher?id=<?= (int)$r['id'] ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= $mine?4:5 ?>">No vouchers yet<?= $mine?' — open the current month above to start':'' ?>.</td></tr><?php endif; ?>
</table>
