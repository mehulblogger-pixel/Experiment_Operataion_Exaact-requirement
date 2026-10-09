<?php
// ============================================================================
//  THE PERMISSION VERB GRID — one implementation, both screens        (R-20)
// ============================================================================
//  Rendered by BOTH the role editor (/access) and the per-user editor
//  (/user-edit). It is a partial rather than two copies because R-14 was caused
//  by exactly that: two permission screens drifting apart until recruitment had
//  one name on one and another name on the other, and the owner read the two
//  side by side and concluded the system held two different objects.
//
//  Caller sets, before including:
//    $pvModules  string[]           module keys to render, in order
//    $pvHas      fn(string): bool   is this permission currently held?
//    $pvField    fn(string): string the name= and value= attributes for a box
//    $pvAllow    fn(string): bool   may this manager grant this permission?
//    $pvRec      fn(string): bool   optional — show the "recommended" pill
//    $pvGroup    string             optional — heading for this block
//
//  The markup is ONE structure styled two ways (see _perm_verb_grid_assets.php):
//  a scannable grid on a laptop, and a card of large labelled switches on a
//  phone. Deliberately not a squeezed table — six columns at 390px is
//  unreadable, and CLAUDE.md forbids averaging a desk screen and a phone screen
//  into one middle. This screen is desk-first, but it must still work in a hand.
$pvRec = $pvRec ?? fn($k) => false;
?>
<div class="vgrid-block mgroup">
<?php if (!empty($pvGroup)): ?>
  <?php // The heading sits ABOVE the table, not in a row inside it. Rendered as a
        // row, the column headers printed before the group name, so each header
        // looked like it belonged to the previous group's list of permissions
        // rather than to the modules underneath it. ?>
  <div class="vgrid-head">
    <span class="vgrid-title"><?= e($pvGroup) ?></span>
    <label class="chk vgrid-allof"><input type="checkbox" class="grp-all"> All of <?= e($pvGroup) ?></label>
  </div>
<?php endif; ?>
<div class="tbl-scroll vgrid-wrap">
<table class="dt vgrid">
  <thead><tr>
    <th>Module</th>
    <?php foreach (PERM_VERBS as $v): ?>
    <th class="vh" title="<?= e(perm_verb_help($v)) ?>">
      <label class="chk vcol"><input type="checkbox" class="col-all" data-verb="<?= e($v) ?>"> <?= e(perm_verb_label($v)) ?></label>
    </th>
    <?php endforeach; ?>
  </tr></thead>
  <tbody>
    <?php foreach ($pvModules as $k): $verbs = access_module_verbs($k); ?>
    <tr class="vrow">
      <td class="vname">
        <label class="chk rowall"><input type="checkbox" class="row-all"> <b><?= e(access_module_label($k)) ?></b></label>
        <?= $pvRec("mod.$k.view") ? ' <span class="pill p-ok" style="padding:0 5px;font-size:10px">recommended</span>' : '' ?>
      </td>
      <?php foreach (PERM_VERBS as $v):
        $key = perm_verb_key($k, $v);
        // Two different reasons for a cell to carry no box, and they must not
        // look the same. "—" means this module has no such act — nothing in a
        // holiday list is ever signed off. A locked cell means the act exists
        // but this manager may not hand it out. An empty checkbox for either
        // would read as "switched off", which is a different thing again.
        $applies = in_array($v, $verbs, true);
        $mayGive = $applies && $pvAllow($key);
        if (!$applies): ?>
        <td class="vcell vna" data-v="<?= e(perm_verb_label($v)) ?>"><span class="muted" title="Nothing in <?= e(access_module_label($k)) ?> is signed off">—</span></td>
      <?php elseif (!$mayGive): ?>
        <td class="vcell vlock" data-v="<?= e(perm_verb_label($v)) ?>"><span class="muted" title="Only a global administrator can grant this">🔒</span></td>
      <?php else: ?>
        <td class="vcell" data-v="<?= e(perm_verb_label($v)) ?>">
          <input type="checkbox" class="perm-box role-perm vbox" data-verb="<?= e($v) ?>" data-mod="<?= e($k) ?>"
                 <?= $pvField($key) ?> <?= $pvHas($key) ? 'checked' : '' ?>
                 aria-label="<?= e(perm_verb_label($v) . ' — ' . access_module_label($k)) ?>">
        </td>
      <?php endif; endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>
