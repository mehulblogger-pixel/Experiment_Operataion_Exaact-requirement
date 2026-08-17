<?php $p = $partner; $picker = $picker ?? false; ?>
<h1><?= $p ? 'Edit ' . e(partner_name($p)) : 'New business partner' ?></h1>
<p class="sub">Client Code is generated automatically. Enter GSTIN once — PAN &amp; State fill in automatically.<?php if ($picker): ?> <span class="muted">Save and this window closes, selecting the new record on the form you came from.</span><?php endif; ?></p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= $p ? '/partner-edit?id=' . (int)$p['id'] : '/partner-new' ?>" class="panel">
  <?php if ($picker): ?><input type="hidden" name="picker" value="1"><?php endif; ?>
<div data-tabs data-tabs-key="partnerform" class="form-tabs">
<section class="fs-pane" data-tab="Identity &amp; roles">
  <h3 class="tab-sub" style="margin-top:0">Identity &amp; roles</h3>
  <div class="form-grid">
    <div class="ff"><label>Legal name *</label><input class="form-control" name="legal_name" required value="<?= e($p['legal_name'] ?? '') ?>"></div>
    <div class="ff"><label>Display name <span class="muted">(auto from legal, editable)</span></label><input class="form-control" name="display_name" value="<?= e($p['display_name'] ?? '') ?>"></div>
    <?php if (!$p): $branchWord = ($defaultRole === 'is_vendor') ? 'Executing' : 'Contracting'; ?>
    <div class="ff"><label><span id="branch_word"><?= $branchWord ?></span> office <span class="muted">(drives the code)</span></label>
      <select class="form-control searchable" name="home_branch_id">
        <?php foreach (($offices ?? []) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $o['code']==='AHM'?'selected':'' ?>><?= e($o['name']) ?> (<?= e($o['code']) ?>)</option><?php endforeach; ?>
      </select><small class="muted">Code will look like <strong>AHM-<?= substr(date('Y'),2,2) ?>-NAME-00001</strong>.</small></div>
    <?php else: ?>
    <div class="ff"><label>Code</label><input class="form-control readonly-field" value="<?= e($p['code']) ?>" readonly></div>
    <?php endif; ?>
    <div class="ff ff-wide"><label>Roles</label>
      <div class="checkgrid" style="grid-template-columns:repeat(3,minmax(150px,1fr));">
        <label class="chk"><input type="checkbox" id="is_client" name="is_client" <?= ($p ? $p['is_client'] : ($defaultRole==='is_client')) ? 'checked' : '' ?>> Is a Client</label>
        <label class="chk"><input type="checkbox" id="is_vendor" name="is_vendor" <?= ($p ? $p['is_vendor'] : ($defaultRole==='is_vendor')) ? 'checked' : '' ?>> Is a Vendor</label>
        <label class="chk"><input type="checkbox" id="is_subcon" name="is_subcontractor" <?= ($p && $p['is_subcontractor']) ? 'checked' : '' ?>> Is a Sub-contractor</label>
      </div>
      <small class="muted">Tick both Client and Vendor for a company that is both. A Sub-contractor (manpower supplier) is automatically also a Vendor.</small></div>
    <?php // The office and the "type" mean different things by role: a client is
          //  contracted with, a vendor is executed at. Say which, live, as the
          //  roles are ticked — and a sub-contractor counts as a vendor. ?>
    <script>(function(){
      // #ptype_word is rendered further down the form, so wait for the whole
      // form to exist before wiring the two labels up.
      function wire(){
        var cl = document.getElementById('is_client'),
            vn = document.getElementById('is_vendor'),
            sc = document.getElementById('is_subcon'),
            bw = document.getElementById('branch_word'),
            tw = document.getElementById('ptype_word');
        if (!vn) return;
        function sync(){
          var isCl = cl && cl.checked, isVn = vn.checked || (sc && sc.checked);
          if (bw) bw.textContent = (isVn && isCl) ? 'Contracting / Executing' : (isVn ? 'Executing' : 'Contracting');
          if (tw) tw.textContent = (isVn && isCl) ? 'Partner' : (isVn ? 'Vendor' : 'Client');
        }
        [cl, vn, sc].forEach(function(el){ if (el) el.addEventListener('change', sync); });
        sync();
      }
      if (document.readyState !== 'loading') wire();
      else document.addEventListener('DOMContentLoaded', wire);
    })();</script>
    <div class="ff"><label><span id="ptype_word"><?= ($p ? ($p['is_vendor'] && !$p['is_client']) : ($defaultRole === 'is_vendor')) ? 'Vendor' : (($p && $p['is_client'] && $p['is_vendor']) ? 'Partner' : 'Client') ?></span> type</label><select class="form-control searchable" name="client_type"><option value="">—</option><?php foreach (lk_options_or('client_type', CLIENT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p && $p['client_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?><option value="__new__">➕ Other (add new)…</option></select>
      <input class="form-control" name="client_type_new" placeholder="New client type" style="display:none;margin-top:6px" data-newfor="client_type"></div>
    <div class="ff"><label>Industry</label><select class="form-control searchable" name="industry"><option value="">—</option><?php foreach (lk_options_or('industry', INDUSTRIES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p && $p['industry']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?><option value="__new__">➕ Other (add new)…</option></select>
      <input class="form-control" name="industry_new" placeholder="New industry" style="display:none;margin-top:6px" data-newfor="industry"></div>
    <div class="ff"><label>Ownership type</label><select class="form-control" name="ownership_type"><option value="">—</option><?php foreach (lk_options_or('ownership', OWNERSHIP) as $k=>$v): ?><option value="<?= $k ?>" <?= ($p && $p['ownership_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Status</label><select class="form-control" name="status"><?php foreach (lk_options_or('partner_status', STATUSES) as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['status'] ?? 'ACTIVE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  </div>
</section>
<section class="fs-pane" data-tab="Tax &amp; terms">
  <h3 class="tab-sub" style="margin-top:0">Tax, registration &amp; terms</h3>
  <div class="form-grid">
    <?php // These are commercial / billing details. They are needed only for a
          // party you invoice — a client, or a company that is both. For a
          // vendor-only record (a site you inspect at, never bill) they are
          // hidden, so nobody is asked for a GSTIN or payment terms that will
          // never be used. Ticking "Is a Client" brings them back. §WO-1 ?>
    <div class="ff ff-wide" id="commercialVendorNote" style="display:none">
      <div class="msg msg-info" style="margin:0">Commercial &amp; billing details (GSTIN, PAN, payment terms…) are <b>not needed for a vendor</b> — a vendor is a site you inspect at, not a party you invoice. Tick <b>Is a Client</b> above if you also bill them, and these fields appear.</div>
    </div>
    <div id="commercialFields" style="display:contents">
    <div class="ff"><label>GSTIN</label><input class="form-control" name="gstin" value="<?= e($p['gstin'] ?? '') ?>" placeholder="e.g. 24ADUPL3517E2ZJ"></div>
    <div class="ff"><label>PAN (auto from GSTIN)</label><input class="form-control readonly-field" id="pan_display" value="<?= e($p['pan'] ?? '') ?>" readonly></div>
    <div class="ff"><label>State (auto from GSTIN)</label><input class="form-control readonly-field" id="state_display" value="<?= e($p['state'] ?? '') ?>" readonly></div>
    <div class="ff"><label>CIN</label><input class="form-control" name="cin" value="<?= e($p['cin'] ?? '') ?>"></div>
    <div class="ff"><label>TAN</label><input class="form-control" name="tan" value="<?= e($p['tan'] ?? '') ?>"></div>
    <div class="ff"><label>MSME / Udyam</label><input class="form-control" name="msme_udyam" value="<?= e($p['msme_udyam'] ?? '') ?>"></div>
    <div class="ff"><label>Website</label><input class="form-control" name="website" value="<?= e($p['website'] ?? '') ?>"></div>
    <?php // Agreed once here, then carried onto every invoice for this customer.
          // Before this existed the invoice form asked for both on every single
          // bill, which is how one customer ends up on 30 days in March and 45
          // in April with nobody able to say which was right. ?>
    <?php // A dropdown from the Payment terms master (Masters → Payment term),
          // with the current value kept even if it is a custom one not in the list,
          // and a blank "— none —" so it is optional. Add new terms under Masters. ?>
    <div class="ff"><label>Payment terms <span id="pt_req" style="color:var(--bad);display:none">*</span><span class="muted">— carried onto their invoices</span></label>
      <?php $ptOpts = function_exists('lk_options_or') ? lk_options_or('payment_term', defined('PAYMENT_TERMS') ? PAYMENT_TERMS : []) : [];
            $ptCur = (string)($p['payment_terms'] ?? ''); ?>
      <select class="form-control searchable" name="payment_terms" id="pt_select">
        <option value="">— none —</option>
        <?php $ptSeen = false; foreach ($ptOpts as $k => $lab): $val = (string)$lab; if ($val === $ptCur) $ptSeen = true; ?>
          <option value="<?= e($val) ?>" <?= $val === $ptCur ? 'selected' : '' ?>><?= e($val) ?></option>
        <?php endforeach; ?>
        <?php if ($ptCur !== '' && !$ptSeen): ?><option value="<?= e($ptCur) ?>" selected><?= e($ptCur) ?> (current)</option><?php endif; ?>
      </select>
      <small class="muted" id="pt_hint">Add or edit options under <a href="/lookup?key=payment_term">Masters → Payment terms</a>.</small></div>
    <?php // §WO-1 — payment terms are required for a client, optional for a
          // vendor-only record. Reflect that as the roles are ticked. ?>
    <script>(function(){
      var cl=document.querySelector('input[name="is_client"]'),
          star=document.getElementById('pt_req'),
          hint=document.getElementById('pt_hint'),
          comm=document.getElementById('commercialFields'),
          note=document.getElementById('commercialVendorNote'),
          cw=<?= json_encode(strtolower(function_exists('Tl')?Tl('client'):'client')) ?>;
      if(!cl) return;
      function sync(){ var on=cl.checked;
        // Commercial / billing details belong to a client (or a both). For a
        // vendor-only record, hide the whole block and explain why.
        if(comm) comm.style.display = on ? 'contents' : 'none';
        if(note) note.style.display = on ? 'none' : '';
        if(star) star.style.display = on ? '' : 'none';
        if(hint) hint.innerHTML = on
          ? '<b>Required for a '+cw+'.</b> Add or edit options under <a href="/lookup?key=payment_term">Masters → Payment terms</a>.'
          : 'Add or edit options under <a href="/lookup?key=payment_term">Masters → Payment terms</a>. Not needed for a vendor-only record.';
      }
      cl.addEventListener('change', sync); sync();
    })();</script>
    <div class="ff"><label>Credit days <span class="muted">— sets the due date on their invoices</span></label>
      <input class="form-control" type="number" min="0" name="credit_days" value="<?= e((string)($p['credit_days'] ?? '')) ?>"
             placeholder="e.g. 45"></div>
    </div><!-- /#commercialFields -->
    <div class="ff ff-wide"><label>Description</label><input class="form-control" name="description" value="<?= e($p['description'] ?? '') ?>"></div>

    <?php // Site location for geofenced attendance — so an inspection engineer's
          // punch-in can be checked against the real site and a spoofed / fake-GPS
          // location is rejected. Optional: leave blank to keep the geofence off.
    ?>
  </div>
</section>
<section class="fs-pane" data-tab="Site &amp; scope">
  <h3 class="tab-sub" style="margin-top:0">Site, scope &amp; contract terms</h3>
  <div class="form-grid">
    <div class="ff ff-wide">
      <label>📍 Site location <span class="muted">— for geofenced attendance; stops an inspector punching in on a fake GPS. Leave blank to switch it off.</span></label>
      <div class="grid" style="grid-template-columns:repeat(3,1fr);gap:10px;margin-top:6px">
        <div><span class="lab">Latitude</span><input class="form-control" type="number" step="any" id="site_lat" name="site_lat" placeholder="e.g. 23.0225" value="<?= e((string)($p['site_lat'] ?? '')) ?>"></div>
        <div><span class="lab">Longitude</span><input class="form-control" type="number" step="any" id="site_lon" name="site_lon" placeholder="e.g. 72.5714" value="<?= e((string)($p['site_lon'] ?? '')) ?>"></div>
        <div><span class="lab">Allowed radius (metres)</span><input class="form-control" type="number" min="0" id="geofence_m" name="geofence_m" placeholder="e.g. 150" value="<?= e((string)($p['geofence_m'] ?? '') ?: '') ?>"></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
        <button type="button" class="btn secondary" id="geo_here">Use this device's current location</button>
        <input class="form-control" id="geo_paste" placeholder="…or paste a Google Maps link or “23.0225, 72.5714”" style="flex:1;min-width:220px">
        <button type="button" class="btn secondary" id="geo_paste_btn">Read coordinates</button>
        <span class="muted" id="geo_msg"></span>
      </div>
      <?php // Locate straight from the company name / address — no need to know the
            // coordinates. Fills lat/long from the map; if there is no clean match it
            // opens Google Maps so the pin can be picked and pasted above. ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
        <input class="form-control" id="geo_query" placeholder="…or find by company name / address — e.g. “Reliance, Jamnagar”" style="flex:1;min-width:220px">
        <button type="button" class="btn secondary" id="geo_find">🔎 Find coordinates</button>
      </div>
    </div>
    <script>
    (function(){
      var lat=document.getElementById('site_lat'), lon=document.getElementById('site_lon'),
          msg=document.getElementById('geo_msg');
      function say(t){ if(msg) msg.textContent=t||''; }
      var here=document.getElementById('geo_here');
      if(here) here.addEventListener('click',function(){
        if(!navigator.geolocation){ say('This browser has no location support.'); return; }
        say('Locating…');
        navigator.geolocation.getCurrentPosition(function(pos){
          lat.value=pos.coords.latitude.toFixed(7); lon.value=pos.coords.longitude.toFixed(7);
          say('Set from this device (±'+Math.round(pos.coords.accuracy)+' m). Stand at the site for the best fix.');
        }, function(){ say('Could not read location — allow it in the browser, and use HTTPS.'); }, {enableHighAccuracy:true, timeout:10000});
      });
      var pb=document.getElementById('geo_paste_btn'), pbox=document.getElementById('geo_paste');
      function parse(s){
        if(!s) return null;
        var m=s.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/) || s.match(/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/)
            || s.match(/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/);
        return m ? {la:parseFloat(m[1]), lo:parseFloat(m[2])} : null;
      }
      if(pb) pb.addEventListener('click',function(){
        var c=parse(pbox.value||'');
        if(!c){ say('Could not find coordinates in that text.'); return; }
        lat.value=c.la.toFixed(7); lon.value=c.lo.toFixed(7); say('Coordinates read.');
      });

      // Find coordinates from the company name / address. Prefills from the name
      // and State already on the form; geocodes with OpenStreetMap (no key needed),
      // and falls back to opening Google Maps when there is no clean match.
      var q=document.getElementById('geo_query'), find=document.getElementById('geo_find');
      function guess(){
        var dn=document.querySelector('[name="display_name"]'), ln=document.querySelector('[name="legal_name"]'),
            st=document.getElementById('state_display');
        var nm=((dn&&dn.value)||(ln&&ln.value)||'').trim(), s=((st&&st.value)||'').trim();
        return (nm + (s?(', '+s):'') + ((nm||s)?', India':'')).replace(/^,\s*/,'').trim();
      }
      function openMaps(query){ try{ window.open('https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(query),'_blank','noopener'); }catch(e){} }
      if(q){ q.addEventListener('focus',function(){ if(!q.value) q.value=guess(); }); }
      if(find) find.addEventListener('click',function(){
        var query=((q&&q.value)||'').trim() || guess();
        if(!query){ say('Type a company name or address to locate.'); return; }
        say('Searching the map…');
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(query),{headers:{'Accept':'application/json'}})
          .then(function(r){ return r.json(); })
          .then(function(a){
            if(a && a.length){ lat.value=parseFloat(a[0].lat).toFixed(7); lon.value=parseFloat(a[0].lon).toFixed(7);
              say('Found: '+String(a[0].display_name||query).slice(0,90)+' — check the pin is right.'); }
            else { openMaps(query); say('No exact match — opened Google Maps; drop the pin, then paste the link above and press “Read coordinates”.'); }
          })
          .catch(function(){ openMaps(query); say('Opened Google Maps — copy the pin’s link and paste it above, then “Read coordinates”.'); });
      });
    })();
    </script>

    <?php $selInsp = ($p && !empty($p['inspection_types'])) ? explode(',', $p['inspection_types']) : []; ?>
    <div class="ff ff-wide"><label>Types of inspection this client needs <span class="muted">— carried into new calls</span></label>
      <div class="checkgrid">
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="inspection_types[]" value="<?= e($k) ?>" <?= in_array($k, $selInsp, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Tick all that apply. In a new call for this client the Type-of-inspection list is narrowed to these. Manage the master under <a href="/lookup?key=inspection_type">Type of inspection</a>.</small></div>
    <?php // §man-month — what this client's contract means by a man-month. Left
          // alone it follows the company default in Settings. Set here it applies
          // to every monthly deputation for this client, and a single deputation
          // can still override it. ?>
    <div class="ff"><label>What a man-month means for this <?= e(Tl('client')) ?></label>
      <select class="form-control" name="manmonth_basis">
        <option value="">— the company default —</option>
        <?php $mmb = (string)($p['manmonth_basis'] ?? '');
              foreach (MANMONTH_BASES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $mmb === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Only matters for monthly <?= e(Tlp('job')) ?>. The default is under <a href="/settings">Settings</a>.</small></div>
    <div class="ff"><label>Minimum working days in their man-month</label>
      <input class="form-control" type="number" min="1" max="31" name="manmonth_min_days"
             value="<?= e((($p['manmonth_min_days'] ?? 0) ?: '')) ?>" placeholder="e.g. 26">
      <small class="muted">Below this the month is claimable pro-rata; above it, it is still one man-month.</small></div>
    <?php if (function_exists('render_custom_fields')) render_custom_fields('partner', $pcfvals ?? []); ?>
  </div>
</section>
</div><!-- /.form-tabs -->
  <div class="fs-actions" style="margin-top:18px;">
    <button class="btn" type="submit">Save</button>
    <a class="btn secondary" href="<?= $p ? '/partner?id=' . (int)$p['id'] : '/clients' ?>">Cancel</a>
  </div>
</form>
