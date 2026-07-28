<?php $lbl = 'display:block;font-size:13px;color:var(--muted);margin:14px 0 5px'; ?>
<h2 class="ptitle">Report <?= e($d['irn']) ?><?= (int)$d['rev'] > 0 ? ' — Rev ' . (int)$d['rev'] : '' ?></h2>
<p class="plead"><?= e($d['title'] ?: '') ?></p>

<?php if (!empty($err)): ?><div class="pmsg err"><?= e($err) ?></div><?php endif; ?>

<?php if ($current): ?>
  <div class="pcard" style="max-width:640px">
    <p style="margin:0 0 8px">You have already
      <b><?= e(strtolower(RCR_DECISIONS[$current['decision']] ?? $current['decision'])) ?></b>
      this revision on <?= e(fdate(substr((string)$current['decided_at'], 0, 10))) ?>,
      recorded against <?= e($current['decided_by_name'] ?: $current['decided_by_email']) ?>.</p>
    <?php if (trim((string)$current['reason']) !== ''): ?>
      <p style="margin:0 0 8px"><b>Reason:</b> <?= e(RCR_REASONS[$current['reason']] ?? $current['reason']) ?></p>
    <?php endif; ?>
    <?php if (trim((string)$current['note']) !== ''): ?>
      <p style="margin:0;white-space:pre-wrap"><b>What you told us:</b><br><?= e($current['note']) ?></p>
    <?php endif; ?>
  </div>
  <p class="pnote">If this needs to change, ask us to re-issue the report — a new revision can be answered afresh.
    We do not overwrite a decision, because the record of what was said, by whom and when is the point of it.</p>
  <p style="margin-top:18px"><a href="/portal/reports">← Back to your reports</a></p>
<?php else: ?>

<p class="pnote">Please read the report before answering —
  <a href="/portal/report?id=<?= (int)$d['id'] ?>">open the PDF</a>.
  Your answer is recorded against this revision and against your name.</p>

<form method="post" action="/portal/report-decision" class="pcard" style="max-width:640px;margin-top:16px">
  <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

  <label style="<?= $lbl ?>;margin-top:0">Your decision *</label>
  <label style="display:flex;gap:9px;align-items:flex-start;margin:0 0 8px;font-size:14px">
    <input type="radio" name="decision" value="ACCEPTED" required>
    <span><b>Accept</b> — the report is satisfactory.</span></label>
  <label style="display:flex;gap:9px;align-items:flex-start;margin:0;font-size:14px">
    <input type="radio" name="decision" value="REJECTED">
    <span><b>Reject</b> — something needs to change before we can use it.</span></label>

  <label style="<?= $lbl ?>" for="rreason">If you are rejecting it, why?</label>
  <select class="form-control" id="rreason" name="reason">
    <option value="">— choose —</option>
    <?php foreach (RCR_REASONS as $k => $v): ?>
      <option value="<?= e($k) ?>"><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>

  <label style="<?= $lbl ?>" for="rnote">What should change? <span style="opacity:.75">(required if you are rejecting)</span></label>
  <textarea class="form-control" id="rnote" name="note" rows="5"
    placeholder="Be as specific as you can — the section, the page, what is wrong and what it should say."></textarea>

  <p class="pnote" style="margin-top:14px">Accepting a report records your opinion of it. It does not change any
    payment terms, release status or hold that may apply.</p>

  <button class="btn" type="submit" style="margin-top:14px">Record my decision</button>
  <a href="/portal/reports" style="margin-left:12px;font-size:14px">Cancel</a>
</form>

<?php endif; ?>

<?php if ($history): ?>
<h3 class="ptitle" style="font-size:16px;margin-top:30px">Every decision on this report</h3>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Rev</th><th>Decision</th><th>Reason</th><th>By</th><th>When</th></tr></thead>
  <tbody>
  <?php foreach ($history as $h): ?>
    <tr>
      <td>Rev <?= (int)$h['rev'] ?></td>
      <td><?= e(RCR_DECISIONS[$h['decision']] ?? $h['decision']) ?></td>
      <td><?= e($h['reason'] ? (RCR_REASONS[$h['reason']] ?? $h['reason']) : '—') ?></td>
      <td><?= e($h['decided_by_name'] ?: $h['decided_by_email']) ?></td>
      <td><?= e(fdate(substr((string)$h['decided_at'], 0, 10))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
