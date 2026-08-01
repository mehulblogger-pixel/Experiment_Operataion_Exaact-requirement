<?php
  $cfg = $cfg ?? []; $grant = $grant ?? []; $used = (int)($used ?? 0); $history = $history ?? [];
  $selfManaged = $self_managed ?? true; $lic = $lic ?? [];
  $cur = $cfg['currency'] ?? 'INR';
  $sym = $cur === 'INR' ? '₹' : ($cur . ' ');
  $pm = (int)($cfg['price_month'] ?? 0); $py = (int)($cfg['price_year'] ?? 0);
  $canBuy = $selfManaged && !empty($cfg['enabled']) && ($pm > 0 || $py > 0);
  $master = function_exists('is_master') && is_master();
  $licSeats = (int)($lic['seats'] ?? 0);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Users &amp; billing</div>
<div class="master-head">
  <div><h1>Users &amp; billing</h1>
    <p class="sub" style="margin:2px 0 0">Pay per person. Add or renew seats online — the moment a payment clears, the
      new seat count is live.</p></div>
  <a class="btn secondary" href="/licence">Licence details</a>
</div>

<div class="kpi-row" style="margin-top:14px">
  <div class="kpi"><span class="k-lab">People active now</span><span class="k-val"><?= $used ?></span></div>
<?php $shownSeats = $selfManaged ? (!empty($grant['active']) ? (int)$grant['seats'] : 0) : $licSeats;
        $shownUntil = $selfManaged ? ($grant['until'] ?? '') : (string)($lic['expires'] ?? ''); ?>
  <div class="kpi <?= $shownSeats ? 'tone-ok' : '' ?>"><span class="k-lab">Seats <?= $selfManaged ? 'paid for' : 'licensed' ?></span>
    <span class="k-val"><?= $shownSeats ?: ($shownSeats === 0 && !$selfManaged ? 'unlimited' : '—') ?></span></div>
  <div class="kpi"><span class="k-lab"><?= $selfManaged ? 'Paid until' : 'Licence to' ?></span>
    <span class="k-val" style="font-size:18px"><?= $shownUntil ? e(fdate($shownUntil)) : '—' ?></span></div>
</div>
<?php if ($shownSeats && $used > $shownSeats): ?>
  <div class="msg msg-warning" style="margin-top:8px"><?= $used ?> people are active but only <?= $shownSeats ?> seats are <?= $selfManaged ? 'paid for' : 'licensed' ?>.
    <?= $selfManaged ? 'Add seats below, or deactivate people who have left.' : 'Ask your provider to add seats to your licence, or deactivate people who have left.' ?></div>
<?php endif; ?>

<?php if (!$selfManaged): ?>
  <div class="panel" style="max-width:640px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">Your users are set by your licence</h3>
    <p class="sub" style="margin:0 0 10px">This copy runs on your own server, so the number of people who may sign in is
      fixed by your <strong>licence key</strong> — a value nobody here can raise, which is what keeps it honest. To add
      or renew users, contact your provider; a new key with the higher count is applied on the licence screen (and picks
      up automatically if online renewal is set up).</p>
    <a class="btn" href="/licence">Open licence</a>
  </div>
<?php elseif ($canBuy): ?>
<div class="panel settings-form" style="max-width:640px">
  <h3 class="tab-sub" style="margin-top:0">Add or renew seats</h3>
  <form method="post" action="/billing-order" id="buyform">
    <div class="form-grid">
      <div class="ff"><label>How many people</label>
        <input class="form-control" type="number" name="seats" id="seats" min="1" max="1000" value="<?= max(1, $used, (int)($grant['seats'] ?? 0)) ?>" oninput="calc()"></div>
      <div class="ff"><label>Billing</label>
        <select class="form-control" name="period" id="period" onchange="calc()">
          <?php if ($pm > 0): ?><option value="month">Monthly — <?= e($sym) ?><?= number_format($pm) ?> / person / month</option><?php endif; ?>
          <?php if ($py > 0): ?><option value="year">Annual — <?= e($sym) ?><?= number_format($py) ?> / person / year</option><?php endif; ?>
        </select></div>
    </div>
    <p class="sub" style="margin:10px 0 4px">Total: <strong id="total" style="font-size:18px"></strong> <span class="muted" id="totnote"></span></p>
    <button class="btn" type="submit" style="margin-top:6px">Pay with Razorpay →</button>
    <span class="muted" style="margin-left:8px;font-size:13px">Secure checkout. Seats activate the instant payment clears.</span>
  </form>
