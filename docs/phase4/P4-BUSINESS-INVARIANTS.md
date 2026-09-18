# Phase 4 — Business Invariants

*Things that must be true of the data at all times, whatever anybody does, from
any path, in any order, from any number of processes at once. Each one names the
probe that would fail if it stopped being true.*

---

| # | Invariant | Proved by |
|---|---|---|
| **P1** | A source is never credited with more people than it was promised | D5–D6, R1 (all shapes), C2, C6.8 |
| **P2** | The promises together never exceed the approved headcount | B1–B6, R1, C1.2 |
| **P3** | A seat somebody has already filled directly cannot be promised to a source | J1–J7 |
| **P4** | An allocation belongs to exactly one requirement | E1–E4, S7.4, T8/T9/T34 |
| **P5** | An allocation is never cut below what it has already delivered | G1–G4 |
| **P6** | Closing an allocation keeps what it delivered and returns only the rest | F1–F6, F10–F12, R3.9–R3.10 |
| **P7** | A refused operation writes nothing at all | B2, B4, C13, D6, G3, H3, S1.2, S3.3, S4.8, S4.17 |
| **P8** | No figure can go negative | G6, R1/R2/R3 (`nothing went negative`) |
| **P9** | Every reported figure equals the rows it is derived from | The whole `$r4check` in the reconciliation battery |
| **P10** | Phase 4 never removes anybody from a seat | D8, C2.5, J10 |
| **P11** | An established credit is never displaced by an arriving one | C3.3–C3.5, T23 |
| **P12** | Entitlement is asked first, with no master bypass | S3.1–S3.13 (S3.9/S3.10 are the superuser) |
| **P13** | A record id is never proof of authorisation | S2.1–S2.6 |
| **P14** | Nothing is coerced into an identity or a quantity | S4 (19 probes), T15/T16 |
| **P15** | A malformed value never reads as "nothing" (which here means *clear the link*) | S4.24–S4.25, S6.6–S6.7, T20 |
| **P16** | A source entity must exist **in this workspace** | S5.1, S5.6, T24 |
| **P17** | A source behind an unbought module is refused, not merely hidden | S5.7, T25 |
| **P18** | A screen left open cannot overwrite what happened meanwhile | S6.1–S6.8 |
| **P19** | Sourcing never becomes a second requirement, and never writes the approved quantity | A11, A12, S7.1–S7.3 |
| **P20** | A requirement that may not execute may not be sourced — but its seats can still be given back | S8.1–S8.5 |
| **P21** | A refusal never describes a record the caller may not see | S9.1–S9.3 |
| **P22** | Under contention, never overfill and never displace (ratified rule, = Phase 3 I21) | C1–C6, and the whole concurrency battery |
| **P23** | The two ceilings and the one arithmetic come from **one** read, not two moments | C4.4, T26 |

---

## The invariant that had to be restated, and why that matters

§11 states the rule as **FULFILLED ≤ ALLOCATED ≤ AUTHORISED**. Read over *total*
fulfilled, that is not satisfiable and must not be forced, because a person found
directly (ADR-001) legitimately has no source. The honest statement is two lines:

```
SOURCED-FULFILLED  ≤  ALLOCATED  ≤  AUTHORISED          (P1, P2)
ALLOCATED + DIRECT-FULFILLED  ≤  AUTHORISED             (P3)
FULFILLED  ≤  AUTHORISED                                (M6 owns this, not Phase 4)
```

This is recorded rather than quietly corrected because the restatement was where
a **real defect** was hiding. Making the first line true of *sourced* arrivals
would, on its own, have made the probe pass and hidden the second line entirely —
and the second line is the one that stops a coordinator promising ten seats to an
agency when three people have already walked in. See `P4-ADVERSARIAL-AUDIT.md`.

## The one case where an invariant may be violated, on purpose

**P3 can be exceeded by a direct arrival landing after a promise was made.**
Phase 4 never refuses a joining — M6 owns that — and never removes anybody (P10),
so the only alternatives would be to displace a person or to silently cut a
supplier's promise. Both are worse. Instead the requirement reports itself
**over-committed**, the panel says so in plain words, and a person trims it
(J8–J14). Shown, never silently corrected.
