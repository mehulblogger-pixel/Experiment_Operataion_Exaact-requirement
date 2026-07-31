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

<?php if (!empty($gate)):
  // A deal held at a gate says so here, on its own screen. Finding out only by
  // pressing Move again and being refused is how a control gets described as
  // "the system is broken".
  $gTo = function_exists('stage_row') ? stage_row((int)$gate['to_stage_id']) : null; ?>
  <div class="msg msg-warning" style="margin-top:12px">
    <b>Held for approval.</b>
    The move to <b><?= e((string)($gTo['name'] ?? 'the next stage')) ?></b> was requested by
    <?= e((string)$gate['requested_by'] ?: 'somebody') ?>
    <?= trim((string)$gate['requested_at']) !== '' ? 'on ' . e(fdate(substr((string)$gate['requested_at'], 0, 10))) : '' ?>
    and is waiting on <b><?= e((string)$gateWaiting) ?></b>. Until then the deal stays where it is, and it is not in the won figures.
    <?php if (trim((string)$gate['note']) !== ''): ?><div style="margin-top:4px">“<?= e((string)$gate['note']) ?>”</div><?php endif; ?>
    <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?php if (!empty($gateCanAct)): ?>
        <form method="post" action="/approval-act" style="display:inline-flex;gap:6px;align-items:center">
          <input type="hidden" name="id" value="<?= (int)$gate['id'] ?>">
          <input type="hidden" name="back" value="/opportunity?id=<?= (int)$o['id'] ?>">
          <input class="form-control" style="width:220px" name="remarks" placeholder="Remark (required to send back)" maxlength="500">
          <button class="btn small" name="decision" value="APPROVED">Approve the move</button>
          <button class="btn small secondary" name="decision" value="REJECTED">Send back</button>
        </form>
      <?php else: ?>
        <form method="post" action="/approval-act" style="display:inline"
              onsubmit="return confirm('Withdraw this request? The deal stays where it is.')">
          <input type="hidden" name="id" value="<?= (int)$gate['id'] ?>">
          <input type="hidden" name="back" value="/opportunity?id=<?= (int)$o['id'] ?>">
          <button class="btn small secondary" name="decision" value="CANCELLED">Withdraw the request</button>
        </form>
      <?php endif; ?>
      <a class="btn small secondary" href="/approvals">All approvals</a>
    </div>
  </div>
<?php endif; ?>

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

<?php // ---- Won: hand it to operations -------------------------------------
      // The join between selling and doing. It was empty on all 160 existing
      // orders because nothing ever made an order FROM a sale. This does.
      // Hidden entirely when operations is not installed — a Sales-only
      // customer must never be shown work their installation cannot do. ?>
