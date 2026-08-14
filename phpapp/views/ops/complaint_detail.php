<?php $ackLate = cmp_ack_overdue($c); $decLate = cmp_decide_overdue($c); $closed = $c['status'] === 'CLOSED'; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/complaints">Complaints &amp; appeals</a> › <?= e($c['ref']) ?></div>
<div class="master-head">
  <div><h1><?= e($c['ref']) ?> — <?= e($c['subject']) ?></h1>
    <p class="sub" style="margin:2px 0 0">
      <?= e($c['kind'] === 'APPEAL' ? 'Appeal' : 'Complaint') ?> ·
      received <?= e($c['received_on'] ? fdate($c['received_on']) : '—') ?> by <?= e($c['received_by'] ?: '—') ?> ·
      <?= e(CMP_CHANNELS[$c['channel']] ?? $c['channel']) ?>
      <?php if ($parent): ?> · against <a href="/complaint?id=<?= (int)$parent['id'] ?>"><?= e($parent['ref']) ?></a><?php endif; ?>
    </p></div>
  <div style="display:flex;gap:8px">
    <?php if ($canRecord && !$closed && $c['kind'] !== 'APPEAL' && $c['outcome'] !== 'PENDING'): ?>
      <a class="btn secondary" href="/complaint-new?appeal_of=<?= (int)$c['id'] ?>">They are appealing</a><?php endif; ?>
    <a class="btn secondary" href="/complaints">← Back</a>
  </div>
</div>

<?php if ($closed): ?>
  <div class="panel" style="border-left:4px solid var(--ok)"><p style="margin:0"><strong>Closed</strong>
    on <?= e(fdate($c['closed_on'])) ?> by <?= e($c['closed_by']) ?>.</p></div>
<?php elseif ($missing): ?>
  <div class="panel" style="border-left:4px solid var(--warn)">
    <p style="margin:0"><strong>Still to do before this can be closed:</strong> <?= e(implode('; ', $missing)) ?>.</p></div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi"><span class="kic">✔️</span><div class="k">Acknowledged</div>
    <div class="v" style="font-size:18px"><?= $c['acknowledged_on'] ? e(fdate($c['acknowledged_on']))
      : ($ackLate ? '<span class="down">' . (int)$ackLate . 'd late</span>' : 'not yet') ?></div>
    <div class="d">target <?= (int)cmp_ack_days() ?> days</div></div>
  <div class="kpi"><span class="kic">🔍</span><div class="k">Ours to answer?</div>
    <div class="v" style="font-size:18px"><?= e(strtok(CMP_VALIDITY[$c['validity']] ?? $c['validity'], '—')) ?></div></div>
  <div class="kpi"><span class="kic">⚖️</span><div class="k">Outcome</div>
    <div class="v" style="font-size:18px"><?= $c['outcome'] === 'PENDING'
      ? ($decLate ? '<span class="down">' . (int)$decLate . 'd late</span>' : 'pending')
      : e(strtok(CMP_OUTCOMES[$c['outcome']], '—')) ?></div>
    <div class="d"><?= e($c['decided_by'] ?: 'nobody yet') ?></div></div>
  <div class="kpi"><span class="kic">✉️</span><div class="k">Complainant told</div>
    <div class="v" style="font-size:18px"><?= $c['notified_on'] ? e(fdate($c['notified_on'])) : 'not yet' ?></div></div>
</div>

<div data-tabs data-tabs-key="complaint" data-tabs-order="Overview,Handle,History">
<div class="panel" data-tab="Overview">
  <div class="ctitle" style="margin-top:0"><h3>What they told us</h3></div>
  <p style="white-space:pre-wrap;margin:0"><?= e($c['description']) ?></p>
  <table class="dt" style="margin-top:12px">
    <tbody>
      <tr><th style="width:200px">From</th><td><?= !empty($c['anonymous']) ? '<em>anonymous</em>' : e($c['complainant_name'] ?: '—') ?>
        <?= $c['complainant_email'] ? ' · ' . e($c['complainant_email']) : '' ?>
        <?= $c['complainant_phone'] ? ' · ' . e($c['complainant_phone']) : '' ?></td></tr>
      <tr><th>Source</th><td><?= e(CMP_SOURCES[$c['source']] ?? $c['source']) ?></td></tr>
      <?php if ($c['job_id']): ?><tr><th><?= e(TH('job')) ?></th><td><a href="/job?id=<?= (int)$c['job_id'] ?>">#<?= (int)$c['job_id'] ?></a></td></tr><?php endif; ?>
      <?php if ($c['report_irn']): ?><tr><th><?= e(TH('report')) ?></th><td><?= e($c['report_irn']) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php // §7.5.4 in the flesh: the names the software will not accept as the decider. ?>
