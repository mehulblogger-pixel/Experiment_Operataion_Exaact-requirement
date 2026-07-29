<?php
  // Create / edit a report instance. IRN is generated automatically on create.
  // $pre carries everything already known from the chosen call / job.
  $pre = $pre ?? [];
  $v = function($field, $preKey = null) use ($doc, $pre) {
      if ($doc) return $doc[$field] ?? '';
      $preKey = $preKey ?: $field;
      return $pre[$preKey] ?? '';
  };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <?= $doc ? e($doc['irn']) : 'New report' ?></div>
<div class="master-head"><div><h1><?= e($doc ? ucfirst(T_EDIT('report')) : ucfirst(T_NEW('report'))) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= $doc ? 'IRN '.e($doc['irn']).' — the IRN never changes.' : 'The IRN (Inspection Reference Number) is generated automatically when you save.' ?></p></div></div>

<?php if (!$doc): ?>
<form method="get" action="/document-new" class="panel" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <div class="ff" style="margin:0;flex:1;min-width:240px"><label>Start from a <?= e(Tl('call')) ?> <span class="muted">— everything known is filled in for you</span></label>
    <select class="form-control searchable" name="call" onchange="this.form.submit()"><option value="">— select a call —</option>
      <?php foreach (($calls ?? []) as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)($_GET['call'] ?? '')===(string)$c['id']?'selected':'' ?>><?= e($c['call_code']) ?><?= $c['client_nm'] ? ' · '.e($c['client_nm']) : '' ?></option><?php endforeach; ?>
    </select></div>
  <?php if (!empty($_GET['job'])): ?><input type="hidden" name="job" value="<?= (int)$_GET['job'] ?>"><?php endif; ?>
  <button class="btn small secondary" type="submit">Load</button>
</form>
<?php if ($pre): ?>
<div class="panel" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 6%,transparent)">
  <b style="color:var(--ok)">✓ Prefilled from <?= e($pre['call_code'] ?? $pre['job_code'] ?? 'the call') ?></b>
  <span class="muted">— client, vendor, PO, site, product, <?= e(Tl("sbu")) ?>, office and dates have been carried across. Change anything below if needed.</span>
</div>
<?php endif; ?>
<?php endif; ?>

