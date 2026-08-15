<?php
// Requirement form — Simple / Advanced progressive disclosure (Phase 2).
// Essential fields are always visible; the Advanced switch reveals the full
// position / deployment / selection / compliance detail. Every field is
// backward-compatible: an old requisition simply has the new fields empty.
$r = $req ?? [];
$v = fn($k, $d = '') => e($r[$k] ?? $d);
$sel = fn($k, $val) => (isset($r[$k]) && (string)$r[$k] === (string)$val) ? 'selected' : '';
$chk = fn($k) => !empty($r[$k]) ? 'checked' : '';
$cur = function_exists('cur_sym') ? cur_sym() : '₹';
?>
<style>
  .rq-modebar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:6px 0 16px}
  .rq-seg{display:inline-flex;border:1px solid var(--line,#e5e7eb);border-radius:9px;overflow:hidden;background:var(--card,#fff)}
  .rq-seg button{border:0;background:transparent;padding:8px 16px;font-size:13px;font-weight:600;color:var(--muted,#656e7a);cursor:pointer}
  .rq-seg button.on{background:var(--brand,#1e40af);color:#fff}
  .rq-modebar .hint{font-size:12.5px;color:var(--muted,#656e7a)}
  .rq-sec{border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:4px 16px 16px;margin:14px 0;background:var(--card,#fff)}
  .rq-sec > h3{font-size:13.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted,#656e7a);margin:14px 0 4px;display:flex;align-items:center;gap:8px}
  .rq-sec > h3 .num{font-family:ui-monospace,monospace;font-size:11px;background:var(--soft,#eef2f7);border-radius:20px;padding:1px 8px;color:var(--brand,#1e40af)}
  .rq-chk{display:flex;flex-wrap:wrap;gap:8px 18px}
  .rq-chk label{display:inline-flex;align-items:center;gap:7px;font-size:13.5px;font-weight:500;color:var(--ink,#1f2937)}
  .rq-chk input{width:16px;height:16px}
  /* Advanced-only sections/fields hidden until the Advanced mode is on */
  form.rq-simple .rq-adv{display:none}
  .rq-calc{background:var(--soft,#f5f8fc);border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:14px 16px;margin-top:6px}
  .rq-calc .row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
  .rq-calc .c{text-align:left} .rq-calc .c .l{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a)}
  .rq-calc .c .n{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums} .rq-calc .c .n.good{color:var(--green,#16a34a)} .rq-calc .c .n.bad{color:var(--red,#dc2626)}
  .rq-calc .note{font-size:12px;color:var(--muted,#656e7a);margin-top:8px}
  @media(max-width:720px){ .rq-calc .row{grid-template-columns:repeat(2,1fr)} }
</style>

<div class="crumbs"><a href="/">Home</a> › <a href="/requisitions"><?= e(TP('requisition')) ?></a> › <?= $req ? 'Edit' : 'New' ?></div>
<h1><?= $req ? 'Edit requirement '.e($req['req_code']) : 'New requirement' ?></h1>
<p class="sub">Approved positions to recruit against. Start in <b>Simple</b> for an ordinary hire; switch to <b>Advanced</b> for full technical, deployment and commercial detail.</p>

<form method="post" action="<?= $req ? '/requisition-edit?id='.(int)$req['id'] : '/requisition-new' ?>" class="rq-simple" id="rqForm">
  <div class="rq-modebar">
    <span class="rq-seg"><button type="button" class="on" data-mode="simple">Simple</button><button type="button" data-mode="advanced">Advanced</button></span>
    <span class="hint">Simple asks only the essentials. Advanced reveals discipline, deployment, selection, compliance and commercials.</span>
  </div>

  <?php // §15 — paste a requirement, let AI extract the fields (human always reviews).
  if (function_exists('ai_enabled') && ai_enabled()): ?>
  <div class="rq-sec" style="border-color:#c7d2fe;background:#eef2ff">
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
        form.classList.remove('rq-simple'); var segs=form.querySelectorAll('.rq-seg button'); for(var j=0;j<segs.length;j++){ segs[j].classList.toggle('on', segs[j].dataset.mode==='advanced'); }
        msg.innerHTML='<b style="color:#3730a3">AI filled '+n+' field(s)</b> — please review every field before saving.';
      }).catch(function(){ go.disabled=false; msg.textContent='Network error — try again.'; });
    });
  })();
  </script>
  <?php endif; ?>

  <!-- ===== 1 · Client & position ===== -->
  <div class="rq-sec">
    <h3><span class="num">1</span> Client &amp; position</h3>
    <div class="form-grid">
      <div class="ff"><label>Type *</label><select class="form-control" name="req_type" id="rq_type"><?php foreach (lk_options_or('requisition_type', REQ_TYPES) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('req_type',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff rq-adv" id="rq_out" style="<?= (($r['req_type'] ?? '')==='REPLACEMENT')?'':'display:none' ?>"><label>Replacing (engineer who left)</label>
        <select class="form-control searchable" name="outgoing_inspector_id"><option value="">—</option><?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= $sel('outgoing_inspector_id',$i['id']) ?>><?= e($i['name']) ?><?= $i['emp_code']?' ('.e($i['emp_code']).')':'' ?></option><?php endforeach; ?></select></div>
      <div class="ff rq-adv"><label>Client</label><select class="form-control searchable" name="client_id"><option value="">—</option><?php foreach (($clients ?? []) as $cl): ?><option value="<?= (int)$cl['id'] ?>" <?= $sel('client_id',$cl['id']) ?>><?= e($cl['display_name'] ?: $cl['legal_name']) ?></option><?php endforeach; ?></select></div>
      <div class="ff rq-adv"><label>Client contact</label><input class="form-control" name="contact_name" value="<?= $v('contact_name') ?>" placeholder="Name at the client"></div>
      <div class="ff rq-adv"><label>Contact email</label><input class="form-control" type="email" name="contact_email" value="<?= $v('contact_email') ?>"></div>
      <div class="ff rq-adv"><label>Contact phone</label><input class="form-control" name="contact_phone" value="<?= $v('contact_phone') ?>"></div>
      <div class="ff rq-adv"><label>Contract / PO ref</label><input class="form-control" name="contract_ref" value="<?= $v('contract_ref') ?>"></div>
      <div class="ff"><label>Office</label><select class="form-control searchable" name="office_id"><option value="">—</option><?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $sel('office_id',$o['id']) ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Responsible 1 — Recruiter</label><select class="form-control searchable" name="recruiter_id"><option value="">—</option><?php foreach (($rccUsers ?? []) as $uid=>$un): ?><option value="<?= (int)$uid ?>" <?= $sel('recruiter_id',$uid) ?>><?= e($un) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Responsible 2 — Reporting manager</label><select class="form-control searchable" name="manager_id"><option value="">—</option><?php foreach (($rccUsers ?? []) as $uid=>$un): ?><option value="<?= (int)$uid ?>" <?= $sel('manager_id',$uid) ?>><?= e($un) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Department</label><select class="form-control searchable" name="department"><option value="">—</option><?php foreach (($rccDepts ?? []) as $dk=>$dv): ?><option value="<?= e($dk) ?>" <?= $sel('department',$dk) ?>><?= e($dv) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label><?= e(T("sbu")) ?></label><select class="form-control" name="sbu"><option value="">—</option><?php foreach (OPS_SBUS as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('sbu',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Designation / position *</label><select class="form-control searchable" name="designation"><option value="">—</option><?php foreach (lk_options_or('designation', DESIGNATIONS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('designation',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>How many? *</label><input class="form-control" type="number" min="1" step="1" name="quantity" id="rq_qty" value="<?= e($r['quantity'] ?? '1') ?>"></div>
      <div class="ff"><label>Project / site</label><input class="form-control" name="project_site" value="<?= $v('project_site') ?>" placeholder="Client works / project"></div>
      <div class="ff rq-adv"><label>Discipline</label><input class="form-control" name="discipline" value="<?= $v('discipline') ?>" placeholder="e.g. Welding, NDT, Coating"></div>
      <div class="ff rq-adv"><label>Category / sub-category</label><input class="form-control" name="category" value="<?= $v('category') ?>" placeholder="e.g. Static equipment"></div>
      <div class="ff rq-adv"><label>Qualification</label><input class="form-control" name="qualification" value="<?= $v('qualification') ?>" placeholder="e.g. B.E. Mechanical"></div>
      <div class="ff rq-adv"><label>Skills / certifications</label><input class="form-control" name="skills" value="<?= $v('skills') ?>" placeholder="e.g. CSWIP 3.1, NDT UT-II"></div>
      <div class="ff rq-adv"><label>Experience (min years)</label><input class="form-control" type="number" step="0.5" name="experience_min" value="<?= $v('experience_min') ?>"></div>
      <div class="ff rq-adv"><label>Relevant experience</label><input class="form-control" name="relevant_experience" value="<?= $v('relevant_experience') ?>" placeholder="in the required scope"></div>
    </div>
  </div>

  <!-- ===== 2 · Deployment (advanced) ===== -->
  <div class="rq-sec rq-adv">
    <h3><span class="num">2</span> Deployment — where &amp; when</h3>
    <div class="form-grid">
      <div class="ff"><label>Work model</label><select class="form-control" name="work_model"><option value="">—</option><?php foreach (REQ_WORK_MODELS as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('work_model',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Deployment location</label><input class="form-control" name="deploy_location" value="<?= $v('deploy_location') ?>"></div>
      <div class="ff"><label>Start date</label><input class="form-control" type="date" name="start_date" id="rq_start" value="<?= $v('start_date') ?>"></div>
      <div class="ff"><label>End date</label><input class="form-control" type="date" name="end_date" id="rq_end" value="<?= $v('end_date') ?>"></div>
      <div class="ff"><label>Duration (months)</label><input class="form-control" type="number" step="0.5" name="duration_months" id="rq_months" value="<?= e(($r['duration_months'] ?? 0) ?: '') ?>" placeholder="auto from dates"></div>
      <div class="ff"><label>Duty hours</label><input class="form-control" name="duty_hours" value="<?= $v('duty_hours') ?>" placeholder="e.g. 8 hrs / 6 days"></div>
      <div class="ff"><label>Shift</label><select class="form-control" name="shift"><option value="">—</option><?php foreach (REQ_SHIFTS as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('shift',$k) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Other allowances</label><input class="form-control" name="other_allowances" value="<?= $v('other_allowances') ?>"></div>
    </div>
    <h3 style="margin-top:6px">Provided by us</h3>
    <div class="rq-chk">
      <label><input type="checkbox" name="prov_travel" value="1" <?= $chk('prov_travel') ?>> Travel</label>
      <label><input type="checkbox" name="prov_accommodation" value="1" <?= $chk('prov_accommodation') ?>> Accommodation</label>
      <label><input type="checkbox" name="prov_food" value="1" <?= $chk('prov_food') ?>> Food</label>
    </div>
  </div>

  <!-- ===== 3 · Selection & compliance (advanced) ===== -->
  <div class="rq-sec rq-adv">
    <h3><span class="num">3</span> Selection &amp; compliance</h3>
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
  <div class="rq-sec">
    <h3><span class="num">4</span> Commercial</h3>
    <div class="form-grid">
      <div class="ff"><label>Billing rate (<?= e($cur) ?>)</label><input class="form-control" type="number" step="0.01" name="billing_rate" id="rq_rate" value="<?= e(($r['billing_rate'] ?? 0) ?: '') ?>"></div>
      <div class="ff"><label>Rate basis</label><select class="form-control" name="rate_basis" id="rq_basis"><?php foreach (REQ_RATE_BASIS as $k=>$val): ?><option value="<?= e($k) ?>" <?= $sel('rate_basis',$k ?: 'MONTHLY') ?: ($k==='MONTHLY' && empty($r['rate_basis'])?'selected':'') ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Est. cost / person / month (<?= e($cur) ?>)</label><input class="form-control" type="number" step="0.01" name="budgeted_cost" id="rq_cost" value="<?= e(($r['budgeted_cost'] ?? 0) ?: '') ?>"></div>
      <div class="ff rq-adv"><label>Target margin (%)</label><input class="form-control" type="number" step="0.1" name="target_margin" value="<?= e(($r['target_margin'] ?? 0) ?: '') ?>"></div>
      <div class="ff rq-adv"><label>Negotiation floor (<?= e($cur) ?>)</label><input class="form-control" type="number" step="0.01" name="negotiation_floor" value="<?= e(($r['negotiation_floor'] ?? 0) ?: '') ?>"></div>
    </div>
    <div class="rq-calc" id="rq_calc">
      <div class="row">
        <div class="c"><div class="l">Expected revenue</div><div class="n" id="cx_rev">—</div></div>
        <div class="c"><div class="l">Expected cost</div><div class="n" id="cx_cost">—</div></div>
        <div class="c"><div class="l">Expected profit</div><div class="n" id="cx_prof">—</div></div>
        <div class="c"><div class="l">Margin</div><div class="n" id="cx_marg">—</div></div>
      </div>
      <div class="note" id="cx_note">Enter quantity, billing rate, basis, cost and duration to preview the commercials. Recomputed and stored on save.</div>
    </div>
  </div>

  <!-- ===== 5 · Approval ===== -->
  <div class="rq-sec">
    <h3><span class="num">5</span> Approval &amp; status</h3>
    <div class="form-grid">
      <div class="ff"><label>Approval reference *</label><input class="form-control" name="approval_ref" value="<?= $v('approval_ref') ?>" placeholder="e.g. HR-APP-2026-014"></div>
      <div class="ff"><label>Approved by</label><input class="form-control" name="approved_by" value="<?= $v('approved_by') ?>"></div>
      <div class="ff"><label>Approval date</label><input class="form-control" type="date" name="approval_date" value="<?= $v('approval_date') ?>"></div>
      <div class="ff"><label>Status</label><select class="form-control" name="status"><?php foreach (lk_options_or('requisition_status', REQ_STATUS) as $k=>$val): ?><option value="<?= e($k) ?>" <?= ($req ? $sel('status',$k) : ($k==='OPEN'?'selected':'')) ?>><?= e($val) ?></option><?php endforeach; ?></select></div>
      <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= $v('notes') ?>"></div>
    </div>
    <?php if (function_exists('custom_fields_for') && custom_fields_for('requisition')): ?>
      <h3>More details</h3>
      <div class="form-grid"><?php render_custom_fields('requisition', $cfvals ?? []); ?></div>
    <?php endif; ?>
  </div>

  <div style="margin-top:8px"><button class="btn" type="submit">Save requirement</button> <a class="btn secondary" href="/requisitions">Cancel</a></div>
</form>

<script>(function(){
  var form = document.getElementById('rqForm');
  // Simple / Advanced toggle (remembers choice for the session).
  var seg = form.querySelector('.rq-seg');
  function setMode(m){ form.classList.toggle('rq-simple', m!=='advanced');
    seg.querySelectorAll('button').forEach(function(b){ b.classList.toggle('on', b.dataset.mode===m); });
    try{ sessionStorage.setItem('rqMode', m); }catch(e){} }
  seg.addEventListener('click', function(e){ var b=e.target.closest('button'); if(b) setMode(b.dataset.mode); });
  var saved; try{ saved = sessionStorage.getItem('rqMode'); }catch(e){}
  // Open in Advanced automatically when editing a requirement that already has advanced data.
  var hasAdv = <?= (!empty($r['quantity']) && (int)($r['quantity']??1) > 1) || !empty($r['work_model']) || !empty($r['billing_rate']) || !empty($r['discipline']) ? 'true':'false' ?>;
  setMode(saved || (hasAdv ? 'advanced' : 'simple'));

  // Replacement-only "replacing" field.
  var t=document.getElementById('rq_type'), o=document.getElementById('rq_out');
  function so(){ if(o) o.style.display=(t.value==='REPLACEMENT' && !form.classList.contains('rq-simple'))?'':'none'; }
  if(t){ t.addEventListener('change',so); }

  // Live commercial preview (mirrors req_commercials() on the server).
  var qty=document.getElementById('rq_qty'), rate=document.getElementById('rq_rate'),
      basis=document.getElementById('rq_basis'), cost=document.getElementById('rq_cost'),
      start=document.getElementById('rq_start'), end=document.getElementById('rq_end'), months=document.getElementById('rq_months');
  var sym='<?= e($cur) ?>';
  function fmt(n){ return sym+' '+Math.round(n).toLocaleString('en-IN'); }
  function months_(){ var m=parseFloat(months.value)||0; if(m>0) return m;
    if(start.value && end.value){ var d=(new Date(end.value)-new Date(start.value))/86400000; if(d>=0) return Math.round(d/30.4*100)/100; } return 0; }
  function calc(){
    var q=Math.max(1,parseInt(qty.value)||1), r=parseFloat(rate.value)||0, c=parseFloat(cost.value)||0, b=basis.value, m=months_();
    var units=(b==='MANDAY'||b==='DAILY')?Math.round(m*22):m;
    var rev=(b==='FIXED')?q*r:q*r*Math.max(units,0);
    var ct=q*c*Math.max(m,0); var pf=rev-ct; var mg=rev>0?(pf/rev*100):0;
    document.getElementById('cx_rev').textContent=rev?fmt(rev):'—';
    document.getElementById('cx_cost').textContent=ct?fmt(ct):'—';
    var pe=document.getElementById('cx_prof'); pe.textContent=(rev||ct)?fmt(pf):'—'; pe.className='n '+(pf>=0?'good':'bad');
    var me=document.getElementById('cx_marg'); me.textContent=rev?mg.toFixed(1)+'%':'—'; me.className='n '+(mg>=0?'good':'bad');
    var note=document.getElementById('cx_note');
    if(rev||ct){ note.textContent=q+' × '+fmt(r)+' ('+(basis.options[basis.selectedIndex].text)+')'+(b==='FIXED'?'':' × '+ (units||0) +' unit(s)')+' over '+(m||0)+' month(s).'; }
  }
  [qty,rate,basis,cost,start,end,months].forEach(function(el){ if(el){ el.addEventListener('input',calc); el.addEventListener('change',calc);} });
  calc();
})();</script>
