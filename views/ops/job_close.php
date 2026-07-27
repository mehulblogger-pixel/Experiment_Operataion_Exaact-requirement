<h1>Close <?= e(Tl('job')) ?> <?= e($job['job_code']) ?></h1>
<p class="sub">Upload the report, enter the day's expenses (<?= e(Tl("sbu")) ?>-wise) and close. Expenses lock to this job automatically.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/job-close?id=<?= (int)$job['id'] ?>" class="panel">
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
