<?php $done = $r['status'] === 'COMPLETE'; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/management-reviews">Management reviews</a> › <?= e($r['ref']) ?></div>
<div class="master-head">
  <div><h1><?= e($r['ref']) ?> — management review</h1>
    <p class="sub" style="margin:2px 0 0">Held <?= e($r['held_on'] ? fdate($r['held_on']) : '—') ?>
      · period <?= e(fdate($r['period_from'])) ?> to <?= e(fdate($r['period_to'])) ?>
      · chaired by <?= e($r['chair'] ?: '—') ?></p></div>
  <a class="btn secondary" href="/management-reviews">← Back</a>
</div>

<?php if ($done): ?>
  <div class="panel" style="border-left:4px solid var(--ok)"><p style="margin:0"><strong>Complete</strong>
    — recorded <?= e(fdate($r['completed_on'])) ?> by <?= e($r['completed_by']) ?>.</p></div>
<?php elseif ($missing): ?>
  <div class="panel" style="border-left:4px solid var(--warn)">
    <p style="margin:0"><strong>Not complete yet:</strong> <?= e(implode('; ', $missing)) ?>.</p>
    <?php if ($blank): ?><p class="sub" style="margin:6px 0 0">Nothing written against:
      <?= e(implode('; ', array_values($blank))) ?>.</p><?php endif; ?></div>
<?php endif; ?>

<?php if ($canEdit && !$done): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Who and when</h3>
  <form method="post" action="/review-header" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <div class="ff"><label>Held on</label><input class="form-control" type="date" name="held_on" value="<?= e($r['held_on']) ?>"></div>
    <div class="ff"><label>Period from</label><input class="form-control" type="date" name="period_from" value="<?= e($r['period_from']) ?>"></div>
    <div class="ff"><label>to</label><input class="form-control" type="date" name="period_to" value="<?= e($r['period_to']) ?>"></div>
    <div class="ff"><label>Chair</label><input class="form-control" name="chair" value="<?= e($r['chair']) ?>"></div>
    <div class="ff ff-wide"><label>Who was there</label><input class="form-control" name="attendees" value="<?= e($r['attendees']) ?>"></div>
    <button class="btn small secondary" type="submit">Save &amp; re-count</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>The required inputs <span class="muted">(§8.9.2 — all <?= count(MR_INPUTS) ?>)</span></h3>
    <?php if ($canEdit && !$done): ?>
    <form method="post" action="/review-refresh" style="margin:0">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn small secondary" type="submit">Re-count from the system</button></form>
    <?php endif; ?>
  </div>
  <p class="sub" style="margin-top:0">The grey line under each heading is <strong>measured</strong> from this
    system for the period above — not typed, not remembered. What it means is yours to write.</p>

  <?php foreach (MR_INPUTS as $key => [$label, $auto]):
    $row = $inputs[$key] ?? null;
    $note = trim((string)($row['note'] ?? ''));
    $measured = trim((string)($row['measured'] ?? '')); ?>
    <div id="in-<?= e($key) ?>" style="padding:12px 0;border-top:1px solid var(--line)">
      <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
        <strong><?= e($label) ?></strong>
        <?= $note === '' ? '<span class="pill p-warn">nothing written</span>' : '<span class="pill p-ok">addressed</span>' ?>
      </div>
      <?php if ($measured !== ''): ?>
        <div class="muted" style="font-size:13px;margin:4px 0 0">Measured: <?= e($measured) ?></div>
      <?php elseif ($auto === ''): ?>
        <div class="muted" style="font-size:13px;margin:4px 0 0">No automatic measure — this one is judgement only.</div>
      <?php endif; ?>
      <?php if ($canEdit && !$done): ?>
        <form method="post" action="/review-input" style="display:flex;gap:6px;margin:6px 0 0;align-items:flex-start">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="input_key" value="<?= e($key) ?>">
          <textarea class="form-control" name="note" rows="2" style="flex:1"
                    placeholder="what this means, and what we are doing about it"><?= e($note) ?></textarea>
          <button class="btn small secondary" type="submit">Save</button>
        </form>
      <?php elseif ($note !== ''): ?>
        <p style="white-space:pre-wrap;margin:6px 0 0"><?= e($note) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>What came out of it <span class="muted">(§8.9.3 — <?= count($actions) ?>)</span></h3></div>
  <p class="sub" style="margin-top:0">A review with fifteen inputs and no decisions is minutes of a meeting.
    At least one is required before this can be recorded as complete.</p>
  <table class="dt">
    <thead><tr><th>About</th><th>Decision</th><th>Whose job</th><th>By when</th><th>State</th><?php if ($canEdit && !$done): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($actions as $x): ?>
      <tr>
        <td class="muted"><?= e(MR_ACTION_KINDS[$x['kind']] ?? $x['kind']) ?></td>
        <td style="max-width:340px;white-space:pre-wrap"><?= e($x['decision']) ?>
          <?php if ($x['done_note']): ?><div class="muted" style="font-size:12px">Done: <?= e($x['done_note']) ?></div><?php endif; ?></td>
        <td><?= e($x['owner'] ?: '—') ?></td>
        <td><?= e($x['due_on'] ? fdate($x['due_on']) : '—') ?></td>
        <td><?= $x['status'] === 'DONE' ? '<span class="pill p-ok">done ' . e(fdate($x['done_on'])) . '</span>'
              : '<span class="pill p-warn">open</span>' ?></td>
        <?php if ($canEdit && !$done): ?>
        <td><?php if ($x['status'] !== 'DONE'): ?>
          <form method="post" action="/review-action-done" style="display:flex;gap:4px;margin:0">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action_id" value="<?= (int)$x['id'] ?>">
            <input class="form-control" style="width:150px" name="done_note" placeholder="what was done">
            <button class="btn small secondary" type="submit">Done</button></form>
        <?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$actions): ?><tr><td colspan="<?= $canEdit && !$done ? 6 : 5 ?>">Nothing decided yet.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canEdit && !$done): ?>
  <h3 class="tab-sub">Record a decision</h3>
  <form method="post" action="/review-action-add" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <div class="ff"><label>About</label>
      <select class="form-control" name="kind">
        <?php foreach (MR_ACTION_KINDS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Whose job</label><input class="form-control" name="owner"></div>
    <div class="ff"><label>By when</label><input class="form-control" type="date" name="due_on"></div>
    <div class="ff ff-wide"><label>The decision *</label>
      <textarea class="form-control" name="decision" rows="3" required
                placeholder="something that will actually be done, by somebody, by a date"></textarea></div>
    <button class="btn small" type="submit">Record it</button>
  </form>

  <h3 class="tab-sub">Complete the review</h3>
  <?php if ($missing): ?>
    <p class="muted" style="margin:0">Not yet — <?= e(implode('; ', $missing)) ?>.</p>
  <?php else: ?>
    <form method="post" action="/review-complete" style="margin:0">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn" type="submit">Record <?= e($r['ref']) ?> as complete</button></form>
  <?php endif; ?>
  <?php endif; ?>
</div>
