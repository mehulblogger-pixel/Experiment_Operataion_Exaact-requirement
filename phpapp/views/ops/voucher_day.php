<?php
// Expenses Phase 1 — screens 1 and 2 on their own: confirm (or change) the
// expenses of a closed day on your own job, and add a bill to it. Phone-first:
// one column, big tap targets, one primary action ("Confirm the day").
$cs = cur_sym();
$back = $claim ? '/voucher?id=' . (int)$claim['id'] : '/job?id=' . (int)$job['id'];
$bills = array_values(array_filter($plan['existing'], fn($x) => ($x['source'] ?? '') !== 'DAY'));
$canWork = $problem === '' && $lock === '';
$limitTxt = [];
foreach ($plan['bills'] as $h) $limitTxt[$h['code']] = $h['_limit'] !== null
    ? (exp_kind($h) === 'NOBILL' ? 'Your daily limit: ' : 'Your limit: ') . $cs . nf((float)$h['_limit'], 0) . ' (from your ' . ($h['_src'] === 'person' ? 'profile' : 'band') . ')' : '';
?>
<div class="master-head">
  <div><h1><?= e($job['job_code']) ?> · <?= e(date('j M', strtotime($date))) ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= e(exp_job_client($job) ?: '—') ?><?= exp_job_site($job) !== exp_job_client($job) ? ' · ' . e(exp_job_site($job)) : '' ?><?= !empty($job['contract_number']) ? ' · ' . e(T('boss')) . ' ' . e($job['contract_number']) : '' ?></p></div>
  <a class="btn secondary" href="<?= e($back) ?>">← <?= $claim ? 'My claim' : e(ucfirst(Tl('job'))) ?></a>
</div>

<?php if ($problem !== ''): ?>
  <div class="msg msg-error"><?= e($problem) ?> <a href="/job?id=<?= (int)$job['id'] ?>">Open the <?= e(Tl('job')) ?></a></div>
<?php elseif ($lock !== ''): ?>
  <div class="msg msg-error"><?= e($lock) ?><?php if ($claim): ?> <a href="/voucher?id=<?= (int)$claim['id'] ?>">Open your claim</a><?php endif; ?></div>
<?php endif; ?>

<?php if (count($days) > 1): ?>
  <div class="chip-row" style="margin:0 0 12px;display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach ($days as $d): ?><a class="pill <?= $d === $date ? 'p-ok' : 'p-mut' ?>" href="/voucher-day?job=<?= (int)$job['id'] ?>&amp;date=<?= e($d) ?>"><?= e(date('j M', strtotime($d))) ?></a><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($canWork): ?>
<form method="post" action="/voucher-day?job=<?= (int)$job['id'] ?>&amp;date=<?= e($date) ?>" class="panel" style="max-width:640px">
  <h3 class="tab-sub" style="margin-top:0"><?= $date === date('Y-m-d') ? 'Today\'s expenses' : 'Expenses for ' . e(fdate($date)) ?></h3>
  <input type="hidden" name="job" value="<?= (int)$job['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
  <?php require __DIR__ . '/_exp_day_fields.php'; ?>
  <button class="btn" type="submit" style="width:100%;min-height:52px;font-size:17px;margin-top:12px"><?= !empty($plan['confirmed']) ? 'Save the day' : 'Confirm the day' ?></button>
  <p class="muted" style="margin:8px 0 0;font-size:13px">No expenses this day? Confirm it anyway — the day worked is recorded for your timesheet.</p>
</form>
<?php endif; ?>

