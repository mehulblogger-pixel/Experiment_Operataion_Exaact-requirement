<h2 class="ptitle">Your overview</h2>
<p class="plead">Everything <?= e(app_name()) ?> is doing for <?= e(portal_client_name()) ?>, at a glance.
  Reports appear here the moment they are issued — you do not have to ask for them.</p>

<?php // Each tile is a figure from a register, so it is only shown to somebody
      // who may open that register. A money tile in front of a QA engineer who
      // is not allowed to see invoices is the leak, not the invoice screen. ?>
<div class="ptiles">
  <?php if (pcan('calls')): ?>
  <div class="ptile"><div class="n"><?= (int)$d['open_calls'] ?></div>
    <div class="l"><?= e(Tlp('call')) ?> still open</div></div>
  <?php endif; ?>
  <?php if (pcan('reports')): ?>
  <div class="ptile"><div class="n"><?= (int)$d['reports'] ?></div>
    <div class="l">reports issued to you</div></div>
  <?php endif; ?>
  <?php if (pcan('invoices')): ?>
  <div class="ptile"><div class="n"><?= e(fmoney_short($d['outstanding'])) ?></div>
    <div class="l">invoiced and not yet settled</div></div>
  <?php endif; ?>
  <?php if (pcan('request')): ?>
  <div class="ptile"><div class="n"><?= (int)$d['requests_open'] ?></div>
    <div class="l">requests awaiting our reply</div></div>
  <?php endif; ?>
</div>

<?php if (pcan('invoices') && (int)$d['overdue'] > 0): ?>
  <div class="pmsg err"><?= (int)$d['overdue'] ?> invoice(s) are past their due date.
    <a href="/portal/invoices">See which</a>. If one of them is in query with us, tell us on the
    request form and we will look at it.</div>
<?php endif; ?>

<?php if (pcan('reports')): ?>
<h3 class="ptitle" style="font-size:16px;margin-top:26px">Latest reports</h3>
<?php if (!$d['recent']): ?>
  <p class="pempty">Nothing issued yet. When a report is finished and signed off, it appears here.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Report</th><th>Title</th><th>Result</th><th>Issued</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($d['recent'] as $r): ?>
    <tr>
      <td><b><?= e($r['irn']) ?></b></td>
      <td><?= e($r['title'] ?: '—') ?></td>
      <td><?= e(lk_options_or('inspection_result', IDEMS_RESULTS)[$r['result']] ?? ($r['result'] ?: '—')) ?></td>
      <td><?= e(fdate(substr((string)($r['finalized_at'] ?: $r['issue_date']), 0, 10))) ?></td>
      <td><a href="/portal/report?id=<?= (int)$r['id'] ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="pnote" style="margin-top:-10px"><a href="/portal/reports">All reports</a></p>
<?php endif; ?>
<?php endif; ?>

<?php if (pcan('calls')): ?>
<h3 class="ptitle" style="font-size:16px;margin-top:30px">Open <?= e(Tlp('call')) ?></h3>
<?php if (!$d['upcoming']): ?>
  <p class="pempty">Nothing open. <a href="/portal/request">Ask us for an inspection</a> when you need one.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Reference</th><th>Required by</th><th>Where it has got to</th></tr></thead>
  <tbody>
  <?php foreach ($d['upcoming'] as $c): ?>
    <tr>
      <td><a href="/portal/call?id=<?= (int)$c['id'] ?>"><b><?= e($c['call_code']) ?></b></a></td>
      <td><?= e(fdate($c['inspection_required_date'])) ?></td>
      <td><?= e(portal_call_state($c)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php endif; ?>
