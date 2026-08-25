<?php
  $today = date('Y-m-d');
  $nSched = 0; $nOverdue = 0; $nClosed = 0;
  foreach ($rows as $c) {
    // A call is DONE when its status is CLOSED, or every job under it has closed —
    // the same rule the call detail uses, so the register agrees with it.
    $cDone = ($c['status'] ?? '') === 'CLOSED' || ((int)$c['job_count'] > 0 && (int)($c['closed_count'] ?? 0) === (int)$c['job_count']);
    $needs = !$cDone && (int)$c['job_count'] === 0;
    if ($cDone) $nClosed++;
    elseif ($needs) { $nSched++; if (($c['inspection_required_date'] ?? '') && $c['inspection_required_date'] < $today) $nOverdue++; }
  }
?>
<?php $fContract = $fContract ?? ''; $fQuote = $fQuote ?? 0; $filtered = ($fContract !== '' || $fQuote); ?>
<?php // Commercial columns are permission-gated: credit follows data.credit, the
      //  cost figure follows the profitability/salary permission. Operational staff
      //  without them (e.g. an assistant manager) see the register without these.
      $seeCredit = can('data.credit') || is_master();
      $seeCost   = can('data.profitability') || (function_exists('can_see_salary') && can_see_salary()) || is_master(); ?>
<div class="master-head">
  <div><h1><?= e(T_REG('call')) ?></h1>
  <?php if ($filtered): ?>
    <p class="sub" style="margin:2px 0 0">Showing every <?= e(Tl('call')) ?> under
      <?= $fContract !== '' ? 'contract <b>' . e($fContract) . '</b>' : (e(Tl('quote')) . ' #' . (int)$fQuote) ?>.
      <a href="/calls" style="margin-left:6px">Clear filter →</a></p>
  <?php else: ?>
  <p class="sub" style="margin:2px 0 0">Received from clients — open one to allocate, or edit the details. Rows tinted red are running late; the lead column reads received→forwarded · forwarded→allocated · received→scheduled.</p>
  <?php endif; ?></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/calls?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ CSV</a>
    <?php if (is_coordinator_level()): ?><a class="btn" href="/call-new">➕ <?= e(ucfirst(T_NEW('call'))) ?></a><?php endif; ?>
  </div>
</div>

<div class="chip-row">
  <span class="ct">Showing <b><?= count($rows) ?></b></span>
  <span class="ct"><span class="dot dot-warn"></span><?= $nSched ?> to schedule</span>
  <?php if ($nOverdue): ?><span class="ct"><span class="dot dot-bad"></span><?= $nOverdue ?> overdue</span><?php endif; ?>
  <span class="ct"><span class="dot dot-ok"></span><?= $nClosed ?> closed</span>
</div>

