# PHASE 3 · M6 — INTEGRATION MATRIX

What recruitment hands to the rest of the platform, and what M6 verified about
each. **No module was rebuilt and no semantics were changed.**

| Module | Relationship | M6 action | Evidence |
|---|---|---|---|
| **Operations** | protected; recruitment hands over a joined person through the existing `candidate-stage` → workforce spine | regression run in full; no Operations semantics touched | complete regression, both engines |
| **Workforce** | a joined candidate becomes a workforce record through the existing path; recruitment creates **no** duplicate person/inspector record | verified the joining path still runs through the one existing hand-off; the seat gate sits **before** it, so a joining that cannot happen never reaches Workforce | lifecycle L1–L2, regression |
| **Marketplace** | `cx_requirements` and `requisitions` remain **separate**, as the architecture lock requires | no mapping added, no merge; the sweep confirms nothing in the marketplace writes a recruitment lifecycle column | action-path sweep §B |
| **Reporting** | recruitment appears through the command centre and the CSV export | both now scoped by branch (M5) and reconciled against the records (M6) | reconcile R4, R5 |
| **Money** | recruitment feeds commercial figures through the requisition's own commercials (`expected_revenue` / `expected_profit`) | untouched by M6; no financial record is created or duplicated by the gate | regression |
| **Quality** | no relationship to the recruitment lifecycle | regression run | regression |
| **Identity** | candidate / person / inspector / marketplace professional remain **four separate records** with CONNECT/MAP relationships | no merge; `recruit.php`'s `person_ref` linkage is unchanged | action-path sweep §A |
| **Public careers** | optional, off by default, in front of `require_login()` | intake still enters the same candidate engine, inherits ownership through M5's door, and cannot hand work to a deactivated recruiter | M5 K1–K6 |

## The one integration defect M6 found between modules

The **approval engine** wrote requisition statuses that the **requisition
lifecycle** does not contain (`'approved'`, `'on_hold'`). A valid action in one
module produced an invalid state in another — the defect class M6 exists to find.
Measured: the requirement fell out of the command centre's open demand and the
execution gate refused all recruitment against a requirement that had just been
approved. Fixed by recording the decision where decisions live and leaving the
status to M3's `reqf_sync()`. Pinned by **L8**.
