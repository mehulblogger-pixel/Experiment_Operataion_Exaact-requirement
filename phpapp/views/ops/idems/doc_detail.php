<?php // Report instance detail — Phase 1 (header, references, lifecycle actions, audit) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents">Documents</a> › <?= e($doc['irn']) ?></div>
<div class="master-head">
  <div><h1><?= e($doc['irn']) ?> <?= $doc['finalized'] ? '🔒' : '' ?></h1>
    <p class="sub" style="margin:2px 0 0"><span class="pill p-info"><?= e($doc['type_code']) ?></span> <?= e($doc['type_name'] ?: $doc['title']) ?> · <span class="pill <?= idems_status_pill($doc['status']) ?>"><?= e(IDEMS_STATUS[$doc['status']] ?? $doc['status']) ?></span></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if (idems_can_edit_doc($doc)): ?><a class="btn secondary" href="/document-edit?id=<?= (int)$doc['id'] ?>">Edit header</a><?php endif; ?>
    <?php if (idems_can_edit_doc($doc) && !empty($hasSchema)): ?><a class="btn" href="/document-fill?id=<?= (int)$doc['id'] ?>">Fill report body</a><?php endif; ?>
    <?php if ((is_master() || can('idems.type.manage')) && empty($hasSchema)): ?><a class="btn secondary" href="/report-builder?type=<?= (int)$doc['report_type_id'] ?>">Design this form</a><?php endif; ?>
    <?php if (idems_can_edit_doc($doc) && in_array($doc['status'],['DRAFT','REJECTED'],true)): ?>
      <form method="post" action="/document-submit?id=<?= (int)$doc['id'] ?>" style="display:inline"><button class="btn" type="submit">Submit for review</button></form>
    <?php endif; ?>
    <?php if (!$doc['finalized'] && (is_master() || can('idems.finalize'))): ?>
      <form method="post" action="/document-finalize?id=<?= (int)$doc['id'] ?>" style="display:inline" onsubmit="return confirm('Finalize &amp; issue this report? It becomes permanently locked (immutable).')"><button class="btn" type="submit">Finalize &amp; issue</button></form>
    <?php endif; ?>
    <a class="btn secondary" href="/document-evidence?id=<?= (int)$doc['id'] ?>">🖼 Evidence</a>
    <a class="btn secondary" href="/document-review?id=<?= (int)$doc['id'] ?>">🔍 Document review</a>
    <?php if (!empty($hasSchema)): ?><a class="btn secondary" href="/document-smart?id=<?= (int)$doc['id'] ?>">💡 Suggested remarks</a><?php endif; ?>
    <?php if (in_array($doc['status'], ['APPROVED','ISSUED'], true) && $doc['type_code'] !== 'RN' && (is_master() || can('mod.idems.edit'))): ?>
      <form method="post" action="/document-release-note" style="display:inline"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="btn secondary" type="submit">📋 Draft Release Note</button></form>
    <?php endif; ?>
    <a class="btn secondary" href="/document-pdf?id=<?= (int)$doc['id'] ?>" target="_blank">📄 PDF</a>
    <?php if (function_exists('idems_pick_template') && idems_pick_template($doc)): ?><a class="btn secondary" href="/document-docx?id=<?= (int)$doc['id'] ?>">📝 Client format</a><?php endif; ?>
  </div>
</div>

<?php if ($doc['finalized']): ?>
<div class="panel" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 7%,transparent)">
  <b style="color:var(--ok)">🔒 Finalized &amp; issued</b> — locked on <?= e($doc['finalized_at'] ? date('d M Y H:i', strtotime($doc['finalized_at'])) : '—') ?> by <?= e($doc['finalized_by']) ?>. This report is immutable.
</div>
<?php endif; ?>

