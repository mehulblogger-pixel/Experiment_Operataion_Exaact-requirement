<?php
  $act = activity_options_by_sbu();
  // Dates already on the call, padded so the coordinator always has a spare box.
  $curDates = call_dates_parse($call['inspection_dates'] ?? '');
  if (!$curDates && !empty($call['inspection_required_date'])) $curDates = [$call['inspection_required_date']];
  $curWd = array_filter(array_map('intval', explode(',', (string)($call['schedule_weekdays'] ?? ''))));
  $ex = credit_explainer($call['ibo_office_id'] ?? (current_user()['home_office_id'] ?? null), $call['executing_office_id'] ?? null);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/calls"><?= e(T_REG('call')) ?></a> › <?= $call ? e($call['call_code']) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $call ? 'Edit ' . e(Tl('call')) . ' ' . e($call['call_code']) : ucfirst(T_NEW('call')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Pick the <?= e(Tl('client')) ?> and the <?= e(Tl('quote')) ?> it is against — the commercial terms come across by themselves. Not in a list? Use <strong>+ Add new</strong> beside any dropdown.</p></div>
  <a class="btn secondary" href="/calls">← Back</a>
</div>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= $call ? '/call-edit?id=' . (int)$call['id'] : '/call-new' ?>" class="panel">

  <h3 class="tab-sub" style="margin-top:0">1. <?= e(T('client')) ?> &amp; <?= e(Tl('quote')) ?></h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('client')) ?> <a href="#" class="addlink" data-qa="client">+ Add new</a></label>
      <select class="form-control searchable" id="client_sel" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($call && $call['client_id']==$c['id'])?'selected':'' ?>><?= e(pname($c)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(T('quote')) ?> <span class="muted">— what was sold</span></label>
      <select class="form-control searchable" id="quote_sel" name="quotation_id" data-cur="<?= (int)($call['quotation_id'] ?? 0) ?>">
        <option value="">— pick a <?= e(Tl('client')) ?> first —</option>
      </select>
      <small class="muted">Accepted, approved or sent <?= e(Tlp('quote')) ?> for this <?= e(Tl('client')) ?>.</small></div>
    <div class="ff"><label>Contract number <span class="muted">— from the <?= e(Tl('quote')) ?></span></label>
      <input class="form-control" id="contract_no" name="contract_number" value="<?= e($call['contract_number'] ?? '') ?>" readonly
             style="background:var(--soft)"></div>

    <div class="ff ff-wide"><label>Line item on the <?= e(Tl('quote')) ?> <span class="muted">— which part of the order this <?= e(Tl('call')) ?> draws on</span></label>
      <select class="form-control searchable" id="qline_sel" name="quote_line_id" data-cur="<?= (int)($call['quote_line_id'] ?? 0) ?>">
        <option value="">— whole <?= e(Tl('quote')) ?> —</option>
      </select></div>

    <div class="ff"><label><?= e(T('vendor')) ?> / <?= e(Tl('manufacturer')) ?> (site) <a href="#" class="addlink" data-qa="vendor">+ Add new</a></label>
      <select class="form-control searchable" id="vendor_sel" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= ($call && $call['vendor_id']==$v['id'])?'selected':'' ?>><?= e(pname($v)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Shared folder / drive link <span class="muted">— the client's papers and our working files</span></label>
      <input class="form-control" type="url" name="folder_link" value="<?= e($call['folder_link'] ?? '') ?>" placeholder="https://…  (SharePoint, Google Drive, OneDrive)">
      <small class="muted">Travels with the <?= e(Tl('job')) ?>, so the <?= e(Tl('engineer')) ?> gets it too.</small></div>
  </div>

  <h3 class="tab-sub">2. What is being inspected <span class="muted">— filled from the <?= e(Tl('quote')) ?>, change if this <?= e(Tl('call')) ?> differs</span></h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('sbu')) ?></label>
      <select class="form-control" id="sbu_sel" name="sbu"><option value="">—</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Activity code <a href="#" class="addlink" data-qa="activity">+ Add new</a></label>
      <select class="form-control" id="activity_sel" name="activity_id"><option value="">— pick <?= e(T('sbu')) ?> first —</option>
        <?php if ($call && ($call['activity_id']??null)) { $curAct = lk_value($call['activity_id']); if ($curAct) echo '<option value="'.(int)$curAct['id'].'" selected>'.e($curAct['label']).'</option>'; } ?>
      </select></div>
    <div class="ff"><label>Type of inspection <span class="muted">(narrows to the <?= e(Tl('client')) ?>'s types)</span></label>
      <select class="form-control searchable" id="insp_sel" name="inspection_type"><option value="">—</option>
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['inspection_type']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        <option value="OTHER" <?= ($call && ($call['inspection_type']??'')==='OTHER')?'selected':'' ?>>Other (type below)…</option>
      </select>
      <input class="form-control" id="insp_other" name="inspection_type_other" value="<?= e($call['inspection_type_other'] ?? '') ?>" placeholder="Other inspection type" style="margin-top:6px;<?= ($call && ($call['inspection_type']??'')==='OTHER')?'':'display:none' ?>"></div>
    <div class="ff"><label>Product category <a href="#" class="addlink" data-qa="product">+ Add new</a></label>
      <select class="form-control searchable" id="product_sel" name="product_category"><option value="">—</option>
        <?php foreach (lk_options_or('product', PRODUCT_CATS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['product_category']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Product (if "Others")</label><input class="form-control" name="product_other" value="<?= e($call['product_other'] ?? '') ?>"></div>
    <div class="ff" id="site_ff" style="<?= ($call && ($call['inspection_type']??'')==='DEPUTATION')?'':'display:none' ?>"><label>Site (<?= e(Tl('client')) ?>'s site)</label>
      <select class="form-control searchable" id="site_sel" name="site_address_id"><option value="">—</option>
        <?php if ($call && ($call['site_address_id']??null)) { $sa=ops_one("SELECT id,label,city FROM partner_addresses WHERE id=?", [$call['site_address_id']]); if ($sa) echo '<option value="'.(int)$sa['id'].'" selected>'.e(($sa['label']?:'Site').' '.$sa['city']).'</option>'; } ?>
      </select></div>
  </div>

  <h3 class="tab-sub">3. When <span class="muted">— one day, several days, or a repeating pattern</span></h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(Tl('call')) ?> received from <?= e(Tl('client')) ?></label>
      <input class="form-control" type="date" name="call_received_date" value="<?= e($call['call_received_date'] ?? date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Engagement pattern</label>
      <select class="form-control searchable" id="dep_sel" name="deputation_type"><option value="">—</option>
        <?php foreach (lk_options_or('engagement_pattern', ['Daily (single day)'=>'Daily (single day)','Multiple days'=>'Multiple days','Continuous days'=>'Continuous days','Monthly (resident posting)'=>'Monthly (resident posting)']) as $k=>$v): ?>
          <option value="<?= e($v) ?>" <?= ($call && ($call['deputation_type']??'')===$v)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(Tl('client')) ?>'s expected date <span class="muted">— the first visit</span></label>
      <input class="form-control" type="date" name="inspection_required_date" value="<?= e($call['inspection_required_date'] ?? '') ?>"></div>
  </div>

  <div class="panel" style="background:var(--soft);margin:8px 0">
    <b>Inspection dates</b> <span class="muted">— add up to 5 here; more can be added on the <?= e(Tl('job')) ?> when it is allocated.</span>
    <div class="form-grid" id="datebox" style="margin-top:8px">
      <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="ff"><label>Date <?= $i + 1 ?></label>
          <input class="form-control" type="date" name="inspection_dates[]" value="<?= e($curDates[$i] ?? '') ?>"></div>
      <?php endfor; ?>
    </div>
    <div style="border-top:1px solid var(--line);margin-top:10px;padding-top:10px">
      <b>…or a repeating pattern</b>
      <span class="muted">— e.g. every Monday and Thursday until the end date. The dates are worked out and listed on the <?= e(Tl('call')) ?>, and stay editable afterwards.</span>
      <div class="form-grid" style="margin-top:8px">
        <div class="ff ff-wide"><label>On these days</label>
          <div class="chip-row pickbox" style="max-height:none">
            <?php foreach (WEEKDAY_NAMES as $n => $nm): ?>
              <label class="ff-check"><input type="checkbox" name="schedule_weekdays[]" value="<?= $n ?>" <?= in_array($n, $curWd, true)?'checked':'' ?>> <?= e($nm) ?></label>
            <?php endforeach; ?>
          </div></div>
        <div class="ff"><label>Until (end date)</label>
          <input class="form-control" type="date" name="schedule_end_date" value="<?= e($call['schedule_end_date'] ?? '') ?>"></div>
      </div>
    </div>
    <?php if ($curDates): ?>
      <p class="muted" style="margin:8px 2px 0"><b><?= count($curDates) ?> date(s) currently on this <?= e(Tl('call')) ?>:</b>
        <?= e(implode(', ', array_map(fn($d) => fdate($d), array_slice($curDates, 0, 12)))) ?><?= count($curDates) > 12 ? ' …' : '' ?></p>
    <?php endif; ?>
  </div>

  <h3 class="tab-sub">4. Which <?= e(TP('office')) ?>, and the money between them</h3>
  <div class="form-grid">
    <div class="ff"><label>Contracting <?= e(T('office')) ?> <span class="muted">— who holds the order</span></label>
      <select class="form-control searchable" id="ibo_sel" name="ibo_office_id"><option value="">— my <?= e(T('office')) ?> —</option>
        <?php $iboCur = $call['ibo_office_id'] ?? (current_user()['home_office_id'] ?? null);
          foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($iboCur==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Executing <?= e(T('office')) ?> <span class="muted">— who does the work</span> <a href="#" class="addlink" data-qa="office">+ Add new</a></label>
      <select class="form-control searchable" id="exec_sel" name="executing_office_id"><option value="">— the same <?= e(T('office')) ?> —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($call && ($call['executing_office_id']??null)==$o['id'])?'selected':'' ?>><?= e($o['name']) ?><?= $o['coordinator_name']?' · '.e($o['coordinator_name']):'' ?></option><?php endforeach; ?>
      </select></div>
    <?php if (!empty($showRegion)): ?>
    <div class="ff"><label>Region <span class="muted">— roll-up for <?= e(T('sbu')) ?> heads and the Business Director</span></label>
      <select class="form-control searchable" name="region"><option value="">—</option>
        <?php foreach (lk_options_or('region', OPS_REGIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && $call['region']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <?php else: ?>
      <input type="hidden" name="region" value="<?= e($call['region'] ?? '') ?>">
    <?php endif; ?>
  </div>

  <div class="panel" id="moneybox" style="background:var(--soft);margin:8px 0">
    <div id="creditnote" class="muted" style="margin-bottom:8px"><?= e($ex['text']) ?></div>
    <div class="form-grid" id="samebox">
      <div class="ff"><label>Value billable to the <?= e(Tl('client')) ?> — <strong>excluding GST</strong> (<?= e(cur_sym()) ?>)</label>
        <input class="form-control" type="number" step="0.01" id="billable_value" name="billable_value" value="<?= e($call['billable_value'] ?? '') ?>"></div>
      <div class="ff"><label>Basis <span class="muted">— as quoted</span></label>
        <select class="form-control" id="billable_basis" name="billable_basis"><option value="">—</option>
          <?php foreach (lk_options_or('charge_unit', CHARGE_UNITS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['billable_basis']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="form-grid" id="crossbox" style="display:none">
      <div class="ff"><label>Credit to the executing <?= e(T('office')) ?> (<?= e(cur_sym()) ?>) <span class="muted">— required</span></label>
        <input class="form-control" type="number" step="0.01" name="expected_credit" value="<?= e($call['expected_credit'] ?? '') ?>"></div>
      <div class="ff"><label>Credit basis</label>
        <select class="form-control" name="credit_type"><option value="">—</option>
          <?php foreach (lk_options_or('credit_type', CREDIT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($call && ($call['credit_type']??'')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Value billable to the <?= e(Tl('client')) ?> (<?= e(cur_sym()) ?>, ex-GST)</label>
        <input class="form-control" type="number" step="0.01" name="billable_value_x" value="<?= e($call['billable_value'] ?? '') ?>" disabled
               title="Shown for context — edit it in the same-office box"></div>
    </div>
  </div>

  <h3 class="tab-sub">5. Against the <?= e(Tl('client')) ?>'s purchase order <span class="muted">(optional)</span></h3>
  <div class="form-grid">
    <div class="ff"><label>Purchase order</label>
      <select class="form-control searchable" id="po_sel" name="po_id"><option value="">— open / none —</option>
        <?php if ($call && ($call['po_id']??null)) { $po=ops_one("SELECT id,po_number FROM partner_purchase_orders WHERE id=?", [$call['po_id']]); if ($po) echo '<option value="'.(int)$po['id'].'" selected>'.e($po['po_number']?:'Open order').'</option>'; } ?>
      </select></div>
    <div class="ff"><label>PO line item <span class="muted">(tracks quantity)</span></label>
      <select class="form-control searchable" id="po_line_sel" name="po_line_item_id"><option value="">—</option>
        <?php if ($call && ($call['po_line_item_id']??null)) { $li=ops_one("SELECT id,description FROM po_line_items WHERE id=?", [$call['po_line_item_id']]); if ($li) echo '<option value="'.(int)$li['id'].'" selected>'.e($li['description']).'</option>'; } ?>
      </select></div>
    <div class="ff ff-check"><input type="checkbox" name="notify_manager" <?= ($call && !empty($call['notify_manager']))?'checked':'' ?>><label>Also e-mail the branch manager on forwarding</label></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($call['notes'] ?? '') ?>"></div>
    <?php render_custom_fields('call', $cfvals ?? []); ?>
  </div>

  <div style="margin-top:16px;">
    <button class="btn" type="submit">Save <?= e(Tl('call')) ?></button>
    <a class="btn secondary" href="/calls">Cancel</a>
  </div>
</form>

<script>
(function(){
  // ---- §a.viii: say what the credit actually is, in the offices' own names ---
  var ibo=document.getElementById('ibo_sel'), ex=document.getElementById('exec_sel');
  var same=document.getElementById('samebox'), cross=document.getElementById('crossbox');
  var note=document.getElementById('creditnote');
  function offName(sel){ return sel.value ? sel.options[sel.selectedIndex].text.split(' · ')[0] : 'your office'; }
  function syncMoney(){
    var e=ex.value||'', m=ibo.value||'';
    var isSame = (e==='') || (e===m);
    same.style.display  = isSame ? '' : 'none';
    cross.style.display = isSame ? 'none' : '';
    note.textContent = isSame
      ? 'One office both holds the contract and does the work, so there is no inter-office credit — only the value billable to the client.'
      : (offName(ibo) + ' holds this contract and ' + offName(ex) + ' will do the work, so ' + offName(ibo)
         + ' gives ' + offName(ex) + ' a credit. Enter what ' + offName(ex) + ' is to receive — they can revert with the figure they need.');
  }
  ibo.addEventListener('change', syncMoney); ex.addEventListener('change', syncMoney); syncMoney();

  // ---- §a.i / §a.ii / §a.iv: the quote drives the commercial fields ---------
  var clientSel=document.getElementById('client_sel'), qSel=document.getElementById('quote_sel'),
      lineSel=document.getElementById('qline_sel'), contractBox=document.getElementById('contract_no');

  function fillQuotes(keep){
    var want = keep ? (qSel.value || qSel.dataset.cur || '') : '';
    qSel.innerHTML = '';
    var o0=document.createElement('option'); o0.value='';
    o0.textContent = clientSel.value ? '— none / direct call —' : '— pick a client first —';
    qSel.appendChild(o0);
    if (!clientSel.value) { fillLines(null); return; }
    fetch('/client-quotes?id=' + encodeURIComponent(clientSel.value))
      .then(function(r){ return r.json(); })
      .then(function(list){
        list.forEach(function(q){
          var el=document.createElement('option'); el.value=q.id; el.textContent=q.label;
          el.dataset.contract = q.contract_number || '';
          if (String(q.id) === String(want)) el.selected = true;
          qSel.appendChild(el);
        });
        if (qSel.value) loadQuote(true);
      }).catch(function(){});
  }
  function fillLines(ctx){
    var want = lineSel.value || lineSel.dataset.cur || '';
    lineSel.innerHTML='';
    var o0=document.createElement('option'); o0.value=''; o0.textContent='— whole quote —'; lineSel.appendChild(o0);
    if (!ctx || !ctx.lines) return;
    ctx.lines.forEach(function(l){
      var el=document.createElement('option'); el.value=l.id; el.textContent=l.label;
      el.dataset.sbu=l.sbu||''; el.dataset.service=l.service_type||'';
      el.dataset.activity=l.activity_id||''; el.dataset.amount=l.amount||''; el.dataset.unit=l.unit||'';
      if (String(l.id) === String(want)) el.selected = true;
      lineSel.appendChild(el);
    });
  }
  function setIfEmpty(el, v){ if (el && v && !el.value) el.value = v; }
  function loadQuote(initial){
    var opt = qSel.options[qSel.selectedIndex];
    contractBox.value = (opt && opt.dataset.contract) ? opt.dataset.contract : '';
    if (!qSel.value) { fillLines(null); return; }
    fetch('/quote-context?id=' + encodeURIComponent(qSel.value))
      .then(function(r){ return r.json(); })
      .then(function(ctx){
        if (!ctx) return;
        if (ctx.contract_number) contractBox.value = ctx.contract_number;
        // Carry the commercial terms across. On a fresh call fill everything; on
        // an existing one only fill blanks, so a deliberate change is not undone.
        var sbu=document.getElementById('sbu_sel');
        if (sbu && ctx.sbu && (!initial || !sbu.value)) { sbu.value = ctx.sbu; sbu.dispatchEvent(new Event('change')); }
        var prod=document.getElementById('product_sel');
        if (prod && ctx.product_category) setIfEmpty(prod, ctx.product_category);
        var insp=document.getElementById('insp_sel');
        if (insp && ctx.inspection_types && ctx.inspection_types.length) setIfEmpty(insp, ctx.inspection_types[0]);
        setIfEmpty(document.getElementById('billable_value'), ctx.total_amount || '');
        fillLines(ctx);
      }).catch(function(){});
  }
  // Choosing one line narrows the call to that part of the order.
  lineSel.addEventListener('change', function(){
    var o = lineSel.options[lineSel.selectedIndex]; if (!o || !o.value) return;
    var sbu=document.getElementById('sbu_sel');
    if (sbu && o.dataset.sbu) { sbu.value=o.dataset.sbu; sbu.dispatchEvent(new Event('change')); }
    var insp=document.getElementById('insp_sel');
    if (insp && o.dataset.service) insp.value=o.dataset.service;
    var bv=document.getElementById('billable_value');
    if (bv && o.dataset.amount) bv.value=o.dataset.amount;
    var bb=document.getElementById('billable_basis');
    if (bb && o.dataset.unit) bb.value=o.dataset.unit;
    // the activity list is filled by the SBU cascade; select afterwards
    if (o.dataset.activity) setTimeout(function(){
      var a=document.getElementById('activity_sel'); if (a) a.value=o.dataset.activity;
    }, 250);
  });
  clientSel.addEventListener('change', function(){ fillQuotes(false); });
  qSel.addEventListener('change', function(){ loadQuote(false); });
  fillQuotes(true);
})();
</script>

<!-- Quick-add modal -->
<div class="modal-back" id="qa_back" style="display:none;">
  <div class="modal">
    <h3 id="qa_title">Add</h3>
    <div class="ff"><label>Name *</label><input class="form-control" id="qa_name" autocomplete="off"></div>
    <div class="qa-field qa-cv">
      <div class="ff"><label>GSTIN (optional — auto PAN/State)</label><input class="form-control" id="qa_gstin"></div>
      <div class="ff ff-check"><input type="checkbox" id="qa_both"><label>Add to <strong>both</strong> <?= e(T('client')) ?> &amp; <?= e(T('vendor')) ?> lists</label></div>
    </div>
    <div class="qa-field qa-office">
      <div class="ff"><label>Code</label><input class="form-control" id="qa_code"></div>
      <div class="ff"><label>City</label><input class="form-control" id="qa_city"></div>
      <div class="ff"><label>Coordinator name</label><input class="form-control" id="qa_cname"></div>
      <div class="ff"><label>Coordinator email</label><input class="form-control" id="qa_cemail"></div>
      <div class="ff"><label>Manager name</label><input class="form-control" id="qa_mname"></div>
      <div class="ff"><label>Manager email</label><input class="form-control" id="qa_memail"></div>
    </div>
    <div class="qa-field qa-activity"><p class="muted">Will be added under the <?= e(T('sbu')) ?> currently selected on the form.</p></div>
    <div id="qa_err" class="msg msg-error" style="display:none;"></div>
    <div style="margin-top:14px;display:flex;gap:8px;">
      <button class="btn" type="button" id="qa_save">Add &amp; select</button>
      <button class="btn secondary" type="button" id="qa_cancel">Cancel</button>
    </div>
  </div>
</div>
<script>window.ACTIVITY = <?= json_encode($act) ?>;
window.INSPTYPES = <?= json_encode(lk_options_or('inspection_type', INSPECTION_TYPES)) ?>;</script>
