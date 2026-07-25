<?php
  // Super-admin audit & compliance dashboard
  $sevPill = ['high'=>'p-bad','medium'=>'p-warn','low'=>'p-mut'];
  $isRisk = fn($a) => in_array($a, AUDIT_HIGH_RISK, true);
  $qs = fn($over) => '/audit-log?' . http_build_query(array_merge(array_diff_key($_GET, ['export'=>1]), $over));
  $maxA = $byAction ? max(array_map(fn($r)=>(int)$r['n'], $byAction)) : 0;
?>
<div class="crumbs"><a href="/">Home</a> › Audit &amp; compliance</div>
<div class="master-head">
  <div><h1>Audit &amp; compliance</h1>
    <p class="sub" style="margin:2px 0 0">Every critical action across all branches. Records are never deleted — deletions are soft and stay logged.</p></div>
  <a class="btn secondary" href="<?= e($qs(['export'=>'csv'])) ?>">⭳ Export CSV</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🛡️</span><div class="k">Total events</div><div class="v"><?= number_format($stats['total']) ?></div><div class="d">since go-live</div></div>
  <div class="kpi"><span class="kic">📅</span><div class="k">Today</div><div class="v"><?= number_format($stats['today']) ?></div><div class="d">events logged</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">High-risk (30d)</div><div class="v <?= $stats['risk30'] ? 'down' : '' ?>"><a href="<?= e($qs(['risk'=>1,'action'=>''])) ?>"><?= number_format($stats['risk30']) ?></a></div><div class="d">need review</div></div>
  <div class="kpi"><span class="kic">👥</span><div class="k">Active users (30d)</div><div class="v"><?= number_format($stats['users30']) ?></div><div class="d">recorded activity</div></div>
</div>

<?php if ($checks): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Compliance checks <span class="muted">(<?= count($checks) ?>)</span></h3></div>
  <?php foreach ($checks as $c): ?>
    <div style="padding:6px 0;border-bottom:1px solid var(--line)">
      <span class="pill <?= $sevPill[$c['sev']] ?? 'p-mut' ?>"><?= e(ucfirst($c['sev'])) ?></span>
      <?= e($c['text']) ?> <?php if (!empty($c['link'])): ?><a href="<?= e($c['link']) ?>">review →</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="panel" style="border:1px solid var(--ok)"><b style="color:var(--ok)">✓ No compliance issues detected</b> — approvers are assigned, nothing is stuck, and no date changes were recorded recently.</div>
<?php endif; ?>

<div class="dash-2col" style="align-items:start">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Activity by action <span class="muted">(30 days)</span></h3></div>
    <?php if (!$byAction): ?><p class="muted">No activity yet.</p><?php endif; ?>
    <div class="hbars">
      <?php foreach (array_slice($byAction, 0, 10) as $b): $w = $maxA ? round((int)$b['n']/$maxA*100) : 0; ?>
        <div class="hbar"><span><a href="<?= e($qs(['action'=>$b['action'],'risk'=>''])) ?>"><?= e(audit_action_label($b['action'])) ?></a><?= $isRisk($b['action']) ? ' <span class="pill p-bad" style="padding:0 4px;font-size:10px">risk</span>' : '' ?></span>
          <span class="track"><span class="fill" style="width:<?= $w ?>%"></span></span><span class="val"><?= (int)$b['n'] ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Most active users <span class="muted">(30 days)</span></h3></div>
    <?php if (!$byUser): ?><p class="muted">No activity yet.</p><?php endif; ?>
    <table class="dt"><tbody>
      <?php foreach ($byUser as $u): ?>
        <tr><td><a href="<?= e($qs(['user'=>$u['username']])) ?>"><?= e($u['username']) ?></a></td><td class="num"><?= (int)$u['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php if ($byOffice): ?>
      <div style="margin-top:10px"><div class="muted" style="font-size:12px;margin-bottom:4px">By branch</div>
      <?php foreach ($byOffice as $o): ?>
        <a class="pill p-mut" style="margin:2px" href="<?= e($qs(['office'=>$o['office_id']])) ?>"><?= e($o['office_name'] ?: 'Unassigned') ?> · <?= (int)$o['n'] ?></a>
      <?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</div>

<form method="get" action="/audit-log" class="panel" style="display:flex;gap:6px;flex-wrap:wrap;align-items:end">
  <div class="ff" style="margin:0"><label>Action</label>
    <select class="form-control" name="action" style="min-width:170px"><option value="">All</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $act===$a?'selected':'' ?>><?= e(audit_action_label($a)) ?></option><?php endforeach; ?></select></div>
  <div class="ff" style="margin:0"><label>Branch</label>
    <select class="form-control" name="office" style="min-width:150px"><option value="">All</option>
      <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$office===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
  <div class="ff" style="margin:0"><label>User</label><input class="form-control" name="user" value="<?= e($user) ?>" style="max-width:150px"></div>
  <div class="ff" style="margin:0"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="ff" style="margin:0"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="ff" style="margin:0;flex:1;min-width:160px"><label>Search (IRN / user / value / reason)</label><input class="form-control" name="q" value="<?= e($q) ?>"></div>
  <label class="chk" style="margin-bottom:6px"><input type="checkbox" name="risk" value="1" <?= $risk?'checked':'' ?>> High-risk only</label>
  <button class="btn small" type="submit">Filter</button>
  <a class="btn small secondary" href="/audit-log">Clear</a>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:10px 14px 0"><h3>Audit log <span class="muted">(<?= number_format($total) ?> match<?= $total===1?'':'es' ?><?= $total > count($rows) ? ', showing latest '.count($rows) : '' ?>)</span></h3></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>When</th><th>IRN / ref</th><th>Action</th><th>By</th><th>Role</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:16px">No audit entries match these filters.</td></tr><?php endif; ?>
      <?php foreach ($rows as $a): ?>
      <tr<?= $isRisk($a['action']) ? ' style="background:color-mix(in srgb,var(--bad) 5%,transparent)"' : '' ?>>
        <td class="muted" style="white-space:nowrap"><?= e($a['created_at'] ? date('d M Y H:i', strtotime($a['created_at'])) : '—') ?></td>
        <td><?= e($a['irn'] ?: ($a['field'] ?: '—')) ?></td>
        <td><span class="pill <?= $isRisk($a['action']) ? 'p-bad' : 'p-mut' ?>"><?= e(audit_action_label($a['action'])) ?></span></td>
        <td><?= e($a['username']) ?></td>
        <td class="muted"><?= e(ORG_ROLES[$a['role']] ?? $a['role']) ?></td>
        <td class="muted"><?= e(trim(($a['field'] && $a['irn'] ? $a['field'].': ' : '') . ($a['old_value'] ? $a['old_value'].' → ' : '') . ($a['new_value'] ?? '')) ?: ($a['reason'] ?? '')) ?><?= ($a['reason'] && $a['new_value']) ? ' <em>('.e($a['reason']).')</em>' : '' ?></td>
        <td class="muted" style="font-size:11px"><?= e($a['ip'] ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<p class="muted" style="margin-top:8px;font-size:12px">Audit records are immutable and are never purged. Report deletion is a soft delete — the record and its history remain for review.</p>
