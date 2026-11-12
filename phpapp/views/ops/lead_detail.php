<?php
  $open = $l['status'] === 'OPEN';
  $curStage = '';
  foreach ($stages as $s) if ((int)$s['id'] === (int)$l['stage_id']) $curStage = $s['name'];
  $oppId = ($canEdit && $open && function_exists('opp_try'))
      ? opp_try(fn() => ops_val("SELECT id FROM opportunities WHERE lead_id=?", [(int)$l['id']]), null) : null;
  $converted = ($l['status'] ?? '') === 'CONVERTED' || !empty($l['converted_partner_id']) || !empty($l['converted_inquiry_id']);
  $effLbl = function_exists('act_effort_label') ? act_effort_label($effort ?? []) : '';
?>
<style>
  /* Owner, 12 Nov 2026 — where the lead is, in three plain steps. */
  .lead-steps{display:flex;gap:6px;list-style:none;margin:10px 0 0;padding:0;flex-wrap:wrap}
  .lead-steps li{display:flex;align-items:center;gap:6px;padding:6px 12px 6px 6px;border-radius:999px;background:var(--soft,#f1f4f8);color:var(--muted,#5b6b6a);font-size:13.5px;font-weight:600}
  .lead-steps li span{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fff;border:1px solid var(--line,#d5dde3);font-size:12px}
  .lead-steps li.done{color:#0f7d5a}.lead-steps li.done span{background:#0f7d5a;border-color:#0f7d5a;color:#fff}
  .lead-steps li.now{background:#e8efff;color:#1d3fb8}.lead-steps li.now span{background:#1d3fb8;border-color:#1d3fb8;color:#fff}
</style>
<?php // The from-lead-to-payment strip. A new lead has nothing after it, so the
      // strip was nine empty boxes that pushed the next step off a phone screen
      // (owner, 12 Nov 2026). It appears once something follows the lead. ?>
<?php $hasNext = !$open || !empty($quotes) || $oppId
        || (function_exists('opp_try') && opp_try(fn() => ops_val("SELECT 1 FROM opportunities WHERE lead_id=?", [(int)$l['id']]), null)); ?>
<?= ($hasNext && function_exists('chain_strip')) ? chain_strip('LEAD', (int)$l['id'], 'LEAD', (int)$l['id']) : '' ?>

<div class="master-head"><div>
  <h1><?= e($l['company_name']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($l['ref']) ?>
    <span class="pill <?= $l['status']==='CONVERTED'?'p-ok':($l['status']==='LOST'?'p-bad':'p-warn') ?>"><?= e(LEAD_STATUS[$l['status']] ?? $l['status']) ?></span>
    <?php if ($curStage): ?><span class="pill p-mut"><?= e($curStage) ?></span><?php endif; ?>
  </p></div>
  <?php // Delete — administrators only, and never once converted (a converted
        //   lead is the record of where a customer came from; mark it lost). ?>
  <?php if ((is_admin_level() || is_master()) && !$converted): ?>
  <form method="post" action="/lead-delete" onsubmit="return confirm('Delete lead <?= e($l['ref']) ?>? This cannot be undone. (Mark it lost instead if you just want to stop chasing it.)')">
    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
    <button class="btn small danger" type="submit">Delete</button></form>
  <?php endif; ?>
</div>

<?php // ---- A reassignment waiting for a manager (owner, 20 Oct 2026) ------------
      $rq = function_exists('lead_reassign_pending') ? lead_reassign_pending((int)$l['id']) : null;
      if ($rq):
        $rqTo = ops_one("SELECT * FROM users WHERE id=?", [(int)$rq['to_user_id']]);
        $me = current_user();
        $canDecide = lead_user_is_manager_for($l, $me) && ((int)$rq['requested_by_id'] !== (int)($me['id'] ?? 0) || is_master());
        $mgrs = array_map('user_name', array_slice(lead_reassign_approvers($l, (int)$rq['requested_by_id']), 0, 4)); ?>
  <div class="panel" role="status" style="margin-top:12px;border-left:4px solid var(--warn,#d97706)">
    <b>Reassignment waiting for a manager.</b>
    <?= e($rq['requested_by']) ?> asked to give this lead to <b><?= e($rqTo ? user_name($rqTo) : 'someone') ?></b> — “<?= e($rq['reason']) ?>”.
    It stays with <?= e($l['owner_name'] ?: 'its owner') ?> until a manager decides<?= $mgrs ? ' (' . e(implode(', ', $mgrs)) . ')' : '' ?>.
    <?php if ($canDecide): ?>
      <form method="post" action="/approval-lead-decide" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:10px">
        <input type="hidden" name="back" value="/lead?id=<?= (int)$l['id'] ?>"><input type="hidden" name="req" value="<?= (int)$rq['id'] ?>">
        <div class="ff" style="margin:0;flex:1;min-width:200px"><label style="font-size:12px">Your note <span class="muted">(required to reject)</span></label>
          <input class="form-control" name="note" maxlength="500"></div>
        <button class="btn small" name="decision" value="approve">Approve</button>
        <button class="btn small secondary" name="decision" value="reject">Reject</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php // ---- Where it is now, and the one next thing ------------------------- ?>
<div class="nowband">
  <?php if ($converted): ?>
    <div class="step">🎉 Won — <?= e($l['company_name']) ?> is now a customer.</div>
    <p class="next">See them in <a href="/client?id=<?= (int)$l['converted_partner_id'] ?>"><b>the customer list</b></a><?php
      $wq = null; foreach (($quotes ?? []) as $qq) if (($qq['status'] ?? '') === 'ACCEPTED') { $wq = $qq; break; }
      if ($wq): ?> · their accepted <?= e(Tl('quote')) ?> <a href="/quote?id=<?= (int)$wq['id'] ?>"><b><?= e($wq['quote_no']) ?></b></a><?php endif;
      if ($l['converted_inquiry_id']): ?> · <a href="/inquiries">the <?= e(Tl('inquiry')) ?> raised for it</a><?php endif; ?>.
      The work carries on from the customer and the <?= e(Tl('quote')) ?>; this lead stays as the record of how it was won.</p>
  <?php elseif ($l['status'] === 'LOST'): ?>
    <div class="step">Closed — this one did not happen.</div>
    <?php if ($l['lost_reason']): ?><p class="next">Reason: <b><?= e($lostReasons[$l['lost_reason']] ?? $l['lost_reason']) ?></b><?= $l['lost_note'] ? ' — ' . e($l['lost_note']) : '' ?>.</p><?php endif; ?>
    <?php if (function_exists('leads_can_reopen') && leads_can_reopen()): ?>
      <details style="margin-top:8px"><summary class="muted" style="font-size:12.5px;cursor:pointer">Reopen this lead</summary>
        <form method="post" action="/lead-reopen" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <input class="form-control" name="reason" required maxlength="300" placeholder="Why — written in its history" style="flex:1;min-width:220px">
          <button class="btn small secondary" type="submit">Reopen</button>
        </form></details>
    <?php endif; ?>
  <?php else:
    //  Owner, 12 Nov 2026 — "once lead is generated the screen is very confusing
    //  … move to contact made, work this as a deal, log a contact, make a
    //  quotation … so where to click, how to click, why to click." One picture of
    //  where it is (three plain steps) and ONE thing to do next, chosen from what
    //  has actually happened; everything else is behind "More" (blueprint: one
    //  primary action, progressive disclosure).
    $talked = 0; foreach (($timeline ?? []) as $a) if (empty($a['auto'])) $talked++;
    $lq = $quotes[0] ?? null;                         // newest quotation on this lead
    $lqSt = $lq ? (string)$lq['status'] : '';
    $plainQ = ['DRAFT' => 'being prepared — finish it and submit it', 'PENDING_APPROVAL' => 'waiting for approval',
               'APPROVED' => 'approved — ready to send to them', 'SENT' => 'sent — waiting for their answer',
               'REJECTED' => 'sent back by the approver — correct it', 'ACCEPTED' => 'accepted', 'LOST' => 'they said no', 'EXPIRED' => 'expired — send a fresh one'];
    $step = $lq ? (in_array($lqSt, ['SENT'], true) ? 3 : 2) : ($talked > 0 ? 2 : 1);
    $steps = [1 => 'Talk to them', 2 => 'Send a ' . Tl('quote'), 3 => 'Their answer']; ?>
    <ol class="lead-steps" aria-label="Where this lead is">
      <?php foreach ($steps as $n => $lbl): ?>
        <li class="<?= $n < $step ? 'done' : ($n === $step ? 'now' : '') ?>"><span><?= $n < $step ? '✓' : $n ?></span><?= e($lbl) ?></li>
      <?php endforeach; ?>
    </ol>
    <p class="next" style="margin-top:8px">
      <?php if ($lq): ?>
        Your <?= e(Tl('quote')) ?> <b><?= e($lq['quote_no']) ?></b> is <b><?= e($plainQ[$lqSt] ?? strtolower($lqSt)) ?></b>.
      <?php elseif ($talked === 0): ?>
        Nothing is logged yet. Start by noting your first call, e-mail or meeting with <?= e($l['contact_name'] ?: 'them') ?>.
      <?php else: ?>
        You have talked to them <?= $talked ?> time<?= $talked === 1 ? '' : 's' ?>. When you know what they need, send them a <?= e(Tl('quote')) ?>.
      <?php endif; ?>
      <?php if (trim((string)$l['next_action']) !== ''): ?>
        <br><span class="muted">Your next step: <?= e($l['next_action']) ?><?= $l['next_action_on'] ? ' — by ' . e(fdate($l['next_action_on'])) : '' ?></span>
      <?php endif; ?>
      <?php if ($stalled): ?> <span class="pill p-bad" style="font-size:11.5px"><?= (int)$days ?> days without progress</span><?php endif; ?>
    </p>
    <?php if ($canEdit): ?>
      <div class="cta">
        <?php if ($lq && $lqSt !== 'LOST' && $lqSt !== 'EXPIRED'): ?>
          <a class="btn" href="/quote?id=<?= (int)$lq['id'] ?>">Open <?= e(Tl('quote')) ?> <?= e($lq['quote_no']) ?> →</a>
          <a class="btn small secondary" href="#log" data-tab-go="Contacts">Log a contact</a>
        <?php elseif ($talked === 0): ?>
          <a class="btn" href="#log" data-tab-go="Contacts">📞 Log your first contact</a>
          <?php if (!empty($canQuote)): ?><a class="btn small secondary" href="/quote-new?lead=<?= (int)$l['id'] ?>">Make a <?= e(Tl('quote')) ?></a><?php endif; ?>
        <?php else: ?>
          <?php if (!empty($canQuote)): ?><a class="btn" href="/quote-new?lead=<?= (int)$l['id'] ?>">Make the <?= e(Tl('quote')) ?> →</a><?php endif; ?>
          <a class="btn small secondary" href="#log" data-tab-go="Contacts">Log a contact</a>
        <?php endif; ?>
        <?php if ($oppId): ?><a class="btn small secondary" href="/opportunity?id=<?= (int)$oppId ?>">Open the deal</a><?php endif; ?>
        <a class="muted" style="font-size:12.5px;margin-left:6px" href="#more-actions" data-tab-go="Actions">More — change stage, mark lost…</a>
      </div>
      <p class="muted" style="font-size:12px;margin:8px 0 0">When they accept the <?= e(Tl('quote')) ?>, this lead closes by itself as <b>won</b> and <?= e($l['company_name']) ?> becomes a customer — nothing else to press.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div data-tabs data-tabs-key="lead" data-tabs-order="Overview,Contacts,Quotes,Documents,History,Actions">
<?php // ---- The essentials, plainly ---------------------------------------- ?>
<div class="panel" data-tab="Overview" style="margin-top:16px">
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
  <?php // Field #7 — manpower deputation, shown when this lead carries it. ?>
  <?php if (($l['deputation_kind'] ?? '') !== ''): ?>
    <h4 style="margin:14px 0 4px">Manpower deputation</h4>
    <table class="grid">
      <tr><th>Type</th><td><?= e(LEAD_DEPUTATION[$l['deputation_kind']] ?? $l['deputation_kind']) ?></td>
          <th>How many</th><td><?= (int)($l['manpower_count'] ?? 0) ?: '—' ?></td></tr>
      <?php if (trim((string)($l['site_location'] ?? '')) !== ''): ?>
        <tr><th>Site</th><td colspan="3"><?= e($l['site_location']) ?></td></tr>
      <?php endif; ?>
      <?php if (trim((string)($l['manpower_skills'] ?? '')) !== ''): ?>
        <tr><th>Skills / qualifications</th><td colspan="3" style="white-space:pre-wrap"><?= e($l['manpower_skills']) ?></td></tr>
      <?php endif; ?>
    </table>
  <?php endif; ?>
</div>

<?php // ---- Contacts — every contact logged, and logging the next one ----------
      // Owner, 12 Nov 2026: "Log contact shall log every contact made in the lead
      // history with its details as a list." The list is here, newest first, with
      // everything typed each time; "＋ Log a contact" opens the form above it.
      $contacts = $contacts ?? [];
      $canLog = $canEdit && $open; ?>
<style>
  .ct-list{list-style:none;margin:0;padding:0}
  .ct{border:1px solid var(--line,#d5dde3);border-radius:10px;padding:10px 12px;margin:0 0 8px;background:var(--panel,#fff)}
  .ct-top{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:baseline}
  .ct-ic{font-size:16px}
  .ct-said{margin:6px 0 0;white-space:pre-wrap}
  .ct-meta{margin:6px 0 0;font-size:12.5px;color:var(--muted,#5b6b6a);display:flex;flex-wrap:wrap;gap:4px 14px}
  .ct-log>summary{list-style:none;display:inline-block;cursor:pointer}
  .ct-log>summary::-webkit-details-marker{display:none}
  .ct-log[open]>summary{margin-bottom:10px}
</style>
<div class="panel" data-tab="Contacts" style="margin-top:16px" id="contacts">
  <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
    <h3 style="margin:0">Contacts <span class="muted" style="font-weight:400;font-size:13px">(<?= count($contacts) ?>)</span></h3>
  </div>
  <p class="muted" style="margin:4px 0 12px">Every call, message or meeting with them, newest first — so the story is never lost and the follow-up is never forgotten.</p>

  <?php if ($canLog): ?>
  <details class="ct-log" id="log"<?= $contacts ? '' : ' open' ?>>
    <summary class="btn small">＋ Log a contact</summary>
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
  </details>
  <?php endif; ?>

  <?php if ($contacts): ?>
  <ul class="ct-list" style="margin-top:12px">
    <?php foreach ($contacts as $a):
      $kind = (string)$a['kind']; $mins = function_exists('lead_minutes_words') ? lead_minutes_words($a['duration_mins'] ?? 0) : ''; ?>
    <li class="ct">
      <div class="ct-top">
        <span class="ct-ic" aria-hidden="true"><?= LEAD_CONTACT_ICONS[$kind] ?? '•' ?></span>
        <b><?= e(LEAD_CONTACT_METHODS[$kind] ?? (ACT_KINDS[$kind] ?? $kind)) ?></b>
        <?php if (($a['direction'] ?? '') !== ''): ?><span class="muted" style="font-size:12.5px"><?= $a['direction'] === 'IN' ? '⭠ they contacted us' : '⭢ we contacted them' ?></span><?php endif; ?>
        <?php if (trim((string)($a['with_whom'] ?? '')) !== ''): ?><span>with <b><?= e($a['with_whom']) ?></b></span><?php endif; ?>
        <?php if (trim((string)($a['outcome'] ?? '')) !== ''): ?><span class="pill p-warn" style="font-size:11px"><?= e($a['outcome']) ?></span><?php endif; ?>
        <span class="muted" style="font-size:12.5px;margin-left:auto"><?= e(fdate(substr((string)$a['occurred_at'], 0, 10))) ?> · <?= e($a['owner'] ?: '—') ?></span>
      </div>
      <?php if (trim((string)($a['body'] ?? '')) !== ''): ?><div class="ct-said"><?= e($a['body']) ?></div><?php endif; ?>
      <?php if ($mins !== '' || trim((string)($a['next_step'] ?? '')) !== '' || trim((string)($a['next_step_on'] ?? '')) !== ''): ?>
      <div class="ct-meta">
        <?php if ($mins !== ''): ?><span>⏱ <?= e($mins) ?></span><?php endif; ?>
        <?php if (trim((string)($a['next_step'] ?? '')) !== '' || trim((string)($a['next_step_on'] ?? '')) !== ''): ?>
          <span>➜ Next: <?= e(trim((string)($a['next_step'] ?? '')) ?: 'follow up') ?><?= trim((string)($a['next_step_on'] ?? '')) !== '' ? ' — by ' . e(fdate($a['next_step_on'])) : '' ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (!$canLog): ?>
    <p class="muted" style="margin:0">No contact has been logged on this lead.</p>
  <?php endif; ?>
</div>

<?php // ---- Quotations, straight off the lead ------------------------------ ?>
<?php if (!empty($canQuote) || $quotes): ?>
<div class="panel" data-tab="Quotes" style="margin-top:16px">
  <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">Quotations <span class="muted" style="font-weight:400;font-size:13px">(<?= count($quotes) ?>)</span></h3>
    <?php if (!empty($canQuote) && $l['status'] !== 'LOST'): ?>
      <a class="btn small" href="/quote-new?lead=<?= (int)$l['id'] ?>">+ Make a <?= e(Tl('quote')) ?></a>
      <?php if (function_exists('pc_can_edit') && pc_can_edit()): ?><a class="btn small secondary" href="/project-costings?lead_id=<?= (int)$l['id'] ?>">+ Project costing</a><?php endif; ?>
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
    <p class="muted" style="margin:10px 0 0">None yet.<?= (!empty($canQuote) && $l['status'] !== 'LOST') ? ' Use <b>Make a ' . e(Tl('quote')) . '</b> when you are ready to price it.' : '' ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- Documents on this lead ------------------------------------------
      // Any number of files kept against the lead for future reference — the
      // customer's RFQ, a spec, an e-mail thread. They travel onto the quote
      // when one is raised from this lead. ?>
<?php $files = $files ?? []; $fileKinds = $fileKinds ?? []; ?>
<div class="panel" data-tab="Documents" style="margin-top:16px" id="files">
  <h3 style="margin-top:0">Documents <span class="muted" style="font-weight:400;font-size:13px">(<?= count($files) ?>)</span></h3>
  <p class="muted" style="font-size:13px;margin:8px 0 0">Attach the customer's enquiry, specifications, drawings or e-mails — as many as you like, up to 8&nbsp;MB each. They stay on record and are carried onto the quotation raised from this lead.</p>
  <?php if ($files): ?>
    <table class="dt" style="margin-top:10px">
      <thead><tr><th scope="col">File</th><th scope="col">Kind</th><th scope="col">Note</th><th scope="col">Added</th><?php if ($canEdit): ?><th scope="col"></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td>📄 <a href="/lead-file?id=<?= (int)$f['id'] ?>"><?= e($f['file_name'] ?: 'file') ?></a></td>
          <td><span class="pill p-mut"><?= e($fileKinds[$f['kind']] ?? $f['kind']) ?></span></td>
          <td><?= $f['note'] ? e($f['note']) : '<span class="muted">—</span>' ?></td>
          <td class="muted" style="white-space:nowrap"><?= e($f['uploaded_at'] ? fdate(substr((string)$f['uploaded_at'],0,10)) : '') ?><?= $f['uploaded_by'] ? '<br><span class="muted" style="font-size:12px">'.e($f['uploaded_by']).'</span>' : '' ?></td>
          <?php if ($canEdit): ?><td class="num"><form method="post" action="/lead-file-delete?id=<?= (int)$f['id'] ?>" style="display:inline" onsubmit="return confirm('Remove this file?')"><button class="btn small secondary">✕</button></form></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted" style="margin:10px 0 0">No documents yet.</p>
  <?php endif; ?>
  <?php if ($canEdit): ?>
  <form method="post" action="/lead-files?id=<?= (int)$l['id'] ?>" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
    <div class="form-sec" style="margin:0"><label style="display:block;font-size:12px" for="lf-file">File(s)</label><input id="lf-file" type="file" name="files[]" multiple required></div>
    <div class="form-sec" style="margin:0"><label style="display:block;font-size:12px" for="lf-kind">What is it?</label>
      <select id="lf-kind" name="kind" class="form-control" style="min-width:220px"><?php foreach ($fileKinds as $k=>$lbl): ?><option value="<?= e($k) ?>"<?= $k==='REQUIREMENT'?' selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
    <div class="form-sec" style="margin:0"><label style="display:block;font-size:12px" for="lf-note">Note (optional)</label><input id="lf-note" class="form-control" name="note" placeholder="e.g. Rev 2" style="width:180px"></div>
    <button class="btn" type="submit">Attach</button>
  </form>
  <?php endif; ?>
</div>

<?php // ---- Move it to the next step ---------------------------------------- ?>
<?php if ($canEdit && $open):
  $fwd = array_values(array_filter($stages, fn($s) => $s['kind']==='OPEN' && (int)$s['id']!==(int)$l['stage_id']));
  $won = array_values(array_filter($stages, fn($s) => $s['kind']==='WON'));
  $lost= array_values(array_filter($stages, fn($s) => $s['kind']==='LOST'));
?>
<div class="panel" data-tab="Actions" style="margin-top:16px" id="more-actions">
  <h3 style="margin-top:0">More — change stage, close it</h3>
  <p class="muted" style="margin:0 0 14px;font-size:13px">You rarely need this page. The stages below are optional markers for your manager's reports — a lead does <b>not</b> have to pass through them. It closes by itself as <b>won</b> when they accept your <?= e(Tl('quote')) ?>.</p>

  <?php if ($fwd): ?>
    <div class="form-sec" style="margin-top:0"><h3 style="font-size:14px">Mark how far it has got <span class="muted" style="font-weight:400">(optional)</span></h3></div>
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
    <div class="form-sec"><h3 style="font-size:14px">They said yes without a <?= e(Tl('quote')) ?> from us</h3>
      <p>Only for when they placed the order directly — for example they sent a purchase order. It makes them a customer and raises the <?= e(Tl('inquiry')) ?>; you check the details on the next screen. (When they accept a <?= e(Tl('quote')) ?>, this happens by itself.)</p></div>
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
    <?php if (function_exists('lead_can_mark_lost') && !lead_can_mark_lost($l)): ?>
      <p class="muted" style="font-size:13px">Only the person responsible for this lead — <b><?= e($l['owner_name'] ?: 'its owner') ?></b> — marks it lost.</p>
    <?php else: foreach ($lost as $s): ?>
      <form method="post" action="/lead-move" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:8px">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
        <div class="ff" style="margin:0"><label style="font-size:12px">Why it was lost</label>
          <select class="form-control" name="lost_reason" required style="max-width:220px">
            <option value="">choose a reason…</option>
            <?php foreach ($lostReasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff" style="margin:0;flex:1;min-width:200px"><label style="font-size:12px">Remarks — what happened</label>
          <input class="form-control" name="lost_note" required maxlength="500" placeholder="required"></div>
        <button class="btn small secondary"><?= e($s['name']) ?></button>
      </form>
    <?php endforeach; endif; ?>
  <?php endif; ?>

  <?php if (!$oppId && function_exists('opp_can_edit') && opp_can_edit()): ?>
    <div class="form-sec"><h3 style="font-size:14px">Work it as a deal <span class="muted" style="font-weight:400">(optional — big or long pursuits)</span></h3>
      <p>For a large order that takes months, with several people or several <?= e(Tlp('quote')) ?> — it gets its own deal page and forecast. Most leads never need this.</p></div>
    <form method="post" action="/opportunity-from-lead" style="display:inline">
      <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
      <button class="btn small secondary">Work this as a deal →</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>
  // A link that names a tab opens that tab first, then scrolls to its target.
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[data-tab-go]') : null; if (!a) return;
    var want = a.getAttribute('data-tab-go'), btn = null;
    document.querySelectorAll('[role="tab"]').forEach(function (b) { if (!btn && (b.textContent || '').trim() === want) btn = b; });
    if (!btn) return;
    ev.preventDefault(); btn.click();
    var t = document.querySelector(a.getAttribute('href'));
    if (t) setTimeout(function () { try { if (t.tagName === 'DETAILS') t.open = true; t.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (_) {} }, 30);
  });
</script>

<?php // ---- Folded away: the detail you only need sometimes ----------------- ?>
<details class="fold" data-tab="Overview">
  <summary>How promising is it? <span class="sub">score <?= (int)$score['score'] ?>/100</span></summary>
  <div class="fold-body">
    <p class="muted" style="margin:0 0 8px;font-size:12.5px">A rules engine, not a guess — every rule that fired is listed.</p>
    <ul style="margin:0;padding-left:20px">
      <?php foreach ($score['why'] as $w): ?><li style="font-size:13.5px"><?= e($w) ?></li><?php endforeach; ?>
    </ul>
  </div>
</details>

<?php if ($canEdit && $open): ?>
<details class="fold" data-tab="Actions">
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
        <?php if ((int)($l['owner_user_id'] ?? 0)): ?>
        <div class="ff ff-wide" id="reassign_why" hidden><label>Why it is being reassigned <span class="muted">— <?= lead_user_is_manager_for($l, current_user()) ? 'kept in its history' : 'a manager approves it before it changes hands' ?></span></label>
          <input class="form-control" name="reassign_reason" maxlength="500"></div>
        <script>(function () {
          var s = document.querySelector('select[name="owner_user_id"]'), w = document.getElementById('reassign_why');
          if (!s || !w) return; var was = s.value;
          s.addEventListener('change', function () { w.hidden = s.value === was; var i = w.querySelector('input'); if (i) i.required = !w.hidden; });
        })();</script>
        <?php endif; ?>
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

<details class="fold" data-tab="History" open>
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
            <td class="muted" style="font-size:12.5px;max-width:340px"><?= nl2br(e((string)($a['body'] ?? ''))) ?>
              <?php $hm = function_exists('lead_minutes_words') ? lead_minutes_words($a['duration_mins'] ?? 0) : ''; $hn = trim((string)($a['next_step'] ?? '')); $ho = trim((string)($a['next_step_on'] ?? ''));
                if ($hm !== '' || $hn !== '' || $ho !== ''): ?>
                <div style="margin-top:3px"><?= $hm !== '' ? '⏱ ' . e($hm) : '' ?><?= ($hm !== '' && ($hn !== '' || $ho !== '')) ? ' · ' : '' ?><?= ($hn !== '' || $ho !== '') ? '➜ Next: ' . e($hn ?: 'follow up') . ($ho !== '' ? ' — by ' . e(fdate($ho)) : '') : '' ?></div>
              <?php endif; ?></td>
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
<details class="fold" data-tab="Actions">
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
</div><!-- /data-tabs (lead) -->
