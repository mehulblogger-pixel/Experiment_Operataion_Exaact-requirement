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

    // Setting select.value in script does NOT fire a change event. Without this
    // every cascade hanging off a searchable dropdown was dead: choosing a
    // client never fetched its quotations, choosing a quotation never pulled the
    // line items, and the inter-office credit panel kept describing the offices
    // as they were when the page loaded while the save validated the new ones.
    function pick(opt, label) {
      select.value = opt.value;
      input.value = label;
      list.style.display = 'none';
      select.dispatchEvent(new Event('input',  { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function items() { return list.querySelectorAll('.ss-item:not(.ss-none)'); }
    function highlight(i) {
      var all = items();
      Array.prototype.forEach.call(all, function (el) { el.classList.remove('ss-on'); });
      if (i >= 0 && all[i]) { all[i].classList.add('ss-on'); all[i].scrollIntoView({ block: 'nearest' }); }
    }
    function build(f) {
      list.innerHTML = ''; f = (f || '').toLowerCase(); var n = 0;
      Array.prototype.forEach.call(select.options, function (opt) {
        var label = opt.textContent.trim();
        if (f && label.toLowerCase().indexOf(f) === -1) return;
        var it = document.createElement('div'); it.className = 'ss-item'; it.textContent = label || '—';
        if (opt.value === select.value) it.classList.add('ss-on');
        it.addEventListener('mousedown', function (e) { e.preventDefault(); pick(opt, label); });
        list.appendChild(it); n++;
      });
      if (!n) { var d = document.createElement('div'); d.className = 'ss-item ss-none'; d.textContent = 'No matches'; list.appendChild(d); }
    }

    // Keyboard: arrows move, Enter or Space chooses, Escape closes. A dropdown
    // that can only be driven with a mouse is unusable for anyone entering a
    // long form at speed.
    input.addEventListener('keydown', function (e) {
      var open = list.style.display !== 'none';
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (!open) { build(''); list.style.display = 'block'; }
        e.preventDefault();
        var all = items(); if (!all.length) return;
        var i = -1;
        Array.prototype.forEach.call(all, function (el, k) { if (el.classList.contains('ss-on')) i = k; });
        i = e.key === 'ArrowDown' ? Math.min(i + 1, all.length - 1) : Math.max(i - 1, 0);
        highlight(i);
      } else if (e.key === 'Enter' || (e.key === ' ' && input.value === '')) {
        if (!open) return;
        var on = list.querySelector('.ss-item.ss-on');
        if (on) { e.preventDefault(); on.dispatchEvent(new MouseEvent('mousedown')); }
      } else if (e.key === 'Escape') {
        list.style.display = 'none'; input.value = cur();
      } else if (e.key === 'Tab') {
        // Tabbing away with something highlighted takes it, as a native select would.
        var sel = list.querySelector('.ss-item.ss-on');
        if (open && sel && input.value !== cur()) sel.dispatchEvent(new MouseEvent('mousedown'));
      }
    });
    // Choosing a value keeps the focus on the box (the option is taken on
    // mousedown, with the default prevented). That meant `focus` never fired
    // again, so clicking the box a second time did nothing at all and the list
    // could not be reopened without tabbing away first. Click now toggles, the
    // way a real <select> does.
    input.addEventListener('mousedown', function () {
      if (list.style.display === 'none') { build(''); list.style.display = 'block'; input.select(); }
      else { list.style.display = 'none'; }
    });
    input.addEventListener('focus', function () { input.select(); build(''); list.style.display = 'block'; });
    input.addEventListener('input', function () { build(input.value); list.style.display = 'block'; highlight(0); });
    input.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; input.value = cur(); }, 150); });
  }

  // ---- Free-text combo (suggestions, but a new value is allowed) ----------
  // For fields like product category, where the list is a helpful memory of what
  // the office has used before rather than a closed set. It replaces the native
  // <input list="..."> datalist, whose popup the browser draws itself: narrow,
  // dark on some platforms, and impossible to theme. This one is the app's own
  // panel — full field width, theme colours, and it keeps whatever is typed.
  function enhanceCombo(input) {
    if (input.dataset.enh === '1') return;
    input.dataset.enh = '1';
    var opts = [];
    var src = input.getAttribute('list');
    if (src) {
      var dl = document.getElementById(src);
      if (dl) {
        Array.prototype.forEach.call(dl.querySelectorAll('option'), function (o) {
          var v = (o.value || o.textContent || '').trim();
          if (v) opts.push(v);
        });
        input.removeAttribute('list');          // stop the native popup entirely
        dl.parentNode && dl.parentNode.removeChild(dl);
      }
    }
    var wrap = document.createElement('div'); wrap.className = 'ss-wrap';
    var list = document.createElement('div'); list.className = 'ss-list'; list.style.display = 'none';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input); wrap.appendChild(list);

    function esc(s) { return s.replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function mark(label, f) {
      if (!f) return esc(label);
      var i = label.toLowerCase().indexOf(f.toLowerCase());
      if (i < 0) return esc(label);
      return esc(label.slice(0, i)) + '<b>' + esc(label.slice(i, i + f.length)) + '</b>' + esc(label.slice(i + f.length));
    }
    function pick(v) { input.value = v; list.style.display = 'none'; input.dispatchEvent(new Event('change', {bubbles:true})); }
    function build() {
      var f = input.value.trim(), lf = f.toLowerCase(), n = 0;
      list.innerHTML = '';
      opts.forEach(function (label) {
        if (f && label.toLowerCase().indexOf(lf) === -1) return;
        var it = document.createElement('div');
        it.className = 'ss-item'; it.innerHTML = mark(label, f);
        it.addEventListener('mousedown', function (e) { e.preventDefault(); pick(label); });
        list.appendChild(it); n++;
      });
      // Typing something new is a valid answer here, so say so rather than
      // showing a dead "no matches" and leaving the user unsure it will save.
      var exact = opts.some(function (o) { return o.toLowerCase() === lf; });
      if (f && !exact) {
        var nw = document.createElement('div');
        nw.className = 'ss-item ss-new'; nw.textContent = 'Use “' + f + '” — new category';
        nw.addEventListener('mousedown', function (e) { e.preventDefault(); pick(f); });
        list.appendChild(nw); n++;
      }
      if (!n) {
        var d = document.createElement('div'); d.className = 'ss-item ss-none';
        d.textContent = 'Start typing to add one'; list.appendChild(d);
      }
    }
    // Same reopening fault as the searchable select above — click toggles.
    input.addEventListener('mousedown', function () {
      if (list.style.display === 'none') { build(); list.style.display = 'block'; }
      else { list.style.display = 'none'; }
    });
    input.addEventListener('focus', function () { build(); list.style.display = 'block'; });
    input.addEventListener('input', function () { build(); list.style.display = 'block'; });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { list.style.display = 'none'; return; }
      var items = list.querySelectorAll('.ss-item:not(.ss-none)');
      if (!items.length || list.style.display === 'none') return;
      var cur = list.querySelector('.ss-item.ss-on'), i = -1;
      Array.prototype.forEach.call(items, function (it, k) { if (it === cur) i = k; });
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        i = e.key === 'ArrowDown' ? Math.min(i + 1, items.length - 1) : Math.max(i - 1, 0);
        if (cur) cur.classList.remove('ss-on');
        items[i].classList.add('ss-on'); items[i].scrollIntoView({block:'nearest'});
      } else if (e.key === 'Enter' && cur) {
        e.preventDefault(); cur.dispatchEvent(new MouseEvent('mousedown'));
      }
    });
    input.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; }, 150); });
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
    rows.forEach(function (r) {
      var o = document.createElement('option'); o.value = r.id; o.textContent = r.label;
      // Carry the commercial detail on the option itself, so choosing a line can
      // price the call without another round trip.
      if (r.rate !== undefined && r.rate !== null) o.dataset.rate = r.rate;
      if (r.unit) o.dataset.unit = r.unit;
      if (r.balance !== undefined) o.dataset.balance = r.balance;
      if (String(r.id) === String(keep)) o.selected = true;
      sel.appendChild(o);
    });
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
    // §g — a list the user still has to open is not "automatically selected".
    // When there is exactly one sensible answer, take it and fire change so the
    // next cascade runs; otherwise leave the choice alone.
    function autoPick(sel, rows) {
      if (!sel || sel.value) return false;
      var real = rows.filter(function (r) { return r && r.id; });
      if (real.length !== 1) return false;
      sel.value = String(real[0].id);
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    }
    function loadPoLines(keep) {
      if (!poLine) return;
      if (!po || !po.value) { fillSelect(poLine, [], ''); return; }
      fetch('/po-lines?id=' + encodeURIComponent(po.value))
        .then(function (r) { return r.json(); })
        .then(function (rows) {
          fillSelect(poLine, rows, keep || poLine.value);
          // autoPick fires change, which prices it; if a line was already chosen
          // server-side there is no change event, so price it explicitly.
          if (!autoPick(poLine, rows) && poLine.value) priceFromPoLine();
        })
        .catch(function () {});
    }
    function loadClientLinks() {
      var id = client ? client.value : '';
      if (!id) { fillSelect(site, [], ''); fillSelect(po, [], ''); fillSelect(poLine, [], ''); return; }
      if (site) fetch('/partner-sites?id=' + encodeURIComponent(id)).then(function (r) { return r.json(); }).then(function (rows) { fillSelect(site, rows, site.value); autoPick(site, rows); }).catch(function () {});
      if (po) fetch('/partner-pos?id=' + encodeURIComponent(id)).then(function (r) { return r.json(); })
        .then(function (rows) {
          fillSelect(po, rows, po.value);
          // One live order for this client is the normal case for an ARC, and
          // picking it also pulls its line items in.
          if (!autoPick(po, rows)) loadPoLines();
        }).catch(function () {});
    }
    // §l — the value billable comes off the purchase order: the line's own rate,
    // times the units this call uses. Typing it again is how a call ends up
    // billed at a figure the order never carried.
    function priceFromPoLine() {
      if (!poLine) return;
      var o = poLine.options[poLine.selectedIndex];
      if (!o || !o.value) return;
      var rateBox = document.getElementById('billable_rate'),
          basis = document.getElementById('billable_basis'),
          qtyBox = document.getElementById('billable_qty'),
          note = document.getElementById('po_bal_note');
      if (rateBox && o.dataset.rate) rateBox.value = o.dataset.rate;
      if (basis && o.dataset.unit) {
        var has = Array.prototype.some.call(basis.options, function (x) { return x.value === o.dataset.unit; });
        if (has) { basis.value = o.dataset.unit; basis.dispatchEvent(new Event('change', { bubbles: true })); }
      }
      if (window.__recalcBillable) window.__recalcBillable();
      // Asking for more than the order has left is not refused — the order may be
      // topped up — but nobody should find out at invoicing.
      if (note) {
        var bal = parseFloat(o.dataset.balance || 'NaN'), q = parseFloat(qtyBox ? qtyBox.value : '0');
        if (!isNaN(bal)) {
          note.textContent = q > bal
            ? '⚠ This ' + (window.__callWord || 'call') + ' uses ' + q + ' but only ' + bal + ' is left on that PO line.'
            : bal + ' left on this PO line after ' + (q || 0) + ' used here.';
          note.className = q > bal ? 'down' : 'muted';
        }
      }
    }
    if (poLine) poLine.addEventListener('change', priceFromPoLine);
    if (client) client.addEventListener('change', loadClientLinks);
    if (po && poLine) po.addEventListener('change', function () { loadPoLines(); });
    // On an existing call the selects are already filled server-side; still load
    // the dependent lists so the line dropdown is not empty on first open.
    if (po && po.value) loadPoLines(poLine ? poLine.value : '');
  }

  // ---- Quick-add ("+ Add new") modal on the New Call form ----
  function initQuickAdd() {
    var back = document.getElementById('qa_back');
    if (!back) return;
    var kind = '', targetId = '';
    var byId = function (id) { return document.getElementById(id); };
    function show(sel) { Array.prototype.forEach.call(document.querySelectorAll(sel), function (n) { n.style.display = 'block'; }); }
    function hideAll() { Array.prototype.forEach.call(document.querySelectorAll('.qa-field'), function (n) { n.style.display = 'none'; }); }
    // §b — the details a client or site cannot be worked against without. A site
    // is not invoiced and is rarely e-mailed, so it is asked for less.
    var CV_FIELDS = ['qa_gstin', 'qa_pan', 'qa_line1', 'qa_qcity', 'qa_state', 'qa_pname', 'qa_pmob', 'qa_pmail'];
    function open(k) {
      kind = k; hideAll(); byId('qa_err').style.display = 'none';
      byId('qa_name').value = '';
      CV_FIELDS.forEach(function (id) { if (byId(id)) byId(id).value = ''; });
      var titles = { client: 'Add Client', vendor: 'Add Vendor', office: 'Add Executing office', product: 'Add Product category', activity: 'Add Activity code', agency: 'Add Agency (sub-con / HR)' };
      byId('qa_title').textContent = titles[k] || 'Add';
      targetId = { client: 'client_sel', vendor: 'vendor_sel', office: 'exec_sel', product: 'product_sel', activity: 'activity_sel', agency: 'agency_sel' }[k];
      if (k === 'client' || k === 'vendor') {
        show('.qa-cv'); if (byId('qa_both')) byId('qa_both').checked = false;
        var isClient = (k === 'client');
        ['qa_req_addr', 'qa_req_city', 'qa_req_mail'].forEach(function (id) {
          if (byId(id)) byId(id).style.display = (id === 'qa_req_mail' && !isClient) ? 'none' : '';
        });
        if (byId('qa_cv_note')) byId('qa_cv_note').textContent = isClient
          ? 'A client is invoiced and visited, so the tax identity, an address and somebody to ring are required now — not later.'
          : 'The engineer travels to this site and reports to this person, so an address and a contact are required now.';
      }
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
        var val = function (id) { return byId(id) ? byId(id).value.trim() : ''; };
        var isClient = (k === 'client') || (byId('qa_both') && byId('qa_both').checked);
        var need = [];
        if (isClient && !val('qa_gstin') && !val('qa_pan')) need.push('a GSTIN or a PAN');
        if (!val('qa_line1') && !val('qa_qcity')) need.push('an address');
        if (!val('qa_pname')) need.push('a contact person');
        if (!val('qa_pmob')) need.push('a mobile number');
        if (isClient && !val('qa_pmail')) need.push('an e-mail address');
        if (need.length) {
          byId('qa_err').textContent = 'Enter ' + (need.length === 1 ? need[0]
            : need.slice(0, -1).join(', ') + ' and ' + need[need.length - 1])
            + '. These are needed before the work can be sent out or billed.';
          byId('qa_err').style.display = 'block'; return;
        }
        CV_FIELDS.forEach(function (id) { body.append(id.replace('qa_', ''), val(id)); });
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

  // ---- One submission, one record ------------------------------------------
  // Every POST form gets a one-shot ticket. The server spends it on the first
  // submission and turns away any replay carrying the same one — a double-click,
  // a browser retry, a refresh-and-resend, or the offline queue re-sending an
  // entry whose reply never arrived. Reloading the page mints a new ticket, so
  // deliberately entering the same thing twice still works.
  function ftUid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'ft-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
  }
  function initOnceOnly() {
    var uid = ftUid;
    Array.prototype.forEach.call(document.querySelectorAll('form'), function (f) {
      var method = (f.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post') return;
      if (f.querySelector('input[name=_ft]')) return;
      var t = document.createElement('input');
      t.type = 'hidden'; t.name = '_ft'; t.value = uid();
      f.appendChild(t);
      f.addEventListener('submit', function () {
        // Grey the button so the second click has nothing to hit. The form is
        // already on its way, so this only ever hides a duplicate.
        Array.prototype.forEach.call(f.querySelectorAll('button[type=submit], input[type=submit]'), function (b) {
          setTimeout(function () { b.disabled = true; b.dataset.wasLabel = b.textContent; if (b.tagName === 'BUTTON') b.textContent = 'Saving…'; }, 0);
        });
        // If the browser comes back to this page (bfcache / back button) the
        // buttons must work again, and the ticket must be a fresh one.
        setTimeout(function () {
          Array.prototype.forEach.call(f.querySelectorAll('button[type=submit], input[type=submit]'), function (b) {
            if (!b.disabled) return;
            b.disabled = false; if (b.dataset.wasLabel) b.textContent = b.dataset.wasLabel;
          });
          t.value = uid();
        }, 12000);
      });
    });
  }
  // Coming back with the back button restores the page from cache with its
  // spent ticket and its greyed buttons. Both have to be reset or the screen
  // looks broken and a genuine second entry would be refused.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    initOnceOnly();
    Array.prototype.forEach.call(document.querySelectorAll('form input[name=_ft]'), function (t) { t.value = ftUid(); });
    Array.prototype.forEach.call(document.querySelectorAll('button[type=submit][disabled], input[type=submit][disabled]'), function (b) {
      b.disabled = false;
      if (b.dataset.wasLabel) b.textContent = b.dataset.wasLabel;
    });
  });

  function init() {
    initOnceOnly();
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
    // Any text input wired to a datalist becomes the themed combo instead.
    Array.prototype.forEach.call(document.querySelectorAll('input.combo, input[list]'), enhanceCombo);
  }
  if (document.readyState !== 'loading') init();
  else document.addEventListener('DOMContentLoaded', init);
})();
