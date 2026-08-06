<?php
$badge = ['DRAFT' => 'AMBER', 'CURRENT' => 'GREEN', 'SUPERSEDED' => 'GREY', 'WITHDRAWN' => 'RED'][$m['status']] ?? 'GREY';
$row = fn($lbl, $val) => $val !== '' && $val !== null ? '<tr><th style="text-align:left;width:190px;color:#697787;font-weight:600">' . e($lbl) . '</th><td>' . $val . '</td></tr>' : '';
$ee = fn($x) => e((string)$x);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/methods">Method library</a> › <?= e($m['method_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($m['method_code']) ?> <span class="badge <?= $badge ?>"><?= e($m['status']) ?></span>
    <?php if ((int)$m['is_validated'] === 1): ?><span class="badge GREEN">Validated</span><?php else: ?><span class="badge AMBER">Not validated</span><?php endif; ?></h1>
    <p class="sub"><?= e($m['title']) ?><?php if ($m['revision']): ?> · <?= e($m['revision']) ?><?php endif; ?></p></div>
  <?php if ($canEdit): ?><a class="btn secondary" href="/method-edit?id=<?= (int)$m['id'] ?>">Edit</a><?php endif; ?>
</div>

<?php if ($supersededBy): ?><div class="note" style="border-left:3px solid #b5751a;padding:12px 14px;margin:12px 0;background:#fff;border:1px solid #e2ddd6;border-radius:8px">
  This revision has been superseded by <a href="/method?id=<?= (int)$supersededBy['id'] ?>"><?= e($supersededBy['method_code']) ?> <?= e($supersededBy['revision']) ?></a>.</div><?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="grid" style="margin:0">
    <?= $row('Standard reference', $ee($m['standard_ref'])) ?>
    <?= $row('Revision', $ee($m['revision'])) ?>
    <?= $row('Category', $ee(method_label('method_category', $m['category']))) ?>
    <?= $row('Discipline / SBU', $ee($m['discipline'])) ?>
    <?= $row('Effective date', $ee($m['effective_date'])) ?>
    <?= $row('Review due', $ee($m['review_due'])) ?>
    <?= $row('Owner / custodian', $ee($m['owner'])) ?>
    <?= $row('Document', $m['file_name'] ? '<a href="/method-file?id=' . (int)$m['id'] . '">' . $ee($m['file_name']) . '</a>' : '') ?>
    <?php if ((int)$m['is_validated'] === 1): ?>
      <?= $row('Validated', $ee($m['validated_on'] . ' by ' . $m['validated_by'])) ?>
      <?= $row('Validation note', $ee($m['validation_note'])) ?>
    <?php endif; ?>
    <?php if ($supersedes): ?><?= $row('Supersedes', '<a href="/method?id=' . (int)$supersedes['id'] . '">' . $ee($supersedes['method_code'] . ' ' . $supersedes['revision']) . '</a>') ?><?php endif; ?>
    <?php foreach (($cvals ?? []) as $lbl => $val): ?><?= $row($lbl, $ee($val)) ?><?php endforeach; ?>
    <?= $row('Description / scope', nl2br($ee($m['description']))) ?>
  </table>
</div>

<?php if ($canEdit): ?>
<div class="grid two" style="margin-top:20px">
  <div class="card">
    <h4 style="margin-top:0">Status</h4>
    <form method="post" action="/method-status">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <select class="form-control" name="status">
        <option value="CURRENT">Set Current (in force)</option>
        <option value="DRAFT">Back to Draft</option>
        <option value="WITHDRAWN">Withdraw</option>
      </select>
      <button class="btn small" type="submit" style="margin-top:10px">Update status</button>
    </form>
    <?php if ((int)$m['is_validated'] === 0): ?>
    <h4 style="margin-top:16px">Record validation</h4>
    <form method="post" action="/method-validate">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <input class="form-control" name="note" placeholder="How it was validated (basis / who)">
      <button class="btn small" type="submit" style="margin-top:10px">Mark validated</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <h4 style="margin-top:0">Issue a new revision</h4>
    <p class="muted" style="font-size:13px;margin:0 0 8px">Creates a new draft revision and marks this one superseded. History is kept.</p>
    <form method="post" action="/method-supersede">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <input class="form-control" name="revision" placeholder="New revision label (e.g. Rev 4)">
      <button class="btn small" type="submit" style="margin-top:10px">Create new revision</button>
    </form>
  </div>
</div>
<?php endif; ?>
