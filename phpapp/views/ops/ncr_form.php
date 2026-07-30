<?php $p = $prefill ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/ncr">Nonconformities</a> › New</div>
<div class="master-head"><div><h1>Raise a nonconformity</h1>
  <p class="sub" style="margin:2px 0 0">Record the event. Whether it needs a corrective action is decided afterwards — most do not.</p></div></div>

<form method="post" action="/ncr-new" class="panel" style="margin-top:16px;max-width:820px">
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>What was wrong <span class="muted">(one line)</span></label>
      <input class="form-control" name="title" required value="<?= e($p['title'] ?? '') ?>" placeholder="e.g. Report issued naming an instrument that was out of calibration"></div>

    <div><label>Where it came from</label>
      <select class="form-control" name="source">
        <?php foreach (ncr_sources() as $k=>$v): ?>
          <option value="<?= e($k) ?>"<?= ($p['source'] ?? 'INTERNAL')===$k?' selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Reference at the source <span class="muted">(optional)</span></label>
      <input class="form-control" name="source_note" value="<?= e($p['source_note'] ?? '') ?>" placeholder="e.g. the client's e-mail, the finding number"></div>

    <div class="ff-wide"><label>How serious</label>
      <?php foreach (NCR_SEVERITY as $k=>$v): ?>
        <label style="display:flex;gap:8px;align-items:flex-start;margin:4px 0;font-weight:400">
          <input type="radio" name="severity" value="<?= e($k) ?>"<?= ($p['severity'] ?? 'MINOR')===$k?' checked':'' ?>>
          <span><?= e($v) ?></span></label>
      <?php endforeach; ?>
      <p class="muted" style="font-size:12px;margin:4px 0 0">A <b>major</b> one cannot be closed until a corrective action has been opened and closed. That is deliberate.</p></div>

    <div class="ff-wide"><label>What happened</label>
      <textarea class="form-control" name="description" rows="4" placeholder="What was found, when, and what it affected."><?= e($p['description'] ?? '') ?></textarea></div>

    <div><label>Against a <?= e(Tl('job')) ?> <span class="muted">(optional — sets the branch)</span></label>
      <select class="form-control" name="job_id">
        <option value="">— none —</option>
        <?php foreach ($jobs as $j): ?>
          <option value="<?= (int)$j['id'] ?>"<?= (string)($p['job_id'] ?? '')===(string)$j['id']?' selected':'' ?>><?= e($j['job_code']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Branch</label>
      <select class="form-control" name="office_id">
        <option value="">— from the <?= e(Tl('job')) ?>, or mine —</option>
        <?php foreach ($offices as $o): ?>
          <option value="<?= (int)$o['id'] ?>"<?= (string)($p['office_id'] ?? '')===(string)$o['id']?' selected':'' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div><label>Detected on</label><input class="form-control" type="date" name="detected_on" value="<?= e($p['detected_on'] ?? date('Y-m-d')) ?>"></div>
    <div><label>Clause <span class="muted">(optional)</span></label><input class="form-control" name="clause" value="<?= e($p['clause'] ?? '') ?>" placeholder="e.g. 6.2.6"></div>

    <div><label>Owner <span class="muted">(who will see it through)</span></label>
      <input class="form-control" name="owner" value="<?= e($p['owner'] ?? '') ?>" placeholder="name or e-mail"></div>
    <div><label>Due by <span class="muted">(blank = 14 days if major, 30 if not)</span></label>
      <input class="form-control" type="date" name="due_on" value="<?= e($p['due_on'] ?? '') ?>"></div>

    <div class="ff-wide"><label>What was done immediately <span class="muted">(can be added later)</span></label>
      <textarea class="form-control" name="containment" rows="2" placeholder="e.g. the report was recalled from the client the same day"><?= e($p['containment'] ?? '') ?></textarea></div>
  </div>
  <div style="margin-top:14px;display:flex;gap:10px">
    <button class="btn">Raise it</button>
    <a class="btn secondary" href="/ncr">Cancel</a>
  </div>
</form>
