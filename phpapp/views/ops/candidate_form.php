<?php
  $curTrade = $cand['trade_id'] ?? '';
  $curSkills = ($curTrade && isset($skillsByTrade[$curTrade])) ? $skillsByTrade[$curTrade] : [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/candidates">Hiring pipeline</a> › <?= $cand ? 'Edit' : 'Add CV' ?></div>
<div class="master-head">
  <div><h1><?= $cand ? 'Edit — ' . e(candidate_name($cand)) : 'Add candidate CV' ?></h1>
    <p class="sub">Submit a candidate for project deputation. You can move them through Submitted → Shortlisted → Interview → Accept / Hold / Reject afterwards.</p></div>
  <a class="btn secondary" href="/candidates">← Back</a>
</div>

<form method="post" action="/<?= $cand ? 'candidate-edit?id=' . (int)$cand['id'] : 'candidate-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>First name *</label><input class="form-control" name="first_name" required value="<?= e($cand['first_name'] ?? '') ?>"></div>
    <div class="ff"><label>Middle name</label><input class="form-control" name="middle_name" value="<?= e($cand['middle_name'] ?? '') ?>"></div>
    <div class="ff"><label>Last name</label><input class="form-control" name="last_name" value="<?= e($cand['last_name'] ?? '') ?>"></div>

    <div class="ff"><label>Client (who needs the resource)</label>
      <select class="form-control searchable" name="client_id"><option value="">—</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)($cand['client_id'] ?? '')===(string)$cl['id']?'selected':'' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Against call / requirement</label>
      <select class="form-control searchable" name="call_id"><option value="">— optional —</option>
        <?php foreach ($depCalls as $dc): ?><option value="<?= (int)$dc['id'] ?>" <?= (string)($cand['call_id'] ?? '')===(string)$dc['id']?'selected':'' ?>><?= e($dc['call_code']) ?><?= $dc['inspection_type']?' · '.e(INSPECTION_TYPES[$dc['inspection_type']] ?? $dc['inspection_type']):'' ?></option><?php endforeach; ?>
      </select></div>
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

    <div class="ff"><label>SBU</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($cand['sbu'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Source</label>
      <select class="form-control" name="source"><?php foreach (CAND_SOURCES as $k=>$v): ?><option value="<?= $k ?>" <?= (($cand['source'] ?? 'FREELANCER')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Agency (if sub-con / freelancer)</label><input class="form-control" name="agency" value="<?= e($cand['agency'] ?? '') ?>"></div>

    <div class="ff"><label>Experience (years)</label><input class="form-control" type="number" step="0.5" name="experience_years" value="<?= e($cand['experience_years'] ?? '') ?>"></div>
    <div class="ff"><label>Email</label><input class="form-control" name="email" value="<?= e($cand['email'] ?? '') ?>"></div>
    <div class="ff"><label>Mobile</label><input class="form-control" name="mobile" value="<?= e($cand['mobile'] ?? '') ?>"></div>

    <div class="ff"><label>Expected rate (₹)</label><input class="form-control" type="number" step="0.01" name="expected_rate" value="<?= e($cand['expected_rate'] ?? '') ?>"></div>
    <div class="ff"><label>Rate type</label>
      <select class="form-control" name="rate_type"><?php foreach (RATE_TYPES as $k=>$v): ?><option value="<?= $k ?>" <?= (($cand['rate_type'] ?? 'MANDAY')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>CV received date</label><input class="form-control" type="date" name="cv_received_date" value="<?= e($cand['cv_received_date'] ?? '') ?>"></div>

    <div class="ff ff-wide"><label>CV link (Drive / SharePoint URL)</label><input class="form-control" name="cv_link" value="<?= e($cand['cv_link'] ?? '') ?>" placeholder="https://…"></div>
    <div class="ff ff-wide"><label>Remarks</label><input class="form-control" name="remarks" value="<?= e($cand['remarks'] ?? '') ?>"></div>
  </div>
  <div style="margin-top:16px;">
    <button class="btn" type="submit"><?= $cand ? 'Save candidate' : 'Add candidate' ?></button>
    <a class="btn secondary" href="/candidates">Cancel</a>
  </div>
</form>
<script>window.SKILLS = <?= json_encode($skillsByTrade) ?>;</script>
