# Phase 2 · M4 — Hiring Request Architecture

> **Corrected and locked.** Three things were made unambiguous after the first
> cut of M4. Read them with this document:
> · `M4-TERMINOLOGY-LOCK.md` — Hiring Request vs Recruitment Requisition vs
>   Marketplace Requirement, and the rule that no screen prints a bare
>   "Requirement".
> · **§11 below** — raising a hiring request is a **capability**
>   (`mod.hiring.edit`), not the role named Coordinator.
> · `M4-REQUISITION-PATHS.md` + `../adr/ADR-001-direct-requisition-path.md` —
>   the governed path and the direct path are both supported and deliberate.

## 1. What was missing, and what was added

The audit found **one** thing genuinely absent: a request layer. A requisition
was created directly, already `OPEN` — a status whose own label reads *"Open
(approved, sourcing)"* — and candidates could be attached at once. Nothing
distinguished *"we would like to hire"* from *"this is approved, start
sourcing"*.

So M4 adds **one object and one column**:

```
hiring_requests                  the request layer
requisitions.hiring_request_id   additive link — NULL for a directly raised one
```

Everything else is reused.

## 2. The chain

```
REQUESTOR (a user identity)
     ↓
HIRING REQUEST          who asked · what · where · how many · when · why
     ↓                  DRAFT → SUBMITTED → APPROVED
     ↓                  ── the boundary: nothing recruits before this ──
REQUISITION             M3's object, unchanged
     ↓
VACANCIES → FULFILMENTS → CANDIDATE → OFFER → JOINING
```

## 3. What is reused, not rebuilt

| Concern | Reused from |
|---|---|
| Department | M2/M3 canonical vocabulary — stored as an **identity**, never free text |
| Designation | canonical vocabulary |
| Position | `positions`, with its existing five-case manpower check |
| Quantity & fulfilment | M3's `quantity` + `reqfulfil.php`, untouched |
| Branch & scope | `offices`, `scope_allows()`, `scope_office_clause()` |
| Custom fields | the existing engine, entity `hiring_request` |
| Request type | the **existing** `requisition_type` lookup, extended (§8) |
| Approval | `recruit_approval_requests` is entity-agnostic — Phase 3 plugs in |
| Audit trail | `activity_log()` |
| Entitlement | the `hiring` → **hr** module map |

New vocabularies — `hr_priority`, `employment_type`, `hiring_request_status` —
are created **through the existing lookup engine**, because the audit found no
such lists existed. They are customer-extendable from day one.

## 4. The two departments (§7)

A request records both, and never assumes they are the same:

```
Requesting department    the team that NEEDS the person   (Projects)
Hiring department        the team they will JOIN          (Engineering)
```

Both are canonical identities. Customer wording resolves through the approved
terms from M3.

## 5. Quantity authority (§13)

Two numbers, because they are two facts:

