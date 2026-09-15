# Phase 2 · M4 — Correction & Final Lock

**M4 was not rebuilt.** The request layer, its schema, its lifecycle and its
tests are as they were. Three things were made unambiguous, the code was audited
rather than the documentation, and what the audit found was fixed and tested.

---

## 1. What was corrected, and what was deliberately left alone

| Corrected | Left alone |
|---|---|
| Terminology — three objects, three locked words | `hiring_requests` schema, lifecycle, statuses |
| The requestor model — capability, not role name | `requisitions`, `cx_requirements`, `candidates` |
| The two routes into recruitment — defined, with a Phase-3 ADR | M3's quantity & fulfilment model (`reqfulfil.php`) |
| Three defects the audit found (below) | The direct requisition route and its own gate |
| | Marketplace / Connect — not touched |

No table was created, renamed, merged or dropped. No lifecycle changed. No data
was migrated. The three corrections changed **which right is asked**, **what the
screens call things**, and **what the documents say**.

---

## 2. Terminology — the formal definitions (locked)

| Object | Table | What it is |
|---|---|---|
| **Hiring Request** | `hiring_requests` | the business **ask** to recruit |
| **Recruitment Requisition** | `requisitions` | approved demand **being executed** |
| **Marketplace Requirement** | `cx_requirements` | a demand a **client posts to the Connect network** |

> A Hiring Request asks permission to recruit. A Recruitment Requisition is the
> approved work of recruiting. A Marketplace Requirement is a client asking the
> network for people.

Record: `M4-TERMINOLOGY-LOCK.md`.

## 3. Terminology — what was explicitly NOT done

- **No new `requirements` table.** Asserted by test A7.
- **`cx_requirements` not renamed.**
- **`requisitions` and `cx_requirements` not merged** — separate tables,
  lifecycles and owners.
- **No extra object introduced to satisfy a word.**

## 4. Terminology — what changed on screen

A bare, unexplained "Requirement" is never the **name** of an object on a
recruitment screen. `hreq_label()` honours the workspace's **own** wording and
qualifies it only when that wording is itself ambiguous — a workspace on the
Recruitment agency pack sees "Recruitment Requirement", never a bare one; a
workspace that says "Requisition" sees exactly that, unpadded. Terminology stays
the customer's; only the ambiguity is removed.

