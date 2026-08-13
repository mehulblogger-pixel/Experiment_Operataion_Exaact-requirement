<h2 class="ptitle">Alerts</h2>
<p class="plead">What has happened on your account — a report issued, a nonconformity raised, a response we have
  reviewed. Newest first; unread are highlighted.</p>

<?php if ($rows): ?>
<form method="post" action="/portal/alerts" style="margin:0 0 14px">
  <input type="hidden" name="read_all" value="1">
  <button class="btn secondary" type="submit">Mark all as read</button>
</form>
<?php endif; ?>

<?php if (!$rows): ?>
  <p class="pempty">Nothing yet.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th></th><th>What</th><th>When</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr style="<?= empty($r['is_read']) ? 'font-weight:600' : 'opacity:.7' ?>">
      <td><?php if (empty($r['is_read'])): ?><span class="badge AMBER" style="font-size:11px">new</span><?php endif; ?></td>
      <td><a href="<?= e($r['url'] ?: '/portal') ?>"><?= e($r['title']) ?></a>
        <div class="pnote" style="font-weight:400"><?= e($events[$r['event']] ?? $r['event']) ?></div></td>
      <td><?= e(fdate(substr((string)$r['created_at'], 0, 10))) ?></td>
      <td><?php if (empty($r['is_read'])): ?>
        <form method="post" action="/portal/alerts" style="display:inline">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm secondary" type="submit">Mark read</button>
        </form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
