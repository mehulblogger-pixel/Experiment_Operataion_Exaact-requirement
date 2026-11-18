<?php
// Expenses Phase 1 — screen 1, "Today's expenses" (design v3). Used inside the
// job close form and on /voucher-day. Expects $plan (exp_day_plan), $job, $date.
// Nothing here is typed by the inspector unless it differs: the vehicle, the
// usual distance, the rate and the allowances come from his band or profile,
// and each says where it came from (Zero Training UI gate, item 7).
$cs = cur_sym();
$srcTxt = fn($s) => $s === 'personal' ? 'your personal allowance' : ($s === 'person' ? 'from your profile' : ($s === 'band' ? 'from your band' : 'standard rate'));
$km1 = fn($n) => rtrim(rtrim(number_format((float)$n, 1, '.', ''), '0'), '.');
$usual = $plan['usual'] ?? null;
$usualOk = $usual && !empty($usual['approved']);
$billsTotal = 0.0;
foreach (($plan['existing'] ?? []) as $ex) if (($ex['source'] ?? '') !== 'DAY') $billsTotal += (float)$ex['row_total'];
$rates = []; foreach ($plan['modes'] as $m) $rates[$m['code']] = (float)$m['_rate'];
?>
<div class="expd" id="expd" data-bills="<?= e((string)$billsTotal) ?>" data-usual="<?= $usualOk ? e((string)$usual['km']) : '' ?>"
     data-tol="<?= e((string)exp_rules()['km_tol']) ?>" data-cur="<?= e($cs) ?>">
  <input type="hidden" name="exp_date" value="<?= e($date) ?>">
  <?php if ($plan['modes']): ?>
  <div class="expd-row">
    <div class="expd-main">
      <label class="expd-lab" for="expd-mode">Travel</label>
      <div class="expd-inline">
        <select class="form-control" name="mode" id="expd-mode" aria-label="How you travelled">
          <?php foreach ($plan['modes'] as $m): ?><option value="<?= e($m['code']) ?>" <?= $plan['mode_code'] === $m['code'] ? 'selected' : '' ?>><?= e($m['label']) ?> · <?= e($cs . nf((float)$m['_rate'], 2)) ?>/km</option><?php endforeach; ?>
        </select>
        <input class="form-control expd-km" type="number" inputmode="decimal" step="0.1" min="0" name="km" id="expd-km" value="<?= e($km1($plan['km'])) ?>" aria-label="Distance in km"> <span>km</span>
      </div>
      <div class="expd-src">● <?php if ($usualOk): ?>filled in · usual distance for this site (<?= e($km1($usual['km'])) ?> km, approved)<?php elseif ($usual): ?>filled in · the distance you gave last time<?php else: ?>type the distance (both ways) — once approved it becomes the usual distance<?php endif; ?>
        · rate <?= e($srcTxt($plan['mode']['_src'] ?? 'default')) ?></div>
      <div class="expd-reason" id="expd-reason" <?= $usualOk && exp_km_differs((float)$plan['km'], (float)$usual['km']) ? '' : 'hidden' ?>>
        <label class="expd-lab" for="expd-why">Why a different distance today?</label>
        <input class="form-control" name="km_reason" id="expd-why" value="<?= e($plan['reason'] ?? '') ?>" placeholder="e.g. road closed, took the bypass">
        <small class="muted">Your approver sees it in amber.</small>
      </div>
    </div>
    <div class="expd-amt" id="expd-travel">—</div>
  </div>
  <?php endif; ?>
  <?php foreach ($plan['auto'] as $h): ?>
  <div class="expd-row">
    <div class="expd-main"><strong><?= e($h['label']) ?></strong>
      <div class="expd-src">● filled in · <?= e($srcTxt($h['_src'])) ?></div></div>
    <div class="expd-amt expd-fixed" data-amt="<?= e((string)$h['_rate']) ?>"><?= e($cs . nf((float)$h['_rate'], 0)) ?></div>
  </div>
  <?php endforeach; ?>
  <?php foreach ($plan['tick'] as $h): ?>
  <label class="expd-row expd-tick">
    <span class="expd-main"><input type="checkbox" name="tick[<?= e($h['code']) ?>]" value="1" data-amt="<?= e((string)$h['_rate']) ?>" <?= !empty($plan['ticked'][$h['code']]) ? 'checked' : '' ?>>
      <strong><?= e($h['label']) ?> today</strong> <span class="expd-src"><?= e($srcTxt($h['_src'])) ?></span></span>
    <span class="expd-amt"><?= e($cs . nf((float)$h['_rate'], 0)) ?></span>
  </label>
  <?php endforeach; ?>
  <?php if ($billsTotal > 0): ?>
  <div class="expd-row"><div class="expd-main">Bills already added for this day</div><div class="expd-amt"><?= e($cs . nf($billsTotal, 0)) ?></div></div>
  <?php endif; ?>
  <?php if (!$plan['modes'] && !$plan['auto'] && !$plan['tick']): ?>
    <p class="muted" style="margin:6px 0">Nothing is filled in for you on a <?= e(Tl('job')) ?> day — no travel or allowance is set on your band or profile. Add a bill if you had one.</p>
  <?php endif; ?>
  <div class="expd-total"><span>Total for the day</span><strong id="expd-total"><?= e($cs) ?>0</strong></div>