<div class="panel" data-tab="Handle">
  <div class="ctitle" style="margin-top:0"><h3>Who may not decide this</h3></div>
  <p class="sub" style="margin-top:0"><?php $accr = function_exists('accredited_pack_on') && accredited_pack_on(); ?><?php if ($accr): ?><?= e(accreditation_ref('complaints')) ?> — the<?php else: ?>The<?php endif; ?> decision has to be made by, or reviewed and approved
    by, somebody who was not involved in the inspection being complained about.
    <?= $c['kind'] === 'APPEAL' ? ' For an appeal, that also excludes whoever decided the original.' : '' ?></p>
  <?php if ($involved): ?>
    <p style="margin:0"><?php foreach ($involved as $n): ?><span class="pill p-bad" style="margin-right:6px"><?= e($n) ?></span><?php endforeach; ?></p>
    <small class="muted">Taken from the <?= e(Tl('engineer')) ?> on the work and, where a <?= e(Tl('report')) ?> number is
      recorded, whoever prepared, finalised or approved it. The coordinator who allocated the work is not counted —
      allocating is not carrying out an inspection.</small>
  <?php else: ?>
    <p class="muted" style="margin:0">Nobody — no <?= e(Tl('engineer')) ?> or <?= e(Tl('report')) ?> number is recorded
      against this one, so the app cannot tell who was involved. Add them on the register entry if it concerns
      specific work, or the §7.5.4 check has nothing to bite on.</p>
  <?php endif; ?>
  <?php if ($block !== ''): ?>
    <p class="pill p-bad" style="display:inline-block;margin:8px 0 0"><?= e($block) ?></p>
  <?php endif; ?>
</div>

