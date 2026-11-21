<?php
// A customer's tax identity, the same boxes on every form that puts a customer
// on file (owner, 21 Nov 2026: D1–D4). Typing the GSTIN fills the PAN and the
// state from it; the server checks the GSTIN's check digit and that a typed PAN
// is the one inside the GSTIN (custid_from_input()).
//
//   $cidVals   — current values (tax_status, gstin, pan, state, tax_reason, country, foreign_tax_id)
//   $cidId     — a prefix for element ids when a page carries two such blocks
//   $cidNote   — an optional sentence above the boxes
$cv = is_array($cidVals ?? null) ? $cidVals : [];
$px = preg_replace('/[^a-z0-9_-]/i', '', (string) ($cidId ?? 'cid'));
$st = strtoupper((string) ($cv['tax_status'] ?? '')) ?: 'REGISTERED';
if (!isset(CUSTID_STATUS[$st])) $st = 'REGISTERED';
$g = (string) ($cv['gstin'] ?? '');
?>
<fieldset class="cid-box" id="<?= e($px) ?>" style="border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin:0 0 10px;min-width:0">
  <legend style="font-weight:700;font-size:13.5px;padding:0 6px">GSTIN and PAN <span class="muted" style="font-weight:400">— required to put a <?= e(Tl('client')) ?> on file</span></legend>
  <?php if (!empty($cidNote)): ?><p class="muted" style="margin:0 0 8px;font-size:12.5px"><?= e($cidNote) ?></p><?php endif; ?>
  <div class="cid-st" role="radiogroup" aria-label="Tax registration" style="display:flex;flex-wrap:wrap;gap:6px 14px;margin:0 0 10px">
    <?php foreach (CUSTID_STATUS as $k => $lbl): ?>
      <label style="display:inline-flex;align-items:center;gap:6px;min-height:32px;font-size:13px"><input type="radio" name="tax_status" value="<?= e($k) ?>" <?= $st === $k ? 'checked' : '' ?>> <?= e($lbl) ?></label>
    <?php endforeach; ?>
  </div>
  <div class="form-grid">
    <div class="ff cid-f" data-for="REGISTERED GOVT_NO_PAN"><label for="<?= e($px) ?>-gstin">GSTIN</label>
      <input class="form-control" id="<?= e($px) ?>-gstin" name="gstin" value="<?= e($g) ?>" maxlength="15" autocomplete="off" placeholder="e.g. 24AAACT1234F1Z5" style="text-transform:uppercase"></div>
    <div class="ff cid-f" data-for="REGISTERED UNREGISTERED GOVT_NO_PAN"><label for="<?= e($px) ?>-pan">PAN <span class="muted cid-auto" style="font-weight:400">(filled from the GSTIN)</span></label>
      <input class="form-control" id="<?= e($px) ?>-pan" name="pan" value="<?= e((string) ($cv['pan'] ?? '')) ?>" maxlength="10" autocomplete="off" placeholder="e.g. AAACT1234F" style="text-transform:uppercase"></div>
    <div class="ff cid-f" data-for="REGISTERED UNREGISTERED GOVT_NO_PAN"><label for="<?= e($px) ?>-state">State <span class="muted cid-auto" style="font-weight:400">(from the GSTIN)</span></label>
      <input class="form-control" id="<?= e($px) ?>-state" name="state" value="<?= e((string) ($cv['state'] ?? '')) ?>" maxlength="60"></div>
    <div class="ff ff-wide cid-f" data-for="UNREGISTERED GOVT_NO_PAN OVERSEAS"><label for="<?= e($px) ?>-why">Reason <span class="muted" style="font-weight:400">(why there is no GSTIN / PAN)</span></label>
      <input class="form-control" id="<?= e($px) ?>-why" name="tax_reason" value="<?= e((string) ($cv['tax_reason'] ?? '')) ?>" maxlength="300" placeholder="e.g. below the GST threshold"></div>
    <div class="ff cid-f" data-for="OVERSEAS"><label for="<?= e($px) ?>-country">Country</label>
      <input class="form-control" id="<?= e($px) ?>-country" name="country" value="<?= e((string) ($cv['country'] ?? '')) ?>" maxlength="80"></div>
    <div class="ff cid-f" data-for="OVERSEAS"><label for="<?= e($px) ?>-ftax">Their tax number <span class="muted" style="font-weight:400">(VAT / EIN / TRN …)</span></label>
      <input class="form-control" id="<?= e($px) ?>-ftax" name="foreign_tax_id" value="<?= e((string) ($cv['foreign_tax_id'] ?? '')) ?>" maxlength="60"></div>
  </div>
  <p class="muted cid-hint" aria-live="polite" style="margin:6px 0 0;font-size:12.5px"></p>
</fieldset>
<script>(function(){
  var box = document.getElementById(<?= json_encode($px) ?>); if (!box) return;
  var states = <?= json_encode(lk_options_or('gst_state', GST_STATES), JSON_UNESCAPED_UNICODE) ?>;
  var g = box.querySelector('[name=gstin]'), p = box.querySelector('[name=pan]'), s = box.querySelector('[name=state]'), hint = box.querySelector('.cid-hint');
  function status(){ var r = box.querySelector('[name=tax_status]:checked'); return r ? r.value : 'REGISTERED'; }
  function sync(){
    var st = status();
    box.querySelectorAll('.cid-f').forEach(function(f){ var on = (' ' + f.getAttribute('data-for') + ' ').indexOf(' ' + st + ' ') >= 0; f.style.display = on ? '' : 'none'; f.querySelectorAll('input').forEach(function(i){ i.disabled = !on; }); });
    box.querySelectorAll('.cid-auto').forEach(function(a){ a.style.display = st === 'REGISTERED' ? '' : 'none'; });
    if (p) p.readOnly = st === 'REGISTERED'; if (s) s.readOnly = st === 'REGISTERED';
    fill();
  }
  function fill(){
    if (!g || status() !== 'REGISTERED') { if (hint) hint.textContent = ''; return; }
    var v = (g.value || '').toUpperCase().replace(/\s+/g, ''); g.value = v;
    if (v.length >= 12) { var pan = v.substr(2, 10); if (/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(pan) && p) p.value = pan; }
    if (v.length >= 2 && s && states[v.substr(0, 2)]) s.value = states[v.substr(0, 2)];
    if (hint) hint.textContent = v.length && v.length !== 15 ? 'A GSTIN is 15 characters (' + v.length + ' typed).' : (v.length === 15 ? 'PAN and state filled from the GSTIN. The check digit is verified when you save.' : '');
  }
  box.querySelectorAll('[name=tax_status]').forEach(function(r){ r.addEventListener('change', sync); });
  if (g) g.addEventListener('input', fill);
  sync();
})();</script>
