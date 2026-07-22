<?php $u = current_user(); ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Inspection Ops') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head><body>
<header class="topbar">
  <a class="brand" href="/">Inspection&nbsp;Ops</a>
  <?php if ($u): ?>
  <nav>
    <a href="/">Dashboard</a>
    <a href="/clients">Clients</a>
    <a href="/vendors">Vendors</a>
  </nav>
  <span class="user"><?= e(user_name($u)) ?> · Admin
    <a class="logout" href="/logout">Logout</a></span>
  <?php endif; ?>
</header>
<main class="container">
<?php foreach (take_flash() as $m): ?>
  <div class="msg msg-<?= e($m['tag']) ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
