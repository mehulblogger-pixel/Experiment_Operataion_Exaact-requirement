<?php
// ============================================================================
//  OPERATIONS HOME — the area landing, in the Sales look.
//
//  Operations Batch 1 (owner, 22 Nov 2026: "Can you revamp same UI UX as sales
//  module"; design pack EXAACT-Operations-Batch1-Designs.pdf approved with six
//  answers). Built on the RECORD PAGE KIT and the SALES WORKSPACE kit
//  (views/ops/_rec_ui.php, _sxw_ui.php) — no design language of its own:
//    · the area bar (every Operations screen this person may open, in the
//      theme's colour — the same bar as Sales), and the TPIA dashboards linked
//      from it (answer 5);
//    · the eight figures as the product's one KPI card;
//    · Your work, Waiting for a person, Disruptions & changes and the
//      cross-office calls as cards;
//    · the four groups of screens as tabs, as before (#screens);
//    · the phone bottom bar, whose items the administrator sets (answer 2),
//      with Punch for anyone who may punch today (answer 3).
//  The "Nothing is removed" note is gone (answer 4).
//
//  Look and feel only. Every figure, list, link, form and permission comes from
//  the same handler (lib/tosrm.php ops_operations_home) and the same helpers as
//  before; every control of the old page is still here for the same people.
// ============================================================================
require_once __DIR__ . '/_sxw_ui.php';
$m = $metrics;
$c = $counts;
// A screen tile inside the tabs. $stats: [value,label] pairs; $badge: optional [text,tone].
$tile = function ($href, $icon, $title, $desc, $stats = [], $badge = null) {
    echo '<a class="ox-tile" href="' . e($href) . '"><span class="ic">' . $icon . '</span><span class="b"><b>' . e($title)
       . ($badge ? ' <span class="sxw-due ' . ($badge[1] === 'red' ? 'p-bad' : ($badge[1] === 'amber' ? 'p-warn' : 'p-info')) . '">' . e($badge[0]) . '</span>' : '') . '</b><small>' . e($desc) . '</small>';
    $st = array_filter($stats, fn($s) => $s[0] !== null);
    if ($st) { echo '<span class="st">'; foreach ($st as $s) echo '<span><em>' . e((string) $s[0]) . '</em> ' . e($s[1]) . '</span>'; echo '</span>'; }
    echo '</span>' . rec_icon('right', 's') . '</a>';
};
$pend = $pending ?? [];
$today0 = strtotime(date('Y-m-d'));
?>
<div class="rec sxw ox has-bn">
<?php sxw_nav('/operations', 'operations'); ?>
<?php
$right = '<div class="sxw-ctl ox-acts">'
  . ((can('mod.calls.edit') || can('mod.calls.view')) ? '<a class="sxw-bt p" href="/call-new">' . rec_icon('plus', 's') . e(ucfirst(T_NEW('call'))) . '</a>' : '')
  . ((function_exists('sched_board_can') && sched_board_can()) ? '<a class="sxw-bt" href="/schedule">' . rec_icon('cal', 's') . 'Scheduling board</a>' : '')
  //  WHO THE WORK IS FOR — doors to the client and vendor registers, which are
  //  maintained in the Directory area; the same office scope as everything else.
  . (can('mod.clients.view') ? '<a class="sxw-bt" href="/clients">' . rec_icon('building', 's') . e(function_exists('THP') ? THP('client') : 'Clients') . '</a>' : '')
  . (can('mod.vendors.view') ? '<a class="sxw-bt" href="/vendors">' . rec_icon('brief', 's') . e(function_exists('THP') ? THP('vendor') : 'Vendors') . '</a>' : '')
  . '</div>';
sxw_head('Operations', 'Everything the field delivers — work orders, jobs, scheduling, deputation and the TPIA service lines, scoped to your branches.', 'flow', $right); ?>
<div class="ox-live"><span class="dot"></span>Live — computed from source records now · <?= e(date('d M Y H:i')) ?><?= $scopeLabel ? ' · ' . e($scopeLabel) : '' ?></div>

<div class="sxw-kp" style="--n:4">
<?php
sxw_kpi('info', 'file',   'New requests',       'New requests',       (string) (int) ($m['new'] ?? 0),            'awaiting triage',          '/calls');
sxw_kpi('warn', 'cal',    'Unscheduled',        'Unscheduled',        (string) (int) ($m['unscheduled'] ?? 0),    'ready but no date',        '/schedule');
sxw_kpi('warn', 'msg',    'Clarification open', 'Clarification open', (string) (int) ($m['clarification'] ?? 0),  'waiting on client/vendor', '/calls');
sxw_kpi('ok',   'checkc', 'Ready to schedule',  'Ready to schedule',  (string) (int) ($m['ready'] ?? 0),          'all inputs complete',      '/schedule');
sxw_kpi('info', 'clock',  "Today's services",   'Today’s services',   (string) (int) ($m['today'] ?? 0),          'scheduled for today',      '/operations#schedule');
sxw_kpi('bad',  'alert',  'Overdue',            'Overdue',            (string) (int) ($m['overdue'] ?? 0),        'past required date',       '/operations#backlog');
sxw_kpi('bad',  'bolt',   'Critical',           'Critical',           (string) (int) ($m['critical'] ?? 0),       'flagged priority',         '/calls');
sxw_kpi('warn', 'note',   'Report pending',     'Report pending',     (string) (int) ($m['report_pending'] ?? 0), 'done, not issued',         '/jobs');
?>
</div>

