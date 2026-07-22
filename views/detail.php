<?php
function fdate($d) { if (!$d) return '—'; $t = strtotime($d); return $t ? date('d M Y', $t) : e($d); }
$badge = $p['status']==='ACTIVE'?'GREEN':($p['status']==='BLACKLISTED'?'RED':'AMBER');
$id = (int)$p['id'];
$isClient = !empty($p['is_client']);
// Contract Numbers / Purchase Orders / Projects only for clients — hidden for a pure
// vendor / manufacturer / trader / supplier (requirement 17). PO tab sits before Contracts (10).
$tabs = ['overview'=>'Overview','general'=>'General','registration'=>'Registration','addresses'=>'Addresses','contacts'=>'Contacts'];
if ($isClient) { $tabs['purchase_orders']='Purchase Orders'; $tabs['contracts']='Contract Numbers'; $tabs['projects']='Projects'; }
$tabs += ['branch_roles'=>'Branch Roles','relationships'=>'Relationships','notes'=>'Notes','timeline'=>'Timeline'];
$sbuOpts = lk_options_or('sbu', OPS_SBUS);
// Cross-tab data flow: primary contact/address and links between them.
$primaryContact = null; foreach ($contacts as $c) { if ($c['is_primary']) { $primaryContact = $c; break; } } if (!$primaryContact && $contacts) $primaryContact = $contacts[0];
$primaryAddress = null; foreach ($addresses as $a) { if ($a['is_primary']) { $primaryAddress = $a; break; } } if (!$primaryAddress && $addresses) $primaryAddress = $addresses[0];
$addrById = []; foreach ($addresses as $a) $addrById[$a['id']] = $a;
$contactsByAddr = []; foreach ($contacts as $c) { $contactsByAddr[$c['address_id'] ?: 0][] = $c; }
function addr_line($a) { return implode(', ', array_filter([$a['line1'],$a['line2'],$a['village']??'',$a['district']??'',$a['city'],$a['state'],$a['pincode']])); }
function addr_name($a) { return (ADDRESS_TYPES[$a['address_type']] ?? $a['address_type']) . ($a['label'] ? ' — '.$a['label'] : ''); }
// Reusable City/State/Department dropdown with an "Other → type it" option.
function master_select($name, $typeKey, $current, $extraClass='') {
    $opts = lk_values_as_options($typeKey);
    $known = false; $out = '<select class="form-control searchable other-toggle '.$extraClass.'" name="'.e($name).'" data-other="'.e($name).'_other_wrap"><option value="">—</option>';
    foreach ($opts as $o) { $sel = ($current!=='' && strcasecmp($current,$o['label'])===0)?' selected':''; if ($sel) $known=true; $out .= '<option value="'.e($o['label']).'"'.$sel.'>'.e($o['label']).'</option>'; }
    $out .= '<option value="__other__"'.(($current!=='' && !$known)?' selected':'').'>Other (type it)…</option></select>';
    $out .= '<div id="'.e($name).'_other_wrap" style="margin-top:6px;'.(($current!=='' && !$known)?'':'display:none;').'"><input class="form-control" name="'.e($name).'_other" placeholder="Type it — spelling auto-corrected" value="'.e(!$known?$current:'').'"></div>';
    return $out;
}
?>
<div class="master-head">
  <div><h1><?= e(partner_name($p)) ?></h1>
    <p class="sub"><?= e($p['code']) ?> · <?= e(roles_label($p)) ?> <span class="badge <?= $badge ?>"><?= e(STATUSES[$p['status']] ?? $p['status']) ?></span></p></div>
  <a class="btn secondary" href="/partner-edit?id=<?= $id ?>">Edit</a>
</div>

<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?><a href="/partner?id=<?= $id ?>&tab=<?= $k ?>" class="<?= $tab===$k?'active':'' ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>

