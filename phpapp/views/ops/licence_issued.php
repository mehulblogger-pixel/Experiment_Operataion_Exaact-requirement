<?php $key = $key ?? ''; $claims = $claims ?? []; $ref = $ref ?? ''; $install = $install ?? ''; $amount = (int)($amount ?? 0); $currency = (string)($currency ?? ''); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/issue-licence">Licence console</a> › Key</div>
<div class="master-head"><div>
  <h1>Licence key ready</h1>
  <p class="sub" style="margin:2px 0 0"><?= e($claims['cust'] ?? '') ?> — <?= (int)($claims['seats'] ?? 0) ?: 'unlimited' ?>
    seat(s), valid to <?= e(fdate((string)($claims['exp'] ?? ''))) ?>. Reference <code><?= e($ref) ?></code>.</p>
</div></div>

<div class="panel" style="max-width:760px;margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">The key</h3>
  <p class="sub" style="margin:0 0 8px">Send this to the customer. They paste it on their <strong>Licence</strong>
    screen<?= $install ? ', or — since an install id is set — it will be pulled automatically at their next check-in' : '' ?>.</p>
  <textarea class="form-control" rows="4" readonly onclick="this.select()" style="font-family:monospace;font-size:12px;word-break:break-all"><?= e($key) ?></textarea>
  <button class="btn small secondary" type="button" style="margin-top:8px"
    onclick="navigator.clipboard&&navigator.clipboard.writeText(this.previousElementSibling.previousElementSibling.value);this.textContent='Copied ✓'">Copy key</button>
  <a class="btn small" href="/issue-licence" style="margin-left:6px">Issue another</a>
</div>

<div class="panel" style="max-width:760px">
  <h3 class="tab-sub" style="margin-top:0">What it grants</h3>
  <table class="dt">
    <tr><td>Customer</td><td><?= e($claims['cust'] ?? '') ?></td></tr>
    <tr><td>Seats</td><td><?= (int)($claims['seats'] ?? 0) ?: 'unlimited' ?></td></tr>
    <tr><td>Expires</td><td><?= e(fdate((string)($claims['exp'] ?? ''))) ?> (grace <?= (int)($claims['grace'] ?? 0) ?> days)</td></tr>
    <tr><td>Modules</td><td><?= e(implode(', ', (array)($claims['mods'] ?? []))) ?></td></tr>
    <?php if ($amount > 0): ?><tr><td>Charged</td><td><?= e(($currency ? $currency . ' ' : '') . number_format($amount)) ?> <span class="muted">(our record — not part of the key)</span></td></tr><?php endif; ?>
    <?php if ($install): ?><tr><td>Install id</td><td><code><?= e($install) ?></code></td></tr><?php endif; ?>
  </table>
</div>
