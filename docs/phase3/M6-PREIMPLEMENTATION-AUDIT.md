# PHASE 3 · M6 — PRE-IMPLEMENTATION AUDIT

Read before any code was changed: the Phase-0 architecture lock, the M1–M5
completion and adversarial evidence, the permission matrix, the route map, and
the recruitment, Operations, Marketplace, Reporting, Money and Workforce
integration surfaces as they stand in the tree.

M6 builds no feature. Its question is the one no single milestone can ask:

> **Can a valid-looking action at one stage produce an invalid business state at
> another?**

---

## 1 · What M1–M5 each made authoritative

| Milestone | The authority it owns | The function that holds it |
|---|---|---|
| M1 | a hiring request is approved before anything executes | `hreq_*`, the approval chain |
| M2 | who may decide, and who may act for them | `appr_can_act`, delegation |
| M3 | SLA, escalation, notification; and **multi-vacancy counting** | `appr_tick`, `reqf_counts` |
| M4 | material change invalidates approval; the **executable boundary**; the **allocation ceiling** | `hreq_is_executable`, `hreq_req_block_reason`, `hreq_qty_guard` |
| M5 | recruiter accountability — one door, validated, audited, concurrent-safe | `rasg_assign` |

Each is sound in isolation. M6's finding is about **who asks them**.

## 2 · The seams, mapped from the code

Every place where recruitment *spends* an approved requirement:

| # | Execution path | Entry | Asked M4 before M6? |
|---|---|---|---|
| 1 | raise a requisition | `hreq_to_requisition()` | **yes** |
| 2 | edit a requisition | `ops.php` requisition save | **yes** |
| 3 | deployment-group quantity | `req_groups_save()` | **yes** (ceiling) |
| 4 | create a candidate | `ops.php` candidate save | **yes** |
| 5 | edit a candidate | `ops.php` candidate save | **yes** |
| 6 | advance a candidate | `candidate-stage` route | **yes** |
| 7 | **advance through the configured pipeline** | `recruitpipe_cand_goto()` | **NO** |
| 8 | **schedule an interview** | `iv_schedule()` | **NO** |
| 9 | **create / submit / approve / issue / accept an OFFER** | `recruit_offer.php` | **NO** |
| 10 | **record a joining** (the seat itself) | `candidate-stage` → `ACCEPTED` | **no ceiling at all** |

Paths 1–6 were connected by M4 and its adversarial audit. **Paths 7–10 were
never asked**, and paths 8–10 are the ones that commit the company to a person.

## 3 · What the first attack measured

Run against the accepted tree in a throwaway copy, changing no product code:

| Finding | Class | Measured |
|---|---|---|
| **F1** an offer can be created, submitted, approved, **ISSUED** and accepted on a requisition whose approval M4 has invalidated; the candidate is moved to `OFFERED` | **BLOCKER** | offer exists, `offer_issue()` returned *"Offer issued. Share the letter with the candidate."* |
| **F2** an interview can be scheduled on the same blocked requisition | **MATERIAL** | interview row created |
| **F3** **three people joined a two-seat requirement** | **BLOCKER** | authorised 2, joined 3 |
| **F4** interviews and offers run on a **CANCELLED** requirement | **MATERIAL** | both created |
| F5 the dashboard agreed with the records for the integrated fixture | HELD | — |
| F6 a candidate with no requirement (ADR-001) is still workable | HELD | — |
| **F7** my own probe for the pipeline engine was **vacuous** | **TEST-HARNESS DEFECT** | it asserted "the stage did not change" on a candidate an earlier probe had already moved to `OFFERED`; disclosed and re-probed |

Environment: clean baselines on both engines before the attack, so no finding is
an environment failure.

## 4 · The decision — REUSE / EXTEND / CONNECT, not BUILD

The architecture lock is applied literally:

- **REUSE** — M4 still owns approval and materiality; M3 still owns counting.
  M6 re-implements neither. A test asserts that the gate contains no materiality
  logic and writes no requirement of its own (`L7`).
- **CONNECT** — one composed question, `rexec_block_reason()`, asked at each of
  the four unguarded paths. It reports the first refusal from the **existing**
  authorities.
- **EXTEND** — the only genuinely new rule is the one nothing owned: **a joining
  may not take a seat that does not exist.** It is expressed through M3's own
  counter (`reqf_counts`), not a second counter.
- **BUILD** — nothing. No engine, no table, no status, no route, no permission.

## 5 · Why a gate and not four checks

Four independent checks is how paths 1–6 ended up protected and 7–10 did not.
One function, four callers, and a test that sweeps for a fifth caller that
forgot, is the shape that survives the next feature.

## 6 · Deliberate non-goals, recorded

- **`iv_record()` is not gated.** Writing down the outcome of an interview that
  has already happened advances nothing and destroys information if refused.
- **Offers are not seat-limited.** The business deliberately runs more offers
  than seats because offers are declined. Only the **joining** consumes a seat.
- **ADR-001 stands.** A candidate with no requisition has no approval to respect
  and no ceiling to apply; inventing a refusal there would stop ordinary work.
- **Marketplace `cx_requirements` and `requisitions` remain separate**, as the
  lock requires. M6 adds no mapping between them.
