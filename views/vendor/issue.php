<p class="pnote" style="margin:0 0 10px"><a href="/vendor/issues">← All nonconformities</a></p>
<h2 class="ptitle"><?= e($n['ref'] ?: ('Finding #' . (int)$n['id'])) ?></h2>

<div class="pcard">
  <div style="font-size:15px;font-weight:600;margin-bottom:6px"><?= e($n['title'] ?: 'Finding') ?></div>
  <?php if (!empty($n['description'])): ?>
    <div class="pnote" style="white-space:pre-wrap;margin-bottom:10px"><?= e($n['description']) ?></div>
  <?php endif; ?>
  <div class="pnote">
    <?php if (!empty($n['clause'])): ?>Clause: <?= e($n['clause']) ?> · <?php endif; ?>
    Severity: <?= e($n['severity'] ?: '—') ?> ·
    Raised: <?= e(fdate($n['detected_on'])) ?>
    <?php if (!empty($n['due_on'])): ?> · Response due: <?= e(fdate($n['due_on'])) ?><?php endif; ?>
    · State: <?= e(ucfirst(strtolower((string)$n['status']))) ?>
  </div>
</div>

<?php if (!empty($err)): ?><div class="pmsg err"><?= e($err) ?></div><?php endif; ?>

<?php if ((string)$n['status'] !== 'CLOSED'): ?>
<h3 class="ptitle" style="font-size:16px;margin-top:22px">Your response</h3>
<form method="post" action="/vendor/issue" class="pcard" style="max-width:640px">
  <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
  <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">How are you responding?</label>
  <select class="form-control" name="response_kind" required>
    <option value="">Choose…</option>
    <?php foreach ($kinds as $k => $label): ?>
      <option value="<?= e($k) ?>"><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Your response</label>
  <textarea class="form-control" name="response" rows="3" required placeholder="What you accept, dispute or will do."></textarea>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Root cause <span style="opacity:.7">(if known)</span></label>
  <textarea class="form-control" name="reason" rows="2" placeholder="Why it happened."></textarea>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Proposed corrective action <span style="opacity:.7">(optional)</span></label>
  <textarea class="form-control" name="proposed_action" rows="2" placeholder="What you will do to correct and prevent recurrence."></textarea>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Evidence reference <span style="opacity:.7">(optional)</span></label>
  <input class="form-control" name="evidence_ref" maxlength="300" placeholder="e.g. CAR-2026-014, inspection photo set, test certificate no.">
  <div style="margin-top:16px"><button class="btn" type="submit">Send your response</button></div>
</form>
<?php else: ?>
  <div class="pmsg ok">This finding is closed. The responses below are kept for the record.</div>
<?php endif; ?>

<h3 class="ptitle" style="font-size:16px;margin-top:24px">Your responses so far</h3>
<?php if (!$responses): ?>
  <p class="pempty">You have not responded yet.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>When</th><th>Kind</th><th>Response</th><th>Proposed action</th><th>Evidence</th><th>Our decision</th></tr></thead>
  <tbody>
  <?php foreach ($responses as $d): ?>
    <tr>
      <td><?= e(fdate(substr((string)$d['created_at'], 0, 10))) ?></td>
      <td><?= e($kinds[$d['response_kind']] ?? $d['response_kind']) ?></td>
      <td style="white-space:pre-wrap;max-width:32ch"><?= e($d['response']) ?>
        <?php if (!empty($d['reason'])): ?><div class="pnote" style="margin-top:4px">Root cause: <?= e($d['reason']) ?></div><?php endif; ?></td>
      <td style="white-space:pre-wrap;max-width:26ch"><?= e($d['proposed_action'] ?: '—') ?></td>
      <td><?= e($d['evidence_ref'] ?: '—') ?></td>
      <td><?php if (!empty($d['decision'])): ?><b><?= e($d['decision']) ?></b>
          <?php if (!empty($d['decision_note'])): ?><div class="pnote"><?= e($d['decision_note']) ?></div><?php endif; ?>
        <?php else: ?><span class="pnote">Awaiting our review</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
