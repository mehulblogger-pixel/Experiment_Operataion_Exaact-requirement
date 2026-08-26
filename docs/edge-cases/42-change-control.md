# Module 42 — Change control · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: change control is real but scattered, and one supersede fell off the sealed chain

There is **no dedicated change-control register**. Change control is *emergent*, done per object:

| Controlled object | Versioning | Sealed on the audit chain? |
|---|---|---|
| Report | reissue at a new revision (`report_docs.revises_id`) | ✅ `REVISED` / `REVISION_DRAFT` |
| Controlled document (§8.3) | `cdoc_supersede()` | ✅ `SUPERSEDED` |
| Method (§7.1) | `method_supersede()` | ✅ `REVISION_OF` |
| Quotation | `quote_revisions` (own change log) | — (own log, not the sealed chain) |
| **Decision rule (§7.4)** | `drule_supersede()` | ❌ **was the ONE supersede that never called `idems_log`** |

Two genuine, non-entangled gaps:

1. **A change to how conformity is decided** — the accept/reject criteria in a decision rule — was
   the single controlled change that did **not** land on the sealed trail. Every sibling supersede
   logs; this one silently did not. That is exactly the kind of change an assessor most wants to see
   on the tamper-evident chain.
2. **No consolidated "what changed and why" view.** The change events all exist, but scattered across
   five surfaces; nobody can answer "what controlled things changed this quarter?" in one place.

**Noted, deferred (not built — would be new state / heavier writes):** a true accreditation-**scope**
register; a generalised **impact-assessment** artefact per change; a formal change-request →
approval → implementation workflow object. These are deliberate future builds, flagged not silently
started.

---

## 1. Built (additive, read-only except the one-line seal, no access change)

1. **The seal fix.** `drule_supersede()` now calls
   `idems_log('decision_rule', $newId, 'REVISION_OF', ['field'=>rule_code, 'old'=>rule_code (#id),
   'reason'=>'supersedes decision rule …'])` — mirroring `method_supersede()` exactly. The old rule's
   `SUPERSEDED` state was already stored; this puts the *change itself* on the tamper-evident chain.
2. **`controlled_changes($days=90, $limit=200)`** — a consolidated, read-only list unioning: report
   reissues (`REVISED`), controlled-doc supersessions (`SUPERSEDED`), method revisions (`REVISION_OF`),
   decision-rule revisions (`REVISION_OF`) from the sealed chain, plus quotation revisions from
   `quote_revisions`. Each row: object type, reference, the change, who, when, and a deep link.
3. **`controlled_changes_count($days=30)`**.
4. A **"Recent controlled changes"** panel on the existing `/audit-log` screen (rides its
   `idems.audit.view` gate — no new route, no new permission).
5. **Tests:** the drule seal assertion added to `test_drules.php`; a new
   `test_module42_changecontrol.php` covering the aggregator across all five domains.

---

## 2. Edge cases handled

1. A decision-rule supersede now logs; the four already-logging supersedes are untouched.
2. The aggregator reads only events that already exist — it invents no state and writes nothing.
3. Each domain maps to a human label and a correct deep link; unknown entities fall back safely.
4. Rows are ordered newest-first across heterogeneous sources by ISO timestamp string.
5. Pre-migration / missing tables (`quote_revisions`, `idems_audit`) → each source is in its own
   try/catch, so a missing table yields an empty contribution, never a crash.
6. The `days` window bounds every source; the panel caps at 40 rows; the function caps at `$limit`.
7. Quotation reference shows `quote_no` + `R<rev>`; a decision-rule/method row shows its code, not a
   bare id — and never a document *number* that would leak beyond the audit surface.

## 3. Guardrails (green)

`drule_supersede`'s existing behaviour (new DRAFT revision + old→SUPERSEDED + carry-forward criteria)
is unchanged — only the log line is added. `ops_idems_audit`, the chain-verify banner (Module 29),
`method_supersede`, `cdoc_supersede`, `quote_revisions` — all unchanged. No new permission; no schema
change; nothing deleted.

## 4. Tests

- `tests/test_drules.php` — the decision-rule revision is now sealed on the chain (was the one
  supersede that did not log).
- `tests/test_module42_changecontrol.php` (18 assertions): the seal fix is present; the consolidated
  list surfaces method, decision-rule, controlled-doc and quotation changes; every row carries the
  view's fields; newest-first ordering; count matches the list; the panel is wired and the existing
  audit screen is preserved.
