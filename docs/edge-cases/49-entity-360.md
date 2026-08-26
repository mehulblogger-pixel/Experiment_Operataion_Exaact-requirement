# Module 49 — Entity 360 Standard · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the same "what happened over time" panel exists, but only two records show it

The activity spine (`lib/activity.php` — `activities` table, `act_log()` writes, `act_for_entity()`
reads) is the shared "everything that happened to this record" store, and `act_render_timeline()`
(`activity.php:285`) is the drop-in panel Module 40 introduced. But it was wired onto **only two**
detail views (complaint, NCR), while **peers with the same spine data showed nothing**:

- **Opportunity 360** — the controller (`opportunities.php:904`) already **fetched**
  `act_for_entity('OPPORTUNITY', …)` and passed it to the view, and `opportunity_detail.php`
  **never rendered it**. Data fetched, thrown away.
- **Invoice 360** — `books.php` logs `CREATED/ISSUED/CANCELLED/CREDIT` to the spine, but
  `invoice_detail.php` had **no** activity panel at all.

Both `OPPORTUNITY` and `INVOICE` are already registered in `ACT_ENTITIES` and already receive
`act_log` writes, so surfacing them is a **pure render call** — no schema, no write-path, no
new-kind registration — exactly the complaint/NCR precedent.

**Noted, deferred (flagged, not built):**
- **Four incompatible timeline renderers** over one `activities` table (`act_render_timeline`,
  `tosrm_render_comms` on call/job, and the hand-rolled tables on lead + customer360). Consolidating
  them onto the one helper is a refactor of *working* screens (changes their output), so out of this
  additive scope — but new wiring standardises on `act_render_timeline`, the intended direction.
- **Candidate / Receipt** are `act_log`-ged under kinds **not** in `ACT_ENTITIES`, so their activity is
  silently unrecoverable — surfacing them would require *also registering the kind* (a write-path /
  enum change), so it is **not** purely additive; flagged.
- **Report 360** shows `idems_audit` but not its spine `REPORT` entries — a lesser duplication,
  skipped to avoid two overlapping history panels.
- **No universal Back/breadcrumb** and no shared 360 header/related-records component — a larger
  standardisation effort.

---

## 1. Built (additive; the shared helper, matching the precedent)

Wired `act_render_timeline()` onto the two peer detail views whose spine data was already present:
- **`opportunity_detail.php`** → `act_render_timeline('OPPORTUNITY', (int)$o['id'], 'Activity')`, after
  the existing "How it moved" stage-history fold (which is preserved).
- **`invoice_detail.php`** → `act_render_timeline('INVOICE', (int)$inv['id'], 'Activity')`, at the foot
  of the record.

Both calls are `function_exists`-guarded, so an install without the activity lib simply shows nothing.

---

## 2. Edge cases handled

1. The renderer shows a safe empty state ("Nothing recorded yet") for a record with no history — no
   crash, no empty panel confusion.
2. The existing opportunity stage-move history and every existing section are untouched — the timeline
   is added, not substituted.
3. The panel matches the complaint/NCR precedent (same helper, same look), so the "standard" is now
   consistent across five entities (complaint, NCR, lead, call, + opportunity/invoice).
4. Guarded against a missing activity lib.

## 3. Guardrails (green)

`act_render_timeline`, the activity spine, the complaint/NCR timelines, and both detail views'
existing content — all unchanged. No new permission; no schema change; no new activity kind; nothing
deleted.

## 4. Tests

`tests/test_module49_entity360.php` (11 assertions): the shared helper and spine exist; OPPORTUNITY and
INVOICE are registered kinds; both views call the renderer; an activity written to the spine round-trips
through the rendered panel (title + row shown); an entity with no history renders the empty state; and
the complaint timeline + the opportunity stage-history are preserved. Suite 3170 passing (only the 3
pre-existing baseline failures remain).
