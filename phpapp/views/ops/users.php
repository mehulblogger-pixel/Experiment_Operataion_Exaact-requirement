<div class="master-head">
  <div><h1><?= e(T_REG('user')) ?></h1>
    <p class="sub"><?= (int)$active ?> active user(s)<?= $seats!=='' ? ' · seat limit ' . e($seats) : '' ?></p></div>
  <div style="display:flex;gap:6px">
    <a class="btn secondary" href="/hierarchy?tab=import" title="Download the register, edit it in Excel, upload it back">Add / update in bulk (Excel)</a>
    <a class="btn secondary" href="/hierarchy?tab=people">Roles &amp; reporting grid</a>
    <a class="btn" href="/user-new">+ Add user</a>
  </div>
</div>
<?php if ($seats!=='' && $active >= (int)$seats): ?>
  <div class="msg msg-error">You have reached your licensed seat limit (<?= e($seats) ?>). Deactivate a user before adding a new one.</div>
<?php endif; ?>

<?php // An account still on the password it was handed is how a small system is
      // usually broken into — not by anything clever. It is named here, to the
      // one person who can do something about it, rather than sitting quietly. ?>
<?php if (!empty($defaults)): ?>
<div class="panel" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 7%,transparent)">
  <b style="color:var(--bad)">⚠ Still on the password it was given</b>
  <div class="muted" style="margin-top:4px">
    These accounts can be signed into by anybody who has seen this software before. Open each one, set a
    password, and tick <strong>make them choose their own at the next sign-in</strong>.
  </div>
  <div style="margin-top:6px">
    <?php foreach ($defaults as $d): ?>
      <a class="pill p-bad" href="/user-edit?id=<?= (int)$d['id'] ?>" style="text-decoration:none"><?= e($d['username']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($locked)): ?>
<div class="panel" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
  <b style="color:var(--warn)">◷ Resting after too many wrong passwords</b>
  <div class="muted" style="margin-top:4px">These clear themselves. Release one early only when you know who is asking.</div>
  <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php foreach ($locked as $l): ?>
      <form method="post" action="/user-unlock" style="display:inline-flex;gap:6px;align-items:center">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <span class="pill p-warn"><?= e($l['username']) ?> · <?= (int)$l['minutes'] ?> min</span>
        <button class="btn small secondary" type="submit">Release now</button>
      </form>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<table class="grid">
  <tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Signature</th><th>Two-step</th><th>Last seen</th><th>Active</th><th>Actions</th></tr>
  <?php foreach ($rows as $u): ?>
  <tr>
    <td><?= e($u['username']) ?></td>
    <td><?= e(trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: '—') ?></td>
    <td><?= e($u['email'] ?: '—') ?></td>
    <td><?= e(ORG_ROLES[!empty($u['is_superuser'])?'MASTER_ADMIN':strtoupper($u['role'] ?? 'ADMIN')] ?? $u['role']) ?></td>
    <?php // A user with no signature on file has their approvals and quotations
          // printed without one, so it belongs in the register, not hidden. ?>
    <td><?= trim((string)($u['signature'] ?? '')) !== ''
            ? '<span class="pill p-ok">on file</span>'
            : '<span class="pill p-warn">none</span>' ?></td>
    <?php // Whether the account needs a code as well as a password, and whether
          // the role says it must. "Required, not set up" is the one to chase. ?>
    <td><?php $on = !empty($u['totp_enabled']); $req = twofa_required_for($u);
      if ($on) echo '<span class="pill p-ok">on</span>';
      elseif ($req) echo '<span class="pill p-bad">required, not set up</span>';
      else echo '<span class="pill p-mut">off</span>'; ?></td>
    <td><?= $u['last_login_at'] ? e(fdate($u['last_login_at'])) : '<span class="muted">never</span>' ?></td>
    <td><?= $u['is_active'] ? '<span class="badge GREEN">Yes</span>' : '<span class="badge RED">No</span>' ?></td>
    <td class="row-actions"><a class="btn small" href="/user-edit?id=<?= (int)$u['id'] ?>">Edit</a>
      <?php if (!empty($u['totp_enabled']) && (is_master() || can('users.manage.global'))): ?>
        <form method="post" action="/user-2fa-reset" style="display:inline"
              onsubmit="return confirm('Clear two-step sign-in for <?= e($u['username']) ?>? Do this only when you are sure who you are speaking to — it removes a lock on their account.')">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn small secondary" type="submit">Reset two-step</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="muted" style="margin-top:12px;">Roles: <strong>Master Admin</strong> sees salary &amp; profit; <strong>Admin</strong> runs scheduling &amp; recon; <strong>Coordinator</strong> creates calls/jobs; <strong>Inspector</strong> sees only their own jobs. The seat limit is set on the server with the <code>SEAT_LIMIT</code> setting.</p>
