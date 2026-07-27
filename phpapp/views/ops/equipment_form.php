<?php $isEdit = !empty($e); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/equipment">Equipment</a> › <?= $isEdit ? e($e['code']) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $isEdit ? e($e['code'] . ' ' . $e['name']) : 'Add an instrument' ?></h1>
    <p class="sub" style="margin:2px 0 0">The identity somebody can read off the label, and every calibration
      certificate it has ever held.</p></div>
  <a class="btn secondary" href="/equipment">← Back</a>
</div>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($isEdit && $block !== ''): ?>
  <div class="msg msg-error"><strong>Not fit for use today.</strong> <?= e($block) ?></div>
<?php elseif ($isEdit): ?>
  <div class="msg msg-ok">In calibration and fit for use.</div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? '/equip-edit?id=' . (int)$e['id'] : '/equip-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Identification code *</label>
      <input class="form-control" name="code" required value="<?= e($e['code'] ?? '') ?>" placeholder="e.g. UTM-014">
      <small class="muted">What is written on the instrument itself.</small></div>
    <div class="ff"><label>Instrument</label><input class="form-control" name="name" value="<?= e($e['name'] ?? '') ?>" placeholder="e.g. Ultrasonic thickness gauge"></div>
    <div class="ff"><label>Type / method</label><input class="form-control" name="kind" value="<?= e($e['kind'] ?? '') ?>" placeholder="e.g. UT, DFT, hardness"></div>
    <div class="ff"><label>Make</label><input class="form-control" name="make" value="<?= e($e['make'] ?? '') ?>"></div>
    <div class="ff"><label>Model</label><input class="form-control" name="model" value="<?= e($e['model'] ?? '') ?>"></div>
    <div class="ff"><label>Serial number</label><input class="form-control" name="serial_no" value="<?= e($e['serial_no'] ?? '') ?>"></div>
    <div class="ff"><label>Range</label><input class="form-control" name="range_spec" value="<?= e($e['range_spec'] ?? '') ?>" placeholder="e.g. 0.6–300 mm"></div>
    <div class="ff"><label>Accuracy / uncertainty</label><input class="form-control" name="accuracy" value="<?= e($e['accuracy'] ?? '') ?>" placeholder="e.g. ±0.05 mm"></div>
    <div class="ff"><label><?= e(TH('office')) ?> that owns it</label>
      <select class="form-control searchable" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (($e['office_id'] ?? '')==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Held by</label>
      <select class="form-control searchable" name="inspector_id"><option value="">— in the office —</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (($e['inspector_id'] ?? '')==$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>State</label>
      <select class="form-control" name="status">
        <?php foreach (EQUIP_STATUS as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($e['status'] ?? 'ACTIVE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Quarantined, under repair or retired means it cannot be named on a <?= e(Tl('report')) ?>.</small></div>
    <div class="ff"><label>Calibration interval (months)</label>
      <input class="form-control" type="number" min="0" name="cal_interval_months" value="<?= e($e['cal_interval_months'] ?? 12) ?>">
      <small class="muted">Used to work out "valid to" when a certificate is filed without one.</small></div>
    <div class="ff"><label>Ownership</label>
      <select class="form-control" name="owned_by">
        <?php foreach (['OWN'=>'Ours','HIRED'=>'Hired','CLIENT'=>"Client's",'VENDOR'=>"Vendor's"] as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= (($e['owned_by'] ?? 'OWN')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($e['notes'] ?? '') ?>"></div>
  </div>
  <button class="btn" type="submit"><?= $isEdit ? 'Save' : 'Add instrument' ?></button>
</form>

<?php if ($isEdit): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Calibration history</h3>
  <p class="sub" style="margin-top:0">Certificates are <strong>never overwritten</strong>. A <?= e(Tl('report')) ?>
    issued in March is judged against the certificate that was live in March, which is only possible if the old ones stay.</p>
  <table class="grid">
    <tr><th>Certificate</th><th>Calibrated</th><th>Valid to</th><th>By</th><th>Traceable to</th><th>Result</th><th>File</th><th></th></tr>
    <?php foreach ($cals as $c): $lapsed = $c['valid_to'] !== '' && $c['valid_to'] < date('Y-m-d'); ?>
      <tr<?= $lapsed ? ' class="muted"' : '' ?>>
        <td><strong><?= e($c['cert_no'] ?: '—') ?></strong></td>
        <td><?= e($c['cal_date'] ? fdate($c['cal_date']) : '—') ?></td>
        <td><?= e($c['valid_to'] ? fdate($c['valid_to']) : '—') ?><?= $lapsed ? ' <span class="pill p-mut">lapsed</span>' : '' ?></td>
        <td><?= e($c['cal_body'] ?: '—') ?></td>
        <td><?= e($c['traceable_to'] ?: '—') ?></td>
        <td><?= $c['result']==='FAIL' ? '<span class="pill p-bad">FAIL</span>' : '<span class="pill p-ok">pass</span>' ?></td>
        <td><?= $c['file_name'] ? '<a href="/equip-cert?id=' . (int)$c['id'] . '" target="_blank" rel="noopener">' . e($c['file_name']) . '</a>' : '<span class="muted">none</span>' ?></td>
        <td><form method="post" action="/equip-cal-del" onsubmit="return confirm('Remove this certificate? The history is the evidence — only do this if it was filed by mistake.')" style="margin:0">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn small secondary" type="submit">Remove</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$cals): ?><tr><td colspan="8">No certificate on file — this instrument cannot be used on a <?= e(Tl('report')) ?> yet.</td></tr><?php endif; ?>
  </table>

  <h3 class="tab-sub">File a calibration certificate</h3>
  <form method="post" action="/equip-cal-add" enctype="multipart/form-data" class="inline-add">
    <input type="hidden" name="equipment_id" value="<?= (int)$e['id'] ?>">
    <div class="ff"><label>Certificate number</label><input class="form-control" name="cert_no"></div>
    <div class="ff"><label>Calibrated on</label><input class="form-control" type="date" name="cal_date"></div>
    <div class="ff"><label>Valid to</label><input class="form-control" type="date" name="valid_to">
      <small class="muted">Blank = calculated from the interval.</small></div>
    <div class="ff"><label>Calibrated by</label><input class="form-control" name="cal_body" placeholder="the laboratory"></div>
    <div class="ff"><label>Traceable to</label><input class="form-control" name="traceable_to" placeholder="e.g. NPL / national standard"></div>
    <div class="ff"><label>Result</label>
      <select class="form-control" name="result"><option value="PASS">Pass</option><option value="FAIL">Fail</option></select></div>
    <div class="ff"><label>The certificate</label><input class="form-control" type="file" name="cert_file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
    <div class="ff ff-wide"><label>Remarks</label><input class="form-control" name="remarks"></div>
    <button class="btn small" type="submit">File certificate</button>
  </form>
</div>
<?php endif; ?>
