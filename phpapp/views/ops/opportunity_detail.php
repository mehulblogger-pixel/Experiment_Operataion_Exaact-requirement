<?php $open = $o['status'] === 'OPEN'; ?>
<?= function_exists('chain_strip') ? chain_strip('OPPORTUNITY', (int)$o['id'], 'OPPORTUNITY', (int)$o['id']) : '' ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/opportunities">Opportunities</a> › <?= e($o['ref']) ?></div>
<div class="master-head"><div>
  <h1><?= e($o['name']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($o['ref']) ?>
    <span class="pill <?= $o['status']==='WON'?'p-ok':($o['status']==='LOST'?'p-bad':'p-warn') ?>"><?= e(OPP_STATUS[$o['status']] ?? $o['status']) ?></span>
    · <?= $o['partner_id'] ? '<a href="/ledger?id=' . (int)$o['partner_id'] . '">' . e($o['client_name'] ?: $o['partner_name']) . '</a>' : e($o['partner_name'] ?: 'no customer yet') ?>
    <?= $o['stage_name'] ? ' · ' . e($o['stage_name']) : '' ?>
  </p></div>
</div>

<?php if ($stalled): ?>
  <div class="msg msg-warning" style="margin-top:12px">
    It has sat in <b><?= e($o['stage_name']) ?></b> for <?= (int)$days ?> days, past the <?= (int)$o['sla_days'] ?> that stage allows. Move it on or record why it is stuck.
  </div>
<?php endif; ?>

<?php if ($o['status'] === 'LOST'): ?>
  <div class="msg msg-error" style="margin-top:12px">
    Lost — <?= e($lostReasons[$o['lost_reason']] ?? $o['lost_reason']) ?><?= $o['lost_to'] ? ' to ' . e($o['lost_to']) : '' ?>.
    <?= e($o['lost_note']) ?>
  </div>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div class="panel">
    <h3 style="margin-top:0">The deal</h3>
    <div class="kv-grid">
      <div><span class="k">Estimate</span><span><?= (float)$o['value'] ? e(fmoney($o['value'])) : '—' ?></span></div>
      <div><span class="k">Probability</span><span><?= (int)($o['probability'] ?: $o['stage_prob']) ?>%</span></div>
      <div><span class="k">Weighted</span><span><b><?= e(fmoney(opp_weighted($o))) ?></b></span></div>
      <div><span class="k">Expected close</span><span><?= $o['expected_close'] ? e(fdate($o['expected_close'])) : '—' ?></span></div>
      <div><span class="k">Owner</span><span><?= e($o['owner_name'] ?: '—') ?></span></div>
      <div><span class="k">Branch</span><span><?= e($o['office_name'] ?: '—') ?></span></div>
      <div><span class="k">Against</span><span><?= e($o['competitor'] ?: '—') ?></span></div>
      <div><span class="k">In this stage</span><span><?= (int)$days ?> days</span></div>
      <?php if ($o['lead_id']): ?>
        <div><span class="k">Came from</span><span><a href="/lead?id=<?= (int)$o['lead_id'] ?>">the lead</a></span></div>
      <?php endif; ?>
    </div>
    <?php if (trim((string)$o['requirement']) !== ''): ?>
      <p class="muted" style="font-size:13px;margin:12px 0 0;white-space:pre-wrap"><?= e($o['requirement']) ?></p>
    <?php endif; ?>
    <?php if ($o['next_action']): ?>
      <p style="margin:12px 0 0"><b>Next:</b> <?= e($o['next_action']) ?><?= $o['next_action_on'] ? ' <span class="muted">by ' . e(fdate($o['next_action_on'])) . '</span>' : '' ?></p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 style="margin-top:0">Quotations on this deal <span class="muted" style="font-weight:400;font-size:13px">(<?= count($quotes) ?>)</span></h3>
    <p class="muted" style="font-size:13px;margin:0 0 10px">Three options and a revision are four quotations and one deal. Attaching them here is what stops the forecast counting the same business four times.</p>
    <?php if ($quotes): ?>
      <table class="dt">
        <caption class="sr-only">Quotations attached to this opportunity</caption>
        <thead><tr><th scope="col">Quotation</th><th scope="col">Status</th><th scope="col" class="num">Value</th><?php if ($canEdit && $open): ?><th scope="col"></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($quotes as $q): ?>
          <tr>
            <td><a href="/quote?id=<?= (int)$q['id'] ?>"><?= e($q['quote_no']) ?><?= (int)$q['rev'] ? ' r' . (int)$q['rev'] : '' ?></a></td>
            <td><span class="pill p-mut"><?= e($q['status']) ?></span></td>
            <td class="num"><?= e(fmoney($q['total_amount'])) ?></td>
            <?php if ($canEdit && $open): ?>
              <td><form method="post" action="/opportunity-quote" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <input type="hidden" name="quotation_id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="act" value="unlink">
                <button class="btn small secondary">Detach</button>
              </form></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="muted" style="margin:0">None yet. An opportunity with no quotation is normal — it is a deal you are working on, not a document.</p>
    <?php endif; ?>

    <?php if ($canEdit && $open && $openQuotes): ?>
      <form method="post" action="/opportunity-quote" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
        <div class="ff" style="flex:1;min-width:220px"><label for="oq">Attach a quotation</label>
          <select id="oq" name="quotation_id" class="searchable">
            <?php foreach ($openQuotes as $q): ?>
              <option value="<?= (int)$q['id'] ?>"><?= e($q['quote_no']) ?><?= (int)$q['rev'] ? ' r' . (int)$q['rev'] : '' ?> — <?= e(fmoney($q['total_amount'])) ?> (<?= e($q['status']) ?>)</option>
            <?php endforeach; ?>
          </select></div>
        <button class="btn small">Attach</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($canEdit && $open): ?>
<form method="post" action="/opportunity-move" class="panel" style="margin-top:16px">
  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
  <h3 style="margin-top:0">Move it on</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>To which stage</label>
      <select name="stage_id" id="opp-stage">
        <?php foreach ($stages as $s): ?>
          <option value="<?= (int)$s['id'] ?>" data-kind="<?= e($s['kind']) ?>" <?= (int)$s['id']===(int)$o['stage_id']?'selected':'' ?>>
            <?= e($s['name']) ?><?= (int)$s['probability'] ? ' (' . (int)$s['probability'] . '%)' : '' ?><?= $s['kind']!=='OPEN' ? ' — closes the deal' : '' ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div><label>If lost, why *</label>
      <select name="lost_reason"><option value="">—</option>
        <?php foreach ($lostReasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">Required to move to a losing stage. It is the only thing that makes the loss useful later.</span></div>
    <div><label>Lost to</label><input name="lost_to" maxlength="200" placeholder="Which competitor, if you know"></div>
    <div class="ff-wide"><label>Note</label><input name="lost_note" maxlength="500" placeholder="What actually happened"></div>
  </div>
  <button class="btn" style="margin-top:12px">Move it</button>
</form>

<form method="post" action="/opportunity-edit" class="panel" style="margin-top:16px">
  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
  <h3 style="margin-top:0">Change the detail</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>Name</label><input name="name" value="<?= e($o['name']) ?>" maxlength="255"></div>
    <div><label>Estimated value</label><input name="value" type="number" step="0.01" value="<?= e($o['value']) ?>"></div>
    <div><label>Expected close</label><input type="date" name="expected_close" value="<?= e($o['expected_close']) ?>"></div>
    <div><label>Owner</label><input name="owner_name" value="<?= e($o['owner_name']) ?>" maxlength="150"></div>
    <div><label>Branch</label>
      <select name="office_id"><option value="">—</option>
        <?php foreach ($offices as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id']===(int)$o['office_id']?'selected':'' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Against</label><input name="competitor" value="<?= e($o['competitor']) ?>" maxlength="200"></div>
    <div><label>Contact</label><input name="contact_name" value="<?= e($o['contact_name']) ?>" maxlength="150"></div>
    <div><label>Contact e-mail</label><input name="contact_email" value="<?= e($o['contact_email']) ?>" maxlength="200"></div>
    <div><label>Contact phone</label><input name="contact_phone" value="<?= e($o['contact_phone']) ?>" maxlength="60"></div>
    <div><label>Next action</label><input name="next_action" value="<?= e($o['next_action']) ?>" maxlength="255"></div>
    <div><label>By when</label><input type="date" name="next_action_on" value="<?= e($o['next_action_on']) ?>"></div>
    <div class="ff-wide"><label>What they need</label><textarea name="requirement" rows="3"><?= e($o['requirement']) ?></textarea></div>
  </div>
  <button class="btn" style="margin-top:12px">Save</button>
</form>
<?php endif; ?>

<?php if ($history): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">How it moved</h3>
  <table class="dt">
    <caption class="sr-only">Stage history</caption>
    <thead><tr><th scope="col">When</th><th scope="col">From</th><th scope="col">To</th><th scope="col" class="num">Days there</th><th scope="col">By</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td><?= e(fdate(substr((string)$h['moved_at'],0,10))) ?></td>
          <td><?= e($h['from_name'] ?: '—') ?></td><td><?= e($h['to_name']) ?></td>
          <td class="num"><?= (int)$h['days_in_previous'] ?></td><td><?= e($h['moved_by']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
