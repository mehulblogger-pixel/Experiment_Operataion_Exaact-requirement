# PHASE 3 · M4 — COMPLETION REPORT

### 1 · Implementation summary
An approved Hiring Request is no longer immutable and no longer silently mutable.
It can be changed by anyone with the edit right, and **what happens next depends on
what changed**, judged against the snapshot the approver actually approved. A
non-material change is allowed and audited; a material one invalidates the standing
approval, stops recruitment, and asks the **existing** approval engine for a new
decision. No second approval, SLA, notification, audit, requisition or candidate
mechanism was created, and no lifecycle status was added.

### 2 · Files changed
`phpapp/lib/hiringreq.php` (the engine), `phpapp/lib/ops.php` (three enforcement
points), `phpapp/tests/test_p3m4_reapproval.php`, `test_p3m4_security.php`,
`test_p3m4_concurrency.php`, `tests/_m4_worker.php`, and four re-pointed assertions
in `test_m4_hiring_request.php` / `test_m4_correction.php`. `deploy_check.php`
regenerated. Documents in `docs/phase3/`.

### 3 · Database changes
Four additive columns on `hiring_requests`; no table created, none dropped, nothing
rewritten. Idempotency measured: 38 columns before and after three repeat
migrations on both engines. See M4-DATABASE-CHANGES.md.

### 4 · Existing functions reused
`appr_start`, `appr_open`, `appr_match`, `appr_levels`, `appr_guard`, `appr_tick`,
`appr_sla_summary`, `hreq_appr_ctx`, `hreq_submit`, `hreq_cancel`, `hreq_in_scope`,
`scope_allows`, `can`, `licence_blocks`, `hreq_segregation_blocks`, `act_log`,
`reqf_counts/derive_status/sync`, `ensure_column`, `ops_ensure_schema`.

### 5 · Existing functions extended
`hreq_snapshot()` (gains raw comparable values, keeps the approver's words),
`hreq_apply_decision()` (captures the approved snapshot at the decision; decides
re-approvals through the same writer), `hreq_is_executable()` (weighs the
re-approval attribute), `hreq_remaining_qty()` (measures from the **approved**
figure), `hreq_to_requisition()` (ceiling re-checked after the insert).

### 6 · New functions — only where genuinely required
`hreq_reapproval_state`, `hreq_block_reason`, `hreq_req_block_reason`,
`hreq_approved_snapshot`, `hreq_approved_qty`, `hreq_material_diff`,
`hreq_require_reapproval`, `hreq_qty_guard`, `hreq_qty_enforce_after_write`.
Nine, each a single rule with one authoritative implementation.

### 7 · Material change
`HREQ_MATERIAL_FIELDS` is the one authority, matching the matrix. Comparison is
**current vs approved snapshot**, never vs the previous edit. Quantity is
asymmetric: an increase is material, a decrease is not, and **both are audited**.
`approval_required` is refused outright after approval — no re-approval route, and
it cannot be smuggled in beside a legitimate change (the whole save is refused).

### 8 · Re-approval
An atomic compare-and-swap moves the request to `REQUIRED`, so two savers cannot
both open a chain; the winner hands it to `appr_start()` through the same context
helper `hreq_submit()` uses. Where no rule matches, it stays `REQUIRED` and is
decided directly — identical to a first submission. Re-approval decisions run
through `hreq_apply_decision()`.

### 9 · Executable boundary
`hreq_is_executable()` weighs status **and** the re-approval attribute, and is now
**connected**: the audit found it had one caller in the product. It is asked at
requisition creation, requisition edit and candidate creation, **at the write**.

### 10 · Quantity ceiling
Enforced at creation (before **and** after the insert), on the requisition edit
path, and after deployment groups overwrite quantity — and measured from the
**approved snapshot**, so a material increase raises the typed figure but never the
authority.

### 11 · Security results
**65 / 65 PASS** on both engines: stale approval URLs, rejected-re-approval flips,
twelve crafted POST fields, real cross-tenant attack, eight branch-scope
operations, replay of creation / change / decision, the approval-requirement
bypass, entitlement and segregation. See M4-SECURITY-RESULTS.md.

### 12 · Concurrency results
**24 / 24 PASS** on both engines with real processes. **One real defect was found
and fixed**: on MariaDB two processes both took the last seat (11 against an
approved 10) because creation was check-then-insert. SQLite hid it by serialising
writers. See M4-CONCURRENCY-RESULTS.md.

### 13 · Mutation results
**Attempted 9 · Caught 9 · Survived 0.** Two survived their first run: **M6** was a
broken no-op mutation, and **M5** exposed a genuine gap in my own tests, closed by
M4.13. See M4-MUTATION-RESULTS.md.

### 14 · SQLite results
`m4_` **352/0** · scenarios **71/0** · security **65/0** · concurrency **24/0** ·
complete regression **10720/0**.

### 15 · MariaDB results
`m4_` **352/0** · scenarios **71/0** · security **65/0** · concurrency **24/0** ·
complete regression **10721/0**. Authoritative.

### 16 · Full regression results
Clean on both engines, including Operations, Quality, Reporting, Money, Workforce,
CRM, Marketplace, Recruitment, Dashboard, Approval, SLA and Notifications.

### 17 · M3 carried-forward items
**H1** and **A-2** discharged as CONNECT with gate evidence; **eleven carried
forward**; none reopened; nothing removed. See M3-CARRIED-FORWARD-INVENTORY.md.

### 18 · Known limitations
1. **The ceiling compensator is pessimistic.** Two racing writers may both revert,
   refusing an allocation that could in principle have succeeded. Never an
   over-allocation; the safe direction.
2. **Re-approval is per request, not per field.** Two material changes while one
   re-approval is open are covered by that one decision.
3. **The candidate boundary is enforced where a requisition is named.** A candidate
   created with no requisition has no approved request to enforce against — ADR-001.
4. **Direct requisitions (ADR-001) are unchanged.** No approved headcount is
   invented for a requisition with no hiring request.
5. **An unrecognised role maps to ADMIN** — pre-existing, recorded, not reopened.
6. **Public careers-page applications** were not wired to the boundary; they create
   candidates without a requisition, so item 3 applies.

### 19 · Exact remaining defects
**None known.** The one defect found by this gate — the last-seat race on MariaDB —
is fixed and verified.

### 20 · Final verdict

# M4 ACCEPTED
