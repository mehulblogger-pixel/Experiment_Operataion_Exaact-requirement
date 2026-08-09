<?php
  $isEdit = (bool)$q;
  $preLead = $preLead ?? null;
  $inqId  = $q['inquiry_id'] ?? ($preInq['id'] ?? '');
  $g = function($k, $d = '') use ($q, $preInq, $preLead) {
    if ($q && isset($q[$k])) return $q[$k];
    if ($preInq && isset($preInq[$k])) return $preInq[$k];
    if ($preLead && isset($preLead[$k]) && $preLead[$k] !== '') return $preLead[$k];
    return $d;
  };
  $rows    = $lines ?: [[]];
  $locRows = $locations ?: [[]];
  $curTypes = array_filter(explode(',', (string)$g('inspection_types')));
  $curOffs  = array_filter(explode(',', (string)$g('exec_office_ids')));
  $terms    = $isEdit ? (string)($q['terms_conditions'] ?? '') : '';
  if (trim($terms) === '') $terms = $defaultTerms;
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/quotes"><?= e(TP('quote')) ?></a> › <?= $isEdit ? e(quote_label($q)) : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $isEdit ? 'Edit — ' . e(quote_label($q)) : ucfirst(T_NEW('quote')) ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= $preInq ? 'From ' . e(Tl('inquiry')) . ' ' . e($preInq['inquiry_no']) . '. ' : '' ?><?= $preLead ? 'From lead ' . e($preLead['ref']) . ' — company, contact and requirement carried across. ' : '' ?>Fill the header, add the sites, then the line items. The <?= e(Tl('quote')) ?> number is generated on save.</p></div>
  <a class="btn secondary" href="/quotes">← Back</a>
</div>

