<?php // Report instance detail — Phase 1 (header, references, lifecycle actions, audit) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <?= e($doc['irn']) ?></div>
<div class="master-head">
  <div><h1><?= e(T_DETAIL('report', $doc['irn'])) ?> <?= $doc['finalized'] ? '🔒' : '' ?></h1>
    <p class="sub" style="margin:2px 0 0"><span class="pill p-info"><?= e($doc['type_code']) ?></span> <?= e($doc['type_name'] ?: $doc['title']) ?> · <span class="pill <?= idems_status_pill($doc['status']) ?>"><?= e(lk_options_or('report_status', IDEMS_STATUS)[$doc['status']] ?? $doc['status']) ?></span></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php if (idems_can_edit_doc($doc)): ?><a class="btn secondary" href="/document-edit?id=<?= (int)$doc['id'] ?>">Edit header</a><?php endif; ?>
    <?php if (idems_can_edit_doc($doc) && !empty($hasSchema)): ?><a class="btn" href="/document-fill?id=<?= (int)$doc['id'] ?>">Fill report body</a><?php endif; ?>
    <?php if ((is_master() || can('idems.type.manage')) && empty($hasSchema)): ?><a class="btn secondary" href="/report-builder?type=<?= (int)$doc['report_type_id'] ?>">Design this form</a><?php endif; ?>
    <?php if (idems_can_edit_doc($doc) && in_array($doc['status'],['DRAFT','REJECTED'],true)): ?>
      <form method="post" action="/document-submit?id=<?= (int)$doc['id'] ?>" style="display:inline"><button class="btn" type="submit">Submit for review</button></form>
    <?php endif; ?>
    <?php
      // A report cannot be issued until somebody has approved it. Finalising is
      // irreversible — the document becomes immutable — so an unapproved report
      // that slips through cannot be taken back. The button says why it is off
      // rather than simply not being there.
      $appr = $approvals ?? [];
      $anyApproved = false; $anyPending = false;
      foreach ($appr as $a) {
        if (($a['status'] ?? '') === 'APPROVED') $anyApproved = true;
        if (in_array($a['status'] ?? '', ['PENDING', 'SENTBACK'], true)) $anyPending = true;
      }
      $canFinalize = $anyApproved && !$anyPending;
      $whyNot = !$appr ? 'No approver has been asked yet — submit it for approval first.'
              : ($anyPending ? 'Still waiting on an approver.' : 'Not approved.');
    ?>
    <?php if (!$doc['finalized'] && (is_master() || can('idems.finalize'))): ?>
      <?php if ($canFinalize): ?>
      <form method="post" action="/document-finalize?id=<?= (int)$doc['id'] ?>" style="display:inline" onsubmit="return confirm('Finalize &amp; issue this report? It becomes permanently locked (immutable).')"><button class="btn" type="submit">Finalize &amp; issue</button></form>
      <?php else: ?>
      <button class="btn" type="button" disabled title="<?= e($whyNot) ?>"
              style="opacity:.5;cursor:not-allowed">Finalize &amp; issue</button>
      <?php endif; ?>
    <?php endif; ?>
    <a class="btn secondary" href="/document-evidence?id=<?= (int)$doc['id'] ?>">🖼 Evidence</a>
    <a class="btn secondary" href="/document-review?id=<?= (int)$doc['id'] ?>">🔍 Document review</a>
    <?php if (!empty($hasSchema)): ?><a class="btn secondary" href="/document-smart?id=<?= (int)$doc['id'] ?>">💡 Suggested remarks</a><?php endif; ?>
    <?php if (in_array($doc['status'], ['APPROVED','ISSUED'], true) && $doc['type_code'] !== 'RN' && (is_master() || can('mod.idems.edit'))): ?>
      <form method="post" action="/document-release-note" style="display:inline"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="btn secondary" type="submit">📋 Draft Release Note</button></form>
    <?php endif; ?>
    <?php // §R1 — when a format (your company's, or this client's) is on file, that
          // IS the report: it prints in your exact layout. Make it the primary output
          // and drop the generic PDF to a plain fallback. ?>
    <?php $yourFmt = function_exists('idems_pick_template') ? idems_pick_template($doc) : null;
          $fmtLabel = $yourFmt ? ((int)($yourFmt['client_id'] ?? 0) ? 'client format' : 'company format') : ''; ?>
    <?php if ($yourFmt): ?>
      <a class="btn" href="/document-docx?id=<?= (int)$doc['id'] ?>">📄 Report — your format <span class="muted" style="font-weight:400">(<?= e($fmtLabel) ?>)</span></a>
    <?php endif; ?>
    <?php if (empty($doc['finalized'])): ?>
      <a class="btn <?= $yourFmt ? 'secondary' : '' ?>" href="/document-pdf?id=<?= (int)$doc['id'] ?>" target="_blank">📄 <?= $yourFmt ? 'Plain PDF' : 'Draft PDF (watermarked)' ?></a>
    <?php else: ?>
      <a class="btn secondary" href="/document-pdf?id=<?= (int)$doc['id'] ?>&copy=original" target="_blank">📄 <?= $yourFmt ? 'Plain PDF' : 'Original' ?></a>
      <?php if (!$yourFmt): ?>
      <a class="btn secondary" href="/document-pdf?id=<?= (int)$doc['id'] ?>&copy=duplicate" target="_blank">📄 Duplicate</a>
      <a class="btn secondary" href="/document-pdf?id=<?= (int)$doc['id'] ?>&copy=triplicate" target="_blank">📄 Triplicate</a>
      <?php endif; ?>
      <?php // Amend-and-reissue: an issued report cannot be edited, but it can be
            // revised — a new draft at Rev n+1, carrying this report's content, is
            // created and this one is kept unchanged as the history. ?>
      <?php if (empty($doc['revised_by_id']) && (is_master() || can('mod.idems.edit'))): ?>
        <form method="post" action="/document-revise" style="display:inline" onsubmit="return confirm('Create Rev <?= (int)($doc['rev'] ?? 0) + 1 ?> of this report? The original stays on file, unchanged.')"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="btn secondary" type="submit">♻️ Reissue as new revision</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php // ---- Revision lineage ------------------------------------------------ ?>
