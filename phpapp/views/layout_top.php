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

  // ---------------------------------------------------------------------------
  //  Groups that fold
  //
  //  A master admin is offered about forty destinations, and they were all
  //  expanded, all the time, weighted the same. On a phone that is a wall you
  //  scroll rather than a menu you read — the owner's words were "cluttered and
  //  very difficult to find things", and they were right.
  //
  //  $grp opens a foldable section, $endgrp closes it. The heading is a real
  //  <button> so it works by keyboard and reads correctly to a screen reader.
  //  Which sections are open is remembered per browser, and the section holding
  //  the page you are on is forced open whatever the memory says — otherwise you
  //  can be looking at a screen whose menu entry is hidden.
  // ---------------------------------------------------------------------------
  $grpN = 0;
  $curGrp = '';
  $grp = function ($label) use (&$grpN, &$curGrp) {
      $grpN++;
      $id = 'sec' . $grpN;
      $curGrp = $label;
      echo '<button type="button" class="s-grp" data-sec="' . e($label) . '" aria-controls="' . $id . '" aria-expanded="true">'
         . '<span class="s-grp-t">' . e($label) . '</span><span class="s-grp-c" aria-hidden="true">›</span></button>'
         . '<div class="s-sec" id="' . $id . '">';
  };
  // Before closing each group, drop in any no-code custom forms filed under it,
  // so a new form appears in the menu under its module with no code change.
  $endgrp = function () use (&$curGrp) {
      if (function_exists('custom_forms_nav')) custom_forms_nav($curGrp);
      echo '</div>';
  };
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? app_name()) ?></title>
<?php // The forms on the page are given this automatically on the way out. The
      // few places that post without a form — quick-add, for one — read it here. ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="<?= e(setting_get('c_primary', '') ?: '#1e40af') ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?= theme_style_tag() ?>
<script src="/assets/js/offline.js" defer></script>
<script>
  // Applied before first paint: a rail that flashes open then snaps shut
  // on every page load is worse than one that never collapsed.
  try { if (localStorage.getItem("navCollapsed") === "1") document.documentElement.classList.add("nav-collapsed"); } catch (e) {}
