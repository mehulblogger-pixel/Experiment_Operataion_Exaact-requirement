<?php
  // Role-scoped roll-up. For an inspector, $rows already holds only their own
  // vouchers; for a coordinator/manager it holds everyone in view. Cards below
  // therefore always reflect exactly what this user is allowed to see.
  $statusPill = ['DRAFT'=>'p-mut','SUBMITTED'=>'p-warn','APPROVED'=>'p-info','PAID'=>'p-ok'];
  $tClaimed = 0; $cDraft = 0; $cSubmitted = 0; $cApproved = 0; $cPaid = 0; $sPaid = 0; $insp = [];
  $curMonth = date('Y-m'); $tThisMonth = 0;
  foreach ($rows as $r) {
    $t = (float)($r['total'] ?? 0); $tClaimed += $t;
    switch ($r['status']) {
      case 'DRAFT': $cDraft++; break;
      case 'SUBMITTED': $cSubmitted++; break;
      case 'APPROVED': $cApproved++; break;
      case 'PAID': $cPaid++; $sPaid += $t; break;
    }
    if (!empty($r['inspector_id'])) $insp[$r['inspector_id']] = 1;
    if (($r['month'] ?? '') === $curMonth) $tThisMonth += $t;
  }
  $awaiting = $cDraft + $cSubmitted;
?>
<div class="master-head">
  <div><h1><?= $mine ? 'My travelling-expense vouchers' : 'Inspector vouchers' ?></h1>
    <p class="sub" style="margin:2px 0 0">Monthly "Statement of Travelling Expenses"<?= $mine ? ' — only your own claims are shown.' : ' — across the inspectors you supervise.' ?></p></div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab"><?= $mine ? 'Total I have claimed' : 'Total expense claimed' ?></div>
    <div class="k-val">₹<?= number_format($tClaimed, 0) ?></div>
    <div class="k-sub"><?= count($rows) ?> voucher<?= count($rows)==1?'':'s' ?><?= $mine?'':' · '.count($insp).' inspector'.(count($insp)==1?'':'s') ?></div></div>
  <div class="kpi"><div class="k-lab">This month (<?= e($curMonth) ?>)</div>
    <div class="k-val">₹<?= number_format($tThisMonth, 0) ?></div>
    <div class="k-sub">current period</div></div>
  <div class="kpi"><div class="k-lab">Awaiting approval</div>
    <div class="k-val"><?= $awaiting ?></div>
    <div class="k-sub"><?= $cDraft ?> draft · <?= $cSubmitted ?> submitted</div></div>
  <div class="kpi"><div class="k-lab"><?= $mine ? 'Paid to me' : 'Paid out' ?></div>
    <div class="k-val up">₹<?= number_format($sPaid, 0) ?></div>
    <div class="k-sub"><?= $cPaid ?> paid · <?= $cApproved ?> approved</div></div>
</div>

<?php if ($mine): ?>
  <form method="get" action="/voucher" class="filter-bar">
    <label class="muted" style="align-self:center">Open a month:</label>
    <input class="form-control" type="month" name="month" value="<?= e($curMonth) ?>" required>
    <button class="btn" type="submit">Open / create</button>
  </form>
<?php else: ?>
  <form method="get" action="/voucher" class="filter-bar">
    <label class="muted" style="align-self:center">Open a voucher:</label>
    <select class="form-control searchable" name="ins" required style="min-width:220px"><option value="">— inspector —</option>
      <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?>
    </select>
    <input class="form-control" type="month" name="month" value="<?= e($curMonth) ?>" required>
    <button class="btn" type="submit">Open / create</button>
  </form>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Month</th><?php if (!$mine): ?><th>Inspector</th><?php endif; ?><th>Status</th><th class="num">Total claimed</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><strong><?= e($r['month']) ?></strong></td>
      <?php if (!$mine): ?><td><?= e($r['inspector_name'] ?? '—') ?></td><?php endif; ?>
      <td><span class="pill <?= $statusPill[$r['status']] ?? 'p-mut' ?>"><?= e($r['status']) ?></span></td>
      <td class="num"><?= (float)($r['total'] ?? 0)>0 ? '₹'.number_format((float)$r['total'],0) : '—' ?></td>
      <td class="num"><a class="btn small" href="/voucher?id=<?= (int)$r['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= $mine?4:5 ?>" style="text-align:center;padding:24px" class="muted">No vouchers yet<?= $mine?' — open the current month above to start':'' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
