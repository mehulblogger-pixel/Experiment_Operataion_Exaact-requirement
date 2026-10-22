<?php
  $amt = $amt ?? []; $order = $order ?? []; $cfg = $cfg ?? [];
  $cur = $amt['currency'] ?? 'INR';
  $sym = $cur === 'INR' ? '₹' : ($cur . ' ');
  $verify_action = $verify_action ?? '/billing-verify';   // subscription checkout overrides this
  $back = $back ?? '/billing';
  $extra = $extra ?? [];                                   // extra hidden fields (e.g. modules)
  $amtMajor = isset($amt['major']) ? (int) $amt['major'] : (isset($amt['total']) ? (int) $amt['total'] : 0);
?>
<div class="master-head"><div><h1>Complete your payment</h1>
  <p class="sub" style="margin:2px 0 0"><?= (int)$amt['seats'] ?> <?= (int)$amt['seats']===1?'person':'people' ?>,
    <?= e($amt['period']==='year'?'annual':'monthly') ?> — <strong><?= e($sym . number_format((int)$amt['major'])) ?></strong>.</p></div></div>

<?php $tx = $amt['tax'] ?? null; if (is_array($tx) && !empty($tx['on'])): ?>
<div class="panel" style="max-width:520px">
  <table style="width:100%;font-size:14px;border-collapse:collapse">
    <tr><td style="padding:4px 0">Price<?= $tx['mode'] === 'inclusive' ? ' (taxable value)' : '' ?></td><td style="text-align:right"><?= e($sym . number_format((int) $tx['net'])) ?></td></tr>
    <?php if ((int) $tx['igst'] > 0): ?><tr><td style="padding:4px 0">IGST <?= e(rtrim(rtrim(number_format((float) $tx['rate'], 2), '0'), '.')) ?>%</td><td style="text-align:right"><?= e($sym . number_format((int) $tx['igst'])) ?></td></tr>
    <?php else: $half = rtrim(rtrim(number_format((float) $tx['rate'] / 2, 2), '0'), '.'); ?>
      <tr><td style="padding:4px 0">CGST <?= e($half) ?>%</td><td style="text-align:right"><?= e($sym . number_format((int) $tx['cgst'])) ?></td></tr>
      <tr><td style="padding:4px 0">SGST <?= e($half) ?>%</td><td style="text-align:right"><?= e($sym . number_format((int) $tx['sgst'])) ?></td></tr><?php endif; ?>
    <tr style="font-weight:700;border-top:1px solid var(--line,#e5e7eb)"><td style="padding:6px 0">Total to pay</td><td style="text-align:right"><?= e($sym . number_format((int) $tx['gross'])) ?></td></tr>
  </table>
  <?php $bt = (array) ($amt['buyer'] ?? []); ?>
  <p style="font-size:13px;margin:10px 0 0">Invoice to <b><?= e($bt['name'] ?? '') ?></b> · <?= $tx['buyer_gstin'] !== '' ? 'GSTIN <b>' . e($tx['buyer_gstin']) . '</b>' : 'not GST-registered' ?>
    · place of supply <?= e(defined('GST_STATES') ? (GST_STATES[$tx['pos']] ?? $tx['pos']) : $tx['pos']) ?></p>
  <p class="muted" style="font-size:12px;margin:4px 0 0">The GST tax invoice is issued the moment the payment is confirmed. Wrong details? Press Cancel and change "Bill to".</p>
</div>
<?php endif; ?>
<div class="panel" style="max-width:520px;text-align:center">
  <p class="sub">The secure Razorpay window should open automatically. If it does not, use the button below.</p>
  <button class="btn" id="paybtn" type="button">Pay <?= e($sym . number_format($amtMajor)) ?></button>
  <a class="btn secondary" href="<?= e($back) ?>" style="margin-left:8px">Cancel</a>
  <p class="muted" style="margin-top:14px;font-size:12.5px">Your card details go straight to Razorpay — this application never sees them.
    Your plan activates the instant the payment is confirmed.</p>
</div>

<?php // Filled in by the Razorpay callback, then submitted. CSRF is stamped in
      // automatically at render, so the verify step is protected like any POST. ?>
<form method="post" action="<?= e($verify_action) ?>" id="verifyform" style="display:none">
  <input type="hidden" name="seats" value="<?= (int)$amt['seats'] ?>">
  <input type="hidden" name="period" value="<?= e($amt['period']) ?>">
  <?php foreach ($extra as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <input type="hidden" name="razorpay_order_id" id="f_order">
  <input type="hidden" name="razorpay_payment_id" id="f_payment">
  <input type="hidden" name="razorpay_signature" id="f_sig">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
  var opts = {
    key: <?= json_encode($cfg['key_id'] ?? '') ?>,
    amount: <?= (int)$order['amount'] ?>,
    currency: <?= json_encode($cur) ?>,
    name: <?= json_encode(function_exists('app_name') ? app_name() : 'Subscription') ?>,
    description: <?= json_encode((int)$amt['seats'] . ' seat(s), ' . $amt['period']) ?>,
    order_id: <?= json_encode($order['id'] ?? '') ?>,
    handler: function(resp){
      document.getElementById('f_order').value   = resp.razorpay_order_id;
      document.getElementById('f_payment').value = resp.razorpay_payment_id;
      document.getElementById('f_sig').value     = resp.razorpay_signature;
      document.getElementById('verifyform').submit();
    },
    modal: { ondismiss: function(){ /* stay on the page so they can retry */ } },
    theme: { color: '#1e40af' }
  };
  function open(){ try { (new Razorpay(opts)).open(); } catch(e){ alert('Could not open the payment window. Please reload.'); } }
  document.getElementById('paybtn').addEventListener('click', open);
  window.addEventListener('load', function(){ setTimeout(open, 300); });
})();
</script>
