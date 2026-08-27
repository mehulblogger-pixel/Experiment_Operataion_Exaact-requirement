# Slice P3 — Inspection Execution Polish (offline sync-state)

**Change-control record (directive Part 25). Classification: IMPROVE (UX polish;
front-end only, additive, non-destructive). Status: DELIVERED (P3a).**

Priority 3 in `03-target-architecture.md` §8; directive Part 8/9 (fast field
execution, offline-first, "never silently lose field data").

---

## 0. Revisit-trigger check (RT1 — `00-program.md` §9)

*Does the operational→commercial gap now dominate?* — **No.** P3 is field/offline
UX; the billing gap stays with P4. Proceeding with P3 as planned.

## 1. Existing state

The app already ships a real PWA offline stack (`assets/js/offline.js`, `sw.js`,
`manifest.php`): per-form **draft autosave** keyed by `data-autosave`, an
**offline submission queue** replayed on reconnect, and a bottom **connection
banner**. Phase-3 §47 already draft-protected the four field forms (report fill,
site check-in, evidence, voucher) — verified by `tests/test_module47_mobile.php`.

## 2. Problem (the gap)

The directive (Part 9) asks the field user to always know their data's state via a
**uniform vocabulary**: *Saved locally / Pending sync / Syncing / Synced / Sync
failed*. The old banner surfaced only two of these ("Offline — saving" and "Back
online — syncing"), and had two real gaps:
- **No "Saved locally" confirmation** — autosave was silent, so a field user on no
  signal had no sign their work was safe.
- **No "Synced" / "Sync failed" states, and a bug:** a send that failed *while
  online* (a 5xx) left the banner reading *"syncing…"* indefinitely and only
  retried on an `online` event that never fires when already online — i.e. it
  could silently stop syncing. That violates "never silently lose field data".

## 3. Solution (delivered — front-end only, one file)

All in `assets/js/offline.js` (loaded once from `layout_top.php`; pure
progressive enhancement):
- **One uniform vocabulary**, via a pure decision function `syncState()` (exposed
  as `window.idemsSyncState`): `OFFLINE` (saved on this device) · `PENDING`
  (n pending sync) · `SYNCING` · `SYNCED` (✓, shown briefly) · `FAILED` (⚠ with a
  **Retry now** button). Singular/plural wording handled.
- **"Saved on this device" chip** per autosave form, updated on each save (and
  labelled "(offline)" when there's no signal) — the missing *Saved locally*
  confirmation.
- **Failed-send fix:** a failure now sets an explicit `FAILED` state and retries
  on a **bounded backoff** (5s→15s→30s→60s) *and* on reconnect / tab-focus, with a
  manual Retry — instead of appearing to sync for ever.

The autosave snapshot still skips `hidden`/`file`/`password` fields (GPS + photos
are never persisted), forms with live file uploads are still never queued, and the
draft-restore prompt and service-worker registration are unchanged.

## 4–8. Impact

- **DB / routes / permissions / server code:** none. This is a single client-side
  file. No new status, table, permission, or route → `docs/02-permission-matrix.md`
  and `docs/03-object-lifecycles.md` need no change.
- **Dependencies:** the `data-autosave` contract and queue format are unchanged, so
  every already-protected form keeps working with no edit.

## 9. Regression & validation

- `node --check assets/js/offline.js` — syntax clean.
- **New JS unit test** `tests/js/sync_state.test.mjs` (runs the real file in a
  stubbed sandbox, no server): **11/11** covering all five states + wording.
  Run with `node tests/js/sync_state.test.mjs`.
- `tests/test_module47_mobile.php` (asserts on `offline.js` substrings) — **10/10**
  (the `form[data-autosave]` wiring and hidden/file skip were preserved verbatim).
- **Full PHP suite: 3782 passed, 0 failed.**
- **Coverage note (honest):** browser DOM behaviour (banner painting, the retry
  timer firing, the chip inserting) is not exercised by an automated end-to-end
  test — only the pure decision logic is. The DOM wiring was verified by
  `node --check` + close manual review; a Playwright smoke test could be added
  later if you want end-to-end coverage of the field flow.

## 10. Rollback

Revert the single file `assets/js/offline.js`; nothing else references the new
states. No data or server behaviour involved.

## RT1 re-check at delivery

The operational→commercial gap still does not dominate. **Next on the roadmap is
P4 (Billable Event ledger)** — the headline differentiator — unless you want P2b
(the gate-pass status, which needs your sign-off on its new lifecycle) or a
Playwright end-to-end pass for this slice first.
