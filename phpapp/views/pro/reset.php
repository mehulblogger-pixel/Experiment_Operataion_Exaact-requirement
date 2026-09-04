<?php $token = $token ?? ''; $problem = $problem ?? ''; $done = $done ?? false; $err = $err ?? ''; ?>
<h1>Set a new password</h1>
<?php if ($done): ?>
  <div class="card"><div class="msg ok" style="margin:0">Your password has been reset. You can now sign in.</div></div>
  <p><a class="btn" href="/pro/login">Sign in →</a></p>
<?php elseif ($problem !== ''): ?>
  <div class="card"><div class="msg err" style="margin:0"><?= e($problem) ?></div>
    <p class="muted" style="margin:12px 0 0"><a href="/pro/forgot">Request a new reset link →</a></p></div>
  <p class="muted"><a href="/pro/login">← Back to sign in</a></p>
<?php else: ?>
  <p class="muted" style="margin:0 0 16px">Choose a new password for your professional account.</p>
  <?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" action="/pro/reset" class="card">
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <label>New password (8+ characters)</label><input type="password" name="password" required minlength="8" autofocus>
    <label>Confirm new password</label><input type="password" name="password2" required minlength="8">
    <button class="btn" type="submit" style="margin-top:16px;width:100%">Set new password</button>
  </form>
<?php endif; ?>
