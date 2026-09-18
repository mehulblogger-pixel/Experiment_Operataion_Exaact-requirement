# Phase 5 — Mutation Results

**39 of 39 caught.** Clean baseline, zero survivors, zero unapplied mutants.

Each mutation breaks exactly one control in a **copy** of the application; three
of them break a control **and its partner**, to prove the pairing is real rather
than assumed. The battery aborts if the baseline is not clean, because a battery
run against a dirty baseline measures nothing.

---

## What the first run actually found

| Run | Result |
|---|---|
| First | **23 of 36** · twelve survivors · one mutant never applied |
| Second | 34 of 36 |
| Final | **39 of 39** · zero survivors · zero anchor-misses |

Not one survivor was excused. Each was classified, and the classifications were
not what a reassuring report would say.

### Eleven were gaps in my own probes

The controls were all present and working. What was missing was any probe that
reached the state they defend against. The ordinary paths cannot produce those
states — but an import, a half-finished migration, a corrupted row, or two
servers whose clocks disagree all can.

Three of them exposed something worse than a missing case: **no probe read the
dashboard at all.** Every assertion asked the engine. The mutation that made the
Command Centre revert to its own arithmetic — the precise defect this phase
exists to remove — could not be caught, because nothing was looking at the
screen. Section K4 now asserts that the dashboard figure **is** the engine's,
not that it matches it, so the two cannot be edited apart.

### One was a real product defect, and it was mine

**P8** removed the live-status filter from the allocation sum and could not be
caught — because removing it was the **correct** behaviour.

The aggregate counted only *live* allocations. Closing an allocation pins it to
exactly what it **delivered**, and those people have arrived: their seats are
spent, not returned. Counting only live rows makes a requirement report people
joined against zero allocated.

Phase 4 had already found this, fixed it, and written the reason into the source
immediately above the line. This aggregate reintroduced it in a different query.

The probe that should have caught it released a promise that had delivered
**nobody** — whose pinned quantity is zero under both the right rule and the
wrong one. It compared two numbers that agree either way. It now delivers
somebody first, which is the only version of the case that can tell them apart.
And **B4** was added: every live requirement in the suite must agree with
`rful_summary()` on allocation, unallocated and over-commitment, so a rule that
is right for one fixture and wrong in general cannot pass again.

### Two of my mutations were broken

| Mutant | What was wrong | Why it matters |
|---|---|---|
| **P12** | Anchored on a fragment that appears in **both** ageing helpers, so it matched twice and was never applied | An unapplied mutant reads as the absence of a problem |
| **P33** | Declared a static cache variable and never assigned it, so the mutant was a **no-op** that "survived" by doing nothing | An ineffective mutant reads exactly like a protected one |

This is the same trap Phase 4 hit, and it is worth stating plainly: the most
dangerous result a battery can produce is a **confident pass for the wrong
reason**.

---

## The battery

| # | Mutation | Caught by |
|---|---|---|
| P1 | The approved ceiling ignores cancelled vacancies again | A8, A10, B1 |
| P2 | Open positions counted from the original ask | A10, B1 |
| P3 | Remaining netted **across** requirements | B1 |
| P4 | Filled no longer clamped to the requirement's size | K1, B1 |
| P5 | Cancelled no longer clamped to what was requested | K2 |
| P6 | Not-yet-sourced ignores direct arrivals | A12 |
| P7 | The live-demand list written out again instead of read from M5 | B1, K4 |
| P8 | A closed allocation stops counting what it delivered | K3d, K3e, K3f |
| P9 | The Command Centre reverts to its own arithmetic | K4 |
| P10 | No target date reported as **on time** | D5 |
| P11 | An unknown ageing basis silently becomes calendar days | C6 |
| P12 | A missing date ages from today | C4 |
| P13 | Time to hire over an empty period reports 0.0 | H6 |
| P14 | Working days stop honouring the branch calendar | C2, C3 |
| P15 | Issuing an offer stops recording the stage move | E3, E4 |
| P16 | A reverted joining leaves the ledger claiming a hire | E9, E10 |
| P17 | A revert recorded as an ordinary move | E10, G4 |
| P18 | The stage route records the move after the compensators again | J5, J6 |
| P19 | A duration measured straight across a revert | G4 |
| P20 | A workflow switch timed as a stage move | G6 |
| P21 | The two stage ladders averaged together | G7 |
| P22 | The pipeline stops recording the stage key | K6c |
| P23 | Historical credit read from the current recruiter field | F4, F5 |
| P24 | A record reassigned afterwards credits its new owner | K7b, K7d |
| P25 | An outcome with no date and two owners is guessed at | K8, K8b |
| P26 | Unattributable outcomes quietly credited to somebody | F2, K8c |
| P27 | The dashboard credits delivery from the current field | K5c |
| P28 | "Carrying" counts vacancies already given up | K5b |
| P29 | Metrics published without a lineage to check | H3 |
| P30 | The demand engine stops applying branch scope | H7, H8 |
| P31 | The credit query stops applying candidate scope | K9b |
| P32 | The target date read straight out of the M4 layer's table | K10, K10b |
| P33 | The settled set cached across writes again | K5c, K9b, K11 |
| P34 | **Both** the approval ceiling **and** the per-row clamp | A8, A10, B1 |
| P35 | **Both** the ledger attribution **and** the unattributed report | F1, F2, F4 |
| P36 | **Both** the revert record **and** the duration guard | G4, G5, G6 |
| P37 | A backwards ledger produces a negative stage duration | L5, L5b |
| P38 | A broken duration clamped to zero instead of excluded | L5b, L5c |
| P39 | A corrupt negative promise counted as written | L3 |

## A note on what counts as "caught"

A mutation is counted as caught only when the Phase 5 suite runs to completion
and **more assertions fail than at baseline**, and the catching assertions are
named above.

During one intermediate run the suite crashed rather than failing — the harness
scores a crash as a catch by adding a large number, and an earlier batch produced
several such results after repeated database churn. **Those were not counted.**
MariaDB was restarted and the battery re-run from a clean server; the final
figure of 39 of 39 contains no crashes, and every line of it is a named
assertion failing for a stated reason.
