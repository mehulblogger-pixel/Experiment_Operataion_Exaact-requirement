# Phase 4 — The Twenty Adversarial Questions (§58)

*Answered from behaviour, with the probe that proves it. "The code says so" is
not an answer anywhere in this document.*

---

**1. Can one approved demand become two?**
No. Sourcing writes only to `requisition_allocations`; it never creates a
requisition and never writes `requisitions.quantity`. Three sources on a
twenty-person requirement leave exactly one requisition row and the approved
quantity untouched — *A11, A12, S7.1–S7.3*.

**2. Can more people be promised than were approved?**
No. Two controls: a ceiling before the write and a compensating check after it,
proven necessary together by combined mutants **T31** and **T33** — *B1–B6,
C1, C8*.

**3. Can a seat somebody has already filled be promised to a source?**
No. The ceiling measures **COMMITTED** — promises *plus* people who arrived
without a source. Ten approved with three walk-ins leaves seven sourceable, not
ten — *J1–J7*. This was a defect found by the adversarial pass.

**4. Can a source be credited with more people than it was promised?**
No, and the refused person keeps their seat — *D5–D8, C2, C9, mutants T5/T6/T32*.

**5. Can an arriving claim push out somebody already counted?**
Never. The compensator's keep-list is the **earliest** credited; the arrival is
the one dropped, and only ever to the direct path — *C3, RT4.3–RT4.6, mutant T23*.

**6. Can Phase 4 remove somebody from a job?**
No, by construction: it writes only `requisition_allocations` and
`candidates.allocation_id`. The worst outcome of any refusal is that a **credit**
returns to the direct path — *D8, C2.5, J10, C9.6, RT3.5, RT4.7*.

**7. Can an allocation be cut below what it already delivered?**
No — *G2–G4, mutant T7*.

**8. Does closing a source destroy what it delivered?**
No. Closing pins the allocation to exactly what arrived; only the undelivered
remainder returns — *F1–F6, F10–F12, R3.9–R3.10, mutant T21*.

**9. Can a non-coordinator change sourcing?**
No — every operation, at the write, with nothing written — *S1.1–S1.8, mutant T12*.

**10. Can a coordinator act on another branch's requirement?**
No. A record id is never proof of authorisation — *S2.1–S2.6, mutant T13*.

**11. Can a workspace that has not bought hiring use this?**
No, and **a superuser cannot either**. The module is switched off in the
workspace's own paid ceiling and the answer read through the product's own choke
point — *S3.1–S3.13, mutant T11*.

**12. Can a malformed value become an identity or a quantity?**
No. Arrays, words, booleans, negatives, fractions, `1e3`, `" 2 "` — all refused,
never coerced, nothing written — *S4 (19 probes), mutants T15/T16/T17*.

**13. Can a malformed value be read as "nothing" and quietly clear a credit?**
No — the distinction is load-bearing and tested both ways: `''` clears, `"abc"`
is refused and changes nothing — *S4.24–S4.25, RT1.5–RT1.6, mutant T16*.

**14. Can a supplier from another workspace be named?**
No. Tenancy is structural — one database per tenant — so the row is simply absent
and is refused rather than written — *S5.1, S5.6, mutant T24*.

**15. Is a source behind an unbought module merely hidden?**
No — it is refused at the write. Hiding an option is not a control —
*S5.7, mutant T25*.

**16. Can a screen left open overwrite what happened meanwhile?**
No, and a **malformed** expectation is stale rather than ignored — otherwise a
bad value would buy a seat — *S6.1–S6.8, O1–O11, mutant T20*.

**17. Can a requirement that may not execute be sourced?**
No. Phase 4 asks M6, which asks M4; it holds no opinion of its own. Giving seats
**back** is deliberately still allowed — tidying up is not execution —
*S8.1–S8.5, mutant T14*.

**18. Can a refusal be used to learn about a record you may not see?**
No. Entitlement, permission, the record and scope are decided **about the person**
before anything that would describe it: a wrong-branch coordinator asking for
99,999 people gets `OUT_OF_SCOPE`, not `OVER_AUTHORISED` — *S9.1–S9.3*.

**19. Can a control be bypassed by not using the screen?**
No. Every probe calls the production function a crafted POST reaches, and the
route battery drives the **real routes** in their own processes and then reads the
database. A link written by raw SQL is undone by a compensator that re-reads the
row rather than trusting the writer — *E3–E4, RT1.7–RT1.13, RT2.2, RT3.6–RT3.7,
mutants T9/T10/T27/T30/T34*.

**20. What happens when two people act at the same instant?**
Never overfilled, never displaced. Where a dead heat cannot be resolved without
displacement, the contested claims are refused and the capacity is left for the
next valid transaction — the ratified rule, unchanged from Phase 3. Real separate
OS processes, synchronised on a shared wall-clock instant, four rounds each —
*C1–C9, mutants T3/T6/T18/T19/T26*.

---

## The final adversarial question (§64)

> **What is the most likely way this will be wrong in production, that every
> test above still passes?**

**A figure that is right and a screen that is read wrongly.**

Every number here is derived, reconciled against its own rows, and bounded by its
definition. What is *not* tested — and cannot be, by this suite — is whether a
coordinator looking at **"5 not yet sourced"** understands that two of the
missing five were used up by people who walked in without a source. The panel
says so in words when the requirement is over-committed. It does **not** say so
in the ordinary case, where the number has simply, quietly, already accounted for
them.

The second most likely: **a released allocation loses a source its attribution.**
If an agency sends somebody who is still mid-pipeline when the allocation is
released, the release pins the allocation to zero, and when that person later
joins, their credit is revoked and they count as a direct arrival. The numbers
stay correct and nobody is harmed — but the agency's placement record quietly
loses a placement it genuinely made. That is a consequence of the rule that a
closed allocation can never grow again, and it is a policy question for the owner
rather than a defect: *should releasing a source forfeit credit for people it had
already sent but who had not yet arrived?*

Both are stated rather than hidden. Neither is a control failure; both are places
where the system is correct and a person could still be misled.
