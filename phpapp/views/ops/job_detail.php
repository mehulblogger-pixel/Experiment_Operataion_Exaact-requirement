<?php $lock = job_lock_state($job); ?>
<?= function_exists('chain_strip') ? chain_strip('JOB', (int)$job['id'], 'JOB', (int)$job['id']) : '' ?>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('job', $job['job_code'])) ?></h1>
    <p class="sub"><?= e($job['client_disp'] ?: $job['client_name'] ?: '—') ?> · <?= e($job['inspector_name'] ?: 'Unassigned') ?></p></div>
  <div class="row-actions">
    <?php if (!$job['closed_flag'] && !$lock['locked']): ?><a class="btn" href="/job-close?id=<?= (int)$job['id'] ?>">Close job</a><?php endif; ?>
    <?php if (can('mod.idems.edit') || is_master()): ?><a class="btn secondary" href="/document-new?job=<?= (int)$job['id'] ?><?= $job['call_id'] ? '&call='.(int)$job['call_id'] : '' ?>" title="Create an inspection report — all known details are filled in">📑 New report</a><?php endif; ?>
    <?php if (is_coordinator_level() && !$job['closed_flag'] && !$lock['locked']): ?><a class="btn secondary" href="/job-edit?id=<?= (int)$job['id'] ?>">Edit</a><?php endif; ?>
    <?php if ($job['call_id']): ?><a class="btn secondary" href="/call?id=<?= (int)$job['call_id'] ?>">View call</a><?php endif; ?>
  </div>
</div>

<?php // ---- Where this job stands, and the one next thing ------------------- ?>
<?php
  $jClosed   = !empty($job['closed_flag']);
  $jSched    = trim((string)($job['scheduled_date'] ?? '')) !== '';
  $jReports  = (int) ops_val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND deleted=0", [(int)$job['id']]);
  $jOnSite   = false;
  if (function_exists('site_visit_window')) { $jw = site_visit_window((int)$job['id']); $jOnSite = ($jw['minutes'] ?? null) !== null; }
  $jCanClose = !$jClosed && empty($lock['locked']);
?>
<div class="nowband">
  <?php if ($jClosed): ?>
    <div class="step">Done — this <?= e(Tl('job')) ?> is closed.</div>
    <p class="next">The report and photographs can still be uploaded if something was missed. Everything else is fixed.</p>
  <?php elseif ($jReports > 0): ?>
    <div class="step">The report is written.</div>
    <p class="next"><b>Next:</b> issue it to the <?= e(Tl('client')) ?>, then close the <?= e(Tl('job')) ?>.</p>
    <?php if ($jCanClose): ?><div class="cta"><a class="btn small" href="/job-close?id=<?= (int)$job['id'] ?>">Close the <?= e(Tl('job')) ?> →</a></div><?php endif; ?>
  <?php elseif ($jOnSite): ?>
    <div class="step">Inspection done on site.</div>
    <p class="next"><b>Next:</b> write the report below, then close the <?= e(Tl('job')) ?>.</p>
    <div class="cta"><a class="btn small secondary" href="#reports">Go to the report</a></div>
  <?php elseif ($jSched): ?>
    <div class="step">Scheduled for <?= e($job['scheduled_date']) ?>.</div>
    <p class="next"><b>Next:</b> the engineer goes to site and taps <b>Record this</b> on arrival (below).</p>
    <div class="cta"><a class="btn small secondary" href="#checkin">Site check-in</a></div>
  <?php else: ?>
    <div class="step">Not scheduled yet.</div>
    <p class="next"><b>Next:</b> set the visit date so the engineer knows when to go.<?php if (is_coordinator_level() && $jCanClose): ?> Use <b>Edit</b> above.<?php endif; ?></p>
  <?php endif; ?>
</div>

<?php // ---- Everything on this job, at a glance — one index, jump anywhere ---- ?>
<?php if (!empty($glance)): ?>
<div class="panel job-glance" style="margin-top:12px;padding:10px 12px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span class="muted" style="font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">On this <?= e(Tl('job')) ?></span>
    <?php foreach ($glance as $g): $has = (int)$g['n'] > 0; $link = ($g['href'] ?? '') !== ''; ?>
      <?php if ($link): ?>
        <a href="<?= e($g['href']) ?>" class="pill <?= $has ? 'p-info' : 'p-mut' ?>" style="text-decoration:none;font-size:12px<?= $has ? '' : ';opacity:.6' ?>">
          <b><?= (int)$g['n'] ?></b> <?= e($g['label']) ?>
        </a>
      <?php else: ?>
        <span class="pill <?= $has ? 'p-info' : 'p-mut' ?>" style="font-size:12px<?= $has ? '' : ';opacity:.6' ?>">
          <b><?= (int)$g['n'] ?></b> <?= e($g['label']) ?>
        </span>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (can('mod.idems.edit') || is_master()): ?>
      <a href="/document-new?job=<?= (int)$job['id'] ?><?= $job['call_id'] ? '&call='.(int)$job['call_id'] : '' ?>" class="pill p-ok" style="text-decoration:none;font-size:12px">+ New <?= e(Tl('report')) ?></a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php // The whole record below is organised into tabs (app.js → initSectionTabs):
      // panels sharing a data-tab label become one tab, so this long screen reads
      // as Overview · Schedule & site · Reports & QA · Money instead of one endless
      // scroll. Tabs wrap so every one is visible without scrolling. ?>
<?php // A field engineer (role INSPECTOR) gets a phone-first, stripped view: their
      // work is the site check-in, the report and the QAP — not the desk/commercial
      // panels (the communication log, the contract-number gap notice, the client
      // bills, the expenses/profitability fold, invoicing). Those are HIDDEN for the
      // inspector, not merely collapsed — hiding them is what actually clears the
      // screen for someone standing at a plant gate on a phone. When there is
      // nothing left on a tab (e.g. Money), that whole tab stops appearing. Every
      // coordinator/manager, and anyone holding the money permissions, still sees
      // the full record. (The inspector's own travel/expenses guidance still lives
      // on the Site check-in panel, so no guidance is lost by dropping the Money tab.)
      $fieldInspector = function_exists('is_field_inspector') ? is_field_inspector() : (function_exists('is_inspector') && is_inspector()); ?>
<div data-tabs data-tabs-key="job">

<?php // The coordinator/manager console — guided playbook, assignment lifecycle
      // (hold/reassign/reschedule/cancel/no-show) and readiness/confirmation.
      // This is operations management, not the field engineer's view: an
      // inspector who cannot manage operations does not see it at all. Their
      // Overview is the contact/site card and the record below; their work is on
      // the Schedule & site and Reports & QA tabs.
      if (function_exists('tosrm_can_edit') && tosrm_can_edit()): ?>
  <?php if (function_exists('tosrm_render_playbook')): ?>
    <div data-tab="Overview"><a id="playbook"></a><?php tosrm_render_playbook($job); ?></div>
  <?php endif; ?>
  <?php // Assignment lifecycle: hold, acceptance, reassignment / reschedule /
        // cancel / no-show, all with kept history. Collapsed to keep Overview short.
        if (function_exists('tosrm_render_job_panel')): ?>
    <details class="fold" data-tab="Overview"><a id="assign"></a>
      <summary>Assignment lifecycle <span class="sub">hold, reschedule, reassign, no-show — with history</span></summary>
      <div class="fold-body"><?php tosrm_render_job_panel($job); ?></div>
    </details>
  <?php endif; ?>
  <?php // Readiness, client/vendor confirmation, and competence-at-allocation.
        if (function_exists('tosrm_render_readiness_panel')): ?>
    <details class="fold" data-tab="Overview"><a id="ready"></a>
      <summary>Readiness &amp; confirmation <span class="sub">client / vendor confirmation &amp; the pre-execution checklist</span></summary>
      <div class="fold-body"><?php tosrm_render_readiness_panel($job); ?></div>
    </details>
  <?php endif; ?>
<?php endif; ?>
<?php // Phase 9 (TOSRM Slice E) — communication log on the job. A management panel
      // (client/coordinator correspondence), not field work — hidden from the inspector.
      if (function_exists('tosrm_render_comms') && !$fieldInspector): ?>
  <details class="fold" data-tab="Overview"><a id="comms"></a>
    <summary>Communication log <span class="sub">calls, e-mails &amp; meetings on this <?= e(Tl('job')) ?></span></summary>
    <div class="fold-body"><?php tosrm_render_comms('JOB', (int)$job['id']); ?></div>
  </details>
<?php endif; ?>

