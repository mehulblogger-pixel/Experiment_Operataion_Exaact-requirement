# Phase 3 · M3 CORRECTION #2 — ADVERSARIAL AUDIT

An attack on correction #2, after it was reported complete. Every finding proved
with a running probe.

**Verdict: NOT ACCEPTED. C1 and C2 are correctly fixed — but the way C1 fails
closed has consequences I under-reported, and all three findings below are mine,
introduced by this correction.**

The regression figures stand and are unaffected: SQLite 9333/0, MariaDB 9334/0,
new suite 47/0, 20 of 22 mutations caught. None of these findings is caught by any
test.

---

## D1 · HIGH — Three of the four approval entities lost their decision notification entirely

### What happens

`appr_email_requester()` now resolves identity only for `HIRING_REQUEST`. For an
**offer**, a **salary structure** and a **requisition** it returns null and sends
nothing.

```
FAIL  P3 · an offer decision still reaches the person who raised it
```

Before this correction, an offer's requester **was** notified. The name lookup was
wrong in the namesake case; it was right in the ordinary case, which is most of
them.

### The honest framing

I traded *a wrong recipient in a rare case* for *no recipient at all in three
entities*. The brief said do not guess, and I did not — but the brief's
fail-closed rule was written about an **unresolvable** identity, and an offer's
raiser is not unresolvable in principle: it is simply **unstored**. `job_offers`
records `approved_by` and `issued_by` as text and carries no creator id at all. I
checked that, chose to fail closed, and recorded it as a limitation — but a
limitation note undersells "a working notification silently stopped for three of
four entity types". Nobody sees an error; the e-mail just stops arriving.

### What it is not

It is **not** a security defect, and reverting to the name lookup would reinstate
the disclosure the whole correction exists to remove. The fix is a canonical
raiser id on those entities, which is a schema change outside this correction's
scope — **so this needs your decision, not my initiative.**

### Classification
**M3 correction #2 defect — under-reported consequence.** The behaviour is
defensible; the disclosure of its size was not.

---

## D2 · MEDIUM — Every offer, salary and requisition decision now writes an unlinkable audit row, for ever

`appr_audit_requester_unresolved()` runs on **every** such decision.

```
FAIL  P1 · five offer decisions do not write five "could not identify" rows (wrote 5)
FAIL  P2 · and the row it writes is attached to something
          (entity_kind="", entity_id=7777)
```

Two problems, and the second is the one that matters:

1. **It is a permanent condition logged as if it were an event.** An offer will
   *never* have a canonical raiser id. Recording "could not identify the requester"
   on every decision for ever is not an audit trail; it is an alarm that can never
   be cleared, and it will bury the entries that do mean something.
2. **The rows point at nothing.** `OFFER` and `SALARY` are not registered in
   `ACT_ENTITIES`, so `act_log()` blanks `entity_kind` and stores a dangling
   `entity_id`. §24 asked for traceability, and an unattached row is not traceable.

This is squarely mine: I added the helper in this correction and tested only that
it fires, never what it costs at volume or whether the row it writes can be read.

### Classification
**M3 correction #2 defect.** Log the structural case once (or not at all) and the
genuine failures properly; do not create a second unlinkable audit stream.

---

## D3 · LOW-MEDIUM — The audit trail misstates its own reason

When the requester **is** identified but is not eligible — inactive, out of scope,
or the workspace unlicensed — the same row is written:

> *"Decision not notified — no canonical requester identity"*

```
FAIL  P4 · a requester who IS identified but ineligible is not logged as
          "no canonical identity" (wrote 1)
```

The identity resolved perfectly. The message says it did not. Two different
failures — *"we do not know who"* and *"we know who, and may not tell them"* — are
recorded as the same thing, so the trail cannot distinguish a data problem from a
policy outcome.

In a project whose discipline is *do not misreport*, an audit line that misreports
its own cause is not a cosmetic issue.

### Classification
**M3 correction #2 defect.** One extra reason string; no structural change.

---

## What held up under attack

- **C1's core** — canonical identity from `requested_by_id`; namesakes in another
  branch and without permission are told nothing; legacy text never overrides the
  id; direct invocation cannot deliver to a name; identity alone does not
  authorize. Seven mutations, all caught.
- **C2's core** — unknown, blank, invalid and cross-tenant entity references all
  deny, and **the decisive case holds**: the approver passes `appr_can_act()` and
  is still refused, so nothing falls through. Offer/salary/requisition visibility
  unchanged.
- **`appr_as_user()`** — untouched, and still restores user, role, scope and
  permissions under exceptions and nesting.
- **F1, F2, F3** — the whole matrix re-proved, 68/0.

---

## Honest note

Three findings, all introduced by this correction, and the pattern behind them is
new: not *a question asked on one path and not its sibling* this time, but
**a fix whose side effects I tested for presence and not for consequence.** I
asserted that the fail-closed path fires and that it writes a row. I did not ask
what that row costs on the thousandth decision, whether anyone could read it, or
whether it told the truth.

**Recommended status: M3 CORRECTION #2 NOT ACCEPTED** — D2 and D3 are small and
mine to fix; **D1 is a product decision that is yours.**
