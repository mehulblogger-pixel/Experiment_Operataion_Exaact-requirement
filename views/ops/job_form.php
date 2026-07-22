<h1><?= $job ? 'Edit Job ' . e($job['job_code']) : 'Allocate Job' ?></h1>
<p class="sub">From call <strong><?= e($call['call_code']) ?></strong>. Expected credit is mandatory. The inspector gets an assignment email once a schedule date is set.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $job ? '/job-edit?id=' . (int)$job['id'] : '/job-new?call=' . (int)$call['id'] ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Executing office</label>
      <select class="form-control searchable" name="executing_office_id">
        <?php foreach ($offices as $o): $sel = $job ? $job['executing_office_id']==$o['id'] : $o['code']==='AHM'; ?>
          <option value="<?= (int)$o['id'] ?>" <?= $sel?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
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
      <?php if (!$boss): ?><small class="muted">No BOSS numbers for this client yet — add them under <a href="/m/boss/new">BOSS numbers</a>.</small><?php endif; ?></div>
    <div class="ff"><label>Scheduled date</label><input class="form-control" type="date" name="scheduled_date" value="<?= e($job['scheduled_date'] ?? '') ?>"></div>
    <div class="ff"><label>Inspection start</label><input class="form-control" type="date" name="inspection_start_date" value="<?= e($job['inspection_start_date'] ?? '') ?>"></div>
    <div class="ff"><label>Inspection end</label><input class="form-control" type="date" name="inspection_end_date" value="<?= e($job['inspection_end_date'] ?? '') ?>"></div>
    <div class="ff"><label>Random date 1</label><input class="form-control" type="date" name="random_date1" value="<?= e($job['random_date1'] ?? '') ?>"></div>
    <div class="ff"><label>Random date 2</label><input class="form-control" type="date" name="random_date2" value="<?= e($job['random_date2'] ?? '') ?>"></div>
    <div class="ff"><label>Random date 3</label><input class="form-control" type="date" name="random_date3" value="<?= e($job['random_date3'] ?? '') ?>"></div>
    <div class="ff"><label>Man-days (0 = auto from dates)</label><input class="form-control" type="number" step="0.5" name="mandays" value="<?= e($job['mandays'] ?? '0') ?>"></div>
    <div class="ff"><label>Expected credit (₹) *</label><input class="form-control" type="number" step="0.01" name="expected_credit" value="<?= e($job['expected_credit'] ?? '') ?>" required></div>
    <div class="ff"><label>Credit type</label>
      <select class="form-control searchable" name="credit_type"><?php foreach (lk_options_or('credit_type', CREDIT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($job && $job['credit_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Credit direction</label>
      <select class="form-control searchable" name="credit_direction"><?php foreach (lk_options_or('credit_direction', CREDIT_DIRECTIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($job && $job['credit_direction']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Reporting frequency</label>
      <select class="form-control searchable" name="reporting_frequency"><?php foreach (lk_options_or('reporting_frequency', REPORT_FREQ) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($job && $job['reporting_frequency']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>SBU</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($job && $job['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff ff-wide"><label>Report folder link (SharePoint / Drive)</label><input class="form-control" name="folder_link" value="<?= e($job['folder_link'] ?? '') ?>" placeholder="Paste the folder URL"></div>
    <?php render_custom_fields('job', $cfvals ?? []); ?>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $job ? 'Save job' : 'Allocate & send email' ?></button>
    <a class="btn secondary" href="/call?id=<?= (int)$call['id'] ?>">Cancel</a>
  </div>
</form>
