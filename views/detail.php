<?php
// fdate() is a shared helper in lib/ops.php. Declaring it again here made this
// one screen fatal the moment ops.php was loaded — which it always is — so the
// client and vendor detail pages died with "Cannot redeclare fdate()". A view
// renders; it does not define functions.
$badge = $p['status']==='ACTIVE'?'GREEN':($p['status']==='BLACKLISTED'?'RED':'AMBER');
$id = (int)$p['id'];
// Contract / PO / deputations apply to clients (companies we receive orders from);
// purchase order comes first, contract is selected after the PO is received.
$tabs = ['overview'=>'Overview','general'=>'General','registration'=>'Registration','addresses'=>'Addresses','contacts'=>'Contacts'];
if (!empty($p['is_client'])) { $tabs['purchase_orders']='Purchase Orders'; $tabs['contracts']='Contract Numbers'; $tabs['projects']=TP('job'); }
$tabs += ['relationships'=>'Relationships','notes'=>'Notes','timeline'=>'Timeline'];
if (!isset($tabs[$tab])) $tab = 'overview';
// Cross-tab data flow: primary contact/address and links between them.
$primaryContact = null; foreach ($contacts as $c) { if ($c['is_primary']) { $primaryContact = $c; break; } } if (!$primaryContact && $contacts) $primaryContact = $contacts[0];
$primaryAddress = null; foreach ($addresses as $a) { if ($a['is_primary']) { $primaryAddress = $a; break; } } if (!$primaryAddress && $addresses) $primaryAddress = $addresses[0];
$addrById = []; foreach ($addresses as $a) $addrById[$a['id']] = $a;
$contactsByAddr = []; foreach ($contacts as $c) { $contactsByAddr[$c['address_id'] ?: 0][] = $c; }
function addr_line($a) { return implode(', ', array_filter([$a['line1'] ?? '',$a['line2'] ?? '',$a['town_village'] ?? '',$a['district'] ?? '',$a['city'] ?? '',$a['state'] ?? '',$a['pincode'] ?? ''])); }
function addr_name($a) { return (lk_options_or('address_type', ADDRESS_TYPES)[$a['address_type']] ?? $a['address_type']) . ($a['label'] ? ' — '.$a['label'] : ''); }
?>
<div class="master-head">
  <div><h1><?= e(partner_name($p)) ?></h1>
    <p class="sub"><?= e($p['code']) ?> · <?= e(roles_label($p)) ?> <span class="badge <?= $badge ?>"><?= e(lk_options_or('partner_status', STATUSES)[$p['status']] ?? $p['status']) ?></span></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php // The assembled view. This screen is the master RECORD; that one is
          // what somebody needs before they ring them. ?>
    <?php if (!empty($p['is_client'])): ?>
      <a class="btn" href="/customer?id=<?= $id ?>">Customer 360</a>
    <?php endif; ?>
    <a class="btn secondary" href="/partner-edit?id=<?= $id ?>">Edit</a>
  </div>
</div>

<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?><a href="/partner?id=<?= $id ?>&tab=<?= $k ?>" class="<?= $tab===$k?'active':'' ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>

