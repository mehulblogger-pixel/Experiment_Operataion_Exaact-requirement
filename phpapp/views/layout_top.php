<?php
  $u = current_user();
  $cur = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  // active-state helper: mark an item active when the current route matches any of its route prefixes
  $navOn = function(array $routes) use ($cur) {
    foreach ($routes as $r) { if ($cur === $r || ($r !== '' && strpos($cur, $r) === 0)) return ' on'; }
    return ($cur === '' && in_array('', $routes, true)) ? ' on' : '';
  };
  $office = ($u && ($u['home_office_id'] ?? null)) ? ops_val("SELECT name FROM offices WHERE id=?", [$u['home_office_id']]) : '';
  $isInsp = $u ? is_inspector() : false;
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? app_name()) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
</head><body class="<?= $u ? 'app' : '' ?><?= $isInsp ? ' inspector' : '' ?>">
<?php if ($u): ?>
<div class="shell">
  <aside class="side" id="side">
    <div class="side-brand">
      <a class="sb-logo" href="/"><?php $lg = logo_html(); echo $lg ?: '<span class="sb-mono">'.e(mb_substr(app_name(),0,1)).'</span><b>'.e(app_name()).'</b>'; ?></a>
      <button class="side-close" aria-label="Close menu" onclick="document.getElementById('side').classList.remove('open');document.getElementById('scrim').classList.remove('on');">✕</button>
    </div>
    <nav class="side-nav">
      <a class="s-item<?= $navOn(['']) ?>" href="/"><span class="s-ic">🏠</span><span>Dashboard</span></a>

      <?php if ($isInsp): ?>
        <div class="s-grp">My work</div>
        <a class="s-item<?= $navOn(['my-jobs']) ?>" href="/my-jobs"><span class="s-ic">🗂</span><span>My Jobs</span></a>
        <a class="s-item<?= $navOn(['vouchers','voucher']) ?>" href="/vouchers"><span class="s-ic">🧾</span><span>My Voucher</span></a>
      <?php else: ?>
        <div class="s-grp">Operations</div>
        <a class="s-item<?= $navOn(['calls','call']) ?>" href="/calls"><span class="s-ic">☎️</span><span>Calls</span></a>
        <a class="s-item<?= $navOn(['jobs','job']) ?>" href="/jobs"><span class="s-ic">🗂</span><span>Jobs</span></a>
        <?php if (is_coordinator_level()): ?>
          <a class="s-item<?= $navOn(['vouchers','voucher']) ?>" href="/vouchers"><span class="s-ic">🧾</span><span>Vouchers</span></a>
          <a class="s-item<?= $navOn(['candidates','candidate']) ?>" href="/candidates"><span class="s-ic">🧑‍💼</span><span>Hiring</span></a>
          <a class="s-item<?= $navOn(['attendance-recon']) ?>" href="/attendance-recon"><span class="s-ic">✅</span><span>Reconcile</span></a>
        <?php endif; ?>

        <?php if (can('data.credit') || can('finance.reconcile') || can('data.profitability') || can('dash.financial')): ?>
        <div class="s-grp">Money</div>
        <?php if (can('data.credit') || can('finance.reconcile')): ?>
          <a class="s-item<?= $navOn(['invoicing']) ?>" href="/invoicing"><span class="s-ic">💳</span><span>Invoicing</span></a>
        <?php endif; ?>
        <?php if (can('data.profitability')): ?>
          <a class="s-item<?= $navOn(['profitability']) ?>" href="/profitability"><span class="s-ic">💹</span><span>Profitability</span></a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (can('dash.operations')||can('dash.financial')||can('dash.utilization')||can('dash.people')): ?>
        <div class="s-grp">Insights</div>
          <a class="s-item<?= $navOn(['reports']) ?>" href="/reports"><span class="s-ic">📊</span><span>Dashboards</span></a>
        <?php endif; ?>

        <div class="s-grp">Directory</div>
        <a class="s-item<?= $navOn(['clients']) ?>" href="/clients"><span class="s-ic">🏢</span><span>Clients</span></a>
        <a class="s-item<?= $navOn(['vendors']) ?>" href="/vendors"><span class="s-ic">🚚</span><span>Vendors</span></a>

        <?php if (is_coordinator_level() || is_admin_level() || can('users.manage.branch') || can('users.manage.global') || can('settings.manage')): ?>
        <div class="s-grp">Admin</div>
          <?php if (is_coordinator_level()): ?><a class="s-item<?= $navOn(['masters','m/']) ?>" href="/masters"><span class="s-ic">📋</span><span>Masters</span></a><?php endif; ?>
          <?php if (is_admin_level()): ?><a class="s-item<?= $navOn(['office-finance']) ?>" href="/office-finance"><span class="s-ic">📐</span><span>Overheads</span></a><?php endif; ?>
          <?php if (can('users.manage.branch')||can('users.manage.global')): ?><a class="s-item<?= $navOn(['users','user-new','user-edit']) ?>" href="/users"><span class="s-ic">👥</span><span>Users</span></a><?php endif; ?>
          <?php if (can('settings.manage')): ?><a class="s-item<?= $navOn(['settings']) ?>" href="/settings"><span class="s-ic">⚙️</span><span>Settings</span></a><?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <span class="sf-av"><?= e(mb_strtoupper(mb_substr(user_name($u),0,2))) ?></span>
      <div class="sf-id">
        <b><?= e(user_name($u)) ?></b>
        <small><?= e(role_label($u)) ?></small>
      </div>
    </div>
  </aside>
  <div class="scrim" id="scrim" onclick="document.getElementById('side').classList.remove('open');this.classList.remove('on');"></div>

  <div class="main">
    <header class="topbar-slim">
      <button class="nav-toggle" aria-label="Menu" onclick="document.getElementById('side').classList.add('open');document.getElementById('scrim').classList.add('on');">☰</button>
      <a class="tb-brand" href="/"><?= e(app_name()) ?></a>
      <div class="tb-spacer"></div>
      <?php if ($office): ?><span class="tb-chip">📍 <?= e($office) ?></span><?php endif; ?>
      <span class="tb-chip">FY <?= e(current_fy()) ?></span>
      <span class="tb-user">
        <a href="/change-password" title="Change password">🔑</a>
        <a class="tb-logout" href="/logout">Logout</a>
      </span>
    </header>
    <main class="container">
<?php foreach (take_flash() as $m): ?>
      <div class="msg msg-<?= e($m['tag']) ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
<?php else: ?>
<main class="container">
<?php endif; ?>
