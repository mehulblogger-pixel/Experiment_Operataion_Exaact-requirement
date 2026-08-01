<?php
  $sel = (int)($_GET['i'] ?? 0);
  $selRow = null;
  foreach ($matrix as $r) if ((int)$r['inspector']['id'] === $sel) $selRow = $r;
?>
<div class="crumbs"><a href="/">Home</a> › Competence &amp; authorisation</div>
<div class="master-head">
  <div><h1>Competence &amp; authorisation</h1>
    <p class="sub" style="margin:2px 0 0">ISO/IEC 17020 §6.1 — what each person is <strong>qualified for</strong>,
      what the body <strong>permits them to do</strong>, and when they were last watched doing it.
      A qualification is what somebody has; an authorisation is what we allow.</p></div>
  <a class="btn secondary" href="/">← Back</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">👥</span><div class="k">Active <?= e(Tlp('engineer')) ?></div><div class="v"><?= (int)$ready['people'] ?></div></div>
  <div class="kpi"><span class="kic">✅</span><div class="k">With a live authorisation</div>
    <div class="v"><?= (int)$ready['authorised'] ?></div><div class="d"><?= (int)$ready['pct'] ?>% covered</div></div>
  <div class="kpi"><span class="kic">⛔</span><div class="k">Required certificate lapsed</div>
    <div class="v"><?= $ready['lapsed'] ? '<span class="down">' . (int)$ready['lapsed'] . '</span>' : '0' ?></div></div>
  <div class="kpi"><span class="kic">👁</span><div class="k">Witnessed check due</div>
    <div class="v"><?= (int)$ready['witness_due'] ?></div><div class="d">§6.1.8 monitoring</div></div>
</div>

