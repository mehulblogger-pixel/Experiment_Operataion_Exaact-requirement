# PHASE 3 · M5 — DATA RECONCILIATION RESULTS

The rule applied to every number: **dashboard figure = the underlying records =
within authorised scope**. Three ways of counting, one answer, or the number is
wrong. Suite `phpapp/tests/test_p3m5_reconcile.php`.

## The fixture, stated so nothing is guessed

Recruiter **A**, branch A, holds:

- a **10-seat** requirement with **3 people joined** (the lifecycle engine, not
  the test, moves it to `PARTIALLY_FILLED`), plus one candidate at `OFFERED`;
- a **4-seat** requirement with one candidate shortlisted 60 days ago, one just
  received and one rejected, and two interview rounds booked for the first;
- a **6-seat CANCELLED** requirement, which must count for nothing.

Recruiter **B**, branch B, holds a 7-seat requirement with one join. One
requirement and one candidate belong to nobody.

## The nine numbers

| Number | Expected from the fixture | Counted from the records | Result |
|---|---|---|---|
| Total assigned requirements | 3 (cancelled included — it was still assigned) | 3 | **PASS** |
| Active assigned | 2 (the cancelled one is not live demand) | 2 | **PASS** |
| Vacancies (seats) | 14 = 10 + 4 | 14 | **PASS** |
| Joins against live requirements | 3 | 3 | **PASS** |
| Open seats | 11 = 14 − 3 | 11 | **PASS** |
| Candidates | every candidate carrying this recruiter | equal | **PASS** |
| Active candidates | candidates − the rejected one | equal | **PASS** |
| Offers | 1 | 1 | **PASS** |
| Interviews | **2 rounds**, not 1 candidate | 2 | **PASS** |
| Overdue | 1 (waiting 60 days) | 1 | **PASS** |
| Unassigned | the one unowned requirement and candidate | equal | **PASS** |

And the completeness check that no number can hide behind:
**assigned + unassigned = every live requirement**, with nothing in between.

## Two defects this reconciliation found

### 1 · A partly-filled requirement had disappeared from the dashboard

The command centre's live-demand list was written out as a literal —
`status IN ('OPEN','PROPOSED','OFFERED','HIRED')` — and it predated
`PARTIALLY_FILLED`, the status **M3 added** so that a requirement for ten people
would stop closing on the first hire.

Measured consequence: a ten-seat requirement with three people joined fell out of
the query altogether. The dashboard reported **0 open positions** where the
records said **7**, the requirement vanished from the biggest-open-demand list,
and the recruiter carrying it disappeared from the performance table.

**Fixed** by naming the live states once, in `RASG_LIVE_REQ`, and having both the
dashboard and the workload counter read that one definition. Probes **R2.4**,
**R2.5**, **R2.6**; mutation **M13** restores the old literal and is caught.

### 2 · The recruitment numbers had no branch scope at all

`rcc_cand_where()` and `rcc_req_where()` built their WHERE clauses from the
screen's own filters and **nothing else**. Both callers — the command centre and
the recruitment **CSV export** — therefore showed every branch's candidates,
requirements and **recruiter names** to anybody who could open recruitment,
however narrow their office scope. The requisition register beside them has
scoped by branch since M14.

**Fixed** at the two builders, so both surfaces are covered by one change:
requirements scope with `scope_clause()` exactly as the register does; candidates
scope through the requirement they are worked against with `scope_office_clause()`
(whose deliberate *"no office means everybody"* rule keeps a candidate attached to
no requirement from vanishing for every branch at once) plus the module's existing
`recruit_sbu_clause()`. **No new rule was invented** — the existing ones are now
applied where they were missing. Probes **R3.1–R3.6**; mutation **M12** removes
the scope again and is caught.

## Reassignment arithmetic

| Check | Result |
|---|---|
| The previous owner's load falls by one | **PASS** |
| The new owner's rises by one | **PASS** |
| The total is unchanged — **no duplicated workload** (I6) | **PASS** |
| The previous owner remains attributable for the period they carried it (I12) | **PASS** |