<?php if (!empty($doc['revises_id']) || !empty($doc['revised_by_id']) || (int)($doc['rev'] ?? 0) > 0): ?>
<div class="panel" style="padding:10px 14px;margin:0 0 12px;font-size:13.5px">
  <?php if ((int)($doc['rev'] ?? 0) > 0): ?><b>Revision <?= (int)$doc['rev'] ?></b><?php endif; ?>
  <?php if (!empty($doc['revises_id'])): $pv = ops_one("SELECT id, irn FROM report_docs WHERE id=?", [(int)$doc['revises_id']]); if ($pv): ?>
    · revises <a href="/document?id=<?= (int)$pv['id'] ?>"><?= e($pv['irn']) ?></a><?php endif; endif; ?>
  <?php if (!empty($doc['revised_by_id'])): $nx = ops_one("SELECT id, irn FROM report_docs WHERE id=?", [(int)$doc['revised_by_id']]); if ($nx): ?>
    · superseded by <a href="/document?id=<?= (int)$nx['id'] ?>"><?= e($nx['irn']) ?></a> — this is no longer the current revision<?php endif; endif; ?>
</div>
<?php endif; ?>

<?php // ---- Where this report stands, and the one next thing --------------- ?>
<?php
  $rSt   = $doc['status'] ?? 'DRAFT';
  $rFin  = !empty($doc['finalized']);
  $rBody = !empty($hasSchema);
  $rCanEdit = idems_can_edit_doc($doc);
