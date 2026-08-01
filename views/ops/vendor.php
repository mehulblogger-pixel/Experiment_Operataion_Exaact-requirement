<?php
  $canSign = $can_sign ?? false; $hasKey = $has_key ?? false; $pubkey = $pubkey ?? '';
  $saas = $saas ?? false; $billing = $billing ?? false;
  $nTenants = (int)($n_tenants ?? 0); $nIssued = (int)($n_issued ?? 0);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Super Admin</div>
<div class="master-head"><div>
  <h1>Super Admin</h1>
  <p class="sub" style="margin:2px 0 0">Everything you run as the software provider, in one place — sell it on your own
    servers, or issue licences for customers who run it on theirs.</p>
</div></div>

<?php // Step 0 — signing. Needed only for the self-hosted (licence) model. ?>
<div class="panel" style="margin-top:14px;<?= $hasKey ? '' : 'border-left:3px solid var(--warn)' ?>">
  <h3 class="tab-sub" style="margin-top:0;">Signing key
    <?= $hasKey ? '<span class="pill p-ok">ready</span>' : '<span class="pill p-warn">not set up</span>' ?></h3>
  <?php if (!$hasKey): ?>
    <p class="sub" style="margin:0 0 10px">This is the security key that lets you issue licences for customers on their
      own servers, and stops them adding users for free. You only need it for the <strong>self-hosted</strong> model —
      the SaaS model (customers on your servers) needs none of this. One click sets it up; no commands, no files.</p>
    <form method="post" action="/signing-setup" onsubmit="return confirm('Set up the signing key now? It stays on this server.')">
      <button class="btn" type="submit">Set up signing (one click)</button>
    </form>
    <p class="muted" style="margin-top:8px;font-size:12px">If your app folder is not writable this will say so; then create the
      key by hand once (the Licence console shows how). Never put this key on a customer's server.</p>
  <?php else: ?>
    <p class="sub" style="margin:0 0 8px">Signing is ready — you can issue licences. Each customer's copy needs the
      <strong>public key</strong> below (paste it once on their Licence screen, or ship it with their copy). It is safe to
      share; it can only <em>check</em> a licence, never create one.</p>
    <?php if ($pubkey): ?>
      <textarea class="form-control" rows="4" readonly onclick="this.select()" style="font-family:monospace;font-size:11px"><?= e($pubkey) ?></textarea>
      <button class="btn small secondary" type="button" style="margin-top:6px"
        onclick="navigator.clipboard&&navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Copied ✓'">Copy public key</button>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="settings-cards" style="margin-top:16px">
  <div class="panel settings-card">
    <h3 class="tab-sub" style="margin-top:0;">Cloud workspaces <span class="muted">(<?= $nTenants ?>)</span></h3>
    <p class="sub" style="margin-bottom:10px">Run the app as many separate businesses on your own servers, each on its own
      subdomain and database. <?= $saas ? 'Cloud mode is on.' : 'Not switched on yet.' ?></p>
    <a class="btn" href="/tenants">Manage workspaces</a>
  </div>

  <div class="panel settings-card">
    <h3 class="tab-sub" style="margin-top:0;">Licence console <span class="muted">(<?= $nIssued ?> issued)</span></h3>
    <p class="sub" style="margin-bottom:10px">Issue a signed licence key for a customer on their own server — seats, expiry,
      modules — in one click. <?= $canSign ? '' : 'Set up the signing key above first.' ?></p>
    <a class="btn<?= $canSign ? '' : ' secondary' ?>" href="/issue-licence">Open licence console</a>
  </div>

  <div class="panel settings-card">
    <h3 class="tab-sub" style="margin-top:0;">Pricing &amp; payment
      <?= $billing ? '<span class="pill p-ok">connected</span>' : '<span class="pill p-mut">off</span>' ?></h3>
    <p class="sub" style="margin-bottom:10px">Set what a user costs (monthly / yearly) and paste your Razorpay keys, so
      customers pay per user online — on SaaS and on self-hosted.</p>
    <a class="btn" href="/billing">Open pricing &amp; billing</a>
  </div>
</div>

<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0;">The two ways you sell this — in plain words</h3>
  <div class="panel-split">
    <div class="panel" style="margin:0">
      <h4 style="margin:0 0 6px">On your servers (SaaS) — easiest</h4>
      <p class="sub" style="margin:0">You host it. In <b>Cloud workspaces</b> you add a workspace for each customer; they
        get their own web address and log in. They pay per user online (<b>Pricing &amp; payment</b>). You never touch a
        licence key or a signing key — it all just works. This is the one to lead with.</p>
    </div>
    <div class="panel" style="margin:0">
      <h4 style="margin:0 0 6px">On the customer's server (self-hosted)</h4>
      <p class="sub" style="margin:0">They install a copy on their own hosting. You keep control by issuing them a signed
        <b>licence key</b> (Licence console) that fixes how many users they may have. They cannot raise it — only you can,
        when they pay. They can even buy more users online and their copy picks up the new key automatically.</p>
    </div>
  </div>
</div>