<form method="post" action="<?= $doc ? '/document-edit?id='.(int)$doc['id'] : '/document-new' ?>" class="panel">
  <input type="hidden" name="call_id" value="<?= (int)$v('call_id') ?>">
  <input type="hidden" name="job_id" value="<?= (int)$v('job_id') ?>">
  <h3 class="tab-sub" style="margin-top:0">Report</h3>
  <div class="form-grid">
    <?php
      $narrowed = isset($allTypes) && count($types) < count($allTypes);
      $freq = $pre['reporting_frequency'] ?? '';
      $freqLbl = $freq !== '' ? (lk_options_or('reporting_frequency', REPORT_FREQ)[$freq] ?? $freq) : '';
      if ($freq === 'CUSTOM' && !empty($pre['report_custom_days'])) $freqLbl .= ' (every ' . (int)$pre['report_custom_days'] . ' days)';
    ?>
    <div class="ff"><label>Report type *
      <?php if ($narrowed): ?><span class="muted">— limited to what this <?= e(Tl('job')) ?> owes</span><?php endif; ?></label>
      <select class="form-control searchable" name="report_type_id" required><option value="">— select —</option>
        <?php // §v — arriving from a deliverable on a job ("Write it") names the
              // format in the URL, so the engineer lands on the right one rather
              // than picking it out of a list they were meant to be narrowed to.
              $wantType = trim((string)($_GET['type'] ?? '')); ?>
        <?php foreach ($types as $t): $selT = $doc ? ((int)$doc['report_type_id'] === (int)$t['id']) : ($wantType !== '' && $t['code'] === $wantType); ?>
          <option value="<?= (int)$t['id'] ?>" <?= $selT?'selected':'' ?>><?= e($t['code']) ?> — <?= e($t['name']) ?></option><?php endforeach; ?>
      </select>
      <?php if ($freqLbl !== ''): ?>
        <small class="muted">Reporting frequency on this <?= e(Tl('job')) ?>: <b><?= e($freqLbl) ?></b></small>
      <?php endif; ?>
      <?php if ($narrowed): ?>
        <small class="muted">Deliverables chosen at allocation: <?= e(implode(', ', $pre['deliverables'] ?? [])) ?>.
          <a href="#" onclick="var s=this.closest('.ff').querySelector('select');
             <?php foreach ($allTypes as $t): if (in_array($t['code'], $types === $allTypes ? [] : array_column($types,'code'), true)) continue; ?>
             s.add(new Option(<?= json_encode($t['code'] . ' — ' . $t['name'] . ' (not allocated)') ?>, '<?= (int)$t['id'] ?>'));
             <?php endforeach; ?>
             this.style.display='none';return false;">Need a different one?</a></small>
      <?php endif; ?></div>
    <div class="ff"><label>Title / subject</label><input class="form-control" name="title" value="<?= e($v('title')) ?>" placeholder="defaults to the report type name"></div>
    <div class="ff"><label>Inspection date</label><input class="form-control" type="date" name="inspection_date" value="<?= e($doc ? ($doc['inspection_date'] ?? '') : ($pre['inspection_date'] ?? date('Y-m-d'))) ?>"></div>
    <div class="ff"><label>Office / branch</label>
      <select class="form-control searchable" name="office_id"><option value="">— your office —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= ((int)$v('office_id')===(int)$o['id'])?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label><?= e(T("sbu")) ?></label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $sk=>$sv): ?><option value="<?= e($sk) ?>" <?= ((string)$v('sbu')===(string)$sk)?'selected':'' ?>><?= e($sv) ?></option><?php endforeach; ?>
      </select></div>
  </div>

  <h3 class="tab-sub">Parties &amp; references <span class="muted">— structured entry (Part 18)</span></h3>
  <div class="form-grid">
    <div class="ff"><label>Client</label>
      <select class="form-control searchable" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)$v('client_id')===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Vendor / manufacturer</label>
      <select class="form-control searchable" name="vendor_id"><option value="">—</option>
        <?php foreach ($vendors as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)$v('vendor_id')===(int)$c['id'])?'selected':'' ?>><?= e($c['nm']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Project code <span class="muted">(used in the IRN)</span></label><input class="form-control" name="project_code" value="<?= e($doc['project_code'] ?? '') ?>" placeholder="e.g. P001"></div>
    <div class="ff"><label>Project name</label><input class="form-control" name="project_name" value="<?= e($doc['project_name'] ?? '') ?>"></div>
    <div class="ff"><label>Purchase order</label><input class="form-control" name="po_ref" value="<?= e($v('po_ref')) ?>"></div>
    <div class="ff"><label>Drawing no.</label><input class="form-control" name="drawing_no" value="<?= e($doc['drawing_no'] ?? '') ?>"></div>
    <div class="ff"><label>Drawing rev.</label><input class="form-control" name="drawing_rev" value="<?= e($doc['drawing_rev'] ?? '') ?>"></div>
    <div class="ff"><label>QAP rev.</label><input class="form-control" name="qap_rev" value="<?= e($doc['qap_rev'] ?? '') ?>"></div>
    <div class="ff"><label>Product category</label><input class="form-control" name="product_category" value="<?= e($v('product_category')) ?>"></div>
    <div class="ff"><label>Material grade</label><input class="form-control" name="material_grade" value="<?= e($doc['material_grade'] ?? '') ?>"></div>
    <div class="ff"><label>Applicable standards</label><input class="form-control" name="standards" value="<?= e($doc['standards'] ?? '') ?>" placeholder="e.g. ASME Sec VIII Div 1"></div>
    <div class="ff"><label>Location</label><input class="form-control" name="location" value="<?= e($v('location')) ?>"></div>
  </div>

  <h3 class="tab-sub">People &amp; outcome</h3>
  <div class="form-grid">
    <div class="ff"><label>Inspector</label>
      <select class="form-control searchable" name="inspector_id"><option value="">—</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= ((int)$v('inspector_id')===(int)$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Approver</label>
      <select class="form-control searchable" name="approver_user_id"><option value="">—</option>
        <?php foreach ($approvers as $u): $nm=trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= ($doc && (int)$doc['approver_user_id']===(int)$u['id'])?'selected':'' ?>><?= e($nm) ?> · <?= e(ORG_ROLES[$u['role']] ?? $u['role']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Inspection result</label>
      <select class="form-control" name="result"><option value="">—</option>
        <?php foreach (lk_options_or('inspection_result', IDEMS_RESULTS) as $rk=>$rv): ?><option value="<?= e($rk) ?>" <?= ($doc && $doc['result']===$rk)?'selected':'' ?>><?= e($rv) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Release status</label>
      <select class="form-control" name="release_status"><option value="">—</option>
        <?php foreach (lk_options_or('release_status', IDEMS_RELEASE) as $rk=>$rv): ?><option value="<?= e($rk) ?>" <?= ($doc && $doc['release_status']===$rk)?'selected':'' ?>><?= e($rv) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Remarks</label><textarea class="form-control" name="remarks" rows="3"><?= e($doc['remarks'] ?? '') ?></textarea></div>
  </div>

  <div style="margin-top:16px">
    <button class="btn" type="submit"><?= $doc ? 'Save report' : 'Create report &amp; generate IRN' ?></button>
    <a class="btn secondary" href="<?= $doc ? '/document?id='.(int)$doc['id'] : '/documents' ?>">Cancel</a>
  </div>
</form>