<div class="ox-2">
  <div class="ox-col">
  <?php // Owner, 22 Nov 2026: "Your work and your pending tasks are the same thing … delete Your work".
        //  The ranked "Your next actions" list (action_centre, B9-2) repeated the pending-task cards
        //  below it, so Operations home now shows the pending-task cards alone. Nothing is lost:
        //  every item of both lists is on /my-work, linked from the card ("See all in My Work").
        //  The Dashboard and the area homes keep the ranked strip. ?>
  <?= function_exists('ops_render_pending_tasks') ? ops_render_pending_tasks() : '' ?>

  <?php // ---- Pending scheduling — the cumulative list ---------------------------
        //  Every call this branch still has to put an engineer on, same-office and
        //  cross-office together. The most pressing are shown; the rest sit in a fold
        //  (B9-1): every call is still on this page, one tap away, counted in the title.
  if ($pend):
    rec_card_open('Waiting for a person (' . count($pend) . ')', 'users', '<span class="muted ox-sub">every ' . e(Tl('call')) . ' awaiting a person</span>');
    $row = function ($pc) use ($today0) {
      $req = (string) $pc['inspection_required_date'];
      $days = $req !== '' ? (int) round((strtotime($req) - $today0) / 86400) : null;
      $cross = !empty($pc['executing_office_id']) && !empty($pc['ibo_office_id']) && (int) $pc['executing_office_id'] !== (int) $pc['ibo_office_id'];
      //  B10-CL-1 — Allocate only for somebody who may actually do it: call_can_allocate()
      //  is the SAME predicate the /job-new route enforces. "Open" is for everyone who sees the row.
      $mayAlloc = function_exists('call_can_allocate') ? call_can_allocate($pc) : is_coordinator_level();
      echo '<div class="ox-row"><span class="c"><b><a href="/call?id=' . (int) $pc['id'] . '">' . e($pc['call_code']) . '</a></b><small>' . e($pc['client_name'] ?: '—') . ($cross ? ' · <span class="sxw-due p-warn">cross-office</span>' : '') . '</small></span>'
         . '<span class="s"><small>' . e(lk_options_or('inspection_type', INSPECTION_TYPES)[$pc['inspection_type']] ?? ($pc['inspection_type'] ?: '—')) . ($pc['sbu'] ? ' · ' . e(lk_options_or('sbu', OPS_SBUS)[$pc['sbu']] ?? $pc['sbu']) : '') . '</small><small>Needed by ' . e($req ?: '—') . '</small></span>'
         . '<span>' . ($days !== null ? '<span class="sxw-due ' . ($days < 0 ? 'p-bad' : ($days <= 2 ? 'p-warn' : 'p-ok')) . '">' . ($days < 0 ? abs($days) . 'd overdue' : $days . 'd') . '</span>' : '') . '</span>'
         . '<span class="a">' . ($mayAlloc ? '<a class="sxw-bt p" href="/job-new?call=' . (int) $pc['id'] . '">Allocate</a>' : '') . '<a class="sxw-bt" href="/call?id=' . (int) $pc['id'] . '">Open</a></span></div>';
    };
    $pendCap = 6;   // named, not a magic number (B9-1)
    foreach (array_slice($pend, 0, $pendCap) as $pc) $row($pc);
    if (count($pend) > $pendCap) {
      echo '<details class="sxw-fold ox-in"><summary><span class="x">Show the remaining ' . (count($pend) - $pendCap) . ' awaiting a person</span>' . rec_icon('down', 's chev') . '</summary><div class="fb">';
      foreach (array_slice($pend, $pendCap) as $pc) $row($pc);
      echo '</div></details>';
    }
    rec_card_close();
  endif; ?>
  </div>

  <div class="ox-col">
  <?php // ---- Disruptions & changes — churn already logged on jobs, rolled up: client /
        //  office cancellations, engineer changes and no-shows, each with its recorded
        //  reason, for the period picked, scoped to your branches.
  $d = $disrupt ?? null;
  if ($d):
    $drCur = $drKey ?? 'fy';
    $seg = '<nav class="sxw-seg" aria-label="Period">';
    foreach (TOSRM_DISRUPT_RANGES as $k => $lab) $seg .= '<a href="/operations?dr=' . e($k) . '"' . ($drCur === $k ? ' class="on" aria-current="true"' : '') . '>' . e($lab) . '</a>';
    $seg .= '</nav>';
    rec_card_open('Disruptions & changes', 'swap');
    echo '<div class="ox-seg">' . $seg . '</div>'; ?>
    <div class="sxw-kp sm" style="--n:2">
      <?php sxw_kpi('bad',  'x',        'Client cancelled', 'Client cancelled', (string) (int) $d['client_cancel'],   'the call was called off',    '#dz');
            sxw_kpi('warn', 'swap',     'Engineer changes', 'Engineer changes', (string) (int) $d['engineer_change'], 'reassigned to another',      '#dz');
            sxw_kpi('bad',  'building', 'Office cancelled', 'Office cancelled', (string) (int) $d['office_cancel'],   'executing branch pulled it', '#dz');
            sxw_kpi('warn', 'user',     'No-shows',         'No-shows',         (string) (int) $d['noshow'],          'client / vendor no-show',    '#dz'); ?>
    </div>
    <details class="sxw-fold ox-in" id="dz"><summary><span class="x">What happened, with reasons</span><?= rec_icon('down', 's chev') ?></summary>
      <div class="fb"><?= tosrm_disruptions_html($d, $drLabel ?? '', false) ?></div></details>
  <?php rec_card_close(); endif; ?>

  <?php // ---- Cross-office calls (contracting ↔ executing) -------------------------
        //  Raised by one branch, executed by another: the executing branch allocates,
        //  the contracting branch tracks and can nudge. Only shown when such calls exist.
  $xoData = $xo ?? null;
  if ($xoData && (!empty($xoData['inbound']) || !empty($xoData['sentout']))):
    rec_card_open('Cross-office calls — allocate & follow-up', 'flow'); ?>
    <details class="sxw-fold ox-in"><summary><span class="x"><?= count($xoData['inbound'] ?? []) + count($xoData['sentout'] ?? []) ?> calls between branches</span><?= rec_icon('down', 's chev') ?></summary>
      <div class="fb"><?= tosrm_xo_html($xoData, $csrf ?? '') ?></div></details>
  <?php rec_card_close(); endif; ?>
  </div>
