<?php
// Users & roles — admin only.
require_can('users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post('do');
    if ($do === 'add') {
        $uname = post('username');
        $exists = db()->prepare("SELECT 1 FROM users WHERE username=?"); $exists->execute([$uname]);
        if (!$uname || $exists->fetch()) { flash('Username missing or already taken.', 'err'); }
        elseif (strlen(post('password')) < 6) { flash('Password must be at least 6 characters.', 'err'); }
        elseif (!seats_available()) {
            $ls = licence_status();
            flash($ls['expired'] ? 'Your licence has expired — renew it on the Billing screen before adding users.'
                                 : "You've reached your seat limit ({$ls['used']}/{$ls['limit']}). Add seats on the Billing screen, or disable a user to free a seat.", 'err');
        }
        else {
            db()->prepare("INSERT INTO users (name,email,username,pass_hash,role,active,created_at) VALUES (?,?,?,?,?,1,?)")
                ->execute([post('name'),post('email'),$uname,password_hash(post('password'),PASSWORD_DEFAULT),
                           array_key_exists(post('role'),ROLES())?post('role'):'viewer', now()]);
            flash('User added.');
        }
    } elseif ($do === 'toggle') {
        $uid = (int)post('uid');
        $cur = db()->prepare("SELECT active FROM users WHERE id=?"); $cur->execute([$uid]); $active=(int)$cur->fetchColumn();
        if ($uid === (int)current_user()['id']) flash("You can't deactivate yourself.", 'err');
        elseif ($active === 0 && !seats_available()) flash('No free seat to enable this user — check Billing.', 'err');
        else {
            db()->prepare("UPDATE users SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id=?")->execute([$uid]);
            flash('User updated.');
        }
    } elseif ($do === 'reset') {
        $uid = (int)post('uid');
        if (strlen(post('password')) < 6) flash('Password too short.', 'err');
        else { db()->prepare("UPDATE users SET pass_hash=? WHERE id=?")->execute([password_hash(post('password'),PASSWORD_DEFAULT),$uid]); flash('Password reset.'); }
    } elseif ($do === 'role') {
        $uid = (int)post('uid');
        if (array_key_exists(post('role'),ROLES()))
            db()->prepare("UPDATE users SET role=? WHERE id=?")->execute([post('role'),$uid]);
        flash('Role updated.');
    }
    redirect('?p=users');
}

$users = db()->query("SELECT * FROM users ORDER BY id")->fetchAll();
layout_top('Users');
?>
<div class="card">
  <h2 class="mt0">Add a user</h2>
  <form method="post">
    <input type="hidden" name="do" value="add"><?= csrf_field() ?>
    <div class="row3">
      <div><label>Full name</label><input name="name"></div>
      <div><label>Username *</label><input name="username" required></div>
      <div><label>Email</label><input name="email" type="email"></div>
    </div>
    <div class="row2">
      <div><label>Role</label><select name="role"><?php foreach (ROLES() as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div><label>Password (min 6)</label><input name="password" type="text" placeholder="set an initial password"></div>
    </div>
    <div style="margin-top:14px"><button class="btn">Add user</button></div>
  </form>
</div>

<div class="card">
  <h2>All users</h2>
  <table>
    <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th class="right">Actions</th></tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name'] ?: '—') ?><?= $u['email']?'<br><span class="muted">'.e($u['email']).'</span>':'' ?></td>
        <td><?= e($u['username']) ?></td>
        <td>
          <form method="post" style="display:inline-flex;gap:6px">
            <input type="hidden" name="do" value="role"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><?= csrf_field() ?>
            <select name="role" onchange="this.form.submit()">
              <?php foreach (ROLES() as $k=>$v): ?><option value="<?= $k ?>" <?= $u['role']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><?= $u['active']?'<span class="pill g">Active</span>':'<span class="pill r">Disabled</span>' ?></td>
        <td class="right">
          <form method="post" style="display:inline"><input type="hidden" name="do" value="toggle"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><?= csrf_field() ?><button class="btn ghost sm"><?= $u['active']?'Disable':'Enable' ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="margin-top:12px">Roles decide what each person can do — a Recruiter runs the pipeline, a Hiring Manager approves, an Interviewer only records feedback, a Viewer reads only.</p>
</div>
<?php layout_bottom();
