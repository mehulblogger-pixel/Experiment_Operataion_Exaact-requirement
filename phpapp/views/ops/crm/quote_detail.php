<?php
  $stPill = ['DRAFT'=>'p-mut','PENDING_APPROVAL'=>'p-warn','APPROVED'=>'p-info','SENT'=>'p-info',
             'ACCEPTED'=>'p-ok','LOST'=>'p-bad','REJECTED'=>'p-bad','EXPIRED'=>'p-mut'];
  $st = $q['status'];
  $act = function($to, $label, $cls='btn small') use ($q) {
    return '<form method="post" action="/quote-status?id='.(int)$q['id'].'" style="display:inline"><input type="hidden" name="to" value="'.e($to).'"><button class="'.$cls.'" type="submit">'.e($label).'</button></form>';
  };
  $locT = lk_options_or('quote_location_type', QUOTE_LOCATION_TYPES);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/quotes"><?= e(TP('quote')) ?></a> › <?= e(quote_label($q)) ?></div>
<div class="master-head">
  <div><h1><?= e(quote_label($q)) ?> <span class="pill <?= $stPill[$st] ?? 'p-mut' ?>" style="font-size:13px;vertical-align:middle"><?= e(lk_options_or('quote_status', QUOTE_STATUS)[$st] ?? $st) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><?= e($q['subject'] ?: '—') ?></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn" href="/quote-pdf?id=<?= (int)$q['id'] ?>">⬇ PDF (for client)</a>
    <a class="btn secondary" href="/quote-doc?id=<?= (int)$q['id'] ?>">Word (editable)</a>
    <?php if ($canEdit && in_array($st, ['DRAFT','PENDING_APPROVAL','REJECTED'], true)): ?><a class="btn secondary" href="/quote-edit?id=<?= (int)$q['id'] ?>">Edit</a><?php endif; ?>
    <a class="btn secondary" href="/quotes">← Back</a>
  </div>
</div>

<?php if (in_array($st, ['APPROVED','SENT'], true) && !empty($q['contact_email'])): ?>
<div class="panel" style="background:var(--soft)">
  <b>Two ways to send this</b>
  <div class="muted" style="margin-top:4px">
    <b>Send to <?= e(Tl('client')) ?></b> sends it automatically from the company mailbox.<br>
    <b>Send from my mailbox</b> downloads the finished message with the PDF already attached; it opens in your
    Outlook as a draft from <?= e(compose_can_personalise() ? (current_user()['email'] ?? '') : 'your address') ?>,
    you press Send, and the <?= e(Tl('client')) ?>'s reply comes back to you — it is in your own Sent Items.
    <?php if (!compose_can_personalise()): ?>
      <br><span style="color:var(--warn)">Add your e-mail address to your profile first, or the draft will open from the company address.</span>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php // Won, but nobody has raised the contract number yet. The registration panel
      // is far down a long page, so the state is called out at the top — worded for
      // whoever is looking, because Accounts have a job to do and everyone else
      // needs to know the order can still be scheduled. ?>
<?php if ($st === 'ACCEPTED' && trim((string)($q['contract_number'] ?? '')) === ''):
        $waited = function_exists('contract_wait_days') ? contract_wait_days($q) : null; ?>
<div class="panel" style="border:1px solid var(--warn);background:color-mix(in srgb,var(--warn) 7%,transparent)">
  <b style="color:var(--warn)">⚠ Contract number is yet to be created</b>
  <?php if ($waited !== null): ?><span class="pill <?= $waited > 7 ? 'p-bad' : 'p-warn' ?>" style="margin-left:6px">waiting <?= (int)$waited ?> day<?= $waited == 1 ? '' : 's' ?></span><?php endif; ?>
  <div class="muted" style="margin-top:4px">
    <?php if ($canContract): ?>
      This is yours to do — register the <?= e(Tl('client')) ?> and enter the contract number below, and the order floats
      to operations automatically.
    <?php else: ?>
      Accounts have it. <?= e(THP('call')) ?> can be raised against this order in the meantime — they will simply keep
      showing the contract number as outstanding until it is registered.
    <?php endif; ?>
  </div>
  <div style="margin-top:8px"><a class="btn small<?= $canContract ? '' : ' secondary' ?>" href="#contract">
    <?= $canContract ? 'Register it now' : 'See the registration panel' ?></a></div>
</div>
<?php endif; ?>