?>
<div class="nowband">
  <?php if ($rFin || $rSt === 'ISSUED'): ?>
    <div class="step">Issued &amp; locked.</div>
    <p class="next">This <?= e(Tl('report')) ?> has gone to the <?= e(Tl('client')) ?> and can no longer be changed. You can still download the PDF or the client format above.</p>
  <?php elseif ($rSt === 'APPROVED'): ?>
    <div class="step">Approved — ready to issue.</div>
    <p class="next"><b>Next:</b> press <b>Finalize &amp; issue</b> above to send it and lock it. After that nothing on it can change.</p>
  <?php elseif ($rSt === 'REJECTED'): ?>
    <div class="step">Sent back for changes.</div>
    <p class="next"><b>Next:</b> read the reviewer's remark below, fix the body, and submit it again.</p>
    <?php if ($rCanEdit && $rBody): ?><div class="cta"><a class="btn small" href="/document-fill?id=<?= (int)$doc['id'] ?>">Fix the report body →</a></div><?php endif; ?>
  <?php elseif (in_array($rSt, ['SUBMITTED','UNDER_REVIEW'], true)): ?>
    <div class="step">Waiting for approval.</div>
    <p class="next">It has gone to the approver named below. Nothing more to do until they approve it or send it back.</p>
  <?php else: /* DRAFT */ ?>
    <div class="step">Still a draft.</div>
    <p class="next"><b>Next:</b> <?= $rBody ? 'fill in the report body, then submit it for review.' : 'this report type has no form designed yet — design it, then fill the body.' ?></p>
    <?php if ($rCanEdit && $rBody): ?><div class="cta"><a class="btn small" href="/document-fill?id=<?= (int)$doc['id'] ?>">Fill the report body →</a></div><?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($doc['finalized']): ?>
<div class="panel" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 7%,transparent)">
  <b style="color:var(--ok)">🔒 Finalized &amp; issued</b> — locked on <?= e($doc['finalized_at'] ? date('d M Y H:i', strtotime($doc['finalized_at'])) : '—') ?> by <?= e($doc['finalized_by']) ?>. This report is immutable.
</div>

<?php // "Verify it yourself" wins contracts; "trust us" does not. Print this
      // code and this address on the report and the client can check it is
      // genuine and unaltered without an account and without asking you. ?>
<?php if (function_exists('verify_code_for')): $vc = verify_code_for($doc); $vu = verify_url($doc);
        $vst = function_exists('chain_verify') ? chain_verify((int)$doc['id']) : ['ok'=>true,'entries'=>0]; ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Let the client check this themselves</h3></div>
  <p class="sub" style="margin-top:0">Put these two lines on the report. Anyone holding it can confirm it is genuine
    and unaltered — without an account, and without asking you.</p>
  <table class="dt"><tbody>
    <tr><th style="width:150px">Verification code</th>
      <td><code style="font-size:16px;letter-spacing:.06em"><?= e($vc) ?></code></td></tr>
    <tr><th>Where to check it</th><td><a href="<?= e($vu) ?>" target="_blank" rel="noopener"><?= e($vu) ?></a></td></tr>
    <tr><th>Evidence chain</th>
      <td><?= $vst['ok'] ? '<span class="pill p-ok">intact across ' . (int)$vst['entries'] . ' entries</span>'
            : '<span class="pill p-bad">' . count($vst['problems']) . ' problem(s)</span> — '
              . '<a href="/evidence-review?doc=' . (int)$doc['id'] . '">look at it</a>' ?></td></tr>
  </tbody></table>
  <small class="muted">The page shows the report number, when it was issued, who by, how much of the evidence was
    located on site, and whether anything has changed since. It shows no client name, no findings and no prices —
    a verification page that gave those away would breach the confidentiality the report is issued under.</small>
</div>
<?php endif; ?>
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
    <?php // Which format is actually in play. The questions on this report come
          // from the report type's designed form; the issued document comes from
          // whichever registered template best fits this client and office. Both
          // are stated, so "is our format being used?" is answered on the page
          // rather than guessed at.
          $tpl = idems_pick_template($doc); ?>
    <div><span class="k">Format in use</span>
      <?php if ($tpl): ?>
        <?= e($tpl['name'] ?: 'Template #' . (int)$tpl['id']) ?>
        <span class="pill p-info"><?= e($tpl['client_id'] ? Tl('client') . '’s own' : ($tpl['report_type_id'] ? 'by type' : ($tpl['office_id'] ? 'by ' . Tl('office') : 'default'))) ?></span>
        <a href="/report-templates" style="font-size:12px">manage</a>
      <?php else: ?>
        <span class="muted">Our standard layout</span>
        <span class="muted" style="font-size:12px">— no <a href="/report-templates">template</a> registered for this <?= e(Tl('client')) ?> or type</span>
      <?php endif; ?>
    </div>
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
    <div><span class="k">Result</span><?= e(lk_options_or('inspection_result', IDEMS_RESULTS)[$doc['result']] ?? '—') ?></div>
    <div><span class="k">Release</span><?= e(lk_options_or('release_status', IDEMS_RELEASE)[$doc['release_status']] ?? '—') ?></div>
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