<form method="get" action="/calls" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="🔍 Search code, client or vendor…">
  <input class="form-control" type="number" name="mincost" value="<?= e($minCost ?? '') ?>" placeholder="Min cost <?= e(cur_sym()) ?>" style="max-width:140px">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q || ($minCost ?? '') !== ''): ?><a class="btn secondary" href="/calls">Clear</a><?php endif; ?>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">☎️</div>
      <p class="muted" style="margin:8px 0 0">No calls yet. <?php if (is_coordinator_level()): ?><a href="/call-new">Create the first call</a>.<?php endif; ?></p></div>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt" style="min-width:1650px">
    <thead><tr>
      <th><?= e(ucfirst(Tl('call'))) ?></th><th><?= e(T('client')) ?></th><th><?= e(T('vendor')) ?> / site</th>
      <th><?= e(T('sbu')) ?></th><th>Activity</th><th>Reporting</th>
      <th>Executing <?= e(T('office')) ?></th><?php if ($seeCredit): ?><th class="num">Credit to give</th><?php endif; ?>
      <th>Coordinator</th><th><?= e(T('engineer')) ?></th>
      <th>Received</th><th>Forwarded</th><th>Allocated</th>
      <th>Required by</th><th>Engagement</th><th>Ends</th><th>Scheduled</th>
      <th class="num" title="Received → forwarded · forwarded → allocated · received → scheduled">Lead (days)</th>
      <th class="num">Delay</th><th class="num"><?= e(TP('job')) ?></th><?php if ($seeCost): ?><th class="num">Cost</th><?php endif; ?><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $c):
      $closed = ($c['status'] ?? '') === 'CLOSED' || ((int)$c['job_count'] > 0 && (int)($c['closed_count'] ?? 0) === (int)$c['job_count']);
      $needs = !$closed && (int)$c['job_count'] === 0;
      $req = $c['inspection_required_date'] ?? '';
      $reqOverdue = $needs && $req && $req < $today;
      $L = $c['lead'] ?? [];
      $dash = '<span class="muted">—</span>';
    ?>
      <tr<?= !empty($L['late']) ? ' style="background:color-mix(in srgb,var(--bad) 5%,transparent)"' : '' ?>>
        <td><a href="/call?id=<?= (int)$c['id'] ?>"><b><?= e($c['call_code']) ?></b></a>
            <?php if (!empty($c['folder_link'])): ?><a href="<?= e($c['folder_link']) ?>" target="_blank" rel="noopener" title="Shared folder">🔗</a><?php endif; ?></td>
        <td><?= e($c['client_disp'] ?: $c['client_name'] ?: '—') ?>
            <?php if (!empty($c['contract_number'])): ?>
              <div class="muted" style="font-size:11px"><?= e($c['contract_number']) ?></div>
            <?php else: ?>
              <?php // Visible in the register too, so a coordinator scanning the
                    // list can see which orders still have paperwork outstanding. ?>
              <div style="font-size:11px"><span class="pill p-warn">contract no. pending</span></div>
            <?php endif; ?></td>
        <td><?= $c['vendor_name'] ? e($c['vendor_name']) : $dash ?></td>
        <td><?= e(lk_options_or('sbu', OPS_SBUS)[$c['sbu']] ?? '—') ?></td>
        <td><?= !empty($c['activity_id']) ? e(lk_value_path($c['activity_id'])) : $dash ?></td>
        <?php // §i — the reporting rhythm and the reports owed, on the register
              // itself. Both are carried onto the job at allocation, so what is
              // shown here is what the engineer will be asked to produce. ?>
        <td><?php
          $fq = $c['reporting_frequency'] ?? '';
          if ($fq === '' || $fq === 'NOREPORT') {
            echo '<span class="pill p-mut">no report</span>';
          } else {
            $lbl = lk_options_or('reporting_frequency', REPORT_FREQ)[$fq] ?? $fq;
            if ($fq === 'CUSTOM' && !empty($c['report_custom_days'])) $lbl .= ' / ' . (int)$c['report_custom_days'] . 'd';
            echo '<span class="pill p-info">' . e($lbl) . '</span>';
          }
          $dl = array_values(array_filter(array_map('trim', explode(',', (string)($c['deliverables'] ?? '')))));
          if ($dl) echo '<div class="muted" style="font-size:11px;margin-top:2px">' . e(implode(' · ', array_slice($dl, 0, 4)))
                      . (count($dl) > 4 ? ' +' . (count($dl) - 4) : '') . '</div>';
          // The rhythm above is what is OWED; if reports have actually been issued,
          // say so — otherwise a reported call still read "no report".
          if ((int)($c['issued_reports'] ?? 0) > 0)
            echo '<div style="margin-top:2px"><span class="pill p-ok" style="font-size:10.5px">✓ ' . (int)$c['issued_reports'] . ' issued</span></div>';
        ?></td>
        <td><?= !empty($c['exec_office_name']) ? e($c['exec_office_name']) : '<span class="muted">same ' . e(T('office')) . '</span>' ?></td>
        <?php if ($seeCredit): ?><td class="num"><?= ((float)($c['expected_credit'] ?? 0)) > 0 ? fmoney($c['expected_credit']) : $dash ?></td><?php endif; ?>
        <td><?= !empty($c['coordinator_display']) ? e($c['coordinator_display']) : $dash ?></td>
        <?php // The engineer on the job, or — if the job carries no named inspector
              //  but the work was reported — whoever wrote the report, so a completed
              //  call never reads "not allocated". ?>
        <td><?php $who = $c['inspector_name'] ?: ($c['report_inspector'] ?? '');
             echo $who !== '' ? e($who)
                 : '<span class="pill p-warn">not allocated' . (isset($L['unallocated_days']) && $L['unallocated_days'] !== null ? ' · ' . (int)$L['unallocated_days'] . 'd' : '') . '</span>'; ?></td>
        <td><?= $c['call_received_date'] ? e(fdate($c['call_received_date'])) : $dash ?></td>
        <td><?= !empty($c['forwarded_at']) ? e(fdate(substr((string)$c['forwarded_at'],0,10))) : $dash ?></td>
        <td><?= !empty($c['allocated_at']) ? e(fdate(substr((string)$c['allocated_at'],0,10))) : $dash ?></td>
        <td><?= $req ? ($reqOverdue ? '<span class="down" style="font-weight:700">'.e(fdate($req)).'</span>' : e(fdate($req))) : $dash ?></td>
        <?php // §when — what was actually asked for, and the day it finishes.
              // A single-day call says so in one word; a continuous run says how
              // many days; the end date is worked out over Sundays and this
              // branch's public holidays rather than guessed. ?>
        <?php $sr = sched_resolve($c, $c['executing_office_id'] ?? null); ?>
        <td><span class="pill p-info"><?= e($sr['label']) ?></span>
            <?php if ($sr['count'] > 1): ?><div class="muted" style="font-size:11px"><?= (int)$sr['count'] ?> day(s)</div><?php endif; ?></td>
        <td><?= $sr['end'] && $sr['count'] > 1 ? e(fdate($sr['end'])) : $dash ?></td>
        <td><?= !empty($c['sched_date']) ? e(fdate($c['sched_date'])) : $dash ?></td>
        <td class="num" style="white-space:nowrap"><span class="muted" style="font-size:11.5px">
          <?= $L['to_forward']  !== null ? (int)$L['to_forward']  : '–' ?> ·
          <?= $L['to_allocate'] !== null ? (int)$L['to_allocate'] : '–' ?> ·
          <?= $L['to_schedule'] !== null ? (int)$L['to_schedule'] : '–' ?>
        </span></td>
        <td class="num">
          <?php if (($L['delay'] ?? null) === null): ?><?= $dash ?>
          <?php elseif ((int)$L['delay'] > 0): ?><span class="pill p-bad"><?= (int)$L['delay'] ?>d late</span>
          <?php else: ?><span class="pill p-ok">on time</span><?php endif; ?>
        </td>
        <td class="num"><?= (int)$c['job_count'] ?: '<span class="muted">0</span>' ?></td>
        <?php if ($seeCost): ?><td class="num"><?= ((float)($c['cost_incurred'] ?? 0))>0 ? fmoney($c['cost_incurred']) : $dash ?></td><?php endif; ?>
        <td>
          <?php // Module 04 — the unified lifecycle status (over both status systems). ?>
          <?php if (function_exists('call_status_label')): $cLc = call_status_label($c); ?>
            <span class="pill <?= e($cLc['tone']) ?>"><?= e($cLc['label']) ?></span><br>
          <?php endif; ?>
          <?php if ($closed): ?><span class="pill p-ok">Closed</span>
          <?php elseif ($needs): ?><span class="pill p-warn">To schedule</span>
          <?php else: ?><span class="pill p-info">In progress</span><?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <a class="btn small secondary" href="/call?id=<?= (int)$c['id'] ?>">Open</a>
          <?php if (is_coordinator_level()): ?><a class="btn small" href="/call-edit?id=<?= (int)$c['id'] ?>">Edit</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
