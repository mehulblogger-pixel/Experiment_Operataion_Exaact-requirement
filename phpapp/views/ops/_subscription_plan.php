<?php
// "Choose your plan" for a company that signed itself up (lib/selfserve.php).
// Included by subscription.php, so $pb, $modLabel, $sym, $e, $access, $current,
// $next, $offer and $sub are already in scope. The total shown here is the list
// price; the server works out credit for unused days and what starts when.
$seatM = (int) ($pb['seat']['month'] ?? 0); $seatY = (int) ($pb['seat']['year'] ?? 0);
$all = array_keys($pb['modules'] ?? []);
$mode = $access['mode'] ?? 'trial';
$pre = [];
if ($mode === 'paid' && !empty($current['mods'])) $pre = $current['mods'];
elseif (is_array($offer) && !empty($offer['mods'])) $pre = (array) $offer['mods'];
$preAdd = strtolower(trim((string) ($_GET['add'] ?? '')));
if ($preAdd !== '' && in_array($preAdd, $all, true) && !in_array($preAdd, $pre, true)) $pre[] = $preAdd;
$seats = $mode === 'paid' && !empty($current['seats']) ? (int) $current['seats']
       : (is_array($offer) && !empty($offer['seats']) ? (int) $offer['seats'] : max(1, (int) ($sub['seats_used'] ?? 1)));
$period = $mode === 'paid' ? (string) ($current['period'] ?? 'month') : (is_array($offer) ? (string) ($offer['period'] ?? 'month') : 'month');
$gst = function_exists('commercial_gst_cfg') ? commercial_gst_cfg() : ['on' => false];
$gstNote = !empty($gst['on']) ? ($gst['mode'] === 'inclusive' ? ' (GST included)' : ' + ' . rtrim(rtrim(number_format((float) $gst['rate'], 2), '0'), '.') . '% GST') : '';
$anyYear = $seatY > 0;
$forSale = $seatM > 0 && array_filter($pb['modules'] ?? [], fn($m) => (int) $m['month'] > 0);
?>
<?php if (!$forSale): ?>
  <div class="msg msg-warning" style="margin:0">Plans are not on sale yet — prices are still being set. Please contact your provider; your workspace keeps working in the meantime.</div>
<?php else: ?>
<form method="post" action="/subscription-order" id="planform">
  <input type="hidden" name="do" value="plan">
  <p class="sub" style="margin:0 0 8px;font-size:13px">1 · Tick the modules you want. Administration (people, settings, clients, vendors) is always included.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-bottom:14px">
    <?php foreach ($all as $k): $m = $pb['modules'][$k] ?? ['month' => 0, 'year' => 0]; $priced = (int) $m['month'] > 0; ?>
      <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 12px;cursor:<?= $priced ? 'pointer' : 'not-allowed' ?>;min-height:48px;<?= $priced ? '' : 'opacity:.6' ?>">
        <input type="checkbox" name="mods[]" value="<?= $e($k) ?>" class="pl-mod" data-m="<?= (int) $m['month'] ?>" data-y="<?= (int) $m['year'] ?>" <?= $priced && in_array($k, $pre, true) ? 'checked' : '' ?> <?= $priced ? '' : 'disabled' ?> style="margin-top:3px;width:18px;height:18px">
        <span><span style="font-weight:600"><?= $e($modLabel($k)) ?></span><br>
          <span class="sub" style="font-size:12px"><?= $priced
            ? $e($sym . number_format((int) $m['month'])) . '/month' . ((int) $m['year'] > 0 ? ' · ' . $e($sym . number_format((int) $m['year'])) . '/year' : '')
            : 'Not for sale yet' ?></span></span>
      </label>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px">
    <div><label for="pl-seats" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">2 · How many people will sign in?</label>
      <input type="number" name="seats" id="pl-seats" min="1" value="<?= (int) $seats ?>" style="width:120px;padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:15px">
      <span class="sub" style="font-size:12px;margin-left:6px"><?= $e($sym . number_format($seatM)) ?>/month each · <?= (int) ($sub['seats_used'] ?? 0) ?> active today</span></div>
    <?php $pk = $pb['storage_pack'] ?? ['gb' => 0, 'month' => 0, 'year' => 0];
          if ((float) $pk['gb'] > 0 && (int) $pk['month'] > 0):
            $prePacks = $mode === 'paid' ? (int) ($current['packs'] ?? 0) : (is_array($offer) ? (int) ($offer['packs'] ?? 0) : 0); ?>
    <div><label for="pl-packs" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">Extra storage — packs of <?= $e(rtrim(rtrim(number_format((float) $pk['gb'], 2), '0'), '.')) ?> GB</label>
      <input type="number" name="packs" id="pl-packs" min="0" value="<?= (int) $prePacks ?>" data-m="<?= (int) $pk['month'] ?>" data-y="<?= (int) $pk['year'] ?>" style="width:120px;padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:15px">
      <span class="sub" style="font-size:12px;margin-left:6px"><?= $e($sym . number_format((int) $pk['month'])) ?>/month each</span></div>
    <?php endif; ?>
    <div><label for="pl-period" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">3 · Pay</label>
      <select name="period" id="pl-period" style="padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:15px">
        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Monthly</option>
        <?php if ($anyYear): ?><option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Yearly</option><?php endif; ?>
      </select></div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--soft,#f6f8fb);border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:12px 14px">
    <div><span class="sub" style="font-size:12px">Plan price</span><br>
      <span id="pl-total" style="font-size:22px;font-weight:700"><?= $e($sym) ?>0</span>
      <span class="sub" id="pl-period-lab" style="font-size:12px">/ month</span><?php if ($gstNote !== ''): ?><span class="sub" style="font-size:12px"><?= $e($gstNote) ?></span><?php endif; ?></div>
    <button class="btn" type="submit"><?= $mode === 'paid' ? 'Change or renew →' : 'Choose this plan & pay →' ?></button>
  </div>
  <?php if ($mode === 'paid'): ?>
    <p class="sub" style="margin:8px 0 0;font-size:12.5px">Adding modules or people starts today, and the unused part of your current plan is taken off the price.
      The same plan again renews from <?= $e(fdate(date('Y-m-d', strtotime(($current['until'] ?? date('Y-m-d')) . ' +1 day')))) ?>.
      A smaller plan starts then too, and nothing is charged now.</p>
  <?php endif; ?>
</form>
<?php endif; ?>

<script>
(function(){
  var SYM = <?= json_encode($sym) ?>, SEAT_M = <?= $seatM ?>, SEAT_Y = <?= $seatY ?>;
  var form = document.getElementById('planform');
  if (!form) return;
  function money(n){ return SYM + Number(n).toLocaleString(); }
  function recalc(){
    var year = document.getElementById('pl-period').value === 'year';
    var total = 0;
    form.querySelectorAll('input.pl-mod:checked').forEach(function(c){ total += parseInt(year ? c.dataset.y : c.dataset.m, 10) || 0; });
    var seats = Math.max(1, parseInt(document.getElementById('pl-seats').value, 10) || 1);
    total += seats * (year ? SEAT_Y : SEAT_M);
    var pk = document.getElementById('pl-packs');
    if (pk) total += Math.max(0, parseInt(pk.value, 10) || 0) * (parseInt(year ? pk.dataset.y : pk.dataset.m, 10) || 0);
    document.getElementById('pl-total').textContent = money(total);
    document.getElementById('pl-period-lab').textContent = year ? '/ year' : '/ month';
  }
  form.addEventListener('change', recalc);
  form.addEventListener('input', recalc);
  recalc();
})();
</script>
