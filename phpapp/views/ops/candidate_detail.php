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
