<?php
// ============================================================================
//  THE DEAL PAGE — Sales redesign Stage 3 (owner, 21 Nov 2026, approved design
//  s3-deal), on the record-page kit the lead page uses (views/ops/_rec_ui.php).
//  Everything the old page had is still here; only where it sits changed:
//    banners ............ trade block; "held for approval" (approve / send back /
//                         withdraw, all approvals)
//    header card ........ name, number, customer (now its customer page, not the
//                         finance ledger), status, contact, Edit, ⋯ (delete, log,
//                         the lead, the flow)
//    three boxes ........ worth about, likely (with its %), hoped to close
//    stage path ......... the pipeline's stages → Won → Lost
//    Overview ........... next action (the old now-band, by state, with Reopen),
//                         quick actions, deal summary (+ business unit, DEF-15),
//                         what they want, project costing
//    Quotes ............. create / pick the customer / the quotations in plain
//                         words with Detach / attach / generate (won, none)
//    Actions ............ a won deal's way forward (one panel per state), raise a
//                         work order, Move it (+ a note for the approver — the
//                         server already took one), Edit the details (+ unit)
//    History ............ how it moved, the ONE log form, the activity timeline
//                         (both used to sit outside the tabs)
//  Every form posts to the same address with the same field names as before.
//  The old page is kept in tests/fixtures/pre_stage3/opportunity_detail.php.
// ============================================================================
require_once __DIR__ . '/_rec_ui.php';
require_once __DIR__ . '/_sxw_ui.php';
$open = $o['status'] === 'OPEN';
$won = $o['status'] === 'WON'; $lostSt = $o['status'] === 'LOST';
$id = (int)$o['id'];
$curStageName = '';
foreach ($stages as $s) if ((int)$s['id'] === (int)$o['stage_id']) $curStageName = $s['name'];
$effLbl = function_exists('act_effort_label') ? act_effort_label($effort ?? []) : '';
$custName = $o['client_name'] ?: ($o['partner_name'] ?: '');
$prob = (int)($o['probability'] ?: $o['stage_prob']);
$Q = Tl('quote'); $D = Tl('opportunity');
$sbuL = function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : OPS_SBUS;
$short = fn($d) => $d ? date('j M', strtotime((string)$d)) : '';
$todayYmd = date('Y-m-d');
$tel = function_exists('sx_tel') ? sx_tel($o['contact_phone'] ?? '') : '';
$canDelete = (is_admin_level() || is_master()) && $o['status'] !== 'WON' && !count($quotes);
$canReopen = function_exists('leads_can_reopen') && leads_can_reopen();
$wonReopen = $won && empty($o['call_id']) && empty($o['contract_id']) && trim((string)($o['sent_to_accounts_at'] ?? '')) === '';

// The stage path: the pipeline's open stages, then Won and Lost.
$openStages = array_values(array_filter($stages, fn($s) => $s['kind'] === 'OPEN'));
$wonStage = null; $lostStage = null;
foreach ($stages as $s) { if ($s['kind'] === 'WON' && !$wonStage) $wonStage = $s; if ($s['kind'] === 'LOST' && !$lostStage) $lostStage = $s; }
$enteredOn = []; $seen = [(string)$curStageName => true];
foreach (array_reverse($history) as $h) { $seen[(string)$h['to_name']] = true; $seen[(string)($h['from_name'] ?? '')] = true; $enteredOn[(string)$h['to_name']] = (string)$h['moved_at']; }
$reached = 0; $curIdx = -1;
foreach ($openStages as $i => $s) { if (!empty($seen[$s['name']])) $reached = $i; if ((int)$s['id'] === (int)$o['stage_id']) $curIdx = $i; }
if ($open && $curIdx >= 0) $reached = $curIdx;
$steps = [];
foreach ($openStages as $i => $s) {
    if ($won)        $state = 'done';
    elseif ($lostSt) $state = $i <= $reached ? 'done' : '';
    else             $state = $i < $reached ? 'done' : ($i === $reached ? 'now' : '');
    $date = $i === 0 ? $short($o['created_at'] ?? '') : $short($enteredOn[$s['name']] ?? '');
    if ($state === 'now') $date = (int)$days . ' day' . ((int)$days === 1 ? '' : 's');
    $steps[] = ['name' => $s['name'], 'state' => $state, 'date' => $date];
}
$steps[] = ['name' => $wonStage['name'] ?? OPP_STATUS['WON'], 'state' => $won ? 'won' : '', 'date' => $won ? $short($o['won_at'] ?? '') : ''];
$steps[] = ['name' => $lostStage['name'] ?? OPP_STATUS['LOST'], 'state' => $lostSt ? 'lost' : '', 'date' => $lostSt ? $short($o['won_at'] ?? '') : ''];
$pill = $won ? ['won', OPP_STATUS['WON']] : ($lostSt ? ['lost', OPP_STATUS['LOST']] : ['open', $curStageName ?: OPP_STATUS['OPEN']]);

// Next action, and how late it is.
$nextOn = trim((string)($o['next_action_on'] ?? ''));
$lateDays = $nextOn !== '' && $nextOn < $todayYmd ? (int)floor((strtotime($todayYmd) - strtotime($nextOn)) / 86400) : 0;
?>
<div class="rec sxw has-bn sxd">
<?php sxw_nav('/opportunities'); ?>
<?= function_exists('chain_strip') ? chain_strip('OPPORTUNITY', $id, 'OPPORTUNITY', $id) : '' ?>

<?php if (!empty($tradeBlock)): ?>
  <div class="rec-ban bad" role="alert"><p>⛔ <?= e($tradeBlock) ?> No <?= e($Q) ?> can be made for this <?= e($D) ?> until that changes.</p></div>
