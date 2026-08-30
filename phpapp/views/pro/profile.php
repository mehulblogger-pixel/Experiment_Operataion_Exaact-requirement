<?php
  // Connect K0+/K-GEO — the Universal Technical Professional Passport editor.
  // Scalar identity + a taxonomy-driven EXPERTISE drill-down (multi-discipline,
  // suggested skills/equipment/certs) + a conditional LOCATION & MOBILITY engine.
  $me = $me ?? []; $saved = $saved ?? false;
  $wt = array_filter(array_map('trim', explode(',', (string)($me['work_types'] ?? ''))));
  $wtypes = function_exists('cx_pro_work_types') ? cx_pro_work_types() : [];
  $expertise = $expertise ?? []; $tax_domains = $tax_domains ?? []; $tax_relations = $tax_relations ?? [];
  $geo_regions = $geo_regions ?? []; $geo_presets = $geo_presets ?? []; $sel_places = $sel_places ?? [];
  $relLabel = ['PRIMARY_ROLE'=>'Primary role','ADDITIONAL_ROLE'=>'Additional role','SPECIALIZATION'=>'Specialisation','SKILL'=>'Skill','EQUIPMENT'=>'Equipment','INDUSTRY'=>'Industry','CERTIFICATION'=>'Certification'];
  // group expertise by relation for a tidy display
  $byRel = [];
  foreach ($expertise as $x) $byRel[strtoupper((string)$x['relation'])][] = $x;
  $mode = strtoupper((string)($me['mobility_mode'] ?? ''));
  if ($mode === '' ) $mode = !empty($me['pan_india']) ? 'PAN_INDIA' : ((int)($me['travel_radius_km'] ?? 0) > 0 ? 'RADIUS' : 'SELECTED');
  $regions = array_filter(array_map('trim', explode(',', strtoupper((string)($me['intl_regions'] ?? '')))));