Changed: the navigation (which offered a bare "Requirements" and "New
requirement", and did not list hiring requests at all), the requisition form
heading and save button, the project-costing flash, and the requisition detail —
which now says **which of the two routes** produced the record.

**Residual, stated rather than hidden:** screens written before M4 that print
`T('requisition')` directly still show the customer's own word unqualified.
They are unambiguous inside recruitment and only collide beside the Connect
marketplace. Extending `hreq_label()` across them is recorded as a Phase-3 tidy.

---

## 5. The requestor model — what was wrong

The documentation said *"coordinator to raise"* and the code asked
`is_coordinator_level()`. That was wrong twice over:

1. It named **roles**, not the **right**.
2. The band it names — the seven management roles **plus Asst. Manager and
   Coordinator** — is **wider than the permission matrix**, which grants Asst.
   Manager no hiring right at all.

## 6. The requestor model — what it is now

> **Any user who holds the Hiring Request CREATE capability, and is within the
> applicable branch scope, may raise a Hiring Request.**

| Act | Right asked | Asked where |
|---|---|---|
| VIEW | `can('mod.hiring.view')` | `ops_module_gate()` before dispatch **and** on entry to the handler |
| CREATE / EDIT | `can('mod.hiring.edit')` | `hreq_save()` — at the write |
| SUBMIT | `can('mod.hiring.edit')` | `hreq_submit()` |
| CANCEL | `can('mod.hiring.edit')` | `hreq_cancel()` |
| CONVERT → requisition | `can('mod.hiring.edit')` **and** `hreq_is_executable()` | `hreq_to_requisition()` |
| DECIDE | the module **and** a management role | `hreq_decide()` |

The right is asked **at each write, not only on the route**, so a direct helper
call, a future AJAX handler or a Phase-3 caller cannot reach the table around it.
Branch scope sits on top of every one of them.

## 7. Approval authority, and self-approval

Approval authority is held **apart** from creation authority — that separation is
the point of a request layer. Deciding stays where the application already puts
recruitment approval: a management act, as an offer is.

**The requestor may not decide their own request.** The comparison is on
`requested_by_id → users.id` — the canonical requestor relationship — and it is
enforced inside `hreq_decide()`, not merely hidden on the screen. The single
exception is a **master**, because a one-person workspace has nobody else to
approve; it is the same standing exception the application already writes into
report finalisation, and it is an exception to **segregation only** — the
licence, the module capability and the branch scope all still apply above it.

`requested_by_id` is now filled from whoever is signed in when nothing was
chosen, so the canonical relationship is always real.

## 8. No new permission, and no second permission system

`mod.hiring.view` and `mod.hiring.edit` are **existing** capabilities, generated
for every module by `ACCESS_MODULES`, granted per role and per user on
Settings → Roles & permissions, and enforced through the same `can()` choke
point — licence first, then master, then the granted set. `mod.hiring.edit` is
the very right the application already requires before project costing may
create a requisition, so both routes into recruitment now ask the same question.

Test B5 asserts both are already in the permission catalogue. Test B4 asserts no
role-name check survives anywhere in the request layer. `docs/02-permission-matrix.md`
was updated in the same change as the code.

---

## 9. The two routes into recruitment — formally defined

**Path A — GOVERNED HIRING REQUEST PATH** *(added by M4)*
`Hiring Request → Approval → Recruitment Requisition → Candidates`.
For hiring that must be authorised before headcount or money is committed.

**Path B — LEGACY / DIRECT REQUISITION PATH** *(pre-dates M4, unchanged)*
A Recruitment Requisition created straight away, `hiring_request_id = NULL`.
For demand already authorised outside EXAACT — a client's signed order, a won
quotation, a manpower-services business whose client order *is* the authorisation.

They are **one architecture, not two**: the same requisition table, the same
quantity and fulfilment model, the same candidate pipeline, one additive nullable
link column, and exactly **one** function in the whole application that writes
that link. They differ only in how the requisition came to exist — and the
requisition screen now says which. This is not an accidental duplicate workflow.

Record: `M4-REQUISITION-PATHS.md`.

## 10. The Phase-3 decision, recorded not taken

M4 **does not** invent the customer policy switch. Whether Path B is (a) always
permitted, (b) disabled for selected customers, or (c) governed by a
tenant/workspace policy — plus (d) aligning its permission with Path A's — is
recorded as `docs/adr/ADR-001-direct-requisition-path.md`, with the trade-offs,
a recommendation and the consequences to work through. It is **OPEN**.

---

## 11. The §5 code audit — what was checked, and what it found

The audit read the **code**, not the documentation. Every avenue named in the
brief was walked:

| Avenue | Finding |
|---|---|
| Route dispatch order | `ops_dispatch()` calls `ops_module_gate()` **first**; it resolves `hiring-request(s)` → `hiring` and asks `can('mod.hiring.view')`, which asks `licence_blocks()` before anything else. Correct. |
| Direct POST | Every POST passes the global CSRF gate (`index.php`) before any route runs. |
| Direct URL | The request detail now asks the scope question where the record is read — **fixed**, it previously relied on the route gate alone. |
| AJAX / API | None exists. No file outside the layer reads or writes `hiring_requests`; one dispatch point reaches it. Asserted by test D. |
| Helper invocation | Every mutating helper now asks the right and the scope itself — **fixed**. |
| Destination / branch / user IDs | Validated against the real registers: requestor against `users`, departments against the canonical vocabulary (including "is it actually a department" and "is it switched off"), position against `positions` (exists, active, in scope), branch against `scope_allows()`. |
| Master bypass | Exactly **one** `is_master()` in the layer, for segregation of duties only. A master on an unbought module is still refused — mutation 4 proves it. |
| Stale lookup caches | The request-type list is a union of the live lookup and M4's additions, so the cache cannot outlive the migration within one request. |
| Cross-branch references | `hreq_in_scope()` on read and on every write. |

**Three defects found and fixed**, none of which the documentation would have
revealed:

1. `hreq_can_decide()`, written as a role band, **opened for a master holding
   core administration on a workspace that had not bought the module.** M10's
   permanent gate probe caught it. The module question is now asked first.
2. **Branch scope existed only on the route.** Disabling the gate outright broke
   no test, because a source-level assertion was holding it in place. Scope is
   now asked at every write and at the read, and the decision was split out of
   the refusal so it can be tested without a redirect.
3. **Two silent-erasure bugs in `hreq_save()`**: a partial update that omitted
   `office_id` moved the request to *no branch* (and slipped past the scope check
   by omission); one that omitted `requested_by_id` erased the canonical
   requestor. Both fields are now preserved when simply absent from the post.

## 12. Every Hiring Request mutation path, and the chain each passes through

This is not a list written by hand. Test section **D** finds every statement in
the layer that writes to the database, works out which function contains it, and
**fails on any function not on the audited list** — so a sixth write added later,
in a new function, breaks the build until it is audited. There are exactly five.

| Mutation path | Entitlement | Capability | Tenant / scope | Validation | Audit |
|---|---|---|---|---|---|
| **create** (`hreq_save`, id 0) | `mod.hiring.view` at the route → `licence_blocks()` first | `mod.hiring.edit` | branch on the posted `office_id`; position re-checked in scope | quantity, title, requestor, both departments, position, date, priority, employment type, request type | `activity_log` create |
| **save / edit** (`hreq_save`, id > 0) | same | `mod.hiring.edit` | `hreq_in_scope($existing)` **and** the posted branch | as above, plus: approved / rejected / cancelled cannot be edited | `activity_log` update |
| **submit** (`hreq_submit`) | same | `mod.hiring.edit` | `hreq_in_scope` | must be `DRAFT`; quantity and title present | `activity_log` status |
| **approve** (`hreq_decide`, true) | same | `hreq_can_decide()` = module **and** management role | `hreq_in_scope` | must be `SUBMITTED`/`UNDER_REVIEW`; **`hreq_may_decide()` — not the requestor** | `activity_log` status |
| **reject** (`hreq_decide`, false) | same | same | same | same | `activity_log` status |
| **cancel** (`hreq_cancel`) | same | `mod.hiring.edit` | `hreq_in_scope` | already-cancelled is a no-op | `activity_log` status |
| **convert → requisition** (`hreq_to_requisition`) | same | `mod.hiring.edit` | `hreq_in_scope` | **`hreq_is_executable()`**, then the approved-quantity ceiling | `activity_log` requisition |
| **any other status mutation** | — | — | — | — | **does not exist**: the three status writers above are the only ones |
| **AJAX / API mutation** | — | — | — | — | **does not exist** |
| **helper callable from another route** | — | — | — | — | **does not exist**: no file outside the layer touches the table |

Test D asserts, for each of the five functions, that the capability, the branch
scope and the state/input validation all appear **before the first write**, and
that each one leaves an audit entry.

## 13. Test results — observed, both engines, identical source

| Engine | Whole suite | M4 correction suite |
|---|---|---|
| SQLite | **8816 passed, 0 failed** | 106 assertions, 0 failed |
| **MariaDB 10.11.14** (authoritative, fresh database) | **8817 passed, 0 failed** | **106 assertions, 0 failed** |

The correction suite was confirmed to have **actually executed on MariaDB** — its
four sections appear in the MariaDB output and its assertions were counted inside
that section on both engines. The MariaDB database was created fresh, so every
migration ran from nothing. No existing test was weakened, deleted or skipped;
one existing assertion was **followed** through a refactor (the scope decision
moved into `hreq_scope_reason()`) and a second assertion added alongside it.

**Mutation battery: eleven mutations, ten caught, one survived.** The full table
— protection removed, expected, actual, verdict — is in `M4-TEST-RESULTS.md` §4.
The single survival removes only the outer route-gate wrapper; the two mutations
that remove the real protection (the scope **decision**, and the **write-time**
check) are both caught. That survival is acceptable *because the redundancy is
proved*, and it is worth being exact about the history: the **original** form of
that mutation, when the gate genuinely was the only check, **survived and
exposed the gap this correction fixed**. It became defence-in-depth only after
the fix.

## 14. Architectural cleanliness (§8), and what is NOT here

| Checked for | Found |
|---|---|
| duplicate requirement tables | none — `cx_requirements` (marketplace) and `site_doc_requirements` (site-access documents, a different domain) are the only others, both pre-existing and untouched |
| duplicate quantity fields | none — `hiring_requests.quantity` (asked) and `requisitions.quantity` (executed, M3's); no third |
| duplicate candidate / hire tables | none — one `candidates` table |
| duplicate approval or pipeline engines | none — `recruit_approval_requests` is reused, not replaced; M4 creates no approval table |
| duplicate Department masters | none — M2/M3's canonical vocabulary |
| new Job Profile tables | none — M4 did not invent one |
| new permission engines | none — `can()` is the only choke point |
| `is_master()` bypasses | exactly one, for segregation of duties, tested in both directions |
| unguarded Hiring Request mutation routes | none — section 12 above, enforced by test D |
| unscoped branch references | none |
| hidden direct conversion paths | none — exactly one writer of `hiring_request_id`, asserted |
| stale documentation | corrected; two wrong statements in the original report are marked **CORRECTED** |

**No Phase-3 work has been started.** No approval routing, matrix, SLA,
delegation, escalation, inbox or notification; no re-approval path; no policy
switch; no recruiter assignment; no Marketplace change; no Job Profile master.

---

## M4 COMPLETE — HARD STOP — READY FOR PHASE 3