<?php endif; ?>
<?php if (!empty($gate)):
  $gTo = function_exists('stage_row') ? stage_row((int)$gate['to_stage_id']) : null; ?>
  <div class="rec-ban warn" role="status">
    <h4><?= rec_icon('lock', 's') ?> Held for approval: move to “<?= e((string)($gTo['name'] ?? 'the next step')) ?>”</h4>
    <p>The move to <b><?= e((string)($gTo['name'] ?? 'the next step')) ?></b> was requested by
    <?= e((string)$gate['requested_by'] ?: 'somebody') ?>
    <?= trim((string)$gate['requested_at']) !== '' ? 'on ' . e(fdate(substr((string)$gate['requested_at'], 0, 10))) : '' ?>
    and is waiting on <b><?= e((string)$gateWaiting) ?></b>. Until then the deal stays where it is, and it is not in the won figures.</p>
    <?php if (trim((string)$gate['note']) !== ''): ?><p style="margin-top:4px">“<?= e((string)$gate['note']) ?>”</p><?php endif; ?>
    <div class="sxd-gate">
      <?php if (!empty($gateCanAct)): ?>
        <form method="post" action="/approval-act" class="sxd-gate-f">
          <input type="hidden" name="id" value="<?= (int)$gate['id'] ?>">
          <input type="hidden" name="back" value="/opportunity?id=<?= $id ?>">
          <label class="sr-only" for="gate-rem">Remark</label>
          <input class="form-control" id="gate-rem" name="remarks" placeholder="Remark (required to send back)" maxlength="500">
          <button class="btn small" name="decision" value="APPROVED">Approve the move</button>
          <button class="btn small secondary" name="decision" value="REJECTED">Send back</button>
        </form>
      <?php elseif ((int)($gate['requested_by_id'] ?? 0) === (int)(current_user()['id'] ?? 0) || is_master()):   // only who may withdraw it sees the button (SALA-023) ?>
        <form method="post" action="/approval-act" style="display:inline"
              onsubmit="return confirm('Withdraw this request? The deal stays where it is.')">
          <input type="hidden" name="id" value="<?= (int)$gate['id'] ?>">
          <input type="hidden" name="back" value="/opportunity?id=<?= $id ?>">
          <button class="btn small secondary" name="decision" value="CANCELLED">Withdraw the request</button>
        </form>
      <?php endif; ?>
      <a class="btn small secondary" href="/approvals">All approvals</a>
    </div>
  </div>
<?php endif; ?>

<div class="rec-top">
  <section class="rec-card" aria-label="The <?= e($D) ?>">
    <div class="rec-hd">
      <div class="rec-tile"><?= rec_icon('brief', 'l') ?></div>
      <h1><?= e($o['name']) ?></h1>
      <span class="rec-pill static <?= e($pill[0]) ?>"><?= e($pill[1]) ?></span>
      <div class="id"><?= e(T('opportunity')) ?> <?= e($o['ref']) ?> ·
        <?php //  The customer opens its customer page (Stage 3: it used to open the finance ledger). ?>
        <?= $o['partner_id'] ? '<a href="/client?id=' . (int)$o['partner_id'] . '">' . e($custName) . '</a>' : e($custName ?: 'no customer yet') ?>
        <?= $curStageName ? ' · ' . e($curStageName) : '' ?></div>
    </div>
    <div class="rec-who">
      <div>
        <div class="r"><span class="pr"><?= rec_icon('user', 's') ?><span><?= e($o['contact_name'] ?: '—') ?></span></span>
          <?php if (trim((string)$o['contact_phone']) !== ''): ?><span class="sep"></span><span class="pr"><?= rec_icon('phone', 's') ?><span><?= $tel !== '' ? '<a href="' . e($tel) . '">' . e($o['contact_phone']) . '</a>' : e($o['contact_phone']) ?></span></span><?php endif; ?></div>
        <?php if (trim((string)$o['contact_email']) !== ''): ?><div class="r"><span class="pr"><?= rec_icon('mail', 's') ?><span><a href="mailto:<?= e($o['contact_email']) ?>"><?= e($o['contact_email']) ?></a></span></span></div><?php endif; ?>
      </div>
      <div class="rec-acts">
        <?php if ($canEdit && $open): ?><a class="rec-ib" href="#edit" data-tab-go="Actions" title="Edit the details" aria-label="Edit the details"><?= rec_icon('pen', 's') ?></a><?php endif; ?>
        <details class="rec-menu">
          <summary class="rec-ib" title="More" aria-label="More"><?= rec_icon('more') ?></summary>
          <div class="rec-pop">
            <?php if ($canEdit): ?><a class="rec-mi" href="#log" data-tab-go="History"><span class="ico"><?= rec_icon('note') ?></span><span>Log a conversation</span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <?php if ($canEdit && $open): ?><a class="rec-mi" href="#move" data-tab-go="Actions"><span class="ico"><?= rec_icon('swap') ?></span><span>Move to another stage</span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <?php if ($o['lead_id']): ?><a class="rec-mi" href="/lead?id=<?= (int)$o['lead_id'] ?>"><span class="ico"><?= rec_icon('users') ?></span><span>See the lead</span><?= rec_icon('right', 's') ?></a><?php endif; ?>
            <a class="rec-mi" href="#flow" data-tab-go="History"><span class="ico"><?= rec_icon('flow') ?></span><span>View flow<small>How it moved: from, to, days there, who</small></span><?= rec_icon('right', 's') ?></a>
            <?php if ($canDelete): ?><a class="rec-mi dang" href="#remove" data-tab-go="Actions"><span class="ico"><?= rec_icon('trash') ?></span><span>Delete this <?= e($D) ?></span><?= rec_icon('right', 's') ?></a><?php endif; ?>
          </div>
        </details>
      </div>
    </div>
  </section>
  <?php rec_kpis([['rupee', (float)$o['value'] ? fmoney($o['value']) : '—', 'Worth about'],
                  ['chart', fmoney(opp_weighted($o)), 'Likely value · ' . $prob . '% chance'],
                  ['cal', $o['expected_close'] ? fdate($o['expected_close']) : '—', 'Hoped to close']]); ?>
</div>
<?php // D2 (owner, 21 Nov 2026): someone else's deal is read only for you, with the reason.
      if (!empty($workNote)): ?>
  <div class="rec-ban info" role="status"><h4><?= rec_icon('lock', 's') ?> View only</h4><p><?= e($workNote) ?></p></div>
<?php endif; ?>

<section class="rec-card">
  <div class="rec-ch"><h3><?= e(TH('opportunity')) ?> stage</h3><a class="rec-lnk" href="#flow" data-tab-go="History"><?= rec_icon('flow', 's') ?> View flow</a></div>
  <?php rec_steps($steps); ?>
</section>

<?php // ---- Won: what it is waiting on — shown above the tabs, whichever tab is open (as the
      //      old page did): the order raised, the contract's state, or the missing customer. ?>
