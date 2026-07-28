<div class="crumbs"><a href="/">Home</a> › Complaints &amp; appeals</div>
<div class="master-head">
  <div><h1>Complaints &amp; appeals</h1>
    <p class="sub" style="margin:2px 0 0">ISO/IEC 17020 §7.5 and §7.6. What an assessor asks for is this list, with
      dates on it — when it arrived, when it was acknowledged, who decided it, and when the complainant was told.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/complaints-policy">Published description</a>
    <?php if ($canRecord): ?><a class="btn" href="/complaint-new">Record one</a><?php endif; ?>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">📬</span><div class="k">Open</div>
    <div class="v"><?= (int)$ready['open'] ?></div><div class="d">of <?= (int)$ready['total'] ?> ever</div></div>
  <div class="kpi"><span class="kic">⏰</span><div class="k">Acknowledgement late</div>
    <div class="v"><?= $ready['ack_late'] ? '<span class="down">' . (int)$ready['ack_late'] . '</span>' : '0' ?></div>
    <div class="d">target <?= (int)$ready['ack_days'] ?> days</div></div>
  <div class="kpi"><span class="kic">⚖️</span><div class="k">Decision late</div>
    <div class="v"><?= $ready['decide_late'] ? '<span class="down">' . (int)$ready['decide_late'] . '</span>' : '0' ?></div>
    <div class="d">target <?= (int)$ready['decide_days'] ?> days</div></div>
  <div class="kpi"><span class="kic">✉️</span><div class="k">Decided, not yet told</div>
    <div class="v"><?= $ready['unnotified'] ? '<span class="down">' . (int)$ready['unnotified'] . '</span>' : '0' ?></div>
    <div class="d">the classic finding</div></div>
  <div class="kpi"><span class="kic">↩️</span><div class="k">Appeals</div>
    <div class="v"><?= (int)$ready['appeals'] ?></div><div class="d"><?= (int)$ready['upheld'] ?> upheld in all</div></div>
</div>

<?php if ($ready['policy_default']): ?>
<div class="panel" style="border-left:4px solid var(--warn)">
  <p style="margin:0"><strong>The published description is still the wording this app shipped with.</strong>
    It is a working draft and it is already live at <a href="/complaints-policy">/complaints-policy</a>, which opens
    without signing in. Read it and put it in your own words — an assessor will ask whether it is really yours.</p>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>The register <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Received</th><th>From</th><th>About</th><th>Ours?</th><th>Outcome</th><th>Decided by</th><th>Told</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c):
      $ackLate = cmp_ack_overdue($c); $decLate = cmp_decide_overdue($c); ?>
      <tr>
        <td><a href="/complaint?id=<?= (int)$c['id'] ?>"><strong><?= e($c['ref']) ?></strong></a>
          <?php if ($c['kind'] === 'APPEAL'): ?><br><span class="pill p-warn">appeal</span><?php endif; ?></td>
        <td><?= e($c['received_on'] ? fdate($c['received_on']) : '—') ?>
          <?php if ($ackLate): ?><br><span class="pill p-bad">ack <?= (int)$ackLate ?>d late</span><?php endif; ?></td>
        <td><?= !empty($c['anonymous']) ? '<span class="muted">anonymous</span>' : e($c['complainant_name'] ?: '—') ?>
          <div class="muted" style="font-size:12px"><?= e(CMP_SOURCES[$c['source']] ?? $c['source']) ?></div></td>
        <td style="max-width:260px"><?= e($c['subject']) ?>
          <?php if ($c['report_irn']): ?><div class="muted" style="font-size:12px"><?= e($c['report_irn']) ?></div><?php endif; ?></td>
        <td><?= $c['validity'] === 'PENDING' ? '<span class="pill p-warn">not decided</span>'
              : ($c['validity'] === 'VALID' ? '<span class="pill p-ok">ours</span>'
              : '<span class="pill">' . e(strtok(CMP_VALIDITY[$c['validity']], '—')) . '</span>') ?></td>
        <td><?= $c['outcome'] === 'PENDING'
              ? ($decLate ? '<span class="pill p-bad">' . (int)$decLate . 'd late</span>' : '<span class="muted">pending</span>')
              : e(strtok(CMP_OUTCOMES[$c['outcome']], '—')) ?></td>
        <td class="muted"><?= e($c['decided_by'] ?: '—') ?></td>
        <td><?= $c['notified_on'] ? e(fdate($c['notified_on']))
              : ($c['outcome'] !== 'PENDING' ? '<span class="pill p-bad">not yet</span>' : '<span class="muted">—</span>') ?></td>
        <td><?= $c['status'] === 'CLOSED' ? '<span class="pill p-ok">closed</span>' : '<span class="pill p-warn">open</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9">Nothing on the register. That is only good news if people know how to reach you — check the <a href="/complaints-policy">published description</a>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (is_admin_level() || is_master()): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Our own deadlines, and what we publish</h3>
  <p class="sub" style="margin-top:0">The standard does not set the number of days — you do, and then you are held
    to it. Anything past these is chased by the nightly reminder and shown in red above.</p>
  <form method="post" action="/complaint-settings" class="inline-add">
    <div class="ff"><label>Acknowledge within (days)</label>
      <input class="form-control" type="number" min="1" name="complaint_ack_days" value="<?= (int)$ready['ack_days'] ?>"></div>
    <div class="ff"><label>Decide within (days)</label>
      <input class="form-control" type="number" min="1" name="complaint_decide_days" value="<?= (int)$ready['decide_days'] ?>"></div>
    <div class="ff ff-wide"><label>The description we publish <span class="muted">— readable at /complaints-policy without signing in (§7.5.1)</span></label>
      <textarea class="form-control" name="complaints_policy" rows="16"><?= e(cmp_policy_text()) ?></textarea></div>
    <button class="btn small" type="submit">Save</button>
  </form>
</div>
<?php endif; ?>
