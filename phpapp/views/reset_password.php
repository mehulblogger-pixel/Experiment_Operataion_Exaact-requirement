<?php $app = function_exists('app_name') ? app_name() : 'Workspace'; ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($app) ?> — Set a new password</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= function_exists('theme_style_tag') ? theme_style_tag() : '' ?>
<style>
  html,body{height:100%} body{margin:0;display:grid;place-items:center;min-height:100%;background:var(--bg);padding:24px}
  .card{width:100%;max-width:400px;background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:32px}
  .hi{font-size:13px;color:var(--brand);font-weight:700}
  h2{font-size:23px;letter-spacing:-.01em;margin:6px 0 4px;color:var(--ink)}
  .s{color:var(--muted);font-size:14px;margin:0 0 20px;line-height:1.55}
  label{font-size:12.5px;font-weight:600;color:var(--muted);display:block;margin-bottom:6px}
  .fld{margin-bottom:14px;position:relative}
  input{width:100%;font-size:15px;padding:12px 14px;border-radius:11px;border:1px solid var(--field-line);background:var(--field);color:var(--ink)}
  input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 22%,transparent);background:var(--card)}
  .go{width:100%;margin-top:6px;background:var(--brand);color:#fff;border:none;padding:13px;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer}
  .go:hover{filter:brightness(1.06)} .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:var(--muted)}
  .err{background:#fee2e2;color:#991b1b;border-radius:10px;padding:11px 14px;font-size:13.5px;margin-bottom:16px;line-height:1.5}
  .ok{background:#dcfce7;color:#166534;border-radius:10px;padding:12px 14px;font-size:13.5px;line-height:1.5;margin-bottom:4px}
  .hint{font-size:12px;color:var(--muted);margin-top:4px}
</style>
</head><body>
  <div class="card">
    <div class="hi">Account recovery</div>
    <?php if (!empty($done)): ?>
      <h2>Password updated</h2>
      <div class="ok"><strong>All set.</strong> Your new password is saved. You can sign in with it now.</div>
      <a class="back" href="/login">Go to sign in →</a>
    <?php elseif (!empty($error) && empty($r)): ?>
      <h2>Link not valid</h2>
      <div class="err"><?= e($error) ?></div>
      <a class="back" href="/forgot">Request a new link →</a>
    <?php else: ?>
      <h2>Set a new password</h2>
      <p class="s">Choose a new password for <strong><?= e($app) ?></strong>. At least 8 characters.</p>
      <?php if (!empty($error)): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="/reset">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="t" value="<?= e($token ?? '') ?>">
        <input type="hidden" name="w" value="<?= e($wkey ?? '') ?>">
        <div class="fld"><label for="p1">New password</label>
          <input id="p1" name="password" type="password" autocomplete="new-password" autofocus required minlength="8" placeholder="••••••••">
          <div class="hint">Tip: use the Show button to check what you typed.</div></div>
        <div class="fld"><label for="p2">Confirm new password</label>
          <input id="p2" name="password2" type="password" autocomplete="new-password" required minlength="8" placeholder="••••••••"></div>
        <button class="go" type="submit">Save new password →</button>
      </form>
      <a class="back" href="/login">← Back to sign in</a>
    <?php endif; ?>
  </div>
  <script src="/assets/js/app.js" defer></script>
</body></html>
