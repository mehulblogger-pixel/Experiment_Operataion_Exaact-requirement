<?php // ============================================================================
//  NEXT-ACTION / ATTENTION STRIP — the shared partial.
//
//  Phase 3 §34 built this strip and B3 built the ranking behind it, but until
//  B9 it rendered on the eight generic area homes ONLY — pages people pass
//  through — while the Dashboard and the Operations home (where people
//  actually land) showed the UNRANKED counts panel, and the full ranked queue
//  lived on /my-work alone. The intelligence was never missing; it was in the
//  wrong place.
//
//  So the markup moved here verbatim and is now included by the area homes,
//  the Dashboard and the Operations home. There is no second engine and no
//  second ordering: every item still comes from dashboard_glance(), which is
//  action_centre(4) — the same canonical, permission-scoped ranking /my-work
//  uses. This file must never sort, score or re-rank what it is given.
// ============================================================================
?>
<?php // Phase 3 §34 — a role-aware "at a glance" strip: your next actions, and (for managers) the pulse. ?>
<?php
// $glanceWantMgmt — whether to render the management pulse (needs-attention /
// outstanding / platform health) as well as the ranked actions.
//
// The area homes have always shown both, and still do. The Dashboard and the
// Operations home take the ranked ACTIONS only: they already carry their own KPI
// row, the pulse belongs to /command-centre, and — measured — the pulse's
// system_status_worst() bcrypt-tests every account that has never had its
// password changed through the app, which is 30s+ on a 191-account workspace the
// moment its cache fingerprint moves. B9-2 is about surfacing the ranked action,
// so that is what those two pages take, at the cost of action_centre(4) alone.
$glanceWantMgmt = isset($glanceWantMgmt) ? (bool)$glanceWantMgmt : true;
$gsGlance = $glanceWantMgmt
    ? (function_exists('dashboard_glance') ? dashboard_glance() : ['actions'=>[], 'mgmt'=>null])
    : ['actions' => function_exists('action_centre') ? action_centre(4) : [], 'mgmt' => null];
?>
<?php $gsGlance = function_exists('dashboard_glance') ? dashboard_glance() : ['actions'=>[], 'mgmt'=>null];
      if ($gsGlance['actions'] || $gsGlance['mgmt']): ?>
<div class="panel" style="margin-top:14px">
  <?php if ($gsGlance['mgmt']): $gsM = $gsGlance['mgmt'];
        $gsHc = ['ok'=>'#15803d','warn'=>'#b45309','bad'=>'var(--bad,#c0392b)'][$gsM['health']] ?? '#15803d';
        $gsHl = ['ok'=>'Healthy','warn'=>'Needs attention','bad'=>'Action needed'][$gsM['health']] ?? 'Healthy'; ?>
    <div style="display:flex;flex-wrap:wrap;gap:22px;align-items:center;margin-bottom:<?= $gsGlance['actions'] ? '12px' : '0' ?>">
      <a href="/command-centre" style="text-decoration:none;color:inherit"><div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em">Needs attention</div>
        <div style="font-weight:700;font-size:20px"><?= (int)$gsM['attention'] ?><?php if ($gsM['attention_hi']): ?> <span style="font-size:12px;color:var(--warn,#b45309);font-weight:600"><?= (int)$gsM['attention_hi'] ?> pressing</span><?php endif; ?></div></a>
      <?php if ($gsM['outstanding'] !== null): ?>
        <a href="/command-centre" style="text-decoration:none;color:inherit"><div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em">Outstanding</div>
          <div style="font-weight:700;font-size:20px;font-variant-numeric:tabular-nums"><?= e(function_exists('fmoney_short') ? fmoney_short($gsM['outstanding']) : number_format($gsM['outstanding'])) ?></div></a>
      <?php endif; ?>
      <a href="/system-status" style="text-decoration:none;color:inherit"><div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em">Platform</div>
        <div style="font-weight:700;font-size:15px;color:<?= $gsHc ?>">● <?= e($gsHl) ?></div></a>
      <a href="/command-centre" class="btn small secondary" style="margin-left:auto">Command Centre →</a>
    </div>
  <?php endif; ?>
  <?php if ($gsGlance['actions']): ?>
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px">
      <h3 class="tab-sub" style="margin:0">Your next actions</h3><a href="/my-work" class="muted" style="font-size:12px;text-decoration:none">My Work →</a>
    </div>
    <div style="display:flex;flex-direction:column;margin-top:6px">
      <?php foreach ($gsGlance['actions'] as $gsAct): $gsDot = ['info'=>'#2563eb','warn'=>'#b45309','bad'=>'var(--bad,#c0392b)'][$gsAct['tone']] ?? '#2563eb'; ?>
        <a href="<?= e($gsAct['href']) ?>" style="display:flex;align-items:center;gap:10px;padding:7px 2px;border-top:1px solid var(--line,#e5e7eb);text-decoration:none;color:inherit">
          <span style="width:7px;height:7px;border-radius:50%;background:<?= $gsDot ?>;flex:none"></span>
          <span style="flex:1;font-size:13.5px;font-weight:600"><?= e($gsAct['title']) ?></span>
          <?php if (!empty($gsAct['overdue'])): ?><span style="font-size:11px;font-weight:700;color:var(--bad,#c0392b)">overdue</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

