# Phase 5 — Completion Report

## Recruitment KPI, SLA & Performance Engine

---

# PHASE 5 — ACCEPTED / LOCKED

| | |
|---|---|
| **Date** | 2026-09-18 |
| **Commit** | `c63fa88` — *Phase 5 — test the screen, not just the engine* |
| **Branch** | `claude/testing-branch-setup-0gqe8n` |
| **Phase 5 battery** | **145 assertions, 0 failed** — SQLite and MariaDB |
| **Full regression** | **SQLite 12,252 passed, 0 failed** · **MariaDB 12,255 passed, 0 failed** |
| **Mutation battery** | **39 of 39 caught** — clean baseline, 0 survivors, 0 unapplied mutants |
| **Authoritative engine** | **MariaDB 10.11.14** for production-oriented evidence |

**No production deployment and no MilesWeb UAT is claimed.** This acceptance
records test, mutation, reconciliation, security and adversarial evidence on the
development branch. Deployment is a separate exercise with its own evidence.

---

## What Phase 5 delivers

A single measurable recruitment performance layer, built on the platform's
**existing** KPI / TAPI / Command Centre architecture. Each capability below is
proved by the evidence in `P5-TEST-RESULTS.md`, `P5-RECONCILIATION-RESULTS.md`,
`P5-MUTATION-RESULTS.md`, `P5-SECURITY-RESULTS.md` and `P5-ADVERSARIAL-AUDIT.md`;
nothing is listed that the evidence does not establish.

| Capability | Proved by |
|---|---|
| Recruitment demand measurement | A1–A13, B1–B4 |
| Approved headcount | A8, K4b · mutations P1, P34 |
| Cancelled demand | A7, K2 · mutations P5, P28 |
| Filled positions | A9, K1 · mutation P4 |
| Open positions | A10, K4 · mutations P2, P3 |
| Source promises / allocations | A11, K3–K3g · mutations P8, P39 |
| Not-yet-sourced demand | A12 · mutation P6 |
| Over-commitment detection | A13, K3g |
| Canonical ageing (calendar / business / SLA kept distinct) | C1–C8 · mutations P11, P12, P14 |
| Target-date lineage | D1–D6, L7 · mutations P10, P32 |
| Time to hire | H6 · mutation P13 |
| Stage movement ledger | E1–E11, J5–J7 · mutations P15, P16, P18, P22 |
| Stage duration measurement | G1–G3, K6 · mutation P19 |
| Revert handling | E9, E10, G4, G5 · mutations P17, P36 |
| Workflow-switch handling | G6 · mutations P20, P36 |
| Historical recruiter credit | F1–F6, K7–K7d · mutations P23, P24, P35 |
| Recruiter accountability measurement | K5–K5c · mutations P27, P28 |
| SLA measurement | registered as `hiring.approval.overdue`, read from the approval engine |
| Entitlement-aware metrics | H1–H5, L8b · mutation P29 |
| Branch-scoped metrics | H7, H8, K9, K9b · mutations P30, P31 |
| Tenant isolation | structural — one database per tenant, unchanged |
| Command Centre integration | K4–K4d, M0–M9 · mutations P9, P27 |
| Data-quality protection against corrupt and legacy states | K1, K2, L1–L5c, L7, L9 · mutations P37, P38, P39 |

---

## The architectural rule this phase exists to establish

**Phase 5 does NOT create a second KPI engine.**

It reuses and extends the existing **TAPI / KPI / Command Centre** architecture.
Recruitment metrics are registered in the analytics layer the rest of the product
already uses, inheriting its scoping, period filtering, units, documented method
per metric and its entitlement model.

**The Command Centre consumes the canonical KPI result** rather than maintaining
recruitment arithmetic of its own.

This is not asserted — it is proved. Probe **K4** asserts that the dashboard
figure **is** the engine's result, not that it matches it, so the two cannot be
edited apart. Mutation **P9**, which returns the Command Centre to its own
arithmetic, is caught.

---

## Cross-phase ownership

