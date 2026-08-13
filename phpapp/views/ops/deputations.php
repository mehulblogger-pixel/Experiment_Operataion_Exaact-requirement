<?php
  // Deputation & site operations dashboard. Reuses the home dashboard's KPI
  // language (.kpi-row / .kpi) so it reads as one system. Every figure links to
  // the work. A deputation is the existing DEPUTATION-type job — this screen adds
  // nothing to how a job is created; it surfaces the Phase-7 site-ops layer.
  $lbl = fn($code) => $statuses[$code] ?? ($code ?: '—');
  $tone = function($code) {
      return in_array($code, ['SUSPENDED','REPLACEMENT_REQ','CANCELLED'], true) ? 'down'
           : (in_array($code, ['ACTIVE','MOBILIZED'], true) ? 'up' : '');
  };
  $fmtDate = fn($s) => $s ? date('d M Y', strtotime($s)) : '—';
?>
<div class="crumbs"><a href="/">Home</a> › Deputation &amp; site operations</div>
<div class="master-head">
  <div><h1>Deputation &amp; site operations</h1>
    <p class="sub" style="margin:2px 0 0">Personnel deputed to a client / project / site — lifecycle, mobilization,
      manpower, attendance sign-off, site registers and reporting. Each posting is a deputation
      <?= e(function_exists('Tlp') ? Tlp('job') : 'job') ?>; this view surfaces its site operations.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/documents?cat=DEPUTATION">Deputation reports</a>
  </div>
</div>

<!-- KPI row — same visual language as the home dashboard -->
<div class="kpi-row" style="margin-top:18px">
  <div class="kpi"><span class="kic">👷</span><div class="k">Active postings</div>
    <div class="v"><a href="/deputations?status=ACTIVE"><?= (int)$d['active'] ?></a></div>
    <div class="d"><?= (int)$d['total'] ?> in total</div></div>
  <div class="kpi"><span class="kic">🧳</span><div class="k">Mobilization pending</div>
    <div class="v"><a href="/deputations?status=MOB_PENDING"><?= (int)$d['mob_pending'] ?></a></div>
    <div class="d">Planned / awaiting mobilization</div></div>
  <div class="kpi"><span class="kic">🧩</span><div class="k">Manpower gap</div>
    <div class="v"><?= $d['manpower_gap'] > 0 ? '<span class="down">'.(int)$d['manpower_gap'].'</span>' : '0' ?></div>
    <div class="d"><?= (int)$d['manpower_mobilized'] ?> of <?= (int)$d['manpower_required'] ?> mobilized</div></div>
  <div class="kpi"><span class="kic">🌴</span><div class="k">On leave</div>
    <div class="v"><a href="/deputations?status=ON_LEAVE"><?= (int)$d['on_leave'] ?></a></div>
    <div class="d"><?= (int)$d['replacement'] ? '<span class="down">'.(int)$d['replacement'].' need replacement</span>' : 'No replacements pending' ?></div></div>
</div>

<!-- What's waiting for a person — mirrors the home "compliance waiting" band -->
<?php
  $wait = [];
  if ((int)$d['pending_approvals'])  $wait[] = ['/deputations', '✅', (int)$d['pending_approvals'], 'Attendance sign-offs waiting', 'warn'];
  if ((int)$d['overdue_actions'])    $wait[] = ['/deputations', '⏰', (int)$d['overdue_actions'], 'Site actions past their date', 'bad'];
  if ((int)$d['conflict_people'])    $wait[] = ['/deputations', '⚠', (int)$d['conflict_people'], 'People double-booked', 'bad'];
  if ((int)$d['demob_planned'])      $wait[] = ['/deputations?status=DEMOB_PLANNED', '📦', (int)$d['demob_planned'], 'Demobilizations coming up', 'warn'];
  if ((int)$d['suspended'])          $wait[] = ['/deputations?status=SUSPENDED', '⏸', (int)$d['suspended'], 'Postings suspended', 'warn'];
?>
<?php if ($wait): ?>
<div class="qcards" style="margin-top:14px">
  <?php foreach ($wait as [$href,$ic,$n,$label,$t]): $cls = $t==='bad'?'tone-bad':($t==='warn'?'tone-warn':'tone-info'); ?>
    <a class="qcard <?= $cls ?>" href="<?= e($href) ?>"><div class="qic"><?= $ic ?></div><div class="qn"><?= (int)$n ?></div><div class="ql"><?= e($label) ?></div></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Manpower plan & gap -->
<?php if ($manpower): ?>
<section class="card" style="margin-top:18px">
  <h2 style="margin:0 0 10px">Manpower plan &amp; gap</h2>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Project</th><th>Position</th><th style="text-align:right">Required</th>
        <th style="text-align:right">Mobilized</th><th style="text-align:right">Gap</th></tr></thead>
      <tbody>
      <?php foreach ($manpower as $m): $g = (int)$m['gap']; ?>
        <tr>
          <td><?= e($m['project']) ?: '—' ?></td>
          <td><?= e($m['position']) ?></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int)$m['required'] ?></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int)$m['mobilized'] ?></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums"><?= $g > 0 ? '<span class="down">'.$g.'</span>' : '0' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Deputation register -->
<section class="card" style="margin-top:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
    <h2 style="margin:0">Deputations<?= $status !== '' ? ' · ' . e($lbl($status)) : '' ?></h2>
    <form method="get" action="/deputations" style="margin:0">
      <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if (!$rows): ?>
    <p class="muted" style="margin:0">No deputations<?= $status !== '' ? ' with this status' : '' ?> yet. A deputation is a
      <?= e(function_exists('Tlp') ? Tlp('job') : 'job') ?> of type “Resident / site posting” — raise one from a
      <?= e(function_exists('Tlp') ? Tlp('call') : 'call') ?>, then set its status here.</p>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th><?= e(function_exists('THP') ? THP('job') : 'Deputation') ?></th><th>Person</th><th>Client</th>
          <th>Site</th><th>Period</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $st = (string)($r['dep_status'] ?? ''); ?>
          <tr>
            <td><a href="/job?id=<?= (int)$r['id'] ?>"><?= e($r['job_code'] ?: ('#'.(int)$r['id'])) ?></a></td>
            <td><?= e($r['person_name'] ?: '—') ?></td>
            <td><?= e($r['client_name'] ?: '—') ?></td>
            <td><?= e($r['dep_site'] ?: '—') ?></td>
            <td style="white-space:nowrap"><?= e($fmtDate($r['inspection_start_date'] ?? '')) ?> – <?= e($fmtDate($r['inspection_end_date'] ?? '')) ?></td>
            <td><?php if ($st !== ''): ?><span class="pill <?= $tone($st) ?>"><?= e($lbl($st)) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
