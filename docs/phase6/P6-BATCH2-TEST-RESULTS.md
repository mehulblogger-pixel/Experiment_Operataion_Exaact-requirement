# Phase 6 · Batch 2 — Test results

*The battery, the baseline it was measured against, and what each section proves.*

**Battery:** `phpapp/tests/test_p6_batch2.php` — **78 assertions**, plus the
real-process worker `phpapp/tests/_p6b2_worker.php`.

**Written BEFORE the implementation**, against the code as it stood. Every
assertion states the required behaviour; none was adjusted afterwards to match
what the new code happened to do.

---

## 1. Baseline and final

| | Assertions | Passed | Failed |
|---|---|---|---|
| **Baseline** — the battery against **unmodified** code | 49 | **19** | **30** |
| **Final** — the same battery against the shipped code | 78 | **78** | **0** |

The 30 baseline failures are the defects this batch removes. The battery grew
from 49 to 78 because the mutation battery and the adversarial pass exposed
**five** places where a probe could have passed whatever the code did (§4).

## 2. Regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **12 460 passed · 0 failed** |
| **MariaDB 10.11.14** (authoritative, server restarted first) | **12 467 passed · 0 failed** |

Operations · Workforce · Recruitment · Marketplace · Reporting · Money ·
Dashboard/KPI all green, **including the locked Phase 5 KPI battery, numerically
unchanged**.

**No test was weakened, deleted or skipped.** No existing test file needed a
fixture change in this batch.

## 3. What each section proves

### A — one candidate, one conversion (BD3)

| | |
|---|---|
| **A1** | three simultaneous conversions of one application → **exactly one** team member |
| A2–A3 | the application is linked to it, and **no orphan** was left by the losers |
| A5–A7 | a sequential retry is refused; the original relationship is untouched |
| A8 | a stale browser POST after conversion creates nothing |
| **A9** | **no false success** — every process that reported success names the team member that really exists |
| **A10** | exactly **one** process reported success |
| A12–A13 | **no false failure** — a conversion that committed reports success, and the record it names is there |
| **A14–A16** | an actor without the recruitment right is refused **and creates nothing** |
| **A17–A20** | inside a caller's transaction a failure **reaches the caller**, and after the caller unwinds nothing was committed |

### B — branch resolution (BD1)

| | |
|---|---|
| B1–B2 | the team member takes the **requirement's** branch, not the actor's |
| B3–B4 | with no requirement branch it falls back to the **recruiter's** |
| **B5–B8** | with neither, the conversion is **REFUSED** — no team member, no partial relationship, and the refusal names the reason |
| **B9** | **nothing silently landed in Ahmedabad** |

### C — Connect ON / OFF (BD2)

| | |
|---|---|
| C1–C3 | **STATE A** — with the marketplace available: converted **and** the identity relationship recorded, and the ledger row really exists |
| **C4–C5** | **without** the marketplace the recruitment conversion **still succeeds** |
| **C6–C7** | **STATE B** — it says so, and **no false ledger row** was written |
| C8–C9 | the state is **reported** by the reconciliation report, so it can be linked later |
| **C10** | two simultaneous conversions with the marketplace **off** still produce exactly one |

### D — scope and the locked boundaries

| | |
|---|---|
| D1–D2 | an out-of-unit application cannot be converted, and nothing is created |
| **D3–D4** | **M4/M6 still refuse first** on a cancelled requirement |

### E — the ledger and the resolver

| | |
|---|---|
| E1 | the person resolver **reaches the team member from the application** — the edge that never existed |
| E2–E3 | the three axes stay distinct: a conversion link is not returned as either other kind |
| E4–E5 | one application may hold **both** a conversion link and a professional link (**I1**) |

### F — audit

F1–F3 a successful conversion writes an attributable, registered activity against
the application · F4 a **refused** conversion is audited too.

### G — person groups (BD4 · invariant I43)

| | |
|---|---|
| **G1–G3** | A+B, then C+D, then B↔C → **all four are one person; nobody is left behind** |
| G4 | the person link is audited |
| G5–G7 | the repair can report without changing anything |
| **G8a–G8e** | two intact, unrelated groups survive a **live** repair untouched — **including two records that share a mobile and an e-mail**. The repair is never fuzzy |

### H — detection only

H1 a dangling reference is detected · H2 the finding states *what*, *which
records*, *why*, *whether repair is safe* and *whether a person must look* ·
**H3 detection changed nothing.**

### W — no second writer

W1 only the recruitment layer writes `candidates.inspector_id` — **seeds
included** · W2 the conversion is a function that can be attacked directly ·
W3 the raw conversion INSERT is gone from the route.

## 4. Probes that were added because they were missing

Five assertions exist only because something proved the battery could pass while
the code was wrong. Recorded because this is the part of testing that is easy to
skip:

| Probe | Added because |
|---|---|
| **A9–A11** | mutant **M17** — a failed conversion could report success and nothing noticed |
| **A14–A16** | mutant **M10** — no probe converted as an unauthorised actor |
| **A17–A20** | the **adversarial pass** — a failure inside a caller's transaction left a committed orphan |
| **G8a–G8e** | mutant **M13** — nothing tested that an *unrelated* group survives a live repair |
| **W1** widened to seeds | mutant **M15** — a seed could write the conversion directly and W1 excused it |

## 5. Engines

Every assertion above ran on **both** SQLite and MariaDB. Nothing in this report
rests on SQLite evidence alone, and the concurrency sections used **real OS
processes with independent database connections**, never a sequential simulation.
