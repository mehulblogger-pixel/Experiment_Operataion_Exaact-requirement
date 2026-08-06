<?php $badge = ['DRAFT' => 'AMBER', 'CURRENT' => 'GREEN', 'SUPERSEDED' => 'GREY', 'WITHDRAWN' => 'RED']; ?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Controlled documents</div>
<div class="master-head">
  <div><h1>Controlled documents</h1>
    <p class="sub"><?= (int)($counts['current'] ?? 0) ?> current<?php if (!empty($counts['review_due'])): ?> · <span style="color:#b5751a"><?= (int)$counts['review_due'] ?> overdue for review</span><?php endif; ?> · policies, procedures &amp; forms under version control (ISO §8.3)</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/cdoc-new">+ Add a document</a><?php endif; ?>
</div>
<form method="get" action="/cdocs" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / title…">
  <input type="hidden" name="show" value="<?= $showAll ? 'all' : 'current' ?>">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($showAll): ?><a class="btn secondary" href="/cdocs">Current only</a>
  <?php else: ?><a class="btn secondary" href="/cdocs?show=all">Show all revisions</a><?php endif; ?>
</form>
<table class="grid">
  <tr><th>Code</th><th>Title</th><th>Type</th><th>Rev</th><th>Approved</th><th>Review due</th><th>Status</th></tr>
  <?php foreach ($rows as $r): $due = $r['review_due'] !== '' && $r['review_due'] < date('Y-m-d') && $r['status'] === 'CURRENT'; ?>
  <tr>
    <td><a href="/cdoc?id=<?= (int)$r['id'] ?>"><strong><?= e($r['doc_code']) ?></strong></a></td>
    <td><?= e($r['title']) ?></td>
    <td><?= e(cdoc_type_label($r['doc_type'])) ?></td>
    <td><?= e($r['revision'] ?: '—') ?></td>
    <td><?= e($r['approved_on'] ?: '—') ?></td>
    <td<?= $due ? ' style="color:#b5751a;font-weight:600"' : '' ?>><?= e($r['review_due'] ?: '—') ?></td>
    <td><span class="badge <?= $badge[$r['status']] ?? 'GREY' ?>"><?= e($r['status']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No controlled documents recorded.<?php if ($canEdit): ?> <a href="/cdoc-new">Add one</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
