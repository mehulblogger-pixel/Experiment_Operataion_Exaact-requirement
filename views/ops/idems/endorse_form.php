<?php // Create / edit an endorsement (upload the manufacturer original + supporting evidence) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/endorsements"><?= e(TP('endorsement')) ?></a> › <?= $e ? e($e['endorsement_no']) : 'New' ?></div>
<div class="master-head"><div><h1><?= e($e ? ucfirst(T_EDIT('endorsement')) : ucfirst(T_NEW('endorsement'))) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= $e ? e($e['endorsement_no']) : 'Upload a manufacturer / vendor quality record for TPIA review &amp; endorsement. The original is never altered.' ?></p></div></div>

<form method="post" action="<?= $e ? '/endorsement-edit?id='.(int)$e['id'] : '/endorsement-new' ?>" enctype="multipart/form-data" class="panel">
  <h3 class="tab-sub" style="margin-top:0">Document</h3>
  <div class="form-grid">
    <div class="ff"><label>Document type *</label>
      <select class="form-control searchable" name="doc_type" required><?php foreach ($docTypes as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($e && $e['doc_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Title / description</label><input class="form-control" name="title" value="<?= e($e['title'] ?? '') ?>" placeholder="e.g. MTC for SA106 Gr.B pipe"></div>
    <div class="ff"><label>Manufacturer document (original) <?= $e ? '' : '*' ?></label><input class="form-control" type="file" name="orig" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"></div>
    <div class="ff"><label>Document version / rev.</label><input class="form-control" name="doc_version" value="<?= e($e['doc_version'] ?? '') ?>"></div>
    <div class="ff"><label>Heat / lot no.</label><input class="form-control" name="heat_no" value="<?= e($e['heat_no'] ?? '') ?>"></div>
    <div class="ff"><label>Item description</label><input class="form-control" name="item_desc" value="<?= e($e['item_desc'] ?? '') ?>"></div>
  </div>

  <h3 class="tab-sub">Parties &amp; references</h3>
  <div class="form-grid">
    <div class="ff"><label>Manufacturer / vendor <a href="#" class="addlink" data-qa="vendor" data-target="select[name='vendor_id']">+ Add new</a></label>
      <select class="form-control searchable" name="vendor_id"><option value="">—</option><?php foreach ($vendors as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($e && (int)$e['vendor_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Client <a href="#" class="addlink" data-qa="client" data-target="select[name='client_id']">+ Add new</a></label>
      <select class="form-control searchable" name="client_id"><option value="">—</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($e && (int)$e['client_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Linked inspection report</label>
      <select class="form-control searchable" name="report_doc_id"><option value="">—</option><?php foreach ($reports as $r): ?><option value="<?= (int)$r['id'] ?>" <?= ($e && (int)$e['report_doc_id']===(int)$r['id'])?'selected':'' ?>><?= e($r['irn']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Project code</label><input class="form-control" name="project_code" value="<?= e($e['project_code'] ?? '') ?>"></div>
    <div class="ff"><label>Project name</label><input class="form-control" name="project_name" value="<?= e($e['project_name'] ?? '') ?>"></div>
    <div class="ff"><label>Purchase order</label><input class="form-control" name="po_ref" value="<?= e($e['po_ref'] ?? '') ?>"></div>
    <div class="ff"><label>Drawing no.</label><input class="form-control" name="drawing_no" value="<?= e($e['drawing_no'] ?? '') ?>"></div>
    <div class="ff"><label>Drawing rev.</label><input class="form-control" name="drawing_rev" value="<?= e($e['drawing_rev'] ?? '') ?>"></div>
    <div class="ff"><label>QAP rev.</label><input class="form-control" name="qap_rev" value="<?= e($e['qap_rev'] ?? '') ?>"></div>
    <div class="ff"><label>Office</label>
      <select class="form-control searchable" name="office_id"><option value="">— your office —</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($e && (int)$e['office_id']===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label><?= e(T("sbu")) ?></label>
      <select class="form-control searchable" name="sbu"><option value="">—</option><?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($e && $e['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  </div>

  <h3 class="tab-sub">Review &amp; approver</h3>
  <div class="form-grid">
    <div class="ff"><label>Reviewing inspector</label>
      <select class="form-control searchable" name="inspector_id"><option value="">—</option><?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ($e && (int)$e['inspector_id']===(int)$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Endorsing approver <span class="muted">— blank uses the inspector's mapped approver</span></label>
      <select class="form-control searchable" name="approver_user_id"><option value="">— auto (inspector's approver) —</option><?php foreach ($approvers as $u): $nm=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= ($e && (int)$e['approver_user_id']===(int)$u['id'])?'selected':'' ?>><?= e($nm) ?> · <?= e(ORG_ROLES[$u['role']] ?? $u['role']) ?></option><?php endforeach; ?></select></div>
    <div class="ff ff-wide"><label>Reviewer notes</label><textarea class="form-control" name="review_remarks" rows="2"><?= e($e['review_remarks'] ?? '') ?></textarea></div>
    <div class="ff ff-wide"><label>Supporting evidence (optional, multiple)</label><input class="form-control" type="file" name="support[]" multiple></div>
  </div>

  <div style="margin-top:16px"><button class="btn" type="submit"><?= $e ? 'Save' : 'Create endorsement' ?></button>
    <a class="btn secondary" href="<?= $e ? '/endorsement?id='.(int)$e['id'] : '/endorsements' ?>">Cancel</a></div>
</form>
