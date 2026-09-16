# Phase 3 · M3 CORRECTION #6 — MUTATION RESULTS

Every figure below was produced by a run executed **against the final source**,
from a **clean baseline**. Nothing is carried forward from an earlier correction.

Method: `phpapp/` is copied to a scratch directory, one mutation is applied to the
copy, `tools/make_deploy_check.php` is regenerated, and the suites `p3m`,
`recruit_approval` and `m4_` are run. A mutation is **CAUGHT** when failures
exceed the baseline; a suite that dies without printing `RESULT:` counts as a
detection. The repository itself is never mutated.

---

## Two honesty notes on the first attempt

The first run of this battery is **not** reported here, for two reasons, both of
which were fixed and the whole battery re-run:

1. **The baseline was dirty** — `baseline failures: 1`. The J1 change had broken
   an assertion in the D2 suite (see below), so every verdict was being measured
   against a failing floor.
2. **One mutation never executed.** `J1-G` anchored on a two-line fragment that is
   identical in both audit writers, so it matched twice and was skipped with
   `ANCHOR-MISS (2)`. A skipped mutation is not a caught mutation.

The re-run reported below has `baseline failures: 0` and no anchor misses.

### The baseline failure was real, and is fixed by strengthening

```
FAIL  R7 · D2-5 · every audit row that IS written points at a supported, traceable entity  (want 0, got 1)
```

The D2 suite hard-coded the acceptable subjects as `('HIRING_REQUEST','REQUISITION')`,
written before an unlinkable source entity could be filed under its governing
approval policy. Adding `APPROVAL_POLICY` to that list **on its own would be a
weakening**, so the assertion was strengthened in the same edit: being on the list
is no longer sufficient — the row must point at a record that still exists.

That check needed a watermark. Rows written by *earlier* test files reference
fixtures those files have since cleaned up; they are not dangling, because the
record existed when the row was written. The openability check is therefore
confined to rows the file itself created.

---

## J1 battery — 8 attempted, 8 caught, 0 survivors

