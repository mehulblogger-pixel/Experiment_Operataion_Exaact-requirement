# Phase 4 — Candidate Traceability, Duplication and Movement (§31–33)

*One human, several applications, several possible sources — and no two of those
facts allowed to overwrite each other.*

---

## §31 — Duplication

**Phase 4 creates no duplicates and merges none.** The existing rule stands
unchanged: one human may have several applications, each kept as its own
`candidates` row so no history is lost, matched by an explicit link or by
phone/e-mail. The source credit is a property of an **application**, not of a
person, which is the correct grain: the same welder may reach you through an
agency on one requirement and directly on another, and both facts are true.

Nothing in Phase 4 reads or writes `person_ref`, and nothing merges rows.

## §32 — Traceability

For any person in a seat, three questions are answerable independently:

| Question | Answered by | Owned by |
|---|---|---|
| Which approved demand are they against? | `candidates.requisition_id` | M3 / M4 |
| Who is accountable for chasing them? | `candidates.recruiter_id` | **M5** |
| Which source did they arrive through? | `candidates.allocation_id` | **Phase 4** |

These are deliberately three columns, three engines and three verbs — *assign*
(M5), *allocate* and *credit* (Phase 4). Merging any two would mean a change to
one silently rewriting another: a supplier change reassigning a recruiter, or a
recruiter change re-crediting an agency.

The pre-existing free-text `candidates.source`, `source_type` and `agency` are
untouched. They record how a CV reached us ("LinkedIn", "walk-in"); they are not
a promise, carry no remainder, and nothing can be reconciled against them. Phase
4 did not overload them precisely because a string comparison would then be
deciding a capacity question.

**The audit trail.** Every credit, move and revocation is on the append-only
`requisition_allocation_events` ledger with the actor, the reason and the before
and after — and on the activity spine via `act_log()`. A **refused** operation
writes nothing to either; the ledger is what happened, not what was attempted
*(N1–N6, H3)*.

## §33 — Movement

Moving a person between sources is one operation through one door
(`rful_attach()`), and it is recorded on **both** sources:

```
ALLOCATED , ATTACHED , STATE , DETACHED        ← the source they left
                       ATTACHED                 ← the source they joined
```

*(Attack probe A14: the old source loses the credit, the new one gains it, and
the move appears on the old source's own record.)*

Moving a person to a different **requirement** is the harder case, and the rule
is the one M5 paid for: the question is asked about the requirement **the save is
producing**, not the one the candidate is leaving. A credit that would then point
at the old requirement's source is refused at the door *(RT1.4)* and removed by
the compensator if it arrives by any other route *(E3–E4, RT1.7–RT1.8)*.

**What a move can never do:** change whether the person holds a seat. That is
M6's, and Phase 4 runs after it and can only reduce a credit *(RT3.5, C9.6)*.

## Reversal

| Event | Effect on the credit | Effect on the seat |
|---|---|---|
| Candidate rejected / withdrawn / declines | Credit remains (true history); they stop counting as fulfilled, and the source's state derives back to ACTIVE | M6's |
| Candidate moved to another requirement | Credit refused or removed | M6's |
| Source released or cancelled | People already arrived keep their credit; the source keeps them | None |
| A joining reverted by M6 under contention | Credit remains — the agency did send them | M6 restores the prior stage |

The last row is deliberate and was the subject of a corrected probe: a candidate
the agency sent is legitimately linked to that agency whether or not they are
ultimately hired, and `rful_fulfilled()` counts only people in a filled stage, so
such a link consumes nothing.
