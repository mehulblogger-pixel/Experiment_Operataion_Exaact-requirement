<?php $isEdit = !empty($inq['id']);
  //  Assigned to someone else (owner, 14 Nov 2026, option C): view only — say who works on it.
  $inqWork = !$isEdit || !function_exists('crm_inquiry_can_work') || crm_inquiry_can_work($inq); ?>
<div class="master-head">
  <div><h1><?= $isEdit ? 'Edit inquiry — ' . e($inq['inquiry_no']) : 'New ' . Tl('client') . ' ' . Tl('inquiry') ?></h1>
    <p class="sub" style="margin:2px 0 0">Capture what the <?= e(Tl('client')) ?> asked for. You can raise a <?= e(Tl('quote')) ?> against it afterwards.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php // A man-month price is costed first (owner, 20 Oct 2026); the costing is
          //  tagged with this enquiry and fills in its customer, branch and unit. ?>
    <?php if ($isEdit && function_exists('pc_can_edit') && pc_can_edit()): ?>
      <a class="btn secondary" href="/project-costings?inquiry_id=<?= (int)$inq['id'] ?>">＋ Project costing</a>
    <?php endif; ?>
    <a class="btn secondary" href="<?= e(nav_back($cur)) ?>">← <?= e(nav_back_label($cur)) ?></a>
  </div>
</div>

<?php if (!empty($error)): ?><div class="msg msg-error" style="margin-bottom:12px"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($originRef)): ?><div class="msg" style="margin-bottom:12px;background:var(--soft);border:1px solid var(--line);border-radius:8px;padding:8px 12px">Came from lead <strong><?= e($originRef) ?></strong> — the client, contact and requirement were carried over automatically.</div><?php endif; ?>

