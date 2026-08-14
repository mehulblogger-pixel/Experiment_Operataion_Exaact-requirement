<?php
  // $prefill repopulates the form when a NEW candidate was stopped by the
  // duplicate guard — we stay in "new" mode but keep what the user typed.
  $isEdit = !empty($cand);
  $cand = $cand ?: ($prefill ?? null);
  $dupes = $dupes ?? [];
  $curTrade = $cand['trade_id'] ?? '';
  $curSkills = ($curTrade && isset($skillsByTrade[$curTrade])) ? $skillsByTrade[$curTrade] : [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/candidates"><?= e(TP('candidate')) ?></a> › <?= $isEdit ? 'Edit' : 'Add CV' ?></div>
<div class="master-head">
  <div><h1><?= $isEdit ? 'Edit — ' . e(candidate_name($cand)) : 'Add candidate CV' ?></h1>
    <p class="sub">Submit a candidate for project work. You can move them through Submitted → Shortlisted → Interview → Accept / Hold / Reject afterwards.</p></div>
  <a class="btn secondary" href="/candidates">← Back</a>
</div>

<?php if ($dupes): ?>
<div class="panel" style="border-left:4px solid var(--amber,#d97706);background:#fffdf5">
  <h3 class="tab-sub" style="margin-top:0;color:#92400e">⚠ Possible duplicate — is this the same person?</h3>
  <p class="sub" style="margin:0 0 8px">We found existing candidate record(s) that look like this person. Open one to continue their file instead of creating a second, or confirm below that this is genuinely a new person.</p>
  <table class="grid" style="margin:0">
    <thead><tr><th>Existing candidate</th><th>Match</th><th>Stage</th><th>Client</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($dupes as $dp): ?>
      <tr>
        <td><b><?= e(trim(($dp['first_name'] ?? '') . ' ' . ($dp['last_name'] ?? ''))) ?></b> <span class="muted"><?= e($dp['cand_code']) ?></span><br>
          <span class="muted" style="font-size:12px"><?= e($dp['mobile'] ?: '') ?><?= $dp['email'] ? ' · ' . e($dp['email']) : '' ?></span></td>
        <td><span class="pill <?= $dp['confidence'] >= 90 ? 'p-bad' : 'p-warn' ?>"><?= (int)$dp['confidence'] ?>%</span><br><span class="muted" style="font-size:11px"><?= e($dp['reasons']) ?></span></td>
        <td><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$dp['stage']] ?? $dp['stage']) ?></td>
        <td class="muted"><?= e($dp['client_disp'] ?: '—') ?></td>
        <td><a class="btn small secondary" href="/candidate?id=<?= (int)$dp['id'] ?>">Open →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<form method="post" action="/<?= $isEdit ? 'candidate-edit?id=' . (int)$cand['id'] : 'candidate-new' ?>" class="panel">
