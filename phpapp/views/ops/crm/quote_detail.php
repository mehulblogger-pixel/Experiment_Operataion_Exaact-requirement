<?php
  $stPill = ['DRAFT'=>'p-mut','PENDING_APPROVAL'=>'p-warn','APPROVED'=>'p-info','SENT'=>'p-info','ACCEPTED'=>'p-ok','LOST'=>'p-bad','EXPIRED'=>'p-mut'];
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
    <?php if ($canEdit && in_array($st, ['DRAFT','PENDING_APPROVAL'], true)): ?><a class="btn secondary" href="/quote-edit?id=<?= (int)$q['id'] ?>">Edit</a><?php endif; ?>
    <a class="btn secondary" href="/quotes">← Back</a>
  </div>
</div>

<!-- Status action bar -->
<div class="panel" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <span class="muted">Move this quote:</span>
  <?php if ($st==='DRAFT'): ?>
    <?= $act('PENDING_APPROVAL','Submit for approval') ?>
  <?php elseif ($st==='APPROVED'): ?>
    <?php if ($canSend): ?><?= $act('SENT','✉ Send to ' . Tl('client')) ?><?php endif; ?>
  <?php elseif ($st==='SENT'): ?>
    <?= $act('ACCEPTED','Mark accepted (won)') ?>
    <a class="btn small secondary" href="/quote-doc?id=<?= (int)$q['id'] ?>">Re-download Word</a>
  <?php endif; ?>
  <?php if (in_array($st, ['DRAFT','PENDING_APPROVAL','APPROVED','SENT'], true)): ?>
    <button class="btn small danger" type="button" onclick="document.getElementById('lostbox').style.display='block'">Mark lost</button>
  <?php endif; ?>
  <?php if ($canEdit && in_array($st, ['SENT','APPROVED','ACCEPTED','LOST'], true)): ?>
    <button class="btn small secondary" type="button" onclick="document.getElementById('revbox').style.display='block'" style="margin-left:auto">Revise (new rev)</button>
  <?php endif; ?>
</div>