<form method="post" action="/<?= $isEdit ? 'inquiry-edit?id=' . (int)$inq['id'] : 'inquiry-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label><?= e(Tl('client')) ?> * <a href="#" class="addlink" data-qa="client" data-target="select[name='client_id']">+ Add new</a></label>
      <select class="form-control searchable" name="client_id"><option value="">— pick or type below —</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)($inq['client_id'] ?? '')===(string)$cl['id']?'selected':'' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or client name (if not yet registered)</label><input class="form-control" name="client_name" value="<?= e($inq['client_name'] ?? '') ?>"></div>
    <?php //  Owner, 13 Nov 2026 (SAL test, step 4): a received date in the future "must show a warning sign
          //  and shall not be recorded". The browser stops at today; the warning says why; the server refuses. ?>
    <div class="ff"><label for="inq-recv">Received date</label><input class="form-control" type="date" id="inq-recv" name="received_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($inq['received_date'] ?? date('Y-m-d')) ?>" aria-describedby="inq-recv-warn">
      <div id="inq-recv-warn" class="msg msg-error" role="alert" style="display:none;margin-top:6px;padding:6px 10px;font-size:12.5px">⚠ This date is in the future. An <?= e(Tl('inquiry')) ?> can only be recorded as received today or earlier — it will not be saved.</div>
      <script>(function(){var d=document.getElementById('inq-recv'),w=document.getElementById('inq-recv-warn');if(!d||!w)return;
        var chk=function(){var bad=d.value!==''&&d.value>d.max;w.style.display=bad?'block':'none';d.setAttribute('aria-invalid',bad?'true':'false');};
        d.addEventListener('input',chk);d.addEventListener('change',chk);chk();})();</script></div>

    <div class="ff"><label>Contact person *</label><input class="form-control" name="contact_name" value="<?= e($inq['contact_name'] ?? '') ?>"></div>
    <div class="ff"><label>Contact email <span class="muted">— email or mobile</span></label><input class="form-control" type="email" name="contact_email" value="<?= e($inq['contact_email'] ?? '') ?>"></div>
    <div class="ff"><label>Contact mobile <span class="muted">— email or mobile</span></label><input class="form-control" name="contact_mobile" value="<?= e($inq['contact_mobile'] ?? '') ?>"></div>

    <div class="ff"><label><?= e(T('sbu')) ?> *</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($inq['sbu'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Source</label>
      <select class="form-control" name="source"><?php foreach ($sourceOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($inq['source'] ?? 'EMAIL')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <?php //  Owner, 13 Nov 2026: the status is not typed — Quoted / Won / Lost follow the quotation,
          //  and Dropped (with a reason) has its own button below the form. ?>
    <div class="ff"><label>Status</label>
      <div class="form-control" style="background:var(--soft);display:flex;align-items:center" aria-readonly="true"><?= e($statusOpts[$inq['status'] ?? 'OPEN'] ?? ($inq['status'] ?? 'Open')) ?></div>
      <div class="muted" style="font-size:11.5px;margin-top:3px">Follows its <?= e(Tl('quote')) ?> — set by itself.</div></div>

    <div class="ff"><label>Assigned to</label>
      <select class="form-control searchable" name="assigned_to"><option value="">—</option>
        <?php foreach ($users as $u): $nm = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= (string)($inq['assigned_to'] ?? '')===(string)$u['id']?'selected':'' ?>><?= e($nm) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>Subject *</label><input class="form-control" name="subject" value="<?= e($inq['subject'] ?? '') ?>" placeholder="e.g. Third-party inspection — Suryavan Mundra"></div>
    <div class="ff ff-wide"><label>Service requirement / details</label><textarea class="form-control" name="service_requirement" rows="3"><?= e($inq['service_requirement'] ?? '') ?></textarea></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($inq['notes'] ?? '') ?>"></div>
  </div>
  <div style="margin-top:16px">
    <?php if (!$inqWork): ?><div class="msg msg-info" style="margin:0 0 8px">🔒 <?= e(crm_assignee_words((int)$inq['assigned_to'], Tl('inquiry'))) ?></div><?php else: ?>
    <button class="btn" type="submit"><?= $inq ? 'Save inquiry' : 'Create inquiry' ?></button><?php endif; ?>
    <a class="btn secondary" href="/inquiries">Cancel</a>
  </div>
</form>

<?php //  WHERE THIS ENQUIRY STANDS (owner, 13 Nov 2026 — "Automatic Won/Lost", "Only 'Dropped', with a reason").
if ($isEdit):
  $iSt = (string)($inq['status'] ?: 'OPEN');
  $iQuotes = ops_all("SELECT id, quote_no, rev, status FROM quotations WHERE inquiry_id=? AND COALESCE(is_current,1)=1 ORDER BY id DESC", [(int)$inq['id']]) ?: [];
  $canEditInq = can('mod.inquiries.edit') && ($inqWork ?? true);
  $words = ['OPEN' => 'Open — no ' . Tl('quote') . ' yet.', 'QUOTED' => 'Quoted — a ' . Tl('quote') . ' is out; this follows it.',
            'WON' => 'Won — its ' . Tl('quote') . ' was accepted.', 'LOST' => 'Lost — its ' . Tl('quote') . ' was lost or expired.',
            'DROPPED' => 'Dropped — closed without a ' . Tl('quote') . '.']; ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Where it stands</h3>
  <p style="margin:0"><span class="pill <?= ['OPEN'=>'p-warn','QUOTED'=>'p-info','WON'=>'p-ok','LOST'=>'p-bad','DROPPED'=>'p-mut'][$iSt] ?? 'p-mut' ?>"><?= e($statusOpts[$iSt] ?? $iSt) ?></span>
    <?= e($words[$iSt] ?? '') ?></p>
  <?php if ($iSt === 'DROPPED'): ?>
    <p class="muted" style="margin:6px 0 0;font-size:13px"><?= e(INQUIRY_DROP_REASONS[$inq['drop_reason'] ?? ''] ?? 'No reason recorded') ?><?= trim((string)($inq['drop_note'] ?? '')) !== '' ? ' — ' . e($inq['drop_note']) : '' ?><?= !empty($inq['dropped_by']) ? ' · by ' . e($inq['dropped_by']) . (!empty($inq['dropped_at']) ? ', ' . e(fdate(substr((string)$inq['dropped_at'], 0, 10))) : '') : '' ?></p>
  <?php endif; ?>
  <?php if ($iQuotes): ?>
    <p style="margin:8px 0 0;font-size:13px"><?= e(TH('quote')) ?>:
      <?php foreach ($iQuotes as $i => $iq): ?><?= $i ? ' · ' : '' ?><a href="/quote?id=<?= (int)$iq['id'] ?>"><?= e($iq['quote_no']) ?><?= (int)$iq['rev'] ? ' r' . (int)$iq['rev'] : '' ?></a> <span class="muted">(<?= e(strtolower(QUOTE_STATUS[$iq['status']] ?? $iq['status'])) ?>)</span><?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($canEditInq && !$iQuotes && $iSt !== 'DROPPED'): ?>
    <details class="fold" style="margin-top:10px">
      <summary>Drop this <?= e(Tl('inquiry')) ?> <span class="sub">closed without a <?= e(Tl('quote')) ?> — say why</span></summary>
      <div class="fold-body">
        <form method="post" action="/inquiry-drop" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
          <input type="hidden" name="id" value="<?= (int)$inq['id'] ?>">
          <div class="ff"><label for="inq-drop-why">Why</label>
            <select class="form-control" id="inq-drop-why" name="drop_reason" required><option value="">— pick one —</option>
              <?php foreach (INQUIRY_DROP_REASONS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="ff" style="flex:1;min-width:200px"><label for="inq-drop-note">Note <span class="muted">optional</span></label><input class="form-control" id="inq-drop-note" name="drop_note" maxlength="300" placeholder="e.g. outside our scope"></div>
          <button class="btn secondary" type="submit">Drop it</button>
        </form>
      </div>
    </details>
  <?php elseif ($canEditInq && $iSt === 'DROPPED'): ?>
    <form method="post" action="/inquiry-reopen" style="margin-top:10px"><input type="hidden" name="id" value="<?= (int)$inq['id'] ?>"><button class="btn secondary small" type="submit">Reopen it</button></form>
  <?php elseif ($iQuotes): ?>
    <p class="muted" style="margin:8px 0 0;font-size:12.5px">With a <?= e(Tl('quote')) ?> raised, this follows the <?= e(Tl('quote')) ?>. If the work is not coming, mark the <?= e(Tl('quote')) ?> lost.</p>
  <?php endif; ?>
</div>
<div style="margin-top:16px"><?php if (function_exists('act_render_timeline')) act_render_timeline('INQUIRY', (int)$inq['id'], 'History of this ' . Tl('inquiry')); ?></div>
<?php endif; ?>