<?php if (!empty($approvals)): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Approval chain</h3></div>
  <div class="appr-steps">
    <?php foreach ($approvals as $a):
      $nm = trim(($a['first_name']??'').' '.($a['last_name']??'')) ?: ($a['username'] ?? '');
      $target = $nm ?: ($a['approver_role'] ? (ORG_ROLES[$a['approver_role']] ?? $a['approver_role']) : 'Any approver');
      $sp = ['PENDING'=>'p-warn','APPROVED'=>'p-ok','REJECTED'=>'p-bad','SENTBACK'=>'p-bad','DELEGATED'=>'p-info'][$a['status']] ?? 'p-mut'; ?>
      <div class="appr-step">
        <span class="pill <?= $sp ?>">L<?= (int)$a['level'] ?> · <?= e(IDEMS_APPR_STATUS[$a['status']] ?? $a['status']) ?></span>
        <strong><?= e($target) ?></strong>
        <?php if ($a['acted_by']): ?><span class="muted">— <?= e($a['acted_by']) ?><?= $a['acted_at']?' · '.e(date('d M H:i', strtotime($a['acted_at']))):'' ?></span><?php endif; ?>
        <?php if ($a['remarks']): ?><div class="muted" style="font-size:12px">“<?= e($a['remarks']) ?>”</div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($canAct) && !empty($curStep)): ?>
    <form method="post" action="/document-approve" class="appr-act" style="margin-top:10px">
      <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
      <input class="form-control" name="remarks" placeholder="Remarks (required to reject / send back)" style="margin-bottom:8px">
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <button class="btn" type="submit" name="decision" value="approve">✓ Approve</button>
        <button class="btn secondary" type="submit" name="decision" value="sendback">↩ Send back</button>
        <button class="btn secondary" type="submit" name="decision" value="reject">✕ Reject</button>
        <span style="margin-left:auto;display:flex;gap:6px;align-items:center">
          <select class="form-control" name="delegate_to" style="max-width:200px"><option value="">Delegate to…</option>
            <?php foreach (($delegateUsers ?? []) as $u): $un=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>"><?= e($un) ?></option><?php endforeach; ?>
          </select>
          <button class="btn secondary" type="submit" name="decision" value="delegate">Delegate</button>
        </span>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php $srcRef = json_decode($doc['data'] ?: '[]', true); if (!empty($srcRef['source_irn'])): ?>
<div class="panel" style="border:1px solid var(--brand)">
  <b>📋 Drafted from inspection report</b> —
  <?php $srcId = (int)($srcRef['source_report_id'] ?? 0); ?>
  <?= $srcId ? '<a href="/document?id='.$srcId.'">'.e($srcRef['source_irn']).'</a>' : e($srcRef['source_irn']) ?>.
  Wording follows that report's findings; edit before issuing.
</div>
<?php endif; ?>

<div class="panel">
  <div class="kv-grid">
    <div><span class="k">IRN</span><strong><?= e($doc['irn']) ?></strong></div>
    <div><span class="k">Report type</span><?= e($doc['type_code']) ?> — <?= e($doc['type_name'] ?: '—') ?></div>
    <div><span class="k">Title</span><?= e($doc['title'] ?: '—') ?></div>
    <div><span class="k">Client</span><?= e($doc['client_disp'] ?: $doc['client_name'] ?: '—') ?></div>
    <div><span class="k">Vendor / mfr</span><?= e($doc['vendor_disp'] ?: $doc['vendor_name'] ?: '—') ?></div>
    <div><span class="k">Project</span><?= e(trim(($doc['project_code'] ?? '').' '.($doc['project_name'] ?? '')) ?: '—') ?></div>
    <div><span class="k">Office</span><?= e($doc['office_name'] ?: '—') ?></div>
    <div><span class="k">Inspector</span><?= e($doc['inspector_name'] ?: '—') ?></div>
    <div><span class="k">Approver</span><?= $approver ? e(trim(($approver['first_name']??'').' '.($approver['last_name']??'')) ?: $approver['username']) : '—' ?></div>
    <div><span class="k">Inspection date</span><?= e($doc['inspection_date'] ?: '—') ?></div>
    <div><span class="k">Issue date</span><?= e($doc['issue_date'] ?: '—') ?></div>
    <div><span class="k">PO</span><?= e($doc['po_ref'] ?: '—') ?></div>
    <div><span class="k">Drawing</span><?= e(trim(($doc['drawing_no'] ?? '').' '.($doc['drawing_rev']? 'Rev '.$doc['drawing_rev']:'')) ?: '—') ?></div>
    <div><span class="k">QAP rev</span><?= e($doc['qap_rev'] ?: '—') ?></div>
    <div><span class="k">Standards</span><?= e($doc['standards'] ?: '—') ?></div>
    <div><span class="k">Material / product</span><?= e(trim(($doc['material_grade'] ?? '').' '.($doc['product_category'] ?? '')) ?: '—') ?></div>
    <div><span class="k">Location</span><?= e($doc['location'] ?: '—') ?></div>
    <div><span class="k">Result</span><?= e(IDEMS_RESULTS[$doc['result']] ?? '—') ?></div>
    <div><span class="k">Release</span><?= e(IDEMS_RELEASE[$doc['release_status']] ?? '—') ?></div>
  </div>
  <?php if ($doc['remarks']): ?><div class="kv-wide" style="margin-top:8px"><span class="k">Remarks</span><?= nl2br(e($doc['remarks'])) ?></div><?php endif; ?>
</div>

