<?php $err = $err ?? ''; ?>
<h1>Welcome back</h1>
<p class="muted" style="margin:0 0 16px">Sign in to your professional profile.</p>
<?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
<form method="post" action="/pro/login" class="card">
  <label>E-mail</label><input type="email" name="email" required autofocus>
  <label>Password</label><input type="password" name="password" required>
  <button class="btn" type="submit" style="margin-top:16px;width:100%">Sign in</button>
  <p style="margin:12px 0 0;text-align:center"><a href="/pro/forgot" style="font-size:14px">Forgot your password?</a></p>
</form>
<p class="muted">New here? <a href="/pro/register">Create your professional profile →</a></p>
