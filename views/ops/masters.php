<h1>Master data</h1>
<p class="sub">The reference lists the Calls and Jobs screens draw from. Add or edit these first.</p>
<div class="card-grid">
  <?php foreach ($masters as $key => $cfg): if (!master_access_ok($cfg['access'])) continue; ?>
    <a class="master-card" href="/m/<?= e($key) ?>">
      <strong><?= e($cfg['label']) ?></strong>
      <span class="muted"><?= (int)ops_val("SELECT COUNT(*) FROM {$cfg['table']}") ?> record(s)</span>
    </a>
  <?php endforeach; ?>
  <a class="master-card" href="/clients"><strong>Clients</strong><span class="muted">Customer master</span></a>
  <a class="master-card" href="/vendors"><strong>Vendors</strong><span class="muted">Manufacturer / supplier master</span></a>
</div>

<?php if (is_admin_level()): ?>
<h3 class="tab-sub" style="margin-top:26px;">Dropdown lists (edit the values behind every dropdown)</h3>
<p class="sub">These power the dropdowns on the Call and Job screens — SBU, Region, Activity, and the rest. Click one to edit its values.</p>
<div class="card-grid">
  <?php foreach (lk_types() as $t): $parent = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null; ?>
    <a class="master-card" href="/lookup?key=<?= e($t['type_key']) ?>">
      <strong><?= e($t['label']) ?></strong>
      <span class="muted"><?= (int)ops_val("SELECT COUNT(*) FROM lookup_values WHERE type_id=?", [$t['id']]) ?> value(s)<?= $parent ? ' · under ' . e($parent['label']) : '' ?></span>
    </a>
  <?php endforeach; ?>
</div>

<h3 class="tab-sub" style="margin-top:26px;">Make it your own (admin)</h3>
<div class="card-grid">
  <a class="master-card" href="/lookups"><strong>⚙️ All master lists</strong><span class="muted">Add a new list, or a dependent list (e.g. SBU → Activity)</span></a>
  <a class="master-card" href="/custom-fields?entity=call"><strong>➕ Custom fields — Calls</strong><span class="muted">Add your own fields to the Call form</span></a>
  <a class="master-card" href="/custom-fields?entity=job"><strong>➕ Custom fields — Jobs</strong><span class="muted">Add your own fields to the Job form</span></a>
</div>
<?php endif; ?>