<?php if ($o['status'] === 'WON' && $canOrder): ?>
  <?php if (!empty($o['call_id'])): ?>
    <div class="msg msg-success" style="margin-top:16px">
      Order raised from this deal — <a href="/call?id=<?= (int)$o['call_id'] ?>"><b>open it</b></a>.
    </div>
  <?php elseif ($orderBlock !== '' && $orderBlock !== 'not-installed'): ?>
    <?php // A reason to stop is only useful with a way forward. The commonest
          // block by far is "no customer yet" on a deal that came from a lead,
          // and the lead is exactly where the conversion lives. ?>
    <div class="msg msg-warning" style="margin-top:16px"><?= e($orderBlock) ?>
      <?php if (empty($o['partner_id']) && !empty($o['lead_id'])): ?>
        <a href="/lead?id=<?= (int)$o['lead_id'] ?>"><b>Convert the lead</b></a> — it makes the customer and fills this in.
      <?php endif; ?>
      <?php if (empty($o['partner_id']) && $canEdit && !empty($clients)): ?>
        <form method="post" action="/opportunity-edit" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
          <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
          <div class="ff" style="flex:1;min-width:220px"><label for="opp-cust">Or pick one already on the master</label>
            <select id="opp-cust" name="partner_id" class="searchable">
              <option value="">—</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <button class="btn small">Set the customer</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <form method="post" action="/opportunity-raise-order" class="panel" style="margin-top:16px;border-left:3px solid var(--brand)">
      <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
      <h3 style="margin-top:0">Raise the order</h3>
      <p class="muted" style="font-size:13px;margin:0 0 10px">
        Carries the customer, the quotation, the agreed value and the branch straight onto the order, so the sale and the work are joined.
        <?php if ($orderQuote): ?>
          It will carry <b><?= e($orderQuote['quote_no']) ?><?= (int)$orderQuote['rev'] ? ' r' . (int)$orderQuote['rev'] : '' ?></b>
          (<?= e($orderQuote['status']) ?>, <?= e(fmoney($orderQuote['total_amount'])) ?>)<?php
            if (strtoupper((string)$orderQuote['status']) !== 'ACCEPTED'): ?> — <b>not marked accepted</b>, so check it is the right one<?php endif; ?>.
        <?php else: ?>
          <b>No quotation is attached</b>, so the order will carry the estimate and somebody will have to set the rate on it.
        <?php endif; ?>
      </p>
      <div class="form-grid" style="gap:12px 16px">
        <div><label>Executing branch</label>
          <select class="form-control" name="executing_office_id">
            <option value="">— <?= e($o['office_name'] ?: 'not set') ?> —</option>
            <?php foreach ($offices as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id']===(int)$o['office_id']?'selected':'' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>Contract number</label><input class="form-control" name="contract_number" maxlength="80" value="<?= e($orderQuote['contract_number'] ?? '') ?>" placeholder="If they gave one"><?php if (!empty($orderQuote['contract_number'])): ?><span class="muted" style="font-size:12px">Carried from the quotation.</span><?php endif; ?></div>
        <div><label>Wanted by</label><input class="form-control" type="date" name="inspection_required_date"></div>
      </div>
      <button class="btn" style="margin-top:12px">Raise the order</button>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php if ($canEdit && $open):
  $curStageName = '';
  foreach ($stages as $s) if ((int)$s['id'] === (int)$o['stage_id']) $curStageName = $s['name'];
?>
<form method="post" action="/opportunity-move" class="panel" style="margin-top:16px">
  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
  <h3 style="margin-top:0">Where is this deal — and where next?</h3>
  <?php // Reported: "Move it does nothing / is very confusing." So the panel now
        // names where the deal sits, says what a move means, and only asks for a
        // lost reason once a losing stage is actually chosen. ?>
  <p style="margin:0 0 4px">Right now it is at <b><?= e($curStageName ?: '—') ?></b>.</p>
  <p class="muted" style="margin:0 0 14px;font-size:13px">Pick the stage this deal has genuinely reached and press <b>Move it</b>.
    Move <b>forward</b> as it progresses. <b>Won</b> closes it as a sale and lets you raise the order. <b>Lost</b> closes it — and asks why, so the pipeline learns from it.</p>
  <div class="form-grid" style="gap:12px 16px">
    <div><label for="opp-stage">Move it to</label>
      <select class="form-control" name="stage_id" id="opp-stage">
        <?php foreach ($stages as $s): ?>
          <option value="<?= (int)$s['id'] ?>" data-kind="<?= e($s['kind']) ?>" <?= (int)$s['id']===(int)$o['stage_id']?'selected':'' ?>>
            <?= e($s['name']) ?><?= (int)$s['probability'] ? ' (' . (int)$s['probability'] . '%)' : '' ?><?= $s['kind']==='WON' ? ' — closes it as a sale' : ($s['kind']==='LOST' ? ' — closes it as lost' : '') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">The one it is on now is where it stays if you change nothing.</span></div>
  </div>
  <div id="opp-lost" style="display:none;margin-top:10px;border-left:3px solid var(--bad,#dc2626);padding-left:12px">
    <p class="muted" style="font-size:12.5px;margin:0 0 8px">You are closing this as <b>lost</b>. A reason is required — it is the only thing that makes a lost deal tell you anything later.</p>
    <div class="form-grid" style="gap:12px 16px">
      <div class="ff"><label>Why it was lost</label>
        <select class="form-control" name="lost_reason"><option value="">choose a reason…</option>
          <?php foreach ($lostReasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Lost to</label><input class="form-control" name="lost_to" maxlength="200" placeholder="Which competitor, if you know"></div>
      <div class="ff-wide"><label>What actually happened</label><input class="form-control" name="lost_note" maxlength="500" placeholder="Optional, but useful"></div>
    </div>
  </div>
  <button class="btn" style="margin-top:12px">Move it</button>
</form>
<script>
// Show the "why lost" fields only when a losing stage is picked, so a normal
// forward move is one dropdown and one button — not a wall of fields about loss.
(function () {
  var sel = document.getElementById('opp-stage'), lost = document.getElementById('opp-lost');
  if (!sel || !lost) return;
  function sync() {
    var o = sel.options[sel.selectedIndex];
    lost.style.display = (o && o.getAttribute('data-kind') === 'LOST') ? 'block' : 'none';
  }
  sel.addEventListener('change', sync); sync();
})();
</script>

<form method="post" action="/opportunity-edit" class="panel" style="margin-top:16px">
  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
  <h3 style="margin-top:0">Change the detail</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div class="ff-wide"><label>Name</label><input class="form-control" name="name" value="<?= e($o['name']) ?>" maxlength="255"></div>
    <div class="ff"><label for="opp-partner">Customer</label>
      <select class="form-control searchable" id="opp-partner" name="partner_id">
        <option value="">— not on the master yet —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===(int)$o['partner_id']?'selected':'' ?>><?= e($c['display_name'] ?: $c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">A deal from a lead has none until the lead is converted. The order cannot be raised without it.</span></div>
    <div><label>Estimated value</label><input class="form-control" name="value" type="number" step="0.01" value="<?= e($o['value']) ?>"></div>
    <div><label>Expected close</label><input class="form-control" type="date" name="expected_close" value="<?= e($o['expected_close']) ?>"></div>
    <div class="ff"><label>Allocated to</label>
      <select class="form-control searchable" name="owner_user_id">
        <option value="">— nobody yet —</option>
        <?php foreach (($users ?? []) as $uu): ?>
          <option value="<?= (int)$uu['id'] ?>" <?= (int)($o['owner_user_id'] ?? 0) === (int)$uu['id'] ? 'selected' : '' ?>>
            <?= e(trim($uu['first_name'] . ' ' . $uu['last_name']) ?: $uu['username']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (trim((string)($o['owner_name'] ?? '')) !== '' && !(int)($o['owner_user_id'] ?? 0)): ?>
        <div class="ff-help">Held against the typed name “<?= e($o['owner_name']) ?>”, which is not linked to a login.
          Pick the person and it becomes a real allocation.</div>
      <?php endif; ?></div>

    <?php // The pipeline was fixed at creation and could never be changed, so one
          // wrong click meant abandoning the deal and re-keying it. Changing it
          // moves the deal to the first open stage of the new pipeline, because a
          // stage from one pipeline on a deal in another is what makes every
          // board and forecast disagree. Only while the deal is open. ?>
    <?php if (($o['status'] ?? 'OPEN') === 'OPEN' && !empty($pipelines)): ?>
      <div class="ff"><label>Pipeline</label>
        <select class="form-control" name="pipeline_id">
          <?php foreach ($pipelines as $pl): ?>
            <option value="<?= (int)$pl['id'] ?>" <?= (int)$pl['id'] === (int)$o['pipeline_id'] ? 'selected' : '' ?>>
              <?= e($pl['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="ff-help">Changing this moves the deal to the first open stage of the pipeline you pick, and the
          move is written into its history.</div></div>
    <?php endif; ?>
    <div><label>Branch</label>
      <select class="form-control" name="office_id"><option value="">—</option>
        <?php foreach ($offices as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id']===(int)$o['office_id']?'selected':'' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Against</label><input class="form-control" name="competitor" value="<?= e($o['competitor']) ?>" maxlength="200"></div>
    <div><label>Contact</label><input class="form-control" name="contact_name" value="<?= e($o['contact_name']) ?>" maxlength="150"></div>
    <div><label>Contact e-mail</label><input class="form-control" name="contact_email" value="<?= e($o['contact_email']) ?>" maxlength="200"></div>
    <div><label>Contact phone</label><input class="form-control" name="contact_phone" value="<?= e($o['contact_phone']) ?>" maxlength="60"></div>
    <div><label>Next action</label><input class="form-control" name="next_action" value="<?= e($o['next_action']) ?>" maxlength="255"></div>
    <div><label>By when</label><input class="form-control" type="date" name="next_action_on" value="<?= e($o['next_action_on']) ?>"></div>
    <div class="ff-wide"><label>What they need</label><textarea class="form-control" name="requirement" rows="3"><?= e($o['requirement']) ?></textarea></div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">Save</button>
  </div>
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
