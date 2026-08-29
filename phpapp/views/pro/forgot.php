<?php $sent = $sent ?? false; ?>
<h1>Reset your password</h1>
<?php if ($sent): ?>
  <div class="card">
    <div class="msg ok" style="margin:0">If that e-mail is registered with us, we've sent a reset link. It's valid for 1 hour.</div>
    <p class="muted" style="margin:12px 0 0">Didn't get it? Check spam, or <a href="/pro/forgot">try again</a>.</p>
  </div>
  <p class="muted"><a href="/pro/login">← Back to sign in</a></p>
<?php else: ?>
  <p class="muted" style="margin:0 0 16px">Enter your e-mail and we'll send you a link to set a new password.</p>
  <form method="post" action="/pro/forgot" class="card">
    <label>E-mail</label><input type="email" name="email" required autofocus>
    <button class="btn" type="submit" style="margin-top:16px;width:100%">Send reset link</button>
  </form>
  <p class="muted"><a href="/pro/login">← Back to sign in</a></p>
<?php endif; ?>
