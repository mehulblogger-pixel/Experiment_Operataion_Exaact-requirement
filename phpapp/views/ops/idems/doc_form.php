<?php // Create / edit a report instance. IRN is generated automatically on create. ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents">Documents</a> › <?= $doc ? e($doc['irn']) : 'New report' ?></div>
<div class="master-head"><div><h1><?= $doc ? 'Edit report' : 'New report' ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= $doc ? 'IRN '.e($doc['irn']).' — the IRN never changes.' : 'The IRN (Inspection Reference Number) is generated automatically when you save.' ?></p></div></div>

<form method="post" action="<?= $doc ? '/document-edit?id='.(int)$doc['id'] : '/document-new' ?>" class="panel">
  <h3 class="tab-sub" style="margin-top:0">Report</h3>
  <div class="form-grid">
    <div class="ff"><label>Report type *</label>
      <select class="form-control searchable" name="report_type_id" required><option value="">— select —</option>
        <?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= ($doc && (int)$doc['report_type_id']===(int)$t['id'])?'selected':'' ?>><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Title / subject</label><input class="form-control" name="title" value="<?= e($doc['title'] ?? '') ?>" placeholder="defaults to the report type name"></div>
    <div class="ff"><label>Inspection date</label><input class="form-control" type="date" name="inspection_date" value="<?= e($doc['inspection_date'] ?? date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Office / branch</label>
      <select class="form-control searchable" name="office_id"><option value="">— your office —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($doc && (int)$doc['office_id']===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>SBU</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($doc && $doc['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
  </div>

  <h3 class="tab-sub">Parties &amp; references <span class="muted">— structured entry (Part 18)</span></h3>
  <div class="form-grid">
    <div class="ff"><label>Client</label>
      <select class="form-control searchable" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($doc && (int)$doc['client_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Vendor / manufacturer</label>
      <select class="form-control searchable" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($doc && (int)$doc['vendor_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Project code <span class="muted">(used in the IRN)</span></label><input class="form-control" name="project_code" value="<?= e($doc['project_code'] ?? '') ?>" placeholder="e.g. P001"></div>
    <div class="ff"><label>Project name</label><input class="form-control" name="project_name" value="<?= e($doc['project_name'] ?? '') ?>"></div>
    <div class="ff"><label>Purchase order</label><input class="form-control" name="po_ref" value="<?= e($doc['po_ref'] ?? '') ?>"></div>
    <div class="ff"><label>Drawing no.</label><input class="form-control" name="drawing_no" value="<?= e($doc['drawing_no'] ?? '') ?>"></div>
    <div class="ff"><label>Drawing rev.</label><input class="form-control" name="drawing_rev" value="<?= e($doc['drawing_rev'] ?? '') ?>"></div>
    <div class="ff"><label>QAP rev.</label><input class="form-control" name="qap_rev" value="<?= e($doc['qap_rev'] ?? '') ?>"></div>
    <div class="ff"><label>Product category</label><input class="form-control" name="product_category" value="<?= e($doc['product_category'] ?? '') ?>"></div>
    <div class="ff"><label>Material grade</label><input class="form-control" name="material_grade" value="<?= e($doc['material_grade'] ?? '') ?>"></div>
    <div class="ff"><label>Applicable standards</label><input class="form-control" name="standards" value="<?= e($doc['standards'] ?? '') ?>" placeholder="e.g. ASME Sec VIII Div 1"></div>
    <div class="ff"><label>Location</label><input class="form-control" name="location" value="<?= e($doc['location'] ?? '') ?>"></div>
  </div>

  <h3 class="tab-sub">People &amp; outcome</h3>
  <div class="form-grid">
    <div class="ff"><label>Inspector</label>
      <select class="form-control searchable" name="inspector_id"><option value="">—</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ($doc && (int)$doc['inspector_id']===(int)$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Approver</label>
      <select class="form-control searchable" name="approver_user_id"><option value="">—</option>
        <?php foreach ($approvers as $u): $nm=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= ($doc && (int)$doc['approver_user_id']===(int)$u['id'])?'selected':'' ?>><?= e($nm) ?> · <?= e(ORG_ROLES[$u['role']] ?? $u['role']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Inspection result</label>
      <select class="form-control" name="result"><option value="">—</option>
        <?php foreach (IDEMS_RESULTS as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($doc && $doc['result']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Release status</label>
      <select class="form-control" name="release_status"><option value="">—</option>
        <?php foreach (IDEMS_RELEASE as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($doc && $doc['release_status']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Remarks</label><textarea class="form-control" name="remarks" rows="3"><?= e($doc['remarks'] ?? '') ?></textarea></div>
  </div>

  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $doc ? 'Save report' : 'Create report &amp; generate IRN' ?></button>
    <a class="btn secondary" href="<?= $doc ? '/document?id='.(int)$doc['id'] : '/documents' ?>">Cancel</a>
  </div>
</form>
