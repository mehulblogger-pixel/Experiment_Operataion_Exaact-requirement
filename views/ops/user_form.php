<?php
  $curRole = $user ? (!empty($user['is_superuser'])?'MASTER_ADMIN':strtoupper($user['role'] ?? 'COORDINATOR')) : 'COORDINATOR';
  $curPerms = ($user && trim((string)($user['permissions'] ?? '')) !== '') ? explode(',', $user['permissions']) : ($defaults['perms'] ?? []);
  $roleList = $globalMgr ? ORG_ROLES : ['OPERATION_MANAGER'=>ORG_ROLES['OPERATION_MANAGER'],'ASST_MANAGER'=>ORG_ROLES['ASST_MANAGER'],'COORDINATOR'=>ORG_ROLES['COORDINATOR'],'INSPECTOR'=>ORG_ROLES['INSPECTOR']];
  $allowPerms = $globalMgr ? all_permissions() : array_intersect_key(all_permissions(), array_flip([
    'mod.calls.view','mod.calls.edit','mod.jobs.view','mod.jobs.edit','mod.vouchers.view','mod.vouchers.edit',
    'mod.hiring.view','mod.hiring.edit','mod.reconcile.view','mod.reconcile.edit','mod.clients.view','mod.vendors.view',
    'mod.masters.view','mod.masters.edit','mod.reports.view','mod.invoicing.view',
    'dash.operations','dash.utilization','data.credit','ops.call.create','ops.job.allocate','ops.job.close','master.manage']));
  $scOff = trim((string)($user['scope_offices'] ?? ''));
  $scSbu = trim((string)($user['scope_sbus'] ?? ''));
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/users">Users</a> › <?= $user ? 'Edit' : 'Add' ?></div>
<h1><?= $user ? 'Edit user' : 'Add user' ?></h1>
<form method="post" action="<?= $user ? '/user-edit?id=' . (int)$user['id'] : '/user-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Username *</label><input class="form-control" name="username" required value="<?= e($user['username'] ?? '') ?>"></div>
    <div class="ff"><label>Password <?= $user ? '(blank = keep)' : '' ?></label><input class="form-control" type="text" name="password" placeholder="<?= $user ? '••••••' : 'set a password' ?>"></div>
    <div class="ff"><label>First name</label><input class="form-control" name="first_name" value="<?= e($user['first_name'] ?? '') ?>"></div>
    <div class="ff"><label>Last name</label><input class="form-control" name="last_name" value="<?= e($user['last_name'] ?? '') ?>"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email" value="<?= e($user['email'] ?? '') ?>"></div>
    <div class="ff"><label>Role</label>
      <select class="form-control searchable" name="role"><?php foreach ($roleList as $k=>$v): ?><option value="<?= $k ?>" <?= $curRole===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Home office</label>
      <select class="form-control searchable" name="home_office_id" <?= $globalMgr?'':'disabled' ?>><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (($user['home_office_id'] ?? '')==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select><?php if (!$globalMgr): ?><small class="muted">Fixed to your office.</small><?php endif; ?></div>
    <div class="ff"><label>Linked inspector (Inspector role)</label>
      <select class="form-control searchable" name="inspector_id"><option value="">—</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ($user && $user['inspector_id']==$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-check"><input type="checkbox" name="is_active" <?= (!$user || $user['is_active'])?'checked':'' ?>><label>Active</label></div>
  </div>

  <?php if ($globalMgr): ?>
  <h3 class="tab-sub">Data scope</h3>
  <div class="form-grid">
    <div class="ff"><label>Offices this user can see</label><input class="form-control" name="scope_offices" value="<?= e($scOff) ?>" placeholder="ALL, or comma-separated office ids (blank = own office)"></div>
    <div class="ff"><label>SBUs this user can see</label><input class="form-control" name="scope_sbus" value="<?= e($scSbu) ?>" placeholder="ALL, or comma-separated SBU codes (blank = all)"></div>
  </div>
  <p class="muted" style="margin:4px 2px 0;">Tip: <code>ALL</code> = everything. For an SBU Head, set Offices=<code>ALL</code> and SBUs=<code>IND</code> (their SBU). For a Branch Manager, leave Offices blank (their home office) and SBUs=<code>ALL</code>.</p>
  <?php endif; ?>

  <h3 class="tab-sub">Permissions <span class="muted">— leave all unticked to use the role's defaults</span></h3>
  <div class="checkgrid">
    <?php foreach ($allowPerms as $k=>$v): ?>
      <label class="chk"><input type="checkbox" name="permissions[]" value="<?= e($k) ?>" <?= in_array($k, $curPerms, true)?'checked':'' ?>> <?= e($v) ?></label>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save user</button>
    <a class="btn secondary" href="/users">Cancel</a>
  </div>
</form>
