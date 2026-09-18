# Phase 4 — Mutation Results

*Does the test suite actually detect a broken control, or does it only agree with
the code? Each mutation breaks exactly one control in a **copy** of the
application; the suite is then run and must fail. A mutation that survives is a
claim nobody is checking.*

**Discipline.** The harness aborts when the baseline is not clean, so no figure
below was measured against a failing suite. Every battery run uses its own
working directory and its own database — two runs sharing either produced a false
result earlier in this programme, and once during this phase. Every survivor is
investigated; **none is excused without evidence.**

Engine: **MariaDB 10.11.14** (authoritative). Suite: the five Phase 4 batteries.

**Final figure: 34 of 37 caught.** The three survivors are named and treated
below; none is counted as caught, and none is excused without evidence.

---

## The twenty mandatory targets (§57)

| # | Mutation | Result | Caught by |
|---|---|---|---|
| T1 | The allocation ceiling is removed | **CAUGHT** | M1–M3 (refused *before* the write) |
| T2 | The ceiling ignores people already found directly | **CAUGHT** | J2, J3, F12a, F12d |
| T3 | The create compensator never withdraws an over-allocation | **CAUGHT** | C8 (multi-round race) |
| T4 | AUTHORISED ignores cancelled vacancies | **CAUGHT** | L4–L9 |
| T5 | A source can be credited past its promise | **CAUGHT** | M4–M6 |
| T6 | The attach compensator never withdraws an over-credit | **CAUGHT** | C2.2–C2.4, C10 |
| T7 | An allocation can be cut below what it delivered | **CAUGHT** | G2, G3 |
| T8 | An allocation may be credited from another requirement | **CAUGHT** | E1, RT1.4 |
| T9 | The link compensator stops checking the requirement | **CAUGHT** | E3, E4, C6.11 |
| T10 | The candidate save stops running the link compensator | **CAUGHT** | RT1.8, RT1.10 |
| T11 | Entitlement — the licence is no longer asked | **CAUGHT** | S3.2–S3.10 |
| T12 | Permission — the coordinator band is removed | **CAUGHT** | S1.1–S1.8 |
| T13 | Branch scope — the actor's scope is not checked | **CAUGHT** | S2.1–S2.6 |
| T14 | The execution boundary — M6's gate is no longer asked | **CAUGHT** | S8.1–S8.4 |
| T15 | A quantity is coerced instead of refused | **CAUGHT** | C8–C12, S4 |
| T16 | An allocation id is coerced instead of refused | **CAUGHT** | S4 link, S4.24 |
| T17 | The source list accepts anything | **CAUGHT** | S4 source (7 probes) |
| T18 | The resize compare-and-swap is removed | **CAUGHT** | C12.3, C12.4 |
| T19 | The attach compare-and-swap is removed | **CAUGHT** | C11.3, C6.6b |
| T20 | A malformed stale expectation is ignored | **CAUGHT** | O1–O6, S6.6 |

## Further targets this phase added

| # | Mutation | Result | Caught by |
|---|---|---|---|
| T21 | Closing does not pin the allocation to what it delivered | **CAUGHT** | F4, F11, R3.10 |
| T22 | A closed allocation can be resized again | **CAUGHT** | F8 |
| T23 | The over-credit compensator displaces the **established** holder | **CAUGHT** | RT4.3–RT4.6 |
| T24 | The source entity need not exist in this workspace | **CAUGHT** | S5.1, S5.6 |
| T25 | A source behind an unbought module is allowed | **CAUGHT** | S5.7 |
| T26 | The resize pre-check mixes two moments again | **CAUGHT** | C4.4 |
| T27 | The stage route stops settling the source's ceiling | **CAUGHT** | RT3.6, RT3.7 |
| T28 | A closed allocation may still take people | **CAUGHT** | F9 |
| T29 | Refused operations are written to the ledger anyway | **CAUGHT** | N1–N6 |
| T30 | The create path smuggles a link past the door | **CAUGHT** | RT2.2 |
| T35 | A corrupt negative quantity is trusted into the total | **CAUGHT** | P1–P3 |
| T36 | UNALLOCATED is no longer clamped at zero | **CAUGHT** | J16, J17 |
| T37 | A status in no lifecycle is treated as open again | **CAUGHT** | P6 |

## Combined mutations — proving *which* control does the work

Four controls are guarded twice: a check before the write and a compensating
check after it. Breaking either alone can leave the suite green because the other
covers it. These prove the pairing is real rather than assumed.

| # | Mutation | Result | Failures |
|---|---|---|---|
| T31 | **Both** the allocation ceiling and its compensator | **CAUGHT** | 45 |
| T32 | **Both** the per-source ceiling and its compensator | **CAUGHT** | 46 |
| T33 | **Both** the resize ceiling and its compensator | **CAUGHT** | 9 |
| T34 | **Both** the link-ownership check and the link compensator | **CAUGHT** | 9 |

---

## The honest history of this battery

The figures above are the **final** run. They are not the first, and the
intermediate results are the point of doing this at all.

