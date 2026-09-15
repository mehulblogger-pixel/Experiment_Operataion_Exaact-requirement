# Phase 2 · M4 — Terminology Lock

**Status: LOCKED.** This document is the definition of record. Nothing in it is
a proposal, and none of the three objects below is renamed, merged or replaced.

## 1. Why this document exists

Three different things in EXAACT can loosely be called *a requirement*. They
have different tables, different owners and different lifecycles. Until now the
screens did not always say which one they meant, and the word "Requirement" on
its own appeared in the navigation for one of them. That is the ambiguity this
closes.

## 2. The three objects

| Object | Table | What it is | Who owns it | Lifecycle |
|---|---|---|---|---|
| **Hiring Request** | `hiring_requests` | the business **ask** to recruit — "we would like to hire" | the requesting department, inside the customer's own workspace | DRAFT → SUBMITTED → UNDER_REVIEW → APPROVED / REJECTED / CANCELLED |
| **Recruitment Requisition** | `requisitions` | **approved demand being executed** — "this is approved, start sourcing" | recruitment, inside the customer's own workspace | OPEN → PARTIALLY_FILLED → HIRED / CLOSED / CANCELLED |
| **Marketplace Requirement** | `cx_requirements` | a demand a **client posts to the Connect network** | the posting client party | the Connect marketplace lifecycle |

A fourth table carries the word but belongs to a different domain entirely and
is never shown beside these: `site_doc_requirements` (identity / site access) is
a list of **documents a site demands of a person**, not a demand for people.

### The sentence that settles it

> A **Hiring Request** asks permission to recruit.
> A **Recruitment Requisition** is the approved work of recruiting.
> A **Marketplace Requirement** is a client asking the network for people.

## 3. What was explicitly NOT done

- **No new `requirements` table was created.** Asserted by test A7.
- **`cx_requirements` was not renamed.**
- **`requisitions` and `cx_requirements` were not merged.** They remain separate
  tables with separate lifecycles and separate owners.
- **No extra object was introduced to satisfy terminology.** The only new object
  in M4 is `hiring_requests`, and it exists because the request *stage* did not
  exist, not because of a word.

## 4. The rule for the user interface

> A bare, unexplained **"Requirement"** is never used as the NAME of an object
> on a recruitment screen.

Where a name is needed, one of the three above is used. This is enforced two
ways:

1. **`hreq_label()`** (`lib/hiringreq.php`) returns the locked word. For the
   execution record it honours the workspace's **own** wording — set through the
   existing terminology engine, Settings → Terminology — and qualifies it only
   when the chosen word is itself ambiguous. A workspace that calls a requisition
   a "Requirement" (the Recruitment agency wording pack does) sees
   **"Recruitment Requirement"**, never a bare one. A workspace that calls it a
   "Requisition" sees exactly that, unpadded. Terminology is still the
   customer's; only ambiguity is removed.
2. **Tests A1–A6** (`tests/test_m4_correction.php`) fail the build if a bare
   "New requirement" / "Save requirement" / "Edit requirement" / `>Requirement<`
   label reappears on the hiring request, hiring request list or requisition
   form screens, or if the navigation offers an unexplained "Requirements".

### What changed on screen

| Screen | Before | Now |
|---|---|---|
| Navigation | `Requirements` → /requisitions | the workspace's word for the requisition, qualified if ambiguous |
| Navigation | `New requirement` | `New hiring request` (governed path) **and** `New <requisition>` (direct path) — both visible |
| Navigation | *(hiring requests were not in the menu at all)* | `Hiring requests` |
| Requisition form | `New requirement` / `Save requirement` | named with the locked word |
| Project costing → recruitment | `Requirement <code> created…` | named with the locked word |
| Requisition detail | *(nothing said where it came from)* | says whether it came from a hiring request or was raised directly |

## 5. Wording still owned by the customer

The Recruitment agency wording pack (`TERM_PACKS['recruitment']`) still renames
the requisition to "Requirement" if a customer picks it. That is deliberate —
M3's principle was to *standardise identity, not force terminology*. The
ambiguity is removed at the point of printing, by `hreq_label()`, not by
overruling the customer's vocabulary.

**Residual, stated plainly:** screens written before M4 that print `T('requisition')`
directly still show that customer's own word without the qualifier. They are not
ambiguous *within* recruitment; they only become ambiguous beside the Connect
marketplace. Extending `hreq_label()` across the older recruitment screens is
listed in `M4-REQUISITION-PATHS.md` as a Phase-3 tidy, and is deliberately not
done here — this correction was not to make M4 bigger.

## 6. Where these words must be used

- All Phase-2 and Phase-3 documentation.
- All new screens.
- Any message a person reads: flashes, errors, e-mail, exports.
