<h2 class="ptitle">Reports shared with you</h2>
<p class="plead">The inspection, audit and assessment reports <?= e(app_name()) ?> has chosen to share with
  <?= e(cvp_vendor_name()) ?>. Only finished, signed-off reports appear — a draft is not a report.</p>

<?php if (!$rows): ?>
  <p class="pempty">Nothing shared yet.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr>
    <th>Report</th><th>Title</th><th>Inspected</th><th>Result</th><th>Release</th>
    <th>Issued</th><th>Verification code</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['irn']) ?></b></td>
      <td><?= e($r['title'] ?: '—') ?></td>
      <td><?= e(fdate($r['inspection_date'])) ?></td>
      <td><?= e(lk_options_or('inspection_result', IDEMS_RESULTS)[$r['result']] ?? ($r['result'] ?: '—')) ?></td>
      <td><?= e(lk_options_or('release_status', IDEMS_RELEASE)[$r['release_status']] ?? ($r['release_status'] ?: '—')) ?></td>
      <td><?= e(fdate(substr((string)($r['finalized_at'] ?: $r['issue_date']), 0, 10))) ?></td>
      <td><?php if (!empty($r['verify_code'])): ?><code><?= e($r['verify_code']) ?></code><?php else: ?>—<?php endif; ?></td>
      <td><a href="/vendor/report?id=<?= (int)$r['id'] ?>">Open PDF</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<p class="pnote">The verification code is on the report itself. Anybody you pass the report to can type that code at
  <a href="/verify">/verify</a> and be told whether it is genuine and unaltered, without an account.</p>
<?php endif; ?>
