<h2 class="ptitle" style="text-align:center;margin-top:30px">Forgot your password?</h2>
<?php if (!empty($sent)): ?>
<p class="plead" style="text-align:center;margin-left:auto;margin-right:auto">
  If that e-mail address has an account here, we have sent it a link to choose a new password.
  The link works once, for one hour. Check your spam folder if it does not arrive in a few minutes.</p>
<p class="pnote" style="max-width:430px;margin:22px auto 0;text-align:center"><a href="/portal/login">Back to sign in</a></p>
<?php else: ?>
<p class="plead" style="text-align:center;margin-left:auto;margin-right:auto">
  Enter the e-mail address you sign in with. We will send you a link to choose a new password.</p>
<?php if (!empty($err)): ?><div class="pmsg err" style="max-width:430px;margin:0 auto"><?= e($err) ?></div><?php endif; ?>
<form class="pform" method="post" action="/portal/forgot">
  <label for="femail">E-mail address</label>
  <input class="form-control" id="femail" name="email" type="email" autocomplete="username" inputmode="email" required autofocus>
  <button class="btn" type="submit" style="margin-top:20px;width:100%">Send me a link</button>
</form>
<p class="pnote" style="max-width:430px;margin:22px auto 0;text-align:center"><a href="/portal/login">Back to sign in</a></p>
<?php endif; ?>
