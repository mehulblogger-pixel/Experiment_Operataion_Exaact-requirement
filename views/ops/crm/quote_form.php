<?php
  $isEdit = (bool)$q;
  $inqId  = $q['inquiry_id'] ?? ($preInq['id'] ?? '');
  $g = function($k, $d = '') use ($q, $preInq) {
    if ($q && isset($q[$k])) return $q[$k];
    if ($preInq && isset($preInq[$k])) return $preInq[$k];
    return $d;
  };
  // one blank template row appended for a new quote
  $rows = $lines ?: [[]];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/quotes">Quotations</a> › <?= $isEdit ? e(quote_label($q)) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $isEdit ? 'Edit — ' . e(quote_label($q)) : 'New quotation' ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= $preInq ? 'From inquiry ' . e($preInq['inquiry_no']) . '. ' : '' ?>Fill the header, add line items, save. Quote number is generated on save.</p></div>
  <a class="btn secondary" href="/quotes">← Back</a>
</div>

<form method="post" action="/<?= $isEdit ? 'quote-edit?id=' . (int)$q['id'] : 'quote-new' ?>" class="panel" id="qform">
  <?php if ($inqId !== ''): ?><input type="hidden" name="inquiry_id" value="<?= (int)$inqId ?>"><?php endif; ?>
  <h3 class="tab-sub" style="margin-top:0">Customer &amp; header</h3>
  <div class="form-grid">
    <div class="ff"><label>Client</label>
      <select class="form-control searchable" name="client_id"><option value="">— pick or type name —</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)$g('client_id')===(string)$cl['id']?'selected':'' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or client name</label><input class="form-control" name="client_name" value="<?= e($g('client_name')) ?>"></div>
    <div class="ff"><label>SBU (primary)</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $g('sbu')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>

    <div class="ff"><label>Contact person</label><input class="form-control" name="contact_name" value="<?= e($g('contact_name')) ?>"></div>
    <div class="ff"><label>Contact email</label><input class="form-control" name="contact_email" value="<?= e($g('contact_email')) ?>"></div>
    <div class="ff"><label>Contact mobile</label><input class="form-control" name="contact_mobile" value="<?= e($g('contact_mobile')) ?>"></div>

    <div class="ff"><label>Executing office</label>
      <select class="form-control searchable" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$g('office_id')===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Site / work location</label><input class="form-control" name="site_location" value="<?= e($g('site_location')) ?>"></div>
    <div class="ff"><label>Location type</label>
      <select class="form-control" name="location_type"><?php foreach ($locTypes as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($g('location_type','REGISTERED')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>

    <div class="ff ff-wide"><label>Subject / scope title</label><input class="form-control" name="subject" value="<?= e($g('subject')) ?>" placeholder="e.g. Third-party inspection services — Pipeline project"></div>

    <div class="ff"><label>Validity (days)</label><input class="form-control" type="number" name="validity_days" value="<?= e($g('validity_days','30')) ?>"></div>
    <div class="ff"><label>Currency</label><input class="form-control" name="currency" value="<?= e($g('currency','INR')) ?>"></div>
    <div class="ff"><label>GST %</label><input class="form-control" type="number" step="0.01" id="gst_pct" name="gst_pct" value="<?= e($g('gst_pct','18')) ?>"></div>

    <div class="ff"><label>Payment terms</label><input class="form-control" name="payment_terms" value="<?= e($g('payment_terms')) ?>" placeholder="e.g. 30 days from invoice"></div>
    <div class="ff"><label>Advance %</label><input class="form-control" type="number" step="0.01" name="advance_pct" value="<?= e($g('advance_pct','0')) ?>"></div>
    <div class="ff" style="align-self:end">
      <label class="chk"><input type="checkbox" name="advance_required" value="1" <?= $g('advance_required')?'checked':'' ?>> Advance required <span class="muted">before scheduling</span></label>
      <label class="chk" style="margin-top:6px"><input type="checkbox" name="report_vs_payment" value="1" <?= $g('report_vs_payment')?'checked':'' ?>> Report only against payment</label>
    </div>
  </div>

  <h3 class="tab-sub">Line items</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt" id="lines">
    <thead><tr>
      <th>SBU</th><th>Service</th><th>Sub-types</th><th>Description</th><th>Location</th><th>Type</th><th>Order</th>
      <th class="num">Qty</th><th>Unit</th><th class="num">Rate ₹</th><th class="num">Amount ₹</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $ln): ?>
    <tr class="lrow">
      <td><select class="form-control" name="l_sbu[]"><option value="">—</option><?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($ln['sbu'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
      <td><select class="form-control" name="l_service[]"><option value="">—</option><?php foreach ($svcOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($ln['service_type'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
      <td><input class="form-control" name="l_subtypes[]" value="<?= e($ln['subtypes'] ?? '') ?>" placeholder="e.g. Site QA/QC" style="min-width:120px"></td>
      <td><input class="form-control" name="l_desc[]" value="<?= e($ln['description'] ?? '') ?>" style="min-width:160px"></td>
      <td><input class="form-control" name="l_loc[]" value="<?= e($ln['location'] ?? '') ?>" style="min-width:110px"></td>
      <td><select class="form-control" name="l_loctype[]"><?php foreach ($locTypes as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ln['location_type'] ?? 'REGISTERED')===$k)?'selected':'' ?>><?= e($k) ?></option><?php endforeach; ?></select></td>
      <td><select class="form-control" name="l_order[]"><?php foreach ($orderOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ln['order_type'] ?? 'LINE')===$k)?'selected':'' ?>><?= e($k) ?></option><?php endforeach; ?></select></td>
      <td><input class="form-control num l_qty" type="number" step="0.01" name="l_qty[]" value="<?= e($ln['qty'] ?? '') ?>" style="width:80px"></td>
      <td><select class="form-control" name="l_unit[]"><?php foreach ($unitOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ln['unit'] ?? 'DAY')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
      <td><input class="form-control num l_rate" type="number" step="0.01" name="l_rate[]" value="<?= e($ln['rate'] ?? '') ?>" style="width:100px"></td>
      <td class="num l_amt"><?= isset($ln['amount']) ? number_format((float)$ln['amount'],0) : '0' ?></td>
      <td><button type="button" class="btn small danger lrm">✕</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin:8px 0"><button type="button" class="btn small secondary" id="addrow">+ Add line</button></div>

  <div class="panel" style="max-width:340px;margin-left:auto;background:var(--soft)">
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">Subtotal</span><b>₹<span id="t_sub">0</span></b></div>
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">GST (<span id="t_gp">18</span>%)</span><b>₹<span id="t_gst">0</span></b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--line);font-size:16px"><span>Total</span><b>₹<span id="t_tot">0</span></b></div>
  </div>

  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $isEdit ? 'Save quotation' : 'Create quotation' ?></button>
    <a class="btn secondary" href="/quotes">Cancel</a>
  </div>
</form>

<script>
(function(){
  var tbody = document.querySelector('#lines tbody');
  function nfmt(n){ return (Math.round(n)||0).toLocaleString('en-IN'); }
  function recalc(){
    var sub=0;
    tbody.querySelectorAll('.lrow').forEach(function(tr){
      var q=parseFloat(tr.querySelector('.l_qty').value)||0, r=parseFloat(tr.querySelector('.l_rate').value)||0;
      var a=q*r; tr.querySelector('.l_amt').textContent=nfmt(a); sub+=a;
    });
    var gp=parseFloat(document.getElementById('gst_pct').value)||0;
    var gst=sub*gp/100;
    document.getElementById('t_sub').textContent=nfmt(sub);
    document.getElementById('t_gp').textContent=gp;
    document.getElementById('t_gst').textContent=nfmt(gst);
    document.getElementById('t_tot').textContent=nfmt(sub+gst);
  }
  function wire(tr){
    tr.querySelectorAll('.l_qty,.l_rate').forEach(function(el){ el.addEventListener('input', recalc); });
    var rm=tr.querySelector('.lrm'); if(rm) rm.addEventListener('click', function(){ if(tbody.querySelectorAll('.lrow').length>1){ tr.remove(); recalc(); } else { tr.querySelectorAll('input').forEach(i=>i.value=''); recalc(); } });
  }
  tbody.querySelectorAll('.lrow').forEach(wire);
  document.getElementById('addrow').addEventListener('click', function(){
    var tr=tbody.querySelector('.lrow').cloneNode(true);
    tr.querySelectorAll('input').forEach(i=>i.value=''); tr.querySelectorAll('select').forEach(s=>s.selectedIndex=0);
    tr.querySelector('.l_amt').textContent='0'; tbody.appendChild(tr); wire(tr);
  });
  document.getElementById('gst_pct').addEventListener('input', recalc);
  recalc();
})();
</script>
