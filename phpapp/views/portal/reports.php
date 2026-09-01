<h2 class="ptitle">Reports issued to you</h2>
<p class="plead">Only reports that have been finished and signed off. Anything still being written is not here,
  because a draft is not a report and should never be quoted as one.</p>

<?php if (!$rows): ?>
  <p class="pempty">Nothing issued yet.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr>
    <th>Report</th><th>Title</th><th>Inspected</th><th>Result</th><th>Release</th>
    <th>Issued</th><th>Your decision</th><th>Verification code</th><th></th>
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
      <td><?php
            $dec = $r['client_decision'] ?? '';
            if ($dec === 'ACCEPTED') echo '<b style="color:var(--ok)">Accepted</b>';
            // Stage 7 — a rejection is an active loop, not a dead end: it is returned
            // to our team (an NCR is opened) and a revised report will follow.
            elseif ($dec === 'REJECTED') echo '<b style="color:var(--bad)">Rejected</b><br><span style="font-size:11.5px;color:var(--muted,#888)">↩ returned to our team for rework</span>';
            else echo '<a href="/portal/report-decision?id=' . (int)$r['id'] . '">Accept or reject →</a>';
          ?></td>
      <td><?php if (!empty($r['verify_code'])): ?>
            <code><?= e($r['verify_code']) ?></code>
          <?php else: ?>—<?php endif; ?></td>
      <td><a href="/portal/report?id=<?= (int)$r['id'] ?>">Open PDF</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<p class="pnote">The verification code is on the report itself. Anybody you pass the report to — your customer,
  a bank, a port authority — can type that code at <a href="/verify">/verify</a> and be told whether it is genuine
  and unaltered, without an account and without asking you or us.</p>
<?php endif; ?>
