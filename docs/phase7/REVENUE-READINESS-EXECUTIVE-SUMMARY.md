# EXAACT — Revenue Readiness · Executive Summary

Baseline `3f5bb75` · 2026-09-20 · audit and programme control only.

---

## A · Can EXAACT be sold today?

# NO — BLOCKED

Three specific, small, fixable defects — and the fact that **nothing has ever
been deployed to production.**

This is not a verdict about quality. The engineering underneath is strong: 12 839
assertions passing on both database engines, mutation-tested, tenant isolation
proven, entitlement fail-closed, and a public sign-up form that provably cannot
be used to enumerate customers. The product is closer to sellable than most
programmes are at this point.

It is blocked because **each of the three defects would be discovered by the
first customer, and each would look like a fundamental failure of the central
promise** — that recruitment and operations are one system whose numbers can be
trusted.

They are all small. None requires new architecture. All three live inside engines
that already exist and already work.

## B · Top blockers

| # | ID | Problem | Business consequence | Customer consequence | Revenue consequence | Security/data | Action | Priority |
|---|---|---|---|---|---|---|---|---|
| 1 | **RB-1** | Hire → Workforce is a checkbox hidden by default; a hired person may never exist in Workforce | Cannot be deployed, rostered or paid; found weeks later by absence | "We hired them. Your system lost them." | Undermines the core one-system claim | None — an omission, not corruption | **CONNECT** existing `rcv_convert()` | **1** |
| 2 | **RB-2** | Requisition reports "filled" when candidates were accepted but nobody joined | Manager stops recruiting against a gap that is still open | Headcount reporting overstates delivery | This is the number a manpower business sells | None — misleading, not corrupt | **EXTEND** — both numbers already exist | **2** |
| 3 | **RB-3** | Duplicate inspectors freely creatable, entirely undetected | One person splits across deployment, attendance, history, utilisation | Utilisation — what a TPIA business sells — is quietly wrong | Undermines the central reporting claim | **Invoice totals unaffected** — each job billed once | **EXTEND** `identity_state_findings()` (detection) | **3** |
| 4 | **UB-3** | No browser/HTTP smoke layer in the repository | Cannot prove a change did not break the journey | None directly | Slows every future release | None | **BUILD** (justified — nothing exists) | 4 |
| 5 | **—** | **No production deployment has ever been performed** | Every claim is unproven outside a test harness | First deploy is also the first test | Blocks any credible pilot | Unknown until proven | Deploy and smoke-test | **1 (parallel)** |

Only five. The list is short because the audit deliberately did not pad it.

## C · Must fix before the first customer

1. **RB-1** — hire must reliably reach Workforce.
2. **RB-2** — "filled" must not mean "accepted".
3. **RB-3** — duplicate inspectors must at minimum be *visible*.
4. **A production deployment must actually happen** and pass a smoke test.

**Q32 must be answered first.** Whether every hire becomes an Inspector, or
non-technical hires become Workforce without becoming Inspectors, determines the
shape of both RB-1 and RB-2. Building them before that decision risks building
them twice.

## D · Can be fixed during a controlled pilot

- **UB-1** — *preventing* duplicate inspectors (detection ships first)
- **UB-2** — surfacing joined-vs-accepted on the dashboard
- **UB-3** — the browser smoke layer (start it before the pilot, grow it during)
- **UB-4 / F-01** — one obvious start per role (Q31, reusing the existing engine)
- **CX-1** — unwrapped tables on inspector-facing screens
- **CX-2** — notification on the access-request queue

## E · Safe to defer

Person Hub and identity convergence · Q3, Q7, Q13 · R18, R21 · R23 taxonomy ·
Q1/Q2 organisation uniqueness · the 87 status vocabularies · terminology
half-adoption · route inventory. All recorded in
`docs/PROGRAMME/DEFERRED-BACKLOG.md` with a named trigger for reopening.

**The live-host workspace incident is a separate infrastructure/recovery issue,
outside Phase 7 scope.**

## F · Production status — exactly what is and is not proven

| Level | Status |
|---|---|
| Automated test proven | **YES** — 12 834 SQLite / 12 839 MariaDB, 0 failures, 510 files, mutation-tested |
| Local HTTP proven | **PARTIAL** — public registration only; ad-hoc, not in the repository |
| Local browser proven | **PARTIAL** — 18 assertions; ad-hoc, not in the repository |
| Production deployed | **NO** |
| Production smoke proven | **NO** |
| Customer UAT proven | **NO** |

## G · TPIA critical journey status

```
Requirement             PASS
Recruitment             PASS
Selection               PASS
Hiring                  FAIL      RB-1 — may never reach Workforce
Joining                 FAIL      RB-2 — "filled" does not mean joined
Workforce               FAIL      RB-1 + RB-3 — may not exist, or may be duplicated
Inspector               FAIL      RB-3 — no uniqueness of any kind
Deployment              NOT VERIFIED
Inspection              NOT VERIFIED
Report                  NOT VERIFIED
QA                      NOT VERIFIED
Timesheet               NOT VERIFIED
Expense                 NOT VERIFIED
Billing                 NOT VERIFIED
Dashboard               PASS (recruitment) / NOT VERIFIED (operations)
```

**NOT VERIFIED is not FAIL.** The Operations and Money modules pass 24 and 17
test files respectively and are protected. They were inspected structurally in
this action but **not traced end-to-end in a browser**, so this audit does not
claim them either way. Verifying that chain is the first job of the browser smoke
layer (UB-3).

## H · The honest summary

Four things are true at once, and all four matter:

1. **The foundations are unusually solid.** Fail-closed entitlement, structural
   tenant isolation, one approval engine, one KPI engine, database-enforced
   uniqueness where it was added, and a measured privacy property most products
   cannot state at all.
2. **The joins between finished parts are where it breaks.** All three blockers
   are handoffs, not features — which is exactly what the Phase 7 entry audit
   predicted.
3. **Nothing has been proven outside a test harness.** That is the largest single
   risk, and no amount of passing tests substitutes for one real deployment.
4. **The fixes are small.** Three blockers, all CONNECT or EXTEND inside existing
   engines, plus one deployment. This is weeks of controlled work, not a phase.

**Recommended sequence:** answer **Q32** → fix **RB-2**, then **RB-1**, then
**RB-3** → deploy to production and smoke-test → build **UB-3** alongside → then
pilot.

---

**RELEASE BLOCKERS → MUST FIX:** RB-1 · RB-2 · RB-3 · production deployment
**UAT BLOCKERS → FIX NEXT:** UB-1 · UB-2 · UB-3 · UB-4
**PILOT ITEMS → CONTROLLED:** CX-1 · CX-2 · CX-3 · CX-4
**DEFERRED → BACKLOG:** `docs/PROGRAMME/DEFERRED-BACKLOG.md`

**Awaiting the owner's implementation decision. Nothing has been fixed.**
