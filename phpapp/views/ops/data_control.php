<?php $app = $ready['app']; ?>
<div class="crumbs"><a href="/">Home</a> › Data &amp; information control</div>
<div class="master-head">
  <div><h1>Control of data &amp; information</h1>
    <p class="sub" style="margin:2px 0 0"><?= e(accreditation_ref('datacontrol')) ?> — controlling the data an accredited body's
      results rest on. It arrived because bodies stopped keeping records on paper and nobody had written down what that means.</p></div>
  <a class="btn secondary" href="/compliance">Compliance overview</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🧪</span><div class="k">This version validated?</div>
    <div class="v" style="font-size:18px"><?= $app['state'] === 'ok' ? '<span class="up">yes</span>'
      : ($app['state'] === 'stale' ? '<span class="down">old version</span>' : '<span class="down">never</span>') ?></div>
    <div class="d">running <?= e($app['version']) ?></div></div>
  <div class="kpi"><span class="kic">🧮</span><div class="k">Integrity checks</div>
    <div class="v"><?= $ready['check_failed'] ? '<span class="down">' . (int)$ready['check_failed'] . ' failed</span>'
      : (int)($ready['checks'] - $ready['check_skipped']) . ' pass' ?></div>
    <div class="d"><?= $ready['last_run'] ? 'last run ' . e(substr($ready['last_run']['ran_on'], 0, 10)) : 'never run' ?></div></div>
  <div class="kpi"><span class="kic">🔑</span><div class="k">Accounts with power</div>
    <div class="v"><?= (int)$ready['access']['admins'] ?></div>
    <div class="d">of <?= (int)$ready['access']['people'] ?> active</div></div>
  <div class="kpi"><span class="kic">💤</span><div class="k">Dormant accounts</div>
    <div class="v"><?= $ready['access']['dormant'] ? '<span class="down">' . (int)$ready['access']['dormant'] . '</span>' : '0' ?></div>
    <div class="d">no sign-in in 90 days</div></div>
  <div class="kpi"><span class="kic">💥</span><div class="k">Failures open</div>
    <div class="v"><?= $ready['failures_open'] ? '<span class="down">' . (int)$ready['failures_open'] . '</span>' : '0' ?></div>
    <div class="d"><?= (int)$ready['failures_unanswered'] ?> not yet answered for</div></div>
</div>

