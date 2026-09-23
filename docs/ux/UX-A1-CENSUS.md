# UX-A1 — Census

**Phase A, step 1 of the UI/UX consolidation pass. Measurement only — no code was
changed, and nothing here is yet a recommendation.**

Counts were taken from the code, not from impression. Where a number looks
alarming, the follow-up check is recorded next to it, because several of them
turn out to be smaller problems than the raw figure suggests.

---

## 1 · Scale

| | |
|---|---|
| View files | **401** |
| Library files | **230** |
| Lines of view code | **49,520** |
| Lines of `app.css` | **1,097** |
| Distinct form actions | **472** |

For context: 472 form actions is not 472 screens a user must understand. Most
are single-purpose action posts (approve, move, close). The number that matters
for this pass is the **24 record-entry forms** and the **~103 navigable
destinations** below.

## 2 · Navigation — level 1

The sidebar carries **21 entries**, of which 5 are inspector-only and appear
conditionally:

`/` · `/owner` · `/search` · `/flow-gaps` · `/advisor` · *(inspector: `/my-jobs`,
`/documents`, `/document-new`, `/endorsements`, `/vouchers`)* · `/sales` ·
`/marketplace` · `/operations` · `/recruitment-cc` · `/quality` · `/reporting` ·
`/money` · `/insights` · `/directory` · `/admin`

**Observation for the audit:** four of the first five entries — Dashboard, Owner
home, Where the flow is broken, What to fix — are all *"tell me what needs
attention"* screens. A user cannot tell from the labels which one to open.

## 3 · Navigation — level 2, behind each area home

| Area | Destinations | Sections | Grouping |
|---|---|---|---|
| Admin | 26 | 7 | grouped |
| Quality & Accreditation | 23 | 2 | under-grouped |
| Marketplace | 14 | 1 | **flat** |
| Money | 11 | 2 | grouped |
| Sales | 9 | 1 | **flat** |
| Reporting | 8 | 4 | grouped |
| Directory | 8 | 2 | grouped |
| Insights | 4 | 1 | flat (small enough) |
| **Total** | **103** | | |

Operations and Recruitment do not use this mechanism and have their own homes:

- **Operations home** — 211 lines, 19 cards, 7 distinct destinations.
- **Recruitment Command Centre** — 450 lines, **20 distinct destinations on one
  screen**: availability, candidate, candidate-new, candidates, careers-admin,
  comp-setup, departments, doc-templates, my-approvals, positions,
  positions-import, positions-org, project-costings, recruit-approvals,
  recruit-export, recruit-pipelines, requisition, requisition-new, requisitions.

**Observation for the audit:** the brief warns against a sidebar that reads like
a list of database tables. The sidebar does not do this — but the Recruitment
home does, with configuration (pipelines, templates, compensation setup, org
import) sitting at the same visual weight as daily work (candidates, positions).

## 4 · Forms

| Form | Controls |
|---|---|
| Requirement | 77 |
| Job | 57 |
| Test request | 54 |
| Engineer | 48 |
| User | 40 |
| Candidate | 32 |
| Instruments | 24 |
| Lead | 21 |
| …14 more | ≤19 |

- **6 of 24** record forms carry more than 30 controls.
- **2 of 24** use a stepped or progressively-disclosed pattern (Requirement,
  Candidate). The other 22 present every field at once.

## 5 · States

| | |
|---|---|
| `flash()` calls in the libraries | **1,317** |
| …that name what to do next | **3** |
| …that are errors | 501 |
| Views with a shared empty-state component | **0** |
| Views with a bare "No records" style message | ~~18~~ **3** |

> **Corrected in UX-A5.** The 18 was inflated by explanatory prose beginning
> "Nothing here…", which is not an empty state. Three views are genuinely bare,
> two of them shared components. The rest are already tailored and specific.

**1,317 confirmations, 3 of which link to what happens next.**

> **Corrected in UX-A5.** This figure is arithmetically right and materially
> misleading, and calling it "the single largest finding" was wrong. Reading the
> messages, they are well written and several explain the *consequence* of the
> action ("the original date is kept in the history", "it appears on the client
> PDF"). The gap is a missing forward **link**, not bad copy — a much smaller
> and cheaper problem. See F-A5-3.

### Raw technical text reaching a user — smaller than it looks

`getMessage()` appears 90 times in the libraries, but only **7** inside a
`flash()`. Of those 7:

- **6** are the demo-data loaders (`Could not load DEMO-S01…S06`), reachable only
  by an admin deliberately loading a demo scenario;
- **1** is tenant provisioning, and already ends with a recovery instruction
  ("create one in your hosting panel and paste its details instead").

So the "stack trace in the user's face" risk is **low**, not systemic. It should
still be tidied, but it is not the emergency the raw count implies.

## 6 · Status vocabulary

**22 distinct pill classes**, with usage wildly uneven:

| Class | Uses |
|---|---|
| `pill p` | 497 |
| `pill ok` | 12 |
| `pill warn` | 7 |
| `pill info` | 7 |
| `pill muted` | 4 |
| `pill insp` | 3 |
| `pill bad` | 3 |

> **CORRECTED in UX-A6 — this table is wrong, and so was the conclusion I drew
> from it.** The regex `pill[- ][a-z]+` collapsed `pill p-ok`, `pill p-warn`,
> `pill p-mut`, `pill p-bad` and `pill p-info` into a phantom class "pill p".
> Counted properly: p-ok 148, p-warn 103, p-mut 96, p-bad 91, p-info 54 — **492
> uses of a proper five-tone semantic vocabulary**, with a legacy tail of ~42.
> Status colour is **not** decorative; it is 92 % consistent already. The real
> Part 8 gap is the missing **icon** (752 pills, 4 icons). See F-A6-1.

## 7 · Mobile

| | |
|---|---|
| Views containing a table | **251** |
| …with any card fallback | **2** |
| Global rule | `table.dt{display:block;overflow-x:auto}` |

Every one of the 251 table views scrolls **sideways** on a phone. This is the
largest mobile finding (Part 14).

## 8 · Breadcrumbs

**274 of 401** views carry breadcrumbs — good coverage. The 127 without are
mostly partials and embedded panels rather than top-level screens; which of them
genuinely need one is an audit question, not a census one.

## 9 · Duplicate doors — raw counts only

How many screens link to each "create" route. Whether each door is *legitimate*
is deliberately NOT judged here; that is step A7.

| Action | Screens linking to it |
|---|---|
| New report (`/document-new`) | 9 |
| New test request (`/call-new`) | 8 |
| Add candidate (`/candidate-new`) | 4 |
| Raise requirement (`/requisition-new`) | 4 |
| New job (`/job-new`) | 3 |
| New invoice (`/invoice-new`) | 2 |
| New lead (`/lead-new`) | 1 |

---

## What the census does NOT yet say

It does not say any of this is wrong. A contextual shortcut on nine screens may
be exactly right. 1,317 confirmations may each be appropriate where they sit.
Judging that is steps A2–A8; this file exists so that judgement rests on
measurement rather than impression.
