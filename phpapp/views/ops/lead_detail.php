<?php
  $open = $l['status'] === 'OPEN';
  $curStage = '';
  foreach ($stages as $s) if ((int)$s['id'] === (int)$l['stage_id']) $curStage = $s['name'];
  $oppId = ($canEdit && $open && function_exists('opp_try'))
      ? opp_try(fn() => ops_val("SELECT id FROM opportunities WHERE lead_id=?", [(int)$l['id']]), null) : null;
  $converted = ($l['status'] ?? '') === 'CONVERTED' || !empty($l['converted_partner_id']) || !empty($l['converted_inquiry_id']);
  $effLbl = function_exists('act_effort_label') ? act_effort_label($effort ?? []) : '';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/leads">Leads</a> › <?= e($l['ref']) ?></div>
<?= function_exists('chain_strip') ? chain_strip('LEAD', (int)$l['id'], 'LEAD', (int)$l['id']) : '' ?>

<div class="master-head"><div>
  <h1><?= e($l['company_name']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($l['ref']) ?>
    <span class="pill <?= $l['status']==='CONVERTED'?'p-ok':($l['status']==='LOST'?'p-bad':'p-warn') ?>"><?= e(LEAD_STATUS[$l['status']] ?? $l['status']) ?></span>
    <?php if ($curStage): ?><span class="pill p-mut"><?= e($curStage) ?></span><?php endif; ?>
  </p></div></div>

<?php // ---- Where it is now, and the one next thing ------------------------- ?>
<div class="nowband">
  <?php if ($converted): ?>
    <div class="step">Won — this became a customer.</div>
    <p class="next">It is now <a href="/client?id=<?= (int)$l['converted_partner_id'] ?>"><b><?= e($l['company_name']) ?> on the customer list</b></a><?php
      if ($l['converted_inquiry_id']): ?> · <a href="/inquiries">an enquiry was raised</a><?php endif; ?>.
      Carry on from there.</p>
  <?php elseif ($l['status'] === 'LOST'): ?>
    <div class="step">Closed — this one did not happen.</div>
    <?php if ($l['lost_reason']): ?><p class="next">Reason: <b><?= e($lostReasons[$l['lost_reason']] ?? $l['lost_reason']) ?></b><?= $l['lost_note'] ? ' — ' . e($l['lost_note']) : '' ?>.</p><?php endif; ?>
  <?php else: ?>
    <div class="step">This lead is at <b><?= e($curStage ?: '—') ?></b>.<?php if ($stalled): ?> <span class="pill p-bad" style="font-size:11.5px"><?= (int)$days ?> days here — overdue</span><?php endif; ?></div>
    <p class="next"><?php if (trim((string)$l['next_action']) !== ''): ?>
        <b>Next:</b> <?= e($l['next_action']) ?><?= $l['next_action_on'] ? ' <span class="muted">by ' . e(fdate($l['next_action_on'])) . '</span>' : '' ?>
      <?php else: ?>
        <span class="muted">No next step set yet — log a contact below and say what happens next.</span>
      <?php endif; ?></p>
    <?php if ($canEdit): ?>
      <div class="cta">
        <?php if ($oppId): ?>
          <a class="btn small secondary" href="/opportunity?id=<?= (int)$oppId ?>">Open the deal →</a>
        <?php elseif (function_exists('opp_can_edit') && opp_can_edit()): ?>
          <form method="post" action="/opportunity-from-lead" style="display:inline">
            <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
            <button class="btn small">Work this as a deal →</button>
          </form>
        <?php endif; ?>
        <a class="btn small secondary" href="#log">Log a contact</a>
        <?php if (!empty($canQuote)): ?><a class="btn small secondary" href="/quote-new?lead=<?= (int)$l['id'] ?>">Make a quotation</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php // ---- The essentials, plainly ---------------------------------------- ?>
<div class="panel" style="margin-top:16px">
  <table class="grid">
    <tr><th>Who to talk to</th><td><?= e($l['contact_name'] ?: '—') ?></td><th>Telephone</th><td><?= e($l['contact_phone'] ?: '—') ?></td></tr>
    <tr><th>E-mail</th><td><?= e($l['contact_email'] ?: '—') ?></td><th>Best reached by</th><td><?= e(($l['pref_contact'] ?? '') !== '' ? (LEAD_PREF_CONTACT[$l['pref_contact']] ?? $l['pref_contact']) : '—') ?></td></tr>
    <tr><th>Worth about</th><td><?= $l['value'] ? fmoney($l['value']) : '—' ?></td><th>Hoped to close</th><td><?= $l['expected_close'] ? e(fdate($l['expected_close'])) : '—' ?></td></tr>
    <tr><th>Handled by</th><td><?= e($l['owner_name'] ?: '—') ?></td><th>Branch</th><td><?= e($l['office_name'] ?: '—') ?></td></tr>
    <?php if ($effLbl !== ''): ?>
      <tr><th><?= $converted ? 'Effort to win' : 'Effort so far' ?></th><td colspan="3"><?= e($effLbl) ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if (trim((string)$l['requirement']) !== ''): ?>
    <h4 style="margin:14px 0 4px">What they want</h4>
    <p style="white-space:pre-wrap;margin:0"><?= e($l['requirement']) ?></p>
  <?php endif; ?>
</div>

<?php // ---- Log a contact — the main daily action --------------------------- ?>
<?php if ($canEdit && $open): ?>
<div class="panel" style="margin-top:16px" id="log">
  <h3 style="margin-top:0">Log a contact</h3>
  <p class="muted" style="margin:0 0 12px">Every call, message or meeting — so the story is never lost and the follow-up is never forgotten.</p>
  <form method="post" action="/lead-contact">
    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label for="c-method">How</label>
        <select class="form-control" name="method" id="c-method">
          <?php foreach ($methods as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= ($l['pref_contact'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="ff"><label for="c-dir">Which way</label>
        <select class="form-control" name="direction" id="c-dir">
          <option value="OUT">We contacted them</option>
          <option value="IN">They contacted us</option>
        </select></div>
      <div class="ff"><label for="c-when">When</label>
        <input class="form-control" type="date" name="occurred_on" id="c-when" value="<?= e($today) ?>" max="<?= e($today) ?>"></div>
      <div class="ff"><label for="c-who">Who you spoke to</label>
        <input class="form-control" name="with_whom" id="c-who" maxlength="150" value="<?= e($l['contact_name'] ?: '') ?>" placeholder="Name at their end"></div>
      <div class="ff ff-wide"><label for="c-note">What was said</label>
        <textarea class="form-control" name="note" id="c-note" rows="2" placeholder="Briefly — what they asked, what you told them, what they decided"></textarea></div>
      <div class="ff"><label for="c-outcome">How it went</label>
        <input class="form-control" name="outcome" id="c-outcome" maxlength="60" list="c-outcomes" placeholder="e.g. Interested, Call back, No answer">
        <datalist id="c-outcomes">
          <option>Interested</option><option>Wants a quotation</option><option>Call back later</option>
          <option>No answer</option><option>Not interested</option><option>Asked us to email</option>
        </datalist></div>
      <div class="ff"><label for="c-time">Time it took</label>
        <select class="form-control" name="time_spent" id="c-time">
          <option value="0">—</option>
          <option value="15">15 minutes</option><option value="30">Half an hour</option>
          <option value="45">45 minutes</option><option value="60">1 hour</option>
          <option value="90">1½ hours</option><option value="120">2 hours</option>
          <option value="240">Half a day</option><option value="480">A full day</option>
        </select></div>
      <div class="ff"><label for="c-pref">They prefer to be reached by</label>
        <select class="form-control" name="pref_contact" id="c-pref">
          <option value="">— no preference noted —</option>
          <?php foreach ($prefMethods as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= ($l['pref_contact'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="form-sec" style="margin-top:6px"><h3 style="font-size:14px">And the next step</h3>
      <p>The one thing people forget. Fill it and it drives your follow-up list; leave it if there is nothing to do yet.</p></div>
    <div class="form-grid">
      <div class="ff ff-wide"><label for="c-next">Next thing to do</label>
        <input class="form-control" name="next_action" id="c-next" maxlength="255" placeholder="e.g. Send the capability statement"></div>
      <div class="ff"><label for="c-nexton">By when</label>
        <input class="form-control" type="date" name="next_action_on" id="c-nexton" min="<?= e($today) ?>"></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Save this contact</button></div>
  </form>
</div>
<?php endif; ?>

<?php // ---- Quotations, straight off the lead ------------------------------ ?>
<?php if (!empty($canQuote) || $quotes): ?>
<div class="panel" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">Quotations <span class="muted" style="font-weight:400;font-size:13px">(<?= count($quotes) ?>)</span></h3>
    <?php if (!empty($canQuote) && $l['status'] !== 'LOST'): ?>
      <a class="btn small" href="/quote-new?lead=<?= (int)$l['id'] ?>">+ Make a quotation</a>
    <?php endif; ?>
  </div>
  <p class="muted" style="font-size:13px;margin:8px 0 0">The company, the contact and what they want come across — you will not type them again.</p>
  <?php if ($quotes): ?>
    <table class="dt" style="margin-top:10px">
      <thead><tr><th scope="col">Quotation</th><th scope="col">Status</th><th scope="col" class="num">Value</th><th scope="col">Made</th></tr></thead>
      <tbody>
      <?php foreach ($quotes as $qq): ?>
        <tr>
          <td><a href="/quote?id=<?= (int)$qq['id'] ?>"><?= e($qq['quote_no']) ?><?= (int)$qq['rev'] ? ' r' . (int)$qq['rev'] : '' ?></a></td>
          <td><span class="pill p-mut"><?= e($qq['status']) ?></span></td>
          <td class="num"><?= e(fmoney($qq['total_amount'])) ?></td>
          <td><?= e(fdate(substr((string)$qq['created_at'],0,10))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted" style="margin:10px 0 0">None yet.<?= (!empty($canQuote) && $l['status'] !== 'LOST') ? ' Use <b>Make a quotation</b> when you are ready to price it.' : '' ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- Move it to the next step ---------------------------------------- ?>
<?php if ($canEdit && $open):
  $fwd = array_values(array_filter($stages, fn($s) => $s['kind']==='OPEN' && (int)$s['id']!==(int)$l['stage_id']));
  $won = array_values(array_filter($stages, fn($s) => $s['kind']==='WON'));
  $lost= array_values(array_filter($stages, fn($s) => $s['kind']==='LOST'));
?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">Move it to the next step</h3>
  <p class="muted" style="margin:0 0 14px;font-size:13px">Move it <b>forward</b> only when something really changed. <b>They said yes</b> makes them a customer. <b>It did not happen</b> closes it, and asks why.</p>

  <?php if ($fwd): ?>
    <div class="form-sec" style="margin-top:0"><h3 style="font-size:14px">Move it forward</h3></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
      <?php foreach ($fwd as $s): ?>
        <form method="post" action="/lead-move" style="display:inline">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
          <button class="btn small secondary"><?= e($s['name']) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($won): ?>
    <div class="form-sec"><h3 style="font-size:14px">They said yes</h3>
      <p>Makes them a customer and raises the enquiry — you confirm the details on the next screen.</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
      <?php foreach ($won as $s): ?>
        <form method="post" action="/lead-move" style="display:inline">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
          <button class="btn small"><?= e($s['name']) ?> →</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($lost): ?>
    <div class="form-sec"><h3 style="font-size:14px">It did not happen</h3>
      <p>Pick the closest reason — it is the only thing that makes a lost lead tell you anything later.</p></div>
    <?php foreach ($lost as $s): ?>
      <form method="post" action="/lead-move" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:8px">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
        <div class="ff" style="margin:0"><label style="font-size:12px">Why it was lost</label>
          <select class="form-control" name="lost_reason" required style="max-width:220px">
            <option value="">choose a reason…</option>
            <?php foreach ($lostReasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff" style="margin:0"><label style="font-size:12px">Anything more</label>
          <input class="form-control" name="lost_note" placeholder="optional" style="max-width:200px"></div>
        <button class="btn small secondary"><?= e($s['name']) ?></button>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- Folded away: the detail you only need sometimes ----------------- ?>
<details class="fold">
  <summary>How promising is it? <span class="sub">score <?= (int)$score['score'] ?>/100</span></summary>
  <div class="fold-body">
    <p class="muted" style="margin:0 0 8px;font-size:12.5px">A rules engine, not a guess — every rule that fired is listed.</p>
    <ul style="margin:0;padding-left:20px">
      <?php foreach ($score['why'] as $w): ?><li style="font-size:13.5px"><?= e($w) ?></li><?php endforeach; ?>
    </ul>
  </div>
</details>

<?php if ($canEdit && $open): ?>
<details class="fold">
  <summary>Edit the details</summary>
  <div class="fold-body">
    <form method="post" action="/lead-edit">
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
      <div class="form-grid">
        <div class="ff ff-wide"><label>Company</label>
          <input class="form-control" name="company_name" value="<?= e($l['company_name']) ?>"></div>
        <div class="ff"><label>Contact</label>
          <input class="form-control" name="contact_name" value="<?= e($l['contact_name']) ?>"></div>
        <div class="ff"><label>Source</label>
          <input class="form-control" name="source" value="<?= e($l['source']) ?>"></div>
        <div class="ff"><label>E-mail</label>
          <input class="form-control" type="email" inputmode="email" name="contact_email" value="<?= e($l['contact_email']) ?>"></div>
        <div class="ff"><label>Telephone</label>
          <input class="form-control" type="tel" inputmode="tel" name="contact_phone" value="<?= e($l['contact_phone']) ?>"></div>
        <div class="ff"><label>Worth about <span class="muted">(<?= e(cur_sym()) ?>)</span></label>
          <input class="form-control" type="number" step="0.01" name="value" value="<?= e($l['value']) ?>"></div>
        <div class="ff"><label>Hoped to close</label>
          <input class="form-control" type="date" name="expected_close" value="<?= e($l['expected_close']) ?>"></div>
        <div class="ff"><label>Handled by</label>
          <select class="form-control searchable" name="owner_user_id">
            <option value="">— nobody yet —</option>
            <?php foreach (($users ?? []) as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= (int)($l['owner_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) ?><?= $u['role'] ? ' — ' . e(ORG_ROLES[$u['role']] ?? $u['role']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (trim((string)($l['owner_name'] ?? '')) !== '' && !(int)($l['owner_user_id'] ?? 0)): ?>
            <div class="ff-help">Held against the typed name “<?= e($l['owner_name']) ?>”, not linked to a login. Pick the person to make it a real allocation.</div>
          <?php endif; ?></div>
        <div class="ff"><label>Best reached by</label>
          <select class="form-control" name="pref_contact">
            <option value="">— not noted —</option>
            <?php foreach (($prefMethods ?? []) as $k=>$v): ?>
              <option value="<?= e($k) ?>" <?= ($l['pref_contact'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="ff"><label>Next by</label>
          <input class="form-control" type="date" name="next_action_on" value="<?= e($l['next_action_on']) ?>"></div>
        <div class="ff ff-wide"><label>Next thing to do</label>
          <input class="form-control" name="next_action" value="<?= e($l['next_action']) ?>"></div>
        <div class="ff ff-wide"><label>What they want</label>
          <textarea class="form-control" name="requirement" rows="3"><?= e($l['requirement']) ?></textarea></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">Save</button></div>
    </form>
  </div>
</details>
<?php endif; ?>

<details class="fold">
  <summary>Its history <span class="sub"><?= count($timeline ?? []) ?> logged · <?= count($history ?? []) ?> move<?= count($history ?? []) === 1 ? '' : 's' ?></span></summary>
  <div class="fold-body" id="timeline">
    <h4 style="margin:0 0 8px">What's happened</h4>
    <?php if ($timeline): ?>
    <table class="dt">
      <thead><tr><th>When</th><th>What</th><th>Notes</th><th>Who</th></tr></thead>
      <tbody>
      <?php foreach ($timeline as $a): ?>
        <tr><td style="white-space:nowrap"><?= e(fdate(substr((string)$a['occurred_at'],0,10))) ?></td>
            <td><span class="pill <?= $a['auto']?'p-mut':'p-ok' ?>" style="font-size:11px"><?= e(ACT_KINDS[$a['kind']] ?? $a['kind']) ?></span>
                <?php if (!empty($a['direction'])): ?><span class="muted" style="font-size:11px"><?= $a['direction']==='IN'?'⭠ in':'⭢ out' ?></span><?php endif; ?>
                <?= e($a['subject']) ?>
                <?php if (!empty($a['outcome'])): ?> <span class="pill p-warn" style="font-size:11px"><?= e($a['outcome']) ?></span><?php endif; ?></td>
            <td class="muted" style="font-size:12.5px;max-width:340px"><?= nl2br(e((string)($a['body'] ?? ''))) ?></td>
            <td style="white-space:nowrap"><?= e($a['owner'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="muted" style="margin:0">Nothing logged yet.</p>
    <?php endif; ?>

    <h4 style="margin:18px 0 8px">How it moved</h4>
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
</details>

<?php if (can('mod.leads.edit') || is_master()): ?>
<details class="fold">
  <summary>Remove this lead</summary>
  <div class="fold-body">
    <?php if ($converted): ?>
      <p class="muted" style="margin:0">This lead was won and converted, so it is the record of where <?= e($l['company_name']) ?> came from and cannot be deleted. If it should not be chased any further, mark it lost above — the history stays either way.</p>
    <?php else: ?>
      <p class="muted" style="margin:0 0 10px">Removes <?= e($l['ref']) ?> — <?= e($l['company_name']) ?> for good. Nothing else points at it yet, so nothing downstream breaks. This cannot be undone.</p>
      <form method="post" action="/lead-delete"
            onsubmit="return confirm('Delete <?= e(addslashes($l['ref'])) ?> — <?= e(addslashes($l['company_name'])) ?>? This cannot be undone.')">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="btn danger" type="submit">Delete this lead</button>
      </form>
    <?php endif; ?>
  </div>
</details>
<?php endif; ?>
