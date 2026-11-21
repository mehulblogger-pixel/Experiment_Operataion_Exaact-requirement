<?php
// ============================================================================
//  THE INQUIRY PAGE — new, read-only (owner, 21 Nov 2026: DEF-14, approved design
//  s3-inq). Until now an inquiry could be seen only on its edit form, which needs
//  the edit right just to open, so a view-only person could not open one at all.
//  Built from the record-page kit, like the lead page:
//    header card ........ client, number, subject, status, contact, Edit / Project
//                         costing / Quote (each by its own right, as before)
//    three boxes ........ received, assigned to, business unit
//    Overview ........... where it stands (its quotations, drop / reopen — moved
//                         here from the edit form, the same forms), what they want
//    Details ............ every field, where it came from
//    Quotes ............. its quotations in plain words
//    History ............ the activity timeline (moved here from the edit form)
//  Opens with the Inquiries view right and the register's branch and
//  business-unit scope (lib/crm.php, route 'inquiry').
// ============================================================================
require_once __DIR__ . '/../_rec_ui.php';
require_once __DIR__ . '/../_sxw_ui.php';
$id = (int)$inq['id'];
$iSt = (string)($inq['status'] ?: 'OPEN');
$Q = Tl('quote');
$canWork = !function_exists('crm_inquiry_can_work') || crm_inquiry_can_work($inq);
$canEditInq = can('mod.inquiries.edit') && $canWork;
$mayQuote = can('mod.quotes.edit') && $canWork;
$canCost = function_exists('pc_can_edit') && pc_can_edit();
$quotes = $quotes ?? [];
$clientName = trim((string)($inq['client_disp'] ?? '')) ?: (trim((string)($inq['client_name'] ?? '')) ?: '—');
$who = trim((string)($inq['owner_first'] ?? '') . ' ' . (string)($inq['owner_last'] ?? '')) ?: (string)($inq['owner_username'] ?? '');
if ($who === '') $who = trim((string)($inq['lead_owner_name'] ?? ''));
$sbuL = function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : OPS_SBUS;
$srcL = function_exists('lk_options_or') ? lk_options_or('inquiry_source', INQUIRY_SOURCES) : INQUIRY_SOURCES;
$pillTone = ['OPEN' => 'new', 'QUOTED' => 'open', 'WON' => 'won', 'LOST' => 'lost', 'DROPPED' => 'lost'][$iSt] ?? 'open';
$words = ['OPEN' => 'Open — no ' . $Q . ' yet.', 'QUOTED' => 'Quoted — a ' . $Q . ' is out; this follows it.',
          'WON' => 'Won — its ' . $Q . ' was accepted.', 'LOST' => 'Lost — its ' . $Q . ' was lost or expired.',
          'DROPPED' => 'Dropped — closed without a ' . $Q . '.'];
$tel = function_exists('sx_tel') ? sx_tel($inq['contact_mobile'] ?? '') : '';
//  The current quotation the Quote button opens (the accepted one first, then the newest) — the register's own rule.
$iq = null;
foreach ($quotes as $qq) if (($qq['status'] ?? '') === 'ACCEPTED') { $iq = $qq; break; }
if (!$iq) foreach ($quotes as $qq) if (($qq['status'] ?? '') !== 'LOST') { $iq = $qq; break; }
if (!$iq && $quotes) $iq = $quotes[0];
$quoteBtns = function ($cls = 'rec-btn') use ($iSt, $iq, $mayQuote, $id, $Q) {
    $h = '';
    if ($iSt === 'OPEN' && $mayQuote) $h .= '<a class="' . $cls . '" href="/quote-new?inquiry=' . $id . '">' . rec_icon('file') . ' Quote</a>';
    elseif (in_array($iSt, ['QUOTED', 'WON'], true) && $iq && can('mod.quotes.view')) {
        $h .= '<a class="' . $cls . '" href="/quote?id=' . (int)$iq['id'] . '">' . rec_icon('file') . ' Open ' . e($iq['quote_no']) . '</a>';
        if ($iSt === 'QUOTED' && $mayQuote)
            $h .= '<a class="' . $cls . ' sec" href="/quote-new?inquiry=' . $id . '" onclick="return confirm(\'Make a second, separate ' . e($Q) . ' for this enquiry? To change the one already made, open it and press Revise instead.\')">' . rec_icon('plus') . ' another</a>';
    } elseif ($iSt === 'LOST' && $mayQuote) $h .= '<a class="' . $cls . '" href="/quote-new?inquiry=' . $id . '">' . rec_icon('file') . ' Quote again</a>';
    return $h;
};
?>
<div class="rec sxw has-bn sxq">
<?php sxw_nav('/inquiries'); ?>
<?= function_exists('chain_strip') ? chain_strip('INQUIRY', $id, 'INQUIRY', $id) : '' ?>

