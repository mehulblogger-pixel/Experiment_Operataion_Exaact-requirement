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