<?php if ($canRecord && !$closed): ?>
<div class="panel" data-tab="Handle">
  <div class="ctitle" style="margin-top:0"><h3>Handle it</h3></div>

  <?php if ($c['acknowledged_on'] === ''): ?>
  <h3 class="tab-sub">1 · Acknowledge</h3>
  <form method="post" action="/complaint-ack" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Acknowledged on</label><input class="form-control" type="date" name="on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff ff-wide"><label>How</label><input class="form-control" name="note" placeholder="e.g. replied by e-mail the same day"></div>
    <button class="btn small" type="submit">Record it</button>
  </form>
  <?php endif; ?>

  <h3 class="tab-sub">2 · Is it ours to answer? <span class="muted">(§7.5.3)</span></h3>
  <form method="post" action="/complaint-validity" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Decision</label>
      <select class="form-control" name="validity">
        <?php foreach (CMP_VALIDITY as $k=>$v): if ($k==='PENDING') continue; ?>
          <option value="<?= e($k) ?>" <?= $c['validity']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Why <span class="muted">— required unless it is ours</span></label>
      <input class="form-control" name="validity_note" value="<?= e($c['validity_note']) ?>"
             placeholder="and who should handle it instead, if you know"></div>
    <button class="btn small secondary" type="submit">Save</button>
  </form>

  <h3 class="tab-sub">3 · Investigate</h3>
  <form method="post" action="/complaint-investigate" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Being handled by</label>
      <input class="form-control" name="assigned_to" value="<?= e($c['assigned_to']) ?>"></div>
    <div class="ff ff-wide"><label>What we found</label>
      <textarea class="form-control" name="investigation" rows="5"><?= e($c['investigation']) ?></textarea></div>
    <div class="ff ff-wide"><label>Root cause</label>
      <input class="form-control" name="root_cause" value="<?= e($c['root_cause']) ?>"
             placeholder="the cause, not the symptom — this is what a corrective action has to address"></div>
    <button class="btn small secondary" type="submit">Save findings</button>
  </form>

  <h3 class="tab-sub">4 · Decide <span class="muted">(§7.5.4)</span></h3>
  <?php if (!$canDecide): ?>
    <p class="muted" style="margin:0">You can record and investigate, but deciding needs the
      “Decide complaints and appeals” permission.</p>
  <?php elseif ($block !== ''): ?>
    <p class="pill p-bad" style="display:inline-block;margin:0"><?= e($block) ?></p>
  <?php else: ?>
  <form method="post" action="/complaint-decide" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Outcome</label>
      <select class="form-control" name="outcome">
        <?php foreach (CMP_OUTCOMES as $k=>$v): if ($k==='PENDING') continue; ?>
          <option value="<?= e($k) ?>" <?= $c['outcome']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>The decision and the reasons for it *</label>
      <textarea class="form-control" name="decision_note" rows="5" required
                placeholder="this paragraph goes to the complainant and is what an assessor reads"><?= e($c['decision_note']) ?></textarea></div>
    <button class="btn small" type="submit">Record the decision</button>
  </form>
  <?php endif; ?>

  <?php if ($c['outcome'] !== 'PENDING'): ?>
  <h3 class="tab-sub">5 · Tell the complainant</h3>
  <form method="post" action="/complaint-notify" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff"><label>Written to them on</label><input class="form-control" type="date" name="on" value="<?= e($c['notified_on'] ?: date('Y-m-d')) ?>"></div>
    <div class="ff ff-wide"><label>How, and to whom</label>
      <input class="form-control" name="notify_note" value="<?= e($c['notify_note']) ?>" placeholder="e.g. e-mailed the decision letter"></div>
    <button class="btn small" type="submit">Record it</button>
  </form>
  <?php endif; ?>

  <?php if (in_array($c['outcome'], CMP_NEEDS_CAPA, true)): ?>
  <h3 class="tab-sub">6 · Corrective action</h3>
  <?php $capa = ($c['capa_ref'] !== '' && function_exists('capa_by_ref')) ? capa_by_ref($c['capa_ref']) : null; ?>
  <?php if ($capa): ?>
    <p style="margin:0"><a href="/capa-item?id=<?= (int)$capa['id'] ?>"><strong><?= e($capa['ref']) ?></strong></a>
      — <?= e(strtok(CAPA_STATUS[$capa['status']] ?? $capa['status'], '—')) ?>.
      <?= $capa['verified_on'] ? 'Checked ' . e(fdate($capa['verified_on'])) . ': it '
            . ($capa['effective'] === 'YES' ? 'worked.' : 'did not work.')
          : 'Nobody has checked yet whether it worked.' ?></p>
  <?php else: ?>
    <p class="sub" style="margin-top:0">We found fault, so something has to change. Raising it here carries the
      complaint and the cause across, so the two records cannot end up disagreeing.</p>
    <?php if (function_exists('ncr_can_raise') && ncr_can_raise()): ?>
    <?php // Most upheld complaints are a nonconformity first: something was
          // wrong, and the work already delivered has to be put right. Whether
          // it also needs a corrective action is a separate judgement, taken on
          // the nonconformity — which is why this button comes first. ?>
    <a class="btn small" style="margin:0 0 8px;display:inline-block"
       href="/ncr-new?<?= e(http_build_query([
         'source' => 'COMPLAINT', 'complaint_id' => (int)$c['id'],
         'source_note' => $c['ref'] ?? '',
         'title' => 'Complaint upheld: ' . ($c['subject'] ?? ''),
         'description' => (string)($c['description'] ?? ''),
         'job_id' => (int)($c['job_id'] ?? 0) ?: '',
         'partner_id' => (int)($c['partner_id'] ?? 0) ?: '',
         'office_id' => (int)($c['office_id'] ?? 0) ?: '',
       ])) ?>">Raise a nonconformity from this</a><br>
    <?php endif; ?>
    <?php if (function_exists('capa_create') && can('mod.capa.edit')): ?>
    <form method="post" action="/capa-from-complaint" style="margin:0 0 8px">
      <input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
      <button class="btn small secondary" type="submit">…or go straight to a corrective action</button>
    </form>
    <?php endif; ?>
    <form method="post" action="/complaint-capa" class="inline-add">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <div class="ff"><label>…or record a reference kept elsewhere</label>
        <input class="form-control" name="capa_ref" value="<?= e($c['capa_ref']) ?>" placeholder="e.g. CAPA-2026-014"></div>
      <button class="btn small secondary" type="submit">Save</button>
    </form>
  <?php endif; ?>
  <?php endif; ?>

  <h3 class="tab-sub">7 · Close</h3>
  <?php if ($missing): ?>
    <p class="muted" style="margin:0">Not yet — <?= e(implode('; ', $missing)) ?>.</p>
  <?php else: ?>
  <form method="post" action="/complaint-close" style="margin:0">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <button class="btn" type="submit">Close <?= e($c['ref']) ?></button>
  </form>
  <?php endif; ?>
</div>
<?php elseif ($canRecord && $closed): ?>
<div class="panel" data-tab="Handle">
  <h3 class="tab-sub" style="margin-top:0">Reopen</h3>
  <form method="post" action="/complaint-reopen" class="inline-add">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="ff ff-wide"><label>Why</label><input class="form-control" name="reason" placeholder="what came to light"></div>
    <button class="btn small secondary" type="submit">Reopen</button>
  </form>
</div>
<?php endif; ?>

<?php if ($appeals): ?>
<div class="panel" data-tab="Overview">
  <div class="ctitle" style="margin-top:0"><h3>Appealed</h3></div>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Received</th><th>Outcome</th><th>Decided by</th></tr></thead>
    <tbody><?php foreach ($appeals as $a): ?>
      <tr><td><a href="/complaint?id=<?= (int)$a['id'] ?>"><?= e($a['ref']) ?></a></td>
        <td><?= e(fdate($a['received_on'])) ?></td>
        <td><?= e(strtok(CMP_OUTCOMES[$a['outcome']] ?? $a['outcome'], '—')) ?></td>
        <td class="muted"><?= e($a['decided_by'] ?: '—') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</div>
<?php endif; ?>

<div class="panel" data-tab="History">
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
</div><!-- /data-tabs (complaint) -->
