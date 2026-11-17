<?php
// Expenses Phase 1 (owner, 17 Nov 2026 — design v3, screens 3 and 4): the claim
// for a period. Lines grouped by date and job (job no., client, site, amounts),
// the total on top, what is unusual in amber, the chain preview, then ONE
// primary action for whoever is looking: the claimant submits, an approver
// approves or sends back, Finance marks it paid with the advance. Nobody but
// the claimant changes a line (V2). An old month voucher shows exactly its own
// figures, one card per day with each head as a line.
  $cs = cur_sym();
  $pillMap = ['DRAFT'=>['p-warn','Draft'],'SUBMITTED'=>['p-info','Waiting for approval'],'APPROVED'=>['p-ok','Approved'],'PAID'=>['p-ok','Paid']];
  $vpill = $pillMap[$v['status']] ?? ['p-warn', ucfirst(strtolower($v['status']))];
  $byDate = [];
  foreach ($entries as $e) $byDate[$e['entry_date']][] = $e;
  ksort($byDate);
  $monthHours = 0;
  $vGrand = (float)($sum['grand'] ?? 0);
  $vBal = $vGrand - (float)$v['advance'] - (float)$v['office_incurred'];
  $workDays = []; foreach ($entries as $e) if (($e['day_type'] ?? 'WORK') === 'WORK') $workDays[$e['entry_date']] = 1;
  $lv = $progress['levels'] ?? []; $curIdx = $progress['current'] ?? null;
  $isOwner = !empty($isOwner); $legacyMonth = !empty($legacyMonth);
  $iWaiting = $v['status'] === 'SUBMITTED' && !empty($canApproveThis);
  $periodTxt = exp_period_label($v);
  $statusLine = $v['status'] === 'DRAFT' ? ($canSubmit ? 'Ready to submit' : 'Draft') : ($iWaiting ? 'Waiting for you' : $vpill[1]);
  $chainTxt = implode(' → ', array_map(fn($l) => $l['short'], $lv));
  $fmt1 = fn($n) => rtrim(rtrim(number_format((float)$n, 1, '.', ''), '0'), '.');
  $warnIds = []; foreach (($warnings ?? []) as $w) $warnIds[(int)$w['eid']] = true;
?>
<div class="master-head">
  <div><h1><?= e($periodTxt) ?> <span class="pill <?= $vpill[0] ?>" style="vertical-align:middle;font-size:12px"><?= e($vpill[1]) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><strong><?= e($v['inspector_name']) ?></strong><?= $v['emp_code'] ? ' · ' . e($v['emp_code']) : '' ?>
      · <?= count($workDays) ?> day<?= count($workDays) === 1 ? '' : 's' ?> worked · <?= e(exp_period_kind_label($v)) ?></p></div>
  <a class="btn secondary" href="<?= e(nav_back($cur)) ?>">← <?= e(nav_back_label($cur)) ?></a>
</div>

<div class="vc-top">
  <div class="vc-total"><?= e($cs . nf($vGrand, 0)) ?></div>
  <div class="vc-status"><?= e($statusLine) ?><?php if ((float)$v['advance'] > 0 || (float)$v['office_incurred'] > 0): ?> · <?= $vBal < 0 ? 'to recover' : 'to pay' ?> <?= e($cs . nf(abs($vBal), 0)) ?><?php endif; ?></div>
</div>

<?php if ($v['status'] === 'DRAFT' && trim((string)($v['sent_back_reason'] ?? '')) !== ''): ?>
  <div class="vchain-note bad" role="status">
    <strong>Sent back<?= ($v['sent_back_by'] ?? '') !== '' ? ' by ' . e($v['sent_back_by']) : '' ?><?= ($v['sent_back_at'] ?? '') !== '' ? ' on ' . e(fdate(substr((string)$v['sent_back_at'], 0, 10))) : '' ?>:</strong>
    <?= e($v['sent_back_reason']) ?><br><span class="muted">Correct it and submit again — it goes back to the first approver.</span>
  </div>
