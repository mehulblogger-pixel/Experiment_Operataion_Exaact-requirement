# Phase 2 · M4 — Hiring Request Architecture

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
| Branch | `hreq_scope_gate()` on the module door, checking the request **and** the branch named on the way in |
| Permission | coordinator to raise; **administrator** to approve or reject |
| Entitlement | `hiring-request(s)` → `hiring` → **hr**, in both module maps |
| Master | no `is_master()` override anywhere in M4 |

Validation is against the real masters, not the dropdown: a foreign requestor,
an unknown or switched-off department, an unknown or switched-off position, an
out-of-scope branch and an unknown priority, employment type or request type are
each refused on the server.

## 12. What existing behaviour is untouched (§19, §22, §38)

- **Direct requisition creation still works**, with `hiring_request_id` NULL.
- Requisition numbering is unchanged; the request has its **own** series,
  `HRQ-YYYY-NNNNNN`.
- Existing requisitions, candidates, offers and joinings are untouched, and M3's
  fulfilment reads them exactly as before.
- Nothing was migrated, and no historical requestor, approval date or department
  was invented (§39).
