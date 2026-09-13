<?php $app = function_exists('app_name') ? app_name() : 'Workspace'; ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($app) ?> — Reset your password</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= function_exists('theme_style_tag') ? theme_style_tag() : '' ?>
<style>
  html,body{height:100%} body{margin:0;display:grid;place-items:center;min-height:100%;background:var(--bg);padding:24px}
  .card{width:100%;max-width:400px;background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:32px}
  .hi{font-size:13px;color:var(--brand);font-weight:700}
  h2{font-size:23px;letter-spacing:-.01em;margin:6px 0 4px;color:var(--ink)}
  .s{color:var(--muted);font-size:14px;margin:0 0 20px;line-height:1.55}
  label{font-size:12.5px;font-weight:600;color:var(--muted);display:block;margin-bottom:6px}
  input{width:100%;font-size:15px;padding:12px 14px;border-radius:11px;border:1px solid var(--field-line);background:var(--field);color:var(--ink)}
  input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 22%,transparent);background:var(--card)}
  .go{width:100%;margin-top:16px;background:var(--brand);color:#fff;border:none;padding:13px;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer}
  .go:hover{filter:brightness(1.06)} .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:var(--muted)}
  .ok{background:#dcfce7;color:#166534;border-radius:10px;padding:12px 14px;font-size:13.5px;line-height:1.5;margin-bottom:4px}
</style>
</head><body>
  <div class="card">
    <div class="hi">Account recovery</div>
    <h2>Reset your password</h2>
    <?php if (!empty($sent)): ?>
      <div class="ok"><strong>Check your e-mail.</strong> If an account exists for that address, we've sent a link to set a new password. It's valid for 60 minutes. Remember to check spam.</div>
      <a class="back" href="/login">← Back to sign in</a>
    <?php else: ?>
      <p class="s">Enter the e-mail address you sign in with and we'll send you a link to set a new password.</p>
      <form method="post" action="/forgot">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label for="email">Your e-mail address</label>
        <input id="email" name="email" type="email" autocomplete="email" autofocus required placeholder="you@company.com" value="<?= e($emailShown ?? '') ?>">
        <button class="go" type="submit">Send reset link →</button>
      </form>
      <a class="back" href="/login">← Back to sign in</a>
    <?php endif; ?>
  </div>
</body></html>