<?php if ($won && $orderBlock !== 'not-installed'): ?>
  <?php if (!empty($o['call_id'])): ?>
    <div class="rec-ban ok" id="take"><p>Order raised from this deal — <a href="/call?id=<?= (int)$o['call_id'] ?>"><b>open it</b></a>.</p></div>
  <?php elseif (!empty($contractRow)): ?>
    <?php $cOpen = strtoupper((string)($contractRow['open_status'] ?? 'OPEN')) === 'OPEN'; ?>
    <div class="rec-ban <?= $cOpen ? 'ok' : 'warn' ?>" id="take">
      <p><b>Contract <?= e($contractRow['contract_number']) ?></b>
      <?= $cOpen
          ? ' is registered and open — raise inspection ' . e(Tlp('call')) . ' from it whenever the work comes in.'
          : ' is registered and <b>waiting for approval</b>. ' . e(contract_waiting_words($contractRow)) . ' The ' . e(Tlp('call')) . ' are raised from it once it is open.' ?></p>
      <?php if ($cOpen && (can('ops.call.create') || is_master())): ?>
        <div style="margin-top:8px"><a class="btn small" href="/call-new?contract_id=<?= (int)$contractRow['id'] ?>">▶ Raise <?= e(Tl('call')) ?></a></div>
      <?php endif; ?>
    </div>
  <?php elseif ($orderBlock !== ''): ?>
    <div class="rec-ban warn" id="take"><p><?= e($orderBlock) ?>
      <?php if (empty($o['partner_id']) && !empty($o['lead_id'])): ?>
        <a href="/lead?id=<?= (int)$o['lead_id'] ?>"><b>Convert the lead</b></a> — it makes the customer and fills this in.
      <?php endif; ?></p>
      <?php // Owner, 13 Nov 2026 (bounce list 3.2): one short form, right here — the same short
            // record a won lead creates. A deal that came from a lead keeps "Convert the lead" above.
            if (empty($o['partner_id']) && empty($o['lead_id']) && !empty($canAddCustomer)): ?>
        <form method="post" action="/opportunity-add-customer" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="ff" style="flex:2;min-width:200px"><label for="opp-newcust">Customer name</label>
            <input class="form-control" id="opp-newcust" name="company_name" maxlength="200" required value="<?= e($o['partner_name']) ?>"></div>
          <div style="flex-basis:100%;min-width:0"><?php $cidVals = ['state' => '']; $cidId = 'opp-cid'; $cidNote = ''; include __DIR__ . '/_custid_fields.php'; ?></div>
          <?php if ((int) ($_GET['loc'] ?? 0) > 0 && ($locRow = ops_one("SELECT id, code, legal_name FROM business_partners WHERE id=?", [(int) $_GET['loc']]))): ?>
            <label class="ff-check" style="flex-basis:100%"><input type="checkbox" name="add_location_to" value="<?= (int) $locRow['id'] ?>"> Yes — it is <?= e($locRow['code'] . ' — ' . $locRow['legal_name']) ?> (same PAN): add this GSTIN as its billing location and use that <?= e(Tl('client')) ?></label>
          <?php endif; ?>
          <?php if (($_GET['samename'] ?? '') === '1'): ?>
            <label class="ff-check" style="flex-basis:100%"><input type="checkbox" name="confirm_new" value="1" required> It is a different company from the one already on file with this name</label>
          <?php endif; ?>
          <button class="btn small">＋ Add as a customer</button>
        </form>
      <?php endif; ?>
      <?php if (empty($o['partner_id']) && $canEdit && !empty($clients)): ?>
        <form method="post" action="/opportunity-edit" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="ff" style="flex:1;min-width:220px"><label for="opp-cust">Or pick one already on the master</label>
            <select id="opp-cust" name="partner_id" class="searchable">
              <option value="">—</option>
              <?php foreach ($clients as $cl): ?>
                <option value="<?= (int)$cl['id'] ?>"><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <button class="btn small">Set the customer</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div data-tabs data-tabs-key="opp" data-tabs-order="Overview,Quotes,Actions,History" data-tabs-class="rec-tabs">