<div class="panel tab-body">
<?php if ($tab === 'overview'): ?>
  <dl class="kv">
    <dt>Roles</dt><dd><?= e(roles_label($p)) ?></dd>
    <dt>Client type</dt><dd><?= e(CLIENT_TYPES[$p['client_type']] ?? '—') ?></dd>
    <dt>Industry</dt><dd><?= e(INDUSTRIES[$p['industry']] ?? '—') ?></dd>
    <dt>Ownership</dt><dd><?= e(lk_options_or('ownership', OWNERSHIP)[$p['ownership_type']] ?? '—') ?></dd>
    <dt>GSTIN</dt><dd><?= e($p['gstin'] ?: '—') ?><?php if ($p['state']): ?> <span class="muted">(<?= e($p['state']) ?>)</span><?php endif; ?></dd>
    <dt>PAN</dt><dd><?= e($p['pan'] ?: '—') ?></dd>
    <dt>CIN</dt><dd><?= e($p['cin'] ?: '—') ?></dd>
    <dt>Website</dt><dd><?php if ($p['website']): ?><a href="<?= e($p['website']) ?>" target="_blank" rel="noopener"><?= e($p['website']) ?></a><?php else: ?>—<?php endif; ?></dd>
    <dt>Primary contact</dt><dd><?php if ($primaryContact): ?><?= e($primaryContact['name']) ?><?php if ($primaryContact['designation']): ?> <span class="muted">(<?= e($primaryContact['designation']) ?>)</span><?php endif; ?> — <?= e(trim($primaryContact['mobile'].' '.$primaryContact['email'])) ?: '—' ?><?php else: ?>—<?php endif; ?></dd>
    <dt>Head office</dt><dd><?php if ($primaryAddress): ?><?= e(addr_line($primaryAddress) ?: addr_name($primaryAddress)) ?><?php else: ?>—<?php endif; ?></dd>
    <dt>Description</dt><dd><?= e($p['description'] ?: '—') ?></dd>
    <?php if (function_exists('custom_display')) foreach (custom_display('partner', $p['id']) as $cf): ?>
      <dt><?= e($cf['label']) ?></dt><dd><?= e($cf['value']) ?></dd>
    <?php endforeach; ?>
  </dl>
  <h3 class="tab-sub">Company hierarchy</h3>
  <div class="hierarchy">
    <div>🏢 <strong><?= e($parent ? partner_name($parent) : partner_name($p)) ?></strong><?php if ($parent): ?> <span class="muted">(parent)</span><?php endif; ?></div>
    <?php foreach ($subsidiaries as $s): ?><div class="child">↳ <?= e(partner_name($s)) ?> <span class="muted">(<?= e($s['code']) ?>)</span></div><?php endforeach; ?>
    <?php if (!$subsidiaries): ?><div class="child muted">No subsidiaries linked.</div><?php endif; ?>
  </div>

<?php elseif ($tab === 'general'): ?>
  <dl class="kv">
    <dt>Legal name</dt><dd><?= e($p['legal_name']) ?></dd>
    <dt>Display name</dt><dd><?= e($p['display_name'] ?: '—') ?></dd>
    <dt>Client code</dt><dd><?= e($p['code']) ?></dd>
    <dt>Roles</dt><dd><?= e(roles_label($p)) ?></dd>
    <dt>GSTIN / PAN / CIN</dt><dd><?= e($p['gstin'] ?: '—') ?> · <?= e($p['pan'] ?: '—') ?> · <?= e($p['cin'] ?: '—') ?></dd>
    <dt>TAN / MSME</dt><dd><?= e($p['tan'] ?: '—') ?> · <?= e($p['msme_udyam'] ?: '—') ?></dd>
  </dl>
  <a class="btn secondary" href="/partner-edit?id=<?= $id ?>">Edit details</a>