?>
<style>
  .ac{position:relative}
  .ac-list{position:absolute;left:0;right:0;top:100%;z-index:20;background:var(--card,#fff);border:1px solid var(--line,#ddd);border-radius:10px;margin-top:2px;max-height:220px;overflow:auto;display:none}
  .ac-list.open{display:block}
  .ac-list div{padding:9px 12px;cursor:pointer;font-size:14px}
  .ac-list div:hover,.ac-list div.sel{background:rgba(15,125,125,.09)}
  .xchip{display:inline-flex;align-items:center;gap:6px;background:rgba(15,125,125,.08);border:1px solid rgba(15,125,125,.25);border-radius:999px;padding:4px 6px 4px 11px;font-size:13px;margin:3px 4px 0 0}
  .xchip form{margin:0;display:inline} .xchip button{border:0;background:transparent;cursor:pointer;color:#9a2a2a;font-size:14px;line-height:1;padding:0 2px}
  .sugchip{display:inline-block;border:1px dashed var(--line,#ccc);border-radius:999px;padding:4px 11px;margin:3px 4px 0 0;font-size:13px;cursor:pointer}
  .sugchip.on{background:rgba(15,125,125,.12);border-style:solid;border-color:rgba(15,125,125,.4)}
  .relgrp{margin:0 0 8px} .relgrp .rl{font-size:12px;color:var(--muted,#667);text-transform:uppercase;letter-spacing:.04em;margin:8px 0 3px}
  .hide{display:none!important}
</style>

<h1>My passport</h1>
<p class="muted" style="margin:0 0 14px">Your complete technical identity — clients and agencies find you by it. Pick broadly, then drill down; add as many skills, roles and disciplines as apply.</p>
<?php if ($saved): ?><div class="msg ok">Saved.</div><?php endif; ?>

<?php // ---- 1. Identity (scalar) — location fields carried as hidden so a save here never wipes mobility ---- ?>
<form method="post" action="/pro/profile">
  <div class="card">
    <h2>You</h2>
    <label>Name</label><input name="name" value="<?= e($me['name'] ?? '') ?>">
    <label>Headline</label><input name="headline" value="<?= e($me['headline'] ?? '') ?>" placeholder="e.g. Senior QA/QC &amp; Welding Inspector, CSWIP 3.1">
    <label>Mobile</label><input name="mobile" value="<?= e($me['mobile'] ?? '') ?>">
    <label>Skills (free text — the structured ones are below)</label><input name="skills" value="<?= e($me['skills'] ?? '') ?>" placeholder="e.g. Welding inspection, NDT (UT/RT)">
    <label>Languages</label><input name="languages" value="<?= e($me['languages'] ?? '') ?>" placeholder="e.g. English, Hindi, Gujarati">
    <p style="margin:12px 0 0"><a href="/pro/documents" class="btn sec" style="width:100%;text-align:center">📎 Manage photo, CV &amp; certificates →</a></p>
  </div>
  <div class="card">
    <h2>How you want to work</h2>
    <div><?php foreach ($wtypes as $k => $lbl): $on = in_array($k, $wt, true); ?>
      <label class="chip"><input type="checkbox" name="work_types[]" value="<?= e($k) ?>" <?= $on?'checked':'' ?>> <?= e($lbl) ?></label>
    <?php endforeach; ?></div>
  </div>
  <div class="card">
    <h2>Availability &amp; rates</h2>
    <div class="grid2">
      <div><label>Availability</label>
        <select name="availability">
          <?php foreach (['AVAILABLE'=>'Available now','FROM'=>'Available from a date','BUSY'=>'Currently busy'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($me['availability']??'')===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?></select></div>
      <div><label>Available from</label><input type="date" name="available_from" value="<?= e($me['available_from'] ?? '') ?>"></div>
      <div><label>Notice needed (days)</label><input type="number" name="notice_days" value="<?= (int)($me['notice_days'] ?? 0) ?>"></div>
      <div><label>Per-visit rate (₹)</label><input type="number" name="per_visit_rate" value="<?= $me['per_visit_rate']?(int)$me['per_visit_rate']:'' ?>"></div>
      <div><label>Day-rate from (₹)</label><input type="number" name="day_rate_min" value="<?= $me['day_rate_min']?(int)$me['day_rate_min']:'' ?>"></div>
      <div><label>Day-rate to (₹)</label><input type="number" name="day_rate_max" value="<?= $me['day_rate_max']?(int)$me['day_rate_max']:'' ?>"></div>
    </div>
  </div>
  <div class="card">
    <h2>Job alerts</h2>
    <?php $prefs = $prefs ?? []; ?>
    <label style="display:block;margin:6px 0"><input type="checkbox" name="notify_whatsapp" value="1" <?= !empty($prefs['whatsapp']) ? 'checked' : '' ?>> WhatsApp <span class="muted">(needs your mobile)</span></label>
    <label style="display:block;margin:6px 0"><input type="checkbox" name="notify_sms" value="1" <?= !empty($prefs['sms']) ? 'checked' : '' ?>> SMS</label>
    <label style="display:block;margin:6px 0"><input type="checkbox" name="notify_email" value="1" <?= !empty($prefs['email']) ? 'checked' : '' ?>> Email</label>
  </div>
  <?php // carry location so the scalar save never blanks mobility (owned by the Location form below) ?>
  <input type="hidden" name="base_city" value="<?= e($me['base_city'] ?? '') ?>">
  <input type="hidden" name="preferred_locations" value="<?= e($me['preferred_locations'] ?? '') ?>">
  <input type="hidden" name="pan_india" value="<?= !empty($me['pan_india']) ? '1' : '0' ?>">
  <input type="hidden" name="overseas" value="<?= !empty($me['overseas']) ? '1' : '0' ?>">
  <input type="hidden" name="travel_radius_km" value="<?= (int)($me['travel_radius_km'] ?? 0) ?>">
  <button class="btn" type="submit" style="width:100%">Save identity</button>
</form>

<?php // ---- 2. EXPERTISE — taxonomy drill-down + suggestions (multi) ---- ?>
<div class="card" id="expertise">
  <h2>Technical expertise</h2>
  <p class="muted" style="margin:0 0 10px;font-size:13px">Choose broadly, then drill down. Add as many as apply — a person can be Mechanical, Welding and NDT at once. &nbsp;<a href="/pro/cv" style="font-weight:600">✨ Prefill from your CV →</a></p>

  <?php if ($expertise): foreach ($tax_relations as $rel): if (empty($byRel[$rel])) continue; ?>
    <div class="relgrp"><div class="rl"><?= e($relLabel[$rel] ?? $rel) ?></div>
      <?php foreach ($byRel[$rel] as $x): ?>
        <span class="xchip"><?= e($x['name']) ?><span class="muted" style="font-size:11px"><?= $x['competency'] ? ' · ' . e(ucfirst(strtolower($x['competency']))) : '' ?><?= (float)$x['years'] > 0 ? ' · ' . rtrim(rtrim((string)$x['years'],'0'),'.') . 'y' : '' ?></span>
          <form method="post" action="/pro/profile"><input type="hidden" name="action" value="detach_node"><input type="hidden" name="ptax_id" value="<?= (int)$x['id'] ?>"><button type="submit" title="Remove">✕</button></form>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endforeach; else: ?>
    <p class="muted" style="margin:0 0 10px">No expertise added yet — add your first below.</p>
  <?php endif; ?>

  <form method="post" action="/pro/profile" id="addExp" style="border-top:1px solid var(--line,#eee);margin-top:10px;padding-top:12px">
    <input type="hidden" name="action" value="attach_node">
    <input type="hidden" name="node_id" id="expNode" value="">
    <div class="grid2">
      <div><label>Domain</label>
        <select id="expDomain"><option value="">— choose —</option>
          <?php foreach ($tax_domains as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Then narrow it (optional)</label>
        <select id="expChild" disabled><option value="">— pick a domain first —</option></select></div>
    </div>
    <label style="margin-top:10px">Or type to find a role / skill / certification</label>
    <div class="ac"><input type="text" id="expSearch" autocomplete="off" placeholder="e.g. pressure vessel inspector, CSWIP, ultrasonic testing"><div class="ac-list" id="expSearchList"></div></div>
    <div id="expSug" style="margin-top:8px"></div>
    <div id="expChosen" class="muted" style="margin-top:10px;font-size:13px"></div>
    <div class="grid2" style="margin-top:6px">
      <div><label>Add as</label>
        <select name="relation" id="expRel">
          <?php foreach ($tax_relations as $r): ?><option value="<?= e($r) ?>"><?= e($relLabel[$r] ?? $r) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Competency (optional)</label>
        <select name="competency"><option value="">—</option>
          <?php foreach (['BEGINNER','WORKING','ADVANCED','EXPERT'] as $c): ?><option value="<?= $c ?>"><?= e(ucfirst(strtolower($c))) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <label>Years on this (optional)</label><input type="number" name="years" min="0" step="0.5" style="max-width:140px">
    <button class="btn" type="submit" id="expAdd" style="margin-top:12px" disabled>Add to my expertise</button>
  </form>
</div>

<?php // ---- 3. LOCATION & MOBILITY — conditional engine ---- ?>
<div class="card" id="mobility">
  <h2>Location &amp; mobility</h2>
  <p class="muted" style="margin:0 0 10px;font-size:13px">Your base, and where you're willing to work. The distance maths is automatic — you only set your base and how far you'll go.</p>
  <form method="post" action="/pro/profile" id="mobForm">
    <input type="hidden" name="action" value="save_mobility">
    <input type="hidden" name="base_place_id" id="baseId" value="<?= (int)($me['base_place_id'] ?? 0) ?>">
    <label>Base city</label>
    <div class="ac"><input type="text" id="baseCity" name="base_city" autocomplete="off" value="<?= e($me['base_city'] ?? '') ?>" placeholder="Start typing — e.g. Jamnagar"><div class="ac-list" id="baseList"></div></div>
    <div class="muted" id="baseMeta" style="font-size:12.5px;margin-top:4px"><?= $me['base_state'] ? e($me['base_state']) . ', ' . e($me['base_country'] ?? 'IN') : '' ?></div>

    <p style="margin:16px 0 6px;font-weight:600">Where are you willing to work?</p>
    <label class="chip" style="display:block;margin:5px 0"><input type="radio" name="mobility_mode" value="RADIUS" <?= $mode==='RADIUS'?'checked':'' ?> class="mm"> Within a travel radius of my base</label>
    <div id="blkRadius" class="<?= $mode==='RADIUS'?'':'hide' ?>" style="padding:2px 0 8px 26px">
      <label>Maximum travel distance</label>
      <select id="radiusPreset">
        <?php $cur=(int)($me['travel_radius_km']??0); $isPreset=in_array($cur,$geo_presets,true); foreach ($geo_presets as $km): ?>
          <option value="<?= $km ?>" <?= $cur===$km?'selected':'' ?>>Within <?= $km ?> km</option>
        <?php endforeach; ?>
        <option value="custom" <?= (!$isPreset && $cur>0)?'selected':'' ?>>Custom…</option>
      </select>
      <input type="number" name="travel_radius_km" id="radiusKm" min="1" max="5000" value="<?= $cur>0?$cur:'' ?>" placeholder="km" style="max-width:140px;margin-top:8px;<?= (!$isPreset && $cur>0)?'':'display:none' ?>">
    </div>
    <label class="chip" style="display:block;margin:5px 0"><input type="radio" name="mobility_mode" value="SELECTED" <?= $mode==='SELECTED'?'checked':'' ?> class="mm"> Only in selected cities / states</label>
    <div id="blkSelected" class="<?= $mode==='SELECTED'?'':'hide' ?>" style="padding:2px 0 8px 26px">
      <div class="ac"><input type="text" id="selSearch" autocomplete="off" placeholder="Add a city or state"><div class="ac-list" id="selList"></div></div>
      <div id="selChips" style="margin-top:8px">
        <?php foreach ($sel_places as $p): ?>
          <span class="xchip" data-id="<?= (int)$p['id'] ?>"><?= e($p['name']) ?><?= $p['kind']==='STATE'?' <span class="muted" style="font-size:11px">(state)</span>':'' ?><input type="hidden" name="selected_places[]" value="<?= (int)$p['id'] ?>"><button type="button" onclick="this.closest('.xchip').remove()">✕</button></span>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="chip" style="display:block;margin:5px 0"><input type="radio" name="mobility_mode" value="PAN_INDIA" <?= $mode==='PAN_INDIA'?'checked':'' ?> class="mm"> Anywhere in India (Pan-India)</label>
    <input type="hidden" name="pan_india" id="panFlag" value="<?= $mode==='PAN_INDIA'?'1':'0' ?>">

    <div style="border-top:1px solid var(--line,#eee);margin-top:12px;padding-top:12px">
      <label class="chip" style="display:block"><input type="checkbox" name="overseas" id="overseas" value="1" <?= !empty($me['overseas'])?'checked':'' ?>> Also available for international assignments</label>
      <div id="blkIntl" class="<?= !empty($me['overseas'])?'':'hide' ?>" style="padding:8px 0 0 26px">
        <?php foreach ($geo_regions as $code => $name): ?>
          <label class="chip" style="margin:3px 4px 0 0"><input type="checkbox" name="intl_regions[]" value="<?= e($code) ?>" <?= in_array($code,$regions,true)?'checked':'' ?>> <?= e($name) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn" type="submit" style="width:100%;margin-top:14px">Save location &amp; mobility</button>
  </form>
</div>

<script>
(function(){
  var J = function(u){ return fetch(u,{credentials:'same-origin'}).then(function(r){return r.json();}).catch(function(){return [];}); };
  // ---- generic autocomplete wiring ----
  function autocomplete(inp, list, url, onPick){
    var t; inp.addEventListener('input', function(){
      clearTimeout(t); var q=inp.value.trim(); if(q.length<2){ list.classList.remove('open'); list.innerHTML=''; return; }
      t=setTimeout(function(){ J(url+encodeURIComponent(q)).then(function(rows){
        list.innerHTML=''; (rows||[]).slice(0,12).forEach(function(r){
          var d=document.createElement('div'); d.textContent=r.name+(r.state? ' · '+r.state : (r.kind? ' · '+r.kind.toLowerCase() : ''));
          d.onclick=function(){ onPick(r); list.classList.remove('open'); };
          list.appendChild(d);
        });
        list.classList.toggle('open', (rows||[]).length>0);
      }); }, 160);
    });
    document.addEventListener('click', function(e){ if(!list.contains(e.target)&&e.target!==inp) list.classList.remove('open'); });
  }
  // ---- Expertise drill-down ----
  var dom=document.getElementById('expDomain'), child=document.getElementById('expChild'),
      node=document.getElementById('expNode'), chosen=document.getElementById('expChosen'),
      addBtn=document.getElementById('expAdd'), sug=document.getElementById('expSug');
  function pick(id,name,kind){ node.value=id; chosen.innerHTML='Selected: <strong>'+name+'</strong>'+(kind?' <span class="muted">('+kind.toLowerCase()+')</span>':''); addBtn.disabled=!id; }
  function loadSug(id){ sug.innerHTML=''; if(!id) return; J('/pro/tax-suggest?node='+id).then(function(rows){
      (rows||[]).forEach(function(r){ var s=document.createElement('span'); s.className='sugchip'; s.textContent=r.name;
        s.onclick=function(){ pick(r.id,r.name,r.kind); document.querySelectorAll('#expSug .sugchip').forEach(function(x){x.classList.remove('on');}); s.classList.add('on'); }; sug.appendChild(s); }); }); }
  if(dom) dom.addEventListener('change', function(){
    child.innerHTML='<option value="">— all of '+(dom.options[dom.selectedIndex].text)+' —</option>'; child.disabled=!dom.value;
    if(dom.value){ pick(dom.value, dom.options[dom.selectedIndex].text, 'domain'); loadSug(dom.value);
      J('/pro/tax-children?parent='+dom.value).then(function(rows){ (rows||[]).forEach(function(r){ var o=document.createElement('option'); o.value=r.id; o.textContent=r.name+' ('+r.kind.toLowerCase()+')'; o.dataset.kind=r.kind; child.appendChild(o); }); }); }
  });
  if(child) child.addEventListener('change', function(){ if(child.value){ pick(child.value, child.options[child.selectedIndex].text, child.options[child.selectedIndex].dataset.kind); loadSug(child.value);} });
  var es=document.getElementById('expSearch'); if(es) autocomplete(es, document.getElementById('expSearchList'), '/pro/tax-resolve?q=', function(r){ es.value=r.name; pick(r.id,r.name,r.kind); loadSug(r.id); });

  // ---- Location & mobility ----
  var baseCity=document.getElementById('baseCity');
  if(baseCity) autocomplete(baseCity, document.getElementById('baseList'), '/pro/geo?q=', function(r){
    baseCity.value=r.name; document.getElementById('baseId').value=r.id;
    document.getElementById('baseMeta').textContent=(r.state? r.state+', ':'')+(r.country||'IN');
  });
  var selSearch=document.getElementById('selSearch');
  if(selSearch) autocomplete(selSearch, document.getElementById('selList'), '/pro/geo?q=', function(r){
    var box=document.getElementById('selChips');
    if(box.querySelector('[data-id="'+r.id+'"]')) return;
    var c=document.createElement('span'); c.className='xchip'; c.dataset.id=r.id;
    c.innerHTML=r.name+(r.kind==='STATE'?' <span class="muted" style="font-size:11px">(state)</span>':'')+'<input type="hidden" name="selected_places[]" value="'+r.id+'"><button type="button">✕</button>';
    c.querySelector('button').onclick=function(){ c.remove(); }; box.appendChild(c); selSearch.value='';
  });
  // conditional blocks + Pan-India strict rule
  function refreshMode(){
    var m=(document.querySelector('.mm:checked')||{}).value||'';
    document.getElementById('blkRadius').classList.toggle('hide', m!=='RADIUS');
    document.getElementById('blkSelected').classList.toggle('hide', m!=='SELECTED');
    document.getElementById('panFlag').value = (m==='PAN_INDIA')?'1':'0';
    // strict: Pan-India disables radius + selected inputs
    var dis = (m==='PAN_INDIA');
    document.getElementById('radiusKm').disabled=dis;
    document.querySelectorAll('#blkSelected input, #selSearch').forEach(function(x){ x.disabled=dis; });
  }
  document.querySelectorAll('.mm').forEach(function(r){ r.addEventListener('change', refreshMode); });
  var rp=document.getElementById('radiusPreset'), rk=document.getElementById('radiusKm');
  if(rp) rp.addEventListener('change', function(){ if(rp.value==='custom'){ rk.style.display=''; rk.focus(); } else { rk.style.display='none'; rk.value=rp.value; } });
  var ov=document.getElementById('overseas'); if(ov) ov.addEventListener('change', function(){ document.getElementById('blkIntl').classList.toggle('hide', !ov.checked); });
  refreshMode();
})();
</script>
