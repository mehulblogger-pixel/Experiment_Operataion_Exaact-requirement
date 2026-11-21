<?php
// New / edit inquiry (owner, 21 Nov 2026: Sales redesign Stage 3, approved design
// s3-inqedit). The same boxes, names, checks and save as before — the one-time key,
// the 15-minute duplicate guard, the future-date refusal, the blocked-client refusal,
// the "may work it" lock — in two numbered sections with the Sales look. "Where it
// stands", Drop / Reopen and the history moved to the inquiry's own read-only page
// (DEF-14), and saving returns there (approved backend item 5). Saved by
// ops_crm_inquiries() in lib/crm.php. The old page: tests/fixtures/pre_stage3/inquiry_form.php.
require_once __DIR__ . '/../_sxw_ui.php';
$isEdit = !empty($inq['id']);
//  Assigned to someone else (owner, 14 Nov 2026, option C): view only — say who works on it.
$inqWork = !$isEdit || !function_exists('crm_inquiry_can_work') || crm_inquiry_can_work($inq);
$step = function ($n, $title, $sub = '') {
    echo '<details class="sxl-step" data-step="' . (int)$n . '" open><summary><i>' . (int)$n . '</i><span class="x"><b>' . e($title) . '</b>'
       . ($sub !== '' ? '<small>' . e($sub) . '</small>' : '') . '</span>' . rec_icon('down', 's') . '</summary><div class="sxl-sb">';
};
$stepEnd = function () { echo '</div></details>'; };
$back = $isEdit ? '/inquiry?id=' . (int)$inq['id'] : '/inquiries';
$right = '<div class="sxw-ctl">'
       //  A man-month price is costed first (owner, 20 Oct 2026); the costing is tagged
       //  with this enquiry and fills in its customer, branch and unit.
       . (($isEdit && function_exists('pc_can_edit') && pc_can_edit()) ? '<a class="sxw-bt" href="/project-costings?inquiry_id=' . (int)$inq['id'] . '">' . rec_icon('calc', 's') . ' ＋ Project costing</a>' : '')
       . '<a class="sxw-bt" href="' . e(isset($cur) ? nav_back($cur) : $back) . '">← ' . e(isset($cur) ? nav_back_label($cur) : 'Back') . '</a></div>';
?>
<div class="rec sxw has-bn sxl sxl-form sxq">
  <?php sxw_nav('/inquiries'); ?>
  <?php sxw_head($isEdit ? 'Edit inquiry — ' . $inq['inquiry_no'] : 'New ' . Tl('client') . ' ' . Tl('inquiry'),
                 'Capture what the ' . Tl('client') . ' asked for. You can raise a ' . Tl('quote') . ' against it afterwards. Status is not typed: it follows the ' . Tl('quote') . ' by itself.', 'mail', $right); ?>

<?php if (!empty($error)): ?><div class="msg msg-error" style="margin-bottom:12px"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($originRef)): ?>
  <div class="sxl-ban info"><span class="ic"><?= rec_icon('flow') ?></span>
    <div class="tx">Came from lead <?= !empty($inq['lead_id']) ? '<a href="/lead?id=' . (int)$inq['lead_id'] . '"><strong>' . e($originRef) . '</strong></a>' : '<strong>' . e($originRef) . '</strong>' ?> — the <?= e(Tl('client')) ?>, contact and requirement were carried over automatically.</div></div>
<?php endif; ?>

