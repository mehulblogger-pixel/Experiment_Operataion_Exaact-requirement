<?php $closed = $b['status'] === 'CLOSED'; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/confidentiality?t=breaches">Confidentiality</a> › <?= e($b['ref']) ?></div>
<div class="master-head"><div>
  <h1><?= e($b['ref']) ?> — <?= e(CONF_BREACH_KINDS[$b['kind']] ?? $b['kind']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><span class="pill <?= $closed?'p-ok':'p-warn' ?>"><?= e(CONF_BREACH_STATUS[$b['status']] ?? $b['status']) ?></span>
    <?= e($b['display_name'] ?: $b['legal_name'] ?: 'no specific client') ?> · discovered <?= e(fdate($b['discovered_on'])) ?></p></div></div>

<?php if (!$closed && $missing): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--bad)">
  <h3 style="margin:0 0 8px">Before this can be closed</h3>
  <ul style="margin:0;padding-left:20px"><?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <h4 style="margin-top:0">What got out</h4>
  <p style="white-space:pre-wrap"><?= e($b['what_got_out']) ?></p>
  <table class="grid">
    <tr><th>Seen by</th><td><?= e($b['who_saw_it'] ?: '—') ?></td>
        <th>Happened</th><td><?= $b['happened_on'] ? e(fdate($b['happened_on'])) : '—' ?></td></tr>
    <tr><th><?= e(TH('job')) ?></th><td><?= $b['job_code'] ? e($b['job_code']) : '—' ?></td>
        <th>Branch</th><td><?= e($b['office_name'] ?: '—') ?></td></tr>
    <tr><th>Nonconformity</th><td colspan="3"><?php if ($ncr): ?>
        <a href="/ncr-item?id=<?= (int)$ncr['id'] ?>"><?= e($ncr['ref']) ?></a>
        <span class="pill <?= ($ncr['status'] ?? '')==='CLOSED'?'p-ok':'p-warn' ?>"><?= e($ncr['status']) ?></span>
        <?php else: ?>—<?php endif; ?></td></tr>
  </table>
</div>

<form method="post" action="/conf-breach" class="panel" style="margin-top:16px;max-width:820px">
  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
  <h3 style="margin-top:0">Containment and who was told</h3>
  <label>What was done to contain it</label>
  <textarea class="form-control" name="containment" rows="3" <?= $canEdit && !$closed ? '' : 'disabled' ?>><?= e($b['containment']) ?></textarea>

  <div class="form-grid" style="gap:12px 16px;margin-top:12px">
    <div><label>Was the affected party told?</label>
      <select class="form-control" name="party_told" <?= $canEdit && !$closed ? '' : 'disabled' ?>>
        <option value="">— not decided —</option>
        <option value="YES" <?= $b['party_told']==='YES'?'selected':'' ?>>Yes</option>
        <option value="NO"  <?= $b['party_told']==='NO'?'selected':'' ?>>No</option>
      </select>
      <small class="muted">Deciding not to tell them is a legitimate answer. Not having decided is not.</small></div>
    <div><label>Told on</label><input class="form-control" type="date" name="party_told_on" value="<?= e($b['party_told_on']) ?>" <?= $canEdit && !$closed ? '' : 'disabled' ?>></div>
    <div class="ff-wide"><label>What they were told, or why they were not</label>
      <textarea class="form-control" name="party_told_note" rows="2" <?= $canEdit && !$closed ? '' : 'disabled' ?>><?= e($b['party_told_note']) ?></textarea></div>
    <div><label>A regulator told?</label>
      <select class="form-control" name="regulator_told" <?= $canEdit && !$closed ? '' : 'disabled' ?>>
        <option value="">— not applicable —</option>
        <option value="YES" <?= $b['regulator_told']==='YES'?'selected':'' ?>>Yes</option>
        <option value="NO"  <?= $b['regulator_told']==='NO'?'selected':'' ?>>No</option>
      </select></div>
    <div><label>Note</label><input class="form-control" name="regulator_told_note" value="<?= e($b['regulator_told_note']) ?>" <?= $canEdit && !$closed ? '' : 'disabled' ?>></div>
  </div>
  <?php if ($canEdit && !$closed): ?><button class="btn" style="margin-top:12px">Save</button><?php endif; ?>
</form>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Close it</h3>
  <?php if ($closed): ?>
    <p><span class="pill p-ok">Closed</span> by <?= e($b['closed_by']) ?> on <?= e(fdate($b['closed_on'])) ?></p>
  <?php elseif ($missing): ?>
    <p class="muted" style="margin:0">Not yet — see the list at the top.</p>
  <?php elseif ($canEdit): ?>
    <form method="post" action="/conf-breach-close">
      <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
      <button class="btn">Close <?= e($b['ref']) ?></button>
    </form>
  <?php endif; ?>
</div>
