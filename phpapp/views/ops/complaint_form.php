<div class="crumbs"><a href="/">Home</a> › <a href="/complaints">Complaints &amp; appeals</a> › Record one</div>
<div class="master-head">
  <div><h1><?= $appealOf ? 'Record an appeal' : 'Record a complaint' ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= $appealOf
      ? 'Against ' . e($appealOf['ref']) . ' — ' . e($appealOf['subject']) . '. It will be decided by somebody who did not make that decision.'
      : 'Anyone may complain about our work, not only the client who paid for it. Record it the day it arrives — the clock runs from then, not from when somebody got round to typing it in.' ?></p></div>
  <a class="btn secondary" href="/complaints">← Back</a>
</div>

<form method="post" action="/complaint-new">
  <?php if ($appealOf): ?><input type="hidden" name="appeal_of_id" value="<?= (int)$appealOf['id'] ?>"><?php endif; ?>
  <div class="panel">
    <div class="fgrid">
      <?php if (!$appealOf): ?>
      <div class="ff"><label>What is it?</label>
        <select class="form-control" name="kind">
          <?php foreach (CMP_KINDS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div class="ff"><label>Received on</label>
        <input class="form-control" type="date" name="received_on" value="<?= e(date('Y-m-d')) ?>"></div>
      <div class="ff"><label>How it reached us</label>
        <select class="form-control" name="channel">
          <?php foreach (CMP_CHANNELS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Who from</label>
        <select class="form-control" name="source">
          <?php foreach (CMP_SOURCES as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($appealOf['source'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>

      <div class="ff"><label>Their name</label>
        <input class="form-control" name="complainant_name" value="<?= e($appealOf['complainant_name'] ?? '') ?>"></div>
      <div class="ff"><label>E-mail</label>
        <input class="form-control" type="email" name="complainant_email" value="<?= e($appealOf['complainant_email'] ?? '') ?>"></div>
      <div class="ff"><label>Telephone</label>
        <input class="form-control" name="complainant_phone" value="<?= e($appealOf['complainant_phone'] ?? '') ?>"></div>
      <div class="ff"><label>Anonymous?</label>
        <label class="chk"><input type="checkbox" name="anonymous" value="1"> They asked not to be named</label>
        <small class="muted">We still investigate. Without a way to reach them we cannot tell them the outcome, and the register says so.</small></div>

      <div class="ff"><label>Which <?= e(Tl('client')) ?> or party</label>
        <select class="form-control searchable" name="partner_id"><option value="">— none / not applicable —</option>
          <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (int)($appealOf['partner_id'] ?? 0)===(int)$cl['id']?'selected':'' ?>><?= e(pname($cl)) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Which <?= e(Tl('engineer')) ?> <span class="muted">— if it concerns one</span></label>
        <select class="form-control searchable" name="inspector_id"><option value="">— not about a person —</option>
          <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (int)($appealOf['inspector_id'] ?? 0)===(int)$i['id']?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
        </select>
        <small class="muted">Naming them is what lets the app refuse to let that person decide their own complaint.</small></div>
      <div class="ff"><label><?= e(TH('job')) ?> number</label>
        <input class="form-control" name="job_id" value="<?= e($appealOf['job_id'] ?? '') ?>" placeholder="if it concerns one <?= e(Tl('job')) ?>"></div>
      <div class="ff"><label><?= e(TH('report')) ?> number (IRN)</label>
        <input class="form-control" name="report_irn" value="<?= e($appealOf['report_irn'] ?? '') ?>"
               placeholder="if it concerns one report">
        <small class="muted">Whoever prepared or approved that report is then barred from deciding it.</small></div>

      <div class="ff ff-wide"><label>Subject *</label>
        <input class="form-control" name="subject" required maxlength="255"
               value="<?= $appealOf ? e('Appeal against ' . $appealOf['ref']) : '' ?>"
               placeholder="one line — what they say went wrong"></div>
      <div class="ff ff-wide"><label>What they told us *</label>
        <textarea class="form-control" name="description" rows="8" required
                  placeholder="in their words as far as possible, not a summary of your view of it"></textarea></div>
    </div>
  </div>
  <div class="form-actions"><button class="btn" type="submit">Record it</button>
    <a class="btn secondary" href="/complaints">Cancel</a></div>
</form>
