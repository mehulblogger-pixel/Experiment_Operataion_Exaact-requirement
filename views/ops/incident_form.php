<?php $isEdit = !empty($inc); $v = function ($k, $d = '') use ($inc) { return e($inc[$k] ?? $d); }; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/incidents">Incident register</a> ›
  <span><?= $isEdit ? e($inc['ref']) : 'Record an incident' ?></span></div>

<div class="master-head">
  <div><h1><?= $isEdit ? 'Update ' . e($inc['ref']) : 'Record an incident' ?></h1>
    <p class="sub" style="margin:2px 0 0">Record it now with what you know. Come back and fill in the rest.</p></div>
</div>

<form method="post" action="<?= $isEdit ? '/incident-edit?id=' . (int)$inc['id'] : '/incident-new' ?>">
  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>What happened</h3></div>
    <div class="form-grid">
      <div class="ff"><label>When it was noticed <span class="muted">— the clock runs from here</span></label>
        <input class="form-control" type="datetime-local" name="detected_at"
               value="<?= $isEdit && $inc['detected_at'] ? e(date('Y-m-d\TH:i', strtotime($inc['detected_at']))) : e(date('Y-m-d\TH:i')) ?>" required></div>
      <div class="ff"><label>What kind</label>
        <select class="form-control" name="kind">
          <?php foreach (INCIDENT_KINDS as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= ($inc['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="ff"><label>How bad</label>
        <select class="form-control" name="severity">
          <?php foreach (INCIDENT_SEVERITY as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= ($inc['severity'] ?? 'MEDIUM') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="ff ff-wide"><label>In your own words, what happened</label>
        <textarea class="form-control" name="summary" rows="3" required
          placeholder="e.g. A coordinator's laptop was stolen from a car in Pune. It was signed in to the system."><?= $v('summary') ?></textarea></div>
      <div class="ff ff-wide"><label>What was affected</label>
        <input class="form-control" name="systems" value="<?= $v('systems') ?>"
               placeholder="e.g. one laptop, the Pune office account, the client contact list"></div>
      <div class="ff"><label>Roughly how many people's data</label>
        <input class="form-control" type="number" min="0" name="people_affected" value="<?= $v('people_affected', '0') ?>"></div>
      <div class="ff"><label>What sort of data</label>
        <input class="form-control" name="data_kinds" value="<?= $v('data_kinds') ?>"
               placeholder="e.g. client contact names and mobile numbers"></div>
      <div class="ff ff-wide"><label>What you did straight away</label>
        <textarea class="form-control" name="immediate_action" rows="2"
          placeholder="e.g. the account was locked and its password reset within ten minutes"><?= $v('immediate_action') ?></textarea></div>
      <div class="ff ff-wide"><label>Why it happened <span class="muted">— fill this in later, once you know</span></label>
        <textarea class="form-control" name="root_cause" rows="2"><?= $v('root_cause') ?></textarea></div>
    </div>
  </div>

  <div class="panel">
    <div class="ctitle" style="margin-top:0"><h3>Who has been told</h3></div>
    <p class="muted" style="font-size:13.5px;line-height:1.65">
      <strong>CERT-In</strong> — within six hours, to incident@cert-in.org.in. Include the reference they give back.<br>
      <strong>The Data Protection Board</strong> and <strong>the people affected</strong> — required by the DPDP Act when
      personal data was involved. Both stay marked outstanding until a date is entered here.
    </p>
    <div class="form-grid">
      <div class="ff"><label>Reported to CERT-In at</label>
        <input class="form-control" type="datetime-local" name="certin_reported_at"
               value="<?= $isEdit && $inc['certin_reported_at'] ? e(date('Y-m-d\TH:i', strtotime($inc['certin_reported_at']))) : '' ?>"></div>
      <div class="ff"><label>Their reference</label>
        <input class="form-control" name="certin_ref" value="<?= $v('certin_ref') ?>"></div>
      <div class="ff"><label>Reported to the Data Protection Board at</label>
        <input class="form-control" type="datetime-local" name="dpb_reported_at"
               value="<?= $isEdit && $inc['dpb_reported_at'] ? e(date('Y-m-d\TH:i', strtotime($inc['dpb_reported_at']))) : '' ?>"></div>
      <div class="ff"><label>The people affected were told at</label>
        <input class="form-control" type="datetime-local" name="people_told_at"
               value="<?= $isEdit && $inc['people_told_at'] ? e(date('Y-m-d\TH:i', strtotime($inc['people_told_at']))) : '' ?>"></div>
      <div class="ff"><label>Status</label>
        <select class="form-control" name="status">
          <option value="OPEN" <?= ($inc['status'] ?? 'OPEN') === 'OPEN' ? 'selected' : '' ?>>Open</option>
          <option value="CLOSED" <?= ($inc['status'] ?? '') === 'CLOSED' ? 'selected' : '' ?>>Closed</option>
        </select></div>
    </div>
  </div>

  <div style="display:flex;gap:8px">
    <button class="btn" type="submit"><?= $isEdit ? 'Save' : 'Record it' ?></button>
    <a class="btn secondary" href="/incidents">Cancel</a>
  </div>
</form>
