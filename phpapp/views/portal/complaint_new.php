<?php $lbl = 'display:block;font-size:13px;color:var(--muted);margin:14px 0 5px'; $p = $prefill ?? []; ?>
<h2 class="ptitle">Raise a complaint or appeal</h2>
<p class="plead">Our process is published at <a href="/complaints-policy">complaints and appeals</a> — it says how
  long we take to acknowledge one, who looks into it, and how we write back to you.</p>

<?php if (!empty($err)): ?><div class="pmsg err"><?= e($err) ?></div><?php endif; ?>

<form method="post" action="/portal/complaint-new" class="pcard" style="max-width:660px">

  <?php if ($appealable): ?>
    <label style="<?= $lbl ?>;margin-top:0" for="cap">Is this an appeal against a decision we already made?</label>
    <select class="form-control" id="cap" name="appeal_of_id">
      <option value="">No — this is a new complaint</option>
      <?php foreach ($appealable as $a): ?>
        <option value="<?= (int)$a['id'] ?>"<?= (string)($p['appeal_of_id'] ?? '') === (string)$a['id'] ? ' selected' : '' ?>>
          Appeal against <?= e($a['ref']) ?> — <?= e(mb_substr((string)$a['subject'], 0, 60)) ?> (decided <?= e(fdate($a['decided_on'])) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <p class="pnote" style="margin:6px 0 0">Only complaints we have actually decided can be appealed. Whoever made
      the original decision is not permitted to decide the appeal.</p>
  <?php else: ?>
    <input type="hidden" name="appeal_of_id" value="">
  <?php endif; ?>

  <label style="<?= $lbl ?>" for="csub">What is it about? *</label>
  <input class="form-control" id="csub" name="subject" maxlength="255" required
         value="<?= e($p['subject'] ?? '') ?>"
         placeholder="One line — e.g. Inspector did not attend on the agreed date">

  <label style="<?= $lbl ?>" for="cdet">What happened? *</label>
  <textarea class="form-control" id="cdet" name="description" rows="6" required
    placeholder="Dates, who was involved, what was agreed and what actually happened. The more specific you are, the faster we can look into it."><?= e($p['description'] ?? '') ?></textarea>

  <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:4px">
    <div>
      <label style="<?= $lbl ?>" for="cjob">Against which visit? <span style="opacity:.75">(if it applies)</span></label>
      <select class="form-control" id="cjob" name="job_id">
        <option value="">— none in particular —</option>
        <?php foreach ($jobs as $j): ?>
          <option value="<?= (int)$j['id'] ?>"<?= (string)($p['job_id'] ?? '') === (string)$j['id'] ? ' selected' : '' ?>>
            <?= e($j['job_code']) ?><?= $j['scheduled_date'] ? ' — ' . e(fdate($j['scheduled_date'])) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="<?= $lbl ?>" for="cirn">Report number <span style="opacity:.75">(if it applies)</span></label>
      <input class="form-control" id="cirn" name="report_irn" maxlength="120" value="<?= e($p['report_irn'] ?? '') ?>">
    </div>
    <div>
      <label style="<?= $lbl ?>" for="cph">Your telephone <span style="opacity:.75">(if we should ring you)</span></label>
      <input class="form-control" id="cph" name="phone" maxlength="60">
    </div>
  </div>

  <p class="pnote" style="margin-top:14px">It is recorded against your name and your company, and our office is told
    straight away so the acknowledgement clock starts. You will be written to with the outcome.</p>

  <button class="btn" type="submit" style="margin-top:14px">Send it</button>
  <a href="/portal/complaints" style="margin-left:12px;font-size:14px">Cancel</a>
</form>
