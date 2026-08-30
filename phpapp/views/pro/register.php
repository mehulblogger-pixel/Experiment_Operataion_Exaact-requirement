<?php $err = $err ?? ''; ?>
<h1>List yourself for work</h1>
<p class="muted" style="margin:0 0 16px">Create your profile once — clients and agencies find you when they need your skills. Free.</p>
<?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
<form method="post" action="/pro/register" class="card">
  <label>Your name</label><input name="name" required autofocus>
  <label>E-mail</label><input type="email" name="email" required>
  <label>Mobile</label><input name="mobile">
  <label>Password (8+ characters)</label><input type="password" name="password" required minlength="8">
  <button class="btn" type="submit" style="margin-top:16px;width:100%">Create my profile</button>
</form>
<p class="muted">Already registered? <a href="/pro/login">Sign in →</a></p>
<p class="muted" style="font-size:13.5px">Hiring, or an agency? <a href="/connect">See all options →</a></p>
