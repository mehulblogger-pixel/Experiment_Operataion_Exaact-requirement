<div class="crumbs"><a href="/">Home</a> › <span>Client portal</span></div>

<div class="master-head">
  <div><h1>Client portal</h1>
    <p class="sub" style="margin:2px 0 0">Who at your clients can sign in, and what they have asked us for.</p></div>
</div>

<div class="panel" style="border:1px solid var(--<?= $enabled ? 'ok' : 'warn' ?>);
     background:color-mix(in srgb,var(--<?= $enabled ? 'ok' : 'warn' ?>) 7%,transparent)">
  <form method="post" action="/portal-settings" style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
    <div style="flex:1;min-width:280px">
      <b><?= $enabled ? 'The portal is on' : 'The portal is off' ?></b>
      <div class="muted" style="margin-top:4px;font-size:13.5px;line-height:1.65">
        <?php if ($enabled): ?>
          Clients you have invited can sign in at <code><?= e($base) ?>/portal</code>. Everybody else gets
          "not found" — being switched on is not the same as being open.
        <?php else: ?>
          Every portal address answers "not found", including for people already invited. Nothing is deleted;
          switching it back on restores their access exactly as it was.
        <?php endif; ?>
      </div>
    </div>
    <div>
      <input type="hidden" name="portal_enabled" value="<?= $enabled ? '0' : '1' ?>">
      <button class="btn" type="submit"><?= $enabled ? 'Switch it off' : 'Switch it on' ?></button>
    </div>
  </form>
</div>

<?php $link = $invite['link'] ?? ($_SESSION['portal_invite_link'] ?? ''); unset($_SESSION['portal_invite_link']); ?>
<?php if ($link !== ''): ?>
<div class="panel" style="border:1px solid var(--info);background:color-mix(in srgb,var(--info) 7%,transparent)">
  <b>Send them this link — it works once, and lasts seven days</b>
  <div class="muted" style="margin-top:4px;font-size:13.5px;line-height:1.65">
    They set their own password on it. We never choose one and never e-mail one, so there is no password of
    theirs for anybody here to know, lose or be blamed for.
  </div>
  <p style="margin:10px 0 0"><code style="word-break:break-all"><?= e($link) ?></code></p>
</div>
<?php endif; ?>