<?php // =================================================================== OVERVIEW ?>
<div data-tab="Overview" data-tab-icon="<?= e(rec_icon_path('home')) ?>">
<div class="rec-grid">
  <?php // ---- Where it is now, and the one next thing (the old now-band) ------- ?>
  <?php if ($won): rec_card_open('Won', 'trophy'); ?>
    <div class="rec-ban ok" style="margin:0"><h4><?= rec_icon('trophy', 's') ?> Won — <?= e($custName ?: 'the customer') ?> said yes. 🎉</h4>
    <?php if (!empty($o['call_id'])): ?>
      <p>The work order is raised — <a href="/call?id=<?= (int)$o['call_id'] ?>"><b>open it</b></a>.</p>
    <?php elseif (function_exists('opp_won_next')): ?>
      <p><?= e(opp_won_next($o)) ?></p>
    <?php endif; ?></div>
    <p class="rec-note">What happens next is on the <a href="#take" data-tab-go="Actions">Actions</a> tab.</p>
    <?php if ($wonReopen && $canReopen): ?>
      <details class="rec-push"><summary class="rec-btn warn" style="list-style:none"><?= rec_icon('swap') ?> Reopen this deal</summary>
        <form method="post" action="/opportunity-reopen" style="margin-top:10px">
          <input type="hidden" name="id" value="<?= $id ?>">
          <label class="rec-lab" style="display:block;margin-top:0">Why — written in its history
            <input class="form-control" name="reason" required maxlength="300"></label>
          <button class="rec-btn" type="submit">Reopen</button>
        </form></details>
    <?php endif; ?>
  <?php rec_card_close(); elseif ($lostSt): rec_card_open('Why it was lost', 'target', '<span class="rec-tag mut">' . rec_icon('lock', 's') . ' Closed</span>'); ?>
    <p style="margin:0;font-size:14px">Closed — this one did not happen.</p>
    <div class="rec-ro" style="margin-top:8px">Reason: <b><?= e($lostReasons[$o['lost_reason']] ?? $o['lost_reason']) ?></b><?= $o['lost_to'] ? ' — lost to ' . e($o['lost_to']) : '' ?><?= $o['lost_note'] ? '. ' . e($o['lost_note']) : '' ?>.</div>
    <?php if ($canReopen): ?>
      <details class="rec-push"><summary class="rec-btn warn" style="list-style:none"><?= rec_icon('swap') ?> Reopen this deal</summary>
        <form method="post" action="/opportunity-reopen" style="margin-top:10px">
          <input type="hidden" name="id" value="<?= $id ?>">
          <label class="rec-lab" style="display:block;margin-top:0">Why — written in its history
            <input class="form-control" name="reason" required maxlength="300"></label>
          <button class="rec-btn" type="submit">Reopen</button>
        </form></details>
    <?php endif; ?>
  <?php rec_card_close(); else:
    rec_card_open('Next action', 'cal', $canEdit ? '<a class="rec-lnk" href="#edit" data-tab-go="Actions">' . rec_icon('pen', 's') . ' Edit</a>' : ''); ?>
    <?php if ($nextOn !== '' || trim((string)$o['next_action']) !== ''): ?>
    <div class="rec-na">
      <span class="ico"><?= rec_icon('cal', 's') ?></span>
      <span><?= $nextOn !== '' ? e(fdate($nextOn)) : '<span class="muted">No date set</span>' ?>
        <?php if ($lateDays > 0): ?> <span class="rec-tag bad"><?= $lateDays ?> day<?= $lateDays === 1 ? '' : 's' ?> late</span>
        <?php elseif ($nextOn === $todayYmd): ?> <span class="rec-tag warn">Today</span><?php endif; ?></span>
      <span class="ico"><?= rec_icon('checkc', 's') ?></span><span><?= trim((string)$o['next_action']) !== '' ? e($o['next_action']) : '<span class="muted">Not written down</span>' ?></span>
      <span class="ico"><?= rec_icon('user', 's') ?></span><span><?= e($o['owner_name'] ?: '—') ?></span>
    </div>
    <?php endif; ?>
    <p style="margin:12px 0 0;font-size:14px">This deal is at <b><?= e($curStageName ?: '—') ?></b>.<?php if ($stalled): ?> <span class="rec-tag bad"><?= (int)$days ?> days here — overdue</span><?php endif; ?></p>
    <?php if (!empty($quoteNow) && !in_array($quoteNow['status'], ['LOST', 'EXPIRED'], true)): ?>
      <p style="margin:6px 0 0;font-size:14px"><?= e($quoteNow['text']) ?></p>
      <div class="rec-push"><a class="rec-btn" href="/quote?id=<?= (int)$quoteNow['id'] ?>"><?= rec_icon('file') ?> Open <?= e(Tl('quote')) ?> <?= e($quoteNow['label']) ?> →</a></div>
    <?php elseif (trim((string)$o['next_action']) === ''): ?>
      <p class="rec-note">No next step noted — move it on below, or note the next step in Edit.</p>
    <?php endif; ?>
    <?php if ($canEdit): ?>
      <div class="rec-two" style="margin-top:8px">
        <a class="rec-btn sec" href="#move" data-tab-go="Actions"><?= rec_icon('swap') ?> Move it on</a>
        <?php if ($o['lead_id']): ?><a class="rec-btn sec" href="/lead?id=<?= (int)$o['lead_id'] ?>"><?= rec_icon('users') ?> See the lead</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php rec_card_close(); endif; ?>

  <?php if ($canEdit && $open): rec_card_open('Quick actions', 'spark');
    rec_quick([['phone', 'Log call', '?id=' . $id . '&log=CALL#log', 'data-tab-go="History" data-log-method="CALL"'],
               ['mail', 'Send e-mail', '?id=' . $id . '&log=EMAIL#log', 'data-tab-go="History" data-log-method="EMAIL"'],
               ['users', 'Log meeting', '?id=' . $id . '&log=MEETING#log', 'data-tab-go="History" data-log-method="MEETING"'],
               ['note', 'Add note', '?id=' . $id . '&log=NOTE#log', 'data-tab-go="History" data-log-method="NOTE"']]); ?>
    <p class="rec-note">Each opens the one shared log form with the method chosen.</p>
  <?php rec_card_close(); endif; ?>

  <?php rec_card_open(TH('opportunity') . ' summary', 'chart');
    $sum = [['Worth about', '<b>' . ((float)$o['value'] ? e(fmoney($o['value'])) : '—') . '</b>'],
            ['Likely value', '<span><b>' . e(fmoney(opp_weighted($o))) . '</b> <span class="muted" style="font-size:12px">(' . $prob . '% chance)</span></span>'],
            ['Handled by', '<b>' . e($o['owner_name'] ?: '—') . '</b>'],
            ['Branch', '<b>' . e($o['office_name'] ?: '—') . '</b>'],
            //  DEF-15 (owner, 21 Nov 2026): the business unit is shown, and set under "Edit the details".
            [ucfirst(Tl('sbu')), '<b>' . e(($o['sbu'] ?? '') !== '' ? ($sbuL[$o['sbu']] ?? $o['sbu']) : '—') . '</b>'],
            ['At this step for', $stalled ? '<span class="rec-tag bad">' . (int)$days . ' days — late</span>' : '<b>' . (int)$days . ' days</b>'],
            //  The quotations are one press away from the summary (the Sales bar above also has a "Quotes" link — this is the deal's own).
            [TP('quote'), '<button type="button" class="rec-lnk" data-tab-go="Quotes">' . count($quotes) . ' — on the Quotes tab</button>']];
    if (trim((string)$o['competitor']) !== '') $sum[] = ['Up against', '<b>' . e($o['competitor']) . '</b>'];
    if ($o['lead_id']) $sum[] = ['Came from', '<a href="/lead?id=' . (int)$o['lead_id'] . '">the lead</a>'];
    if ($effLbl !== '') $sum[] = [$won ? 'Effort to win' : 'Effort so far', '<b>' . e($effLbl) . '</b>'];
    rec_summary($sum);
    rec_card_close(); ?>

  <?php if (trim((string)$o['requirement']) !== ''): rec_card_open('What they want', 'target'); ?>
    <div style="white-space:pre-wrap;font-size:14px"><?= e($o['requirement']) ?></div>
  <?php rec_card_close(); endif; ?>

  <?php // ---- Project costing on this deal ------------------------------------
    if (!empty($costingCan)): $pcRow = $costing ?? null; $back = '/opportunity?id=' . $id;
      rec_card_open('Project costing', 'calc'); ?>
    <?php if ($pcRow): ?>
      <?php rec_summary([['Costing', '<a href="/project-costing?id=' . (int)$pcRow['id'] . '"><b>' . e($pcRow['code']) . '</b> — ' . e($pcRow['title']) . '</a>'],
                         ['Revenue', '<b>' . fmoney_short($pcRow['exp_revenue']) . '</b>']]); ?>
      <?php $pcLock = function_exists('pc_unlink_block_deal') ? pc_unlink_block_deal($id) : '';
            if ($pcLock !== ''): ?>
        <p class="rec-note" title="<?= e($pcLock) ?>">🔒 Stays linked — this deal <?= !empty($o['contract_id']) ? 'has a contract' : (trim((string)($o['sent_to_accounts_at'] ?? '')) !== '' ? 'is with Accounts' : 'has been won') ?></p>
      <?php else: ?>
      <form method="post" action="/project-costing-attach" style="margin-top:8px" onsubmit="return confirm('Unlink this costing from the deal?')">
        <input type="hidden" name="costing_id" value="<?= (int)$pcRow['id'] ?>"><input type="hidden" name="opportunity_id" value="0"><input type="hidden" name="back" value="<?= e($back) ?>">
        <button class="btn small secondary">Unlink</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="rec-note" style="margin-top:0">Not linked yet.</p>
      <form method="post" action="/project-costing-new" style="display:inline">
        <input type="hidden" name="opportunity_id" value="<?= $id ?>"><input type="hidden" name="client_id" value="<?= (int)($o['client_id'] ?? 0) ?>">
        <input type="hidden" name="title" value="<?= e(trim((string)($o['name'] ?? '')) ?: ($o['ref'] ?? 'Costing')) ?>">
        <button class="btn small primary">＋ Create costing for this deal</button>
      </form>
      <?php if (!empty($costingUnlinked)): ?>
      <form method="post" action="/project-costing-attach" style="display:flex;gap:6px;align-items:center;margin-top:8px;flex-wrap:wrap">
        <input type="hidden" name="opportunity_id" value="<?= $id ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
        <label class="sr-only" for="pc-att">Attach an existing costing</label>
        <select name="costing_id" id="pc-att" class="form-control" style="flex:1;min-width:180px"><option value="">attach existing…</option>
          <?php foreach ($costingUnlinked as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['code'] . ' — ' . $u['title']) ?></option><?php endforeach; ?>
        </select><button class="btn small secondary">Attach</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  <?php rec_card_close(); endif; ?>
