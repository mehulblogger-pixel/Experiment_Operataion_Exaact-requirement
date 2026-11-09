<?php
// Your subscription — a company that HAS a paid plan (owner, 9 Nov 2026: "if I
// want to add only one module, or renew only one module, or add some seats …
// the customer may get confused"). Three plain cards instead of one "rebuild
// your whole plan" form:
//   1. Add to your plan — tick only what is new; logins and storage are typed
//      as the number to ADD; the price is only for what is added, for the days
//      left (the plan keeps its renewal date — the owner's Q-L rule, unchanged).
//   2. Your renewal — what renews and when; renew now (pay in advance); or
//      change what renews (keep only some modules / fewer logins), booked for
//      the renewal day with nothing charged now.
//   3. Rebuild the whole plan — the full form, for monthly ↔ yearly.
// The server prices everything (saas_plan_change_quote); this screen only
// estimates. Included by subscription.php: $pb, $modLabel, $sym, $e, $current,
// $next, $sub, $access in scope.
$per = ($current['period'] ?? 'month') === 'year' ? 'year' : 'month';
$perWord = $per === 'year' ? 'year' : 'month';
$have = array_values((array) ($current['mods'] ?? []));
$all = array_keys($pb['modules'] ?? []);
$price = fn($k) => (int) (($pb['modules'][$k] ?? [])[$per] ?? 0);
$seatP = (int) ($pb['seat'][$per] ?? 0);
$pk = $pb['storage_pack'] ?? ['gb' => 0, 'month' => 0, 'year' => 0];
$packP = ((float) $pk['gb'] > 0) ? (int) ($pk[$per] ?? 0) : 0;
$until = (string) ($current['until'] ?? '');
$from = (string) ($current['from'] ?? '');
$today = date('Y-m-d');
$left = max(0, (int) round((strtotime($until) - strtotime($today)) / 86400));
$total = $from !== '' ? max(1, (int) round((strtotime($until) - strtotime($from)) / 86400))
                      : max(1, (int) round((strtotime($until) - strtotime($until . ($per === 'year' ? ' -1 year' : ' -1 month'))) / 86400));