<div class="panel">
  <h2 style="margin:0 0 4px;font-size:17px">Invite somebody</h2>
  <p class="muted" style="margin:0 0 14px;font-size:13.5px">
    One person, one address. Give each person at the client their own sign-in rather than sharing one — a shared
    login cannot be withdrawn from the one who leaves.
  </p>
  <form method="post" action="/portal-users" class="form-grid" style="display:grid;gap:12px;
        grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end">
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Client company</label>
      <select class="form-control" name="partner_id" required>
        <option value="">Choose…</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Their name</label>
      <input class="form-control" name="name" maxlength="150">
    </div>
    <div>
      <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Their e-mail</label>
      <input class="form-control" name="email" type="email" required>
    </div>
    <div><button class="btn" type="submit">Create the invitation</button></div>
  </form>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:16px 18px 0"><h2 style="margin:0;font-size:17px">Who can sign in</h2></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Client</th><th>Person</th><th>E-mail</th><th>State</th><th>What they can do</th><th>Last signed in</th><th></th></tr></thead>
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
        <?php
          // Blank means everything, so somebody invited before per-person
          // access existed does not silently lose it on upgrade. Said in
          // words, because a row of empty boxes would read as "no access".
          $held = trim((string)($r['perms'] ?? ''));
          $heldArr = $held === '' ? array_keys(PORTAL_PERMS) : array_filter(explode(',', $held));
          $sites = array_filter(explode(',', (string)($r['site_ids'] ?? '')));
        ?>
        <td style="max-width:260px">
          <?php if ($held === ''): ?><span class="pill p-warn">everything</span>
          <?php else: ?>
            <span style="font-size:12px"><?= e(implode(', ', array_map(fn($k) => portal_perm_labels()[$k] ?? $k, $heldArr))) ?></span>
          <?php endif; ?>
          <?php if ($sites): ?><br><span class="muted" style="font-size:12px"><?= count($sites) ?> site(s) only</span><?php endif; ?>
          <br><a href="/portal-user-perms?id=<?= (int)$r['id'] ?>" style="font-size:12px">Change</a>
        </td>
        <td><?= $r['last_login_at'] ? e(fdate(substr((string)$r['last_login_at'], 0, 10))) : '<span class="muted">never</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/portal-user-toggle" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm" type="submit"><?= empty($r['is_active']) ? 'Restore' : 'Withdraw' ?></button>
          </form>
          <form method="post" action="/portal-user-reinvite" style="display:inline"
                onsubmit="return confirm('This cancels their current password and makes a fresh invitation link. Continue?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm" type="submit">New link</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="7" style="text-align:center;padding:24px" class="muted">Nobody invited yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:16px 18px 0">
    <h2 style="margin:0;font-size:17px">What clients have asked for</h2>
    <p class="muted" style="margin:4px 0 0;font-size:13.5px">
      A request is not work. Somebody here reads it, decides, and raises the <?= e(Tl('call')) ?> — which is why
      a client cannot put a job into the register themselves.
    </p>
  </div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Asked</th><th>Client</th><th>What for</th><th>Where</th><th>Wanted by</th>
      <th>State</th><th style="min-width:280px">Answer them</th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td><?= e(fdate(substr((string)$r['created_at'], 0, 10))) ?></td>
        <td><?= e($r['partner_name'] ?: '—') ?></td>
        <td><?= e($r['subject']) ?>
          <div class="muted" style="font-size:12px;white-space:pre-wrap;max-width:38ch"><?= e($r['detail']) ?></div>
          <?php if ($r['contact_name'] || $r['contact_phone']): ?>
            <div class="muted" style="font-size:12px"><?= e(trim($r['contact_name'] . ' ' . $r['contact_phone'])) ?></div>
          <?php endif; ?></td>
        <td><?= e($r['site'] ?: '—') ?></td>
        <td><?= e(fdate($r['wanted_on'])) ?></td>
        <td><span class="pill <?= $r['status'] === 'NEW' ? 'p-warn' : ($r['status'] === 'DECLINED' ? 'p-bad' : 'p-ok') ?>">
              <?= e(PORTAL_REQ_STATUS[$r['status']] ?? $r['status']) ?></span>
            <?php if (!empty($r['handled_by'])): ?>
              <div class="muted" style="font-size:12px"><?= e($r['handled_by']) ?></div>
            <?php endif; ?></td>
        <td>
          <?php if ($r['status'] === 'NEW'): ?>
          <form method="post" action="/portal-request">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input class="form-control" name="reply" placeholder="What shall we tell them?" style="margin-bottom:6px">
            <button class="btn btn-sm" type="submit" name="status" value="CONVERTED">Accept &amp; raise the call</button>
            <button class="btn btn-sm secondary" type="submit" name="status" value="ACCEPTED">Just acknowledge</button>
            <button class="btn btn-sm secondary" type="submit" name="status" value="DECLINED">Decline</button>
            <div class="muted" style="font-size:12px;margin-top:4px">Raising the call creates it and drops you on it to
              set scope and who goes. Declining needs a reason — silence is why clients stop using a portal.</div>
          </form>
          <?php elseif ($r['status'] === 'ACCEPTED'): ?>
            <?php if (!empty($r['reply'])): ?><div class="muted" style="font-size:12px;margin-bottom:6px"><?= e($r['reply']) ?></div><?php endif; ?>
            <form method="post" action="/portal-request">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm" type="submit" name="status" value="CONVERTED">Raise the call now</button>
            </form>
          <?php elseif ($r['status'] === 'CONVERTED' && !empty($r['call_id'])): ?>
            <a class="btn btn-sm secondary" href="/call?id=<?= (int)$r['call_id'] ?>">Open the call →</a>
            <?php if (!empty($r['reply'])): ?><div class="muted" style="font-size:12px;margin-top:4px"><?= e($r['reply']) ?></div><?php endif; ?>
          <?php else: ?>
            <span class="muted" style="font-size:13px"><?= e($r['reply'] ?: '—') ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?>
      <tr><td colspan="7" style="text-align:center;padding:24px" class="muted">Nothing asked yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
