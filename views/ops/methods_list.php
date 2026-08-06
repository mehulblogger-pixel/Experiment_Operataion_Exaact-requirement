<?php
$badge = ['DRAFT' => 'AMBER', 'CURRENT' => 'GREEN', 'SUPERSEDED' => 'GREY', 'WITHDRAWN' => 'RED'];
?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Method library</div>
<div class="master-head">
  <div><h1>Inspection methods &amp; standards</h1>
    <p class="sub"><?= (int)($counts['current'] ?? 0) ?> current<?php if (!empty($counts['unvalidated'])): ?> · <span style="color:#b5751a"><?= (int)$counts['unvalidated'] ?> not yet validated</span><?php endif; ?> · controlled revisions (ISO §7.1)</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/method-new">+ Add a method</a><?php endif; ?>
</div>

<form method="get" action="/methods" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / title / standard…">
  <input type="hidden" name="show" value="<?= $showAll ? 'all' : 'current' ?>">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($showAll): ?><a class="btn secondary" href="/methods">Current only</a>
  <?php else: ?><a class="btn secondary" href="/methods?show=all">Show all revisions</a><?php endif; ?>
</form>

<table class="grid">
  <tr><th>Code</th><th>Title</th><th>Standard</th><th>Rev</th><th>Category</th><th>Status</th><th>Validated</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="/method?id=<?= (int)$r['id'] ?>"><strong><?= e($r['method_code']) ?></strong></a></td>
    <td><?= e($r['title']) ?></td>
    <td><?= e($r['standard_ref'] ?: '—') ?></td>
    <td><?= e($r['revision'] ?: '—') ?></td>
    <td><?= e(method_label('method_category', $r['category'])) ?></td>
    <td><span class="badge <?= $badge[$r['status']] ?? 'GREY' ?>"><?= e($r['status']) ?></span></td>
    <td><?php if ((int)$r['is_validated'] === 1): ?><span class="badge GREEN">Yes</span><?php else: ?><span class="badge AMBER">No</span><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No methods recorded.<?php if ($canEdit): ?> <a href="/method-new">Add one</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
