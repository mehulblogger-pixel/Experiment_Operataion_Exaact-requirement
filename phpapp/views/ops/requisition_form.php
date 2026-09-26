<?php
// Requirement form — five steps, phone first.
//   Position · Where & when · Selection · Commercial · Approval
// Only the first step is needed to save; a requirement with a designation and a
// headcount is a usable draft. Within each step, genuinely rare fields sit
// behind that step's own "More detail" button. This replaced a Simple/Advanced
// switch that still put ~60 fields, the whole cost build-up and a required
// approval reference on one page with the only Save button below all of them.
// Every field is backward-compatible: an old requisition simply has the newer
// fields empty, and typed values are never rewritten into coded ones.
$r = $req ?? [];
$isEdit = !empty($req['id']);
$v = fn($k, $d = '') => e($r[$k] ?? $d);
$sel = fn($k, $val) => (isset($r[$k]) && (string)$r[$k] === (string)$val) ? 'selected' : '';
$chk = fn($k) => !empty($r[$k]) ? 'checked' : '';
$cur = function_exists('cur_sym') ? cur_sym() : '₹';
?>
<?php if (!empty($form_err)): ?><div class="msg msg-error" style="margin:10px 0"><?= e($form_err) ?></div><?php endif; ?>
<style>
  /* ---------------------------------------------------------------------
     REQUIREMENT FORM — five steps, phone first.
     Was one page of ~60 fields with the Save button below all of them.
     docs/05-ui-ux-blueprint.md: "Break long forms into steps. Always show
     progress." and "Never reduce body text below 16px on mobile."
     The stepper is a PROGRESSIVE ENHANCEMENT: the .rq-wiz class is added by
     script, so with scripting off every step stays visible and the form
     still saves exactly as it always did.
     --------------------------------------------------------------------- */

  /* ---- step rail ---- */
  .rq-steps{display:flex;gap:4px;margin:14px 0 6px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
  .rq-steps::-webkit-scrollbar{display:none}
  .rq-steps button{flex:1 0 auto;min-width:104px;min-height:56px;border:0;background:transparent;
    padding:6px 10px;cursor:pointer;text-align:left;border-bottom:3px solid var(--line,#e5e7eb);
    display:flex;flex-direction:column;gap:2px;color:var(--muted,#656e7a);font:inherit}
  .rq-steps button .k{font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
  .rq-steps button .t{font-size:14.5px;font-weight:600;color:var(--ink,#1f2937);white-space:nowrap}
  .rq-steps button.on{border-bottom-color:var(--brand,#1e40af);color:var(--brand,#1e40af)}
  .rq-steps button.on .t{color:var(--brand,#1e40af)}
  .rq-steps button.done .k::after{content:" ✓";color:var(--green,#16a34a)}
  .rq-bar{height:4px;border-radius:4px;background:var(--line,#e5e7eb);overflow:hidden;margin:0 0 4px}
  .rq-bar i{display:block;height:100%;background:var(--brand,#1e40af);transition:width .2s ease;width:20%}
  .rq-count{font-size:13px;color:var(--muted,#656e7a);margin:0 0 12px}
  @media (prefers-reduced-motion: reduce){ .rq-bar i{transition:none} }

  /* ---- sections ---- */
  .rq-sec{border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:4px 16px 18px;margin:14px 0;background:var(--card,#fff)}
  .rq-sec > h3{font-size:13.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted,#656e7a);margin:16px 0 4px;display:flex;align-items:center;gap:8px}
  .rq-sec > h3 .num{font-family:ui-monospace,monospace;font-size:11.5px;background:var(--soft,#eef2f7);border-radius:20px;padding:2px 9px;color:var(--brand,#1e40af)}
  .rq-sec .optional{margin-left:auto;font-size:11.5px;letter-spacing:0;text-transform:none;color:var(--muted,#656e7a);font-weight:400}
  form.rq-wiz .rq-sec[data-step]{display:none}
  form.rq-wiz .rq-sec[data-step].on{display:block}

  /* ---- readable on a phone (blueprint: never below 16px) ---- */
  @media(max-width:760px){
    .rq-sec label,.rq-chk label{font-size:16px}
    .rq-sec .form-control{font-size:16px}          /* also stops iOS zooming on focus */
    .rq-sec small.muted,.rq-sec .muted{font-size:13.5px}
    .rq-sec{padding:4px 13px 16px;border-radius:12px}
  }

  .rq-chk{display:flex;flex-wrap:wrap;gap:10px 18px}
  .rq-chk label{display:inline-flex;align-items:center;gap:9px;font-size:14.5px;font-weight:500;color:var(--ink,#1f2937);
    min-height:44px;padding:2px 0}                  /* gloves: a tappable row, not a 16px box */
  .rq-chk input{width:20px;height:20px;flex:0 0 auto}

  /* ---- footer: Save is reachable from step 1, and always in reach ---- */
  .rq-foot{position:sticky;bottom:0;z-index:5;display:flex;gap:10px;align-items:center;flex-wrap:wrap;
    margin-top:14px;padding:12px 0 calc(12px + env(safe-area-inset-bottom,0px));
    background:linear-gradient(to top,var(--bg,#f7f9fc) 62%,transparent)}
  .rq-foot .btn{min-height:48px;font-size:15px}
  /* .btn declares its own display, which beats the [hidden] attribute's
     UA rule — so "Back" stayed on screen on step 1 until this said otherwise. */
  .rq-foot .btn[hidden]{display:none}
  .rq-foot .spacer{flex:1 1 auto}
  .rq-foot .why{font-size:13px;color:var(--muted,#656e7a);flex-basis:100%;margin:0}
  @media(max-width:560px){
    .rq-foot .btn{flex:1 1 46%}
    .rq-foot .spacer{display:none}
  }

  /* ---- certificate chips ---- */
  .rq-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px;min-height:4px}
  .rq-chips .chip{display:inline-flex;align-items:center;gap:7px;background:var(--soft,#eef2f7);
    border:1px solid var(--line,#e5e7eb);border-radius:20px;padding:5px 7px 5px 12px;font-size:13.5px}
  .rq-chips .chip button{border:0;background:transparent;cursor:pointer;color:var(--muted,#656e7a);
    font-size:16px;line-height:1;min-width:28px;min-height:28px;border-radius:50%}
  .rq-chips .chip button:hover{background:var(--line,#e5e7eb);color:var(--red,#dc2626)}

  /* ---- deployment groups: a table on a laptop, cards on a phone ---- */
  #rq_groups{width:100%}
  @media(max-width:760px){
    #rq_groups thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
    #rq_groups,#rq_groups tbody,#rq_groups tr,#rq_groups td{display:block;width:auto}
    #rq_groups tr.rqg-row{border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:10px 12px;margin:0 0 10px;position:relative;background:var(--card,#fff)}
    #rq_groups td{border:0;padding:6px 0}
    #rq_groups td::before{content:attr(data-l);display:block;font-size:12.5px;font-weight:600;
      color:var(--muted,#656e7a);margin-bottom:4px}
    #rq_groups td.num{position:absolute;top:6px;right:8px;padding:0}
    #rq_groups td.num::before{content:none}
    #rq_groups input[name="group_headcount[]"]{width:100%!important}
  }

  /* ---- rare fields, tucked behind each step's own "More detail" ----
     Scoped to the section, so opening detail on Commercial does not also
     unfold it on Position. Only applies once scripting has added .rq-wiz —
     with scripting off every field is visible, as it was before. */
  form.rq-wiz .rq-sec:not(.rq-show-adv) .rq-adv{display:none}

  /* ---- commercial preview ---- */
  .rq-calc{background:var(--soft,#f5f8fc);border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:14px 16px;margin-top:6px}
  .rq-calc .row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
  .rq-calc .c{text-align:left} .rq-calc .c .l{font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a)}
  .rq-calc .c .n{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums} .rq-calc .c .n.good{color:var(--green,#16a34a)} .rq-calc .c .n.bad{color:var(--red,#dc2626)}
  .rq-calc .note{font-size:12.5px;color:var(--muted,#656e7a);margin-top:8px}
  @media(max-width:720px){ .rq-calc .row{grid-template-columns:repeat(2,1fr)} }
  /* Cost build-up */
  .rq-cost{border:1px dashed var(--line,#e5e7eb);border-radius:12px;padding:12px 14px;background:var(--soft,#f7f9fc)}
  .rq-cost .rq-cost-head{max-width:520px}
  .rq-cost .muted{color:var(--muted,#656e7a);font-weight:400}
  .rq-cost .ff[data-head][hidden]{display:none}
  .rq-bu{margin-top:10px;border-top:1px solid var(--line,#e5e7eb);padding-top:10px}
  .rq-bu .bu-lines{display:flex;flex-direction:column;gap:3px}
  .rq-bu .bu-lines .li{display:flex;justify-content:space-between;font-size:13.5px;color:var(--ink,#334155)}
  .rq-bu .bu-lines .li i{color:var(--muted,#656e7a);font-style:normal}
  .rq-bu .bu-total{display:flex;justify-content:space-between;align-items:baseline;margin-top:7px;font-size:14px;font-weight:600}
  .rq-bu .bu-total b{font-size:18px;color:var(--brand,#1e40af);font-variant-numeric:tabular-nums}
  #rq_cost_auto{cursor:pointer;text-decoration:underline;color:var(--brand,#1e40af)}
</style>

<div class="crumbs"><a href="/">Home</a> › <a href="/requisitions"><?= e(TP('requisition')) ?></a> › <?= $isEdit ? 'Edit' : 'New' ?></div>
<?php // M4 correction §1 — name the object. This screen creates an approved
      // RECRUITMENT REQUISITION directly (the direct path); it is not the hiring
      // request, and it is not a marketplace requirement.
      $RL = function_exists('hreq_label') ? hreq_label('requisition') : 'Recruitment Requisition'; ?>
<h1><?= $isEdit ? 'Edit ' . e(mb_strtolower($RL)) . ' ' . e($req['req_code']) : 'New ' . e(mb_strtolower($RL)) ?></h1>
<p class="sub">Approved positions to recruit against. Five short steps — only the first is needed to save; the rest can follow.</p>

<form method="post" action="<?= $isEdit ? '/requisition-edit?id='.(int)$req['id'] : '/requisition-new' ?>" id="rqForm">
  <?php // Five named steps. Only the first is required to save — a requirement
        // with a designation and a headcount is a usable draft, and the rest can
        // follow. Steps 3-5 are marked optional on the rail itself so nobody
        // hunts for a field that was never needed. ?>
  <div class="rq-steps" role="tablist" aria-label="Requirement steps">
    <button type="button" class="on" data-step="1" role="tab" aria-selected="true"><span class="k">Step 1</span><span class="t">Position</span></button>
    <button type="button" data-step="2" role="tab" aria-selected="false"><span class="k">Step 2</span><span class="t">Where &amp; when</span></button>
    <button type="button" data-step="3" role="tab" aria-selected="false"><span class="k">Step 3</span><span class="t">Selection</span></button>
    <button type="button" data-step="4" role="tab" aria-selected="false"><span class="k">Step 4</span><span class="t">Commercial</span></button>
    <button type="button" data-step="5" role="tab" aria-selected="false"><span class="k">Step 5</span><span class="t">Approval</span></button>
  </div>
  <div class="rq-bar" aria-hidden="true"><i id="rq_bar_i"></i></div>
  <p class="rq-count" id="rq_count">Step 1 of 5 — Position. You can save after this step; the rest can follow.</p>

  <?php // §15 — paste a requirement, let AI extract the fields (human always reviews).
  if (function_exists('ai_enabled') && ai_enabled()): ?>
  <div class="rq-sec on" data-step="1" style="border-color:#c7d2fe;background:#eef2ff">
    <h3 style="color:#3730a3"><span class="num" style="background:#e0e7ff;color:#3730a3">✨</span> Paste a requirement — let AI fill the form</h3>
    <p class="sub" style="margin:0 0 8px">Paste the client's email, job description or WhatsApp message. AI extracts the fields for you to <b>review before saving</b> — it never creates the requirement itself.</p>
    <textarea class="form-control" id="ai_src" rows="4" placeholder="e.g. We need 5 senior welding inspectors (CSWIP 3.1) at our Dahej site for 12 months, day shift; gate pass and medical required; rate around 90k per month…"></textarea>
    <div style="margin-top:8px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button type="button" class="btn" id="ai_go">✨ Extract with AI</button>
      <span id="ai_msg" class="muted" style="font-size:12.5px"></span>
    </div>
  </div>
  <script>
  (function(){
    var go=document.getElementById('ai_go'), src=document.getElementById('ai_src'), msg=document.getElementById('ai_msg'), form=document.getElementById('rqForm');
    if(!go||!form) return;
    function setField(name,val){ if(val===undefined||val===null||String(val)==='') return; var el=form.elements[name]; if(!el||el.length===undefined&&!el.tagName) return;
      if(el.tagName==='SELECT'){ var v=String(val).toLowerCase().trim();
        for(var i=0;i<el.options.length;i++){ var o=el.options[i]; var ov=o.value.toLowerCase(), ot=o.text.toLowerCase();
          if(ov===v||ot===v||(v.length>2&&(ot.indexOf(v)>=0||v.indexOf(ov)>=0&&ov!=='' ))){ el.selectedIndex=i; el.dispatchEvent(new Event('change')); return; } } }
      else { el.value=val; el.dispatchEvent(new Event('input')); } }
    go.addEventListener('click', function(){
      var text=(src.value||'').trim(); if(!text){ msg.textContent='Paste the requirement text first.'; return; }
      go.disabled=true; msg.textContent='Reading…';
      var fd=new FormData(); fd.append('_csrf','<?= e(csrf_token()) ?>'); fd.append('text',text);
      fetch('/req-ai-extract',{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}).then(function(r){return r.json();}).then(function(d){
        go.disabled=false;
        if(!d||!d.ok){ msg.textContent=(d&&d.error)||'Could not extract — fill the form manually.'; return; }
        var f=d.fields||{}, n=0;
        ['designation','quantity','discipline','category','skills','qualification','experience_min','project_site','deploy_location','work_model','start_date','end_date','duty_hours','shift','billing_rate','rate_basis','contact_name','contact_phone','notes'].forEach(function(k){ if(f[k]!==undefined&&String(f[k])!==''){ setField(k,f[k]); n++; } });
        // AI fills fields across several steps — show the detail fields and
        // let the reviewer walk every step rather than land on one of them.
        if (form.rqShowAllSteps) form.rqShowAllSteps();
        msg.innerHTML='<b style="color:#3730a3">AI filled '+n+' field(s)</b> — please review every field before saving.';
      }).catch(function(){ go.disabled=false; msg.textContent='Network error — try again.'; });
    });
  })();
  </script>
  <?php endif; ?>

  <!-- ===== 1 · Client & position ===== -->
  <div class="rq-sec on" data-step="1">
    <h3><span class="num">1</span> Client &amp; position</h3>
    <p class="sub" style="margin:0 0 4px;font-size:13.5px">Who it is for, and what you are recruiting.</p>
    <div class="form-grid">
      <div class="ff"><label>Type *</label><select class="form-control" name="req_type" id="rq_type"><?php foreach (lk_options_or('requisition_type', REQ_TYPES) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('req_type',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff rq-adv" id="rq_out" style="<?= (($r['req_type'] ?? '')==='REPLACEMENT')?'':'display:none' ?>"><label>Replacing (engineer who left)</label>
        <select class="form-control searchable" name="outgoing_inspector_id"><option value="">—</option><?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= $sel('outgoing_inspector_id',$i['id']) ?>><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?></select></div>
      <div class="ff rq-adv"><label>Client <a href="#" class="addlink" data-qa="client" data-target="select[name='client_id']">+ Add new</a></label><select class="form-control searchable" name="client_id"><option value="">—</option><?php foreach (($clients ?? []) as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= $sel('client_id',$cl['id']) ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?></select>
        <small class="muted">No PO yet? Add the client here — details can follow.</small></div>
      <?php // The client's own contacts are already on file and already fetched for
        // the deployment-groups table below. Offering them here stops the same
        // person being re-typed (and misspelled) at the top of the form.
        $rqCC = []; foreach (($clientContacts ?? []) as $cc) $rqCC[] = (string)$cc['name']; ?>
      <div class="ff rq-adv"><label>Client contact</label>
        <input class="form-control" name="contact_name" id="rq_contact_name" value="<?= $v('contact_name') ?>" list="rq_cc_list" placeholder="Name at the client">
        <datalist id="rq_cc_list"><?php foreach ($rqCC as $n): ?><option value="<?= e($n) ?>"></option><?php endforeach; ?></datalist>
      </div>
      <div class="ff rq-adv"><label>Contact email</label><input class="form-control" type="email" name="contact_email" value="<?= $v('contact_email') ?>"></div>
      <div class="ff rq-adv"><label>Contact phone</label><input class="form-control" name="contact_phone" value="<?= $v('contact_phone') ?>"></div>
      <div class="ff rq-adv"><label>Contract number</label><input class="form-control" name="contract_ref" value="<?= $v('contract_ref') ?>" placeholder="Contract no. (if any)"></div>
      <div class="ff rq-adv"><label>PO reference</label><input class="form-control" name="po_ref" value="<?= $v('po_ref') ?>" placeholder="Client's PO number (if any)"></div>
      <div class="ff rq-adv"><label>Quotation ref <span class="muted">— for tracking</span></label><input class="form-control" name="quotation_ref" value="<?= $v('quotation_ref') ?>" placeholder="e.g. QTN/2026/0123"></div>
      <div class="ff"><label>Office</label><select class="form-control searchable" name="office_id"><option value="">—</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $sel('office_id',$o['id']) ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Responsible 1 — Recruiter</label><?php /* M5 — the owner this screen was showing. A save that arrives with a different owner already in the        database is refused rather than silently overwriting whoever changed it first. */ ?><input type="hidden" name="own_base_recruiter_id" value="<?= (int)($req['recruiter_id'] ?? 0) ?>"><select class="form-control searchable" name="recruiter_id"><option value="">—</option><?php foreach (($rccUsers ?? []) as $uid=>$un): ?><option value="<?= (int)$uid ?>" <?= $sel('recruiter_id',$uid) ?>><?= e($un) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Responsible 2 — Reporting manager</label><input type="hidden" name="own_base_manager_id" value="<?= (int)($req['manager_id'] ?? 0) ?>"><select class="form-control searchable" name="manager_id"><option value="">—</option><?php foreach (($rccUsers ?? []) as $uid=>$un): ?><option value="<?= (int)$uid ?>" <?= $sel('manager_id',$uid) ?>><?= e($un) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Department</label><select class="form-control searchable" name="department"><option value="">—</option><?php foreach ((function_exists('dept_form_options') ? dept_form_options($req['department'] ?? '') : ($rccDepts ?? [])) as $dk=>$dv): ?><option value="<?= e($dk) ?>" <?= $sel('department',$dk) ?>><?= e($dv) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label><?= e(T("sbu")) ?></label><select class="form-control" name="sbu"><option value="">—</option><?php foreach (lk_options_or('sbu', OPS_SBUS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('sbu',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Designation / position *</label><select class="form-control searchable" name="designation"><option value="">—</option><?php foreach (lk_options_or('designation', DESIGNATIONS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('designation',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <?php // WHICH TEAM this requirement is for. It travels with the requirement
            // to acceptance, where it is confirmed, so nobody is classified by a
            // database default. A workspace that does no site work has no such
            // distinction to make, so the question is not asked there. ?>
      <?php if (!function_exists('wf_ops_capability') || wf_ops_capability() !== 'NO'): ?>
      <div class="ff"><label>Which team <span class="muted">— confirmed again when somebody is accepted</span></label>
        <select class="form-control" name="team_role">
          <option value="">— not decided yet —</option>
          <?php foreach (WF_TEAM_ROLES as $trK => $trV): ?>
            <option value="<?= e($trK) ?>" <?= $sel('team_role', $trK) ?>><?= e($trV) ?></option>
          <?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div class="ff"><label>How many? *</label><input class="form-control" type="number" min="1" step="1" name="quantity" id="rq_qty" value="<?= e($r['quantity'] ?? '1') ?>"></div>
      <div class="ff"><label>Project / site</label><input class="form-control" name="project_site" value="<?= $v('project_site') ?>" placeholder="Client works / project"></div>
      <div class="ff ff-wide"><label>Locations required <span class="muted">— one per line; add as many as you need</span></label>
        <textarea class="form-control" name="locations" rows="3" placeholder="e.g.&#10;Dahej — 3&#10;Hazira — 2&#10;Mundra — 1"><?= e($r['locations'] ?? '') ?></textarea>
        <small class="muted">Each line is a location for this requirement. Received CVs can be tagged to one of these.</small></div>
      <?php // 1c — deployment GROUPS: split the headcount by who they report to and
            //  where (e.g. 3 under A, 3 under B, 2 under C). The "reports to" list is
            //  the CLIENT'S own contacts (fetched, not re-typed); or type one not on file. ?>
      <div class="ff ff-wide rq-adv">
        <label>Deployment groups <span class="muted">— split the headcount by reporting person &amp; site; the total sets “How many?”. Leave empty for a simple single group.</span></label>
        <table class="grid" id="rq_groups" style="width:100%">
          <thead><tr><th style="width:84px">How many</th><th>Reports to <span class="muted">(client contact)</span></th><th>Site / location</th><th>Notes</th><th></th></tr></thead>
          <tbody id="rq_groups_body">
            <?php $gs = $groups ?? []; if (!$gs) $gs = [[]];
                  foreach ($gs as $g): ?>
              <tr class="rqg-row">
                <td data-l="How many"><input class="form-control" type="number" min="0" name="group_headcount[]" value="<?= e($g['headcount'] ?? '') ?>" style="width:76px"></td>
                <td data-l="Reports to (client contact)">
                  <select class="form-control rqg-contact" name="group_contact_id[]">
                    <option value="">— pick, or type below —</option>
                    <?php foreach (($clientContacts ?? []) as $cc): ?><option value="<?= (int)$cc['id'] ?>" <?= (int)($g['report_contact_id'] ?? 0)===(int)$cc['id']?'selected':'' ?>><?= e($cc['name']) ?><?= $cc['designation']?' · '.e($cc['designation']):'' ?><?= $cc['mobile']?' · '.e($cc['mobile']):'' ?></option><?php endforeach; ?>
                  </select>
                  <input class="form-control rqg-name" name="group_report_name[]" value="<?= e($g['report_name'] ?? '') ?>" placeholder="…or a name not on file" style="margin-top:4px">
                  <div style="display:flex;gap:4px;margin-top:4px">
                    <input class="form-control" name="group_report_phone[]" value="<?= e($g['report_phone'] ?? '') ?>" placeholder="phone">
                    <input class="form-control" name="group_report_email[]" value="<?= e($g['report_email'] ?? '') ?>" placeholder="email">
                  </div>
                </td>
                <td data-l="Site / location"><input class="form-control" name="group_site[]" value="<?= e($g['site'] ?? '') ?>" placeholder="site"></td>
                <td data-l="Notes"><input class="form-control" name="group_notes[]" value="<?= e($g['notes'] ?? '') ?>"></td>
                <td class="num"><button type="button" class="btn small secondary rqg-del" title="Remove">✕</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:6px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <button type="button" class="btn small secondary" id="rqg_add">+ Add group</button>
          <span class="muted" id="rqg_total" style="font-size:12px"></span>
        </div>
      </div>
      <script>(function(){
        var body=document.getElementById('rq_groups_body'), addBtn=document.getElementById('rqg_add'),
            totalEl=document.getElementById('rqg_total'), qty=document.getElementById('rq_qty'),
            clientSel=document.querySelector('select[name="client_id"]');
        if(!body) return;
        function rows(){ return [].slice.call(body.querySelectorAll('.rqg-row')); }
        function recomputeTotal(){
          var t=0, any=false;
          rows().forEach(function(r){ var f=r.querySelector('input[name="group_headcount[]"]'); if(f.value!=='') any=true; t+=(parseInt(f.value,10)||0); });
          if(totalEl) totalEl.textContent = any ? ('Total across groups: '+t+' — sets “How many?”.') : 'No groups — treated as one.';
          if(any && qty) qty.value = t;
        }
        function addRow(){
          var first=body.querySelector('.rqg-row'); if(!first) return;
          var clone=first.cloneNode(true);
          clone.querySelectorAll('input').forEach(function(i){ i.value=''; });
          var sel=clone.querySelector('.rqg-contact'); if(sel) sel.selectedIndex=0;
          body.appendChild(clone); recomputeTotal();
        }
        addBtn && addBtn.addEventListener('click', addRow);
        body.addEventListener('click', function(e){ var d=e.target.closest && e.target.closest('.rqg-del'); if(!d) return;
          if(rows().length>1) d.closest('.rqg-row').remove();
          else { d.closest('.rqg-row').querySelectorAll('input').forEach(function(i){i.value='';}); var s=d.closest('.rqg-row').querySelector('.rqg-contact'); if(s)s.selectedIndex=0; }
          recomputeTotal(); });
        body.addEventListener('input', recomputeTotal);
        function repopulateContacts(list){
          rows().forEach(function(r){ var sel=r.querySelector('.rqg-contact'); if(!sel) return; var keep=sel.value;
            sel.innerHTML='<option value="">— pick, or type below —</option>';
            (list||[]).forEach(function(c){ var o=document.createElement('option'); o.value=c.id; o.textContent=c.name+(c.designation?' · '+c.designation:'')+(c.mobile?' · '+c.mobile:''); if(String(c.id)===String(keep)) o.selected=true; sel.appendChild(o); }); });
        }
        clientSel && clientSel.addEventListener('change', function(){
          var id=clientSel.value; if(!id){ repopulateContacts([]); return; }
          fetch('/client-contacts?id='+encodeURIComponent(id),{headers:{'X-Requested-With':'fetch'}}).then(function(r){return r.json();}).then(function(list){ repopulateContacts(list); }).catch(function(){});
        });
        recomputeTotal();
      })();</script>
      <?php
        //  DISCIPLINE & SPECIALITY — the two fields that decide whether this
        //  requirement can ever be matched against the people on our books.
        //  Both come from the SAME masters the people register uses, so
        //  "Welding" on a requirement is the same "Welding" on an engineer.
        //  They are no longer Advanced-only: without them the match is blind.
        //
        //  Every list here stays TYPEABLE. Requirements raised before this hold
        //  free text, and a strict list would either reject it or drop it — so
        //  choosing "Something else" reveals the old box, the typed words are
        //  saved exactly as before, and no historical record is touched.
        $rqTrades   = function_exists('req_trade_options')   ? req_trade_options()   : [];
        $rqSkills   = function_exists('req_skills_by_trade') ? req_skills_by_trade() : [];
        $rqTradeId  = (int)($r['trade_id'] ?? 0);
        $rqSkillId  = (int)($r['skill_id'] ?? 0);
        // An older requirement carries words but no link — keep the words visible.
        $rqFreeDisc = ($rqTradeId === 0 && trim((string)($r['discipline'] ?? '')) !== '');
        $rqFreeCat  = ($rqSkillId === 0 && trim((string)($r['category']   ?? '')) !== '');
      ?>
      <?php if ($rqTrades): ?>
      <div class="ff"><label>Discipline <span class="muted">— what kind of work</span></label>
        <select class="form-control" name="trade_id" id="rq_trade">
          <option value="">— choose —</option>
          <?php foreach ($rqTrades as $tid => $tlabel): ?>
            <option value="<?= (int)$tid ?>" <?= $rqTradeId === (int)$tid ? 'selected' : '' ?>><?= e($tlabel) ?></option>
          <?php endforeach; ?>
          <option value="0" <?= $rqFreeDisc ? 'selected' : '' ?>>Something else — type it</option>
        </select>
        <div id="rq_disc_free" style="<?= $rqFreeDisc ? 'margin-top:6px' : 'display:none' ?>">
          <input class="form-control" name="discipline" value="<?= $v('discipline') ?>" placeholder="e.g. Welding, NDT, Coating">
          <small class="muted">Not on the list? Type it here, then ask your administrator to add it under Masters.</small>
        </div>
      </div>
      <div class="ff"><label>Speciality <span class="muted">— narrows the discipline</span></label>
        <select class="form-control" name="skill_id" id="rq_skill">
          <option value="">— choose a discipline first —</option>
        </select>
        <div id="rq_cat_free" style="<?= $rqFreeCat ? 'margin-top:6px' : 'display:none' ?>">
          <input class="form-control" name="category" value="<?= $v('category') ?>" placeholder="e.g. Pressure Vessels">
        </div>
      </div>
      <script>window.RQ_SKILLS = <?= json_encode($rqSkills, JSON_UNESCAPED_UNICODE) ?>;
              window.RQ_SKILL_SEL = <?= (int)$rqSkillId ?>;</script>
      <?php else: /* no Trade list configured on this workspace — the old boxes, unchanged */ ?>
      <div class="ff"><label>Discipline</label><input class="form-control" name="discipline" value="<?= $v('discipline') ?>" placeholder="e.g. Welding, NDT, Coating"></div>
      <div class="ff"><label>Category / sub-category</label><input class="form-control" name="category" value="<?= $v('category') ?>" placeholder="e.g. Static equipment"></div>
      <?php endif; ?>

      <?php // Qualification — the NSQF-banded ladder already seeded in every
            // workspace. Typeable, for the degree nobody listed. ?>
      <?php $rqQuals = function_exists('req_qual_options') ? req_qual_options() : []; ?>
      <div class="ff rq-adv"><label>Minimum qualification</label>
        <input class="form-control" name="qualification" value="<?= $v('qualification') ?>" list="rq_qual_list" placeholder="e.g. B.E. Mechanical">
        <?php if ($rqQuals): ?><datalist id="rq_qual_list"><?php foreach ($rqQuals as $qlabel): ?><option value="<?= e($qlabel) ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
      </div>

      <?php
        //  CERTIFICATES — was one free-text box, so "CSWIP 3.1", "cswip3.1" and
        //  "CSWIP Level 3.1" were three different requirements to the system.
        //  Now picked from the certification master (30 certificates with their
        //  issuing bodies), shown as removable chips. Still stored in the same
        //  `skills` text column, comma-separated, so every existing reader —
        //  the job-description generator, the careers posting, exports — keeps
        //  working with no change, and anything typed before is left alone.
        $rqCerts = function_exists('req_cert_options') ? req_cert_options() : [];
        $rqHave  = array_values(array_filter(array_map('trim', explode(',', (string)($r['skills'] ?? '')))));
      ?>
      <div class="ff ff-wide rq-adv"><label>Certificates required</label>
        <?php if ($rqCerts): ?>
          <select class="form-control" id="rq_cert_pick">
            <option value="">+ add a certificate…</option>
            <?php foreach ($rqCerts as $ccode => $clabel): ?><option value="<?= e($clabel) ?>"><?= e($clabel) ?></option><?php endforeach; ?>
            <?php //  A real certificate the seeded list has never heard of used to
                  //  have nowhere to go, so it ended up in a notes field where
                  //  nothing can match on it. Offered only to somebody who may
                  //  maintain the master — see ops_cert_add().
                  $rqCanAddCert = function_exists('connect_qualtax_manage_can') ? connect_qualtax_manage_can()
                                : (function_exists('is_admin_level') && is_admin_level());
                  if ($rqCanAddCert): ?>
              <option value="__new__">➕ Not on the list — add one…</option>
            <?php endif; ?>
          </select>
          <?php if ($rqCanAddCert): ?>
            <div id="rq_cert_new" hidden style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:6px;margin-top:6px">
              <input class="form-control" id="rq_cert_new_name" placeholder="Certificate name — e.g. CSWIP 3.2" maxlength="240">
              <input class="form-control" id="rq_cert_new_body" placeholder="Issuing body (optional) — e.g. TWI" maxlength="120">
              <button class="btn small" type="button" id="rq_cert_new_go">Add</button>
              <button class="btn small secondary" type="button" id="rq_cert_new_x">Cancel</button>
            </div>
            <div class="muted" id="rq_cert_msg" style="margin-top:4px;font-size:12.5px"></div>
          <?php endif; ?>
          <div class="rq-chips" id="rq_cert_chips"></div>
          <input type="hidden" name="skills" id="rq_skills_val" value="<?= $v('skills') ?>">
          <script>window.RQ_CERTS_HAVE = <?= json_encode($rqHave, JSON_UNESCAPED_UNICODE) ?>;
                   window.RQ_CERT_CSRF = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;</script>
          <small class="muted">Pick as many as the role needs.
            <?= $rqCanAddCert ? 'Anything you add here joins the list for everyone.' : 'Maintained under Admin → Qualifications &amp; certifications.' ?></small>
        <?php else: ?>
          <input class="form-control" name="skills" value="<?= $v('skills') ?>" placeholder="e.g. CSWIP 3.1, NDT UT-II">
        <?php endif; ?>
      </div>
      <div class="ff rq-adv"><label>Experience (min years)</label><input class="form-control" type="number" step="0.5" name="experience_min" value="<?= $v('experience_min') ?>"></div>
      <div class="ff rq-adv"><label>Relevant experience</label><input class="form-control" name="relevant_experience" value="<?= $v('relevant_experience') ?>" placeholder="in the required scope"></div>
      <div class="ff rq-adv" style="grid-column:1/-1"><label>Key responsibilities</label><textarea class="form-control" name="responsibilities" rows="3" placeholder="One responsibility per line — feeds the auto-generated job description &amp; careers posting."><?= $v('responsibilities') ?></textarea></div>
    </div>
  </div>

  <!-- ===== 2 · Deployment ===== -->
  <div class="rq-sec" data-step="2">
    <h3><span class="num">2</span> Deployment — where &amp; when</h3>
    <div class="form-grid">
      <div class="ff"><label>Work model</label><select class="form-control" name="work_model"><option value="">—</option><?php foreach (lk_options_or('req_work_model', REQ_WORK_MODELS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('work_model',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <?php // Offer the places this workspace already deploys to, rather than asking
        // everyone to re-type "Dahej" in four spellings. Still free text.
        $rqLocs = [];
        try { foreach (ops_all("SELECT DISTINCT deploy_location FROM requisitions WHERE COALESCE(deploy_location,'')<>'' ORDER BY deploy_location") as $lr) $rqLocs[] = $lr['deploy_location']; }
        catch (Throwable $e) {} ?>
      <div class="ff"><label>Deployment location</label>
        <input class="form-control" name="deploy_location" value="<?= $v('deploy_location') ?>" list="rq_loc_list" placeholder="e.g. Dahej">
        <?php if ($rqLocs): ?><datalist id="rq_loc_list"><?php foreach ($rqLocs as $l): ?><option value="<?= e($l) ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
      </div>
      <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date" id="rq_start" value="<?= $v('start_date') ?>"></div>
      <div class="ff"><label>End date</label><input class="form-control" type="date" name="end_date" id="rq_end" value="<?= $v('end_date') ?>"></div>
      <div class="ff"><label>Duration (months)</label><input class="form-control" type="number" step="0.5" name="duration_months" id="rq_months" value="<?= e(($r['duration_months'] ?? 0) ?: '') ?>" placeholder="auto from dates"></div>
      <?php $rqDuty = function_exists('req_duty_hours_options') ? req_duty_hours_options() : []; ?>
      <div class="ff"><label>Duty hours</label>
        <input class="form-control" name="duty_hours" value="<?= $v('duty_hours') ?>" list="rq_duty_list" placeholder="e.g. 8 hours / 6 days">
        <?php if ($rqDuty): ?><datalist id="rq_duty_list"><?php foreach ($rqDuty as $dlabel): ?><option value="<?= e($dlabel) ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
      </div>
      <div class="ff"><label>Shift</label><select class="form-control" name="shift"><option value="">—</option><?php foreach (lk_options_or('req_shift', REQ_SHIFTS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('shift',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <?php $rqAllow = function_exists('req_allowance_options') ? req_allowance_options() : []; ?>
      <div class="ff"><label>Other allowances</label>
        <input class="form-control" name="other_allowances" value="<?= $v('other_allowances') ?>" list="rq_allow_list" placeholder="e.g. Site allowance">
        <?php if ($rqAllow): ?><datalist id="rq_allow_list"><?php foreach ($rqAllow as $alabel): ?><option value="<?= e($alabel) ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
      </div>
    </div>
    <?php // 1f — who provides each facility on deployment: not applicable / we provide /
          //  the client provides. Covers Food, Accommodation, Travel and Local conveyance. ?>
    <h3 style="margin-top:6px">Facilities — provided by</h3>
    <div class="form-grid">
      <?php $provBy = function($key, $label) use ($v) {
              $cur = (string)$v($key);
              // Back-compat: if only the legacy boolean is set, treat it as "US".
              if ($cur === '' && $key === 'prov_food_by' && (int)$v('prov_food') === 1) $cur = 'US';
              if ($cur === '' && $key === 'prov_accom_by' && (int)$v('prov_accommodation') === 1) $cur = 'US';
              if ($cur === '' && $key === 'prov_travel_by' && (int)$v('prov_travel') === 1) $cur = 'US';
              echo '<div class="ff"><label>' . e($label) . '</label><select class="form-control" name="' . e($key) . '">';
              foreach (['' => '— not applicable —', 'US' => 'Us', 'CLIENT' => 'Client'] as $k => $lbl)
                  echo '<option value="' . e($k) . '"' . ($cur === $k ? ' selected' : '') . '>' . e($lbl) . '</option>';
              echo '</select></div>';
          }; ?>
      <?php $provBy('prov_food_by', 'Food'); ?>
      <?php $provBy('prov_accom_by', 'Accommodation'); ?>
      <?php $provBy('prov_travel_by', 'Travel'); ?>
      <?php $provBy('prov_local_by', 'Local conveyance'); ?>
    </div>
  </div>

  <!-- ===== 3 · Selection & compliance ===== -->
  <div class="rq-sec" data-step="3">
    <h3><span class="num">3</span> Selection &amp; compliance <span class="optional">optional — skip if not required</span></h3>
    <div class="rq-chk" style="margin-bottom:6px">
      <label><input type="checkbox" name="sel_client_interview" value="1" <?= $chk('sel_client_interview') ?>> Client interview</label>
      <label><input type="checkbox" name="sel_tech_interview" value="1" <?= $chk('sel_tech_interview') ?>> Technical interview</label>
      <label><input type="checkbox" name="sel_hr_interview" value="1" <?= $chk('sel_hr_interview') ?>> HR interview</label>
      <label><input type="checkbox" name="client_approval_req" value="1" <?= $chk('client_approval_req') ?>> Client approval required</label>
      <label><input type="checkbox" name="training_req" value="1" <?= $chk('training_req') ?>> Training required</label>
    </div>
    <h3 style="margin-top:4px">Documents / gate requirements</h3>
    <div class="rq-chk">
      <label><input type="checkbox" name="cmp_medical" value="1" <?= $chk('cmp_medical') ?>> Medical fitness</label>
      <label><input type="checkbox" name="cmp_pcc" value="1" <?= $chk('cmp_pcc') ?>> Police verification (PCC)</label>
      <label><input type="checkbox" name="cmp_gate_pass" value="1" <?= $chk('cmp_gate_pass') ?>> Gate pass</label>
      <label><input type="checkbox" name="cmp_safety" value="1" <?= $chk('cmp_safety') ?>> Safety induction</label>
      <label><input type="checkbox" name="cmp_certification" value="1" <?= $chk('cmp_certification') ?>> Certification</label>
    </div>
    <div class="form-grid" style="margin-top:8px"><div class="ff ff-wide"><label>Documents note</label><input class="form-control" name="documents_note" value="<?= $v('documents_note') ?>"></div></div>
  </div>

  <!-- ===== 4 · Commercial ===== -->
  <div class="rq-sec" data-step="4">
    <h3><span class="num">4</span> Commercial <span class="optional">optional — usually finance or the manager</span></h3>

    <?php // ---- Cost build-up (sourcing-model aware) --------------------------
          //  How WE source this person decides which cost heads apply. The
          //  monthly cost per person is built up from them, feeds the flat
          //  "Est. cost/person/month" below, and drives the project costing. ?>
    <div class="rq-cost" id="rq_cost_bu">
      <div class="rq-cost-head">
        <div class="ff" style="margin:0">
          <label>Sourcing model — how we supply this person</label>
          <select class="form-control" name="sourcing_model" id="rq_smodel">
            <option value="">— choose —</option>
            <?php foreach (lk_options_or('req_sourcing_model', REQ_SOURCING_MODELS) as $k=>$val): ?>
              <option value="<?= e($k) ?>" <?= $sel('sourcing_model',$k) ?>><?= e($val) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="muted" id="rq_smodel_hint"></small>
        </div>
      </div>
      <div class="form-grid" style="margin-top:8px">
        <div class="ff" data-head="wage"><label id="lbl_wage">Base wage / salary — / person / month (<?= e($cur) ?>)</label>
          <input class="form-control" type="number" step="0.01" name="cost_wage" id="rq_wage" value="<?= e(($r['cost_wage'] ?? 0) ?: '') ?>"></div>
        <div class="ff" data-head="statutory"><label>Statutory &amp; benefits — <b>% of wage</b> <span class="muted">PF/ESIC/bonus/leave</span></label>
          <input class="form-control" type="number" step="0.1" min="0" max="100" name="cost_statutory_pct" id="rq_stat" value="<?= e(($r['cost_statutory_pct'] ?? 0) ?: '') ?>" placeholder="e.g. 25 (a percent, not rupees)">
          <small class="muted">A percentage of the wage, not a rupee amount. Typically 15–45%.</small></div>
        <div class="ff" data-head="agency"><label>Agency service fee / markup — <b>%</b></label>
          <input class="form-control" type="number" step="0.1" min="0" max="100" name="cost_agency_pct" id="rq_agency" value="<?= e(($r['cost_agency_pct'] ?? 0) ?: '') ?>" placeholder="e.g. 8 (a percent)"></div>
        <div class="ff" data-head="reimburse"><label>Reimbursables — / person / month (<?= e($cur) ?>) <span class="muted">travel/stay/food/PPE</span></label>
          <input class="form-control" type="number" step="0.01" name="cost_reimburse" id="rq_reimb" value="<?= e(($r['cost_reimburse'] ?? 0) ?: '') ?>"></div>
        <div class="ff" data-head="oneoff"><label>One-time / person (<?= e($cur) ?>) <span class="muted">medical/PCC/training/mobilisation</span></label>
          <input class="form-control" type="number" step="0.01" name="cost_oneoff" id="rq_oneoff" value="<?= e(($r['cost_oneoff'] ?? 0) ?: '') ?>"></div>
      </div>
      <div class="rq-bu" id="rq_bu"><div class="bu-lines" id="rq_bu_lines"></div>
        <div class="bu-total"><span>Built-up cost / person / month</span><b id="rq_bu_total">—</b></div>
      </div>
    </div>

    <div class="form-grid" style="margin-top:12px">
      <div class="ff"><label>Billing rate (<?= e($cur) ?>) <span class="muted">what we charge the client</span></label><input class="form-control" type="number" step="0.01" name="billing_rate" id="rq_rate" value="<?= e(($r['billing_rate'] ?? 0) ?: '') ?>"></div>
      <div class="ff"><label>Rate basis</label><select class="form-control" name="rate_basis" id="rq_basis"><?php foreach (lk_options_or('req_rate_basis', REQ_RATE_BASIS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('rate_basis',$k ?: 'MONTHLY') ?: ($k==='MONTHLY' && empty($r['rate_basis'])?'selected':'') ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Est. cost / person / month (<?= e($cur) ?>) <span class="muted" id="rq_cost_auto"></span></label><input class="form-control" type="number" step="0.01" name="budgeted_cost" id="rq_cost" value="<?= e(($r['budgeted_cost'] ?? 0) ?: '') ?>"></div>
      <div class="ff rq-adv"><label>Target margin (%)</label><input class="form-control" type="number" step="0.1" min="0" max="100" name="target_margin" value="<?= e(($r['target_margin'] ?? 0) ?: '') ?>" placeholder="e.g. 20"></div>
      <div class="ff rq-adv"><label>Negotiation floor (<?= e($cur) ?>)</label><input class="form-control" type="number" step="0.01" name="negotiation_floor" value="<?= e(($r['negotiation_floor'] ?? 0) ?: '') ?>"></div>
    </div>
    <div class="rq-calc" id="rq_calc">
      <div class="row">
        <div class="c"><div class="l">Expected revenue</div><div class="n" id="cx_rev">—</div></div>
        <div class="c"><div class="l">Expected cost <span class="muted">(project)</span></div><div class="n" id="cx_cost">—</div></div>
        <div class="c"><div class="l">Expected profit</div><div class="n" id="cx_prof">—</div></div>
        <div class="c"><div class="l">Margin</div><div class="n" id="cx_marg">—</div></div>
      </div>
      <div class="note" id="cx_note">Choose the sourcing model and fill the cost heads, plus quantity, billing rate, basis and duration, to preview the project costing. Recomputed and stored on save.</div>
    </div>
  </div>

  <!-- ===== 5 · Approval ===== -->
  <div class="rq-sec" data-step="5">
    <h3><span class="num">5</span> Approval &amp; status <span class="optional">optional — fill when the approval comes through</span></h3>
    <div class="form-grid">
      <div class="ff"><label>Approval reference</label><input class="form-control" name="approval_ref" value="<?= $v('approval_ref') ?>" placeholder="e.g. HR-APP-2026-014"></div>
      <?php // Who approved this. Was free text, so the same manager appeared under
        // four spellings and no approval report could count them. The user list
        // is offered; a name from outside the system is still accepted. ?>
      <div class="ff"><label>Approved by</label>
        <input class="form-control" name="approved_by" value="<?= $v('approved_by') ?>" list="rq_appr_list" placeholder="Name of the approver">
        <?php if (!empty($rccUsers)): ?><datalist id="rq_appr_list"><?php foreach ($rccUsers as $un): ?><option value="<?= e($un) ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
      </div>
      <div class="ff"><label>Approval date</label><input class="form-control" type="date" name="approval_date" value="<?= $v('approval_date') ?>"></div>
      <div class="ff"><label>Status</label><select class="form-control" name="status"><?php foreach (lk_options_or('requisition_status', REQ_STATUS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= ($req ? $sel('status',$k) : ($k==='OPEN'?'selected':'')) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= $v('notes') ?>"></div>
    </div>
    <?php if (function_exists('custom_fields_for') && custom_fields_for('requisition')): ?>
      <h3>More details</h3>
      <div class="form-grid"><?php render_custom_fields('requisition', $cfvals ?? []); ?></div>
    <?php endif; ?>
  </div>

  <?php //  Save is reachable from step 1. A requirement with a designation and a
        //  headcount is a usable draft; the previous form put the only Save
        //  button below ~60 fields, so a coordinator on a phone had to scroll
        //  the whole form before anything could be kept. The bar is sticky so
        //  the primary action is never off-screen. ?>
  <div class="rq-foot">
    <button type="button" class="btn secondary" id="rq_prev" hidden>← Back</button>
    <button class="btn" type="submit" id="rq_save">Save <?= e(mb_strtolower($RL)) ?></button>
    <button type="button" class="btn" id="rq_next">Next →</button>
    <span class="spacer"></span>
    <a class="btn secondary" href="/requisitions">Cancel</a>
    <p class="why" id="rq_why">You can save now and add the rest later.</p>
  </div>
</form>
<?php if (function_exists('fd_overlay_html')) echo fd_overlay_html('requisition'); // Form Designer: per-company label/order/hide overrides ?>

<script>(function(){
  var form = document.getElementById('rqForm');
  var rail = form.querySelector('.rq-steps');
  var secs = [].slice.call(form.querySelectorAll('.rq-sec[data-step]'));
  var tabs = rail ? [].slice.call(rail.querySelectorAll('button')) : [];
  var bar  = document.getElementById('rq_bar_i'),  count = document.getElementById('rq_count');
  var prev = document.getElementById('rq_prev'),   next  = document.getElementById('rq_next');
  var why  = document.getElementById('rq_why'),    MAX   = 5, cur = 1;
  var TITLES = {1:'Position',2:'Where & when',3:'Selection',4:'Commercial',5:'Approval'};
  var OPTIONAL = {3:1,4:1,5:1};

  // PROGRESSIVE ENHANCEMENT. The stepping classes are added here, by script, so
  // a browser with scripting off still renders every field on one page and the
  // form saves exactly as it did before.
  if (secs.length) form.classList.add('rq-wiz');

  // --- per-step "More detail" -------------------------------------------
  // The steps are the disclosure; within a step, genuinely rare fields (PO and
  // contract references, negotiation floor, relevant experience) stay tucked
  // behind one button rather than being a second mode the user has to know about.
  secs.forEach(function(sec){
    if (!sec.querySelector('.rq-adv')) return;
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'btn small secondary'; b.style.marginTop = '10px';
    b.textContent = '+ More detail';
    b.addEventListener('click', function(){
      b.textContent = sec.classList.toggle('rq-show-adv') ? '− Less detail' : '+ More detail';
    });
    sec.appendChild(b);
  });

  // --- stepping ----------------------------------------------------------
  function show(n){
    n = Math.min(MAX, Math.max(1, n)); cur = n;
    secs.forEach(function(sec){ sec.classList.toggle('on', sec.getAttribute('data-step') === String(n)); });
    tabs.forEach(function(t){
      var tn = parseInt(t.getAttribute('data-step'), 10);
      t.classList.toggle('on', tn === n);
      t.classList.toggle('done', tn < n);
      t.setAttribute('aria-selected', tn === n ? 'true' : 'false');
    });
    if (bar) bar.style.width = Math.round(n / MAX * 100) + '%';
    if (count) count.textContent = 'Step ' + n + ' of ' + MAX + ' — ' + TITLES[n] +
      (OPTIONAL[n] ? '. Optional — you can skip this.' : '.');
    if (prev) prev.hidden = (n === 1);
    if (next) next.textContent = (n === MAX) ? 'Done' : (OPTIONAL[n + 1] ? 'Skip for now →' : 'Next →');
    if (why)  why.textContent = (n === 1)
      ? 'You can save now and add the rest later.'
      : 'Saving keeps everything filled in so far, on every step.';
    // Keep the active step in view on a phone without yanking the whole page.
    var t = tabs[n - 1]; if (t && t.scrollIntoView) t.scrollIntoView({block:'nearest', inline:'center'});
  }
  if (rail) rail.addEventListener('click', function(e){
    var b = e.target.closest('button'); if (b) show(parseInt(b.getAttribute('data-step'), 10));
  });
  if (next) next.addEventListener('click', function(){
    if (cur === MAX) { form.requestSubmit ? form.requestSubmit() : form.submit(); return; }
    show(cur + 1);
    form.scrollIntoView({block:'start', behavior:'smooth'});
  });
  if (prev) prev.addEventListener('click', function(){ show(cur - 1); form.scrollIntoView({block:'start', behavior:'smooth'}); });

  // A required field left blank on another step must not fail silently — the
  // browser cannot focus what is hidden, so jump to its step first.
  form.addEventListener('invalid', function(e){
    var sec = e.target.closest('.rq-sec[data-step]');
    if (sec) { var n = parseInt(sec.getAttribute('data-step'), 10); if (n !== cur) show(n); }
  }, true);

  // Used by the AI extractor: it fills fields across several steps, so the
  // reviewer is shown all of them rather than dropped on one.
  form.rqShowAllSteps = function(){
    form.classList.remove('rq-wiz');
    secs.forEach(function(s){ s.classList.add('on'); s.classList.add('rq-show-adv'); });
    if (count) count.textContent = 'All steps shown — review every field before saving.';
  };

  show(1);

  // Replacement-only "replacing" field.
  var t=document.getElementById('rq_type'), o=document.getElementById('rq_out');
  function so(){ if(o) o.style.display=(t && t.value==='REPLACEMENT')?'':'none'; }
  if(t){ t.addEventListener('change',so); }

  // --- Discipline → Speciality, from the same masters the people register uses.
  //  Choosing a discipline narrows the speciality list to that discipline's own
  //  specialities, so "Pressure Vessels" can never be filed under "Electrical".
  //  "Something else" (value 0) reveals the free-text box instead, for the
  //  discipline nobody has added to Masters yet.
  var trSel = document.getElementById('rq_trade'), skSel = document.getElementById('rq_skill'),
      discFree = document.getElementById('rq_disc_free'), catFree = document.getElementById('rq_cat_free');
  if (trSel && skSel) {
    var SK = window.RQ_SKILLS || {}, wantSkill = window.RQ_SKILL_SEL || 0;
    function fillSkills(keep){
      var tid = trSel.value, rows = (tid && tid !== '0') ? (SK[tid] || []) : [];
      skSel.innerHTML = '';
      var first = document.createElement('option');
      first.value = '';
      first.textContent = rows.length ? '— choose —'
                        : (tid === '0' ? '— type it below —' : '— choose a discipline first —');
      skSel.appendChild(first);
      rows.forEach(function(r){
        var op = document.createElement('option');
        op.value = r.id; op.textContent = r.label;
        if (String(r.id) === String(keep)) op.selected = true;
        skSel.appendChild(op);
      });
      if (rows.length) {
        var other = document.createElement('option');
        other.value = '0'; other.textContent = 'Something else — type it';
        skSel.appendChild(other);
      }
      skSel.disabled = !rows.length && tid !== '0';
      // A picker enhanced into a searchable widget must be told to redraw.
      syncFree();
    }
    function syncFree(){
      // The free-text box is shown only when the list cannot express the answer.
      if (discFree) discFree.style.display = (trSel.value === '0') ? '' : 'none';
      if (catFree)  catFree.style.display  = (trSel.value === '0' || skSel.value === '0') ? '' : 'none';
    }
    trSel.addEventListener('change', function(){ fillSkills(0); });
    skSel.addEventListener('change', syncFree);
    fillSkills(wantSkill);
  }

  // --- Certificates as chips -------------------------------------------
  //  Still stored in the same comma-separated `skills` column, so the job
  //  description generator, the careers posting and every export keep working
  //  untouched — only the way it is entered has changed.
  var certPick = document.getElementById('rq_cert_pick'), certBox = document.getElementById('rq_cert_chips'),
      certVal = document.getElementById('rq_skills_val');
  if (certPick && certBox && certVal) {
    var have = (window.RQ_CERTS_HAVE || []).slice();
    function paint(){
      certBox.innerHTML = '';
      have.forEach(function(name, i){
        var chip = document.createElement('span'); chip.className = 'chip';
        var txt = document.createElement('span'); txt.textContent = name; chip.appendChild(txt);
        var x = document.createElement('button');
        x.type = 'button'; x.textContent = '×';
        x.setAttribute('aria-label', 'Remove ' + name);
        x.addEventListener('click', function(){ have.splice(i, 1); paint(); });
        chip.appendChild(x); certBox.appendChild(chip);
      });
      certVal.value = have.join(', ');
    }
    certPick.addEventListener('change', function(){
      var v = certPick.value;
      if (v === '__new__') { showNew(true); certPick.selectedIndex = 0; return; }
      if (v && have.indexOf(v) < 0) { have.push(v); paint(); }
      certPick.selectedIndex = 0;
    });
    paint();

    // ---- learn a certificate the list does not have yet --------------------
    // The new name is saved to the same master the picker reads, so it is
    // offered to everybody from now on — and added to THIS requisition without
    // the person having to find it again in the list.
    var newBox = document.getElementById('rq_cert_new'),
        newName = document.getElementById('rq_cert_new_name'),
        newBody = document.getElementById('rq_cert_new_body'),
        newGo  = document.getElementById('rq_cert_new_go'),
        newX   = document.getElementById('rq_cert_new_x'),
        newMsg = document.getElementById('rq_cert_msg');
    function showNew(on){
      if (!newBox) return;
      newBox.hidden = !on;
      if (newMsg) newMsg.textContent = '';
      if (on && newName) { newName.value = ''; if (newBody) newBody.value = ''; newName.focus(); }
    }
    function addOption(label){
      // Keep the picker sorted-ish and never add the same label twice.
      for (var i = 0; i < certPick.options.length; i++)
        if (certPick.options[i].value === label) return;
      var o = document.createElement('option');
      o.value = label; o.textContent = label;
      certPick.insertBefore(o, certPick.options[certPick.options.length - 1]);
    }
    function saveNew(){
      if (!newName || !newGo) return;
      var nm = (newName.value || '').trim();
      if (!nm) { newMsg.textContent = 'Type the name of the certificate.'; return; }
      newGo.disabled = true; newMsg.textContent = 'Adding…';
      var body = new URLSearchParams();
      body.set('_csrf', window.RQ_CERT_CSRF || '');
      body.set('name', nm);
      body.set('body', (newBody && newBody.value || '').trim());
      fetch('/cert-add', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString(), credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
          newGo.disabled = false;
          if (!j || !j.ok) { newMsg.textContent = (j && j.error) || 'Could not add that certificate.'; return; }
          addOption(j.label);
          if (have.indexOf(j.label) < 0) { have.push(j.label); paint(); }
          newMsg.textContent = j.note || 'Added.';
          showNew(false);
        })
        .catch(function(){ newGo.disabled = false; newMsg.textContent = 'Could not reach the server — check your connection and try again.'; });
    }
    if (newGo) newGo.addEventListener('click', saveNew);
    if (newX)  newX.addEventListener('click', function(){ showNew(false); });
    // Enter inside the name box should add, not submit the whole requisition.
    if (newName) newName.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') { ev.preventDefault(); saveNew(); } });
    if (newBody) newBody.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') { ev.preventDefault(); saveNew(); } });
  }

  // Live commercial preview (mirrors req_commercials()/req_cost_buildup() server-side).
  var qty=document.getElementById('rq_qty'), rate=document.getElementById('rq_rate'),
      basis=document.getElementById('rq_basis'), cost=document.getElementById('rq_cost'),
      start=document.getElementById('rq_start'), end=document.getElementById('rq_end'), months=document.getElementById('rq_months');
  // Cost build-up inputs
  var smodel=document.getElementById('rq_smodel'), wage=document.getElementById('rq_wage'),
      stat=document.getElementById('rq_stat'), agency=document.getElementById('rq_agency'),
      reimb=document.getElementById('rq_reimb'), oneoff=document.getElementById('rq_oneoff');
  var sym='<?= e($cur) ?>';
  function fmt(n){ return sym+' '+Math.round(n).toLocaleString('en-IN'); }
  function months_(){ var m=parseFloat(months.value)||0; if(m>0) return m;
    if(start.value && end.value){ var d=(new Date(end.value)-new Date(start.value))/86400000; if(d>=0) return Math.round(d/30.4*100)/100; } return 0; }
  function num(el){ return el?(parseFloat(el.value)||0):0; }

  // Which cost heads each sourcing model uses, and the wage label + hint.
  var MODELS={
    OWN_PAYROLL:{heads:['wage','statutory','reimburse','oneoff'],wage:'Base wage / salary',hint:'Person on our roll — wage plus statutory (PF/ESIC/bonus/leave) plus any reimbursables.'},
    MANPOWER_AGENCY:{heads:['wage','statutory','agency','reimburse','oneoff'],wage:'Base wage / salary',hint:'Manpower supply agency — wage + statutory, then the agency service fee on top, plus reimbursables.'},
    SUBCON_AGENCY:{heads:['wage','reimburse','oneoff'],wage:"Sub-contractor's all-in rate",hint:'Third-party sub-contract — their rate is all-inclusive; add only what we carry (e.g. reimbursables, one-offs).'},
    FREELANCER:{heads:['wage','reimburse','oneoff'],wage:'Professional fee',hint:'Freelancer / consultant — a flat professional fee; no statutory, no agency fee.'},
    '':{heads:['wage','statutory','agency','reimburse','oneoff'],wage:'Base wage / salary',hint:'Pick a sourcing model to show only the cost heads that apply.'}
  };
  function applyModel(){
    var m=MODELS[smodel.value]||MODELS[''];
    document.getElementById('lbl_wage').firstChild.nodeValue='';
    document.getElementById('lbl_wage').textContent=m.wage+' — / person / month ('+sym+')';
    document.getElementById('rq_smodel_hint').textContent=m.hint;
    document.querySelectorAll('#rq_cost_bu .ff[data-head]').forEach(function(ff){
      ff.hidden = m.heads.indexOf(ff.getAttribute('data-head'))<0;
    });
  }
  // Monthly build-up per the model. Returns {monthly, oneoff, lines:[[label,val]]}.
  function buildup(){
    var mdl=smodel.value, w=num(wage), sp=num(stat), ap=num(agency), rb=num(reimb), of=num(oneoff);
    var statutory=0, fee=0, lines=[];
    if(mdl==='OWN_PAYROLL'||mdl==='MANPOWER_AGENCY') statutory=w*sp/100;
    if(mdl==='MANPOWER_AGENCY') fee=(w+statutory)*ap/100;
    var wLbl=(MODELS[mdl]||MODELS['']).wage;
    lines.push([wLbl,w]);
    if(statutory>0) lines.push(['Statutory & benefits ('+sp+'%)',statutory]);
    if(fee>0) lines.push(['Agency service fee ('+ap+'%)',fee]);
    if(rb>0) lines.push(['Reimbursables / month',rb]);
    return {monthly:w+statutory+fee+rb, oneoff:of, lines:lines};
  }
  function renderBuildup(bu){
    var box=document.getElementById('rq_bu_lines'); box.innerHTML='';
    bu.lines.forEach(function(l){ var d=document.createElement('div'); d.className='li'; d.innerHTML='<i>'+l[0]+'</i><span>'+fmt(l[1])+'</span>'; box.appendChild(d); });
    document.getElementById('rq_bu_total').textContent=bu.monthly?fmt(bu.monthly):'—';
    // Auto-fill the flat "Est. cost/person/month" from the build-up unless the
    // user has typed their own override. Track whether cost is auto-managed.
    if(bu.monthly>0 && !cost.dataset.userset){ cost.value=Math.round(bu.monthly*100)/100;
      document.getElementById('rq_cost_auto').textContent='· auto from build-up'; }
  }
  cost.addEventListener('input',function(){ cost.dataset.userset='1'; document.getElementById('rq_cost_auto').textContent='· manual override — click to re-link'; });
  document.getElementById('rq_cost_auto').addEventListener('click',function(){ delete cost.dataset.userset; calc(); });

  function calc(){
    applyModel();
    var bu=buildup(); renderBuildup(bu);
    var q=Math.max(1,parseInt(qty.value)||1), r=parseFloat(rate.value)||0, c=parseFloat(cost.value)||0, b=basis.value, m=months_();
    if(m<=0) m=1;   // an unspecified duration is quoted per month — mirror the server, never zero the P&L
    var units=(b==='MANDAY'||b==='DAILY')?Math.round(m*22):m;
    var rev=(b==='FIXED')?q*r:q*r*Math.max(units,0);
    var recurring=q*c*Math.max(m,0), oneoffTot=q*(bu.oneoff||0);
    var ct=recurring+oneoffTot; var pf=rev-ct; var mg=rev>0?(pf/rev*100):0;
    document.getElementById('cx_rev').textContent=rev?fmt(rev):'—';
    document.getElementById('cx_cost').textContent=ct?fmt(ct):'—';
    var pe=document.getElementById('cx_prof'); pe.textContent=(rev||ct)?fmt(pf):'—'; pe.className='n '+(pf>=0?'good':'bad');
    var me=document.getElementById('cx_marg'); me.textContent=rev?mg.toFixed(1)+'%':'—'; me.className='n '+(mg>=0?'good':'bad');
    var note=document.getElementById('cx_note');
    if(rev||ct){ note.textContent=q+' person(s) × '+fmt(c)+'/mo × '+(m||0)+' month(s) = '+fmt(recurring)+' recurring'
      +(oneoffTot>0?' + '+fmt(oneoffTot)+' one-off':'')+'.  Revenue: '+q+' × '+fmt(r)+' ('+(basis.options[basis.selectedIndex].text)+')'+(b==='FIXED'?'':' × '+(units||0)+' unit(s)')+'.'; }
  }
  [qty,rate,basis,start,end,months,smodel,wage,stat,agency,reimb,oneoff].forEach(function(el){ if(el){ el.addEventListener('input',calc); el.addEventListener('change',calc);} });
  // If editing a req that already had a manual cost different from the build-up, respect it.
  <?php if ($req && (float)($r['budgeted_cost'] ?? 0) > 0): ?>cost.dataset.userset='1';<?php endif; ?>
  calc();
})();</script>
