<?php
$badge = ['DRAFT' => 'AMBER', 'CURRENT' => 'GREEN', 'SUPERSEDED' => 'GREY', 'WITHDRAWN' => 'RED'][$d['status']] ?? 'GREY';
$ee = fn($x) => e((string)$x);
$row = fn($lbl, $val) => $val !== '' && $val !== null ? '<tr><th style="text-align:left;width:190px;color:#697787;font-weight:600">' . e($lbl) . '</th><td>' . $val . '</td></tr>' : '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/cdocs">Controlled documents</a> › <?= e($d['doc_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($d['doc_code']) ?> <span class="badge <?= $badge ?>"><?= e($d['status']) ?></span></h1>
    <p class="sub"><?= e($d['title']) ?><?php if ($d['revision']): ?> · <?= e($d['revision']) ?><?php endif; ?></p></div>
  <?php if ($canEdit): ?><a class="btn secondary" href="/cdoc-edit?id=<?= (int)$d['id'] ?>">Edit</a><?php endif; ?>
</div>

<?php if ($supersededBy): ?><div class="note" style="padding:12px 14px;margin:12px 0;background:#fff;border:1px solid #e2ddd6;border-left:3px solid #b5751a;border-radius:8px">
  Superseded by <a href="/cdoc?id=<?= (int)$supersededBy['id'] ?>"><?= e($supersededBy['doc_code']) ?> <?= e($supersededBy['revision']) ?></a>.</div><?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="grid" style="margin:0">
    <?= $row('Type', $ee(cdoc_type_label($d['doc_type']))) ?>
    <?= $row('Revision', $ee($d['revision'])) ?>
    <?= $row('Owner / custodian', $ee($d['owner'])) ?>
    <?= $row('Approved by', $ee($d['approved_by'] . ($d['approved_on'] ? ' on ' . $d['approved_on'] : ''))) ?>
    <?= $row('Effective date', $ee($d['effective_date'])) ?>
    <?= $row('Review due', $ee($d['review_due'])) ?>
    <?= $row('Distribution', $ee($d['distribution'])) ?>
    <?= $row('Document', $d['file_name'] ? '<a href="/cdoc-file?id=' . (int)$d['id'] . '">' . $ee($d['file_name']) . '</a>' : '') ?>
    <?php if ($supersedes): ?><?= $row('Supersedes', '<a href="/cdoc?id=' . (int)$supersedes['id'] . '">' . $ee($supersedes['doc_code'] . ' ' . $supersedes['revision']) . '</a>') ?><?php endif; ?>
    <?php foreach (($cvals ?? []) as $lbl => $val): ?><?= $row($lbl, $ee($val)) ?><?php endforeach; ?>
    <?= $row('Description / purpose', nl2br($ee($d['description']))) ?>
  </table>
</div>

<?php if ($canEdit): ?>
<div class="grid two" style="margin-top:20px">
  <div class="card"><h4 style="margin-top:0">Status</h4>
    <form method="post" action="/cdoc-status">
      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <select class="form-control" name="status">
        <option value="CURRENT">Set Current (in force)</option>
        <option value="DRAFT">Back to Draft</option>
        <option value="WITHDRAWN">Withdraw</option>
      </select>
      <button class="btn small" type="submit" style="margin-top:10px">Update status</button>
    </form>
  </div>
  <div class="card"><h4 style="margin-top:0">Issue a new revision</h4>
    <p class="muted" style="font-size:13px;margin:0 0 8px">Creates a new draft revision and marks this one superseded. History is kept.</p>
    <form method="post" action="/cdoc-supersede">
      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <input class="form-control" name="revision" placeholder="New revision label (e.g. Rev 3)">
      <button class="btn small" type="submit" style="margin-top:10px">Create new revision</button>
    </form>
  </div>
</div>
<?php endif; ?>
