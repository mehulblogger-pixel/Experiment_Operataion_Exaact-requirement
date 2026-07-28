<h2 class="ptitle" style="text-align:center;margin-top:30px">Set your password</h2>
<p class="plead" style="text-align:center;margin-left:auto;margin-right:auto">
  You have been invited to the <?= e(app_name()) ?> client portal. Choose a password now. Nobody here will ever
  know it — that is the point of doing it this way rather than sending you one.</p>

<?php if (!empty($err)): ?><div class="pmsg err" style="max-width:430px;margin:0 auto"><?= e($err) ?></div><?php endif; ?>

<?php if (!empty($dead)): ?>
  <p class="pnote" style="max-width:430px;margin:22px auto 0;text-align:center">
    An invitation link works once and lasts seven days — that is deliberate. If somebody else has already used
    this one, tell your contact here straight away.
    <a href="/portal/login">Sign in</a> if you have already set a password.</p>
<?php else: ?>
<form class="pform" method="post" action="/portal/accept">
  <input type="hidden" name="t" value="<?= e($token ?? '') ?>">
  <label for="apass">New password</label>
  <input class="form-control" id="apass" name="password" type="password" autocomplete="new-password" required autofocus>
  <label for="aconf">Type it again</label>
  <input class="form-control" id="aconf" name="confirm" type="password" autocomplete="new-password" required>
  <button class="btn" type="submit" style="margin-top:20px;width:100%">Set it and sign in</button>
</form>

<p class="pnote" style="max-width:430px;margin:22px auto 0;text-align:center">
  Invitation links last seven days and work once. If yours has expired, ask your contact here for a new one.</p>
<?php endif; ?>
