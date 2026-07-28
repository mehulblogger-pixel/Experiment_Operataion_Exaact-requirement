<h2 class="ptitle">Complaints and appeals</h2>
<p class="plead">Anything you have raised with us, and where it has got to. How we handle them is set out in our
  <a href="/complaints-policy">published complaints and appeals process</a>.</p>

<p style="margin:0 0 22px"><a class="btn" href="/portal/complaint-new">Raise a complaint or appeal</a></p>

<?php if (!$rows): ?>
  <p class="pempty">You have not raised anything with us.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Reference</th><th>What it is about</th><th>Raised</th><th>Acknowledged</th><th>Where it has got to</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['ref']) ?></b><?= $r['kind'] === 'APPEAL' ? '<br><span style="opacity:.7;font-size:12px">appeal</span>' : '' ?></td>
      <td><?= e($r['subject']) ?></td>
      <td><?= e(fdate($r['received_on'])) ?></td>
      <td><?= $r['acknowledged_on'] ? e(fdate($r['acknowledged_on'])) : '<span style="opacity:.7">not yet</span>' ?></td>
      <td><?php
        // Status only. Who it was assigned to, what the engineer said and the
        // internal correspondence are ours, not the client's.
        if ($r['status'] === 'CLOSED') {
            echo 'Closed';
            if ($r['notified_on']) echo '<br><span style="opacity:.7;font-size:12px">we wrote to you on ' . e(fdate($r['notified_on'])) . '</span>';
        } elseif ($r['decided_on']) {
            echo 'Decided, being written up';
        } elseif ($r['acknowledged_on']) {
            echo 'Being looked into';
        } else {
            echo 'With us';
        }
      ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="pnote">If you disagree with how one of these was decided, you can appeal it — and the person who made the
  original decision is not allowed to decide the appeal.</p>
<?php endif; ?>
