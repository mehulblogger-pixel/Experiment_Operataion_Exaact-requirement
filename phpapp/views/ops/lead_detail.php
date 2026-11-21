<?php
// ============================================================================
//  THE LEAD PAGE — record-page design approved by the owner on 20 Nov 2026
//  ("Yes built at present looks ok"; pictures v3 1–11). Built from the shared
//  record-page kit (views/ops/_rec_ui.php) so the deal, quotation, enquiry and
//  order pages can follow with the same pieces.
//
//  Everything the old page had is still here (owner: "Hope you have not missed
//  ... each and everything"); only where it sits changed:
//    header card ......... name, number, branch, status pill ▾ (moves the stage),
//                          contact line, ✏️ Edit, 📎 Attach, ⋯ more
//    three boxes ......... value, hoped to close, handled by
//    stage path .......... the company's own open stages → Quotation → Won → Lost
//    Overview ............ next action + the one smart button, quick actions,
//                          what they want, summary, recent history, how promising
//                          (open leads only — owner: a lost lead read 35/100)
//    Details ............. every field, the edit form, manpower deputation
//    Contacts ............ every conversation + the ONE log form
//    Quotes .............. quotations in plain words, make one, project costing
//    More ................ documents, full history, how it moved, score,
//                          work as a deal, delete
//  Every form posts to the same addresses with the same field names as before.
// ============================================================================
require_once __DIR__ . '/_rec_ui.php';
require_once __DIR__ . '/_sxw_ui.php';   // Sales Stage 2 (21 Nov 2026): the Sales bar above, the phone bottom bar below
$open = $l['status'] === 'OPEN';
$id = (int) $l['id'];
$curStage = '';
foreach ($stages as $s) if ((int)$s['id'] === (int)$l['stage_id']) $curStage = $s['name'];
$oppId = ($canEdit && $open && function_exists('opp_try'))
    ? opp_try(fn() => ops_val("SELECT id FROM opportunities WHERE lead_id=?", [$id]), null) : null;
$converted = ($l['status'] ?? '') === 'CONVERTED' || !empty($l['converted_partner_id']) || !empty($l['converted_inquiry_id']);
$lost = !$converted && ($l['status'] ?? '') === 'LOST';
$effLbl = function_exists('act_effort_label') ? act_effort_label($effort ?? []) : '';
$contacts = $contacts ?? []; $files = $files ?? []; $fileKinds = $fileKinds ?? []; $quotes = $quotes ?? []; $timeline = $timeline ?? []; $history = $history ?? [];
$canLog = $canEdit && $open;
//  D2: someone else's lead is read only — no quoting from it either.
if (!empty($workNote)) $canQuote = false;
$talked = 0; foreach ($timeline as $a) if (empty($a['auto'])) $talked++;
$lq = $quotes[0] ?? null; $lqSt = $lq ? (string) $lq['status'] : '';
$Q = Tl('quote');
$short = fn($d) => $d ? date('j M', strtotime((string) $d)) : '';
$todayYmd = date('Y-m-d');

// Edit form: opened by ✏️, by ?edit=1, or when a save was refused (what was typed comes back).
$editRetry = function_exists('form_old') && form_old('_edit', '') === '1';
$editOpen = $editRetry || !empty($_GET['edit']);
$ev = fn($k) => $editRetry ? form_old($k, (string) ($l[$k] ?? '')) : (string) ($l[$k] ?? '');

// The stage path: the company's own open stages, then Quotation, Won and Lost.
$openStages = array_values(array_filter($stages, fn($s) => $s['kind'] === 'OPEN'));
$wonStage = null; $lostStage = null;
foreach ($stages as $s) { if ($s['kind'] === 'WON' && !$wonStage) $wonStage = $s; if ($s['kind'] === 'LOST' && !$lostStage) $lostStage = $s; }
$seen = [(string) $curStage => true]; $enteredOn = [];
foreach (array_reverse($history) as $h) {
    $seen[(string) $h['to_name']] = true; $seen[(string) ($h['from_name'] ?? '')] = true;
    $enteredOn[(string) $h['to_name']] = (string) $h['moved_at'];
}
$reached = 0; $curIdx = -1;
foreach ($openStages as $i => $s) {
    if (!empty($seen[$s['name']])) $reached = $i;
    if ((int) $s['id'] === (int) $l['stage_id']) $curIdx = $i;
}
if ($open && $curIdx >= 0) $reached = $curIdx;
$steps = [];
foreach ($openStages as $i => $s) {
    if ($converted)  $state = 'done';
    elseif ($lost)   $state = $i <= $reached ? 'done' : '';
    else             $state = $i < $reached ? 'done' : ($i === $reached ? ($lq ? 'done' : 'now') : '');
    $date = $i === 0 ? $short($l['created_at'] ?? '') : $short($enteredOn[$s['name']] ?? '');
    if ($state === 'now') $date = (int) ($days ?? 0) . ' day' . ((int) ($days ?? 0) === 1 ? '' : 's');
    $steps[] = ['name' => $s['name'], 'state' => $state, 'date' => $date];
}
$steps[] = ['name' => ucfirst($Q), 'state' => $lq ? ($open ? 'now' : 'done') : '', 'date' => $lq ? $short($lq['created_at']) : ''];
$steps[] = ['name' => $wonStage['name'] ?? LEAD_STATUS['CONVERTED'], 'state' => $converted ? 'won' : '',
            'date' => $converted ? $short($enteredOn[$wonStage['name'] ?? ''] ?? ($l['updated_at'] ?? '')) : ''];
$steps[] = ['name' => $lostStage['name'] ?? LEAD_STATUS['LOST'], 'state' => $lost ? 'lost' : '',
            'date' => $lost ? $short($enteredOn[$lostStage['name'] ?? ''] ?? ($l['updated_at'] ?? '')) : ''];

// The status pill: where it is, in a word or two.
if ($converted)  $pill = ['won', LEAD_STATUS['CONVERTED']];
elseif ($lost)   $pill = ['lost', 'Lead – ' . LEAD_STATUS['LOST']];
elseif ($lq && $lqSt === 'SENT') $pill = ['open', ucfirst($Q) . ' sent'];
else             $pill = [$reached === 0 && !$lq ? 'new' : 'open', $curStage ?: LEAD_STATUS['OPEN']];

// Stage moves (the status pill menu): forward open stages, won without a quotation, lost.
$fwd = array_values(array_filter($stages, fn($s) => $s['kind'] === 'OPEN' && (int) $s['id'] !== (int) $l['stage_id']));
$won = array_values(array_filter($stages, fn($s) => $s['kind'] === 'WON'));
$lostSt = array_values(array_filter($stages, fn($s) => $s['kind'] === 'LOST'));
$canMarkLost = !function_exists('lead_can_mark_lost') || lead_can_mark_lost($l);
//  Both old doors to delete are kept: the header button (administrators, not on a won
//  lead) and the "Remove this lead" panel (anyone who edits leads).
$canDelete = can('mod.leads.edit') || is_master() || (is_admin_level() && !$converted);
$canDeal = $canEdit && $open && !$oppId && function_exists('opp_can_edit') && opp_can_edit();
$canCost = function_exists('pc_can_edit') && pc_can_edit();

