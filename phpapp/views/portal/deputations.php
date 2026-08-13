<?php
  $lbl = fn($c) => $statuses[$c] ?? ($c ?: '—');
  $fmt = fn($s) => $s ? fdate(substr((string)$s, 0, 10)) : '—';
  $apLabel = ['DRAFT'=>'Draft','SUBMITTED'=>'Awaiting your approval','CLIENT_REVIEW'=>'Under your review',
              'RETURNED'=>'Returned to the team','APPROVED'=>'Approved','REJECTED'=>'Rejected','LOCKED'=>'Locked'];
?>
<h2 class="ptitle">People deputed to you</h2>
<p class="plead">The personnel working with you under this engagement, and the attendance periods
  submitted for your confirmation. Approving a period confirms the days we may bill — it is not an invoice.</p>

<?php // ---- Attendance periods awaiting the client ----
  $pending = array_values(array_filter($approvals, fn($a) => in_array($a['status'], ['SUBMITTED','CLIENT_REVIEW'], true)));
?>
<?php if ($canApprove && $pending): ?>
  <h3 class="ptitle" style="font-size:17px">Attendance to confirm</h3>
  <div class="pscroll"><table class="ptable">
    <thead><tr><th>Person</th><th>Period</th><th>Basis</th><th>Billable</th><th>Confirm</th></tr></thead>
    <tbody>
    <?php foreach ($pending as $a): ?>
      <tr>
        <td><b><?= e($a['person_name'] ?: '—') ?></b><?= $a['job_code'] ? '<br><span style="color:var(--muted,#888);font-size:12px">'.e($a['job_code']).'</span>' : '' ?></td>
        <td><?= e($fmt($a['period_from'])) ?> – <?= e($fmt($a['period_to'])) ?></td>
        <td><?= e($a['basis'] ?: '—') ?></td>
        <td><?= (float)$a['billable_days'] ? e(rtrim(rtrim(number_format((float)$a['billable_days'],1),'0'),'.')).' days' : '' ?><?= (float)$a['billable_hours'] ? ' '.e(rtrim(rtrim(number_format((float)$a['billable_hours'],1),'0'),'.')).' h' : '' ?></td>
        <td>
          <form method="post" action="/portal/dep-approve" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <input type="text" name="client_rep" placeholder="Your name" style="width:120px">
            <input type="text" name="comments" placeholder="Note (optional)" style="width:150px">
            <button class="btn" type="submit" name="decision" value="APPROVED">Approve</button>
            <button class="btn secondary" type="submit" name="decision" value="RETURNED">Return</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>

<?php // ---- The deputed personnel ----
?>
<h3 class="ptitle" style="font-size:17px">Personnel</h3>
<?php if (!$rows): ?>
  <p class="pempty">No deputed personnel to show yet.</p>
<?php else: ?>
  <div class="pscroll"><table class="ptable">
    <thead><tr><th>Person</th><th>Reference</th><th>Site</th><th>Period</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['person_name'] ?: '—') ?></b></td>
        <td><?= e($r['job_code'] ?: '—') ?></td>
        <td><?= e($r['dep_site'] ?: '—') ?></td>
        <td><?= e($fmt($r['inspection_start_date'])) ?> – <?= e($fmt($r['inspection_end_date'])) ?></td>
        <td><?= e($r['dep_status'] ? $lbl($r['dep_status']) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>

<?php // ---- History of confirmed / returned periods ----
  $decided = array_values(array_filter($approvals, fn($a) => !in_array($a['status'], ['SUBMITTED','CLIENT_REVIEW','DRAFT'], true)));
?>
<?php if ($decided): ?>
  <h3 class="ptitle" style="font-size:17px">Attendance history</h3>
  <div class="pscroll"><table class="ptable">
    <thead><tr><th>Person</th><th>Period</th><th>Billable</th><th>Status</th><th>Confirmed by</th></tr></thead>
    <tbody>
    <?php foreach ($decided as $a): ?>
      <tr>
        <td><?= e($a['person_name'] ?: '—') ?></td>
        <td><?= e($fmt($a['period_from'])) ?> – <?= e($fmt($a['period_to'])) ?></td>
        <td><?= (float)$a['billable_days'] ? e(rtrim(rtrim(number_format((float)$a['billable_days'],1),'0'),'.')).' days' : '—' ?></td>
        <td><?php $c = $a['status']==='APPROVED'?'var(--ok)':($a['status']==='REJECTED'?'var(--bad)':'inherit'); ?>
          <b style="color:<?= $c ?>"><?= e($apLabel[$a['status']] ?? $a['status']) ?></b></td>
        <td><?= e($a['client_rep'] ?: '—') ?><?= $a['approved_on'] ? '<br><span style="color:var(--muted,#888);font-size:12px">'.e($fmt($a['approved_on'])).'</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
