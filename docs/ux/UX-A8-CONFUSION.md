# UX-A8 — Confusion audit, per screen and per role

**Phase A, step 8. Audit only — no code changed.**

Scope: Parts 38 (what a first-time user would misunderstand) and 39 (the same,
per role). The brief's instruction is explicit: improve **labels, helper text,
hierarchy and contextual relationships WITHOUT changing the underlying model.**

The headline is that EXAACT has already done most of this work — and then hidden
it on one screen.

---

## F-A8-1 · Twenty-five curated definitions exist, and users never see them
**Class: structural · Severity: HIGH · Confidence: read from the registry**

`TERM_DEFAULTS` carries, for every core object, a **written one-line definition**
— not a label, an explanation. A sample, verbatim:

| Object | The definition already written |
|---|---|
| Work Order (`call`) | *"A piece of work the customer has asked for, with its dates and location."* |
| Job | *"One person put on one work order, for particular dates."* |
| Team Member (`engineer`) | *"The person who carries out the work."* |
| Candidate | *"A person being considered for hiring."* |
| Requisition | *"Approved demand for a new position — the role you are hiring for."* |
| Placement | *"A candidate successfully hired and placed."* |
| Client | *"The party that engages us and gets what we produce."* |
| Vendor | *"The party whose goods or works the job concerns."* |
| Report | *"A document we issue against a job and send to the client."* |
| Contract Number (`boss`) | *"…It is not typed on a deputation — it comes down from the quotation and the inspection call, and the register fills itself."* |

These are **good**. Several answer exactly the question Part 38 poses — "Job"
versus "Work Order" is settled in nine words.

**They are rendered in exactly one place: `views/ops/terminology.php`, line 56** —
the admin screen where you *rename* things. They appear nowhere on the screens
where the confusion actually occurs.

> *Correcting my own first pass:* I initially measured "0 views render a
> definition". That was a bad grep — the admin view reads `$d[3]` positionally,
> which my search for `term_help|term_def` could never match. The definitions are
> shown; they are shown in one place, to admins, on the configuration screen.

**Why it causes confusion:** a coordinator wondering whether a "Job" is the same
as a "Work Order" has the answer written, curated and shipped — and no way to
reach it. Part 38's whole list is answerable from data the product already holds.

**Recommended treatment (C2/C5):** surface the definition where the word is
used — as a tooltip on record headers and list titles, and as the one-line
`.sub` under a screen's `h1` where that is currently generic. **No new content
needs writing for these 25 terms.**

**Risk:** low. Display only, no model change — precisely what Part 38 permits.

---

## F-A8-2 · The five most confusable terms have no definition at all
**Class: structural · Severity: HIGH**

The registry covers 25 terms. It does **not** contain:

| Missing | Why it matters |
|---|---|
| **Hiring request** | The pair Part 38 lists first. Nothing distinguishes it from a requisition. |
| **Workforce** | Sits between Candidate and Inspector with no stated boundary. |
| **Inspector** | Distinct from Team Member and from Professional; undefined. |
| **QA** | Report versus QA is a lifecycle stage users routinely conflate. |
| **Billing readiness** | Distinct from Invoice — the money confusion Part 38 names. |

So the terms with definitions are largely the ones people understand, and the
terms people confuse have none. That is the inverse of where the effort should
have landed — understandably, since the registry exists to let companies *rename*
things, and these five are not renameable concepts.

**Recommended treatment (B1/C2):** write the five missing definitions and surface
them with the other 25. This is **content authoring, not code** — and it should
be checked against `docs/03-object-lifecycles.md` so the wording matches the model
rather than inventing a new one.

---

## F-A8-3 · No screen states a relationship between two confusable objects
**Class: structural · Severity: MEDIUM · Confidence: measured**

| Pair Part 38 names | Screens that explain the difference |
|---|---:|
| Hiring request vs Requisition | **0** |
| Candidate vs Team member | **0** |
| Marketplace requirement vs Recruitment requisition | 1 (mentions, does not distinguish) |
| Accepted vs Joined | **1** |

The single exception is instructive. The candidate screen asks **"Have they
actually joined?"** with a separate dated action — which resolves the
Accepted-vs-Joined confusion not by explaining it but by making the distinction
*visible in the interface*. That is the better pattern, and it is the one Part 22
("cross-module handoffs") asks for.

**Recommended treatment (C5):** the `RelatedRecords` component from Part 28,
stating the relationship in place — *"Hired into Workforce → [Open]"*,
*"Raised from hiring request HR-00231 → [Open]"*. The chain becomes visible
rather than described.

---

## Part 39 — per role: largely handled by gating

The brief asks whether each role sees the application through their own work
perspective. Two mechanisms already do this:

- **Licence gating.** `ops_area_licence_ok()` hides whole areas a workspace has
  not bought, so a recruitment-only company never meets inspection vocabulary.
- **Permission gating.** The dashboard alone carries 21 role/permission
  conditionals (UX-A3), and search sources each carry a `$can` gate (UX-A4).

A recruiter therefore does not see QA terminology, and an inspector does not see
billing readiness. **The cross-role confusion Part 39 anticipates is mostly
prevented structurally**, not left to labels.

The residue is within-role: a recruiter genuinely must distinguish hiring request
from requisition, and candidate from team member. That is F-A8-2 and F-A8-3, not
a separate per-role problem.

---

## What is already right

- **295 of 401 views carry a one-line explanation** under the heading, and 169
  carry an info callout. The product explains its *screens* well; what it does
  not explain is the *relationships between objects*.
- **The shipped defaults already avoid jargon.** `call` ships as **"Work Order"**,
  not "Inspection Call"; `engineer` ships as **"Team Member"**, not "Inspector".
  Somebody has already made these choices in the user's favour.
- **Renaming exists and is per-company**, so a workspace can align the product to
  the words its own staff use — a confusion remedy stronger than any label change
  this pass could make.
- **Accepted vs Joined is solved in the interface**, not in prose.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A8-1 | 25 curated definitions shown only on the admin rename screen | structural | HIGH |
| F-A8-2 | Hiring request, workforce, inspector, QA, billing readiness have no definition | structural | HIGH |
| F-A8-3 | No screen states the relationship between two confusable objects | structural | MEDIUM |

**Retired:** per-role confusion (handled by licence and permission gating);
screen-level explanation (295 of 401 already have it); jargon in the defaults
(already avoided).

**No product decision required.** F-A8-2 needs five sentences written and checked
against the lifecycle document; the rest is display.
