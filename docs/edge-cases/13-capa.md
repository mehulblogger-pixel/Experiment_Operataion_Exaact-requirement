# Module 13 — CAPA (configurable RCA) · Edge-case analysis (pre-build)

**Status:** edge-cases drafted — **awaiting your go + one decision (§4) before code.** P2.
Additive; the effectiveness gate and lifecycle are preserved.

---

## 0. Headline: the hard part already works — make the method configurable

CAPA is mature: `OPEN → IN_PROGRESS → VERIFYING → CLOSED` (+ `CLOSED_FAILED` → successor), a
**real effectiveness gate** (`capa_close_missing` requires `verified_on`; `capa_close_block`
refuses close when `effective='NO'`), a required "could it happen elsewhere?" preventive check
(`similar_checked`), owner + due at parent and per-action level, overdue clocks + reminders, and
auto-create from NCR and complaint. **The spec's headline — "effectiveness verification gates
closure" — is already real.**

**What's missing vs the spec:**
1. **Configurable RCA methods.** `CAPA_RC_METHODS` (FIVE_WHY / FISHBONE / INTERVIEW / DATA /
   OTHER) is a **hardcoded const**; the picker and the `capa-cause` validation both read it. The
   spec wants "let the organisation configure methods." The app already has the exact mechanism
   (`lk_ensure_type_map` / `lk_options_or` editable lookups — used by the adjacent NCDCA layer)
   — it's just not wired to CAPA.
2. **Structured RCA content.** Choosing FIVE_WHY or FISHBONE changes only the stored *label*;
   the cause is one free-text box regardless. No 5-Why ladder, no Fishbone categories.
3. **No unit tests** on the close/verify/escalate gates — a guardrail gap.

**Deliberately noted, not in the default scope:** corrective-vs-preventive column split, a
structured recurrence field (recurrence lives in the effectiveness note today), parent-CAPA
reopen. Those are schema/lifecycle changes.

---

## 1. Proposed additive layer (recommended = §4-A)

1. **Make RCA methods configurable** — `capa_rc_methods()` = `lk_options_or('capa_rc_method',
   CAPA_RC_METHODS)`, and seed that editable lookup from the const in `capa_migrate()` via
   `lk_ensure_type_map('capa_rc_method', 'Root-cause method (CAPA)', CAPA_RC_METHODS, 'capa')`.
   Wire the `capa_detail.php` method `<select>` **and** the `capa-cause` validation to
   `capa_rc_methods()`. Admins add/rename methods through the existing masters editor; the five
   defaults are seeded so nothing disappears; labels for historical values are preserved.
2. **Fill the gate test coverage** — behavioural tests for `capa_close_missing` /
   `capa_close_block` (RCA + method + similar + actions-done + verified required; `effective=NO`
   blocks close) and the escalate→successor flow. These are the guardrails I'd be building on.

Delivers "the organisation can configure the RCA methods" (the spec's explicit ask) plus
protects the effectiveness gate with tests — no schema change, no lifecycle change, no new
permission (reuses the masters/lookup infrastructure and `capa.close`).

## 2. Edge cases

1. **No lookup rows** → `lk_options_or` falls back to `CAPA_RC_METHODS` → identical to today
   (backward compatible).
2. **Seeded lookup** → the five defaults are present from `lk_ensure_type_map`; admins extend
   them; the picker always offers at least the defaults.
3. **Admin adds a method** → it appears in the picker and `capa-cause` accepts it (validation
   reads the lookup, not the const), so a saved custom method is not rejected.
4. **Admin renames/deactivates a method** → existing CAPAs that stored the old code still show
   their stored value; a re-save picks from the current list. Never a fatal on an unknown code.
5. **Historical `FIVE_WHY` value** → still labelled (the const seed keeps it in the lookup).
6. **`rc_method` still mandatory when a root cause is entered** → unchanged; the gate that a
   method must be recorded stays.
7. **NCDCA `rc_method` lookup is separate** (`5WHY`/`8D`/…) → I use a distinct `capa_rc_method`
   type so CAPA's codes (`FIVE_WHY`…) don't collide with NCDCA's; no cross-contamination.
8. **Un-migrated / lookup engine absent** → `function_exists` guards; falls back to the const.
9. **Close gate unchanged** → verification still required; `effective='NO'` still blocks close.

## 3. Guardrails (must stay green)

- `test_simplify_nonconformity` (routes/views resolve, nav tile gone) — untouched.
- `test_ncdca` (the `eff_result` column, QA "close major NC without effectiveness" flag) —
  untouched; I do not touch the NCDCA `rc_method` lookup.
- `test_tapi_domain` (capa effectiveness KPI on ncdca codes) — untouched.
- `capa_close_missing` / `capa_close_block` / verify gate / escalate — behaviour unchanged
  (and now covered by new tests).

---

## 4. OPEN DECISION — configurable methods only, or also structured RCA input?

- **(A) Configurable methods + gate tests (recommended, P2-sized):** wire the RCA method list to
  an editable, seeded lookup; add the missing gate tests. Small, on-spec ("configure methods"),
  zero schema/lifecycle change.
- **(B) Also add an optional structured 5-Why aid:** when the method is 5-Why, offer an optional
  five-line "why? → because…" ladder that composes into the `root_cause` text (Fishbone: optional
  category prompts). **Never forced** — the free-text root cause remains, and other methods are
  unaffected — honouring "do not force every CAPA through every method." More view work; the
  structured lines compose into the existing `root_cause` column (no schema change) or a small
  child store (schema change). I'd keep it to the compose-into-root_cause approach to stay
  non-destructive.

Default if you don't specify: **(A)** — make methods configurable and cover the gates now; the
structured 5-Why aid can be a follow-up.

## 5. Tests

1. `capa_rc_methods()` returns the seeded defaults; a lookup addition appears; empty lookup →
   the const (backward compatible).
2. `capa-cause` accepts a configured method value; the select is driven by `capa_rc_methods()`.
3. Gate coverage (new): a CAPA cannot close without root cause + method + similar-checked +
   an action done + verification; `effective='NO'` blocks close and escalate spawns a successor.
4. No new permission; NCDCA `rc_method` lookup untouched.