<?php elseif ($tab === 'registration'): ?>
  <table class="grid"><tr><th>Document</th><th>Number</th><th>Valid till</th></tr>
    <?php foreach ($registrations as $r): ?><tr><td><?= e(lk_options_or('registration_type', REG_TYPES)[$r['doc_type']] ?? $r['doc_type']) ?></td><td><?= e($r['number'] ?: '—') ?></td><td><?= fdate($r['valid_to']) ?></td></tr><?php endforeach; ?>
    <?php if (!$registrations): ?><tr><td colspan="3">No registrations yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add registration</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=registration" class="inline-add">
    <div class="ff"><label>Document</label><select class="form-control" id="reg_doc" name="doc_type"><?php foreach (lk_options_or('registration_type', REG_TYPES) as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Number <span class="muted">(auto-fills GSTIN/PAN)</span></label><input class="form-control" id="reg_number" name="number"></div>
    <script>window.REGDATA = {"GSTIN": <?= json_encode($p['gstin'] ?? '') ?>, "PAN": <?= json_encode($p['pan'] ?? '') ?>, "TAN": <?= json_encode($p['tan'] ?? '') ?>, "CIN": <?= json_encode($p['cin'] ?? '') ?>, "MSME": <?= json_encode($p['msme_udyam'] ?? '') ?>};</script>
    <div class="ff"><label>Valid till</label><input class="form-control" type="date" name="valid_to"></div>
    <div class="ff"><label>Notes</label><input class="form-control" name="notes"></div>
    <button class="btn small" type="submit">Add</button>
  </form>

<?php elseif ($tab === 'addresses'): ?>
  <?php foreach ($addresses as $a): ?>
    <div class="addr-card"><div><span class="badge GREEN"><?= e(lk_options_or('address_type', ADDRESS_TYPES)[$a['address_type']] ?? $a['address_type']) ?></span> <strong><?= e($a['label']) ?></strong><?php if ($a['is_primary']): ?> <span class="badge AMBER">head office</span><?php endif; ?></div>
    <div class="muted"><?= e(addr_line($a)) ?></div>
    <?php foreach (($contactsByAddr[$a['id']] ?? []) as $ct): ?><div class="muted">· <?= e($ct['name']) ?> <?= e($ct['designation']) ?> — <?= e(trim($ct['mobile'].' '.$ct['email'])) ?></div><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$addresses): ?><p>No addresses yet.</p><?php endif; ?>
  <h3 class="tab-sub">Add an address</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=address" class="inline-add">
    <div class="ff"><label>Type</label><select class="form-control" name="address_type"><?php foreach (lk_options_or('address_type', ADDRESS_TYPES) as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Label</label><input class="form-control" name="label"></div>
    <div class="ff"><label>Address line</label><input class="form-control" name="line1"></div>
    <div class="ff"><label>Town / Village / City</label><input class="form-control" name="town_village"></div>
    <div class="ff"><label>District</label><input class="form-control" name="district"></div>
    <div class="ff"><label>City (metro)</label><input class="form-control" name="city" list="citylist"><datalist id="citylist"><?php foreach ($cityList ?? [] as $ct): ?><option value="<?= e($ct) ?>"><?php endforeach; ?></datalist></div>
    <div class="ff"><label>State</label><select class="form-control searchable" name="state"><option value="">—</option><?php foreach (lk_options_or('gst_state', GST_STATES) as $sc=>$sn): ?><option value="<?= e($sn) ?>"><?= e($sn) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Pincode</label><input class="form-control" name="pincode"></div>
    <button class="btn small" type="submit">Add Address</button>
  </form>

<?php elseif ($tab === 'contacts'): ?>
  <table class="grid"><tr><th>Name</th><th>Designation</th><th>Department</th><th>Project</th><th>Mobile</th><th>Email</th><th>At (site / office)</th></tr>
    <?php foreach ($contacts as $c): ?><tr><td><?= e($c['name']) ?><?php if ($c['is_primary']): ?> <span class="badge AMBER">primary</span><?php endif; ?></td><td><?= e($c['designation'] ?: '—') ?></td><td><?= e(lk_options_or('department', DEPARTMENTS)[$c['department']] ?? ($c['department'] ?: '—')) ?></td><td><?= e($c['project'] ?? '' ?: '—') ?></td><td><?= e($c['mobile'] ?: '—') ?></td><td><?= e($c['email'] ?: '—') ?></td><td><?= isset($addrById[$c['address_id']]) ? e(addr_name($addrById[$c['address_id']])) : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$contacts): ?><tr><td colspan="7">No contacts yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a contact</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=contact" class="inline-add">
    <div class="ff"><label>Name</label><input class="form-control" name="name" required></div>
    <div class="ff"><label>Designation</label><input class="form-control" name="designation" placeholder="e.g. QA/QC Manager"></div>
    <div class="ff"><label>Department</label><select class="form-control searchable" name="department"><option value="">—</option><?php foreach (lk_options_or('department', DEPARTMENTS) as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Project (optional)</label><input class="form-control" name="project"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" name="mobile"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email"></div>
    <div class="ff"><label>At which site / office</label><select class="form-control searchable" name="address_id"><option value="">— (none / head office) —</option><?php foreach ($addresses as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e(addr_name($a)) ?></option><?php endforeach; ?></select><?php if (!$addresses): ?><div class="helptext">Add addresses first to link a contact to a site.</div><?php endif; ?></div>
    <button class="btn small" type="submit">Add Contact</button>
  </form>

<?php elseif ($tab === 'contracts'): ?>
  <?php // A contract and the quotation it came from are two views of one
        // agreement, so each names the other. Registering the number on the
        // quotation puts the contract here; recording it here puts the number
        // back on the quotation. Either way nothing is left saying "pending".
        $awaiting = function_exists('quotations_awaiting_contract') ? quotations_awaiting_contract($id) : []; ?>
  <table class="grid"><tr><th>Contract No.</th><th>Against <?= e(Tl('quote')) ?></th><th>Title</th><th>Value</th><th>Start</th><th>End</th></tr>
    <?php foreach ($contracts as $c):
      $cq = !empty($c['quotation_id']) ? ops_one("SELECT id, quote_no, rev, total_amount FROM quotations WHERE id=?", [(int)$c['quotation_id']]) : null; ?>
      <tr><td><?= e($c['contract_number']) ?></td>
        <td><?php if ($cq): ?><a href="/quote?id=<?= (int)$cq['id'] ?>"><?= e($cq['quote_no']) ?><?= (int)$cq['rev'] ? ' R' . (int)$cq['rev'] : '' ?></a>
            <?php else: ?><span class="muted">— recorded directly —</span><?php endif; ?></td>
        <td><?= e($c['title'] ?: '—') ?></td><td><?= $c['value']!==null?cur_sym().e($c['value']):'—' ?></td>
        <td><?= fdate($c['start_date']) ?></td>
        <td><?php if (trim((string)$c['end_date']) === ''): ?><span class="pill p-warn">no end date</span>
            <?php else: ?><?= fdate($c['end_date']) ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$contracts): ?><tr><td colspan="6">No contracts yet.</td></tr><?php endif; ?></table>

  <?php if ($awaiting): ?>
    <p class="muted" style="margin-top:10px"><b><?= count($awaiting) ?></b>
      <?= e(count($awaiting) === 1 ? Tl('quote') : Tlp('quote')) ?> for this <?= e(Tl('client')) ?>
      <?= count($awaiting) === 1 ? 'has' : 'have' ?> no contract number yet — pick one below and it is filled in on both sides.</p>
  <?php endif; ?>

  <h3 class="tab-sub">Add a contract</h3>
  <p class="muted">Contracts are usually recorded after a purchase order is received. If the order came from a
    <?= e(Tl('quote')) ?> we raised, name it — the number is written back onto that <?= e(Tl('quote')) ?>,
    and the value and dates are offered from it.</p>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=contract" class="inline-add">
    <div class="ff"><label>Contract number</label><input class="form-control" name="contract_number" required></div>
    <div class="ff"><label>Against <?= e(Tl('quote')) ?> <span class="muted">— only those with no contract number</span></label>
      <select class="form-control searchable" id="ct_quote" name="quotation_id">
        <option value="">— none / recorded directly —</option>
        <?php foreach ($awaiting as $aq): ?>
          <option value="<?= (int)$aq['id'] ?>" data-value="<?= e((string)$aq['total_amount']) ?>" data-title="<?= e($aq['subject'] ?? '') ?>">
            <?= e($aq['quote_no']) ?><?= (int)$aq['rev'] ? ' R' . (int)$aq['rev'] : '' ?>
            · <?= e(cur_sym()) ?><?= number_format((float)$aq['total_amount'], 0) ?>
            · <?= e(lk_options_or('quote_status', QUOTE_STATUS)[$aq['status']] ?? $aq['status']) ?>
            <?= $aq['subject'] ? ' — ' . e(mb_strimwidth($aq['subject'], 0, 40, '…')) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!$awaiting): ?><small class="muted">Every <?= e(Tl('quote')) ?> for this <?= e(Tl('client')) ?> already has a contract number.</small><?php endif; ?></div>
    <div class="ff"><label>Title</label><input class="form-control" id="ct_title" name="title"></div>
    <div class="ff"><label><?= e(T("sbu")) ?></label><select class="form-control searchable" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" step="0.01" id="ct_value" name="value"></div>
    <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date"></div>
    <?php // Without an end date a contract never expires, and the expiry warnings
          // and the scheduling gate have nothing to work from. It was missing here. ?>
    <div class="ff"><label>End date <span class="muted">— when cover stops</span></label><input class="form-control" type="date" name="end_date"></div>
    <div class="ff"><label>Quantity sold <span class="muted">— optional; blank means untracked</span></label><input class="form-control" type="number" step="0.01" name="qty_total"></div>
    <button class="btn small" type="submit">Add Contract</button>
  </form>
  <script>
  (function () {
    // Naming the quotation offers its value and subject, so the two records do
    // not drift apart over a typo.
    var q = document.getElementById('ct_quote');
    if (!q) return;
    q.addEventListener('change', function () {
      var o = q.options[q.selectedIndex]; if (!o || !o.value) return;
      var v = document.getElementById('ct_value'), t = document.getElementById('ct_title');
      if (v && !v.value && o.dataset.value) v.value = o.dataset.value;
      if (t && !t.value && o.dataset.title) t.value = o.dataset.title;
    });
  })();
  </script>

