<h2 class="ptitle">Your overview</h2>
<p class="plead">The inspection, audit and assessment work <?= e(app_name()) ?> has shared with
  <?= e(cvp_vendor_name()) ?>, at a glance. Reports appear here the moment we share them — you do not have to ask.</p>

<?php if (!empty($d['actions'])): ?>
<div class="pcard" style="border-left:5px solid var(--warn)">
  <div style="font-weight:600;margin-bottom:8px">Awaiting you</div>
  <?php foreach ($d['actions'] as $a): ?>
    <div style="margin:4px 0"><a href="<?= e($a['url']) ?>"><?= e($a['label']) ?> →</a></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="ptiles">
  <?php if (vcan('reports')): ?>
  <div class="ptile"><div class="n"><?= (int)$d['reports'] ?></div>
    <div class="l">reports shared with you</div></div>
  <?php endif; ?>
  <?php if (vcan('issues')): ?>
  <div class="ptile"><div class="n"><?= (int)$d['issues_open'] ?></div>
    <div class="l">open nonconformities</div></div>
  <?php endif; ?>
</div>

<?php if (vcan('issues') && (int)$d['issues_open'] > 0 && function_exists('cvp_vendor_issue_links')): ?>
  <div class="pmsg err"><?= (int)$d['issues_open'] ?> nonconformity(ies) need your response.
    <a href="/vendor/issues">See which</a>.</div>
<?php endif; ?>

<?php if (vcan('reports')): ?>
<h3 class="ptitle" style="font-size:16px;margin-top:26px">Latest reports</h3>
<?php if (!$d['recent']): ?>
  <p class="pempty">Nothing shared yet. When we share a report with you, it appears here.</p>
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
      <td><a href="/vendor/report?id=<?= (int)$r['id'] ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="pnote" style="margin-top:-10px"><a href="/vendor/reports">All reports</a></p>
<?php endif; ?>
<?php endif; ?>
