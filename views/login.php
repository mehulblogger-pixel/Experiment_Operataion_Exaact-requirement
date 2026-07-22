<div class="panel" style="max-width:380px;margin:60px auto;">
  <h1>Sign in</h1>
  <p class="sub">Inspection Operations Management</p>
  <?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/login" class="stack">
    <label>Username</label>
    <input class="form-control" type="text" name="username" autofocus required>
    <label>Password</label>
    <input class="form-control" type="password" name="password" required>
    <div style="margin-top:16px;"><button class="btn" type="submit">Sign in</button></div>
  </form>
</div>
