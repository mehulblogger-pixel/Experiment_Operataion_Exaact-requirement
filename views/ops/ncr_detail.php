<?php $closed = $n['status'] === 'CLOSED'; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/ncr">Nonconformities</a> › <?= e($n['ref']) ?></div>
<div class="master-head">
  <div><h1><?= e($n['ref']) ?> — <?= e($n['title']) ?></h1>
  <p class="sub" style="margin:2px 0 0">
    <span class="pill <?= $n['severity']==='MAJOR'?'p-bad':($n['severity']==='MINOR'?'p-warn':'p-mut') ?>"><?= e(ucfirst(strtolower($n['severity']))) ?></span>
    <span class="pill <?= $closed?'p-ok':'p-warn' ?>"><?= e(NCR_STATUS[$n['status']] ?? $n['status']) ?></span>
    <?= e(ncr_source_label($n['source'])) ?><?= $n['source_note'] ? ' — ' . e($n['source_note']) : '' ?>
  </p></div>
</div>

<?php if (!$closed && $missing): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--bad)">
  <h3 style="margin:0 0 8px">Before this can be closed</h3>
  <ul style="margin:0;padding-left:20px">
    <?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <table class="grid">
    <tr><th>Raised</th><td><?= e(fdate($n['detected_on'])) ?> by <?= e($n['detected_by'] ?: '—') ?></td>
        <th>Branch</th><td><?= e($n['office_name'] ?: '—') ?></td></tr>
    <tr><th><?= e(TH('job')) ?></th><td><?= $n['job_id'] ? '<a href="/job?id=' . (int)$n['job_id'] . '">' . e($n['job_code']) . '</a>' : '—' ?></td>
        <th>Client / party</th><td><?= e($n['display_name'] ?: $n['legal_name'] ?: '—') ?></td></tr>
    <tr><th>Owner</th><td><?= e($n['owner'] ?: '—') ?></td>
        <th>Due</th><td><?= $n['due_on'] ? e(fdate($n['due_on'])) : '—' ?></td></tr>
    <?php if ($n['clause']): ?><tr><th>Clause</th><td colspan="3"><?= e($n['clause']) ?></td></tr><?php endif; ?>
  </table>
  <?php if (trim((string)$n['description']) !== ''): ?>
    <h4 style="margin:14px 0 4px">What happened</h4>
    <p style="white-space:pre-wrap;margin:0"><?= e($n['description']) ?></p>
  <?php endif; ?>
</div>

