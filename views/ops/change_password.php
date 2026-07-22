<h1>Change password</h1>
<p class="sub">Please change the default password after first login.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/change-password" class="panel" style="max-width:460px;">
  <div class="ff"><label>Current password</label><input class="form-control" type="password" name="current" required></div>
  <div class="ff"><label>New password (min 8 characters)</label><input class="form-control" type="password" name="new" required></div>
  <div class="ff"><label>Confirm new password</label><input class="form-control" type="password" name="confirm" required></div>
  <div style="margin-top:16px;"><button class="btn" type="submit">Update password</button>
    <a class="btn secondary" href="/">Cancel</a></div>
</form>