</script>
</head><body class="<?= $u ? 'app' : '' ?><?= $isInsp ? ' inspector' : '' ?>">
<?php if ($u): ?>
<div class="shell">
  <aside class="side" id="side">
    <div class="side-brand">
      <a class="sb-logo" href="/"><?php $lg = logo_html(); echo $lg ?: '<span class="sb-mono">'.e(mb_substr(app_name(),0,1)).'</span><b>'.e(app_name()).'</b>'; ?></a>
      <button class="side-close" aria-label="Close menu" onclick="document.getElementById('side').classList.remove('open');document.getElementById('scrim').classList.remove('on');">✕</button>
      <?php // Desktop only: shrink the rail to icons so a wide register gets the
            // screen. The choice is remembered per browser. ?>
      <button class="side-collapse" id="navCollapse" type="button" aria-label="Collapse the menu" title="Collapse the menu">«</button>
    </div>
    <nav class="side-nav">
      <?php // With forty destinations, typing three letters beats any amount of
            // grouping. It filters what is already on the page — no request, no
            // index to keep in step with the menu, and it works offline. ?>
      <div class="s-find">
        <input type="search" id="navFind" class="s-find-in" placeholder="Find a screen…"
               autocomplete="off" spellcheck="false" aria-label="Find a screen">
        <button type="button" class="s-find-x" id="navFindX" aria-label="Clear" hidden>✕</button>
      </div>
      <p class="s-find-none" id="navFindNone" hidden>Nothing matches that.</p>
      <a class="s-item<?= $navOn(['']) ?>" href="/"><span class="s-ic">🏠</span><span>Dashboard</span></a>
      <?php // Also in the top bar, but a destination in the menu is how people
            // discover that searching records is a thing at all. ?>
      <a class="s-item<?= $navOn(['search']) ?>" href="/search"><span class="s-ic">🔍</span><span>Search records</span></a>
      <?php // Where a handover between selling, doing and billing was skipped. ?>
      <?php if (function_exists('chain_can') && chain_can()): ?>
        <a class="s-item<?= $navOn(['flow-gaps']) ?>" href="/flow-gaps"><span class="s-ic">🔗</span><span>Where the flow is broken</span></a>
      <?php endif; ?>
      <?php // The one screen that answers "what should I do today" with money attached.
            // It sits above the module groups on purpose: it is cross-module, and a
            // person who only opens one screen a day should open this one. ?>
      <?php if (function_exists('adv_can') && adv_can()): ?>
        <a class="s-item<?= $navOn(['advisor']) ?>" href="/advisor"><span class="s-ic">🧭</span><span>What to fix</span></a>
      <?php endif; ?>

      <?php // Every label below is the first words of the page heading it opens,
            // and every business noun comes from Settings -> Terminology. ?>
      <?php if ($isInsp): ?>
        <?php // The engineer performs the inspection AND writes the report, so the
              // report register belongs on their menu. They already hold
              // mod.idems.view/edit — this branch simply never offered it, and the
              // person who writes every report had a two-item menu. ?>
        <?php $grp('My work'); ?>
        <a class="s-item<?= $navOn(['my-jobs']) ?>" href="/my-jobs"><span class="s-ic">🗂</span><span>My <?= e(Tlp('job')) ?></span></a>
        <?php if (can('mod.idems.view')): ?>
          <a class="s-item<?= $navOn(['documents','document','document-edit','document-fill']) ?>" href="/documents"><span class="s-ic">📑</span><span>My <?= e(Tlp('report')) ?></span></a>
          <?php if (can('mod.idems.edit')): ?><a class="s-item<?= $navOn(['document-new']) ?>" href="/document-new"><span class="s-ic">➕</span><span><?= e(ucfirst(T_NEW('report'))) ?></span></a><?php endif; ?>
          <a class="s-item<?= $navOn(['endorsements','endorsement','endorsement-new','endorsement-edit']) ?>" href="/endorsements"><span class="s-ic">✅</span><span><?= e(THP('endorsement')) ?></span></a>
        <?php endif; ?>
        <a class="s-item<?= $navOn(['vouchers','voucher']) ?>" href="/vouchers"><span class="s-ic">🧾</span><span>My <?= e(Tlp('voucher')) ?></span></a>
        <?php $endgrp(); ?>
      <?php else: ?>
        <?php // Every area below is a flat link to its Home (a page that lays out
              //  the area's screens as tiles), not a folding accordion. Visibility
              //  and the highlight route-set both come from lib/areas.php, so the
              //  rail and the landing page can never disagree. ?>
        <?php if (ops_area_has('sales')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('sales')) ?>" href="/sales"><span class="s-ic">🎯</span><span>Sales</span></a>
        <?php endif; ?>

        <?php // Operations is no longer a folding group. Tapping it navigates to
              // the Operations Home, where every one of the screens that used to
              // hang under this heading is laid out on the page with its live
              // state. The link is "on" for any Operations-area route so you are
              // never on an Operations screen with nothing highlighted. ?>
        <?php if (can('mod.calls.view')||can('mod.jobs.view')||can('mod.vouchers.view')||can('mod.hiring.view')||can('mod.reconcile.view')): ?>
        <a class="s-item<?= $navOn(['operations','ops-desk','calls','call','jobs','job','deputations','availability','schedule','capacity-outlook','recurring','timesheet','ratings','vouchers','voucher','candidates','candidate','requisitions','requisition','attendance-recon','contract-overrides']) ?>" href="/operations"><span class="s-ic">🛠️</span><span>Operations</span></a>
        <?php endif; ?>

        <?php if (ops_area_has('quality')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('quality')) ?>" href="/quality"><span class="s-ic">🛡️</span><span>Quality &amp; Accreditation</span></a>
        <?php endif; ?>

        <?php if (ops_area_has('reporting')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('reporting')) ?>" href="/reporting"><span class="s-ic">📑</span><span>Reporting</span></a>
        <?php endif; ?>

        <?php if (ops_area_has('money')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('money')) ?>" href="/money"><span class="s-ic">💰</span><span>Money</span></a>
        <?php endif; ?>

        <?php if (ops_area_has('insights')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('insights')) ?>" href="/insights"><span class="s-ic">📊</span><span>Insights</span></a>
        <?php endif; ?>

        <?php if (ops_area_has('directory')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('directory')) ?>" href="/directory"><span class="s-ic">🏢</span><span>Directory</span></a>
        <?php endif; ?>

        <?php if (ops_area_has('admin')): ?>
        <a class="s-item<?= $navOn(ops_area_routes('admin')) ?>" href="/admin"><span class="s-ic">⚙️</span><span>Admin</span></a>
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
  <script>
  (function () {
    // Copy each item's own label onto the element, so the collapsed rail can
    // show it as a tooltip without duplicating every string in the markup.
    document.querySelectorAll(".side-nav .s-item").forEach(function (a) {
      var lab = a.querySelector("span:not(.s-ic)");
      if (lab) a.setAttribute("data-label", lab.textContent.trim());
    });

    // ---- Folding groups -------------------------------------------------
    // Closed to start with, and one open at a time — an accordion, not a set of
    // independent switches. The first version defaulted every group to OPEN,
    // which meant a master admin still met all 48 destinations at once on a
    // first visit: the wall this was supposed to remove.
    //
    // Two rules survive from that version because they are about not stranding
    // anybody: the section holding the page you are on is forced open whatever
    // the memory says, and the 60px icon rail is untouched (at that width there
    // is no heading to click to get a section back).
    //
    // What is remembered is now which ONE section is open, not which are shut.
    var openSec = "";
    try { openSec = localStorage.getItem("navOpen") || ""; } catch (e3) { openSec = ""; }
    function save() { try { localStorage.setItem("navOpen", openSec); } catch (e4) {} }

    var groups = [];
    document.querySelectorAll(".side-nav .s-grp").forEach(function (h) {
      var sec = h.nextElementSibling;
      if (!sec || !sec.classList.contains("s-sec")) return;
      var name = h.getAttribute("data-sec") || "";
      var here = !!sec.querySelector(".s-item.on");
      function apply(o) {
        sec.hidden = !o;
        h.setAttribute("aria-expanded", o ? "true" : "false");
        h.classList.toggle("shut", !o);
      }
      var g = { h: h, sec: sec, name: name, here: here, apply: apply };
      groups.push(g);
      // Where you are wins over what you last opened.
      if (here) openSec = name;
      h.addEventListener("click", function () {
        var opening = sec.hidden;
        openSec = opening ? name : "";
        groups.forEach(function (x) { x.apply(opening && x.name === name); });
        save();
      });
    });
    groups.forEach(function (g) { g.apply(g.name === openSec); });
    save();

    // ---- Type to find ---------------------------------------------------
    var find = document.getElementById("navFind");
    var findX = document.getElementById("navFindX");
    var findNone = document.getElementById("navFindNone");
    if (find) {
      var nav = document.querySelector(".side-nav");
      function runFilter() {
        var q = find.value.trim().toLowerCase();
        findX.hidden = q === "";
        nav.classList.toggle("filtering", q !== "");
        var hits = 0;
        document.querySelectorAll(".side-nav .s-item").forEach(function (a) {
          var hit = q === "" || (a.getAttribute("data-label") || "").toLowerCase().indexOf(q) >= 0;
          a.hidden = !hit;
          if (hit) hits++;
        });
        // While filtering, every section is opened and any that has no match is
        // taken off screen with its heading — a heading over nothing reads as a
        // broken page.
        document.querySelectorAll(".side-nav .s-grp").forEach(function (h) {
          var sec = h.nextElementSibling;
          if (!sec || !sec.classList.contains("s-sec")) return;
          if (q === "") { h.hidden = false; sec.hidden = h.classList.contains("shut"); return; }
          var any = !!sec.querySelector(".s-item:not([hidden])");
          h.hidden = !any;
          sec.hidden = !any;
        });
        findNone.hidden = !(q !== "" && hits === 0);
      }
      find.addEventListener("input", runFilter);
      find.addEventListener("keydown", function (e5) {
        if (e5.key === "Escape") { find.value = ""; runFilter(); find.blur(); }
        // Enter opens the only thing left, so three letters and a tap is the
        // whole journey.
        if (e5.key === "Enter") {
          var first = document.querySelector(".side-nav .s-item:not([hidden])");
          if (first) first.click();
        }
      });
      findX.addEventListener("click", function () { find.value = ""; runFilter(); find.focus(); });
    }
    var btn = document.getElementById("navCollapse");
    if (!btn) return;
    function sync() {
      var on = document.documentElement.classList.contains("nav-collapsed");
      btn.textContent = on ? "»" : "«";
      btn.title = on ? "Expand the menu" : "Collapse the menu";
      btn.setAttribute("aria-label", btn.title);
    }
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var on = document.documentElement.classList.toggle("nav-collapsed");
      document.documentElement.classList.remove("nav-peek");
      try { localStorage.setItem("navCollapsed", on ? "1" : "0"); } catch (e2) {}
      sync();
    });
    sync();

    // §m — while the rail is collapsed, clicking it opens the full menu over the
    // page, and clicking anywhere else closes it again. That keeps a wide
    // register at full width without making the menu two clicks away.
    var side = document.getElementById("side");
    if (side) {
      side.addEventListener("click", function (e) {
        if (e.target.closest("#navCollapse")) return;
        if (!document.documentElement.classList.contains("nav-collapsed")) return;
        // A menu item click is a navigation, not a request to peek.
        if (e.target.closest(".s-item")) return;
        document.documentElement.classList.add("nav-peek");
      });
    }
    document.addEventListener("click", function (e) {
      if (side && side.contains(e.target)) return;
      document.documentElement.classList.remove("nav-peek");
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") document.documentElement.classList.remove("nav-peek");
    });
  })();
  </script>

  <div class="main">
    <header class="topbar-slim">
      <button class="nav-toggle" aria-label="Menu" onclick="document.getElementById('side').classList.add('open');document.getElementById('scrim').classList.add('on');">☰</button>
      <a class="tb-brand" href="/"><?= e(app_name()) ?></a>
      <?php // Records, not screens. The box in the rail below filters the MENU;
            // this one searches what is IN the registers. They were confusable
            // while there was only one of them, which is half of why nobody
            // could find a record without knowing which register held it. ?>
      <form class="tb-search" method="get" action="/search" role="search">
        <label class="sr-only" for="gsearch">Search every register</label>
        <input id="gsearch" type="search" name="q" autocomplete="off" spellcheck="false"
               value="<?= e($cur === 'search' ? (string)($_GET['q'] ?? '') : '') ?>"
               placeholder="Search records…  (/)">
        <button type="submit" aria-label="Search">🔍</button>
      </form>
      <div class="tb-spacer"></div>
      <?php if ($office): ?><span class="tb-chip">📍 <?= e($office) ?></span><?php endif; ?>
      <span class="tb-chip">FY <?= e(current_fy()) ?></span>
      <span class="tb-user">
        <a href="/my-signature" class="tb-sig" title="Upload or draw the signature that goes on your approved documents and quotations">✍️ <span>Signature</span></a>
        <a href="/change-password" title="Change password">🔑</a>
        <a href="/two-factor" title="Two-step sign-in — a code from your phone as well as your password"><?= twofa_on($u) ? '🛡️' : '🛡' ?></a>
        <a class="tb-logout" href="/logout">Logout</a>
      </span>
    </header>
    <?php // Web-first punch bar: a field engineer punches in/out for today from
          // the very top of the app on any device — no Android app, no job screen.
          // Renders to nothing for anyone who is not a field engineer. ?>
    <?php if (function_exists('punch_bar')) echo punch_bar(); ?>
    <main class="container">
<?php // A role that has been told to use two-step sign-in but has not set it up
      // is nudged on every screen. Not locked out — an inspector who cannot reach
      // Monday's jobs because of a security setting will simply stop using the app. ?>
<?php if (twofa_owed($u)): ?>
      <div class="msg msg-warning">Your role requires two-step sign-in and it is not set up yet.
        <a href="/two-factor"><strong>Set it up now</strong></a> — it takes about a minute and needs only your phone.</div>
<?php endif; ?>
<?php foreach (take_flash() as $m): ?>
      <div class="msg msg-<?= e($m['tag']) ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
<?php else: ?>
<main class="container">
<?php endif; ?>
