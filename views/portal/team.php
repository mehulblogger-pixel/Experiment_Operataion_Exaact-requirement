<h2 class="ptitle">Your team</h2>
<p class="plead">You manage who at <?= e(portal_client_name()) ?> can sign in here. Invite a colleague, withdraw
  someone who has left, or set a date after which access ends on its own.</p>

<?php if (!empty($err)): ?><div class="pmsg err"><?= e($err) ?></div><?php endif; ?>

<?php $link = $invite['link'] ?? ''; if ($link): ?>
<div class="pcard" style="border-left:5px solid var(--ok)">
  <b>Send them this link — it works once, and lasts seven days</b>
  <p class="pnote" style="margin:8px 0 0"><code style="word-break:break-all"><?= e($link) ?></code></p>
</div>
<?php endif; ?>

<form method="post" action="/portal/team" class="pcard" style="max-width:640px">
  <input type="hidden" name="act" value="invite">
  <div style="font-weight:600;margin-bottom:10px">Invite a colleague</div>
  <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Their name</label>
  <input class="form-control" name="name" maxlength="150">
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Their e-mail</label>
  <input class="form-control" name="email" type="email" required>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Access ends on <span style="opacity:.7">(optional)</span></label>
  <input class="form-control" name="access_expires" type="date">
  <div style="margin-top:16px"><button class="btn" type="submit">Create the invitation</button></div>
</form>

<div class="pscroll"><table class="ptable">
  <thead><tr><th>Person</th><th>E-mail</th><th>State</th><th>Access ends</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $isMe = (int)$r['id'] === (int)$me; ?>
    <tr>
      <td><?= e($r['name'] ?: '—') ?><?= !empty($r['is_org_admin']) ? ' <span class="badge" style="font-size:11px">admin</span>' : '' ?><?= $isMe ? ' <span class="pnote">(you)</span>' : '' ?></td>
      <td><?= e($r['email']) ?></td>
      <td><?php if (empty($r['is_active'])): ?><span class="badge" style="font-size:12px">withdrawn</span>
          <?php elseif ((string)$r['password_hash'] === ''): ?><span class="badge AMBER" style="font-size:12px">invited</span>
          <?php else: ?><span class="badge GREEN" style="font-size:12px">active</span><?php endif; ?></td>
      <td>
        <form method="post" action="/portal/team" style="display:flex;gap:6px;align-items:center">
          <input type="hidden" name="act" value="expiry"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input class="form-control" name="access_expires" type="date" value="<?= e($r['access_expires'] ?: '') ?>" style="max-width:150px">
          <button class="btn btn-sm secondary" type="submit">Set</button>
        </form>
      </td>
      <td><?php if (!$isMe): ?>
        <form method="post" action="/portal/team" style="display:inline">
          <input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm" type="submit"><?= empty($r['is_active']) ? 'Restore' : 'Withdraw' ?></button>
        </form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="pnote">Making somebody an administrator like you is done by your contact at <?= e(app_name()) ?>.</p>