<?php // ---- 1 · Software validation ------------------------------------- ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>1 · Software validation <span class="muted">(§7.11)</span></h3></div>
  <?php if ($app['state'] !== 'ok'): ?>
    <p class="pill p-bad" style="display:inline-block;margin:0 0 10px">
      <?= $app['state'] === 'none'
        ? 'No validation record exists for this application at all.'
        : 'The last validation was of version ' . e($app['row']['version'] ?: 'unrecorded')
          . '. You are running ' . e($app['version']) . '.' ?>
      A body that validated version one and has upgraded since has not validated what it is using.</p>
  <?php else: ?>
    <p class="pill p-ok" style="display:inline-block;margin:0 0 10px">Version <?= e($app['version']) ?> was validated
      on <?= e(fdate($app['row']['validated_on'])) ?> by <?= e($app['row']['validated_by']) ?>.</p>
  <?php endif; ?>
  <p class="sub" style="margin-top:0">This is the one part of the clause software cannot do for you, and pretending
    otherwise would be the worst thing this screen could do. What it can do is tell you loudly when the version you
    are running has nothing against it. Validate the spreadsheet somebody built and the report template with a
    formula in it too — the clause covers those, not just the application.</p>
  <table class="dt">
    <thead><tr><th>Ref</th><th>What</th><th>Version</th><th>For what purpose</th><th>Result</th><th>Validated</th><th>Review by</th></tr></thead>
    <tbody>
    <?php foreach ($validations as $v): $due = $v['next_review'] !== '' && $v['next_review'] < date('Y-m-d'); ?>
      <tr>
        <td><strong><?= e($v['ref']) ?></strong></td>
        <td><?= e($v['component']) ?><div class="muted" style="font-size:12px"><?= e(SW_KINDS[$v['kind']] ?? $v['kind']) ?></div></td>
        <td class="muted"><?= e($v['version'] ?: '—') ?></td>
        <td style="max-width:240px"><?= e($v['purpose']) ?>
          <?php if ($v['limitations']): ?><div class="muted" style="font-size:12px">Limits: <?= e($v['limitations']) ?></div><?php endif; ?></td>
        <td><?= $v['result'] === 'FAIL' ? '<span class="pill p-bad">not fit</span>'
              : ($v['result'] === 'PARTIAL' ? '<span class="pill p-warn">with limits</span>' : '<span class="pill p-ok">fit</span>') ?></td>
        <td class="muted"><?= e(fdate($v['validated_on'])) ?><br><?= e($v['validated_by']) ?></td>
        <td><?= $due ? '<span class="pill p-bad">' . e(fdate($v['next_review'])) . '</span>' : e($v['next_review'] ? fdate($v['next_review']) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$validations): ?><tr><td colspan="7">Nothing validated. This is the first thing an assessor asks about under the 2026 edition.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canEdit): ?>
  <h3 class="tab-sub">Record a validation</h3>
  <form method="post" action="/sw-validation-add" class="inline-add">
    <div class="ff"><label>What kind</label>
      <select class="form-control" name="kind">
        <?php foreach (SW_KINDS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Component *</label>
      <input class="form-control" name="component" required value="<?= e(sw_app_component()) ?>"></div>
    <div class="ff"><label>Version</label>
      <input class="form-control" name="version" value="<?= e($app['version']) ?>"></div>
    <div class="ff"><label>Result</label>
      <select class="form-control" name="result">
        <?php foreach (SW_RESULTS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Validated on</label><input class="form-control" type="date" name="validated_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Review again by</label><input class="form-control" type="date" name="next_review" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>"></div>
    <div class="ff ff-wide"><label>What it is for *</label>
      <input class="form-control" name="purpose" required
             placeholder="e.g. recording results, issuing <?= e(Tlp('report')) ?>, and calculating job costs"></div>
    <div class="ff ff-wide"><label>What was tested *</label>
      <textarea class="form-control" name="what_tested" rows="3" required
                placeholder="the actual steps somebody carried out — an assessor reads this, not a tick"></textarea></div>
    <div class="ff ff-wide"><label>What was expected</label><textarea class="form-control" name="expected" rows="2"></textarea></div>
    <div class="ff ff-wide"><label>What actually happened</label><textarea class="form-control" name="observed" rows="2"></textarea></div>
    <div class="ff ff-wide"><label>Limitations</label>
      <input class="form-control" name="limitations" placeholder="anything it is NOT fit for — this is the honest half"></div>
    <div class="ff ff-wide"><label>Where the evidence is kept</label>
      <input class="form-control" name="evidence" placeholder="e.g. screenshots and test sheet in the quality folder"></div>
    <button class="btn small" type="submit">Record it</button>
  </form>
  <?php endif; ?>
</div>

<?php // ---- 2 · Integrity checks ---------------------------------------- ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>2 · Data integrity <span class="muted">(<?= (int)$ready['checks'] ?> checks)</span></h3>
    <?php if ($canEdit): ?>
    <form method="post" action="/data-check-run" style="margin:0">
      <button class="btn small" type="submit">Run them now</button></form>
    <?php endif; ?>
  </div>
  <p class="sub" style="margin-top:0">Every line below is a question with a right answer, asked of the live
    database. You can press the button in front of an assessor — which is a different kind of evidence from a
    signed statement that everything is fine.
    <?php if ($ready['run_stale']): ?><br><strong>No run on file inside the last <?= INTEGRITY_STALE_DAYS ?> days.</strong><?php endif; ?></p>
  <table class="dt">
    <thead><tr><th style="width:44px"></th><th>What is checked</th><th>Why it matters</th><th>Found</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
      <tr>
        <td><?= !empty($c['skipped']) ? '<span class="muted">–</span>'
              : ($c['ok'] ? '<span class="pill p-ok">ok</span>' : '<span class="pill p-bad">no</span>') ?></td>
        <td><strong><?= e($c['what']) ?></strong></td>
        <td class="muted" style="max-width:380px"><?= e($c['why']) ?></td>
        <td><?= !empty($c['skipped']) ? '<span class="muted">not in use</span>'
              : ($c['found'] ? '<strong class="down">' . (int)$c['found'] . '</strong>' : '0') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h3 class="tab-sub">Runs on file</h3>
  <table class="dt">
    <thead><tr><th>When</th><th>Who</th><th>Version</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($runs as $r): ?>
      <tr><td class="muted"><?= e(substr((string)$r['ran_on'], 0, 16)) ?></td>
        <td><?= e($r['ran_by'] ?: '—') ?></td>
        <td class="muted"><?= e($r['app_version']) ?></td>
        <td><?= $r['failed'] ? '<span class="pill p-bad">' . (int)$r['failed'] . ' failed of ' . (int)$r['total'] . '</span>'
              : '<span class="pill p-ok">all ' . (int)$r['total'] . ' passed</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$runs): ?><tr><td colspan="4">Never run. A check that was never recorded is not evidence of anything.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php // ---- 3 · Access ------------------------------------------------- ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>3 · Who can get at it</h3></div>
  <p class="sub" style="margin-top:0">Read from the permission engine, not from an org chart — this is what the
    software would actually let each person do today.</p>
  <table class="dt">
    <thead><tr><th>Who</th><th>Role</th><th>Last signed in</th><th>Two-step</th>
      <?php foreach (DC_POWERS as $p => $label): ?><th style="font-size:11px"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($access as $u): ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong><div class="muted" style="font-size:12px"><?= e($u['username']) ?></div></td>
        <td class="muted"><?= e($u['role']) ?><?= $u['master'] ? ' <span class="pill p-warn">everything</span>' : ' <span style="font-size:11px">· ' . (int)($u['perm_count'] ?? 0) . ' perms</span>' ?><?= !empty($u['toxic']) ? ' <span class="pill p-bad" title="Holds both sides of a maker/checker pair">SoD</span>' : '' ?></td>
        <td><?= $u['last_login'] === '' ? '<span class="pill p-warn">never</span>'
              : ($u['dormant'] ? '<span class="pill p-bad">' . (int)$u['days_idle'] . 'd ago</span>'
                               : e(substr($u['last_login'], 0, 10))) ?></td>
        <td><?= $u['two_step'] ? '<span class="pill p-ok">on</span>' : '<span class="muted">off</span>' ?></td>
        <?php foreach (DC_POWERS as $p => $label): ?>
          <td><?= !empty($u['powers'][$p]) ? '<span class="pill p-warn">yes</span>' : '<span class="muted">—</span>' ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php // Module 02 — segregation-of-duties flags: non-master people holding both sides of a maker/checker pair.
        $sod = array_values(array_filter($access, fn($u) => !empty($u['toxic']))); ?>
  <?php if ($sod): ?>
  <div class="panel" style="border-left:3px solid var(--bad);margin-top:12px">
    <h4 class="tab-sub" style="margin-top:0">Segregation-of-duties flags <span class="muted" style="font-weight:400;font-size:12px">(<?= count($sod) ?>)</span></h4>
    <p class="muted" style="margin:0 0 8px;font-size:12.5px">These people hold <strong>both sides</strong> of a maker/checker
      pair. Nothing is blocked — the runtime still stops a self-approval one transaction at a time; this is for an access
      review to decide whether the concentration of power is acceptable (a small office may have to double-hat).</p>
    <table class="dt"><thead><tr><th>Who</th><th>Holds both</th></tr></thead><tbody>
      <?php foreach ($sod as $u): ?>
        <tr><td><strong><?= e($u['name']) ?></strong> <span class="muted" style="font-size:12px"><?= e($u['username']) ?></span></td>
          <td><?php foreach ($u['toxic'] as $t): ?><div style="margin:2px 0"><span class="pill p-bad"><?= e($t['label']) ?></span></div><?php endforeach; ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <?php endif; ?>

  <small class="muted">Change any of it under <a href="/access">Settings → Roles &amp; access</a> or on the person’s
    own account. Fewer people holding each power is the whole point of the column. Every access change is now recorded in
    the compliance audit log.</small>
</div>

<?php // ---- 4 · Failures ----------------------------------------------- ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>4 · When it broke <span class="muted">(<?= count($failures) ?>)</span></h3></div>
  <p class="sub" style="margin-top:0">Entries marked <em>automatic</em> wrote themselves when the application hit a
    fatal error. A failure log somebody has to remember to write records only the failures they were in the mood to
    admit to. Every entry has to answer one question before it can be resolved: <strong>what did it do to the
    data?</strong></p>
  <table class="dt">
    <thead><tr><th>Ref</th><th>When</th><th>What failed</th><th>Data affected?</th><th>Corrective action</th><th>State</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($failures as $f): $miss = failure_close_missing($f); ?>
      <tr>
        <td><strong><?= e($f['ref']) ?></strong>
          <?php if ($f['source'] === 'AUTOMATIC'): ?><br><span class="pill">automatic</span><?php endif; ?></td>
        <td class="muted"><?= e(substr((string)$f['occurred_at'], 0, 16)) ?></td>
        <td style="max-width:300px"><?= e($f['summary']) ?>
          <?php if ($f['data_note']): ?><div class="muted" style="font-size:12px"><?= e($f['data_note']) ?></div><?php endif; ?></td>
        <td><?= $f['data_affected'] === 'UNKNOWN' ? '<span class="pill p-bad">not answered</span>'
              : ($f['data_affected'] === 'YES' ? '<span class="pill p-bad">yes</span>' : '<span class="pill p-ok">no</span>') ?></td>
        <td><?= $f['capa_ref'] ? e($f['capa_ref']) : '<span class="muted">—</span>' ?></td>
        <td><?= $f['status'] === 'RESOLVED' ? '<span class="pill p-ok">resolved</span>' : '<span class="pill p-warn">open</span>' ?></td>
        <?php if ($canEdit): ?>
        <td style="min-width:280px">
          <?php if ($f['status'] !== 'RESOLVED'): ?>
          <form method="post" action="/failure-update" style="display:flex;gap:4px;flex-wrap:wrap;margin:0 0 4px">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <select class="form-control" style="width:130px" name="data_affected">
              <?php foreach (FAILURE_DATA as $k=>$v): ?><option value="<?= e($k) ?>" <?= $f['data_affected']===$k?'selected':'' ?>><?= e(strtok($v,'—')) ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" style="width:140px" name="data_note" value="<?= e($f['data_note']) ?>" placeholder="what was affected">
            <input class="form-control" style="width:140px" name="immediate_action" value="<?= e($f['immediate_action']) ?>" placeholder="what was done">
            <button class="btn small secondary" type="submit">Save</button></form>
          <?php if (!$f['capa_ref'] && $f['data_affected'] === 'YES'): ?>
          <form method="post" action="/failure-capa" style="margin:0 0 4px">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn small" type="submit">Raise a corrective action</button></form>
          <?php endif; ?>
          <?php if ($miss): ?>
            <div class="muted" style="font-size:12px">Before resolving: <?= e(implode('; ', $miss)) ?>.</div>
          <?php else: ?>
          <form method="post" action="/failure-resolve" style="margin:0">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn small" type="submit">Mark resolved</button></form>
          <?php endif; ?>
          <?php else: ?><span class="muted"><?= e(fdate($f['resolved_on'])) ?></span><?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$failures): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>">Nothing logged. Entries appear here by themselves when the application fails.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canEdit): ?>
  <h3 class="tab-sub">Record a failure by hand</h3>
  <p class="sub" style="margin-top:0">For the ones the application cannot see itself — the host going down, a
    backup that would not restore, a file that came back corrupted.</p>
  <form method="post" action="/failure-add" class="inline-add">
    <div class="ff"><label>When</label><input class="form-control" type="datetime-local" name="occurred_at"
        value="<?= e(date('Y-m-d\TH:i')) ?>"></div>
    <div class="ff"><label>Data affected?</label>
      <select class="form-control" name="data_affected">
        <?php foreach (FAILURE_DATA as $k=>$v): ?><option value="<?= e($k) ?>" <?= $k==='UNKNOWN'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>What failed *</label>
      <input class="form-control" name="summary" required placeholder="in one line"></div>
    <div class="ff ff-wide"><label>Detail</label><textarea class="form-control" name="detail" rows="3"></textarea></div>
    <div class="ff ff-wide"><label>What was done at the time</label>
      <input class="form-control" name="immediate_action"></div>
    <button class="btn small secondary" type="submit">Record it</button>
  </form>
  <?php endif; ?>
</div>