<?php if ($st === 'REJECTED'): ?>
<div class="panel" style="border:1px solid var(--bad);background:color-mix(in srgb,var(--bad) 6%,transparent)">
  <b style="color:var(--bad)">Rejected by <?= e($q['rejected_by'] ?: 'the approver') ?></b>
  <span class="muted"><?= $q['rejected_at'] ? ' on ' . e(fdate(substr((string)$q['rejected_at'],0,10))) : '' ?></span>
  <?php if (!empty($q['reject_remarks'])): ?><div style="margin-top:4px"><b>Comment:</b> <?= e($q['reject_remarks']) ?></div><?php endif; ?>
  <div class="muted" style="margin-top:4px">Correct it, then use <b>Submit for approval again</b> below — the chain is rebuilt from scratch and this rejection is cleared. For a change the client asked for, raise a new revision instead so both versions stay on the record.</div>
</div>
<?php endif; ?>

<?php if (!empty($locked)): ?>
<div class="panel" style="border:1px solid var(--warn)">
  <b>🔒 Locked.</b> <span class="muted"><?= e($lockReason ?? '') ?></span>
  <?php if ($st === 'SENT' || trim((string)($q['sent_at'] ?? '')) !== ''): ?>
    <div style="margin-top:8px">
      <button class="btn small" type="button" onclick="document.getElementById('revbox').style.display='block';document.getElementById('revbox').scrollIntoView({behavior:'smooth'})">Raise a revision</button>
      <span class="muted" style="margin-left:6px">A revision keeps the same <?= e(Tl('quote')) ?> number, takes the next revision number,
        carries every line and site across, and goes through approval again before it can be sent.</span>
    </div>
  <?php elseif ($editReq): ?>
    <div style="margin-top:8px"><span class="pill p-warn">Request pending</span>
      <span class="muted">raised by <?= e($editReq['requested_by']) ?> — “<?= e($editReq['reason']) ?>”</span></div>
  <?php else: ?>
    <form method="post" action="/quote-unlock?id=<?= (int)$q['id'] ?>" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="do" value="request">
      <div class="ff" style="margin:0;flex:1;min-width:240px"><label>Why does it need re-opening?</label>
        <input class="form-control" name="reason" required placeholder="e.g. client asked to correct the rate on line 2"></div>
      <button class="btn small secondary" type="submit">Request re-edit</button>
    </form>
  <?php endif; ?>
</div>
<?php elseif ($editReq && is_master()): ?>
<div class="panel" style="border:1px solid var(--warn)">
  <b>Re-edit requested</b> <span class="muted">by <?= e($editReq['requested_by']) ?> — “<?= e($editReq['reason']) ?>”</span>
  <form method="post" action="/quote-unlock?id=<?= (int)$q['id'] ?>" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="req" value="<?= (int)$editReq['id'] ?>">
    <div class="ff" style="margin:0"><label>Unlock for (hours)</label>
      <input class="form-control" type="number" name="hours" value="24" min="1" max="168" style="width:110px"></div>
    <div class="ff" style="margin:0;flex:1;min-width:200px"><label>Note</label><input class="form-control" name="note"></div>
    <button class="btn small" name="do" value="grant" type="submit">Grant</button>
    <button class="btn small danger" name="do" value="refuse" type="submit">Refuse</button>
  </form>
</div>
<?php elseif (!empty($q['unlocked_until']) && strtotime($q['unlocked_until']) > time()): ?>
<div class="panel" style="border:1px solid var(--ok)">
  <b style="color:var(--ok)">Unlocked</b> <span class="muted">until <?= e(date('d M Y H:i', strtotime($q['unlocked_until']))) ?> — it re-locks itself afterwards.</span>
</div>
<?php endif; ?>