</div>

<?php // The four groups of screens are tabs (app.js → initSectionTabs). "All Operations
      // screens" on the bar lands here (#screens). ?>
<div id="screens" class="ox-tabs" data-tabs data-tabs-key="area">
<section data-tab="Backlog &amp; registers">
  <?php include __DIR__ . '/_ops_registers.php'; ?>
</section>
<section data-tab="Work intake &amp; delivery"><div class="ox-grid">
  <?php
  $tile('/calls', '📋', THP('call'), 'Orders from the client, with lead time and delay.',
        [[$c['calls_open'], 'Open'], [$m['new'] ?? null, 'New']]);
  $tile('/jobs', '🗂️', THP('job'), 'Allocation, execution and per-day closure.',
        [[$c['jobs_active'], 'Active'], [$m['unscheduled'] ?? null, 'Unscheduled']],
        ($m['report_pending'] ?? 0) > 0 ? [(int) $m['report_pending'] . ' report pending', 'amber'] : null);
  // Scheduling board, capacity outlook and availability are one workspace with tabs.
  if (function_exists('sched_board_can') && sched_board_can())
    $tile('/schedule', '🗓️', 'Scheduling board', 'Board, capacity and availability — one workspace with tabs.',
          [[$m['today'] ?? null, 'Today'], [$m['ready'] ?? null, 'Ready']]);
  if (can('mod.calls.view'))
    $tile('/recurring', '🔁', 'Recurring services', 'Contracts that raise work on a schedule.', [[$c['recurring'], 'Active']]);
  ?>
</div></section>

<section data-tab="TPIA service lines"><div class="ox-grid">
  <?php
  // From the Service Scope Engine — only services active on this install, each
  // linking to the screen that delivers it.
  foreach ($services as $s) $tile($s['route'] ?: '#', ($s['icon'] ?: '🧩'), $s['name'], $s['description'] ?: '', [], ['Service', 'svc']);
  ?>
</div></section>

<section data-tab="Controls, time &amp; people"><div class="ox-grid">
  <?php
  if (can('mod.calls.view'))
    $tile('/contract-overrides', '🛑', 'Contract exceptions', 'Overrides where a contract is exhausted or expired.',
          [], ($c['overrides'] ?? 0) > 0 ? [(int) $c['overrides'] . ' pending', 'red'] : null);
  if (can('mod.reconcile.view'))
    $tile('/attendance-recon', '✅', 'Attendance reconciliation', 'Entry / exit against what was billed.');
  if (function_exists('timesheet_can') && timesheet_can())
    $tile('/timesheet', '⏱️', 'Timesheet', 'Hours logged against jobs.');
  if (can('mod.vouchers.view'))
    $tile('/vouchers', '🧾', THP('voucher'), 'Field expenses raised against a job.', [[$c['vouchers'], 'Pending']]);
  if (function_exists('rating_can') && rating_can())
    $tile('/ratings', '⭐', 'Inspector ratings', 'Post-job quality & conduct ratings.');
  if (can('mod.hiring.view'))
    $tile('/recruitment', '🧭', 'Recruitment', 'Command centre — pipeline, requirements, candidates & deployment, all in one place.',
          [[$c['requisitions'], 'Open reqs']]);
  ?>
</div></section>
</div>
</div>
<?php sxw_bottom('/operations', null, 'operations'); ?>
