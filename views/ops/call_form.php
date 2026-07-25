<?php
  $act = activity_options_by_sbu();
  // Dates already on the call, padded so the coordinator always has a spare box.
  $curDates = call_dates_parse($call['inspection_dates'] ?? '');
  if (!$curDates && !empty($call['inspection_required_date'])) $curDates = [$call['inspection_required_date']];
  $curWd = array_filter(array_map('intval', explode(',', (string)($call['schedule_weekdays'] ?? ''))));
  $ex = credit_explainer($call['ibo_office_id'] ?? (current_user()['home_office_id'] ?? null), $call['executing_office_id'] ?? null);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/calls"><?= e(T_REG('call')) ?></a> › <?= $isEdit ? e($call['call_code']) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $isEdit ? 'Edit ' . e(Tl('call')) . ' ' . e($call['call_code']) : ucfirst(T_NEW('call')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Pick the <?= e(Tl('client')) ?> and the <?= e(Tl('quote')) ?> it is against — the commercial terms come across by themselves. Not in a list? Use <strong>+ Add new</strong> beside any dropdown.</p></div>
  <a class="btn secondary" href="/calls">← Back</a>
</div>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= $isEdit ? '/call-edit?id=' . (int)$call['id'] : '/call-new' ?>" class="panel">

  <h3 class="tab-sub" style="margin-top:0">1. <?= e(T('client')) ?> &amp; <?= e(Tl('quote')) ?></h3>
  <?php // §b — a party added in a hurry has a name and nothing else. Say so here,
        // while the client is still on the phone, rather than three weeks later
        // when the engineer is at the gate with nobody to ring. ?>
  <div id="party_gaps" style="display:none;margin:0 0 12px"></div>
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
    <?php // Read-only ONLY while a quotation is driving it. On a direct call there
          // is no cascade to fill it, and a call drawing down against a live ARC
          // still has a contract number the client will quote back at you — a
          // locked, permanently blank box made that impossible to record. ?>
    <div class="ff"><label>Contract number <span class="muted" id="cn_hint">— from the <?= e(Tl('quote')) ?></span></label>
      <input class="form-control" id="contract_no" name="contract_number" value="<?= e($call['contract_number'] ?? '') ?>"
             placeholder="pick a <?= e(Tl('quote')) ?>, or type it for a direct <?= e(Tl('call')) ?>">
      <small class="muted" id="cn_note" style="display:none">Typed by hand — no <?= e(Tl('quote')) ?> is driving this <?= e(Tl('call')) ?>.</small></div>

    <div class="ff ff-wide"><label>Line item on the <?= e(Tl('quote')) ?> <span class="muted">— which part of the order this <?= e(Tl('call')) ?> draws on</span></label>
      <select class="form-control searchable" id="qline_sel" name="quote_line_id" data-cur="<?= (int)($call['quote_line_id'] ?? 0) ?>">
        <option value="">— whole <?= e(Tl('quote')) ?> —</option>
      </select></div>

    <?php // The site is very often the client's own premises — a manufacturer who
          // buys inspection of their own works. That partner is a client AND the
          // site, so it is offered here in one tick rather than making somebody
          // go and flag the client as a vendor first. ?>
    <div class="ff"><label><?= e(T('vendor')) ?> / <?= e(Tl('manufacturer')) ?> (site) <a href="#" class="addlink" data-qa="vendor">+ Add new</a></label>
      <select class="form-control searchable" id="vendor_sel" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= ($call && $call['vendor_id']==$v['id'])?'selected':'' ?>><?= e(pname($v)) ?></option><?php endforeach; ?>
      </select>
      <label class="chk" style="margin-top:5px"><input type="checkbox" id="site_is_client">
        Inspection is at the <?= e(Tl('client')) ?>'s own premises</label>
      <small class="muted" id="sic_note" style="display:none">The <?= e(Tl('client')) ?> is recorded as the site too.</small></div>
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

  <?php // §h — a single-day call needs one date, which is already captured above
        // as the client's expected date. The five-date grid and the repeating
        // pattern only make sense for a multi-day or recurring engagement, so
        // they stay out of the way until the pattern says otherwise. ?>
  <div class="panel" id="dates_panel" style="background:var(--soft);margin:8px 0">
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

  <?php // §i — what the client is owed in the way of reporting is agreed when the
        // call is taken, not invented at allocation. Both fields are carried onto
        // every job raised from this call, so the engineer is asked for exactly
        // what was promised. ?>
  <h3 class="tab-sub">6. Reporting owed to the <?= e(Tl('client')) ?> <span class="muted">— carried onto the <?= e(TP('job')) ?></span></h3>
  <?php
    $callFreq  = $call['reporting_frequency'] ?? '';
    $callDays  = $call['report_custom_days'] ?? '';
    $callDeliv = array_values(array_filter(array_map('trim', explode(',', (string)($call['deliverables'] ?? '')))));
  ?>
  <div class="form-grid">
    <div class="ff"><label>Reporting frequency</label>
      <select class="form-control" id="call_freq_sel" name="reporting_frequency">
        <option value="">— decide at allocation —</option>
        <?php foreach (lk_options_or('reporting_frequency', REPORT_FREQ) as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $callFreq===$k?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">How often the <?= e(Tl('engineer')) ?> must send progress in — shown on the <?= e(T_REG('call')) ?>.</small></div>
    <div class="ff" id="call_days_wrap" style="<?= $callFreq==='CUSTOM'?'':'display:none' ?>"><label>…every how many days?</label>
      <input class="form-control" type="number" min="1" name="report_custom_days" value="<?= e($callDays) ?>" placeholder="e.g. 3"></div>

    <div class="ff ff-wide"><label>Types of <?= e(TP('report')) ?> required</label>
      <div class="checkgrid">
        <?php foreach (deliverable_options() as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="deliverables[]" value="<?= e($k) ?>" <?= in_array($k, $callDeliv, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Each format ticked is handed to the <?= e(Tl('engineer')) ?> on every <?= e(Tl('job')) ?> raised from this <?= e(Tl('call')) ?>, and is the only list they can report against. Maintained in the <a href="/report-types" target="_blank"><?= e(Tl('report')) ?> types</a> register.</small></div>
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

  // ---- §h: show the date grid only when the engagement is multi-day ---------
  var depSel = document.getElementById('dep_sel'), datesPanel = document.getElementById('dates_panel');
  function syncDates(){
    if (!depSel || !datesPanel) return;
    var v = (depSel.value || '').toLowerCase();
    var single = v.indexOf('single') >= 0 || v === '';
    // Never hide dates that are already recorded — an existing call would look
    // as though its schedule had been wiped.
    var hasDates = Array.prototype.some.call(
      datesPanel.querySelectorAll('input[type=date]'), function(i){ return !!i.value; });
    var hasPattern = Array.prototype.some.call(
      datesPanel.querySelectorAll('input[type=checkbox]'), function(i){ return i.checked; });
    datesPanel.style.display = (single && !hasDates && !hasPattern) ? 'none' : '';
  }
  if (depSel) depSel.addEventListener('change', syncDates);
  syncDates();

  // ---- §i: "every N days" only matters when the frequency is Custom ----------
  var freqSel = document.getElementById('call_freq_sel'), daysWrap = document.getElementById('call_days_wrap');
  if (freqSel && daysWrap) {
    freqSel.addEventListener('change', function(){
      daysWrap.style.display = freqSel.value === 'CUSTOM' ? '' : 'none';
    });
  }

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
        else setContractMode(false);        // direct call — the box is the user's
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
  // ---- §11: the client's own works is the inspection site -------------------
  // Ticking this copies the client into the site box, adding the option if the
  // partner has never been used as a site before. The server flags them as a
  // site partner on save, so next time they are simply in the list.
  var sicBox = document.getElementById('site_is_client'), sicNote = document.getElementById('sic_note');
  function clientOptionLabel(){
    var o = clientSel.options[clientSel.selectedIndex];
    return o && o.value ? o.textContent.trim() : '';
  }
  function applySameAsClient(){
    if (!sicBox.checked) { sicNote.style.display = 'none'; return; }
    var cid = clientSel.value, label = clientOptionLabel();
    if (!cid) { sicBox.checked = false; alert('Pick the client first.'); return; }
    var found = false;
    Array.prototype.forEach.call(vendorSel.options, function(o){ if (o.value === cid) found = true; });
    if (!found) {
      var op = document.createElement('option');
      op.value = cid; op.textContent = label + ' (client)';
      vendorSel.appendChild(op);
    }
    vendorSel.value = cid;
    vendorSel.dispatchEvent(new Event('change', {bubbles:true}));
    sicNote.style.display = '';
  }
  var vendorSel = document.getElementById('vendor_sel');
  sicBox.addEventListener('change', applySameAsClient);
  // Untick it the moment somebody chooses a different site by hand.
  vendorSel.addEventListener('change', function(){
    if (sicBox.checked && vendorSel.value !== clientSel.value) {
      sicBox.checked = false; sicNote.style.display = 'none';
    }
  });
  // Reflect an already-saved call where the two are the same.
  if (vendorSel.value && vendorSel.value === clientSel.value) {
    sicBox.checked = true; sicNote.style.display = '';
  }

  function setIfEmpty(el, v){ if (el && v && !el.value) el.value = v; }
  // The contract box is owned by the quotation when one is chosen, and by the
  // user when one is not. Switching to "direct call" must NOT wipe a number the
  // user typed themselves — that was how a direct call against a live ARC ended
  // up with no contract reference at all.
  var cnHint = document.getElementById('cn_hint'), cnNote = document.getElementById('cn_note');
  function setContractMode(fromQuote, value){
    if (fromQuote) {
      contractBox.value = value || '';
      contractBox.readOnly = true;
      contractBox.style.background = 'var(--soft)';
      if (cnHint) cnHint.textContent = '— from the quotation';
      if (cnNote) cnNote.style.display = 'none';
    } else {
      contractBox.readOnly = false;
      contractBox.style.background = '';
      if (cnHint) cnHint.textContent = '— type it if this draws on an existing contract';
      if (cnNote) cnNote.style.display = '';
    }
  }
  function loadQuote(initial){
    var opt = qSel.options[qSel.selectedIndex];
    if (!qSel.value) { setContractMode(false); fillLines(null); return; }
    setContractMode(true, opt && opt.dataset.contract ? opt.dataset.contract : '');
    fetch('/quote-context?id=' + encodeURIComponent(qSel.value))
      .then(function(r){ return r.json(); })
      .then(function(ctx){
        if (!ctx) return;
        if (ctx.contract_number) contractBox.value = ctx.contract_number;
        // remembered so clearing the line falls back to the quote's own types
        window.__quoteTypes = ctx.inspection_types || null;
        // The product category is a header field on the quotation — one order,
        // one category — so it narrows off the quote rather than off the line.
        window.__quoteProduct = ctx.product_category || null;
        narrowToLine(null, window.__quoteTypes);
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
  // ---- §e: narrow the call's own dropdowns to what the order actually sold ---
  // The whole catalogue is the wrong list once a quotation line is chosen: that
  // line sold one service, at one product category. Offering everything is how
  // a call gets raised for work nobody quoted. The full list stays one click
  // away, because a client does occasionally ask for something extra.
  var INSP_ALL = null, PROD_ALL = null;
  function snapshot(sel){
    return Array.prototype.map.call(sel.options, function(o){
      return {v:o.value, t:o.textContent, s:o.selected};
    });
  }
  function rebuild(sel, all, allow, keep){
    if (!sel) return;
    var cur = keep || sel.value;
    sel.innerHTML = '';
    all.forEach(function(o){
      if (allow && o.v && allow.indexOf(o.v) === -1 && o.v !== cur) return;
      var op = document.createElement('option');
      op.value = o.v; op.textContent = o.t;
      if (o.v === cur) op.selected = true;
      sel.appendChild(op);
    });
    // The enhancer wraps the select in its own text input; rebuilding the
    // options behind it means that input has to be re-synced.
    var wrap = sel.closest('.ss-wrap');
    if (wrap) { var inp = wrap.querySelector('input'); if (inp) inp.value = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent.trim() : ''; }
  }
  function narrowNote(sel, on, label){
    var ff = sel ? sel.closest('.ff') : null; if (!ff) return;
    var note = ff.querySelector('.narrow-note');
    if (!on) { if (note) note.remove(); return; }
    if (note) return;
    note = document.createElement('small');
    note.className = 'muted narrow-note';
    note.innerHTML = 'Limited to what the ' + label + ' sold. <a href="#">Show every option</a>';
    note.querySelector('a').addEventListener('click', function(e){
      e.preventDefault();
      if (sel.id === 'insp_sel' && INSP_ALL) rebuild(sel, INSP_ALL, null);
      if (sel.id === 'product_sel' && PROD_ALL) rebuild(sel, PROD_ALL, null);
      note.remove();
    });
    ff.appendChild(note);
  }
  function narrowToLine(o, ctxTypes){
    var insp = document.getElementById('insp_sel'), prod = document.getElementById('product_sel');
    if (insp && !INSP_ALL) INSP_ALL = snapshot(insp);
    if (prod && !PROD_ALL) PROD_ALL = snapshot(prod);
    // A chosen line pins one service; otherwise allow everything the quote sold.
    var allow = (o && o.dataset.service) ? [o.dataset.service]
              : (ctxTypes && ctxTypes.length ? ctxTypes : null);
    if (insp && allow) { rebuild(insp, INSP_ALL, allow, allow.length === 1 ? allow[0] : insp.value); narrowNote(insp, true, 'quotation'); }
    else if (insp && INSP_ALL) { rebuild(insp, INSP_ALL, null); narrowNote(insp, false); }
    // The category the order was priced against, pinned the same way.
    var pcat = window.__quoteProduct || null;
    // A category typed freehand on the quote is not in the master list; narrowing
    // to it would leave the box empty, so leave the full list alone instead.
    if (pcat && PROD_ALL && !PROD_ALL.some(function(o){ return o.v === pcat; })) pcat = null;
    if (prod && pcat) { rebuild(prod, PROD_ALL, [pcat], pcat); narrowNote(prod, true, 'quotation'); }
    else if (prod && PROD_ALL) { rebuild(prod, PROD_ALL, null); narrowNote(prod, false); }
  }

  // Choosing one line narrows the call to that part of the order.
  lineSel.addEventListener('change', function(){
    var o = lineSel.options[lineSel.selectedIndex];
    if (!o || !o.value) { narrowToLine(null, window.__quoteTypes || null); return; }
    narrowToLine(o, null);
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
  // ---- §b: a new client's master must actually be filled in -----------------
  // The details are not optional — an order cannot be invoiced without a tax
  // identity, and an inspection cannot be carried out without an address and
  // somebody to ring. The call is still saved, so nothing typed is lost, but it
  // cannot be forwarded to an executing office until the master is complete.
  var gapBox = document.getElementById('party_gaps'), vendorSel = document.getElementById('vendor_sel');
  function gapsFor(sel, role, label) {
    if (!sel || !sel.value) return Promise.resolve(null);
    return fetch('/partner-gaps?role=' + role + '&id=' + encodeURIComponent(sel.value))
      .then(function(r){ return r.json(); })
      .then(function(g){ return (g && g.missing && g.missing.length) ? {g:g, label:label} : null; })
      .catch(function(){ return null; });
  }
  function checkGaps() {
    if (!gapBox) return;
    Promise.all([
      gapsFor(clientSel, 'client', '<?= e(Tl('client')) ?>'),
      gapsFor(vendorSel, 'site',   'site')
    ]).then(function(res){
      var bad = res.filter(Boolean);
      if (!bad.length) { gapBox.style.display = 'none'; gapBox.innerHTML = ''; return; }
      var html = '<div class="panel" style="margin:0;border:1px solid var(--warn);'
               + 'background:color-mix(in srgb,var(--warn) 8%,transparent)">'
               + '<b>⚠ The master record is not complete</b>';
      bad.forEach(function(b){
        var m = b.g.missing;
        var list = m.length === 1 ? m[0] : m.slice(0, -1).join(', ') + ' and ' + m[m.length - 1];
        html += '<div style="margin-top:6px">' + esc(b.g.name) + ' <span class="muted">(' + b.label + ')</span>'
             +  ' is missing ' + esc(list) + '. '
             +  '<a class="btn small secondary" href="' + b.g.url + '" target="_blank" rel="noopener">Complete it</a></div>';
      });
      html += '<div class="muted" style="margin-top:8px">Save this ' + '<?= e(Tl('call')) ?>'
           +  ' by all means — nothing typed is lost. It cannot be forwarded to an executing '
           +  '<?= e(Tl('office')) ?> until the details are in, because that is the point at which '
           +  'somebody has to travel there and somebody has to be invoiced.'
           +  ' <a href="#" id="gaps_recheck">Re-check</a></div></div>';
      gapBox.innerHTML = html;
      gapBox.style.display = '';
      var rc = document.getElementById('gaps_recheck');
      if (rc) rc.addEventListener('click', function(e){ e.preventDefault(); checkGaps(); });
    });
  }
  function esc(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  clientSel.addEventListener('change', function(){ fillQuotes(false); checkGaps(); });
  if (vendorSel) vendorSel.addEventListener('change', checkGaps);
  qSel.addEventListener('change', function(){ loadQuote(false); });
  fillQuotes(true);
  checkGaps();
  // Coming back from the master in the other tab should not need a reload.
  window.addEventListener('focus', checkGaps);
})();
</script>

<!-- Quick-add modal -->
<div class="modal-back" id="qa_back" style="display:none;">
  <div class="modal">
    <h3 id="qa_title">Add</h3>
    <div class="ff"><label>Name *</label><input class="form-control" id="qa_name" autocomplete="off"></div>
    <?php // §b — a name on its own is not a master record. Ask for what operations
          // and accounts cannot work without, here, while the coordinator still
          // has the client on the phone. Everything else can be filled in later
          // on the full master screen. ?>
    <div class="qa-field qa-cv">
      <div class="ff"><label>GSTIN <span class="muted" id="qa_gstin_hint">— auto PAN / state</span></label><input class="form-control" id="qa_gstin"></div>
      <div class="ff"><label>PAN <span class="muted">— if there is no GSTIN</span></label><input class="form-control" id="qa_pan"></div>
      <div class="ff"><label>Address <span id="qa_req_addr">*</span></label><input class="form-control" id="qa_line1" placeholder="works / office address"></div>
      <div class="ff"><label>City <span id="qa_req_city">*</span></label><input class="form-control" id="qa_qcity"></div>
      <div class="ff"><label>State</label><input class="form-control" id="qa_state"></div>
      <div class="ff"><label>Contact person <span>*</span></label><input class="form-control" id="qa_pname"></div>
      <div class="ff"><label>Mobile <span>*</span></label><input class="form-control" id="qa_pmob"></div>
      <div class="ff"><label>E-mail <span id="qa_req_mail">*</span></label><input class="form-control" id="qa_pmail"></div>
      <div class="ff ff-check"><input type="checkbox" id="qa_both"><label>Add to <strong>both</strong> <?= e(T('client')) ?> &amp; <?= e(T('vendor')) ?> lists</label></div>
      <p class="muted ff-wide" id="qa_cv_note" style="margin:2px 0 0"></p>
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
