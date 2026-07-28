<?php $open = capa_is_open($c); $al = capa_action_overdue($c); $vl = capa_verify_overdue($c); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/capa">Corrective actions</a> › <?= e($c['ref']) ?></div>
<div class="master-head">
  <div><h1><?= e($c['ref']) ?> — <?= e($c['title']) ?></h1>
    <p class="sub" style="margin:2px 0 0">
      <?= e(strtok(CAPA_SEVERITY[$c['severity']] ?? $c['severity'], '—')) ?> ·
      raised <?= e($c['raised_on'] ? fdate($c['raised_on']) : '—') ?> by <?= e($c['raised_by'] ?: '—') ?> ·
      <?= e(strtok(CAPA_SOURCES[$c['source']] ?? $c['source'], '—')) ?>
      <?php if ($complaint): ?> · <a href="/complaint?id=<?= (int)$complaint['id'] ?>"><?= e($complaint['ref']) ?></a><?php endif; ?>
      <?php if ($follows): ?> · follows <a href="/capa-item?id=<?= (int)$follows['id'] ?>"><?= e($follows['ref']) ?></a><?php endif; ?>
    </p></div>
  <a class="btn secondary" href="/capa">← Back</a>
</div>

<?php if (!$open): ?>
  <div class="panel" style="border-left:4px solid <?= $c['status'] === 'CLOSED' ? 'var(--ok)' : 'var(--warn)' ?>">
    <p style="margin:0"><strong><?= e(CAPA_STATUS[$c['status']] ?? $c['status']) ?></strong>
      on <?= e(fdate($c['closed_on'])) ?> by <?= e($c['closed_by']) ?>.
      <?php if ($followed): ?> Carried forward as
        <?php foreach ($followed as $f): ?><a href="/capa-item?id=<?= (int)$f['id'] ?>"><?= e($f['ref']) ?></a> <?php endforeach; ?>
      <?php endif; ?></p></div>
<?php elseif ($missing): ?>
  <div class="panel" style="border-left:4px solid var(--warn)">
    <p style="margin:0"><strong>Still to do before this can be closed:</strong> <?= e(implode('; ', $missing)) ?>.</p></div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🎯</span><div class="k">Root cause</div>
    <div class="v" style="font-size:18px"><?= trim((string)$c['root_cause']) !== '' ? 'recorded' : '<span class="down">not yet</span>' ?></div>
    <div class="d"><?= e($c['rc_method'] ? CAPA_RC_METHODS[$c['rc_method']] : 'method not stated') ?></div></div>
  <div class="kpi"><span class="kic">🔁</span><div class="k">Anywhere else?</div>
    <div class="v" style="font-size:18px"><?= empty($c['similar_checked']) ? '<span class="down">unanswered</span>'
        : ($c['similar_found'] === 'YES' ? 'yes' : 'no') ?></div>
    <div class="d">§8.7.2 d)</div></div>
  <div class="kpi"><span class="kic">✅</span><div class="k">Action carried out</div>
    <div class="v" style="font-size:18px"><?= $c['completed_on'] ? e(fdate($c['completed_on']))
        : ($al ? '<span class="down">' . (int)$al . 'd late</span>' : 'not yet') ?></div>
    <div class="d">due <?= e($c['due_on'] ? fdate($c['due_on']) : '—') ?></div></div>
  <div class="kpi"><span class="kic">🔎</span><div class="k">Did it work?</div>
    <div class="v" style="font-size:18px"><?= $c['verified_on']
        ? ($c['effective'] === 'YES' ? '<span class="up">yes</span>' : '<span class="down">no</span>')
        : ($vl ? '<span class="down">' . (int)$vl . 'd late</span>' : 'not checked') ?></div>
    <div class="d"><?= e($c['verified_by'] ?: 'nobody yet') ?></div></div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>What went wrong</h3></div>
  <p style="white-space:pre-wrap;margin:0"><?= e($c['description']) ?></p>
  <?php if (trim((string)$c['immediate_action']) !== ''): ?>
    <h3 class="tab-sub">What we did straight away</h3>
    <p style="white-space:pre-wrap;margin:0"><?= e($c['immediate_action']) ?></p>
  <?php endif; ?>
</div>

