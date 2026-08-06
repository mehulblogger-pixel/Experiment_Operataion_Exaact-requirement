<?php
$rbadge = ['LOW' => 'GREEN', 'MEDIUM' => 'AMBER', 'HIGH' => 'RED', 'CRITICAL' => 'RED'];
$sbadge = ['OPEN' => 'AMBER', 'TREATING' => 'BLUE', 'MONITORING' => 'BLUE', 'CLOSED' => 'GREEN'];
?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Risks &amp; opportunities</div>
<div class="master-head">
  <div><h1>Risks &amp; opportunities</h1>
    <p class="sub"><?= (int)($counts['open'] ?? 0) ?> open<?php if (!empty($counts['high'])): ?> · <span style="color:#b3402f"><?= (int)$counts['high'] ?> high / critical</span><?php endif; ?> · actions to address risk (ISO §8.5)</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/risk-new">+ Add an entry</a><?php endif; ?>
</div>
<form method="get" action="/risks" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / title / area…">
  <input type="hidden" name="show" value="<?= $showAll ? 'all' : 'open' ?>">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($showAll): ?><a class="btn secondary" href="/risks">Open only</a>
  <?php else: ?><a class="btn secondary" href="/risks?show=all">Show all</a><?php endif; ?>
</form>
<table class="grid">
  <tr><th>Code</th><th>Title</th><th>Type</th><th>Category</th><th>Rating</th><th>Owner</th><th>Status</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="/risk?id=<?= (int)$r['id'] ?>"><strong><?= e($r['risk_code']) ?></strong></a></td>
    <td><?= e($r['title']) ?></td>
    <td><span class="badge <?= $r['kind'] === 'OPPORTUNITY' ? 'GREEN' : 'GREY' ?>"><?= e(ucfirst(strtolower($r['kind']))) ?></span></td>
    <td><?= e(risk_cat_label($r['category'])) ?></td>
    <td><?php if ($r['rating']): ?><span class="badge <?= $rbadge[$r['rating']] ?? 'GREY' ?>"><?= e($r['rating']) ?></span><?php else: ?>—<?php endif; ?></td>
    <td><?= e($r['owner'] ?: '—') ?></td>
    <td><span class="badge <?= $sbadge[$r['status']] ?? 'GREY' ?>"><?= e(ucfirst(strtolower($r['status']))) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">Nothing recorded<?= $showAll ? '' : ' that is still open' ?>.<?php if ($canEdit): ?> <a href="/risk-new">Add an entry</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