// History rows for the timeline.
$tlIcon = ['CALL' => ['info', 'phone'], 'EMAIL' => ['info', 'mail'], 'WHATSAPP' => ['ok', 'wa'], 'MEETING' => ['gold', 'users'], 'VISIT' => ['gold', 'pin'],
           'NOTE' => ['', 'note'], 'FILE' => ['', 'clip'], 'SYSTEM' => ['ok', 'spark'], 'STAGE' => ['ok', 'flow']];
$tlItems = function ($rows) use ($tlIcon) {
    $out = [];
    foreach ($rows as $a) {
        [$tone, $ic] = $tlIcon[(string) $a['kind']] ?? ['', 'hist'];
        $kindLbl = ACT_KINDS[$a['kind']] ?? ucfirst(strtolower((string) $a['kind']));
        $title = trim((string) ($a['subject'] ?? '')) !== '' ? (string) $a['subject'] : $kindLbl;
        if (empty($a['auto']) && trim((string) ($a['subject'] ?? '')) !== '' && stripos($title, $kindLbl) === false) $title = $kindLbl . ' — ' . $title;
        if (trim((string) ($a['outcome'] ?? '')) !== '') $title .= ' · ' . $a['outcome'];
        $w = [];
        if (trim((string) ($a['body'] ?? '')) !== '') $w[] = nl2br(e((string) $a['body']));
        $meta = [];
        if (!empty($a['direction'])) $meta[] = $a['direction'] === 'IN' ? 'they contacted us' : 'we contacted them';
        $hm = function_exists('lead_minutes_words') ? lead_minutes_words($a['duration_mins'] ?? 0) : ''; if ($hm !== '') $meta[] = $hm;
        $hn = trim((string) ($a['next_step'] ?? '')); $ho = trim((string) ($a['next_step_on'] ?? ''));
        if ($hn !== '' || $ho !== '') $meta[] = 'Next: ' . ($hn ?: 'follow up') . ($ho !== '' ? ' — by ' . fdate($ho) : '');
        $meta[] = 'by ' . ($a['owner'] ?: '—');
        $w[] = e(implode(' · ', $meta));
        $out[] = [$tone, $ic, $title, implode('<br>', $w), $a['occurred_at'] ? date('j M', strtotime((string) $a['occurred_at'])) : ''];
    }
    return $out;
};

// The one next thing to do — chosen from what has actually happened (owner, 12 Nov 2026).
$plainQ = ['DRAFT' => 'being prepared — finish it and submit it', 'PENDING_APPROVAL' => 'waiting for approval',
           'APPROVED' => 'approved — ready to send to them', 'SENT' => 'sent — waiting for their answer',
           'REJECTED' => 'sent back by the approver — correct it', 'ACCEPTED' => 'accepted', 'LOST' => 'they said no', 'EXPIRED' => 'expired — send a fresh one'];
$nextOn = trim((string) ($l['next_action_on'] ?? ''));
$lateDays = $nextOn !== '' && $nextOn < $todayYmd ? (int) floor((strtotime($todayYmd) - strtotime($nextOn)) / 86400) : 0;
$ageDays = !empty($l['created_at']) ? max(0, (int) floor((time() - strtotime((string) $l['created_at'])) / 86400)) : 0;
$hasNext = !$open || !empty($quotes) || $oppId
    || (function_exists('opp_try') && opp_try(fn() => ops_val("SELECT 1 FROM opportunities WHERE lead_id=?", [$id]), null));
$tel = function_exists('sx_tel') ? sx_tel($l['contact_phone'] ?? '') : '';
$wa = function_exists('sx_wa') ? sx_wa($l['contact_phone'] ?? '') : '';
$jsq = fn($s) => e(json_encode((string) $s));
?>
<div class="rec sxw has-bn"<?= $editRetry ? ' data-retry-skip' : '' ?>>
<?php sxw_nav('/leads'); ?>
<?= ($hasNext && function_exists('chain_strip')) ? chain_strip('LEAD', $id, 'LEAD', $id) : '' ?>

