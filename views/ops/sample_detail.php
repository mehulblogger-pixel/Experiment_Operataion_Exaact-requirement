<?php
$badge = ['RECEIVED' => 'AMBER', 'IN_TESTING' => 'BLUE', 'HOLD' => 'AMBER',
          'RETURNED' => 'GREEN', 'DISPOSED' => 'GREY'][$s['status']] ?? 'GREY';
$open = in_array($s['status'], SAMPLE_OPEN, true);
$row = fn($lbl, $val) => $val !== '' && $val !== null ? '<tr><th style="text-align:left;width:190px;color:#697787;font-weight:600">' . e($lbl) . '</th><td>' . e($val) . '</td></tr>' : '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/samples">Items &amp; samples</a> › <?= e($s['item_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($s['item_code']) ?> <span class="badge <?= $badge ?>"><?= e(str_replace('_', ' ', $s['status'])) ?></span></h1>
    <p class="sub"><?= e($s['description']) ?></p></div>
  <?php if ($canEdit): ?><a class="btn secondary" href="/sample-edit?id=<?= (int)$s['id'] ?>">Edit</a><?php endif; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="grid" style="margin:0">
    <?= $row('Type', sample_label('sample_type', $s['item_type'])) ?>
    <?= $row('Maker / heat / batch', $s['maker_ref']) ?>
    <?= $row('Quantity', trim($s['quantity'] . ' ' . $s['unit'])) ?>
    <?= $row('Received on', $s['received_on']) ?>
    <?= $row('Received by', $s['received_by']) ?>
    <?= $row('Condition on receipt', sample_label('sample_condition', $s['condition_code'])) ?>
    <?= $row('Condition note', $s['condition_note']) ?>
    <?= $row('Storage location', sample_label('sample_storage', $s['storage_code'])) ?>
    <?php if (!empty($s['closed_on'])): ?>
      <?= $row('Outcome', $s['disposition'] === 'RETURN' ? 'Returned to owner' : 'Disposed of') ?>
      <?= $row('Closed on', $s['closed_on'] . ' by ' . $s['closed_by']) ?>
      <?= $row('Closing note', $s['closed_note']) ?>
    <?php endif; ?>
    <?php foreach (($cvals ?? []) as $lbl => $val): ?><?= $row($lbl, $val) ?><?php endforeach; ?>
    <?= $row('Notes', $s['notes']) ?>
  </table>
</div>

<h3 style="margin-top:24px">Chain of custody</h3>
<div class="panel" style="padding:14px 16px">
  <?php if (!$custody): ?><p class="muted" style="margin:0">No custody entries yet.</p><?php else: ?>
  <ul style="list-style:none;margin:0;padding:0">
    <?php foreach ($custody as $c): ?>
      <li style="padding:8px 0;border-bottom:1px solid var(--line-2,#eee)">
        <strong><?= e(str_replace('_', ' ', $c['action'])) ?></strong>
        <?php if ($c['location']): ?> → <?= e($c['location']) ?><?php endif; ?>
        <span class="muted" style="font-size:12px">· <?= e($c['actor']) ?> · <?= e(substr((string)$c['at'], 0, 16)) ?></span>
        <?php if ($c['note']): ?><br><span class="muted" style="font-size:13px"><?= e($c['note']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<?php if ($canEdit && $open): ?>
<div class="grid two" style="margin-top:20px">
  <div class="card">
    <h4 style="margin-top:0">Add a custody note / move</h4>
    <form method="post" action="/sample-event">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <label class="form-label">Move to storage (optional)</label>
      <select class="form-control" name="location">
        <option value="">— keep where it is —</option>
        <?php foreach (sample_opts('sample_storage') as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <input class="form-control" name="note" placeholder="What happened / who took it" style="margin-top:8px">
      <button class="btn small" type="submit" style="margin-top:10px">Record</button>
    </form>
  </div>
  <div class="card">
    <h4 style="margin-top:0">Change status</h4>
    <form method="post" action="/sample-status">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <select class="form-control" name="status">
        <option value="IN_TESTING">Move to testing / inspection</option>
        <option value="HOLD">Put on hold</option>
        <option value="RECEIVED">Back to received</option>
        <option value="RETURNED">Close — returned to owner</option>
        <option value="DISPOSED">Close — disposed of</option>
      </select>
      <input class="form-control" name="note" placeholder="Note (required to close)" style="margin-top:8px">
      <button class="btn small" type="submit" style="margin-top:10px">Update status</button>
    </form>
  </div>
</div>
<?php endif; ?>