<?php endif; ?>

<?php // Locks: the month freeze (existing) and the two deadlines (E4). ?>
<?php if ($v['status'] === 'DRAFT' && ($lr = voucher_lock_reason($v)) !== ''): ?>
  <div class="vchain-note warn" role="status">🔒 <?= e($lr) ?></div>
<?php elseif (!empty($claimLock) && $isOwner): ?>
  <div class="vchain-note warn" role="status">🔒 <?= e($claimLock) ?></div>
<?php endif; ?>
<?php if (!empty($canAskReopen)): ?>
  <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="vchain-note warn" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="action" value="ask_reopen">
    <div style="flex:1;min-width:220px"><strong>A deadline has passed for <?= empty($claimLock) ? 'some days of ' : '' ?>this <?= e(Tl('voucher')) ?>.</strong>
      <?= !empty($n1) ? 'Ask ' . e(vappr_name($n1)) . ' (your N+1) to reopen it.' : 'You have no "Reports to" on your record — ask an administrator to fill it in first.' ?>
      <input class="form-control" name="reason" placeholder="why it is late (optional)" style="margin-top:6px"></div>
    <?php if (!empty($n1)): ?><button class="btn secondary" type="submit">Ask to reopen</button><?php endif; ?>
  </form>
<?php elseif ($isOwner && trim((string)($v['reopen_asked_at'] ?? '')) !== '' && $v['status'] === 'DRAFT'): ?>
  <div class="vchain-note warn" role="status">You asked <?= !empty($n1) ? e(vappr_name($n1)) : 'your N+1' ?> to reopen it on <?= e(fdate(substr((string)$v['reopen_asked_at'], 0, 10))) ?>.</div>
<?php endif; ?>
<?php if (!empty($canDeadlineReopen)): ?>
  <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="vchain-note warn" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="action" value="deadline_reopen">
    <div style="flex:1;min-width:220px"><strong><?= e($v['inspector_name']) ?> missed a deadline on this <?= e(Tl('voucher')) ?><?= trim((string)($v['reopen_asked_at'] ?? '')) !== '' ? ' and asked you to reopen it' : '' ?>.</strong>
      As their N+1 you may reopen it for <?= (int)max(1, $rules['claim_days']) ?> days. Your reason is kept in History.
      <input class="form-control" name="reason" required placeholder="reason for reopening" style="margin-top:6px"></div>
    <button class="btn secondary" type="submit">Reopen for <?= e($v['inspector_name']) ?></button>
  </form>
<?php endif; ?>

<?php if (!empty($warnings)): ?>
  <div class="vc-warn" role="status">
    <?php foreach ($warnings as $w): ?><div>⚠ <?= e($w['text']) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($isOwner && !empty($submitProblems)): ?>
  <div class="vc-warn" role="status"><strong>Before you submit:</strong>
    <?php foreach ($submitProblems as $p): ?><div>• <?= e($p) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if (!empty($openDays)): ?>
  <div class="panel vc-open">
    <strong>Days you worked that still need their expenses confirmed</strong>
    <?php foreach ($openDays as $od): ?>
      <div class="vc-openrow"><span><?= e(date('j M', strtotime($od['date']))) ?> · <?= e($od['job']['job_code']) ?> · <?= e(exp_job_site($od['job'])) ?></span>
        <a class="btn small" href="/voucher-day?job=<?= (int)$od['job']['id'] ?>&amp;date=<?= e($od['date']) ?>">Confirm expenses</a></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="vc-days">
<?php foreach ($groups as $g):
  $gj = $g['job_id'] ? exp_job($g['job_id']) : null;
  $dayLocked = $canEdit && exp_day_lock($v, $g['date']) !== '';
  $mayChange = $canEdit && !$dayLocked;