<?php // ---- Day-by-day plan & reshuffle -----------------------------------
      // A multi-day deputation can carry a different engineer on different days.
      // This shows the plan and lets a coordinator swap who goes on any one day
      // — the morning somebody calls in sick — without touching the rest of the
      // job. Only shown when there is genuinely more than one visit day.
      $vp = $visitPlan ?? [];
      $canReshuffle = !$job['closed_flag'] && is_coordinator_level();
      if (count($vp) > 1): ?>
<div class="panel" id="visits" data-tab="Schedule &amp; site">
  <div class="ctitle" style="margin-top:0"><h3>Who goes on each day
    <span class="muted">(<?= count($vp) ?> <?= count($vp) === 1 ? 'day' : 'days' ?>)</span></h3></div>
  <?php $vpClash = count(array_filter($vp, fn($x) => $x['busy'] !== '')); ?>
  <?php if ($vpClash): ?>
    <div class="msg msg-warning" style="margin-top:0"><?= (int)$vpClash ?>
      day(s) have the assigned <?= e(Tlp('engineer')) ?> booked elsewhere — reassign below.</div>
  <?php endif; ?>
  <form method="post" action="/job-reassign?id=<?= (int)$job['id'] ?>">
    <table class="grid">
      <tr><th>Date</th><th><?= e(ucfirst(Tl('engineer'))) ?></th><th>Status</th></tr>
      <?php foreach ($vp as $x): ?>
        <tr>
          <td><?= e($x['weekday']) ?> <?= e($x['pretty']) ?></td>
          <td>
            <?php if ($canReshuffle): ?>
              <select class="form-control" name="visit_inspector[<?= e($x['date']) ?>]">
                <?php foreach (($inspectors ?? []) as $i): ?>
                  <option value="<?= (int)$i['id'] ?>" <?= (int)$i['id'] === (int)$x['inspector_id'] ? 'selected' : '' ?>>
                    <?= e($i['name']) ?><?= !empty($i['emp_code']) ? ' (' . e($i['emp_code']) . ')' : '' ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?><?= e($x['inspector_name'] ?: '—') ?><?php endif; ?>
          </td>
          <td>
            <?php if (!$x['working']): ?><span class="pill p-mut">non-working day</span>
            <?php elseif ($x['busy'] !== ''): ?><span class="pill p-bad"><?= e($x['busy']) ?></span>
            <?php else: ?><span class="pill p-ok">free</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($canReshuffle): ?>
      <div style="margin-top:12px"><button class="btn" type="submit">Update day-by-day plan</button>
        <span class="muted" style="margin-left:8px">Changing a day here does not change the job's main <?= e(Tl('engineer')) ?>.</span></div>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<?php // ---- Phase 7 (PDSO): deputation & site-operations panel — only for a
      // deputation posting; reuses this job, adds the site-ops layer.
      if (!empty($dep)) require __DIR__ . '/_deputation_panel.php'; ?>

<?php // ---- §WO-8: day-by-day completion — close each visit with its report
      if (count($vp) > 1 && empty($job['closed_flag'])): ?>
<div class="panel" id="visit-close" data-tab="Schedule &amp; site">
  <div class="ctitle" style="margin-top:0"><h3>Day-by-day completion
    <span class="muted">— close each visit with its report; the <?= e(Tl('job')) ?> closes once every working day is done</span></h3></div>
  <table class="grid">
    <tr><th>Date</th><th><?= e(ucfirst(Tl('engineer'))) ?></th><th>Status</th><th>Report &amp; close</th></tr>
    <?php foreach ($vp as $x): ?>
      <tr>
        <td><?= e($x['weekday']) ?> <?= e($x['pretty']) ?></td>
        <td><?= e($x['inspector_name'] ?: '—') ?></td>
        <td><?php if (!$x['working']): ?><span class="pill p-mut">non-working</span>
            <?php elseif (!empty($x['done'])): ?><span class="pill p-ok">closed</span>
            <?php else: ?><span class="pill" style="background:#b45309;color:#fff">open</span><?php endif; ?></td>
        <td><?php if (!empty($x['done'])): ?>
              <?php if (!empty($x['report_link'])): ?><a href="<?= e($x['report_link']) ?>" target="_blank" rel="noopener">report</a>
              <?php else: ?><span class="muted">report on file</span><?php endif; ?>
              <?php if (!empty($x['closed_by'] ?? '')): ?> <span class="muted" style="font-size:11px">by <?= e($x['closed_by']) ?></span><?php endif; ?>
            <?php elseif ($x['working'] && is_coordinator_level()): ?>
              <form method="post" action="/job-visit-close?id=<?= (int)$job['id'] ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="date" value="<?= e($x['date']) ?>">
                <input class="form-control" name="report_link" placeholder="report link — or leave blank to use a report already on this <?= e(Tl('job')) ?>" style="width:300px">
                <button class="btn small" type="submit">Close day</button>
              </form>
            <?php else: ?><span class="muted">—</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php // ---- Site check-in -------------------------------------------------
      // The engineer photographs the work at the plant, drives home, and writes
      // the report that evening. So neither the report nor the upload says
      // anything about where the inspection happened. This does: one tap, ON
      // SITE, at the time. It is what the photographs are later compared with,
      // and it is the fallback for a phone that strips location from its
      // pictures — which many corporate phones do.
      $visits = function_exists('site_visits') ? site_visits((int)$job['id']) : [];
      $canCheckIn = !$job['closed_flag'] && function_exists('site_checkin')
          && (is_coordinator_level() || ($fieldInspector
              && (int)(current_user()['inspector_id'] ?? 0) === (int)($job['inspector_id'] ?? 0))); ?>
