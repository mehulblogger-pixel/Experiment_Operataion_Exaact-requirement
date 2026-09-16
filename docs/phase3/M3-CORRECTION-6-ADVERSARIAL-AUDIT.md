# Phase 3 · M3 CORRECTION #6 — ADVERSARIAL AUDIT

Scope: commits `be3270c` … `8904f0b` — the J1 audit-reference rule and the
J2/J3/J4 evidence corrections.

Method: four probes, each aimed at a claim that, if false, would make the
correction's own evidence hollow. Two of my four probes were **invalid on the
first run** and are reported as such below.

**Two findings. One is a regression this correction introduced.**

---

## What held up

### The fallback reference opens through the timeline's OWN mechanism

`C6` resolves a reference by querying the source table directly. That is a
shortcut of my own; clause 5 of the brief says the reference must be *"resolved/
opened by the existing entity mechanism"*. Asked that way instead — through
`act_link()`, which is what the timeline actually calls:

```
rows written after the record was deleted: 2
    kind=APPROVAL_POLICY id=1 link='/recruit-approvals?id=1'
    kind=APPROVAL_POLICY id=1 link='/recruit-approvals?id=1'
```

A real, well-formed link to a screen that exists. Clause 5 holds.

*(First attempt invalid: I called `act_entity_link()`, which does not exist, and
my probe's own fallback string made every row look unlinkable. The function is
`act_link()`.)*

### The C6.7 contract assertions are not trivially satisfied

`C6.7` asserts `appr_may_be_asked()` returns a **strict** boolean. That is worthless
if every probe row returns `false`, so the assertion was checked for a genuine
`true`:

```
appr_may_be_asked on a LIVE, in-scope record = true
```

*(First attempt invalid: my probe made one user both raiser and approver, so
**segregation** denied it and the answer was `false` for a reason that had nothing
to do with the contract. A separate raiser gives `true`.)*

---

## 🟠 K1 · MEDIUM · Correction #6 introduced a repetition regression on OFFER and SALARY

Ten identical refusals on the same unresolvable chain, measured on the pre-fix
library and on the current one:

| Entity | Before correction #6 | After correction #6 |
|---|---:|---:|
| HIRING_REQUEST | 10 rows | 10 rows |
| REQUISITION | 10 rows | 10 rows |
| **OFFER** | **0 rows** | **10 rows** |
| **SALARY** | **0 rows** | **10 rows** |

For hiring requests and requisitions this is a **pre-existing** D2 gap, unchanged
(and the same family as the open finding H2). For **offer and salary it is new**:
before this correction those refusals produced nothing, because the early
type-check returned. By giving them a subject that opens, I also gave them the
ability to repeat.

§3 of the brief is explicit — *"D2 remains: no repetitive permanent
identity-missing noise."* A source record that has ceased to exist is a permanent
condition, not an event, and it is now written once per decision attempt on two
entities that previously had none.

The fix is not to undo the fallback. It is that **repetition suppression is a
second dimension of D2's rule, and only the first (which *reasons* deserve a row)
was ever implemented.** `APPR_NOTIFY_AUDITED` filters by reason; nothing filters by
"this permanent condition is already recorded".

## 🟠 K2 · MEDIUM · The D2 anti-noise assertion now passes for the wrong reason

```php
$legacyReq = ['id'=>0,'entity'=>'OFFER','entity_id'=>$oidOld,'subject'=>'…','requester'=>'Meera Nair'];
…
for ($i=0;$i<5;$i++) appr_email_requester($legacyReq,'approved','');
t_eq($acts('Decision not notified'), $a0, 'R7 · D2-1 · five offer decisions … write NO repetitive rows');
```

`$legacyReq` **carries no `rule_id`**. So after correction #6 there is no openable
fallback, no row is written, and the assertion passes — not because repetition is
suppressed, but because that particular fixture has nothing to file under. The
same call with a `rule_id` present writes **five rows**.

The suite's one guard against audit noise is therefore **blind to K1**. This is the
same defect class as the false green found in the correction #5 audit: an
assertion that holds for a reason other than the one it claims.

## 🟡 K3 · LOW · An audit event can now be dropped entirely

```
escalation event on a legacy chain (rule_id = 0, record gone) wrote 0 rows
```

When neither the source record nor a governing policy can be opened, correction #6
writes **nothing**. That is the documented third branch, and it is the right
default against a dangling reference — but the brief also offered *"an existing
non-entity event mechanism"* as an option, and I did not measure how often this
branch is reached before choosing to drop the event. For a chain created by
`appr_start()` the `rule_id` is always set, so the branch is narrow; hand-made and
pre-M2 rows are the exposure.

Trade-off, stated plainly: **no dangling reference, at the cost of a lost escalation
record in a narrow legacy case.** It should be a deliberate decision, not a
side-effect.

---

## Why H1, H2 and H3 are still open

They are not forgotten, not blocked, and not hard. They are open because **no
correction prompt has authorised them**.

* They were found in the **correction #5** adversarial audit.
* The **correction #6** brief scoped the work explicitly: its status block names
  *"one product defect: J1"*, and §13 closes with *"ONLY correct J1 and strengthen
  J2/J3/J4 evidence."*
* The standing method is a HARD STOP after every correction, with each correction
  issued as its own prompt. Implementing H1–H3 inside correction #6 would have
  widened the scope beyond what was asked.

Their current state, re-checked against this build:

| | Status |
|---|---|
| **H1** — a direct `appr_act()` call still approves an orphan chain and reports "fully cleared" | open, untouched |
| **H2** — `appr_tick()` writes a reminder row on every run over a pending orphan | open; correction #6 made those rows **openable**, and changed nothing about how often they are written |
| **H3** — `appr_sla_summary()` counts orphans in its tiles | open, untouched |

**K1 belongs with H2.** They are the same rule seen twice: *a permanent condition is
not an event, however many times you look at it.* H2 is that rule missing on the
reminder path; K1 is it missing on the decision path, which correction #6 has just
extended to two more entities. Correcting them separately would repeat the pattern
every audit in this sequence has found — a rule fixed at one call site and not its
sibling.

---

## Verdict

The J1 rule itself is sound and its evidence holds: the reference opens through the
real mechanism, the contracts are pinned and non-trivial, and the mutation
accounting is honest. But the correction **traded one audit defect for a smaller
one**: references no longer dangle, and two entities that were previously silent
now repeat.

**Recommended status: M3 CORRECTION #6 NOT ACCEPTED**, pending **K1** and **K2**,
with **K3** to be decided rather than inherited.

Open across M3: **H1, H2, H3, K1, K2** (product/evidence) and **K3** (a decision).