| Run | Caught | What the survivors meant |
|---|---|---|
| 1 | **23 / 34** | Seven genuine test gaps; four controls indistinguishable from their partner |
| 2 | **31 / 34** | After `test_p4_routes.php` and sections L, M, N, O. Two compare-and-swaps still survived |
| 3 | **32 / 37** | After the race harness was fixed to synchronise on a wall clock. Three race-dependent survivors, plus one equivalent mutant |
| 4 | **33 / 37** | After C8 and C9 were made multi-round |
| **final** | **34 / 37** | After the UNALLOCATED clamp gained a probe. Three race-dependent survivors remain, treated below |

---

## Every survivor, and the evidence for its treatment

No survivor was excused. Each was placed in one of the categories §57 demands,
with the evidence that put it there.

### Genuine test gaps — eight, all closed

These exposed claims **nobody was checking**. Each produced new probes.

| Mutant | The claim nothing was testing | Probes added |
|---|---|---|
| T10, T27, T30 | The three `ops.php` wiring points. Every probe called the **engine**; nothing drove the **routes** | `test_p4_routes.php` |
| T4 | Cancelling vacancies lowering the allocation ceiling | section **L** |
| T23 | The compensator's keep-list — who keeps a credit when a source is over-credited | **RT4** |
| T20 | A malformed stale expectation on the **create** path | section **O** |
| T29 | The ledger, asserted by count rather than by name | section **N** |
| T1, T3, T5 | Whether a refusal happens **before** the write or is written and withdrawn | section **M** |
| T36 | That "still to be sourced" reads **zero**, not a negative number, while over-committed | **J15–J18** |
| **T6, T18, T19** | The three contested-path controls. See `P4-MUTATION-VERIFICATION.md` | **C10, C11, C12** |

### Equivalent mutants — one, removed rather than left untestable

**T36 in its first form** mutated a second bound on UNALLOCATED that the
row-level clamp already guaranteed: the mutated code behaved identically.
Defensive code that cannot be reached cannot be tested or trusted, so the
redundancy was **removed** and T36 re-aimed at the clamp that does the work —
where it was then found to be a real gap (above).

### Independently protected — none

Earlier drafts of this document classified T6, T18 and T19 as *independently
protected*, and were internally inconsistent about it: the heading said **two**
while three mutants were listed beneath it. Both the count and the classification
were wrong, and the correction matters more than the tidy-up.

They were not protected. They **survived because the test harness was not
racing** — the workers paid a nine-millisecond one-time cost inside the window
they were supposed to be contending in, so eight processes released at the same
microsecond still arrived nine milliseconds apart. Once that was measured and
fixed (test-side only), all three were caught, and two of them turned out to
allow genuinely incorrect business states: an over-credit that persists, and
sources credited with people they never held.

The full evidence, the measurements and the §8 protection table are in
**`P4-MUTATION-VERIFICATION.md`**.

---

## The honest history of this battery

| Run | Caught | What the survivors meant |
|---|---|---|
| 1 | **23 / 34** | Seven genuine test gaps; four controls indistinguishable from their partner |
| 2 | **31 / 34** | After `test_p4_routes.php` and sections L, M, N, O |
| 3 | **32 / 37** | After the race harness gained a wall-clock barrier |
| 4 | **33 / 37** | After C8 and C9 were made multi-round |
| 5 | **34 / 37** | After the UNALLOCATED clamp gained a probe. T6/T18/T19 still survived |
| **final** | *(see the headline figure above)* | After the workers were warmed before the barrier, so the contested paths actually execute |

Reliability of the three formerly-surviving mutants, measured over three
independent batteries on a freshly restarted MariaDB — **9 runs, 9 caught, 0
survived, 0 dirty baselines**:

| Mutant | Round 1 | Round 2 | Round 3 |
|---|---|---|---|
| T6 | CAUGHT (26 assertions) | CAUGHT (26) | CAUGHT (26) |
| T18 | CAUGHT (1) | CAUGHT (1) | CAUGHT (2) |
| T19 | CAUGHT (4) | CAUGHT (2) | CAUGHT (2) |

T18 was caught every time but by only one or two assertions, and the internal
round that caught it varied — so `C12` was raised from two rounds to four. That
is more attempts at the same claim, not a weaker one.

## A dirty baseline — and a wrong diagnosis, corrected

An earlier reliability loop reported **`baseline: 3`** on four consecutive
batteries. Those runs were **discarded, not read as catches**, per the rule that a
dirty baseline means stop. That much was right.

**The diagnosis was wrong, and it was recorded in a commit message before it was
checked.** It was attributed to MariaDB thrashing its table cache after ~880,000
queries. It was nothing of the sort. When the failures were finally captured by
name rather than counted, they were:

```
C8.2 · round 3 · the requirement is NOT over-promised
C8.3 · round 3 · nothing is promised twice          (want 0, got 4)
C8.4 · round 3 · at most one of the six succeeded
```

A **real product defect**, and a serious one: the requirement over-promised by
four. See the completion report and `P4-MUTATION-VERIFICATION.md` for the cause
and the fix.

The lesson is recorded because it is the more useful half: **an intermittent
failure was explained away as environmental before its name had been read.** The
environment was a plausible story, the numbers supported it, and it was wrong.
A failure is not diagnosed until the failing assertion has been named.