Phase 5 measures. It does not decide. Nothing an earlier milestone owns is
re-decided here, and **Phase 5 must never become a replacement for any of these
engines.**

| Owner | Owns |
|---|---|
| **M3** | The meaning of "filled" · approval / SLA engine foundations · stage and approval behaviour already locked |
| **M4** | The approval ceiling · the re-approval boundary · the hiring-request execution boundary |
| **M5** | Recruiter assignment and accountability · historical recruiter ownership and credit |
| **Phase 4** | Multi-source allocation and source-promise behaviour |
| **Phase 5** | KPI measurement · reconciliation · ageing · performance measurement · historical measurement · the dashboard presentation of those measurements |

Phase 5 **asks** each owner and presents the answer. Where it aggregates over many
requirements for speed, a reconciliation probe proves the aggregate equals the sum
of the per-record owners — it never substitutes its own rule.

---

## What was asked for, in business terms

A recruitment business runs on a handful of numbers: how many people are we
approved to hire, how many have we found, how long is it taking, who is
delivering, and what is late. Phase 5 was asked to make those numbers
**trustworthy** — one calculation each, the same answer everywhere, and never a
figure that looks like a measurement when it is really an absence of one.

## What the audit found before a line was written

The audit did not survey the code. It drove a fixture through the production
paths — **ten people approved, four vacancies given up, three joined, two seats
promised to an agency** — and asked the three engines the same question:

| | The records say | The Command Centre said |
|---|---|---|
| Approved headcount | **6** | 10 |
| Open positions | **3** | **7** |
| Promised to a source | 2 | *no such concept* |

**Seven open positions where the records said three**, on the one screen a
coordinator plans their week from. The dashboard had its own arithmetic and had
never learned that vacancies can be *given up*.

This is the same door M5 closed here once before, from the other side: that fix
corrected a stale status list, not the **ownership** of the sum — so the next
divergence arrived by a different route. Phase 5 moves the ownership.

## What was built

**One engine.** `lib/recruit_kpi.php` — one authoritative calculation per KPI. It
asks the modules that own the answers and recomputes none of them: M3 for what
"filled" means and what was requested and cancelled, M4 for the approved ceiling
(through its own layer, never its table), Phase 4 for what is promised to which
source, M5's ledger for who was accountable, the approval engine for SLA, and the
scheduling module for which days a branch works.

**No new dashboard, no new KPI engine, no new SLA engine, and no new tables.**
Recruitment metrics join **TAPI**, the analytics layer the rest of the product
already uses, and inherit its scoping, period filtering, documented method per
metric and — importantly — its entitlement rule: a company without *People &
hiring* is **withheld** a figure rather than shown a zero it might act on.

**Four additive nullable columns**, on an existing table, and nothing else.

## The one thing that had to be fixed first

Stage performance needs the candidate event ledger, and the audit measured three
reasons it could not be read:

1. **Issuing an offer** moved a candidate to OFFERED and recorded **nothing**.
2. **Reverting a joining** that had no seat left the ledger **ending at
   ACCEPTED** — it claimed a person had joined who had not.
3. One column carried **three vocabularies**: pipeline stage names, legacy stage
   codes, and literal `"Workflow: …"` strings.

Every stage movement now writes through one canonical writer, with the stage's
stable key beside its display name, the ladder it belongs to, and whether it was
a move, a revert or a workflow switch.

Note what (2) does *not* mean: today's time-to-hire reads `decided_at`, not the
ledger, and the execution gate correctly clears that stamp when it reverts.
**Nothing shipped was wrong.** The exposure was forward-looking — Phase 5 would
have been the first work to treat that ledger as authoritative.

## Who delivered this, and who is carrying it

The recruiter table answered *"who delivered these hires?"* from the **current**
recruiter column. That column cannot answer a question about the past: reassign a
requirement today and last month's performance table rewrites itself.

