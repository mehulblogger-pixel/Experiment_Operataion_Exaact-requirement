<?php
$statusBadge = function ($s) {
    $map = ['RECEIVED' => 'AMBER', 'IN_TESTING' => 'BLUE', 'HOLD' => 'AMBER',
            'RETURNED' => 'GREEN', 'DISPOSED' => 'GREY'];
    return $map[$s] ?? 'GREY';
};
?>
<div class="crumbs"><a href="/">Home</a> › Quality &amp; accreditation › Items &amp; samples</div>
<div class="master-head">
  <div><h1>Inspection items &amp; samples</h1>
    <p class="sub"><?= (int)($counts['open'] ?? 0) ?> open · what was received, its condition, where it is kept, and how it left (ISO §7.2)</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/sample-new">+ Receive an item</a><?php endif; ?>
</div>

<form method="get" action="/samples" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / description / maker ref…">
  <input type="hidden" name="show" value="<?= $showAll ? 'all' : 'open' ?>">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($showAll): ?><a class="btn secondary" href="/samples">Open only</a>
  <?php else: ?><a class="btn secondary" href="/samples?show=all">Show all</a><?php endif; ?>
</form>

<table class="grid">
  <tr><th>Code</th><th>Description</th><th>Type</th><th>Received</th><th>Condition</th><th>Storage</th><th>Status</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="/sample?id=<?= (int)$r['id'] ?>"><strong><?= e($r['item_code']) ?></strong></a></td>
    <td><?= e($r['description']) ?><?php if ($r['maker_ref']): ?><br><span class="muted" style="font-size:12px"><?= e($r['maker_ref']) ?></span><?php endif; ?></td>
    <td><?= e(sample_label('sample_type', $r['item_type'])) ?></td>
    <td><?= e($r['received_on'] ?: '—') ?></td>
    <td><?= e(sample_label('sample_condition', $r['condition_code'])) ?></td>
    <td><?= e(sample_label('sample_storage', $r['storage_code'])) ?></td>
    <td><span class="badge <?= $statusBadge($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No items recorded<?= $showAll ? '' : ' that are still open' ?>.<?php if ($canEdit): ?> <a href="/sample-new">Receive one</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