<!-- Status action bar -->
<div class="panel" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <span class="muted">Move this quote:</span>
  <?php // A rejected quote is not an ending — it goes back round. It must offer
        // the same submit button a draft does, or the only way forward is to
        // raise a whole new revision for what may be a one-word correction. ?>
  <?php if ($st==='DRAFT' || $st==='REJECTED'): ?>
    <?= $act('PENDING_APPROVAL', $st==='REJECTED' ? '↻ Submit for approval again' : 'Submit for approval') ?>
    <?php if ($st==='REJECTED'): ?>
      <a class="btn small secondary" href="/quote-edit?id=<?= (int)$q['id'] ?>">Edit first</a>
    <?php endif; ?>
  <?php elseif ($st==='APPROVED'): ?>
    <?php if ($canSend): ?><?= $act('SENT','✉ Send to ' . Tl('client')) ?><?php endif; ?>
  <?php elseif ($st==='SENT'): ?>
    <?= $act('ACCEPTED','Mark accepted (won)') ?>
    <a class="btn small secondary" href="/quote-doc?id=<?= (int)$q['id'] ?>">Re-download Word</a>
  <?php endif; ?>
  <?php if (in_array($st, ['APPROVED','SENT'], true) && !empty($q['contact_email'])): ?>
    <span style="width:1px;height:22px;background:var(--line)"></span>
    <a class="btn small secondary" href="/quote-compose?id=<?= (int)$q['id'] ?>"
       title="Downloads a draft that opens in your Outlook with the PDF attached — you press Send">✉️ Send from my mailbox</a>
    <a class="btn small secondary" href="/quote-compose?id=<?= (int)$q['id'] ?>&amp;mode=mailto"
       title="Opens your default mail app — no attachment (browsers cannot attach files to a mailto link)">Compose without attachment</a>
  <?php endif; ?>
  <?php if (in_array($st, ['DRAFT','REJECTED','PENDING_APPROVAL','APPROVED','SENT'], true)): ?>
    <button class="btn small danger" type="button" onclick="document.getElementById('lostbox').style.display='block'">Mark lost</button>
  <?php endif; ?>
  <?php // Revising is how a sent quotation is changed, so this is deliberately NOT
        // gated on the edit lock — otherwise the only correct action would be the
        // one greyed out. ?>
  <?php if (!empty($canRevise) && in_array($st, ['SENT','APPROVED','ACCEPTED','LOST','REJECTED'], true)): ?>
    <button class="btn small secondary" type="button" onclick="document.getElementById('revbox').style.display='block'" style="margin-left:auto">Revise (new rev)</button>
  <?php endif; ?>
</div>

<?php if ($approvals): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Approval chain</h3>
  <?php if ($st==='PENDING_APPROVAL' && $pendingWith): ?>
    <div class="panel" style="margin:0 0 10px;background:var(--soft)">
      <b>⏳ Waiting with <?= e($pendingWith) ?></b>
      <?php if ($pendingStep): ?><span class="muted"> — level <?= (int)$pendingStep['level'] ?> of <?= count($approvals) ?></span><?php endif; ?>
    </div>
  <?php endif; ?>
  <table class="dt">
    <thead><tr><th class="num">Level</th><th>With whom</th><th>Status</th><th>Acted</th><th>Comment</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($approvals as $ai => $a):
      $people = $stepPeople[$ai] ?? [];
      if ($people) { $names = array_map('crm_person_label', array_slice($people, 0, 4)); $more = count($people) - count($names); $who = implode(', ', $names) . ($more > 0 ? " and $more other(s)" : ''); }
      elseif (!empty($a['approver_role'])) $who = 'Anyone with the role ' . (ORG_ROLES[$a['approver_role']] ?? $a['approver_role']) . ' (nobody holds it yet)';
      else $who = 'Any approver';
    ?>
    <tr>
      <td class="num"><b><?= (int)$a['level'] ?></b></td>
      <td><?= e($who) ?></td>
      <td><span class="pill <?= $a['status']==='APPROVED'?'p-ok':($a['status']==='REJECTED'?'p-bad':'p-warn') ?>">
          <?= e($a['status']==='PENDING' ? 'Pending' : ucfirst(strtolower($a['status']))) ?></span></td>
      <td class="muted"><?= $a['acted_by'] ? e($a['acted_by']).'<br><span style="font-size:11px">'.e(fdate(substr((string)$a['acted_at'],0,10))).'</span>' : '—' ?></td>
      <td class="muted"><?= $a['remarks'] ? e($a['remarks']) : '—' ?></td>
      <td class="num" style="white-space:nowrap">
        <?php if (crm_can_act_approval($a)): ?>
        <form method="post" action="/quote-approve?id=<?= (int)$q['id'] ?>" style="display:flex;gap:4px;align-items:center;justify-content:flex-end">
          <input type="hidden" name="step" value="<?= (int)$a['id'] ?>">
          <input class="form-control" name="remarks" placeholder="comment (required to reject)" style="width:190px">
          <button class="btn small" name="decision" value="approve" type="submit">Approve</button>
          <button class="btn small danger" name="decision" value="reject" type="submit">Reject</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php // Everybody approves the wrong thing once. While it is still inside the
        // building that is a mistake, not an event. ?>
  <?php if (!empty($canRetract)): ?>
  <form method="post" action="/quote-unapprove?id=<?= (int)$q['id'] ?>"
        style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end;border-top:1px solid var(--line);padding-top:10px">
    <div class="ff" style="margin:0;flex:1;min-width:260px"><label>Approved it by mistake? Take it back</label>
      <input class="form-control" name="reason" placeholder="why you are taking it back (optional)"></div>
    <button class="btn small secondary" type="submit"
      onclick="return confirm('Take your approval back? The quotation goes to waiting-for-approval again, and any level approved after yours is reset with it.')">Take my approval back</button>
  </form>
  <p class="muted" style="margin-top:6px">Possible only while the <?= e(Tl('quote')) ?> is still inside the company.
    Once it has gone to the <?= e(Tl('client')) ?> it cannot be taken back — raise a revision instead.</p>
  <?php endif; ?>
  <p class="muted" style="margin-top:6px">It becomes <strong>Approved</strong> once every level has approved. A rejection needs a comment and marks the whole <?= e(Tl('quote')) ?> as rejected. Configure the chain under <a href="/approval-rules?module=quote">Approval rules</a>.</p>
