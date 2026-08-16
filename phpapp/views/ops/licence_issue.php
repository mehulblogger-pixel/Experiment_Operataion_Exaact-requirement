<?php $can = $can_sign ?? false; $modules = $modules ?? []; $history = $history ?? []; $beats = $beats ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/licence">Licence</a> › Issue a licence</div>
<div class="master-head"><div>
  <h1>Licence console</h1>
  <p class="sub" style="margin:2px 0 0">Generate a signed licence key for a customer. Only this server can sign one —
    the customer's copy can check a key but never create one, which is what keeps the seat count honest.</p>
</div></div>

<?php if (!$can): ?>
<div class="panel" style="max-width:720px;margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Set up signing first</h3>
  <p class="sub">This server has no signing key yet, so it cannot issue licences. The easiest way is one click — the app
    makes the key itself and saves it here. No commands, no files.</p>
  <form method="post" action="/signing-setup" onsubmit="return confirm('Set up the signing key now? It stays on this server.')" style="margin:0 0 14px">
    <button class="btn" type="submit">Set up signing (one click)</button>
  </form>
  <details><summary style="cursor:pointer;font-size:13px" class="muted">Or set it up by hand (if the one click cannot write the file)</summary>
  <ol class="muted" style="font-size:13px;line-height:1.8;margin-top:8px">
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
  </details>
</div>
<?php else: ?>

