<?php $closed = $a['status'] === 'CLOSED'; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/internal-audits">Internal audits</a> › <?= e($a['ref']) ?></div>
<div class="master-head">
  <div><h1><?= e($a['ref']) ?><?= $a['scope'] ? ' — ' . e($a['scope']) : '' ?></h1>
    <p class="sub" style="margin:2px 0 0">Planned <?= e($a['planned_on'] ? fdate($a['planned_on']) : '—') ?>
      · auditor <?= e($a['auditor'] ?: '—') ?>
      <?= $a['area_owner'] ? ' · area run by ' . e($a['area_owner']) : '' ?>
      · clauses <?= e(implode(', ', audit_clauses_of($a))) ?></p></div>
  <a class="btn secondary" href="/internal-audits">← Back</a>
</div>

<?php if ($closed): ?>
  <div class="panel" style="border-left:4px solid var(--ok)"><p style="margin:0"><strong>Closed</strong>
    on <?= e(fdate($a['closed_on'])) ?> by <?= e($a['closed_by']) ?>.</p></div>
<?php elseif ($missing): ?>
  <div class="panel" style="border-left:4px solid var(--warn)">
    <p style="margin:0"><strong>Still to do before this can be closed:</strong> <?= e(implode('; ', $missing)) ?>.</p></div>
<?php endif; ?>

<?php if ($canEdit && !$closed): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Record what happened</h3>
  <form method="post" action="/audit-record" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <div class="ff"><label>Carried out on</label>
      <input class="form-control" type="date" name="carried_out_on" value="<?= e($a['carried_out_on']) ?>"></div>
    <div class="ff"><label>Auditor</label><input class="form-control" name="auditor" value="<?= e($a['auditor']) ?>"></div>
    <div class="ff"><label>Who runs this area</label><input class="form-control" name="area_owner" value="<?= e($a['area_owner']) ?>"></div>
    <div class="ff ff-wide"><label>How it was done</label><input class="form-control" name="method" value="<?= e($a['method']) ?>"></div>
    <div class="ff ff-wide"><label>What the audit found, overall</label>
      <textarea class="form-control" name="summary" rows="5"><?= e($a['summary']) ?></textarea></div>
    <button class="btn small" type="submit">Save</button>
  </form>
</div>
<?php elseif (trim((string)$a['summary']) !== ''): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>What the audit found</h3></div>
  <p style="white-space:pre-wrap;margin:0"><?= e($a['summary']) ?></p>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Findings <span class="muted">(<?= count($findings) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Clause</th><th>Kind</th><th>What was found</th><th>Evidence</th><th>Corrective action</th><?php if ($canEdit && !$closed): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($findings as $f): $needs = in_array($f['kind'], FINDING_NEEDS_CAPA, true); ?>
      <tr>
        <td><?= e($f['clause'] ?: '—') ?></td>
        <td><?= $needs ? '<span class="pill p-bad">' . e(FINDING_KINDS[$f['kind']]) . '</span>'
              : '<span class="pill">' . e(FINDING_KINDS[$f['kind']] ?? $f['kind']) . '</span>' ?></td>
        <td style="max-width:300px;white-space:pre-wrap"><?= e($f['detail']) ?></td>
        <td class="muted" style="max-width:200px"><?= e($f['evidence']) ?></td>
        <td><?= $f['capa_ref']
              ? '<a href="/capa-item?id=' . (int)$f['capa_id'] . '">' . e($f['capa_ref']) . '</a>'
              : ($needs ? '<span class="pill p-bad">none yet</span>' : '<span class="muted">—</span>') ?></td>
        <?php if ($canEdit && !$closed): ?>
        <td style="min-width:170px">
          <?php if ($needs && !$f['capa_ref']): ?>
          <form method="post" action="/audit-finding-capa" style="margin:0 0 4px">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="finding_id" value="<?= (int)$f['id'] ?>">
            <button class="btn small" type="submit">Raise a corrective action</button></form>
          <?php endif; ?>
          <form method="post" action="/audit-finding-delete" style="margin:0"
                onsubmit="return confirm('Remove this finding?')">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="finding_id" value="<?= (int)$f['id'] ?>">
            <button class="btn small secondary" type="submit">Remove</button></form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$findings): ?><tr><td colspan="<?= $canEdit && !$closed ? 6 : 5 ?>">Nothing recorded. Even “conforms” is a finding worth writing down — it is the evidence the clause was looked at.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canEdit && !$closed): ?>
  <h3 class="tab-sub">Add a finding</h3>
  <form method="post" action="/audit-finding-add" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <div class="ff"><label>Clause</label>
      <select class="form-control" name="clause"><option value="">—</option>
        <?php foreach (audit_clauses_of($a) as $k): ?>
          <option value="<?= e($k) ?>"><?= e($clauses[$k] ?? $k) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Kind</label>
      <select class="form-control" name="kind">
        <?php foreach (FINDING_KINDS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $k==='OBSERVATION'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>What was found *</label>
      <input class="form-control" name="detail" required placeholder="plainly — this becomes the corrective action's description"></div>
    <div class="ff ff-wide"><label>Evidence seen</label>
      <input class="form-control" name="evidence" placeholder="which records, which reports, which people"></div>
    <button class="btn small secondary" type="submit">Add</button>
  </form>

  <h3 class="tab-sub">Close the audit</h3>
  <?php if ($missing): ?>
    <p class="muted" style="margin:0">Not yet — <?= e(implode('; ', $missing)) ?>.</p>
  <?php else: ?>
    <form method="post" action="/audit-close" style="margin:0">
      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
      <button class="btn" type="submit">Close <?= e($a['ref']) ?></button></form>
  <?php endif; ?>
  <?php endif; ?>
</div>
