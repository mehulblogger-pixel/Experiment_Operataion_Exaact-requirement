<?php
  // Every dropdown in the app, grouped by the module it belongs to so an admin
  // can find the one they mean without knowing its internal key.
  $groups = [];
  foreach ($types as $t) $groups[$t['module'] ?: 'Other'][] = $t;
  $order = ['Sales','Operations','Reporting','People','Money','Directory','Other'];
  uksort($groups, function($a, $b) use ($order) {
      $ia = array_search($a, $order, true); $ib = array_search($b, $order, true);
      return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
  });
  $counts = [];
  foreach (ops_all("SELECT type_id, COUNT(*) c FROM lookup_values GROUP BY type_id") as $r) $counts[(int)$r['type_id']] = (int)$r['c'];
  $q = trim($_GET['q'] ?? '');
?>
<div class="crumbs"><a href="/">Home</a> › Masters</div>
<div class="master-head">
  <div><h1>Masters</h1>
    <p class="sub" style="margin:2px 0 0">Every dropdown in the app, grouped by module. Edit the values, add your own list, and tick which forms it shows on — all in one place. A dependent list filters by a parent list's value. <?= count($types) ?> lists, <?= array_sum($counts) ?> values.</p></div>
</div>

<form method="get" action="/lookups" class="panel" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <div class="ff" style="margin:0;flex:1;min-width:240px"><label>Find a list</label>
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="e.g. status, result, unit, designation"></div>
  <button class="btn small secondary" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn small secondary" href="/lookups">Clear</a><?php endif; ?>
</form>

<?php // The "add a list" form lives at the top, right under the search, so a new
      // list can be started without scrolling past every existing one. ?>
<details class="panel" open style="margin-bottom:14px">
  <summary style="cursor:pointer;font-weight:600">➕ Add a new master list</summary>
  <form method="post" action="/lookups" style="margin-top:12px">
    <div class="form-grid">
      <div class="ff"><label>List name *</label><input class="form-control" name="label" placeholder="e.g. Activity code, Product family, Service type" required></div>
      <div class="ff"><label>Short key (auto if blank)</label><input class="form-control" name="type_key" placeholder="e.g. activity"></div>
      <div class="ff"><label>Depends on (optional — makes it a dependent list)</label>
        <select class="form-control searchable" name="parent_type_id"><option value="">— none (top-level list) —</option>
          <?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['label']) ?></option><?php endforeach; ?>
        </select>
        <small class="muted">Pick a parent to make each value belong under a parent value (e.g. Activity under <?= e(T('sbu')) ?>).</small></div>
    </div>
    <?php // The "which form?" question, answered right here. Tick the forms this
          // list should appear on and it is added to them — no separate custom-field
          // step. Leave all unticked to make a list you will attach later. ?>
    <div class="ff" style="margin-top:6px"><label>Show this list on <span class="muted">(optional — you can change this later)</span></label>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px">
        <?php foreach (lk_form_targets() as $en => $lbl): ?>
          <label class="chk" style="font-weight:400"><input type="checkbox" name="forms[]" value="<?= e($en) ?>"> <?= e($lbl) ?> form</label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Ticking a form adds this list as a dropdown on it automatically. Other forms are still available under Custom fields.</small></div>
    <div style="margin-top:14px;"><button class="btn" type="submit">Create list</button></div>
  </form>
</details>

<?php foreach ($groups as $gname => $list):
      if ($q !== '') {
          $list = array_values(array_filter($list, fn($t) =>
              stripos($t['label'], $q) !== false || stripos($t['type_key'], $q) !== false));
          if (!$list) continue;
      } ?>
  <h3 class="tab-sub"><?= e($gname) ?> <span class="muted">(<?= count($list) ?>)</span></h3>
  <div class="panel" style="padding:0;overflow:hidden">
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="dt">
      <thead><tr><th>List</th><th>Key</th><th>Depends on</th><th class="num">Values</th><th>Shows on</th><th>Type</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $t): $parent = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null; ?>
      <tr>
        <td><a href="/lookup?key=<?= e($t['type_key']) ?>"><strong><?= e($t['label']) ?></strong></a></td>
        <td><code><?= e($t['type_key']) ?></code></td>
        <td><?= $parent ? e($parent['label']) : '—' ?></td>
        <td class="num"><?= (int)($counts[(int)$t['id']] ?? 0) ?></td>
        <td><?php $shown = $formsByType[(int)$t['id']] ?? [];
              echo $shown ? e(implode(', ', $shown)) : '<span class="muted">—</span>'; ?></td>
        <td><?= $t['is_system'] ? '<span class="pill p-info">Built-in</span>' : '<span class="pill p-ok">Custom</span>' ?></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn small secondary" href="/lookup?key=<?= e($t['type_key']) ?>">Edit values</a>
          <?php if (!$t['is_system'] || is_master()): ?>
            <a class="btn small danger" href="/lookups?del=<?= (int)$t['id'] ?>" onclick="return confirm('Delete this whole list and its values?<?= $t['is_system'] ? ' Built-in dropdowns fall back to their shipped values.' : '' ?>')">Delete</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endforeach; ?>
