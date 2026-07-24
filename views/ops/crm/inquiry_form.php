<div class="crumbs"><a href="/">Home</a> › <a href="/inquiries">Inquiries</a> › <?= $inq ? 'Edit' : 'New' ?></div>
<div class="master-head">
  <div><h1><?= $inq ? 'Edit inquiry — ' . e($inq['inquiry_no']) : 'New customer inquiry' ?></h1>
    <p class="sub" style="margin:2px 0 0">Capture what the customer asked for. You can raise a quotation against it afterwards.</p></div>
  <a class="btn secondary" href="/inquiries">← Back</a>
</div>

<form method="post" action="/<?= $inq ? 'inquiry-edit?id=' . (int)$inq['id'] : 'inquiry-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Client</label>
      <select class="form-control searchable" name="client_id"><option value="">— pick or type below —</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)($inq['client_id'] ?? '')===(string)$cl['id']?'selected':'' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or client name (if not yet registered)</label><input class="form-control" name="client_name" value="<?= e($inq['client_name'] ?? '') ?>"></div>
    <div class="ff"><label>Received date</label><input class="form-control" type="date" name="received_date" value="<?= e($inq['received_date'] ?? date('Y-m-d')) ?>"></div>

    <div class="ff"><label>Contact person</label><input class="form-control" name="contact_name" value="<?= e($inq['contact_name'] ?? '') ?>"></div>
    <div class="ff"><label>Contact email</label><input class="form-control" name="contact_email" value="<?= e($inq['contact_email'] ?? '') ?>"></div>
    <div class="ff"><label>Contact mobile</label><input class="form-control" name="contact_mobile" value="<?= e($inq['contact_mobile'] ?? '') ?>"></div>

    <div class="ff"><label>SBU</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($inq['sbu'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Source</label>
      <select class="form-control" name="source"><?php foreach ($sourceOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($inq['source'] ?? 'EMAIL')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Status</label>
      <select class="form-control" name="status"><?php foreach ($statusOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($inq['status'] ?? 'OPEN')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>

    <div class="ff"><label>Assigned to</label>
      <select class="form-control searchable" name="assigned_to"><option value="">—</option>
        <?php foreach ($users as $u): $nm = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= (string)($inq['assigned_to'] ?? '')===(string)$u['id']?'selected':'' ?>><?= e($nm) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Subject</label><input class="form-control" name="subject" value="<?= e($inq['subject'] ?? '') ?>" placeholder="e.g. Third-party inspection — Adani Mundra"></div>
    <div class="ff ff-wide"><label>Service requirement / details</label><textarea class="form-control" name="service_requirement" rows="3"><?= e($inq['service_requirement'] ?? '') ?></textarea></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($inq['notes'] ?? '') ?>"></div>
  </div>
  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $inq ? 'Save inquiry' : 'Create inquiry' ?></button>
    <a class="btn secondary" href="/inquiries">Cancel</a>
  </div>
</form>
