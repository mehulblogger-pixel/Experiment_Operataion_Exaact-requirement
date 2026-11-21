<?php
// Simple Sales — Quick new lead (owner's Excel: company, mobile, source → done).
// Saves through lead_create(); the full lead form stays one click away.
require __DIR__ . '/_sx_ui.php';
$v = fn($k, $d = '') => e((string) ($old[$k] ?? $d));
?>
<div class="sx" style="max-width:860px">
  <div class="sxhead"><h1>➕ New lead</h1><a class="sxchip" href="/lead-new" id="sl-full" title="Opens the full form with what you have typed here already filled in">Full lead form ›</a></div>
  <p class="sxwhy"><b>Three boxes and you are done.</b> The first follow-up is set to the next working day; change it if you already agreed a date.</p>
  <?php if ($err !== ''): ?><div class="sxerr" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" action="/sales-lead" class="sxcard" style="margin-top:0" enctype="multipart/form-data" id="sl-form">
    <?php if ($dupes): ?>
      <div class="sxwarn" role="alert"><b>⚠ This may already be on file:</b>
        <ul><?php foreach ($dupes as $dp): ?><li><?= $dp['url'] !== '' ? '<a href="' . e($dp['url']) . '" target="_blank" rel="noopener">' . e($dp['text']) . '</a>' : e($dp['text']) ?></li><?php endforeach; ?></ul>
        <p style="margin:8px 0 0">Open it instead — or, if this really is new, press <b>Save anyway</b>.</p>
        <input type="hidden" name="confirm" value="1"></div>
    <?php endif; ?>
    <div class="sxform">
      <div class="wide"><label for="sl_co">Company or person *</label><input id="sl_co" class="form-control" name="company_name" required autofocus value="<?= $v('company_name') ?>" placeholder="e.g. Shree Engineering Works"></div>
      <div><label for="sl_ph">Mobile</label><input id="sl_ph" class="form-control" name="contact_phone" inputmode="tel" value="<?= $v('contact_phone') ?>" placeholder="98250 12345"><small>Used for 💬 WhatsApp and 📞 call from Today.</small></div>
      <div><label for="sl_src">Where did it come from?</label><select id="sl_src" class="form-control" name="source"><option value="">—</option>
        <?php foreach ($sources as $k => $lab): ?><option value="<?= e($k) ?>"<?= (string) ($old['source'] ?? '') === (string) $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
      <div><label for="sl_cn">Contact person</label><input id="sl_cn" class="form-control" name="contact_name" value="<?= $v('contact_name') ?>"></div>
      <div><label for="sl_val">Rough value (₹, optional)</label><input id="sl_val" class="form-control" name="value" inputmode="decimal" value="<?= $v('value') ?>"></div>
      <div><label for="sl_next">First follow-up</label><input id="sl_next" class="form-control" type="date" name="next_action_on" min="<?= e(date('Y-m-d')) ?>" value="<?= $v('next_action_on', $next) ?>"><small>Next working day by default.</small></div>
      <?php if (count($offices) > 1): ?>
        <div><label for="sl_off"><?= e(function_exists('T') ? T('office') : 'Office') ?></label><select id="sl_off" class="form-control" name="office_id">
          <?php foreach ($offices as $o): ?><option value="<?= (int) $o['id'] ?>"<?= (int) ($old['office_id'] ?? $myOffice) === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
          <small>Decides who it goes to (Sales settings).</small></div>
      <?php endif; ?>
      <?php if ($people): ?>
        <div><label for="sl_own">Give it to</label><select id="sl_own" class="form-control" name="owner_user_id"><option value="">— by office (automatic) —</option>
          <?php foreach ($people as $id => $nm): ?><option value="<?= (int) $id ?>"<?= (int) ($old['owner_user_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="wide"><label for="sl_files">Attach files <span class="muted" style="font-weight:400">(optional — the customer's enquiry, a drawing, an e-mail; up to <?= (int) round(LEAD_FILE_MAX / 1048576) ?> MB each)</span></label>
        <input id="sl_files" type="file" name="files[]" multiple data-max-bytes="<?= (int) LEAD_FILE_MAX ?>">
        <input type="hidden" name="file_kind" value="REQUIREMENT"><small>Kept under the lead's Documents and carried onto its quotation.</small></div>
    </div>
    <div class="sxactions">
      <button class="btn" type="submit"><?= $dupes ? 'Save anyway' : 'Save lead' ?></button>
      <button class="btn secondary" type="submit" name="another" value="1"><?= $dupes ? 'Save anyway & add another' : 'Save & add another' ?></button>
      <a class="btn ghost" href="/sales-today">Cancel</a>
    </div>
  </form>
</div>
<script>
// "Full lead form ›" takes what is already typed here with it (owner, 20 Nov 2026 —
// it used to open an empty form and everything typed was lost).
(function () {
  var a = document.getElementById('sl-full'), f = document.getElementById('sl-form');
  if (!a || !f) return;
  a.addEventListener('click', function (ev) {
    var q = [];
    ['company_name', 'contact_name', 'contact_phone', 'source', 'value', 'office_id', 'owner_user_id', 'next_action_on'].forEach(function (n) {
      var el = f.querySelector('[name="' + n + '"]');
      if (el && String(el.value || '').trim() !== '') q.push(encodeURIComponent(n) + '=' + encodeURIComponent(String(el.value).trim()));
    });
    var files = f.querySelector('input[type=file]');
    if (files && files.files && files.files.length && !window.confirm('The file(s) you chose cannot travel to the full form — choose them again there. Continue?')) { ev.preventDefault(); return; }
    if (q.length) { ev.preventDefault(); location.href = '/lead-new?' + q.join('&'); }
  });
})();
</script>