</div>
</div>

<?php // =================================================================== QUOTES ?>
<div data-tab="Quotes" data-tab-icon="<?= e(rec_icon_path('file')) ?>" data-count="<?= count($quotes) ?>">
<?php rec_card_open('Quotations on this deal (' . count($quotes) . ')', 'file'); ?>
  <?php if ($canEdit && $open): ?>
    <form method="post" action="/opportunity-new-quote" style="margin:0 0 8px">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="rec-btn"<?= (empty($o['partner_id']) && trim((string)$o['partner_name']) === '') ? ' disabled title="Type the customer\'s name on the deal first"' : '' ?>><?= rec_icon('plus') ?> ＋ Create a new quotation</button>
    </form>
  <?php endif; ?>
  <p class="rec-note" style="margin-top:0">Create a quotation straight from the deal — it carries the customer and the working value, and lands on the quote so you can add line items and submit. (Or attach an existing one below.) Keeping them all here stops the forecast counting the same business more than once.</p>

  <?php // Owner, 13 Nov 2026 (bounce list 2.1): a deal no longer needs a customer RECORD
        //   to quote. The yellow "Link the customer to raise a quotation" stop is gone;
        //   what is left is a quiet, optional way to tie the deal to a customer who is
        //   already on the master. ?>
  <?php if ($canEdit && $open && empty($o['partner_id'])): ?>
    <p class="rec-note">
      <?= e(trim((string)$o['partner_name']) !== '' ? $o['partner_name'] : 'This customer') ?> is not on the client master yet — that is fine.
      The <?= e($Q) ?> goes out in their name, and they are added to the master by itself when they accept it.</p>
    <?php if (!empty($clients)): ?>
      <details class="fold" style="margin-top:8px">
        <summary>Already a customer? <span class="sub">pick them from the master</span></summary>
        <div class="fold-body">
          <form method="post" action="/opportunity-edit" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="ff" style="flex:1;min-width:220px"><label for="opp-cust-open">Customer</label>
              <select id="opp-cust-open" name="partner_id" class="form-control searchable">
                <option value="">— pick a customer —</option>
                <?php foreach ($clients as $cl): ?>
                  <option value="<?= (int)$cl['id'] ?>"><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <button class="btn small secondary" type="submit">Set the customer</button>
          </form>
        </div>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <?php foreach ($quotes as $q): [$qs, $qt] = rec_quote_status($q['status']); ?>
    <div class="rec-li" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center">
      <div style="min-width:0">
        <div class="top"><a href="/quote?id=<?= (int)$q['id'] ?>"><b><?= e($q['quote_no']) ?></b></a><?php if ((int)$q['rev']): ?><span class="rec-tag mut">Rev <?= (int)$q['rev'] ?></span><?php endif; ?></div>
        <div style="margin-top:6px"><span class="rec-tag <?= e($qt) ?>"><?= e($qs) ?></span> <span class="muted" style="font-size:12.5px">· <?= e(fmoney($q['total_amount'])) ?></span></div>
      </div>
      <?php if ($canEdit && $open): ?>
        <form method="post" action="/opportunity-quote" style="display:inline">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="quotation_id" value="<?= (int)$q['id'] ?>">
          <input type="hidden" name="act" value="unlink">
          <button class="btn small secondary">Detach</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$quotes): ?><p class="muted" style="margin:10px 0 0">None yet — that's normal for a deal you are still working on.</p><?php endif; ?>

  <?php if ($canEdit && $open && $openQuotes): ?>
    <form method="post" action="/opportunity-quote" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="ff" style="flex:1;min-width:220px"><label for="oq">Attach a quotation</label>
        <select id="oq" name="quotation_id" class="searchable">
          <?php foreach ($openQuotes as $q): ?>
            <option value="<?= (int)$q['id'] ?>"><?= e($q['quote_no']) ?><?= (int)$q['rev'] ? ' r' . (int)$q['rev'] : '' ?> — <?= e(fmoney($q['total_amount'])) ?> (<?= e(rec_quote_status($q['status'])[0]) ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <button class="btn small">Attach</button>
    </form>
  <?php endif; ?>
  <?php // Owner, 13 Nov 2026 (bounce list 2.5): "Generate" is only for a deal already
        //  WON without a quotation, that wants a quotation for the record. ?>
  <?php if ($canEdit && ($o['status'] ?? '') === 'WON' && !empty($o['partner_id']) && !$quotes): ?>
      <form method="post" action="/opportunity-generate-quote" style="margin-top:12px">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn small secondary" type="submit">＋ Generate quotation</button>
        <span class="muted" style="font-size:12px;margin-left:6px">This deal was won without a <?= e($Q) ?>. This drafts one from it, for the record.</span>
      </form>
  <?php endif; ?>
<?php rec_card_close(); ?>
</div>

<?php // =================================================================== ACTIONS ?>
<div data-tab="Actions" data-tab-icon="<?= e(rec_icon_path('swap')) ?>">
<?php if ($won): ?>
  <div class="rec-ban ok sxd-won"><h4><?= rec_icon('trophy', 's') ?> Won — <?= e($custName ?: 'the customer') ?> said yes. 🎉</h4>
    <?php if (function_exists('opp_won_next')): ?><p><?= e(opp_won_next($o)) ?></p><?php endif; ?></div>
<?php endif; ?>
<?php // ---- Won: to Accounts for a contract, or straight to a work order -----
      // The governed path (recommended): hand it to Accounts, who register a
      // contract. The quick path (a one-off with no contract): raise the work
      // order straight onto a call. Shown only when operations is installed. ?>
<?php if ($won && $orderBlock !== 'not-installed'): ?>
  <?php // The order raised / contract state / missing customer show above the tabs (#take there). ?>
  <?php if (empty($o['call_id']) && empty($contractRow) && $orderBlock === ''): ?>
    <?php // Owner, 13 Nov 2026 (bounce list 2.2): a deal won THROUGH A QUOTATION goes to
          // Accounts through that quotation — the contract is registered there.
          if (!empty($winQuote)):
            $wqC = $winQuote['contract'] ?? null; $wqNo = trim((string)($winQuote['contract_number'] ?? ''));
            $wqOs = $wqC ? strtoupper((string)($wqC['open_status'] ?? '') ?: 'OPEN') : ''; ?>
      <?php rec_card_open('✓ Won through ' . $Q . ' ' . $winQuote['label'], 'trophy', '', 'id="take"'); ?>
        <p style="font-size:13.5px;margin:0">
          <?php if ($wqNo === ''): ?>
            It is with Accounts — they register the contract on that <?= e($Q) ?>. There is nothing to send from the deal.
          <?php elseif ($wqOs === 'PENDING' && $wqC): ?>
            Contract <b><?= e($wqNo) ?></b> is registered and waiting for approval. <?= e(contract_waiting_words($wqC)) ?>
          <?php elseif ($wqOs === 'OPEN' || !$wqC): ?>
            Contract <b><?= e($wqNo) ?></b> is open — the <?= e(Tlp('call')) ?> are raised from it.
          <?php else: ?>
            Contract <b><?= e($wqNo) ?></b> — <?= e(strtolower(CONTRACT_OPEN_STATES[$wqOs] ?? $wqOs)) ?>.
          <?php endif; ?>
        </p>
        <?php if (can('mod.quotes.view') || is_master()): ?>
          <div style="margin-top:8px"><a class="btn small secondary" href="/quote?id=<?= (int)$winQuote['id'] ?>#contract">Open <?= e($Q) ?> <?= e($winQuote['label']) ?></a></div>
        <?php endif; ?>
      <?php rec_card_close(); ?>
    <?php elseif (!empty($o['sent_to_accounts_at'])): ?>
      <?php rec_card_open('✓ Handed to Accounts — awaiting contract registration', 'check', '', 'id="take"'); ?>
        <p class="muted" style="font-size:13px;margin:0">Sent on <?= e(fdate(substr((string)$o['sent_to_accounts_at'], 0, 10))) ?>.
          Accounts register the contract, it is approved under your company's rule, then Operations raise the <?= e(Tlp('call')) ?> from it.
          <?php if (!$canRegisterContract): ?>There is nothing more for Sales to do here.<?php endif; ?></p>
        <?php if ($canRegisterContract): ?>
          <form method="post" action="/opportunity-contract" style="margin-top:12px">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div style="font-weight:600;margin-bottom:8px">Register the contract <span class="muted" style="font-weight:400">— for <?= e($o['partner_name'] ?: 'this customer') ?>, agreed value <?= e(fmoney($o['value'])) ?></span></div>
            <div class="form-grid" style="gap:12px 16px">
              <div><label>Branch</label>
                <select class="form-control" name="branch_id">
                  <option value="">— <?= e($o['office_name'] ?: 'not set') ?> —</option>
                  <?php foreach ($offices as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === (int)$o['office_id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
                </select></div>
              <div><label>Contract number</label><input class="form-control" name="contract_number" maxlength="80" placeholder="The number they gave, or auto">
                <label class="ff-check" style="margin-top:4px"><input type="checkbox" name="auto_contract" value="1"> Auto-generate</label></div>
              <div><label>Start date</label><input class="form-control" type="date" name="start_date"></div>
              <div><label>End date</label><input class="form-control" type="date" name="end_date"></div>
            </div>
            <button class="btn" style="margin-top:12px">Register the contract</button>
          </form>
        <?php endif; ?>
      <?php rec_card_close(); ?>
    <?php else: ?>
      <?php rec_card_open('Take this won deal forward', 'flow', '', 'id="take"'); ?>
        <form method="post" action="/opportunity-send-to-accounts" style="margin:0">
          <input type="hidden" name="id" value="<?= $id ?>">
          <b>Won directly, or an order received without a quotation?</b>
          <p class="muted" style="font-size:13px;margin:6px 0 10px">
            Send it to <b>Accounts</b> to register the contract — no quotation needed. Accounts register it, it is
            approved under your company's rule, then Operations raise the <?= e(Tlp('call')) ?> from the contract. This is the
            same governed path a quoted win takes, so the sale still passes through Finance.</p>
          <button class="btn">Send to Accounts to register the contract</button>
        </form>
      <?php rec_card_close(); ?>
    <?php endif; ?>

    <?php // The quick path — a direct work order with no contract. Kept as a secondary,
          // folded option; only for someone who can actually raise operations orders. ?>
    <?php if ($canOrder): ?>
    <details class="fold" style="margin-top:12px">
      <summary>Or raise a work order directly <span class="sub">a one-off with no contract — set the rate on the order</span></summary>
      <div class="fold-body">
      <form method="post" action="/opportunity-raise-order" style="border-left:3px solid var(--line);padding-left:12px">
        <input type="hidden" name="id" value="<?= $id ?>">
        <p class="muted" style="font-size:13px;margin:0 0 10px">
          Carries the customer, the agreed value and the branch straight onto the order.
          <?php if ($orderQuote): ?>
            It will carry <b><?= e($orderQuote['quote_no']) ?><?= (int)$orderQuote['rev'] ? ' r' . (int)$orderQuote['rev'] : '' ?></b>
            (<?= e(rec_quote_status($orderQuote['status'])[0]) ?>, <?= e(fmoney($orderQuote['total_amount'])) ?>).
          <?php else: ?>
            <b>No quotation is attached</b>, so the order carries the estimate and somebody sets the rate on it, and it does not pass through Finance.
          <?php endif; ?>
        </p>
        <div class="form-grid" style="gap:12px 16px">
          <div><label>Executing branch</label>
            <select class="form-control" name="executing_office_id">
              <option value="">— <?= e($o['office_name'] ?: 'not set') ?> —</option>
              <?php foreach ($offices as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === (int)$o['office_id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label>Contract number</label><input class="form-control" name="contract_number" maxlength="80" value="<?= e($orderQuote['contract_number'] ?? '') ?>" placeholder="If they gave one"></div>
          <div><label>Wanted by</label><input class="form-control" type="date" name="inspection_required_date"></div>
        </div>
        <button class="btn secondary" style="margin-top:12px">Raise the work order</button>
      </form>
      </div>
    </details>
    <?php endif; ?>
  <?php endif; ?>
<?php elseif (!$open): ?>
  <p class="muted" style="margin:0 0 12px">This <?= e($D) ?> is closed. To change anything, reopen it from the Overview.</p>
<?php endif; ?>

<?php // ---- Move it to the next step --------------------------------------- ?>
<?php if ($canEdit && $open): ?>
<form method="post" action="/opportunity-move" class="rec-card" id="move">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="rec-ch"><span class="ico"><?= rec_icon('swap') ?></span><h3>Move it to the next step</h3></div>
  <p class="rec-note" style="margin:-6px 0 12px">Pick the step this deal has genuinely reached and press <b>Move it</b>.
    <b>Won</b> closes it as a sale and lets you raise the order. <b>Lost</b> closes it — and asks why.</p>
  <div class="form-grid" style="gap:12px 16px">
    <div><label for="opp-stage">Move it to</label>
      <?php //  D3 (owner, 21 Nov 2026): which stages need a quotation this deal does not have yet.
            $needQ = []; if (function_exists('opp_needs_quote_for')) foreach ($stages as $s) if (opp_needs_quote_for($o, $s) !== '') $needQ[(int)$s['id']] = true; ?>
      <select class="form-control" name="stage_id" id="opp-stage">
        <?php foreach ($stages as $s): ?>
          <option value="<?= (int)$s['id'] ?>" data-kind="<?= e($s['kind']) ?>"<?= isset($needQ[(int)$s['id']]) ? ' data-needq="1"' : '' ?> <?= (int)$s['id'] === (int)$o['stage_id'] ? 'selected' : '' ?>>
            <?= e($s['name']) ?><?= (int)$s['probability'] ? ' (' . (int)$s['probability'] . '%)' : '' ?><?= $s['kind'] === 'WON' ? ' — closes it as a sale' : ($s['kind'] === 'LOST' ? ' — closes it as lost' : '') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="ff-help">The one it is on now is where it stays if you change nothing.</span></div>
    <?php //  A move that needs approval carries a note for the approver — the server always took
          //  one (gate_note); only the box was missing (approved design s3-deal). ?>
    <div><label for="opp-gnote">Note for the approver <span class="muted">— if this move needs approval</span></label>
      <input class="form-control" id="opp-gnote" name="gate_note" maxlength="500" placeholder="e.g. Client asked for 5% off"></div>
  </div>
  <div id="opp-lost" style="display:none;margin-top:10px;border-left:3px solid var(--bad,#dc2626);padding-left:12px">
    <p class="muted" style="font-size:12.5px;margin:0 0 8px">You are closing this as <b>lost</b>. A reason is required — it is the only thing that makes a lost deal tell you anything later.</p>
    <div class="form-grid" style="gap:12px 16px">
      <div class="ff"><label>Why it was lost</label>
        <select class="form-control" name="lost_reason"><option value="">choose a reason…</option>
          <?php foreach ($lostReasons as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Lost to</label><input class="form-control" name="lost_to" maxlength="200" placeholder="Which competitor, if you know"></div>
      <div class="ff-wide"><label>What actually happened</label><input class="form-control" name="lost_note" maxlength="500" placeholder="Optional, but useful"></div>
    </div>
  </div>
  <?php if ($needQ): ?>
  <div id="opp-needq" style="display:none;margin-top:10px;border-left:3px solid var(--warn,#b45309);padding-left:12px">
    <p class="muted" style="font-size:12.5px;margin:0 0 8px">This step needs a <?= e(Tl('quote')) ?> on the deal, and it has none yet. Make or link the <?= e(Tl('quote')) ?> first — or, if it went to the <?= e(Tl('client')) ?> outside EXAACT, say so here. The reason is kept in the deal's history.</p>
    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="sent_outside" value="1"> Sent outside EXAACT</label>
    <div class="ff" style="margin-top:8px"><label for="opp-outr">Why / how it was sent</label>
      <input class="form-control" id="opp-outr" name="outside_reason" maxlength="400" placeholder="e.g. e-mailed from Outlook on 18 Nov, ref ABC/123"></div>
  </div>
  <?php endif; ?>
  <button class="btn" style="margin-top:12px">Move it</button>
</form>
<script>
(function () {
  var sel = document.getElementById('opp-stage'), lost = document.getElementById('opp-lost'), nq = document.getElementById('opp-needq');
  if (!sel || !lost) return;
  function sync() {
    var o = sel.options[sel.selectedIndex];
    if (nq) nq.style.display = (o && o.getAttribute('data-needq') === '1') ? 'block' : 'none';
    lost.style.display = (o && o.getAttribute('data-kind') === 'LOST') ? 'block' : 'none';
  }
  sel.addEventListener('change', sync); sync();
})();
</script>
<?php endif; ?>

<?php // ---- Edit the details ------------------------------------------------ ?>
<?php if ($canEdit && $open): ?>
<section class="rec-card wide">
<details id="edit"<?= !empty($_GET['edit']) ? ' open' : '' ?>>
  <summary class="rec-ch" style="list-style:none;cursor:pointer;margin:0"><span class="ico"><?= rec_icon('pen') ?></span><h3>Edit the details</h3><span class="rec-lnk"><?= rec_icon('down', 's') ?></span></summary>
  <form method="post" action="/opportunity-edit" style="margin-top:12px">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-grid" style="gap:12px 16px">
      <div class="ff-wide"><label>Name</label><input class="form-control" name="name" value="<?= e($o['name']) ?>" maxlength="255"></div>
      <div class="ff"><label for="opp-partner">Customer</label>
        <select class="form-control searchable" id="opp-partner" name="partner_id">
          <option value="">— not on the master yet —</option>
          <?php foreach ($clients as $cl): ?>
            <option value="<?= (int)$cl['id'] ?>" <?= (int)$cl['id'] === (int)$o['partner_id'] ? 'selected' : '' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="ff-help">A deal from a lead has none until the lead is converted. The order cannot be raised without it.</span></div>
      <div><label>Worth about</label><input class="form-control" name="value" type="number" step="0.01" min="0" value="<?= e($o['value']) ?>"></div>
      <div><label>Hoped to close</label><input class="form-control" type="date" name="expected_close" value="<?= e($o['expected_close']) ?>"></div>
      <div class="ff"><label>Handled by</label>
        <select class="form-control searchable" name="owner_user_id">
          <option value="">— nobody yet —</option>
          <?php foreach (($users ?? []) as $uu): ?>
            <option value="<?= (int)$uu['id'] ?>" <?= (int)($o['owner_user_id'] ?? 0) === (int)$uu['id'] ? 'selected' : '' ?>>
              <?= e(trim($uu['first_name'] . ' ' . $uu['last_name']) ?: $uu['username']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (trim((string)($o['owner_name'] ?? '')) !== '' && !(int)($o['owner_user_id'] ?? 0)): ?>
          <div class="ff-help">Held against the typed name “<?= e($o['owner_name']) ?>”, not linked to a login. Pick the person to make it a real allocation.</div>
        <?php endif; ?></div>
      <?php if (!empty($pipelines)): ?>
        <div class="ff"><label>Set of steps</label>
          <select class="form-control" name="pipeline_id">
            <?php foreach ($pipelines as $pl): ?>
              <option value="<?= (int)$pl['id'] ?>" <?= (int)$pl['id'] === (int)$o['pipeline_id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="ff-help">Changing this moves the deal to the first open step of the set you pick, and the move is written into its history.</div></div>
      <?php endif; ?>
      <div><label>Branch</label>
        <select class="form-control" name="office_id"><option value="">—</option>
          <?php foreach ($offices as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === (int)$o['office_id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
        </select></div>
      <?php //  DEF-15 — the business unit: the units this person's scope covers, and the deal's own (kept as it is if not changed). ?>
      <div class="ff"><label for="opp-sbu"><?= e(ucfirst(Tl('sbu'))) ?></label>
        <select class="form-control" id="opp-sbu" name="sbu"><option value="">— not decided yet —</option>
          <?php $sbuOpts = $sbus ?? []; $sbuCur = (string)($o['sbu'] ?? '');
            if ($sbuCur !== '' && !isset($sbuOpts[$sbuCur])) $sbuOpts = [$sbuCur => ($sbuL[$sbuCur] ?? $sbuCur)] + $sbuOpts;
            foreach ($sbuOpts as $sk => $sv): ?><option value="<?= e($sk) ?>"<?= $sbuCur === (string)$sk ? ' selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
        </select>
        <div class="ff-help">The win % is not typed — it follows the stage.</div></div>
      <div><label>Up against</label><input class="form-control" name="competitor" value="<?= e($o['competitor']) ?>" maxlength="200"></div>
      <div><label>Contact</label><input class="form-control" name="contact_name" value="<?= e($o['contact_name']) ?>" maxlength="150"></div>
      <div><label>Contact e-mail</label><input class="form-control" name="contact_email" value="<?= e($o['contact_email']) ?>" maxlength="200"></div>
      <div><label>Contact phone</label><input class="form-control" name="contact_phone" value="<?= e($o['contact_phone']) ?>" maxlength="60"></div>
      <div><label>Next thing to do</label><input class="form-control" name="next_action" value="<?= e($o['next_action']) ?>" maxlength="255"></div>
      <div><label>By when</label><input class="form-control" type="date" name="next_action_on" value="<?= e($o['next_action_on']) ?>"></div>
      <div class="ff-wide"><label>What they want</label><textarea class="form-control" name="requirement" rows="3"><?= e($o['requirement']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Save</button></div>
  </form>
</details>
</section>
<?php endif; ?>

<?php // Delete — administrators only, and only while it is safe to remove: not
      //   won, and no quotation linked. Otherwise it is part of the record. ?>
<?php if ($canDelete): rec_card_open('Remove this ' . $D, 'trash', '', 'id="remove"'); ?>
  <p class="muted" style="margin:0">Removes <?= e($o['ref']) ?> for good. Lost deals are kept as history — consider marking it lost instead.</p>
  <form method="post" action="/opportunity-delete" onsubmit="return confirm('Delete <?= e($D) ?> <?= e($o['ref']) ?>? This cannot be undone. (Lost deals are kept as history — consider marking it lost instead.)')">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="rec-btn dang" type="submit"><?= rec_icon('trash') ?> Delete</button></form>
<?php rec_card_close(); endif; ?>
</div>

<?php // =================================================================== HISTORY ?>
<div data-tab="History" data-tab-icon="<?= e(rec_icon_path('hist')) ?>">
  <?php // DEF-SAL-030 — the History tab is always there; a new deal says how it started. ?>
  <?php rec_card_open('How it moved', 'flow', '<span class="rec-tag mut">' . ($history ? count($history) . ' move' . (count($history) === 1 ? '' : 's') : 'no moves yet') . '</span>', 'id="flow"'); ?>
    <?php if (!$history): ?>
    <p class="muted" style="margin:0">Opened<?= !empty($o['created_by']) ? ' by ' . e($o['created_by']) : '' ?><?= !empty($o['created_at']) ? ' on ' . e(fdate(substr((string)$o['created_at'], 0, 10))) : '' ?> — it has not moved to another step yet.</p>
    <?php else: ?>
    <div class="rec-scroll"><table class="rec-tbl">
      <caption class="sr-only">Step history</caption>
      <thead><tr><th scope="col">When</th><th scope="col">From</th><th scope="col">To</th><th scope="col" style="text-align:right">Days there</th><th scope="col">By</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr><td style="white-space:nowrap"><?= e(fdate(substr((string)$h['moved_at'], 0, 10))) ?></td>
            <td><?= e($h['from_name'] ?: '—') ?></td><td><?= e($h['to_name']) ?></td>
            <td style="text-align:right"><?= (int)$h['days_in_previous'] ?></td><td><?= e($h['moved_by']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  <?php rec_card_close(); ?>

  <?php // The ONE log form (views/ops/_log_form.php) — the same one the lead page and
        // Sales → Today use (owner, 19 Nov 2026). Now inside the History tab.
        if ($canEdit): $lfBack = (string)($_GET['back'] ?? '');
          $lfBack = function_exists('sx_safe_back') ? sx_safe_back($lfBack, '') : '';
          $lf = ['kind' => 'OPPORTUNITY', 'id' => $id, 'office' => (int)($o['office_id'] ?? 0), 'who' => (string)($o['contact_name'] ?? ''),
                 'back' => $lfBack !== '' ? $lfBack : '/opportunity?id=' . $id . '#log', 'open' => !empty($_GET['log'])]; ?>
    <section class="rec-card"><?php include __DIR__ . '/_log_form.php'; ?></section>
  <?php endif; ?>
  <?php // Module 49 — the activity spine, shown where the record is read. ?>
  <?php if (function_exists('act_render_timeline')) act_render_timeline('OPPORTUNITY', (int)$o['id'], 'Activity'); ?>
</div>

</div><!-- /data-tabs (opp) -->
</div><!-- /rec -->
<?php sxw_bottom('/opportunities'); ?>
