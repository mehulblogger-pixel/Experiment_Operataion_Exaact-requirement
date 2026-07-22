<h1><?= $user ? 'Edit user' : 'Add user' ?></h1>
<form method="post" action="<?= $user ? '/user-edit?id=' . (int)$user['id'] : '/user-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Username *</label><input class="form-control" name="username" required value="<?= e($user['username'] ?? '') ?>"></div>
    <div class="ff"><label>Password <?= $user ? '(leave blank to keep)' : '' ?></label><input class="form-control" type="text" name="password" placeholder="<?= $user ? '••••••' : 'set a password' ?>"></div>
    <div class="ff"><label>First name</label><input class="form-control" name="first_name" value="<?= e($user['first_name'] ?? '') ?>"></div>
    <div class="ff"><label>Last name</label><input class="form-control" name="last_name" value="<?= e($user['last_name'] ?? '') ?>"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email" value="<?= e($user['email'] ?? '') ?>"></div>
    <div class="ff"><label>Role</label>
      <select class="form-control" name="role">
        <?php $cur = $user ? (!empty($user['is_superuser'])?'MASTER_ADMIN':strtoupper($user['role'] ?? 'COORDINATOR')) : 'COORDINATOR';
        foreach (OPS_ROLES as $k=>$v): ?><option value="<?= $k ?>" <?= $cur===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Linked inspector (for Inspector role)</label>
      <select class="form-control searchable" name="inspector_id"><option value="">—</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ($user && $user['inspector_id']==$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-check"><input type="checkbox" name="is_active" <?= (!$user || $user['is_active'])?'checked':'' ?>><label>Active</label></div>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save user</button>
    <a class="btn secondary" href="/users">Cancel</a>
  </div>
</form>