<div class="panel settings-form" style="max-width:720px;margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Issue a key</h3>
  <form method="post" action="/issue-licence-new">
    <div class="form-grid">
      <div class="ff ff-wide"><label>Customer</label><input class="form-control" name="customer" required placeholder="Acme Inspection Pvt Ltd"></div>
      <div class="ff"><label>Seats <span class="muted">— people who may sign in</span></label>
        <input class="form-control" type="number" min="0" name="seats" value="5"><small class="muted">0 = unlimited.</small></div>
      <div class="ff"><label>…of which field seats <span class="muted">— optional</span></label>
        <input class="form-control" type="number" min="0" name="field_seats" value="0"><small class="muted">Light seats for inspectors, capped separately. 0 = one flat pool.</small></div>
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
      <?php // What the customer was billed for this key — a record for us, so the
            // "Keys issued" list can answer "how much did we charge". It is NOT part
            // of the signed key; it changes nothing the customer's copy enforces. ?>
      <div class="ff"><label>Price charged <span class="muted">— optional, our record only</span></label>
        <div style="display:flex;gap:6px">
          <input class="form-control" style="max-width:90px" name="currency" value="<?= e(function_exists('billing_config') ? billing_config()['currency'] : 'INR') ?>" maxlength="5">
          <input class="form-control" type="number" min="0" step="1" name="amount" value="0" placeholder="total amount">
        </div>
        <small class="muted">The total you invoiced for this licence. Leave 0 if billed elsewhere.</small></div>
    </div>
    <?php // One-click plan: pre-fills the modules below. Server also honours `tier` for robustness.
    $planTiers = function_exists('superadmin_tiers') ? superadmin_tiers() : []; ?>
    <?php if ($planTiers): ?>
    <h3 class="tab-sub">Plan <span class="muted" style="font-weight:400;font-size:12px">— pick one to set the modules, or choose Custom below</span></h3>
    <input type="hidden" name="tier" id="lic_tier" value="">
    <div class="row-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px">
      <?php foreach ($planTiers as $tk=>$t): ?>
        <button type="button" class="btn secondary lic-plan" data-tier="<?= e($tk) ?>" data-mods="<?= e(implode(',', $t['mods'])) ?>" title="<?= e($t['pitch']) ?>"><?= e($t['label']) ?></button>
      <?php endforeach; ?>
      <button type="button" class="btn btn-ghost lic-plan" data-tier="" data-mods="__ALL__">Custom / everything</button>
    </div>
    <?php endif; ?>
    <h3 class="tab-sub">Modules included</h3>
    <div class="checkgrid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
      <?php foreach ($modules as $k => $m): $core = !empty($m[3]); ?>
        <label class="chk" style="align-items:flex-start"><input type="checkbox" class="lic-mod" data-mod="<?= e($k) ?>" name="mods[<?= e($k) ?>]" value="1" checked <?= $core?'disabled':'' ?>>
          <span><strong><?= e($m[0]) ?></strong><?= $core?' <span class="pill p-mut">always</span>':'' ?><br><span class="muted" style="font-size:12px"><?= e($m[1]) ?></span></span></label>
      <?php endforeach; ?>
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Generate signed key</button>
    <?php if ($planTiers): ?>
    <script>(function(){
      var btns=document.querySelectorAll('.lic-plan'), boxes=document.querySelectorAll('.lic-mod'), tierField=document.getElementById('lic_tier');
      btns.forEach(function(b){ b.addEventListener('click', function(){
        var all=b.getAttribute('data-mods')==='__ALL__', mods=all?null:(b.getAttribute('data-mods')||'').split(',');
        if(tierField) tierField.value = b.getAttribute('data-tier')||'';
        boxes.forEach(function(cb){ if(cb.disabled) return; cb.checked = all ? true : mods.indexOf(cb.getAttribute('data-mod'))>=0; });
        btns.forEach(function(x){ x.classList.remove('primary'); }); if(!all) b.classList.add('primary');
      }); });
    })();</script>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<?php if ($beats): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Installations — what each copy last reported</h3>
  <p class="sub" style="margin:0 0 10px">Every self-hosted copy that renews itself checks in here. A red note means a copy
    to look at: one that has gone quiet, is over its seats, or reports it is running unlicensed although you issued it a key.
    Reports are what the copy tells us, so they flag honest drift and copies going dark — they are not proof against someone
    who deliberately hacks their own server.</p>
  <table class="dt">
    <thead><tr><th>Customer</th><th>State</th><th>Users</th><th>Last heard</th><th>Concern</th></tr></thead>
    <tbody>
    <?php foreach ($beats as $b): $bad = !empty($b['flags']); ?>
      <tr<?= $bad ? ' style="background:rgba(220,38,38,.06)"' : '' ?>>
        <td><b><?= e($b['customer'] ?: '—') ?></b><br><span class="muted" style="font-size:11px"><?= e($b['host'] ?: $b['install_id']) ?></span></td>
        <td><?= e($b['state'] ?: '—') ?></td>
        <td><?= (int)$b['seats_used'] ?><?= (int)$b['seats_lic'] > 0 ? ' / ' . (int)$b['seats_lic'] : '' ?></td>
        <td class="muted"><?= (int)$b['days_ago'] === 0 ? 'today' : ((int)$b['days_ago'] . ' day' . ((int)$b['days_ago'] === 1 ? '' : 's') . ' ago') ?></td>
        <td><?php if ($bad): ?><span class="pill bad"><?= e(implode('; ', $b['flags'])) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($history): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Keys issued</h3>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Customer</th><th>Seats</th><th class="num">Charged</th><th>Expires</th><th>Install id</th><th>When</th><?php if ($can): ?><th>Reissue</th><?php endif; ?></tr></thead>
    <tbody>
    <?php $chgTot = [];
      foreach ($history as $h): ?>
      <tr><td><code><?= e($h['ref']) ?></code></td><td><?= e($h['customer']) ?></td>
        <td><?= (int)$h['seats'] ?: 'unlimited' ?></td>
        <?php $amt = (int)($h['amount'] ?? 0); $ccy = (string)($h['currency'] ?? '');
              if ($amt > 0) $chgTot[$ccy ?: '?'] = ($chgTot[$ccy ?: '?'] ?? 0) + $amt; ?>
        <td class="num"><?= $amt > 0 ? e(($ccy ? $ccy . ' ' : '') . number_format($amt)) : '<span class="muted">—</span>' ?></td>
        <td class="muted"><?= e(fdate($h['exp'])) ?></td>
        <td class="muted"><?= e($h['install_id'] ?: '—') ?></td><td class="muted"><?= e(fdate(substr((string)$h['created_at'],0,10))) ?></td>
        <?php if ($can): ?>
        <td><?php if ((int)$h['seats'] > 0): ?>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
            <?php foreach ([5 => '+5', 1 => '+1', -1 => '−1', -5 => '−5'] as $d => $lbl): if ((int)$h['seats'] + $d < 1 && $d < 0) continue; ?>
              <form method="post" action="/issue-licence-reissue" style="margin:0"
                    onsubmit="return confirm('Reissue <?= e($h['customer']) ?> with <?= (int)$h['seats'] + $d ?> seats?')">
                <input type="hidden" name="src" value="<?= (int)$h['id'] ?>"><input type="hidden" name="delta" value="<?= $d ?>">
                <button class="btn small<?= $d < 0 ? ' secondary' : '' ?>" type="submit" style="padding:2px 8px;font-size:12px"><?= $lbl ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php else: ?><span class="muted" style="font-size:12px">unlimited</span><?php endif; ?></td>
        <?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($chgTot): ?><p class="sub" style="margin:10px 0 0"><strong>Recorded charged:</strong>
    <?= e(implode(' · ', array_map(fn($c, $v) => ($c === '?' ? '' : $c . ' ') . number_format($v), array_keys($chgTot), $chgTot))) ?>
    <span class="muted">— from the keys where a price was entered.</span></p><?php endif; ?>
  <p class="muted" style="font-size:12px;margin:8px 0 0">Reissue keeps the same expiry and modules and only changes the seat count —
    hand the customer the new key it produces (or they pick it up automatically if they renew online). The
    <em>Charged</em> column is your own record; the licence itself carries seats, expiry and modules, never a price.</p>
</div>
<?php endif; ?>
