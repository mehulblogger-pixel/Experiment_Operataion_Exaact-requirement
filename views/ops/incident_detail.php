<?php $h = incident_hours_left($inc); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/incidents">Incident register</a> › <span><?= e($inc['ref']) ?></span></div>

<div class="master-head">
  <div><h1><?= e($inc['ref']) ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= e(INCIDENT_KINDS[$inc['kind']] ?? $inc['kind']) ?> ·
      noticed <?= e(fdate($inc['detected_at'])) ?> · recorded by <?= e($inc['created_by']) ?></p></div>
  <div><a class="btn secondary" href="/incident-edit?id=<?= (int)$inc['id'] ?>">Update</a></div>
</div>

<?php if ($h !== null): ?>
<div class="panel" style="border:1px solid var(--<?= $h <= 0 ? 'bad' : 'warn' ?>);background:color-mix(in srgb,var(--<?= $h <= 0 ? 'bad' : 'warn' ?>) 8%,transparent)">
  <b style="color:var(--<?= $h <= 0 ? 'bad' : 'warn' ?>)">
    <?= $h <= 0 ? '⚠ Past the six-hour mark by ' . abs($h) . ' hours and not yet reported'
                : '◷ ' . $h . ' hours left to report this to CERT-In' ?></b>
  <div class="muted" style="margin-top:6px;font-size:13.5px;line-height:1.7">
    Send to <strong>incident@cert-in.org.in</strong>. What they expect, and all of which is on this page:
    when it was noticed, what kind of incident it was, what was affected, roughly how many people and what sort of
    data, what you did straight away, and who to contact here
    <?= ($g['name'] && $g['email']) ? '— <strong>' . e($g['name']) . ', ' . e($g['email']) . '</strong>' : '(no grievance officer is named yet — Settings → Compliance)' ?>.
    Then come back and enter the time and their reference, and this warning goes away.
  </div>
</div>
<?php endif; ?>

<div class="panel-split">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>What happened</h3></div>
    <p style="font-size:14px;line-height:1.7"><?= nl2br(e($inc['summary'])) ?></p>
    <table class="grid">
      <tr><th>How bad</th><td><?= e(INCIDENT_SEVERITY[$inc['severity']] ?? $inc['severity']) ?></td></tr>
      <tr><th>What was affected</th><td><?= e($inc['systems'] ?: '—') ?></td></tr>
      <tr><th>People's data involved</th><td><?= (int)$inc['people_affected'] ?><?= $inc['data_kinds'] ? ' — ' . e($inc['data_kinds']) : '' ?></td></tr>
      <tr><th>Done straight away</th><td><?= e($inc['immediate_action'] ?: '—') ?></td></tr>
      <tr><th>Why it happened</th><td><?= e($inc['root_cause'] ?: 'not established yet') ?></td></tr>
      <tr><th>Status</th><td><span class="pill <?= $inc['status'] === 'CLOSED' ? 'p-mut' : 'p-warn' ?>"><?= e($inc['status']) ?></span>
        <?= $inc['closed_at'] ? ' ' . e(fdate($inc['closed_at'])) : '' ?></td></tr>
    </table>
  </div>

  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Who has been told</h3></div>
    <table class="grid">
      <tr><th>CERT-In</th>
        <td><?= trim((string)$inc['certin_reported_at']) !== ''
              ? '<span class="pill p-ok">' . e(fdate($inc['certin_reported_at'])) . '</span> ' . e($inc['certin_ref'])
              : '<span class="pill p-bad">not yet</span>' ?></td></tr>
      <tr><th>Data Protection Board</th>
        <td><?= trim((string)$inc['dpb_reported_at']) !== ''
              ? '<span class="pill p-ok">' . e(fdate($inc['dpb_reported_at'])) . '</span>'
              : '<span class="pill p-mut">not yet — required when personal data was involved</span>' ?></td></tr>
      <tr><th>The people affected</th>
        <td><?= trim((string)$inc['people_told_at']) !== ''
              ? '<span class="pill p-ok">' . e(fdate($inc['people_told_at'])) . '</span>'
              : '<span class="pill p-mut">not yet — required when personal data was involved</span>' ?></td></tr>
    </table>
    <p class="muted" style="font-size:12.5px;margin-top:10px">
      Recorded <?= e(fdate($inc['created_at'])) ?>. This entry, and every change to it, is on the audit trail —
      which is what an auditor will ask to see.
    </p>
  </div>
</div>
