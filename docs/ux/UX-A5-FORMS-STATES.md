# UX-A5 — Forms, empty states, errors and success

**Phase A, step 5. Audit only — no code changed.**

Scope: Parts 9 (forms), 10 (smart defaults), 11 (progressive disclosure),
15 (empty states), 16 (errors), 17 (success states), and 18 (workflow
continuity, which turned out to belong here).

**This step retired more assumptions than it confirmed.** Three of the six areas
are already in good shape, and one census figure I reported earlier needs
correcting. Those corrections are the most useful part of this document, because
work planned against them would have been wasted.

---

## Correcting my own census finding

In UX-A1 I reported: *"1,317 flash calls, 3 of which name a next step"* and
called it the largest finding of the census.

That figure is arithmetically right and **materially misleading**. Reading a
sample of the messages:

> "Rescheduled — the original date is kept in the history."
> "Letterhead saved — it appears on the client PDF."
> "Sent back to the author as a draft with your comment."
> "Action dropped, with the reason recorded."
> "Recorded that the complainant was told, and when."

These are **well-written**. Several explain the *consequence* of the action —
what was preserved, where it will show up, what was recorded — which is more
useful than a bare "Saved." The writing is not the problem.

What the messages don't do is carry a **link to the next step**. That is a real
gap, but it is a much smaller and cheaper one than "1,317 bad messages", and it
should not be used to justify rewriting copy that is already good.

---

## F-A5-1 · Four heavy forms present every field at once
**Class: structural · Severity: HIGH**

| Form | Controls | Disclosure |
|---|---:|---|
| Job | 57 | **none** |
| Test request | 54 | **none** |
| Engineer | 48 | **none** |
| User | 40 | **none** |
| Requirement | 77 | stepped |
| Candidate | 32 | disclosed |

Part 9 calls this "one of the highest priority areas" and says never to present
a wall of fields. Four forms — including the two largest in Operations — do
exactly that.

**Recommended treatment (C7):** the pattern already proven on the Requirement
form: named steps, save available from step one, and each step's rare fields
behind its own "More detail". That form went from ~60 fields on one page with
the only Save button below all of them, to five steps, and it is already verified
in a browser at phone width.

**Risk:** medium. These are the forms most used daily, and stepping changes
muscle memory. The Requirement precedent matters here — it was a progressive
enhancement, so with scripting off every field still renders on one page and the
form saves exactly as before. The same rule must hold.

---

## F-A5-2 · The system knows things it still asks for
**Class: cosmetic · Severity: MEDIUM**

Part 10: *"Where the system already knows information, PRE-FILL IT."*

| Pre-filled from context | Forms (of 24) |
|---|---:|
| Today's date | 10 |
| Values carried from a parent record | 11 |
| The user's own office | **3** |
| The current user | **2** |

Date and parent-carry are reasonably done. **Office and requester are not** —
these are the two things the system most reliably knows, and they are being
re-typed or re-chosen on most forms.

Part 10 also asks that important assumptions be *stated*, with a way to change
them ("Using Ahmedabad branch [Change]"). Only **3 forms** do anything of the
kind. This matters more than the pre-fill itself: a silent default on a field
like office is the brief's own "never silently guess critical information".

**Recommended treatment (C7):** pre-fill office and requester where a sensible
default exists, and show the assumption inline with a Change affordance rather
than filling it invisibly.

---

## F-A5-3 · No next-step link on the destination
**Class: structural · Severity: MEDIUM**

Only **14 views** mention a next step in any form, and there is no shared
component for it (**2** partial helpers).

This is the residue of the census finding, correctly sized: the user is taken to
the right place (see below) and told clearly what happened — but the record they
land on does not say *what to do now*.

**Recommended treatment (C5):** one `NextAction` component driven by the
**existing** lifecycle rules, rendered on the record header. Part 19 is explicit
that no new statuses may be created for this, and none are needed — the
transitions already exist in `docs/03-object-lifecycles.md`.