</div>
<?php endif; ?>

<div id="lostbox" class="panel" style="display:none;border:1px solid var(--bad)">
  <form method="post" action="/quote-status?id=<?= (int)$q['id'] ?>">
    <input type="hidden" name="to" value="LOST">
    <h3 class="tab-sub" style="margin-top:0">Mark as lost / regretted</h3>
    <div class="form-grid">
      <div class="ff"><label>Reason *</label>
        <select class="form-control" name="lost_reason" id="lost_reason" required onchange="document.getElementById('lo_other').style.display=this.value==='OTHER'?'block':'none'">
          <option value="">— pick a reason —</option>
          <?php foreach ($lostReasons as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff" id="lo_other" style="display:none"><label>Other — please specify</label><input class="form-control" name="lost_reason_other"></div>
    </div>
    <div style="margin-top:10px"><button class="btn danger" type="submit">Confirm lost</button>
      <button class="btn secondary" type="button" onclick="document.getElementById('lostbox').style.display='none'">Cancel</button></div>
  </form>
</div>

<div id="revbox" class="panel" style="display:none;border:1px solid var(--line)">
  <form method="post" action="/quote-revise?id=<?= (int)$q['id'] ?>">
    <h3 class="tab-sub" style="margin-top:0">Create a new revision</h3>
    <p class="sub">This bumps the revision number (Rev <?= str_pad((string)((int)$q['rev']+1),2,'0',STR_PAD_LEFT) ?>) and copies everything into a fresh draft you can edit. The current version is kept in history.</p>
    <div class="ff ff-wide"><label>What changed? (logged in history)</label><input class="form-control" name="summary" placeholder="e.g. Revised rates after negotiation"></div>
    <div style="margin-top:10px"><button class="btn" type="submit">Create revision</button>
      <button class="btn secondary" type="button" onclick="document.getElementById('revbox').style.display='none'">Cancel</button></div>
  </form>
</div>

<div class="panel-split">
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0"><?= e(T('client')) ?></h3>
    <table class="kv">
      <tr><td class="muted">Client</td><td><?= e($q['client_name'] ?: '—') ?></td></tr>
      <tr><td class="muted">Contact</td><td><?= e($q['contact_name'] ?: '—') ?><?= $q['contact_email']?' · '.e($q['contact_email']):'' ?><?= $q['contact_mobile']?' · '.e($q['contact_mobile']):'' ?></td></tr>
      <tr><td class="muted"><?= e(T("sbu")) ?></td><td><?= e(lk_options_or('sbu', OPS_SBUS)[$q['sbu']] ?? $q['sbu'] ?: '—') ?></td></tr>
      <tr><td class="muted">Location</td><td><?= e($q['site_location'] ?: '—') ?> <span class="pill p-mut"><?= e($locT[$q['location_type']] ?? $q['location_type']) ?></span></td></tr>
    </table>
  </div>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Commercials</h3>
    <table class="kv">
      <tr><td class="muted">Validity</td><td><?= (int)$q['validity_days'] ?> days</td></tr>
      <tr><td class="muted">Payment terms</td><td><?= e($q['payment_terms'] ?: '—') ?></td></tr>
      <tr><td class="muted">Advance</td><td><?= (float)$q['advance_pct'] ?>% <?= $q['advance_required']?'<span class="pill p-warn">required before scheduling</span>':'' ?></td></tr>
      <tr><td class="muted">Deliverable</td><td><?= $q['report_vs_payment']?'<span class="pill p-warn">Report only against payment</span>':'Standard' ?></td></tr>
    </table>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>#</th><th><?= e(T('sbu')) ?></th><th>Service</th><th>Description</th><th>Location</th><th>Order</th><th class="num">Qty</th><th>Unit</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($lines as $i=>$l): ?>
      <tr>
        <td class="muted"><?= $i+1 ?></td>
        <td><?= e(lk_options_or('sbu', OPS_SBUS)[$l['sbu']] ?? $l['sbu'] ?: '—') ?></td>
        <td><?= e(lk_options_or('inspection_type', INSPECTION_TYPES)[$l['service_type']] ?? $l['service_type'] ?: '—') ?><?= $l['subtypes']?'<div class="muted" style="font-size:11px">'.e($l['subtypes']).'</div>':'' ?></td>
        <td><?= e($l['description'] ?: '—') ?></td>
        <td><?= e($l['location'] ?: '—') ?></td>
        <td><span class="pill <?= $l['order_type']==='OPEN'?'p-info':'p-mut' ?>"><?= e(lk_options_or('order_type', ORDER_TYPES)[$l['order_type']] ?? $l['order_type']) ?></span></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$l['qty'],2),'0'),'.') ?></td>
        <td><?= e(lk_options_or('charge_unit', CHARGE_UNITS)[$l['unit']] ?? $l['unit']) ?></td>
        <td class="num"><?= e(cur_sym()) ?><?= number_format((float)$l['rate'],0) ?></td>
        <td class="num"><?= e(cur_sym()) ?><?= number_format((float)$l['amount'],0) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$lines): ?><tr><td colspan="10" class="muted" style="text-align:center;padding:16px">No line items.</td></tr><?php endif; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="9" class="num muted">Subtotal</td><td class="num"><b><?= e(cur_sym()) ?><?= number_format((float)$q['subtotal'],0) ?></b></td></tr>
      <tr><td colspan="9" class="num muted">GST (<?= (float)$q['gst_pct'] ?>%)</td><td class="num"><?= e(cur_sym()) ?><?= number_format((float)$q['gst_amount'],0) ?></td></tr>
      <tr><td colspan="9" class="num"><b>Total</b></td><td class="num"><b><?= e(cur_sym()) ?><?= number_format((float)$q['total_amount'],0) ?></b></td></tr>
    </tfoot>
  </table>
  </div>
