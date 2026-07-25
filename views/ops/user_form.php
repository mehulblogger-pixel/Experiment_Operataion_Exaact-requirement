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

  <h3 class="tab-sub">Reporting &amp; position <span class="muted">— builds the organisation hierarchy (N+1) and drives approvals</span></h3>
  <div class="form-grid">
    <div class="ff"><label>Position / designation</label><input class="form-control" name="position_title" value="<?= e($user['position_title'] ?? '') ?>" placeholder="e.g. Sr. Coordinator"></div>
    <div class="ff"><label>Weekly working days</label>
      <select class="form-control" name="weekly_working_days"><?php foreach (['6'=>'6 days (Mon–Sat)','5.5'=>'5.5 days (alternate Sat off)','5'=>'5 days (Mon–Fri)'] as $k=>$v): ?><option value="<?= $k ?>" <?= ((string)($user['weekly_working_days'] ?? '6')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Reports to <span class="muted">— pick a system user</span></label>
      <select class="form-control searchable" name="reports_to_id"><option value="">— none / top of chain —</option>
        <?php foreach (($managers ?? []) as $m): $nm=trim(($m['first_name']??'').' '.($m['last_name']??'')) ?: $m['username']; ?><option value="<?= (int)$m['id'] ?>" <?= ((int)($user['reports_to_id'] ?? 0)===(int)$m['id'])?'selected':'' ?>><?= e($nm) ?> · <?= e(ORG_ROLES[$m['role']] ?? $m['role']) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <p class="muted" style="margin:4px 2px 6px;">If the reporting manager has no login, leave "Reports to" blank and fill their details below — they still appear in the hierarchy and receive approval e-mails.</p>
  <div class="form-grid">
    <div class="ff"><label>Manager name <span class="muted">(if no login)</span></label><input class="form-control" name="reports_to_name" value="<?= e($user['reports_to_name'] ?? '') ?>"></div>
    <div class="ff"><label>Manager position</label><input class="form-control" name="reports_to_position" value="<?= e($user['reports_to_position'] ?? '') ?>"></div>
    <div class="ff"><label>Manager e-mail</label><input class="form-control" type="email" name="reports_to_email" value="<?= e($user['reports_to_email'] ?? '') ?>"></div>
  </div>

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