?>
  <div class="vc-day">
    <div class="vc-dayhead">
      <span><strong><?= e(date('j M', strtotime($g['date']))) ?></strong>
        <?php if ($gj): ?> · <strong><?= e($gj['job_code']) ?></strong>
        <?php elseif (!$g['work']): ?> · <?= e($g['day_type'] === 'LEAVE' ? 'Leave' : ($g['day_type'] === 'OFFICE' ? 'In office' : ucfirst(strtolower($g['day_type'])))) ?>
        <?php else: ?> · Other expenses<?php endif; ?></span>
      <strong><?= e($cs . nf($g['total'], 0)) ?></strong>
    </div>
    <?php if ($gj): ?><div class="vc-daysub"><?= e(exp_job_client($gj) ?: '—') ?><?= exp_job_site($gj) !== exp_job_client($gj) ? ' · ' . e(exp_job_site($gj)) : '' ?></div><?php endif; ?>
    <ul class="vc-lines">
    <?php foreach ($g['lines'] as $e):
      $parts = exp_line_parts($e, $headLabels);
      $h = exp_head((string)($e['head_code'] ?? ''));
      $nobill = $h && exp_kind($h) === 'NOBILL';
      $isBill = $h && in_array(exp_kind($h), ['BILL', 'NOBILL'], true);
      $monthHours += (float)$e['hours'];
    ?>
      <li class="<?= isset($warnIds[(int)$e['id']]) ? 'vc-amber' : '' ?>">
        <span class="vc-what">
          <?php if (!$parts): ?><span class="muted"><?= $g['work'] ? e(trim((string)$e['notes']) !== '' ? $e['notes'] : 'Day worked — no expenses') : e(trim((string)($e['leave_code'] ?: $e['office_code'] ?: '')) ?: 'No expenses') ?></span><?php endif; ?>
          <?php foreach ($parts as $i => $pt): ?><?= $i ? ' · ' : '' ?><?= e($pt['label']) ?> <strong><?= e($cs . nf($pt['amount'], 0)) ?></strong><?php endforeach; ?>
          <?php if ($nobill): ?> <span class="pill p-mut">no bill</span><?php endif; ?>
          <?php if ((int)($e['ticked'] ?? 0) === 1): ?> <span class="pill p-mut">ticked</span><?php endif; ?>
          <?php if (!empty($e['receipt_data'])): ?> <a href="/voucher-line-receipt?id=<?= (int)$e['id'] ?>" target="_blank" rel="noopener">📎 view</a><?php elseif ($h && exp_needs_photo($h)): ?> <span class="pill p-warn">no photo</span><?php endif; ?>
          <?php if (trim((string)($e['km_reason'] ?? '')) !== ''): ?><br><span class="vc-note">Reason for the distance: “<?= e($e['km_reason']) ?>”</span><?php endif; ?>
          <?php if ($parts && trim((string)$e['notes']) !== '' && $e['head_code'] !== 'KMTRAVEL'): ?><br><span class="vc-note"><?= e($e['notes']) ?></span><?php endif; ?>
        </span>
        <?php if ($mayChange): ?>
          <span class="vc-act">
            <?php if ($isBill): ?>
              <details class="vc-edit"><summary class="btn small secondary">Change</summary>
                <form method="post" action="/voucher-line" enctype="multipart/form-data" class="vc-editform">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><input type="hidden" name="_do" value="update">
                  <label>Amount (<?= e($cs) ?>)<input class="form-control" type="number" step="0.01" min="0" name="amount" value="<?= e((string)(float)$e['row_total']) ?>" required></label>
                  <label>Note<input class="form-control" name="note" value="<?= e($e['notes']) ?>"></label>
                  <?php if (!$nobill): ?><label>Photo of the bill<input class="form-control" type="file" name="receipt" accept="image/*,.pdf" capture="environment"></label><?php endif; ?>
                  <button class="btn small" type="submit">Save</button>
                </form></details>
            <?php endif; ?>
            <form method="post" action="/voucher-line" onsubmit="return confirm('Remove this line?')" style="display:inline"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><input type="hidden" name="_do" value="del"><button class="btn small danger" type="submit" aria-label="Remove this line">✕</button></form>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
    <?php if ($gj && $mayChange && $isOwner): ?>
      <div class="vc-dayfoot"><a class="btn small secondary" href="/voucher-day?job=<?= (int)$gj['id'] ?>&amp;date=<?= e($g['date']) ?>">Change the day / ＋ add a bill</a></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$groups): ?>
  <div class="panel muted" style="text-align:center;padding:24px">Nothing here yet. <?= $isOwner ? 'When you close a day on your ' . e(Tl('job')) . ', its expenses appear here by themselves.' : '' ?></div>
