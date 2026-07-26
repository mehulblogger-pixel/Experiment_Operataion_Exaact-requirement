<?php
  // ==========================================================================
  //  Organisation — one screen, five views of the same data.
  //
  //  Chart    the reporting tree everyone recognises
  //  Offices  the office structure, nested, edited in place
  //  People   every person's role / office / manager in one editable grid
  //  Excel    download the register, edit it in Excel, upload it back
  //  Gaps     what is missing before the chart can be trusted
  //
  //  They all read and write the SAME two tables, which is what makes this the
  //  single source of truth rather than a picture of one.
  // ==========================================================================
  function org_node_html($n, $all, $canEdit, $depth = 0) {
    $nm  = trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? '')) ?: ($n['username'] ?? '—');
    $pos = $n['position_title'] ?: (ORG_ROLES[$n['role']] ?? $n['role']);
    $kids = $n['children'] ?? [];
    $init = mb_strtoupper(mb_substr($nm, 0, 1));
    echo '<li>';
    echo '<div class="oc-node">';
    echo   '<div class="oc-card' . ($depth === 0 ? ' oc-top' : '') . '">';
    echo     '<div class="oc-head"><span class="oc-av">' . e($init) . '</span>';
    echo       '<div class="oc-id"><b>' . e($nm) . '</b><span class="oc-role">' . e($pos) . '</span></div></div>';
    if (!empty($n['email'])) echo '<div class="oc-mail">' . e($n['email']) . '</div>';
    if ($kids) {
      echo '<div class="oc-meta"><span class="pill p-info">' . (int)$n['direct_n'] . ' direct</span>';
      if ((int)$n['total_n'] > (int)$n['direct_n']) echo ' <span class="pill p-mut">' . (int)$n['total_n'] . ' total</span>';
      echo '</div>';
    }
    if ($canEdit) {
      echo '<div class="oc-act">';
      echo   '<a href="/user-edit?id=' . (int)$n['id'] . '">edit</a>';
      echo   '<form method="post" action="/hierarchy" class="oc-move"><input type="hidden" name="do" value="set">';
      echo     '<input type="hidden" name="tab" value="chart">';
      echo     '<input type="hidden" name="user_id" value="' . (int)$n['id'] . '">';
      echo     '<select name="manager_id" onchange="this.form.submit()" title="Move under another manager">';
      echo       '<option value="">— top level —</option>';
      foreach ($all as $u) {
        if ((int)$u['id'] === (int)$n['id']) continue;
        $un = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
        $sel = (int)($n['reports_to_id'] ?? 0) === (int)$u['id'] ? ' selected' : '';
        echo '<option value="' . (int)$u['id'] . '"' . $sel . '>' . e($un) . '</option>';
      }
      echo     '</select></form>';
      echo '</div>';
    }
    echo   '</div>';
    if ($kids) echo '<button type="button" class="oc-toggle" aria-label="Collapse or expand">−</button>';
    echo '</div>';
    if ($kids) {
      echo '<ul>';
      foreach ($kids as $c) org_node_html($c, $all, $canEdit, $depth + 1);
      echo '</ul>';
    }
    echo '</li>';
  }

  // The office tree — an indented list with connector lines, edited in place.
  function org_office_html($o, $canEdit, $counts, $offOpts, $heads, $desc, $depth = 0) {
    $id = (int)$o['id'];
    $n  = $counts[$id] ?? 0;
    echo '<li class="ot-li">';
    echo '<div class="ot-row' . (!(int)($o['is_active'] ?? 1) ? ' ot-off' : '') . '">';
    echo   '<div class="ot-main">';
    echo     '<b>' . e($o['name']) . '</b>';
    if (!empty($o['code'])) echo ' <span class="muted">' . e($o['code']) . '</span>';
    echo     ' <span class="pill p-mut">' . e(OFFICE_TYPES[$o['office_type'] ?? 'BRANCH'] ?? 'Branch') . '</span>';
    if (!(int)($o['is_active'] ?? 1)) echo ' <span class="pill p-bad">inactive</span>';
    echo     '<div class="ot-sub">';
    $bits = [];
    if (!empty($o['city'])) $bits[] = e($o['city']);
    if (!empty($o['region'])) $bits[] = e($o['region']);
    $head = $heads[(int)($o['head_user_id'] ?? 0)] ?? '';
    $bits[] = $head ? ('Head: ' . e($head)) : '<span class="p-warn-txt">no head set</span>';
    $bits[] = $n . ' ' . ($n === 1 ? 'person' : 'people');
    echo       implode(' · ', $bits);
    echo     '</div>';
    echo   '</div>';
    if ($canEdit) {
      echo '<div class="ot-act">';
      echo   '<form method="post" action="/hierarchy" class="ot-inline">';
      echo     '<input type="hidden" name="do" value="office-move"><input type="hidden" name="tab" value="offices">';
      echo     '<input type="hidden" name="office_id" value="' . $id . '">';
      echo     '<span class="ot-lab">under</span>';
      echo     '<select name="parent_office_id" onchange="this.form.submit()" title="Move this ' . e(Tl('office')) . ' under another">';
      echo       '<option value="">— top level —</option>';
      // Its own sub-offices are left out: choosing one would fold the branch
      // into a loop, and the picker should not offer what it will refuse.
      $blocked = array_merge([$id], $desc[$id] ?? []);
      foreach ($offOpts as $oid => $lbl) {
        if (in_array((int)$oid, $blocked, true)) continue;
        echo '<option value="' . (int)$oid . '"' . ((int)($o['parent_office_id'] ?? 0) === (int)$oid ? ' selected' : '') . '>' . e($lbl) . '</option>';
      }
      echo     '</select></form>';
      echo   '<a class="btn small secondary" href="/hierarchy?tab=offices&office=' . $id . '">Edit</a>';
      echo   '<a class="btn small secondary" href="/hierarchy?tab=offices&office=new&under=' . $id . '">+ Sub</a>';
      echo   '<form method="post" action="/hierarchy" class="ot-inline">';
      echo     '<input type="hidden" name="do" value="office-toggle"><input type="hidden" name="tab" value="offices">';
      echo     '<input type="hidden" name="office_id" value="' . $id . '">';
      echo     '<button class="btn small secondary" type="submit">' . ((int)($o['is_active'] ?? 1) ? 'Deactivate' : 'Reactivate') . '</button>';
      echo   '</form>';
      // Delete is offered only where it is actually possible. An office that
      // work has been booked against would take calls, deputations and vouchers
      // with it, so instead of a button that fails, the row says what is holding
      // it — and Deactivate above already does the safe version of the same job.
      $uses = office_in_use($id);
      if (!$uses) {
        echo '<form method="post" action="/hierarchy" class="ot-inline"'
           . ' onsubmit="return confirm(\'Delete ' . e(addslashes($o['name'] ?? '')) . '? Nothing has been recorded against it, so nothing else changes. This cannot be undone.\')">';
        echo   '<input type="hidden" name="do" value="office-delete"><input type="hidden" name="tab" value="offices">';
        echo   '<input type="hidden" name="office_id" value="' . $id . '">';
        echo   '<button class="btn small secondary ot-del" type="submit">Delete</button>';
        echo '</form>';
      } else {
        echo '<span class="muted ot-why" title="' . e(office_in_use_text($uses)) . '">in use · cannot delete</span>';
      }
      echo '</div>';
    }
    echo '</div>';
    if (!empty($o['children'])) {
      echo '<ul class="ot-ul">';
      foreach ($o['children'] as $c) org_office_html($c, $canEdit, $counts, $offOpts, $heads, $desc, $depth + 1);
      echo '</ul>';
    }
    echo '</li>';
  }

  // Every person, keyed by id — the office rows need head names without one
  // query per row.
  $peopleNames = [];
  foreach ($all as $u) $peopleNames[(int)$u['id']] = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];

  $gapCount = 0; foreach ($health as $g) if ($g['level'] !== 'info') $gapCount++;
  $tabs = [
    'chart'   => 'Chart',
    'offices' => THP('office'),
    'people'  => 'People',
    'import'  => 'Excel upload',
    'gaps'    => 'Gaps' . ($gapCount ? ' (' . $gapCount . ')' : ''),
  ];
