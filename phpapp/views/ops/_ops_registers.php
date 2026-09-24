<?php // Operations registers — backlog, schedule & assignment — folded into the
      //  Operations home (formerly the separate Operations desk). Expects
      //  $backlog, $schedule, $assignments, $dataquality, $from, $to.
      //
      //  B9-1 — these four registers are the Operations home's length. The data
      //  layer already caps them (60 / 60 / 40 rows), but on a phone the B7
      //  responsive-table engine renders each row as a card (79–262px), so four
      //  full registers came to 46,666px — 82% of a 57,082px page.
      //
      //  So each register now shows its most recent rows inline and keeps the
      //  remainder one tap away in the SAME <details class="fold"> that B6 uses
      //  in 46 other views. Nothing is removed, nothing is re-queried, no row is
      //  unreachable, and the summary states exactly how many are behind it.
      //  The section ids (#backlog, #schedule, #assignments) are unchanged, so
      //  /ops-desk → /operations#backlog still lands where it always did.
?>
<div class="row-actions" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
  <?php if (function_exists('sched_board_can') && sched_board_can()): ?><a class="btn secondary" href="/schedule">Scheduling board</a><?php endif; ?>
  <a class="btn secondary" href="/recurring">Recurring</a>
  <a class="btn secondary" href="/sla-targets">SLA targets</a>
</div>

<?php
// How many rows of each register are shown before the fold. Chosen so the first
// screenful of a register is useful on a phone; the rest is one tap away.
$regCap = 8;

// Render one register: the header row, the first $regCap rows, then — only when
// there are more — a fold holding every remaining row in an identical table.
// $rows is an array of complete <tr>…</tr> strings.
$regTable = function (array $rows, $headHtml, $emptyHtml) use ($regCap) {
    $open  = '<div style="overflow-x:auto"><table class="grid">' . $headHtml;
    $close = '</table></div>';
    if (!$rows) { echo $open . $emptyHtml . $close; return; }
    $head = array_slice($rows, 0, $regCap);
    $rest = array_slice($rows, $regCap);
    echo $open . implode('', $head) . $close;
    if ($rest) {
        echo '<details class="fold"><summary>Show the remaining ' . count($rest)
           . ' <span class="sub">of ' . count($rows) . ' shown here</span></summary>'
           . $open . implode('', $rest) . $close . '</details>';
    }
};
?>

<?php if (!empty($dataquality)): ?>
<section class="card" style="margin-bottom:14px">
  <h2 style="margin:0 0 8px">Data-quality flags <span class="muted" style="font-size:13px">(advisory — complete or override on the call)</span></h2>
  <?php
  $rows = [];
  foreach ($dataquality as $dq) {
      $rows[] = '<tr><td><a href="/call?id=' . (int)$dq['call_id'] . '#ops">'
              . e($dq['call_code'] ?: ('#' . $dq['call_id'])) . '</a></td><td>'
              . e(implode(', ', $dq['flags'])) . '</td></tr>';
  }
  $regTable($rows, '<tr><th>Call</th><th>Missing before scheduling</th></tr>', '');
  ?>
</section>
<?php endif; ?>

<section class="card" style="margin-bottom:14px" id="backlog">
  <h2 style="margin:0 0 8px">Backlog</h2>
  <?php
  $rows = [];
  foreach (($backlog ?? []) as $b) {
      $rows[] = '<tr' . (!empty($b['overdue']) ? ' style="background:rgba(180,35,43,.06)"' : '') . '>'
          . '<td><a href="/call?id=' . (int)$b['id'] . '#ops">' . e($b['call_code'] ?: ('#' . $b['id'])) . '</a></td>'
          . '<td>' . e($b['client_name'] ?: '—') . '</td>'
          . '<td>' . e(INSPECTION_TYPES[$b['inspection_type']] ?? $b['inspection_type']) . '</td>'
          . '<td>' . e($b['inspection_required_date'] ?: '—') . '</td>'
          . '<td style="text-align:right">' . ($b['age_days'] === null ? '—' : (int)$b['age_days']) . '</td>'
          . '<td>' . e($b['pending_reason']) . '</td>'
          . '<td>' . ($b['priority'] ? '<span class="badge">' . e($b['priority']) . '</span>' : '—') . '</td>'
          . '</tr>';
  }
  $regTable($rows,
      '<tr><th>Call</th><th>Client</th><th>Service</th><th>Required</th><th>Age (d)</th><th>Pending on</th><th>Priority</th></tr>',
      '<tr><td colspan="7">Nothing in the backlog — all caught up.</td></tr>');
  ?>
</section>

<section class="card" style="margin-bottom:14px" id="schedule">
  <h2 style="margin:0 0 8px">Schedule register <span class="muted" style="font-size:13px"><?= e($from ?? '') ?> → <?= e($to ?? '') ?></span></h2>
  <?php
  $rows = [];
  foreach (($schedule ?? []) as $s) {
      $rows[] = '<tr>'
          . '<td>' . e($s['scheduled_date'] ?: '—') . '</td>'
          . '<td><a href="/job?id=' . (int)$s['job_id'] . '#assign">' . e($s['job_code']) . '</a></td>'
          . '<td>' . e($s['client_name'] ?: '—') . '</td>'
          . '<td>' . e(INSPECTION_TYPES[$s['inspection_type']] ?? $s['inspection_type']) . '</td>'
          . '<td>' . e($s['inspector_name'] ?: 'Unassigned') . '</td>'
          . '<td>' . e($s['assign_state'] ?: '—') . '</td>'
          . '<td>' . e($s['accept_state'] ?: '—') . '</td>'
          . '<td><span class="badge">' . e(lk_options_or('job_stage', JOB_STAGES)[$s['stage'] ?? ''] ?? ($s['stage'] ?? '')) . '</span></td>'
          . '</tr>';
  }
  $regTable($rows,
      '<tr><th>Date</th><th>Job</th><th>Client</th><th>Service</th><th>Resource</th><th>Hold</th><th>Acceptance</th><th>Stage</th></tr>',
      '<tr><td colspan="8">No scheduled work in this window.</td></tr>');
  ?>
</section>

<section class="card" id="assignments">
  <h2 style="margin:0 0 8px">Assignment register</h2>
  <?php
  $rows = [];
  foreach (($assignments ?? []) as $a) {
      $rows[] = '<tr>'
          . '<td><a href="/job?id=' . (int)$a['job_id'] . '#assign">' . e($a['job_code']) . '</a></td>'
          . '<td>' . e($a['inspector_name'] ?: '—') . '</td>'
          . '<td>' . e($a['client_name'] ?: '—') . '</td>'
          . '<td>' . e(INSPECTION_TYPES[$a['inspection_type']] ?? $a['inspection_type']) . '</td>'
          . '<td>' . e($a['scheduled_date'] ?: '—') . '</td>'
          . '<td>' . e($a['assign_state'] ?: '—') . '</td>'
          . '<td>' . e($a['accept_state'] ?: '—') . (trim((string)$a['accept_reason']) !== '' ? ' · ' . e($a['accept_reason']) : '') . '</td>'
          . '</tr>';
  }
  $regTable($rows,
      '<tr><th>Job</th><th>Resource</th><th>Client</th><th>Service</th><th>Date</th><th>Hold</th><th>Acceptance</th></tr>',
      '<tr><td colspan="7">No assignments yet.</td></tr>');
  ?>
</section>