Delivery is now attributed from the M5 ledger at the moment each joining was
decided — and the attribution **states its own evidence**, because a number whose
provenance cannot be stated should not be on a screen. Where every recorded
change came *after* that moment, the first change names who held it beforehand;
without that, a record assigned before the ledger existed and reassigned today
would credit today's owner with yesterday's work — the exact rewriting the ledger
exists to prevent, arriving through an empty search result. Where the ledger has
nothing at all, the current column is the only evidence there is: it is used
**and labelled**, because refusing to read it would blank every recruiter's
figure on the day of the upgrade.

What remains genuinely unattributable is **shown as such**, never shared out
among the people who can be named.

## The defects this work found and fixed

Four, and **three of them were in my own new code**:

1. **The Command Centre's private arithmetic** — the seven-vs-three defect above,
   plus the "needs attention" list, the tracker, the project headcount and the
   recruitment home screen, all counting vacancies the business had given up.
2. **`rkpi_target()` read the `hiring_requests` table directly**, breaking the
   boundary that only M4's own layer may touch it. Caught by M4's existing suite.
3. **`rkpi_settled_rows()` cached its query in a static.** A caller reading after
   a write in the same process got the state from before it. In production each
   request is its own process, so this would have looked harmless for a long
   time; in one long-running process it was immediately wrong.
4. **The allocation sum counted only live allocations.** Closing an allocation
   pins it to exactly what it **delivered**, and those people have arrived: their
   seats are spent, not returned. Phase 4 had already found this, fixed it, and
   written the reason into the source above the line. This aggregate reintroduced
   it in a different query. Found by the mutation battery — the mutant that
   *deleted* the filter could not be caught, because deleting it was correct.

And one found by attacking the finished work: **a ledger whose timestamps run
backwards produced a negative stage duration.** It is now excluded and counted,
not clamped to zero — clamping would turn a data fault into a plausible
measurement.

## Evidence

| | Assertions | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|---|
| Phase 5 battery | 145 | 0 failed | 0 failed |
| Full regression | — | **12,252 passed, 0 failed** | **12,255 passed, 0 failed** |

Both were run to completion. MariaDB is authoritative for production-oriented
evidence; neither figure is inferred from the other.

**Mutation testing: 39 of 39 caught** — clean baseline, zero survivors, zero
unapplied mutants, and zero crashes counted as catches.

The first run caught **23 of 36**. Eleven survivors were gaps in my own probes —
including three that revealed **no probe read the dashboard at all**, so the
mutation making the Command Centre revert to its own arithmetic could not be
caught. One survivor was the real product defect above. Two of my own mutations
were broken: one anchored on text that appeared twice and was never applied, one
declared a cache and never assigned it, so it "survived" by doing nothing. An
unapplied or ineffective mutation reads exactly like a protected one.

## The screen itself was tested, not just the engine

The repository's UI rule is that a screen must be understandable without
training. That is a claim about the **words on the page**, so the Command Centre
is rendered in the suite and the HTML is read (section M): the corrected figures
are there, the sentence explaining why the approved number differs from the
original ask is there, the recruiter table separates *carrying* from *delivered*
and says which is which — and **none of the engine's vocabulary reaches a user's
eyes**. "Unallocated" and "over-committed" are accurate and useless to a
coordinator reading a screen in a hurry; the page says *"Nobody looking yet"* and
*"more people have been promised to sources than the approvals allow"*.

No new layout was invented. The new cards and columns reuse the existing
responsive primitives, so they collapse on a narrow screen exactly as the
shipped ones do. The Command Centre remains desk-first, per the repository's
phone-first / desk-first split.

## Recorded result — reconciliation

The defect Phase 5 was created to remove, measured through the production paths
before any code was changed:

| | The recruitment logic said | The old Command Centre said |
|---|---|---|
| Requested | 10 | 10 |
| Cancelled | 4 | *not read* |
| **Approved headcount** | **6** | **10** |
| Filled | 3 | 3 |
| **Open positions** | **3** | **7** |

The architecture is now corrected so that, for the tested population:

**M3 `reqf_counts()` = Phase 4 `rful_summary()` = Phase 5 KPI engine = Command
Centre.**

