<?php
$badge = ['DRAFT' => 'AMBER', 'CURRENT' => 'GREEN', 'SUPERSEDED' => 'GREY', 'WITHDRAWN' => 'RED'][$r['status']] ?? 'GREY';
$ee = fn($x) => e((string)$x);
$row = fn($lbl, $val) => $val !== '' && $val !== null ? '<tr><th style="text-align:left;width:200px;color:#697787;font-weight:600">' . e($lbl) . '</th><td>' . $val . '</td></tr>' : '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/drules">Decision rules</a> › <?= e($r['rule_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($r['rule_code']) ?> <span class="badge <?= $badge ?>"><?= e($r['status']) ?></span></h1>
    <p class="sub"><?= e($r['title']) ?></p></div>
  <?php if ($canEdit): ?><a class="btn secondary" href="/drule-edit?id=<?= (int)$r['id'] ?>">Edit</a><?php endif; ?>
</div>

<?php if ($supersededBy): ?><div class="note" style="padding:12px 14px;margin:12px 0;background:#fff;border:1px solid #e2ddd6;border-left:3px solid #b5751a;border-radius:8px">
  Superseded by <a href="/drule?id=<?= (int)$supersededBy['id'] ?>"><?= e($supersededBy['rule_code']) ?></a>.</div><?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="grid" style="margin:0">
    <?= $row('Belongs to method', $method ? '<a href="/method?id=' . (int)$method['id'] . '">' . $ee($method['method_code'] . ' — ' . $method['title']) . '</a>' : '') ?>
    <?= $row('Characteristic judged', $ee($r['characteristic'])) ?>
    <?= $row('Acceptance criteria', nl2br($ee($r['accept_criteria']))) ?>
    <?= $row('Rejection criteria', nl2br($ee($r['reject_criteria']))) ?>
    <?= $row('Decision basis', $ee(drule_basis_label($r['decision_basis']))) ?>
    <?= $row('Uncertainty applied', $ee($r['uncertainty_rule'])) ?>
    <?= $row('Owner / custodian', $ee($r['owner'])) ?>
    <?php foreach (($cvals ?? []) as $lbl => $val): ?><?= $row($lbl, $ee($val)) ?><?php endforeach; ?>
    <?= $row('Notes', nl2br($ee($r['notes']))) ?>
  </table>
</div>

<?php if ($canEdit): ?>
<div class="grid two" style="margin-top:20px">
  <div class="card"><h4 style="margin-top:0">Status</h4>
    <form method="post" action="/drule-status">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <select class="form-control" name="status">
        <option value="CURRENT">Set Current (in force)</option>
        <option value="DRAFT">Back to Draft</option>
        <option value="WITHDRAWN">Withdraw</option>
      </select>
      <button class="btn small" type="submit" style="margin-top:10px">Update status</button>
    </form>
  </div>
  <div class="card"><h4 style="margin-top:0">Issue a new revision</h4>
    <p class="muted" style="font-size:13px;margin:0 0 8px">Creates a new draft and marks this one superseded. History is kept.</p>
    <form method="post" action="/drule-supersede">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn small" type="submit">Create new revision</button>
    </form>
  </div>
</div>
<?php endif; ?>