<?php // ISO/IEC 17020 §6.2 — the instruments this report relied on. Naming them
      // here is what turns the equipment register from paperwork into evidence,
      // and it is what the finalise gate reads. ?>
<?php // The measuring-equipment register is an accreditation concern; a business
      // that is not an accredited inspection body or laboratory does not show it.
      if (function_exists('report_equipment') && (!function_exists('accredited_pack_on') || accredited_pack_on())):
        $req = report_equipment($doc['id']);
        $eqDate = report_equipment_date($doc);
        $eqBlock = report_equipment_block($doc);
        $canEq = !$doc['finalized'] && (can('mod.equipment.view') || is_master()); ?>
<div class="panel" id="equipment">
  <div class="ctitle" style="margin-top:0"><h3>📏 Measuring &amp; test equipment used <span class="muted">(<?= count($req) ?>)</span></h3>
    <a href="/equipment">Register →</a></div>
  <?php if ($eqBlock !== ''): ?>
    <div class="msg msg-error" style="margin:6px 0"><?= e($eqBlock) ?>
      This <?= e(Tl('report')) ?> <strong>will not issue</strong> until it is corrected.</div>
  <?php elseif ($req): ?>
    <div class="msg msg-ok" style="margin:6px 0">Every instrument named was within calibration on <?= e(fdate($eqDate)) ?>.</div>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px">None recorded. If a measurement was taken, name the instrument —
      an assessor will ask which one was used and for its certificate.</p>
  <?php endif; ?>
  <?php if ($req): ?>
  <table class="grid">
    <tr><th>Code</th><th>Instrument</th><th>Serial</th><th>Used on</th><th>Calibration</th><?php if ($canEq): ?><th></th><?php endif; ?></tr>
    <?php foreach ($req as $r): $why = equipment_block((int)$r['equipment_id'], $r['used_on'] ?: $eqDate); ?>
      <tr><td><strong><?= e($r['code']) ?></strong></td><td><?= e($r['name']) ?></td>
        <td><?= e($r['serial_no'] ?: '—') ?></td>
        <td><?= e($r['used_on'] ? fdate($r['used_on']) : fdate($eqDate)) ?></td>
        <td><?= $why === '' ? '<span class="pill p-ok">in calibration</span>'
                            : '<span class="pill p-bad" title="' . e($why) . '">not valid</span>' ?></td>
        <?php if ($canEq): ?>
        <td><form method="post" action="/report-equip-del" style="margin:0">
          <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn small secondary" type="submit">Remove</button></form></td>
        <?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if ($canEq): $avail = equipment_all(); ?>
  <form method="post" action="/report-equip-add" class="inline-add" style="margin-top:10px">
    <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
    <div class="ff"><label>Instrument</label>
      <select class="form-control searchable" name="equipment_id" required>
        <option value="">—</option>
        <?php foreach ($avail as $a): $bad = equipment_block($a['id'], $eqDate); ?>
          <option value="<?= (int)$a['id'] ?>"><?= e($a['code'] . ' — ' . $a['name']) ?><?= $bad ? ' (not valid on this date)' : '' ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Used on</label><input class="form-control" type="date" name="used_on" value="<?= e($eqDate) ?>"></div>
    <button class="btn small" type="submit">Add</button>
  </form>
  <?php endif; ?>
</div>
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

<details class="fold">
  <summary>Audit trail <span class="sub">every change to this <?= e(Tl('report')) ?>, with who and when (<?= count($audit) ?>)</span></summary>
  <div class="fold-body" style="padding-left:0;padding-right:0">
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
</details>
