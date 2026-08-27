# Slice P5 — Consolidation (vestigial-status readers)

**Change-control record (directive Part 25). Classification: REFACTOR (read-side
correction; additive, non-destructive). Status: DELIVERED (P5a). The riskier
consolidation items are staged and gated on your sign-off.**

`03-target-architecture.md` §8 P5; `02-gap-and-reuse-map.md` §3 (disconnects R9/R10,
§29 dual-truth, R4 contract door).

---

## 0. Revisit-trigger check (RT1)

Not applicable — packaging/cleanup, not the billing bridge (delivered in P4).

## 1. Existing state / problem

`docs/99-gaps-and-risks.md` R10: some statuses are **vestigial** — the app never
advances them, yet code reads them. The concrete harm: the ops-desk
`report_pending` metric (`tosrm_ops_metrics`, `lib/tosrm.php`) counted
`jobs.stage='REPORT_PENDING'`, a value nothing ever writes, so the dashboard tile
was **always 0** — a silently wrong number a coordinator relies on.

## 2. Solution (delivered — P5a)

Point the reader at the **real** lifecycle state. The Job lifecycle
(`docs/03-object-lifecycles.md`) runs on `closed_flag` + `report_approval` +
`invoice_raised`; "report pending" is a CLOSED job whose report is awaiting the
reporting manager's sign-off:

```
report_pending = COUNT(jobs WHERE closed_flag=1 AND report_approval='PENDING')
```

This is exactly the fix the code's own R10 note prescribed. The vestigial
**fields** (`jobs.stage`, `calls.status=CLOSED`, `report_docs.ARCHIVED`) are
**kept and still noted** — removing or advancing them would add transitions, which
is out of scope without your sign-off. Only the misleading *reader* was corrected.

Scope check on the other `stage` reads (all safe, left alone): every other
`stage` reference is either the real, actively-advanced `candidates.stage`
(recruitment), or a display-only select in contract-360 / CRM / portal job lists
(shows the mostly-`ALLOCATED` default — not a false count). The
`calls.status<>'CLOSED'` guards always pair with the real `op_status<>'CLOSED'`
check, so they are dead-but-harmless.

## 3–8. Impact

- **DB / status / permission:** none. No schema, no new/advanced status, no
  permission. `docs/02-permission-matrix.md` unchanged; `docs/03-object-lifecycles.md`
  unchanged (the Job lifecycle already documents `report_approval`).
- **Behaviour change:** the `report_pending` tile now shows a real, non-zero count
  when closed jobs await report sign-off. This is the intended correction of an
  always-0 bug, not a regression.
- **Docs:** `docs/99-gaps-and-risks.md` R10 updated (reader fixed; fields still noted).

## 9. Regression & validation

- `php -l` clean.
- New `tests/test_report_pending_metric.php` — **3/3**: a closed job with
  `report_approval='PENDING'` is counted; a closed+approved one is not; a job
  carrying the vestigial `stage='REPORT_PENDING'` but not closed is **not**
  counted (proving the metric no longer reads `jobs.stage`).
- **Full suite: 3864 passed, 0 failed.**

## 10. Rollback

Revert the one query in `tosrm.php`. No data touched.

## Staged (need your sign-off — deliberately NOT done here)

These consolidation items from `02` §3 change controls or financial truth, so per
the guardrails they wait for an explicit decision:
1. **R9 — cost dual-write.** Job-closure expenses and the inspector voucher both
   write the cost picture. Converge via a canonical read (the app already prefers
   `job_profit()` snapshots) — a data-model change to design carefully.
2. **§29 / §80 — financial dual-truth.** `jobs.invoice_amount` /
   `payment_received` (legacy snapshot) vs the books ledger. `finance_truth_unified`
   is ON; remaining legacy readers should be switched **only after** §29
   reconciliation proves parity — never before.
3. **R4 — contract second door.** `partner-add kind=contract` bypasses the
   PENDING→endorse→approve two-signature lifecycle. Routing it through the control
   touches an approval control — confirm before changing.
4. **Advance or drop the vestigial fields** (`jobs.stage` intermediates, etc.):
   either wire real transitions (adds statuses → needs sign-off) or drop the
   unused values (a lookup/schema change). Left as kept-and-noted for now.
