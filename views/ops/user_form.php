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
<div class="crumbs"><a href="/">Home</a> › <a href="/hierarchy">Organisation &amp; people</a> ›
  <a href="/users">Login accounts</a> › <?= $user ? 'Edit' : 'Add' ?></div>
<div class="master-head">
  <div style="display:flex;align-items:flex-start;gap:12px">
    <a class="btn secondary" href="/users" title="Back" style="margin-top:2px">← Back</a>
    <div><h1><?= $user ? 'Edit ' . e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username']) : 'Add a person' ?></h1>
      <p class="sub" style="margin:2px 0 0">One person: who they are, what they can see, where they sit and who they report to.</p></div>
  </div>
</div>
<form method="post" action="<?= $user ? '/user-edit?id=' . (int)$user['id'] : '/user-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Username *</label><input class="form-control" name="username" required value="<?= e($user['username'] ?? '') ?>"></div>
    <div class="ff"><label>Password <?= $user ? '(blank = keep)' : '' ?></label>
      <input class="form-control" type="text" name="password" placeholder="<?= $user ? '••••••' : 'set a password' ?>">
      <span class="muted" style="font-size:11.5px">At least <?= (int)pwd_min_len() ?> characters, with a letter and a number, and not their own name.</span></div>
    <?php // Whoever types a password here knows it. This makes the person replace
          // it at their first sign-in, so it stops being a password two people share. ?>
    <div class="ff ff-check"><label><input type="checkbox" name="must_change_pwd" value="1"
        <?= !empty($user['must_change_pwd']) ? 'checked' : '' ?>> They must choose their own at the next sign-in</label></div>
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
  <?php // These were two free-text boxes asking for "comma-separated office ids".
        // Nobody knows an office id, and a typo silently gave somebody the wrong
        // data. Both are now tick-lists built from the same masters the rest of
        // the app reads, so what you can pick is what exists. ?>
  <?php $offSel = array_filter(array_map('trim', explode(',', $scOff)));
        $sbuSel = array_filter(array_map('trim', explode(',', $scSbu)));
        $offAll = in_array('ALL', array_map('strtoupper', $offSel), true);
        $sbuAll = $scSbu === '' || in_array('ALL', array_map('strtoupper', $sbuSel), true); ?>
  <div class="form-grid">
    <div class="ff ff-wide"><label><?= e(THP('office')) ?> this person can see
        <span class="muted">— tick as many as apply; none ticked = their home <?= e(Tl('office')) ?> only</span></label>
      <label class="ff-check" style="margin-bottom:6px"><input type="checkbox" name="scope_offices_all" value="1"
        id="sc_off_all" <?= $offAll ? 'checked' : '' ?>> <b>Every <?= e(Tl('office')) ?></b> — now and any added later</label>
      <div class="chip-row" id="sc_off_list" style="<?= $offAll ? 'opacity:.45;pointer-events:none' : '' ?>">
        <?php foreach ($offices as $o): ?>
          <label class="ff-check"><input type="checkbox" name="scope_offices[]" value="<?= (int)$o['id'] ?>"
            <?= in_array((string)$o['id'], $offSel, true) ? 'checked' : '' ?>> <?= e($o['name']) ?></label>
        <?php endforeach; ?>
      </div></div>

    <div class="ff ff-wide"><label><?= e(THP('sbu')) ?> this person can see
        <span class="muted">— from the <?= e(Tl('sbu')) ?> master</span></label>
      <label class="ff-check" style="margin-bottom:6px"><input type="checkbox" name="scope_sbus_all" value="1"
        id="sc_sbu_all" <?= $sbuAll ? 'checked' : '' ?>> <b>Every <?= e(Tl('sbu')) ?></b></label>
      <div class="chip-row" id="sc_sbu_list" style="<?= $sbuAll ? 'opacity:.45;pointer-events:none' : '' ?>">
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $code => $lbl): ?>
          <label class="ff-check"><input type="checkbox" name="scope_sbus[]" value="<?= e($code) ?>"
            <?= in_array((string)$code, $sbuSel, true) ? 'checked' : '' ?>> <?= e($lbl) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Missing one? Add it to the <a href="/m/sbu"><?= e(Tl('sbu')) ?> master</a> and it appears here.</small></div>
  </div>
  <script>
    // "Every office" and the individual ticks contradict each other, so one
    // greys the other out rather than letting both be set and guessing later.
    (function () {
      [['sc_off_all','sc_off_list'],['sc_sbu_all','sc_sbu_list']].forEach(function (p) {
        var a = document.getElementById(p[0]), l = document.getElementById(p[1]);
        if (!a || !l) return;
        a.addEventListener('change', function () {
          l.style.opacity = a.checked ? '.45' : '';
          l.style.pointerEvents = a.checked ? 'none' : '';
        });
      });
    })();
  </script>
  <?php endif; ?>

  <h3 class="tab-sub">Reporting &amp; position <span class="muted">— builds the organisation hierarchy (N+1) and drives approvals</span></h3>
  <div class="form-grid">
    <?php // Designation comes from the master so everybody's card reads the same
          // way and reports can group by it. A title genuinely not on the list can
          // be added here, and it goes into the master — not just onto this row. ?>
    <?php $desigOpts = lk_options_or('designation', DESIGNATIONS);
          $curDesig = trim((string)($user['position_title'] ?? ''));
          $known = in_array($curDesig, $desigOpts, true) || isset($desigOpts[$curDesig]); ?>
    <div class="ff"><label>Position / designation <span class="muted">— from the master</span></label>
      <select class="form-control searchable" name="position_title" id="u_desig">
        <option value="">— not set —</option>
        <?php foreach ($desigOpts as $code => $lbl): ?>
          <option value="<?= e($lbl) ?>" <?= $curDesig === $lbl ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
        <?php if ($curDesig !== '' && !$known): ?>
          <option value="<?= e($curDesig) ?>" selected><?= e($curDesig) ?> (not on the master)</option>
        <?php endif; ?>
        <option value="__new__">+ Add a designation not on this list…</option>
      </select>
      <input class="form-control" name="position_title_new" id="u_desig_new" style="display:none;margin-top:6px"
             placeholder="Type the new designation — it is added to the master">
      <small class="muted"><a href="/m/designation">Manage the designation master</a></small></div>

    <?php // The old choice was 6 / 5.5 / 5 days, where 5.5 meant "alternate
          // Saturday off". There is no alternate Saturday here, so it is gone,
          // and the hours a day are typed rather than assumed. ?>
    <div class="ff"><label>Working days a week</label>
      <select class="form-control" name="weekly_working_days">
        <?php foreach (['6'=>'6 days (Mon–Sat)', '5'=>'5 days (Mon–Fri)'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= ((string)($user['weekly_working_days'] ?? '6') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Working hours a day</label>
      <input class="form-control" type="number" step="0.25" min="1" max="16" name="daily_hours"
             value="<?= e((string)($user['daily_hours'] ?? '') !== '' ? $user['daily_hours'] : setting_get('daily_hours_cap', '8.5')) ?>">
      <small class="muted">Set per person. Blank falls back to the company figure in Settings
        (<?= e(setting_get('daily_hours_cap', '8.5')) ?> h). This is what utilisation and the daily cap are measured against.</small></div>

    <?php // Manager is picked from the people already on file. Their e-mail and
          // position come with them, so the same manager cannot end up recorded
          // three different ways on three different rows. ?>
    <div class="ff ff-wide"><label>Reports to <span class="muted">— pick from the people already on file</span></label>
      <select class="form-control searchable" name="reports_to_id" id="u_mgr">
        <option value="">— nobody / top of the chain —</option>
        <?php foreach (($managers ?? []) as $m): $nm = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['username']; ?>
          <option value="<?= (int)$m['id'] ?>" data-name="<?= e($nm) ?>" data-email="<?= e($m['email'] ?? '') ?>"
                  data-pos="<?= e($m['position_title'] ?? (ORG_ROLES[$m['role']] ?? $m['role'])) ?>"
                  <?= ((int)($user['reports_to_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>>
            <?= e($nm) ?> · <?= e($m['position_title'] ?: (ORG_ROLES[$m['role']] ?? $m['role'])) ?><?= $m['email'] ? ' · ' . e($m['email']) : '' ?></option>
        <?php endforeach; ?>
        <option value="__new__">+ The manager is not on this list — add them…</option>
      </select></div>
  </div>

  <div class="form-grid" id="u_mgr_manual" style="<?= (int)($user['reports_to_id'] ?? 0) ? 'display:none' : '' ?>">
    <p class="muted ff-wide" style="margin:0 0 4px">A manager with no login of their own: they still appear on the chart
      and still receive approval e-mails. Saving this also adds them to the people list, so next time they are in the dropdown.</p>
    <div class="ff"><label>Manager name</label><input class="form-control" name="reports_to_name" id="u_mgr_name" value="<?= e($user['reports_to_name'] ?? '') ?>"></div>
    <div class="ff"><label>Manager position</label><input class="form-control" name="reports_to_position" id="u_mgr_pos" value="<?= e($user['reports_to_position'] ?? '') ?>"></div>
    <div class="ff"><label>Manager e-mail</label><input class="form-control" type="email" name="reports_to_email" id="u_mgr_email" value="<?= e($user['reports_to_email'] ?? '') ?>"></div>
  </div>
  <script>
    (function () {
      var d = document.getElementById('u_desig'), dn = document.getElementById('u_desig_new');
      if (d) d.addEventListener('change', function () {
        var isNew = d.value === '__new__';
        dn.style.display = isNew ? '' : 'none';
        if (isNew) dn.focus();
      });
      var m = document.getElementById('u_mgr'), box = document.getElementById('u_mgr_manual');
      function fill() {
        var o = m.options[m.selectedIndex], isNew = m.value === '__new__', picked = m.value && !isNew;
        box.style.display = picked ? 'none' : '';
        if (picked) {   // carry the manager's own details across, so one person is recorded one way
          document.getElementById('u_mgr_name').value  = o.getAttribute('data-name') || '';
          document.getElementById('u_mgr_email').value = o.getAttribute('data-email') || '';
          document.getElementById('u_mgr_pos').value   = o.getAttribute('data-pos') || '';
        } else if (isNew) {
          ['u_mgr_name','u_mgr_email','u_mgr_pos'].forEach(function (id) { document.getElementById(id).value = ''; });
          document.getElementById('u_mgr_name').focus();
        }
      }
      if (m) m.addEventListener('change', fill);
    })();
  </script>

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