</div>
<script>
  var PM=<?= (int)$pm ?>, PY=<?= (int)$py ?>, SYM=<?= json_encode($sym) ?>;
  function calc(){var s=Math.max(1,parseInt(document.getElementById('seats').value||'1',10));
    var p=document.getElementById('period').value;var u=(p==='year'?PY:PM);var t=s*u;
    document.getElementById('total').textContent=SYM+t.toLocaleString();
    document.getElementById('totnote').textContent='('+s+' × '+SYM+u.toLocaleString()+' / '+(p==='year'?'year':'month')+')';}
  calc();
</script>
<?php elseif ($master): ?>
  <div class="msg msg-info" style="margin-top:14px">Set your price per user and your Razorpay keys below to switch on self-service payment.</div>
<?php else: ?>
  <div class="msg msg-info" style="margin-top:14px">Online payment is not switched on yet. Ask the Master Admin to set it up.</div>
<?php endif; ?>

<?php if ($master && $selfManaged): ?>
<details class="panel" <?= $canBuy ? '' : 'open' ?> style="margin-top:16px">
  <summary style="cursor:pointer;font-weight:600">Pricing &amp; payment keys
    <?= !empty($cfg['enabled']) ? '<span class="pill p-ok">Razorpay connected</span>' : '<span class="pill p-mut">not connected</span>' ?></summary>
  <p class="sub" style="margin:10px 0">Set what a seat costs, and paste your Razorpay <strong>Key Id</strong> and <strong>Key Secret</strong>
    (Razorpay Dashboard → Settings → API Keys). The secret is stored here only and shown masked.</p>
  <form method="post" action="/billing-config" class="settings-form">
    <div class="form-grid">
      <div class="ff"><label>Currency</label><input class="form-control" name="currency" value="<?= e($cur) ?>" maxlength="5"></div>
      <div class="ff"><label>Price per user / month</label><input class="form-control" type="number" min="0" name="price_month" value="<?= $pm ?>"></div>
      <div class="ff"><label>Price per user / year</label><input class="form-control" type="number" min="0" name="price_year" value="<?= $py ?>"></div>
      <div class="ff"><label>Razorpay Key Id</label><input class="form-control" name="rzp_key_id" value="<?= e($cfg['key_id'] ?? '') ?>" placeholder="rzp_live_… or rzp_test_…" autocomplete="off"></div>
      <div class="ff ff-wide"><label>Razorpay Key Secret</label><input class="form-control" type="password" name="rzp_key_secret" autocomplete="new-password" placeholder="<?= !empty($cfg['key_secret']) ? '•••••••• (leave blank to keep)' : 'paste the secret' ?>"></div>
    </div>
    <button class="btn" type="submit" style="margin-top:10px">Save pricing &amp; keys</button>
  </form>
  <p class="muted" style="margin-top:10px;font-size:12.5px">Leave a period's price at 0 to hide it. Use <code>rzp_test_</code> keys to try the flow without real money.</p>
</details>
<?php endif; ?>

<?php if ($history): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Payments</h3>
  <table class="dt">
    <thead><tr><th>When</th><th>Seats</th><th>Period</th><th>Amount</th><th>Paid until</th><th>By</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td class="muted"><?= e(fdate(substr((string)$h['created_at'],0,10))) ?></td>
        <td><?= (int)$h['seats'] ?></td><td class="muted"><?= e($h['period']) ?></td>
        <td><?= e(($h['currency']==='INR'?'₹':$h['currency'].' ') . number_format((int)$h['amount'])) ?></td>
        <td class="muted"><?= e(fdate($h['paid_until'])) ?></td><td class="muted"><?= e($h['by_user']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
