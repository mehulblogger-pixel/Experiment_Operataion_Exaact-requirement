# Phase 5 — Completion Report

## Recruitment KPI, SLA & Performance Engine

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

## Total product change

**12 files, 1,007 insertions, 32 deletions** — of which 740 lines are the new
engine and its reasoning. The rest is screens being taught to ask it.

## One Phase 3 file was touched

`test_m3_multi_vacancy.php` located the raw `INSERT INTO candidate_events` in
`ops.php` and checked `reqf_sync` appeared within 700 characters. That INSERT no
longer exists, so `strpos` returned `false` and **the probe reported the absence
of a problem**.

It is re-anchored on the canonical writer and bounded by the branch itself rather
than by a byte count, and the claim is additionally proved end to end by driving
the real `candidate-stage` route in its own process. **No product code was
changed to make it pass.** It touches a Phase 3 file and is the owner's to review.

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

## What is NOT claimed

- **No concurrency evidence, because there is nothing to race.** Phase 5 adds no
  compare-and-swap, no compensator and no contended write; its only writes are
  ledger entries appended beside a change that has already committed.
- **`out_of_order` and `uncoded` counts are computed but not yet on a screen.**
  They belong in a data-quality view that does not exist yet.
- **No backfill of pre-Phase-5 stage codes.** The old display text is genuinely
  ambiguous — that ambiguity is what the codes remove, and inventing codes from
  it would bake it in while looking like a fix.