<?php
  // ---- Report body (designed form values) ----
  if (!empty($hasSchema)):
    $bySec = []; foreach ($fields as $f) $bySec[(int)$f['section_id']][] = $f;
    $filesBy = []; foreach ($files as $fl) $filesBy[$fl['field_key']][] = $fl;
    $showVal = function($f) use ($data, $filesBy) {
      $k=$f['fkey']; $v=$data[$k] ?? '';
      if (in_array($f['ftype'],['photo','file','signature'],true)) {
        if (empty($filesBy[$k])) return '<span class="muted">—</span>';
        $h=''; foreach ($filesBy[$k] as $fl){ if(strpos($fl['mime'],'image/')===0) $h.='<a href="/report-file?id='.(int)$fl['id'].'" target="_blank"><img src="/report-file?id='.(int)$fl['id'].'" class="ev-th"></a> '; else $h.='<a class="pill p-info" href="/report-file?id='.(int)$fl['id'].'" target="_blank">📎 '.e($fl['file_name']).'</a> '; }
        return $h;
      }
      if ($f['ftype']==='table') { if(!is_array($v)||!$v) return '<span class="muted">—</span>'; $cols=idems_table_cols($f); $h='<table class="dt" style="margin-top:4px"><thead><tr>'; foreach($cols as $cl) $h.='<th>'.e($cl).'</th>'; $h.='</tr></thead><tbody>'; foreach($v as $r){ $r=(array)$r; $h.='<tr>'; foreach($cols as $ck=>$cl) $h.='<td>'.e($r[$ck]??'').'</td>'; $h.='</tr>'; } return $h.'</tbody></table>'; }
      if ($f['ftype']==='multiselect' && is_array($v)) { $o=idems_field_options($f); return e(implode(', ', array_map(fn($x)=>$o[$x]??$x,$v))) ?: '<span class="muted">—</span>'; }
      if (in_array($f['ftype'],['select','radio'],true)) { $o=idems_field_options($f); return e($o[$v] ?? $v) ?: '<span class="muted">—</span>'; }
      if ($f['ftype']==='checkbox') return ($v==='1'||$v===1)?'Yes':'No';
      return $v!=='' ? nl2br(e(is_array($v)?'':$v)) : '<span class="muted">—</span>';
    };
?>
<?php foreach ($sections as $s): if (empty($bySec[(int)$s['id']])) continue; ?>
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3><?= e($s['title']) ?></h3></div>
    <div class="kv-grid">
      <?php foreach ($bySec[(int)$s['id']] as $f): if(in_array($f['ftype'],['heading','note'],true)) continue; ?>
        <div class="<?= (int)$f['col_span']===2 || in_array($f['ftype'],['table','textarea','photo','file','signature'],true) ? 'kv-wide' : '' ?>"><span class="k"><?= e($f['label']) ?></span><?= $showVal($f) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!empty($bySec[0])): ?>
  <div class="panel"><div class="kv-grid"><?php foreach ($bySec[0] as $f): if(in_array($f['ftype'],['heading','note'],true)) continue; ?><div class="<?= (int)$f['col_span']===2?'kv-wide':'' ?>"><span class="k"><?= e($f['label']) ?></span><?= $showVal($f) ?></div><?php endforeach; ?></div></div>
<?php endif; ?>
<style>.ev-th{width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}</style>
<?php else: ?>
<p class="muted" style="margin:10px 2px">No form is designed for this report type yet<?= (is_master()||can('idems.type.manage')) ? ' — use "Design this form" above to add sections &amp; fields.' : '.' ?></p>
<?php endif; ?>

<?php if (is_master() || can('idems.timestamp.edit')): ?>
<div class="panel" style="border:1px dashed var(--line)">
  <div class="ctitle" style="margin-top:0"><h3>🔧 Adjust dates <span class="muted" style="font-weight:400">— Branch Application Manager only</span></h3></div>
  <p class="muted" style="margin:0 0 8px">System dates are normally locked. A change here is recorded permanently (old &amp; new value, who, when, reason).</p>
  <form method="post" action="/document-timestamp" style="display:flex;gap:6px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
    <div class="ff" style="margin:0"><label>Field</label>
      <select class="form-control" name="field"><option value="inspection_date">Inspection date</option><option value="issue_date">Issue date</option></select></div>
    <div class="ff" style="margin:0"><label>New value</label><input class="form-control" type="date" name="value"></div>
    <div class="ff" style="margin:0;flex:1;min-width:180px"><label>Reason (required)</label><input class="form-control" name="reason" required></div>
    <button class="btn" type="submit">Apply &amp; log</button>
  </form>
</div>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:10px 14px 0"><h3>Audit trail</h3></div>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>When</th><th>Action</th><th>By</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($audit as $a): ?>
      <tr>
        <td class="muted"><?= e($a['created_at'] ? date('d M Y H:i', strtotime($a['created_at'])) : '—') ?></td>
        <td><span class="pill p-mut"><?= e($a['action']) ?></span></td>
        <td><?= e($a['username']) ?></td>
        <td class="muted"><?= e(trim(($a['field']?$a['field'].': ':'').($a['old_value']?$a['old_value'].' → ':'').($a['new_value'] ?? '')) ?: ($a['reason'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