<?php if ($dupes): ?><input type="hidden" name="dup_ack" value="1"><?php endif; ?>
  <div class="ff ff-wide" style="background:var(--soft);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px">
    <label>Against requisition (management approval) *</label>
    <select class="form-control searchable" name="requisition_id" required>
      <option value="">— pick an approved requisition —</option>
      <?php foreach (($requisitions ?? []) as $rq): ?><option value="<?= (int)$rq['id'] ?>" <?= (string)($preReq ?? '')===(string)$rq['id']?'selected':'' ?>><?= e($rq['req_code']) ?> · <?= e(DESIGNATIONS[$rq['designation']] ?? $rq['designation']) ?> · <?= e(lk_options_or('requisition_type', REQ_TYPES)[$rq['req_type']] ?? '') ?></option><?php endforeach; ?>
    </select>
    <small class="muted">Every hire must trace to an approved requisition. Not listed? <a href="/requisition-new">Raise one first</a>.</small>
  </div>
  <div class="form-grid">
    <div class="ff"><label>First name *</label><input class="form-control" name="first_name" required value="<?= e($cand['first_name'] ?? '') ?>"></div>
    <div class="ff"><label>Middle name</label><input class="form-control" name="middle_name" value="<?= e($cand['middle_name'] ?? '') ?>"></div>
    <div class="ff"><label>Last name</label><input class="form-control" name="last_name" value="<?= e($cand['last_name'] ?? '') ?>"></div>

    <div class="ff"><label>Client (who needs the resource) <a href="#" class="addlink" data-qa="client">+ Add new</a></label>
      <select class="form-control searchable" id="client_sel" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)($cand['client_id'] ?? '')===(string)$cl['id']?'selected':'' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Against call / requirement <a href="/call-new" target="_blank" class="muted" style="font-size:12px">＋ New call (full form) ↗</a></label>
      <select class="form-control searchable" name="call_id"><option value="">— optional —</option>
        <?php foreach ($depCalls as $dc): ?><option value="<?= (int)$dc['id'] ?>" <?= (string)($cand['call_id'] ?? '')===(string)$dc['id']?'selected':'' ?>><?= e($dc['call_code']) ?><?= $dc['inspection_type']?' · '.e(INSPECTION_TYPES[$dc['inspection_type']] ?? $dc['inspection_type']):'' ?></option><?php endforeach; ?>
      </select><small class="muted">Not listed? Open the full New Call form, add it, then reopen this form to pick it.</small></div>
    <div class="ff"><label>Proposed site / location</label><input class="form-control" name="proposed_site" value="<?= e($cand['proposed_site'] ?? '') ?>"></div>

    <div class="ff"><label>Trade / discipline</label>
      <select class="form-control searchable" id="trade_sel" name="trade_id"><option value="">—</option>
        <?php foreach ($trades as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (string)$curTrade===(string)$t['id']?'selected':'' ?>><?= e($t['label']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Sub-category (skill)</label>
      <select class="form-control" id="skill_sel" name="skill_id"><option value="">— pick trade —</option>
        <?php foreach ($curSkills as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (string)($cand['skill_id'] ?? '')===(string)$s['id']?'selected':'' ?>><?= e($s['label']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Designation offered</label>
      <select class="form-control searchable" name="designation"><option value="">—</option>
        <?php foreach (lk_options_or('designation', DESIGNATIONS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($cand['designation'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>

    <div class="ff"><label><?= e(T("sbu")) ?></label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($cand['sbu'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Source</label>
      <select class="form-control" id="cand_source" name="source"><?php foreach (lk_options_or('candidate_source', CAND_SOURCES) as $k=>$v): ?><option value="<?= $k ?>" <?= (($cand['source'] ?? 'FREELANCER')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label id="agency_lbl">Agency (sub-con / HR agency) <a href="#" class="addlink" data-qa="agency">+ Add new</a></label>
      <?php $curAgency = $cand['agency'] ?? ''; $inList = in_array($curAgency, $agencies, true); ?>
      <select class="form-control searchable" id="agency_sel" name="agency">
        <option value="">—</option>
        <?php if ($curAgency && !$inList): ?><option value="<?= e($curAgency) ?>" selected><?= e($curAgency) ?></option><?php endif; ?>
        <?php foreach ($agencies as $a): ?><option value="<?= e($a) ?>" <?= $curAgency===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?>
      </select></div>

    <div class="ff"><label>Experience (years)</label><input class="form-control" type="number" step="0.5" name="experience_years" value="<?= e($cand['experience_years'] ?? '') ?>"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email" value="<?= e($cand['email'] ?? '') ?>"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" name="mobile" value="<?= e($cand['mobile'] ?? '') ?>"></div>

    <div class="ff"><label>Expected rate (<?= e(cur_sym()) ?>)</label><input class="form-control" type="number" step="0.01" name="expected_rate" value="<?= e($cand['expected_rate'] ?? '') ?>"></div>
    <div class="ff"><label>Rate type</label>
      <select class="form-control" name="rate_type"><?php foreach (lk_options_or('rate_type', RATE_TYPES) as $k=>$v): ?><option value="<?= $k ?>" <?= (($cand['rate_type'] ?? 'MANDAY')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>CV received date</label><input class="form-control" type="date" name="cv_received_date" value="<?= e($cand['cv_received_date'] ?? '') ?>"></div>

    <div class="ff ff-wide"><label>CV link (Drive / SharePoint URL)</label><input class="form-control" name="cv_link" value="<?= e($cand['cv_link'] ?? '') ?>" placeholder="https://…"></div>
    <div class="ff ff-wide"><label>Remarks</label><input class="form-control" name="remarks" value="<?= e($cand['remarks'] ?? '') ?>"></div>
  </div>
  <?php if (function_exists('custom_fields_for') && custom_fields_for('candidate')): ?>
    <h3 class="tab-sub">More details</h3>
    <div class="form-grid"><?php render_custom_fields('candidate', $cfvals ?? []); ?></div>
  <?php endif; ?>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $isEdit ? 'Save candidate' : ($dupes ? 'Save anyway — this is a new person' : 'Add candidate') ?></button>
    <a class="btn secondary" href="/candidates">Cancel</a>
  </div>
</form>
<!-- Quick-add modal (shared markup — client + agency) -->
<div class="modal-back" id="qa_back" style="display:none;">
  <div class="modal">
    <h3 id="qa_title">Add</h3>
    <div class="ff"><label>Name *</label><input class="form-control" id="qa_name" autocomplete="off"></div>
    <div class="qa-field qa-cv">
      <div class="ff"><label>GSTIN (optional — auto PAN/State)</label><input class="form-control" id="qa_gstin"></div>
      <div class="ff ff-check"><input type="checkbox" id="qa_both"><label>Add to <strong>both</strong> Client &amp; Vendor lists</label></div>
    </div>
    <div id="qa_err" class="msg msg-error" style="display:none;"></div>
    <div style="margin-top:14px;display:flex;gap:8px;">
      <button class="btn" type="button" id="qa_save">Add &amp; select</button>
      <button class="btn secondary" type="button" id="qa_cancel">Cancel</button>
    </div>
  </div>
</div>
<script>
window.SKILLS = <?= json_encode($skillsByTrade) ?>;
(function(){
  // Emphasise Agency when the source is a sub-con / HR agency
  var src=document.getElementById('cand_source'), lbl=document.getElementById('agency_lbl'), sel=document.getElementById('agency_sel');
  var need=<?= json_encode(CAND_AGENCY_SOURCES) ?>;
  function sync(){ var on=need.indexOf(src.value)>=0; if(lbl) lbl.style.fontWeight=on?'700':''; if(sel){ sel.style.borderColor=on?'#d98a2b':''; } }
  if(src){ src.addEventListener('change', sync); sync(); }
})();
</script>