</div>

<?php if (!empty($orderJobs)):
  $oInv=0;$oPaid=0; foreach($orderJobs as $oj){ $oInv+=(float)$oj['invoice_amount']; $oPaid+= !empty($oj['payment_received'])?(float)$oj['payment_amount']:0; } ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Jobs &amp; revenue against this order</h3>
  <div class="chip-row" style="margin-bottom:8px">
    <span class="ct">Ordered <b><?= e(cur_sym()) ?><?= number_format((float)$q['total_amount'],0) ?></b></span>
    <span class="ct">Invoiced <b><?= e(cur_sym()) ?><?= number_format($oInv,0) ?></b></span>
    <span class="ct">Received <b><?= e(cur_sym()) ?><?= number_format($oPaid,0) ?></b></span>
    <span class="ct"><?= count($orderJobs) ?> job<?= count($orderJobs)==1?'':'s' ?></span>
  </div>
  <table class="grid"><tr><th>Job</th><th>Inspector</th><th>Stage</th><th class="num">Invoiced</th><th class="num">Received</th></tr>
    <?php foreach ($orderJobs as $oj): ?>
    <tr><td><a href="/job?id=<?= (int)$oj['id'] ?>"><?= e($oj['job_code']) ?></a></td>
      <td><?= e($oj['inspector_name'] ?: '—') ?></td>
      <td><?= $oj['closed_flag']?'Closed':'Open' ?></td>
      <td class="num"><?= (float)$oj['invoice_amount']>0?cur_sym().number_format((float)$oj['invoice_amount'],0):'—' ?></td>
      <td class="num"><?= !empty($oj['payment_received'])?cur_sym().number_format((float)$oj['payment_amount'],0):'—' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="margin-top:6px">Revenue booked against quote <?= e($q['quote_no']) ?><?= $q['contract_number']?' / contract '.e($q['contract_number']):'' ?>. Link more jobs from the job form ("Against <?= e(Tl('quote')) ?> / contract").</p>
</div>
<?php endif; ?>

