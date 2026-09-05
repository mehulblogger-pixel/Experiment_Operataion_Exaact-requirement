<?php
// My Pending Tasks — the full role-aware worklist.
require_can('view');
$groups = tasks_groups();
$total = 0; foreach ($groups as $g) $total += $g['count'];
layout_top('My tasks');
?>
<div class="card">
  <h2 class="mt0">Pending actions <span class="pill <?= $total? 'a':'g' ?>" style="font-size:12px"><?= $total ?></span></h2>
  <?php if (!$groups): ?>
    <p class="muted mt0">🎉 Nothing pending for your role right now. You're all caught up.</p>
  <?php else: ?>
    <p class="muted mt0">These are the things waiting on you, based on your role. Click any item to act on it.</p>
  <?php endif; ?>
</div>

<?php foreach ($groups as $g): ?>
<div class="card">
  <h2><?= e($g['label']) ?> <span class="pill <?= e($g['tone']) ?>"><?= $g['count'] ?></span></h2>
  <table>
    <?php foreach ($g['items'] as $it): ?>
      <tr><td><a href="<?= e($it['link']) ?>"><?= e($it['text']) ?></a></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endforeach; ?>
<?php layout_bottom();
