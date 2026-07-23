(function () {
  "use strict";

  // ---- Live GSTIN -> PAN + State autofill on the partner form ----
  var STATES = {"01":"Jammu & Kashmir","02":"Himachal Pradesh","03":"Punjab","04":"Chandigarh","05":"Uttarakhand","06":"Haryana","07":"Delhi","08":"Rajasthan","09":"Uttar Pradesh","10":"Bihar","11":"Sikkim","12":"Arunachal Pradesh","13":"Nagaland","14":"Manipur","15":"Mizoram","16":"Tripura","17":"Meghalaya","18":"Assam","19":"West Bengal","20":"Jharkhand","21":"Odisha","22":"Chhattisgarh","23":"Madhya Pradesh","24":"Gujarat","25":"Daman & Diu","26":"Dadra & Nagar Haveli","27":"Maharashtra","28":"Andhra Pradesh (Old)","29":"Karnataka","30":"Goa","31":"Lakshadweep","32":"Kerala","33":"Tamil Nadu","34":"Puducherry","35":"Andaman & Nicobar","36":"Telangana","37":"Andhra Pradesh","38":"Ladakh"};

  function gstAutofill() {
    var gstin = document.querySelector('input[name="gstin"]');
    var panOut = document.getElementById('pan_display');
    var stateOut = document.getElementById('state_display');
    if (!gstin) return;
    function apply() {
      var g = (gstin.value || '').toUpperCase().replace(/\s+/g, '');
      var pan = '', state = '';
      if (g.length >= 12) {
        var p = g.substring(2, 12);
        if (/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(p)) pan = p;
      }
      if (g.length >= 2) state = STATES[g.substring(0, 2)] || '';
      if (panOut) panOut.value = pan;
      if (stateOut) stateOut.value = state;
    }
    gstin.addEventListener('input', apply);
    gstin.addEventListener('blur', function () { gstin.value = gstin.value.toUpperCase().replace(/\s+/g, ''); });
    apply();
  }

  // ---- Searchable dropdowns (type to filter) ----
  function enhanceSelect(select) {
    if (select.dataset.enh === '1' || select.multiple) return;
    select.dataset.enh = '1';
    var wrap = document.createElement('div'); wrap.className = 'ss-wrap';
    var input = document.createElement('input'); input.type = 'text'; input.className = 'form-control'; input.autocomplete = 'off';
    var list = document.createElement('div'); list.className = 'ss-list'; list.style.display = 'none';
    select.style.display = 'none';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(input); wrap.appendChild(list); wrap.appendChild(select);
    function cur() { var o = select.options[select.selectedIndex]; return o ? o.textContent.trim() : ''; }
    input.value = cur();
    function build(f) {
      list.innerHTML = ''; f = (f || '').toLowerCase(); var n = 0;
      Array.prototype.forEach.call(select.options, function (opt) {
        var label = opt.textContent.trim();
        if (f && label.toLowerCase().indexOf(f) === -1) return;
        var it = document.createElement('div'); it.className = 'ss-item'; it.textContent = label || '—';
        it.addEventListener('mousedown', function (e) { e.preventDefault(); select.value = opt.value; input.value = label; list.style.display = 'none'; });
        list.appendChild(it); n++;
      });
      if (!n) { var d = document.createElement('div'); d.className = 'ss-item ss-none'; d.textContent = 'No matches'; list.appendChild(d); }
    }
    input.addEventListener('focus', function () { input.select(); build(''); list.style.display = 'block'; });
    input.addEventListener('input', function () { build(input.value); list.style.display = 'block'; });
    input.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; input.value = cur(); }, 150); });
  }

  // ---- Cascading (dependent) master-list dropdowns ----
  // A .cascade block holds one <select class="cascade-sel" data-level> per level.
  // When a parent select changes, the next level repopulates with only the
  // values whose parent matches the chosen value; deeper levels reset.
  function initCascades() {
    var data = window.LKDATA || {};
    Array.prototype.forEach.call(document.querySelectorAll('.cascade'), function (block) {
      var field = block.getAttribute('data-field');
      var meta = data[field];
      if (!meta) return;
      var sels = block.querySelectorAll('select.cascade-sel');
      function fill(level) {
        var sel = sels[level];
        if (!sel) return;
        var parentVal = level === 0 ? null : parseInt(sels[level - 1].value || '0', 10) || null;
        var keep = sel.value;
        // wipe options except the placeholder (first)
        while (sel.options.length > 1) sel.remove(1);
        (meta.byType[level] || []).forEach(function (o) {
          if (level > 0 && o.parent !== parentVal) return;
          var op = document.createElement('option');
          op.value = o.id; op.textContent = o.label;
          if (String(o.id) === String(keep)) op.selected = true;
          sel.appendChild(op);
        });
      }
      Array.prototype.forEach.call(sels, function (sel, i) {
        sel.addEventListener('change', function () {
          for (var d = i + 1; d < sels.length; d++) { sels[d].value = ''; fill(d); }
        });
      });
    });
  }

  // Set a select's value, adding the option if needed, and refresh a searchable wrapper.
  function setSelectValue(select, value, label) {
    if (!select) return;
    var found = false;
    Array.prototype.forEach.call(select.options, function (o) { if (o.value == value) found = true; });
    if (!found) { var op = document.createElement('option'); op.value = value; op.textContent = label; select.appendChild(op); }
    select.value = value;
    // if wrapped by the searchable enhancer, update its visible input text
    if (select.dataset.enh === '1' && select.parentNode && select.parentNode.className === 'ss-wrap') {
      var inp = select.parentNode.querySelector('input.form-control');
      if (inp) inp.value = label;
    }
    select.dispatchEvent(new Event('change'));
  }

  // ---- Activity dropdown linked to the chosen SBU (SBU → Activity) ----
  function initActivity() {
    var sbu = document.getElementById('sbu_sel');
    var act = document.getElementById('activity_sel');
    if (!sbu || !act || !window.ACTIVITY) return;
    function fill(keepId) {
      var code = sbu.value;
      var opts = window.ACTIVITY[code] || [];
      act.innerHTML = '<option value="">' + (code ? 'Select activity…' : '— pick SBU first —') + '</option>';
      opts.forEach(function (o) {
        var op = document.createElement('option'); op.value = o.id; op.textContent = o.label;
        if (String(o.id) === String(keepId)) op.selected = true;
        act.appendChild(op);
      });
    }
    var initial = act.value;
    fill(initial);
    sbu.addEventListener('change', function () { fill(''); });
    window._activityFill = fill; // so quick-add can refresh after adding
  }

  // ---- Call: deputation-site + PO/line-item pickers, and "Other" type ----
  function fillSelect(sel, rows, keep) {
    if (!sel) return;
    sel.innerHTML = '<option value="">—</option>';
    rows.forEach(function (r) { var o = document.createElement('option'); o.value = r.id; o.textContent = r.label; if (String(r.id) === String(keep)) o.selected = true; sel.appendChild(o); });
  }
  function initCallLinks() {
    var client = document.getElementById('client_sel');
    var insp = document.getElementById('insp_sel');
    var siteFf = document.getElementById('site_ff');
    var site = document.getElementById('site_sel');
    var po = document.getElementById('po_sel');
    var poLine = document.getElementById('po_line_sel');
    var other = document.getElementById('insp_other');
    if (!client && !insp) return;
    function toggleOther() { if (other && insp) other.style.display = insp.value === 'OTHER' ? 'block' : 'none'; }
    function toggleSite() { if (siteFf && insp) siteFf.style.display = insp.value === 'DEPUTATION' ? 'block' : 'none'; }
    if (insp) insp.addEventListener('change', function () { toggleOther(); toggleSite(); });
    toggleOther(); toggleSite();
    function loadClientLinks() {
      var id = client ? client.value : '';
      if (!id) { fillSelect(site, [], ''); fillSelect(po, [], ''); fillSelect(poLine, [], ''); return; }
      if (site) fetch('/partner-sites?id=' + encodeURIComponent(id)).then(function (r) { return r.json(); }).then(function (rows) { fillSelect(site, rows, site.value); }).catch(function () {});
      if (po) fetch('/partner-pos?id=' + encodeURIComponent(id)).then(function (r) { return r.json(); }).then(function (rows) { fillSelect(po, rows, po.value); }).catch(function () {});
    }
    if (client) client.addEventListener('change', loadClientLinks);
    if (po && poLine) po.addEventListener('change', function () {
      if (!po.value) { fillSelect(poLine, [], ''); return; }
      fetch('/po-lines?id=' + encodeURIComponent(po.value)).then(function (r) { return r.json(); }).then(function (rows) { fillSelect(poLine, rows, poLine.value); }).catch(function () {});
    });
  }

  // ---- Quick-add ("+ Add new") modal on the New Call form ----
  function initQuickAdd() {
    var back = document.getElementById('qa_back');
    if (!back) return;
    var kind = '', targetId = '';
    var byId = function (id) { return document.getElementById(id); };
    function show(sel) { Array.prototype.forEach.call(document.querySelectorAll(sel), function (n) { n.style.display = 'block'; }); }
    function hideAll() { Array.prototype.forEach.call(document.querySelectorAll('.qa-field'), function (n) { n.style.display = 'none'; }); }
    function open(k) {
      kind = k; hideAll(); byId('qa_err').style.display = 'none';
      byId('qa_name').value = ''; byId('qa_gstin').value = '';
      var titles = { client: 'Add Client', vendor: 'Add Vendor', office: 'Add Executing office', product: 'Add Product category', activity: 'Add Activity code', agency: 'Add Agency (sub-con / HR)' };
      byId('qa_title').textContent = titles[k] || 'Add';
      targetId = { client: 'client_sel', vendor: 'vendor_sel', office: 'exec_sel', product: 'product_sel', activity: 'activity_sel', agency: 'agency_sel' }[k];
      if (k === 'client' || k === 'vendor') { show('.qa-cv'); if (byId('qa_both')) byId('qa_both').checked = false; }
      if (k === 'office') show('.qa-office');
      if (k === 'activity') show('.qa-activity');
      back.style.display = 'flex'; byId('qa_name').focus();
    }
    function close() { back.style.display = 'none'; }
    Array.prototype.forEach.call(document.querySelectorAll('.addlink'), function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); open(a.getAttribute('data-qa')); });
    });
    byId('qa_cancel').addEventListener('click', close);
    back.addEventListener('click', function (e) { if (e.target === back) close(); });
    byId('qa_save').addEventListener('click', function () {
      var name = byId('qa_name').value.trim();
      if (!name) { byId('qa_err').textContent = 'Enter a name.'; byId('qa_err').style.display = 'block'; return; }
      var k = kind;
      var body = new URLSearchParams(); body.append('name', name);
      if (k === 'client' || k === 'vendor') {
        body.append('gstin', byId('qa_gstin').value);
        if (byId('qa_both') && byId('qa_both').checked) k = 'both';
      }
      if (k === 'office') {
        body.append('code', byId('qa_code').value); body.append('city', byId('qa_city').value);
        body.append('coordinator_name', byId('qa_cname').value); body.append('coordinator_email', byId('qa_cemail').value);
        body.append('manager_name', byId('qa_mname').value); body.append('manager_email', byId('qa_memail').value);
      }
      if (k === 'activity') { var s = byId('sbu_sel'); if (!s || !s.value) { byId('qa_err').textContent = 'Pick an SBU on the form first.'; byId('qa_err').style.display = 'block'; return; } body.append('sbu', s.value); }
      fetch('/quick-add?kind=' + encodeURIComponent(k), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok) { byId('qa_err').textContent = res.error || 'Could not add.'; byId('qa_err').style.display = 'block'; return; }
          if (k === 'both') {
            setSelectValue(byId('client_sel'), res.id, res.label);
            setSelectValue(byId('vendor_sel'), res.id, res.label);
          } else if (k === 'activity') {
            var act = byId('activity_sel');
            var op = document.createElement('option'); op.value = res.id; op.textContent = res.label; op.selected = true; act.appendChild(op);
          } else {
            setSelectValue(byId(targetId), res.id, res.label);
          }
          close();
        })
        .catch(function () { byId('qa_err').textContent = 'Network error.'; byId('qa_err').style.display = 'block'; });
    });
  }

  // ---- Reporting frequency: show the "every N days" box only for Custom ----
  function initCustomFreq() {
    var sel = document.getElementById('freq_sel');
    var wrap = document.getElementById('custom_days_wrap');
    if (!sel || !wrap) return;
    function toggle() { wrap.style.display = sel.value === 'CUSTOM' ? '' : 'none'; }
    sel.addEventListener('change', toggle);
    toggle();
  }

  // ---- Narrow Type-of-inspection to the selected client's configured types ----
  function initClientInspection() {
    var client = document.getElementById('client_sel');
    var insp = document.getElementById('insp_sel');
    if (!client || !insp || !window.INSPTYPES) return;
    var all = window.INSPTYPES;
    function rebuild(codes) {
      var keep = insp.value;
      var use = (codes && codes.length) ? codes : Object.keys(all);
      insp.innerHTML = '<option value="">—</option>';
      use.forEach(function (c) {
        if (!all[c]) return;
        var op = document.createElement('option'); op.value = c; op.textContent = all[c];
        if (c === keep) op.selected = true;
        insp.appendChild(op);
      });
      // reflect into the searchable wrapper, if any
      if (insp.dataset.enh === '1' && insp.parentNode && insp.parentNode.className === 'ss-wrap') {
        var inpEl = insp.parentNode.querySelector('input.form-control');
        if (inpEl) inpEl.value = insp.options[insp.selectedIndex] ? insp.options[insp.selectedIndex].textContent : '';
      }
    }
    function load() {
      var id = client.value;
      if (!id) { rebuild(null); return; }
      fetch('/partner-meta?id=' + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (res) { rebuild(res.inspection_types || []); })
        .catch(function () { rebuild(null); });
    }
    client.addEventListener('change', load);
  }

  // ---- Inspector: skills checkboxes driven by the chosen trade ----
  function initTradeSkills() {
    var trade = document.getElementById('trade_sel');
    var box = document.getElementById('skills_box');
    if (!trade || !box || !window.SKILLS) return;
    var selected = window.SKILLS_SELECTED || [];
    function render() {
      var tid = trade.value;
      var list = (tid && window.SKILLS[tid]) ? window.SKILLS[tid] : [];
      if (!list.length) { box.innerHTML = '<span class="muted">' + (tid ? 'No skills under this trade yet — add them under Skill.' : 'Pick a trade to see its skills.') + '</span>'; return; }
      var html = '<div class="checkgrid">';
      list.forEach(function (s) {
        var on = selected.indexOf(s.id) !== -1 ? 'checked' : '';
        html += '<label class="chk"><input type="checkbox" name="skill_ids[]" value="' + s.id + '" ' + on + '> ' + s.label + '</label>';
      });
      html += '</div>';
      box.innerHTML = html;
    }
    trade.addEventListener('change', function () { selected = []; render(); });
    render();
  }

  // ---- Auto-fill Display name from Legal name (until user edits it) ----
  function initDisplayName() {
    var legal = document.querySelector('input[name="legal_name"]');
    var disp = document.querySelector('input[name="display_name"]');
    if (!legal || !disp) return;
    var touched = disp.value.trim() !== '';
    disp.addEventListener('input', function () { touched = true; });
    legal.addEventListener('input', function () { if (!touched) disp.value = legal.value; });
  }

  // ---- Make credit mandatory (visibly) when a call is forwarded to a branch ----
  function initForwardCredit() {
    var exec = document.getElementById('exec_sel');
    var credit = document.querySelector('input[name="expected_credit"]');
    if (!exec || !credit) return;
    function sync() {
      var on = exec.value !== '';
      credit.required = on;
      var lbl = credit.closest('.ff') && credit.closest('.ff').querySelector('label');
      if (lbl && on && lbl.textContent.indexOf('★') === -1) lbl.innerHTML = lbl.innerHTML + ' <span style="color:#c0392b">★ required</span>';
      if (on) { credit.style.borderColor = '#F37021'; }
    }
    exec.addEventListener('change', sync); sync();
  }

  // ---- "Other (add new)…" on a dropdown reveals a text box ----
  function initOtherNew() {
    Array.prototype.forEach.call(document.querySelectorAll('input[data-newfor]'), function (inp) {
      var sel = document.querySelector('[name="' + inp.getAttribute('data-newfor') + '"]');
      if (!sel) return;
      function sync() { inp.style.display = (sel.value === '__new__') ? 'block' : 'none'; if (sel.value !== '__new__') inp.value = ''; }
      sel.addEventListener('change', sync); sync();
    });
  }

  // ---- Sub-contractor ⇒ also a Vendor (manpower supplier) ----
  function initSubconVendor() {
    var sc = document.getElementById('is_subcon');
    var ven = document.getElementById('is_vendor');
    if (!sc || !ven) return;
    sc.addEventListener('change', function () { if (sc.checked) ven.checked = true; });
  }

  // ---- Registration: number auto-fills from the company's GSTIN/PAN/TAN/CIN ----
  function initRegAutofill() {
    var doc = document.getElementById('reg_doc');
    var num = document.getElementById('reg_number');
    if (!doc || !num || !window.REGDATA) return;
    function sync() { var v = window.REGDATA[doc.value]; if (v && !num.value) num.value = v; }
    doc.addEventListener('change', function () { var v = window.REGDATA[doc.value] || ''; num.value = v; });
    sync();
  }

  // ---- Trade → Skill as a single dropdown (PO line items) ----
  function initSkillSelect() {
    var trade = document.getElementById('trade_sel');
    var skill = document.getElementById('skill_sel');
    if (!trade || !skill || !window.SKILLS) return;
    trade.addEventListener('change', function () {
      var list = window.SKILLS[trade.value] || [];
      skill.innerHTML = '<option value="">' + (trade.value ? 'Select sub-category…' : '— pick trade —') + '</option>';
      list.forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.label; skill.appendChild(o); });
    });
  }

  function init() {
    gstAutofill();
    initDisplayName();
    initSkillSelect();
    initForwardCredit();
    initSubconVendor();
    initRegAutofill();
    initOtherNew();
    initCascades();
    initActivity();
    initCustomFreq();
    initClientInspection();
    initCallLinks();
    initTradeSkills();
    initQuickAdd();
    Array.prototype.forEach.call(document.querySelectorAll('select.searchable'), enhanceSelect);
  }
  if (document.readyState !== 'loading') init();
  else document.addEventListener('DOMContentLoaded', init);
})();
