<?php
// Login screen (public).
if (current_user()) redirect('?p=dashboard');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_attempt(post('username'), post('password'))) redirect('?p=dashboard');
    $err = 'Wrong username or password.';
}
$product = e(product_name());
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= $product ?></title>
<style>
  :root{--brand:<?= e(brand_color()) ?>;--accent:<?= e(accent_color()) ?>}
  *{box-sizing:border-box}
  body{margin:0;font-family:"Inter","Segoe UI",system-ui,Arial,sans-serif;background:#0b1020;color:#0f172a;
       min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#fff;border-radius:16px;width:100%;max-width:390px;padding:30px 28px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
  .brand{display:flex;align-items:center;gap:11px;margin-bottom:22px}
  .mark{width:42px;height:42px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--accent));
        display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:20px}
  .brand b{font-size:18px}
  label{display:block;font-size:12.5px;font-weight:600;color:#334155;margin:14px 0 5px}
  input{width:100%;padding:11px 12px;border:1px solid #e2e8f0;border-radius:9px;font:inherit}
  input:focus{outline:2px solid var(--brand);outline-offset:-1px}
  .btn{width:100%;margin-top:20px;background:var(--brand);color:#fff;border:none;padding:12px;border-radius:9px;
       font-weight:700;font-size:15px;cursor:pointer}
  .err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:10px 12px;border-radius:9px;font-size:13px;margin-top:6px}
  .hint{color:#94a3b8;font-size:12px;margin-top:16px;text-align:center}
</style></head>
<body>
  <form class="card" method="post" autocomplete="off">
    <div class="brand"><div class="mark"><?= e(mb_substr(product_name(),0,1)) ?></div><b><?= $product ?></b></div>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
    <label>Username</label>
    <input name="username" autofocus required>
    <label>Password</label>
    <input name="password" type="password" required>
    <?= csrf_field() ?>
    <button class="btn">Sign in</button>
    <div class="hint">Recruitment &amp; Selection workspace</div>
  </form>
</body></html>
