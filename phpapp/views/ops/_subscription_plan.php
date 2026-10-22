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
?>
<form method="post" action="/subscription-order" id="planform">
  <input type="hidden" name="do" value="plan">
  <p class="sub" style="margin:0 0 8px;font-size:13px">1 · Tick the modules you want. Administration (people, settings, clients, vendors) is always included.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-bottom:14px">
    <?php foreach ($all as $k): $m = $pb['modules'][$k] ?? ['month' => 0, 'year' => 0]; ?>
      <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 12px;cursor:pointer;min-height:48px">
        <input type="checkbox" name="mods[]" value="<?= $e($k) ?>" class="pl-mod" data-m="<?= (int) $m['month'] ?>" data-y="<?= (int) $m['year'] ?>" <?= in_array($k, $pre, true) ? 'checked' : '' ?> style="margin-top:3px;width:18px;height:18px">
        <span><span style="font-weight:600"><?= $e($modLabel($k)) ?></span><br>
          <span class="sub" style="font-size:12px"><?= $e($sym . number_format((int) $m['month'])) ?>/month · <?= $e($sym . number_format((int) $m['year'])) ?>/year</span></span>
      </label>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px">
    <div><label for="pl-seats" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">2 · How many people will sign in?</label>
      <input type="number" name="seats" id="pl-seats" min="1" value="<?= (int) $seats ?>" style="width:120px;padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:15px">
      <span class="sub" style="font-size:12px;margin-left:6px"><?= $e($sym . number_format($seatM)) ?>/month each · <?= (int) ($sub['seats_used'] ?? 0) ?> active today</span></div>
    <div><label for="pl-period" style="font-size:13px;font-weight:600;display:block;margin-bottom:3px">3 · Pay</label>
      <select name="period" id="pl-period" style="padding:9px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:15px">
        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Monthly</option>
        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Yearly</option>
      </select></div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--soft,#f6f8fb);border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:12px 14px">
    <div><span class="sub" style="font-size:12px">Plan price</span><br>
      <span id="pl-total" style="font-size:22px;font-weight:700"><?= $e($sym) ?>0</span>
      <span class="sub" id="pl-period-lab" style="font-size:12px">/ month</span></div>
    <button class="btn" type="submit"><?= $mode === 'paid' ? 'Change or renew →' : 'Choose this plan & pay →' ?></button>
  </div>
  <?php if ($mode === 'paid'): ?>
    <p class="sub" style="margin:8px 0 0;font-size:12.5px">Adding modules or people starts today, and the unused part of your current plan is taken off the price.
      The same plan again renews from <?= $e(fdate(date('Y-m-d', strtotime(($current['until'] ?? date('Y-m-d')) . ' +1 day')))) ?>.
      A smaller plan starts then too, and nothing is charged now.</p>
  <?php endif; ?>
</form>

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
    document.getElementById('pl-total').textContent = money(total);
    document.getElementById('pl-period-lab').textContent = year ? '/ year' : '/ month';
  }
  form.addEventListener('change', recalc);
  form.addEventListener('input', recalc);
  recalc();
})();
</script>
