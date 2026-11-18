<?php
// Simple Sales — Log activity: what happened + outcome → the next follow-up
// date in working days (Sales settings). Writes to the lead's or deal's own
// timeline, the same records its own page writes.
require __DIR__ . '/_sx_ui.php';
$v = fn($k, $d = '') => e((string) ($old[$k] ?? $d));
$selM = (string) ($old['method'] ?? 'CALL'); $selO = (string) ($old['outcome'] ?? '');
$icons = ['CALL' => '📞', 'WHATSAPP' => '💬', 'EMAIL' => '✉️', 'MEETING' => '🤝', 'VISIT' => '🚗', 'NOTE' => '📝'];
?>
<div class="sx" style="max-width:860px">
  <div class="sxhead"><h1>📝 Log activity</h1><a class="sxchip" href="<?= e($t['url']) ?>">Open <?= $t['kind'] === 'LEAD' ? 'lead' : 'deal' ?> ›</a></div>
  <div class="sxside" style="margin-top:6px"><div><span><?= $t['kind'] === 'LEAD' ? 'Lead' : 'Deal' ?> <?= e($t['ref']) ?></span><strong><?= e($t['name']) ?></strong></div>
    <div><span>Stage</span><strong style="font-size:15px"><?= e($t['stage'] ?: '—') ?></strong></div>
    <div><span>Next step now</span><strong style="font-size:15px"><?= e($t['next'] ?: '—') ?><?= $t['next_on'] !== '' ? ' · ' . e(fdate($t['next_on'])) : '' ?></strong></div>
    <?php if ($t['phone'] !== ''): ?><div style="margin-left:auto"><?= sx_contact_btns($t['phone'], $t['name']) ?></div><?php endif; ?></div>
  <?php if (!$t['open']): ?><div class="sxwarn">This <?= $t['kind'] === 'LEAD' ? 'lead' : 'deal' ?> is closed. You can still record a conversation.</div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="sxerr" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" action="/sales-log" class="sxcard" style="margin-top:0">
    <input type="hidden" name="<?= $t['kind'] === 'LEAD' ? 'lead' : 'deal' ?>" value="<?= (int) $t['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <fieldset style="border:0;padding:0;margin:0 0 12px"><legend class="sxlab" style="margin:0 0 6px">1 · How</legend>
      <div class="sxpick"><?php foreach ($methods as $k => $lab): ?><label><input type="radio" name="method" value="<?= e($k) ?>"<?= $selM === $k ? ' checked' : '' ?>> <?= ($icons[$k] ?? '') . ' ' . e($lab) ?></label><?php endforeach; ?></div></fieldset>
    <fieldset style="border:0;padding:0;margin:0 0 12px"><legend class="sxlab" style="margin:0 0 6px">2 · What happened</legend>
      <div class="sxpick" id="sx_oc"><?php foreach ($outcomes as $k => $o): ?>
        <label><input type="radio" name="outcome" value="<?= e($k) ?>" data-date="<?= e($dates[$k] ?? '') ?>"<?= $selO === $k ? ' checked' : '' ?>> <?= e($o['label']) ?> <small class="muted"><?= $o['days'] ? '→ ' . (int) $o['days'] . ' working day' . ($o['days'] === 1 ? '' : 's') : '→ you pick' ?></small></label>
      <?php endforeach; ?></div></fieldset>
    <div class="sxform">
      <div class="wide"><label for="lg_note">Note (what was said)</label><textarea id="lg_note" class="form-control" name="note" rows="3" placeholder="e.g. Wants rates for 2 inspectors from January"><?= $v('note') ?></textarea></div>
      <div><label for="lg_who">Spoke with</label><input id="lg_who" class="form-control" name="with_whom" value="<?= $v('with_whom', $t['who']) ?>"></div>
      <div><label for="lg_na">Next step</label><input id="lg_na" class="form-control" name="next_action" value="<?= $v('next_action') ?>" placeholder="e.g. Send revised quote"></div>
      <div><label for="lg_on">Next follow-up</label><input id="lg_on" class="form-control" type="date" name="next_action_on" min="<?= e(date('Y-m-d')) ?>" value="<?= $v('next_action_on') ?>"><small>Filled in from what happened — weekly off and holidays skipped. Change it if you agreed a date.</small></div>
    </div>
    <div class="sxactions"><button class="btn" type="submit">Save</button><a class="btn ghost" href="<?= e($back) ?>">Cancel</a></div>
  </form>
  <?php if ($recent): ?><div class="sxcard"><h2>Last conversations</h2><div class="tbl"><table class="sxt">
    <?php foreach ($recent as $a): ?><tr><td class="nw"><?= e(fdate(substr((string) $a['occurred_at'], 0, 10))) ?></td><td><?= e(LEAD_CONTACT_METHODS[$a['kind']] ?? $a['kind']) ?></td><td><?= e(trim(($a['outcome'] ?? '') . (($a['body'] ?? '') !== '' ? ' — ' . $a['body'] : ''), ' —')) ?></td><td class="muted"><?= e($a['owner'] ?? '') ?></td></tr><?php endforeach; ?>
  </table></div></div><?php endif; ?>
</div>
<script>
(function () { var box = document.getElementById('sx_oc'), on = document.getElementById('lg_on'); if (!box || !on) return;
  box.addEventListener('change', function (e) { var d = e.target && e.target.getAttribute('data-date'); if (e.target.name === 'outcome') { on.value = d || ''; if (!d) on.focus(); } }); })();
</script>
