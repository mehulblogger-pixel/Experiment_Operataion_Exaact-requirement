<?php
// ============================================================================
//  Sales settings — Stage 6 of the Sales redesign (owner, 21 Nov 2026: approved
//  design pack, "All as recommended"). Only the Sales settings are edited here;
//  lists that already have their own editor are linked to it (nothing twice).
//  Every field name, limit and rule is the one it had (the old page is kept in
//  tests/fixtures/pre_stage6/sx_settings.php); the handler is unchanged.
//
//  DEF-13: a link to another screen is drawn only when this person may open
//  that screen — the same questions the screen itself asks (its module gate in
//  ops_route_module_map(), its feature switch, and its own check). The lists a
//  person may not open are named, with who keeps them, instead of a dead link.
// ============================================================================
require_once __DIR__ . '/_sxw_ui.php';

//  "Would this screen open for me?" — its module gate (ops_route_module_map()
//  and the licence), its feature switch, then its own handler's check.
$__s6open = function ($route, $own) {
    $base = (strncmp($route, 'm/', 2) === 0) ? 'masters' : $route;
    $mod = function_exists('ops_route_module_map') ? (ops_route_module_map()[$base] ?? null) : null;
    if ($mod === null && function_exists('ops_module_family')) $mod = ops_module_family($base);
    if ($mod && !can("mod.$mod.view")) return false;
    if (function_exists('ops_module_gate') && !ops_module_gate($route, true)) return false;
    if (function_exists('feat_on') && !feat_on('/' . $route)) return false;
    return (bool) $own;
};
$isAdm  = function_exists('is_admin_level') && is_admin_level();
$links = [
  //  [label, icon, url, what it is, may this person open it, who keeps it otherwise]
  ['Lead sources', 'tag', '/lookup?key=lead_source', 'Where leads come from', $__s6open('lookup', $isAdm), 'an administrator (Admin → Lists)'],
  ['Reasons for losing', 'x', '/lookup?key=quote_lost_reason', 'Why we lose', $__s6open('lookup', $isAdm), 'an administrator (Admin → Lists)'],
  ['Buying roles', 'users', '/lookup?key=contact_buying_role', 'A contact\'s part in a purchase', $__s6open('lookup', $isAdm), 'an administrator (Admin → Lists)'],
  ['Public holidays', 'cal', '/m/holidays', 'Skipped when follow-up dates are worked out', $__s6open('m/holidays', function_exists('master_access_ok') ? master_access_ok('admin') : $isAdm), 'an administrator (Admin → Masters)'],
  ['Who approves ' . Tlp('quote'), 'lock', '/sales-setup', 'Set up Sales', $__s6open('sales-setup', is_master() || can('settings.manage') || can('crm.quote.approve')), 'whoever may approve ' . Tlp('quote') . ' (Set up Sales)'],
];
$stageLink = $stageEditorOn && $__s6open('pipelines', (!function_exists('pipe_can_view') || pipe_can_view()));
?>
<div class="rec sxw has-bn sxs6">
  <?php sxw_nav('/sales-settings'); ?>
  <?php sxw_head('Sales settings', 'Set once. These decide how the Sales screens fill themselves in: how follow-ups are dated, which days are off, when a deal counts as stale and who gets new leads. Changes are recorded in the audit history.', 'cal'); ?>

  <form method="post" action="/sales-settings">
  <div class="sxs6-g">
    <div class="sxs6-c">
      <?php rec_card_open('What happened → next follow-up', 'note', $example ? '<span class="rec-tag warn">Example values — edit them</span>' : ''); ?>
        <p class="rec-note" style="margin:0 0 8px">When someone logs a call and picks what happened, the next follow-up is set this many <b>working</b> days later. 0 = they pick the date.</p>
        <div class="tbl"><table class="rec-tbl sxs6-oc" id="sx_oc_t"><thead><tr><th scope="col">What happened</th><th scope="col" class="n">Working days</th><th scope="col"><span class="sr-only">Note</span></th></tr></thead><tbody>
          <?php $i = 0; foreach (array_merge(array_values($outcomes), [['k' => '', 'label' => '', 'days' => 3]]) as $o): ?>
            <tr><td><input type="hidden" name="oc_key[<?= $i ?>]" value="<?= e($o['k']) ?>"><input class="form-control" name="oc_label[<?= $i ?>]" value="<?= e($o['label']) ?>" placeholder="<?= $o['k'] === '' ? 'Add another…' : '' ?>" aria-label="Outcome"></td>
              <td class="n"><input class="form-control sxnum" type="number" min="0" max="365" name="oc_days[<?= $i ?>]" value="<?= (int) $o['days'] ?>" aria-label="Working days"></td>
              <td class="muted sxs6-hint"><?= $o['k'] !== '' ? 'empty the name to remove' : '' ?></td></tr>
          <?php $i++; endforeach; ?></tbody></table></div>
        <p class="rec-note">Empty a name to remove it. 0 days = the person picks the date. Keep at least one.</p>
      <?php rec_card_close(); ?>

      <?php rec_card_open('Stages and win %', 'flow'); ?>
        <div class="tbl"><table class="rec-tbl"><thead><tr><th scope="col">Stage</th><th scope="col" class="n">Win %</th></tr></thead><tbody>
          <?php foreach ($stages as $s): ?><tr><td><?= e($s['name']) ?></td><td class="n"><?= (int) $s['probability'] ?>%</td></tr><?php endforeach; ?></tbody></table></div>
        <p class="rec-note"><?php if ($stageLink): ?><a class="sxw-lnk" href="/pipelines">Change stages and win % <?= rec_icon('right', 's') ?></a>
          <?php elseif (!$stageEditorOn): ?>The stage editor is switched off (Modules &amp; features). Switch "Pipelines" on to change these.
          <?php else: ?>Stages are changed under Pipelines &amp; funnels, by people who may change pipelines.<?php endif; ?></p>
      <?php rec_card_close(); ?>

      <?php rec_card_open('Lists kept in their own place', 'list'); ?>
        <?php $open = array_values(array_filter($links, fn($l) => $l[4] && (!function_exists('feat_on') || feat_on($l[2]))));
              $shut = array_values(array_filter($links, fn($l) => !$l[4])); ?>
        <?php if ($open): ?><div class="sxs6-pills"><?php foreach ($open as [$t, $ic, $u, $s]): ?>
          <a class="sxs6-pill" href="<?= e($u) ?>" title="<?= e($s) ?>"><?= rec_icon($ic, 's') ?><?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?>
        <?php if ($shut): ?><p class="rec-note"><?php foreach ($shut as $k => [$t, , , , , $who]): ?><?= $k ? '<br>' : '' ?><b><?= e($t) ?></b> — kept by <?= e($who) ?>; ask them to change it.<?php endforeach; ?></p><?php endif; ?>
      <?php rec_card_close(); ?>
    </div>

    <div class="sxs6-c">
      <?php rec_card_open('Weekly off', 'cal'); ?>
        <div class="sxs6-days" role="group" aria-label="Weekly off"><?php foreach (SX_WEEKDAYS as $d => $lab): ?><label><input type="checkbox" name="off[]" value="<?= $d ?>"<?= in_array($d, $off, true) ? ' checked' : '' ?>><span><?= e($lab) ?></span></label><?php endforeach; ?></div>
        <p class="rec-note">Follow-ups never land on these days (or on a public holiday). None ticked = works every day.</p>
      <?php rec_card_close(); ?>

      <?php rec_card_open('Stale deals', 'hour'); ?>
        <label class="sxs6-lab" for="sx6_stale">A deal is "stale" after</label>
        <div class="sxs6-inl"><input id="sx6_stale" class="form-control sxnum" type="number" min="1" max="365" name="stale" value="<?= (int) $stale ?>" aria-label="Days"><span>days with no activity</span></div>
      <?php rec_card_close(); ?>

      <?php //  DEF-10 (owner, 21 Nov 2026): ONE renewal window for Renewals, Today and Sales home.
            if (isset($renewDays)): rec_card_open('Renewals', 'cal'); ?>
        <label class="sxs6-lab" for="sx6_renew">Show contracts ending within</label>
        <div class="sxs6-inl"><input id="sx6_renew" class="form-control sxnum" type="number" min="7" max="365" name="renew_days" value="<?= (int) $renewDays ?>" aria-label="Days"><span>days</span></div>
        <p class="rec-note">Used by Renewals, Today and Sales home. Only open contracts are listed.<?= !empty($renewExample) ? ' <b>60 is an example</b> — change it to suit you.' : '' ?></p>
      <?php rec_card_close(); endif; ?>

      <?php rec_card_open('New leads go to', 'building'); ?>
        <p class="rec-note" style="margin:0 0 8px">A quick lead for an office goes to its salesperson. Empty = whoever types it. Each list shows only people who work for that office.</p>
        <div class="tbl"><table class="rec-tbl"><thead><tr><th scope="col">Branch</th><th scope="col">Person</th></tr></thead><tbody><?php foreach ($offices as $o): ?>
          <tr><td><?= e($o['name']) ?></td><td><select class="form-control" name="own[<?= (int) $o['id'] ?>]" aria-label="Salesperson for <?= e($o['name']) ?>"><option value="">— whoever types it —</option>
            <?php foreach ($people as $uid => $nm): if (function_exists('sx_user_covers_office') && !sx_user_covers_office($uid, (int) $o['id']) && (int) ($map[(string) (int) $o['id']] ?? 0) !== (int) $uid) continue;   // only people of this office ?><option value="<?= (int) $uid ?>"<?= (int) ($map[(string) (int) $o['id']] ?? 0) === (int) $uid ? ' selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?></select></td></tr>
        <?php endforeach; ?></tbody></table></div>
        <p class="rec-note">Only people who work for that branch can be picked.</p>
      <?php rec_card_close(); ?>
    </div>
  </div>

  <div class="sxs6-bar"><span>Changes go on the settings audit trail.</span><button class="sxw-bt p" type="submit">Save Sales settings</button></div>
  </form>
</div>
<?php sxw_bottom('/sales-settings'); ?>
