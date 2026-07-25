<?php // IDEMS approval rules (configurable multi-level chain) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents">Documents</a> › Approval rules</div>
<div class="master-head"><div><h1>Approval rules</h1>
  <p class="sub" style="margin:2px 0 0">Design the approval chain by report type, office, client or SBU. Leave a match blank for "any". Multiple levels run in order. No rule ⇒ the inspector's mapped approver is used (single level).</p></div>
  <a class="btn secondary" href="/approver-map">Approver mapping →</a>
</div>

<form method="post" action="/idems-approval-rules" class="panel">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <h3 class="tab-sub" style="margin-top:0"><?= $edit ? 'Edit rule' : 'Add a rule' ?></h3>
  <div class="form-grid">
    <div class="ff"><label>Rule name</label><input class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. NCR needs SBU head"></div>
    <div class="ff"><label>Level <span class="muted">(1 = first)</span></label><input class="form-control" type="number" min="1" name="level" value="<?= e($edit['level'] ?? 1) ?>"></div>
    <div class="ff"><label>SLA hours <span class="muted">(escalate after)</span></label><input class="form-control" type="number" min="0" name="sla_hours" value="<?= e($edit['sla_hours'] ?? 24) ?>"></div>
    <div class="ff ff-check"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>><label>Active</label></div>
  </div>
  <h4 style="margin:8px 0 4px">Applies to <span class="muted" style="font-weight:400">(blank = any)</span></h4>
  <div class="form-grid">
    <div class="ff"><label>Report type</label><select class="form-control searchable" name="report_type_code"><option value="">Any</option><?php foreach ($types as $t): ?><option value="<?= e($t['code']) ?>" <?= (($edit['report_type_code'] ?? '')===$t['code'])?'selected':'' ?>><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Office</label><select class="form-control searchable" name="office_id"><option value="">Any</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ((int)($edit['office_id'] ?? 0)===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Client</label><select class="form-control searchable" name="client_id"><option value="">Any</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['client_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>SBU</label><select class="form-control searchable" name="sbu"><option value="">Any</option><?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['sbu'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  </div>
  <h4 style="margin:8px 0 4px">Approver</h4>
  <div class="form-grid">
    <div class="ff"><label>Who approves</label><select class="form-control" name="approver_kind" id="akind"><?php foreach ($kinds as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['approver_kind'] ?? 'INSPECTOR_MAP')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff" data-akind="USER"><label>Person</label><select class="form-control searchable" name="approver_user_id"><option value="">—</option><?php foreach ($users as $u): $n=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= ((int)($edit['approver_user_id'] ?? 0)===(int)$u['id'])?'selected':'' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="ff" data-akind="ROLE"><label>Role</label><select class="form-control searchable" name="approver_role"><option value="">—</option><?php foreach (ORG_ROLES as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['approver_role'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  </div>
  <div style="margin-top:12px"><button class="btn" type="submit"><?= $edit ? 'Save rule' : 'Add rule' ?></button><?php if ($edit): ?> <a class="btn secondary" href="/idems-approval-rules">Cancel</a><?php endif; ?></div>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Level</th><th>Rule</th><th>Applies to</th><th>Approver</th><th>SLA</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:14px">No rules — reports route to each inspector's mapped approver.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r):
        $applies = array_filter([$r['report_type_code']?:null, $r['office_name']?:null, $r['client_name']?:null, $r['sbu']?:null]);
        $appr = $r['approver_kind']==='USER' ? '(person)' : ($r['approver_kind']==='ROLE' ? (ORG_ROLES[$r['approver_role']] ?? $r['approver_role']) : (IDEMS_APPROVER_KINDS[$r['approver_kind']] ?? $r['approver_kind'])); ?>
      <tr>
        <td><strong>L<?= (int)$r['level'] ?></strong></td>
        <td><?= e($r['name'] ?: '—') ?></td>
        <td class="muted"><?= $applies ? e(implode(' · ', $applies)) : 'Any' ?></td>
        <td><?= e($appr) ?></td>
        <td class="muted"><?= (int)$r['sla_hours'] ?>h</td>
        <td><?= $r['active'] ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-mut">Off</span>' ?></td>
        <td style="white-space:nowrap"><a class="btn small secondary" href="/idems-approval-rule-edit?id=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" action="/idems-approval-rules" style="display:inline" onsubmit="return confirm('Remove rule?')"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary">✕</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<script>(function(){var s=document.getElementById('akind');function u(){document.querySelectorAll('[data-akind]').forEach(function(e){e.style.display=e.getAttribute('data-akind')===s.value?'':'none';});}s.addEventListener('change',u);u();})();</script>
