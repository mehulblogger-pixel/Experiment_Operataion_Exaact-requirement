<?php // Expenses Phase 1 — "Close the day" (design v3, screen 1). The engineer on
      // the job confirms the day's expenses here; they land in their own claim.
      // Anyone else closing it (a coordinator) records no expenses for them (V2).
      $expIns = (int)my_inspector_id();
      $expJob = exp_job((int)$job['id']);
      $expMine = $expIns && exp_job_is_mine($expIns, $expJob);
      $expDate = $expMine ? exp_close_default_date($expIns, $expJob) : '';
      $expClaim = $expMine ? exp_claim_for($expIns, $expDate, false) : null;
      $expLock = $expMine ? ($expClaim ? exp_line_change_problem($expClaim, $expDate) : exp_day_lock(['status' => 'DRAFT'], $expDate)) : '';
      $plan = $expMine ? exp_day_plan($expIns, $expJob, $expDate) : null; ?>
<h1><?= e($job['job_code']) ?><?= $expMine ? ' · ' . e(date('j M', strtotime($expDate))) : '' ?></h1>
<p class="sub"><?= e(exp_job_client($expJob) ?: '') ?><?= $expJob && exp_job_site($expJob) !== exp_job_client($expJob) ? ' · ' . e(exp_job_site($expJob)) : '' ?>
  <?= $expMine ? '— close the day and confirm its expenses. They go into your ' . e(Tl('voucher')) . ' by themselves.' : '— upload the report and close. The ' . e(Tl('engineer')) . ' confirms the day\'s expenses on their own ' . e(Tl('voucher')) . '.' ?></p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
<?php // Stated before the form: the bills are what the client is charged on.
      // Each outstanding head can be resolved two ways — upload its bill, or, if
      // that expense simply did not happen on this job, mark it "not incurred".
      $missing = function_exists('job_bills_missing') ? job_bills_missing($job) : []; ?>
<?php if ($missing): ?>
<div class="panel" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
  <b style="color:var(--warn)">◷ Chargeable expenses to settle</b>
  <div class="muted" style="margin-top:4px">The client agreed to be charged for the items below. For each, either
    <a href="/job?id=<?= (int)$job['id'] ?>#bills">upload its bill</a>, or tick it as <b>not incurred on this job</b> in the
    form below — then close.</div>
</div>
<?php endif; ?>
<?php // Module 21 — do not let an inspection be closed by accident while a hold /
      // witness point is still open. Warns loudly (does not block — clearing/waiving
      // is done on the job screen). ?>
<?php $hwSum = function_exists('hwp_job_summary') ? hwp_job_summary((int)$job['id']) : ['open'=>0,'label'=>'']; ?>
<?php if (!empty($hwSum['open'])): ?>
<div class="panel" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 7%,transparent)">
  <b style="color:var(--bad)">✋ <?= (int)$hwSum['open'] ?> open hold / witness point<?= $hwSum['open']===1?'':'s' ?></b>
  <span class="muted">(<?= e($hwSum['label']) ?>)</span>
  <div class="muted" style="margin-top:4px">The manufacturer should not proceed / despatch until these are cleared or waived.
    Closing the <?= e(Tl('job')) ?> now does not clear them — settle them on the
    <a href="/job?id=<?= (int)$job['id'] ?>#holdpoints">hold &amp; witness panel</a> first if the work is genuinely blocked.</div>