This is not a statement that figures were tested. **The dashboard consumes the
canonical KPI result.** Probe K4 asserts identity between the screen's figure and
the engine's; B4 re-checks every live requirement in the suite against Phase 4 on
allocation, not-yet-sourced demand and over-commitment; and mutation P9, which
returns the Command Centre to independent arithmetic, is caught.

Full detail in `P5-RECONCILIATION-RESULTS.md`.

---

## Recorded result — mutation

**39 of 39 mutations caught · 0 survivors · 0 unapplied mutants.**

Earlier runs are recorded because of what they exposed, and are **not** the
result:

| Exposed by an earlier run | Disposition |
|---|---|
| **No probe read the dashboard at all** — every assertion asked the engine, so the mutation returning the Command Centre to its own arithmetic could not be caught | Section K4 added; caught |
| **An allocation-state defect in this phase's own aggregate** — it counted only live allocations, so a closed allocation stopped counting the people it had delivered | Corrected to Phase 4's rule; probes K3d–K3g and B4 added; caught |
| **Ineffective and unapplied mutation definitions** — one anchored on text appearing twice so it never ran; one declared a cache and never assigned it, so it "survived" by doing nothing | Both re-anchored; both caught |

The final battery was run **from a clean MariaDB baseline**, against the exact
tree that ships. **Crashes were not counted as catches**: an intermediate batch
produced several results where the suite crashed rather than failed, which the
harness scores as a catch; those were discarded and the battery re-run. The final
figure contains zero crashes and every line of it is a named assertion failing for
a stated reason.

Full detail, including the catching assertion for each of the 39, in
`P5-MUTATION-RESULTS.md`.

---

## Recorded result — security

Verified principles, in full in `P5-SECURITY-RESULTS.md`:

- Recruitment metrics **inherit the existing Recruitment / TAPI entitlement
  model**; no second entitlement mechanism was created.
- **Unknown metric lineage results in NO DATA, not zero.** A zero is a number a
  manager acts on; an unlicensed figure must not be able to masquerade as one.
- **Tenant isolation remains structural** through the existing
  database-per-tenant architecture. No Phase 5 query crosses a connection.
- **Demand metrics use branch scope** — the same clause the registers use.
- **Recruiter credit uses the same candidate scope the Command Centre uses.**
- **A record ID is never treated as authorisation.** Every read is scoped by
  clause, not by whether the caller managed to name an id.
- **Browser-supplied tenant identity is not trusted** — there is none to supply.
- **Phase 5 is read-only** and cannot modify any recruitment business record.
  The worst outcome of any Phase 5 refusal is that a figure is withheld.
- **M4 hiring-request data is accessed through the M4-owned interface**
  (`hreq_get()`), not by reading its table.

---

## Recorded result — adversarial

The adversarial pass attacked the finished work with states no screen can create
and every real database eventually contains. It tested: **negative quantities ·
negative cancellations · negative allocations · foreign allocations · corrupt
dates · backwards dates · missing targets · invalid metric keys · missing lineage
· same-timestamp ledger events · the entitlement boundary · the dashboard route.**

One defect was found and fixed: **a backwards stage ledger produced a negative
stage duration.** Clock skew between two application servers, an import, or a
manual fix all produce this ordering.

The final behaviour:

- a negative duration is **not** converted to zero;
- the corrupt step is **excluded** from the measurement;
- the defect is **counted and reported** (`out_of_order`);
- the **valid surrounding measurements remain usable** — one bad row does not
  discard the rest.

Clamping to zero was rejected deliberately: it would convert a data fault into a
plausible measurement that a reader would believe. Now covered by probes L5–L5c
and by mutations P37 (restore the negative) and P38 (clamp instead of exclude).

Full detail in `P5-ADVERSARIAL-AUDIT.md`.

---

## Total product change

**12 files, 1,007 insertions, 32 deletions** — of which 740 lines are the new
engine and its reasoning. The rest is screens being taught to ask it.

## Phase 3 Test Maintenance Identified During Phase 5

**This is a test-maintenance / owner-review item. It is not a Phase 5 product
defect, and it does not reopen Phase 3.**

