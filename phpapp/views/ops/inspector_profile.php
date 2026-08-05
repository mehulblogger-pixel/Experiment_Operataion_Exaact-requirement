<?php $ins = $ins ?? []; $certs = $certs ?? []; $rating = $rating ?? null; $jobStats = $jobStats ?? [];
  $recentJobs = $recentJobs ?? []; $reportsDone = $reportsDone ?? 0; $ts = $ts ?? []; $tsMonth = $tsMonth ?? date('Y-m'); $canSalary = $canSalary ?? false;
  $starStr = function($n){ $f=floor($n); $h=($n-$f)>=0.5; return str_repeat('★',(int)$f).($h?'⯪':'').str_repeat('☆',5-(int)$f-($h?1:0)); };
  $tone = fn($v)=> $v>=80?'p-ok':($v>=50?'p-warn':'p-bad');
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/m/inspectors"><?= e(THP('engineer')) ?></a> › <?= e($ins['name']) ?></div>
<div class="master-head"><div>
  <h1><?= e($ins['name']) ?> <?php if ($ins['status']!=='ACTIVE'): ?><span class="pill p-mut"><?= e($ins['status']) ?></span><?php endif; ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($ins['emp_code'] ?: '—') ?><?= $ins['sbu'] ? ' · '.e(sbu_label($ins['sbu']) ?: $ins['sbu']) : '' ?>
    <?= $ins['mobile'] ? ' · 📞 '.e($ins['mobile']) : '' ?><?= $ins['email'] ? ' · '.e($ins['email']) : '' ?></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn secondary" href="/timesheet?ins=<?= (int)$ins['id'] ?>">⏱️ Timesheet</a>
    <a class="btn secondary" href="/ratings">⭐ Ratings</a>
    <?php if (is_master() || (function_exists('is_coordinator_level') && is_coordinator_level())): ?><a class="btn secondary" href="/m/inspectors/edit?id=<?= (int)$ins['id'] ?>">Edit</a><?php endif; ?>
  </div>
</div>

<?php // ---- headline cards ---- ?>
<div class="stat-row" style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap">
  <?php if ($rating): ?>
  <div class="qcard"><div class="qn" style="font-size:20px;color:#e6a700"><?= $starStr($rating['stars']) ?></div>
    <div class="ql">Rating <span class="pill <?= $tone($rating['overall']) ?>"><?= (int)$rating['overall'] ?></span> · last <?= (int)$rating['months'] ?> mo</div></div>
  <div class="qcard <?= $rating['report_score']===null ? '' : ($rating['report_score']>=80?'tone-ok':'') ?>">
    <div class="qn" style="font-size:22px"><?= $rating['report_score']===null ? '—' : (int)$rating['report_score'].'%' ?></div>
    <div class="ql">Reporting accuracy<?= $rating['report_score']===null ? '' : ' ('.(int)$rating['on_time'].'/'.(int)$rating['reportable'].')' ?></div></div>
  <?php endif; ?>
  <div class="qcard"><div class="qn" style="font-size:22px"><?= (int)$jobStats['done'] ?></div><div class="ql">Inspections done<?= (int)$jobStats['open']>0 ? ' · '.(int)$jobStats['open'].' open' : '' ?></div></div>
  <div class="qcard"><div class="qn" style="font-size:22px"><?= (int)$reportsDone ?></div><div class="ql">Reports written</div></div>
  <div class="qcard"><div class="qn" style="font-size:22px"><?= e(function_exists('timesheet_hhmm') ? timesheet_hhmm($ts['total_minutes']) : '—') ?></div><div class="ql">On site this month</div></div>
</div>

