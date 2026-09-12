<?php
// The à-la-carte "add modules / seats" form with a live quote. Included by
// subscription.php, so $pb, $addable, $modLabel, $sym, $e are already in scope.
$seatM = (int) ($pb['seat']['month'] ?? 0); $seatY = (int) ($pb['seat']['year'] ?? 0);
// A module can be pre-selected via /subscription?add=<key> — e.g. the "Upgrade to
// add it" button on the Features screen deep-links straight to its purchase.
$preAdd = strtolower(trim((string) ($_GET['add'] ?? '')));
?>
<form method="post" action="/subscription-order" id="subform">
  <?php if (!empty($addable)): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-bottom:12px">
    <?php foreach ($addable as $k): $m = $pb['modules'][$k] ?? ['month' => 0, 'year' => 0]; ?>
      <label style="display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:10px 12px;cursor:pointer">
        <input type="checkbox" name="add_mods[]" value="<?= $e($k) ?>" class="sub-mod" data-m="<?= (int) $m['month'] ?>" data-y="<?= (int) $m['year'] ?>" <?= $k === $preAdd ? 'checked' : '' ?> style="margin-top:2px">
        <span><span style="font-weight:600"><?= $e($modLabel($k)) ?></span><br>
          <span class="sub" style="font-size:12px"><?= $e($sym . number_format((int) $m['month'])) ?>/mo · <?= $e($sym . number_format((int) $m['year'])) ?>/yr</span></span>
      </label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Seats to add</label>
      <input type="number" name="add_seats" id="sub-seats" min="0" value="0" style="width:110px;padding:7px 9px;border:1px solid var(--line,#e5e7eb);border-radius:8px">
      <span class="sub" style="font-size:12px;margin-left:6px"><?= $e($sym . number_format($seatM)) ?>/mo each</span></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Billing period</label>
      <select name="period" id="sub-period" style="padding:7px 9px;border:1px solid var(--line,#e5e7eb);border-radius:8px">
        <option value="month">Monthly</option>
        <option value="year">Yearly</option>
      </select></div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--soft,#f6f8fb);border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:12px 14px">
    <div><span class="sub" style="font-size:12px">Total to pay now</span><br>
      <span id="sub-total" style="font-size:22px;font-weight:700"><?= $e($sym) ?>0</span>
      <span class="sub" id="sub-period-lab" style="font-size:12px">/ month</span></div>
    <button class="btn" type="submit">Pay &amp; activate →</button>
  </div>
</form>

<script>
(function(){
  var SYM = <?= json_encode($sym) ?>, SEAT_M = <?= $seatM ?>, SEAT_Y = <?= $seatY ?>;
  var form = document.getElementById('subform');
  if (!form) return;
  function money(n){ return SYM + Number(n).toLocaleString(); }
  function recalc(){
    var year = document.getElementById('sub-period').value === 'year';
    var total = 0;
    form.querySelectorAll('input.sub-mod:checked').forEach(function(c){ total += parseInt(year ? c.dataset.y : c.dataset.m, 10) || 0; });
    var seats = parseInt(document.getElementById('sub-seats').value, 10) || 0;
    total += seats * (year ? SEAT_Y : SEAT_M);
    document.getElementById('sub-total').textContent = money(total);
    document.getElementById('sub-period-lab').textContent = year ? '/ year' : '/ month';
  }
  form.addEventListener('change', recalc);
  form.addEventListener('input', recalc);
  recalc();
})();
</script>
