# Phase 3 · M3 Correction #5b (cross-tenant strengthening) — ADVERSARIAL AUDIT

Scope: commit `2279fa6` — the C5.7 two-tenant section added to
`tests/test_p3m3c5_entity.php`, and the documentation updated with it.

Method: every claim made in the completion note was re-tested by constructing the
condition that would falsify it, never by re-reading the source.

**One MEDIUM product defect, three LOW findings.** The H1/H2/H3 findings from the
previous audit remain open and untouched; this audit adds to them.

---

## What held up

### The second workspace is real — proved by destroying it

The claim is that tenant B is a genuinely separate database and that tenant A's
refusal is isolation rather than a bad id. **Probe:** point the child process at
tenant A's own database, change nothing else.

```
RESULT: 200 passed, 25 failed
  FAIL  C5.7 · tenant A's approval chains do not exist in tenant B  (want 0, got 4)
  FAIL  C5.7 · tenant A's approver and raiser do not exist in tenant B  (want 0, got 2)
  FAIL  C5.7 · OFFER · informational DENIES — ENTITY_UNRESOLVED, not a leak  (want 'ENTITY_UNRESOLVED', got '')
  FAIL  C5.7 · OFFER · nobody is asked about another workspace's record  (want 0, got 1)
  FAIL  C5.7 · OFFER · nothing was sent  (want 8, got 9)
  … 20 more
```

The section collapses when the second workspace is not separate, so it is not
decoration. It also states plainly what the boundary is holding back: with the
record reachable in the same database, `OFFER`, `SALARY` and `REQUISITION` become
**fully eligible** — reason `''`, one recipient found, and an e-mail actually
sent. Nothing inside the approval engine objects. The workspace boundary is the
whole of the protection, which is what "isolation is structural" means, now shown
rather than asserted.

`HIRING_REQUEST` came back `RECIPIENT_OUT_OF_SCOPE` instead — the first sign of
J3 below.

### The audit-row expectation is genuinely two-valued

The corrected expectation (one row where the timeline can link the entity, none
where it cannot) was checked in both directions and fails if either half is
wrong. It is not a widened tolerance.

---

## 🟠 J1 · MEDIUM · Every `ENTITY_UNRESOLVED` audit row is itself a dangling reference

`appr_audit_notify()` carries this guard, added by correction #3 (D2):

```php
if ($entity === '' || !array_key_exists($entity, ACT_ENTITIES)) return;   // never a dangling reference
```

That is a check on the entity **TYPE**. The most common reason the function is
called at all is `ENTITY_UNRESOLVED` — which means, by definition, that the
**RECORD** does not exist. A supported type is written; an openable target is not.

**Probe.** After the correction #5 suite, every row the function wrote was tested
against its own source table:

```
rows written by appr_audit_notify: 4
    HIRING_REQUEST   #1         -> RECORD DOES NOT EXIST
    REQUISITION      #1         -> RECORD DOES NOT EXIST
    HIRING_REQUEST   #99400001  -> RECORD DOES NOT EXIST
    REQUISITION      #99400004  -> RECORD DOES NOT EXIST
  rows whose target cannot be opened: 4
```

**4 of 4.** Not an edge case — the *only* outcome for this reason code.

In the product this is reachable through H1's own scenario: a hiring request with
a live approval chain is deleted, a decision is attempted, and the activity
timeline gains a permanent entry — *"Decision not notified (ENTITY_UNRESOLVED) —
the source record is unavailable — Hiring request #<id> APPROVED"* — carrying a
link built from `ACT_ENTITIES`, which opens nothing.

**Why my own test did not catch it.** `C5.5` asserts "no dangling audit reference
was created along the way" and measures:

```php
WHERE (entity_kind IS NULL OR entity_kind='') AND subject LIKE '%Decision not notified%'
```

A blank **type**. The assertion was written against the same conflation it was
meant to police, so it passes while every row it is guarding is unopenable.

**This is the G1 pattern one layer further in.** G1 was *supported type ≠ resolved
record* in `appr_visible()`. J1 is the same sentence in `appr_audit_notify()`.
Correction #5 carried the record check into every entity of the notification
layer; the audit layer still asks the type question — which is the shape the last
audit predicted and H2 also sits on.

Not fixed. It belongs with H1/H2/H3 in one correction, not as a separate patch —
the same "when a record can stop existing, every layer that reads it needs the
same answer".

---

## 🟡 J2 · LOW · The teardown assertion is a tautology on the production engine

```php
t_ok($bFile === '' || !is_file($bFile), 'C5.7 · tenant B\'s database is torn down');
```

On MariaDB `$bFile` is `''` — tenant B is a **database**, dropped with
`DROP DATABASE` — so the expression is `t_ok(true)`. The suite reports a pass for
a teardown it never checked, on the engine that matters. I confirmed the drop by
hand (`SHOW DATABASES LIKE 'exaact_c5b%'` returned nothing), but hand-checking is
not evidence the suite carries.

Fix: assert the real condition per engine — file absent on SQLite, schema absent
in `information_schema.SCHEMATA` on MariaDB.

---

## 🟡 J3 · LOW · One column of the C5.7 matrix is proved by a different mechanism

Under the core G1 mutation (record check removed from `appr_visible()`), C5.7
fails for three entities and **not** for `HIRING_REQUEST`:

```
FAIL C5.7 · OFFER · actionable DENIES
FAIL C5.7 · SALARY · actionable DENIES
FAIL C5.7 · REQUISITION · actionable DENIES
```

Probe B explains why: for `HIRING_REQUEST` the scope guard denies independently
(`RECIPIENT_OUT_OF_SCOPE`). So the redundancy is real and proved — this is not a
survivor being excused — but the four columns of the matrix are **not proved by
the same mechanism**, and the completion note presented them as if they were.

The informational assertion is unaffected: it asserts the exact reason string, so
it does discriminate for all four entities.

---

## 🟡 J4 · LOW · The suite leaves activity rows behind

Cleanup removes chains, rules, hiring requests, candidates, users, offices and
`email_log` — but never `activities`. Pre-existing across the suite (C5.3's rows
show up too), harmless today because each run gets a fresh database, but it means
any file running later in the same process sees rows this one created.

---

## Disclosure on the mutation figure

The G1 battery (10/10 caught) was run against the **179-assertion** version of the
suite. Only the core G1 mutation was re-run against the 225-assertion version.
Adding assertions cannot turn a caught mutation into a survivor, so the figure
stands — but it is not a fresh 10/10 against the current file, and is not claimed
as one.

---

## Verdict

The cross-tenant strengthening does what it claims, and the probe that tried to
break it made the claim stronger rather than weaker. But it surfaced **J1**, which
is a real product defect of the same family as G1 and H2.

**M3 remains NOT ACCEPTED.** Open: **H1, H2, H3, J1** (product) and **J2, J3, J4**
(evidence quality). J1 and H2 are the same layer and should be corrected together
with H1 and H3 as one question — *when a record can stop existing, every layer
that reads it needs the same answer* — now known to include the **audit layer's
own rows**, not just what it decides to write.
