<h2 class="ptitle">Nonconformities</h2>
<p class="plead">Findings <?= e(app_name()) ?> has raised on your work and shared with you. Open one to record your
  acceptance, disagreement, root cause, corrective action or evidence.</p>

<?php if (!$rows): ?>
  <p class="pempty">Nothing open.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Ref</th><th>Finding</th><th>Severity</th><th>Raised</th><th>Due</th><th>State</th><th>Your responses</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['ref'] ?: ('#' . (int)$r['id'])) ?></b></td>
      <td><?= e($r['title'] ?: ($r['description'] ? mb_substr($r['description'], 0, 80) : '—')) ?></td>
      <td><?= e($r['severity'] ?: '—') ?></td>
      <td><?= e(fdate($r['detected_on'])) ?></td>
      <td><?= e(fdate($r['due_on'])) ?></td>
      <td><?php $closed = (string)$r['status'] === 'CLOSED'; ?>
        <span class="badge <?= $closed ? 'GREEN' : 'AMBER' ?>" style="font-size:12px"><?= e(ucfirst(strtolower((string)$r['status']))) ?></span></td>
      <td><?= (int)$r['my_responses'] ?></td>
      <td><a href="/portal/issue?id=<?= (int)$r['id'] ?>"><?= $closed ? 'View' : 'Respond →' ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
