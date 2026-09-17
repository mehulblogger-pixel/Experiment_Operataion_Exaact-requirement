# M3 — CARRIED-FORWARD INVENTORY (input to M4)

Copied from the M3 acceptance gate. **M3 is not reopened.** An item is actioned in
M4 only where (a) M4 depends on it, (b) it becomes a real M4 security or
correctness defect, or (c) the M4 implementation resolves it naturally.

| ID | Description | M3 status | M4 relevance | M4 action | Reason |
|---|---|---|---|---|---|
| **H1** | `appr_act()` called directly still approves an orphan chain | open | **HIGH** — M4 creates re-approval chains through the same engine | **CONNECT** | M4 must not add a new way to reach `appr_act()` on an orphan. M4 will route re-approval through `appr_start()`/`appr_guard()` and assert the orphan case in its own suite. The underlying H1 fix stays in M3's backlog. |
| **H3** | `appr_sla_summary()` counts orphan chains | open | medium — M4 adds chains to that summary | **DEFER** | M4 changes no counting logic. Reopening it would restart the treadmill for a display defect. |
| **S2** | condition identity omits the decision result | open | low | **DEFER** | Unrelated to re-approval. |
| **S3** | a condition key never expires | open | low | **DEFER** | Unrelated. |
| **U2** | unindexed `cond_key` lookup where the optional index is absent | open | low | **DEFER** | A documented cost; M4 adds no new condition lookups. |
| **C-3** | the condition fingerprint is written to `body`, a display column | open | low | **DEFER** | Not reachable for these entity kinds; M4 adds no timeline panel for them. |
| **A-2** | a colliding record id stamps the wrong record | open | **HIGH** — M4 passes hiring-request and requisition ids across boundaries | **CONNECT** | M4 will never treat an id as proof: every M4 write re-reads the row and re-checks scope and ownership in the connected workspace. The M3-side fix stays deferred. |
| **A-3** | `STORED` survives a rollback | open | low | **DEFER** | M4 wraps no condition write in a transaction. |
| **A-4** | engines disagree on an over-long condition key | open | low | **DEFER** | M4 emits no new condition keys. |
| **A-5** | byte truncation mangles a non-ASCII condition key | open | low | **DEFER** | Same. |
| **A-6** | the loose-comparison mutation is unpinned | open | low | **DEFER** | Test-coverage gap in M3's own suite. |
| **A-7** | the column channel masks the write channel | open | low | **DEFER** | Diagnostic display only. |
| **L1–L8** | the eight documented limitations of the M3 condition store | open | low | **DEFER** | All are named, bounded and inert for M4. |

**Actioned in M4: two — H1 and A-2, both as CONNECT, neither as a reopening.**
M4 will prove it does not *create* a new instance of either. Everything else is
carried forward untouched.