<div class="rec-top">
  <section class="rec-card" aria-label="The <?= e(Tl('inquiry')) ?>">
    <div class="rec-hd">
      <div class="rec-tile"><?= rec_icon('mail', 'l') ?></div>
      <h1><?= e($clientName) ?></h1>
      <span class="rec-pill static <?= e($pillTone) ?>"><?= e($statusOpts[$iSt] ?? $iSt) ?></span>
      <div class="id"><?= e(T('inquiry')) ?> <?= e($inq['inquiry_no']) ?> · <?= e($inq['subject'] ?: 'no subject') ?><?= !empty($inq['office_name']) ? ' · ' . e($inq['office_name']) . ' branch' : '' ?></div>
    </div>
    <div class="rec-who">
      <div>
        <div class="r"><span class="pr"><?= rec_icon('user', 's') ?><span><?= e($inq['contact_name'] ?: '—') ?></span></span>
          <?php if (trim((string)($inq['contact_mobile'] ?? '')) !== ''): ?><span class="sep"></span><span class="pr"><?= rec_icon('phone', 's') ?><span><?= $tel !== '' ? '<a href="' . e($tel) . '">' . e($inq['contact_mobile']) . '</a>' : e($inq['contact_mobile']) ?></span></span><?php endif; ?></div>
        <?php if (trim((string)($inq['contact_email'] ?? '')) !== ''): ?><div class="r"><span class="pr"><?= rec_icon('mail', 's') ?><span><a href="mailto:<?= e($inq['contact_email']) ?>"><?= e($inq['contact_email']) ?></a></span></span></div><?php endif; ?>
      </div>
      <div class="rec-acts sxq-acts">
        <?php if ($canEditInq): ?><a class="rec-ib" href="/inquiry-edit?id=<?= $id ?>" title="Edit this <?= e(Tl('inquiry')) ?>" aria-label="Edit this <?= e(Tl('inquiry')) ?>"><?= rec_icon('pen', 's') ?></a><?php endif; ?>
        <?php // A man-month price is costed first (owner, 20 Oct 2026); the costing is
              //  tagged with this enquiry and fills in its customer, branch and unit. ?>
        <?php if ($canCost): ?><a class="sxw-bt" href="/project-costings?inquiry_id=<?= $id ?>"><?= rec_icon('calc', 's') ?> ＋ Project costing</a><?php endif; ?>
        <?= $quoteBtns('sxw-bt p') ?>
      </div>
    </div>
  </section>
  <?php rec_kpis([['cal', trim((string)($inq['received_date'] ?? '')) !== '' ? fdate($inq['received_date']) : '—', 'Received'],
                  ['user', $who !== '' ? $who : 'Nobody yet', 'Assigned to'],
                  ['brief', ($inq['sbu'] ?? '') !== '' ? ($sbuL[$inq['sbu']] ?? $inq['sbu']) : '—', ucfirst(Tl('sbu'))]]); ?>
</div>

<?php if (!$canWork && can('mod.inquiries.edit')): ?>
  <div class="rec-ban info" role="status"><p style="margin:0">🔒 <?= e(crm_assignee_words((int)$inq['assigned_to'], Tl('inquiry'))) ?></p></div>
<?php endif; ?>

<div data-tabs data-tabs-key="inq" data-tabs-order="Overview,Details,Quotes,History" data-tabs-class="rec-tabs">