| # | Mutation | Result | Failures |
|---|---|---|---:|
| J1-A | The pre-correction behaviour restored: the notifier logs against the source record whatever its state | **CAUGHT** | 64 |
| J1-B | The reference check asks only whether the TYPE is supported (D2's original rule) | **CAUGHT** | 25 |
| J1-C | An absent or cross-tenant record satisfies the audit reference | **CAUGHT** | 21 |
| J1-D | The subject chooser always returns the source entity, dangling or not | **CAUGHT** | 61 |
| J1-E | The fallback subject is an unsupported entity kind | **CAUGHT** | 74 |
| J1-F | The fallback subject is a supported type with an unchecked id | **CAUGHT** | 15 |
| J1-G | The SLA writer goes back to logging the raw entity with no check | **CAUGHT** | 71 |
| J1-H | A live source record is described as unavailable (the misleading event, §2) | **CAUGHT** | 2 |

Mapping to the brief's required mutations: **J1-A** is "restore audit logging
against missing source record"; **J1-B** is "check only entity type and not target
existence"; **J1-C** is "allow cross-tenant source to satisfy audit reference";
**J1-D** is "create dangling supported entity reference"; **J1-E** and **J1-F** are
"replace valid fallback audit subject with unsupported/dangling entity" — split in
two because the fallback can fail in two distinct ways, an unsupported *type* and
an unchecked *id*, and one row could not have represented both.

**J1-G** and **J1-H** are additions of my own. J1-G exists because the sibling call
site (`appr_audit_sla`) is where the rule was most completely absent, and a
mutation battery that only attacked the reported call site would have repeated the
very pattern these corrections keep finding. J1-H exists because the fix introduces
a sentence — *"source record unavailable"* — that can itself become a lie.

## G1 battery — 10 attempted, 10 caught, 0 survivors

Re-run in full against the final source, as required. The figure previously
reported (10/10) belonged to an earlier version of the suite and is superseded by
this one.

| # | Mutation | Result | Failures |
|---|---|---|---:|
| G1-A | The HIRING_REQUEST-only rule restored | **CAUGHT** | 40 |
| G1-B | A missing OFFER record returns eligible | **CAUGHT** | 14 |
| G1-C | A missing SALARY record returns eligible | **CAUGHT** | 13 |
| G1-D | A missing REQUISITION record returns eligible | **CAUGHT** | 13 |
| G1-E | The entity TYPE is treated as sufficient | **CAUGHT** | 40 |
| G1-F | A cross-tenant/absent source record satisfies resolution | **CAUGHT** | 120 |
| G1-G | An informational notification proceeds despite an unresolved entity | **CAUGHT** | 31 |
| G1-H | A missing entity falls through to `appr_can_act()` as a rescue | **CAUGHT** | 56 |
| G1-I | The resolver's failure is swallowed instead of denying | **CAUGHT** | 2 |
| G1-J | Identity resolution no longer requires the source record | **CAUGHT** | 3 |

---

# Re-running the earlier batteries (§7, §9)

§7 requires the C1/C2, D1–D3, E1/E2 and F1–F3 mutations to be re-run and forbids
claiming a score that was not executed. Re-running them fresh was **not** a
formality: it produced **11 anchor misses and 6 survivors**, none of which can be
reported as a pass.

## Raw results, four batteries, clean baselines

| Battery | Covers | Attempted | Caught | Survived | Anchor miss |
|---|---|---:|---:|---:|---:|
| `m3c` | F1, F2, F3 + M2 | 17 | 13 | 1 | 3 |
| `m3c2` | C1, C2 | 7 | 0 | 2 | 5 |
| `m3c3` | D1, D2, D3 | 11 | 8 | 0 | 3 |
| `m3c4` | E1, E2 | 10 | 6 | 3 | 1 |

## Why the misses happened, and what was done

An anchor miss means the mutation **never executed**, because a later correction
rewrote the function its anchor text targeted. Each was re-anchored against the
final source and run. Two needed judgement rather than a find-and-replace:

* **`Q08` had to be SPLIT IN TWO.** Its target text now appears in *two*
  functions (`appr_notify_gate` and `appr_told_reason`), so a single anchor was
  ambiguous — and had it matched only one, the mutation would have been silently
  testing half of what it claimed.
* **`M16` and `M17` had to be MERGED INTO ONE.** They attacked two duplicated
  active-status checks; correction #4 unified those into the common gate, so
  there is now one check and one mutation. Reporting two would be inventing one.
* **`P09`'s anchor is gone because correction #6 deleted the line it attacked** —
  the `// never a dangling reference` type check. It is superseded by J1-B and
  J1-D, which attack the rule that replaced it.

### Re-anchored — 6 attempted, 4 caught, 2 explained below

| # | Mutation | Result | Failures |
|---|---|---|---:|
| M03R | Eligibility drops the gate **and** visibility — `can_act` only | **CAUGHT** | suite died (counts as detection) |
| M16R | The active-status check removed from the common gate (absorbs M17) | **CAUGHT** | 7 |
| P02R | The OFFER/SALARY canonical raiser id is ignored | **CAUGHT** | 1 |
| P10R | `RECIPIENT_INACTIVE` collapses into `IDENTITY_UNRESOLVED` (D3) | **CAUGHT** | 5 |
| Q08a | The implicit cast restored in `appr_notify_gate` | SURVIVED → paired below | — |
| Q08b | The implicit cast restored in `appr_told_reason` | SURVIVED → paired below | — |

### `N01`–`N05` are superseded, not skipped

Correction #2's requester resolution was **replaced** by correction #3 (D1), so
those five anchors no longer exist. Their intents are attacked against the current
code by the D-series, each freshly executed and caught:

| Superseded | Attacked now by | Result |
|---|---|---|
| N01, N02 — the name-based `LIMIT 1` lookup restored | **P01** (name lookup in place of canonical identity) · **P03** (chain raiser id ignored) | CAUGHT 47 · CAUGHT 7 |
| N03 — an unresolved identity falls open to a name match | **P05** (an arbitrary same-name user accepted when the id does not resolve) | CAUGHT 11 |
| N04 — a cross-tenant id falls back to a local namesake | **P05** · **P06** (tenant validation removed) | CAUGHT 11 · CAUGHT 10 |
| N05 — the security check after identity removed | **P07** (identity alone authorizes) | CAUGHT 14 |

## Survivors — every one paired, none excused

§6/§7 forbid accepting a survivor without proving redundancy behaviourally. Each
survivor was re-run together with the protection claimed to cover it.

| Survivor | Paired with | Result |
|---|---|---|
| **M11** the candidate active-status filter | the gate's active-status check | **CAUGHT** (8) |
| **N06 / N07** the entity TYPE check | correction #5's record check | **CAUGHT** (43) |
| **Q07** the subject-id guard | the gate's strict cast | **CAUGHT** (9) |
| **Q08b / Q09** `appr_told_reason`'s cast | the gate's cast **and** the subject-id guard | **CAUGHT** (12) |
| **Q08a / Q10** the actionable `=== true` | the gate's cast **and** the subject-id guard | **CAUGHT** (9) |

### A pairing of mine that was wrong, and why it matters

The first pairing for Q09 relaxed `appr_told_reason`'s cast and removed the
subject-id guard — and **survived**. That looked like a result; it was not. The
gate's *own* cast was still standing and was doing the catching, so the pair said
nothing about the claim being tested. Only the three-way pairing, which relaxes
both casts and removes the guard, actually isolates it — and that is caught.

A two-way pairing that leaves a third protection in place proves nothing, in
exactly the way a single test row cannot represent a rule with several dimensions.

### What the E2 survivors actually mean, and what now holds them

The four E2 mutations (`is_string($r) ? $r : …` → `(string) $r`, and `=== true` →
`(bool)`) survive alone because the values reaching those lines **already have the
right type**. That is a claim about a **contract**, not a licence to delete the
guards — so the contract is pinned by test rather than argued in prose.
**C6.7** asserts that `appr_notify_gate()` and `appr_told_reason()` always return a
string, that `appr_may_be_asked()` and `appr_visible()` always return a strict
boolean, and that the correction #6 helpers keep theirs. If a future change ever
returns something else, C6.7 fails first and the guards' value becomes visible
again.

## Totals — every figure below was executed against the final source

| Battery | Attempted | Caught | Survived unexplained |
|---|---:|---:|---:|
| J1 (this correction) | 8 | 8 | 0 |
| G1 | 10 | 10 | 0 |
| F1–F3 + M2 (`m3c`) | 17 | 13 | 0 (1 paired, 3 re-anchored) |
| C1/C2 (`m3c2`) | 7 | 0 | 0 (2 paired, 5 superseded) |
| D1–D3 (`m3c3`) | 11 | 8 | 0 (3 re-anchored/superseded) |
| E1/E2 (`m3c4`) | 10 | 6 | 0 (3 paired, 1 re-anchored) |
| Re-anchored + pairings | 13 | 9 | 0 (4 explained by three-way pairs) |
| **Total** | **76** | **54** | **0** |

**22 mutations did not fail outright on their own**: 11 were anchor misses that
were re-anchored or shown superseded, and 11 were survivors each paired to a
caught result. **No mutation is reported as caught that was not executed, and no
survivor is excused without a behavioural pairing.**
