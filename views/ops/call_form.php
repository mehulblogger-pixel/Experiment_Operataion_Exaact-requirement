<h1><?= $call ? 'Edit Call ' . e($call['call_code']) : 'New Call' ?></h1>
<p class="sub">Log the inspection call. The call code is generated automatically.</p>
<form method="post" action="<?= $call ? '/call-edit?id=' . (int)$call['id'] : '/call-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Client</label>
      <select class="form-control searchable" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($call && $call['client_id']==$c['id'])?'selected':'' ?>><?= e(pname($c)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Vendor / Manufacturer (site)</label>
      <select class="form-control searchable" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= ($call && $call['vendor_id']==$v['id'])?'selected':'' ?>><?= e(pname($v)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>IBO / Managing office</label>
      <select class="form-control searchable" name="ibo_office_id"><option value="">— Ahmedabad's own —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($call && $call['ibo_office_id']==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Region</label>
      <select class="form-control searchable" name="region"><option value="">—</option>
        <?php foreach (lk_options_or('region', OPS_REGIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['region']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>SBU</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Product category</label>
      <select class="form-control searchable" name="product_category"><option value="">—</option>
        <?php foreach (lk_options_or('product', PRODUCT_CATS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['product_category']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Product (if "Others")</label><input class="form-control" name="product_other" value="<?= e($call['product_other'] ?? '') ?>"></div>
    <div class="ff"><label>Call received date</label><input class="form-control" type="date" name="call_received_date" value="<?= e($call['call_received_date'] ?? date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Inspection required by</label><input class="form-control" type="date" name="inspection_required_date" value="<?= e($call['inspection_required_date'] ?? '') ?>"></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($call['notes'] ?? '') ?>"></div>
    <?php render_custom_fields('call', $cfvals ?? []); ?>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save call</button>
    <a class="btn secondary" href="/calls">Cancel</a>
  </div>
</form>