<form method="post" action="/<?= $isEdit ? 'inquiry-edit?id=' . (int)$inq['id'] : 'inquiry-new' ?>" class="sxl-main" id="inq-form">
  <?php if (!$isEdit): ?><input type="hidden" name="form_once" value="<?= e((string)($inq['form_once'] ?? bin2hex(random_bytes(12)))) ?>"><?php endif; ?>
  <?php if (!$isEdit && !empty($dupOf)): ?>
    <div class="sxl-ban warn" role="status"><span class="ic"><?= rec_icon('alert') ?></span>
      <div class="tx"><b class="t">The same <?= e(Tl('inquiry')) ?> was saved a few minutes ago</b>
        <?= e($dupOf['inquiry_no']) ?> — same <?= e(Tl('client')) ?> and subject. <a class="btn small" href="/inquiry?id=<?= (int)$dupOf['id'] ?>">Open <?= e($dupOf['inquiry_no']) ?></a>
        <label class="sxl-ok" style="margin-top:6px"><input type="checkbox" name="dup_ok" value="1"> Save it as a separate <?= e(Tl('inquiry')) ?></label></div>
    </div>
  <?php endif; ?>

  <?php $step(1, ucfirst(Tl('client')) . ' and contact', 'A ' . Tl('client') . ' on hold or blocked is refused, with the reason.'); ?>
  <div class="form-grid">
    <div class="ff"><label><?= e(ucfirst(Tl('client'))) ?> * <?php if (!function_exists('partner_can_add') || partner_can_add()): ?><a href="#" class="addlink" data-qa="client" data-target="select[name='client_id']">+ Add new</a><?php endif; ?></label>
      <select class="form-control searchable" name="client_id"><option value="">— pick or type below —</option>
        <?php foreach ($clients as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= (string)($inq['client_id'] ?? '') === (string)$cl['id'] ? 'selected' : '' ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>…or <?= e(Tl('client')) ?> name (if not yet registered)</label><input class="form-control" name="client_name" value="<?= e($inq['client_name'] ?? '') ?>">
      <div class="ff-help">A similar name gets a warning, never a block.</div></div>
    <?php //  Owner, 13 Nov 2026 (SAL test, step 4): a received date in the future "must show a warning sign
          //  and shall not be recorded". The browser stops at today; the warning says why; the server refuses. ?>
    <div class="ff"><label for="inq-recv">Received date</label><input class="form-control" type="date" id="inq-recv" name="received_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($inq['received_date'] ?? date('Y-m-d')) ?>" aria-describedby="inq-recv-warn">
      <div id="inq-recv-warn" class="msg msg-error" role="alert" style="display:none;margin-top:6px;padding:6px 10px;font-size:12.5px">⚠ This date is in the future. An <?= e(Tl('inquiry')) ?> can only be recorded as received today or earlier — it will not be saved.</div>
      <script>(function(){var d=document.getElementById('inq-recv'),w=document.getElementById('inq-recv-warn');if(!d||!w)return;
        var chk=function(){var bad=d.value!==''&&d.value>d.max;w.style.display=bad?'block':'none';d.setAttribute('aria-invalid',bad?'true':'false');};
        d.addEventListener('input',chk);d.addEventListener('change',chk);chk();})();</script></div>
    <div class="ff"><label>Contact person *</label><input class="form-control" name="contact_name" value="<?= e($inq['contact_name'] ?? '') ?>"></div>
    <?php // Owner test J3-01 (21 Nov 2026): both boxes said "— email or mobile". One label each. ?>
    <div class="ff"><label for="inq-cemail">Contact e-mail</label><input class="form-control" type="email" id="inq-cemail" name="contact_email" autocomplete="email" value="<?= e($inq['contact_email'] ?? '') ?>"></div>
    <div class="ff"><label for="inq-cmob">Contact mobile</label><input class="form-control" type="tel" id="inq-cmob" name="contact_mobile" inputmode="tel" autocomplete="tel" value="<?= e($inq['contact_mobile'] ?? '') ?>"></div>
  </div>
  <?php $stepEnd(); ?>

  <?php $step(2, 'The request', 'What they asked for, and who is working on it.'); ?>
  <div class="form-grid">
    <div class="ff ff-wide"><label>Subject *</label><input class="form-control" name="subject" value="<?= e($inq['subject'] ?? '') ?>" placeholder="e.g. Third-party inspection — Suryavan Mundra"></div>
    <div class="ff"><label><?= e(T('sbu')) ?> *</label>
      <select class="form-control searchable" name="sbu"><option value="">—</option>
        <?php foreach ($sbuOpts as $k => $v): ?><option value="<?= e($k) ?>" <?= ($inq['sbu'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Source</label>
      <select class="form-control" name="source"><?php foreach ($sourceOpts as $k => $v): ?><option value="<?= e($k) ?>" <?= (($inq['source'] ?? 'EMAIL') === $k) ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Assigned to</label>
      <select class="form-control searchable" name="assigned_to"><option value="">—</option>
        <?php foreach ($users as $u): $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']; ?><option value="<?= (int)$u['id'] ?>" <?= (string)($inq['assigned_to'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
      </select></div>
    <?php //  Owner, 13 Nov 2026: the status is not typed — Quoted / Won / Lost follow the quotation,
          //  and Dropped (with a reason) has its own button on the inquiry page. ?>
    <div class="ff"><label>Status</label>
      <div class="form-control" style="background:var(--soft);display:flex;align-items:center" aria-readonly="true"><?= e($statusOpts[$inq['status'] ?? 'OPEN'] ?? ($inq['status'] ?? 'Open')) ?></div>
      <div class="ff-help">Follows its <?= e(Tl('quote')) ?> — set by itself.</div></div>
    <div class="ff ff-wide"><label>Service requirement / details</label><textarea class="form-control" name="service_requirement" rows="3"><?= e($inq['service_requirement'] ?? '') ?></textarea></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= e($inq['notes'] ?? '') ?>"></div>
  </div>
  <?php $stepEnd(); ?>

  <div class="sxl-act">
    <?php if (!$inqWork): ?>
      <span class="sp">🔒 <?= e(crm_assignee_words((int)$inq['assigned_to'], Tl('inquiry'))) ?></span>
      <a class="btn secondary" href="<?= e($back) ?>">Cancel</a>
    <?php else: ?>
      <span class="sp">After saving you return to the <?= e(Tl('inquiry')) ?><?= $isEdit ? '' : ' you have just made' ?>.</span>
      <a class="btn secondary" href="<?= e($back) ?>">Cancel</a>
      <button class="btn" type="submit"><?= $isEdit ? 'Save inquiry' : 'Create inquiry' ?></button>
    <?php endif; ?>
  </div>
</form>
</div>
<?php sxw_bottom('/inquiries'); ?>
