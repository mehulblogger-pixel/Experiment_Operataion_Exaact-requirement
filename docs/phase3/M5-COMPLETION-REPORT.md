# PHASE 3 · M5 — COMPLETION REPORT
## Zero-sibling-path gap control: recruiter accountability

### 1 · What M5 is about, in business terms

Someone must be answerable for filling every requirement and for chasing every
candidate. EXAACT already recorded that — three columns, a dropdown on two forms —
but recorded it the way a notepad does: whatever arrived was written down. The
dropdown offered only active people; the save accepted anything. Nothing checked
that the person existed, was still employed, or covered that branch. Nothing wrote
down who used to be accountable. Two managers reassigning at the same moment meant
the last one silently won, and neither was told.

M5 turns accountability into a controlled act: **one door, every path, every
question asked at the write, and a permanent record of who held what and when.**

### 2 · Files changed

**New:** `phpapp/lib/recruit_assign.php` — the door, the ledger, the workload
counter. **Changed:** `lib/ops.php` (four save paths), `lib/careers.php` (public
intake), `lib/recruit_cc.php` (scope + one definition of live demand), `lib/db.php`
(boot migration), `index.php`, `views/ops/requisition_form.php`,
`views/ops/candidate_form.php`. **Tests:** `test_p3m5_assign.php`,
`test_p3m5_reconcile.php`, `test_p3m5_concurrency.php`, `tests/_m5_worker.php`.
`deploy-check.php` regenerated. Documents in `docs/phase3/`; `docs/02-permission-matrix.md`
updated in the same commit, as the repository rules require.

### 3 · Database changes

**One new table** — `recruiter_assignments`, append-only, two indexes. No column
added, altered or dropped. No status added; `docs/03-object-lifecycles.md` is
untouched. See M5-DATABASE-CHANGES.md.

### 4 · Permissions

**No new permission.** The door asks exactly the band the recruitment write routes
already require, and asks entitlement **first**, with no master bypass. What
changed is *where* the questions are asked — at the write, not implied by a
dropdown — and that three questions nobody was asking are now asked.

### 5 · The action path matrix (§ first requirement)

`M5-ACTION-PATH-MATRIX.md` lists every production path capable of changing
ownership, with the file and line that performs the write, and — separately — the
paths that were **checked and do not exist**: no bulk, no import, no AJAX, no API,
no background assignment. Their absence is recorded so that adding one later is a
visible decision rather than an accident.

### 6 · The business invariants (§ second requirement)

All thirteen (I1–I13) are defined in `M5-BUSINESS-INVARIANTS.md`, each with the
control that enforces it, the probe that proves it, and the business cost of its
being false.

### 7 · Data reconciliation (§ third requirement)

Every recruiter number is counted three ways — the dashboard, the workload counter
and raw SQL — and they agree. **Two defects were found by doing this**, both fixed:
a partly-filled requirement had been falling out of the dashboard entirely (0 open
positions where the records said 7), and the recruitment numbers and CSV export had
**no branch scope at all**. See M5-RECONCILIATION-RESULTS.md.

### 8 · The state matrix (§ fourth requirement)

Every requisition status, every candidate stage, and every M4 re-approval state is
tested — not one of them. See M5-ASSIGNMENT-STATE-MATRIX.md.

### 9 · Stale data / TOCTOU (§ fifth requirement)

A save carries the owner its screen was showing. If the column has moved since, the
save is **refused, not merged** (G1). A crafted POST that omits the baseline is
refused too — no baseline, no overwrite (G4). A POST that omits the field entirely
changes nothing; before M5 it silently unassigned the requirement (G6). And a
stale screen cannot outrun the M4 boundary either (H1).

### 10 · Create vs edit (§ sixth requirement)

Create and edit are literally the same code: a record is created **unowned** and
the owner is then set through the door. Probe **I** asserts that the two refuse
**identically** for an inactive person, an unknown person and a wrong-branch
person. The UI is not the control — every probe calls the production function with
the payload a crafted POST would carry.

### 11 · The negative matrix (§ seventh requirement)

Recorded in full in M5-ASSIGNMENT-STATE-MATRIX.md § D. Each refusal is asserted by
**its own code** — `RECRUITER_INACTIVE`, `OUT_OF_SCOPE`, `M4_BLOCKED`, `STALE`,
`LOST_RACE` and the rest — never merely "something refused".

### 12 · Test results

SQLite **10920 / 0** · MariaDB 10.11 **10921 / 0** (authoritative). M5 adds **189**
assertions. No test weakened, deleted or skipped. See M5-TEST-RESULTS.md.

### 13 · Concurrency results

**27 / 27** on both engines with real processes. **One real defect found and
fixed**: a losing process read the column, saw the person it had asked for — put
there by the winner — and reported success. Two winners, two ledger rows, one
actual move. See M5-CONCURRENCY-RESULTS.md.

