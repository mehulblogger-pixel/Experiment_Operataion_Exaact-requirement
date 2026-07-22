<h1>My Jobs</h1>
<p class="sub">Your assigned inspections. Upload the report and close each job on completion.</p>
<table class="grid">
  <tr><th>Job</th><th>Client</th><th>Scheduled</th><th>Inspection</th><th>Reporting</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $j): ?>
  <tr>
    <td><a href="/job?id=<?= (int)$j['id'] ?>"><?= e($j['job_code']) ?></a></td>
    <td><?= e($j['client_disp'] ?: $j['client_name'] ?: '—') ?></td>
    <td><?= e($j['scheduled_date'] ?: '—') ?></td>
    <td><?= e(($j['inspection_start_date'] ?: '?') . ' → ' . ($j['inspection_end_date'] ?: '?')) ?></td>
    <td><?= e(REPORT_FREQ[$j['reporting_frequency']] ?? '—') ?></td>
    <td><?= $j['closed_flag'] ? '<span class="badge GREEN">Closed</span>' : '<span class="badge AMBER">Open</span>' ?></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/job?id=<?= (int)$j['id'] ?>">Open</a>
      <?php if (!$j['closed_flag']): ?><a class="btn small" href="/job-close?id=<?= (int)$j['id'] ?>">Upload &amp; Close</a><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">No jobs assigned to you.</td></tr><?php endif; ?>
</table>
