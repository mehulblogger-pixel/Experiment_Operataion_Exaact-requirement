<?php
  $order = $order ?? []; $cfg = $cfg ?? []; $size = (int) ($size ?? 0); $price = (int) ($price ?? 0);
  $cur = $currency ?? 'INR'; $sym = $cur === 'INR' ? '₹' : ($cur . ' ');
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/ai-forms">Build forms with AI</a> › Buy AI actions</div>
<div class="master-head"><div><h1>Buy more AI actions</h1>
  <p class="sub" style="margin:2px 0 0"><strong><?= $size ?></strong> extra AI actions for this month — <strong><?= e($sym . number_format($price)) ?></strong>.</p></div></div>

<div class="panel" style="max-width:520px;text-align:center">
  <p class="sub">The secure Razorpay window should open automatically. If it does not, use the button below.</p>
  <button class="btn" id="paybtn" type="button">Pay <?= e($sym . number_format($price)) ?></button>
  <a class="btn secondary" href="/ai-forms" style="margin-left:8px">Cancel</a>
  <p class="muted" style="margin-top:14px;font-size:12.5px">Your card details go straight to Razorpay — this application never sees them.
    The actions are added the instant the payment is confirmed.</p>
</div>

<form method="post" action="/ai-topup-verify" id="verifyform" style="display:none">
  <input type="hidden" name="razorpay_order_id" id="f_order">
  <input type="hidden" name="razorpay_payment_id" id="f_payment">
  <input type="hidden" name="razorpay_signature" id="f_sig">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
  var opts = {
    key: <?= json_encode($cfg['key_id'] ?? '') ?>,
    amount: <?= (int) ($order['amount'] ?? 0) ?>,
    currency: <?= json_encode($cur) ?>,
    name: <?= json_encode(function_exists('app_name') ? app_name() : 'AI actions') ?>,
    description: <?= json_encode($size . ' AI actions') ?>,
    order_id: <?= json_encode($order['id'] ?? '') ?>,
    handler: function(resp){
      document.getElementById('f_order').value   = resp.razorpay_order_id;
      document.getElementById('f_payment').value = resp.razorpay_payment_id;
      document.getElementById('f_sig').value     = resp.razorpay_signature;
      document.getElementById('verifyform').submit();
    },
    modal: { ondismiss: function(){} },
    theme: { color: '#1e40af' }
  };
  function open(){ try { (new Razorpay(opts)).open(); } catch(e){ alert('Could not open the payment window. Please reload.'); } }
  document.getElementById('paybtn').addEventListener('click', open);
  window.addEventListener('load', function(){ setTimeout(open, 300); });
})();
</script>
