<?php
// ============================================================================
//  OPERATIONS HOME — the area landing.
//
//  The left rail no longer expands "Operations" into a wall of nineteen links.
//  Tapping Operations brings you here: every screen that used to hide inside
//  that dropdown is laid out on the page, grouped and with its live state, so
//  the page tells you what Operations looks like today rather than making you
//  open each register to find out.
//
//  Nothing is removed — every tile is a link to the screen that already exists.
// ============================================================================
$m = $metrics;
$c = $counts;
// A tile. $stats is an array of [value,label] pairs; $badge is optional [text,tone].
$tile = function ($href, $icon, $title, $desc, $stats = [], $badge = null) {
    echo '<a class="op-tile" href="' . e($href) . '">';
    echo '<span class="op-ic">' . $icon . '</span>';
    echo '<span class="op-b"><span class="op-t">' . e($title);
    if ($badge) echo ' <span class="op-badge ' . e($badge[1]) . '">' . e($badge[0]) . '</span>';
    echo '</span><span class="op-d">' . e($desc) . '</span>';
    if ($stats) {
        echo '<span class="op-stat">';
        foreach ($stats as $s) {
            if ($s[0] === null) continue;
            echo '<span><b>' . e((string)$s[0]) . '</b><i>' . e($s[1]) . '</i></span>';
        }
        echo '</span>';
    }
    echo '</span><span class="op-go">›</span></a>';
};
?>
<style>
  /* Operations Home — scoped presentation, uses the app tokens. */
  .op-live{display:flex;align-items:center;gap:7px;color:var(--muted);font-size:12.5px;margin:14px 0 10px}
  .op-live .dot{width:7px;height:7px;border-radius:50%;background:var(--green,#16a34a);box-shadow:0 0 0 3px color-mix(in srgb,var(--green,#16a34a) 22%,transparent)}
  .op-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:6px}
  .op-kpi{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-left:4px solid var(--brand,#1e40af);
    border-radius:12px;padding:12px 14px;text-decoration:none;color:inherit;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));display:block}
  .op-kpi .v{font-size:25px;font-weight:700;line-height:1.1}
  .op-kpi .l{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a);margin-top:2px}
  .op-kpi .h{font-size:11px;color:#9aa3af;margin-top:4px}
  .op-kpi.warn{border-left-color:var(--amber,#d97706)} .op-kpi.warn .v{color:var(--amber,#d97706)}
  .op-kpi.bad{border-left-color:var(--red,#dc2626)} .op-kpi.bad .v{color:var(--red,#dc2626)}
  .op-kpi.good{border-left-color:var(--green,#16a34a)} .op-kpi.good .v{color:var(--green,#16a34a)}
  .op-sec{font-size:12px;letter-spacing:.5px;text-transform:uppercase;color:var(--muted,#656e7a);font-weight:700;
    margin:26px 0 10px;display:flex;align-items:center;gap:10px}
  .op-sec::after{content:"";flex:1;height:1px;background:var(--line,#e5e7eb)}
  .op-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .op-tile{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:13px 14px;
    text-decoration:none;color:inherit;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));display:flex;gap:11px;align-items:flex-start;transition:.12s}
  .op-tile:hover{box-shadow:var(--shadow,0 10px 30px rgba(18,32,60,.10));transform:translateY(-1px)}
  .op-ic{width:36px;height:36px;flex:0 0 36px;border-radius:10px;background:var(--info-bg,#eff6ff);
    display:grid;place-items:center;font-size:18px}
  .op-b{min-width:0;flex:1;display:flex;flex-direction:column}
  .op-t{font-weight:600;font-size:14.5px;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
  .op-d{color:var(--muted,#656e7a);font-size:12.5px;margin-top:2px;line-height:1.45}
  .op-stat{margin-top:9px;display:flex;gap:16px;flex-wrap:wrap}
  .op-stat b{font-size:16px;font-weight:700;display:block}
  .op-stat i{font-size:10.5px;color:var(--muted,#656e7a);text-transform:uppercase;letter-spacing:.3px;font-style:normal}
  .op-badge{font-size:10px;font-weight:700;border-radius:20px;padding:2px 8px}
  .op-badge.red{background:var(--bad-bg,#fef2f2);color:var(--bad,#b91c1c)}
  .op-badge.amber{background:var(--warn-bg,#fffbeb);color:var(--warn,#b45309)}
  .op-badge.svc{background:#eef2ff;color:var(--brand,#1e40af)}
  .op-go{color:#c7ccd4;font-size:17px;align-self:center}
  .op-note{background:#fffdf5;border:1px solid #f0e6c8;border-radius:12px;padding:12px 15px;font-size:13px;color:#6b5d2f;margin:20px 0 4px}
  .op-note b{color:#4d4320}
  @media (max-width:820px){ .op-kpis{grid-template-columns:repeat(2,1fr)} .op-grid{grid-template-columns:1fr} }
</style>

<div class="crumbs"><a href="/">Home</a> › Operations</div>
<div class="master-head">
  <div>
    <h1>Operations</h1>
    <p class="sub" style="margin:2px 0 0">Everything the field delivers — work orders, jobs, scheduling, deputation and the TPIA service lines. Every figure is scoped to your branches and opens the records behind it.</p>
  </div>
  <div class="row-actions">
    <?php if (can('mod.calls.edit') || can('mod.calls.view')): ?><a class="btn primary" href="/call-new">＋ <?= e(ucfirst(T_NEW('call'))) ?></a><?php endif; ?>
    <?php if (function_exists('tosrm_ops_desk_can') && tosrm_ops_desk_can()): ?><a class="btn secondary" href="/ops-desk">Operations desk</a><?php endif; ?>
    <?php if (function_exists('sched_board_can') && sched_board_can()): ?><a class="btn secondary" href="/schedule">Scheduling board</a><?php endif; ?>
  </div>
</div>

<div class="op-live"><span class="dot"></span> Live — computed from source records now · <?= e(date('d M Y H:i')) ?><?= $scopeLabel ? ' · ' . e($scopeLabel) : '' ?></div>

<?= function_exists('ops_render_pending_tasks') ? ops_render_pending_tasks() : '' ?>

<div class="op-kpis">
  <a class="op-kpi" href="/calls"><div class="v"><?= (int)($m['new'] ?? 0) ?></div><div class="l">New requests</div><div class="h">awaiting triage</div></a>
  <a class="op-kpi warn" href="/schedule"><div class="v"><?= (int)($m['unscheduled'] ?? 0) ?></div><div class="l">Unscheduled</div><div class="h">ready but no date</div></a>
  <a class="op-kpi warn" href="/calls"><div class="v"><?= (int)($m['clarification'] ?? 0) ?></div><div class="l">Clarification open</div><div class="h">waiting on client/vendor</div></a>
  <a class="op-kpi good" href="/schedule"><div class="v"><?= (int)($m['ready'] ?? 0) ?></div><div class="l">Ready to schedule</div><div class="h">all inputs complete</div></a>
  <a class="op-kpi" href="/ops-desk"><div class="v"><?= (int)($m['today'] ?? 0) ?></div><div class="l">Today’s services</div><div class="h">scheduled for today</div></a>
  <a class="op-kpi bad" href="/ops-desk"><div class="v"><?= (int)($m['overdue'] ?? 0) ?></div><div class="l">Overdue</div><div class="h">past required date</div></a>
  <a class="op-kpi bad" href="/calls"><div class="v"><?= (int)($m['critical'] ?? 0) ?></div><div class="l">Critical</div><div class="h">flagged priority</div></a>
  <a class="op-kpi warn" href="/jobs"><div class="v"><?= (int)($m['report_pending'] ?? 0) ?></div><div class="l">Report pending</div><div class="h">done, not issued</div></a>
</div>

<?php // ---- Pending scheduling — the cumulative list -------------------------
      //  Every call this branch still has to put an engineer on, same-office and
      //  cross-office together, so there is one place that answers "what needs
      //  scheduling". Cross-office ones are tagged; their hand-off lives below.
      $pend = $pending ?? []; if ($pend): $today0 = strtotime(date('Y-m-d')); ?>
  <div class="op-sec">Pending scheduling — every <?= e(Tl('call')) ?> awaiting a person <span class="muted" style="font-weight:400;text-transform:none;letter-spacing:0">(<?= count($pend) ?>)</span></div>
  <div class="card-grid">
    <?php foreach ($pend as $pc):
        $req = (string)$pc['inspection_required_date'];
        $days = $req !== '' ? (int)round((strtotime($req) - $today0) / 86400) : null;
        $cross = !empty($pc['executing_office_id']) && !empty($pc['ibo_office_id'])
                 && (int)$pc['executing_office_id'] !== (int)$pc['ibo_office_id']; ?>
      <div class="master-card" style="cursor:default">
        <strong><a href="/call?id=<?= (int)$pc['id'] ?>" style="text-decoration:none;color:inherit"><?= e($pc['call_code']) ?></a></strong>
        <span class="muted"><?= e($pc['client_name'] ?: '—') ?><?= $cross ? ' · <span class="badge AMBER">cross-office</span>' : '' ?></span>
        <span class="muted"><?= e(lk_options_or('inspection_type', INSPECTION_TYPES)[$pc['inspection_type']] ?? ($pc['inspection_type'] ?: '—')) ?><?= $pc['sbu'] ? ' · ' . e(lk_options_or('sbu', OPS_SBUS)[$pc['sbu']] ?? $pc['sbu']) : '' ?></span>
        <span class="muted">Needed by <?= e($req ?: '—') ?><?php if ($days !== null): ?> · <span class="badge <?= $days < 0 ? 'RED' : ($days <= 2 ? 'AMBER' : 'GREEN') ?>"><?= $days < 0 ? abs($days) . 'd overdue' : $days . 'd' ?></span><?php endif; ?></span>
        <span style="margin-top:6px"><a class="btn small" href="/job-new?call=<?= (int)$pc['id'] ?>">Allocate</a>
          <a class="btn small secondary" href="/call?id=<?= (int)$pc['id'] ?>">Open</a></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php // ---- Cross-office calls (contracting ↔ executing) ---------------------
      //  Calls raised by one branch and executed by another. Each office keeps
      //  the TAT clock in view: the executing branch allocates; the contracting
      //  branch tracks and can nudge. Only shown when such calls exist.
      $xoData = $xo ?? null;
      if ($xoData && (!empty($xoData['inbound']) || !empty($xoData['sentout']))): ?>
  <div class="op-sec">Cross-office calls — allocate &amp; follow-up</div>
  <?= tosrm_xo_html($xoData, $csrf ?? '') ?>
<?php endif; ?>

<?php // ---- Disruptions & changes — churn already logged on jobs, rolled up.
      //  Client / office cancellations, engineer changes and no-shows, each with
      //  its recorded reason. This financial year, scoped to your branches. ?>
<?php $d = $disrupt ?? null; if ($d): $drCur = $drKey ?? 'fy'; ?>
<style>
  .dz-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:26px 0 10px}
  .dz-head .t{font-size:12px;letter-spacing:.5px;text-transform:uppercase;color:var(--muted,#656e7a);font-weight:700}
  .dz-filter{display:inline-flex;gap:6px;flex-wrap:wrap}
  .dz-chip{font-size:12px;font-weight:600;padding:3px 11px;border-radius:20px;border:1px solid var(--line,#e5e7eb);
    background:var(--card,#fff);color:var(--muted,#656e7a);text-decoration:none}
  .dz-chip.on{background:var(--brand,#1e40af);color:#fff;border-color:var(--brand,#1e40af)}
</style>
<div class="dz-head">
  <span class="t">Disruptions &amp; changes</span>
  <span class="dz-filter">
    <?php foreach (TOSRM_DISRUPT_RANGES as $k => $lab): ?>
      <a href="/operations?dr=<?= e($k) ?>" class="dz-chip<?= $drCur === $k ? ' on' : '' ?>"><?= e($lab) ?></a>
    <?php endforeach; ?>
  </span>
</div>
<?= tosrm_disruptions_html($d, $drLabel ?? '') ?>
<?php endif; ?>

<?php // The three groups become tabs (app.js → initSectionTabs); the KPI row
      // above stays pinned as the summary. One long screen → three short ones. ?>
<div data-tabs data-tabs-key="area">
<section data-tab="Work intake &amp; delivery"><div class="op-grid">
  <?php
  $tile('/calls', '📋', THP('call'), 'Orders from the client, with lead time and delay.',
        [[$c['calls_open'], 'Open'], [$m['new'] ?? null, 'New']]);
  $tile('/jobs', '🗂️', THP('job'), 'Allocation, execution and per-day closure.',
        [[$c['jobs_active'], 'Active'], [$m['unscheduled'] ?? null, 'Unscheduled']],
        ($m['report_pending'] ?? 0) > 0 ? [(int)$m['report_pending'] . ' report pending', 'amber'] : null);
  if (function_exists('sched_board_can') && sched_board_can())
    $tile('/schedule', '🗓️', 'Scheduling board', 'Assign inspectors to dates; capacity-aware.',
          [[$m['today'] ?? null, 'Today'], [$m['ready'] ?? null, 'Ready']]);
  if (function_exists('tosrm_ops_desk_can') && tosrm_ops_desk_can())
    $tile('/ops-desk', '🎛️', 'Operations desk', 'The control centre — backlog, schedule & assignment registers.',
          [[$m['overdue'] ?? null, 'At risk']]);
  if (function_exists('tosrm_ops_desk_can') && tosrm_ops_desk_can())
    $tile('/capacity-outlook', '📈', 'Capacity outlook', 'Who is free, who is stretched, over the coming weeks.');
  if (can('mod.calls.view'))
    $tile('/recurring', '🔁', 'Recurring services', 'Contracts that raise work on a schedule.',
          [[$c['recurring'], 'Active']]);
  ?>
</div></section>

<section data-tab="TPIA service lines"><div class="op-grid">
  <?php
  // Rendered from the Service Scope Engine — only services active on this
  // install appear, each linking to the screen that delivers it.
  foreach ($services as $s) {
    $tile($s['route'] ?: '#', ($s['icon'] ?: '🧩'), $s['name'], $s['description'] ?: '', [], ['Service', 'svc']);
  }
  if (can('mod.jobs.view') && function_exists('can_manage_availability') && can_manage_availability())
    $tile('/availability', '🟢', TH('engineer') . ' availability', 'Daily availability board for the field team.');
  ?>
</div></section>

<section data-tab="Controls, time &amp; people"><div class="op-grid">
  <?php
  if (can('mod.calls.view'))
    $tile('/contract-overrides', '🛑', 'Contract exceptions', 'Overrides where a contract is exhausted or expired.',
          [], ($c['overrides'] ?? 0) > 0 ? [(int)$c['overrides'] . ' pending', 'red'] : null);
  if (can('mod.reconcile.view'))
    $tile('/attendance-recon', '✅', 'Attendance reconciliation', 'Entry / exit against what was billed.');
  if (function_exists('timesheet_can') && timesheet_can())
    $tile('/timesheet', '⏱️', 'Timesheet', 'Hours logged against jobs.');
  if (can('mod.vouchers.view'))
    $tile('/vouchers', '🧾', THP('voucher'), 'Field expenses raised against a job.',
          [[$c['vouchers'], 'Pending']]);
  if (function_exists('rating_can') && rating_can())
    $tile('/ratings', '⭐', 'Inspector ratings', 'Post-job quality & conduct ratings.');
  if (can('mod.hiring.view'))
    $tile('/recruitment-cc', '🧭', 'Recruitment', 'Command centre — pipeline, requirements, candidates & deployment, all in one place.',
          [[$c['requisitions'], 'Open reqs']]);
  ?>
</div></section>
</div>

<div class="op-note"><b>Nothing is removed.</b> Every screen that lived inside the old “Operations” menu is still here — it now opens from this page instead of a nested dropdown.</div>
