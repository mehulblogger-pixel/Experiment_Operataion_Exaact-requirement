<div class="crumbs"><a href="/">Home</a> › Corrective actions</div>
<?php if (function_exists('nc_tabs')) nc_tabs('capa'); ?>
<div class="master-head">
  <div><h1>Nonconformities &amp; corrective actions</h1>
    <p class="sub" style="margin:2px 0 0"><?= e(accreditation_ref('correctiveaction')) ?>. An action nobody went back to check is not a
      corrective action — it is a note. So nothing closes here until somebody has checked whether it worked.</p></div>
  <?php if ($canEdit): ?><a class="btn" href="/capa-new">Raise one</a><?php endif; ?>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🛠</span><div class="k">Open</div>
    <div class="v"><?= (int)$ready['open'] ?></div><div class="d">of <?= (int)$ready['total'] ?> ever</div></div>
  <div class="kpi"><span class="kic">⏰</span><div class="k">Action overdue</div>
    <div class="v"><?= $ready['action_late'] ? '<span class="down">' . (int)$ready['action_late'] . '</span>' : '0' ?></div>
    <div class="d">target <?= (int)$ready['due_days'] ?> days</div></div>
  <div class="kpi"><span class="kic">🔎</span><div class="k">Never checked</div>
    <div class="v"><?= $ready['verify_late'] ? '<span class="down">' . (int)$ready['verify_late'] . '</span>' : '0' ?></div>
    <div class="d">done, but nobody looked back</div></div>
  <div class="kpi"><span class="kic">❓</span><div class="k">“Anywhere else?” unanswered</div>
    <div class="v"><?= $ready['no_similar'] ? '<span class="down">' . (int)$ready['no_similar'] . '</span>' : '0' ?></div>
    <div class="d">§8.7.2 d)</div></div>
  <div class="kpi"><span class="kic">↻</span><div class="k">Didn’t work</div>
    <div class="v"><?= (int)$ready['failed'] ?></div><div class="d">carried forward honestly</div></div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>The register <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Ref</th><th>Raised</th><th>What</th><th>From</th><th>Owner</th><th>Due</th><th>Checked?</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c):
      $al = capa_action_overdue($c); $vl = capa_verify_overdue($c); ?>
      <tr>
        <td><a href="/capa-item?id=<?= (int)$c['id'] ?>"><strong><?= e($c['ref']) ?></strong></a>
          <?php if ($c['severity'] === 'MAJOR'): ?><br><span class="pill p-bad">major</span><?php endif; ?></td>
        <td class="muted"><?= e($c['raised_on'] ? fdate($c['raised_on']) : '—') ?></td>
        <td style="max-width:280px"><?= e($c['title']) ?>
          <?php if ($c['clause']): ?><div class="muted" style="font-size:12px">§<?= e($c['clause']) ?></div><?php endif; ?></td>
        <td class="muted"><?= e(strtok(CAPA_SOURCES[$c['source']] ?? $c['source'], '—')) ?>
          <?php if ($c['source_ref']): ?><br><?= e($c['source_ref']) ?><?php endif; ?></td>
        <td class="muted"><?= e($c['owner'] ?: '—') ?></td>
        <td><?= $al ? '<span class="pill p-bad">' . (int)$al . 'd late</span>' : e($c['due_on'] ? fdate($c['due_on']) : '—') ?></td>
        <td><?= $c['verified_on']
              ? ($c['effective'] === 'YES' ? '<span class="pill p-ok">worked</span>' : '<span class="pill p-bad">did not work</span>')
              : ($vl ? '<span class="pill p-bad">' . (int)$vl . 'd late</span>' : '<span class="muted">—</span>') ?></td>
        <td><?= capa_is_open($c) ? '<span class="pill p-warn">' . e(strtok(CAPA_STATUS[$c['status']] ?? $c['status'], '—')) . '</span>'
              : ($c['status'] === 'CLOSED' ? '<span class="pill p-ok">closed</span>' : '<span class="pill">superseded</span>') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8">Nothing raised yet. That is either a very good year or a register nobody uses — an assessor will assume the second.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (is_admin_level() || is_master()): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Our own deadlines</h3>
  <form method="post" action="/capa-settings" class="inline-add">
    <div class="ff"><label>Action to be done within (days)</label>
      <input class="form-control" type="number" min="1" name="capa_due_days" value="<?= (int)$ready['due_days'] ?>"></div>
    <div class="ff"><label>Check it worked after (days)</label>
      <input class="form-control" type="number" min="1" name="capa_verify_days" value="<?= (int)$ready['verify_days'] ?>">
      <small class="muted">Long enough for the change to have been tested by real work.</small></div>
    <button class="btn small" type="submit">Save</button>
  </form>
</div>
<?php endif; ?>