<div class="panel-split" style="margin-top:16px;flex-wrap:wrap">
  <?php // ---- details ---- ?>
  <div class="panel">
    <h3 style="margin-top:0">Details</h3>
    <div class="kv-grid">
      <div><span class="k">Employee code</span><?= e($ins['emp_code'] ?: '—') ?></div>
      <div><span class="k"><?= e(T('sbu')) ?></span><?= e($ins['sbu'] ? (sbu_label($ins['sbu']) ?: $ins['sbu']) : '—') ?></div>
      <div class="kv-wide"><span class="k">Skills</span><?= e($ins['skills'] ?: '—') ?></div>
      <div><span class="k">Mobile</span><?= e($ins['mobile'] ?: '—') ?></div>
      <div><span class="k">E-mail</span><?= e($ins['email'] ?: '—') ?></div>
      <div><span class="k">Leave balance</span><?= e(rtrim(rtrim(number_format((float)$ins['leave_balance'],1),'0'),'.')) ?> d</div>
      <div><span class="k">Comp-off</span><?= e(rtrim(rtrim(number_format((float)$ins['compoff_balance'],1),'0'),'.')) ?> d</div>
      <?php if ($canSalary): ?><div><span class="k">CTC</span><?= e(fmoney($ins['salary_ctc'])) ?></div><?php endif; ?>
    </div>
  </div>

  <?php // ---- certifications ---- ?>
  <div class="panel">
    <h3 style="margin-top:0">Certifications <span class="muted">(<?= count($certs) ?>)</span></h3>
    <?php if ($certs): ?>
      <table class="dt"><thead><tr><th>Certificate</th><th>Number</th><th>Valid to</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($certs as $c): $st = inspector_cert_state($c['valid_to']); ?>
        <tr><td><?= e($c['name']) ?><?php if (!empty($c['is_mandatory'])): ?> <span class="pill p-info" style="font-size:10px">required</span><?php endif; ?></td>
          <td class="muted"><?= e($c['number'] ?: '—') ?></td>
          <td class="muted"><?= $c['valid_to'] ? e(fdate($c['valid_to'])) : '—' ?></td>
          <td><span class="pill <?= $st['pill'] ?>"><?= e($st['label']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php else: ?><p class="muted" style="margin:0">No certifications recorded.</p><?php endif; ?>
  </div>
</div>

<?php // ---- rating breakdown ---- ?>
<?php if ($rating): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">How the rating is made <span class="muted" style="font-weight:400">— last <?= (int)$rating['months'] ?> months</span></h3>
  <div class="kv-grid">
    <div><span class="k">Timely reporting</span><?= $rating['report_score']===null ? '<span class="muted">no reports yet</span>' : '<span class="pill '.$tone($rating['report_score']).'">'.(int)$rating['report_score'].'</span> '.(int)$rating['on_time'].' of '.(int)$rating['reportable'].' on time' ?></div>
    <div><span class="k">Inspections done</span><span class="pill <?= $tone($rating['volume_score']) ?>"><?= (int)$rating['volume_score'] ?></span> <?= (int)$rating['done'] ?> of <?= (int)$rating['target'] ?> target</div>
    <div><span class="k">Complaints</span><?= (int)$rating['complaints']===0 ? '<span class="pill p-ok">none</span>' : '<span class="pill p-bad">'.(int)$rating['complaints'].'</span> → '.(int)$rating['comp_score'].'/100' ?></div>
    <div><span class="k">Overall</span><b><?= (int)$rating['overall'] ?>/100</b> · <span style="color:#e6a700"><?= $starStr($rating['stars']) ?></span></div>
  </div>
</div>
<?php endif; ?>

<?php // ---- recent jobs ---- ?>
<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px"><b style="font-size:13.5px">Recent <?= e(Tlp('job')) ?></b></div>
  <div class="dt-scroll"><table class="dt">
    <thead><tr><th><?= e(TH('job')) ?></th><th><?= e(Tl('client')) ?></th><th>Scheduled</th><th>Report</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($recentJobs as $j): ?>
      <tr><td><code><?= e($j['job_code']) ?></code></td><td><?= e($j['client_name'] ?: '—') ?></td>
        <td class="muted"><?= $j['scheduled_date'] ? e(fdate($j['scheduled_date'])) : '—' ?></td>
        <td class="muted"><?= $j['report_upload_date'] ? e(fdate($j['report_upload_date'])) : '—' ?></td>
        <td><?= $j['closed_flag'] ? '<span class="pill p-ok">closed</span>' : '<span class="pill p-warn">open</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recentJobs): ?><tr><td colspan="5" class="muted" style="padding:16px">No jobs yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
