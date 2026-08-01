<?php $can = $can_sign ?? false; $modules = $modules ?? []; $history = $history ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/licence">Licence</a> › Issue a licence</div>
<div class="master-head"><div>
  <h1>Licence console</h1>
  <p class="sub" style="margin:2px 0 0">Generate a signed licence key for a customer. Only this server can sign one —
    the customer's copy can check a key but never create one, which is what keeps the seat count honest.</p>
</div></div>

<?php if (!$can): ?>
<div class="panel" style="max-width:720px;margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Set up signing first</h3>
  <p class="sub">This server has no private signing key, so it cannot issue keys yet. Create a key pair <strong>once</strong>,
    keep the private half here, and ship the public half in the app.</p>
  <ol class="muted" style="font-size:13px;line-height:1.8">
    <li>On a machine with OpenSSL, run:
      <pre style="background:var(--soft);padding:10px;border-radius:8px;overflow:auto;white-space:pre-wrap">openssl ecparam -name prime256v1 -genkey -noout -out licence-private.pem
openssl ec -in licence-private.pem -pubout -out licence-public.pem</pre></li>
    <li>Upload <code>licence-private.pem</code> into this app folder (it is gitignored, so an upload never ships it), or
      set its contents as the <code>LICENCE_PRIVKEY</code> environment variable. Keep it on this server only.</li>
    <li>Put the contents of <code>licence-public.pem</code> into <code>LICENCE_PUBKEY</code> on every customer install
      (or replace <code>LICENCE_PUBKEY_DEFAULT</code> in <code>lib/licencekey.php</code>). Reload this page.</li>
  </ol>
  <p class="muted" style="font-size:12.5px">The default build already ships a public key; if you use it, its private
    half is what goes here. Never put the private key on a customer's server.</p>
</div>
<?php else: ?>

<div class="panel settings-form" style="max-width:720px;margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Issue a key</h3>
  <form method="post" action="/issue-licence-new">
    <div class="form-grid">
      <div class="ff ff-wide"><label>Customer</label><input class="form-control" name="customer" required placeholder="Acme Inspection Pvt Ltd"></div>
      <div class="ff"><label>Seats <span class="muted">— people who may sign in</span></label>
        <input class="form-control" type="number" min="0" name="seats" value="5"><small class="muted">0 = unlimited.</small></div>
      <div class="ff"><label>Valid for</label>
        <select class="form-control" name="months">
          <?php foreach ([1=>'1 month',3=>'3 months',6=>'6 months',12=>'1 year',24=>'2 years',36=>'3 years'] as $m=>$lbl): ?>
            <option value="<?= $m ?>" <?= $m===12?'selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>…or an exact expiry date</label><input class="form-control" type="date" name="exp_date">
        <small class="muted">Overrides "valid for" if set.</small></div>
      <div class="ff"><label>Grace period (days)</label><input class="form-control" type="number" min="0" max="120" name="grace" value="<?= (int)LICENCE_GRACE_DEFAULT ?>">
        <small class="muted">Read-only after expiry, for this long, before write is refused.</small></div>
      <div class="ff"><label>Install id <span class="muted">— optional</span></label><input class="form-control" name="install_id" placeholder="for automatic pull">
        <small class="muted">Set this to let the customer's install fetch renewals automatically.</small></div>
    </div>
    <h3 class="tab-sub">Modules included</h3>
    <div class="checkgrid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
      <?php foreach ($modules as $k => $m): $core = !empty($m[3]); ?>
        <label class="chk" style="align-items:flex-start"><input type="checkbox" name="mods[<?= e($k) ?>]" value="1" checked <?= $core?'disabled':'' ?>>
          <span><strong><?= e($m[0]) ?></strong><?= $core?' <span class="pill p-mut">always</span>':'' ?><br><span class="muted" style="font-size:12px"><?= e($m[1]) ?></span></span></label>
      <?php endforeach; ?>
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Generate signed key</button>
  </form>
</div>
<?php endif; ?>

<?php if ($history): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Keys issued</h3>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Customer</th><th>Seats</th><th>Expires</th><th>Install id</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td><code><?= e($h['ref']) ?></code></td><td><?= e($h['customer']) ?></td>
        <td><?= (int)$h['seats'] ?: 'unlimited' ?></td><td class="muted"><?= e(fdate($h['exp'])) ?></td>
        <td class="muted"><?= e($h['install_id'] ?: '—') ?></td><td class="muted"><?= e(fdate(substr((string)$h['created_at'],0,10))) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
