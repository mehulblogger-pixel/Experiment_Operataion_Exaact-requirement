# Module 47 — Mobile / PWA · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the PWA is already complete — the gap is that offline draft-save covers only one form

Contrary to the usual "add a manifest + service worker" opportunity, this app **already ships a full,
working PWA**: `manifest.php` (linked at `layout_top.php:50`), `sw.js` (network-first navigations +
offline shell), served before the auth gate (`index.php:597-613`), with viewport/theme-color/apple
meta, HTTPS enforced, responsive CSS (table→scroll at ≤640px, field-mode fill CSS), camera capture
(`capture="environment"`), GPS check-in, geofencing and voice dictation. It is installable and
genuinely phone-optimised for inspectors.

The real gap is in **`assets/js/offline.js`**, which provides draft autosave + an offline POST
queue — but only for forms carrying `data-autosave`, and that attribute was on **exactly one** form:
report fill (`fill.php:332`). So the site **check-in**, **voucher entry** and **evidence** forms — the
other things an inspector fills *in the field on a flaky connection* — had **no draft protection**:
lose signal mid-entry and the typed text is gone.

**Noted, deferred (flagged, not built):**
- **Web push** (zero `PushManager`/VAPID anywhere) — a real greenfield add, but heavier (needs PNG
  icons, a VAPID key, and iOS only supports it for installed PWAs).
- **PNG maskable icons** — the manifest icons are inline SVG only (fine for install, weaker for
  Android splash and a prerequisite for a push icon).
- **`accept`/`capture` on the evidence document input** — deliberately **NOT** added: that input is
  dual-purpose (a photo *or* a PDF mill/calibration certificate), so forcing camera-only would break
  document attach. It is left generic on purpose.
- Wide desk grids (`min-width:1500px`) are horizontal-scroll on phones — acceptable, they're
  coordinator/finance surfaces, not inspector phone-first ones.

---

## 1. Built (additive; one attribute per form; reuses the shipped offline engine)

Added `data-autosave` — the attribute `offline.js` already keys on — to the three remaining
phone-first inspector forms, each with a **per-record key** so drafts never collide:
- **Site check-in** (`job_detail.php`) → `checkin-<job_id>`.
- **Evidence upload** (`idems/evidence.php`) → `evidence-<doc_id>` (protects the caption text).
- **Voucher edit** (`voucher_detail.php` `#vform`) → `voucher-<voucher_id>` (protects the expense lines).

No route, handler, JS or CSS changed — the existing `wireAutosave`/`wireQueue`/restore-prompt machinery
now covers these forms too.

---

## 2. Edge cases handled (all by the existing engine, verified against it)

1. `offline.js snapshot()` **skips `file`, `password` and `hidden` fields**, so the check-in's hidden
   GPS/`device_at` and the photo inputs are **never persisted or restored** — only the visible text
   (note / caption / expense lines) is drafted. A stale GPS can never be replayed.
2. Restore is offered only via an explicit "Restore it" button, never auto-applied.
3. The offline **queue** path is skipped when a form has files present (`hasFiles`), so a check-in /
   evidence / voucher-with-receipt submitted offline is not wrongly queued — the user is told the
   typed text is kept and to resubmit when back online. Text-only voucher saves still queue-and-replay.
4. The evidence document input keeps accepting any document (PDF certs), not camera-only.
5. The draft is cleared on successful submit (existing `submit` handler).

## 3. Guardrails (green)

The manifest, service worker, offline queue engine, camera capture, GPS/geofence check-in, dictation,
and the report-fill autosave — all unchanged. No route, permission, schema or behaviour change;
nothing deleted. Purely three opt-in attributes.

## 4. Tests

`tests/test_module47_mobile.php` (10 assertions): the offline engine still wires `form[data-autosave]`
and its snapshot skips hidden/file fields; the report-fill autosave is preserved; the check-in,
evidence and voucher forms each now carry a per-record `data-autosave`; their live-upload/GPS paths and
form ids are intact; and the evidence input still accepts any document (not camera-only). Suite 3159
passing (only the 3 pre-existing baseline failures remain).