$share = min(1, $left / $total);
$gst = function_exists('commercial_gst_cfg') ? commercial_gst_cfg() : ['on' => false];
$gstNote = !empty($gst['on']) ? ($gst['mode'] === 'inclusive' ? ' (GST included)' : ' + ' . rtrim(rtrim(number_format((float) $gst['rate'], 2), '0'), '.') . '% GST') : '';
$fd = fn($d) => function_exists('fdate') ? fdate($d) : $d;
$renewOn = date('Y-m-d', strtotime($until . ' +1 day'));
$ren = $next ?: ['mods' => $have, 'seats' => (int) $current['seats'], 'packs' => (int) ($current['packs'] ?? 0), 'period' => $per];
$renPer = ($ren['period'] ?? $per) === 'year' ? 'year' : 'month';
$renQ = function_exists('saas_company_quote') ? saas_company_quote((array) $ren['mods'], (int) $ren['seats'], $renPer, (int) ($ren['packs'] ?? 0)) : ['total' => 0];
$used = (int) ($sub['seats_used'] ?? 0);
$card = 'border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:14px 16px;margin-top:12px;background:var(--card,#fff)';
$inp = 'width:110px;padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:15px';
?>
<!-- 1 · Add to your plan -->
<form method="post" action="/subscription-order" id="addform" style="<?= $card ?>">
  <input type="hidden" name="do" value="plan"><input type="hidden" name="mode" value="add">
  <h3 style="margin:0 0 2px;font-size:15px">Add to your plan</h3>
  <p class="sub" style="margin:0 0 10px;font-size:12.5px">Starts today and ends with the rest of your plan on <b><?= $e($fd($until)) ?></b> — you pay only for what you add, for the <?= (int) $left ?> days left.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-bottom:12px">
    <?php foreach ($all as $k): $p = $price($k); $own = in_array($k, $have, true); ?>
      <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:10px 12px;min-height:46px;<?= $own ? 'background:var(--soft,#f6f8fb)' : ($p > 0 ? 'cursor:pointer' : 'opacity:.6') ?>">
        <?php if ($own): ?><span style="color:#047857;font-weight:800;margin-top:1px">✓</span>
        <?php else: ?><input type="checkbox" name="add_mods[]" value="<?= $e($k) ?>" class="ad-mod" data-p="<?= $p ?>" <?= $p > 0 ? '' : 'disabled' ?> style="margin-top:3px;width:18px;height:18px"><?php endif; ?>
        <span><span style="font-weight:600"><?= $e($modLabel($k)) ?></span><br>
          <span class="sub" style="font-size:12px"><?= $own ? 'In your plan' : ($p > 0 ? 'Add · ' . $e($sym . number_format($p)) . ' / ' . $perWord : 'Not for sale yet') ?></span></span>
      </label>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
    <div><label for="ad-logins" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">Add logins</label>
      <input type="number" name="add_logins" id="ad-logins" min="0" value="0" style="<?= $inp ?>">
      <span class="sub" style="font-size:12px;margin-left:6px">you have <?= (int) $current['seats'] ?>, <?= $used ?> in use · <?= $e($sym . number_format($seatP)) ?> / <?= $perWord ?> each</span></div>
    <?php if ($packP > 0): ?>
    <div><label for="ad-packs" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">Add storage packs (<?= $e(rtrim(rtrim(number_format((float) $pk['gb'], 2), '0'), '.')) ?> GB each)</label>
      <input type="number" name="add_packs" id="ad-packs" min="0" value="0" style="<?= $inp ?>">
      <span class="sub" style="font-size:12px;margin-left:6px">you have <?= (int) ($current['packs'] ?? 0) ?> · <?= $e($sym . number_format($packP)) ?> / <?= $perWord ?> each</span></div>
    <?php endif; ?>
  </div>
  <?php include __DIR__ . '/_billto.php'; ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--soft,#f6f8fb);border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:12px 14px">
    <div><span class="sub" style="font-size:12px" id="ad-what">Tick a module or type how many logins to add</span><br>
      <span id="ad-total" style="font-size:22px;font-weight:700"><?= $e($sym) ?>0</span>
      <span class="sub" style="font-size:12px">for the <?= (int) $left ?> days left<?= $e($gstNote) ?> — the exact figure is on the next screen</span></div>
    <button class="btn" type="submit" id="ad-go" disabled>Add and pay →</button>
  </div>
</form>