<?php if ($canEdit && $open): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Work it through</h3></div>

  <h3 class="tab-sub">1 · Cause <span class="muted">(§8.7.2)</span></h3>
  <form method="post" action="/capa-cause" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff ff-wide"><label>What we did straight away</label>
      <textarea class="form-control" name="immediate_action" rows="3"><?= e($c['immediate_action']) ?></textarea></div>
    <div class="ff ff-wide"><label>Root cause</label>
      <textarea class="form-control" name="root_cause" rows="4"
                placeholder="the cause, not the person — “the engineer forgot” is a symptom with a name attached"><?= e($c['root_cause']) ?></textarea></div>
    <div class="ff"><label>How it was worked out</label>
      <select class="form-control" name="rc_method"><option value="">— choose —</option>
        <?php foreach (CAPA_RC_METHODS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $c['rc_method']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Has this happened, or could it happen, elsewhere?</label>
      <select class="form-control" name="similar_found"><option value="">— not answered —</option>
        <option value="NO"  <?= $c['similar_found']==='NO'?'selected':'' ?>>No — we looked, and it is confined to this</option>
        <option value="YES" <?= $c['similar_found']==='YES'?'selected':'' ?>>Yes — it applies more widely</option>
      </select>
      <small class="muted">One line in the standard, the most-missed one in practice. “No” is a fine answer if somebody actually looked.</small></div>
    <div class="ff ff-wide"><label>Where, and what we are doing about that</label>
      <input class="form-control" name="similar_note" value="<?= e($c['similar_note']) ?>"></div>
    <button class="btn small secondary" type="submit">Save</button>
  </form>

  <h3 class="tab-sub">2 · Plan</h3>
  <form method="post" action="/capa-plan" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff ff-wide"><label>What will be done</label>
      <textarea class="form-control" name="action_plan" rows="4"
                placeholder="something that changes how the work happens, not “counselled the engineer”"><?= e($c['action_plan']) ?></textarea></div>
    <div class="ff"><label>Whose job</label><input class="form-control" name="owner" value="<?= e($c['owner']) ?>"></div>
    <div class="ff"><label>By when</label><input class="form-control" type="date" name="due_on" value="<?= e($c['due_on']) ?>"></div>
    <button class="btn small secondary" type="submit">Save plan</button>
  </form>

  <?php if ($c['completed_on'] === ''): ?>
  <h3 class="tab-sub">3 · It was done</h3>
  <form method="post" action="/capa-done" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Carried out on</label><input class="form-control" type="date" name="completed_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <button class="btn small" type="submit">Record it</button>
  </form>
  <?php else: ?>
  <h3 class="tab-sub">4 · Did it work? <span class="muted">(§8.7.3)</span></h3>
  <p class="sub" style="margin-top:0">This is the step everybody skips, and the one an assessor asks for by name.
    Go and look at the work that has happened since <?= e(fdate($c['completed_on'])) ?>.</p>
  <form method="post" action="/capa-verify" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Checked on</label><input class="form-control" type="date" name="verified_on" value="<?= e($c['verified_on'] ?: date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Did the problem stop?</label>
      <select class="form-control" name="effective">
        <option value="YES" <?= $c['effective']==='YES'?'selected':'' ?>>Yes — it worked</option>
        <option value="NO"  <?= $c['effective']==='NO'?'selected':'' ?>>No — it did not</option>
      </select></div>
    <div class="ff ff-wide"><label>What you looked at *</label>
      <textarea class="form-control" name="effectiveness_note" rows="3" required
                placeholder="e.g. reviewed the last 20 reports of this type; none had the same gap"><?= e($c['effectiveness_note']) ?></textarea></div>
    <button class="btn small" type="submit">Record the check</button>
  </form>
  <?php endif; ?>

  <h3 class="tab-sub">5 · Close</h3>
  <?php if ($c['effective'] === 'NO'): ?>
    <p class="pill p-bad" style="display:inline-block;margin:0 0 8px">Checked and it did not work. This cannot be closed as done.</p>
    <form method="post" action="/capa-escalate" style="margin:0">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button class="btn" type="submit">Close as ineffective and raise a further action</button></form>
  <?php elseif ($missing): ?>
    <p class="muted" style="margin:0">Not yet — <?= e(implode('; ', $missing)) ?>.</p>
  <?php elseif (!$canClose): ?>
    <p class="muted" style="margin:0">Everything is done. Closing needs the “Close corrective actions” permission.</p>
  <?php else: ?>
    <form method="post" action="/capa-close" style="margin:0">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button class="btn" type="submit">Close <?= e($c['ref']) ?></button></form>
  <?php endif; ?>
</div>
<?php elseif (!$open): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>What was done</h3></div>
  <table class="dt"><tbody>
    <tr><th style="width:200px">Root cause</th><td style="white-space:pre-wrap"><?= e($c['root_cause'] ?: '—') ?></td></tr>
    <tr><th>Worked out by</th><td><?= e($c['rc_method'] ? CAPA_RC_METHODS[$c['rc_method']] : '—') ?></td></tr>
    <tr><th>Anywhere else?</th><td><?= e($c['similar_found'] ?: 'not answered') ?><?= $c['similar_note'] ? ' — ' . e($c['similar_note']) : '' ?></td></tr>
    <tr><th>Action taken</th><td style="white-space:pre-wrap"><?= e($c['action_plan'] ?: '—') ?></td></tr>
    <tr><th>Did it work?</th><td><?= e($c['effective'] ?: '—') ?><?= $c['effectiveness_note'] ? ' — ' . e($c['effectiveness_note']) : '' ?></td></tr>
  </tbody></table>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>What happened, in order</h3></div>
  <table class="dt">
    <thead><tr><th>When</th><th>What</th><th>Who</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
      <tr><td class="muted"><?= e(substr((string)$ev['at'], 0, 16)) ?></td>
        <td><strong><?= e($ev['action']) ?></strong></td>
        <td><?= e($ev['actor'] ?: '—') ?></td>
        <td class="muted"><?= e($ev['note']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$events): ?><tr><td colspan="4">Nothing yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
