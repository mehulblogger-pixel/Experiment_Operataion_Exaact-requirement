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

  function init() {
    gstAutofill();
    initCascades();
    Array.prototype.forEach.call(document.querySelectorAll('select.searchable'), enhanceSelect);
  }
  if (document.readyState !== 'loading') init();
  else document.addEventListener('DOMContentLoaded', init);
})();
