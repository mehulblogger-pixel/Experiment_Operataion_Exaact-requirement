<?php
// Simple Sales — Sales settings. Only the NEW settings are edited here; the
// lists that already exist are linked to their own editors (nothing twice).
require_once dirname(__DIR__, 3) . '/views/ops/_sx_ui.php';
$links = [
  ['📣 Lead sources', '/lookup?key=lead_source', 'Where leads come from'],
  ['❌ Reasons for losing', '/lookup?key=quote_lost_reason', 'Why we lose'],
  ['🧑‍💼 Buying roles', '/lookup?key=contact_buying_role', 'A contact\'s part in a purchase'],
  ['📅 Public holidays', '/m/holidays', 'Skipped when follow-up dates are worked out'],
  ['✅ Who approves ' . Tlp('quote'), '/sales-setup', 'Set up Sales'],
];
?>
<div class="sx" style="max-width:1000px">
  <div class="sxhead"><h1>⚙️ Sales settings</h1></div>
  <p class="sxwhy"><b>Set once.</b> These decide how the Sales screens fill themselves in. Changes are recorded in the audit history.</p>
  <form method="post" action="/sales-settings">
    <div class="sxcard" style="margin-top:0"><h2>📝 What happened → next follow-up <?= $example ? '<span class="sxex">Example values — edit them</span>' : '' ?></h2>
      <p class="muted" style="margin:0 0 8px;font-size:13px">When someone logs a call and picks what happened, the next follow-up is set this many <b>working</b> days later. 0 = they pick the date.</p>
      <div class="tbl"><table class="sxt" id="sx_oc_t"><tr><th>What happened</th><th class="n">Working days</th><th></th></tr>
        <?php $i = 0; foreach (array_merge(array_values($outcomes), [['k' => '', 'label' => '', 'days' => 3]]) as $o): ?>
          <tr><td><input type="hidden" name="oc_key[<?= $i ?>]" value="<?= e($o['k']) ?>"><input class="form-control" name="oc_label[<?= $i ?>]" value="<?= e($o['label']) ?>" placeholder="<?= $o['k'] === '' ? 'Add another…' : '' ?>" aria-label="Outcome"></td>
            <td class="n" style="width:130px"><input class="form-control sxnum" type="number" min="0" max="365" name="oc_days[<?= $i ?>]" value="<?= (int) $o['days'] ?>" aria-label="Working days"></td>
            <td class="muted" style="font-size:12px"><?= $o['k'] !== '' ? 'empty the name to remove' : '' ?></td></tr>
        <?php $i++; endforeach; ?></table></div></div>

    <div class="sxgrid2" style="margin-top:12px">
      <div class="sxcard"><h2>📆 Weekly off</h2>
        <p class="muted" style="margin:0 0 8px;font-size:13px">Follow-ups never land on these days (or on a public holiday).</p>
        <div class="sxpick"><?php foreach (SX_WEEKDAYS as $d => $lab): ?><label><input type="checkbox" name="off[]" value="<?= $d ?>"<?= in_array($d, $off, true) ? ' checked' : '' ?>> <?= e($lab) ?></label><?php endforeach; ?></div>
        <h2 style="margin-top:16px">💤 A deal is "stale" after</h2>
        <div style="display:flex;gap:8px;align-items:center"><input class="form-control sxnum" style="max-width:110px" type="number" min="1" max="365" name="stale" value="<?= (int) $stale ?>" aria-label="Days"><span>days with no activity</span></div></div>
      <div class="sxcard"><h2>🏢 New leads go to</h2>
        <p class="muted" style="margin:0 0 8px;font-size:13px">A quick lead for an office goes to its salesperson. Empty = whoever types it. Each list shows only people who work for that office.</p>
        <div class="tbl"><table class="sxt"><?php foreach ($offices as $o): ?>
          <tr><td><?= e($o['name']) ?></td><td><select class="form-control" name="own[<?= (int) $o['id'] ?>]" aria-label="Salesperson for <?= e($o['name']) ?>"><option value="">— whoever types it —</option>
            <?php foreach ($people as $uid => $nm): if (function_exists('sx_user_covers_office') && !sx_user_covers_office($uid, (int) $o['id']) && (int) ($map[(string) (int) $o['id']] ?? 0) !== (int) $uid) continue;   /* only people of this office */ ?><option value="<?= (int) $uid ?>"<?= (int) ($map[(string) (int) $o['id']] ?? 0) === (int) $uid ? ' selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?></select></td></tr>
        <?php endforeach; ?></table></div></div>
    </div>
    <div class="sxactions"><button class="btn" type="submit">Save Sales settings</button></div>
  </form>

  <div class="sxgrid2" style="margin-top:14px">
    <div class="sxcard"><h2>🧭 Stages and win %</h2>
      <div class="tbl"><table class="sxt"><tr><th>Stage</th><th class="n">Win %</th></tr>
        <?php foreach ($stages as $s): ?><tr><td><?= e($s['name']) ?></td><td class="n"><?= (int) $s['probability'] ?>%</td></tr><?php endforeach; ?></table></div>
      <p style="margin:8px 0 0;font-size:13px"><?= $stageEditorOn ? '<a href="/pipelines">Change stages and win % ›</a>' : '<span class="muted">The stage editor is switched off (Modules &amp; features). Switch "Pipelines" on to change these.</span>' ?></p></div>
    <div class="sxcard"><h2>📋 Lists kept in their own place</h2><div class="tbl"><table class="sxt">
      <?php foreach ($links as [$t, $u, $s]): if (function_exists('feat_on') && !feat_on($u)) continue; ?><tr><td><a href="<?= e($u) ?>"><?= e($t) ?></a></td><td class="muted"><?= e($s) ?></td></tr><?php endforeach; ?></table></div></div>
  </div>
</div>
