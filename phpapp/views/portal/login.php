<h2 class="ptitle" style="text-align:center;margin-top:30px">Sign in</h2>
<p class="plead" style="text-align:center;margin-left:auto;margin-right:auto">
  For clients of <?= e(app_name()) ?>. You will see your own inspection requests, the reports we have issued to
  you, and your invoices — nothing belonging to anybody else.</p>

<?php if (!empty($err)): ?><div class="pmsg err" style="max-width:430px;margin:0 auto"><?= e($err) ?></div><?php endif; ?>

<form class="pform" method="post" action="/portal/login">
  <label for="pemail">E-mail address</label>
  <input class="form-control" id="pemail" name="email" type="email" autocomplete="username"
         value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
  <label for="ppass">Password</label>
  <input class="form-control" id="ppass" name="password" type="password" autocomplete="current-password" required>
  <button class="btn" type="submit" style="margin-top:20px;width:100%">Sign in</button>
</form>

<p class="pnote" style="max-width:430px;margin:22px auto 0;text-align:center">
  <strong>New here?</strong> A company or agency can <a href="/join">create an account →</a>.
  An individual professional can <a href="/pro/register">list themselves →</a>.
  If your contact here invited you, the link they sent lets you set your own password.</p>

<p class="pnote" style="max-width:430px;margin:14px auto 0;text-align:center">
  Holding a report and only want to check it is genuine? You do not need an account —
  <a href="/verify">check the code on it</a>.</p>
