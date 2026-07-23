<?php
  $act = activity_options_by_sbu();
  // Prefill from the call for a new job; use the job's own values when editing.
  $curSbu   = $job ? ($job['sbu'] ?? '')             : ($call['sbu'] ?? '');
  $curActId = $job ? ($job['activity_id'] ?? '')     : ($call['activity_id'] ?? '');
  $curInsp  = $job ? ($job['inspection_type'] ?? '') : ($call['inspection_type'] ?? '');
  $curFreq  = $job ? ($job['reporting_frequency'] ?? 'NOREPORT') : 'NOREPORT';
  $curDays  = $job['report_custom_days'] ?? '';
  $curDeliv = $job && $job['deliverables'] !== '' ? explode(',', $job['deliverables']) : [];
  $curActRow = $curActId ? lk_value($curActId) : null;
  function contact_block($info, $role) {
    if (!$info) { echo '<div class="muted">No ' . e($role) . ' selected on the call.</div>'; return; }
    $p = $info['p'];
    echo '<div class="info-party"><strong>' . e($p['display_name'] ?: $p['legal_name']) . '</strong> <span class="muted">' . e($p['code']) . '</span>';
    if ($p['gstin']) echo '<div class="muted">GSTIN ' . e($p['gstin']) . '</div>';
    $addr = $info['addresses'][0] ?? null;
    if ($addr) echo '<div class="muted">' . e(trim(($addr['line1'] ?? '') . ' ' . ($addr['city'] ?? '') . ' ' . ($addr['state'] ?? ''))) . '</div>';
    if ($info['contacts']) {
      echo '<table class="mini"><tr><th>Contact</th><th>Designation</th><th>Mobile</th><th>Email</th></tr>';
      foreach ($info['contacts'] as $c) echo '<tr><td>' . e($c['name']) . '</td><td>' . e($c['designation'] ?: '—') . '</td><td>' . e($c['mobile'] ?: $c['phone'] ?: '—') . '</td><td>' . e($c['email'] ?: '—') . '</td></tr>';
      echo '</table>';
    } else echo '<div class="muted">No contact persons recorded.</div>';
    echo '</div>';
  }
?>
<h1><?= $job ? 'Edit Job ' . e($job['job_code']) : 'Allocate Job' ?></h1>
<p class="sub">From call <strong><?= e($call['call_code']) ?></strong>. Expected credit is mandatory. The inspector gets an assignment email once a schedule date is set.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<div class="panel info-panel">
  <h3 class="tab-sub">Client &amp; vendor details (from the call)</h3>
  <div class="panel-split">
    <div><div class="info-role">Client</div><?php contact_block($clientInfo ?? null, 'client'); ?></div>
    <div><div class="info-role">Vendor / Site</div><?php contact_block($vendorInfo ?? null, 'vendor'); ?></div>
  </div>
</div>

