<?php // Field-finding #9/#10 — the bare wrapper for a screen shown inside the in-page popup (embed=1):
      // the same stylesheet and theme, but no sidebar / top nav — just the screen, sized for the modal. ?>
<?php $title = $title ?? (function_exists('app_name') ? app_name() : 'Exaact'); ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="/assets/css/app.css">
<?= function_exists('theme_style_tag') ? theme_style_tag() : '' ?>
</head><body class="app embed">
<main class="container embed-main" style="padding:16px 18px">
