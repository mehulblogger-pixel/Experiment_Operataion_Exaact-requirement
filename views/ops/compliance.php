<?php
  $pill = ['ok'=>'p-ok', 'warn'=>'p-warn', 'bad'=>'p-bad', 'unknown'=>'p-mut'];
  $word = ['ok'=>'Done', 'warn'=>'Partly', 'bad'=>'Not done', 'unknown'=>'Not ours to answer'];
  $byArea = [];
  foreach ($rows as $r) $byArea[$r['area']][] = $r;
?>
<div class="crumbs"><a href="/">Home</a> › <span>Where we stand</span></div>

<div class="master-head">
  <div><h1>Where we stand</h1>
    <p class="sub" style="margin:2px 0 0">Measured from this running system, not from a checklist somebody ticked.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn secondary" href="/incidents">Incident register</a>
    <a class="btn secondary" href="/data-requests">Requests about personal data</a>
    <a class="btn secondary" href="/audit-log">Audit trail</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi tone-ok"><span class="kic">✔</span><span class="k-lab">Done</span>
    <span class="k-val"><?= (int)$counts['ok'] ?></span><span class="k-sub">and provable from here</span></div>
  <div class="kpi tone-warn"><span class="kic">◐</span><span class="k-lab">Partly</span>
    <span class="k-val"><?= (int)$counts['warn'] ?></span><span class="k-sub">built, not switched on</span></div>
  <div class="kpi tone-bad"><span class="kic">✕</span><span class="k-lab">Not done</span>
    <span class="k-val"><?= (int)$counts['bad'] ?></span><span class="k-sub">needs a decision from you</span></div>
  <div class="kpi tone-info"><span class="kic">?</span><span class="k-lab">Not ours to answer</span>
    <span class="k-val"><?= (int)$counts['unknown'] ?></span><span class="k-sub">hosting, backups, certification</span></div>
</div>

<?php // Software cannot make a company compliant. What it can do is refuse to
      // pretend, so a line nobody has done says so in the same words every time. ?>
<div class="panel">
  <p class="muted" style="margin:0;font-size:13.5px;line-height:1.65">
    A line marked <strong>Done</strong> was checked against the running system a moment ago. A line marked
    <strong>Not ours to answer</strong> depends on your hosting account, your backups or a certificate — this
    software cannot see those, and marking them green would be the most dangerous thing on the page. Each of
    those says who can answer it and what to ask for.
  </p>
</div>

<?php foreach ($byArea as $area => $items): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:12px 14px 0;margin:0"><h3><?= e($area) ?></h3></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th style="min-width:230px">What</th><th style="width:120px">State</th><th>Where it stands</th><th>What to do</th></tr></thead>
    <tbody>
      <?php foreach ($items as $r): ?>
      <tr>
        <td><b><?= e($r['what']) ?></b></td>
        <td><span class="pill <?= $pill[$r['state']] ?>"><?= e($word[$r['state']]) ?></span></td>
        <td style="font-size:13px"><?= e($r['detail']) ?></td>
        <td style="font-size:13px" class="muted"><?= $r['todo'] !== '' ? e($r['todo']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endforeach; ?>

<div class="panel-split">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Latest incidents</h3>
      <a class="btn small" href="/incident-new">+ Record one</a></div>
    <?php if (!$incidents): ?>
      <p class="muted" style="font-size:13.5px">Nothing recorded. If something happens, record it here <em>first</em> —
        the six-hour clock runs from when it was noticed, not from when somebody got round to writing it down.</p>
    <?php else: ?>
      <table class="dt">
        <thead><tr><th>Ref</th><th>Detected</th><th>Six-hour clock</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($incidents as $i): $h = incident_hours_left($i); ?>
          <tr>
            <td><a href="/incident?id=<?= (int)$i['id'] ?>"><b><?= e($i['ref']) ?></b></a></td>
            <td><?= e(fdate($i['detected_at'])) ?></td>
            <td><span class="pill <?= $h === null ? 'p-ok' : ($h <= 0 ? 'p-bad' : ($h < 2 ? 'p-warn' : 'p-info')) ?>"><?= e(incident_clock_text($i)) ?></span></td>
            <td><span class="pill <?= $i['status'] === 'CLOSED' ? 'p-mut' : 'p-warn' ?>"><?= e($i['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>The audit trail</h3></div>
    <p class="muted" style="font-size:13.5px">
      Kept for <strong><?= (int)audit_retain_days() ?> days</strong>. The oldest entry on file is
      <strong><?= (int)audit_oldest_days() ?> days</strong> old. The CERT-In directions put the floor at 180 days,
      so the setting cannot be taken below it.
    </p>
    <p class="muted" style="font-size:13.5px">
      Keeping it for ever is not the safe answer either — on a shared host it is a growing record of who did what.
      Trimming removes only entries past the retention you have set.
    </p>
    <form method="post" action="/compliance" onsubmit="return confirm('Remove audit entries older than <?= (int)audit_retain_days() ?> days? This cannot be undone.')">
      <input type="hidden" name="_do" value="trim">
      <button class="btn secondary" type="submit">Trim what is past retention</button>
    </form>
  </div>
</div>
