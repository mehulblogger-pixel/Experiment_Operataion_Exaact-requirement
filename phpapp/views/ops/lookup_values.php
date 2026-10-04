<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › <a href="/lookups">Masters</a> › <?= e($t['label']) ?></div>
<div class="master-head">
  <div><h1><?= e($t['label']) ?></h1>
    <p class="sub">Values in this master list<?= $parentType ? ' · each belongs under a <strong>' . e($parentType['label']) . '</strong>' : '' ?></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/lookups">← All master lists</a>
    <?php // The form that adds a value sits below the table, so on a list of forty
          //  designations "add one" meant scrolling past all forty. The button
          //  belongs where the eye already is. ?>
    <a class="btn" href="#lkAdd" id="lkAddJump">+ Add a value</a>
  </div>
</div>

<?php // A recruitment workspace can reset this list to the recruitment-agency
      // defaults if it is carrying the generic/inspection content (e.g. Department
      // showing Inspection / NDT / HSE). Only where the list has a recruitment
      // default and the Recruitment module is on. ?>
<?php $recDef = function_exists('lk_recruit_default_for') ? lk_recruit_default_for($t['type_key']) : null;
      if ($recDef && function_exists('licence_enabled') && licence_enabled('hr')): ?>
<div class="panel" style="border-left:4px solid var(--brand,#3f4fce);background:var(--soft,#f6f8fb);padding:11px 14px;margin-bottom:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
  <div style="flex:1;min-width:220px"><b>Recruitment-agency defaults available</b>
    <div class="muted" style="font-size:12.5px">Replace this list with the recruitment set (<?= count($recDef) ?> values) — e.g. Recruitment / Talent Acquisition, Sourcing, Account Management, Business Development, HR &amp; Compliance… Existing records keep their saved value.</div></div>
  <form method="post" action="/lookup?key=<?= e($t['type_key']) ?>" onsubmit="return confirm('Replace all values on this list with the recruitment-agency defaults? Records already saved keep their value; only the dropdown options change.')">
    <input type="hidden" name="reset_recruit" value="1">
    <button class="btn" type="submit">↺ Use recruitment defaults</button>
  </form>
</div>
<?php endif; ?>

<?php // Where this list appears. Tick a form to add it as a dropdown there; untick
      // to take it off (values people already chose stay saved, just stop showing). ?>

<?php // Typing beats scanning on a list of any length, and these lists grow. ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
  <input id="lkFind" class="form-control" type="search" autocomplete="off"
         style="flex:1 1 240px;min-width:0" placeholder="Find a value in this list…">
  <span id="lkFindCount" class="muted" style="font-size:13px"></span>
</div>

<table class="grid" id="lkTable">
  <?php // Module 01 — is this value safe to remove? Show how many records use it. ?>
  <?php $tracked = function_exists('lk_value_usage') && isset(lk_usage_map()[$t['type_key']]); ?>
  <tr><th>Value</th><?php if ($parentType): ?><th>Under (<?= e($parentType['label']) ?>)</th><?php endif; ?><th>Code</th><th>Active</th><?php if ($tracked): ?><th>Used by</th><?php endif; ?><th>Actions</th></tr>
  <?php foreach ($values as $v): $use = $tracked ? lk_value_usage($t['type_key'], $v['code']) : null; ?>
  <tr>
    <td><strong><?= e($v['label']) ?></strong></td>
    <?php if ($parentType): ?><td><?= e($v['parent_value_id'] ? lk_value($v['parent_value_id'])['label'] ?? '—' : '—') ?></td><?php endif; ?>
    <td><?= $v['code'] !== '' ? '<code>' . e($v['code']) . '</code>' : '—' ?></td>
    <td><?= $v['active'] ? 'Yes' : 'No' ?></td>
    <?php if ($tracked): ?><td><?= $use === null ? '<span class="muted">—</span>' : ($use > 0 ? '<span class="pill p-warn">' . (int)$use . ' record' . ($use==1?'':'s') . '</span>' : '<span class="muted">0</span>') ?></td><?php endif; ?>
    <td class="row-actions">
      <a class="btn small" href="/lookup?key=<?= e($t['type_key']) ?>&edit=<?= (int)$v['id'] ?>">Edit</a>
      <a class="btn small danger" href="/lookup?key=<?= e($t['type_key']) ?>&del=<?= (int)$v['id'] ?>" onclick="return confirm(<?= ((int)$use > 0) ? "'" . (int)$use . " record(s) still use this value — they will show a raw code if you remove it. Remove anyway?'" : "'Remove this value?'" ?>)">Delete</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$values): ?><tr><td colspan="<?= ($parentType ? 5 : 4) + ($tracked ? 1 : 0) ?>">No values yet — add one below.</td></tr><?php endif; ?>
</table>