<?php elseif ($tab === 'purchase_orders'): ?>
  <?php $ctById = []; foreach ($contracts as $ct) $ctById[$ct['id']] = $ct; ?>
  <?php // Whether an order carries line items decides whether it is usable
        // downstream at all, so it is a column here rather than something you
        // find out by opening the order or, worse, by staring at an empty
        // dropdown on an inspection call. ?>
  <table class="grid"><tr><th>PO No.</th><th>Type</th><th>Title</th><th>Value</th><th>Contract</th><th>Line items</th></tr>
    <?php foreach ($pos as $o): $nLines = (int)ops_val("SELECT COUNT(*) FROM po_line_items WHERE purchase_order_id=?", [$o['id']]); ?><tr><td><a href="/po?id=<?= (int)$o['id'] ?>"><?= e($o['po_number'] ?: '(open)') ?></a></td><td><?= e(lk_options_or('po_type', PO_TYPES)[$o['po_type']] ?? $o['po_type']) ?></td><td><?= e($o['title'] ?: '—') ?></td><td><?= $o['value']!==null?cur_sym().e($o['value']):'—' ?></td><td><?= isset($ctById[$o['contract_id']]) ? e($ctById[$o['contract_id']]['contract_number']) : '—' ?></td>
      <td><?php if ($nLines): ?><?= $nLines ?><?php else: ?><a class="pill p-warn" href="/po?id=<?= (int)$o['id'] ?>">none — add them</a><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$pos): ?><tr><td colspan="6">No purchase orders yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a purchase order</h3>
  <?php // The order the client sends is the answer to a quotation we sent them.
        // Name the quotation and everything else is already written down: the
        // contract number, the business unit, the value and every line item. Typing them
        // again is how the order and the quotation drift apart. ?>
  <?php $poQuotes = function_exists('quotations_for_po') ? quotations_for_po($id) : []; ?>
  <p class="muted">Pick the <?= e(Tl('quote')) ?> this order answers — the contract number, <?= e(Tl('sbu')) ?>,
    value and all its line items come across with it. For an order that arrived without a
    <?= e(Tl('quote')) ?>, leave it blank and fill the boxes yourself.</p>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=po" class="inline-add">
    <div class="ff ff-wide"><label><?= e(T('quote')) ?> this order answers</label>
      <select class="form-control searchable" id="po_quote" name="quotation_id">
        <option value="">— none / arrived directly —</option>
        <?php foreach ($poQuotes as $pq): ?>
          <option value="<?= (int)$pq['id'] ?>"
                  data-contract="<?= (int)($pq['contract_id'] ?? 0) ?>"
                  data-contractno="<?= e((string)($pq['contract_number'] ?? '')) ?>"
                  data-sbu="<?= e((string)($pq['sbu'] ?? '')) ?>"
                  data-value="<?= e((string)$pq['total_amount']) ?>"
                  data-title="<?= e((string)($pq['subject'] ?? '')) ?>"
                  data-lines="<?= (int)$pq['line_count'] ?>">
            <?= e($pq['quote_no']) ?><?= (int)$pq['rev'] ? ' R' . (int)$pq['rev'] : '' ?>
            · <?= e(cur_sym()) ?><?= number_format((float)$pq['total_amount'], 0) ?>
            · <?= (int)$pq['line_count'] ?> line(s)
            <?= ($pq['contract_number'] ?? '') !== '' ? ' · contract ' . e($pq['contract_number']) : ' · no contract yet' ?>
            <?= $pq['subject'] ? ' — ' . e(mb_strimwidth($pq['subject'], 0, 40, '…')) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="muted" id="po_quote_note"><?= $poQuotes ? '' : 'No open ' . e(Tlp('quote')) . ' for this ' . e(Tl('client')) . ' yet.' ?></small></div>
    <div class="ff"><label>PO number</label><input class="form-control" name="po_number"></div>
    <div class="ff"><label>Type</label><select class="form-control" name="po_type"><?php foreach (lk_options_or('po_type', PO_TYPES) as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Against contract</label>
      <select class="form-control searchable" id="po_contract" name="contract_id"><option value="">— none —</option>
        <?php foreach ($contracts as $ct): ?><option value="<?= (int)$ct['id'] ?>"><?= e($ct['contract_number'].' '.$ct['title']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label><?= e(TP('sbu')) ?> — revenue (tick one or more)</label>
      <div class="checkgrid"><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><label class="chk"><input type="checkbox" class="po-sbu" name="po_sbu[]" value="<?= e($k) ?>"> <?= e($v) ?></label><?php endforeach; ?></div></div>
    <div class="ff"><label>Title</label><input class="form-control" id="po_title" name="title"></div>
    <div class="ff"><label>Value <span class="muted">— from the <?= e(Tl('quote')) ?> if one is named</span></label><input class="form-control" type="number" step="0.01" id="po_value" name="value"></div>
    <button class="btn small" type="submit">Add PO</button>
  </form>
  <script>
  (function () {
    var q = document.getElementById('po_quote');
    if (!q) return;
    q.addEventListener('change', function () {
      var o = q.options[q.selectedIndex], note = document.getElementById('po_quote_note');
      if (!o || !o.value) { if (note) note.textContent = ''; return; }
      var d = o.dataset;
      // the contract it already belongs to
      var c = document.getElementById('po_contract');
      if (c && d.contract && d.contract !== '0') {
        c.value = d.contract;
        c.dispatchEvent(new Event('change', { bubbles: true }));
        var box = c.parentNode && c.parentNode.querySelector('input');
        if (box) box.value = (c.options[c.selectedIndex] || {}).textContent || '';
      }
      // the business unit it was sold under
      if (d.sbu) Array.prototype.forEach.call(document.querySelectorAll('.po-sbu'), function (cb) {
        if (d.sbu.split(',').indexOf(cb.value) >= 0) cb.checked = true;
      });
      var t = document.getElementById('po_title'), v = document.getElementById('po_value');
      if (t && !t.value && d.title) t.value = d.title;
      if (v && !v.value && d.value) v.value = d.value;
      if (note) note.textContent = d.lines && d.lines !== '0'
        ? d.lines + ' line item(s) will be copied from this ' + o.textContent.trim().split(' ')[0] + ' when you save.'
        : 'This quotation has no line items, so none will be copied.';
    });
  })();
  </script>

<?php elseif ($tab === 'projects'): ?>
  <table class="grid"><tr><th>Call</th><th>Type</th><th>Received</th><th>Required by</th><th>Status</th><th></th></tr>
    <?php foreach (($linkedCalls ?? []) as $lc): ?><tr>
      <td><a href="/call?id=<?= (int)$lc['id'] ?>"><?= e($lc['call_code']) ?></a></td>
      <td><?= e(INSPECTION_TYPES[$lc['inspection_type']] ?? ($lc['inspection_type'] ?: '—')) ?></td>
      <td><?= fdate($lc['call_received_date']) ?></td><td><?= fdate($lc['inspection_required_date']) ?></td>
      <td><span class="badge <?= ($lc['status']??'')==='CLOSED'?'GREEN':'AMBER' ?>"><?= e($lc['status']) ?></span></td>
      <td class="row-actions"><a class="btn small secondary" href="/call?id=<?= (int)$lc['id'] ?>">Open</a></td>
    </tr><?php endforeach; ?>
    <?php if (empty($linkedCalls)): ?><tr><td colspan="6">No <?= e(Tlp('call')) ?> for this partner yet. <a href="/call-new">Create one</a>.</td></tr><?php endif; ?></table>

<?php elseif ($tab === 'relationships'): ?>
  <table class="grid"><tr><th>This company…</th><th>Related company</th><th>Notes</th></tr>
    <?php foreach ($rels as $r): ?><tr><td><?= e(lk_options_or('relationship_type', REL_TYPES)[$r['relation_type']] ?? $r['relation_type']) ?></td><td><?php if ($r['rid']): ?><a href="/partner?id=<?= (int)$r['rid'] ?>"><?= e($r['rd'] ?: $r['rn']) ?></a><?php else: ?>—<?php endif; ?></td><td><?= e($r['notes'] ?: '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$rels): ?><tr><td colspan="3">No relationships recorded.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a relationship</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=relationship" class="inline-add">
    <div class="ff"><label>Relation</label><select class="form-control" name="relation_type"><?php foreach (lk_options_or('relationship_type', REL_TYPES) as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Related company</label><select class="form-control searchable" name="related_id"><option value="">— select company —</option><?php foreach ($all_partners as $ap): ?><option value="<?= (int)$ap['id'] ?>"><?= e($ap['display_name'] ?: $ap['legal_name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Notes</label><input class="form-control" name="notes"></div>
    <button class="btn small" type="submit">Add</button>
  </form>

<?php elseif ($tab === 'notes'): ?>
  <?php foreach ($notes as $n): ?><div class="note-item"><div class="muted"><?= fdate($n['created_at']) ?> · <?= e($n['author_name'] ?: '—') ?></div><?= e($n['note']) ?></div><?php endforeach; ?>
  <?php if (!$notes): ?><p>No notes yet.</p><?php endif; ?>
  <h3 class="tab-sub">Add a note</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=note" class="inline-add">
    <div class="ff ff-wide"><label>Note</label><input class="form-control" name="note" required></div>
    <button class="btn small" type="submit">Add Note</button>
  </form>

<?php elseif ($tab === 'timeline'): ?>
  <?php
    $tl = [['d'=>$p['created_at'],'i'=>'🏢','t'=>'Partner record created ('.$p['code'].')']];
    foreach ($notes as $n) $tl[] = ['d'=>$n['created_at'],'i'=>'📝','t'=>'Note: '.substr($n['note'],0,70)];
    foreach ($contracts as $c) $tl[] = ['d'=>$c['start_date'],'i'=>'📄','t'=>'Contract '.$c['contract_number']];
    foreach ($pos as $o) $tl[] = ['d'=>$o['start_date'],'i'=>'🧾','t'=>'PO '.($o['po_number'] ?: '(open)')];
    usort($tl, fn($a,$b) => strcmp((string)$b['d'], (string)$a['d']));
  ?>
  <div class="timeline">
    <?php foreach ($tl as $ev): ?><div class="tl-item"><span class="tl-icon"><?= $ev['i'] ?></span><span class="tl-date"><?= fdate($ev['d']) ?></span><span class="tl-text"><?= e($ev['t']) ?></span></div><?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
