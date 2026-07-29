<?php
  $curRole = $user ? (!empty($user['is_superuser'])?'MASTER_ADMIN':strtoupper($user['role'] ?? 'COORDINATOR')) : 'COORDINATOR';
  // Comma-separated when it comes from the database, already a list when a
  // refused save hands back what was typed. Accept either rather than trusting
  // the caller — getting this wrong takes the whole screen down.
  $permRaw  = $user['permissions'] ?? '';
  $curPerms = is_array($permRaw) ? $permRaw
            : (trim((string)$permRaw) !== '' ? explode(',', (string)$permRaw) : ($defaults['perms'] ?? []));
  $roleList = $globalMgr ? ORG_ROLES : ['OPERATION_MANAGER'=>ORG_ROLES['OPERATION_MANAGER'],'ASST_MANAGER'=>ORG_ROLES['ASST_MANAGER'],'COORDINATOR'=>ORG_ROLES['COORDINATOR'],'INSPECTOR'=>ORG_ROLES['INSPECTOR']];
  $allowPerms = $globalMgr ? all_permissions() : array_intersect_key(all_permissions(), array_flip([
    'mod.calls.view','mod.calls.edit','mod.jobs.view','mod.jobs.edit','mod.vouchers.view','mod.vouchers.edit',
    'mod.hiring.view','mod.hiring.edit','mod.reconcile.view','mod.reconcile.edit','mod.clients.view','mod.vendors.view',
    'mod.masters.view','mod.masters.edit','mod.reports.view','mod.invoicing.view',
    'dash.operations','dash.utilization','data.credit','ops.call.create','ops.job.allocate','ops.job.close','master.manage']));
  $asCsv = function ($v) { return is_array($v) ? implode(',', $v) : trim((string)$v); };
  $scOff = $asCsv($user['scope_offices'] ?? '');
  $scSbu = $asCsv($user['scope_sbus'] ?? '');
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
    <?php // The same offices the Organisation screen owns — one table, so one
          // added here shows up there and in every other dropdown at once. And
          // it can be added here, because being sent to another screen halfway
          // through this form loses everything already typed. ?>
    <div class="ff"><label>Home <?= e(Tl('office')) ?></label>
      <select class="form-control searchable" name="home_office_id" id="u_home_off" <?= $globalMgr?'':'disabled' ?>><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (($user['home_office_id'] ?? '')==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
        <?php if ($globalMgr): ?><option value="__new__">+ Add an <?= e(Tl('office')) ?> not on this list…</option><?php endif; ?>
      </select><?php if (!$globalMgr): ?><small class="muted">Fixed to your <?= e(Tl('office')) ?>.</small><?php endif; ?></div>
    <?php if ($globalMgr): ?>
    <div class="ff ff-wide" id="u_home_off_new" style="display:none">
      <div class="panel" style="margin:0;padding:12px;background:var(--field)">
        <b style="font-size:13px">New <?= e(Tl('office')) ?></b>
        <div class="muted" style="font-size:12.5px;margin:2px 0 8px">Added to the one <?= e(Tl('office')) ?> list, so it appears
          under <a href="/hierarchy?tab=offices">Organisation &amp; people</a> and in every other dropdown straight away.</div>
        <div class="form-grid" style="margin:0">
          <div class="ff"><label>Name *</label><input class="form-control" name="new_office_name" placeholder="e.g. Surat"></div>
          <div class="ff"><label>Code</label><input class="form-control" name="new_office_code" placeholder="e.g. SUR"></div>
          <div class="ff"><label>City</label><input class="form-control" name="new_office_city"></div>
          <div class="ff"><label>Type</label>
            <select class="form-control" name="new_office_type">
              <?php foreach (OFFICE_TYPES as $ok => $ol): ?>
                <option value="<?= e($ok) ?>"<?= $ok === 'BRANCH' ? ' selected' : '' ?>><?= e($ol) ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
      </div>
    </div>
    <script>
      (function () {
        var s = document.getElementById('u_home_off'), box = document.getElementById('u_home_off_new');
        if (!s || !box) return;
        function sync() {
          var isNew = s.value === '__new__';
          box.style.display = isNew ? '' : 'none';
          var n = box.querySelector('input[name=new_office_name]');
          if (isNew && n) n.focus();
        }
        s.addEventListener('change', sync); sync();
      })();
    </script>
    <?php endif; ?>
    <div class="ff"><label>Linked <?= e(Tl('engineer')) ?> (Inspector role)</label>
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

    <?php // 5.5 days is five full days plus one half day. It was mislabelled
          // "alternate Sat off", which is a different arrangement altogether and
          // not one used here. The half day is a real half day, and how long it
          // is gets typed rather than assumed to be half of a full one. ?>
    <?php $wwdCur = rtrim(rtrim((string)($user['weekly_working_days'] ?? '6'), '0'), '.') ?: '6'; ?>
    <div class="ff"><label>Working days a week</label>
      <select class="form-control" name="weekly_working_days" id="u_wwd">
        <?php foreach (['6'=>'6 days (Mon–Sat)', '5.5'=>'5.5 days (5 full + 1 half day)', '5'=>'5 days (Mon–Fri)'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $wwdCur === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Working hours a full day</label>
      <input class="form-control" type="number" step="0.25" min="1" max="16" name="daily_hours"
             value="<?= e((string)($user['daily_hours'] ?? '') !== '' ? $user['daily_hours'] : setting_get('daily_hours_cap', '8.5')) ?>">
      <small class="muted">Set per person. Blank falls back to the company figure in Settings
        (<?= e(setting_get('daily_hours_cap', '8.5')) ?> h). This is what utilisation and the daily cap are measured against.</small></div>
    <div class="ff" id="u_half_wrap" style="<?= $wwdCur === '5.5' ? '' : 'display:none' ?>">
      <label>Hours on the half day</label>
      <input class="form-control" type="number" step="0.25" min="0.5" max="12" name="half_day_hours"
             value="<?= e((string)($user['half_day_hours'] ?? '') !== '' ? $user['half_day_hours'] : setting_get('half_day_hours', '4')) ?>">
      <small class="muted">Not simply half of a full day — a half day here is
        <?= e(setting_get('half_day_hours', '4')) ?> hours by default. Change it per person if theirs differs.</small></div>
    <script>
      // The half-day box only means anything on a 5.5-day week.
      (function () {
        var w = document.getElementById('u_wwd'), h = document.getElementById('u_half_wrap');
        if (!w || !h) return;
        function sync() { h.style.display = w.value === '5.5' ? '' : 'none'; }
        w.addEventListener('change', sync); sync();
      })();
    </script>

    <?php // ---- What this person costs, and where that cost belongs -------
          // Two different kinds of people. An inspection engineer's cost follows
          // the work they did, day by day, and needs no percentages. Everybody
          // else has to say where their cost belongs, because no single job
          // caused it. That is the whole difference, so it is one tick box. ?>
    <?php if ($canSalary): ?>
    <div class="ff-wide" style="border-top:1px solid var(--line);padding-top:14px;margin-top:6px">
      <h3 class="tab-sub" style="margin-top:0">Cost &amp; where it belongs</h3>
      <p class="sub">Used by the month-end cost run to work out profit by <?= e(Tl('sbu')) ?>, activity code and <?= e(T('boss')) ?> number. Leave the salary blank and this person is simply left out of the calculation.</p>
    </div>
    <div class="ff"><label>Cost to the company, per month (₹)</label>
      <input class="form-control" type="number" step="0.01" min="0" name="monthly_ctc" id="u_ctc"
             value="<?= e((string)($user['monthly_ctc'] ?? '')) ?>">
      <small class="muted">Everything the company pays for this person in a month, not just the take-home.</small></div>
    <div class="ff ff-check">
      <label><input type="checkbox" name="is_production" id="u_prod" value="1"
        <?= !empty($user['is_production']) ? 'checked' : '' ?>>
        This person does inspections (production staff)</label>
      <small class="muted">Tick it and their cost follows the days they worked. Leave it and you set the split below.</small></div>

    <div class="ff-wide" id="u_split_wrap" style="<?= !empty($user['is_production']) ? 'display:none' : '' ?>">
      <label><?= e(TP('sbu')) ?> split for <?= e(date('F Y')) ?>
        <span class="muted">— where this person's cost belongs</span></label>
      <?php if ($splitCarried && $splitNow): ?>
        <div class="msg-info" style="margin:6px 0">Carried forward from the last month it was set. Save to make it this month's own figure, or change it first.</div>
      <?php endif; ?>
      <div class="checkgrid">
        <?php foreach ($splitSbus as $code => $label): ?>
          <div class="ff" style="margin:0">
            <label style="font-weight:normal"><?= e($label) ?></label>
            <input class="form-control split-pct" type="number" step="0.01" min="0" max="100"
                   name="sbu_pct[<?= e($code) ?>]" value="<?= e((string)($splitNow[$code] ?? '')) ?>" placeholder="0">
          </div>
        <?php endforeach; ?>
      </div>
      <p class="muted" style="margin-top:6px">Total: <strong id="u_split_total">0</strong>% — it has to come to 100.
        Equal across all of them is fine; so is 40/40/10/10. Set it fresh each month; if you do not, last month's carries forward and past months are never changed.</p>
    </div>
    <script>
      (function () {
        var prod = document.getElementById('u_prod'), wrap = document.getElementById('u_split_wrap');
        if (prod && wrap) {
          var sync = function () { wrap.style.display = prod.checked ? 'none' : ''; };
          prod.addEventListener('change', sync); sync();
        }
        var boxes = document.querySelectorAll('.split-pct'), out = document.getElementById('u_split_total');
        if (!boxes.length || !out) return;
        var tot = function () {
          var t = 0;
          Array.prototype.forEach.call(boxes, function (b) { t += parseFloat(b.value) || 0; });
          out.textContent = Math.round(t * 100) / 100;
          out.style.color = (t === 0 || Math.abs(t - 100) < 0.01) ? '' : '#b91c1c';
        };
        Array.prototype.forEach.call(boxes, function (b) { b.addEventListener('input', tot); });
        tot();
      })();
    </script>
    <?php endif; ?>

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