<?php endif; ?>
</div>

<?php if ($canEdit): ?>
<?php // "Not for a job" types (E5) — only those switched on for this person. ?>
<details class="panel" id="quick-add" <?= !empty($nonJob) ? '' : 'style="opacity:.9"' ?>>
  <summary style="font-weight:700;cursor:pointer;min-height:44px;display:flex;align-items:center">＋ Add an expense not for a <?= e(Tl('job')) ?></summary>
  <?php if (!empty($nonJob)): ?>
  <form method="post" action="/voucher-quick-add" enctype="multipart/form-data" class="inline-add" style="align-items:flex-end;margin-top:8px">
    <div class="ff"><label>What for *</label>
      <select class="form-control" name="head" required><?php foreach ($nonJob as $h): ?><option value="<?= e($h['code']) ?>"><?= e($h['label']) ?><?= exp_kind($h) === 'NOBILL' ? ' (no bill)' : '' ?><?= $h['_limit'] !== null ? ' — limit ' . e($cs . nf((float)$h['_limit'], 0)) : '' ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Amount (<?= e($cs) ?>) *</label><input class="form-control" style="width:120px" type="number" step="0.01" min="0" name="amount" required></div>
    <div class="ff"><label>Date</label><input class="form-control" type="date" name="entry_date" value="<?= e(max($period[0], min($period[1], date('Y-m-d')))) ?>" min="<?= e($period[0]) ?>" max="<?= e($period[1]) ?>"></div>
    <div class="ff"><label>Photo of the bill</label><input class="form-control" type="file" name="receipt" accept="image/*,.pdf" capture="environment"></div>
    <div class="ff ff-wide"><label>Note <span class="muted">— needed for a "no bill" expense</span></label><input class="form-control" name="note" placeholder="what it was for"></div>
    <button class="btn" type="submit">Add expense</button>
  </form>
  <?php else: ?>
    <p class="muted" style="margin:8px 0 0">Add an expense: expenses on a <?= e(Tl('job')) ?> are added when you close the day on the <?= e(Tl('job')) ?>. No "not for a <?= e(Tl('job')) ?>" type is switched on for you — ask the Master Admin if you need one.</p>
  <?php endif; ?>
</details>
<?php endif; ?>

