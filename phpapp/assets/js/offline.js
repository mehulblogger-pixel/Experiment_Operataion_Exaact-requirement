/* IDEMS field mode — draft autosave, offline banner, and queue-and-sync.
   Designed for shared PHP hosting: no build step, no framework.

   1. Any form marked data-autosave keeps a local draft in localStorage as the
      inspector types, so nothing is lost if the phone sleeps or signal drops.
      A small "Saved on this device" chip confirms it, so the field user can see
      their work is safe without a network.
   2. If the device is offline when the form is submitted, the submission is
      queued locally and replayed automatically when the connection returns.
      (Forms with file uploads are never queued — photos need a live upload —
      but their text draft is still kept.)
   3. A bottom banner shows ONE uniform sync-state vocabulary, so the same words
      mean the same thing everywhere:
        Saved locally  · your draft is on this device
        Pending sync   · a submission is queued, waiting to send
        Syncing        · sending the queued submissions now
        Synced         · everything queued has been sent
        Sync failed    · a send failed; it will retry, or tap Retry
      A failed send now retries on a backoff (and on reconnect / tab focus)
      instead of appearing to "sync" forever.
*/
(function () {
  var LS_DRAFT = 'idems.draft.';
  var LS_QUEUE = 'idems.queue';

  // ---------- sync-state model ----------
  var state = { syncing: false, failed: false, justSynced: false, attempts: 0 };
  var retryTimer = null;
  var RETRY_DELAYS = [5000, 15000, 30000, 60000];

  function queue() { try { return JSON.parse(localStorage.getItem(LS_QUEUE) || '[]'); } catch (e) { return []; } }
  function setQueue(q) { try { localStorage.setItem(LS_QUEUE, JSON.stringify(q)); } catch (e) {} }

  // Pure decision: given the raw signals, what does the banner say? One place,
  // one vocabulary. Exposed on window for tests and debugging.
  function syncState(s) {
    var q = s.queued | 0;
    if (s.failed && q)            return { key: 'FAILED',  show: true, tone: '#b91c1c', retry: true,
                                           text: '⚠ Sync failed — ' + q + ' item' + (q > 1 ? 's' : '') + ' waiting on this device' };
    if (!s.online)                return { key: 'OFFLINE', show: true, tone: '#b45309',
                                           text: '⚠ Offline — saved on this device' + (q ? ' · ' + q + ' pending sync' : '') };
    if (s.syncing && q)           return { key: 'SYNCING', show: true, tone: '#1e40af',
                                           text: '⟳ Syncing ' + q + ' item' + (q > 1 ? 's' : '') + '…' };
    if (q)                        return { key: 'PENDING', show: true, tone: '#1e40af',
                                           text: '⏳ ' + q + ' item' + (q > 1 ? 's' : '') + ' pending sync' };
    if (s.justSynced)             return { key: 'SYNCED',  show: true, tone: '#15803d', text: '✓ Synced' };
    return { key: 'IDLE', show: false };
  }
  try { window.idemsSyncState = syncState; } catch (e) {}

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
  function paintBar() {
    var b = ensureBar();
    var s = syncState({ online: navigator.onLine, queued: queue().length,
                        syncing: state.syncing, failed: state.failed, justSynced: state.justSynced });
    if (!s.show) { b.style.display = 'none'; return; }
    b.style.display = 'block';
    b.style.background = s.tone; b.style.color = '#fff';
    b.textContent = s.text;
    if (s.retry) {
      var btn = document.createElement('button');
      btn.type = 'button'; btn.textContent = 'Retry now';
      btn.style.cssText = 'margin-left:10px;padding:2px 10px;border:0;border-radius:4px;background:#fff;color:#b91c1c;font:inherit;font-weight:700;cursor:pointer';
      btn.onclick = function () { state.failed = false; state.attempts = 0; if (retryTimer) { clearTimeout(retryTimer); retryTimer = null; } flush(); };
      b.appendChild(btn);
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
  // The per-form "Saved locally" confirmation the field user needs.
  function savedChip(f) {
    if (f.__savedChip) return f.__savedChip;
    var chip = document.createElement('div');
    chip.className = 'idems-saved-chip';
    chip.style.cssText = 'font:500 11px system-ui,Segoe UI,Arial,sans-serif;color:#6b7280;margin:0 0 6px';
    if (f.parentNode) f.parentNode.insertBefore(chip, f);
    f.__savedChip = chip;
    return chip;
  }
  function markSaved(f) {
    var c = savedChip(f), t = new Date().toLocaleTimeString();
    c.textContent = (navigator.onLine ? '✓ Saved on this device' : '✓ Saved on this device (offline)') + ' · ' + t;
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
      try { localStorage.setItem(key, JSON.stringify({ saved: Date.now(), values: snapshot(f) })); markSaved(f); } catch (e) {}
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

  function scheduleRetry() {
    if (retryTimer) return;
    var d = RETRY_DELAYS[Math.min(state.attempts, RETRY_DELAYS.length - 1)];
    state.attempts++;
    retryTimer = setTimeout(function () {
      retryTimer = null;
      if (navigator.onLine && queue().length) { state.failed = false; flush(); }
    }, d);
  }
  function flush() {
    var q = queue();
    if (!q.length) { state.syncing = false; state.failed = false; paintBar(); return; }
    if (!navigator.onLine) { state.syncing = false; paintBar(); return; }
    state.syncing = true; state.failed = false; paintBar();
    var item = q[0];
    var body = new URLSearchParams();
    item.body.forEach(function (p) { body.append(p[0], p[1]); });
    fetch(item.url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok && r.status >= 500) throw new Error('server');
        q.shift(); setQueue(q); state.attempts = 0;
        if (q.length) { paintBar(); flush(); }
        else {
          // Everything queued has gone — show "Synced" briefly, then refresh so the
          // server-rendered page reflects what was just sent.
          state.syncing = false; state.justSynced = true; paintBar();
          setTimeout(function () { state.justSynced = false; location.reload(); }, 1500);
        }
      })
      .catch(function () {
        // A real failure (offline mid-send, or a 5xx while online): say so, and
        // retry on a backoff instead of pretending to sync for ever.
        state.syncing = false; state.failed = true; paintBar();
        scheduleRetry();
      });
  }

  // ---------- boot ----------
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-autosave]').forEach(function (f) { wireAutosave(f); wireQueue(f); });
    paintBar();
    if (navigator.onLine) flush();
  });
  window.addEventListener('online', function () { state.failed = false; state.attempts = 0; if (retryTimer) { clearTimeout(retryTimer); retryTimer = null; } paintBar(); flush(); });
  window.addEventListener('offline', function () { state.syncing = false; paintBar(); });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && navigator.onLine && queue().length && !state.syncing) { state.failed = false; flush(); }
  });

  // register the service worker (safe to fail on http/localhost setups)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
})();