?>
<?= org_head($tab,
      'One place for the reporting tree, the ' . e(Tl('office')) . ' structure, every person in them and their login — '
      . (int)$totalPeople . ' active people across ' . count($offOpts) . ' ' . e(Tlp('office')) . '.',
      $tab === 'chart' ? '/' : '/hierarchy?tab=chart',
      $tab === 'chart' ? 'Home' : 'Back') ?>

<?php // First-run guidance. Once every step is done it stops taking up room and
      // becomes a line you can open if you want it. ?>
<?php $steps = org_start_here(); $left = array_values(array_filter($steps, function ($s) { return !$s['done']; })); ?>
<?php if ($left): ?>
<div class="panel" style="border:1px solid var(--info);background:color-mix(in srgb,var(--info) 6%,transparent)">
  <div class="ctitle" style="margin-top:0"><h3>Start here — in this order</h3>
    <span class="pill p-info"><?= count($steps) - count($left) ?> of <?= count($steps) ?> done</span></div>
  <p class="muted" style="margin:0 0 10px;font-size:13.5px">The order matters: a person cannot be given an
    <?= e(Tl('office')) ?> or a designation that does not exist yet.</p>
  <div class="ss-wrap" style="display:grid;gap:8px">
    <?php foreach ($steps as $i => $s): $isNext = !$s['done'] && $s['n'] === $left[0]['n']; ?>
      <div style="display:flex;gap:10px;align-items:flex-start;padding:9px 11px;border-radius:10px;
                  border:1px solid <?= $isNext ? 'var(--brand)' : 'var(--line)' ?>;
                  background:<?= $isNext ? 'color-mix(in srgb,var(--brand) 7%,transparent)' : 'var(--card)' ?>;
                  <?= $s['done'] ? 'opacity:.6' : '' ?>">
        <span style="font-weight:800;min-width:22px;color:<?= $s['done'] ? 'var(--ok)' : 'var(--brand)' ?>">
          <?= $s['done'] ? '✔' : (int)$s['n'] ?></span>
        <div style="flex:1">
          <b><?= e($s['title']) ?></b>
          <?php if ($isNext): ?><span class="pill p-info" style="margin-left:6px">do this next</span><?php endif; ?>
          <div class="muted" style="font-size:13px;margin-top:2px"><?= e($s['why']) ?></div>
        </div>
        <span class="muted" style="font-size:12.5px;white-space:nowrap"><?= e($s['state']) ?></span>
        <a class="btn small <?= $isNext ? '' : 'secondary' ?>" href="<?= e($s['href']) ?>"><?= e($s['cta']) ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!$canEdit): ?>
  <?php // A Business Director sees the whole organisation but does not maintain
        // it — say so, rather than leaving them wondering why nothing responds. ?>
  <div class="msg msg-info">You are seeing the complete organisation, read-only — every person, every <?= e(Tl('office')) ?> and every gap.
    Changes to reporting lines and <?= e(Tlp('office')) ?> are made by a Super Admin.</div>
<?php endif; ?>

