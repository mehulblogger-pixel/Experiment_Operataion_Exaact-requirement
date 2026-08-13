<h2 class="ptitle">Your password</h2>
<?php if (!empty($done)): ?>
  <div class="pmsg ok">Password changed. <a href="/vendor">Go to your overview</a>.</div>
<?php else: ?>
  <p class="plead">Choose a password only you know. We never see it, and we cannot read it back to you — if you
    forget it, ask your contact here and we will send a fresh invitation link.</p>
<?php endif; ?>

<?php if (!empty($err)): ?><div class="pmsg err"><?= e($err) ?></div><?php endif; ?>

<?php if (empty($done)): ?>
<form method="post" action="/vendor/password" style="max-width:430px">
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px" for="vnpass">New password</label>
  <input class="form-control" id="vnpass" name="password" type="password" autocomplete="new-password" required autofocus>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px" for="vnconf">Type it again</label>
  <input class="form-control" id="vnconf" name="confirm" type="password" autocomplete="new-password" required>
  <button class="btn" type="submit" style="margin-top:20px">Save it</button>
</form>
<?php endif; ?>