<form method="post" action="<?= $job ? '/job-edit?id=' . (int)$job['id'] : '/job-new?call=' . (int)$call['id'] ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Executing office</label>
      <select class="form-control searchable" name="executing_office_id">
        <?php foreach ($offices as $o): $sel = $job ? $job['executing_office_id']==$o['id'] : (($call['executing_office_id']??null)? $call['executing_office_id']==$o['id'] : $o['code']==='AHM'); ?>
          <option value="<?= (int)$o['id'] ?>" <?= $sel?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Stage</label>
      <select class="form-control" name="stage"><?php foreach (JOB_STAGES as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($job['stage'] ?? 'ALLOCATED')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Job type</label>
      <select class="form-control" name="job_type"><?php foreach (JOB_TYPES as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($job['job_type'] ?? 'INSPECTION')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      <small class="muted">For deputation at site, set the start and completion dates over the deputation period.</small></div>
    <div class="ff"><label>Type of inspection</label>
      <select class="form-control searchable" name="inspection_type"><option value="">—</option>
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $curInsp===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>SBU <span class="muted">(from call)</span></label>
      <select class="form-control" id="sbu_sel" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $curSbu===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Activity code <span class="muted">(from call)</span></label>
      <select class="form-control" id="activity_sel" name="activity_id"><option value="">— pick SBU first —</option>
        <?php if ($curActRow) echo '<option value="'.(int)$curActRow['id'].'" selected>'.e($curActRow['label']).'</option>'; ?>
      </select></div>

    <div class="ff"><label>Inspector</label>
      <select class="form-control searchable" name="inspector_id"><option value="">—</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ($job && $job['inspector_id']==$i['id'])?'selected':'' ?>><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Sub-contractor (if used)</label>
      <select class="form-control searchable" name="subcon_id"><option value="">—</option>
        <?php foreach ($subcons as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ($job && $job['subcon_id']==$s['id'])?'selected':'' ?>><?= e($s['agency']) ?><?= $s['inspector_name']?' — '.e($s['inspector_name']):'' ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Sub-con cost (₹)</label><input class="form-control" type="number" step="0.01" name="subcon_cost" value="<?= e($job['subcon_cost'] ?? '') ?>"></div>
    <div class="ff"><label>BOSS number</label>
      <select class="form-control searchable" name="boss_id"><option value="">—</option>
        <?php foreach ($boss as $bn): ?><option value="<?= (int)$bn['id'] ?>" <?= ($job && $job['boss_id']==$bn['id'])?'selected':'' ?>><?= e($bn['boss_number']) ?> (<?= e($bn['status']) ?>)</option><?php endforeach; ?>
      </select>
      <?php if (!$boss): ?><small class="muted">No BOSS numbers for this client yet — add under <a href="/m/boss/new">BOSS numbers</a>.</small><?php endif; ?></div>

    <div class="ff"><label>Scheduled date</label><input class="form-control" type="date" name="scheduled_date" value="<?= e($job['scheduled_date'] ?? '') ?>"></div>
    <div class="ff"><label>Inspection start</label><input class="form-control" type="date" name="inspection_start_date" value="<?= e($job['inspection_start_date'] ?? '') ?>"></div>
    <div class="ff"><label>Inspection end</label><input class="form-control" type="date" name="inspection_end_date" value="<?= e($job['inspection_end_date'] ?? '') ?>"></div>
    <div class="ff"><label>Random date 1</label><input class="form-control" type="date" name="random_date1" value="<?= e($job['random_date1'] ?? '') ?>"></div>
    <div class="ff"><label>Random date 2</label><input class="form-control" type="date" name="random_date2" value="<?= e($job['random_date2'] ?? '') ?>"></div>
    <div class="ff"><label>Random date 3</label><input class="form-control" type="date" name="random_date3" value="<?= e($job['random_date3'] ?? '') ?>"></div>
    <div class="ff"><label>Man-days (0 = auto from dates)</label><input class="form-control" type="number" step="0.5" name="mandays" value="<?= e($job['mandays'] ?? '0') ?>"></div>

    <div class="ff"><label>Expected credit (₹) *</label><input class="form-control" type="number" step="0.01" name="expected_credit" value="<?= e($job['expected_credit'] ?? $call['expected_credit'] ?? '') ?>" required></div>
    <div class="ff"><label>Credit type</label>
      <select class="form-control searchable" name="credit_type"><?php foreach (lk_options_or('credit_type', CREDIT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($job['credit_type'] ?? $call['credit_type'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Credit direction</label>
      <select class="form-control searchable" name="credit_direction"><?php foreach (lk_options_or('credit_direction', CREDIT_DIRECTIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($job && $job['credit_direction']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>

    <div class="ff"><label>Reporting frequency</label>
      <select class="form-control" id="freq_sel" name="reporting_frequency"><?php foreach (lk_options_or('reporting_frequency', REPORT_FREQ) as $k=>$v): ?><option value="<?= e($k) ?>" <?= $curFreq===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff" id="custom_days_wrap" style="<?= $curFreq==='CUSTOM'?'':'display:none' ?>"><label>…every how many days?</label>
      <input class="form-control" type="number" min="1" name="report_custom_days" value="<?= e($curDays) ?>" placeholder="e.g. 3"></div>

    <div class="ff ff-wide"><label>Report folder link (SharePoint / Drive)</label><input class="form-control" name="folder_link" value="<?= e($job['folder_link'] ?? '') ?>" placeholder="Paste the folder URL"></div>

    <div class="ff ff-wide"><label>Deliverables / reports required after completion</label>
      <div class="checkgrid">
        <?php foreach (lk_options_or('deliverable', DELIVERABLES) as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="deliverables[]" value="<?= e($k) ?>" <?= in_array($k, $curDeliv, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Tick all report formats the client needs. Manage the list under <a href="/lookup?key=deliverable">Deliverables</a>.</small></div>

    <?php render_custom_fields('job', $cfvals ?? []); ?>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $job ? 'Save job' : 'Allocate & send email' ?></button>
    <a class="btn secondary" href="/call?id=<?= (int)$call['id'] ?>">Cancel</a>
  </div>
</form>
<script>window.ACTIVITY = <?= json_encode($act) ?>;</script>