### 14 · Mutation results

**Attempted 22 · Caught 22 · Survived 0**, against a clean baseline. One survived the first battery (M14 —
ownership back in a blind field list) and it was a genuine coverage gap, not an
excusable survivor. The answer was to stop the save paths trusting themselves: a
compensating check now puts any unauthorised ownership write back, audits it, and
does **not** record it as an assignment. Two further mutations (M16, M17) were then
written to attack that new protection; both are caught. See M5-MUTATION-RESULTS.md.

### 14b · Independent adversarial audit — run after the first verdict

The first ACCEPTED verdict was **premature and is withdrawn**. Attacking the
finished work found **four defects, three material**: an array posted into the
recruiter field became *user #1* (a person inferred from a type conversion); one
save could set a branch-A recruiter while moving the candidate to a branch-B
requirement, because the scope question was asked about where the record **is**
rather than where the save is **putting** it; the dashboard and the workload
counter used **two different scope rules**, so a candidate with no requirement was
counted by one and not the other; and the order of refusals let a caller with no
permission read off the current owner by guessing baselines. All four are fixed,
pinned in section **P** of the assignment suite, and covered by five new
mutations. See **M5-ADVERSARIAL-AUDIT.md**.

### 15 · The final adversarial question

> *Can I identify any other route, API, import, bulk action, background process,
> edit screen, or sibling workflow that can change recruiter ownership without
> passing the same control?*

## **NO** — and here is the inspection that supports it, not the fact that tests pass.

1. **Textual sweep of the whole repository** for the three ownership columns. The
   only remaining occurrences outside `recruit_assign.php` and the test suite are
   three `ensure_column()` calls (schema definition, `recruit_cc.php:22–25`), the
   form-designer's **label** metadata (`formdesign.php:124,178` — layout, not a
   write), a **read** of the advertised requisition in `careers_notify_recruiter()`,
   and the demo seeds, which are CLI-only and reachable from no route.
2. **Dynamic UPDATE builders** on those two tables — the only way a column can be
   written without its name appearing in the source — are three, and all three were
   read: the requisition save and the candidate save in `ops.php` (ownership
   removed from both field lists, **and** compensated after the write), and
   `compliance.php:812`, whose column list is the fixed `candidate_erase_fields()`
   and contains no ownership column.
3. **Mass assignment.** No handler iterates `$_POST` writing arbitrary column
   names. `custom_save()` writes to `custom_values` — a separate table keyed by
   entity and record — and cannot reach a column on `requisitions` or `candidates`.
4. **Bulk / import / AJAX / API / background.** The bulk framework's only adopter
   is `leads-bulk`; the only recruitment import is the org chart, which writes
   `positions`; the only recruitment AJAX route generates job-description text;
   `api.php` serves one action (`licence`) and never loads these paths; no cron
   task writes an ownership column.
5. **Defence in depth, for the paths nobody has written yet.** Even if a future
   save carried an ownership column, `rasg_enforce_table()` puts it back and audits
   the attempt. The conclusion above does not rest on this — but it means being
   wrong about it would be visible and reversible rather than silent.
6. **A test enforces the answer.** Probe **J7** sweeps `lib/` on every run: an
   INSERT or UPDATE naming an ownership column anywhere outside the door fails the
   suite. The answer cannot quietly stop being true.

### 16 · Known limitations, stated

1. **Pre-M5 ownership is not backfilled.** Rows written before the door existed may
   name people who no longer exist or are deactivated. `rasg_phantoms()` reports
   them with the reason; they are **not** silently cleared, because the value is
   the only surviving evidence of who was recorded. Cleaning them is a business
   decision, and I have not made it on your behalf.
2. **A finished requirement cannot be reassigned** (HIRED / CLOSED / CANCELLED). If
   you need to correct the owner of a completed requirement, that is a new rule and
   I will not add one without you asking.
3. **`hreq_to_requisition()` and the costing path create unowned requirements.**
   That is honest — nobody has been made accountable yet — and they appear in the
   unassigned count. Auto-assigning them would be inventing an owner.
4. **The public careers intake cannot ask a permission question** because there is
   no signed-in person. It skips that one question and no other.
5. **An unrecognised role maps to ADMIN** (`ua()`) — pre-existing, recorded in M4,
   not introduced or touched here.

### 17 · Exact remaining defects

**None known.** **Eight** defects were found by building this and then attacking
it, and all eight are fixed and verified: the two reconciliation defects (§7), the
concurrency defect (§13), the blind-field-list gap (§14), and the four the
adversarial audit found (§14b).

### 18 · Final verdict

Issued after the adversarial audit, its four fixes, a re-run mutation battery
against a clean baseline (22 attempted · 22 caught · 0 survived) and a complete
regression on both engines.

# M5 ACCEPTED