<?php if ($tab === 'chart'): ?>
  <?php if ($canEdit && $proposals && $unplaced > 1): ?>
  <div class="panel" style="border:1px solid var(--warn)">
    <b>⚠ Most people have no reporting manager, so the chart is a flat row.</b>
    <p class="sub" style="margin:6px 0 8px">
      A hierarchy only forms once people are placed under somebody. This proposes the obvious chain from the role
      ladder — an <?= e(ORG_ROLES['INSPECTOR'] ?? 'Inspector') ?> under a Coordinator, a Coordinator under an
      Operation Manager, and so on — preferring somebody in the same <?= e(T('office')) ?>.
      Apply it, then correct anyone who is wrong on the <a href="/hierarchy?tab=people">People</a> tab.
    </p>
    <details style="margin-bottom:10px">
      <summary class="muted" style="cursor:pointer"><?= count($proposals) ?> proposed reporting line(s) — see them first</summary>
      <table class="dt" style="margin-top:8px">
        <thead><tr><th>Person</th><th>Would report to</th></tr></thead>
        <tbody>
        <?php foreach ($proposals as $p): ?>
          <tr><td><?= e($p['user']) ?> <span class="muted"><?= e(ORG_ROLES[$p['role']] ?? $p['role']) ?></span></td>
              <td><?= e($p['manager']) ?> <span class="muted"><?= e(ORG_ROLES[$p['manager_role']] ?? $p['manager_role']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </details>
    <form method="post" action="/hierarchy" style="display:inline">
      <input type="hidden" name="do" value="auto"><input type="hidden" name="tab" value="chart">
      <button class="btn" type="submit">Arrange these <?= count($proposals) ?> automatically</button>
    </form>
    <span class="muted" style="margin-left:8px">Only people who have no manager are touched.</span>
  </div>
  <?php endif; ?>

  <?php if (!$tree): ?>
    <div class="panel"><p class="muted">No active users yet. Add them one at a time under <a href="/users">Users</a>, or load the whole team at once from the <a href="/hierarchy?tab=import">Excel upload</a> tab.</p></div>
  <?php else: ?>
    <div class="panel oc-wrap">
      <div class="oc-tools">
        <button type="button" class="btn small secondary" id="oc-expand">Expand all</button>
        <button type="button" class="btn small secondary" id="oc-collapse">Collapse all</button>
        <span class="oc-sep"></span>
        <button type="button" class="btn small secondary" id="oc-zout" title="Zoom out">−</button>
        <span class="oc-zval" id="oc-zval">100%</span>
        <button type="button" class="btn small secondary" id="oc-zin" title="Zoom in">+</button>
        <button type="button" class="btn small" id="oc-fit" title="Scale the chart to fit the window">Fit to screen</button>
        <button type="button" class="btn small secondary" id="oc-100" title="Back to full size">100%</button>
        <span class="oc-sep"></span>
        <label class="chk" style="white-space:nowrap"><input type="checkbox" id="oc-compact"> Compact</label>
        <span class="muted oc-hint">Click − on a card to fold a branch. Drag to pan.</span>
      </div>
      <div class="oc-scroll">
        <div class="oc-sizer">
          <div class="oc-stage">
            <ul class="oc-chart">
              <?php foreach ($tree as $root) org_node_html($root, $all, $canEdit); ?>
            </ul>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'offices'): ?>
  <?php if ($editOffice !== null): $o = $editOffice; $isNew = !(int)($o['id'] ?? 0); ?>
    <div class="panel">
      <h3 class="tab-sub" style="margin-top:0"><?= $isNew ? 'New ' . e(Tl('office')) : 'Edit ' . e(Tl('office')) . ' — ' . e($o['name'] ?? '') ?></h3>
      <form method="post" action="/hierarchy">
        <input type="hidden" name="do" value="office-save"><input type="hidden" name="tab" value="offices">
        <input type="hidden" name="office_id" value="<?= (int)($o['id'] ?? 0) ?>">
        <div class="form-grid">
          <div class="ff"><label>Code</label><input name="o_code" value="<?= e($o['code'] ?? '') ?>" placeholder="e.g. AMD"></div>
          <div class="ff ff-wide"><label><?= e(TH('office')) ?> name *</label><input name="o_name" required value="<?= e($o['name'] ?? '') ?>"></div>
          <div class="ff"><label>Type</label><select name="o_type">
            <?php foreach (OFFICE_TYPES as $k => $v): ?>
              <option value="<?= e($k) ?>"<?= ($o['office_type'] ?? 'BRANCH') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="ff"><label>Sits under</label><select name="o_parent">
            <option value="">— top level —</option>
            <?php $pre = (int)($o['parent_office_id'] ?? ($_GET['under'] ?? 0));
                  foreach ($offOpts as $oid => $lbl): ?>
              <option value="<?= (int)$oid ?>"<?= $pre === (int)$oid ? ' selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="ff"><label>Head of this <?= e(Tl('office')) ?></label><select name="o_head">
            <option value="">— not set —</option>
            <?php foreach ($all as $u): $un = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']; ?>
              <option value="<?= (int)$u['id'] ?>"<?= (int)($o['head_user_id'] ?? 0) === (int)$u['id'] ? ' selected' : '' ?>><?= e($un) ?> — <?= e(ORG_ROLES[$u['role']] ?? $u['role']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="ff"><label>Region / zone</label><input name="o_region" value="<?= e($o['region'] ?? '') ?>"></div>
          <div class="ff"><label>City</label><input name="o_city" value="<?= e($o['city'] ?? '') ?>"></div>
          <div class="ff ff-wide"><label>Address</label><input name="o_address" value="<?= e($o['address'] ?? '') ?>"></div>
          <div class="ff"><label>Phone</label><input name="o_phone" value="<?= e($o['phone'] ?? '') ?>"></div>
          <div class="ff"><label>Order on the chart</label><input name="o_sort" type="number" value="<?= (int)($o['sort_order'] ?? 0) ?>"></div>
          <div class="ff ff-check"><label class="chk"><input type="checkbox" name="o_active" value="1"<?= (int)($o['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
          <button class="btn" type="submit">Save</button>
          <a class="btn secondary" href="/hierarchy?tab=offices">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="master-head" style="margin-bottom:8px">
      <div><h3 class="tab-sub" style="margin:0"><?= e(THP('office')) ?> structure</h3>
        <p class="sub" style="margin:2px 0 0">Drag-free: change the “sits under” box on any row and it moves at once. Every person's home <?= e(Tl('office')) ?> comes from this list.</p></div>
      <?php if ($canEdit): ?><a class="btn" href="/hierarchy?tab=offices&office=new">+ New <?= e(Tl('office')) ?></a><?php endif; ?>
    </div>
    <?php if (!$offTree): ?>
      <p class="muted">No <?= e(Tlp('office')) ?> yet.</p>
    <?php else: ?>
      <ul class="ot-ul ot-root">
        <?php foreach ($offTree as $o) org_office_html($o, $canEdit, $offCounts, $offOpts, $peopleNames, $offDesc); ?>
      </ul>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'people'): ?>
  <div class="panel">
    <div class="master-head" style="margin-bottom:8px">
      <div><h3 class="tab-sub" style="margin:0">Everybody, in one grid</h3>
        <p class="sub" style="margin:2px 0 0">Set role, designation, <?= e(Tl('office')) ?> and reporting manager for the whole team, then save once. This is the same data the chart draws.</p></div>
      <input id="pp-find" class="pp-find" type="search" placeholder="Filter by name, role or office…">
    </div>
    <form method="post" action="/hierarchy">
      <input type="hidden" name="do" value="people-save"><input type="hidden" name="tab" value="people">
      <div style="overflow-x:auto">
      <table class="dt pp-table">
        <thead><tr><th>Person</th><th>Role</th><th>Designation</th><th><?= e(TH('office')) ?></th><th>Reports to</th></tr></thead>
        <tbody>
        <?php foreach ($people as $p):
              $nm = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: $p['username']; ?>
          <tr data-find="<?= e(strtolower($nm . ' ' . $p['username'] . ' ' . (ORG_ROLES[$p['role']] ?? '') . ' ' . ($offAll[(int)($p['home_office_id'] ?? 0)] ?? ''))) ?>">
            <td><a href="/user-edit?id=<?= (int)$p['id'] ?>"><b><?= e($nm) ?></b></a>
                <div class="muted" style="font-size:11.5px"><?= e($p['username']) ?><?= $p['email'] ? ' · ' . e($p['email']) : '' ?></div></td>
            <td><select name="p_role[<?= (int)$p['id'] ?>]" <?= $canEdit ? '' : 'disabled' ?>>
              <?php foreach (ORG_ROLES as $k => $v): ?>
                <option value="<?= e($k) ?>"<?= $p['role'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></td>
            <td><input name="p_position[<?= (int)$p['id'] ?>]" value="<?= e($p['position_title'] ?? '') ?>" placeholder="as printed on a card" <?= $canEdit ? '' : 'disabled' ?>></td>
            <td><select name="p_office[<?= (int)$p['id'] ?>]" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="">— none —</option>
              <?php foreach ($offOpts as $oid => $lbl): ?>
                <option value="<?= (int)$oid ?>"<?= (int)($p['home_office_id'] ?? 0) === (int)$oid ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></td>
            <td><select name="p_manager[<?= (int)$p['id'] ?>]" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="">— top level —</option>
              <?php foreach ($all as $u): if ((int)$u['id'] === (int)$p['id']) continue;
                    $un = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']; ?>
                <option value="<?= (int)$u['id'] ?>"<?= (int)($p['reports_to_id'] ?? 0) === (int)$u['id'] ? ' selected' : '' ?>><?= e($un) ?></option>
              <?php endforeach; ?>
              </select>
              <?php if (!(int)($p['reports_to_id'] ?? 0) && trim($p['reports_to_name'] ?? '') !== ''): ?>
                <div class="muted" style="font-size:11px">typed in as “<?= e($p['reports_to_name']) ?>” — no login</div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php if ($canEdit): ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
          <button class="btn" type="submit">Save all changes</button>
          <span class="muted">A change that would make two people report to each other is skipped and reported back.</span>
        </div>
      <?php endif; ?>
    </form>
  </div>

<?php elseif ($tab === 'import'): ?>
  <?php if (!$importRows): ?>
  <div class="panel-split">
    <div class="panel">
      <h3 class="tab-sub" style="margin-top:0">1 · Download the register</h3>
      <p class="sub">The file opens in Excel with <b>Role</b>, <b><?= e(TH('office')) ?></b>, <b>Reports to</b>, <?= e(T('sbu')) ?>,
        working days and Active as proper dropdowns, so nothing has to be typed from memory.
        It already contains everybody you have — edit them in place, add new rows at the bottom, and upload it back.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <a class="btn" href="/org-template">Download register (.xlsx)</a>
        <a class="btn secondary" href="/org-template?blank=1">Blank template (.xlsx)</a>
        <a class="btn secondary" href="/org-template?fmt=csv">CSV instead</a>
      </div>
      <?php if (!$hasZip): ?>
        <p class="muted" style="margin-top:10px">This server has no ZIP extension, so the .xlsx buttons will hand you a CSV. It opens in Excel and uploads back identically — only the built-in dropdowns are missing, and the preview screen gives you those anyway.</p>
      <?php endif; ?>
      <details style="margin-top:12px">
        <summary class="muted" style="cursor:pointer">What each column means</summary>
        <table class="dt" style="margin-top:8px">
          <tbody>
            <tr><td><b>Username</b></td><td>The login. Leave it blank for a new person and one is made from their e-mail or name. A row whose username already exists <b>updates</b> that person rather than creating a duplicate.</td></tr>
            <tr><td><b>Role</b></td><td>Pick from the dropdown. Codes (<code>BRANCH_MANAGER</code>) work too.</td></tr>
            <tr><td><b>Reports to</b></td><td>The manager's <b>username</b> — or their full name / e-mail. It may point at somebody further down the same file; both rows are created first and linked afterwards. <b>This column is what builds the chart.</b></td></tr>
            <tr><td><b><?= e(TH('office')) ?></b></td><td>Name or code of an existing <?= e(Tl('office')) ?>. Create new ones on the <a href="/hierarchy?tab=offices"><?= e(THP('office')) ?></a> tab first.</td></tr>
            <tr><td><b>Password</b></td><td>Only read for people who do not exist yet. Left blank they get <code>changeme123</code> and should change it at first login.</td></tr>
            <tr><td><b>Active</b></td><td><code>No</code> deactivates somebody who has left, keeping their history intact.</td></tr>
          </tbody>
        </table>
      </details>
    </div>
    <div class="panel">
      <h3 class="tab-sub" style="margin-top:0">2 · Upload it back</h3>
      <p class="sub">Nothing is written yet — you get a preview first, with every unmatched value offered as a dropdown to correct on screen.</p>
      <?php if ($canEdit): ?>
      <form method="post" action="/hierarchy" enctype="multipart/form-data" style="margin-top:10px">
        <input type="hidden" name="do" value="import-preview"><input type="hidden" name="tab" value="import">
        <div class="ff"><label>Excel or CSV file</label><input type="file" name="sheet" accept=".xlsx,.csv,.txt" required></div>
        <button class="btn" type="submit" style="margin-top:10px">Check the file</button>
      </form>
      <?php else: ?><p class="muted">Only a Super Admin can upload the register.</p><?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <?php
    $nCreate = 0; $nUpdate = 0; $nIssue = 0;
    foreach ($importRows as $r) { if ($r['action'] === 'create') $nCreate++; else $nUpdate++; if ($r['issues']) $nIssue++; }
  ?>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">3 · Check before writing — <?= e($importName) ?></h3>
    <div class="kpi-row" style="margin:8px 0 14px">
      <div class="kpi"><span class="k-lab">Rows read</span><span class="k-val"><?= count($importRows) ?></span></div>
      <div class="kpi"><span class="k-lab">New people</span><span class="k-val"><?= $nCreate ?></span></div>
      <div class="kpi"><span class="k-lab">Existing, updated</span><span class="k-val"><?= $nUpdate ?></span></div>
      <div class="kpi"><span class="k-lab">Need a look</span><span class="k-val"><?= $nIssue ?></span></div>
    </div>
    <p class="sub">Anything the file did not match is shown as a dropdown — set it here and it is saved with the rest. Untick a row to leave that person alone.</p>
    <form method="post" action="/hierarchy">
      <input type="hidden" name="do" value="import-apply"><input type="hidden" name="tab" value="import">
      <div style="overflow-x:auto">
      <table class="dt">
        <thead><tr><th>#</th><th>Import</th><th>Person</th><th>Role</th><th><?= e(TH('office')) ?></th><th>Reports to</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($importRows as $i => $r): ?>
          <tr<?= $r['issues'] ? ' style="background:color-mix(in srgb,var(--warn) 8%,transparent)"' : '' ?>>
            <td class="muted"><?= (int)$r['line'] ?></td>
            <td><label class="chk"><input type="checkbox" name="i_skip[<?= $i ?>]" value="1"> skip</label></td>
            <td><b><?= e(trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['username']) ?></b>
                <div class="muted" style="font-size:11.5px"><?= e($r['username']) ?><?= $r['email'] ? ' · ' . e($r['email']) : '' ?></div>
                <span class="pill <?= $r['action'] === 'create' ? 'p-ok' : 'p-info' ?>"><?= $r['action'] === 'create' ? 'new' : 'update' ?></span></td>
            <td><select name="i_role[<?= $i ?>]">
              <?php // Never let a blank role look like whichever role happens to
                    // be first in the list — make the gap visible instead. ?>
              <?php if ($r['role'] === ''): ?><option value="" selected>— choose a role —</option><?php endif; ?>
              <?php foreach (ORG_ROLES as $k => $v): ?>
                <option value="<?= e($k) ?>"<?= $r['role'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></td>
            <td><select name="i_office[<?= $i ?>]">
              <option value="">— none —</option>
              <?php foreach ($offOpts as $oid => $lbl): ?>
                <option value="<?= (int)$oid ?>"<?= (int)$r['office_id'] === (int)$oid ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></td>
            <td><select name="i_manager[<?= $i ?>]">
              <option value="0">— top level —</option>
              <?php // people arriving in this same file, so a forward reference is selectable
              foreach ($importRows as $j => $o): if ($j === $i) continue;
                    $lbl = trim($o['first_name'] . ' ' . $o['last_name']) ?: $o['username']; ?>
                <option value="row:<?= $j ?>"<?= ((int)$r['manager_row'] === $j) ? ' selected' : '' ?>><?= e($lbl) ?> — in this file</option>
              <?php endforeach; ?>
              <?php foreach ($all as $u): $un = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']; ?>
                <option value="<?= (int)$u['id'] ?>"<?= (int)$r['manager_user_id'] === (int)$u['id'] ? ' selected' : '' ?>><?= e($un) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (trim($r['reports_to_raw']) !== ''): ?><div class="muted" style="font-size:11px">file says “<?= e($r['reports_to_raw']) ?>”</div><?php endif; ?>
            </td>
            <td><?php foreach ($r['issues'] as $is): ?><div class="pill p-warn"><?= e($is) ?></div><?php endforeach; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div style="margin-top:12px">
        <button class="btn" type="submit">Import <?= count($importRows) ?> row(s)</button>
      </div>
    </form>
    <form method="post" action="/hierarchy" style="margin-top:8px">
      <input type="hidden" name="do" value="import-cancel">
      <button class="btn secondary" type="submit">Cancel and start over</button>
    </form>
  </div>
  <?php endif; ?>

<?php else: /* gaps */ ?>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Is the chart the whole truth?</h3>
    <?php if (!$health): ?>
      <p><span class="pill p-ok">All clear</span> Everybody has a manager and an <?= e(Tl('office')) ?>, every <?= e(Tl('office')) ?> has a head, and there are no loops. The chart on the first tab is the complete organisation.</p>
    <?php else: ?>
      <p class="sub">Each item below is a reason the chart is not yet a complete picture. They are listed worst first.</p>
      <?php
        $order = ['bad' => 0, 'warn' => 1, 'info' => 2];
        usort($health, fn($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
        foreach ($health as $g): ?>
        <div class="gp gp-<?= e($g['level']) ?>">
          <div class="gp-h"><span class="pill p-<?= $g['level'] === 'bad' ? 'bad' : ($g['level'] === 'warn' ? 'warn' : 'info') ?>"><?= $g['level'] === 'info' ? 'FYI' : ($g['level'] === 'bad' ? 'Blocks the chart' : 'Incomplete') ?></span>
            <b><?= e($g['title']) ?></b></div>
          <p class="sub" style="margin:6px 0 4px"><?= e($g['why']) ?></p>
          <p class="muted" style="margin:0 0 6px"><b>Fix:</b> <?= e($g['fix']) ?></p>
          <div class="chip-row">
            <?php foreach (array_slice($g['people'], 0, 40) as $p): ?>
              <?php if ((int)$p['id']): ?><a class="ct" href="/user-edit?id=<?= (int)$p['id'] ?>"><?= e($p['label']) ?></a>
              <?php else: ?><span class="ct"><?= e($p['label']) ?></span><?php endif; ?>
            <?php endforeach; ?>
            <?php if (count($g['people']) > 40): ?><span class="ct">+<?= count($g['people']) - 40 ?> more</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<style>
  /* ---- Top-down organisation chart -------------------------------------
     Each level is a flex row; the connectors are drawn with borders on the
     ::before / ::after of every node, which is what turns a nested list into
     a chart with lines instead of an indented outline. */
  .oc-wrap{padding:12px}
  .oc-tools{display:flex;gap:6px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
  .oc-sep{width:1px;height:20px;background:var(--line);margin:0 4px}
  .oc-zval{font-size:12px;color:var(--muted);min-width:42px;text-align:center;font-variant-numeric:tabular-nums}
  .oc-hint{margin-left:6px}
  @media (max-width:900px){ .oc-hint{display:none} }
  /* The stage is scaled with a transform; the sizer is given the resulting
     pixel size so the scrollbars stay honest (a transform does not change
     layout size on its own). */
  /* Bounded height: the chart scrolls inside the panel instead of pushing the
     page down, which is what lets "fit" mean one screen. */
  .oc-scroll{overflow:auto;padding:10px 4px 18px;max-height:calc(100vh - 250px);min-height:280px}
  /* The scaled stage still occupies its UNSCALED width in layout, which would
     leave a phantom overflow to the right. The sizer is the true painted box,
     so clipping to it removes that without hiding anything visible. */
  .oc-sizer{margin:0 auto;overflow:hidden}
  .oc-stage{transform-origin:0 0;display:inline-block}
  .oc-chart, .oc-chart ul{display:flex;justify-content:center;list-style:none;margin:0;padding:0}
  .oc-chart ul{padding-top:26px;position:relative}
  .oc-chart li{position:relative;padding:26px 6px 0;text-align:center;list-style:none}

  /* the two half-width lines that join siblings together */
  .oc-chart li::before, .oc-chart li::after{
    content:'';position:absolute;top:0;right:50%;width:50%;height:26px;
    border-top:2px solid var(--line);
  }
  .oc-chart li::after{right:auto;left:50%;border-left:2px solid var(--line)}
  /* an only child needs no horizontal line, just the vertical drop */
  .oc-chart li:only-child::after,.oc-chart li:only-child::before{display:none}
  .oc-chart li:only-child{padding-top:26px}
  /* trim the outer edges so the line does not overhang the first/last card */
  .oc-chart li:first-child::before,.oc-chart li:last-child::after{border:0 none}
  .oc-chart li:last-child::before{border-right:2px solid var(--line);border-radius:0 6px 0 0}
  .oc-chart li:first-child::after{border-radius:6px 0 0 0}
  /* the drop from a parent down to its children's connector line */
  .oc-chart ul::before{content:'';position:absolute;top:0;left:50%;width:0;height:26px;border-left:2px solid var(--line)}
  /* the roots sit side by side with no line above them */
  .oc-chart > li{padding-top:0}
  .oc-chart > li::before,.oc-chart > li::after{display:none}

  .oc-node{position:relative;display:inline-block}
  .oc-card{display:inline-flex;flex-direction:column;gap:3px;text-align:left;min-width:170px;max-width:200px;
    background:var(--card);border:1px solid var(--line);border-radius:12px;padding:10px 12px;
    box-shadow:0 1px 2px rgba(0,0,0,.05)}
  .oc-card.oc-top{border-color:var(--brand);box-shadow:0 0 0 2px color-mix(in srgb,var(--brand) 18%,transparent)}
  .oc-head{display:flex;gap:9px;align-items:center}
  .oc-av{width:30px;height:30px;flex:0 0 30px;border-radius:50%;background:var(--brand);color:#fff;
    display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
  .oc-id{display:flex;flex-direction:column;min-width:0}
  .oc-id b{font-size:13.5px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-role{font-size:11.5px;color:var(--brand);font-weight:600;line-height:1.3}
  .oc-mail{font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .oc-meta{margin-top:2px}
  .oc-meta .pill{font-size:10.5px;padding:1px 7px}
  .oc-act{display:flex;gap:6px;align-items:center;margin-top:4px;font-size:11px}
  .oc-act select{font-size:11px;max-width:118px;border:1px solid var(--line);border-radius:6px;
    padding:2px 4px;background:var(--field);color:var(--ink)}
  .oc-move{margin:0}

  .oc-toggle{position:absolute;left:50%;bottom:-13px;transform:translateX(-50%);
    width:22px;height:22px;border-radius:50%;border:1px solid var(--line);background:var(--card);
    color:var(--muted);font-size:14px;line-height:1;cursor:pointer;z-index:2;padding:0}
  .oc-toggle:hover{border-color:var(--brand);color:var(--brand)}
  /* a folded branch hides its children and flips the button to + */
  li.oc-folded > ul{display:none}

  /* Compact drops the e-mail line and tightens everything, which is what makes
     a wide org readable without zooming out so far that names blur. */
  .oc-compact .oc-card{min-width:132px;max-width:150px;padding:7px 9px;border-radius:9px}
  .oc-compact .oc-mail{display:none}
  .oc-compact .oc-av{width:24px;height:24px;flex-basis:24px;font-size:11px}
  .oc-compact .oc-id b{font-size:12.5px}
  .oc-compact .oc-role{font-size:10.5px}
  .oc-compact .oc-chart li{padding-left:3px;padding-right:3px}
  .oc-compact .oc-act select{max-width:86px}
  /* Shorter drops between levels — on a deep chain this is most of the height. */
  .oc-compact .oc-chart ul{padding-top:16px}
  .oc-compact .oc-chart li{padding-top:16px}
  .oc-compact .oc-chart li::before,.oc-compact .oc-chart li::after{height:16px}
  .oc-compact .oc-chart ul::before{height:16px}
  .oc-compact .oc-chart > li{padding-top:0}

  /* ---- Office tree ------------------------------------------------------
     An indented list, not a chart: an office structure is read top-to-bottom
     like a filing cabinet, and every row has to stay clickable. */
  .ot-ul{list-style:none;margin:0;padding:0}
  .ot-ul .ot-ul{margin-left:16px;padding-left:16px;border-left:2px solid var(--line)}
  .ot-li{position:relative;margin:4px 0}
  .ot-ul .ot-ul > .ot-li::before{content:'';position:absolute;left:-16px;top:19px;width:12px;border-top:2px solid var(--line)}
  .ot-row{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;
    border:1px solid var(--line);border-radius:10px;padding:8px 12px;background:var(--card)}
  .ot-row.ot-off{opacity:.6}
  .ot-main{min-width:0}
  .ot-sub{font-size:11.5px;color:var(--muted);margin-top:2px}
  .p-warn-txt{color:var(--warn);font-weight:600}
  .ot-act{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .ot-inline{margin:0;display:inline}
  .ot-lab{font-size:11px;color:var(--muted);margin-right:3px}
  .ot-act select{font-size:11.5px;max-width:180px;border:1px solid var(--line);border-radius:6px;
    padding:3px 5px;background:var(--field);color:var(--ink)}

  /* ---- People grid ------------------------------------------------------ */
  .pp-find{min-width:240px;border:1px solid var(--line);border-radius:8px;padding:7px 10px;
    background:var(--field);color:var(--ink)}
  .pp-table td select, .pp-table td input{width:100%;min-width:120px;box-sizing:border-box}
  .pp-table td{vertical-align:top}

  /* ---- Gap cards -------------------------------------------------------- */
  .gp{border:1px solid var(--line);border-left-width:4px;border-radius:10px;padding:12px 14px;margin:10px 0}
  .gp-bad{border-left-color:var(--bad)}
  .gp-warn{border-left-color:var(--warn)}
  .gp-info{border-left-color:var(--line)}
  .gp-h{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

  @media print{
    .master-head .btn,.side,.nav-toggle,.oc-tools,.oc-act,.oc-toggle,.crumbs,.tabs,.ot-act{display:none!important}
    .oc-scroll{overflow:visible;max-height:none}
    .oc-sizer{width:auto!important;height:auto!important}
    .oc-stage{transform:none!important}
    .oc-card{box-shadow:none}
  }
</style>
<script>
(function(){
  // ---- People tab: live filter -----------------------------------------
  var find = document.getElementById('pp-find');
  if (find) find.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('.pp-table tbody tr').forEach(function(tr){
      tr.style.display = (!q || (tr.dataset.find || '').indexOf(q) >= 0) ? '' : 'none';
    });
  });

  var chart  = document.querySelector('.oc-chart');
  if (!chart) return;
  var scroll = document.querySelector('.oc-scroll');
  var sizer  = document.querySelector('.oc-sizer');
  var stage  = document.querySelector('.oc-stage');
  var wrap   = document.querySelector('.oc-wrap');
  var zval   = document.getElementById('oc-zval');
  var MIN = 0.35, MAX = 1.4;
  var zoom = 1, userSet = false;

  // ---- zoom -------------------------------------------------------------
  // A transform does not change layout size, so the sizer is given the scaled
  // pixel dimensions; otherwise the scrollbars would describe the unscaled
  // chart and the panel would keep its full-size height.
  function measure(){
    var prev = stage.style.transform;
    stage.style.transform = 'none';
    var w = chart.scrollWidth, h = chart.scrollHeight;
    stage.style.transform = prev;
    return {w: w, h: h};
  }
  function applyZoom(k, keepCentre){
    k = Math.min(MAX, Math.max(MIN, k));
    var before = scroll.scrollLeft + scroll.clientWidth / 2;
    var ratio  = zoom ? (k / zoom) : 1;
    zoom = k;
    var m = measure();
    stage.style.transform = 'scale(' + k + ')';
    sizer.style.width  = Math.ceil(m.w * k) + 'px';
    sizer.style.height = Math.ceil(m.h * k) + 'px';
    zval.textContent = Math.round(k * 100) + '%';
    if (keepCentre) scroll.scrollLeft = before * ratio - scroll.clientWidth / 2;
    else centre();
  }
  function centre(){
    // when the chart is narrower than the window the sizer is centred by its
    // auto margins; when it is wider, start the view in the middle
    if (sizer.offsetWidth > scroll.clientWidth)
      scroll.scrollLeft = (sizer.offsetWidth - scroll.clientWidth) / 2;
  }
  // Depth of a node, so the deepest level can be folded first.
  function depthOf(li){ var d = 0, n = li.parentNode; while (n && n !== chart) { if (n.tagName === 'UL') d++; n = n.parentNode; } return d; }
  function maxDepth(){
    var d = 0;
    chart.querySelectorAll('li').forEach(function(li){ if (!li.closest('.oc-folded') || li.classList.contains('oc-folded')) d = Math.max(d, depthOf(li)); });
    return d;
  }
  function needed(){
    var m = measure();
    var cs = getComputedStyle(scroll);
    // clientWidth/Height include the container's own padding, so take it off or
    // the fit is a few pixels too generous and still scrolls
    var aw = scroll.clientWidth  - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight) - 2;
    var ah = scroll.clientHeight - parseFloat(cs.paddingTop)  - parseFloat(cs.paddingBottom) - 2;
    if (aw <= 0 || ah <= 0) return 1;
    // fit BOTH dimensions — fitting only the width left a deep chart running
    // off the bottom of the window
    return Math.min(1, aw / m.w, ah / m.h);
  }
  function fit(){
    var k = needed();
    // Shrinking past MIN makes the names unreadable, which is not "fitting".
    // Fold the deepest level instead and try again — a big org then fits by
    // showing fewer levels rather than by becoming a blur.
    var guard = 0;
    while (k < MIN && guard++ < 12) {
      var d = maxDepth();
      if (d < 1) break;
      var folded = false;
      chart.querySelectorAll('li').forEach(function(li){
        if (depthOf(li) === d - 1 && li.querySelector('ul') && !li.classList.contains('oc-folded')) {
          setFolded(li, true); folded = true;
        }
      });
      if (!folded) break;
      k = needed();
    }
    applyZoom(k);
  }
  // Fit on load and whenever the shape changes, unless the user has chosen a
  // zoom of their own — then leave their choice alone.
  function autoFit(){ if (!userSet) fit(); else applyZoom(zoom, true); }

  document.getElementById('oc-fit').addEventListener('click', function(){ userSet = false; fit(); });
  document.getElementById('oc-100').addEventListener('click', function(){ userSet = true; applyZoom(1); });
  document.getElementById('oc-zin').addEventListener('click', function(){ userSet = true; applyZoom(zoom + 0.1, true); });
  document.getElementById('oc-zout').addEventListener('click', function(){ userSet = true; applyZoom(zoom - 0.1, true); });
  // Ctrl/⌘ + wheel zooms, as it does in every other diagram tool.
  scroll.addEventListener('wheel', function(e){
    if (!e.ctrlKey && !e.metaKey) return;
    e.preventDefault(); userSet = true;
    applyZoom(zoom + (e.deltaY < 0 ? 0.08 : -0.08), true);
  }, {passive:false});

  // ---- density ----------------------------------------------------------
  var compact = document.getElementById('oc-compact');
  function setCompact(on){
    wrap.classList.toggle('oc-compact', on);
    try { localStorage.setItem('ocCompact', on ? '1' : '0'); } catch (err) {}
    autoFit();
  }
  compact.addEventListener('change', function(){ setCompact(this.checked); });
  try {
    // Default to compact for a big org — it is what makes it fit and stay legible.
    var saved = localStorage.getItem('ocCompact');
    var want = saved === null ? (chart.querySelectorAll('.oc-card').length > 8) : (saved === '1');
    compact.checked = want; wrap.classList.toggle('oc-compact', want);
  } catch (err) {}

  // ---- fold / unfold ----------------------------------------------------
  function setFolded(li, folded){
    li.classList.toggle('oc-folded', folded);
    var node = li.firstElementChild;
    var btn = node ? node.querySelector('.oc-toggle') : null;
    if (btn) btn.textContent = folded ? '+' : '−';
  }
  chart.addEventListener('click', function(e){
    var btn = e.target.closest ? e.target.closest('.oc-toggle') : null;
    if (!btn) return;
    var li = btn.closest('li');
    setFolded(li, !li.classList.contains('oc-folded'));
    autoFit();                      // folding changes the width, so refit
  });
  var ex = document.getElementById('oc-expand'), co = document.getElementById('oc-collapse');
  if (ex) ex.addEventListener('click', function(){
    chart.querySelectorAll('li').forEach(function(li){ setFolded(li, false); });
    autoFit();
  });
  if (co) co.addEventListener('click', function(){
    chart.querySelectorAll('li').forEach(function(li){ if (li.querySelector('ul')) setFolded(li, true); });
    autoFit();
  });

  // ---- drag to pan ------------------------------------------------------
  var down = false, x0 = 0, y0 = 0, l0 = 0, t0 = 0;
  scroll.addEventListener('mousedown', function(e){
    if (e.target.closest && e.target.closest('.oc-card')) return;   // let card clicks work
    down = true; x0 = e.pageX; y0 = e.pageY; l0 = scroll.scrollLeft; t0 = scroll.scrollTop;
    scroll.style.cursor = 'grabbing';
  });
  ['mouseup','mouseleave'].forEach(function(ev){
    scroll.addEventListener(ev, function(){ down = false; scroll.style.cursor = ''; });
  });
  scroll.addEventListener('mousemove', function(e){
    if (!down) return; e.preventDefault();
    scroll.scrollLeft = l0 - (e.pageX - x0);
    scroll.scrollTop  = t0 - (e.pageY - y0);
  });

  // ---- go ---------------------------------------------------------------
  fit();
  window.addEventListener('resize', autoFit);
})();
</script>
