<h1>Close <?= e(Tl('job')) ?> <?= e($job['job_code']) ?></h1>
<p class="sub">Upload the report, enter the day's expenses (<?= e(Tl("sbu")) ?>-wise) and close. Expenses lock to this job automatically.</p>
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
  <div class="form-grid">
    <div class="ff"><label>Report upload date<?= $job['reporting_frequency']!=='NOREPORT'?' *':'' ?></label>
      <input class="form-control" type="date" name="report_upload_date" value="<?= e(date('Y-m-d')) ?>" <?= $job['reporting_frequency']!=='NOREPORT'?'required':'' ?>></div>
    <div class="ff ff-wide"><label>Report link (optional)</label><input class="form-control" name="report_link" placeholder="Link to the uploaded report"></div>
    <div class="ff"><label><?= e(T("sbu")) ?> (for these expenses)</label>
      <select class="form-control" name="sbu"><option value="">— same as job —</option><?php foreach (OPS_SBUS as $k=>$v): ?><option value="<?= $k ?>" <?= $job['sbu']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <?php $baseLbl = expense_heading_labels(); ?>
    <div class="ff"><label><?= e($baseLbl['travel']) ?> (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="travel" value="0"></div>
    <div class="ff"><label><?= e($baseLbl['local']) ?> (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="local" value="0"></div>
    <div class="ff"><label><?= e($baseLbl['food']) ?> (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="food" value="0"></div>
    <div class="ff"><label><?= e($baseLbl['lodging']) ?> (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="lodging" value="0"></div>
    <div class="ff"><label><?= e($baseLbl['misc']) ?> (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="misc" value="0"></div>
    <?php foreach (expense_extra_headings() as $code=>$label): ?>
      <div class="ff"><label><?= e($label) ?> (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="extra[<?= e($code) ?>]" value="0"></div>
    <?php endforeach; ?>
    <div class="ff ff-wide"><label>Expense notes</label><input class="form-control" name="exp_notes"></div>
  </div>
  <p class="muted" style="margin:2px;">Add or rename headings under <a href="/lookup?key=expense_heading">Expense headings</a> — new ones appear here automatically.</p>
  <div style="margin-top:16px;">
    <button class="btn" type="submit">Close job &amp; send closure email</button>
    <a class="btn secondary" href="/job?id=<?= (int)$job['id'] ?>">Cancel</a>
  </div>
</form>