<?php // ---- The one action for this viewer ---- ?>
<div class="panel vc-act-panel">
  <?php if ($v['status'] === 'SUBMITTED' && $curIdx !== null): ?>
    <p class="vchain-wait"><strong>Waiting for:</strong> <?= e(vappr_waiting_text($progress)) ?><?= $iWaiting ? ' <span class="pill p-warn">your turn</span>' : '' ?></p>
    <?php if (($lv[$curIdx]['problem'] ?? '') !== ''): ?><div class="vchain-note warn" role="status">⚠ <?= e($lv[$curIdx]['problem']) ?></div><?php endif; ?>
  <?php elseif ($v['status'] === 'DRAFT' && $lv): ?>
    <p class="vchain-wait">It will go to: <strong><?= e($chainTxt) ?></strong></p>
  <?php endif; ?>
  <?php if ($lv && $v['status'] !== 'DRAFT'): ?>
    <ol class="vchain" aria-label="Approval chain">
      <?php foreach ($lv as $i => $l): $d = $l['done'] ?? null; ?>
        <li class="<?= $d ? 'done' : ($i === $curIdx ? 'now' : '') ?>">
          <span class="vchain-dot" aria-hidden="true"><?= $d ? '✓' : ($i === $curIdx ? '●' : '○') ?></span>
          <span><strong><?= e($l['label']) ?></strong>
            <?php if ($d && !empty($l['auto'])): ?> — <?= e($d['user_name']) ?>, <span class="muted">already signed<?= ($d['via'] ?? '') !== '' ? ' at ' . e($d['via']) : ' at an earlier level' ?> — not asked twice</span>
            <?php elseif ($d): ?> — <?= e($d['user_name']) ?><?= ($d['on_behalf_name'] ?? '') !== '' ? ' for ' . e($d['on_behalf_name']) : '' ?>, <?= e(fdate(substr((string)$d['created_at'], 0, 10))) ?>
            <?php elseif ($l['approvers']): ?> — <?= e(implode(', ', array_slice(array_map('vappr_name', $l['approvers']), 0, 4))) ?><?= count($l['approvers']) > 4 ? ' …' : '' ?>
            <?php else: ?> — <span class="muted">an administrator</span><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
  <div class="row-actions" style="flex-wrap:wrap;gap:8px">
    <?php if (!empty($canSubmit)): ?>
      <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="submit"><button class="btn vc-primary" type="submit" <?= !empty($submitProblems) ? 'disabled title="Add what is missing first"' : '' ?>>Submit <?= e($cs . nf($vGrand, 0)) ?></button></form>
    <?php endif; ?>
    <?php if ($iWaiting): ?>
      <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
        <input type="hidden" name="action" value="approve">
        <div class="ff"><label>Note <span class="muted">— optional</span></label><input class="form-control" name="note"></div>
        <button class="btn vc-primary" type="submit">Approve <?= e($cs . nf($vGrand, 0)) ?><?= !empty($act['on_behalf']) ? ' for ' . e(vappr_name(vappr_user($act['on_behalf']))) : '' ?></button>
      </form>
      <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
        <input type="hidden" name="action" value="sendback">
        <div class="ff"><label>Reason for sending back *</label><input class="form-control" name="reason" required placeholder="e.g. hotel bill for 12 Nov missing"></div>
        <button class="btn secondary" type="submit">Send back with a reason</button>
      </form>
    <?php elseif ($v['status']==='SUBMITTED' && !voucher_owner_is_me($v) && (int)($v['submitted_by'] ?? 0) === (int)(current_user()['id'] ?? 0)): ?>
      <p class="muted" style="margin:0;font-size:12.5px">Awaiting approval — it must be approved by someone other than the person who submitted it (maker&nbsp;≠&nbsp;checker).</p>
    <?php endif; ?>
    <?php if (!empty($canPaid)): ?>
      <?php // E6 — Finance enters the advance when it marks the claim paid. ?>
      <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" class="inline-add" style="align-items:flex-end">
        <input type="hidden" name="action" value="paid">
        <div class="ff"><label>Advance already paid (<?= e($cs) ?>)</label><input class="form-control" style="width:140px" type="number" step="0.01" min="0" name="advance" value="<?= (float)$v['advance'] > 0 ? e((string)(float)$v['advance']) : '' ?>" placeholder="0"></div>
        <button class="btn vc-primary" type="submit">Mark paid</button>
      </form>
    <?php endif; ?>
    <?php if (!empty($canReopen)): ?>
      <form method="post" action="/voucher-status?id=<?= (int)$v['id'] ?>" onsubmit="return confirm('Reopen for editing?')"><input type="hidden" name="action" value="reopen"><button class="btn secondary" type="submit">Reopen</button></form>
    <?php endif; ?>
  </div>
</div>