<?php if ($st==='ACCEPTED'): ?>
<div class="panel" id="contract" style="border:1px solid var(--ok)">
  <h3 class="tab-sub" style="margin-top:0">Won — client &amp; contract registration (Accounts)</h3>
  <?php if ($q['contract_number']): ?>
    <table class="kv">
      <tr><td class="muted">Registered client</td><td><?= $clientReg ? e($clientReg['legal_name']).' <span class="muted">('.e($clientReg['code']).')</span>' : e($q['client_name']) ?></td></tr>
      <tr><td class="muted">Contract number</td><td><b><?= e($q['contract_number']) ?></b></td></tr>
    </table>
    <p class="sub" style="margin:8px 0 0">The <strong>handover to operations</strong> — <?= e(Tl('client')) ?>, <?= e(Tl('quote')) ?> and contract number, contacts, what was sold, the order lines and the commercial conditions — has gone to the delivery team, so they can raise <?= e(Tlp('call')) ?> against it without asking Sales for anything. It is saved in the system either way; it is also e-mailed once SMTP is set up in Settings.</p>
    <form method="post" action="/quote-float?id=<?= (int)$q['id'] ?>" style="margin-top:8px"><button class="btn small secondary" type="submit">Re-send to operations</button></form>
  <?php elseif ($canContract): ?>
    <p class="sub">Enter the contract number to register the client (if new) and float the order to operations.</p>
    <form method="post" action="/quote-contract?id=<?= (int)$q['id'] ?>">
      <div class="form-grid">
        <div class="ff"><label>Contract number *</label><input class="form-control" name="contract_number" required placeholder="e.g. CON/2026/0142"><small class="muted">Entered by Accounts or the administrator once the order is confirmed.</small></div>
        <div class="ff"><label>Contract start</label><input class="form-control" type="date" name="start_date"></div>
        <div class="ff"><label>Contract end</label><input class="form-control" type="date" name="end_date"></div>
      </div>
      <?php if (empty($q['client_id']) && $q['client_name']): ?><p class="muted" style="margin:6px 2px">"<?= e($q['client_name']) ?>" will be registered as a client automatically.</p><?php endif; ?>
      <div style="margin-top:10px"><button class="btn" type="submit">Register &amp; float to operations</button></div>
    </form>
  <?php else: ?>
    <p class="sub">Awaiting Accounts to register the client &amp; contract number.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($st==='LOST' && $q['lost_reason']): ?>
<div class="panel" style="border:1px solid var(--bad)"><b>Lost reason:</b> <?= e(lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS)[$q['lost_reason']] ?? $q['lost_reason']) ?><?= $q['lost_reason_other']?' — '.e($q['lost_reason_other']):'' ?></div>
<?php endif; ?>