<!-- 2 · Your renewal -->
<div style="<?= $card ?>">
  <h3 style="margin:0 0 2px;font-size:15px">Your renewal</h3>
  <p style="margin:0 0 10px;font-size:13.5px">On <b><?= $e($fd($renewOn)) ?></b> your plan renews as:
    <b><?= $e(implode(', ', array_map($modLabel, (array) $ren['mods']))) ?></b> + Administration, <?= (int) $ren['seats'] ?> logins<?= (int) ($ren['packs'] ?? 0) ? ', ' . (int) $ren['packs'] . ' storage pack' . ((int) $ren['packs'] === 1 ? '' : 's') : '' ?>,
    <?= $renPer === 'year' ? 'yearly' : 'monthly' ?> — <b><?= $e($sym . number_format((int) $renQ['total'])) ?></b> / <?= $renPer ?><?= $e($gstNote) ?>.
    <?= $next && !empty($next['paid']) ? '<span style="color:#047857;font-weight:600">Already paid.</span>' : '' ?></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <?php if (!($next && !empty($next['paid']))): ?>
    <form method="post" action="/subscription-order" style="margin:0">
      <input type="hidden" name="do" value="plan"><input type="hidden" name="mode" value="renew">
      <button class="btn" type="submit">Renew now — pay in advance →</button></form>
    <?php endif; ?>
    <details style="flex:1 1 100%">
      <summary class="btn ghost" style="cursor:pointer;display:inline-block;list-style:none">Change what renews…</summary>
      <form method="post" action="/subscription-order" style="margin-top:10px;padding:12px;border:1px solid var(--line,#e5e7eb);border-radius:10px">
        <input type="hidden" name="do" value="plan"><input type="hidden" name="mode" value="renewal">
        <p class="sub" style="margin:0 0 8px;font-size:12.5px">Keep only what you want from <?= $e($fd($renewOn)) ?>. Until then everything you have stays on. Nothing is charged now — you pay for the smaller plan when it starts.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
          <?php foreach ($have as $k): ?>
            <label style="display:inline-flex;gap:6px;align-items:center;border:1px solid var(--line,#e5e7eb);border-radius:999px;padding:6px 12px;font-size:13.5px">
              <input type="checkbox" name="keep_mods[]" value="<?= $e($k) ?>" <?= in_array($k, (array) $ren['mods'], true) ? 'checked' : '' ?>> <?= $e($modLabel($k)) ?></label>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end">
          <div><label style="font-size:13px;font-weight:600;display:block;margin-bottom:3px" for="rn-seats">Logins from <?= $e($fd($renewOn)) ?></label>
            <input type="number" name="renew_logins" id="rn-seats" min="1" max="<?= (int) $current['seats'] ?>" value="<?= (int) $ren['seats'] ?>" style="<?= $inp ?>">
            <span class="sub" style="font-size:12px;margin-left:6px"><?= $used ?> people are active — deactivate those who leave before then</span></div>
          <?php if ((int) ($current['packs'] ?? 0) > 0): ?>
          <div><label style="font-size:13px;font-weight:600;display:block;margin-bottom:3px" for="rn-packs">Storage packs</label>
            <input type="number" name="renew_packs" id="rn-packs" min="0" max="<?= (int) $current['packs'] ?>" value="<?= (int) ($ren['packs'] ?? 0) ?>" style="<?= $inp ?>"></div>
          <?php endif; ?>
          <button class="btn" type="submit">Book this for <?= $e($fd($renewOn)) ?></button>
        </div>
      </form>
    </details>
  </div>
</div>

<!-- 3 · Rebuild -->
<details style="<?= $card ?>">
  <summary style="cursor:pointer;font-weight:600;font-size:14px">Rebuild the whole plan — switch monthly ↔ yearly, or start again</summary>
  <div style="margin-top:12px"><?php $noBillto = true; include __DIR__ . '/_subscription_plan.php'; ?></div>
</details>

<script>
(function(){
  var f = document.getElementById('addform'); if (!f) return;
  var SYM = <?= json_encode($sym) ?>, SEAT = <?= $seatP ?>, PACK = <?= $packP ?>, SHARE = <?= json_encode($share) ?>, PER = <?= json_encode($perWord) ?>;
  function money(n){ return SYM + Math.round(n).toLocaleString(); }
  function recalc(){
    var full = 0, bits = [];
    f.querySelectorAll('input.ad-mod:checked').forEach(function(c){ full += parseInt(c.dataset.p, 10) || 0; bits.push(c.closest('label').querySelector('span span').textContent); });
    var l = Math.max(0, parseInt(document.getElementById('ad-logins').value, 10) || 0);
    if (l) { full += l * SEAT; bits.push(l + ' login' + (l === 1 ? '' : 's')); }
    var pk = document.getElementById('ad-packs'), p = pk ? Math.max(0, parseInt(pk.value, 10) || 0) : 0;
    if (p) { full += p * PACK; bits.push(p + ' storage pack' + (p === 1 ? '' : 's')); }
    document.getElementById('ad-total').textContent = money(full * SHARE);
    document.getElementById('ad-what').textContent = bits.length ? 'Adding ' + bits.join(', ') + ' — ' + money(full) + ' a full ' + PER + ', about' : 'Tick a module or type how many logins to add';
    document.getElementById('ad-go').disabled = !bits.length;
  }
  f.addEventListener('change', recalc); f.addEventListener('input', recalc); recalc();
})();
</script>
