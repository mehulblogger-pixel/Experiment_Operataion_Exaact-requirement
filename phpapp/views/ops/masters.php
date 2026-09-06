<div class="crumbs"><a href="/">Home</a> › Masters</div>
<div class="master-head">
  <div><h1>Masters</h1>
    <p class="sub" style="margin:2px 0 0">The ready-made answers your forms offer. Set them up once, and everyone picks from the same list instead of re-typing.</p></div>
</div>

<?php // A plain-English map of the whole screen, so the three kinds of "master"
      // stop looking like the same thing. Each card below sits under one of these. ?>
<div class="panel" style="margin-bottom:18px">
  <strong>How this fits together — three layers</strong>
  <div class="card-grid" style="margin-top:10px">
    <div class="master-card" style="cursor:default">
      <strong>1 · Records you keep</strong>
      <span class="muted">Real things and people — your offices, staff, <?= e(Tl('client')) ?>s and <?= e(Tl('vendor')) ?>s. You add and edit these one by one.</span>
    </div>
    <div class="master-card" style="cursor:default">
      <strong>2 · Dropdown lists</strong>
      <span class="muted">The choices behind every dropdown — <?= e(Tl('sbu')) ?>, Region, Activity, statuses. Edit the choices, or make your own list.</span>
    </div>
    <div class="master-card" style="cursor:default">
      <strong>3 · Extra fields on a form</strong>
      <span class="muted">A box that isn't there yet? Add your own field to a form — plain text, a date, or one of your dropdown lists.</span>
    </div>
  </div>
</div>

<h3 class="tab-sub">1 · Records you keep</h3>
<p class="sub">The people and places your work refers to. Open one to add or edit its records.</p>
<div class="card-grid">
  <?php // Some of these are the same records the Organisation module maintains.
        // Two editors over one table is how the same office ends up with two
        // versions of itself, so those cards send you to the one place that
        // owns them instead of opening a second form over the top. ?>
  <?php foreach ($masters as $key => $cfg): if (!master_access_ok($cfg['access'])) continue;
        if (!master_card_shown($key)) continue;   // hide records for modules not in this plan
        $n = (int)ops_val("SELECT COUNT(*) FROM {$cfg['table']}"); ?>
    <?php if (!empty($cfg['goto'])): ?>
      <a class="master-card" href="<?= e($cfg['goto']) ?>">
        <strong><?= e($cfg['label']) ?></strong>
        <span class="muted"><?= $n ?> record(s) · <?= e($cfg['goto_note'] ?? 'maintained elsewhere') ?> →</span>
      </a>
    <?php else: ?>
      <a class="master-card" href="/m/<?= e($key) ?>">
        <strong><?= e($cfg['label']) ?></strong>
        <span class="muted"><?= $n ?> record(s)</span>
      </a>
    <?php endif; ?>
  <?php endforeach; ?>
  <a class="master-card" href="/clients"><strong><?= e(TP('client')) ?></strong><span class="muted"><?= e(T('client')) ?> master</span></a>
  <a class="master-card" href="/vendors"><strong><?= e(TP('vendor')) ?></strong><span class="muted">Manufacturer / supplier master</span></a>
  <a class="master-card" href="/work-norms"><strong>🕔 Working norms</strong><span class="muted">Weekly days &amp; hours per designation / office</span></a>
  <a class="master-card" href="/agency-staff"><strong>🧑‍🔧 Agency staff</strong><span class="muted">Freelancers / sub-contractors by agency, with their documents</span></a>
  <?php if (master_card_shown('asset-register')): ?>
  <a class="master-card" href="/asset-register"><strong>📦 Asset issuance</strong><span class="muted">Stamps, diaries, safety gear &amp; devices issued to engineers</span></a>
  <?php endif; ?>
</div>

<?php if (is_admin_level()): ?>
<h3 class="tab-sub" style="margin-top:26px;">2 · Dropdown lists</h3>
<p class="sub">The choices behind every dropdown, <strong>grouped by the part of the system they belong to</strong>. Click a list to edit its choices, or use <strong>All master lists</strong> to add a new one and tick which forms it appears on.</p>
<div class="card-grid" style="margin-bottom:6px">
  <a class="master-card" href="/lookups" style="border:1px solid var(--brand)">
    <strong>⚙️ All master lists →</strong>
    <span class="muted">Add a list, add a dependent list, and choose which forms it shows on — one place.</span>
  </a>
</div>
<?php
  // Group the lists by module, and split into the groups this install actually
  // has vs. the ones belonging to modules it did not buy (shown, collapsed, only
  // so nothing looks lost). A helper to render one group's cards.
  $renderGroup = function($tag, $rows) {
      echo '<h4 style="margin:16px 0 6px;font-size:14px">' . e(lk_module_group_label($tag))
         . ' <span class="muted" style="font-weight:400">· ' . count($rows) . ' list(s)</span></h4>';
      echo '<div class="card-grid">';
      foreach ($rows as $t) {
          $parent = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null;
          $n = (int)ops_val("SELECT COUNT(*) FROM lookup_values WHERE type_id=?", [$t['id']]);
          echo '<a class="master-card" href="/lookup?key=' . e($t['type_key']) . '"><strong>'
             . e($t['label']) . '</strong><span class="muted">' . $n . ' choice(s)'
             . ($parent ? ' · under ' . e($parent['label']) : '') . '</span></a>';
      }
      echo '</div>';
  };
  $grouped = lk_types_grouped();
  $offGroups = [];
  foreach ($grouped as $tag => $rows) {
      if (lk_group_enabled($tag)) $renderGroup($tag, $rows);
      else $offGroups[$tag] = $rows;
  }
?>
<?php if ($offGroups): ?>
  <details style="margin-top:14px">
    <summary style="cursor:pointer;color:var(--muted);font-size:13.5px;padding:6px 0">
      Show lists from modules not in this plan (<?= array_sum(array_map('count', $offGroups)) ?> more)
    </summary>
    <div style="opacity:.85;margin-top:4px">
      <?php foreach ($offGroups as $tag => $rows) $renderGroup($tag, $rows); ?>
    </div>
  </details>
<?php endif; ?>

<h3 class="tab-sub" style="margin-top:26px;">3 · Extra fields on a form</h3>
<p class="sub">Add a field the form doesn't have yet. It appears on that form automatically. A dropdown field can use any list from layer 2. Only the forms this plan includes are shown.</p>
<?php
  // Show custom-field targets grouped, hiding whole groups / forms that belong
  // to a module this install did not buy (so recruitment offers Requisition,
  // Candidate and Client/Vendor, not the inspection Call / Job / Sample forms).
  $cfGroups = function_exists('lk_form_target_groups') ? lk_form_target_groups() : [];
  foreach ($cfGroups as $groupName => $forms):
      $shown = array_filter($forms, fn($lbl, $ent) => cf_target_shown($ent), ARRAY_FILTER_USE_BOTH);
      if (!$shown) continue; ?>
  <h4 style="margin:14px 0 6px;font-size:14px"><?= e($groupName) ?></h4>
  <div class="card-grid">
    <?php foreach ($shown as $entity => $label): ?>
      <a class="master-card" href="/custom-fields?entity=<?= e($entity) ?>">
        <strong>➕ Fields on the <?= e($label) ?> form</strong>
        <span class="muted">Add your own boxes to the <?= e($label) ?> form</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php endif; ?>
