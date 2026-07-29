<?php
  $selSbus = ($ins && !empty($ins['sbus'])) ? explode(',', $ins['sbus']) : [];
  $selSkills = ($ins && !empty($ins['skill_ids'])) ? explode(',', $ins['skill_ids']) : [];
  $curTrade = $ins['trade_id'] ?? '';
  $trades = lk_type('trade') ? lk_root_values(lk_type('trade')['id']) : [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › <a href="/m/inspectors"><?= e(TP('engineer')) ?></a> › <?= $ins ? 'Edit' : 'Add' ?></div>
<div class="master-head">
  <div><h1><?= $ins ? 'Edit — ' . e($ins['name']) : 'Add ' . Tl('engineer') ?></h1></div>
  <a class="btn secondary" href="/m/inspectors">← Back to <?= e(THP('engineer')) ?></a>
</div>

<form method="post" action="/m/inspectors/<?= $ins ? 'edit?id=' . (int)$ins['id'] : 'new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>First name *</label><input class="form-control" name="first_name" required value="<?= e($ins['first_name'] ?? '') ?>"></div>
    <div class="ff"><label>Middle name</label><input class="form-control" name="middle_name" value="<?= e($ins['middle_name'] ?? '') ?>"></div>
    <div class="ff"><label>Last name</label><input class="form-control" name="last_name" value="<?= e($ins['last_name'] ?? '') ?>"></div>
    <div class="ff"><label>Employee code</label><input class="form-control" id="emp_code" name="emp_code" value="<?= e($ins['emp_code'] ?? '') ?>" placeholder="auto">
      <small class="muted" id="emp_code_hint"><?= $ins ? 'Leave as-is to keep this code.' : 'Leave blank to auto-generate. Sub-contractors get <b>SC-###</b>, freelancers <b>FL-###</b>, staff <b>EMP##</b>.' ?></small></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email" value="<?= e($ins['email'] ?? '') ?>"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" name="mobile" value="<?= e($ins['mobile'] ?? '') ?>"></div>
    <div class="ff"><label>Designation</label>
      <select class="form-control searchable" name="designation"><option value="">—</option>
        <?php foreach (lk_options_or('designation', DESIGNATIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ins['designation'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Engineer type</label>
      <select class="form-control" name="staff_kind"><?php foreach (['ASSET'=>'Own employee','FREELANCER'=>'Freelancer','SUBCON'=>'Sub-contractor'] as $k=>$v): ?><option value="<?= $k ?>" <?= (($ins['staff_kind'] ?? 'ASSET')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Trade / discipline</label>
      <select class="form-control searchable" id="trade_sel" name="trade_id"><option value="">—</option>
        <?php foreach ($trades as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (string)$curTrade===(string)$t['id']?'selected':'' ?>><?= e($t['label']) ?></option><?php endforeach; ?>
      </select><small class="muted">Manage under <a href="/lookup?key=trade">Trade</a> / <a href="/lookup?key=skill">Skill</a>.</small></div>
    <div class="ff"><label>Status</label>
      <select class="form-control" name="status"><?php foreach (['ACTIVE'=>'Active','INACTIVE'=>'Inactive'] as $k=>$v): ?><option value="<?= $k ?>" <?= (($ins['status'] ?? 'ACTIVE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Posted office <span class="muted">— shows on that office's availability board</span></label>
      <select class="form-control searchable" name="home_office_id"><option value="">—</option>
        <?php foreach (($offices ?? []) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ((int)($ins['home_office_id'] ?? 0)===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Weekly working days <span class="muted">— or leave at 6 to inherit the designation/office norm</span></label>
      <select class="form-control" name="weekly_working_days"><?php foreach (['6'=>'6 days (Mon–Sat) / inherit norm','5.5'=>'5.5 days (alternate Sat off)','5'=>'5 days (Mon–Fri)'] as $k=>$v): ?><option value="<?= $k ?>" <?= ((string)($ins['weekly_working_days'] ?? '6')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Reporting manager <span class="muted">— for approvals & hierarchy</span></label>
      <select class="form-control searchable" name="reports_to_id"><option value="">—</option>
        <?php foreach (($managers ?? []) as $m): $nm=trim(($m['first_name']??'').' '.($m['last_name']??'')) ?: $m['username']; ?><option value="<?= (int)$m['id'] ?>" <?= ((int)($ins['reports_to_id'] ?? 0)===(int)$m['id'])?'selected':'' ?>><?= e($nm) ?> · <?= e(ORG_ROLES[$m['role']] ?? $m['role']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if (can_see_salary()): ?>
    <div class="ff"><label>Annual CTC (<?= e(cur_sym()) ?>) <span class="muted">— cost split across <?= e(Tlp("sbu")) ?></span></label><input class="form-control" type="number" step="0.01" name="salary_ctc" value="<?= e($ins['salary_ctc'] ?? '') ?>"></div>
    <div class="ff"><label>Hiring agency <span class="muted">— if engaged via an external agency</span></label><input class="form-control" name="agency_name" value="<?= e($ins['agency_name'] ?? '') ?>" placeholder="e.g. Patel Manpower"></div>
    <div class="ff"><label>Agency hiring cost (<?= e(cur_sym()) ?>/yr) <span class="muted">— extra cost paid to the agency</span></label><input class="form-control" type="number" step="0.01" name="agency_cost" value="<?= e($ins['agency_cost'] ?? '') ?>"></div>
    <?php endif; ?>

    <div class="ff ff-wide"><label><?= e(TP("sbu")) ?> (multi — monthly cost is distributed across these)</label>
      <div class="checkgrid">
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="sbus[]" value="<?= e($k) ?>" <?= in_array($k, $selSbus, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div></div>

    <div class="ff ff-wide"><label>Skills (from the chosen trade)</label>
      <div class="skill-box" id="skills_box"><span class="muted">Pick a trade to see its skills.</span></div>
      <small class="muted">Skills come from the Trade you select. Add more under <a href="/lookup?key=skill">Skill</a>.</small></div>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $ins ? 'Save ' . Tl('engineer') : 'Add ' . Tl('engineer') ?></button>
    <a class="btn secondary" href="/m/inspectors">Cancel</a>
  </div>
</form>

<?php if ($ins): ?>
<div class="panel">
  <h3 class="tab-sub">Digital signature <span class="muted">— added automatically to this <?= e(Tl('engineer')) ?>'s reports (IDEMS)</span></h3>
  <?php if (!empty($ins['signature'])): ?><div style="margin-bottom:8px"><img src="<?= e($ins['signature']) ?>" alt="signature" style="max-width:240px;border:1px solid var(--line);border-radius:8px;background:#fff"></div><?php endif; ?>
  <form method="post" action="/m/inspectors/edit?id=<?= (int)$ins['id'] ?>" enctype="multipart/form-data">
    <input type="hidden" name="_do" value="signature">
    <canvas id="inspSig" width="420" height="130" style="border:1px solid var(--line);border-radius:8px;background:#fff;touch-action:none;max-width:100%"></canvas>
    <input type="hidden" name="signature" id="inspSigV">
    <div style="margin:6px 0;display:flex;gap:6px;align-items:center"><button type="button" class="btn small secondary" onclick="inspSigClear()">Clear</button>
      <span class="muted">or upload:</span> <input type="file" name="sigfile" accept="image/*"></div>
    <button class="btn small" type="submit">Save signature</button>
    <?php if (!empty($ins['signature'])): ?><button class="btn small secondary" type="submit" name="_clear" value="1">Remove</button><?php endif; ?>
  </form>
  <script>(function(){var c=document.getElementById('inspSig'),x=c.getContext('2d'),d=false,dy=false;x.lineWidth=2.2;x.lineCap='round';function p(e){var r=c.getBoundingClientRect();var t=e.touches?e.touches[0]:e;return{x:(t.clientX-r.left)*(c.width/r.width),y:(t.clientY-r.top)*(c.height/r.height)};}function s(e){d=true;var q=p(e);x.beginPath();x.moveTo(q.x,q.y);e.preventDefault();}function m(e){if(!d)return;var q=p(e);x.lineTo(q.x,q.y);x.stroke();dy=true;e.preventDefault();}function en(){d=false;if(dy)document.getElementById('inspSigV').value=c.toDataURL('image/png');}c.addEventListener('mousedown',s);c.addEventListener('mousemove',m);window.addEventListener('mouseup',en);c.addEventListener('touchstart',s);c.addEventListener('touchmove',m);c.addEventListener('touchend',en);window.inspSigClear=function(){x.clearRect(0,0,c.width,c.height);document.getElementById('inspSigV').value='';};})();</script>
</div>

<div class="panel">
  <h3 class="tab-sub">Certifications &amp; validity</h3>
  <p class="sub">The system e-mails the <?= e(Tl('engineer')) ?> and the QA/QC nominee when a certificate is within a month of expiry. Once the hard copy is received, update the validity date here.
    Tick <strong>Required</strong> on the ones this person may not work without — ISO/IEC 17020 §6.1. A required certificate that has lapsed
    <strong>stops them being allocated</strong>; a manager can still allow it, but must say why, and the reason is kept on the <?= e(Tl('job')) ?>.</p>
  <table class="grid">
    <tr><th>Certificate</th><th>Number</th><th>Issued</th><th>Valid to</th><th>Status</th><th>Required?</th><th>Actions</th></tr>
    <?php foreach ($certs as $c): $days = $c['valid_to'] ? (int)round((strtotime($c['valid_to']) - time())/86400) : null; ?>
    <tr>
      <td><strong><?= e($c['name']) ?></strong></td>
      <td><?= e($c['number'] ?: '—') ?></td>
      <td><?= e($c['issued_date'] ?: '—') ?></td>
      <td><?= e($c['valid_to'] ?: '—') ?></td>
      <td><?php if ($days===null): ?>—<?php elseif ($days<0): ?><span class="badge RED">Expired</span><?php elseif ($days<=30): ?><span class="badge AMBER"><?= $days ?>d left</span><?php else: ?><span class="badge GREEN">Valid</span><?php endif; ?></td>
      <td><?= !empty($c['is_mandatory'])
            ? '<span class="pill ' . ($days !== null && $days < 0 ? 'p-bad' : 'p-info') . '">required</span>'
            : '<span class="muted">optional</span>' ?></td>
      <td class="row-actions">
        <form method="post" action="/m/inspectors/edit?id=<?= (int)$ins['id'] ?>" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="_do" value="cert_update"><input type="hidden" name="cert_id" value="<?= (int)$c['id'] ?>">
          <input class="form-control" style="width:100px" name="cert_number" value="<?= e($c['number']) ?>" placeholder="No.">
          <input class="form-control" style="width:150px" type="date" name="cert_valid_to" value="<?= e($c['valid_to']) ?>">
          <label class="chk" style="white-space:nowrap"><input type="checkbox" name="cert_mandatory" value="1" <?= !empty($c['is_mandatory'])?'checked':'' ?>> Required</label>
          <button class="btn small" type="submit">Update</button>
        </form>
        <form method="post" action="/m/inspectors/edit?id=<?= (int)$ins['id'] ?>" onsubmit="return confirm('Remove this certificate?')">
          <input type="hidden" name="_do" value="cert_del"><input type="hidden" name="cert_id" value="<?= (int)$c['id'] ?>">
          <button class="btn small danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$certs): ?><tr><td colspan="7">No certificates recorded.</td></tr><?php endif; ?>
  </table>
  <h3 class="tab-sub">Add a certificate</h3>
  <form method="post" action="/m/inspectors/edit?id=<?= (int)$ins['id'] ?>" class="inline-add">
    <input type="hidden" name="_do" value="cert_add">
    <div class="ff"><label>Certificate name *</label><input class="form-control" name="cert_name" required placeholder="e.g. CSWIP 3.1"></div>
    <div class="ff"><label>Number</label><input class="form-control" name="cert_number"></div>
    <div class="ff"><label>Issued date</label><input class="form-control" type="date" name="cert_issued"></div>
    <div class="ff"><label>Valid to</label><input class="form-control" type="date" name="cert_valid_to"></div>
    <div class="ff"><label>Required for work?</label>
      <label class="chk"><input type="checkbox" name="cert_mandatory" value="1"> They may not be allocated without it</label></div>
    <button class="btn small" type="submit">Add certificate</button>
  </form>
</div>
<?php endif; ?>

<?php if ($ins && is_master()): ?>
<div class="panel" style="border:1px solid #d9b38c;background:#fffaf3">
  <h3 class="tab-sub">Allowances &amp; rates <span class="muted">— Super Admin only. Not visible to anyone else.</span></h3>
  <p class="sub">Tick what this <?= e(Tl('engineer')) ?> is entitled to claim on the monthly voucher, and set their personal rate where it differs from the default. Blank rate = use the master default.</p>
  <form method="post" action="/m/inspectors/edit?id=<?= (int)$ins['id'] ?>">
    <input type="hidden" name="_do" value="allow_save">

    <h4 class="tab-sub" style="margin-top:6px">Travel modes (per-km)</h4>
    <table class="grid">
      <tr><th>Allowed</th><th>Mode</th><th>Basis</th><th>Default</th><th>This <?= e(Tl('engineer')) ?>'s rate (<?= e(cur_sym()) ?>/km)</th></tr>
      <?php foreach ($travelModes as $m): $a = $allowMap['MODE'][$m['code']] ?? null; ?>
      <tr>
        <td><label class="chk"><input type="checkbox" name="allow_mode[<?= e($m['code']) ?>]" value="1" <?= ($a && $a['allowed'])?'checked':'' ?>></label></td>
        <td><strong><?= e($m['label']) ?></strong> <span class="muted">(<?= e($m['code']) ?>)</span></td>
        <td><?= e(lk_options_or('travel_basis', TRAVEL_BASIS)[$m['basis']] ?? $m['basis']) ?></td>
        <td><?= $m['basis']==='PER_KM' ? cur_sym().e($m['default_rate']).'/km' : '—' ?></td>
        <td><?php if ($m['basis']==='PER_KM'): ?><input class="form-control" style="width:130px" type="number" step="0.01" name="mode_rate[<?= e($m['code']) ?>]" value="<?= e($a['rate'] ?? '') ?>" placeholder="<?= e($m['default_rate']) ?>"><?php else: ?><span class="muted">actual bill</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <h4 class="tab-sub" style="margin-top:14px">Expense heads</h4>
    <table class="grid">
      <tr><th>Allowed</th><th>Head</th><th>Type</th><th>Override amount/rate (<?= e(cur_sym()) ?>)</th></tr>
      <?php foreach ($expHeads as $h): $a = $allowMap['HEAD'][$h['code']] ?? null; ?>
      <tr>
        <td><label class="chk"><input type="checkbox" name="allow_head[<?= e($h['code']) ?>]" value="1" <?= ($a && $a['allowed'])?'checked':'' ?>></label></td>
        <td><strong><?= e($h['label']) ?></strong> <span class="muted">(<?= e($h['code']) ?>)</span></td>
        <td><?= e(lk_options_or('expense_head_type', EXP_HEAD_TYPES)[$h['head_type']] ?? $h['head_type']) ?></td>
        <td><?php if ($h['head_type']!=='BILL'): ?><input class="form-control" style="width:130px" type="number" step="0.01" name="head_rate[<?= e($h['code']) ?>]" value="<?= e($a['rate'] ?? '') ?>" placeholder="<?= e($h['default_rate']) ?>"><?php else: ?><span class="muted">actual bill</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div style="margin-top:12px"><button class="btn" type="submit">Save allowances &amp; rates</button></div>
  </form>
</div>
<?php endif; ?>

<script>
window.SKILLS = <?= json_encode(skills_by_trade()) ?>;
window.SKILLS_SELECTED = <?= json_encode(array_map('intval', $selSkills)) ?>;
<?php if (!$ins): ?>
(function(){
  var kindSel=document.querySelector('select[name="staff_kind"]'), code=document.getElementById('emp_code'), hint=document.getElementById('emp_code_hint');
  var PFX={ASSET:'EMP##',SUBCON:'SC-###',FREELANCER:'FL-###'};
  function sync(){ if(!hint) return; var k=kindSel?kindSel.value:'ASSET';
    hint.innerHTML = code && code.value.trim() ? 'Using the code you typed.' : ('Blank → auto <b>'+(PFX[k]||'EMP##')+'</b> on save.'); }
  if(kindSel) kindSel.addEventListener('change', sync);
  if(code) code.addEventListener('input', sync);
  sync();
})();
<?php endif; ?>
</script>