<?php // =================================================================== OVERVIEW ?>
<div data-tab="Overview" data-tab-icon="<?= e(rec_icon_path('home')) ?>">
<div class="rec-grid">
  <?php //  WHERE THIS ENQUIRY STANDS (owner, 13 Nov 2026 — "Automatic Won/Lost", "Only 'Dropped', with a reason").
    rec_card_open('Where it stands', 'flow'); ?>
    <p style="margin:0;font-size:14px"><span class="pill <?= ['OPEN' => 'p-warn', 'QUOTED' => 'p-info', 'WON' => 'p-ok', 'LOST' => 'p-bad', 'DROPPED' => 'p-mut'][$iSt] ?? 'p-mut' ?>"><?= e($statusOpts[$iSt] ?? $iSt) ?></span>
      <?= e($words[$iSt] ?? '') ?></p>
    <?php if ($iSt === 'QUOTED'): ?><p class="rec-note">This <?= e(Tl('inquiry')) ?> follows its <?= e($Q) ?> by itself — it becomes <b>Won</b> when the <?= e($Q) ?> is accepted and <b>Lost</b> when every <?= e($Q) ?> is lost or expired.</p><?php endif; ?>
    <?php if ($iSt === 'DROPPED'): ?>
      <p class="muted" style="margin:6px 0 0;font-size:13px"><?= e(INQUIRY_DROP_REASONS[$inq['drop_reason'] ?? ''] ?? 'No reason recorded') ?><?= trim((string)($inq['drop_note'] ?? '')) !== '' ? ' — ' . e($inq['drop_note']) : '' ?><?= !empty($inq['dropped_by']) ? ' · by ' . e($inq['dropped_by']) . (!empty($inq['dropped_at']) ? ', ' . e(fdate(substr((string)$inq['dropped_at'], 0, 10))) : '') : '' ?></p>
    <?php endif; ?>
    <?php if ($quotes): ?>
      <div class="rec-scroll" style="margin-top:10px"><table class="rec-tbl">
        <thead><tr><th scope="col"><?= e(TH('quote')) ?></th><th scope="col">Status</th><th scope="col" style="text-align:right">Value</th></tr></thead>
        <tbody><?php foreach ($quotes as $qq): [$qs, $qt] = rec_quote_status($qq['status']); ?>
          <tr><td><a href="/quote?id=<?= (int)$qq['id'] ?>"><?= e($qq['quote_no']) ?><?= (int)$qq['rev'] ? ' r' . (int)$qq['rev'] : '' ?></a></td>
              <td><span class="rec-tag <?= e($qt) ?>"><?= e($qs) ?></span></td>
              <td style="text-align:right"><?= e(fmoney($qq['total_amount'])) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <?php if ($canEditInq && !$quotes && $iSt !== 'DROPPED'): ?>
      <details class="fold" style="margin-top:10px">
        <summary>Drop this <?= e(Tl('inquiry')) ?> <span class="sub">closed without a <?= e($Q) ?> — say why</span></summary>
        <div class="fold-body">
          <form method="post" action="/inquiry-drop" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="ff"><label for="inq-drop-why">Why</label>
              <select class="form-control" id="inq-drop-why" name="drop_reason" required><option value="">— pick one —</option>
                <?php foreach (INQUIRY_DROP_REASONS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
            <div class="ff" style="flex:1;min-width:200px"><label for="inq-drop-note">Note <span class="muted">optional</span></label><input class="form-control" id="inq-drop-note" name="drop_note" maxlength="300" placeholder="e.g. outside our scope"></div>
            <button class="btn secondary" type="submit">Drop it</button>
          </form>
        </div>
      </details>
    <?php elseif ($canEditInq && $iSt === 'DROPPED'): ?>
      <form method="post" action="/inquiry-reopen" style="margin-top:10px"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn secondary small" type="submit">Reopen it</button></form>
    <?php elseif ($quotes): ?>
      <p class="rec-note">With a <?= e($Q) ?> raised, this follows the <?= e($Q) ?>. Dropping an <?= e(Tl('inquiry')) ?> is only possible while it has no <?= e($Q) ?>; if the work is not coming, mark the <?= e($Q) ?> lost.</p>
    <?php endif; ?>
    <?php $qb = $quoteBtns(); if ($qb !== ''): ?><div class="rec-push"><?= $qb ?></div><?php endif; ?>
  <?php rec_card_close(); ?>

  <?php rec_card_open('What they want', 'target', $canEditInq ? '<a class="rec-lnk" href="/inquiry-edit?id=' . $id . '">' . rec_icon('pen', 's') . ' Edit</a>' : '');
    rec_rows([['file', 'Subject', e($inq['subject'] ?: '—')],
              ['task', 'Service requirement', trim((string)($inq['service_requirement'] ?? '')) !== '' ? '<span style="white-space:pre-wrap">' . e($inq['service_requirement']) . '</span>' : '—'],
              ['note', 'Notes', e(trim((string)($inq['notes'] ?? '')) ?: '—')],
              ['tag', 'Source', e($srcL[$inq['source']] ?? $inq['source'] ?: '—')]]);
    rec_card_close(); ?>
</div>
</div>

<?php // =================================================================== DETAILS ?>
<div data-tab="Details" data-tab-icon="<?= e(rec_icon_path('list')) ?>">
<div class="rec-grid">
  <?php rec_card_open('Where it came from', 'flow');
    rec_rows([['users', 'Lead', !empty($inq['lead_id']) ? '<a href="/lead?id=' . (int)$inq['lead_id'] . '">' . e(($inq['origin_ref'] ?? '') ?: 'the lead') . (!empty($inq['origin_company']) ? ' · ' . e($inq['origin_company']) : '') . '</a>' : '—'],
              ['building', ucfirst(Tl('client')), !empty($inq['client_id']) ? '<a href="/client?id=' . (int)$inq['client_id'] . '">' . e($clientName) . (!empty($inq['client_code']) ? ' (' . e($inq['client_code']) . ')' : '') . '</a>' : e($clientName) . (trim((string)($inq['client_name'] ?? '')) !== '' ? ' <span class="muted">— not on the master yet</span>' : '')],
              ['brief', ucfirst(Tl('opportunity')), !empty($deal) ? '<a href="/opportunity?id=' . (int)$deal['id'] . '">' . e($deal['ref']) . ' · ' . e($deal['name']) . '</a>' : '—'],
              ['pin', 'Branch', !empty($inq['office_name']) ? e($inq['office_name']) : '<span class="muted" title="No branch set — every branch sees it">All branches</span>'],
              ['user', 'Recorded by', e(($inq['created_by'] ?? '') ?: '—') . (!empty($inq['created_at']) ? ', ' . e(fdate(substr((string)$inq['created_at'], 0, 10))) : '')]]);
    rec_card_close(); ?>
  <?php rec_card_open(TH('inquiry') . ' details', 'file', $canEditInq ? '<a class="rec-lnk" href="/inquiry-edit?id=' . $id . '">' . rec_icon('pen', 's') . ' Edit</a>' : '');
    rec_rows([['user', 'Contact person', e($inq['contact_name'] ?: '—')],
              ['mail', 'Contact e-mail', trim((string)($inq['contact_email'] ?? '')) !== '' ? e($inq['contact_email']) : '—'],
              ['phone', 'Contact mobile', trim((string)($inq['contact_mobile'] ?? '')) !== '' ? e($inq['contact_mobile']) : '—'],
              ['cal', 'Received on', trim((string)($inq['received_date'] ?? '')) !== '' ? e(fdate($inq['received_date'])) : '—'],
              ['user', 'Assigned to', $who !== '' ? e($who) : '<span class="muted">Nobody yet</span>'],
              ['brief', ucfirst(Tl('sbu')), e(($inq['sbu'] ?? '') !== '' ? ($sbuL[$inq['sbu']] ?? $inq['sbu']) : '—')],
              ['tag', 'Source', e($srcL[$inq['source']] ?? $inq['source'] ?: '—')],
              ['flow', 'Status', e($statusOpts[$iSt] ?? $iSt) . ' <span class="muted">— follows its ' . e($Q) . ', set by itself</span>']]);
    rec_card_close(); ?>
</div>
</div>

<?php // =================================================================== QUOTES ?>
<div data-tab="Quotes" data-tab-icon="<?= e(rec_icon_path('file')) ?>" data-count="<?= count($quotes) ?>">
<?php rec_card_open(ucfirst(Tlp('quote')) . ' (' . count($quotes) . ')', 'file'); ?>
  <?php foreach ($quotes as $qq): [$qs, $qt] = rec_quote_status($qq['status']); ?>
    <div class="rec-li">
      <div class="top"><a href="/quote?id=<?= (int)$qq['id'] ?>"><b><?= e($qq['quote_no']) ?></b></a><?php if ((int)$qq['rev']): ?><span class="rec-tag mut">Rev <?= (int)$qq['rev'] ?></span><?php endif; ?></div>
      <div style="margin-top:6px"><span class="rec-tag <?= e($qt) ?>"><?= e($qs) ?></span></div>
      <div class="rec-two" style="margin-top:8px;font-size:13px"><div><span class="muted">Value</span><br><b><?= e(fmoney($qq['total_amount'])) ?></b></div><div><span class="muted">Made</span><br><b><?= e(fdate(substr((string)$qq['created_at'], 0, 10))) ?></b></div></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$quotes): ?><p class="muted" style="margin:0 0 6px">None yet.</p><?php endif; ?>
  <?php $qb = $quoteBtns(); if ($qb !== ''): ?><div class="rec-two" style="margin-top:8px"><?= $qb ?></div><?php endif; ?>
<?php rec_card_close(); ?>
</div>

<?php // =================================================================== HISTORY ?>
<div data-tab="History" data-tab-icon="<?= e(rec_icon_path('hist')) ?>">
  <?php if (function_exists('act_render_timeline')) act_render_timeline('INQUIRY', $id, 'History of this ' . Tl('inquiry')); ?>
</div>

</div><!-- /data-tabs (inq) -->
</div><!-- /rec -->
<?php sxw_bottom('/inquiries'); ?>
