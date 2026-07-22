<?php
function fdate($d) { if (!$d) return '—'; $t = strtotime($d); return $t ? date('d M Y', $t) : e($d); }
$badge = $p['status']==='ACTIVE'?'GREEN':($p['status']==='BLACKLISTED'?'RED':'AMBER');
$id = (int)$p['id'];
$tabs = ['overview'=>'Overview','general'=>'General','registration'=>'Registration','addresses'=>'Addresses','contacts'=>'Contacts','contracts'=>'Contract Numbers','purchase_orders'=>'Purchase Orders','projects'=>'Projects','relationships'=>'Relationships','notes'=>'Notes','timeline'=>'Timeline'];
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
    <dt>Industry</dt><dd><?= e(INDUSTRIES[$p['industry']] ?? '—') ?></dd>
    <dt>Ownership</dt><dd><?= e(OWNERSHIP[$p['ownership_type']] ?? '—') ?></dd>
    <dt>GSTIN</dt><dd><?= e($p['gstin'] ?: '—') ?><?php if ($p['state']): ?> <span class="muted">(<?= e($p['state']) ?>)</span><?php endif; ?></dd>
    <dt>PAN</dt><dd><?= e($p['pan'] ?: '—') ?></dd>
    <dt>CIN</dt><dd><?= e($p['cin'] ?: '—') ?></dd>
    <dt>Website</dt><dd><?php if ($p['website']): ?><a href="<?= e($p['website']) ?>" target="_blank" rel="noopener"><?= e($p['website']) ?></a><?php else: ?>—<?php endif; ?></dd>
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
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=registration" class="inline-add">
    <div class="ff"><label>Document</label><select class="form-control" name="doc_type"><?php foreach (REG_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Number</label><input class="form-control" name="number"></div>
    <div class="ff"><label>Valid till</label><input class="form-control" type="date" name="valid_to"></div>
    <div class="ff"><label>Notes</label><input class="form-control" name="notes"></div>
    <button class="btn small" type="submit">Add</button>
  </form>

<?php elseif ($tab === 'addresses'): ?>
  <?php foreach ($addresses as $a): ?>
    <div class="addr-card"><div><span class="badge GREEN"><?= e(ADDRESS_TYPES[$a['address_type']] ?? $a['address_type']) ?></span> <strong><?= e($a['label']) ?></strong></div>
    <div class="muted"><?= e(implode(', ', array_filter([$a['line1'],$a['line2'],$a['city'],$a['state'],$a['pincode']]))) ?></div></div>
  <?php endforeach; ?>
  <?php if (!$addresses): ?><p>No addresses yet.</p><?php endif; ?>
  <h3 class="tab-sub">Add an address</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=address" class="inline-add">
    <div class="ff"><label>Type</label><select class="form-control" name="address_type"><?php foreach (ADDRESS_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Label</label><input class="form-control" name="label"></div>
    <div class="ff"><label>Address line</label><input class="form-control" name="line1"></div>
    <div class="ff"><label>City</label><input class="form-control" name="city"></div>
    <div class="ff"><label>State</label><input class="form-control" name="state"></div>
    <div class="ff"><label>Pincode</label><input class="form-control" name="pincode"></div>
    <button class="btn small" type="submit">Add Address</button>
  </form>

<?php elseif ($tab === 'contacts'): ?>
  <table class="grid"><tr><th>Name</th><th>Designation</th><th>Mobile</th><th>Email</th></tr>
    <?php foreach ($contacts as $c): ?><tr><td><?= e($c['name']) ?></td><td><?= e($c['designation'] ?: '—') ?></td><td><?= e($c['mobile'] ?: '—') ?></td><td><?= e($c['email'] ?: '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$contacts): ?><tr><td colspan="4">No contacts yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a contact</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=contact" class="inline-add">
    <div class="ff"><label>Name</label><input class="form-control" name="name" required></div>
    <div class="ff"><label>Designation</label><input class="form-control" name="designation"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" name="mobile"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email"></div>
    <button class="btn small" type="submit">Add Contact</button>
  </form>

<?php elseif ($tab === 'contracts'): ?>
  <table class="grid"><tr><th>Contract No.</th><th>Title</th><th>Value</th><th>Start</th><th>End</th></tr>
    <?php foreach ($contracts as $c): ?><tr><td><?= e($c['contract_number']) ?></td><td><?= e($c['title'] ?: '—') ?></td><td><?= $c['value']!==null?'₹'.e($c['value']):'—' ?></td><td><?= fdate($c['start_date']) ?></td><td><?= fdate($c['end_date']) ?></td></tr><?php endforeach; ?>
    <?php if (!$contracts): ?><tr><td colspan="5">No contracts yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a contract</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=contract" class="inline-add">
    <div class="ff"><label>Contract number</label><input class="form-control" name="contract_number" required></div>
    <div class="ff"><label>Title</label><input class="form-control" name="title"></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" name="value"></div>
    <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date"></div>
    <button class="btn small" type="submit">Add Contract</button>
  </form>

<?php elseif ($tab === 'purchase_orders'): ?>
  <table class="grid"><tr><th>PO No.</th><th>Type</th><th>Title</th><th>Value</th></tr>
    <?php foreach ($pos as $o): ?><tr><td><a href="/po?id=<?= (int)$o['id'] ?>"><?= e($o['po_number'] ?: '(open)') ?></a></td><td><?= e(PO_TYPES[$o['po_type']] ?? $o['po_type']) ?></td><td><?= e($o['title'] ?: '—') ?></td><td><?= $o['value']!==null?'₹'.e($o['value']):'—' ?></td></tr><?php endforeach; ?>
    <?php if (!$pos): ?><tr><td colspan="4">No purchase orders yet.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a purchase order</h3>
  <p class="muted">For open / ARC orders, save the PO then add line items (days, months, audit days).</p>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=po" class="inline-add">
    <div class="ff"><label>PO number</label><input class="form-control" name="po_number"></div>
    <div class="ff"><label>Type</label><select class="form-control" name="po_type"><?php foreach (PO_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Title</label><input class="form-control" name="title"></div>
    <div class="ff"><label>Value</label><input class="form-control" type="number" name="value"></div>
    <button class="btn small" type="submit">Add PO</button>
  </form>

<?php elseif ($tab === 'projects'): ?>
  <p class="muted">Inspection calls linked to this partner will appear here once the Operations module is added (next stage).</p>

<?php elseif ($tab === 'relationships'): ?>
  <table class="grid"><tr><th>This company…</th><th>Related company</th><th>Notes</th></tr>
    <?php foreach ($rels as $r): ?><tr><td><?= e(REL_TYPES[$r['relation_type']] ?? $r['relation_type']) ?></td><td><?php if ($r['rid']): ?><a href="/partner?id=<?= (int)$r['rid'] ?>"><?= e($r['rd'] ?: $r['rn']) ?></a><?php else: ?>—<?php endif; ?></td><td><?= e($r['notes'] ?: '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$rels): ?><tr><td colspan="3">No relationships recorded.</td></tr><?php endif; ?></table>
  <h3 class="tab-sub">Add a relationship</h3>
  <form method="post" action="/partner-add?id=<?= $id ?>&kind=relationship" class="inline-add">
    <div class="ff"><label>Relation</label><select class="form-control" name="relation_type"><?php foreach (REL_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Related company ID</label><input class="form-control" name="related_id" placeholder="numeric id from the other partner's page"></div>
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