<?php if ($st==='PENDING_APPROVAL' && $approvals): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Approval chain</h3>
  <table class="grid"><tr><th>Level</th><th>Approver</th><th>Status</th><th>Acted</th><th></th></tr>
    <?php foreach ($approvals as $a): $who = $a['approver_user_id'] ? ('User #'.$a['approver_user_id']) : ($a['approver_role'] ? (ORG_ROLES[$a['approver_role']] ?? $a['approver_role']) : 'Any approver'); ?>
    <tr>
      <td><b><?= (int)$a['level'] ?></b></td>
      <td><?= e($who) ?></td>
      <td><span class="pill <?= $a['status']==='APPROVED'?'p-ok':($a['status']==='REJECTED'?'p-bad':'p-warn') ?>"><?= e($a['status']) ?></span></td>
      <td class="muted"><?= $a['acted_by'] ? e($a['acted_by']).' · '.e(substr((string)$a['acted_at'],0,10)) : '—' ?><?= $a['remarks']?'<div style="font-size:11px">'.e($a['remarks']).'</div>':'' ?></td>
      <td class="num" style="white-space:nowrap">
        <?php if (crm_can_act_approval($a)): ?>
        <form method="post" action="/quote-approve?id=<?= (int)$q['id'] ?>" style="display:inline-flex;gap:4px;align-items:center">
          <input type="hidden" name="step" value="<?= (int)$a['id'] ?>">
          <input class="form-control" name="remarks" placeholder="remarks" style="width:130px">
          <button class="btn small" name="decision" value="approve" type="submit">Approve</button>
          <button class="btn small danger" name="decision" value="reject" type="submit">Reject</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="muted" style="margin-top:6px">The quote becomes <strong>Approved</strong> automatically once every step is approved. Configure the chain under <a href="/quote-approval-rules">Approval rules</a>.</p>
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
      <tr><td class="muted">SBU</td><td><?= e(lk_options_or('sbu', OPS_SBUS)[$q['sbu']] ?? $q['sbu'] ?: '—') ?></td></tr>
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
    <thead><tr><th>#</th><th>SBU</th><th>Service</th><th>Description</th><th>Location</th><th>Order</th><th class="num">Qty</th><th>Unit</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($lines as $i=>$l): ?>
      <tr>
        <td class="muted"><?= $i+1 ?></td>
        <td><?= e(lk_options_or('sbu', OPS_SBUS)[$l['sbu']] ?? $l['sbu'] ?: '—') ?></td>
        <td><?= e(lk_options_or('crm_service_type', CRM_SERVICE_TYPES)[$l['service_type']] ?? $l['service_type'] ?: '—') ?><?= $l['subtypes']?'<div class="muted" style="font-size:11px">'.e($l['subtypes']).'</div>':'' ?></td>
        <td><?= e($l['description'] ?: '—') ?></td>
        <td><?= e($l['location'] ?: '—') ?></td>
        <td><span class="pill <?= $l['order_type']==='OPEN'?'p-info':'p-mut' ?>"><?= e(lk_options_or('order_type', ORDER_TYPES)[$l['order_type']] ?? $l['order_type']) ?></span></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$l['qty'],2),'0'),'.') ?></td>
        <td><?= e(lk_options_or('quote_unit', QUOTE_UNITS)[$l['unit']] ?? $l['unit']) ?></td>
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
<div class="panel" style="border:1px solid var(--ok)">
  <h3 class="tab-sub" style="margin-top:0">Won — client &amp; contract registration (Accounts)</h3>
  <?php if ($q['contract_number']): ?>
    <table class="kv">
      <tr><td class="muted">Registered client</td><td><?= $clientReg ? e($clientReg['legal_name']).' <span class="muted">('.e($clientReg['code']).')</span>' : e($q['client_name']) ?></td></tr>
      <tr><td class="muted">Contract number</td><td><b><?= e($q['contract_number']) ?></b></td></tr>
    </table>
    <p class="sub" style="margin:8px 0 0">Operations packet (client, quote/contract no, contacts, service requirement, order lines, techno-commercial) has been floated to the team.</p>
    <form method="post" action="/quote-float?id=<?= (int)$q['id'] ?>" style="margin-top:8px"><button class="btn small secondary" type="submit">Re-send to operations</button></form>
  <?php elseif ($canContract): ?>
    <p class="sub">Enter the contract number to register the client (if new) and float the order to operations.</p>
    <form method="post" action="/quote-contract?id=<?= (int)$q['id'] ?>">
      <div class="form-grid">
        <div class="ff"><label>Contract number *</label><input class="form-control" name="contract_number" required placeholder="e.g. CON/2026/0142"></div>
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
    <?php if ($hist): ?><div style="margin-top:8px">
      <?php foreach ($hist as $h): ?><div class="muted" style="font-size:12px;padding:2px 0">Rev <?= str_pad((string)$h['rev'],2,'0',STR_PAD_LEFT) ?> · <?= e($h['summary']) ?> — <?= e($h['changed_by']) ?>, <?= e(substr((string)$h['changed_at'],0,10)) ?></div><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Follow-ups</h3>
    <?php if ($followups): ?>
    <table class="grid"><tr><th>When</th><th>Due date</th><th>Status</th></tr>
      <?php foreach ($followups as $f): ?><tr><td><?= e(lk_options_or('followup_kind', FOLLOWUP_KINDS)[$f['kind']] ?? $f['kind']) ?></td><td><?= e($f['due_date']) ?></td><td><span class="pill <?= $f['status']==='SENT'?'p-ok':($f['status']==='SKIPPED'?'p-mut':'p-warn') ?>"><?= e($f['status']) ?></span></td></tr><?php endforeach; ?>
    </table>
    <p class="muted" style="margin-top:6px">Scheduled when the quote is marked sent (3 / 6 / 9 days, fortnight, month). Auto-emails with templates come in the next phase.</p>
    <?php else: ?><p class="muted">Follow-ups are scheduled once the quote is marked <b>sent</b>.</p><?php endif; ?>
  </div>
</div>