---

## F-A5-4 · Three bare empty states, two of them in shared components
**Class: cosmetic · Severity: LOW**

**Corrected from the census.** I previously reported "18 views with a bare no-
records message". That count was inflated by explanatory prose beginning
"Nothing here…" which has nothing to do with empty states.

The genuine total is **three**:

- `views/list.php`
- `views/ops/master_list.php`
- `views/ops/tapi_drill.php`

Two of those are **generic components used by many screens**, so the reach is
larger than three — and so is the value of fixing them, because it is two edits.

**Everything else is already good.** A sample of the tailored empty states:

> "No active staff yet. Add users under …" · "No assessment issued yet. Raise a
> …" · "No checklist points are configured yet." · "No applications yet." ·
> "No attendance periods submitted yet."

Specific, human, and several already point at where to go — which is exactly
what Part 15 asks for. **This area needs two fixes, not a programme.**

---

## F-A5-5 · Errors — smaller than the raw count, but not zero
**Class: cosmetic · Severity: LOW**

Carried from the census and unchanged: `getMessage()` appears 90 times in the
libraries but only **7** inside a `flash()`. Six of those are the admin-only
demo loaders (`Could not load DEMO-S01…S06`); one is tenant provisioning and
already ends with a recovery instruction.

> **CORRECTED in UX-A7 — this conclusion was wrong, and wrong in the comfortable
> direction.** Part 16 is **violated on a routine screen**: Masters → Add a
> person shows the user a raw `SQLSTATE[23000] … Duplicate entry`. My search
> looked for `getMessage()` inside `flash()`; that error arrives through an
> **uncaught exception**, which the search could never find. Counting the
> handled paths and concluding the rule was met was the error. See F-A7-1.

---

## What is already right

### Workflow continuity is largely solved (Part 18)

The brief calls this *"one of the MOST IMPORTANT changes"*. Checked against the
journeys it names, the destinations are already correct:

| Action | Lands on |
|---|---|
| Create candidate | `/candidate?id=` — the profile |
| Save requirement | `/requisition?id=` — the record |
| Approve hiring request | `/hiring-request?id=` — the request |
| Save job | `/job?id=` — the job |
| Report actions | `/document-fill?id=`, `/document-review?id=` — the next stage |

Of 1,202 `redirect()` calls, **398 go to the record just touched**. Of the 440
that go to a list, the most frequent destinations are `/settings`, `/users`,
`/tenants`, `/report-templates`, `/companies`, `/data-control`, `/two-factor` —
**configuration screens, where returning to the list is the correct behaviour.**

So Part 18 is not the large piece of work the brief anticipates. The gap is
F-A5-3 — naming the next action once the user arrives — not the arrival itself.

### The message copy is good
See the correction above. The tone is human, specific and often explains
consequence. C7 should **add forward links, not rewrite the words**.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A5-1 | Job (57), Test request (54), Engineer (48), User (40) show every field at once | structural | HIGH |
| F-A5-2 | Office pre-filled on 3 forms, current user on 2; assumptions rarely stated | cosmetic | MEDIUM |
| F-A5-3 | No shared next-action component; 14 views mention one | structural | MEDIUM |
| F-A5-4 | 3 bare empty states, 2 in shared components | cosmetic | LOW |
| F-A5-5 | 7 raw exception messages, 6 admin-only — **but see F-A7-1: an UNCAUGHT one reaches users on a routine screen** | cosmetic | ~~LOW~~ see A7 |

**Retired as false alarms:** workflow continuity (already correct), message
quality (already good), empty states at scale (3, not 18).

**Not retired after all:** errors. A7 found an uncaught exception putting a raw
SQLSTATE in front of users on an ordinary task.

**No product decision required.** F-A5-1 is the one substantial piece of work,
and it has a proven in-repo precedent to copy rather than a design to invent.
