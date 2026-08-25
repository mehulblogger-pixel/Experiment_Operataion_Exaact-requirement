<?php $inspectors = $inspectors ?? []; $ins = $ins ?? null; $insId = $insId ?? 0; $month = $month ?? date('Y-m'); $ts = $ts ?? []; $attStatus = $attStatus ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › Timesheet</div>
<div class="master-head"><div>
  <h1>Timesheet</h1>
  <p class="sub" style="margin:2px 0 0">Built automatically from each inspector's site punches — hours on site, per day, with the day's attendance.</p>
</div>
<?php if ($ins): ?><a class="btn secondary" href="/timesheet?ins=<?= (int)$insId ?>&amp;month=<?= e($month) ?>&amp;export=csv">⬇ CSV</a><?php endif; ?>
</div>

<form method="get" action="/timesheet" class="panel" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:14px">
  <div class="ff" style="margin:0;min-width:220px"><label>Inspector</label>
    <select class="form-control searchable" name="ins" onchange="this.form.submit()">
      <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (int)$i['id']===(int)$insId?'selected':'' ?>><?= e($i['name']) ?><?= $i['emp_code'] ? ' · '.e($i['emp_code']) : '' ?></option><?php endforeach; ?>
    </select></div>
  <div class="ff" style="margin:0"><label>Month</label><input class="form-control" type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"></div>
  <button class="btn small secondary" type="submit">Show</button>
</form>

<?php if ($ins): ?>
<div class="stat-row" style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap">
  <div class="qcard tone-ok"><div class="qn" style="font-size:22px"><?= e(timesheet_hhmm($ts['total_minutes'])) ?></div><div class="ql">On site this month</div></div>
  <div class="qcard"><div class="qn" style="font-size:22px"><?= (int)$ts['days_on_site'] ?></div><div class="ql">Days on site</div></div>
  <div class="qcard"><div class="qn" style="font-size:22px"><?= (int)$ts['total_jobs'] ?></div><div class="ql">Site visits</div></div>
  <div class="qcard"><div class="qn" style="font-size:22px"><?= (int)$ts['days_att'] ?></div><div class="ql">Attendance-marked days</div></div>
  <?php $anomalies = $anomalies ?? []; ?>
  <div class="qcard <?= $anomalies ? 'tone-bad' : 'tone-ok' ?>"><div class="qn" style="font-size:22px"><?= count($anomalies) ?></div><div class="ql">Reconciliation flags</div></div>
</div>

<?php // ---- Module 31: reconciliation — cross-checks presence vs hours and flags anomalies ---- ?>
<?php if ($anomalies): $anLabel = ['impossible'=>'Impossible timing', 'missing_checkout'=>'Missing check-out', 'overlap'=>'Overlapping jobs', 'excessive'=>'Excessive hours', 'no_hours'=>'On site, no hours', 'no_presence'=>'Hours, no check-in']; ?>
<div class="panel" style="margin-top:14px;border-left:3px solid var(--bad)">
  <h3 class="tab-sub" style="margin-top:0">Reconciliation flags <span class="muted" style="font-weight:400;font-size:12px">(<?= count($anomalies) ?>)</span></h3>
  <p class="muted" style="margin:0 0 8px;font-size:12.5px">Cross-checked from the site punches, the voucher hours and the daily cap. These need a look — nothing is blocked.</p>
  <table class="dt"><thead><tr><th>Date</th><th>Flag</th><th>Detail</th></tr></thead><tbody>
    <?php foreach ($anomalies as $a): ?>
      <tr><td class="muted" style="white-space:nowrap"><?= e(date('d M', strtotime($a['date']))) ?></td>
        <td><span class="pill <?= in_array($a['type'], ['impossible','excessive'], true) ? 'p-bad' : 'p-warn' ?>"><?= e($anLabel[$a['type']] ?? $a['type']) ?></span></td>
        <td style="font-size:12.5px"><?= e($a['text']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php elseif ($ins): ?>
<div class="panel" style="margin-top:14px;border-left:3px solid var(--ok)"><p style="margin:0"><b style="color:var(--ok)">✓ Nothing to reconcile</b> — presence, hours and the daily cap all line up this month.</p></div>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="dt-scroll"><table class="dt">
    <thead><tr><th>Date</th><th>Jobs</th><th>First in</th><th>Last out</th><th class="num">On site</th><th>Attendance</th></tr></thead>
    <tbody>
    <?php foreach ($ts['rows'] as $r): ?>
      <tr>
        <td><b><?= e(fdate($r['date'])) ?></b> <span class="muted" style="font-size:11px"><?= e(date('D', strtotime($r['date']))) ?></span></td>
        <td class="muted" style="font-size:12.5px"><?= e(implode(', ', $r['jobs'])) ?: '—' ?></td>
        <td class="muted"><?= $r['in'] ? e(substr($r['in'],11,5)) : '—' ?></td>
        <td class="muted"><?= $r['out'] ? e(substr($r['out'],11,5)) : '—' ?></td>
        <td class="num"><?php if ($r['minutes']>0): ?><b><?= e(timesheet_hhmm($r['minutes'])) ?></b><?php elseif ($r['incomplete']): ?><span class="pill p-warn" title="Arrived but no departure punch">open</span><?php else: ?>—<?php endif; ?></td>
        <td><?= $r['status']==='ONSITE' ? '<span class="pill p-ok">On site</span>' : ($r['status'] ? '<span class="pill p-mut">'.e($attStatus[$r['status']] ?? $r['status']).'</span>' : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$ts['rows']): ?><tr><td colspan="6" class="muted" style="padding:16px">No punches or attendance recorded for this inspector in <?= e($month) ?>.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($ts['rows']): ?>
    <tfoot><tr style="font-weight:600;border-top:2px solid var(--line)">
      <td>Total</td><td class="muted"><?= (int)$ts['total_jobs'] ?> visits</td><td></td><td></td>
      <td class="num"><?= e(timesheet_hhmm($ts['total_minutes'])) ?></td><td class="muted"><?= (int)$ts['days_on_site'] ?> days on site</td>
    </tr></tfoot>
    <?php endif; ?>
  </table></div>
</div>
<p class="muted" style="font-size:12px;margin-top:10px">“On site” hours come from the arrival and departure punches. A day showing <span class="pill p-warn">open</span> has an
  arrival with no departure yet. Turn on geofencing (Evidence &amp; check-in settings) to guarantee the punches are made at the site.</p>
<?php endif; ?>
