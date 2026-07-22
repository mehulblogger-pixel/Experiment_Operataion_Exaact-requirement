<div class="master-head">
  <div><h1>Users &amp; roles</h1>
    <p class="sub"><?= (int)$active ?> active user(s)<?= $seats!=='' ? ' · seat limit ' . e($seats) : '' ?></p></div>
  <a class="btn" href="/user-new">+ Add user</a>
</div>
<?php if ($seats!=='' && $active >= (int)$seats): ?>
  <div class="msg msg-error">You have reached your licensed seat limit (<?= e($seats) ?>). Deactivate a user before adding a new one.</div>
<?php endif; ?>
<table class="grid">
  <tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Actions</th></tr>
  <?php foreach ($rows as $u): ?>
  <tr>
    <td><?= e($u['username']) ?></td>
    <td><?= e(trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: '—') ?></td>
    <td><?= e($u['email'] ?: '—') ?></td>
    <td><?= e(ORG_ROLES[!empty($u['is_superuser'])?'MASTER_ADMIN':strtoupper($u['role'] ?? 'ADMIN')] ?? $u['role']) ?></td>
    <td><?= $u['is_active'] ? '<span class="badge GREEN">Yes</span>' : '<span class="badge RED">No</span>' ?></td>
    <td class="row-actions"><a class="btn small" href="/user-edit?id=<?= (int)$u['id'] ?>">Edit</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="muted" style="margin-top:12px;">Roles: <strong>Master Admin</strong> sees salary &amp; profit; <strong>Admin</strong> runs scheduling &amp; recon; <strong>Coordinator</strong> creates calls/jobs; <strong>Inspector</strong> sees only their own jobs. The seat limit is set on the server with the <code>SEAT_LIMIT</code> setting.</p>
