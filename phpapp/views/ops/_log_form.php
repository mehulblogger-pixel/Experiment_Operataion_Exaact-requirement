<?php
// THE one "log a conversation" form (owner, 19 Nov 2026: Today's Log button
// opened a different form from the lead page — there must be one). Used on the
// lead page, the deal page, and reached from Sales → Today's "📝 Log" button.
// It posts to /sales-log, which writes the record's own timeline through
// lead_log_contact() / act_log(). Picking "what happened" fills the next
// follow-up date in WORKING days (Sales settings → outcomes, weekly off,
// holidays); the person can still change it.
//
// Expects $lf = ['kind' => 'LEAD'|'OPPORTUNITY', 'id', 'office', 'who', 'pref' (lead only),
//                'back' (where to return), 'open' (bool: start unfolded)].
if (!function_exists('sx_outcomes')) return;
$lfOut = sx_outcomes();
$lfDates = []; foreach ($lfOut as $k => $o) $lfDates[$k] = sx_add_working_days($o['days'], (int) ($lf['office'] ?? 0));
$lfToday = date('Y-m-d'); $lfN = 'lf' . (int) $lf['id'];
$lfIcons = ['CALL' => '📞', 'WHATSAPP' => '💬', 'EMAIL' => '✉️', 'MEETING' => '🤝', 'VISIT' => '🚗', 'NOTE' => '📝'];
$lfPref = (string) ($lf['pref'] ?? ''); $lfSel = isset(LEAD_CONTACT_METHODS[$lfPref]) ? $lfPref : 'CALL';
?>
<style>
.lf-pick{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 4px}
.lf-pick label{display:inline-flex;align-items:center;gap:6px;border:1.5px solid var(--line);border-radius:999px;padding:7px 12px;font-size:13.5px;font-weight:600;cursor:pointer;min-height:40px;margin:0;background:var(--card)}
.lf-pick input{accent-color:var(--brand)}
.lf-pick label:has(input:checked){border-color:var(--brand);background:color-mix(in srgb,var(--brand) 10%,var(--card));color:var(--brand)}
.lf-pick small{font-weight:500;color:var(--muted)}
.lf-lab{font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);margin:12px 0 6px}
.lf-more>summary{cursor:pointer;color:var(--brand);font-weight:600;font-size:13px;margin:10px 0 6px}
</style>
<details class="ct-log lf" id="log"<?= !empty($lf['open']) ? ' open' : '' ?>>
  <summary class="btn small">📝 Log a conversation</summary>
  <form method="post" action="/sales-log" style="margin-top:10px">
    <input type="hidden" name="<?= $lf['kind'] === 'LEAD' ? 'lead' : 'deal' ?>" value="<?= (int) $lf['id'] ?>">
    <input type="hidden" name="back" value="<?= e((string) ($lf['back'] ?? '')) ?>">
    <div class="lf-lab">1 · How</div>
    <div class="lf-pick"><?php foreach (LEAD_CONTACT_METHODS as $k => $v): ?><label><input type="radio" name="method" value="<?= e($k) ?>"<?= $lfSel === $k ? ' checked' : '' ?>> <?= ($lfIcons[$k] ?? '') . ' ' . e($v) ?></label><?php endforeach; ?></div>
    <div class="lf-lab">2 · What happened</div>
    <div class="lf-pick" data-lf-out="<?= e($lfN) ?>"><?php foreach ($lfOut as $k => $o): ?>
      <label><input type="radio" name="outcome" value="<?= e($k) ?>" data-date="<?= e($lfDates[$k] ?? '') ?>"> <?= e($o['label']) ?> <small><?= $o['days'] ? '→ ' . (int) $o['days'] . ' working day' . ($o['days'] === 1 ? '' : 's') : '→ you pick' ?></small></label>
    <?php endforeach; ?></div>
    <div class="form-grid">
      <div class="ff ff-wide"><label for="<?= $lfN ?>-note">What was said</label>
        <textarea class="form-control" name="note" id="<?= $lfN ?>-note" rows="2" placeholder="Briefly — what they asked, what you told them, what they decided"></textarea></div>
      <div class="ff"><label for="<?= $lfN ?>-who">Who you spoke to</label>
        <input class="form-control" name="with_whom" id="<?= $lfN ?>-who" maxlength="150" value="<?= e((string) ($lf['who'] ?? '')) ?>"></div>
      <div class="ff"><label for="<?= $lfN ?>-na">Next thing to do</label>
        <input class="form-control" name="next_action" id="<?= $lfN ?>-na" maxlength="255" placeholder="e.g. Send revised quote"></div>
      <div class="ff"><label for="<?= $lfN ?>-on">By when</label>
        <input class="form-control" type="date" name="next_action_on" id="<?= $lfN ?>-on" min="<?= e($lfToday) ?>">
        <small class="muted">Fills itself in from what happened — weekly off and holidays skipped. Change it if you agreed a date.</small></div>
    </div>
    <details class="lf-more"><summary>More details (optional)</summary>
      <div class="form-grid">
        <div class="ff"><label for="<?= $lfN ?>-dir">Which way</label>
          <select class="form-control" name="direction" id="<?= $lfN ?>-dir"><option value="OUT">We contacted them</option><option value="IN">They contacted us</option></select></div>
        <div class="ff"><label for="<?= $lfN ?>-when">When</label>
          <input class="form-control" type="date" name="occurred_on" id="<?= $lfN ?>-when" value="<?= e($lfToday) ?>" max="<?= e($lfToday) ?>"></div>
        <div class="ff"><label for="<?= $lfN ?>-time">Time it took</label>
          <select class="form-control" name="time_spent" id="<?= $lfN ?>-time"><option value="0">—</option>
            <?php foreach ([15 => '15 minutes', 30 => 'Half an hour', 45 => '45 minutes', 60 => '1 hour', 90 => '1½ hours', 120 => '2 hours', 240 => 'Half a day', 480 => 'A full day'] as $m => $t): ?><option value="<?= $m ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
        <?php if ($lf['kind'] === 'LEAD' && defined('LEAD_PREF_CONTACT')): ?>
        <div class="ff"><label for="<?= $lfN ?>-pref">They prefer to be reached by</label>
          <select class="form-control" name="pref_contact" id="<?= $lfN ?>-pref"><option value="">— no preference noted —</option>
            <?php foreach (LEAD_PREF_CONTACT as $k => $v): ?><option value="<?= e($k) ?>"<?= $lfPref === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
      </div>
    </details>
    <div class="form-actions"><button class="btn" type="submit">Save this contact</button></div>
  </form>
</details>
<script>
(function () { var box = document.querySelector('[data-lf-out="<?= e($lfN) ?>"]'), on = document.getElementById('<?= e($lfN) ?>-on'); if (!box || !on) return;
  box.addEventListener('change', function (e) { if (e.target && e.target.name === 'outcome') { var d = e.target.getAttribute('data-date'); on.value = d || ''; if (!d) on.focus(); } });
  if (location.search.indexOf('log=1') >= 0) { var d = document.getElementById('log'); if (d) { d.open = true; setTimeout(function () { d.scrollIntoView({ block: 'start' }); }, 50); } } })();
</script>
