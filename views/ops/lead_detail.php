<?php $open = $l['status'] === 'OPEN'; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/leads">Leads</a> › <?= e($l['ref']) ?></div>
<?= function_exists('chain_strip') ? chain_strip('LEAD', (int)$l['id'], 'LEAD', (int)$l['id']) : '' ?>
<div class="master-head"><div>
  <h1><?= e($l['company_name']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($l['ref']) ?>
    <span class="pill <?= $l['status']==='CONVERTED'?'p-ok':($l['status']==='LOST'?'p-bad':'p-warn') ?>"><?= e(LEAD_STATUS[$l['status']] ?? $l['status']) ?></span>
    <?php if ($l['stage_name']): ?><span class="pill p-mut"><?= e($l['stage_name']) ?></span><?php endif; ?>
    <?php if ($stalled): ?><span class="pill p-bad"><?= (int)$days ?> days in this stage — past its <?= (int)$l['sla_days'] ?>-day allowance</span><?php endif; ?>
  </p></div></div>

<div class="panel" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">Score <span class="pill <?= $score['score']>=60?'p-ok':($score['score']>=35?'p-warn':'p-mut') ?>"><?= (int)$score['score'] ?></span></h3>
    <span class="muted" style="font-size:12.5px">A rules engine, not a guess — every rule that fired is listed.</span>
  </div>
  <ul style="margin:8px 0 0;padding-left:20px">
    <?php foreach ($score['why'] as $w): ?><li style="font-size:13.5px"><?= e($w) ?></li><?php endforeach; ?>
  </ul>
</div>

<div class="panel" style="margin-top:16px">
  <table class="grid">
    <tr><th>Contact</th><td><?= e($l['contact_name'] ?: '—') ?></td><th>E-mail</th><td><?= e($l['contact_email'] ?: '—') ?></td></tr>
    <tr><th>Telephone</th><td><?= e($l['contact_phone'] ?: '—') ?></td><th>Source</th><td><?= e($l['source'] ?: '—') ?></td></tr>
    <tr><th>Value</th><td><?= $l['value'] ? fmoney($l['value']) : '—' ?></td><th>Expected close</th><td><?= $l['expected_close'] ? e(fdate($l['expected_close'])) : '—' ?></td></tr>
    <tr><th>Owner</th><td><?= e($l['owner_name'] ?: '—') ?></td><th>Branch</th><td><?= e($l['office_name'] ?: '—') ?></td></tr>
    <tr><th>Next thing to do</th><td colspan="3"><?= e($l['next_action'] ?: '—') ?><?= $l['next_action_on'] ? ' — by ' . e(fdate($l['next_action_on'])) : '' ?></td></tr>
    <?php if ($l['converted_partner_id']): ?>
      <tr><th>Became</th><td colspan="3"><a href="/client?id=<?= (int)$l['converted_partner_id'] ?>">the customer record</a>
        <?php if ($l['converted_inquiry_id']): ?> · <a href="/inquiries">an inquiry</a><?php endif; ?>
        on <?= e(fdate(substr((string)$l['converted_at'],0,10))) ?> by <?= e($l['converted_by']) ?></td></tr>
    <?php endif; ?>
    <?php if ($l['lost_reason']): ?>
      <tr><th>Lost because</th><td colspan="3"><?= e($lostReasons[$l['lost_reason']] ?? $l['lost_reason']) ?>
        <?= $l['lost_note'] ? '<br><span class="muted">' . e($l['lost_note']) . '</span>' : '' ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if (trim((string)$l['requirement']) !== ''): ?>
    <h4 style="margin:14px 0 4px">What they want</h4>
    <p style="white-space:pre-wrap;margin:0"><?= e($l['requirement']) ?></p>
  <?php endif; ?>
</div>

<?php if ($canEdit && $open): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Move it on</h3>
  <p class="muted" style="margin:0 0 10px">Moving to a <b>won</b> stage converts the lead — it becomes a customer and an inquiry. Moving to <b>lost</b> needs a reason, so win/loss analysis means something.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($stages as $s): if ((int)$s['id'] === (int)$l['stage_id']) continue; ?>
      <?php if ($s['kind'] === 'LOST'): ?>
        <form method="post" action="/lead-move" style="display:flex;gap:6px;align-items:center">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
          <select name="lost_reason" required style="max-width:200px">
            <option value="">why was it lost?</option>
            <?php foreach ($lostReasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
          <input name="lost_note" placeholder="anything more" style="max-width:180px">
          <button class="btn small secondary"><?= e($s['name']) ?></button>
        </form>
      <?php else: ?>
        <form method="post" action="/lead-move" style="display:inline">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
          <button class="btn small<?= $s['kind']==='WON'?'':' secondary' ?>"><?= e($s['name']) ?><?= $s['kind']==='WON'?' →':'' ?></button>
        </form>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" action="/lead-edit" class="panel" style="margin-top:16px;max-width:860px">
  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
  <h3 style="margin-top:0">Details</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>Company</label><input name="company_name" value="<?= e($l['company_name']) ?>"></div>
    <div><label>Contact</label><input name="contact_name" value="<?= e($l['contact_name']) ?>"></div>
    <div><label>Source</label><input name="source" value="<?= e($l['source']) ?>"></div>
    <div><label>E-mail</label><input type="email" name="contact_email" value="<?= e($l['contact_email']) ?>"></div>
    <div><label>Telephone</label><input name="contact_phone" value="<?= e($l['contact_phone']) ?>"></div>
    <div><label>Value</label><input type="number" step="0.01" name="value" value="<?= e($l['value']) ?>"></div>
    <div><label>Expected close</label><input type="date" name="expected_close" value="<?= e($l['expected_close']) ?>"></div>
    <div><label>Owner</label><input name="owner_name" value="<?= e($l['owner_name']) ?>"></div>
    <div><label>Next by</label><input type="date" name="next_action_on" value="<?= e($l['next_action_on']) ?>"></div>
    <div class="ff-wide"><label>Next thing to do</label><input name="next_action" value="<?= e($l['next_action']) ?>"></div>
    <div class="ff-wide"><label>What they want</label><textarea name="requirement" rows="3"><?= e($l['requirement']) ?></textarea></div>
  </div>
  <button class="btn" style="margin-top:12px">Save</button>
</form>
<?php endif; ?>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">How it moved</h3>
  <?php if (!$history): ?><p class="muted" style="margin:0">It has not moved yet.</p><?php else: ?>
  <table class="dt">
    <thead><tr><th>When</th><th>From</th><th>To</th><th class="num">Days there</th><th>Who</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td><?= e(fdate(substr((string)$h['moved_at'],0,10))) ?></td>
          <td><?= e($h['from_name'] ?: '—') ?></td><td><?= e($h['to_name']) ?></td>
          <td class="num"><?= (int)$h['days_in_previous'] ?></td><td><?= e($h['moved_by']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($timeline): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Everything that happened</h3>
  <table class="dt">
    <thead><tr><th>When</th><th>What</th><th>Who</th></tr></thead>
    <tbody>
    <?php foreach ($timeline as $a): ?>
      <tr><td><?= e(fdate(substr((string)$a['occurred_at'],0,10))) ?></td>
          <td><span class="pill <?= $a['auto']?'p-mut':'p-ok' ?>" style="font-size:11px"><?= e(ACT_KINDS[$a['kind']] ?? $a['kind']) ?></span>
              <?= e($a['subject']) ?></td>
          <td><?= e($a['owner'] ?: '—') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
