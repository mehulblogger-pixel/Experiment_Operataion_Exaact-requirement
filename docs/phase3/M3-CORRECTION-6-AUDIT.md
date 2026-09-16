# Phase 3 · M3 CORRECTION #6 — AUDIT (before any code was written)

§1 requires the existing activity/audit mechanism to be audited first, and forbids
building a second audit system. This is that audit. Every statement below was
established by running the code, not by reading it.

---

## 1 · What the audit trail is

There is **one** spine: `act_log()` in `lib/activity.php`, writing to `activities`.
`ACT_ENTITIES` maps an entity code to a label and the route that opens it. The
approval module already files on this spine — M1 registered `HIRING_REQUEST` and
`REQUISITION`, M2 registered `APPROVAL_POLICY` and `APPROVAL_DELEGATE`. **No new
audit system is needed, and none was built.**

`/recruit-approvals?id=<rule_id>` is an existing screen that opens a single
approval policy (`views/ops/approval_rules.php:36` already links that way), so
`APPROVAL_POLICY` is a subject that genuinely opens.

## 2 · Who writes approval audit rows

| Call site | Guard before correction #6 |
|---|---|
| `appr_audit_sla()` — every SLA, reminder and escalation event | **none at all** |
| `appr_audit_notify()` — a decision that could not be notified | entity **TYPE** in `ACT_ENTITIES` (D2) |
| `appr_rule_save()` etc. | fixed subject `APPROVAL_POLICY` + a live rule id |
| `appr_delegate_*()` | fixed subject `APPROVAL_DELEGATE` + a live row id |

The last two are already correct. The first two are the subject of J1.

## 3 · What `act_log()` does with a kind it does not know

```php
if (!isset(ACT_ENTITIES[$entityKind])) $entityKind = '';
```

It **blanks the type and still writes the row, keeping the id.** So an unguarded
caller passing `OFFER` does not produce "no row" — it produces a row with
`entity_kind=''` and a live `entity_id`. That is the orphan D2 explicitly named:
*a blank entity_kind with an arbitrary entity id.*

## 4 · Controlled probe — what each writer actually produced

Rows were inspected **at the moment they were written**, because a fixture the
suite deletes later would otherwise look like a dangling reference. (My first
probe made exactly that mistake and was discarded.)

```
--- appr_audit_sla(), source record PRESENT ---
  HIRING_REQUEST  kind=HIRING_REQUEST id=98800001 type=supported record=exists
  REQUISITION     kind=REQUISITION    id=98800002 type=supported record=exists
  OFFER           kind=''             id=98800003    <-- ORPHAN
  SALARY          kind=''             id=98800004    <-- ORPHAN

--- appr_audit_sla(), source record DELETED ---
  HIRING_REQUEST  kind=HIRING_REQUEST id=98800001 type=supported record=MISSING
  REQUISITION     kind=REQUISITION    id=98800002 type=supported record=MISSING
  OFFER / SALARY  kind=''             id=…            <-- ORPHAN

--- appr_audit_notify(), source record DELETED (the reported J1 path) ---
  HIRING_REQUEST  kind=HIRING_REQUEST id=98800001 type=supported record=MISSING
  REQUISITION     kind=REQUISITION    id=98800002 type=supported record=MISSING
```

## 5 · Findings

| | |
|---|---|
| **J1-a** — the reported defect | `appr_audit_notify()` writes a supported type with a missing record. Its guard asks about the TYPE; the reason it is writing under says the RECORD is gone. |
| **J1-b** — the sibling | `appr_audit_sla()` has **no guard at all**, so it does the same thing on the SLA/reminder/escalation path, which D2 never touched. |
| **J1-c** — the orphan | For `OFFER` and `SALARY`, `appr_audit_sla()` produced rows with **a blank type and a live id** — the very pattern D2 forbids — and has done since M3 §24. |

All three are one sentence, which is G1's sentence one layer in:

> **A SUPPORTED TYPE IS NOT AN OPENABLE RECORD.**

## 6 · Decision — what to build

Not three patches. **One rule, in one function, called by every writer in the
module**, because the recurring failure across corrections #1–#5 has been a rule
applied at one call site and not its sibling.

`appr_audit_ref_ok($kind, $id)` answers all five clauses of §1:

1. the type is supported by the timeline (`ACT_ENTITIES`)
2. the id is valid (`> 0`)
3. the record exists
4. it belongs to **this** tenant — structural: `appr_entity_record()` and
   `appr_rule()` read the live connection, so another workspace's row simply does
   not resolve here
5. it resolves through the **existing** entity mechanism (G1's single resolution
   point), not a private one

`appr_audit_subject($req)` then picks the smallest existing subject that opens:

1. the source record itself, when it is genuinely openable here;
2. else the **approval policy governing this chain** — already registered, already
   has a screen, already tenant-bound;
3. else **nothing**. A row nobody can follow is worse than no row.

No new entity kind, no new route, no new table, no second audit system, and no
historical approval deleted because its source record disappeared.

## 7 · One accuracy trap found while designing the fix

The obvious implementation appends *"source record unavailable"* whenever the
event falls back to the policy. That would be **false** for an offer or salary
whose record is alive and well and which fell back only because its type is not on
the timeline — a misleading event, which §2 forbids. The wording is therefore
driven by a separate question, `appr_audit_source_gone()`, and is asserted in both
directions (C6.1 proves a live record is never described as unavailable; C6.4
proves a deleted one always is).

## 8 · Scope note

H1, H2 and H3 from the correction #5 adversarial audit are **not** in this
correction's scope and remain open. Correction #6 makes the rows H2 complains
about *openable*; it does not change how often they are written.
