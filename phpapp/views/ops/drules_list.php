<?php $badge = ['DRAFT' => 'AMBER', 'CURRENT' => 'GREEN', 'SUPERSEDED' => 'GREY', 'WITHDRAWN' => 'RED']; ?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Decision rules</div>
<div class="master-head">
  <div><h1>Decision rules &amp; acceptance criteria</h1>
    <p class="sub"><?= (int)($counts['current'] ?? 0) ?> current · how accept / reject is decided, including measurement uncertainty (ISO §7.4)</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/drule-new">+ Add a rule</a><?php endif; ?>
</div>
<form method="get" action="/drules" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / title / characteristic…">
  <input type="hidden" name="show" value="<?= $showAll ? 'all' : 'current' ?>">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($showAll): ?><a class="btn secondary" href="/drules">Current only</a>
  <?php else: ?><a class="btn secondary" href="/drules?show=all">Show all revisions</a><?php endif; ?>
</form>
<table class="grid">
  <tr><th>Code</th><th>Title</th><th>Characteristic</th><th>Basis</th><th>Status</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="/drule?id=<?= (int)$r['id'] ?>"><strong><?= e($r['rule_code']) ?></strong></a></td>
    <td><?= e($r['title']) ?></td>
    <td><?= e($r['characteristic'] ?: '—') ?></td>
    <td><?= e(drule_basis_label($r['decision_basis'])) ?></td>
    <td><span class="badge <?= $badge[$r['status']] ?? 'GREY' ?>"><?= e($r['status']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted" style="padding:16px">No decision rules recorded.<?php if ($canEdit): ?> <a href="/drule-new">Add one</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