<form method="post" action="/<?= $isEdit ? 'quote-edit?id=' . (int)$q['id'] : 'quote-new' ?>" class="panel" id="qform">
  <?php if ($inqId !== ''): ?><input type="hidden" name="inquiry_id" value="<?= (int)$inqId ?>"><?php endif; ?>
  <?php if ($preLead): ?><input type="hidden" name="lead_id" value="<?= (int)$preLead['id'] ?>"><?php endif; ?>

  <h3 class="tab-sub" style="margin-top:0"><?= e(T('client')) ?> &amp; header</h3>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('client')) ?> <a href="#" class="addlink" data-qa="client" data-target="#client_id">+ Add new</a></label>
      <select class="form-control searchable" name="client_id" id="client_id"><option value="">— not on file, type the name —</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)$g('client_id')===(string)$cl['id']?'selected':'' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or <?= e(Tl('client')) ?> name <span class="muted" id="cn_hint"></span></label>
      <input class="form-control" name="client_name" id="client_name" value="<?= e($g('client_name')) ?>"></div>
    <div class="ff"><label><?= e(T('sbu')) ?> (primary)</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= $g('sbu')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>

    <div class="ff"><label>Contact person</label><input class="form-control" name="contact_name" id="contact_name" value="<?= e($g('contact_name')) ?>"></div>
    <div class="ff"><label>Contact e-mail</label><input class="form-control" type="email" name="contact_email" id="contact_email" value="<?= e($g('contact_email')) ?>"></div>
    <div class="ff"><label>Contact mobile</label><input class="form-control" name="contact_mobile" id="contact_mobile" value="<?= e($g('contact_mobile')) ?>"></div>

    <div class="ff ff-wide"><label>Subject / scope title</label><input class="form-control" name="subject" value="<?= e($g('subject')) ?>" placeholder="e.g. Third-party inspection services — pipeline project"></div>

    <?php // What we have already quoted this customer and its group. Filled by
          // JS from /quote-client when a customer is known, so it also updates
          // the moment you pick a different customer above. Empty and invisible
          // until then. ?>
    <div class="ff ff-wide" id="qhist" style="display:none"></div>

    <div class="ff ff-wide"><label>Types of inspection requested <span class="muted">— same list the <?= e(Tl('call')) ?> uses<span id="it_narrow"></span></span></label>
      <div class="chip-row pickbox" id="itypes">
        <?php foreach ($svcOpts as $k=>$v): ?>
          <label class="ff-check" data-code="<?= e($k) ?>"><input type="checkbox" name="inspection_types[]" value="<?= e($k) ?>" <?= in_array($k,$curTypes,true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div></div>

    <div class="ff"><label>Product category <span class="muted">— spelling is unified automatically</span></label>
      <input class="form-control" name="product_category" list="prodcats" value="<?= e(product_cat_label($g('product_category'))) ?>" placeholder="e.g. Transformers">
      <datalist id="prodcats"><?php foreach ($prodCats as $pc): ?><option value="<?= e($pc) ?>"><?php endforeach; ?></datalist></div>
    <div class="ff"><label>Where did this <?= e(Tl('quote')) ?> come from?</label>
      <select class="form-control" name="origin" id="origin">
        <?php foreach ($origins as $k=>$v): ?><option value="<?= e($k) ?>" <?= $g('origin','OWN')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff origin-extra"><label>Portal / reference <span class="muted">— for a <?= e(Tl('quote')) ?> submitted outside our format</span></label>
      <input class="form-control" name="origin_portal" value="<?= e($g('origin_portal')) ?>" placeholder="e.g. GeM, client SRM portal">
      <input class="form-control" name="origin_ref" value="<?= e($g('origin_ref')) ?>" placeholder="their reference / bid no." style="margin-top:6px"></div>
  </div>

  <h3 class="tab-sub">Executing <?= e(TP('office')) ?> <span class="muted">— who will actually do the work</span></h3>
  <div class="form-grid">
    <div class="ff"><label>Primary / owning <?= e(T('office')) ?></label>
      <select class="form-control searchable" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$g('office_id')===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
      <small class="muted">Owns the <?= e(Tl('quote')) ?> and the follow-up.</small></div>
    <div class="ff ff-wide"><label>Also executed by <span class="muted">— tick every other <?= e(T('office')) ?> that will work on it</span></label>
      <div class="chip-row pickbox">
        <?php foreach ($offices as $o): ?>
          <label class="ff-check"><input type="checkbox" name="exec_office_ids[]" value="<?= (int)$o['id'] ?>" <?= in_array((string)$o['id'],$curOffs,true)?'checked':'' ?>> <?= e($o['name']) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Each line item can then be assigned to one of these, so revenue and inter-<?= e(T('office')) ?> credit split correctly.</small></div>
  </div>

  <?php // The manufacturer / vendor whose works are inspected. Chosen here so it
        // carries straight onto the work-order's site dropdown when the quote is won. ?>
  <div class="form-grid">
    <div class="ff"><label><?= e(T('vendor')) ?> / <?= e(Tl('manufacturer')) ?> site <a href="#" class="addlink" data-qa="vendor" data-target="select[name=&quot;vendor_id&quot;]">+ Add new</a> <span class="muted">— carried to the <?= e(Tl('call')) ?></span></label>
      <select class="form-control searchable" name="vendor_id"><option value="">— none / decide later —</option>
        <?php foreach (($vendors ?? []) as $vd): ?>
          <option value="<?= (int)$vd['id'] ?>" <?= (string)$g('vendor_id')===(string)$vd['id']?'selected':'' ?>><?= e($vd['display_name'] ?: $vd['legal_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>

  <h3 class="tab-sub">Sites <span class="muted">— add every location the work covers</span></h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt" id="locs">
    <thead><tr>
      <th style="min-width:130px">Name / label</th><th style="min-width:130px">Type</th>
      <th style="min-width:150px">Vendor (site) <span class="muted">— optional</span></th>
      <th style="min-width:200px">Address line 1</th><th style="min-width:160px">Line 2</th>
      <th style="min-width:130px">City</th><th style="min-width:130px">State</th><th style="min-width:90px">PIN</th>
      <th style="min-width:140px">Site contact</th><th style="min-width:130px">Mobile</th>
      <th style="min-width:140px">Nearest <?= e(T('office')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($locRows as $L): ?>
    <tr class="locrow">
      <td><input type="hidden" name="loc_id[]" value="<?= (int)($L['id'] ?? 0) ?>">
          <input class="form-control" name="loc_label[]" value="<?= e($L['label'] ?? '') ?>" placeholder="e.g. Vapi works"></td>
      <td><select class="form-control" name="loc_type[]">
            <?php foreach ($locTypes as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($L['location_type'] ?? 'SITE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></td>
      <td><select class="form-control loc-vendor" name="loc_vendor[]">
            <option value="">— not a vendor site / decide later —</option>
            <?php foreach (($vendors ?? []) as $vd): ?><option value="<?= (int)$vd['id'] ?>" <?= ((string)($L['vendor_id'] ?? '')===(string)$vd['id'])?'selected':'' ?>><?= e($vd['display_name'] ?: $vd['legal_name']) ?></option><?php endforeach; ?>
          </select></td>
      <td><input class="form-control" name="loc_line1[]" value="<?= e($L['line1'] ?? '') ?>" placeholder="Plot / building / street"></td>
      <td><input class="form-control" name="loc_line2[]" value="<?= e($L['line2'] ?? '') ?>" placeholder="Area / landmark"></td>
      <td><input class="form-control" name="loc_city[]" value="<?= e($L['city'] ?? '') ?>"></td>
      <td><input class="form-control" name="loc_state[]" value="<?= e($L['state'] ?? '') ?>"></td>
      <td><input class="form-control" name="loc_pin[]" value="<?= e($L['pincode'] ?? '') ?>"></td>
      <td><input class="form-control" name="loc_contact[]" value="<?= e($L['contact_name'] ?? '') ?>"></td>
      <td><input class="form-control" name="loc_mobile[]" value="<?= e($L['contact_mobile'] ?? '') ?>"></td>
      <td><select class="form-control" name="loc_office[]"><option value="">—</option>
            <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ((string)($L['office_id'] ?? '')===(string)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
          </select>
          <input type="hidden" name="loc_country[]" value="<?= e($L['country'] ?? 'India') ?>"></td>
      <td><button type="button" class="btn small danger locrm" title="Remove this site">✕</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin:8px 0"><button type="button" class="btn small secondary" id="addloc">+ Add site</button>
    <span class="muted" style="margin-left:8px">Each line item below picks the site it belongs to.</span></div>

  <h3 class="tab-sub">Commercials</h3>
  <div class="form-grid">
    <div class="ff"><label>Validity (days)</label><input class="form-control" type="number" name="validity_days" value="<?= e($g('validity_days','30')) ?>"></div>
    <div class="ff"><label>Currency</label><input class="form-control" name="currency" value="<?= e($g('currency','INR')) ?>"></div>
    <div class="ff"><label>GST %</label><input class="form-control" type="number" step="0.01" id="gst_pct" name="gst_pct" value="<?= e($g('gst_pct','18')) ?>"></div>

    <div class="ff"><label>Payment terms</label>
      <select class="form-control searchable" name="payment_terms">
        <option value="">— select —</option>
        <?php $pt = (string)$g('payment_terms'); $known = false;
              foreach ($payTerms as $k=>$v) { if ($pt === $v || $pt === $k) $known = true; } ?>
        <?php foreach ($payTerms as $k=>$v): ?>
          <option value="<?= e($v) ?>" <?= ($pt === $v || $pt === $k)?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
        <?php if ($pt !== '' && !$known): ?><option value="<?= e($pt) ?>" selected><?= e($pt) ?> (existing)</option><?php endif; ?>
      </select>
      <small class="muted">Edit the list under Masters → Sales → Payment term.</small></div>
    <div class="ff"><label>Advance %</label><input class="form-control" type="number" step="0.01" name="advance_pct" value="<?= e($g('advance_pct','0')) ?>"></div>
    <div class="ff" style="align-self:end">
      <label class="chk"><input type="checkbox" name="advance_required" value="1" <?= $g('advance_required')?'checked':'' ?>> Advance required <span class="muted">before scheduling</span></label>
      <label class="chk" style="margin-top:6px"><input type="checkbox" name="report_vs_payment" value="1" <?= $g('report_vs_payment')?'checked':'' ?>> Report only against payment</label>
    </div>
    <?php
      // Default to whoever is writing the quote, if they have a signature on
      // file. Leaving it blank fell through to the company signature, which is
      // right as a fallback but wrong as a default for a named salesperson.
      $meId  = (int)(current_user()['id'] ?? 0);
      $mineOnFile = false;
      foreach ($signatories as $s) if ((int)$s['id'] === $meId) $mineOnFile = true;
      $sigSel = (string)$g('signatory_user_id');
      if ($sigSel === '' && !$isEdit && $mineOnFile) $sigSel = (string)$meId;
    ?>
    <div class="ff"><label>Signed by <span class="muted">— the stored signature goes on the PDF</span></label>
      <select class="form-control searchable" name="signatory_user_id">
        <option value="">— use the company signature —</option>
        <?php foreach ($signatories as $s): $nm = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: $s['username']; ?>
          <option value="<?= (int)$s['id'] ?>" <?= $sigSel===(string)$s['id']?'selected':'' ?>><?= e($nm) ?><?= (int)$s['id']===$meId ? ' (you)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">
        <?php if (!$signatories): ?>
          Nobody has a signature on file yet — the PDF falls back to the company signature.
        <?php elseif (!$mineOnFile): ?>
          Yours is not on file yet.
        <?php endif; ?>
        <a href="/my-signature" target="_blank" rel="noopener">Upload or draw your signature →</a>
        <?php if (can('crm.template.manage') || is_master()): ?>
          · <a href="/crm-templates#signature">company signature</a>
        <?php endif; ?>
      </small></div>
  </div>

  <h3 class="tab-sub">Line items</h3>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt qlines" id="lines">
    <thead><tr>
      <th style="min-width:130px"><?= e(T('sbu')) ?></th><th style="min-width:150px">Activity</th>
      <th style="min-width:190px">Service <span class="muted" style="font-weight:400">— from the types above</span></th>
      <th style="min-width:220px">Description</th><th style="min-width:170px">Site</th>
      <th style="min-width:150px"><?= e(T('office')) ?></th><th style="min-width:130px">Order</th>
      <th class="num" style="min-width:80px">Qty</th><th style="min-width:130px">Unit</th>
      <th class="num" style="min-width:110px">Rate <?= e(cur_sym()) ?></th>
      <th class="num" style="min-width:110px">Amount <?= e(cur_sym()) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $ln): ?>
    <tr class="lrow">
      <td><select class="form-control l_sbu" name="l_sbu[]"><option value="">—</option><?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($ln['sbu'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
      <td><select class="form-control l_act" name="l_activity[]" data-cur="<?= (int)($ln['activity_id'] ?? 0) ?>"><option value="">— pick an <?= e(T('sbu')) ?> first —</option></select></td>
      <?php // A plain select on purpose. It was a .searchable select, which the
            // enhancer replaces with a text box — so it looked like a free-text
            // field, accepted typing, then silently reverted. The list is short
            // and closed; a real dropdown is the honest control. Its options are
            // rebuilt by JS from the "Types of inspection requested" ticked above,
            // so a line can never quote something outside the agreed scope. ?>
      <td><select class="form-control l_service" name="l_service[]" data-cur="<?= e($ln['service_type'] ?? '') ?>"></select></td>
      <td><input class="form-control" name="l_desc[]" value="<?= e($ln['description'] ?? '') ?>"></td>
      <td><select class="form-control l_loc" name="l_location[]" data-cur="<?= (int)($ln['location_id'] ?? 0) ?>"><option value="">— add a site above —</option></select></td>
      <td><select class="form-control" name="l_office[]"><option value="">— primary —</option>
            <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ((string)($ln['office_id'] ?? '')===(string)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></td>
      <td><select class="form-control" name="l_order[]"><?php foreach ($orderOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ln['order_type'] ?? 'LINE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
      <td><input class="form-control num l_qty" type="number" step="0.01" name="l_qty[]" value="<?= e($ln['qty'] ?? '') ?>"></td>
      <td><select class="form-control" name="l_unit[]"><?php foreach ($unitOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ln['unit'] ?? 'MANDAY')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
      <td><input class="form-control num l_rate" type="number" step="0.01" name="l_rate[]" value="<?= e($ln['rate'] ?? '') ?>"></td>
      <td class="num l_amt"><?= isset($ln['amount']) ? number_format((float)$ln['amount'],0) : '0' ?></td>
      <td><button type="button" class="btn small danger lrm" title="Remove this line">✕</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin:8px 0"><button type="button" class="btn small secondary" id="addrow">+ Add line</button></div>

  <div class="panel" style="max-width:340px;margin-left:auto;background:var(--soft)">
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">Subtotal</span><b><?= e(cur_sym()) ?><span id="t_sub">0</span></b></div>
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">GST (<span id="t_gp">18</span>%)</span><b><?= e(cur_sym()) ?><span id="t_gst">0</span></b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--line);font-size:16px"><span>Total</span><b><?= e(cur_sym()) ?><span id="t_tot">0</span></b></div>
  </div>

  <h3 class="tab-sub">Terms &amp; conditions <span class="muted">— the company default, edit for this <?= e(Tl('quote')) ?> if needed</span></h3>
  <textarea class="form-control" name="terms_conditions" rows="10" style="font-family:inherit"><?= e($terms) ?></textarea>
  <small class="muted">Change the default for every future <?= e(Tl('quote')) ?> under Settings.</small>

  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $isEdit ? 'Save ' . Tl('quote') : 'Create ' . Tl('quote') ?></button>
    <a class="btn secondary" href="<?= $isEdit ? '/quote?id=' . (int)$q['id'] : '/quotes' ?>">Cancel</a>
  </div>
</form>

<script>
(function(){
  // ---- data handed over from PHP ------------------------------------------
  var ACT_BY_SBU  = <?= json_encode($actBySbu) ?>;
  var CLIENT_TYPES= <?= json_encode($clientTypes) ?>;

  var tbody = document.querySelector('#lines tbody');
  var lbody = document.querySelector('#locs tbody');
  function nfmt(n){ return (Math.round(n)||0).toLocaleString('en-IN'); }

  // ---- §i / §ii: picking a client locks the free-text name and fills contacts
  var sel = document.getElementById('client_id'), nameBox = document.getElementById('client_name');
  var excludeQuote = <?= $isEdit ? (int)$q['id'] : 0 ?>;
  function syncClient(fill){
    var on = sel.value !== '';
    nameBox.disabled = on;
    nameBox.style.background = on ? 'var(--soft)' : '';
    document.getElementById('cn_hint').textContent = on ? '— using the selected ' + <?= json_encode(Tl('client')) ?> : '';
    if (on) nameBox.value = sel.options[sel.selectedIndex].text;
    narrowTypes();
    if (!on) { renderHistory([]); return; }
    // Fetch whenever a customer is known — on load too, not only on change — so
    // the group's quotation history is there before you price anything. Only
    // auto-fill the contact fields when the user actually picked (fill), never
    // on load, where it would clobber an edited quote.
    fetch('/quote-client?id=' + encodeURIComponent(sel.value) + '&exclude=' + excludeQuote)
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (fill) {
          [['contact_name','name'],['contact_email','email'],['contact_mobile','mobile']].forEach(function(p){
            var el = document.getElementById(p[0]);
            if (el && !el.value && d.contact && d.contact[p[1]]) el.value = d.contact[p[1]];
          });
          // Commercials agreed with this customer flow through from the master —
          // payment terms first — filled only when the field is still empty, so a
          // one-off change on this quote is never overwritten.
          if (d.commercials) {
            var pt = document.querySelector('select[name="payment_terms"]');
            if (pt && !pt.value && d.commercials.payment_terms) {
              var want = d.commercials.payment_terms, has = false, i;
              for (i = 0; i < pt.options.length; i++) { if (pt.options[i].value === want) { has = true; break; } }
              if (!has) { var o = document.createElement('option'); o.value = want; o.textContent = want + ' (from client)'; pt.appendChild(o); }
              pt.value = want;
              pt.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }
          if (d.addresses && d.addresses.length) offerAddresses(d.addresses);
        }
        renderHistory(d.history || []);
      }).catch(function(){});
  }

  // "What we have already quoted this group." A group company's own name is
  // shown against its rows so subsidiaries are obvious; the customer you are
  // quoting shows no name because every unlabelled row is theirs.
  function renderHistory(list){
    var box = document.getElementById('qhist');
    if (!box) return;
    if (!list || !list.length) { box.style.display = 'none'; box.innerHTML = ''; return; }
    function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    var rows = list.map(function(h){
      return '<tr>'
        + '<td><a href="/quote?id=' + h.id + '" target="_blank">' + esc(h.no) + '</a>'
        + (h.company ? ' <span class="pill p-mut" style="font-size:11px">' + esc(h.company) + '</span>' : '') + '</td>'
        + '<td>' + esc(h.subject || '—') + '</td>'
        + '<td class="num">' + esc(h.value) + '</td>'
        + '<td><span class="pill p-mut" style="font-size:11px">' + esc(h.status) + '</span></td>'
        + '<td style="white-space:nowrap">' + esc(h.when) + '</td>'
        + '</tr>';
    }).join('');
    box.innerHTML =
      '<div class="panel" style="margin:0;border-left:3px solid var(--brand)">'
      + '<h3 style="margin-top:0;font-size:14px">Already quoted to this ' + <?= json_encode(Tl('client')) ?> + ' &amp; its group '
      + '<span class="muted" style="font-weight:400">(' + list.length + ')</span></h3>'
      + '<p class="muted" style="font-size:12.5px;margin:0 0 8px">So you price with the full picture. A name against a row means it is a group company, not this one. Opens in a new tab.</p>'
      + '<div style="overflow-x:auto"><table class="dt"><thead><tr>'
      + '<th>Quotation</th><th>Subject</th><th class="num">Value</th><th>Status</th><th>Raised</th>'
      + '</tr></thead><tbody>' + rows + '</tbody></table></div></div>';
    box.style.display = '';
  }
  // §v — narrow the inspection-type picker to what this client actually buys
  function narrowTypes(){
    var allow = CLIENT_TYPES[sel.value] || null;
    var n = 0;
    document.querySelectorAll('#itypes .ff-check').forEach(function(l){
      var ok = !allow || allow.indexOf(l.dataset.code) >= 0 || l.querySelector('input').checked;
      l.style.display = ok ? '' : 'none';
      if (ok) n++;
    });
    document.getElementById('it_narrow').textContent =
      allow ? ' · narrowed to this ' + <?= json_encode(Tl('client')) ?> + '’s ' + n + ' type(s)' : '';
  }
  sel.addEventListener('change', function(){ syncClient(true); });
  syncClient(false);

  // offer the client's addresses as ready-made sites
  function norm(s){ return String(s == null ? '' : s).trim().toLowerCase(); }
  // Does a site row already hold this address? Matched on line 1 + city (or the
  // label when there is no street line) so the same address is not added twice.
  function addressAlreadyASite(a){
    var found = null;
    lbody.querySelectorAll('.locrow').forEach(function(tr){
      var l1 = norm(tr.querySelector('[name="loc_line1[]"]').value);
      var ct = norm(tr.querySelector('[name="loc_city[]"]').value);
      var lb = norm(tr.querySelector('[name="loc_label[]"]').value);
      var aL1 = norm(a.line1), aCt = norm(a.city), aLb = norm(a.label || a.address_type);
      var match = (aL1 && aL1 === l1 && aCt === ct) || (!aL1 && aLb && aLb === lb);
      if (match) found = tr;
    });
    return found;
  }
  function markChipAdded(b, on){
    if (on) { b.dataset.added = '1'; b.style.opacity = '0.5'; b.style.pointerEvents = 'none';
              b.textContent = b.textContent.replace(/^\+ /, '✓ '); }
    else    { delete b.dataset.added; b.style.opacity = ''; b.style.pointerEvents = '';
              b.textContent = b.textContent.replace(/^✓ /, '+ '); }
  }
  function offerAddresses(list){
    if (document.getElementById('addrhint')) return;
    var d = document.createElement('div');
    d.id = 'addrhint'; d.className = 'panel'; d.style.margin = '8px 0';
    d.innerHTML = '<b>' + list.length + ' address(es) on file for this ' + <?= json_encode(Tl('client')) ?> + '.</b> '
                + '<span class="muted" style="font-size:12px">Click one to add it as a site — each is added once.</span><br>';
    list.forEach(function(a, i){
      var b = document.createElement('button');
      b.type='button'; b.className='btn small secondary'; b.style.margin='2px';
      b.textContent = '+ ' + (a.label || a.address_type || 'Address') + (a.city ? ' — ' + a.city : '');
      b.addEventListener('click', function(){
        if (b.dataset.added) return;               // already a site — do nothing
        var existing = addressAlreadyASite(a);
        var tr = existing || addLoc(a);
        if (tr) { tr._srcChip = b; markChipAdded(b, true); }
      });
      // If this address is already on the form (e.g. editing a saved quote), show
      // it as added from the start rather than letting it be added again.
      var pre = addressAlreadyASite(a);
      if (pre) { pre._srcChip = b; markChipAdded(b, true); }
      d.appendChild(b);
    });
    document.getElementById('locs').parentNode.parentNode.insertBefore(d, document.getElementById('locs').parentNode);
  }

  // ---- sites ---------------------------------------------------------------
  function blankLocRow(){
    var tr = lbody.querySelector('.locrow').cloneNode(true);
    tr.querySelectorAll('input').forEach(function(i){ i.value = i.name === 'loc_country[]' ? 'India' : ''; });
    tr.querySelectorAll('select').forEach(function(s){ s.selectedIndex = 0; });
    return tr;
  }
  function addLoc(a){
    var tr = blankLocRow();
    if (a) {
      var set = function(n, v){ var el = tr.querySelector('[name="'+n+'"]'); if (el && v) el.value = v; };
      set('loc_label[]', a.label || a.address_type); set('loc_line1[]', a.line1); set('loc_line2[]', a.line2);
      set('loc_city[]', a.city); set('loc_state[]', a.state); set('loc_pin[]', a.pincode);
    }
    lbody.appendChild(tr); wireLoc(tr); refreshLocPickers();
    return tr;
  }
  // Fill a site row's address from a picked vendor/address. A field is only
  // overwritten when it is empty or still holds what a previous auto-fill put
  // there — so switching the vendor updates the address, but anything typed by
  // hand is kept.
  function fillRowAddress(tr, addr, contact){
    var af = tr._autofill || {};
    function put(name, val){
      var el = tr.querySelector('[name="'+name+'"]'); if (!el) return;
      val = val || '';
      if (el.value === '' || el.value === (af[name] || '')) el.value = val;
      af[name] = val;
    }
    if (addr){
      put('loc_line1[]', addr.line1); put('loc_line2[]', addr.line2);
      put('loc_city[]', addr.city);   put('loc_state[]', addr.state);
      put('loc_pin[]', addr.pincode);
      var cty = tr.querySelector('[name="loc_country[]"]'); if (cty && addr.country) cty.value = addr.country;
    }
    if (contact){ put('loc_contact[]', contact.name); put('loc_mobile[]', contact.mobile); }
    tr._autofill = af;
    refreshLocPickers();
  }
  function wireLoc(tr){
    var rm = tr.querySelector('.locrm');
    if (rm) rm.addEventListener('click', function(){
      // If this row came from a client-address chip, re-enable that chip so the
      // address can be added again after it was removed.
      if (tr._srcChip) { markChipAdded(tr._srcChip, false); tr._srcChip = null; }
      if (lbody.querySelectorAll('.locrow').length > 1) tr.remove();
      else { tr.querySelectorAll('input').forEach(function(i){ if(i.name!=='loc_id[]') i.value=''; }); tr._autofill = null; }
      refreshLocPickers();
    });
    // Pick a vendor as the site → name the row after that vendor and mark it as
    // manufacturer works, so several sites at different vendors read clearly even
    // before their addresses are known ("site to be confirmed").
    var vsel = tr.querySelector('.loc-vendor');
    if (vsel) vsel.addEventListener('change', function(){
      var lab = tr.querySelector('[name="loc_label[]"]'), ty = tr.querySelector('[name="loc_type[]"]');
      if (vsel.value) {
        var nm = vsel.options[vsel.selectedIndex].text;
        if (lab && !lab.value) lab.value = nm;
        if (ty && (ty.value === 'SITE' || ty.value === 'TBD' || ty.value === '')) ty.value = 'WORKS';
        // items 1 & 3 — pull the vendor's registered address so the site details
        // fill themselves instead of being copied by hand.
        fetch('/partner-address?id=' + encodeURIComponent(vsel.value))
          .then(function(r){ return r.json(); })
          .then(function(d){ fillRowAddress(tr, d.address, d.contact); })
          .catch(function(){});
      }
      refreshLocPickers();
    });
    tr.querySelectorAll('input,select').forEach(function(el){ el.addEventListener('change', refreshLocPickers); });
  }
  // §viii / §xi — every line's Site dropdown lists the sites entered above.
  // With several sites this is how each line says which one it is for.
  function refreshLocPickers(){
    var opts = [];
    lbody.querySelectorAll('.locrow').forEach(function(tr, i){
      var id  = tr.querySelector('[name="loc_id[]"]').value || '';
      var lab = tr.querySelector('[name="loc_label[]"]').value;
      var city= tr.querySelector('[name="loc_city[]"]').value;
      var l1  = tr.querySelector('[name="loc_line1[]"]').value;
      var text = (lab || l1 || ('Site ' + (i+1))) + (city ? ' — ' + city : '');
      if (lab || l1 || city) opts.push({ id: id, text: text, idx: i });
    });
    tbody.querySelectorAll('.l_loc').forEach(function(s){
      var want = s.value || s.dataset.cur || '';
      s.innerHTML = '';
      var o0 = document.createElement('option'); o0.value=''; o0.textContent = opts.length ? '— pick a site —' : '— add a site above —';
      s.appendChild(o0);
      opts.forEach(function(o){
        var el = document.createElement('option');
        el.value = o.id; el.textContent = o.text;
        if (o.id && String(o.id) === String(want)) el.selected = true;
        s.appendChild(el);
      });
      // exactly one site: use it, so nobody has to pick on every line
      if (opts.length === 1 && opts[0].id && !want) s.value = opts[0].id;
    });
  }

  // ---- §x: activity depends on the line's business unit ------------------------------
  function fillAct(tr){
    var sbu = tr.querySelector('.l_sbu').value;
    var act = tr.querySelector('.l_act');
    var want = act.value || act.dataset.cur || '';
    var list = ACT_BY_SBU[sbu] || [];
    act.innerHTML = '';
    var o0 = document.createElement('option'); o0.value='';
    o0.textContent = sbu ? (list.length ? '— pick an activity —' : '— none set for this ' + <?= json_encode(T('sbu')) ?> + ' —')
                         : '— pick an ' + <?= json_encode(T('sbu')) ?> + ' first —';
    act.appendChild(o0);
    list.forEach(function(a){
      var el = document.createElement('option'); el.value = a.id; el.textContent = a.label;
      if (String(a.id) === String(want)) el.selected = true;
      act.appendChild(el);
    });
  }

  // ---- totals --------------------------------------------------------------
  function recalc(){
    var sub = 0;
    tbody.querySelectorAll('.lrow').forEach(function(tr){
      var q = parseFloat(tr.querySelector('.l_qty').value) || 0,
          r = parseFloat(tr.querySelector('.l_rate').value) || 0;
      var a = q * r; tr.querySelector('.l_amt').textContent = nfmt(a); sub += a;
    });
    var gp = parseFloat(document.getElementById('gst_pct').value) || 0;
    var gst = sub * gp / 100;
    document.getElementById('t_sub').textContent = nfmt(sub);
    document.getElementById('t_gp').textContent  = gp;
    document.getElementById('t_gst').textContent = nfmt(gst);
    document.getElementById('t_tot').textContent = nfmt(sub + gst);
  }
  // ---- §10: a line's Service is whatever was ticked in the header ----------
  // The scope is agreed once, at the top, in "Types of inspection requested".
  // Every line then picks from exactly that set — so a line item can never quote
  // a service the client was not offered, and nobody has to remember the codes.
  var SVC_LABELS = <?= json_encode(array_map(fn($v) => (string)$v, $svcOpts)) ?>;
  function tickedTypes(){
    var out = [];
    document.querySelectorAll('#itypes input[type=checkbox]:checked').forEach(function(cb){ out.push(cb.value); });
    return out;
  }
  function fillService(tr){
    var sel = tr.querySelector('.l_service');
    if (!sel) return;
    var want = sel.value || sel.dataset.cur || '';
    var types = tickedTypes();
    sel.innerHTML = '';
    var o0 = document.createElement('option');
    o0.value = '';
    o0.textContent = types.length ? '— pick one —' : '— tick the types of inspection above —';
    sel.appendChild(o0);
    types.forEach(function(code){
      var op = document.createElement('option');
      op.value = code; op.textContent = SVC_LABELS[code] || code;
      if (code === want) op.selected = true;
      sel.appendChild(op);
    });
    // A saved line whose type was later un-ticked keeps its value rather than
    // being silently blanked, and says so, so the mismatch is visible.
    if (want && types.indexOf(want) === -1) {
      var op = document.createElement('option');
      op.value = want; op.textContent = (SVC_LABELS[want] || want) + ' — no longer in the header';
      op.selected = true;
      sel.appendChild(op);
    }
    sel.disabled = false;
  }
  function fillAllServices(){ tbody.querySelectorAll('.lrow').forEach(fillService); }
  document.querySelectorAll('#itypes input[type=checkbox]').forEach(function(cb){
    cb.addEventListener('change', fillAllServices);
  });

  function wireLine(tr){
    tr.querySelectorAll('.l_qty,.l_rate').forEach(function(el){ el.addEventListener('input', recalc); });
    tr.querySelector('.l_sbu').addEventListener('change', function(){ fillAct(tr); });
    fillService(tr);
    var rm = tr.querySelector('.lrm');
    if (rm) rm.addEventListener('click', function(){
      if (tbody.querySelectorAll('.lrow').length > 1) { tr.remove(); }
      else { tr.querySelectorAll('input').forEach(function(i){ i.value=''; }); }
      recalc();
    });
    fillAct(tr);
  }

  // ---- origin: the portal fields only matter for a quote we did not write ---
  var origin = document.getElementById('origin');
  function syncOrigin(){
    var ext = origin.value !== 'OWN';
    document.querySelectorAll('.origin-extra').forEach(function(el){ el.style.display = ext ? '' : 'none'; });
  }
  origin.addEventListener('change', syncOrigin); syncOrigin();

  lbody.querySelectorAll('.locrow').forEach(wireLoc);
  tbody.querySelectorAll('.lrow').forEach(wireLine);
  document.getElementById('addloc').addEventListener('click', function(){ addLoc(null); });
  document.getElementById('addrow').addEventListener('click', function(){
    var tr = tbody.querySelector('.lrow').cloneNode(true);
    tr.querySelectorAll('input').forEach(function(i){ i.value=''; });
    tr.querySelectorAll('select').forEach(function(s){ s.selectedIndex=0; s.dataset.cur=''; });
    tr.querySelector('.l_amt').textContent = '0';
    tbody.appendChild(tr); wireLine(tr); refreshLocPickers();
  });
  document.getElementById('gst_pct').addEventListener('input', recalc);
  refreshLocPickers(); recalc();
})();
</script>