### What changed

Phase 5 changed the stage-movement implementation so that **production stage
movements use the canonical stage writer**, giving the ledger one vocabulary
instead of three.

### What that did to an existing Phase 3 test

`tests/test_m3_multi_vacancy.php` contained a **structural probe** that searched
`ops.php` for the literal

```
INSERT INTO candidate_events
```

and expected `reqf_sync` to appear within a fixed **700-character window** after
it.

That structural assumption became obsolete: the production code now routes the
operation through the canonical writer, so the string it searched for no longer
exists. `strpos()` returned `false`, and **the probe reported the absence of the
old implementation pattern as evidence that the defect was absent** — the most
dangerous result a test can give.

### What was done

- The probe was **re-anchored to the canonical writer**, and bounded by **the
  branch itself** rather than by a byte count, because a byte window has to be
  widened every time anything is inserted between the two points and each
  widening quietly weakens the probe.
- The underlying business invariant is **additionally proved end to end** by the
  Phase 5 test that drives the real `candidate-stage` route in its own process
  and reads the database (section J).

### Recorded explicitly

- **No product code was changed merely to satisfy the test.**
- **The business invariant remains enforced** — every stage movement still
  recomputes the requisition's standing.
- The Phase 3 test was maintained because **the architecture legitimately
  evolved**, not because it was inconvenient.
- **Phase 3 is not reopened.** No Phase 3 product behaviour was modified, no
  Phase 3 correction cycle is proposed, and the Phase 3 lock is unchanged. The
  only Phase 3-related action taken is this maintenance and its acknowledgement.
- It touches a Phase 3 file and is therefore **the owner's to review**.

## Backward compatibility

A workspace that upgrades and never opens a dashboard is unchanged. All schema
changes are additive, forward-only, idempotent and non-destructive. Existing
ledger rows keep their words and carry no stage code, which every reader treats
as NO DATA rather than guessing. Every pre-Phase-5 figure that was correct stays
correct; the ones that were wrong now agree with the records.

## Documents

`P5-PREIMPLEMENTATION-AUDIT.md` · `P5-KPI-DEFINITIONS.md` ·
`P5-BUSINESS-INVARIANTS.md` · `P5-ACTION-PATH-MATRIX.md` · `P5-TEST-PLAN.md` ·
`P5-TEST-RESULTS.md` · `P5-MUTATION-RESULTS.md` ·
`P5-RECONCILIATION-RESULTS.md` · `P5-SECURITY-RESULTS.md` ·
`P5-ADVERSARIAL-AUDIT.md` · this report.

## Known limitations — carried forward, not blockers

These are recorded as accepted limitations of Phase 5 as locked. **They are not
new development requirements** and nothing below blocks acceptance.

1. **No Phase 5 concurrency battery**, because Phase 5 introduces no contended
   business write, no compare-and-swap and no compensator. Its only writes are
   ledger entries appended beside a change that has already committed, and a
   failed ledger write is a failure to *observe*, never a failure to transact.
   Manufacturing a race in order to report one would be theatre.
2. **`out_of_order` and `uncoded` counts are available from the KPI engine but
   are not yet exposed through a dedicated user-facing data-quality screen.**
   They are returned to any caller; the right home for them is a data-quality
   view that does not exist yet.
3. **Pre-Phase-5 ledger rows may carry no stage code** and are reported as
   `uncoded` rather than guessed from ambiguous display text. No backfill is
   attempted: that ambiguity is precisely what the codes remove, and inventing
   codes from it would bake the ambiguity in while looking like a fix.
4. **The M5 literal `'ACCEPTED'` tidiness item remains unchanged.** It has no
   current behavioural difference — `REQF_FILLED_STAGES` is that single value —
   and it belongs to a locked milestone.

## What is NOT claimed

- **No production deployment.** No MilesWeb UAT. Neither is evidenced here, and
  neither is claimed.
- Nothing beyond the capabilities table above. Every capability listed there
  names the probes and mutations that establish it.