<?php if ($canEdit): ?>
<div class="panel" style="<?= $ready['enforced'] ? 'border:1px solid var(--ok)' : 'border:1px dashed var(--line)' ?>">
  <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;justify-content:space-between">
    <div style="flex:1;min-width:280px">
      <b><?= $ready['enforced'] ? '✅ Enforcement is ON' : 'Enforcement is OFF' ?></b>
      <p class="muted" style="margin:4px 0 0">
        <?php if ($ready['enforced']): ?>
          Somebody with no live authorisation covering the work <strong>cannot be allocated</strong>.
        <?php else: ?>
          Authorisations are recorded but do not yet stop an allocation. Switch this on once the matrix below is
          populated — it is deliberately off by default, because switching it on with an empty matrix would stop
          every allocation in the company on the same afternoon.
        <?php endif; ?>
      </p>
    </div>
    <form method="post" action="/auth-enforce" style="margin:0">
      <input type="hidden" name="on" value="<?= $ready['enforced'] ? '0' : '1' ?>">
      <button class="btn<?= $ready['enforced'] ? ' secondary' : '' ?>" type="submit">
        <?= $ready['enforced'] ? 'Switch enforcement off' : 'Switch enforcement on' ?></button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>The matrix <span class="muted">(<?= count($matrix) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th><?= e(TH('engineer')) ?></th><th>Certificates</th><th>Authorised for</th>
      <th>Last witnessed</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($matrix as $r): $i = $r['inspector']; ?>
      <tr<?= $sel === (int)$i['id'] ? ' style="background:var(--soft)"' : '' ?>>
        <td><a href="/competence?i=<?= (int)$i['id'] ?>"><strong><?= e($i['name']) ?></strong></a>
            <?php if ($i['emp_code']): ?><span class="muted"><?= e($i['emp_code']) ?></span><?php endif; ?></td>
        <td><?= (int)$r['certs'] ?> held<?= $r['mandatory'] ? ', ' . (int)$r['mandatory'] . ' required' : '' ?>
            <?php if ($r['due_soon']): ?><span class="pill p-warn"><?= (int)$r['due_soon'] ?> due</span><?php endif; ?></td>
        <td><?= $r['scopes'] ? e(implode(', ', array_slice($r['scopes'], 0, 3)))
                              . (count($r['scopes']) > 3 ? ' +' . (count($r['scopes']) - 3) : '')
                            : '<span class="muted">nothing</span>' ?></td>
        <td><?= $r['witness'] ? e(fdate($r['witness']['assessed_on'])) : '<span class="muted">never</span>' ?>
            <?php if ($r['witness_due']): ?><span class="pill p-warn">due</span><?php endif; ?></td>
        <td><?php if ($r['lapsed']): ?><span class="pill p-bad">certificate lapsed</span>
            <?php elseif (!$r['auth_live']): ?><span class="pill p-mut">not authorised</span>
            <?php else: ?><span class="pill p-ok">authorised</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$matrix): ?><tr><td colspan="5">No active <?= e(Tlp('engineer')) ?> on record.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($selRow): $i = $selRow['inspector']; $auth = authorisations_for($i['id'], true); ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3><?= e($i['name']) ?> — authorisations</h3>
    <a href="/m/inspectors/edit?id=<?= (int)$i['id'] ?>">Their record →</a></div>
  <table class="grid">
    <tr><th>Level</th><th>Covers</th><th>From</th><th>To</th><th>State</th><th>Granted by</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr>
    <?php foreach ($auth as $a): $live = auth_live($a); ?>
      <tr<?= $live ? '' : ' class="muted"' ?>>
        <td><strong><?= e(AUTH_LEVELS[$a['level']] ?? $a['level']) ?></strong></td>
        <td><?= e(auth_scope_label($a)) ?></td>
        <td><?= e($a['valid_from'] ? fdate($a['valid_from']) : '—') ?></td>
        <td><?= e($a['valid_to'] ? fdate($a['valid_to']) : 'open') ?></td>
        <td><?= $live ? '<span class="pill p-ok">live</span>'
                      : '<span class="pill p-mut">' . e(AUTH_STATUS[$a['status']] ?? $a['status']) . '</span>' ?>
            <?php if ($a['status_reason']): ?><div class="muted" style="font-size:12px"><?= e($a['status_reason']) ?></div><?php endif; ?></td>
        <td class="muted"><?= e($a['granted_by']) ?></td>
        <?php if ($canEdit): ?>
        <td><form method="post" action="/auth-status" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;margin:0">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <select class="form-control" style="width:130px" name="status">
            <?php foreach (AUTH_STATUS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $a['status']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select>
          <input class="form-control" style="width:180px" name="reason" placeholder="why (required to change)">
          <button class="btn small secondary" type="submit">Apply</button></form></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$auth): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>">Nothing recorded — this person is not authorised for any work.</td></tr><?php endif; ?>
  </table>

  <?php if ($canEdit): ?>
  <h3 class="tab-sub">Grant an authorisation</h3>
  <form method="post" action="/auth-add" class="inline-add">
    <input type="hidden" name="inspector_id" value="<?= (int)$i['id'] ?>">
    <div class="ff"><label>Level</label>
      <select class="form-control" name="level">
        <?php foreach (AUTH_LEVELS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $k==='INSPECTOR'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Covers</label>
      <select class="form-control" name="scope_kind" id="sk" onchange="document.getElementById('sv').style.display=this.value==='ANY'?'none':'block'">
        <?php foreach (AUTH_SCOPES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff" id="sv" style="display:none"><label>Which one</label>
      <input class="form-control" name="scope_value" list="scopelist" placeholder="type of inspection code, activity id or client id">
      <datalist id="scopelist">
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </datalist></div>
    <div class="ff"><label>From</label><input class="form-control" type="date" name="valid_from" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>To</label><input class="form-control" type="date" name="valid_to">
      <small class="muted">Blank = open-ended.</small></div>
    <div class="ff"><label>Basis <span class="muted">— what it rests on</span></label>
      <select class="form-control" name="basis">
        <?php foreach (AUTH_BASES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Evidence reference</label><input class="form-control" name="basis_ref" placeholder="certificate no., assessment date, file"></div>
    <div class="ff"><label>Review every (months)</label><input class="form-control" type="number" min="0" max="120" name="review_months" placeholder="0 = no review cycle"></div>
    <div class="ff"><label>Re-witness every (months)</label><input class="form-control" type="number" min="0" max="120" name="witness_every_months" placeholder="0 = not scheduled"></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" placeholder="anything else worth recording"></div>
    <button class="btn small" type="submit">Grant</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Witnessed assessments <span class="muted">§6.1.8</span></h3></div>
  <p class="sub" style="margin-top:0">Watching somebody actually do the job is the monitoring the standard asks for.
    A result other than "competent" <strong>suspends their authorisations</strong> — that is the point of it.</p>
  <table class="grid">
    <tr><th>Date</th><th>Assessor</th><th>Where</th><th>Scores</th><th>Outcome</th><th>Next due</th></tr>
    <?php foreach (witness_for($i['id']) as $w): $sc = json_decode($w['scores'] ?: '[]', true) ?: []; ?>
      <tr><td><?= e(fdate($w['assessed_on'])) ?></td><td><?= e($w['assessor']) ?></td>
        <td><?= e($w['location'] ?: '—') ?></td>
        <td><?= $sc ? e(round(array_sum($sc) / max(1, count($sc)), 1)) . ' / 5' : '—' ?></td>
        <td><?= $w['outcome']==='PASS' ? '<span class="pill p-ok">competent</span>'
                                       : '<span class="pill p-bad">' . e(WITNESS_OUTCOME[$w['outcome']] ?? $w['outcome']) . '</span>' ?></td>
        <td><?= e($w['next_due'] ? fdate($w['next_due']) : '—') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!witness_for($i['id'])): ?><tr><td colspan="6">Never witnessed. §6.1.8 expects periodic monitoring.</td></tr><?php endif; ?>
  </table>

  <?php if ($canEdit): ?>
  <h3 class="tab-sub">Record a witnessed assessment</h3>
  <form method="post" action="/witness-add" class="inline-add">
    <input type="hidden" name="inspector_id" value="<?= (int)$i['id'] ?>">
    <div class="ff"><label>Assessed on</label><input class="form-control" type="date" name="assessed_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Where</label><input class="form-control" name="location" placeholder="site / works"></div>
    <?php foreach (WITNESS_CRITERIA as $k=>$lbl): ?>
      <div class="ff"><label><?= e($lbl) ?></label>
        <select class="form-control" name="s[<?= e($k) ?>]">
          <option value="0">—</option>
          <?php for ($n=1;$n<=5;$n++): ?><option value="<?= $n ?>" <?= $n===4?'selected':'' ?>><?= $n ?></option><?php endfor; ?>
        </select></div>
    <?php endforeach; ?>
    <div class="ff"><label>Outcome</label>
      <select class="form-control" name="outcome">
        <?php foreach (WITNESS_OUTCOME as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Next due</label><input class="form-control" type="date" name="next_due"></div>
    <div class="ff ff-wide"><label>Remarks</label><input class="form-control" name="remarks"></div>
    <?php // Record once. A competent result can grant the authorisation it
          // supports in the same step, so competence is not typed twice. ?>
    <div class="ff ff-wide" style="border-top:1px solid var(--line);padding-top:10px;margin-top:2px">
      <label class="chk"><input type="checkbox" name="grant_auth" value="1" id="grant_auth" onchange="document.getElementById('grantfields').style.display=this.checked?'contents':'none'">
        Grant an authorisation from this assessment <span class="muted">— only if the outcome is "competent"</span></label></div>
    <span id="grantfields" style="display:none">
      <div class="ff"><label>Authorise as</label>
        <select class="form-control" name="level">
          <?php foreach (AUTH_LEVELS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $k==='INSPECTOR'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Covers</label>
        <select class="form-control" name="scope_kind" id="wsk" onchange="document.getElementById('wsv').style.display=this.value==='ANY'?'none':'block'">
          <?php foreach (AUTH_SCOPES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff" id="wsv" style="display:none"><label>Which one</label>
        <input class="form-control" name="scope_value" placeholder="type/activity/client"></div>
      <div class="ff"><label>Review every (months)</label><input class="form-control" type="number" min="0" max="120" name="review_months" value="12"></div>
      <div class="ff"><label>Re-witness every (months)</label><input class="form-control" type="number" min="0" max="120" name="witness_every_months" value="12"></div>
    </span>
    <button class="btn small" type="submit">Record assessment</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
