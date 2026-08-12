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
        <?php if (can('mod.leads.view')||can('mod.inquiries.view')||can('mod.quotes.view')||can('mod.crm_reports.view')||is_master()): ?>
        <?php $grp('Sales'); ?>
        <?php if (function_exists('leads_can_view') && leads_can_view()): ?><a class="s-item<?= $navOn(['leads','lead']) ?>" href="/leads"><span class="s-ic">🎯</span><span>Leads</span></a><?php endif; ?>
        <?php if (function_exists('opp_can_view') && opp_can_view()): ?><a class="s-item<?= $navOn(['opportunities','opportunity']) ?>" href="/opportunities"><span class="s-ic">💡</span><span>Opportunities</span></a><?php endif; ?>
        <?php if (can('mod.inquiries.view')): ?><a class="s-item<?= $navOn(['inquiries','inquiry']) ?>" href="/inquiries"><span class="s-ic">📨</span><span><?= e(THP('inquiry')) ?></span></a><?php endif; ?>
        <?php if (can('mod.quotes.view')): ?><a class="s-item<?= $navOn(['quotes','quote']) ?>" href="/quotes"><span class="s-ic">📝</span><span><?= e(THP('quote')) ?></span></a><?php endif; ?>
        <?php if (function_exists('crmdash_can') && crmdash_can()): ?><a class="s-item<?= $navOn(['crm-dashboard']) ?>" href="/crm-dashboard"><span class="s-ic">📈</span><span>Sales dashboard</span></a><?php endif; ?>
        <?php if (function_exists('pipe_can_view') && pipe_can_view()): ?><a class="s-item<?= $navOn(['pipelines','pipeline']) ?>" href="/pipelines"><span class="s-ic">🪜</span><span>Pipelines &amp; funnels</span></a><?php endif; ?>
        <?php // Deals held at a stage. The count is what makes it a queue rather
              // than a screen somebody remembers to visit. ?>
        <?php if (function_exists('gate_can_view') && gate_can_view()):
                $gN = function_exists('gate_pending_count') ? gate_pending_count() : 0; ?>
          <a class="s-item<?= $navOn(['approvals','stage-gates']) ?>" href="/approvals"><span class="s-ic">🛂</span><span>Approvals<?= $gN ? ' (' . (int)$gN . ')' : '' ?></span></a>
        <?php endif; ?>
        <?php // Both only appear once Ads Pro is actually connected. A company
              // that does not advertise should never be shown an empty report. ?>
        <?php if (function_exists('roi_available') && roi_available() && roi_can()): ?>
          <a class="s-item<?= $navOn(['ads-roi']) ?>" href="/ads-roi"><span class="s-ic">💸</span><span>Advertising return</span></a>
        <?php endif; ?>

        <?php $endgrp(); endif; ?>

        <?php if (can('mod.calls.view')||can('mod.jobs.view')||can('mod.vouchers.view')||can('mod.hiring.view')||can('mod.reconcile.view')): ?>
        <?php $grp('Operations'); ?>
        <?php if (can('mod.calls.view')): ?><a class="s-item<?= $navOn(['calls','call']) ?>" href="/calls"><span class="s-ic">☎️</span><span><?= e(THP('call')) ?></span></a><?php endif; ?>
        <?php if (can('mod.jobs.view')): ?><a class="s-item<?= $navOn(['jobs','job']) ?>" href="/jobs"><span class="s-ic">🗂</span><span><?= e(THP('job')) ?></span></a><?php endif; ?>
        <?php if (can('mod.jobs.view') && function_exists('can_manage_availability') && can_manage_availability()): ?><a class="s-item<?= $navOn(['availability']) ?>" href="/availability"><span class="s-ic">🟢</span><span><?= e(TH('engineer')) ?> availability</span></a><?php endif; ?>
        <?php if (function_exists('timesheet_can') && timesheet_can()): ?><a class="s-item<?= $navOn(['timesheet']) ?>" href="/timesheet"><span class="s-ic">⏱️</span><span>Timesheet</span></a><?php endif; ?>
        <?php if (function_exists('rating_can') && rating_can()): ?><a class="s-item<?= $navOn(['ratings']) ?>" href="/ratings"><span class="s-ic">⭐</span><span>Inspector ratings</span></a><?php endif; ?>
        <?php if (can('mod.vouchers.view')): ?><a class="s-item<?= $navOn(['vouchers','voucher']) ?>" href="/vouchers"><span class="s-ic">🧾</span><span><?= e(THP('voucher')) ?></span></a><?php endif; ?>
        <?php if (can('mod.hiring.view')): ?><a class="s-item<?= $navOn(['candidates','candidate']) ?>" href="/candidates"><span class="s-ic">🧑‍💼</span><span><?= e(THP('candidate')) ?></span></a><?php endif; ?>
        <?php if (can('mod.hiring.view')): ?><a class="s-item<?= $navOn(['requisitions','requisition']) ?>" href="/requisitions"><span class="s-ic">📋</span><span><?= e(THP('requisition')) ?></span></a><?php endif; ?>
        <?php if (can('mod.reconcile.view')): ?><a class="s-item<?= $navOn(['attendance-recon']) ?>" href="/attendance-recon"><span class="s-ic">✅</span><span>Attendance reconciliation</span></a><?php endif; ?>
        <?php // The badge counts what is waiting on *you*. An expired contract goes
              // straight to the Super Admin, so a Branch Manager is not nagged about
              // a decision they cannot take.
              if (can('mod.calls.view')):
                $ovN = 0;
                foreach (ops_all("SELECT kind, status FROM contract_overrides WHERE status IN ('PENDING','ENDORSED')") as $_o) {
                    $needsBm = function_exists('override_needs_endorsement') && override_needs_endorsement($_o['kind']);
                    $mine = ($_o['status'] === 'ENDORSED' || !$needsBm)
                        ? (function_exists('can_grant_override') && can_grant_override())
                        : (function_exists('can_endorse_override') && can_endorse_override());
                    if ($mine) $ovN++;
                }
        ?>
          <a class="s-item<?= $navOn(['contract-overrides']) ?>" href="/contract-overrides"><span class="s-ic">🛑</span><span>Contract exceptions<?= $ovN ? ' (' . $ovN . ')' : '' ?></span></a>
        <?php endif; ?>
        <?php $endgrp(); endif; ?>

        <?php // These eleven were rendered loose, with no heading of their own, so
              // they read as a continuation of Operations and made that group
              // nineteen items long. They are their own subject — the things an
              // accreditation assessor asks for — and now they say so.
              // Which of these registers only an accredited body needs is decided
              // by the accreditation packs (inspection OR laboratory). With none
              // on, they are hidden (and refused at the route). The universal ones
              // — complaints, the client portal, identity and confidentiality —
              // always show.
              $inspPack = !function_exists('accredited_pack_on') || accredited_pack_on();
              if (($inspPack && (can('mod.equipment.view')||can('mod.competence.view')||can('mod.impartiality.view')
                  ||can('mod.ncr.view')||can('mod.capa.view')||can('mod.audits.view')||can('mod.datacontrol.view')
                  ||(function_exists('trust_can_review') && trust_can_review())))
                  ||can('mod.complaints.view')||can('mod.confidentiality.view')
                  ||can('mod.portal.view')||can('mod.identity.view')): ?>
        <?php $grp('Quality & accreditation'); ?>
        <?php if ($inspPack && can('mod.equipment.view')): ?><a class="s-item<?= $navOn(['equipment','equip-new','equip-edit']) ?>" href="/equipment"><span class="s-ic">📏</span><span>Equipment &amp; calibration</span></a><?php endif; ?>
        <?php if (function_exists('sample_can_view') && sample_can_view()): $smpN = function_exists('sample_counts') ? sample_counts()['open'] : 0; ?><a class="s-item<?= $navOn(['samples','sample','sample-new','sample-edit']) ?>" href="/samples"><span class="s-ic">📦</span><span>Items &amp; samples<?= $smpN ? ' (' . (int)$smpN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (function_exists('method_can_view') && method_can_view()): ?><a class="s-item<?= $navOn(['methods','method','method-new','method-edit']) ?>" href="/methods"><span class="s-ic">📚</span><span>Method library</span></a><?php endif; ?>
        <?php if (function_exists('drule_can_view') && drule_can_view()): ?><a class="s-item<?= $navOn(['drules','drule','drule-new','drule-edit']) ?>" href="/drules"><span class="s-ic">⚖️</span><span>Decision rules</span></a><?php endif; ?>
        <?php if (function_exists('cdoc_can_view') && cdoc_can_view()): $cdN = function_exists('cdoc_counts') ? cdoc_counts()['review_due'] : 0; ?><a class="s-item<?= $navOn(['cdocs','cdoc','cdoc-new','cdoc-edit']) ?>" href="/cdocs"><span class="s-ic">📄</span><span>Controlled documents<?= $cdN ? ' (' . (int)$cdN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (function_exists('risk_can_view') && risk_can_view()): $rkN = function_exists('risk_counts') ? risk_counts()['high'] : 0; ?><a class="s-item<?= $navOn(['risks','risk','risk-new','risk-edit']) ?>" href="/risks"><span class="s-ic">🎲</span><span>Risks &amp; opportunities<?= $rkN ? ' (' . (int)$rkN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (function_exists('retention_can_view') && retention_can_view()): ?><a class="s-item<?= $navOn(['retention']) ?>" href="/retention"><span class="s-ic">🗄️</span><span>Retention schedule</span></a><?php endif; ?>
        <?php if (function_exists('disclosure_can_view') && disclosure_can_view()): $dsN = function_exists('disclosure_counts') ? disclosure_counts()['pending'] : 0; ?><a class="s-item<?= $navOn(['disclosure']) ?>" href="/disclosure"><span class="s-ic">📢</span><span>Disclosure consent<?= $dsN ? ' (' . (int)$dsN . ')' : '' ?></span></a><?php endif; ?>
        <?php if ($inspPack && can('mod.competence.view')): ?><a class="s-item<?= $navOn(['competence']) ?>" href="/competence"><span class="s-ic">🎓</span><span>Competence &amp; authorisation</span></a><?php endif; ?>
        <?php if ($inspPack && can('mod.impartiality.view')): ?><a class="s-item<?= $navOn(['impartiality']) ?>" href="/impartiality"><span class="s-ic">⚖️</span><span>Impartiality</span></a><?php endif; ?>
        <?php if (can('mod.complaints.view')): $cmpN = function_exists('cmp_all') ? count(cmp_all(['status'=>'OPEN'])) : 0; ?><a class="s-item<?= $navOn(['complaints','complaint','complaint-new']) ?>" href="/complaints"><span class="s-ic">📮</span><span>Complaints &amp; appeals<?= $cmpN ? ' (' . $cmpN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (function_exists('sat_can_view') && sat_can_view()): $stN = function_exists('sat_summary') ? sat_summary()['followup'] : 0; ?><a class="s-item<?= $navOn(['satisfaction','satisfaction-survey','satisfaction-new']) ?>" href="/satisfaction"><span class="s-ic">⭐</span><span>Customer satisfaction<?= $stN ? ' (' . (int)$stN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (can('mod.confidentiality.view') || can('mod.identity.view') || is_master_of(['confidentiality','identity'])): ?><a class="s-item<?= $navOn(['confidentiality','conf-breach']) ?>" href="/confidentiality"><span class="s-ic">🔒</span><span>Confidentiality</span></a><?php endif; ?>
        <?php if (function_exists('ops_sitedocs') && licence_enabled('operations') && (can('mod.identity.view') || can('mod.clients.view') || is_master_of(['identity','clients']))): ?><a class="s-item<?= $navOn(['site-docs']) ?>" href="/site-docs"><span class="s-ic">🛂</span><span>Site entry documents</span></a><?php endif; ?>
        <?php if (function_exists('rcr_can_view') && rcr_can_view()): $rcrN = function_exists('rcr_counts') ? rcr_counts()['rejected'] : 0; ?><a class="s-item<?= $navOn(['report-reviews']) ?>" href="/report-reviews"><span class="s-ic">📬</span><span>Client acceptance<?= $rcrN ? ' (' . $rcrN . ')' : '' ?></span></a><?php endif; ?>
        <?php if ($inspPack && (can('mod.ncr.view') || can('mod.capa.view'))): $ncrN = function_exists('ncr_counts') ? ncr_counts()['open'] : 0; ?><a class="s-item<?= $navOn(['ncr','ncr-item','ncr-new']) ?>" href="/ncr"><span class="s-ic">⚠</span><span>Nonconformities<?= $ncrN ? ' (' . $ncrN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (function_exists('hwp_can_view') && hwp_can_view()): $hwN = function_exists('hwp_open_all') ? count(hwp_open_all(function_exists('scope_offices') && is_array(scope_offices()) ? scope_offices() : null)) : 0; ?><a class="s-item<?= $navOn(['hold-points']) ?>" href="/hold-points"><span class="s-ic">✋</span><span>Hold &amp; witness points<?= $hwN ? ' (' . (int)$hwN . ')' : '' ?></span></a><?php endif; ?>
        <?php if ($inspPack && can('mod.capa.view')): $capaN = function_exists('capa_all') ? count(capa_all(['open'=>1])) : 0; ?><a class="s-item<?= $navOn(['capa','capa-item','capa-new']) ?>" href="/capa"><span class="s-ic">🛠</span><span>Corrective actions<?= $capaN ? ' (' . $capaN . ')' : '' ?></span></a><?php endif; ?>
        <?php if ($inspPack && can('mod.audits.view')): ?><a class="s-item<?= $navOn(['internal-audits','internal-audit','internal-audit-new']) ?>" href="/internal-audits"><span class="s-ic">🔍</span><span>Internal audits</span></a>
        <a class="s-item<?= $navOn(['management-reviews','management-review']) ?>" href="/management-reviews"><span class="s-ic">🏛</span><span>Management review</span></a><?php endif; ?>
        <?php if ($inspPack && function_exists('trust_can_review') && trust_can_review()): $evN = function_exists('trust_readiness') ? trust_readiness()['pending'] : 0; ?><a class="s-item<?= $navOn(['evidence-review']) ?>" href="/evidence-review"><span class="s-ic">📍</span><span>Evidence review<?= $evN ? ' (' . $evN . ')' : '' ?></span></a><?php endif; ?>
        <?php if ($inspPack && can('mod.datacontrol.view')): ?><a class="s-item<?= $navOn(['data-control']) ?>" href="/data-control"><span class="s-ic">🗃</span><span>Data &amp; information control</span></a><?php endif; ?>
        <?php if (can('mod.portal.view')): $pqN = function_exists('portal_requests_all') ? count(portal_requests_all('NEW')) : 0; ?><a class="s-item<?= $navOn(['portal-users']) ?>" href="/portal-users"><span class="s-ic">🌐</span><span>Client portal<?= $pqN ? ' (' . $pqN . ')' : '' ?></span></a><?php endif; ?>
        <?php if (can('mod.identity.view') && function_exists('iddoc_can_view') && iddoc_can_view()): ?><a class="s-item<?= $navOn(['identity']) ?>" href="/identity"><span class="s-ic">🪪</span><span>Identity documents</span></a><?php endif; ?>
        <?php $endgrp(); endif; ?>

        <?php if (can('mod.idems.view')): ?>
        <?php $grp('Reporting'); ?>
        <a class="s-item<?= $navOn(['documents','document','document-new','document-edit']) ?>" href="/documents"><span class="s-ic">📑</span><span><?= e(T_REG('report')) ?></span></a>
        <?php if (can('mod.idems.edit') || is_master_of('idems')): ?><a class="s-item<?= $navOn(['document-new']) ?>" href="/document-new"><span class="s-ic">➕</span><span><?= e(ucfirst(T_NEW('report'))) ?></span></a><?php endif; ?>
        <a class="s-item<?= $navOn(['endorsements','endorsement','endorsement-new','endorsement-edit']) ?>" href="/endorsements"><span class="s-ic">✅</span><span><?= e(T_REG('endorsement')) ?></span></a>
        <a class="s-item<?= $navOn(['vendors','vendor-profile']) ?>" href="/vendors"><span class="s-ic">🏭</span><span>Vendor register</span></a>
        <a class="s-item<?= $navOn(['expediting']) ?>" href="/expediting"><span class="s-ic">🚚</span><span>Expediting register</span></a>
        <a class="s-item<?= $navOn(['expediting-projects']) ?>" href="/expediting-projects"><span class="s-ic">🗂️</span><span>Project delivery</span></a>
        <a class="s-item<?= $navOn(['writing-assistant','phrase-library','phrase-edit']) ?>" href="/writing-assistant"><span class="s-ic">✒️</span><span>Technical writing</span></a>
        <a class="s-item<?= $navOn(['learning']) ?>" href="/learning"><span class="s-ic">🧠</span><span>Learning insights</span></a>
        <?php if (licence_enabled('reporting') && (can('idems.type.manage') || is_master() || can('users.manage.global'))): ?><a class="s-item<?= $navOn(['approver-map']) ?>" href="/approver-map"><span class="s-ic">👤</span><span>Approver mapping</span></a><?php endif; ?>
        <?php if (licence_enabled('reporting') && (can('idems.type.manage') || is_master())): ?><a class="s-item<?= $navOn(['idems-approval-rules','idems-approval-rule-edit','approval-rules']) ?>" href="/approval-rules"><span class="s-ic">🔀</span><span>Approval rules</span></a><?php endif; ?>
        <?php if (licence_enabled('reporting') && (can('idems.type.manage') || is_master() || can('crm.template.manage'))): ?><a class="s-item<?= $navOn(['report-templates','report-template-edit','templates']) ?>" href="/templates"><span class="s-ic">📝</span><span>Document templates</span></a><?php endif; ?>
        <?php if (licence_enabled('reporting') && (can('idems.audit.view') || is_master())): ?><a class="s-item<?= $navOn(['audit-log']) ?>" href="/audit-log"><span class="s-ic">🛡️</span><span>Audit trail</span></a><?php endif; ?>
        <?php // One screen that says, measured from the running system, which of the
              // legal obligations are met and which are not. Kept next to the audit
              // trail because that is where somebody looks when a client asks. ?>
        <?php if (is_master() || can('settings.manage')): ?><a class="s-item<?= $navOn(['compliance','incidents','incident','incident-new','incident-edit','data-requests','person-erase']) ?>" href="/compliance"><span class="s-ic">⚖️</span><span>Where we stand</span></a><?php endif; ?>
        <?php $endgrp(); endif; ?>

        <?php if (can('mod.invoicing.view') || can('mod.profitability.view')): ?>
        <?php $grp('Money'); ?>
        <?php if (can('mod.invoicing.view')): ?><a class="s-item<?= $navOn(['invoicing']) ?>" href="/invoicing"><span class="s-ic">💳</span><span><?= e(T_REG('invoice')) ?></span></a><?php endif; ?>
        <?php if (can('mod.invoicing.view') && (can('finance.reconcile') || can('data.credit') || is_master())): ?>
          <?php // Where operations hands over to the books, and then the books
                // themselves: invoices with lines and tax, money in matched to
                // them, and one ledger per customer. ?>
          <a class="s-item<?= $navOn(['to-bill']) ?>" href="/to-bill"><span class="s-ic">⧗</span><span>Waiting to be billed</span></a>
          <a class="s-item<?= $navOn(['invoices','invoice']) ?>" href="/invoices"><span class="s-ic">🧾</span><span>Invoices</span></a>
          <a class="s-item<?= $navOn(['receipts','receipt']) ?>" href="/receipts"><span class="s-ic">💰</span><span>Money in</span></a>
          <a class="s-item<?= $navOn(['receivables']) ?>" href="/receivables"><span class="s-ic">⏳</span><span>Receivables ageing</span></a>
          <a class="s-item<?= $navOn(['tally']) ?>" href="/tally"><span class="s-ic">📤</span><span>Tally export</span></a>
        <?php endif; ?>
        <?php // The app-switcher: opens MGH Books in a new tab, already signed in
              // through the shared login. Shown only once Books is connected and its
              // web address is set. No token is minted here — the shared session
              // carries the identity, so this is just an outbound link. ?>
        <?php if (function_exists('books_switch_ready') && books_switch_ready()): ?><a class="s-item" href="<?= e(books_app_url()) ?>" target="_blank" rel="noopener"><span class="s-ic">📗</span><span>Accounts &amp; GST ↗</span></a><?php endif; ?>
        <?php if (can('mod.profitability.view')): ?><a class="s-item<?= $navOn(['profitability']) ?>" href="/profitability"><span class="s-ic">💹</span><span><?= e(T_REG('boss')) ?></span></a><?php endif; ?>
        <?php $endgrp(); endif; ?>

        <?php if (can('mod.reports.view')): ?>
        <?php $grp('Insights'); ?>
          <a class="s-item<?= $navOn(['reports']) ?>" href="/reports"><span class="s-ic">📊</span><span>Dashboards</span></a>
        <?php $endgrp(); endif; ?>

        <?php if (can('mod.clients.view') || can('mod.vendors.view')): ?>
        <?php $grp('Directory'); ?>
        <?php if (function_exists('act_can_view') && act_can_view()): ?><a class="s-item<?= $navOn(['activities']) ?>" href="/activities"><span class="s-ic">🕘</span><span>Activity</span></a><?php endif; ?>
        <?php if (can('mod.clients.view')): ?><a class="s-item<?= $navOn(['clients']) ?>" href="/clients"><span class="s-ic">🏢</span><span><?= e(T_REG('client')) ?></span></a><?php endif; ?>
        <?php if (can('mod.vendors.view')): ?><a class="s-item<?= $navOn(['vendors']) ?>" href="/vendors"><span class="s-ic">🚚</span><span><?= e(T_REG('vendor')) ?></span></a><?php endif; ?>
        <?php $endgrp(); endif; ?>

        <?php if (can('mod.masters.view')||can('mod.overheads.view')||can('mod.users.view')
                  ||can('mod.profitability.view')||can('mod.reports.view')||is_master()
                  ||(can('mod.settings.view') && can('settings.manage'))): ?>
        <?php $grp('Admin'); ?>
          <?php if (can('mod.masters.view')): ?><a class="s-item<?= $navOn(['masters','m/','lookups']) ?>" href="/masters"><span class="s-ic">📋</span><span>Masters</span></a><?php endif; ?>
          <?php if (can('mod.overheads.view')): ?><a class="s-item<?= $navOn(['office-finance']) ?>" href="/office-finance"><span class="s-ic">📐</span><span><?= e(TH("office")) ?> costs &amp; overheads</span></a><?php endif; ?>
          <?php if (can('mod.overheads.view')): ?><a class="s-item<?= $navOn(['cost-run']) ?>" href="/cost-run"><span class="s-ic">🧮</span><span>Month-end cost run</span></a><?php endif; ?>
          <?php if (can('mod.profitability.view')): ?><a class="s-item<?= $navOn(['sbu-pl']) ?>" href="/sbu-pl"><span class="s-ic">📊</span><span><?= e(T('sbu')) ?> profit &amp; loss</span></a><?php endif; ?>
          <?php // A branch manager runs the branch one job at a time, not off a
                // monthly total — so the per-inspection figure sits beside the
                // branch one rather than being buried in it. ?>
          <?php if (can('mod.profitability.view')): ?><a class="s-item<?= $navOn(['call-profit']) ?>" href="/call-profit"><span class="s-ic">🧾</span><span>Profit by <?= e(Tl('call')) ?></span></a><?php endif; ?>
          <?php if (licence_enabled('operations') && (can('mod.reports.view')||can('dash.operations')||can('dash.financial'))): ?><a class="s-item<?= $navOn(['mis']) ?>" href="/mis"><span class="s-ic">📈</span><span>Management dashboard</span></a><?php endif; ?>
          <?php if (can('mod.users.view')): ?><a class="s-item<?= $navOn(['users','user-new','user-edit']) ?>" href="/users"><span class="s-ic">👥</span><span><?= e(T_REG('user')) ?></span></a><?php endif; ?>
          <?php if (can('mod.users.view')): ?><a class="s-item<?= $navOn(['hierarchy']) ?>" href="/hierarchy"><span class="s-ic">🗂️</span><span>Organisation</span></a><?php endif; ?>
          <?php if (is_master()): ?><a class="s-item<?= $navOn(['access']) ?>" href="/access"><span class="s-ic">🔐</span><span>Roles &amp; permissions</span></a><?php endif; ?>
          <?php // The Ads Pro connection lives here rather than under Sales, and
                // is shown before it is connected — a settings screen that only
                // appears once the thing is configured cannot be used to
                // configure it. ?>
          <?php if (function_exists('ads_can_manage') && ads_can_manage()): ?><a class="s-item<?= $navOn(['adspro']) ?>" href="/adspro"><span class="s-ic">📢</span><span>Ads Pro connection</span></a><?php endif; ?>
          <?php if (function_exists('sso_on') && (can('users.manage.global') || is_master())): ?><a class="s-item<?= $navOn(['sso']) ?>" href="/sso"><span class="s-ic">🔑</span><span>Single sign-on</span></a><?php endif; ?>
          <?php // The licence. Shown to anyone who could act on it, and carrying
                // the state in the label so an expiry is noticed before it bites
                // rather than on the morning somebody cannot save an invoice. ?>
          <?php if (function_exists('lk_can_manage') && lk_can_manage()):
                  $lkS = lk_state(); $lkBad = in_array($lkS['state'], ['GRACE','READONLY','INVALID'], true); ?>
            <a class="s-item<?= $navOn(['licence']) ?>" href="/licence"><span class="s-ic">📜</span><span>Licence<?php
              if ($lkBad) echo ' ⚠';
              elseif ($lkS['state'] === 'TRIAL') echo ' (' . (int)$lkS['days_left'] . 'd)';
              elseif ($lkS['state'] === 'VALID' && $lkS['days_left'] !== null && $lkS['days_left'] <= 30) echo ' (' . (int)$lkS['days_left'] . 'd)';
            ?></span></a>
          <?php endif; ?>
          <?php // The screen itself requires settings.manage. Offering it on
                // mod.settings.view meant a business director was shown the link
                // and then refused at the door — which reads as a broken app. ?>
          <?php if (can('mod.settings.view') && can('settings.manage')): ?><a class="s-item<?= $navOn(['settings','terminology','ai-settings','reset-data']) ?>" href="/settings"><span class="s-ic">⚙️</span><span>System settings</span></a><?php endif; ?>
          <?php if (can('settings.manage') || is_master()): ?><a class="s-item<?= $navOn(['company-profile']) ?>" href="/company-profile"><span class="s-ic">🏢</span><span>Company profile</span></a><?php endif; ?>
          <?php if ((can('settings.manage') || is_master()) && function_exists('books_licensed') && books_licensed()): $bxc = function_exists('books_outbox_counts') ? books_outbox_counts() : []; ?><a class="s-item<?= $navOn(['books-bridge']) ?>" href="/books-bridge"><span class="s-ic">📗</span><span>MGH Books<?= !empty($bxc['stuck']) ? ' (' . (int)$bxc['stuck'] . ')' : '' ?></span></a><?php endif; ?>
        <?php $endgrp(); endif; ?>
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