<details class="panel">
  <summary style="font-weight:700;cursor:pointer">Summary, print and download</summary>
  <table class="grid" style="margin-top:8px">
    <tr><th>Particular</th><th style="text-align:right">Amount</th></tr>
    <tr><td>Travel charges (KM × rate)</td><td style="text-align:right"><?= e($cs) ?><?= nf($sum['travel'],0) ?></td></tr>
    <?php foreach ($sum['heads'] as $code=>$amt): if ($amt==0) continue; ?>
      <tr><td><?= e($headLabels[$code] ?? $code) ?></td><td style="text-align:right"><?= e($cs) ?><?= nf($amt,0) ?></td></tr>
    <?php endforeach; ?>
    <tr style="background:var(--soft)"><td><strong>Grand Total</strong></td><td style="text-align:right"><strong><?= e($cs) ?><?= nf($sum['grand'],0) ?></strong></td></tr>
    <tr><td>Less: Advance <span class="muted">(entered by Finance when paid)</span></td><td style="text-align:right"><?= e($cs) ?><?= nf((float)$v['advance'],0) ?></td></tr>
    <tr><td>Less: Expenses incurred by Office</td><td style="text-align:right"><?= e($cs) ?><?= nf((float)$v['office_incurred'],0) ?></td></tr>
    <tr style="background:var(--soft)"><td><strong>Balance to be paid / (recovered)</strong></td><td style="text-align:right"><strong><?= e($cs) ?><?= nf($vBal,0) ?></strong></td></tr>
  </table>
  <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn secondary" href="/voucher-print?id=<?= (int)$v['id'] ?>" target="_blank">🖨 Print / Save PDF</a>
    <a class="btn secondary" href="/voucher-csv?id=<?= (int)$v['id'] ?>">⬇ Download (Excel/CSV)</a>
  </div>
  <?php if ($canEdit): ?>
  <form method="post" action="/voucher-header?id=<?= (int)$v['id'] ?>" enctype="multipart/form-data" style="margin-top:12px">
    <div class="form-grid">
      <div class="ff"><label>Nature of spend</label>
        <select class="form-control" name="nature"><option value="">—</option><?php foreach ($natureOpts as $k=>$vv): ?><option value="<?= e($k) ?>" <?= $v['nature']===$k?'selected':'' ?>><?= e($vv) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Less Office-incurred (<?= e($cs) ?>)</label><input class="form-control" type="number" step="0.01" name="office_incurred" value="<?= e($v['office_incurred']) ?>"></div>
      <div class="ff ff-wide"><label>Supporting file — one file for all bills (PDF/JPG, max 6 MB)</label><input class="form-control" type="file" name="support"></div>
    </div>
    <div style="margin-top:8px"><button class="btn small secondary" type="submit">Save details / upload</button></div>
  </form>
  <?php else: ?>
    <p class="muted" style="margin:8px 0 0">Nature: <?= e($natureOpts[$v['nature']] ?? $v['nature'] ?: '—') ?></p>
  <?php endif; ?>
  <?php if ($v['supporting_file']): ?><p style="margin-top:8px">📎 Supporting: <a href="/voucher-file?id=<?= (int)$v['id'] ?>" target="_blank"><?= e($v['supporting_name'] ?: 'file') ?></a></p><?php endif; ?>
</details>

<?php if ($legacyMonth && $canEdit): ?>
<details class="panel">
  <summary style="font-weight:700;cursor:pointer">The month sheet (older <?= e(Tlp('voucher')) ?>) — hours, days off and amounts per head</summary>
  <?php $monthHours = 0; require __DIR__ . '/voucher_grid.php'; ?>
</details>
<?php endif; ?>