| Number | Means |
|---|---|
| `hiring_requests.quantity` | how many were **asked for** |
| `requisitions.quantity` | how many are being **executed** (M3's, unchanged) |

Approval may cut ten to six, and then both matter. There is no third quantity,
and `reqf_counts()` still reads the requisition's — a test pins that.

## 6. Cardinality (§20)

**One request → many requisitions.** A request for ten may be executed as six in
one branch and four in another. What is refused is converting **more than was
approved**:

```
approved 10 → raise 6 → 4 remaining → raise 4 → 0 remaining → raise 1 → refused
```

## 7. Job Profile vs Job Description (§11)

The audit established that **no Job Profile object exists**. `recruit_jd.php`
generates a description from a requisition's own fields; it is a renderer, not a
master.

M4 therefore preserves the *distinction* using what genuinely exists:

| Side | What plays the role |
|---|---|
| Reusable definition | **Designation** (canonical) + **Position** (establishment) |
| Specific to this request | `hiring_requests.job_description` |

A true Job Profile master — a named, versioned bundle of responsibilities,
skills and experience — is **recommended and not built**. It is recorded as a
decision, not assumed into existence.

## 8. Snapshot (§12)

On submission the request records what it **meant** — titles, department
display names, position, quantity, terms — in `snapshot_json`. Renaming the
department master afterwards does not change what was approved. A mutation that
stops taking the snapshot fails the suite.

## 9. The approval boundary (§17, §18)

M4 builds the **boundary**, not the workflow.

- `hreq_is_executable()` is the single question, and
  `hreq_to_requisition()` refuses before doing anything else.
- `approval_required`, `approval_ref`, `decided_by`, `decided_at`,
  `decision_note` are recorded.
- A workspace that sets `approval_required = 0` moves straight to `APPROVED` on
  submit — the boundary still exists, it is simply satisfied immediately.

**Deliberately not built:** routing, matrix, delegation, SLA, escalation,
inbox, notifications. Phase 3 replaces the decision caller with the existing
approval engine; the state transition already lives in one place so both go
through it.

## 10. Protecting an approved request (§33)

Once `APPROVED`, editing is refused with a message saying a re-approval is
needed and that the path is not built yet. Rejected and cancelled requests are
likewise closed to editing. M4 makes the boundary real; Phase 3 adds the
re-approval route.

## 11. Security

| Boundary | How |
|---|---|
| Tenant | one database per customer — no new surface |
| Branch | `hreq_scope_gate()` on the module door, checking the request **and** the branch named on the way in — **and** `hreq_in_scope()` at each write, so the route is not the only check |
| Capability | **`mod.hiring.edit`** to raise, edit, submit, cancel or convert; **`mod.hiring.view`** to read; a management role **plus** the module to decide — see below |
| Segregation | the requestor may **not** decide their own request (master excepted) |
| Entitlement | `hiring-request(s)` → `hiring` → **hr**, in both module maps |
| Master | no `is_master()` override of the licence, the capability or the branch scope anywhere in M4. The single master exception is to **segregation of duties** (below), and it is the exception the application already states for report finalisation. |

### The capability model (corrected)

Who may raise a hiring request is a **capability** question, not a role-name one.
The first cut of M4 asked `is_coordinator_level()` — a role band — and that was
wrong twice over: it named roles instead of the right, and the band it names
(the seven management roles + Asst. Manager + Coordinator) is **wider than the
permission matrix**, which grants Asst. Manager no hiring right at all.

The right already existed and no new permission was created. `ACCESS_MODULES`
generates `mod.<module>.view` / `mod.<module>.edit` for every module; both are
granted per role **and** per user on Settings → Roles & permissions, and both are
enforced through the same `can()` choke point — licence first, then master, then
the granted set. `hiring` is one of those modules.

| Act | Right asked | Where it is asked |
|---|---|---|
| VIEW | `can('mod.hiring.view')` | `ops_module_gate()` before dispatch, **and** `ops_hiring_requests()` on entry |
| CREATE / EDIT | `can('mod.hiring.edit')` | `hreq_save()` — at the write, not only on the route |
| SUBMIT | `can('mod.hiring.edit')` | `hreq_submit()` |
| CANCEL | `can('mod.hiring.edit')` | `hreq_cancel()` |
| CONVERT to requisition | `can('mod.hiring.edit')` **and** `hreq_is_executable()` | `hreq_to_requisition()` |
| DECIDE (approve / reject) | `hreq_can_decide()` = the module **and** a management role | `hreq_decide()` |

`mod.hiring.edit` is the same right the application already requires before
project costing may create a requisition (`projcosting.php`), so the two routes
into recruitment now ask the same question of the same person.

**Approval authority is held apart from creation authority.** That separation is
the point of a request layer. Deciding stays where the application already puts
recruitment approval — a management act, as an offer is (`recruit_offer.php`) —
and Phase 3 replaces the caller with the configured approval matrix. Note that
`hreq_can_decide()` asks the module question **first**: written as a role band
alone it opened for a master on a workspace that had not bought the module, which
M10's permanent gate probe caught.

**Segregation of duties.** The requestor may not approve their own request. The
comparison is on `requested_by_id → users.id` — the canonical requestor
relationship — not on a name or a role. The one exception is a **master**,
because a single-administrator workspace has nobody else to approve, and that is
the same standing exception the application already writes into report
finalisation ("the approver and the issuer cannot be the same"). It is an
exception to segregation only: the licence, the module capability and the branch
scope all still apply above it.

`requested_by_id` is filled from whoever is signed in when nothing was chosen,
and a partial update that does not carry the field can no longer erase it.

### Branch scope — a gap the mutation testing found

Disabling `hreq_scope_gate()` outright broke **no test**. The gate was held in
place only by a source-level assertion (it still *contained* its two
`scope_allows()` calls, so reading the source proved nothing), and the acts that
follow it — submit, decide, cancel, convert — trusted the route to have asked.

A route gate is the right place to refuse early. It is the wrong place to be the
only check. Three things changed:

1. **The decision was separated from the refusal.** `ops_require()` redirects and
   exits, which makes a gate impossible to ask a question of — so the question now
   lives in `hreq_scope_reason()`, which answers with a reason or an empty string
   and can be tested directly. `hreq_scope_gate()` is the one-line refusal around
   it.
2. **Every write asks it too.** `hreq_in_scope()` is asked inside `hreq_save()`
   (for an existing request), `hreq_submit()`, `hreq_decide()`, `hreq_cancel()`
   and `hreq_to_requisition()`.
3. **So does the read.** The request detail asks the scope question where the
   record is actually read, so a bookmarked URL to another branch's request is
   refused even if the gate above it were removed.

The tests are **behavioural**: a user in another branch, holding every right there
is to hold, is refused edit, submit, decide, cancel and convert with nothing
written, and is refused the record by direct URL.

The same audit found two silent-erasure bugs in `hreq_save()`, both fixed: a
partial update that did not carry `office_id` moved the request to *no branch*
(and slipped past the scope check by omission), and one that did not carry
`requested_by_id` erased the canonical requestor. Both fields are now preserved
when the field is simply absent from the post.

Validation is against the real masters, not the dropdown: a foreign requestor,
an unknown or switched-off department, an unknown or switched-off position, an
out-of-scope branch and an unknown priority, employment type or request type are
each refused on the server.

## 12. What existing behaviour is untouched (§19, §22, §38)

- **Direct requisition creation still works**, with `hiring_request_id` NULL —
  and it is a supported, deliberate route, not a leftover. See
  `M4-REQUISITION-PATHS.md`. Its own gate was **not** changed by the correction.
  Provenance cannot be faked: `hiring_request_id` is not a field the requisition
  form accepts, and exactly one function in the application writes it.
- Requisition numbering is unchanged; the request has its **own** series,
  `HRQ-YYYY-NNNNNN`.
- Existing requisitions, candidates, offers and joinings are untouched, and M3's
  fulfilment reads them exactly as before.
- Nothing was migrated, and no historical requestor, approval date or department
  was invented (§39).
