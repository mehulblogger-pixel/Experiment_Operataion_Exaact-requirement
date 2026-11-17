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
  $waitIds = $waitIds ?? []; $canCreate = $canCreate ?? false;
  $canOwn = $canOwn ?? $mine; $seesAll = $seesAll ?? false; $isFinance = $isFinance ?? false;
  //  Batch 2 (owner, 17 Nov 2026): say exactly what this list holds for THIS person.
  if ($mine)          $whatShown = ' — only your own claims are shown.';
  elseif ($seesAll)   $whatShown = ' — every voucher in the company (administrator).';
  else                $whatShown = ' — your own, those waiting for your approval, and those you have approved or sent back'
                               . ($isFinance ? ', plus those at or past the Finance step in the offices you cover.' : '.');
?>
<div class="master-head">
  <div><h1><?= $mine ? 'My expenses' : 'Expense ' . e(Tlp('voucher')) ?></h1>
    <p class="sub" style="margin:2px 0 0">One <?= e(Tl('voucher')) ?> per period (<?= e(strtolower(EXP_PERIODS[exp_rules()['period']] ?? 'monthly')) ?>)<?= e($whatShown) ?></p></div>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="k-lab"><?= $mine ? 'Total I have claimed' : 'Total expense claimed' ?></div>
    <div class="k-val"><?= e(cur_sym()) ?><?= nf($tClaimed, 0) ?></div>
    <div class="k-sub"><?= count($rows) ?> voucher<?= count($rows)==1?'':'s' ?><?= $mine?'':' · '.count($insp).' inspector'.(count($insp)==1?'':'s') ?></div></div>
  <div class="kpi"><div class="k-lab">This month (<?= e($curMonth) ?>)</div>
    <div class="k-val"><?= e(cur_sym()) ?><?= nf($tThisMonth, 0) ?></div>
    <div class="k-sub">current period</div></div>
  <div class="kpi"><div class="k-lab">Awaiting approval</div>
    <div class="k-val"><?= $awaiting ?></div>
    <div class="k-sub"><?= $cDraft ?> draft · <?= $cSubmitted ?> submitted</div></div>
  <div class="kpi"><div class="k-lab"><?= $mine ? 'Paid to me' : 'Paid out' ?></div>
    <div class="k-val up"><?= e(cur_sym()) ?><?= nf($sPaid, 0) ?></div>
    <div class="k-sub"><?= $cPaid ?> paid · <?= $cApproved ?> approved</div></div>
</div>

<?php if ($waitIds): ?>
  <div class="panel" style="border-left:3px solid var(--accent,#2b6cff);padding:10px 14px">
    <strong><?= count($waitIds) ?> <?= e(count($waitIds) == 1 ? Tl('voucher') : Tlp('voucher')) ?> waiting for your approval</strong>
    <span class="muted"> — marked "Your turn" below. Open one to approve it or send it back with a reason.</span>
  </div>
<?php endif; ?>
<?php if ($canOwn): ?>
  <?php // Expenses Phase 1 — the day's expenses land in the claim by themselves when
        // the day is closed; this opens the claim for today's period. ?>
  <div class="filter-bar" style="gap:8px;flex-wrap:wrap">
    <a class="btn" href="/voucher?date=<?= e(date('Y-m-d')) ?>" style="min-height:44px;display:inline-flex;align-items:center">My <?= e(Tl('voucher')) ?> for this period</a>
    <?php if (!empty($nonJob)): ?><a class="btn secondary" href="/voucher?date=<?= e(date('Y-m-d')) ?>#quick-add" style="min-height:44px;display:inline-flex;align-items:center">＋ Add expense</a><?php endif; ?>
    <form method="get" action="/voucher" style="display:flex;gap:6px;align-items:center">
      <label class="muted"><?= $mine ? 'Another month:' : 'Your own, another month:' ?></label>
      <input class="form-control" type="month" name="month" value="<?= e($curMonth) ?>" required>
      <button class="btn secondary" type="submit">Open</button>
    </form>
  </div>
<?php endif; ?>
<?php if (!$mine && $canCreate): ?>
  <form method="get" action="/voucher" class="filter-bar">
    <label class="muted" style="align-self:center">Open a voucher for someone:</label>
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
    <thead><tr><th>Period</th><?php if (!$mine): ?><th>Inspector</th><?php endif; ?><th>Status</th><th class="num">Total claimed</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><strong><?= e(exp_period_label($r)) ?></strong></td>
      <?php if (!$mine): ?><td><?= e($r['inspector_name'] ?? '—') ?></td><?php endif; ?>
      <td><span class="pill <?= $statusPill[$r['status']] ?? 'p-mut' ?>"><?= e($r['status']) ?></span><?php if (in_array((int)$r['id'], $waitIds, true)): ?> <span class="pill p-warn">Your turn</span><?php endif; ?></td>
      <td class="num"><?= (float)($r['total'] ?? 0)>0 ? cur_sym().nf((float)$r['total'],0) : '—' ?></td>
      <td class="num"><a class="btn small" href="/voucher?id=<?= (int)$r['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= $mine?4:5 ?>" style="text-align:center;padding:24px" class="muted">No vouchers yet<?= $mine?' — open the current month above to start':'' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
