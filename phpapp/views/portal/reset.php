<h2 class="ptitle" style="text-align:center;margin-top:30px">Choose a new password</h2>
<?php if (!empty($err)): ?><div class="pmsg err" style="max-width:430px;margin:0 auto"><?= e($err) ?></div><?php endif; ?>
<?php if (!empty($dead)): ?>
  <p class="pnote" style="max-width:430px;margin:22px auto 0;text-align:center">
    <a href="/portal/forgot">Send me a new link</a> &nbsp;·&nbsp; <a href="/portal/login">Sign in</a></p>
<?php else: ?>
<form class="pform" method="post" action="/portal/reset">
  <input type="hidden" name="t" value="<?= e($token ?? '') ?>">
  <label for="rpass">New password</label>
  <input class="form-control" id="rpass" name="password" type="password" autocomplete="new-password" required autofocus>
  <label for="rconf">Type it again</label>
  <input class="form-control" id="rconf" name="confirm" type="password" autocomplete="new-password" required>
  <button class="btn" type="submit" style="margin-top:20px;width:100%">Save and sign in</button>
</form>
<?php endif; ?>
