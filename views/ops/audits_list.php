<div class="crumbs"><a href="/">Home</a> › Internal audits</div>
<div class="master-head">
  <div><h1>Internal audits</h1>
    <p class="sub" style="margin:2px 0 0">ISO/IEC 17020 §8.8. The question an assessor asks is not “did you audit”
      — it is “did you audit <em>all</em> of it, and did the auditor audit their own work”.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/management-reviews">Management reviews</a>
    <?php if ($canEdit): ?><a class="btn" href="/internal-audit-new">Plan one</a><?php endif; ?>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">📋</span><div class="k">Audits on file</div>
    <div class="v"><?= (int)$ready['total'] ?></div><div class="d"><?= (int)$ready['open'] ?> not yet closed</div></div>
  <div class="kpi"><span class="kic">🕳</span><div class="k">Clauses not covered</div>
    <div class="v"><?= $ready['uncovered'] ? '<span class="down">' . (int)$ready['uncovered'] . '</span>' : '0' ?></div>
    <div class="d">of <?= (int)$ready['clauses'] ?>, in <?= (int)$ready['cycle_days'] ?> days</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">Nonconformities with no action</div>
    <div class="v"><?= $ready['nc_without_capa'] ? '<span class="down">' . (int)$ready['nc_without_capa'] . '</span>' : '0' ?></div>
    <div class="d">found, and left</div></div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Clause coverage <span class="muted">(last <?= (int)$ready['cycle_days'] ?> days)</span></h3></div>
  <p class="sub" style="margin-top:0">A body is expected to cover the whole standard across its cycle. Red is a clause
    nothing has looked at in that window.</p>
  <div class="checkgrid">
    <?php foreach ($coverage as $k => $c): ?>
      <span class="pill <?= $c['covered'] ? 'p-ok' : 'p-bad' ?>" style="margin:0 4px 4px 0;display:inline-block">
        <?= e($k) ?><?= $c['covered'] ? ' · ' . e(fdate($c['last'])) : ' · never' ?></span>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>The audits <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Planned</th><th>Carried out</th><th>Clauses</th><th>Auditor</th><th>Area owner</th><th>Findings</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): $f = audit_findings((int)$a['id']);
      $nc = count(array_filter($f, fn($x) => in_array($x['kind'], FINDING_NEEDS_CAPA, true))); ?>
      <tr>
        <td><a href="/internal-audit?id=<?= (int)$a['id'] ?>"><strong><?= e($a['ref']) ?></strong></a></td>
        <td class="muted"><?= e($a['planned_on'] ? fdate($a['planned_on']) : '—') ?></td>
        <td><?= $a['carried_out_on'] ? e(fdate($a['carried_out_on'])) : '<span class="muted">—</span>' ?></td>
        <td class="muted" style="max-width:200px"><?= e(implode(', ', audit_clauses_of($a))) ?></td>
        <td><?= e($a['auditor'] ?: '—') ?></td>
        <td class="muted"><?= e($a['area_owner'] ?: '—') ?></td>
        <td><?= count($f) ?><?= $nc ? ' <span class="pill p-bad">' . $nc . ' NC</span>' : '' ?></td>
        <td><?= $a['status'] === 'CLOSED' ? '<span class="pill p-ok">closed</span>'
              : '<span class="pill p-warn">' . e(AUDIT_STATUS[$a['status']] ?? $a['status']) . '</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8">Nothing planned. §8.8 expects a programme, not an audit the week before an assessment.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($canEdit && (is_admin_level() || is_master())): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">The cycle</h3>
  <form method="post" action="/audit-settings" class="inline-add">
    <input type="hidden" name="id" value="0">
    <div class="ff"><label>Cover every clause within (days)</label>
      <input class="form-control" type="number" min="30" name="audit_cycle_days" value="<?= (int)$ready['cycle_days'] ?>"></div>
    <button class="btn small" type="submit">Save</button>
  </form>
</div>
<?php endif; ?>
