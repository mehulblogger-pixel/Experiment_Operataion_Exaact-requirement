<?php
// Plans, pricing & usage — the ONE place the platform owner sets what customers
// pay (lib/commercial.php). A blank box is "not set": what it prices is not for
// sale yet. Beside each box: the suggested value from market research and why.
$p = $p ?? []; $usage = $usage ?? []; $sym = $sym ?? '₹';
$mods = $p['modules'] ?? []; $raw = $p['raw'] ?? []; $cat = $p['catalogue'] ?? []; $pb = $p['yearly'] ?? [];
$sug = fn($k) => (string) ($cat[$k][3] ?? '');
$why = fn($k) => (string) ($cat[$k][4] ?? '');
// One input with its suggestion. $name is the form field; $key the setting it saves.
// $short: the suggestion only (the reason is already shown once above it).
$box = function ($name, $key, $val, $attrs = '', $short = false) use ($sug, $why) {
    $s = $sug($key);
    return '<input class="form-control" name="' . e($name) . '" value="' . e($val) . '" placeholder="not set"'
        . ($s !== '' ? ' data-suggest="' . e($s) . '"' : '') . ' ' . $attrs . '>'
        . ($s !== '' || $why($key) !== '' ? '<div class="pu-hint" title="' . e($why($key)) . '">' . ($s !== '' ? 'Suggested: <b>' . e($s) . '</b>' . ($short ? '' : ' · ') : '') . ($short ? '' : e($why($key))) . '</div>' : '');
};
$firstMod = true;
$unset = function_exists('commercial_unset') ? commercial_unset() : [];
$yr = fn($v) => (int) $v > 0 ? e($sym . number_format((int) $v)) : '<span class="muted">not offered</span>';
?>
<style>
  .pu-hint{font-size:11.5px;color:var(--muted,#656e7a);margin-top:3px;line-height:1.35;max-width:340px}
  .pu-grid td{vertical-align:top}
  .pu-grid input{width:130px}
  .pu-note{font-size:12.5px;color:var(--muted,#656e7a);margin:0 0 10px}
</style>
<div class="master-head"><div>
  <h1>Plans, pricing &amp; usage</h1>
  <p class="sub" style="margin:2px 0 0">What customers pay — every module, every login, yearly, GST and AI top-ups. A blank box is not set, and what it prices is not for sale until you fill it in.</p>
</div></div>

<?php if ($unset): ?>
<div class="panel" style="max-width:860px;margin-top:14px;border-left:4px solid var(--warn,#b45309)">
  <b><?= count($unset) ?> number<?= count($unset) === 1 ? '' : 's' ?> still to decide</b> <span class="pu-note">(storage and trial ones are under Companies → 💾 Storage allowance and 🚀 Self sign-up).</span><br>
  <span class="pu-note">Each box shows a suggested starting value from market research (22 Oct 2026) and the reason. "Fill in the suggested values" puts them in the empty boxes only — nothing is saved until you press Save.</span>
  <div style="margin-top:8px"><button type="button" class="btn ghost" id="pu-fill">Fill in the suggested values</button></div>
</div>
<?php endif; ?>

<form method="post" action="/pricing-usage" class="settings-form" id="pu-form">
  <div class="panel" style="max-width:860px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">Prices <span class="muted" style="font-weight:400;font-size:12.5px">— a company pays each module it picks (once per company) plus each login</span></h3>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="grid pu-grid" style="min-width:600px">
      <tr><th>What</th><th>Per month (<?= e($sym) ?>)</th><th>Per year (<?= e($sym) ?>) <span class="muted" style="font-weight:400">— blank uses the discount</span></th><th>Yearly price charged</th></tr>
      <?php foreach ($mods as $k => $m): ?>
      <tr>
        <td><b><?= e($m['label']) ?></b><div class="pu-hint">per company</div></td>
        <td><?= $box('mod_' . $k . '_month', 'saas_price_mod_' . $k . '_month', $m['month'], 'type="number" min="0"', !$firstMod) ?></td><?php $firstMod = false; ?>
        <td><input class="form-control" type="number" min="0" name="mod_<?= e($k) ?>_year" value="<?= e($m['year']) ?>" placeholder="discount" style="width:130px"></td>
        <td><?= $yr($pb['modules'][$k]['year'] ?? 0) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td><b>Each login (person)</b></td>
        <td><?= $box('seat_month', 'billing_price_user_month', $p['seat_month'] ?? '', 'type="number" min="0"') ?></td>
        <td><input class="form-control" type="number" min="0" name="seat_year" value="<?= e($p['seat_year'] ?? '') ?>" placeholder="discount" style="width:130px"></td>
        <td><?= $yr($pb['seat']['year'] ?? 0) ?></td>
      </tr>
    </table>
    </div>
    <div class="form-grid" style="margin-top:12px">
      <div class="ff"><label>Yearly discount (%) — used where a yearly price is blank</label>
        <?= $box('saas_yearly_discount_pct', 'saas_yearly_discount_pct', $raw['saas_yearly_discount_pct'] ?? '', 'type="number" min="0" max="90" step="0.5"') ?></div>
      <div class="ff"><label>Currency</label>
        <input class="form-control" name="currency" value="<?= e($p['currency'] ?? 'INR') ?>" maxlength="3" style="text-transform:uppercase"></div>
    </div>
    <p class="pu-note" style="margin-top:8px">Blank yearly price and no discount = yearly is not offered. Extra storage packs are priced under Companies → 💾 Storage allowance.</p>
  </div>

  <div class="panel" style="max-width:860px;margin-top:14px" id="gst">
    <h3 class="tab-sub" style="margin-top:0">GST <span class="muted" style="font-weight:400;font-size:12.5px">— switch on once the platform is GST-registered</span></h3>
    <input type="hidden" name="_has_plat_gst_enabled" value="1">
    <label style="display:flex;gap:8px;align-items:center;font-weight:600;margin-bottom:10px">
      <input type="checkbox" name="plat_gst_enabled" value="1" <?= ($raw['plat_gst_enabled'] ?? '') === '1' ? 'checked' : '' ?> style="width:18px;height:18px">
      Charge GST on plan payments and issue a GST tax invoice for each</label>
    <div class="form-grid">
      <div class="ff"><label>GST rate (%)</label><?= $box('plat_gst_rate', 'plat_gst_rate', $raw['plat_gst_rate'] ?? '', 'type="number" min="0" max="28" step="0.01"') ?></div>
      <div class="ff"><label>Prices above are</label>
        <select class="form-control" name="plat_gst_mode" data-suggest="<?= e($sug('plat_gst_mode')) ?>">
          <option value="" <?= ($raw['plat_gst_mode'] ?? '') === '' ? 'selected' : '' ?>>— not set —</option>
          <option value="exclusive" <?= ($raw['plat_gst_mode'] ?? '') === 'exclusive' ? 'selected' : '' ?>>before GST (GST is added at checkout)</option>
          <option value="inclusive" <?= ($raw['plat_gst_mode'] ?? '') === 'inclusive' ? 'selected' : '' ?>>including GST</option>
        </select><div class="pu-hint">Suggested: <b>before GST</b> · <?= e($why('plat_gst_mode')) ?></div></div>
      <div class="ff"><label>SAC code</label><?= $box('plat_gst_sac', 'plat_gst_sac', $raw['plat_gst_sac'] ?? '', 'maxlength="8"') ?></div>
      <div class="ff"><label>Your GSTIN</label><?= $box('plat_gstin', 'plat_gstin', $raw['plat_gstin'] ?? '', 'maxlength="15" style="text-transform:uppercase"') ?></div>
      <div class="ff"><label>Your legal (registered) name</label><?= $box('plat_legal_name', 'plat_legal_name', $raw['plat_legal_name'] ?? '', 'maxlength="200"') ?></div>
      <div class="ff"><label>Invoice number prefix</label>
        <input class="form-control" name="plat_invoice_prefix" value="<?= e($raw['plat_invoice_prefix'] ?? '') ?>" placeholder="EX" maxlength="6" style="text-transform:uppercase">
        <div class="pu-hint">Invoices read like EX/2026-27/0001, one series for all companies, restarting each 1 April.</div></div>
      <div class="ff ff-wide"><label>Your registered address</label><?= $box('plat_address', 'plat_address', $raw['plat_address'] ?? '', 'maxlength="400"') ?></div>
    </div>
    <p class="pu-note" style="margin-top:8px">Same state as the customer's GSTIN → CGST + SGST; another state → IGST. Customers without a GSTIN are taxed at your state's rate.
      Tax is rounded to the rupee. E-invoicing (IRN) becomes compulsory only if your yearly turnover passes ₹5 crore.</p>
  </div>

  <div class="panel" style="max-width:860px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">AI allowance &amp; top-up</h3>
    <p class="muted" style="margin:0 0 10px;font-size:12.5px">
      Platform AI is <strong style="color:<?= !empty($p['ai_platform']) ? 'var(--good,#15803d)' : 'var(--warn,#b45309)' ?>"><?= !empty($p['ai_platform']) ? 'switched ON' : 'not set up' ?></strong>.
      <?= !empty($p['ai_platform']) ? '' : 'Set PLATFORM_AI_KEY in config.local.php on the server to switch it on.' ?>
    </p>
    <div class="form-grid">
      <div class="ff"><label>Included AI actions / workspace / month</label>
        <input class="form-control" type="number" min="1" name="ai_cap" value="<?= (int) ($p['ai_cap'] ?? 0) ?>"></div>
      <div class="ff"><label>Top-up pack size <span class="muted">— actions per pack</span></label>
        <input class="form-control" type="number" min="1" name="ai_pack_size" value="<?= (int) ($p['ai_pack_size'] ?? 0) ?>"></div>
      <div class="ff"><label>Top-up pack price (<?= e($sym) ?>)</label><?= $box('ai_pack_price', 'ai_pack_price', $p['ai_pack_price'] ?? '', 'type="number" min="0"') ?></div>
    </div>
  </div>

  <div class="panel" style="max-width:860px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">Console plans — logins included <span class="muted" style="font-weight:400;font-size:12.5px">— for companies you set up yourself on Companies</span></h3>
    <div class="form-grid">
      <?php foreach (($p['plans'] ?? []) as $pk => $pl): ?>
        <div class="ff"><label><?= e($pl['label']) ?></label>
          <input class="form-control" type="number" min="0" name="plan_logins_<?= e($pk) ?>" value="<?= e($pl['saved']) ?>" placeholder="<?= (int) $pl['now'] ?> (in force)">
          <?php if ($pl['saved'] === ''): ?><div class="pu-hint">Not saved yet — <?= (int) $pl['now'] ?> logins, as this plan has always had, stay in force.</div><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <p class="pu-note" style="margin-top:8px">A company's logins = its plan's included logins + logins it bought. Companies that sign themselves up pay per login instead.</p>
  </div>

  <div class="panel" style="max-width:860px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">Seat cost view <span class="muted" style="font-weight:400;font-size:12.5px">— the Control panel's "what each kind of seat costs" table</span></h3>
    <div class="form-grid">
      <div class="ff"><label>Field seat (inspector) / month</label><?= $box('billing_price_field_month', 'billing_price_field_month', $raw['billing_price_field_month'] ?? '', 'type="number" min="0"') ?></div>
      <div class="ff"><label>Portal seat (client / vendor) / month</label><?= $box('billing_price_portal_month', 'billing_price_portal_month', $raw['billing_price_portal_month'] ?? '', 'type="number" min="0"') ?></div>
      <div class="ff"><label>Portal seats included free</label><?= $box('billing_portal_free', 'billing_portal_free', $raw['billing_portal_free'] ?? '', 'type="number" min="0"') ?></div>
    </div>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;max-width:860px">
    <button class="btn" type="submit" name="action" value="save">Save prices</button>
    <button class="btn" type="submit" name="action" value="apply_all" style="background:var(--brand)">Save &amp; push to all workspaces →</button>
  </div>
  <p class="muted" style="font-size:12px;margin-top:6px;max-width:860px">New workspaces use these automatically, and companies that signed themselves up get them every night. "Push to all" updates every existing workspace now.</p>
</form>

<div class="panel" style="max-width:860px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0">Workspace usage this month</h3>
  <?php if (!$usage): ?>
    <p class="muted">No hosted workspaces yet.</p>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid" style="min-width:520px">
    <tr><th>Workspace</th><th>Seats used</th><th>AI actions used</th><th></th></tr>
    <?php foreach ($usage as $u):
        $aiPct = ($u['ai_cap'] ?? 0) > 0 ? min(100, round(($u['ai_used'] ?? 0) / $u['ai_cap'] * 100)) : 0;
        $tone = $aiPct >= 100 ? 'var(--bad,#c0342b)' : ($aiPct >= 80 ? 'var(--warn,#b45309)' : 'var(--good,#15803d)'); ?>
    <tr>
      <td><b><?= e($u['name']) ?></b><?= empty($u['ok']) ? ' <span class="muted" style="font-size:11px">(unreachable)</span>' : '' ?></td>
      <td><?= (int) ($u['seats_used'] ?? 0) ?><?= ($u['seat_cap'] ?? 0) > 0 ? ' / ' . (int) $u['seat_cap'] : '' ?></td>
      <td><?= (int) ($u['ai_used'] ?? 0) ?> / <?= (int) ($u['ai_cap'] ?? 0) ?></td>
      <td style="min-width:120px"><div style="height:7px;border-radius:99px;background:var(--line,#eef1f4);overflow:hidden"><span style="display:block;height:100%;width:<?= (int) $aiPct ?>%;background:<?= $tone ?>"></span></div></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var b = document.getElementById('pu-fill'), f = document.getElementById('pu-form');
  if (!b || !f) return;
  b.addEventListener('click', function(){
    var n = 0;
    f.querySelectorAll('[data-suggest]').forEach(function(el){
      if (el.value === '' && el.dataset.suggest !== '') { el.value = el.dataset.suggest; el.style.background = '#fffbe6'; n++; }
    });
    b.textContent = n ? n + ' boxes filled — check them, then press Save' : 'Nothing empty to fill';
  });
})();
</script>