<div class="panel-split">
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Revision history</h3>
    <table class="grid"><tr><th>Rev</th><th>Status</th><th class="num">Total</th><th>When</th><th></th></tr>
      <?php foreach ($revs as $rv): ?>
      <tr>
        <td><b><?= $rv['rev']>0?'Rev '.str_pad((string)$rv['rev'],2,'0',STR_PAD_LEFT):'Original' ?></b> <?= $rv['is_current']?'<span class="pill p-ok">current</span>':'' ?></td>
        <td><?= e(lk_options_or('quote_status', QUOTE_STATUS)[$rv['status']] ?? $rv['status']) ?></td>
        <td class="num"><?= e(cur_sym()) ?><?= number_format((float)$rv['total_amount'],0) ?></td>
        <td class="muted"><?= e(substr((string)$rv['created_at'],0,10)) ?></td>
        <td><?= $rv['is_current']?'':'<a class="btn small secondary" href="/quote?id='.(int)$rv['id'].'">view</a>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($st === 'ACCEPTED'): ?>
      <div class="panel" style="margin:10px 0 0;background:var(--soft)">
        <b>Final copy</b> <span class="muted">— the accepted version, as sent.</span>
        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
          <a class="btn small" href="/quote-pdf?id=<?= (int)$q['id'] ?>">⬇ PDF</a>
          <a class="btn small secondary" href="/quote-doc?id=<?= (int)$q['id'] ?>">⬇ Word</a>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($hist): ?>
      <h4 class="tab-sub" style="font-size:13px;margin:14px 0 6px">What changed</h4>
      <table class="dt">
        <thead><tr><th>Rev</th><th>Change</th><th>By</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($hist as $hi => $h):
          // Compare each entry with the one before it, so the list reads as a
          // running record of what actually changed rather than just "edited".
          $prev = $hist[$hi + 1] ?? null;
          $diff = $prev ? crm_diff_snapshots(json_decode((string)$prev['snapshot'], true), json_decode((string)$h['snapshot'], true)) : [];
        ?>
          <tr>
            <td><?= str_pad((string)$h['rev'],2,'0',STR_PAD_LEFT) ?></td>
            <td><?= e($h['summary']) ?>
              <?php if ($diff): ?>
                <details style="margin-top:4px"><summary class="muted" style="cursor:pointer;font-size:12px"><?= count($diff) ?> field(s)</summary>
                  <div style="font-size:12px;margin-top:4px">
                  <?php foreach (array_slice($diff, 0, 12) as $d): ?>
                    <div><b><?= e(str_replace('_', ' ', $d['field'])) ?>:</b>
                      <span class="muted"><?= e($d['from'] === '' ? '(blank)' : mb_substr($d['from'], 0, 60)) ?></span>
                      → <?= e($d['to'] === '' ? '(blank)' : mb_substr($d['to'], 0, 60)) ?></div>
                  <?php endforeach; ?>
                  </div>
                </details>
              <?php endif; ?>
            </td>
            <td class="muted"><?= e($h['changed_by']) ?></td>
            <td class="muted"><?= e(fdate(substr((string)$h['changed_at'],0,10))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Follow-ups</h3>
    <?php if ($followups): ?>
    <?php // Row cards, not a table. This panel is half the page wide and six
          // editable columns could not fit in it — the table ran outside the
          // panel and had to be zoomed out to read. These wrap instead. ?>
    <form method="post" action="/quote-followup?id=<?= (int)$q['id'] ?>">
      <input type="hidden" name="do" value="save">
      <div class="fu-list">
        <?php foreach ($followups as $f):
              $tone = ['DONE'=>'p-ok','SENT'=>'p-info','SKIPPED'=>'p-mut','PENDING'=>'p-warn'][$f['status']] ?? 'p-mut';
              $overdue = ($f['status'] === 'PENDING' && ($f['due_date'] ?? '') !== '' && $f['due_date'] < date('Y-m-d')); ?>
        <div class="fu<?= $overdue ? ' fu-late' : '' ?>">
          <input type="hidden" name="f_id[]" value="<?= (int)$f['id'] ?>">
          <div class="fu-head">
            <b><?= e(lk_options_or('followup_kind', FOLLOWUP_KINDS)[$f['kind']] ?? $f['kind']) ?></b>
            <span class="pill <?= e($tone) ?>"><?= e(['PENDING'=>'Pending','DONE'=>'Done','SENT'=>'Sent','SKIPPED'=>'Skipped'][$f['status']] ?? $f['status']) ?></span>
            <?php if ($overdue): ?><span class="pill p-bad">overdue</span><?php endif; ?>
            <?php if (!empty($q['contact_email'])): ?>
              <a class="btn small secondary fu-mail" href="/followup-compose?id=<?= (int)$f['id'] ?>"
                 title="Opens the chase in your own mailbox">✉️ Chase</a><?php endif; ?>
          </div>
          <div class="fu-grid">
            <label>Due<input class="form-control" type="date" name="f_due[]" value="<?= e($f['due_date']) ?>"></label>
            <label>Status<select class="form-control" name="f_status[]">
                <?php foreach (['PENDING'=>'Pending','DONE'=>'Done','SENT'=>'Sent','SKIPPED'=>'Skipped'] as $sk=>$sv): ?>
                  <option value="<?= e($sk) ?>" <?= ($f['status']===$sk)?'selected':'' ?>><?= e($sv) ?></option><?php endforeach; ?>
              </select></label>
            <label>Done on<input class="form-control" type="date" name="f_done[]" value="<?= e($f['done_date'] ?? '') ?>"></label>
          </div>
          <label class="fu-note">What was said
            <input class="form-control" name="f_note[]" value="<?= e($f['note'] ?? '') ?>" placeholder="e.g. purchase head reviewing, call back Tuesday"></label>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:10px"><button class="btn small" type="submit">Save follow-ups</button></div>
    </form>
    <style>
      .fu-list{display:flex;flex-direction:column;gap:10px}
      .fu{border:1px solid var(--line);border-radius:var(--radius-sm,10px);padding:10px 12px;background:var(--soft)}
      .fu-late{border-color:var(--bad)}
      .fu-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px}
      .fu-head b{font-size:13.5px}
      .fu-mail{margin-left:auto}
      /* auto-fit means one column in a narrow panel and three in a wide one,
         with no fixed min-width to push the card past its container */
      .fu-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:8px}
      .fu label,.fu-note{display:flex;flex-direction:column;gap:3px;font-size:11.5px;font-weight:700;
        color:var(--muted);text-transform:uppercase;letter-spacing:.3px}
      .fu-note{margin-top:8px}
      .fu .form-control{width:100%;min-width:0;font-size:13.5px;text-transform:none;letter-spacing:0}
    </style>
    <p class="muted" style="margin-top:6px">Scheduled automatically when the <?= e(Tl('quote')) ?> is marked sent — day 3, 6, 9, fortnight and month. Edit any date, mark one done with a note, or add your own below.</p>
    <?php else: ?>
      <p class="muted">Follow-ups are scheduled automatically once the <?= e(Tl('quote')) ?> is marked <b>sent</b> — day 3, 6, 9, fortnight and month. You can also add one by hand.</p>
    <?php endif; ?>
    <form method="post" action="/quote-followup?id=<?= (int)$q['id'] ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:end;margin-top:8px;border-top:1px solid var(--line);padding-top:10px">
      <input type="hidden" name="do" value="add">
      <div class="ff" style="margin:0"><label>Add a follow-up on</label><input class="form-control" type="date" name="due_date" value="<?= e(date('Y-m-d')) ?>"></div>
      <div class="ff" style="margin:0;flex:1;min-width:160px"><label>Note</label><input class="form-control" name="note" placeholder="e.g. call the purchase head"></div>
      <button class="btn small secondary" type="submit">+ Add</button>
    </form>
  </div>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Sites <span class="muted">— every location this <?= e(Tl('quote')) ?> covers</span></h3>
  <?php if ($locs): ?>
  <table class="dt">
    <thead><tr><th>Site</th><th>Type</th><th>Address</th><th>Contact</th><th>Nearest <?= e(T('office')) ?></th></tr></thead>
    <tbody>
    <?php $offById = []; foreach ($offAll as $o) $offById[(int)$o['id']] = $o['name'];
          foreach ($locs as $L): ?>
      <tr>
        <td><b><?= e($L['label'] ?: '—') ?></b></td>
        <td><?= e(lk_options_or('site_location_type', SITE_LOCATION_TYPES)[$L['location_type']] ?? $L['location_type']) ?></td>
        <td><?= e(quote_location_line($L)) ?: '<span class="muted">—</span>' ?></td>
        <td><?= e(trim(($L['contact_name'] ?? '') . ' ' . ($L['contact_mobile'] ?? ''))) ?: '<span class="muted">—</span>' ?></td>
        <td><?= e($offById[(int)($L['office_id'] ?? 0)] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="muted">No sites recorded. Add them on the edit screen.</p><?php endif; ?>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Documents <span class="muted">— our format, attachments, the <?= e(Tl('client')) ?>'s PO and anything the <?= e(Tl('engineer')) ?> will need</span></h3>
  <?php if ($files): ?>
  <table class="dt">
    <thead><tr><th>File</th><th>Kind</th><th>Note</th><th>Shared with <?= e(Tl('engineer')) ?></th><th>Added</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr>
        <td><a href="/quote-file?id=<?= (int)$f['id'] ?>">⬇ <?= e($f['file_name']) ?></a>
            <span class="muted" style="font-size:11px">(<?= e(number_format(((int)$f['b64len']) * 3 / 4 / 1024, 0)) ?> KB)</span></td>
        <td><?= e($fileKinds[$f['kind']] ?? $f['kind']) ?></td>
        <td class="muted"><?= e($f['note'] ?: '—') ?></td>
        <td><?= $f['share_with_inspector'] ? '<span class="pill p-ok">yes</span>' : '<span class="pill p-mut">no</span>' ?></td>
        <td class="muted"><?= e($f['uploaded_by']) ?><br><span style="font-size:11px"><?= e(fdate(substr((string)$f['uploaded_at'],0,10))) ?></span></td>
        <td class="num">
          <?php if ($canEdit || is_master()): ?>
          <form method="post" action="/quote-file-delete?id=<?= (int)$f['id'] ?>" onsubmit="return confirm('Remove this file?')" style="display:inline">
            <button class="btn small danger" type="submit">✕</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="muted">Nothing attached yet.</p><?php endif; ?>

  <?php if ($canEdit || is_master()): ?>
  <form method="post" action="/quote-files?id=<?= (int)$q['id'] ?>" enctype="multipart/form-data" style="margin-top:10px;border-top:1px solid var(--line);padding-top:10px">
    <div class="form-grid">
      <div class="ff"><label>Kind</label>
        <select class="form-control" name="kind" id="fkind">
          <?php foreach ($fileKinds as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Files <span class="muted">— several at once, 8 MB each</span></label>
        <input class="form-control" type="file" name="files[]" multiple></div>
      <div class="ff"><label>Note</label><input class="form-control" name="note" placeholder="e.g. approved QAP rev 2"></div>
      <div class="ff po-only" style="display:none"><label>PO number</label><input class="form-control" name="po_number" value="<?= e($q['po_number'] ?? '') ?>"></div>
      <div class="ff po-only" style="display:none"><label>PO date</label><input class="form-control" type="date" name="po_date" value="<?= e($q['po_date'] ?? '') ?>"></div>
      <div class="ff" style="align-self:end">
        <label class="chk"><input type="checkbox" name="share_with_inspector" value="1" checked> Share with the <?= e(Tl('engineer')) ?> on every <?= e(Tl('call')) ?> from this <?= e(Tl('quote')) ?></label></div>
    </div>
    <div style="margin-top:10px"><button class="btn small" type="submit">Attach</button></div>
  </form>
  <script>
  (function(){
    var k = document.getElementById('fkind');
    function sync(){ document.querySelectorAll('.po-only').forEach(function(el){ el.style.display = k.value === 'PO' ? '' : 'none'; }); }
    k.addEventListener('change', sync); sync();
  })();
  </script>
  <?php endif; ?>
</div>
