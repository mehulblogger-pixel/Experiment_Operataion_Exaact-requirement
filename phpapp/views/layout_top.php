<?php $u = current_user(); ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? app_name()) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
</head><body>
<header class="topbar">
  <a class="brand" href="/"><?php $lg = logo_html(); echo $lg ?: e(app_name()); ?></a>
  <?php if ($u): ?>
  <nav>
    <a href="/">Dashboard</a>
    <?php if (is_inspector()): ?>
      <a href="/my-jobs">My Jobs</a>
      <a href="/vouchers">My Voucher</a>
    <?php else: ?>
      <a href="/calls">Calls</a>
      <a href="/jobs">Jobs</a>
      <?php if (is_coordinator_level()): ?><a href="/vouchers">Vouchers</a><?php endif; ?>
      <?php if (is_coordinator_level()): ?><a href="/attendance-recon">Reconcile</a><?php endif; ?>
      <?php if (is_coordinator_level()): ?><a href="/candidates">Hiring</a><?php endif; ?>
      <a href="/clients">Clients</a>
      <a href="/vendors">Vendors</a>
      <a href="/masters">Masters</a>
      <?php if (can('dash.operations') || can('dash.financial') || can('dash.utilization') || can('dash.people')): ?><a href="/reports">Dashboards</a><?php endif; ?>
      <?php if (can('data.profitability')): ?><a href="/profitability">Profitability</a><?php endif; ?>
      <?php if (can('users.manage.branch') || can('users.manage.global')): ?><a href="/users">Users</a><?php endif; ?>
      <?php if (is_admin_level()): ?><a href="/office-finance">Overheads</a><?php endif; ?>
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