<details class="panel" id="history">
  <summary style="font-weight:700;cursor:pointer">History</summary>
  <?php if (empty($history)): ?>
    <p class="muted" style="margin:8px 0 0">Nothing yet. Each submission, approval, send-back, reopen and payment is recorded here — who, at which level, when and why.</p>
  <?php else: ?>
    <?php $verb = ['SUBMITTED' => 'Submitted', 'APPROVED' => 'Approved', 'SENT_BACK' => 'Sent back', 'REOPENED' => 'Reopened', 'PAID' => 'Marked paid', 'ALREADY_SIGNED' => 'Counted as signed', 'REOPEN_ASKED' => 'Asked to reopen']; ?>
    <ul class="vhist">
      <?php foreach (array_reverse($history) as $h): ?>
        <li><span class="muted"><?= e(fdate(substr((string)$h['created_at'], 0, 10))) ?> <?= e(substr((string)$h['created_at'], 11, 5)) ?></span>
          <strong><?= e($verb[$h['action']] ?? $h['action']) ?></strong>
          <?= ($h['level_label'] ?? '') !== '' ? '· ' . e($h['level_label']) : '' ?>
          · <?= e($h['user_name']) ?><?= ($h['on_behalf_name'] ?? '') !== '' ? ' for ' . e($h['on_behalf_name']) : '' ?>
          <?php if (trim((string)($h['reason'] ?? '')) !== ''): ?><div class="vhist-why">“<?= e($h['reason']) ?>”</div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</details>
<style>
  .vc-top{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;margin:10px 0 12px}
  .vc-total{font-size:34px;font-weight:800;font-variant-numeric:tabular-nums}
  .vc-status{font-size:16px;color:var(--muted)}
  .vc-warn{background:var(--warn-soft,#fff7e6);border-left:4px solid var(--warn,#b45309);border-radius:8px;padding:10px 12px;margin:8px 0;font-size:15px;line-height:1.5}
  .vc-open .vc-openrow{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line)}
  .vc-days{display:grid;gap:10px;margin:10px 0}
  .vc-day{border:1px solid var(--line);border-radius:12px;background:var(--card);padding:10px 14px}
  .vc-dayhead{display:flex;justify-content:space-between;gap:10px;font-size:16px}
  .vc-daysub{color:var(--muted);font-size:14px;margin-top:2px}
  .vc-lines{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:4px}
  .vc-lines li{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;padding:6px 0;border-top:1px dashed var(--line);font-size:15px}
  .vc-lines li.vc-amber{background:var(--warn-soft,#fff7e6);border-left:3px solid var(--warn,#b45309);padding-left:8px;border-radius:4px}
  .vc-note{font-size:13px;color:var(--muted)}
  .vc-act{display:flex;gap:6px;align-items:flex-start;flex:none}
  .vc-edit summary{list-style:none;display:inline-block} .vc-edit summary::-webkit-details-marker{display:none}
  .vc-editform{display:grid;gap:6px;margin-top:6px;min-width:220px}
  .vc-dayfoot{margin-top:8px}
  .vc-primary{min-height:48px;font-size:16px;padding-left:22px;padding-right:22px}
  .vchain{list-style:none;margin:8px 0 10px;padding:0;display:grid;gap:6px;font-size:13px}
  .vchain li{display:flex;gap:8px;align-items:flex-start}
  .vchain-dot{width:18px;flex:none;text-align:center;color:var(--muted,#6b7280)}
  .vchain li.done .vchain-dot{color:var(--ok,#15803d)} .vchain li.now{font-weight:600} .vchain li.now .vchain-dot{color:var(--accent,#2b6cff)}
  .vchain-wait{margin:6px 0;font-size:15px}
  .vchain-note{border-radius:8px;padding:8px 10px;margin:8px 0;font-size:14px;line-height:1.5}
  .vchain-note.bad{background:var(--bad-soft,#fdecec);border-left:3px solid var(--bad,#c62828)}
  .vchain-note.warn{background:var(--warn-soft,#fff7e6);border-left:3px solid var(--warn,#b45309)}
  .vhist{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:8px;font-size:13px}
  .vhist li{border-bottom:1px solid var(--line,#eee);padding-bottom:6px}
  .vhist-why{margin:2px 0 0 0;color:var(--ink,#28313f)}
  @media (max-width:560px){ .vc-lines li{flex-direction:column} .vc-act{align-self:flex-end} .vc-primary{width:100%} }
</style>