<h3 class="tab-sub" id="lkAdd"><?= $editRow ? 'Edit value' : 'Add a value' ?></h3>
<form method="post" action="/lookup?key=<?= e($t['type_key']) ?>" class="panel" id="lkAddForm">
  <?php if ($editRow): ?><input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
  <div class="form-grid">
    <div class="ff"><label>Value *</label><input class="form-control" name="label" required value="<?= e($editRow['label'] ?? '') ?>" placeholder="e.g. Premium"></div>
    <?php if ($parentType): ?>
    <div class="ff"><label>Under which <?= e($parentType['label']) ?>? *</label>
      <select class="form-control searchable" name="parent_value_id" required><option value="">—</option>
        <?php foreach ($parentValues as $pv): ?><option value="<?= (int)$pv['id'] ?>" <?= ($editRow && (int)$editRow['parent_value_id']===(int)$pv['id'])?'selected':'' ?>><?= e($pv['label']) ?><?= $pv['parent_value_id'] ? ' (' . e(lk_value($pv['parent_value_id'])['label'] ?? '') . ')' : '' ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="ff"><label>Code (optional)</label><input class="form-control" name="code" value="<?= e($editRow['code'] ?? '') ?>" placeholder="short code, if you use one"></div>
  </div>
  <div style="margin-top:14px;">
    <button class="btn" type="submit"><?= $editRow ? 'Save changes' : 'Add value' ?></button>
    <?php if ($editRow): ?><a class="btn secondary" href="/lookup?key=<?= e($t['type_key']) ?>">Cancel</a><?php endif; ?>
  </div>
</form>


<?php //  CONFIGURATION BELOW THE CONTENT (R-15). These two panels — where the
      //  list appears, and which module owns it — used to sit above the values,
      //  so opening a 40-value list showed two settings boxes first and the
      //  values after them. They are set once and rarely changed; the values are
      //  why people open this screen. Same panels, same forms, further down. ?>
<h3 class="tab-sub" style="margin-top:22px">List settings <span class="muted" style="font-weight:400">— set once, rarely changed</span></h3>
<details class="panel" <?= empty($shownOn) ? '' : 'open' ?> style="margin-bottom:14px">
  <summary style="cursor:pointer;font-weight:600">🖥️ Appears on these forms<?= $shownOn ? ' — ' . e(implode(', ', $shownOn)) : ' — not on any form yet' ?></summary>
  <form method="post" action="/lookup?key=<?= e($t['type_key']) ?>" style="margin-top:12px">
    <input type="hidden" name="set_forms" value="1">
    <?php lk_render_form_ticks($shownOn); ?>
    <small class="muted">The list shows as a dropdown on each ticked form. To make a field required, use <a href="/custom-fields">Custom fields</a>.</small>
    <div style="margin-top:12px"><button class="btn small" type="submit">Save where it appears</button></div>
  </form>
</details>

<?php // Which module this list belongs to — move it to another group here. ?>
<details class="panel" style="margin-bottom:14px">
  <summary style="cursor:pointer;font-weight:600">🗂️ Module — <?= e(function_exists('lk_module_group_label') ? lk_module_group_label($t['module'] ?? '') : ($t['module'] ?: 'General')) ?></summary>
  <form method="post" action="/lookup?key=<?= e($t['type_key']) ?>" style="margin-top:12px">
    <input type="hidden" name="set_module" value="1">
    <div class="ff" style="max-width:340px"><label>This list belongs to</label>
      <select class="form-control" name="module">
        <?php
          $cur = (string)($t['module'] ?? '');
          $modOpts = ['' => 'General'];
          foreach (['People', 'Directory', 'Sales', 'Operations', 'Reporting', 'Money'] as $mtag) {
              // Always include the list's current module even if that module is off,
              // so moving is never blocked; otherwise only offer enabled modules.
              if ($mtag !== $cur && function_exists('lk_group_enabled') && !lk_group_enabled($mtag)) continue;
              $modOpts[$mtag] = function_exists('lk_module_group_label') ? lk_module_group_label($mtag) : $mtag;
          }
          foreach ($modOpts as $mv => $ml) echo '<option value="' . e($mv) . '"' . ($mv === $cur ? ' selected' : '') . '>' . e($ml) . '</option>';
        ?>
      </select>
      <small class="muted">Changes which heading it sits under on the Masters screen.</small></div>
    <div style="margin-top:12px"><button class="btn small" type="submit">Save module</button></div>
  </form>
</details>

<script>
(function () {
  //  Jump to the add form and put the cursor in it, so "+ Add a value" is one
  //  tap rather than a tap and a scroll.
  var jump = document.getElementById('lkAddJump'), form = document.getElementById('lkAddForm');
  if (jump && form) jump.addEventListener('click', function () {
    setTimeout(function () { var f = form.querySelector('input[name="label"]'); if (f) f.focus(); }, 180);
  });

  //  Filter the values as you type. Rows only — never the header row.
  var box = document.getElementById('lkFind'), table = document.getElementById('lkTable'),
      count = document.getElementById('lkFindCount');
  if (!box || !table) return;
  var rows = [].slice.call(table.rows).slice(1);

  function apply() {
    var q = box.value.trim().toLowerCase(), hits = 0;
    rows.forEach(function (r) {
      var on = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
      r.style.display = on ? '' : 'none';
      if (on) hits++;
    });
    count.textContent = q ? hits + ' of ' + rows.length + ' shown' : '';
  }
  box.addEventListener('input', apply);
  box.addEventListener('keydown', function (e) { if (e.key === 'Escape') { box.value = ''; apply(); } });
})();
</script>
