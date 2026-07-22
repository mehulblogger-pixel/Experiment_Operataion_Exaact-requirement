<?php $u = current_user(); ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Inspection Ops') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<?php
  $brandColor = valid_hex_color(setting_get('brand_color', '#1e40af'));
  $brandInk = brand_ink($brandColor);
  $brandLogo = setting_get('brand_logo', '');
  $brandName = setting_get('brand_name', '') ?: 'Inspection Ops';
?>
<style>
  :root{--brand:<?= $brandColor ?>;--brand-ink:<?= $brandInk ?>;}
  .topbar{background:var(--brand);color:var(--brand-ink)}
  .topbar .brand,.topbar .user,.topbar nav a,.topbar nav a:hover,.logout{color:var(--brand-ink)}
  .topbar nav a{opacity:.85}.topbar nav a:hover{opacity:1}
  .btn{background:var(--brand);border-color:var(--brand);color:var(--brand-ink)}
  .btn.secondary{background:#fff;color:var(--brand)}
  .brand-logo{height:30px;width:auto;vertical-align:middle;margin-right:10px;border-radius:4px;background:#fff;padding:2px}
</style>
</head><body>
<header class="topbar">
  <a class="brand" href="/"><?php if ($brandLogo): ?><img class="brand-logo" src="<?= e($brandLogo) ?>" alt="logo"><?php endif; ?><?= e($brandName) ?></a>
  <?php if ($u): ?>
  <nav>
    <a href="/">Dashboard</a>
    <?php if (is_inspector()): ?>
      <a href="/my-jobs">My Jobs</a>
    <?php else: ?>
      <a href="/calls">Calls</a>
      <a href="/jobs">Jobs</a>
      <a href="/clients">Clients</a>
      <a href="/vendors">Vendors</a>
      <a href="/masters">Masters</a>
      <?php if (can('dash.operations') || can('dash.financial') || can('dash.utilization') || can('dash.people')): ?><a href="/reports">Dashboards</a><?php endif; ?>
      <?php if (can('users.manage.branch') || can('users.manage.global')): ?><a href="/users">Users</a><?php endif; ?>
      <?php if (can('settings.manage')): ?><a href="/settings">Settings</a><?php endif; ?>
    <?php endif; ?>
  </nav>
  <span class="user"><?= e(user_name($u)) ?> · <?= e(role_label($u)) ?>
    <a class="logout" href="/change-password">Password</a>
    <a class="logout" href="/logout">Logout</a></span>
  <?php endif; ?>
</header>
<main class="container">
<?php foreach (take_flash() as $m): ?>
  <div class="msg msg-<?= e($m['tag']) ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
