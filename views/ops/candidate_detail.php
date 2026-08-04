<?php
  $stageBadge = ['RECEIVED'=>'AMBER','SUBMITTED'=>'AMBER','SHORTLISTED'=>'AMBER','INTERVIEW'=>'AMBER',
                'HOLD'=>'AMBER','REJECTED'=>'RED','ACCEPTED'=>'GREEN','WITHDRAWN'=>'RED'];
  $cur = $cand['stage'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/candidates"><?= e(TP('candidate')) ?></a> › <?= e($cand['cand_code'] ?: candidate_name($cand)) ?></div>
<div class="master-head">
  <div><h1><?= e(candidate_name($cand)) ?>
      <span class="badge <?= $stageBadge[$cur] ?? 'AMBER' ?>" style="vertical-align:middle"><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$cur] ?? $cur) ?></span></h1>
    <p class="sub"><?= e($cand['cand_code']) ?><?= $cand['trade_label']?' · '.e($cand['trade_label']):'' ?><?= $cand['skill_label']?' / '.e($cand['skill_label']):'' ?> · <?= e(lk_options_or('candidate_source', CAND_SOURCES)[$cand['source']] ?? $cand['source']) ?></p></div>
  <div class="row-actions">
    <a class="btn secondary" href="/candidate-edit?id=<?= (int)$cand['id'] ?>">Edit</a>
    <a class="btn secondary" href="/candidates">← Back</a>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub">Candidate details</h3>
  <div class="kv-grid">
    <div><span class="k">Client</span><span class="v"><?= e($cand['client_disp'] ?: $cand['client_name'] ?: '—') ?></span></div>
    <div><span class="k">Against call</span><span class="v"><?= $cand['call_id'] ? '<a href="/call?id='.(int)$cand['call_id'].'">'.e($cand['call_code']).'</a>' : '—' ?></span></div>
    <div><span class="k">Proposed site</span><span class="v"><?= e($cand['proposed_site'] ?: '—') ?></span></div>
    <div><span class="k"><?= e(T("sbu")) ?></span><span class="v"><?= e(lk_options_or('sbu', OPS_SBUS)[$cand['sbu']] ?? $cand['sbu'] ?: '—') ?></span></div>
    <div><span class="k">Designation</span><span class="v"><?= e(lk_options_or('designation', DESIGNATIONS)[$cand['designation']] ?? $cand['designation'] ?: '—') ?></span></div>
    <div><span class="k">Experience</span><span class="v"><?= e(rtrim(rtrim((string)($cand['experience_years'] ?? 0), '0'), '.') ?: '0') ?> yrs</span></div>
    <div><span class="k">Agency</span><span class="v"><?= e($cand['agency'] ?: '—') ?></span></div>
    <div><span class="k">Email</span><span class="v"><?= e($cand['email'] ?: '—') ?></span></div>
    <div><span class="k">Mobile</span><span class="v"><?= e($cand['mobile'] ?: '—') ?></span></div>
    <div><span class="k">Expected rate</span><span class="v"><?= $cand['expected_rate']>0 ? cur_sym().number_format((float)$cand['expected_rate'],0).' ('.e(lk_options_or('rate_type', RATE_TYPES)[$cand['rate_type']] ?? $cand['rate_type']).')' : '—' ?></span></div>
    <div><span class="k">CV received</span><span class="v"><?= e($cand['cv_received_date'] ?: '—') ?></span></div>
    <div><span class="k">CV file</span><span class="v"><?= $cand['cv_link'] ? '<a href="'.e($cand['cv_link']).'" target="_blank" rel="noopener">Open CV ↗</a>' : '—' ?></span></div>
    <?php if (function_exists('custom_display')) foreach (custom_display('candidate', $cand['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><span class="v"><?= e($cf['value'] ?: '—') ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($cand['remarks']): ?><p class="muted" style="margin-top:8px">Remarks: <?= e($cand['remarks']) ?></p><?php endif; ?>
  <?php if ($cand['inspector_id']): ?>
    <p class="msg msg-success" style="margin-top:10px">Hired — this candidate is now <?= e(Tl('engineer')) ?>
      <a href="/m/inspectors/edit?id=<?= (int)$cand['inspector_id'] ?>">#<?= (int)$cand['inspector_id'] ?></a>. Allocate <?= e(Tlp('job')) ?> from the <?= e(THP('job')) ?> screen.</p>
  <?php endif; ?>
</div>

<!-- CV analysis + keyword search -->
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">CV analysis <span class="muted">— keywords are stored for searching</span></h3>
  <?php $kw = array_filter(array_map('trim', explode(',', (string)($cand['cv_keywords'] ?? '')))); ?>
  <?php if ($kw): ?>
    <div class="chip-row" style="margin-bottom:6px">
      <?php foreach ($kw as $k): ?><a class="ct" href="/candidates?q=<?= e(urlencode($k)) ?>" title="Find candidates with this keyword"><?= e($k) ?></a><?php endforeach; ?>
    </div>
    <p class="muted" style="font-size:12px">Analysed <?= e(substr((string)($cand['cv_analyzed_at'] ?? ''),0,10)) ?><?= $cand['cv_file_name']?' · '.e($cand['cv_file_name']):'' ?>. Click a keyword to find similar CVs.</p>
  <?php else: ?>
    <p class="sub">No keywords yet — upload the CV (.docx / .txt) or paste the text below and analyse.</p>
  <?php endif; ?>
  <?php if (is_coordinator_level()): ?>
  <form method="post" action="/candidate-cv?id=<?= (int)$cand['id'] ?>" enctype="multipart/form-data" style="margin-top:8px">
    <div class="form-grid">
      <div class="ff"><label>Upload CV (.docx / .txt / .pdf)</label><input class="form-control" type="file" name="cv_file" accept=".docx,.txt,.pdf"></div>
    </div>
    <div class="ff ff-wide"><label>…or paste CV text</label><textarea class="form-control" name="cv_text" rows="4" placeholder="Paste the CV text here for the most accurate keyword extraction"><?= e($cand['cv_text'] ?? '') ?></textarea></div>
    <div style="margin-top:8px"><button class="btn" type="submit">Analyse &amp; save keywords</button></div>
  </form>
  <?php endif; ?>
</div>

<!-- §20 client submission & interview tracking -->
<?php if (is_coordinator_level()): $fb = $cand['client_feedback'] ?? ''; $io = $cand['interview_outcome'] ?? ''; ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Client submission &amp; interview</h3>
  <form method="post" action="/candidate-client?id=<?= (int)$cand['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label>CV submitted to client on</label><input class="form-control" type="date" name="submitted_client_date" value="<?= e($cand['submitted_client_date'] ?? '') ?>"></div>
      <div class="ff"><label>Client feedback</label>
        <select class="form-control" name="client_feedback"><option value="">—</option>
          <?php foreach (['PENDING'=>'Awaiting','SHORTLISTED'=>'Shortlisted','REJECTED'=>'Rejected'] as $k=>$v): ?><option value="<?= $k ?>" <?= $fb===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Feedback date</label><input class="form-control" type="date" name="client_feedback_date" value="<?= e($cand['client_feedback_date'] ?? '') ?>"></div>

      <div class="ff" style="align-self:end"><label class="chk"><input type="checkbox" name="interview_required" value="1" <?= !empty($cand['interview_required'])?'checked':'' ?>> Interview required</label></div>
      <div class="ff"><label>Interview planned for</label><input class="form-control" type="date" name="interview_date" value="<?= e($cand['interview_date'] ?? '') ?>"></div>
      <div class="ff"><label>Interview completed on</label><input class="form-control" type="date" name="interview_done_date" value="<?= e($cand['interview_done_date'] ?? '') ?>"></div>

      <div class="ff"><label>Interview outcome</label>
        <select class="form-control" name="interview_outcome"><option value="">—</option>
          <?php foreach (['SELECTED'=>'Selected','REJECTED'=>'Rejected','HOLD'=>'On hold'] as $k=>$v): ?><option value="<?= $k ?>" <?= $io===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="ff ff-wide"><label>Client feedback note</label><input class="form-control" name="client_feedback_note" value="<?= e($cand['client_feedback_note'] ?? '') ?>"></div>
    </div>
    <div style="margin-top:8px"><button class="btn" type="submit">Save tracking</button></div>
  </form>
  <?php if ($io === 'SELECTED'): ?>
  <div style="margin-top:10px;padding:10px;border:1px solid var(--ok);border-radius:8px">
    <b style="color:var(--ok)">✓ Selected.</b> Request the candidate's credentials (CV, salary slips, IDs, certificates).
    <form method="post" action="/candidate-credential?id=<?= (int)$cand['id'] ?>" style="display:inline;margin-left:8px"><button class="btn small" type="submit"><?= !empty($cand['credential_requested'])?'Re-send credential request':'Send credential request' ?></button></form>
    <?php if (!empty($cand['credential_requested'])): ?><span class="pill p-ok" style="margin-left:6px">requested</span><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (is_coordinator_level() && !in_array($cur, ['ACCEPTED','WITHDRAWN'], true)): ?>
<div class="panel">
  <h3 class="tab-sub">Move this candidate</h3>
  <form method="post" action="/candidate-stage?id=<?= (int)$cand['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label>New stage</label>
        <select class="form-control" name="to_stage" id="cand_stage">
          <?php foreach (lk_options_or('candidate_stage', CAND_STAGES) as $k=>$v): if ($k===$cur) continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff ff-wide"><label>Remark (decision note, interview feedback…)</label><input class="form-control" name="remark" placeholder="e.g. Client shortlisted; interview on 25th"></div>
    </div>
    <?php if (empty($cand['inspector_id'])): ?>
    <label class="chk" id="hire_chk" style="margin:8px 2px;display:none"><input type="checkbox" name="make_inspector" id="mk_insp" value="1"> On <strong>Accept</strong>, also add this person to Inspectors</label>
    <div id="hire_details" class="panel" style="display:none;background:var(--soft);margin-top:6px">
      <div class="form-grid">
        <div class="ff"><label>Supplied by agency <span class="muted">(optional)</span></label>
          <select class="form-control" name="agency_id" id="ag_sel"><option value="" data-type="" data-fee="0" data-monthly="0">— none / direct —</option>
            <?php foreach (agencies_list() as $a): ?><option value="<?= (int)$a['id'] ?>" data-type="<?= e($a['agency_type']) ?>" data-fee="<?= e($a['one_time_fee']) ?>" data-monthly="<?= e($a['monthly_rate']) ?>"><?= e($a['name']) ?> · <?= e(lk_options_or('agency_type', AGENCY_TYPES)[$a['agency_type']] ?? $a['agency_type']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label>On whose roll?</label>
          <select class="form-control" name="roll_type" id="roll_sel"><?php foreach (lk_options_or('roll_type', ROLL_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff" id="fee_one"><label>One-time placement fee (<?= e(cur_sym()) ?>) <span class="muted">recruitment</span></label><input class="form-control" type="number" step="0.01" name="placement_fee" value=""></div>
        <div class="ff" id="fee_month"><label>Monthly agency charge (<?= e(cur_sym()) ?>) <span class="muted">manpower</span></label><input class="form-control" type="number" step="0.01" name="agency_cost" value=""></div>
      </div>
      <p class="muted" style="margin:2px 2px 0;font-size:12px">Recruitment → our roll + one-time fee (added to costing, one-time). Manpower → agency roll + monthly charge (their bill; we invoice the client our rate).</p>
    </div>
    <?php endif; ?>
    <div style="margin-top:8px"><button class="btn" type="submit">Update stage</button></div>
  </form>
</div>
<script>
  (function(){
    var sel = document.getElementById('cand_stage'), chk = document.getElementById('hire_chk');
    if (!sel) return;
    var mk = document.getElementById('mk_insp'), det = document.getElementById('hire_details');
    var ag = document.getElementById('ag_sel'), roll = document.getElementById('roll_sel');
    var feeOne = document.getElementById('fee_one'), feeMonth = document.getElementById('fee_month');
    function syncStage(){ if (chk) chk.style.display = (sel.value === 'ACCEPTED') ? 'inline-flex' : 'none'; if (sel.value!=='ACCEPTED' && det) det.style.display='none'; }
    function syncHire(){ if (det) det.style.display = (mk && mk.checked && sel.value==='ACCEPTED') ? 'block' : 'none'; }
    function syncAgency(){
      if (!ag) return; var o = ag.options[ag.selectedIndex], t = o.getAttribute('data-type');
      if (roll) roll.value = (t === 'MANPOWER') ? 'AGENCY' : 'OWN';
      if (feeOne)   feeOne.style.display   = (t === 'RECRUITMENT' || t==='') ? '' : 'none';
      if (feeMonth) feeMonth.style.display = (t === 'MANPOWER') ? '' : 'none';
      var f = feeOne && feeOne.querySelector('input'), m = feeMonth && feeMonth.querySelector('input');
      if (f && t==='RECRUITMENT' && !f.value) f.value = o.getAttribute('data-fee')||'';
      if (m && t==='MANPOWER' && !m.value) m.value = o.getAttribute('data-monthly')||'';
    }
    sel.addEventListener('change', function(){ syncStage(); syncHire(); });
    if (mk) mk.addEventListener('change', syncHire);
    if (ag) ag.addEventListener('change', syncAgency);
    syncStage(); syncHire(); syncAgency();
  })();
</script>
<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">History</h3>
  <table class="grid">
    <tr><th>When</th><th>Change</th><th>Remark</th><th>By</th></tr>
    <?php foreach ($events as $ev): ?>
    <tr>
      <td><?= e(substr($ev['created_at'],0,16)) ?></td>
      <td><?= $ev['from_stage'] ? e(lk_options_or('candidate_stage', CAND_STAGES)[$ev['from_stage']] ?? $ev['from_stage']).' → ' : '' ?><strong><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$ev['to_stage']] ?? $ev['to_stage']) ?></strong></td>
      <td><?= e($ev['remark'] ?: '—') ?></td>
      <td><?= e($ev['actor'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$events): ?><tr><td colspan="4">No history yet.</td></tr><?php endif; ?>
  </table>
</div>

<?php
// ---------------------------------------------------------------------------
//  Erasing an applicant's details
//
//  This is the sharpest personal data in the system and it belongs to somebody
//  who may never have worked for you: name, mobile, e-mail, the text of their
//  CV, expected rate. The DPDP Act allows keeping it only while there is a
//  reason to, and "nobody deleted it" is not one.
//
//  Offered on the candidate's own screen rather than buried in a compliance
//  register, because this is where somebody is standing when the request
//  arrives.
// ---------------------------------------------------------------------------
$erasePv = function_exists('candidate_erase_preview') ? candidate_erase_preview((int)$cand['id']) : null;
?>
<?php if ($erasePv && (is_master() || can('settings.manage') || can('person.iddoc.manage'))): ?>
<div class="panel mt-4">
  <div class="form-sec"><h3>Erase this person's details</h3>
    <p>For when an applicant asks you to remove what you hold about them.</p></div>

  <?php if ($erasePv['erased']): ?>
    <div class="msg msg-info">
      <b>Already erased</b> on <?= e(fdate(substr((string)$cand['erased_at'], 0, 10))) ?>
      <?= trim((string)($cand['erased_by'] ?? '')) !== '' ? 'by ' . e($cand['erased_by']) : '' ?>.
      <?php if (trim((string)($cand['erase_reason'] ?? '')) !== ''): ?>
        <div class="mt-1">Reason recorded: <?= e($cand['erase_reason']) ?></div>
      <?php endif; ?>
      <div class="mt-1">The reference and the hiring decision were kept on purpose, so the register still adds up.</div>
    </div>

  <?php elseif ($erasePv['hired']): ?>
    <div class="msg msg-warning">
      <b>This candidate was hired.</b> Their details are now held as a <?= e(Tl('engineer')) ?> — for employment, not
      for recruitment — so erasing them here would not remove them from the system and would only make this register
      disagree with the rest of it. Handle it from their own record instead.
    </div>

  <?php else: ?>
    <div class="row-top gap-4" style="align-items:flex-start">
      <div class="grow" style="min-width:260px">
        <p class="t-md t-mut mb-2"><b>What goes:</b>
          <?= $erasePv['holds'] ? e(implode(', ', array_map(fn($f) => str_replace('_', ' ', $f), $erasePv['holds'])))
                                : 'nothing — no personal details are held on this record' ?>.</p>
        <p class="t-md t-mut mb-0"><b>What stays:</b> <?= e(implode(', ', $erasePv['keeps'])) ?> — a register that
          silently loses rows is one nobody can audit, so the shape of the decision is kept and only the person is
          removed.</p>
      </div>
      <form method="post" action="/candidate-erase" class="col" style="min-width:260px"
            onsubmit="return confirm('Erase the personal details on <?= e(addslashes((string)$cand['cand_code'])) ?>? This cannot be undone.')">
        <input type="hidden" name="id" value="<?= (int)$cand['id'] ?>">
        <div class="ff mb-0"><label>Why <span class="muted">(kept on the record)</span></label>
          <input class="form-control" name="reason" maxlength="255" placeholder="e.g. asked to be removed, 14 Aug 2026"></div>
        <button class="btn danger" type="submit">Erase the details</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
