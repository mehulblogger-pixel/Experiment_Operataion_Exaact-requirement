# PHASE 3 · M4 — MATERIAL CHANGE MATRIX

The authoritative field set. Every field below is a **real column** of
`hiring_requests` (`lib/hiringreq.php:77`) — nothing is invented.

**The test applied to each field:** *would an approver who saw the old value have
been authorising a different business commitment, or would a different person have
had to authorise it?* If yes → material. Not every field is material, and saying
so is the point of this matrix.

| Field | Material? | Why | Re-approval | Snapshot | Impact on active recruitment |
|---|---|---|---|---|---|
| `quantity` **increase** | **YES** | more headcount than was authorised — the single commonest way to spend authority nobody granted | required | yes | blocked above the approved ceiling |
| `quantity` **decrease** | no | asking for less than was authorised stays inside the approval | no — audited | yes | none; the ceiling simply falls |
| `hiring_department_id` | **YES** | a different department's headcount and budget, and the approval chain is selected by department | required | yes | blocked |
| `designation` | **YES** | a different role at a different cost band | required | yes | blocked |
| `grade` | **YES** | the pay band the commitment sits in | required | yes | blocked |
| `position_id` | **YES** | which establishment seat is being filled | required | yes | blocked |
| `new_position_requested` | **YES** | asks to create establishment that did not exist | required | yes | blocked |
| `job_title` | **YES** | the thing that was approved, in the approver's own words | required | yes | blocked |
| `employment_type` | **YES** | permanent vs contract is a different commitment and a different cost | required | yes | blocked |
| `office_id` | **YES** | changes the branch the cost belongs to **and** the approval chain and scope | required | yes | blocked |
| `work_location` | **YES** | where the person actually works — cost, compliance and mobility follow it | required | yes | blocked |
| `client_id` | **YES** | which client contract the cost is billed against | required | yes | blocked |
| `request_type` | **YES** | NEW vs BACKFILL vs PROJECT is the *basis* on which it was authorised | required | yes | blocked |
| `requested_by_id` | **YES** | segregation of duties was evaluated against this identity; changing it can make the recorded approver invalid | required | yes | blocked |
| `approval_required` | **YES · never editable after approval** | turning it off would retire the approval entirely — this is a bypass, not a change | refused outright | yes | blocked |
| `required_by` | no | when it is wanted, not what was authorised | no — audited | yes | none |
| `priority` | no | urgency, not commitment | no — audited | yes | none |
| `reason` | no | the narrative behind an unchanged ask | no — audited | yes | none |
| `project_ref` | no | a reference string; the **client** is the commercial fact and is material above | no — audited | yes | none |
| `job_description` | no | elaboration of a role whose title, grade and department are unchanged | no — audited | yes | none |
| `requesting_department_id` | no | who asked, not what the business committed to | no — audited | yes | none |
| `requested_by_name` | no | display only; the identity is `requested_by_id` and is material | no — audited | yes | none |
| `req_no`, `status`, `approval_ref`, `decided_*`, `submitted_at`, `snapshot_json`, `created_*`, `updated_*` | n/a | system-controlled; not user-editable through `hreq_save()` | — | — | — |

## Rules this matrix encodes

1. **Quantity is asymmetric.** Increase is material, decrease is not. Treating both
   the same would force a re-approval on a manager reducing a request — annoying,
   and it teaches people to route around the control.
2. **`approval_required` is not a field, it is the control.** It may not be edited
   after approval at all. Re-approval is not offered, because the honest answer is
   refusal.
3. **Every change is audited, material or not.** Non-material means *no
   re-approval*, never *no record*.
4. **The snapshot captures everything**, material or not. Deciding materiality is a
   comparison over the snapshot, so the snapshot cannot be selective.
5. **Materiality is computed from the approved snapshot, not from the previous
   edit.** Ten non-material edits followed by one material one must still compare
   against what the approver actually saw.