</div>
<?php endif; ?>
<form method="post" action="/job-close?id=<?= (int)$job['id'] ?>" class="panel">
  <?php if (!empty($needAttendanceReason)): // §WO-9 — manager approving a close with a missing site check-in ?>
  <div class="ff ff-wide" style="margin-bottom:12px;border:1px solid var(--bad);border-radius:8px;padding:12px;background:var(--soft)">
    <label style="color:var(--bad)">Manager approval — missing <?= e(implode(' and ', $attMiss ?? [])) ?></label>
    <textarea class="form-control" name="attendance_override_reason" rows="2" required placeholder="Why is the site check-in missing? (e.g. phones not allowed inside the plant)"></textarea>
    <small class="muted">Recorded on the <?= e(Tl('job')) ?> and counted against the <?= e(Tl('engineer')) ?>'s site-attendance rating.</small>
  </div>
  <?php endif; ?>
  <?php if ($missing): ?>
  <div class="ff ff-wide" style="margin-bottom:12px">
    <label>Not incurred on this job <span class="muted">— tick any the client agreed to pay but that did not happen here</span></label>
    <div class="chip-row" style="margin-top:6px">
      <?php foreach ($missing as $code => $label): ?>
        <label class="ff-check"><input type="checkbox" name="nil_heads[]" value="<?= e($code) ?>"> <?= e($label) ?> — none this job</label>
      <?php endforeach; ?>
    </div>
    <small class="muted">A ticked item needs no bill; the decision is recorded on the job. An unticked item still needs its bill uploaded before closing.</small>
  </div>
  <?php endif; ?>
  <?php // Owner, 27 Nov 2026 (decision 3, step one): no report on file → warn, and ask why.
        if (function_exists('job_noreport_migrate')) job_noreport_migrate();
        $noRepNeed = function_exists('job_close_needs_report_reason') && job_close_needs_report_reason($job); ?>
  <?php if ($noRepNeed): ?>
  <div class="ff ff-wide" id="noreport-box" style="margin-bottom:12px;border:1px solid var(--warn);border-radius:8px;padding:12px;background:color-mix(in srgb,var(--warn) 7%,transparent)">
    <label for="no_report_reason" style="color:var(--warn)">⚠ No report is on file for this <?= e(Tl('job')) ?></label>
    <p class="muted" style="margin:4px 0 8px">No report has been written or uploaded on it. If the report exists, paste its link below.
      To close it without one, say why — the reason is kept in its History, and a report added later is recorded and the Operations Manager is told.</p>
    <textarea class="form-control" id="no_report_reason" name="no_report_reason" rows="2" minlength="<?= (int)JOB_NOREPORT_REASON_MIN ?>"
      placeholder="e.g. Client cancelled the inspection on site — no report to issue"><?= e((string)($_POST['no_report_reason'] ?? '')) ?></textarea>
  </div>
  <?php endif; ?>
  <div class="form-grid">
    <div class="ff"><label>Report upload date<?= $job['reporting_frequency']!=='NOREPORT' && !$noRepNeed ?' *':'' ?></label>
      <input class="form-control" type="date" name="report_upload_date" value="<?= e(date('Y-m-d')) ?>" <?= $job['reporting_frequency']!=='NOREPORT' && !$noRepNeed ?'required':'' ?>></div>
    <div class="ff ff-wide"><label>Report link (optional)</label><input class="form-control" name="report_link" placeholder="Link to the uploaded report" value="<?= e((string)($_POST['report_link'] ?? '')) ?>"></div>
  </div>
  <?php if ($expMine): ?>
    <h3 class="tab-sub" style="margin:14px 0 6px"><?= $expDate === date('Y-m-d') ? 'Today\'s expenses' : 'Expenses for ' . e(fdate($expDate)) ?></h3>
    <?php if ($expLock !== ''): ?>
      <div class="msg msg-error"><?= e($expLock) ?></div>
    <?php else: ?>
      <?php $job_ = $job; $job = $expJob; $date = $expDate; require __DIR__ . '/_exp_day_fields.php'; $job = $job_; ?>
      <p class="muted" style="margin:6px 2px 0;font-size:13px">A bill (ticket, hotel, cab)? Add it on the next screen, with its photo.</p>
    <?php endif; ?>
  <?php endif; ?>
  <div style="margin-top:16px;">
    <button class="btn" type="submit" style="min-height:48px"><?= $expMine ? 'Close the day' : 'Close ' . e(Tl('job')) . ' &amp; send closure email' ?></button>
    <a class="btn secondary" href="/job?id=<?= (int)$job['id'] ?>">Cancel</a>
  </div>
  <?php if ($expMine): ?><p class="muted" style="margin:8px 2px 0;font-size:13px">No expenses today? Close the day anyway.</p><?php endif; ?>
</form>