<?php if ($bills): ?>
<div class="panel" style="max-width:640px">
  <h3 class="tab-sub" style="margin-top:0">Bills on this day</h3>
  <?php foreach ($bills as $bl): $h = exp_head((string)$bl['head_code']); $amt = (float)$bl['row_total']; ?>
    <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--line)">
      <span><strong><?= e($h['label'] ?? ($headLabels[$bl['head_code']] ?? 'Expense')) ?></strong>
        <?php if ($h && exp_kind($h) === 'NOBILL'): ?><span class="pill p-mut">no bill</span><?php endif; ?>
        <?php if (!empty($bl['receipt_data'])): ?> <a href="/voucher-line-receipt?id=<?= (int)$bl['id'] ?>" target="_blank" rel="noopener">📎 bill</a><?php elseif ($h && exp_needs_photo($h)): ?> <span class="pill p-warn">photo needed</span><?php endif; ?>
        <?php if (trim((string)$bl['notes']) !== ''): ?><br><span class="muted"><?= e($bl['notes']) ?></span><?php endif; ?></span>
      <span style="white-space:nowrap"><strong><?= e($cs . nf($amt, 0)) ?></strong>
        <?php if ($canWork): ?><form method="post" action="/voucher-line" style="display:inline" onsubmit="return confirm('Remove this bill?')"><input type="hidden" name="id" value="<?= (int)$bl['id'] ?>"><input type="hidden" name="_do" value="del"><button class="btn small danger" type="submit" aria-label="Remove">✕</button></form><?php endif; ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($canWork && $plan['bills']): ?>
<details class="panel" style="max-width:640px" <?= isset($_GET['bill']) ? 'open' : '' ?>>
  <summary style="font-weight:700;font-size:16px;cursor:pointer;min-height:44px;display:flex;align-items:center">＋ Add a bill (ticket, hotel, cab)</summary>
  <form method="post" action="/voucher-quick-add" enctype="multipart/form-data" style="margin-top:10px">
    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="entry_date" value="<?= e($date) ?>">
    <input type="hidden" name="back" value="/voucher-day?job=<?= (int)$job['id'] ?>&date=<?= e($date) ?>">
    <label class="expd-lab">What kind of bill?</label>
    <div class="exp-kinds" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
      <?php foreach ($plan['bills'] as $i => $h): ?>
        <label class="pill p-mut" style="min-height:40px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
          <input type="radio" name="head" value="<?= e($h['code']) ?>" <?= $i === 0 ? 'checked' : '' ?> data-limit="<?= e($limitTxt[$h['code']]) ?>" data-nobill="<?= exp_kind($h) === 'NOBILL' ? '1' : '0' ?>">
          <?= e($h['label']) ?><?= exp_kind($h) === 'NOBILL' ? ' (no bill)' : '' ?></label>
      <?php endforeach; ?>
    </div>
    <label class="expd-lab" for="bill-amt">Amount (<?= e($cs) ?>)</label>
    <input class="form-control" id="bill-amt" type="number" inputmode="decimal" step="0.01" min="0" name="amount" required style="font-size:18px;min-height:44px;max-width:220px">
    <div class="muted" id="bill-limit" style="font-size:13px;margin:4px 0 10px"></div>
    <div id="bill-photo"><label class="expd-lab">Photo of the bill</label>
      <input class="form-control" type="file" name="receipt" accept="image/*,.pdf" capture="environment"></div>
    <div id="bill-note" style="margin-top:10px"><label class="expd-lab">Note <span class="muted" id="bill-note-req">— optional</span></label>
      <input class="form-control" name="note" placeholder="what it was for"></div>
    <button class="btn" type="submit" style="margin-top:12px;min-height:48px">Add to this day</button>
  </form>
</details>
<script>(function(){
  var r=document.querySelectorAll('input[name="head"]'), lim=document.getElementById('bill-limit'), ph=document.getElementById('bill-photo'), nr=document.getElementById('bill-note-req');
  function sync(){ var c=document.querySelector('input[name="head"]:checked'); if(!c) return;
    lim.textContent=c.dataset.limit||''; var nb=c.dataset.nobill==='1'; ph.style.display=nb?'none':''; nr.textContent=nb?'— needed (no bill)':'— optional'; }
  r.forEach(function(x){ x.addEventListener('change',sync); }); sync();
})();</script>
<?php endif; ?>
