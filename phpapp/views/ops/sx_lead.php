<?php
// Quick lead (owner, 21 Nov 2026: Sales redesign Stage 2, approved design s2-quick).
// Twenty seconds: who, how to reach them, when to call. The same boxes, checks and
// save as before (sx_lead_save() / sx_lead_dupes() in lib/sales_more.php), in the
// Sales workspace look; the full lead form stays one tap away and takes what is
// typed with it. The old page is kept in tests/fixtures/pre_stage2/sx_lead.php.
require_once __DIR__ . '/_sxw_ui.php';
$v = fn($k, $d = '') => e((string) ($old[$k] ?? $d));
$right = '<div class="sxw-ctl"><a class="sxw-bt" href="/lead-new" id="sl-full" title="Opens the full form with what you have typed here already filled in">Full lead form ›</a></div>';
?>
<div class="rec sxw has-bn sxl sxl-form">
  <?php sxw_nav('/leads'); ?>
  <?php sxw_head('Quick lead', 'Who, how to reach them, when to call. Everything else can be added on the lead page.', 'bolt', $right); ?>
  <?php if ($err !== ''): ?><div class="sxl-ban bad" role="alert"><span class="ic"><?= rec_icon('alert') ?></span><div class="tx"><?= e($err) ?></div></div><?php endif; ?>

<div class="sxl-fg">
  <form method="post" action="/sales-lead" enctype="multipart/form-data" id="sl-form" class="sxl-main">
    <?php if ($dupes): ?>
      <div class="sxl-ban warn" role="alert"><span class="ic"><?= rec_icon('alert') ?></span>
        <div class="tx"><b class="t">This looks like one we already have</b>
          <ul class="sxl-dl"><?php foreach ($dupes as $dp): ?><li><?= $dp['url'] !== '' ? '<a href="' . e($dp['url']) . '" target="_blank" rel="noopener">' . e($dp['text']) . '</a>' : e($dp['text']) ?></li><?php endforeach; ?></ul>
          <span class="sxl-hint">Open one of them instead — or, if this really is new, press <b>Save anyway</b>.</span></div>
        <input type="hidden" name="confirm" value="1"></div>
    <?php endif; ?>
    <section class="rec-card">
      <div class="form-grid">
        <div class="ff ff-wide"><label for="sl_co">Company or person *</label><input id="sl_co" class="form-control" name="company_name" required autofocus value="<?= $v('company_name') ?>" placeholder="e.g. Shree Engineering Works"></div>
        <div class="ff"><label for="sl_ph">Mobile</label><input id="sl_ph" class="form-control" name="contact_phone" inputmode="tel" value="<?= $v('contact_phone') ?>" placeholder="98250 12345"><div class="ff-help">At least 10 digits. Used for WhatsApp and Call from Today.</div></div>
        <div class="ff"><label for="sl_src">Where did it come from?</label><select id="sl_src" class="form-control" name="source"><option value="">—</option>
          <?php foreach ($sources as $k => $lab): ?><option value="<?= e($k) ?>"<?= (string) ($old['source'] ?? '') === (string) $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label for="sl_cn">Contact person</label><input id="sl_cn" class="form-control" name="contact_name" value="<?= $v('contact_name') ?>"></div>
        <div class="ff"><label for="sl_val">Rough value (₹, optional)</label><input id="sl_val" class="form-control" name="value" inputmode="decimal" value="<?= $v('value') ?>"></div>
        <div class="ff"><label for="sl_next">First follow-up</label><input id="sl_next" class="form-control" type="date" name="next_action_on" min="<?= e(date('Y-m-d')) ?>" value="<?= $v('next_action_on', $next) ?>"><div class="ff-help">Next working day by default (Sales settings).</div></div>
        <?php if (count($offices) > 1): ?>
          <div class="ff"><label for="sl_off"><?= e(function_exists('T') ? T('office') : 'Office') ?></label><select id="sl_off" class="form-control" name="office_id">
            <?php foreach ($offices as $o): ?><option value="<?= (int) $o['id'] ?>"<?= (int) ($old['office_id'] ?? $myOffice) === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
            <div class="ff-help">Decides who it goes to (Sales settings).</div></div>
        <?php endif; ?>
        <?php if ($people): ?>
          <div class="ff"><label for="sl_own">Give it to</label><select id="sl_own" class="form-control" name="owner_user_id"><option value="">— by office (automatic) —</option>
            <?php foreach ($people as $id => $nm): ?><option value="<?= (int) $id ?>"<?= (int) ($old['owner_user_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?></select>
            <div class="ff-help">Automatic = Sales settings → New leads go to.</div></div>
        <?php endif; ?>
        <div class="ff ff-wide"><label for="sl_files">Attach files <span class="muted" style="font-weight:400">(optional — the customer's enquiry, a drawing, an e-mail; up to <?= (int) round(LEAD_FILE_MAX / 1048576) ?> MB each)</span></label>
          <input id="sl_files" type="file" name="files[]" multiple data-max-bytes="<?= (int) LEAD_FILE_MAX ?>">
          <input type="hidden" name="file_kind" value="REQUIREMENT"><div class="ff-help">Kept under the lead's Documents and carried onto its quotation.</div></div>
      </div>
    </section>
    <div class="sxl-act">
      <span class="sp">The next step is set to “First call”.</span>
      <a class="btn secondary" href="/sales-today">Cancel</a>
      <button class="btn secondary" type="submit" name="another" value="1"><?= $dupes ? 'Save anyway & add another' : 'Save & add another' ?></button>
      <button class="btn" type="submit"><?= $dupes ? 'Save anyway' : 'Save lead' ?></button>
    </div>
  </form>
  <aside class="sxl-side">
    <?php rec_card_open('Quick or full?', 'bolt'); ?>
      <p class="sxl-hint" style="margin:0 0 8px">Quick lead is for the phone call in front of you. The full form adds e-mail, deputation, pipeline and your company's own fields.</p>
      <p class="sxl-hint" style="margin:0"><b>Full lead form ›</b> carries what you typed across. Files you chose must be picked again there — it says so.</p>
    <?php rec_card_close(); ?>
  </aside>
</div>
</div>
<?php sxw_bottom('/leads'); ?>
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
