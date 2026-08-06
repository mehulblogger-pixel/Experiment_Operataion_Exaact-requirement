<?php
$rbadge = ['LOW' => 'GREEN', 'MEDIUM' => 'AMBER', 'HIGH' => 'RED', 'CRITICAL' => 'RED'][$r['rating']] ?? 'GREY';
$sbadge = ['OPEN' => 'AMBER', 'TREATING' => 'BLUE', 'MONITORING' => 'BLUE', 'CLOSED' => 'GREEN'][$r['status']] ?? 'GREY';
$open = in_array($r['status'], RISK_OPEN, true);
$ee = fn($x) => e((string)$x);
$lvl = fn($c) => (RISK_LEVELS[$c] ?? '—');
$row = fn($lbl, $val) => $val !== '' && $val !== null ? '<tr><th style="text-align:left;width:180px;color:#697787;font-weight:600">' . e($lbl) . '</th><td>' . $val . '</td></tr>' : '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/risks">Risks &amp; opportunities</a> › <?= e($r['risk_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($r['risk_code']) ?>
    <span class="badge <?= $r['kind'] === 'OPPORTUNITY' ? 'GREEN' : 'GREY' ?>"><?= e(ucfirst(strtolower($r['kind']))) ?></span>
    <?php if ($r['rating']): ?><span class="badge <?= $rbadge ?>"><?= e($r['rating']) ?></span><?php endif; ?>
    <span class="badge <?= $sbadge ?>"><?= e(ucfirst(strtolower($r['status']))) ?></span></h1>
    <p class="sub"><?= e($r['title']) ?></p></div>
  <?php if ($canEdit): ?><a class="btn secondary" href="/risk-edit?id=<?= (int)$r['id'] ?>">Edit</a><?php endif; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="grid" style="margin:0">
    <?= $row('Category', $ee(risk_cat_label($r['category']))) ?>
    <?= $row('Area / context', $ee($r['context'])) ?>
    <?= $row('Description', nl2br($ee($r['description']))) ?>
    <?= $row('Likelihood', $ee($lvl($r['likelihood']))) ?>
    <?= $row('Impact', $ee($lvl($r['impact']))) ?>
    <?= $row('Rating', $ee($r['rating'])) ?>
    <?= $row('Treatment', nl2br($ee($r['treatment']))) ?>
    <?= $row('Owner', $ee($r['owner'])) ?>
    <?= $row('Review due', $ee($r['review_due'])) ?>
    <?php if (!empty($r['closed_on'])): ?>
      <?= $row('Closed', $ee($r['closed_on'] . ' by ' . $r['closed_by'])) ?>
      <?= $row('Closing note', $ee($r['closed_note'])) ?>
    <?php endif; ?>
    <?php foreach (($cvals ?? []) as $lbl => $val): ?><?= $row($lbl, $ee($val)) ?><?php endforeach; ?>
  </table>
</div>

<?php if ($canEdit && $open): ?>
<div class="card" style="margin-top:20px;max-width:460px">
  <h4 style="margin-top:0">Change status</h4>
  <form method="post" action="/risk-status">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <select class="form-control" name="status">
      <option value="TREATING">Treating (action under way)</option>
      <option value="MONITORING">Monitoring</option>
      <option value="OPEN">Back to Open</option>
      <option value="CLOSED">Close</option>
    </select>
    <input class="form-control" name="note" placeholder="Note (required to close)" style="margin-top:8px">
    <button class="btn small" type="submit" style="margin-top:10px">Update status</button>
  </form>
</div>
<?php endif; ?>
