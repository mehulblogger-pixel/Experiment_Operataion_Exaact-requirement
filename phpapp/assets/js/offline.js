/* IDEMS field mode — draft autosave, offline banner, and queue-and-sync.
   Designed for shared PHP hosting: no build step, no framework.

   1. Any form marked data-autosave keeps a local draft in localStorage as the
      inspector types, so nothing is lost if the phone sleeps or signal drops.
   2. If the device is offline when the form is submitted, the submission is
      queued locally and replayed automatically when the connection returns.
      (Forms with file uploads are never queued — photos need a live upload —
      but their text draft is still kept.)
   3. A small banner shows the connection state and how many items are waiting.
*/
(function () {
  var LS_DRAFT = 'idems.draft.';
  var LS_QUEUE = 'idems.queue';

  // ---------- connection banner ----------
  var bar;
  function ensureBar() {
    if (bar) return bar;
    bar = document.createElement('div');
    bar.id = 'idems-netbar';
    bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:9999;padding:8px 14px;font:600 13px system-ui,Segoe UI,Arial,sans-serif;text-align:center;display:none';
    document.body.appendChild(bar);
    return bar;
  }
  function queue() { try { return JSON.parse(localStorage.getItem(LS_QUEUE) || '[]'); } catch (e) { return []; } }
  function setQueue(q) { try { localStorage.setItem(LS_QUEUE, JSON.stringify(q)); } catch (e) {} }
  function paintBar() {
    var b = ensureBar(), n = queue().length;
    if (!navigator.onLine) {
      b.style.display = 'block'; b.style.background = '#b45309'; b.style.color = '#fff';
      b.textContent = '⚠ Offline — your work is being saved on this device' + (n ? ' · ' + n + ' waiting to sync' : '');
    } else if (n) {
      b.style.display = 'block'; b.style.background = '#1e40af'; b.style.color = '#fff';
      b.textContent = '⟳ Back online — syncing ' + n + ' saved item' + (n > 1 ? 's' : '') + '…';
    } else {
      b.style.display = 'none';
    }
  }

  // ---------- draft autosave ----------
  function formKey(f) { return LS_DRAFT + (f.getAttribute('data-autosave') || location.pathname + location.search); }
  function snapshot(f) {
    var out = {};
    f.querySelectorAll('input,select,textarea').forEach(function (el) {
      if (!el.name || el.type === 'file' || el.type === 'password' || el.type === 'hidden') return;
      if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) (out[el.name] = out[el.name] || []).push(el.value); }
      else out[el.name] = el.value;
    });
    return out;
  }
  function restore(f, data) {
    f.querySelectorAll('input,select,textarea').forEach(function (el) {
      if (!el.name || !(el.name in data)) return;
      var v = data[el.name];
      if (el.type === 'checkbox' || el.type === 'radio') el.checked = Array.isArray(v) && v.indexOf(el.value) >= 0;
      else el.value = v;
    });
    f.dispatchEvent(new Event('input', { bubbles: true }));   // re-run conditional/calc logic
  }
  function wireAutosave(f) {
    var key = formKey(f), t = null;
    // offer to restore a newer local draft
    try {
      var raw = localStorage.getItem(key);
      if (raw) {
        var d = JSON.parse(raw);
        if (d && d.saved && Object.keys(d.values || {}).length) {
          var when = new Date(d.saved).toLocaleString();
          var note = document.createElement('div');
          note.className = 'panel';
          note.style.cssText = 'border:1px solid #b45309;background:#fff7ed;margin-bottom:12px';
          note.innerHTML = '<b>Unsaved draft found on this device</b> <span style="color:#6b7280">(' + when + ')</span> ' +
            '<button type="button" class="btn small" id="idems-restore">Restore it</button> ' +
            '<button type="button" class="btn small secondary" id="idems-discard">Discard</button>';
          f.parentNode.insertBefore(note, f);
          note.querySelector('#idems-restore').onclick = function () { restore(f, d.values); note.remove(); };
          note.querySelector('#idems-discard').onclick = function () { localStorage.removeItem(key); note.remove(); };
        }
      }
    } catch (e) {}
    function save() {
      try { localStorage.setItem(key, JSON.stringify({ saved: Date.now(), values: snapshot(f) })); } catch (e) {}
    }
    f.addEventListener('input', function () { clearTimeout(t); t = setTimeout(save, 700); });
    f.addEventListener('change', function () { clearTimeout(t); t = setTimeout(save, 300); });
    f.addEventListener('submit', function () { setTimeout(function () { try { localStorage.removeItem(key); } catch (e) {} }, 400); });
  }

  // ---------- queue submissions made while offline ----------
  function hasFiles(f) {
    var found = false;
    f.querySelectorAll('input[type=file]').forEach(function (i) { if (i.files && i.files.length) found = true; });
    return found;
  }
  function wireQueue(f) {
    f.addEventListener('submit', function (ev) {
      if (navigator.onLine) return;
      if (hasFiles(f)) { alert('You are offline and this form includes photos or files.\n\nYour typed entries are saved on this device — reconnect and submit again to upload the images.'); ev.preventDefault(); return; }
      ev.preventDefault();
      var fd = new FormData(f), pairs = [];
      fd.forEach(function (v, k) { if (typeof v === 'string') pairs.push([k, v]); });
      var q = queue();
      q.push({ url: f.getAttribute('action') || location.pathname + location.search, body: pairs, at: Date.now() });
      setQueue(q); paintBar();
      alert('You are offline. This entry has been saved on the device and will be sent automatically when the connection returns.');
    });
  }
  function flush() {
    var q = queue();
    if (!q.length || !navigator.onLine) { paintBar(); return; }
    var item = q[0];
    var body = new URLSearchParams();
    item.body.forEach(function (p) { body.append(p[0], p[1]); });
    fetch(item.url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok && r.status >= 500) throw new Error('server');
        q.shift(); setQueue(q); paintBar();
        if (q.length) flush(); else if (item) setTimeout(function () { location.reload(); }, 600);
      })
      .catch(function () { paintBar(); });   // keep it queued; try again on the next online event
  }

  // ---------- boot ----------
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-autosave]').forEach(function (f) { wireAutosave(f); wireQueue(f); });
    paintBar();
    if (navigator.onLine) flush();
  });
  window.addEventListener('online', function () { paintBar(); flush(); });
  window.addEventListener('offline', paintBar);

  // register the service worker (safe to fail on http/localhost setups)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
})();