</div>
<style>
  .expd{border:1px solid var(--line);border-radius:12px;padding:6px 14px;background:var(--card)}
  .expd-row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--line)}
  .expd-main{flex:1;min-width:0}
  .expd-lab{display:block;font-weight:700;margin-bottom:4px}
  .expd-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .expd-inline select{min-width:200px;flex:1}
  .expd-km{width:110px;font-size:18px;min-height:44px}
  .expd-src{font-size:13px;color:var(--muted);margin-top:4px}
  .expd-amt{font-weight:700;font-size:17px;white-space:nowrap;font-variant-numeric:tabular-nums}
  .expd-tick{cursor:pointer;min-height:48px;align-items:center}
  .expd-tick input{width:24px;height:24px;vertical-align:middle;margin-right:8px}
  .expd-reason{margin-top:8px;padding:8px 10px;border-left:3px solid var(--warn,#b45309);background:var(--warn-soft,#fff7e6);border-radius:6px}
  .expd-total{display:flex;justify-content:space-between;padding:12px 0 8px;font-size:18px}
  .expd-total strong{font-size:22px}
  @media (max-width:560px){.expd-inline select{min-width:0;width:100%}}
</style>
<script>(function(){
  var box=document.getElementById('expd'); if(!box) return;
  var RATES=<?= json_encode($rates) ?>, cur=box.dataset.cur||'', usual=parseFloat(box.dataset.usual||''), tol=parseFloat(box.dataset.tol||'0')||0;
  var mode=document.getElementById('expd-mode'), km=document.getElementById('expd-km'), why=document.getElementById('expd-reason');
  function fmt(n){ return cur+(Math.round(n)).toLocaleString('en-IN'); }
  function calc(){
    var t=parseFloat(box.dataset.bills||'0')||0, tr=0;
    if(km&&mode){ var k=parseFloat(km.value)||0; tr=k*(RATES[mode.value]||0); t+=tr;
      var c=document.getElementById('expd-travel'); if(c) c.textContent=k>0?fmt(tr):'—';
      if(why&&!isNaN(usual)&&usual>0){ why.hidden=!(Math.abs(k-usual)>usual*tol/100+0.0001); } }
    box.querySelectorAll('.expd-fixed').forEach(function(x){ t+=parseFloat(x.dataset.amt)||0; });
    box.querySelectorAll('.expd-tick input').forEach(function(x){ if(x.checked) t+=parseFloat(x.dataset.amt)||0; });
    document.getElementById('expd-total').textContent=fmt(t);
  }
  box.addEventListener('input',calc); box.addEventListener('change',calc); calc();
})();</script>