<div class="rec-top">
  <?php // ---- Header card ------------------------------------------------- ?>
  <section class="rec-card" aria-label="The lead">
    <div class="rec-hd">
      <div class="rec-tile"><?= rec_icon('building', 'l') ?></div>
      <h1><?= e($l['company_name']) ?></h1>
      <?php if ($canEdit && $open): ?>
      <details class="rec-menu" id="stage-menu">
        <summary class="rec-pill <?= e($pill[0]) ?>" aria-label="Status: <?= e($pill[1]) ?> — change the stage"><?= e($pill[1]) ?> <?= rec_icon('down', 's') ?></summary>
        <div class="rec-pop">
          <h4>Change the stage</h4>
          <p class="hint">Stages are optional markers for your manager's reports. A lead closes by itself as <b>won</b> when they accept your <?= e($Q) ?>. You are asked to confirm before anything moves.</p>
          <?php //  D7 (owner, 21 Nov 2026 — findings J1-04): the current stage is marked and
                //  cannot be picked; stages ahead as before; earlier stages apart, under
                //  "Move back", and the question says "Move back …".
                $curIx = -1; foreach ($openStages as $i => $s) if ((int) $s['id'] === (int) $l['stage_id']) $curIx = $i;
                $ahead = $curIx < 0 ? $openStages : array_slice($openStages, $curIx + 1);
                $behind = $curIx > 0 ? array_reverse(array_slice($openStages, 0, $curIx)) : []; ?>
          <?php if ($fwd || $curStage): ?><div class="rec-grp">Mark how far it has got</div><?php endif; ?>
          <?php if ($curIx >= 0): $s = $openStages[$curIx]; ?>
            <div class="rec-mi cur" aria-disabled="true"><span class="ico"><?= rec_icon('target') ?></span><span><?= e($s['name']) ?><small>Now · <?= (int) ($days ?? 0) ?> day<?= (int) ($days ?? 0) === 1 ? '' : 's' ?></small></span><span class="rec-tag info">Current</span></div>
          <?php endif; ?>
          <?php foreach ($ahead as $s): ?>
            <form method="post" action="/lead-move" onsubmit="return confirm('Move <?= e(addslashes($l['company_name'])) ?> to “<?= e(addslashes($s['name'])) ?>”?')">
              <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="stage_id" value="<?= (int) $s['id'] ?>">
              <button class="rec-mi" type="submit"><span class="ico"><?= rec_icon('checkc') ?></span><span><?= e($s['name']) ?></span><?= rec_icon('right', 's') ?></button>
            </form>
          <?php endforeach; ?>
          <?php if ($behind): ?><div class="rec-grp">Move back</div><?php endif; ?>
          <?php foreach ($behind as $s): ?>
            <form method="post" action="/lead-move" onsubmit="return confirm('Move back from “<?= e(addslashes($curStage)) ?>” to “<?= e(addslashes($s['name'])) ?>”?')">
              <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="stage_id" value="<?= (int) $s['id'] ?>">
              <button class="rec-mi" type="submit"><span class="ico"><?= rec_icon('swap') ?></span><span>Move back to <?= e($s['name']) ?></span><?= rec_icon('right', 's') ?></button>
            </form>
          <?php endforeach; ?>
          <?php if ($won || $lostSt): ?><div class="rec-grp">Close it</div><?php endif; ?>
          <?php foreach ($won as $s): ?>
            <form method="post" action="/lead-move" onsubmit="return confirm('They said yes without a <?= e(addslashes($Q)) ?> from us? It makes <?= e(addslashes($l['company_name'])) ?> a customer and raises the <?= e(addslashes(Tl('inquiry'))) ?> — you check the details on the next screen.')">
              <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="stage_id" value="<?= (int) $s['id'] ?>">
              <button class="rec-mi" type="submit"><span class="ico" style="color:var(--ok)"><?= rec_icon('trophy') ?></span><span>They said yes without a <?= e($Q) ?><small>Marks it “<?= e($s['name']) ?>” — only when they placed the order directly, e.g. sent a purchase order</small></span><?= rec_icon('right', 's') ?></button>
            </form>
          <?php endforeach; ?>
          <?php foreach ($lostSt as $s): ?>
            <?php if (!$canMarkLost): ?>
              <div class="rec-mi" style="cursor:default"><span class="ico"><?= rec_icon('lock') ?></span><span><?= e($s['name']) ?><small>Only the person responsible for this lead — <?= e($l['owner_name'] ?: 'its owner') ?> — marks it lost.</small></span><span></span></div>
            <?php else: ?>
              <details>
                <summary class="rec-mi dang" style="list-style:none"><span class="ico"><?= rec_icon('x') ?></span><span><?= e($s['name']) ?> — it did not happen<small style="color:var(--bad)">Asks the reason and what happened</small></span><?= rec_icon('down', 's') ?></summary>
                <form method="post" action="/lead-move" class="rec-subform" onsubmit="return confirm('Close <?= e(addslashes($l['company_name'])) ?> as “<?= e(addslashes($s['name'])) ?>”?')">
                  <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="stage_id" value="<?= (int) $s['id'] ?>">
                  <label class="rec-lab" style="margin:0">Why it was lost
                    <select class="form-control" name="lost_reason" required>
                      <option value="">choose a reason…</option>
                      <?php foreach ($lostReasons as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select></label>
                  <label class="rec-lab" style="margin:0">Remarks — what happened
                    <input class="form-control" name="lost_note" required maxlength="500" placeholder="required"></label>
                  <button class="rec-btn dang" style="margin-top:4px" type="submit">Close it as “<?= e($s['name']) ?>”</button>
                </form>
              </details>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="rec-mi" href="#flow" data-tab-go="More"><span class="ico"><?= rec_icon('flow') ?></span><span>View flow<small>How it moved: from, to, days there, who</small></span><?= rec_icon('right', 's') ?></a>
        </div>
      </details>
      <?php else: ?>
        <span class="rec-pill static <?= e($pill[0]) ?>"><?= e($pill[1]) ?></span>
      <?php endif; ?>
      <div class="id">Lead ID: <?= e($l['ref']) ?><?= ($l['office_name'] ?? '') !== '' ? ' · ' . e($l['office_name']) . ' branch' : '' ?></div>
    </div>
    <div class="rec-who">
      <div>
        <div class="r"><span class="pr"><?= rec_icon('user', 's') ?><span><?= e($l['contact_name'] ?: '—') ?></span></span>
          <?php if (trim((string) $l['contact_phone']) !== ''): ?><span class="sep"></span><span class="pr"><?= rec_icon('phone', 's') ?><span><?= $tel !== '' ? '<a href="' . e($tel) . '">' . e($l['contact_phone']) . '</a>' : e($l['contact_phone']) ?></span></span><?php endif; ?></div>
        <?php if (trim((string) $l['contact_email']) !== ''): ?><div class="r"><span class="pr"><?= rec_icon('mail', 's') ?><span><a href="mailto:<?= e($l['contact_email']) ?>"><?= e($l['contact_email']) ?></a></span></span></div><?php endif; ?>
      </div>
      <div class="rec-acts">
        <?php if ($canEdit && $open): ?><a class="rec-ib" href="?id=<?= $id ?>&edit=1#edit" data-tab-go="Details" title="Edit the details" aria-label="Edit the details"><?= rec_icon('pen', 's') ?></a><?php endif; ?>
        <?php if ($canEdit): ?><a class="rec-ib" href="#files" data-tab-go="More" title="Attach a file" aria-label="Attach a file"><?= rec_icon('clip', 's') ?></a><?php endif; ?>
        <details class="rec-menu">
          <summary class="rec-ib" title="More" aria-label="More"><?= rec_icon('more') ?></summary>
          <div class="rec-pop">
            <?php if ($canLog): ?><a class="rec-mi" href="#log" data-tab-go="Contacts"><span class="ico"><?= rec_icon('note') ?></span><span>Log a conversation</span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <?php if (!empty($canQuote) && !$lost): ?><a class="rec-mi" href="/quote-new?lead=<?= $id ?>"><span class="ico"><?= rec_icon('file') ?></span><span>Make a <?= e($Q) ?></span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <?php if ($oppId): ?><a class="rec-mi" href="/opportunity?id=<?= (int) $oppId ?>"><span class="ico"><?= rec_icon('brief') ?></span><span>Open the deal</span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <?php if ($canDeal): ?><a class="rec-mi" href="#deal" data-tab-go="More"><span class="ico"><?= rec_icon('brief') ?></span><span>Work this as a deal<small>Only for big or long pursuits</small></span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <a class="rec-mi" href="#history" data-tab-go="More"><span class="ico"><?= rec_icon('hist') ?></span><span>Full history</span><?= rec_icon('right', 's') ?></a>
            <?php if ($canDelete && !$converted): ?><a class="rec-mi dang" href="#remove" data-tab-go="More"><span class="ico"><?= rec_icon('trash') ?></span><span>Delete this lead</span><?= rec_icon('right', 's') ?></a><?php endif; ?>
          </div>
        </details>
      </div>
    </div>
  </section>
  <?php rec_kpis([['rupee', $l['value'] ? fmoney($l['value']) : '—', 'Lead value'],
                  ['cal', $l['expected_close'] ? fdate($l['expected_close']) : '—', 'Hoped to close'],
                  ['user', $l['owner_name'] ?: '—', 'Handled by']]); ?>
</div>

<?php // ---- D2 (owner, 21 Nov 2026): someone else's lead is read only for you ---------
      if (!empty($workNote)): ?>
  <div class="rec-ban info" role="status"><h4><?= rec_icon('lock', 's') ?> View only</h4><p><?= e($workNote) ?></p></div>
<?php endif; ?>

<?php // ---- A reassignment waiting for a manager (owner, 20 Oct 2026) ------------
      $rq = function_exists('lead_reassign_pending') ? lead_reassign_pending($id) : null;
      if ($rq):
        $rqTo = ops_one("SELECT * FROM users WHERE id=?", [(int)$rq['to_user_id']]);
        $me = current_user();
        $canDecide = lead_user_is_manager_for($l, $me) && ((int)$rq['requested_by_id'] !== (int)($me['id'] ?? 0) || is_master());
        $mgrs = array_map('user_name', array_slice(lead_reassign_approvers($l, (int)$rq['requested_by_id']), 0, 4)); ?>
  <div class="rec-ban warn" role="status">
    <h4><?= rec_icon('swap', 's') ?> Reassignment waiting for a manager</h4>
    <p><?= e($rq['requested_by']) ?> asked to give this lead to <b><?= e($rqTo ? user_name($rqTo) : 'someone') ?></b> — “<?= e($rq['reason']) ?>”.
    It stays with <?= e($l['owner_name'] ?: 'its owner') ?> until a manager decides<?= $mgrs ? ' (' . e(implode(', ', $mgrs)) . ')' : '' ?>.</p>
    <?php if ($canDecide): ?>
      <form method="post" action="/approval-lead-decide">
        <input type="hidden" name="back" value="/lead?id=<?= $id ?>"><input type="hidden" name="req" value="<?= (int)$rq['id'] ?>">
        <label class="rec-lab" style="display:block">Your note <span class="muted" style="font-weight:400">(required to reject)</span>
          <input class="form-control" name="note" maxlength="500"></label>
        <div class="rec-two"><button class="rec-btn" name="decision" value="approve">Approve</button><button class="rec-btn sec" name="decision" value="reject">Reject</button></div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php // ---- Where it is ---------------------------------------------------------- ?>
<section class="rec-card">
  <div class="rec-ch"><h3>Lead stage</h3><a class="rec-lnk" href="#flow" data-tab-go="More"><?= rec_icon('flow', 's') ?> View flow</a></div>
  <?php rec_steps($steps); ?>
</section>

<div data-tabs data-tabs-key="lead" data-tabs-order="Overview,Details,Contacts,Quotes,More" data-tabs-class="rec-tabs">

<?php // =================================================================== OVERVIEW ?>
<div data-tab="Overview" data-tab-icon="<?= e(rec_icon_path('home')) ?>">
<?php if ($converted): ?>
  <div class="rec-ban ok">
    <h4><?= rec_icon('trophy', 's') ?> Won — <?= e($l['company_name']) ?> is now a customer</h4>
    <p>The work carries on from the customer and the <?= e($Q) ?>; this lead stays as the record of how it was won.</p>
    <div class="rec-links">
      <?php if (!empty($l['converted_partner_id'])): ?><a href="/client?id=<?= (int)$l['converted_partner_id'] ?>"><?= rec_icon('building', 's') ?> The customer</a><?php endif; ?>
      <?php $wq = null; foreach ($quotes as $qq) if (($qq['status'] ?? '') === 'ACCEPTED') { $wq = $qq; break; }
        if ($wq): ?><a href="/quote?id=<?= (int)$wq['id'] ?>"><?= rec_icon('file', 's') ?> <?= e($wq['quote_no']) ?></a><?php endif; ?>
      <?php if (!empty($l['converted_inquiry_id'])): ?><a href="/inquiries"><?= rec_icon('list', 's') ?> The <?= e(Tl('inquiry')) ?> raised for it</a><?php endif; ?>
      <?php $anyDeal = function_exists('opp_try') ? opp_try(fn() => ops_val("SELECT id FROM opportunities WHERE lead_id=? ORDER BY id DESC LIMIT 1", [$id]), null) : null;
        if ($anyDeal): ?><a href="/opportunity?id=<?= (int)$anyDeal ?>"><?= rec_icon('brief', 's') ?> Its deal</a><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<div class="rec-grid">
<?php if ($lost): ?>
  <?php rec_card_open('Why it was lost', 'target', '<span class="rec-tag mut">' . rec_icon('lock', 's') . ' Locked</span>'); ?>
    <div class="rec-lab" style="margin-top:0">Reason</div>
    <div class="rec-ro"><?= $l['lost_reason'] ? e($lostReasons[$l['lost_reason']] ?? $l['lost_reason']) : '<span class="muted">No reason recorded</span>' ?></div>
    <div class="rec-lab">Remarks — what happened</div>
    <div class="rec-ro"><?= trim((string) $l['lost_note']) !== '' ? e($l['lost_note']) : '<span class="muted">—</span>' ?></div>
    <p class="rec-note">Closed — this one did not happen. The reason is kept as it was given; to change anything, the lead is reopened.</p>
    <?php if (function_exists('leads_can_reopen') && leads_can_reopen()): ?>
      <details class="rec-push" <?= !empty($_GET['reopen']) ? 'open' : '' ?>>
        <summary class="rec-btn warn" style="list-style:none"><?= rec_icon('swap') ?> Reopen this lead</summary>
        <form method="post" action="/lead-reopen" style="margin-top:10px">
          <input type="hidden" name="id" value="<?= $id ?>">
          <label class="rec-lab" style="display:block;margin-top:0">Why it is being reopened <span class="muted" style="font-weight:400">— written in its history</span>
            <input class="form-control" name="reason" required maxlength="300"></label>
          <button class="rec-btn" type="submit">Reopen</button>
        </form>
      </details>
    <?php endif; ?>
  <?php rec_card_close(); ?>
<?php elseif ($open): ?>
  <?php // ---- Next action, and the ONE thing to do next ---------------------------
    $naRight = $canEdit ? '<a class="rec-lnk" href="?id=' . $id . '&edit=1#edit" data-tab-go="Details">' . rec_icon('pen', 's') . ' Edit</a>' : '';
    rec_card_open('Next action', 'cal', $naRight); ?>
    <?php if ($nextOn !== '' || trim((string) $l['next_action']) !== ''): ?>
    <div class="rec-na">
      <span class="ico"><?= rec_icon('cal', 's') ?></span>
      <span><?= $nextOn !== '' ? e(fdate($nextOn)) : '<span class="muted">No date set</span>' ?>
        <?php if ($lateDays > 0): ?> <span class="rec-tag bad"><?= $lateDays ?> day<?= $lateDays === 1 ? '' : 's' ?> late</span>
        <?php elseif ($nextOn === $todayYmd): ?> <span class="rec-tag warn">Today</span><?php endif; ?></span>
      <span class="ico"><?= rec_icon('checkc', 's') ?></span><span><?= trim((string) $l['next_action']) !== '' ? e($l['next_action']) : '<span class="muted">Not written down</span>' ?></span>
      <span class="ico"><?= rec_icon('user', 's') ?></span><span><?= e($l['owner_name'] ?: '—') ?></span>
    </div>
    <?php endif; ?>
    <p style="margin:<?= ($nextOn !== '' || trim((string) $l['next_action']) !== '') ? '12px' : '0' ?> 0 0;font-size:14px">
      <?php if ($lq): ?>
        Your <?= e($Q) ?> <b><?= e($lq['quote_no']) ?></b> is <b><?= e($plainQ[$lqSt] ?? strtolower($lqSt)) ?></b>.
      <?php elseif ($talked === 0): ?>
        Nothing is logged yet. Start by noting your first call, e-mail or meeting with <?= e($l['contact_name'] ?: 'them') ?>.
      <?php else: ?>
        You have talked to them <?= $talked ?> time<?= $talked === 1 ? '' : 's' ?>. When you know what they need, send them a <?= e($Q) ?>.
      <?php endif; ?>
    </p>
    <?php if ($canEdit): ?>
      <div class="rec-push">
      <?php if ($lq && $lqSt !== 'LOST' && $lqSt !== 'EXPIRED'): ?>
        <a class="rec-btn" href="/quote?id=<?= (int)$lq['id'] ?>"><?= rec_icon('file') ?> Open <?= e($Q) ?> <?= e($lq['quote_no']) ?></a>
      <?php elseif ($talked === 0): ?>
        <a class="rec-btn" href="#log" data-tab-go="Contacts"><?= rec_icon('phone') ?> Log your first contact</a>
        <?php if (!empty($canQuote)): ?><a class="rec-btn sec" style="margin-top:8px" href="/quote-new?lead=<?= $id ?>">Make a <?= e($Q) ?></a><?php endif; ?>
      <?php else: ?>
        <?php if (!empty($canQuote)): ?><a class="rec-btn" href="/quote-new?lead=<?= $id ?>"><?= rec_icon('file') ?> Make the <?= e($Q) ?></a><?php else: ?><a class="rec-btn" href="#log" data-tab-go="Contacts"><?= rec_icon('note') ?> Log a contact</a><?php endif; ?>
      <?php endif; ?>
      <?php if ($oppId): ?><a class="rec-btn sec" style="margin-top:8px" href="/opportunity?id=<?= (int)$oppId ?>"><?= rec_icon('brief') ?> Open the deal</a><?php endif; ?>
      <p class="rec-note">When they accept the <?= e($Q) ?>, this lead closes by itself as <b>won</b> and <?= e($l['company_name']) ?> becomes a customer — nothing else to press.</p>
      </div>
    <?php endif; ?>
  <?php rec_card_close(); ?>

  <?php if ($canLog): rec_card_open('Quick actions', 'spark');
    rec_quick([['phone', 'Log call', '?id=' . $id . '&log=CALL#log', 'data-tab-go="Contacts" data-log-method="CALL"'],
               ['mail', 'Send e-mail', '?id=' . $id . '&log=EMAIL#log', 'data-tab-go="Contacts" data-log-method="EMAIL"'],
               ['task', 'Add task', '?id=' . $id . '&edit=1#edit', 'data-tab-go="Details" title="Set the next thing to do and by when"'],
               ['note', 'Add note', '?id=' . $id . '&log=NOTE#log', 'data-tab-go="Contacts" data-log-method="NOTE"']]);
    rec_card_close(); endif; ?>
<?php endif; ?>

  <?php // ---- Lead summary ------------------------------------------------------
    $sum = [['First created', '<b>' . e(fdate(substr((string) $l['created_at'], 0, 10))) . '</b>'],
            ['Last updated', '<b>' . e(fdate(substr((string) ($l['updated_at'] ?: $l['created_at']), 0, 10))) . '</b>'],
            ['Total interactions', '<b>' . (int) $talked . '</b>'],
            [$converted ? 'Effort to win' : 'Effort so far', '<b>' . ($effLbl !== '' ? e($effLbl) : '—') . '</b>'],
            ['Lead age', '<span class="rec-tag mut">' . $ageDays . ' day' . ($ageDays === 1 ? '' : 's') . '</span>']];
    if ($open) $sum[] = ['In this stage', !empty($stalled) ? '<span class="rec-tag bad">' . (int) $days . ' days without progress</span>' : '<b>' . (int) $days . ' day' . ((int) $days === 1 ? '' : 's') . '</b>'];
    rec_card_open('Lead summary', 'chart'); rec_summary($sum); rec_card_close(); ?>

  <?php rec_card_open('History (' . count($timeline) . ')', 'hist');
    if ($timeline) { rec_timeline($tlItems(array_slice($timeline, 0, 3)));
      echo '<div class="rec-push" style="text-align:center"><a class="rec-lnk" href="#history" data-tab-go="More">View full history ' . rec_icon('right', 's') . '</a></div>'; }
    else echo '<p class="muted" style="margin:0">Nothing logged yet.</p>';
    rec_card_close(); ?>

  <?php if (trim((string)$l['requirement']) !== '' || ($canEdit && $open)):
    rec_card_open('What they want', 'target', $canEdit && $open ? '<a class="rec-lnk" href="?id=' . $id . '&edit=1#edit" data-tab-go="Details">' . rec_icon('pen', 's') . ' Edit</a>' : ''); ?>
    <div style="white-space:pre-wrap;font-size:14px"><?= trim((string)$l['requirement']) !== '' ? e($l['requirement']) : '<span class="muted">Not written down yet.</span>' ?></div>
  <?php rec_card_close(); endif; ?>

  <?php // "How promising" means something only while the lead is being chased
        // (owner, 20 Nov 2026: a lost lead still showed 35/100).
    if ($open && !empty($score)):
      rec_card_open('How promising', 'trend', '<b style="font-size:15px">' . (int) $score['score'] . ' / 100</b>'); ?>
    <div class="rec-bar"><i style="width:<?= max(0, min(100, (int) $score['score'])) ?>%"></i></div>
    <p class="rec-note" style="margin:0 0 6px">A rules engine, not a guess — every rule that fired is listed.</p>
    <ul class="rec-why"><?php foreach ($score['why'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
  <?php rec_card_close(); endif; ?>
</div>
</div>

<?php // =================================================================== DETAILS ?>
<div data-tab="Details" data-tab-icon="<?= e(rec_icon_path('list')) ?>">
<div class="rec-grid">
  <?php rec_card_open('Lead details', 'file', $canEdit && $open ? '<a class="rec-lnk" href="?id=' . $id . '&edit=1#edit" onclick="var d=document.getElementById(\'edit\');if(d){d.open=true;d.scrollIntoView({behavior:\'smooth\'});return false;}">' . rec_icon('pen', 's') . ' Edit</a>' : '');
    $phoneV = trim((string) $l['contact_phone']) === '' ? '—'
      : '<span class="v act"><span>' . e($l['contact_phone']) . '</span><span style="display:flex;gap:6px">'
        . ($tel !== '' ? '<a class="rec-mini" href="' . e($tel) . '" title="Call" aria-label="Call">' . rec_icon('phone') . '</a>' : '')
        . ($wa !== '' ? '<a class="rec-mini" href="' . e($wa) . '" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp">' . rec_icon('wa') . '</a>' : '')
        . '</span></span>';
    $mailV = trim((string) $l['contact_email']) === '' ? '—'
      : '<span class="v act"><span>' . e($l['contact_email']) . '</span><button type="button" class="rec-mini" title="Copy" aria-label="Copy the e-mail address" onclick="try{navigator.clipboard.writeText(' . $jsq($l['contact_email']) . ');this.title=\'Copied\';}catch(e){}">' . rec_icon('copy') . '</button></span>';
    $srcLbl = function_exists('lk_options_or') ? (lk_options_or('lead_source', LEAD_SOURCES)[$l['source']] ?? $l['source']) : (LEAD_SOURCES[$l['source']] ?? $l['source']);
    rec_rows([['building', 'Company', e($l['company_name']) . (!empty($l['partner_id']) ? ' <a class="rec-lnk" href="/client?id=' . (int) $l['partner_id'] . '">on the customer list ›</a>' : '')],
              ['user', 'Contact', e($l['contact_name'] ?: '—')],
              ['tag', 'Source', e($srcLbl ?: '—')],
              ['mail', 'E-mail', $mailV],
              ['phone', 'Telephone', $phoneV],
              ['rupee', 'Worth about', $l['value'] ? e(fmoney($l['value'])) : '—'],
              ['cal', 'Hoped to close', $l['expected_close'] ? e(fdate($l['expected_close'])) : '—'],
              ['user', 'Handled by', e($l['owner_name'] ?: '—')],
              ['pin', 'Branch', e($l['office_name'] ?: '—')],
              //  DEF-15 (owner, 21 Nov 2026): the business unit is shown, and set under "Edit the details".
              ['brief', ucfirst(Tl('sbu')), e(($l['sbu'] ?? '') !== '' ? ((function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : OPS_SBUS)[$l['sbu']] ?? $l['sbu']) : '—')],
              ['msg', 'Best reached by', e(($l['pref_contact'] ?? '') !== '' ? (LEAD_PREF_CONTACT[$l['pref_contact']] ?? $l['pref_contact']) : '—')],
              ['cal', 'Next by', $l['next_action_on'] ? e(fdate($l['next_action_on'])) : '—'],
              ['task', 'Next thing to do', e($l['next_action'] ?: '—')],
              ['target', 'What they want', trim((string) $l['requirement']) !== '' ? '<span style="white-space:pre-wrap">' . e($l['requirement']) . '</span>' : '—']]);
    rec_card_close(); ?>

  <?php if (($l['deputation_kind'] ?? '') !== ''): rec_card_open('Manpower deputation', 'hat');
    $mrows = [['brief', 'Type', e(LEAD_DEPUTATION[$l['deputation_kind']] ?? $l['deputation_kind'])],
              ['users', 'How many', (int)($l['manpower_count'] ?? 0) ?: '—']];
    if (trim((string)($l['site_location'] ?? '')) !== '') $mrows[] = ['pin', 'Site', e($l['site_location'])];
    if (trim((string)($l['manpower_skills'] ?? '')) !== '') $mrows[] = ['task', 'Skills / qualifications', '<span style="white-space:pre-wrap">' . e($l['manpower_skills']) . '</span>'];
    rec_rows($mrows); rec_card_close(); endif; ?>

  <?php if ($canEdit && $open): ?>
  <section class="rec-card wide">
  <details id="edit"<?= $editOpen ? ' open' : '' ?>>
    <summary class="rec-ch" style="list-style:none;cursor:pointer;margin:0"><span class="ico"><?= rec_icon('pen') ?></span><h3>Edit the details</h3><span class="rec-lnk"><?= rec_icon('down', 's') ?></span></summary>
    <?php if ($editRetry): ?><div class="msg msg-warning" style="margin-top:12px">Nothing was lost — what you typed is below. Correct it and press <b>Save</b> again.</div><?php endif; ?>
    <form method="post" action="/lead-edit" style="margin-top:12px">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-grid">
        <div class="ff ff-wide"><label for="le-co">Company</label>
          <input class="form-control" id="le-co" name="company_name" required value="<?= e($ev('company_name')) ?>"></div>
        <div class="ff"><label for="le-cn">Contact</label>
          <input class="form-control" id="le-cn" name="contact_name" value="<?= e($ev('contact_name')) ?>"></div>
        <div class="ff"><label for="le-src">Source</label>
          <input class="form-control" id="le-src" name="source" value="<?= e($ev('source')) ?>"></div>
        <div class="ff"><label for="le-em">E-mail</label>
          <input class="form-control" id="le-em" type="email" inputmode="email" name="contact_email" value="<?= e($ev('contact_email')) ?>"></div>
        <div class="ff"><label for="le-ph">Telephone</label>
          <input class="form-control" id="le-ph" type="tel" inputmode="tel" name="contact_phone" value="<?= e($ev('contact_phone')) ?>"></div>
        <div class="ff"><label for="le-val">Worth about <span class="muted">(<?= e(cur_sym()) ?>)</span></label>
          <input class="form-control" id="le-val" type="number" step="0.01" min="0" name="value" value="<?= e($ev('value')) ?>"></div>
        <div class="ff"><label for="le-cl">Hoped to close</label>
          <input class="form-control" id="le-cl" type="date" name="expected_close" value="<?= e($ev('expected_close')) ?>"></div>
        <?php //  DEF-15 — the business unit: the units this person's scope covers, and the lead's own (kept as it is if not changed). ?>
        <div class="ff"><label for="le-sbu"><?= e(ucfirst(Tl('sbu'))) ?></label>
          <select class="form-control" id="le-sbu" name="sbu"><option value="">— not decided yet —</option>
            <?php $sbuOpts = $sbus ?? []; $sbuCur = (string) $ev('sbu');
              if ($sbuCur !== '' && !isset($sbuOpts[$sbuCur])) $sbuOpts = [$sbuCur => ((function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : OPS_SBUS)[$sbuCur] ?? $sbuCur)] + $sbuOpts;
              foreach ($sbuOpts as $sk => $sv): ?><option value="<?= e($sk) ?>"<?= $sbuCur === (string) $sk ? ' selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label for="le-own">Handled by</label>
          <select class="form-control searchable" id="le-own" name="owner_user_id">
            <option value="">— nobody yet —</option>
            <?php $ownSel = $editRetry ? (int) form_old('owner_user_id', (string) ($l['owner_user_id'] ?? 0)) : (int) ($l['owner_user_id'] ?? 0);
              foreach (($users ?? []) as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= $ownSel === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username']) ?><?= $u['role'] ? ' — ' . e(ORG_ROLES[$u['role']] ?? $u['role']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (trim((string)($l['owner_name'] ?? '')) !== '' && !(int)($l['owner_user_id'] ?? 0)): ?>
            <div class="ff-help">Held against the typed name “<?= e($l['owner_name']) ?>”, not linked to a login. Pick the person to make it a real allocation.</div>
          <?php endif; ?></div>
        <?php if ((int)($l['owner_user_id'] ?? 0)): ?>
        <div class="ff ff-wide" id="reassign_why"<?= $editRetry && $ownSel !== (int) $l['owner_user_id'] ? '' : ' hidden' ?>><label>Why it is being reassigned <span class="muted">— <?= lead_user_is_manager_for($l, current_user()) ? 'kept in its history' : 'a manager approves it before it changes hands' ?></span></label>
          <input class="form-control" name="reassign_reason" maxlength="500" value="<?= e($editRetry ? form_old('reassign_reason', '') : '') ?>"></div>
        <script>(function () {
          var s = document.querySelector('select[name="owner_user_id"]'), w = document.getElementById('reassign_why');
          if (!s || !w) return; var was = <?= json_encode((string) (int) $l['owner_user_id']) ?>;
          s.addEventListener('change', function () { w.hidden = s.value === was; var i = w.querySelector('input'); if (i) i.required = !w.hidden; });
        })();</script>
        <?php endif; ?>
        <div class="ff"><label for="le-pref">Best reached by</label>
          <select class="form-control" id="le-pref" name="pref_contact">
            <option value="">— not noted —</option>
            <?php foreach (($prefMethods ?? []) as $k=>$v): ?>
              <option value="<?= e($k) ?>" <?= $ev('pref_contact') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="ff"><label for="le-non">Next by</label>
          <input class="form-control" id="le-non" type="date" name="next_action_on" value="<?= e($ev('next_action_on')) ?>"></div>
        <div class="ff ff-wide"><label for="le-na">Next thing to do</label>
          <input class="form-control" id="le-na" name="next_action" maxlength="255" value="<?= e($ev('next_action')) ?>"></div>
        <div class="ff ff-wide"><label for="le-req">What they want</label>
          <textarea class="form-control" id="le-req" name="requirement" rows="3"><?= e($ev('requirement')) ?></textarea></div>
        <?php // Manpower deputation travels with every save — the old form had none, so saving it wiped them. ?>
        <div class="ff"><label for="le-dep">Manpower deputation</label>
          <select class="form-control" id="le-dep" name="deputation_kind">
            <option value="">— none —</option>
            <?php foreach (LEAD_DEPUTATION as $k => $v): ?><option value="<?= e($k) ?>" <?= $ev('deputation_kind') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label for="le-mc">How many people</label>
          <input class="form-control" id="le-mc" type="number" min="0" step="1" name="manpower_count" value="<?= e($ev('manpower_count')) ?>"></div>
        <div class="ff ff-wide"><label for="le-site">Site <span class="muted">(required for a man-month deputation)</span></label>
          <input class="form-control" id="le-site" name="site_location" maxlength="300" value="<?= e($ev('site_location')) ?>"></div>
        <div class="ff ff-wide"><label for="le-sk">Skills / qualifications</label>
          <textarea class="form-control" id="le-sk" name="manpower_skills" rows="2"><?= e($ev('manpower_skills')) ?></textarea></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">Save</button></div>
    </form>
  </details>
  </section>
  <?php endif; ?>
</div>
</div>

<?php // =================================================================== CONTACTS ?>
<div data-tab="Contacts" data-tab-icon="<?= e(rec_icon_path('users')) ?>" data-count="<?= count($contacts) ?>" id="contacts">
<?php rec_card_open('Contacts (' . count($contacts) . ')', 'users'); ?>
  <p class="rec-note" style="margin:-6px 0 12px">Every call, message or meeting with them, newest first — so the story is never lost and the follow-up is never forgotten.</p>
  <?php if ($canLog):
        // The ONE log form (views/ops/_log_form.php) — the same one the deal page
        // and Sales → Today use (owner, 19 Nov 2026).
        $lfBack = (string) ($_GET['back'] ?? '');
        $lfBack = function_exists('sx_safe_back') ? sx_safe_back($lfBack, '') : '';
        $lf = ['kind' => 'LEAD', 'id' => $id, 'office' => (int) ($l['office_id'] ?? 0), 'who' => (string) ($l['contact_name'] ?? ''),
               'pref' => (string) ($l['pref_contact'] ?? ''), 'back' => $lfBack !== '' ? $lfBack : '/lead?id=' . $id . '#lead=contacts',
               'open' => !$contacts || !empty($_GET['log'])];
        include __DIR__ . '/_log_form.php';
  endif; ?>
  <?php if ($contacts): ?>
  <div style="margin-top:12px">
    <?php foreach ($contacts as $a):
      $kind = (string)$a['kind']; $mins = function_exists('lead_minutes_words') ? lead_minutes_words($a['duration_mins'] ?? 0) : '';
      [$tone, $ic] = $tlIcon[$kind] ?? ['', 'note']; ?>
    <div class="rec-li">
      <div class="top"><span class="rec-bub <?= e($tone) ?>" style="width:30px;height:30px"><?= rec_icon($ic, 's') ?></span>
        <b><?= e(LEAD_CONTACT_METHODS[$kind] ?? (ACT_KINDS[$kind] ?? $kind)) ?></b>
        <?php if (trim((string)($a['outcome'] ?? '')) !== ''): ?><span class="rec-tag warn"><?= e($a['outcome']) ?></span><?php endif; ?>
      </div>
      <?php if (trim((string)($a['body'] ?? '')) !== ''): ?><div style="margin-top:6px;white-space:pre-wrap;font-size:13.5px"><?= e($a['body']) ?></div><?php endif; ?>
      <div class="sub"><?= e(fdate(substr((string)$a['occurred_at'], 0, 10))) ?><?= $mins !== '' ? ' · ' . e($mins) : '' ?><?= trim((string)($a['with_whom'] ?? '')) !== '' ? ' · with ' . e($a['with_whom']) : '' ?>
        <?= ($a['direction'] ?? '') !== '' ? ' · ' . ($a['direction'] === 'IN' ? 'they contacted us' : 'we contacted them') : '' ?> · by <?= e($a['owner'] ?: '—') ?></div>
      <?php if (trim((string)($a['next_step'] ?? '')) !== '' || trim((string)($a['next_step_on'] ?? '')) !== ''): ?>
        <div class="sub" style="color:var(--brand-ink);font-weight:600">Next: <?= e(trim((string)($a['next_step'] ?? '')) ?: 'follow up') ?><?= trim((string)($a['next_step_on'] ?? '')) !== '' ? ' — by ' . e(fdate($a['next_step_on'])) : '' ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php elseif (!$canLog): ?>
    <p class="muted" style="margin:0">No contact has been logged on this lead.</p>
  <?php endif; ?>
<?php rec_card_close(); ?>
</div>

<?php // =================================================================== QUOTES ?>
<?php if (!empty($canQuote) || $quotes): ?>
<div data-tab="Quotes" data-tab-icon="<?= e(rec_icon_path('file')) ?>" data-count="<?= count($quotes) ?>">
<?php rec_card_open(ucfirst(Tlp('quote')) . ' (' . count($quotes) . ')', 'file'); ?>
  <?php foreach ($quotes as $qq): [$qs, $qt] = rec_quote_status($qq['status']); ?>
    <div class="rec-li">
      <div class="top"><a href="/quote?id=<?= (int)$qq['id'] ?>"><b><?= e($qq['quote_no']) ?></b></a><?php if ((int)$qq['rev']): ?><span class="rec-tag mut">Rev <?= (int)$qq['rev'] ?></span><?php endif; ?></div>
      <div style="margin-top:6px"><span class="rec-tag <?= e($qt) ?>"><?= e($qs) ?></span></div>
      <div class="rec-two" style="margin-top:8px;font-size:13px"><div><span class="muted">Value</span><br><b><?= e(fmoney($qq['total_amount'])) ?></b></div><div><span class="muted">Made</span><br><b><?= e(fdate(substr((string)$qq['created_at'],0,10))) ?></b></div></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$quotes): ?><p class="muted" style="margin:0 0 6px">None yet.<?= (!empty($canQuote) && !$lost) ? ' Use <b>Make a ' . e($Q) . '</b> when you are ready to price it.' : '' ?></p><?php endif; ?>
  <?php if (!empty($canQuote) && !$lost): ?>
    <p class="rec-note">The company, the contact and what they want come across — you will not type them again.</p>
    <div class="rec-two">
      <a class="rec-btn" href="/quote-new?lead=<?= $id ?>"><?= rec_icon('plus') ?> Make a <?= e($Q) ?></a>
      <?php if ($canCost): ?><a class="rec-btn sec" href="/project-costings?lead_id=<?= $id ?>"><?= rec_icon('calc') ?> Project costing</a><?php endif; ?>
    </div>
  <?php endif; ?>
<?php rec_card_close(); ?>
</div>
<?php endif; ?>

<?php // =================================================================== MORE ?>
<div data-tab="More" data-tab-icon="<?= e(rec_icon_path('more')) ?>">
  <nav class="rec-moremenu" aria-label="More on this lead">
    <a href="#files"><?= rec_icon('clip', 's') ?> Documents (<?= count($files) ?>)</a>
    <a href="#history"><?= rec_icon('hist', 's') ?> Full history</a>
    <a href="#flow"><?= rec_icon('flow', 's') ?> View flow</a>
    <?php if ($open && !empty($score)): ?><a href="#score"><?= rec_icon('trend', 's') ?> How promising</a><?php endif; ?>
    <?php if ($canDeal): ?><a href="#deal"><?= rec_icon('brief', 's') ?> Work as a deal</a><?php endif; ?>
    <?php if ($canDelete): ?><a href="#remove" class="dang"><?= rec_icon('trash', 's') ?> Delete</a><?php endif; ?>
  </nav>

  <?php // ---- Documents — the customer's RFQ, a spec, an e-mail thread. They travel
        //      onto the quotation raised from this lead. ?>
  <?php rec_card_open('Documents (' . count($files) . ')', 'clip', '', 'id="files"'); ?>
    <p class="rec-note" style="margin:-6px 0 10px">Attach the customer's enquiry, specifications, drawings or e-mails — as many as you like, up to <?= (int) round(LEAD_FILE_MAX / 1048576) ?>&nbsp;MB each. They stay on record and are carried onto the <?= e($Q) ?> raised from this lead.</p>
    <?php foreach ($files as $f): ?>
      <div class="rec-li" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center">
        <div style="min-width:0"><div class="top"><?= rec_icon('file', 's') ?><a href="/lead-file?id=<?= (int)$f['id'] ?>" style="overflow-wrap:anywhere"><b><?= e($f['file_name'] ?: 'file') ?></b></a></div>
          <div class="sub"><span class="rec-tag mut"><?= e($fileKinds[$f['kind']] ?? $f['kind']) ?></span> Note: <?= $f['note'] ? e($f['note']) : '—' ?> · <?= e($f['uploaded_at'] ? fdate(substr((string)$f['uploaded_at'],0,10)) : '') ?><?= $f['uploaded_by'] ? ' · ' . e($f['uploaded_by']) : '' ?></div></div>
        <?php if ($canEdit): ?><form method="post" action="/lead-file-delete?id=<?= (int)$f['id'] ?>" onsubmit="return confirm('Remove this file?')"><button class="rec-mini bad" type="submit" title="Remove" aria-label="Remove <?= e($f['file_name']) ?>"><?= rec_icon('x') ?></button></form><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$files): ?><p class="muted" style="margin:0 0 6px">No documents yet.</p><?php endif; ?>
    <?php if ($canEdit): ?>
    <form method="post" action="/lead-files?id=<?= $id ?>" enctype="multipart/form-data" style="margin-top:10px">
      <label class="rec-lab" style="display:block;margin-top:0" for="lf-file">File(s)</label>
      <input id="lf-file" type="file" name="files[]" multiple required data-max-bytes="<?= (int) LEAD_FILE_MAX ?>" style="margin-top:6px;max-width:100%">
      <div class="rec-two" style="margin-top:4px">
        <div><label class="rec-lab" style="display:block" for="lf-kind">What is it?</label>
          <select id="lf-kind" name="kind" class="form-control"><?php foreach ($fileKinds as $k=>$lbl): ?><option value="<?= e($k) ?>"<?= $k==='REQUIREMENT'?' selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
        <div><label class="rec-lab" style="display:block" for="lf-note">Note (optional)</label><input id="lf-note" class="form-control" name="note" maxlength="255" placeholder="e.g. Rev 2"></div>
      </div>
      <button class="rec-btn" type="submit"><?= rec_icon('clip') ?> Attach</button>
    </form>
    <?php endif; ?>
  <?php rec_card_close(); ?>

  <?php rec_card_open('Full history (' . count($timeline) . ')', 'hist', '', 'id="history"');
    if ($timeline) rec_timeline($tlItems($timeline)); else echo '<p class="muted" style="margin:0">Nothing logged yet.</p>';
    rec_card_close(); ?>

  <?php rec_card_open('View flow — how it moved', 'flow', '', 'id="flow"'); ?>
    <?php if (!$history): ?><p class="muted" style="margin:0">It has not moved yet.</p><?php else: ?>
    <div class="rec-scroll"><table class="rec-tbl">
      <thead><tr><th scope="col">When</th><th scope="col">From</th><th scope="col">To</th><th scope="col" style="text-align:right">Days there</th><th scope="col">Who</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr><td style="white-space:nowrap"><?= e(fdate(substr((string)$h['moved_at'],0,10))) ?></td>
            <td><?= e($h['from_name'] ?: '—') ?></td><td><?= e($h['to_name']) ?></td>
            <td style="text-align:right"><?= (int)$h['days_in_previous'] ?></td><td><?= e($h['moved_by']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  <?php rec_card_close(); ?>

  <?php if ($open && !empty($score)): rec_card_open('How promising', 'trend', '<b style="font-size:15px">' . (int) $score['score'] . ' / 100</b>', 'id="score"'); ?>
    <div class="rec-bar"><i style="width:<?= max(0, min(100, (int) $score['score'])) ?>%"></i></div>
    <ul class="rec-why"><?php foreach ($score['why'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
  <?php rec_card_close(); endif; ?>

  <?php if ($canDeal): rec_card_open('Work it as a deal', 'brief', '<span class="rec-tag mut">optional</span>', 'id="deal"'); ?>
    <p style="margin:0;font-size:14px">For a large order that takes months, with several people or several <?= e(Tlp('quote')) ?> — it gets its own deal page and forecast. Most leads never need this.</p>
    <form method="post" action="/opportunity-from-lead">
      <input type="hidden" name="lead_id" value="<?= $id ?>">
      <button class="rec-btn sec" type="submit"><?= rec_icon('brief') ?> Work this as a deal</button>
    </form>
  <?php rec_card_close(); endif; ?>

  <?php if ($canDelete): rec_card_open('Remove this lead', 'trash', '', 'id="remove"'); ?>
    <?php if ($converted): ?>
      <p class="muted" style="margin:0">This lead was won and converted, so it is the record of where <?= e($l['company_name']) ?> came from and cannot be deleted. If it should not be chased any further, mark it lost — the history stays either way.</p>
    <?php else: ?>
      <p class="muted" style="margin:0">Removes <?= e($l['ref']) ?> — <?= e($l['company_name']) ?> for good. Nothing else points at it yet, so nothing downstream breaks. This cannot be undone. (Mark it lost instead if you just want to stop chasing it.)</p>
      <form method="post" action="/lead-delete"
            onsubmit="return confirm('Delete <?= e(addslashes($l['ref'])) ?> — <?= e(addslashes($l['company_name'])) ?>? This cannot be undone.')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="rec-btn dang" type="submit"><?= rec_icon('trash') ?> Delete this lead</button>
      </form>
    <?php endif; ?>
  <?php rec_card_close(); endif; ?>
</div>

</div><!-- /data-tabs (lead) -->
</div><!-- /rec -->
<?php sxw_bottom('/leads'); ?>
