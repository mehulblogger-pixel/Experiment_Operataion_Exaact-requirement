<?php $act = activity_options_by_sbu(); ?>
<h1><?= $call ? 'Edit Call ' . e($call['call_code']) : 'New Call' ?></h1>
<p class="sub">Log the inspection call. Not in a list? Use <strong>+ Add new</strong> next to any dropdown — it's added and selected here. The call code is generated automatically.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= $call ? '/call-edit?id=' . (int)$call['id'] : '/call-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Client <a href="#" class="addlink" data-qa="client">+ Add new</a></label>
      <select class="form-control searchable" id="client_sel" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($call && $call['client_id']==$c['id'])?'selected':'' ?>><?= e(pname($c)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Vendor / Manufacturer (site) <a href="#" class="addlink" data-qa="vendor">+ Add new</a></label>
      <select class="form-control searchable" id="vendor_sel" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= ($call && $call['vendor_id']==$v['id'])?'selected':'' ?>><?= e(pname($v)) ?></option><?php endforeach; ?>
      </select></div>

    <div class="ff"><label>Managing / IBO office</label>
      <select class="form-control searchable" name="ibo_office_id"><option value="">— Ahmedabad's own —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($call && $call['ibo_office_id']==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Contracting office <span class="muted">(who owns the client / PO)</span></label>
      <select class="form-control searchable" id="con_sel" name="contracting_office_id"><option value="">— my office —</option>
        <?php $conCur = $call['contracting_office_id'] ?? (current_user()['home_office_id'] ?? null);
          foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($conCur==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Executing office (branch) <a href="#" class="addlink" data-qa="office">+ Add new</a></label>
      <select class="form-control searchable" id="exec_sel" name="executing_office_id"><option value="">— same office executes —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($call && ($call['executing_office_id']??null)==$o['id'])?'selected':'' ?>><?= e($o['name']) ?><?= $o['coordinator_name']?' · '.e($o['coordinator_name']):'' ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Same as contracting = billable value only (no inter-office credit). A different office opens the credit fields.</small></div>

    <div class="ff"><label>Region</label>
      <select class="form-control searchable" name="region"><option value="">—</option>
        <?php foreach (lk_options_or('region', OPS_REGIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['region']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>SBU</label>
      <select class="form-control" id="sbu_sel" name="sbu"><option value="">—</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Activity code <a href="#" class="addlink" data-qa="activity">+ Add new</a></label>
      <select class="form-control" id="activity_sel" name="activity_id"><option value="">— pick SBU first —</option>
        <?php if ($call && ($call['activity_id']??null)) { $curAct = lk_value($call['activity_id']); if ($curAct) echo '<option value="'.(int)$curAct['id'].'" selected>'.e($curAct['label']).'</option>'; } ?>
      </select></div>

    <div class="ff"><label>Type of inspection <span class="muted">(narrows to the client's types)</span></label>
      <select class="form-control searchable" id="insp_sel" name="inspection_type"><option value="">—</option>
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['inspection_type']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        <option value="OTHER" <?= ($call && ($call['inspection_type']??'')==='OTHER')?'selected':'' ?>>Other (type below)…</option>
      </select>
      <input class="form-control" id="insp_other" name="inspection_type_other" value="<?= e($call['inspection_type_other'] ?? '') ?>" placeholder="Other inspection type" style="margin-top:6px;<?= ($call && ($call['inspection_type']??'')==='OTHER')?'':'display:none' ?>"></div>
    <div class="ff" id="site_ff" style="<?= ($call && ($call['inspection_type']??'')==='DEPUTATION')?'':'display:none' ?>"><label>Deputation site (client's site)</label>
      <select class="form-control searchable" id="site_sel" name="site_address_id"><option value="">—</option>
        <?php if ($call && ($call['site_address_id']??null)) { $sa=ops_one("SELECT id,label,city FROM partner_addresses WHERE id=?", [$call['site_address_id']]); if ($sa) echo '<option value="'.(int)$sa['id'].'" selected>'.e(($sa['label']?:'Site').' '.$sa['city']).'</option>'; } ?>
      </select></div>
    <div class="ff"><label>Product category <a href="#" class="addlink" data-qa="product">+ Add new</a></label>
      <select class="form-control searchable" id="product_sel" name="product_category"><option value="">—</option>
        <?php foreach (lk_options_or('product', PRODUCT_CATS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['product_category']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Product (if "Others")</label><input class="form-control" name="product_other" value="<?= e($call['product_other'] ?? '') ?>"></div>

    <div class="ff"><label>Deputation type</label>
      <select class="form-control searchable" name="deputation_type"><option value="">—</option>
        <?php foreach (lk_options_or('deputation_type', ['Daily (single day)'=>'Daily (single day)','Multiple days'=>'Multiple days','Continuous days'=>'Continuous days','Monthly (PM deputation)'=>'Monthly (PM deputation)']) as $k=>$v): ?>
          <option value="<?= e($v) ?>" <?= ($call && ($call['deputation_type']??'')===$v)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"></div>

    <!-- SAME office → billable value only (ex-GST); DIFFERENT office → inter-office credit -->
    <div class="ff ff-wide" id="billbox">
      <div class="form-grid" id="samebox">
        <div class="ff"><label>Billable value to client — <strong>excluding GST</strong> (₹)</label>
          <input class="form-control" type="number" step="0.01" name="billable_value" value="<?= e($call['billable_value'] ?? '') ?>"></div>
        <div class="ff"><label>Basis</label>
          <select class="form-control" name="billable_basis"><option value="">—</option>
            <?php foreach (CREDIT_TYPES as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['billable_basis']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        <p class="muted ff-wide" style="margin:2px 2px 0">Same contracting &amp; executing office — no inter-office credit; this is the client-billable value (ex-GST).</p>
      </div>
      <div class="form-grid" id="crossbox" style="display:none">
        <div class="ff"><label>Credit to executing office (₹) <span class="muted">— required</span></label>
          <input class="form-control" type="number" step="0.01" name="expected_credit" value="<?= e($call['expected_credit'] ?? '') ?>"></div>
        <div class="ff"><label>Credit type</label>
          <select class="form-control" name="credit_type"><option value="">—</option>
            <?php foreach (CREDIT_TYPES as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['credit_type']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        <p class="muted ff-wide" style="margin:2px 2px 0">Contracting office proposes the credit; the executing office can revert with the credit it requires (on the call page after saving).</p>
      </div>
    </div>

    <div class="ff"><label>Against PO (client's orders)</label>
      <select class="form-control searchable" id="po_sel" name="po_id"><option value="">— open / none —</option>
        <?php if ($call && ($call['po_id']??null)) { $po=ops_one("SELECT id,po_number FROM partner_purchase_orders WHERE id=?", [$call['po_id']]); if ($po) echo '<option value="'.(int)$po['id'].'" selected>'.e($po['po_number']?:'Open order').'</option>'; } ?>
      </select></div>
    <div class="ff"><label>PO line item (tracks qty)</label>
      <select class="form-control searchable" id="po_line_sel" name="po_line_item_id"><option value="">—</option>
        <?php if ($call && ($call['po_line_item_id']??null)) { $li=ops_one("SELECT id,description FROM po_line_items WHERE id=?", [$call['po_line_item_id']]); if ($li) echo '<option value="'.(int)$li['id'].'" selected>'.e($li['description']).'</option>'; } ?>
      </select></div>
    <div class="ff"><label>Call received from client (date)</label><input class="form-control" type="date" name="call_received_date" value="<?= e($call['call_received_date'] ?? date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Client's expected inspection date</label><input class="form-control" type="date" name="inspection_required_date" value="<?= e($call['inspection_required_date'] ?? '') ?>"></div>

    <div class="ff ff-check"><input type="checkbox" name="notify_manager" <?= ($call && !empty($call['notify_manager']))?'checked':'' ?>><label>Also e-mail the branch manager on forwarding</label></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($call['notes'] ?? '') ?>"></div>
    <?php render_custom_fields('call', $cfvals ?? []); ?>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $call ? 'Save call' : 'Save call' ?></button>
    <a class="btn secondary" href="/calls">Cancel</a>
  </div>
</form>

<script>
(function(){
  var con=document.getElementById('con_sel'), ex=document.getElementById('exec_sel');
  var same=document.getElementById('samebox'), cross=document.getElementById('crossbox');
  if(!(con&&ex&&same&&cross)) return;
  function sync(){
    var e=ex.value||'', c=con.value||'';
    var isSame = (e==='') || (c!=='' && e===c);   // no executing branch, or executing == contracting
    same.style.display  = isSame ? '' : 'none';
    cross.style.display = isSame ? 'none' : '';
  }
  con.addEventListener('change', sync); ex.addEventListener('change', sync); sync();
})();
</script>

<!-- Quick-add modal -->
<div class="modal-back" id="qa_back" style="display:none;">
  <div class="modal">
    <h3 id="qa_title">Add</h3>
    <div class="ff"><label>Name *</label><input class="form-control" id="qa_name" autocomplete="off"></div>
    <div class="qa-field qa-cv">
      <div class="ff"><label>GSTIN (optional — auto PAN/State)</label><input class="form-control" id="qa_gstin"></div>
      <div class="ff ff-check"><input type="checkbox" id="qa_both"><label>Add to <strong>both</strong> Client &amp; Vendor lists</label></div>
    </div>
    <div class="qa-field qa-office">
      <div class="ff"><label>Code</label><input class="form-control" id="qa_code"></div>
      <div class="ff"><label>City</label><input class="form-control" id="qa_city"></div>
      <div class="ff"><label>Coordinator name</label><input class="form-control" id="qa_cname"></div>
      <div class="ff"><label>Coordinator email</label><input class="form-control" id="qa_cemail"></div>
      <div class="ff"><label>Manager name</label><input class="form-control" id="qa_mname"></div>
      <div class="ff"><label>Manager email</label><input class="form-control" id="qa_memail"></div>
    </div>
    <div class="qa-field qa-activity"><p class="muted">Will be added under the SBU currently selected on the form.</p></div>
    <div id="qa_err" class="msg msg-error" style="display:none;"></div>
    <div style="margin-top:14px;display:flex;gap:8px;">
      <button class="btn" type="button" id="qa_save">Add &amp; select</button>
      <button class="btn secondary" type="button" id="qa_cancel">Cancel</button>
    </div>
  </div>
</div>
<script>window.ACTIVITY = <?= json_encode($act) ?>;
window.INSPTYPES = <?= json_encode(lk_options_or('inspection_type', INSPECTION_TYPES)) ?>;</script>
