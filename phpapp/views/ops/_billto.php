<?php
// "Bill to" at checkout — whose name (and GSTIN) goes on the invoice. Filled
// in from the company profile; what was typed is kept if it was refused.
// Included inside the plan / add-module forms (lib/selfserve.php
// saas_billto_from_post() reads it). Needs $e in scope.
$bt  = function_exists('saas_invoice_buyer') ? saas_invoice_buyer() : ['name' => '', 'gstin' => '', 'address' => '', 'state' => ''];
$dr  = is_array($_SESSION['billto_draft'] ?? null) ? $_SESSION['billto_draft'] : null;
if ($dr) $bt = ['name' => $dr['name'] ?? '', 'gstin' => $dr['gstin'] ?? '', 'address' => $dr['address'] ?? '',
                'state' => function_exists('commercial_state_code') ? commercial_state_code($dr['state'] ?? '') : ''];
$gstOn = function_exists('commercial_gst_cfg') && commercial_gst_cfg()['on'];
$ip = 'width:100%;padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:14px;box-sizing:border-box';
?>
<details class="bt" <?= $dr || ($gstOn && $bt['gstin'] === '') ? 'open' : '' ?> style="border:1px solid var(--line,#e5e7eb);border-radius:10px;margin:0 0 14px">
  <summary style="cursor:pointer;padding:11px 12px;font-size:13.5px;font-weight:600">
    Bill to: <?= $e($bt['name'] !== '' ? $bt['name'] : '— add your business name —') ?><?= $gstOn ? ' · ' . ($bt['gstin'] !== '' ? 'GSTIN ' . $e($bt['gstin']) : 'no GSTIN') : '' ?>
    <span class="sub" style="font-weight:400;font-size:12px"> — change</span></summary>
  <div style="padding:0 12px 12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px" for="bt-name">Registered business name</label>
      <input id="bt-name" name="billto_name" value="<?= $e($bt['name']) ?>" maxlength="200" required style="<?= $ip ?>"></div>
    <?php if ($gstOn): ?>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px" for="bt-gstin">GSTIN <span class="sub" style="font-weight:400">— to claim input tax credit</span></label>
      <input id="bt-gstin" name="billto_gstin" value="<?= $e($bt['gstin']) ?>" maxlength="15" autocomplete="off" placeholder="leave blank if not registered"
             pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z][Zz][0-9A-Za-z]" title="15 characters, like 24AAACE1234F1Z5" style="<?= $ip ?>;text-transform:uppercase"></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px" for="bt-state">State <span class="sub" style="font-weight:400">— taken from the GSTIN when given</span></label>
      <select id="bt-state" name="billto_state" style="<?= $ip ?>">
        <option value="">— choose —</option>
        <?php foreach ((defined('GST_STATES') ? GST_STATES : []) as $code => $name): ?>
          <option value="<?= $e($code) ?>" <?= (string) $bt['state'] === (string) $code ? 'selected' : '' ?>><?= $e($name) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div style="grid-column:1/-1"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px" for="bt-addr">Billing address</label>
      <textarea id="bt-addr" name="billto_address" rows="2" maxlength="500" style="<?= $ip ?>"><?= $e($bt['address']) ?></textarea></div>
    <label style="grid-column:1/-1;display:flex;gap:8px;align-items:center;font-size:13px">
      <input type="checkbox" name="billto_save" value="1" checked style="width:17px;height:17px"> Save these to our company profile for next time</label>
  </div>
</details>
