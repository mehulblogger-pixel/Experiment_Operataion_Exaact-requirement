<div class="crumbs"><a href="/">Home</a> › Equipment</div>
<div class="master-head">
  <div><h1>Equipment register</h1>
    <p class="sub" style="margin:2px 0 0">Every measuring and test instrument, and the certificate proving it was
      calibrated when it was used — ISO/IEC 17020 §6.2. A <?= e(Tl('report')) ?> naming an instrument that was out of
      calibration <strong>will not issue</strong>.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if ($canEdit): ?><a class="btn" href="/equip-new">+ Add an instrument</a><?php endif; ?>
    <a class="btn secondary" href="/equipment<?= $showAll ? '' : '?all=1' ?>"><?= $showAll ? 'Hide retired' : 'Show retired' ?></a>
  </div>
</div>

<?php $overdue = array_values(array_filter($dueSoon, fn($r) => $r['days_left'] === null || $r['days_left'] < 0)); ?>
<?php if ($overdue): ?>
  <div class="msg msg-error"><strong><?= count($overdue) ?> instrument(s) cannot be used</strong> — no certificate on
    file, or calibration has lapsed. They are marked below.</div>
<?php elseif ($dueSoon): ?>
  <div class="msg msg-warning"><?= count($dueSoon) ?> instrument(s) fall due for calibration within 30 days.</div>
<?php endif; ?>

<div class="panel">
  <table class="dt">
    <thead><tr><th>Code</th><th>Instrument</th><th>Serial</th><th><?= e(TH('office')) ?></th>
      <th>Held by</th><th>Certificate</th><th>Valid to</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $cal = equipment_current_calibration($r['id']); $d = equipment_days_left($r['id']);
          $blk = equipment_block($r['id']); ?>
      <tr>
        <td><a href="<?= $canEdit ? '/equip-edit?id=' . (int)$r['id'] : '#' ?>"><strong><?= e($r['code']) ?></strong></a></td>
        <td><?= e($r['name']) ?><?php if ($r['kind']): ?> <span class="muted"><?= e($r['kind']) ?></span><?php endif; ?></td>
        <td><?= e($r['serial_no'] ?: '—') ?></td>
        <td><?= e($r['office_name'] ?: '—') ?></td>
        <td><?= e($r['inspector_name'] ?: '—') ?></td>
        <td><?= $cal ? e($cal['cert_no'] ?: '—') : '<span class="muted">none on file</span>' ?></td>
        <td><?= $cal && $cal['valid_to'] ? e(fdate($cal['valid_to'])) : '—' ?>
            <?php if ($d !== null && $d >= 0 && $d <= 30): ?><span class="pill p-warn"><?= (int)$d ?>d</span><?php endif; ?></td>
        <td><?php if ($blk !== ''): ?><span class="pill p-bad" title="<?= e($blk) ?>">cannot be used</span>
            <?php else: ?><span class="pill p-ok"><?= e(EQUIP_STATUS[$r['status']] ?? $r['status']) ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8">No instruments recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
