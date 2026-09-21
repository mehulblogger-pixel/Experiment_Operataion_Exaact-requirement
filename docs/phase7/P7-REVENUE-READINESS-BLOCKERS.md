# Phase 7 · Revenue readiness — what is blocked, and why

*Read-only findings. Nothing in this document was "repaired automatically".*

The consolidated prompt sets out seven work items and, in §19, tells me to stop
and report rather than improvise if a new table or column turns out to be
necessary. Four items needed neither and are built. Three do, and are reported
here instead of guessed at.

---

## 1. `team_role` at the requisition — BLOCKED (needs one new column)

**What was asked.** The classification is to be decided at the requisition or
position, carried to acceptance, confirmed there, and never silently defaulted.

**What exists.**

| | |
|---|---|
| `inspectors.team_role` | `VARCHAR(10) DEFAULT 'FIELD'` — `lib/ops.php:419` |
| `requisitions` | **no such column** — full definition at `lib/ops.php:247` and every `ensure_column('requisitions', …)` in the codebase |
| `positions` | **no such column** — `lib/position.php:20` |

So there is nowhere to put the decision. The chain the owner drew —
requisition → team_role → candidate → acceptance → workforce — has no first
link. Today the only thing that decides the classification of a recruited
person is the **database default of `FIELD`**, which is precisely what the
locked decision forbids.

**Why I did not simply add it.** §19 lists "a new table/column appears
necessary" as a stop condition, and §2 says not to build a new classification
table. One column on an existing table (`requisitions.team_role VARCHAR(10)
NULL`, no default) is the smallest thing that can work and reuses the existing
FIELD / COORD / OFFICE vocabulary — but it is still a schema change, and the
decision is the owner's.

**What is needed:** approval of one nullable column on `requisitions`. Nothing
else. No new table, no new engine, no change to existing rows.

---

## 2. RB-1 — mandatory workforce creation — BLOCKED (depends on item 1)

Making a workforce record mandatory for every accepted candidate is
straightforward on its own. The problem is what that record would say.

Every workforce record carries a `team_role`. With the hidden checkbox removed
and no value coming from the requisition, every mandatory record would take the
database default — **FIELD** — and the system would begin classifying office and
coordination hires as deployable field inspectors, silently, at scale. That is
the exact failure the locked decision was written to prevent, and RB-1 without
item 1 would cause it rather than avoid it.

RB-1 is therefore ready to build the moment item 1 is approved, and unsafe
before it.

### A conflict inside the prompt, flagged rather than guessed

§4 names two flags as the "hidden checkbox" to remove: `dup_ack` and
`make_inspector`. These are not the same thing.

- **`make_inspector`** is the optional-workforce flag. It is what RB-1 is about,
  and it is correct to remove its power to suppress a workforce record.
- **`dup_ack`** is the duplicate-match acknowledgement tick built in RB-3 Step 2
  and **explicitly required by §7 of this same prompt**. Removing it would
  delete the safeguard §7 mandates.

I have read §4 as applying to `make_inspector` only. If that reading is wrong,
say so before RB-1 is built.

---

## 3. RB-2 — accepted is not joined — PARTLY DELIVERABLE, PARTLY BLOCKED

**The defect, located exactly.** `reqf_counts()` in `lib/reqfulfil.php:93`
returns a figure called `joined`:

```php
$out['joined'] = COUNT(candidates WHERE stage IN ('ACCEPTED') AND inspector_id IS NOT NULL)
```

That is not joining. It counts people who were **hired and given a workforce
record** — which is a fact about paperwork, not about anybody turning up for
work. Worse, once RB-1 makes the workforce record mandatory, this number becomes
**arithmetically identical to `filled`**, and any screen printing it would report
"10 of 10 joined" on the strength of ten acceptances. That is the false claim §8
forbids, already computed and waiting for a consumer.

Nothing currently reads it, so it can be corrected safely and at no cost.

**Deliverable without any schema change:** stop the engine from claiming
"joined" for something that is not joining.

**Blocked:** a genuine `Joined: 6` alongside `Accepted/Hired: 8` needs a real
signal that somebody actually joined. There is none:

- `CAND_STAGES` (`lib/ops.php:70`) ends at `ACCEPTED` — there is no `JOINED`
  stage.
- `candidates` has **no** joining, onboarding or start-date column (checked
  against every `ensure_column('candidates', …)` in the codebase).
- `inspectors` has no date of joining. The only joining date anywhere is
  `offers.joining_date`, which is the **expected** date written into the offer
  letter, not a record that the date was met.

**What is needed:** a decision on one of two smallest-possible options —

1. one nullable column, `candidates.joined_at`, set by an explicit "mark as
   joined" action; or
2. one new stage, `JOINED`, after `ACCEPTED`.

Option 1 is the smaller change and keeps `ACCEPTED` meaning exactly what it
means today. Option 2 touches `docs/03-object-lifecycles.md`, which the project
rules say may not gain a status without asking.

---

## 4. What is NOT blocked, and is built

| Item | Status |
|---|---|
| §9 employee-number integrity, including across tenants | **done** |
| §3 workspace capability classification | **not blocked** — the existing catalogue already answers the question (see below); wiring it is part of RB-1 and waits with it |
| §10 SQLite busy timeout | **done** |
| §11 demo unload safety | **done** |

### §3 is ready, and needs nothing new

`cockpit_capability_catalogue()` in `lib/setup_cockpit.php:62` already maps every
business activity to the modules it needs, and that mapping is exactly the table
in §3:

| Capability | Modules | Inspector concept |
|---|---|---|
| `TECH_RECRUITMENT`, `PERMANENT_PLACEMENT` | `hr` | **no** |
| `TECHNICAL_MANPOWER`, `CONTRACT_STAFFING` | `operations`, `hr` | yes |
| `TPIA` | `operations`, `reporting` | yes |
| `TECHNICAL_CONSULTANCY` | `operations` | yes where applicable |

A workspace is Operations-enabled when its chosen capabilities include the
`operations` module; `cockpit_capabilities()` returns an empty list when nothing
has been configured, which is the "unconfigured" case the owner said must never
be inferred from. No new capability engine is needed, and none was built.

It is not wired into acceptance yet because the only thing it would change is
whether a hire becomes an Inspector — which is RB-1, and RB-1 waits on item 1.