<div class="panel" id="checkin" data-tab="Schedule &amp; site">
  <div class="ctitle" style="margin-top:0"><h3>Site check-in <span class="muted">(<?= count($visits) ?>)</span></h3></div>
  <?php // The timesheet is not typed — it is built from these punches. Say so here,
        //  so an engineer knows that checking IN and OUT on site is how their hours
        //  are recorded. Coordinators/managers get a link to the assembled sheet. ?>
  <p class="muted" style="font-size:12px;margin:0 0 10px">
    Check <b>in</b> when you reach site and <b>out</b> when you leave — this is how your
    time is recorded. Your <?= e(Tl('report')) ?> and closure days come from here, and the
    <?php if (function_exists('timesheet_can') && timesheet_can()): ?><a href="/timesheet?ins=<?= (int)($job['inspector_id'] ?? 0) ?>">monthly timesheet</a><?php else: ?>monthly timesheet<?php endif; ?>
    is built automatically from these punches. (Travel &amp; expenses go on your <a href="/vouchers">voucher</a>.)
  </p>
  <?php if (function_exists('geofence_on') && geofence_on()): $gt = geofence_target($job); ?>
    <?php if ($gt): ?>
      <div class="msg msg-info" style="margin-top:0;margin-bottom:10px">📍 Punch-in is fenced to <b><?= e($gt['label']) ?></b> —
        only works within <b><?= (int)$gt['radius'] ?> m</b> of the site, and a photo is required.
        <?php if ($gt['source'] === 'party'): ?><span class="muted">(using the party's location)</span><?php endif; ?></div>
    <?php else: ?>
      <div class="msg msg-warning" style="margin-top:0;margin-bottom:10px">Geofencing is on but this job has no site location set, so punch-in is not fenced. Set one below.</div>
    <?php endif; ?>
    <?php if (is_coordinator_level() || is_master()): ?>
      <details style="margin-bottom:12px"><summary style="cursor:pointer;font-size:13px" class="muted">Set / adjust this job's exact site location</summary>
        <div style="margin-top:8px;max-width:520px"><?= geofence_editor('job-geo', (int)$job['id'],
            ['lat'=>$job['site_lat'] ?? null, 'lon'=>$job['site_lon'] ?? null, 'rad'=>(int)($job['site_geofence_m'] ?? 0), 'label'=>$job['site_label'] ?? ''], true) ?></div>
      </details>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($visits): ?>
    <table class="dt">
      <thead><tr><th>What / when</th><th>Where</th><th>Accuracy</th><th>Photo</th><th>Who</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($visits as $v): ?>
        <tr><td class="muted"><strong><?= e(VISIT_KINDS[$v['kind'] ?? 'ENTRY'] ?? $v['kind']) ?></strong><br><?= e(substr((string)$v['at'], 0, 16)) ?></td>
          <td><?php if ($v['lat'] !== null): ?>
                <a href="<?= e(geo_map_url($v['lat'], $v['lon'])) ?>" target="_blank" rel="noopener"><?= e(geo_pretty($v['lat'], $v['lon'])) ?></a>
              <?php else: ?>—<?php endif; ?></td>
          <td class="muted"><?= $v['accuracy'] !== null ? e(round((float)$v['accuracy'])) . ' m' : '—' ?></td>
          <td><?= !empty($v['photo_name'])
                ? '<a href="/checkin-photo?id=' . (int)$v['id'] . '" target="_blank" rel="noopener">photo</a>'
                : '<span class="muted">—</span>' ?></td>
          <td><?= e($v['by_user']) ?></td>
          <td class="muted"><?= e($v['note']) ?>
            <?php foreach (array_filter(explode(',', (string)$v['flags'])) as $fl): ?>
              <span class="pill p-warn"><?= e(EV_FLAGS[$fl] ?? $fl) ?></span><?php endforeach; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="sub" style="margin-top:0">Nothing recorded on site yet.</p>
  <?php endif; ?>
  <?php $win = function_exists('site_visit_window') ? site_visit_window((int)$job['id']) : ['minutes'=>null];
        if ($win['minutes'] !== null): ?>
    <p class="pill p-ok" style="display:inline-block;margin:0 0 8px">On site
      <?= e(substr((string)$win['in']['at'], 11, 5)) ?> to <?= e(substr((string)$win['out']['at'], 11, 5)) ?>
      — <?= (int)floor($win['minutes'] / 60) ?> h <?= (int)($win['minutes'] % 60) ?> min.</p>
  <?php endif; ?>
  <?php $ciMiss = function_exists('site_visit_close_missing') ? site_visit_close_missing($job) : [];
        if ($ciMiss): ?>
    <p class="pill p-warn" style="display:inline-block;margin:0 0 8px">Before this
      <?= e(Tl('job')) ?> can be closed it needs <?= e(implode(' and ', $ciMiss)) ?>.</p>
  <?php endif; ?>
  <?php if ($canCheckIn): ?>
  <form method="post" action="/site-checkin" id="ciForm" enctype="multipart/form-data"
        style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:8px">
    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
    <input type="hidden" name="gps" id="ciGps">
    <input type="hidden" name="device_at" id="ciAt">
    <div class="ff" style="margin:0"><label>What</label>
      <select class="form-control" name="kind">
        <?php foreach (VISIT_KINDS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <?php // capture="environment" opens the rear camera straight away on a
          // phone, and falls back to an ordinary file chooser on a laptop —
          // which is why this needs no Android app to work in the field. ?>
    <div class="ff" style="margin:0"><label>Photograph<?= checkin_photo_required() ? ' *' : ' (optional)' ?></label>
      <input class="form-control" type="file" name="photo" accept="image/*" capture="environment"></div>
    <div class="ff" style="margin:0;min-width:220px"><label>Note</label>
      <input class="form-control" name="note" placeholder="gate number, unit, who you met"></div>
    <button class="btn" type="button" id="ciBtn">📍 Record this</button>
    <span class="muted" id="ciMsg" style="font-size:13px"></span>
  </form>
  <small class="muted">The time is <strong>ours, not the phone's</strong> — a device clock can be changed, and a
    browser cannot read the mobile network's clock, so the server stamps it. The location is the browser's fix at
    the moment you press the button, which for a check-in really is where you are standing. Writing the report at
    home later changes none of this.</small>
  <script>
  (function(){
    var b=document.getElementById('ciBtn'), m=document.getElementById('ciMsg');
    if(!b) return;
    b.addEventListener('click', function(){
      if(!navigator.geolocation){ m.textContent='This browser cannot give a location.'; return; }
      b.disabled=true; m.textContent='Getting your location…';
      navigator.geolocation.getCurrentPosition(function(p){
        document.getElementById('ciGps').value=[p.coords.latitude,p.coords.longitude,p.coords.accuracy].join(',');
        document.getElementById('ciAt').value=new Date().toISOString();
        document.getElementById('ciForm').submit();
      }, function(err){
        b.disabled=false;
        m.textContent='No location: '+(err && err.message ? err.message : 'permission refused')+'.';
      }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
    });
  })();
  </script>
  <?php endif; ?>
</div>

<?php // The deadline, said out loud while there is still time to act on it —
      // and after, said plainly, with what can still be done. ?>
<?php if ($lock['locked']): ?>
  <div class="msg-error">
    <strong>🔒 Locked.</strong> <?= e(job_lock_text($lock)) ?>
    Dates, man-days, expenses and credit are fixed as they stand.
    <strong>Reports and photographs can still be uploaded</strong> — that is not blocked.
    <?php if (can_unlock_job()): ?>
      <form method="post" action="/job-unlock?id=<?= (int)$job['id'] ?>" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <div class="ff" style="margin:0;min-width:240px"><label>Why is it being reopened?</label>
          <input class="form-control" type="text" name="reason" required placeholder="e.g. client confirmed the second visit date late"></div>
        <div class="ff" style="margin:0;width:120px"><label>For how many days</label>
          <input class="form-control" type="number" name="days" value="3" min="1" max="30"></div>
        <button class="btn" type="submit">Reopen it</button>
      </form>
      <p class="muted" style="margin-top:6px">The reason and your name are kept with the <?= e(Tl('job')) ?> and written to the audit trail.</p>
    <?php else: ?>
      <br>Ask a manager to reopen it if a figure genuinely has to be corrected.
    <?php endif; ?>
  </div>
<?php elseif ($lock['unlocked']): ?>
  <div class="msg-warning"><strong>Reopened until <?= e($job['unlock_until']) ?>.</strong>
    <?= e($job['unlock_reason']) ?> — reopened by <?= e($job['unlocked_by']) ?>.
    Close it before then or it locks again.</div>
<?php elseif (!$job['closed_flag'] && $lock['left'] !== null && $lock['left'] <= 3): ?>
  <div class="msg-warning"><strong><?= e(job_lock_text($lock)) ?></strong>
    After that nothing on it can be changed, and your manager is told.</div>
<?php endif; ?>

<?php
  // ---- Who to call, and where to go ---------------------------------------
  // The engineer is standing at a gate. Everything needed to get in and to
  // reach somebody is here, on the job itself, tap-to-dial and tap-to-mail —
  // it used to exist only in the assignment e-mail, or not at all.
  $cRow = function ($info, $kind) {
    if (!$info) return '';
    $out = '';
    $contacts = array_slice($info['contacts'] ?? [], 0, 3);
    foreach ($contacts as $c) {
      $nm = trim((string)($c['name'] ?? ''));
      if ($nm === '') continue;
      $mob = trim((string)(($c['mobile'] ?? '') ?: ($c['phone'] ?? '')));
      $em  = trim((string)($c['email'] ?? ''));
      $out .= '<div class="ct-row"><b>' . e($nm) . '</b>';
      if (!empty($c['designation'])) $out .= ' <span class="muted">' . e($c['designation']) . '</span>';
      if (!empty($c['is_primary'])) $out .= ' <span class="pill p-info">primary</span>';
      $out .= '<div class="ct-links">';
      $out .= $mob !== '' ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $mob)) . '">📞 ' . e($mob) . '</a>' : '<span class="muted">no number</span>';
      $out .= $em !== '' ? ' <a href="mailto:' . e($em) . '">✉️ ' . e($em) . '</a>' : '';
      $out .= '</div></div>';
    }
    return $out ?: '<p class="muted" style="margin:0">No contact on file for this ' . e($kind) . '.</p>';
  };
  $addrLine = function ($a) {
    if (!$a) return '';
    $bits = array_filter([$a['line1'] ?? '', $a['line2'] ?? '', $a['city'] ?? '', $a['state'] ?? '', $a['pincode'] ?? '']);
    return implode(', ', $bits);
  };
  // The site: the address named on the call, else the vendor's primary address,
  // else the client's — whichever is the best answer we actually have.
  $site = $siteAddr;
  if (!$site && !empty($vendorInfo['addresses'])) $site = $vendorInfo['addresses'][0];
  if (!$site && !empty($clientInfo['addresses'])) $site = $clientInfo['addresses'][0];
  $siteTxt = $addrLine($site);
?>
<div class="panel" data-tab="Overview">
  <h3 class="tab-sub" style="margin-top:0">Who to contact, and where to go</h3>
  <div class="panel-split">
    <div>
      <span class="lab-sm"><?= e(TH('client')) ?> — <?= e($job['client_disp'] ?: $job['client_name'] ?: '—') ?></span>
      <?= $cRow($clientInfo, Tl('client')) ?>
    </div>
    <div>
      <span class="lab-sm"><?= e(TH('vendor')) ?> / site — <?= e($job['vendor_name'] ?: '—') ?></span>
      <?= $cRow($vendorInfo, Tl('vendor')) ?>
    </div>
  </div>
  <div class="ct-site">
    <span class="lab-sm">Site address</span>
    <?php if ($siteTxt !== ''): ?>
      <div><?= e($siteTxt) ?>
        <a class="btn small secondary" target="_blank" rel="noopener"
           href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($siteTxt) ?>">🗺 Map</a></div>
    <?php else: ?>
      <p class="muted" style="margin:0">No site address recorded on the <?= e(Tl('call')) ?> or against the <?= e(Tl('vendor')) ?>.</p>
    <?php endif; ?>
    <?php if (!empty($jcall['folder_link'])): ?>
      <div style="margin-top:6px"><a href="<?= e($jcall['folder_link']) ?>" target="_blank" rel="noopener">🔗 Drawings &amp; client papers</a></div>
    <?php endif; ?>
  </div>
</div>
<style>
  .lab-sm{display:block;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;
    letter-spacing:.4px;margin-bottom:6px}
  .ct-row{padding:7px 0;border-bottom:1px solid var(--line)}
  .ct-row:last-child{border-bottom:0}
  .ct-links{display:flex;gap:12px;flex-wrap:wrap;margin-top:2px;font-size:13.5px}
  .ct-site{margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
</style>

<?php // §10 — the same standing notice the call carries. Work is never blocked
      // for want of a contract number, but it stays visible on the deputation
      // too, because this is the screen the coordinator and the engineer live
      // on once the call has been allocated.
      $jgap = ($jcall && function_exists('call_contract_gap')) ? call_contract_gap($jcall) : null; ?>
<?php // A commercial/coordinator notice the field engineer can neither cause nor
      // clear — hidden from the inspector so it does not read as a blocker on work
      // that is never actually blocked for want of a contract number.
      if ($jgap && !$fieldInspector): ?>
<div class="panel" data-tab="Schedule &amp; site" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
  <b style="color:var(--warn)">⚠ Contract number not available</b>
  <div class="muted" style="margin-top:4px"><?= e($jgap['text']) ?></div>
  <?php if (!empty($jgap['quote_id']) && (can('crm.contract.register') || is_master())): ?>
    <div style="margin-top:8px"><a class="btn small" href="/quote?id=<?= (int)$jgap['quote_id'] ?>#contract">Register the contract number</a></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // §v — the formats the order promised are the formats this engineer is
      // asked for, and each one is one click from being written. A deliverable
      // with nothing against it is work still owed; the reporting module's own
      // status is shown for the rest, so nobody has to go and look. ?>
<?php
  $dlCodes = trim((string)($job['deliverables'] ?? '')) !== '' ? array_filter(array_map('trim', explode(',', $job['deliverables']))) : [];
  $dlMap = deliverable_options();
  $jobDocs = [];
  // Loaded whether or not formats were agreed — a report written against a
  // deputation with no agreed format still has to be findable from it.
  foreach (ops_all(
      "SELECT id, irn, type_code, status, finalized FROM report_docs WHERE job_id=? AND deleted=0 ORDER BY id", [(int)$job['id']]) as $rd)
      $jobDocs[$rd['type_code']][] = $rd;
  // Anything already written under a format nobody ticked, so it is still listed.
  $extraCodes = array_values(array_diff(array_keys($jobDocs), $dlCodes));
  $canWrite = can('mod.idems.edit') || is_master();
?>
<?php // §R1-D — Quality Assurance Plans for this job. A PO may bring one QAP or
      // many (one per line item). They are attached as-is (usually PDF), never
      // parsed, and the inspector reads them while writing the report. ?>
<?php $qaps = $qaps ?? []; $canQap = function_exists('job_qap_can') ? job_qap_can() : (can('mod.idems.edit') || is_master()); ?>
<div class="panel" id="qaps" data-tab="Reports &amp; QA">
  <div class="ctitle" style="margin-top:0"><h3>QAP / reference documents <span class="muted">(<?= count($qaps) ?>)</span></h3></div>
  <p class="muted" style="margin:0 0 10px">The Quality Assurance Plan(s) for this <?= e(Tl('job')) ?> — one or several, per PO line item.
    The inspector opens them while writing the <?= e(Tl('report')) ?>, and they are kept on record for traceability.</p>
  <?php if ($qaps): ?>
  <table class="dt">
    <thead><tr><th>File</th><th>PO line / item</th><th>Note</th><th>Added</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($qaps as $q): ?>
      <tr>
        <td>📄 <a href="/job-qap?id=<?= (int)$q['id'] ?>" target="_blank" rel="noopener"><?= e($q['file_name'] ?: 'QAP') ?></a></td>
        <td><?= $q['po_line'] ? e($q['po_line']) : '<span class="muted">—</span>' ?></td>
        <td><?= $q['note'] ? e($q['note']) : '<span class="muted">—</span>' ?></td>
        <td class="muted" style="white-space:nowrap"><?= e($q['uploaded_at'] ? substr($q['uploaded_at'],0,10) : '') ?><?= $q['uploaded_by'] ? '<br><span class="muted">'.e($q['uploaded_by']).'</span>' : '' ?></td>
        <td class="num"><?php if ($canQap): ?><form method="post" action="/job-qap-del" style="display:inline" onsubmit="return confirm('Remove this QAP?')"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn small secondary">✕</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted" style="margin:0 0 10px">No QAP attached yet.</p>
  <?php endif; ?>
  <?php if ($canQap): ?>
  <form method="post" action="/job-qap-upload" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:6px">
    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
    <div class="ff" style="margin:0"><label>QAP file(s) <span class="muted">(PDF or any file)</span></label><input class="form-control" type="file" name="qap[]" multiple required></div>
    <div class="ff" style="margin:0"><label>PO line / item <span class="muted">(optional)</span></label><input class="form-control" name="po_line" placeholder="e.g. Line 1 — Valves" style="width:200px"></div>
    <div class="ff" style="margin:0"><label>Note <span class="muted">(optional)</span></label><input class="form-control" name="note" placeholder="e.g. Rev 2" style="width:160px"></div>
    <button class="btn" type="submit">Attach QAP</button>
  </form>
  <?php endif; ?>
</div>
<?php // This panel is the ONLY bridge from a deputation into the report engine.
      // It used to render only when deliverables had been ticked on the call —
      // so if the coordinator missed that tick, the engineer had no route to the
      // reporting module at all and fell back to pasting a link to a file kept
      // somewhere else. It now always renders. ?>
<div class="panel" id="reports" data-tab="Reports &amp; QA">
  <div class="ctitle" style="margin-top:0"><h3><?= e(ucfirst(TP('report'))) ?> on this <?= e(Tl('job')) ?> <span class="muted">(<?= count($dlCodes) + count($extraCodes) ?>)</span></h3></div>
  <?php if ($dlCodes): ?>
  <p class="muted" style="margin:0 0 10px">These are the formats agreed on the <?= e(Tl('call')) ?>. Each one opens in the
    <?= e(Tl('report')) ?> module already filled in from this <?= e(Tl('job')) ?>, on the <?= e(Tl('client')) ?>'s own
    format where one is registered.</p>
  <?php else: ?>
  <p class="muted" style="margin:0 0 10px">No <?= e(Tl('report')) ?> format was agreed on the <?= e(Tl('call')) ?>.
    That does not stop one being written — start it below and it opens already filled in from this <?= e(Tl('job')) ?>.
    To have formats carried through automatically next time, tick them on the <?= e(Tl('call')) ?>.</p>
  <?php endif; ?>
  <table class="dt">
    <thead><tr><th>Format</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($dlCodes as $code): $docs = $jobDocs[$code] ?? []; ?>
      <tr>
        <td><b><?= e($dlMap[$code] ?? $code) ?></b> <span class="muted"><?= e($code) ?></span></td>
        <td><?php if (!$docs): ?><span class="pill p-warn">not started</span>
            <?php else: foreach ($docs as $d): ?>
              <a href="/document?id=<?= (int)$d['id'] ?>"><?= e($d['irn']) ?></a>
              <span class="pill <?= !empty($d['finalized']) ? 'p-ok' : (($d['status'] ?? '') === 'DRAFT' ? 'p-mut' : 'p-info') ?>">
                <?= e(!empty($d['finalized']) ? 'issued' : strtolower((string)($d['status'] ?: 'draft'))) ?></span>
            <?php endforeach; endif; ?></td>
        <td class="num"><?php if (can('mod.idems.edit') || is_master()): ?>
          <a class="btn small<?= $docs ? ' secondary' : '' ?>" href="/document-new?job=<?= (int)$job['id'] ?><?= $job['call_id'] ? '&call=' . (int)$job['call_id'] : '' ?>&type=<?= e(urlencode($code)) ?>"><?= $docs ? 'Add another' : 'Write it' ?></a>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php // Written under a format that was never agreed — still this job's work. ?>
    <?php foreach ($extraCodes as $code): ?>
      <tr>
        <td><b><?= e($dlMap[$code] ?? $code) ?></b> <span class="pill p-mut">not on the agreed list</span></td>
        <td><?php foreach ($jobDocs[$code] as $d): ?>
              <a href="/document?id=<?= (int)$d['id'] ?>"><?= e($d['irn']) ?></a>
              <span class="pill <?= !empty($d['finalized']) ? 'p-ok' : 'p-info' ?>"><?= e(!empty($d['finalized']) ? 'issued' : strtolower((string)($d['status'] ?: 'draft'))) ?></span>
            <?php endforeach; ?></td>
        <td></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$dlCodes && !$extraCodes): ?>
      <tr><td colspan="2" class="muted">Nothing written yet.</td>
          <td class="num"><?php if ($canWrite): ?>
            <a class="btn small" href="/document-new?job=<?= (int)$job['id'] ?><?= $job['call_id'] ? '&call=' . (int)$job['call_id'] : '' ?>">Write one</a>
          <?php endif; ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php if ($canWrite && ($dlCodes || $extraCodes)): ?>
    <p class="muted" style="margin:10px 2px 0">Need a format that is not listed?
      <a href="/document-new?job=<?= (int)$job['id'] ?><?= $job['call_id'] ? '&call=' . (int)$job['call_id'] : '' ?>">Write another <?= e(Tl('report')) ?></a>.</p>
  <?php endif; ?>
</div>

<?php
// --- Hold / witness points --------------------------------------------------
// First-class intervention points: raised automatically when an inspector
// issues a report with an activity dispositioned Hold / Witnessed / Reviewed /
// Client clearance required, or added by hand here. Open points gate despatch.
$hwPts = function_exists('hwp_for_job') ? hwp_for_job((int)$job['id']) : [];
$hwOpen = 0; foreach ($hwPts as $p) { if (($p['status'] ?? '') === 'OPEN') $hwOpen++; }
$hwCanEdit = function_exists('hwp_can_edit') && hwp_can_edit();
$hwTypePill = function($t) {
    $m = ['HOLD'=>'p-bad','WITNESS'=>'p-info','REVIEW'=>'p-mut','CLEARANCE'=>'p-warn'];
    return '<span class="pill '.($m[$t]??'p-mut').'">'.e(function_exists('hwp_type_label')?hwp_type_label($t):$t).'</span>';
};
if (function_exists('hwp_for_job')):
?>
<div class="panel" id="holdpoints" data-tab="Reports &amp; QA">
  <div class="ctitle" style="margin-top:0"><h3>Hold &amp; witness points
    <span class="muted">(<?= count($hwPts) ?><?= $hwOpen ? ', ' . $hwOpen . ' open' : '' ?>)</span></h3></div>
  <?php if ($hwOpen): ?>
    <div class="msg-warning" style="margin:0 0 10px"><strong>🚫 <?= (int)$hwOpen ?> open point<?= $hwOpen===1?'':'s' ?></strong>
      — the manufacturer should not proceed / despatch until these are cleared or waived.</div>
  <?php endif; ?>
  <p class="muted" style="margin:0 0 10px">These are raised automatically when a report is issued with an activity marked
    <b>Hold</b>, <b>Witnessed</b>, <b>Reviewed</b> or <b>Client clearance required</b> — or add one by hand below.</p>
  <?php if ($hwPts): ?>
  <table class="dt">
    <thead><tr><th>Type</th><th>QAP clause</th><th>Activity / description</th><th>Status</th><th>From</th><?php if ($hwCanEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($hwPts as $p): $isOpen = ($p['status'] ?? '')==='OPEN'; ?>
      <tr<?= $isOpen ? '' : ' style="opacity:.6"' ?>>
        <td><?= $hwTypePill($p['point_type']) ?></td>
        <td><?= e($p['qap_clause'] ?: '—') ?><?= $p['sub_clause'] ? ' <span class="muted">('.e($p['sub_clause']).')</span>' : '' ?></td>
        <td><?= e($p['activity'] ?: '') ?><?php if ($p['description']): ?><div class="muted" style="font-size:.9em"><?= e(mb_strimwidth((string)$p['description'],0,140,'…')) ?></div><?php endif; ?></td>
        <td><span class="pill <?= $isOpen?'p-warn':(($p['status']==='CLEARED')?'p-ok':'p-mut') ?>"><?= e(function_exists('hwp_status_label')?hwp_status_label($p['status']):$p['status']) ?></span>
            <?php if (!$isOpen && $p['cleared_by']): ?><div class="muted" style="font-size:.82em"><?= e($p['cleared_by']) ?><?= $p['clearance_ref']?' · '.e($p['clearance_ref']):'' ?></div><?php endif; ?></td>
        <td><?php if (!empty($p['report_doc_id'])): ?><a href="/document?id=<?= (int)$p['report_doc_id'] ?>" title="<?= e($p['irn']) ?>"><?= e($p['irn'] ?: 'report') ?></a><?php else: ?><span class="pill p-mut">manual</span><?php endif; ?></td>
        <?php if ($hwCanEdit): ?>
        <td class="num"><?php if ($isOpen): ?>
          <details><summary class="btn small">Clear / waive</summary>
            <form method="post" style="display:flex;flex-direction:column;gap:5px;margin-top:6px;min-width:220px">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input class="form-control" name="clearance_ref" placeholder="Clearance ref (NCR / mail / doc)">
              <input class="form-control" name="remark" placeholder="Remark">
              <div style="display:flex;gap:5px">
                <button class="btn small" formaction="/hw-point-clear">Clear</button>
                <button class="btn small secondary" formaction="/hw-point-waive">Waive</button>
              </div>
            </form>
          </details>
        <?php else: ?>
          <form method="post" action="/hw-point-reopen" style="display:inline"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn small secondary">Reopen</button></form>
        <?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php if ($hwCanEdit): ?>
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;margin-top:10px">
    <form method="post" action="/hw-point-new" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
      <div class="ff" style="margin:0"><label>Type</label>
        <select class="form-control" name="point_type"><?php foreach (HW_POINT_TYPES as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff" style="margin:0"><label>QAP clause</label><input class="form-control" name="qap_clause" style="width:90px"></div>
      <div class="ff" style="margin:0"><label>Description</label><input class="form-control" name="description" placeholder="What must be held / witnessed" style="min-width:220px"></div>
      <button class="btn small" type="submit">Add point</button>
    </form>
    <form method="post" action="/hw-point-derive" title="Re-scan issued reports for hold / witness dispositions">
      <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
      <button class="btn small secondary" type="submit">↻ Re-scan issued reports</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php $holds = function_exists('job_hold_reasons') ? job_hold_reasons($job) : []; if ($holds): ?>
<div class="panel" data-tab="Reports &amp; QA" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 8%,transparent)">
  <b style="color:var(--bad)">🚫 HOLD — do not issue the report / deliverable to the client:</b> <?= e(implode('; ', $holds)) ?>.
  <?php if (!empty($job['adv_required']) && empty($job['adv_received']) && (is_coordinator_level() || can('data.credit') || can('finance.reconcile'))): ?>
    <form method="post" action="/job-advance?id=<?= (int)$job['id'] ?>" style="margin-top:8px"><input type="hidden" name="adv_received" value="1"><button class="btn small" type="submit">Mark advance received</button></form>
  <?php endif; ?>
</div>
<?php elseif (!empty($job['quotation_id']) && (!empty($job['adv_required']) || !empty($job['report_hold']))): ?>
<div class="panel" data-tab="Reports &amp; QA" style="border:1px solid var(--ok)"><b style="color:var(--ok)">✓ Payment conditions cleared</b> — advance/payment received; the deliverable may be issued.</div>
<?php endif; ?>

<?php if (($job['report_approval'] ?? '') !== ''): ?>
  <?php $ra = $job['report_approval']; $canAppr = function_exists('can_approve_report') && can_approve_report($job); ?>
  <div class="panel" data-tab="Reports &amp; QA" style="border:1px solid <?= $ra==='APPROVED'?'var(--ok)':($ra==='REJECTED'?'var(--bad)':'var(--warn,#c90)') ?>">
    <?php if ($ra==='PENDING'): ?>
      <b>🕓 Report awaiting approval</b> from <?= e($job['inspector_name'] ?: 'the ' . Tl('engineer')) ?>'s reporting manager.
      <?php if ($canAppr): ?>
        <form method="post" action="/report-approve?id=<?= (int)$job['id'] ?>" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <input class="form-control" name="note" placeholder="Optional remark" style="max-width:280px">
          <button class="btn small" type="submit" name="decision" value="approve">Approve report</button>
          <button class="btn small secondary" type="submit" name="decision" value="reject">Send back</button>
        </form>
      <?php endif; ?>
    <?php elseif ($ra==='APPROVED'): ?>
      <b style="color:var(--ok)">✓ Report approved</b> by <?= e($job['report_approved_by'] ?: '—') ?><?= $job['report_approved_at']?' on '.e(date('d M Y', strtotime($job['report_approved_at']))):'' ?>.<?= $job['report_approval_note']?' — '.e($job['report_approval_note']):'' ?>
    <?php else: ?>
      <b style="color:var(--bad)">↩ Report sent back</b> by <?= e($job['report_approved_by'] ?: '—') ?><?= $job['report_approval_note']?' — '.e($job['report_approval_note']):'' ?>.
      <?php if ($canAppr): ?>
        <form method="post" action="/report-approve?id=<?= (int)$job['id'] ?>" style="margin-top:8px"><input type="hidden" name="decision" value="approve"><button class="btn small" type="submit">Approve now</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<details class="fold" data-tab="Overview">
  <summary>Full <?= e(Tl('job')) ?> details <span class="sub">call, stage, office, dates, reporting, credit &amp; deliverables</span></summary>
  <div class="fold-body">
  <div class="kv-grid">
    <?php if (!empty($job['quotation_id'])): $lq = ops_one("SELECT quote_no, rev, contract_number FROM quotations WHERE id=?", [$job['quotation_id']]); ?>
    <div><span class="k">Against <?= e(Tl('quote')) ?></span><?= $lq ? e($lq['quote_no'].((int)$lq['rev']>0?' R'.$lq['rev']:'')) : '—' ?><?= ($lq && $lq['contract_number'])?' · '.e($lq['contract_number']):'' ?></div>
    <?php endif; ?>
    <?php if (!empty($job['adv_required'])): ?><div><span class="k">Advance</span><?= rtrim(rtrim(number_format((float)$job['adv_pct'],2),'0'),'.') ?>% · <?= !empty($job['adv_received'])?'<span style="color:var(--ok)">received</span>':'<span style="color:var(--bad)">pending</span>' ?></div><?php endif; ?>
    <div><span class="k">Call</span><?= e($job['call_code'] ?: '—') ?></div>
    <div><span class="k">Job type</span><?= e(lk_options_or('job_type', JOB_TYPES)[$job['job_type'] ?? 'INSPECTION'] ?? '—') ?></div>
    <div><span class="k">Stage</span><?= e(lk_options_or('job_stage', JOB_STAGES)[$job['stage'] ?? 'ALLOCATED'] ?? '—') ?></div>
    <div><span class="k">Executing office</span><?= e($job['office_name'] ?: '—') ?></div>
    <div><span class="k">Inspector</span><?= e($job['inspector_name'] ?: '—') ?></div>
    <div><span class="k">Sub-con</span><?= e($job['subcon_agency'] ?: '—') ?></div>
    <div><span class="k"><?= e(T('boss')) ?></span><?= e($job['boss_number'] ?: '—') ?></div>
    <div><span class="k">Scheduled</span><?= e($job['scheduled_date'] ?: '—') ?></div>
    <div><span class="k">Inspection</span><?= e(($job['inspection_start_date'] ?: '?') . ' → ' . ($job['inspection_end_date'] ?: '?')) ?></div>
    <div><span class="k">Type of inspection</span><?= e(INSPECTION_TYPES[$job['inspection_type']] ?? ($job['inspection_type'] ?: '—')) ?></div>
    <div><span class="k">Activity</span><?= e(($job['activity_id']??null) ? lk_value_path($job['activity_id']) : '—') ?></div>
    <div><span class="k">Reporting</span><?= e(REPORT_FREQ[$job['reporting_frequency']] ?? '—') ?><?= ($job['reporting_frequency']==='CUSTOM' && !empty($job['report_custom_days'])) ? ' (every '.(int)$job['report_custom_days'].' days)' : '' ?></div>
    <?php // Inter-office credit is a commercial figure — shown only to those who hold
          //  the credit permission, never to a field inspector who now reaches their
          //  own job. ?>
    <?php if (can('data.credit') || is_master()): ?>
    <div><span class="k">Credit direction</span><?= e(CREDIT_DIRECTIONS[$job['credit_direction']] ?? '—') ?></div>
    <div><span class="k">Expected credit</span><?= fmoney($job['expected_credit']) ?></div>
    <?php endif; ?>
    <div><span class="k">Report uploaded</span><?= e($job['report_upload_date'] ?: '—') ?></div>
    <div><span class="k">TAT</span><?= $job['tat_days']===null?'—':(int)$job['tat_days'].' day(s)' ?></div>
    <div class="kv-wide"><span class="k">Report folder</span><?php if ($job['folder_link']): ?><a href="<?= e($job['folder_link']) ?>" target="_blank" rel="noopener"><?= e($job['folder_link']) ?></a><?php else: ?>—<?php endif; ?></div>
    <div class="kv-wide"><span class="k">Deliverables required</span><?php
      $dl = $job['deliverables'] !== '' ? explode(',', $job['deliverables']) : [];
      $map = deliverable_options();
      echo $dl ? e(implode(', ', array_map(fn($c) => $map[$c] ?? $c, $dl))) : '—';
    ?></div>
    <?php if (!empty($job['reporting_frequency']) && $job['reporting_frequency'] !== 'NOREPORT'): ?>
      <div><span class="k">Reporting frequency</span><?= e(lk_options_or('reporting_frequency', REPORT_FREQ)[$job['reporting_frequency']] ?? $job['reporting_frequency']) ?><?php
        if ($job['reporting_frequency'] === 'CUSTOM' && !empty($job['report_custom_days'])) echo ' — every ' . (int)$job['report_custom_days'] . ' day(s)';
      ?></div>
    <?php endif; ?>
    <?php foreach (custom_display('job', $job['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><?= e($cf['value']) ?></div>
    <?php endforeach; ?>
  </div>
  </div>
</details>

<?php if (!empty($quoteDocs)): ?>
<details class="fold">
  <summary><?= e(T('client')) ?> documents <span class="sub">the PO, spec, drawing &amp; QAP the <?= e(Tl('client')) ?> sent (<?= count($quoteDocs) ?>)</span></summary>
  <div class="fold-body">
  <table class="dt">
    <thead><tr><th>Document</th><th>Kind</th><th>Note</th><th>Added</th></tr></thead>
    <tbody>
    <?php foreach ($quoteDocs as $d): ?>
      <tr>
        <td><a href="/quote-file?id=<?= (int)$d['id'] ?>">⬇ <?= e($d['file_name']) ?></a>
            <span class="muted" style="font-size:11px">(<?= e(number_format(((int)$d['b64len']) * 3 / 4 / 1024, 0)) ?> KB)</span></td>
        <td><?= e(QUOTE_FILE_KINDS[$d['kind']] ?? $d['kind']) ?></td>
        <td class="muted"><?= e($d['note'] ?: '—') ?></td>
        <td class="muted"><?= e(fdate(substr((string)$d['uploaded_at'],0,10))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="margin-top:6px">The purchase order, specification, drawing and QAP the <?= e(Tl('client')) ?> sent when the <?= e(Tl('quote')) ?> was accepted.</p>
  </div>
</details>
<?php endif; ?>

<?php // The expenses the client agreed to pay for. Every one ticked needs a
      // bill on file or the job will not close, so the outstanding ones are
      // named here rather than only being discovered at the Close screen.
      $chgHeads = chargeable_heads($job);
      $chgOpts  = chargeable_head_options();
      $byHead   = job_bills_by_head($job['id']);
      $missing  = job_bills_missing($job);
      $canBill  = job_bill_can_upload($job); ?>
<?php // Client-billable bills are coordinator/finance work — hidden from the field
      // inspector (their own out-of-pocket expenses go on the monthly voucher, not here).
      if (($chgHeads || $byHead) && !$fieldInspector): ?>
<div class="panel" id="bills" data-tab="Money">
  <div class="ctitle" style="margin-top:0"><h3>Charged to the <?= e(Tl('client')) ?> — bills required
    <span class="muted">(<?= count($chgHeads) ?>)</span></h3></div>
  <?php if ($missing): ?>
    <div class="msg msg-error" style="margin:8px 0"><?= e(job_bills_block($job)) ?></div>
  <?php elseif ($chgHeads): ?>
    <div class="msg msg-ok" style="margin:8px 0">Every chargeable expense on this <?= e(Tl('job')) ?> has its bill on file.</div>
  <?php endif; ?>
  <table class="grid">
    <tr><th>Heading</th><th>Bill no.</th><th>Bill date</th><th class="num">Amount</th><th>File</th><th>Filed by</th><?php if ($canBill): ?><th></th><?php endif; ?></tr>
    <?php $btot = 0; foreach ($chgHeads as $code): $rows = $byHead[$code] ?? []; ?>
      <?php if (!$rows): ?>
        <tr><td><strong><?= e($chgOpts[$code] ?? $code) ?></strong></td>
            <td colspan="<?= $canBill ? 6 : 5 ?>"><span class="pill p-warn">no bill yet</span></td></tr>
      <?php else: foreach ($rows as $i => $b): $btot += (float)$b['amount']; ?>
        <tr><td><?= $i === 0 ? '<strong>' . e($chgOpts[$code] ?? $code) . '</strong>' : '' ?></td>
          <td><?= e($b['bill_no'] ?: '—') ?></td>
          <td><?= e($b['bill_date'] ? fdate($b['bill_date']) : '—') ?></td>
          <td class="num"><?= fmoney($b['amount']) ?></td>
          <td><a href="/bill-file?id=<?= (int)$b['id'] ?>" target="_blank" rel="noopener"><?= e($b['file_name']) ?></a></td>
          <td class="muted"><?= e($b['uploaded_by']) ?></td>
          <?php if ($canBill): ?>
          <td><form method="post" action="/bill-delete" onsubmit="return confirm('Remove this bill?')" style="margin:0">
            <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn small secondary" type="submit">Remove</button></form></td>
          <?php endif; ?></tr>
      <?php endforeach; endif; ?>
    <?php endforeach; ?>
    <?php // Bills filed under a heading that has since been un-ticked. Kept, and
          // shown, but no longer charged on — otherwise they simply vanish. ?>
    <?php foreach ($byHead as $code => $rows): if (in_array($code, $chgHeads, true)) continue; foreach ($rows as $b): ?>
      <tr class="muted"><td><?= e($chgOpts[$code] ?? $code) ?> <span class="pill p-mut">no longer charged</span></td>
        <td><?= e($b['bill_no'] ?: '—') ?></td><td><?= e($b['bill_date'] ? fdate($b['bill_date']) : '—') ?></td>
        <td class="num"><?= fmoney($b['amount']) ?></td>
        <td><a href="/bill-file?id=<?= (int)$b['id'] ?>" target="_blank" rel="noopener"><?= e($b['file_name']) ?></a></td>
        <td><?= e($b['uploaded_by']) ?></td><?php if ($canBill): ?><td></td><?php endif; ?></tr>
    <?php endforeach; endforeach; ?>
    <?php if ($btot > 0): ?><tr><td colspan="3"><strong>Recoverable from the <?= e(Tl('client')) ?></strong></td>
      <td class="num"><strong><?= fmoney($btot) ?></strong></td><td colspan="<?= $canBill ? 3 : 2 ?>"></td></tr><?php endif; ?>
  </table>

  <?php if ($canBill && $chgHeads): ?>
  <form method="post" action="/bill-add?job=<?= (int)$job['id'] ?>" enctype="multipart/form-data" style="margin-top:14px">
    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label>Which expense *</label>
        <select class="form-control" name="head_code" required>
          <?php foreach ($chgHeads as $code): ?>
            <option value="<?= e($code) ?>"><?= e($chgOpts[$code] ?? $code) ?><?= isset($missing[$code]) ? ' — still needed' : '' ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Bill number</label><input class="form-control" name="bill_no" placeholder="as printed on the bill"></div>
      <div class="ff"><label>Bill date</label><input class="form-control" type="date" name="bill_date"></div>
      <div class="ff"><label>Amount (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" min="0" name="amount" value="0"></div>
      <div class="ff"><label>The bill itself *</label><input class="form-control" type="file" name="bill_file" required
             accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx"></div>
      <div class="ff ff-wide"><label>Note</label><input class="form-control" name="note" placeholder="e.g. return fare Ahmedabad–Vadodara"></div>
    </div>
    <button class="btn" type="submit">Upload bill</button>
    <span class="muted" style="margin-left:8px">A photo of the bill is fine. Up to <?= (int)upload_max_mb() ?> MB.</span>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // Profitability — revenue, invoice value, margin, net profit — is commercial
      // and restricted to managers/finance who hold the figure permissions. A
      // coordinator or inspector sees the expenses list (their operational cost),
      // never what the job earned. ("Profitability shall only be shown to Managers
      // and above who have the access. Nothing to coordinators, inspectors, etc.")
      $canSeeProfit = (function_exists('can') && (can('data.profitability') || can('data.revenue'))) || is_master(); ?>
<?php // The expenses list here is the CLIENT-BILLABLE cost the coordinator files at
      // closure, and profitability is managers-only — neither is the field engineer's
      // concern, so the whole fold is hidden from the inspector. Their own travel and
      // out-of-pocket expenses go on the monthly voucher (pointed to from the Site
      // check-in panel above), so no inspector-facing guidance is lost.
      if (!$fieldInspector): ?>
<details class="fold" data-tab="Money">
  <summary><?= $canSeeProfit ? 'Expenses &amp; profitability' : 'Expenses' ?> <span class="sub"><?= $canSeeProfit ? 'what it cost, and what the ' . e(Tl('job')) . ' made' : 'what it cost' ?></span></summary>
  <div class="fold-body">
  <div class="panel-split">
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Expenses</h3>
    <?php // A line recorded twice can now be removed. New duplicates are refused
          // at submission, but the ones already on file need a way out.
          $canDropExp = is_coordinator_level() || can('finance.reconcile') || is_master();
          $seenExp = []; ?>
    <table class="grid">
      <tr><th>Date</th><th><?= e(T("sbu")) ?></th><th>Travel</th><th>Local</th><th>Food</th><th>Lodging</th><th>Misc</th><th>Total</th><?php if ($canDropExp): ?><th></th><?php endif; ?></tr>
      <?php $etot=0; $extraLbls=expense_extra_headings(); foreach ($expenses as $x): $ex=expense_extra_decode($x['extra']??''); $rt=$x['travel']+$x['local']+$x['food']+$x['lodging']+$x['misc']+array_sum($ex); $etot+=$rt;
        $sig = $x['exp_date'] . '|' . $x['sbu'] . '|' . $rt; $dupe = isset($seenExp[$sig]); $seenExp[$sig] = 1; ?>
      <tr<?= $dupe ? ' style="background:color-mix(in srgb,var(--warn) 8%,transparent)"' : '' ?>>
        <td><?= e($x['exp_date']) ?><?php if ($dupe): ?><div><span class="pill p-warn">same as the line above</span></div><?php endif; ?></td>
        <td><?= e(OPS_SBUS[$x['sbu']] ?? $x['sbu']) ?></td>
        <td><?= fmoney($x['travel']) ?></td><td><?= fmoney($x['local']) ?></td><td><?= fmoney($x['food']) ?></td>
        <td><?= fmoney($x['lodging']) ?></td><td><?= fmoney($x['misc']) ?></td><td><strong><?= fmoney($rt) ?></strong></td>
        <?php if ($canDropExp): ?>
        <td><form method="post" action="/expense-delete?id=<?= (int)$x['id'] ?>" onsubmit="return confirm('Remove this expense line?')" style="margin:0">
          <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
          <button class="btn small secondary" type="submit" title="Remove this line">Remove</button></form></td>
        <?php endif; ?></tr>
      <?php if ($ex): ?><tr><td colspan="<?= $canDropExp ? 9 : 8 ?>" class="muted" style="font-size:12px">+ <?= e(implode(', ', array_map(fn($c,$a)=>($extraLbls[$c]??$c).': '.fmoney($a), array_keys($ex), array_values($ex)))) ?></td></tr><?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$expenses): ?><tr><td colspan="<?= $canDropExp ? 9 : 8 ?>">No expenses recorded (entered at closure).</td></tr><?php endif; ?>
    </table>
    <?php // This list is the CLIENT-BILLABLE expenses, entered by the coordinator
          //  when the job is closed. An engineer's own travel & out-of-pocket
          //  expenses are not typed here — they go on the monthly voucher (which is
          //  also the timesheet) and flow back into this job automatically. Point
          //  the engineer at it, because the empty list above reads like a dead end. ?>
    <?php if (function_exists('is_inspector') && is_inspector()): ?>
      <p class="muted" style="font-size:12px;margin:8px 0 0">
        Recording your <strong>own</strong> travel &amp; expenses? They go on your monthly
        <a href="/vouchers">voucher</a>, not here — one voucher a month, one line a day, and
        every line you tag to a <?= e(Tl('job')) ?> is added to it automatically. The list above
        is the client-billable expenses the coordinator files when the <?= e(Tl('job')) ?> closes.
      </p>
    <?php elseif (is_coordinator_level() || is_master()): ?>
      <p class="muted" style="font-size:12px;margin:8px 0 0">
        These are the client-billable expenses, entered at closure. The engineer's own
        travel is claimed on their monthly <a href="/vouchers">voucher</a> and already
        rolled into profitability below.
      </p>
    <?php endif; ?>
  </div>
  <?php if ($canSeeProfit): ?>
  <div class="panel">
    <h3 class="tab-sub">Profitability<?= can_see_salary() ? '' : ' (summary)' ?></h3>
    <div class="kv-grid">
      <div><span class="k">Man-days</span><?= e($profit['mandays']) ?></div>
      <?php if (can_see_salary()): ?>
        <div><span class="k">Daily cost</span><?= fmoney($profit['daily_cost']) ?></div>
        <div><span class="k">Labour cost</span><?= fmoney($profit['labour']) ?></div>
      <?php endif; ?>
      <div><span class="k">Expenses</span><?= fmoney($profit['expenses']) ?></div>
      <?php if (!empty($profit['recovered'])): ?>
        <div><span class="k">Less: recovered from the <?= e(Tl('client')) ?></span>−<?= fmoney($profit['recovered']) ?></div>
      <?php endif; ?>
      <div><span class="k">Sub-con cost</span><?= fmoney($profit['subcon']) ?></div>
      <?php // The two are not the same number and were treated as though they
            // were. What the client pays is the invoice value; what this branch
            // keeps out of it is the revenue, which is less by whatever credit
            // was passed to the branch that did the work. ?>
      <div><span class="k">Invoice value<?= $profit['billed'] > 0 ? '' : ' (agreed, not yet billed)' ?></span><?= fmoney($profit['invoice']) ?></div>
      <?php if (!empty($profit['cross'])): ?>
        <div><span class="k">Credit to the executing <?= e(Tl('office')) ?></span><?= fmoney($profit['own_credit']) ?></div>
      <?php endif; ?>
      <div><span class="k">Revenue<?= empty($profit['cross']) ? '' : ' (after the credit)' ?></span><strong><?= fmoney($profit['revenue']) ?></strong></div>
      <?php if (can_see_salary()): ?>
        <div class="kv-wide"><span class="k">Net profit</span><strong style="color:<?= $profit['profit']>=0?'var(--good,#1f8a4c)':'#c0392b' ?>"><?= fmoney($profit['profit']) ?></strong></div>
      <?php else: ?>
        <div class="kv-wide"><span class="k">Net profit</span><em class="muted">Visible to Master Admin only</em></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; /* $canSeeProfit */ ?>
  </div>
  </div>
</details>
<?php endif; /* !$fieldInspector — expenses/profitability fold */ ?>

<?php if (can('data.credit') || can('finance.reconcile')): ?>
<div class="panel" id="invoice" data-tab="Money">
  <h3 class="tab-sub">Invoice &amp; payment / credit</h3>
  <?php // One click raises a real GST invoice in the Money module from this closed
        // job, with the amount already filled from the quote/call — no re-keying,
        // no separate screen to start it (#4 / #5). Shown once the job is closed
        // and only when the Money module is licensed. ?>
  <?php if (!empty($job['closed_flag']) && function_exists('books_invoice_create') && (function_exists('licence_enabled') ? (licence_enabled('money') || licence_enabled('invoicing')) : true)): ?>
    <form method="post" action="/job-bill?id=<?= (int)$job['id'] ?>" style="margin-bottom:12px"
          onsubmit="return confirm('Raise a draft GST invoice for this <?= e(Tl('job')) ?>? The amount is filled from the <?= e(Tl('quote')) ?>.');">
      <button class="btn" type="submit">🧾 Raise GST invoice from this <?= e(Tl('job')) ?> →</button>
      <span class="muted" style="font-size:12.5px;margin-left:6px">Creates the invoice with the amount pre-filled; you review &amp; issue it.</span>
    </form>
  <?php endif; ?>
  <?php // §INV-1 — the real GST invoices raised against this job, drafts included,
        // so a raised invoice is visible here at once (not "nothing reflected").
        $bInv = $booksInvoices ?? [];
        if ($bInv): ?>
    <table class="kv" style="margin-bottom:12px">
      <tr><th>Invoice</th><th>Date</th><th class="num">Total</th><th>Status</th></tr>
      <?php foreach ($bInv as $bi):
        $st = (string)$bi['status'];
        $tone = $st==='PAID'?'p-ok':($st==='DRAFT'?'p-warn':'p-info');
        $stLbl = defined('INV_STATUS') ? (INV_STATUS[$st] ?? $st) : $st; ?>
      <tr>
        <td><a href="/invoice?id=<?= (int)$bi['id'] ?>"><b><?= e($bi['invoice_no'] ?: '(draft)') ?></b></a></td>
        <td><?= e($bi['invoice_date'] ?: '—') ?></td>
        <td class="num"><?= e(cur_sym()) ?><?= number_format((float)$bi['total'], 0) ?></td>
        <td><span class="pill <?= $tone ?>"><?= e($stLbl) ?></span><?php if ($st==='DRAFT'): ?> <a href="/invoice?id=<?= (int)$bi['id'] ?>" class="muted" style="font-size:12px">review &amp; issue →</a><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
  <?php $isInter = ($job['credit_direction'] ?? '') === 'GIVEN'; ?>
  <?php // The old manual checkbox/number entry, kept for corrections and installs
        // not using the Money module — tucked away so the real invoice above is the
        // one thing people look at (§INV-1 "one invoice truth"). ?>
  <details<?= $bInv ? '' : ' open' ?> style="margin-top:4px">
    <summary class="muted" style="cursor:pointer;font-size:13px">Manual invoice / payment entry<?= $bInv ? ' (the real invoice is shown above)' : '' ?></summary>
  <form method="post" action="/job-invoice?id=<?= (int)$job['id'] ?>" style="margin-top:10px">
    <div class="form-grid">
      <div class="ff ff-check"><input type="checkbox" name="invoice_raised" <?= !empty($job['invoice_raised'])?'checked':'' ?>><label>Invoice raised</label></div>
      <div class="ff"><label>Invoice number</label><input class="form-control" name="invoice_number" value="<?= e($job['invoice_number'] ?? '') ?>"></div>
      <div class="ff"><label>Invoice date</label><input class="form-control" type="date" name="invoice_date" value="<?= e($job['invoice_date'] ?? '') ?>"></div>
      <div class="ff"><label>Due date</label><input class="form-control" type="date" name="invoice_due_date" value="<?= e($job['invoice_due_date'] ?? '') ?>"></div>
      <div class="ff"><label>Invoice amount (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="invoice_amount" value="<?= e($job['invoice_amount'] ?? '') ?>"></div>
      <div class="ff ff-check"><input type="checkbox" name="payment_received" <?= !empty($job['payment_received'])?'checked':'' ?>><label>Payment received</label></div>
      <div class="ff"><label>Payment date</label><input class="form-control" type="date" name="payment_date" value="<?= e($job['payment_date'] ?? '') ?>"></div>
      <div class="ff"><label>Payment amount (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="payment_amount" value="<?= e($job['payment_amount'] ?? '') ?>"></div>
      <div class="ff ff-check"><input type="checkbox" name="credit_received" <?= !empty($job['credit_received'])?'checked':'' ?>><label>Inter-office credit received<?= $isInter?' (this job is credit-given)':'' ?></label></div>
    </div>
    <p class="muted" style="margin:4px 2px">For a local client (same contracting &amp; executing office) use invoice + payment. When the executing branch is different, use the inter-office credit received flag.</p>
    <div style="margin-top:8px"><button class="btn small" type="submit">Save invoice / payment</button></div>
  </form>
  </details>
</div>
<?php endif; ?>
</div><!-- /data-tabs (job record) -->

<?php // A deep link to a section that is now collapsed (e.g. #assign from the
      //   schedule register) should open that section, not land on a shut panel. ?>
<script>(function(){
  function openForHash(){
    var h = (location.hash || '').slice(1); if(!h) return;
    var el = document.getElementById(h); if(!el) return;
    var d = el.closest ? el.closest('details') : null;
    if(d){ d.open = true; setTimeout(function(){ el.scrollIntoView({block:'start'}); }, 30); }
  }
  window.addEventListener('hashchange', openForHash);
  openForHash();
})();</script>