<div class="panel tab-body">
<?php if ($tab === 'overview'): ?>
  <dl class="kv">
    <dt>Roles</dt><dd><?= e(roles_label($p)) ?></dd>
    <dt>Client type</dt><dd><?= e(CLIENT_TYPES[$p['client_type']] ?? '—') ?></dd>
    <dt>Industry</dt><dd><?= e(industry_label($p['industry'])) ?></dd>
    <dt>Ownership</dt><dd><?= e(OWNERSHIP[$p['ownership_type']] ?? '—') ?></dd>
    <dt>GSTIN</dt><dd><?= e($p['gstin'] ?: '—') ?><?php if ($p['state']): ?> <span class="muted">(<?= e($p['state']) ?>)</span><?php endif; ?></dd>
    <dt>PAN</dt><dd><?= e($p['pan'] ?: '—') ?></dd>
    <dt>CIN</dt><dd><?= e($p['cin'] ?: '—') ?></dd>
    <dt>Website</dt><dd><?php if ($p['website']): ?><a href="<?= e($p['website']) ?>" target="_blank" rel="noopener"><?= e($p['website']) ?></a><?php else: ?>—<?php endif; ?></dd>
    <dt>Primary contact</dt><dd><?php if ($primaryContact): ?><?= e($primaryContact['name']) ?><?php if ($primaryContact['designation']): ?> <span class="muted">(<?= e($primaryContact['designation']) ?>)</span><?php endif; ?> — <?= e(trim($primaryContact['mobile'].' '.$primaryContact['email'])) ?: '—' ?><?php else: ?>—<?php endif; ?></dd>
    <dt>Head office</dt><dd><?php if ($primaryAddress): ?><?= e(addr_line($primaryAddress) ?: addr_name($primaryAddress)) ?><?php else: ?>—<?php endif; ?></dd>
    <dt>Description</dt><dd><?= e($p['description'] ?: '—') ?></dd>
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
    <?php foreach ($registrations as $r): ?><tr><td><?= e(REG_TYPES[$r['doc_type']] ?? $r['doc_type']) ?></td><td><?= e($r['number'] ?: '—') ?></td><td><?= fdate($r['valid_to']) ?></td></tr><?php endforeach; ?>
    <?php if (!$registrations): ?><tr><td colspan="3">No registrations yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add registration</h3>
  <p class="muted">Pick GSTIN or PAN and the number auto-fills from the company's General details (still editable).</p>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=registration" class="inline-add" id="reg_form"
        data-gstin="<?= e($p['gstin'] ?? '') ?>" data-pan="<?= e($p['pan'] ?? '') ?>">
    <div class="ff"><label>Document</label><select class="form-control" id="reg_doc_type" name="doc_type"><?php foreach (REG_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Number</label><input class="form-control" id="reg_number" name="number"></div>
    <div class="ff"><label>Valid till</label><input class="form-control" type="date" name="valid_to"></div>
    <div class="ff"><label>Notes</label><input class="form-control" name="notes"></div>
    <button class="btn small" type="submit">Add</button>
  </form>

<?php elseif ($tab === 'addresses'): ?>
  <?php foreach ($addresses as $a): ?>
    <div class="addr-card"><div><span class="badge GREEN"><?= e(ADDRESS_TYPES[$a['address_type']] ?? $a['address_type']) ?></span> <strong><?= e($a['label']) ?></strong><?php if ($a['is_primary']): ?> <span class="badge AMBER">head office</span><?php endif; ?></div>
    <div class="muted"><?= e(addr_line($a)) ?></div>
    <?php foreach (($contactsByAddr[$a['id']] ?? []) as $ct): ?><div class="muted">· <?= e($ct['name']) ?> <?= e($ct['designation']) ?> — <?= e(trim($ct['mobile'].' '.$ct['email'])) ?></div><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$addresses): ?><p>No addresses yet.</p><?php endif; ?>
  <h3 class="tab-sub">Add an address</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=address" class="inline-add">
    <div class="ff"><label>Type</label><select class="form-control" name="address_type"><?php foreach (ADDRESS_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Label</label><input class="form-control" name="label"></div>
    <div class="ff"><label>Address line</label><input class="form-control" name="line1"></div>
    <div class="ff"><label>Town / Village</label><input class="form-control" name="village"></div>
    <div class="ff"><label>District</label><input class="form-control" name="district"></div>
    <div class="ff"><label>City</label><?= master_select('city', 'city', '') ?></div>
    <div class="ff"><label>State</label><?= master_select('state', 'state_in', '') ?></div>
    <div class="ff"><label>Pincode</label><input class="form-control" name="pincode"></div>
    <button class="btn small" type="submit">Add Address</button>
  </form>

<?php elseif ($tab === 'contacts'): ?>
  <table class="grid"><tr><th>Name</th><th>Designation</th><th>Department</th><th>Project</th><th>Mobile</th><th>Email</th><th>At (site / office)</th></tr>
    <?php foreach ($contacts as $c): ?><tr><td><?= e($c['name']) ?><?php if ($c['is_primary']): ?> <span class="badge AMBER">primary</span><?php endif; ?></td><td><?= e($c['designation'] ?: '—') ?></td><td><?= e(($c['department'] ?? '') ?: '—') ?></td><td><?= e(($c['project'] ?? '') ?: '—') ?></td><td><?= e($c['mobile'] ?: '—') ?></td><td><?= e($c['email'] ?: '—') ?></td><td><?= isset($addrById[$c['address_id']]) ? e(addr_name($addrById[$c['address_id']])) : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$contacts): ?><tr><td colspan="7">No contacts yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a contact</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=contact" class="inline-add">
    <div class="ff"><label>Name</label><input class="form-control" name="name" required></div>
    <div class="ff"><label>Designation</label><input class="form-control" name="designation" placeholder="e.g. Sr. Engineer"></div>
    <div class="ff"><label>Department</label><?= master_select('department', 'department', '') ?></div>
    <div class="ff"><label>Project <span class="muted">(optional)</span></label><input class="form-control" name="project" placeholder="Project he/she works under"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" name="mobile"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email"></div>
    <div class="ff"><label>At which site / office</label><select class="form-control searchable" name="address_id"><option value="">— (none / head office) —</option><?php foreach ($addresses as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e(addr_name($a)) ?></option><?php endforeach; ?></select><?php if (!$addresses): ?><div class="helptext">Add addresses first to link a contact to a site.</div><?php endif; ?></div>
    <button class="btn small" type="submit">Add Contact</button>
  </form>

<?php elseif ($tab === 'contracts'): ?>
  <p class="muted">A contract is normally recorded after its purchase order is received.</p>
  <table class="grid"><tr><th>Contract No.</th><th>Title</th><th>SBU</th><th>Value</th><th>Start</th><th>End</th></tr>
    <?php foreach ($contracts as $c): ?><tr><td><?= e($c['contract_number']) ?></td><td><?= e($c['title'] ?: '—') ?></td><td><?= e(sbu_csv_labels($c['sbu'] ?? '', $sbuOpts)) ?></td><td><?= $c['value']!==null?'₹'.e($c['value']):'—' ?></td><td><?= fdate($c['start_date']) ?></td><td><?= fdate($c['end_date']) ?></td></tr><?php endforeach; ?>
    <?php if (!$contracts): ?><tr><td colspan="6">No contracts yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a contract</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=contract" class="inline-add">
    <div class="ff"><label>Contract number</label><input class="form-control" name="contract_number" required></div>
    <div class="ff"><label>Title</label><input class="form-control" name="title"></div>
    <div class="ff"><label>SBU(s) <span class="muted">— revenue owner</span></label>
      <select class="form-control" name="sbu[]" multiple size="4"><?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" step="0.01" name="value"></div>
    <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date"></div>
    <button class="btn small" type="submit">Add Contract</button>
  </form>

<?php elseif ($tab === 'purchase_orders'): ?>
  <?php $ctById = []; foreach ($contracts as $ct) $ctById[$ct['id']] = $ct; ?>
  <table class="grid"><tr><th>PO No.</th><th>Type</th><th>Title</th><th>SBU</th><th>Value</th><th>Contract</th></tr>
    <?php foreach ($pos as $o): ?><tr><td><a href="/po?id=<?= (int)$o['id'] ?>"><?= e($o['po_number'] ?: '(open)') ?></a></td><td><?= e(PO_TYPES[$o['po_type']] ?? $o['po_type']) ?></td><td><?= e($o['title'] ?: '—') ?></td><td><?= e(sbu_csv_labels($o['sbu'] ?? '', $sbuOpts)) ?></td><td><?= $o['value']!==null?'₹'.e($o['value']):'—' ?></td><td><?= isset($ctById[$o['contract_id']]) ? e($ctById[$o['contract_id']]['contract_number']) : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$pos): ?><tr><td colspan="6">No purchase orders yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a purchase order</h3>
  <p class="muted">For open / ARC orders, save the PO then add line items (manpower, trade, sub-category, GST).</p>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=po" class="inline-add">
    <div class="ff"><label>PO number</label><input class="form-control" name="po_number"></div>
    <div class="ff"><label>Type</label><select class="form-control" name="po_type"><?php foreach (PO_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Against contract</label><select class="form-control searchable" name="contract_id"><option value="">— none —</option><?php foreach ($contracts as $ct): ?><option value="<?= (int)$ct['id'] ?>"><?= e($ct['contract_number'].' '.$ct['title']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>SBU(s) <span class="muted">— revenue owner</span></label>
      <select class="form-control" name="sbu[]" multiple size="4"><?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Title</label><input class="form-control" name="title"></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" step="0.01" name="value"></div>
    <button class="btn small" type="submit">Add PO</button>
  </form>

<?php elseif ($tab === 'projects'): ?>
  <p class="sub">Inspection calls where this company is the client or the vendor / site.</p>
  <table class="grid"><tr><th>Call</th><th>Role</th><th>Type</th><th>Required date</th><th>Executing office</th><th>Status</th></tr>
    <?php foreach (($partner_calls ?? []) as $c): ?><tr>
      <td><a href="/call?id=<?= (int)$c['id'] ?>"><?= e($c['call_code']) ?></a></td>
      <td><?= !empty($c['as_client']) ? 'Client' : 'Vendor / site' ?></td>
      <td><?= e(INSPECTION_TYPES[$c['inspection_type']] ?? ($c['inspection_type'] ?: '—')) ?></td>
      <td><?= fdate($c['inspection_required_date']) ?></td>
      <td><?= e($c['exec_name'] ?: 'Ahmedabad') ?></td>
      <td><span class="badge <?= ($c['status']==='CLOSED'?'GREEN':'AMBER') ?>"><?= e($c['status']) ?></span></td></tr><?php endforeach; ?>
    <?php if (empty($partner_calls)): ?><tr><td colspan="6">No inspection calls linked yet.</td></tr><?php endif; ?></table>

<?php elseif ($tab === 'branch_roles'): ?>
  <p class="sub">The same company can be a <strong>Client</strong> under one branch and a <strong>Vendor</strong> under another — recorded here without creating a duplicate record.</p>
  <table class="grid"><tr><th>Branch</th><th>Role at that branch</th><th>Notes</th></tr>
    <?php $roleNames=['CLIENT'=>'Client','VENDOR'=>'Vendor','BOTH'=>'Client &amp; Vendor','MANUFACTURER'=>'Manufacturer','TRADER'=>'Trader','SUBCON'=>'Sub-contractor'];
    foreach (($branch_roles ?? []) as $br): ?><tr><td><?= e($br['office_name'] ?: '—') ?> <span class="muted"><?= e($br['office_code'] ?? '') ?></span></td><td><?= $roleNames[$br['role']] ?? e($br['role']) ?></td><td><?= e($br['notes'] ?: '—') ?></td></tr><?php endforeach; ?>
    <?php if (empty($branch_roles)): ?><tr><td colspan="3">No branch-specific roles yet — global roles (<?= e(roles_label($p)) ?>) apply everywhere.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a branch role</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=branch_role" class="inline-add">
    <div class="ff"><label>Branch</label><select class="form-control searchable" name="branch_id" required><option value="">— select —</option><?php foreach (offices_list() as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']).' ('.e($o['code']).')' ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Role at this branch</label><select class="form-control" name="role"><option value="CLIENT">Client</option><option value="VENDOR">Vendor</option><option value="BOTH">Client &amp; Vendor</option><option value="MANUFACTURER">Manufacturer</option><option value="TRADER">Trader</option><option value="SUBCON">Sub-contractor</option></select></div>
    <div class="ff"><label>Notes</label><input class="form-control" name="notes"></div>
    <button class="btn small" type="submit">Add</button>
  </form>

<?php elseif ($tab === 'relationships'): ?>
  <table class="grid"><tr><th>This company…</th><th>Related company</th><th>Notes</th></tr>
    <?php foreach ($rels as $r): ?><tr><td><?= e(REL_TYPES[$r['relation_type']] ?? $r['relation_type']) ?></td><td><?php if ($r['rid']): ?><a href="/partner?id=<?= (int)$r['rid'] ?>"><?= e($r['rd'] ?: $r['rn']) ?></a><?php else: ?>—<?php endif; ?></td><td><?= e($r['notes'] ?: '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$rels): ?><tr><td colspan="3">No relationships recorded.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a relationship</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=relationship" class="inline-add">
    <div class="ff"><label>Relation</label><select class="form-control" name="relation_type"><?php foreach (REL_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
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
