# PHASE 3 · M5 — TEST RESULTS

| Suite | SQLite | MariaDB 10.11 |
|---|---|---|
| `test_p3m5_assign.php` — the door, negative matrix, state matrix, create-vs-edit, TOCTOU, history, sibling paths | **127 / 0** | **127 / 0** |
| `test_p3m5_reconcile.php` — the nine numbers, dashboard vs records vs scope | **35 / 0** | **35 / 0** |
| `test_p3m5_concurrency.php` — real-process races | **27 / 0** | **27 / 0** |
| `p3m4` (the previous milestone, unchanged) | **171 / 0** | **171 / 0** |
| `recruit` (the whole recruitment module) | **282 / 0** | **282 / 0** |
| **Complete regression** | **10920 / 0** | **10921 / 0** |

No test was weakened, deleted or skipped. No skip was introduced. The MariaDB
total is one higher because one engine-specific assertion exists only there.
M5 added **189** assertions (167 at first delivery, 22 more pinning the adversarial audit's findings).

## What each section of the assignment suite proves

| Section | Question |
|---|---|
| A | the permitted case works, so every refusal below means something |
| B | the negative matrix — each refusal identified by **its own code**, never "something refused" |
| C | entitlement is asked **first**, and there is no master bypass |
| D | **every** requisition status and candidate stage, not one of them |
| E | the M4 execution boundary stays authoritative through assignment |
| F | history survives reassignment, and the audit spine sees every move |
| G | a stale screen is refused; a POST with no baseline is refused; a POST that omits the field changes nothing |
| H | a stale screen cannot outrun the M4 boundary either |
| I | create and edit refuse **identically** — there is no softer door |
| J | no sibling route writes ownership behind the door, proved by a repository-wide sweep |
| K | the public careers intake cannot hand work to somebody who has left |
| L | ownership nobody holds is **reported**, not silently cleared |
| M | a successful assignment means **this** process wrote it (deterministic pin for the race in C2) |
| N | a save that writes ownership behind the door is **put back**, told, and not recorded as an assignment |
| P | the four findings of the adversarial audit: an array or a typo is not a person, the scope question is asked about the **destination**, moving work across a branch under a stranded owner is refused, and the dashboard and the workload counter agree about a candidate with no requirement |

## Assertions re-pointed, and why — none weakened

| Assertion | Was | Now | Why |
|---|---|---|---|
| `C5 · concurrency` | "exactly one of the two wrote" | the final owner is one of the two asked for **and** the ledger is an unbroken chain ending where the column stands | the original was the right invariant for two callers with the **same** baseline (asserted by C1–C4) but wrong for a baseline-less service call, which legitimately moves from whatever it re-reads. See M5-CONCURRENCY-RESULTS.md |
| `J2 / J3 · sibling sweep` | a fixed window of source after an anchor | the **array literal itself**, from `[` to `]` | the first version matched my own new code on the next line, and a legitimate **read** of the advertised requisition in `careers_notify_recruiter()` |
| `J5b · compensators` | one total count | counted **per table** | a total is a number to guess, and a guessed number passes for the wrong reason |
| `C1–C3 · entitlement` | read `rasg_check()`'s body | read `rasg_may_touch()`, **plus** the order inside the writer, **plus** three behavioural probes (C6–C8) | the caller gate was split out when the adversarial audit showed the refusal order leaked the current owner; the pins follow the code and now assert more than before |

## Test defects of my own, found and reported

- **Three probes matched the wrong text** (J2, J3, J5b) and were rewritten to test
  the intended claim rather than discarded.
- **C5 asserted an invariant that does not hold for one of its two callers.**
  MariaDB exposed it; SQLite had hidden it behind write serialisation.
- **One mutation survived the first battery** (M14 — the candidate save writing
  ownership blindly again). It was a genuine coverage gap, not an excusable
  survivor: the probes read the field list's literal text while the mutation
  appended to the list at runtime, and no test can drive the route itself because
  it ends in `redirect()`, which exits. Both the product and the probes were
  changed — see M5-MUTATION-RESULTS.md.
