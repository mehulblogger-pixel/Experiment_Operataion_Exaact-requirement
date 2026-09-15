# Phase 2 · M2 — The Multi-Vacancy Boundary

**Status: DOCUMENTED, DEFERRED. M2 was instructed not to fix this, and did not.**

M2 changed no part of the behaviour described below. This document exists so
the defect is recorded precisely enough to be fixed deliberately later, by
whoever owns that decision.

## 1. What the defect is

A requisition can ask for several people (`requisitions.quantity`). The
*counting* side handles that correctly. The *closure* side does not.

> **On `requisitions.quantity`.** M1 recorded that this column does not exist.
> It does — but it is created lazily by `req_migrate()` (`lib/recruit.php:44`,
> `INT DEFAULT 1`) the first time a requisition screen is opened, so it is absent
> from a fresh database and from the test databases. Both documents have been
> corrected; the full evidence is in `M2-QUANTITY-COLUMN-FINDING.md`. The defect
> described below is unaffected — if anything the column's existence makes it
> sharper, because a user really can ask for 10 people and still see the
> requisition close after the first hire.

When the first candidate is converted to a hire, `lib/ops.php` runs:

```php
UPDATE requisitions
   SET hired_inspector_id = ?, status = 'HIRED'
 WHERE id = ?
```

Two consequences for a requisition asking for, say, 5 people:

1. **`status` becomes `HIRED` after the first hire.** A terminal status is
   reached with 4 seats still open.
2. **`hired_inspector_id` holds one person.** It is a single column, so hires
   2–5 are not recorded there at all.

## 2. What still works

The seat arithmetic is independent of both fields and remains correct:

```
filled = COUNT(candidates WHERE requisition_id = r.id AND stage = 'ACCEPTED')
open   = quantity − filled
```

The Recruitment Command Centre uses this, so open-seat counts, demand and
recruiter productivity are right even while the status says `HIRED`.

## 3. Where it is visible

- Requisition lists and filters that key off `status`.
- Any report treating `HIRED` as "this vacancy is closed".
- `hired_inspector_id` as a record of who was hired — only ever the first.

## 4. Why M2 did not fix it

M2's scope is organisation and job structure. Its instructions were explicit:
do not implement the multi-vacancy fix, do not reinterpret `status='HIRED'`,
and do not change `hired_inspector_id` behaviour, vacancy quantity, fill count
or closure rules. All four are untouched, and the M2 test battery asserts that
`hired_inspector_id` and `position_id` are still present and unmodified.

A correct fix is also not a one-line change. It requires a decision on what
`status` should mean for a partially filled requisition (a `PARTIALLY_FILLED`
state, or deriving status from `filled` vs `quantity`), and a decision on
whether `hired_inspector_id` is superseded by the candidate records or kept as
"the first hire" for backward compatibility. Existing rows already carry the
old meaning, so any change needs a migration story.

## 5. Recommended shape of the eventual fix

1. Derive closure from the seat count rather than a manually set flag:
   `filled >= quantity` closes the requisition; `0 < filled < quantity` is
   partially filled.
2. Treat the `candidates` rows at stage `ACCEPTED` as the record of who was
   hired. Keep `hired_inspector_id` populated with the first hire so existing
   reports and documents keep working.
3. Migrate historical rows by recomputing status from the counts, with a
   dry-run report before anything is written.

No historical recruitment data should be deleted in the process.
