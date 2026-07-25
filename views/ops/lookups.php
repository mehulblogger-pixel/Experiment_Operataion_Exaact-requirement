<div class="master-head">
  <div><h1>Masters</h1><p class="sub">Every dropdown in the app. Edit values, or add your own list — dependent lists filter by a parent list's value.</p></div>
</div>

<table class="grid">
  <tr><th>List</th><th>Key</th><th>Depends on</th><th>Values</th><th>Type</th><th>Actions</th></tr>
  <?php foreach ($types as $t): $parent = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null; ?>
  <tr>
    <td><a href="/lookup?key=<?= e($t['type_key']) ?>"><strong><?= e($t['label']) ?></strong></a></td>
    <td><code><?= e($t['type_key']) ?></code></td>
    <td><?= $parent ? e($parent['label']) : '—' ?></td>
    <td><?= (int)ops_val("SELECT COUNT(*) FROM lookup_values WHERE type_id=?", [$t['id']]) ?></td>
    <td><?= $t['is_system'] ? '<span class="badge">Built-in</span>' : '<span class="badge GREEN">Custom</span>' ?></td>
    <td class="row-actions">
      <a class="btn small" href="/lookup?key=<?= e($t['type_key']) ?>">Edit values</a>
      <?php if (!$t['is_system'] || is_master()): ?>
        <a class="btn small danger" href="/lookups?del=<?= (int)$t['id'] ?>" onclick="return confirm('Delete this whole list and its values?<?= $t['is_system']?' Built-in dropdowns will fall back to defaults.':'' ?>')">Delete</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h3 class="tab-sub">Add a new master list</h3>
<form method="post" action="/lookups" class="panel">
  <div class="form-grid">
    <div class="ff"><label>List name *</label><input class="form-control" name="label" placeholder="e.g. Activity code, Product family, Service type" required></div>
    <div class="ff"><label>Short key (auto if blank)</label><input class="form-control" name="type_key" placeholder="e.g. activity"></div>
    <div class="ff"><label>Depends on (optional — makes it a dependent list)</label>
      <select class="form-control searchable" name="parent_type_id"><option value="">— none (top-level list) —</option>
        <?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['label']) ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Pick a parent to make each value belong under a parent value (e.g. Activity under SBU).</small></div>
  </div>
  <div style="margin-top:14px;"><button class="btn" type="submit">Create list</button></div>
</form>
