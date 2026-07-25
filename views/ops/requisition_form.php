<div class="crumbs"><a href="/">Home</a> › <a href="/requisitions"><?= e(TP('requisition')) ?></a> › <?= $req ? 'Edit' : 'New' ?></div>
<h1><?= $req ? 'Edit requisition '.e($req['req_code']) : 'New requisition' ?></h1>
<p class="sub">Record the management approval for a position. A candidate can only be hired against an approved requisition.</p>
<form method="post" action="<?= $req ? '/requisition-edit?id='.(int)$req['id'] : '/requisition-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Type *</label><select class="form-control" name="req_type" id="rq_type"><?php foreach (REQ_TYPES as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($req && $req['req_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff" id="rq_out" style="<?= ($req && $req['req_type']==='REPLACEMENT')?'':'display:none' ?>"><label>Replacing (engineer who left)</label>
      <select class="form-control searchable" name="outgoing_inspector_id"><option value="">—</option><?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ($req && $req['outgoing_inspector_id']==$i['id'])?'selected':'' ?>><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Office</label><select class="form-control searchable" name="office_id"><option value="">—</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ($req && $req['office_id']==$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>SBU</label><select class="form-control" name="sbu"><option value="">—</option><?php foreach (OPS_SBUS as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($req && $req['sbu']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Designation / position</label><select class="form-control searchable" name="designation"><option value="">—</option><?php foreach (DESIGNATIONS as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($req && $req['designation']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Project / site</label><input class="form-control" name="project_site" value="<?= e($req['project_site'] ?? '') ?>"></div>
    <div class="ff"><label>Budgeted monthly cost (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="budgeted_cost" value="<?= e($req['budgeted_cost'] ?? '') ?>"></div>
    <div class="ff"><label>Status</label><select class="form-control" name="status"><?php foreach (REQ_STATUS as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($req ? $req['status']===$k : $k==='OPEN')?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Approval reference *</label><input class="form-control" name="approval_ref" value="<?= e($req['approval_ref'] ?? '') ?>" placeholder="e.g. HR-APP-2026-014"></div>
    <div class="ff"><label>Approved by</label><input class="form-control" name="approved_by" value="<?= e($req['approved_by'] ?? '') ?>"></div>
    <div class="ff"><label>Approval date</label><input class="form-control" type="date" name="approval_date" value="<?= e($req['approval_date'] ?? '') ?>"></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($req['notes'] ?? '') ?>"></div>
  </div>
  <div style="margin-top:16px"><button class="btn" type="submit">Save requisition</button> <a class="btn secondary" href="/requisitions">Cancel</a></div>
</form>
<script>(function(){var t=document.getElementById('rq_type'),o=document.getElementById('rq_out');if(t&&o){function s(){o.style.display=(t.value==='REPLACEMENT')?'':'none';}t.addEventListener('change',s);s();}})();</script>
