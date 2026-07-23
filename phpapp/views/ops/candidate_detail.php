<?php
  $stageBadge = ['RECEIVED'=>'AMBER','SUBMITTED'=>'AMBER','SHORTLISTED'=>'AMBER','INTERVIEW'=>'AMBER',
                'HOLD'=>'AMBER','REJECTED'=>'RED','ACCEPTED'=>'GREEN','WITHDRAWN'=>'RED'];
  $cur = $cand['stage'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/candidates">Hiring pipeline</a> › <?= e($cand['cand_code'] ?: candidate_name($cand)) ?></div>
<div class="master-head">
  <div><h1><?= e(candidate_name($cand)) ?>
      <span class="badge <?= $stageBadge[$cur] ?? 'AMBER' ?>" style="vertical-align:middle"><?= e(CAND_STAGES[$cur] ?? $cur) ?></span></h1>
    <p class="sub"><?= e($cand['cand_code']) ?><?= $cand['trade_label']?' · '.e($cand['trade_label']):'' ?><?= $cand['skill_label']?' / '.e($cand['skill_label']):'' ?> · <?= e(CAND_SOURCES[$cand['source']] ?? $cand['source']) ?></p></div>
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
    <div><span class="k">SBU</span><span class="v"><?= e(lk_options_or('sbu', OPS_SBUS)[$cand['sbu']] ?? $cand['sbu'] ?: '—') ?></span></div>
    <div><span class="k">Designation</span><span class="v"><?= e(lk_options_or('designation', DESIGNATIONS)[$cand['designation']] ?? $cand['designation'] ?: '—') ?></span></div>
    <div><span class="k">Experience</span><span class="v"><?= e(rtrim(rtrim((string)($cand['experience_years'] ?? 0), '0'), '.') ?: '0') ?> yrs</span></div>
    <div><span class="k">Agency</span><span class="v"><?= e($cand['agency'] ?: '—') ?></span></div>
    <div><span class="k">Email</span><span class="v"><?= e($cand['email'] ?: '—') ?></span></div>
    <div><span class="k">Mobile</span><span class="v"><?= e($cand['mobile'] ?: '—') ?></span></div>
    <div><span class="k">Expected rate</span><span class="v"><?= $cand['expected_rate']>0 ? '₹'.number_format((float)$cand['expected_rate'],0).' ('.e(RATE_TYPES[$cand['rate_type']] ?? $cand['rate_type']).')' : '—' ?></span></div>
    <div><span class="k">CV received</span><span class="v"><?= e($cand['cv_received_date'] ?: '—') ?></span></div>
    <div><span class="k">CV file</span><span class="v"><?= $cand['cv_link'] ? '<a href="'.e($cand['cv_link']).'" target="_blank" rel="noopener">Open CV ↗</a>' : '—' ?></span></div>
  </div>
  <?php if ($cand['remarks']): ?><p class="muted" style="margin-top:8px">Remarks: <?= e($cand['remarks']) ?></p><?php endif; ?>
  <?php if ($cand['inspector_id']): ?>
    <p class="msg msg-success" style="margin-top:10px">Hired — this candidate is now inspector
      <a href="/m/inspectors/edit?id=<?= (int)$cand['inspector_id'] ?>">#<?= (int)$cand['inspector_id'] ?></a>. Allocate deputation jobs from the Jobs screen.</p>
  <?php endif; ?>
</div>

<?php if (is_coordinator_level() && !in_array($cur, ['ACCEPTED','WITHDRAWN'], true)): ?>
<div class="panel">
  <h3 class="tab-sub">Move this candidate</h3>
  <form method="post" action="/candidate-stage?id=<?= (int)$cand['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label>New stage</label>
        <select class="form-control" name="to_stage" id="cand_stage">
          <?php foreach (CAND_STAGES as $k=>$v): if ($k===$cur) continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff ff-wide"><label>Remark (decision note, interview feedback…)</label><input class="form-control" name="remark" placeholder="e.g. Client shortlisted; interview on 25th"></div>
    </div>
    <?php if (empty($cand['inspector_id'])): ?>
    <label class="chk" id="hire_chk" style="margin:8px 2px;display:none"><input type="checkbox" name="make_inspector" value="1"> On <strong>Accept</strong>, also add this person to Inspectors (as <?= e(CAND_SOURCES[$cand['source']] ?? $cand['source']) ?>)</label>
    <?php endif; ?>
    <div style="margin-top:8px"><button class="btn" type="submit">Update stage</button></div>
  </form>
</div>
<script>
  (function(){
    var sel = document.getElementById('cand_stage'), chk = document.getElementById('hire_chk');
    if (!sel) return;
    function sync(){ if (chk) chk.style.display = (sel.value === 'ACCEPTED') ? 'inline-flex' : 'none'; }
    sel.addEventListener('change', sync); sync();
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
      <td><?= $ev['from_stage'] ? e(CAND_STAGES[$ev['from_stage']] ?? $ev['from_stage']).' → ' : '' ?><strong><?= e(CAND_STAGES[$ev['to_stage']] ?? $ev['to_stage']) ?></strong></td>
      <td><?= e($ev['remark'] ?: '—') ?></td>
      <td><?= e($ev['actor'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$events): ?><tr><td colspan="4">No history yet.</td></tr><?php endif; ?>
  </table>
</div>
