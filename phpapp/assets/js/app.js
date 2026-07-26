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
    function poLineNote(hint) {
      var n = document.getElementById('po_line_note');
      if (!n) {
        var host = poLine && poLine.closest ? poLine.closest('.ff') : null;
        if (!host) return;
        n = document.createElement('small'); n.id = 'po_line_note';
        host.appendChild(n);
      }
      n.textContent = '';
      n.className = hint ? 'down' : 'muted';
      if (!hint) return;
      n.appendChild(document.createTextNode(hint.text + ' '));
      if (hint.url) {
        var a = document.createElement('a');
        a.href = hint.url; a.target = '_blank'; a.textContent = hint.link || 'Open';
        n.appendChild(a);
      }
    }
    function loadPoLines(keep) {
      if (!poLine) return;
      if (!po || !po.value) { fillSelect(poLine, [], ''); poLineNote(null); return; }
      fetch('/po-lines?id=' + encodeURIComponent(po.value))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          // The endpoint answers with {lines, hint}; older callers were handed a
          // bare array, so accept both rather than break on a cached script.
          var rows = (data && data.lines) ? data.lines : (Array.isArray(data) ? data : []);
          var hint = (data && data.hint) ? data.hint : null;
          fillSelect(poLine, rows, keep || poLine.value);
          // An order with no lines looks exactly like a broken dropdown, and the
          // note underneath is hidden by the open list at the moment somebody is
          // staring at it. So the reason goes INSIDE the list as well, where the
          // eye already is.
          if (!rows.length && hint) {
            var o = document.createElement('option');
            o.value = ''; o.disabled = true;
            o.textContent = '— no line items on this order —';
            poLine.appendChild(o);
          }
          poLineNote(rows.length ? null : hint);
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
    // The master screen reads the PAN and the state straight out of the GSTIN
    // as it is typed. Quick-add asked for the same GSTIN and then made people
    // work them out themselves, which is how a vendor ends up with no PAN.
    (function () {
      var g = byId('qa_gstin');
      if (!g) return;
      g.addEventListener('input', function () {
        var v = (g.value || '').toUpperCase().replace(/\s+/g, '');
        var pan = byId('qa_pan'), st = byId('qa_state');
        if (v.length >= 12 && pan) {
          var p = v.substring(2, 12);
          if (/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(p)) pan.value = p;
        }
        if (v.length >= 2 && st) {
          var name = STATES[v.substring(0, 2)] || '';
          if (name) {
            st.value = name;
            // the searchable widget hides the real select, so tell it
            st.dispatchEvent(new Event('change', { bubbles: true }));
            var wrap = st.parentNode, box = wrap && wrap.querySelector('input');
            if (box) box.value = name;
          }
        }
      });
      g.addEventListener('blur', function () { g.value = (g.value || '').toUpperCase().replace(/\s+/g, ''); });
    })();
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
      // Posted without a form, so the token the server stamps into forms is not
      // here — it is read off the page instead. Without it the save is refused.
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) body.append('_csrf', csrfMeta.getAttribute('content') || '');
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
          // It may have found the company already on file and used that one. Say
          // so — silently picking a different record than the one just typed is
          // how people end up not trusting the box.
          if (res.note) { try { alert(res.note); } catch (e) {} }
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

  // ---- Make credit mandatory (visibly) when a call crosses offices ----------
  //
  //  This used to require the credit whenever ANY executing office was chosen —
  //  including when it was the same office that holds the contract, in which
  //  case there is no inter-office credit and the whole box is hidden. A
  //  required field inside a hidden container is the one thing a browser will
  //  neither submit nor complain about: the button goes dead, silently, and
  //  stays dead. That is what "Save inspection call is not working" was.
  function initForwardCredit() {
    var exec = document.getElementById('exec_sel');
    var ibo = document.getElementById('ibo_sel');
    var credit = document.querySelector('input[name="expected_credit"]');
    if (!exec || !credit) return;
    function sync() {
      var e = exec.value || '', m = ibo ? (ibo.value || '') : '';
      // exactly the condition that shows the box — one office doing its own
      // work owes itself nothing
      var on = e !== '' && e !== m;
      credit.required = on;
      var ff = credit.closest ? credit.closest('.ff') : null;
      var lbl = ff && ff.querySelector('label');
      if (lbl) {
        var star = lbl.querySelector('.req-star');
        if (on && !star) {
          var sp = document.createElement('span');
          sp.className = 'req-star'; sp.style.color = '#c0392b'; sp.textContent = ' ★ required';
          lbl.appendChild(sp);
        } else if (!on && star) star.remove();
      }
      credit.style.borderColor = on ? '#F37021' : '';
    }
    exec.addEventListener('change', sync);
    if (ibo) ibo.addEventListener('change', sync);
    sync();
  }

  // ---- Save must never fail silently ---------------------------------------
  //
  //  A browser refuses to submit a form holding an invalid field, and tries to
  //  point at it. If that field is not on screen it cannot point at anything —
  //  so it refuses, says nothing, and the button appears dead. Every searchable
  //  dropdown in this app hides its real <select> behind a text box, so ANY
  //  required dropdown was one empty answer away from killing the whole form,
  //  with nothing on screen and nothing in the log to say why.
  //
  //  The previous attempt at this listened for the form's submit event and took
  //  the requirement off anything hidden. It could never have worked: the
  //  browser blocks BEFORE submit fires, so the listener never ran. That is the
  //  bug being fixed here, and it is why "Save is not working" came back.
  //
  //  So the browser's own checking is switched off entirely and done here
  //  instead, where it can be seen:
  //    · a field that is on screen and wrong  — rung in red, scrolled to, named
  //      in a message above the form. The form does not submit.
  //    · a field that is off screen           — let through to the server, which
  //      checks it anyway and answers with a message that opens the section it
  //      lives in. Better a clear refusal from the server than a dead button.
  //  Either way something happens when Save is pressed. That is the whole point.
  function fieldLabel(el) {
    var ff = el.closest ? el.closest('.ff') : null;
    var lab = ff ? ff.querySelector('label') : null;
    if (!lab) return el.name || 'a field';
    // Labels carry hints and an "+ Add new" link; naming the box means the words
    // that name it, not everything printed alongside.
    var c = lab.cloneNode(true);
    Array.prototype.forEach.call(c.querySelectorAll('a, button, .muted, small'), function (n) {
      n.parentNode.removeChild(n);
    });
    var t = c.textContent.replace(/\s+/g, ' ').trim().replace(/[\s*:—-]+$/, '');
    return t || el.name || 'a field';
  }
  function onScreen(el) {
    return !!(el && (el.offsetWidth || el.offsetHeight || (el.getClientRects && el.getClientRects().length)));
  }
  // What the person actually sees for this control. A searchable dropdown is a
  // hidden <select> with a visible text box next to it; marking the select in
  // red would mark nothing.
  function shownAs(el) {
    if (onScreen(el)) return el;
    var wrap = el.closest ? el.closest('.ss-wrap') : null;
    var box = wrap ? wrap.querySelector('input') : null;
    return (box && onScreen(box)) ? box : null;
  }
  function clearMarks(f) {
    Array.prototype.forEach.call(f.querySelectorAll('.field-bad'), function (n) { n.classList.remove('field-bad'); });
    Array.prototype.forEach.call(f.querySelectorAll('.ff-bad'), function (n) { n.classList.remove('ff-bad'); });
    var old = f.querySelector('.guard-msg');
    if (old && old.parentNode) old.parentNode.removeChild(old);
  }
  function showProblems(f, bad) {
    var names = bad.map(function (b) { return fieldLabel(b.el); });
    var box = document.createElement('div');
    box.className = 'msg msg-error guard-msg';
    box.textContent = bad.length === 1
      ? 'Nothing has been saved — ' + names[0] + ' still needs filling in.'
      : 'Nothing has been saved — ' + bad.length + ' boxes still need filling in: ' + names.join(', ') + '.';
    f.insertBefore(box, f.firstChild);
    bad.forEach(function (b) {
      b.mark.classList.add('field-bad');
      var ff = b.mark.closest ? b.mark.closest('.ff') : null;
      if (ff) ff.classList.add('ff-bad');
      var clear = function () {
        b.mark.classList.remove('field-bad');
        if (ff) ff.classList.remove('ff-bad');
      };
      b.mark.addEventListener('input', clear);
      b.mark.addEventListener('change', clear);
      b.el.addEventListener('change', clear);
    });
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    var first = bad[0].mark;
    setTimeout(function () { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }, 350);
  }
  function initFormGuard() {
    Array.prototype.forEach.call(document.querySelectorAll('form'), function (f) {
      if (f.dataset.guard === '1') return;
      f.dataset.guard = '1';
      // The browser must never make this decision, because it cannot always
      // show its reasons.
      f.noValidate = true;
      f.addEventListener('submit', function (e) {
        clearMarks(f);
        var bad = [];
        Array.prototype.forEach.call(f.elements, function (el) {
          if (el.disabled || el.type === 'hidden' || el.type === 'submit'
              || el.type === 'button' || el.type === 'reset') return;
          if (!el.checkValidity || el.checkValidity()) return;
          var mark = shownAs(el);
          if (!mark) return;                 // not on screen: the server judges it
          bad.push({ el: el, mark: mark });
        });
        if (!bad.length) return;             // let it save
        e.preventDefault();
        // Stop the one-shot-ticket handler too, or it greys the button and
        // writes "Saving…" over a form that is not going anywhere.
        e.stopImmediatePropagation();
        showProblems(f, bad);
      });
    });
  }

  // ---- The shape of the engagement decides which boxes exist ---------------
  //
  //  Five shapes, and each needs different answers. A single-day call needs one
  //  date; a continuous one needs a number of days; multiple dates need the
  //  dates themselves. Showing all of it at once is what made this screen
  //  unreadable, so everything not belonging to the chosen shape is taken off
  //  the page — not merely greyed, taken off, so it cannot be filled in by
  //  accident and cannot block the save.
  //
  //  The dates, the end date and the working-day arithmetic are NOT worked out
  //  here. They are asked of the server, because Sundays and each branch's own
  //  public holidays are its rules, and a second copy of a rule in JavaScript is
  //  a second rule that will drift.
  var PAT_N_LABEL = {
    PER_WEEK: 'Visits each week',
    EVERY_N: 'Every how many days?',
    WEEKDAYS: '', FORTNIGHT: '', MONTHLY_1: '',
  };
  var ENG_HINT = {
    SINGLE: 'One visit, on one day.',
    CONTINUOUS: 'The engineer is there day after day — say how many working days.',
    MULTIPLE: 'The client names the days. It runs from the earliest to the latest.',
    PATTERN: 'Worked out from how it repeats and the date it runs until.',
    MONTHLY: 'A posting at the works, on a man-month basis.',
  };
  function initEngagement() {
    var sel = document.getElementById('eng_sel');
    if (!sel) return;
    var out = document.getElementById('sched_out');
    var hint = document.getElementById('eng_hint');
    var form = sel.form;

    function applyShape() {
      var t = sel.value || 'SINGLE';
      Array.prototype.forEach.call(document.querySelectorAll('.eng-box'), function (b) {
        var mine = (b.getAttribute('data-for') || '').split(',').indexOf(t) >= 0;
        b.style.display = mine ? '' : 'none';
        // Out of sight means out of the form: a box for a shape nobody chose
        // must not be posted and must never hold up a save.
        Array.prototype.forEach.call(b.querySelectorAll('input, select'), function (el) {
          el.disabled = !mine;
        });
      });
      if (hint) hint.textContent = ENG_HINT[t] || '';
      // Within a pattern, only some kinds need a number.
      var pk = document.getElementById('pattern_kind');
      if (pk) {
        var lab = PAT_N_LABEL[pk.value] || '';
        var nBox = document.getElementById('pat_n_box');
        var wdBox = document.getElementById('pat_wd_box');
        if (nBox) {
          nBox.style.display = (t === 'PATTERN' && lab) ? '' : 'none';
          var n = document.getElementById('pattern_n'); if (n) n.disabled = !(t === 'PATTERN' && lab);
          var nl = document.getElementById('pat_n_label'); if (nl && lab) nl.textContent = lab;
        }
        if (wdBox) {
          var wantWd = (t === 'PATTERN' && pk.value === 'WEEKDAYS');
          wdBox.style.display = wantWd ? '' : 'none';
          Array.prototype.forEach.call(wdBox.querySelectorAll('input'), function (el) { el.disabled = !wantWd; });
        }
      }
    }

    var timer = null;
    function preview() {
      if (!out) return;
      var t = sel.value || 'SINGLE';
      var start = (document.getElementById('req_date') || document.getElementById('sched_date') || {}).value || '';
      var body = new URLSearchParams();
      body.set('engagement_type', t);
      body.set('start', start);
      body.set('days_count', (document.getElementById('days_count') || {}).value || '');
      body.set('months_count', (document.getElementById('months_count') || {}).value || '');
      body.set('pattern_kind', (document.getElementById('pattern_kind') || {}).value || '');
      body.set('pattern_n', (document.getElementById('pattern_n') || {}).value || '');
      body.set('schedule_end_date', (document.getElementById('schedule_end_date') || {}).value || '');
      var off = document.getElementById('exec_sel') || document.querySelector('[name=executing_office_id]');
      if (off && off.value) body.set('office', off.value);
      Array.prototype.forEach.call(document.querySelectorAll('[name="schedule_weekdays[]"]:checked'), function (c) {
        body.append('schedule_weekdays[]', c.value);
      });
      Array.prototype.forEach.call(document.querySelectorAll('[name="inspection_dates[]"]'), function (d) {
        if (d.value) body.append('inspection_dates[]', d.value);
      });
      var insp = document.getElementById('insp_pick') || document.querySelector('[name=inspector_id]');
      if (insp && insp.value) body.set('inspector', insp.value);
      var cli = document.getElementById('client_sel') || document.querySelector('[name=client_id]');
      if (cli && cli.value) body.set('client', cli.value);
      var mb = document.querySelector('[name=manmonth_basis]');
      if (mb && mb.value) body.set('manmonth_basis', mb.value);
      var md = document.querySelector('[name=manmonth_min_days]');
      if (md && md.value) body.set('manmonth_min_days', md.value);
      // Days the coordinator has decided to work despite the calendar.
      Array.prototype.forEach.call(document.querySelectorAll('.forceday:checked'), function (c) {
        body.append('force_dates[]', c.value);
      });
      var jobId = (document.querySelector('[name=_job_id]') || {}).value;
      if (jobId) body.set('job', jobId);
      // Every POST carries the one-per-session ticket; without it the request is
      // turned away as a forgery and the panel simply never appears.
      var tok = document.querySelector('input[name=_csrf]');
      if (tok) body.set('_csrf', tok.value);

      fetch('/sched-preview', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) { render(d); })
        .catch(function () {});
    }
    function render(d) {
      if (!d || !d.count) { out.style.display = 'none'; return; }
      out.style.display = '';
      out.className = 'msg ' + (d.clashes ? 'msg-warning' : 'msg-success');
      var bits = ['<strong>' + d.label + '</strong> — '];
      if (d.count === 1) bits.push(d.startPretty + '.');
      else bits.push(d.startPretty + ' to <strong>' + d.endPretty + '</strong>, ' + d.count + ' inspection day(s). ');
      if (d.note) bits.push('<span class="muted">' + d.note + '.</span>');
      if (d.days && d.days.length && d.count <= 40) {
        bits.push('<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">');
        d.days.forEach(function (x) {
          var cls = !x.free ? 'p-bad' : 'p-ok';
          var t = x.weekday + ' ' + x.pretty + (x.busy ? ' — ' + x.busy : '');
          bits.push('<span class="pill ' + cls + '">' + t + '</span>');
        });
        bits.push('</div>');
      }
      if (d.clashes) {
        bits.push('<div style="margin-top:6px"><strong>' + d.clashes +
          ' date(s) clash with what that engineer is already doing.</strong> ');
        var alts = [];
        (d.days || []).forEach(function (x) {
          if (x.free || !x.alternatives || !x.alternatives.length) return;
          alts.push(x.pretty + ': ' + x.alternatives.map(function (a) { return a.name; }).join(', '));
        });
        bits.push(alts.length ? 'Free instead — ' + alts.join('; ') + '.' : 'Nobody else in this branch is free either.');
        bits.push('</div>');
      }
      // A posting is billed in man-months, and how many are claimable is not
      // the number of months on the calendar. Show the working.
      if (d.type === 'MONTHLY' && d.manmonths && d.manmonths.length) {
        var mm = ['<table class="grid" style="margin-top:8px"><tr><th>Month</th>'
          + '<th style="text-align:right">Working days</th><th style="text-align:right">Man-months</th></tr>'];
        d.manmonths.forEach(function (m) {
          mm.push('<tr><td>' + m.label + '</td><td style="text-align:right">' + m.working_days + '</td>'
            + '<td style="text-align:right"><strong>' + m.units.toFixed(2) + '</strong>'
            + (m.short ? ' <span class="pill p-warn">short — pro-rata</span>' : '') + '</td></tr>');
        });
        mm.push('<tr><td colspan="2" style="text-align:right"><strong>Claimable</strong></td>'
          + '<td style="text-align:right"><strong>' + Number(d.claimable).toFixed(2) + '</strong></td></tr></table>');
        mm.push('<div class="muted" style="margin-top:4px">'
          + (d.basis === 'MIN_DAYS'
              ? 'A man-month here is a minimum of ' + d.minDays + ' working days: below that it is pro-rata, above it is still one.'
              : 'A man-month here is the calendar month, whatever the working days come to.')
          + ' Taken from ' + (d.basisFrom || 'the default') + '.</div>');
        bits.push(mm.join(''));
      }
      // Sundays and holidays inside the run. The engagement steps over them on
      // its own; ticking one puts it back, which is the coordinator's call and
      // nobody else's.
      if (d.skipped && d.skipped.length) {
        var sk = ['<div style="margin-top:8px"><strong>Stepped over</strong> '
          + '<span class="muted">— tick any day the ' + (window.__engineerWord || 'engineer')
          + ' will work anyway, and the end date moves back.</span>'
          + '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">'];
        d.skipped.forEach(function (x) {
          sk.push('<label class="ff-check"><input type="checkbox" class="forceday" name="force_dates[]" value="'
            + x.date + '"> ' + x.weekday + ' ' + x.pretty + ' <span class="muted">(' + x.why + ')</span></label>');
        });
        sk.push('</div></div>');
        bits.push(sk.join(''));
      }
      out.innerHTML = bits.join('');
      // Anything already forced stays ticked when the panel is redrawn.
      (d.forced || []).forEach(function (f) {
        var c = out.querySelector('.forceday[value="' + f + '"]');
        if (c) c.checked = true;
      });
      Array.prototype.forEach.call(out.querySelectorAll('.forceday'), function (c) {
        c.addEventListener('change', later);
      });
      perVisit(d);
    }
    // A run of named dates may be covered by more than one engineer — the
    // client asked for the 1st, 5th and 12th, and whoever is free takes each.
    // Only on the allocate screen, where there is somebody to choose from.
    function perVisit(d) {
      var host = document.getElementById('visit_rows');
      var pick = document.getElementById('insp_pick');
      if (!host || !pick) return;
      var wants = (d.type === 'MULTIPLE' || d.type === 'PATTERN') && d.days && d.days.length && d.days.length <= 40;
      host.style.display = wants ? '' : 'none';
      if (!wants) { host.innerHTML = ''; return; }
      var chosen = {};
      Array.prototype.forEach.call(host.querySelectorAll('select'), function (s) {
        chosen[s.getAttribute('data-date')] = s.value;
      });
      var rows = ['<div class="ff ff-wide" style="margin:0"><label>Who goes on each day '
        + '<span class="muted">— leave as is to send the same engineer</span></label></div>'
        + '<table class="grid"><tr><th>Date</th><th>Engineer</th><th></th></tr>'];
      d.days.forEach(function (x) {
        var opts = '';
        Array.prototype.forEach.call(pick.options, function (o) {
          var sel = String(chosen[x.date] || pick.value) === String(o.value) ? ' selected' : '';
          opts += '<option value="' + o.value + '"' + sel + '>' + o.textContent.trim() + '</option>';
        });
        var note = !x.working ? '<span class="pill p-mut">' + x.why + '</span>'
                 : (x.busy ? '<span class="pill p-bad">' + x.busy + '</span>'
                           : '<span class="pill p-ok">free</span>');
        rows.push('<tr><td>' + x.weekday + ' ' + x.pretty + '</td>'
          + '<td><select class="form-control" data-date="' + x.date
          + '" name="visit_inspector[' + x.date + ']">' + opts + '</select></td>'
          + '<td>' + note + '</td></tr>');
      });
      rows.push('</table>');
      host.innerHTML = rows.join('');
    }
    function later() { clearTimeout(timer); timer = setTimeout(preview, 250); }

    sel.addEventListener('change', function () { applyShape(); later(); });
    if (form) {
      form.addEventListener('change', later);
      form.addEventListener('input', later);
    }
    var pk = document.getElementById('pattern_kind');
    if (pk) pk.addEventListener('change', applyShape);

    // "+ Add another date" — one more line, not a wall of empty boxes.
    var add = document.getElementById('adddate_call') || document.getElementById('adddate');
    var boxEl = document.getElementById('datebox');
    if (add && boxEl) {
      add.addEventListener('click', function () {
        var n = boxEl.querySelectorAll('.dateline').length + 1;
        var wrap = document.createElement('div');
        wrap.className = 'ff dateline';
        wrap.style.maxWidth = '280px';
        wrap.innerHTML = '<label>Date ' + n + '</label>'
          + '<input class="form-control" type="date" name="inspection_dates[]">';
        boxEl.appendChild(wrap);
        wrap.querySelector('input').focus();
      });
    }
    applyShape();
    later();
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
    // First, so its submit listener runs before the one-shot ticket greys the
    // button: listeners on the same element fire in the order they were added.
    initFormGuard();
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
    initEngagement();
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
