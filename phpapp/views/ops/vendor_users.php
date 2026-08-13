<div class="crumbs"><a href="/">Home</a> › <span>Vendor portal</span></div>

<div class="master-head">
  <div><h1>Vendor portal</h1>
    <p class="sub" style="margin:2px 0 0">Who at your vendors can sign in, and which reports they may see.</p></div>
</div>

<div class="panel" style="border:1px solid var(--<?= $enabled ? 'ok' : 'warn' ?>);
     background:color-mix(in srgb,var(--<?= $enabled ? 'ok' : 'warn' ?>) 7%,transparent)">
  <form method="post" action="/vendor-settings" style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
    <div style="flex:1;min-width:280px">
      <b><?= $enabled ? 'The vendor portal is on' : 'The vendor portal is off' ?></b>
      <div class="muted" style="margin-top:4px;font-size:13.5px;line-height:1.65">
        <?php if ($enabled): ?>
          Vendors you have invited can sign in at <code><?= e($base) ?>/vendor</code>. Everybody else gets
          "not found". A vendor sees only the reports you have shared — being switched on is not the same as being open.
        <?php else: ?>
          Every <code>/vendor</code> address answers "not found", including for people already invited. Nothing is
          deleted; switching it back on restores their access exactly as it was.
        <?php endif; ?>
      </div>
    </div>
    <div>
      <input type="hidden" name="vendor_portal_enabled" value="<?= $enabled ? '0' : '1' ?>">
      <button class="btn" type="submit"><?= $enabled ? 'Switch it off' : 'Switch it on' ?></button>
    </div>
  </form>
</div>

<?php $link = $invite['link'] ?? ($_SESSION['vendor_invite_link'] ?? ''); unset($_SESSION['vendor_invite_link']); ?>
<?php if ($link !== ''): ?>
<div class="panel" style="border:1px solid var(--info);background:color-mix(in srgb,var(--info) 7%,transparent)">
  <b>Send them this link — it works once, and lasts seven days</b>
  <div class="muted" style="margin-top:4px;font-size:13.5px;line-height:1.65">
    They set their own password on it. We never choose one and never e-mail one.
  </div>
  <p style="margin:10px 0 0"><code style="word-break:break-all"><?= e($link) ?></code></p>
</div>
<?php endif; ?>

<div class="panel">
  <h2 style="margin:0 0 4px;font-size:17px">Invite somebody</h2>
  <p class="muted" style="margin:0 0 14px;font-size:13.5px">
    One person, one address. Only companies marked as a vendor in the directory appear here.
  </p>
  <form method="post" action="/vendor-users" class="form-grid" id="vinviteForm" style="display:grid;gap:12px;
        grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end">
    <input type="hidden" name="contact_id" id="vinv_contact_id" value="">
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Vendor company</label>
      <select class="form-control" name="vendor_id" id="vinv_partner" required>
        <option value="">Choose…</option>
        <?php foreach ($vendors as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Pick a saved contact <span style="opacity:.7">(optional)</span></label>
      <select class="form-control" id="vinv_contact"><option value="">— type below instead —</option></select>
    </div>
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Their name</label>
      <input class="form-control" name="name" id="vinv_name" maxlength="150">
    </div>
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Their e-mail</label>
      <input class="form-control" name="email" id="vinv_email" type="email" required>
    </div>
    <div><button class="btn" type="submit">Create the invitation</button></div>
  </form>
</div>

<script>
(function () {
  var byPartner = <?= json_encode($contactsByPartner ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var partner = document.getElementById('vinv_partner');
  var pick    = document.getElementById('vinv_contact');
  var name    = document.getElementById('vinv_name');
  var email   = document.getElementById('vinv_email');
  var cid     = document.getElementById('vinv_contact_id');
  if (!partner || !pick) return;
  function fill() {
    pick.innerHTML = '<option value="">— type below instead —</option>';
    cid.value = '';
    var list = byPartner[partner.value] || [];
    list.forEach(function (c) {
      var o = document.createElement('option');
      o.value = c.id; o.textContent = c.name + (c.desig ? ' · ' + c.desig : '') + ' — ' + c.email;
      o.dataset.name = c.name; o.dataset.email = c.email;
      pick.appendChild(o);
    });
    pick.disabled = list.length === 0;
  }
  partner.addEventListener('change', fill);
  pick.addEventListener('change', function () {
    var o = pick.options[pick.selectedIndex];
    if (pick.value) { cid.value = pick.value; name.value = o.dataset.name || ''; email.value = o.dataset.email || ''; }
    else { cid.value = ''; }
  });
  email.addEventListener('input', function () { cid.value = ''; pick.value = ''; });
  fill();
})();
</script>

<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:16px 18px 0"><h2 style="margin:0;font-size:17px">Who can sign in</h2></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Vendor</th><th>Person</th><th>E-mail</th><th>State</th><th>Last signed in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['partner_name'] ?: '—') ?></td>
        <td><?= e($r['name'] ?: '—') ?></td>
        <td><?= e($r['email']) ?></td>
        <td><?php if (empty($r['is_active'])): ?>
              <span class="pill p-bad">withdrawn</span>
            <?php elseif ((string)$r['password_hash'] === ''): ?>
              <span class="pill p-warn">invited, not yet accepted</span>
            <?php else: ?>
              <span class="pill p-ok">active</span>
            <?php endif; ?></td>
        <td><?= $r['last_login_at'] ? e(fdate(substr((string)$r['last_login_at'], 0, 10))) : '<span class="muted">never</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/vendor-user-toggle" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm" type="submit"><?= empty($r['is_active']) ? 'Restore' : 'Withdraw' ?></button>
          </form>
          <form method="post" action="/vendor-user-reinvite" style="display:inline"
                onsubmit="return confirm('This cancels their current password and makes a fresh invitation link. Continue?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm" type="submit">New link</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="6" style="text-align:center;padding:24px" class="muted">Nobody invited yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:16px 18px 0">
    <h2 style="margin:0;font-size:17px">Reports you can share</h2>
    <p class="muted" style="margin:4px 0 0;font-size:13.5px">
      A finalized report that names a vendor is NOT automatically theirs to read. Share it here, deliberately, and
      it appears in that vendor's portal. Un-share it and it disappears again.
    </p>
  </div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Report</th><th>Title</th><th>Vendor</th><th>Finalized</th><th>Shared?</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($reports as $r): $on = (int)($r['vendor_visible'] ?? 0); ?>
      <tr>
        <td><b><?= e($r['irn']) ?></b></td>
        <td><?= e($r['title'] ?: '—') ?></td>
        <td><?= e($r['vendor_name'] ?: '—') ?></td>
        <td><?= e(fdate(substr((string)$r['finalized_at'], 0, 10))) ?></td>
        <td><?php if ($on): ?><span class="pill p-ok">shared</span><?php else: ?><span class="muted">not shared</span><?php endif; ?></td>
        <td>
          <form method="post" action="/vendor-share" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="on" value="<?= $on ? '0' : '1' ?>">
            <button class="btn btn-sm <?= $on ? 'secondary' : '' ?>" type="submit"><?= $on ? 'Un-share' : 'Share with vendor' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$reports): ?>
      <tr><td colspan="6" style="text-align:center;padding:24px" class="muted">No finalized reports name a vendor yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