<?php if ($canEdit && !$closed): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Owner and date</h3>
  <form method="post" action="/ncr-assign" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
    <div><label>Owner</label><input class="form-control" name="owner" value="<?= e($n['owner']) ?>"></div>
    <div><label>Due by</label><input class="form-control" type="date" name="due_on" value="<?= e($n['due_on']) ?>"></div>
    <button class="btn small">Save</button>
  </form>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">1 · What was done immediately</h3>
  <p class="muted" style="margin:0 0 10px">Containment stops it getting worse. It is not the corrective action and does not need to find the cause.</p>
  <?php if (trim((string)$n['containment']) !== ''): ?>
    <p style="white-space:pre-wrap"><?= e($n['containment']) ?></p>
    <p class="muted" style="font-size:12px">Recorded by <?= e($n['contained_by']) ?> on <?= e(fdate($n['contained_on'])) ?></p>
  <?php endif; ?>
  <?php if ($canEdit && !$closed): ?>
  <form method="post" action="/ncr-contain">
    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
    <textarea class="form-control" name="containment" rows="2" placeholder="e.g. the report was recalled from the client the same day"><?= e($n['containment']) ?></textarea>
    <button class="btn small" style="margin-top:8px">Save</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">2 · What happened to the work that was already wrong</h3>
  <p class="muted" style="margin:0 0 10px">This is the question an assessor asks. Nothing closes without an answer.</p>
  <?php if (trim((string)$n['disposition']) !== ''): ?>
    <p><b><?= e(NCR_DISPOSITIONS[$n['disposition']] ?? $n['disposition']) ?></b>
      <?php if (trim((string)$n['disposition_note']) !== ''): ?><br><span style="white-space:pre-wrap"><?= e($n['disposition_note']) ?></span><?php endif; ?></p>
    <p class="muted" style="font-size:12px">Decided by <?= e($n['disposition_by']) ?> on <?= e(fdate($n['disposition_on'])) ?></p>
  <?php endif; ?>
  <?php if ($canEdit && !$closed): ?>
  <form method="post" action="/ncr-disposition">
    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
    <select class="form-control" name="disposition" style="max-width:360px">
      <?php foreach (NCR_DISPOSITIONS as $k=>$v): ?>
        <option value="<?= e($k) ?>"<?= $n['disposition']===$k?' selected':'' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <textarea class="form-control" name="disposition_note" rows="2" style="margin-top:8px" placeholder="Required if you are accepting it as it stands"><?= e($n['disposition_note']) ?></textarea>
    <button class="btn small" style="margin-top:8px">Record it</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">3 · Does it need a corrective action?</h3>
  <?php if ($capa): ?>
    <p><a href="/capa-item?id=<?= (int)$capa['id'] ?>"><b><?= e($capa['ref']) ?></b></a> — <?= e($capa['title']) ?>
      <span class="pill <?= ($capa['status'] ?? '')==='CLOSED'?'p-ok':'p-warn' ?>"><?= e($capa['status']) ?></span></p>
    <p class="muted" style="margin:0">This nonconformity cannot close until that is closed.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 10px">
      <?php if ($n['severity']==='MAJOR'): ?>
        <b>A major nonconformity needs one.</b> Finding the cause is not optional when the result is unreliable.
      <?php else: ?>
        Most do not. A one-off typo needs correcting, not a root-cause investigation — open one only if this could happen again.
      <?php endif; ?>
    </p>
    <?php if ($canEdit && !$closed): ?>
      <form method="post" action="/ncr-capa">
        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
        <button class="btn<?= $n['severity']==='MAJOR'?'':' secondary' ?>">Open a corrective action from this</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">4 · Close it</h3>
  <?php if ($closed): ?>
    <p><span class="pill p-ok">Closed</span> by <?= e($n['closed_by']) ?> on <?= e(fdate($n['closed_on'])) ?></p>
    <?php if (trim((string)$n['close_note']) !== ''): ?><p style="white-space:pre-wrap"><?= e($n['close_note']) ?></p><?php endif; ?>
    <?php if ($canClose): ?>
      <form method="post" action="/ncr-reopen" onsubmit="return confirm('Reopen this nonconformity?')">
        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
        <input class="form-control" name="why" placeholder="Why is it being reopened?" style="max-width:420px">
        <button class="btn small secondary">Reopen</button>
      </form>
    <?php endif; ?>
  <?php elseif (!$canClose): ?>
    <p class="muted" style="margin:0">Somebody with the closing permission has to close this.</p>
  <?php elseif ($missing): ?>
    <p class="muted" style="margin:0">Not yet — see the list at the top of this page.</p>
  <?php else: ?>
    <form method="post" action="/ncr-close">
      <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
      <textarea class="form-control" name="close_note" rows="2" placeholder="Anything worth saying on the way out (optional)"></textarea>
      <button class="btn" style="margin-top:8px">Close <?= e($n['ref']) ?></button>
    </form>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">History</h3>
  <?php if (!$events): ?><p class="muted" style="margin:0">Nothing yet.</p><?php else: ?>
  <table class="dt">
    <thead><tr><th>When</th><th>What</th><th>Who</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
      <tr><td><?= e(fdate(substr((string)$ev['at'],0,10))) ?></td>
          <td><?= e($ev['action']) ?></td>
          <td><?= e($ev['actor']) ?></td>
          <td><?= e($ev['note']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
