# RB-3 · Step 2 — Implementation map
## Duplicate-staff match + explicit acknowledgement

*Inspection first, per the instruction's §4. Written **before** any code.*

**Baseline:** `1be25de` (RB-3 Step 1 complete and approved). Working tree clean.
**Method:** the codebase was searched; no table or function name was assumed.

---

## 1. Existing candidate / application source

| | |
|---|---|
| **Table** | `candidates` — created at `lib/ops.php:192` |
| **Identity fields actually present** | `first_name`, `middle_name`, `last_name`, `email`, `mobile` |
| **Grouping key** | `person_ref` (`lib/recruit.php:1034`) — "these applications are one human" |
| **Link to staff** | `inspector_id` — set only by the conversion |
| **Public intake** | `lib/careers.php:144`, de-dupes **per requisition only** (correct: invariant I3) |

**There is no date of birth, no PAN and no Aadhaar anywhere in this
application** — confirmed by searching for `'dob'`, `date_of_birth`, `pan` and
`aadhaar` across `lib/`. Per the instruction's §5 these are **not invented**.
The matching signals are therefore the ones that exist: **mobile, e-mail, name**.

## 2. Existing staff / employee source

| | |
|---|---|
| **Table** | `inspectors` — created at `lib/ops.php:136` |
| **Identity fields** | `name`, `first_name`, `middle_name`, `last_name`, `email`, `mobile` |
| **Employee number** | `emp_code` — **unique for life since Step 1** (`ux_inspectors_emp_code`) |
| **Liveness** | `status`; blank counts as ACTIVE (field-finding #26) |

## 3. Existing person identity fields — the complete list

Candidate `first_name`/`middle_name`/`last_name`/`email`/`mobile`
↔ inspector `first_name`/`middle_name`/`last_name`/`name`/`email`/`mobile`.

That is the whole overlap. Nothing else is common to both sides.

## 4. Existing acceptance / hiring function

`rcv_convert($candId, $opt)` — `lib/recruit.php:1273`. **One** production caller:
`lib/ops.php:5775`, gated on `$_POST['make_inspector']`. It already asks its own
permission, tenant, scope, state, execution-boundary, branch and entitlement
gates before opening its transaction, and returns a deterministic refusal code.

**This is where the new gate belongs** — the action asks its own questions
(invariant I27), not the page that called it.

## 5. Existing stage-movement function

Route `candidate-stage`, `lib/ops.php:5690`. **Not changed by this step.** The
stage move still commits before the conversion is attempted, exactly as today;
changing that is the atomic-acceptance work and is explicitly out of scope.

## 6. Existing audit mechanism

`act_log($entityKind, $entityId, $kind, $subject, $opt)` → the `activities` table
(`lib/activity.php:115`, insert at `:467`). Recruitment wraps it as
`rcv_log($candId, $kind, $subject)` (`lib/recruit.php:1253`), which writes
`IDENTITY_LINKED` / `IDENTITY_REFUSED` against the candidate.

**Reused as-is. No second audit system.**

## 7. Existing tenant boundary

**Structural: one database per tenant, no `tenant_id` column.** `db()` is this
tenant's database and nothing else is reachable from the request. A cross-tenant
match is therefore impossible *by construction* rather than by a filter — which
is stronger, and is what the X12 test must actually demonstrate (two real
databases, as Batch 1 did).

## 8. Existing permission mechanism

| | |
|---|---|
| **Who may convert** | `is_coordinator_level()`, asked inside `rcv_convert()` |
| **Which records they may open** | `connect_identity_scope_ok('candidate'|'inspector', $id)` |
| **CSRF** | **central and unconditional** — `index.php:1152` rejects any POST whose `_csrf` does not match; `csrf_stamp_forms()` (`lib/helpers.php:327`) adds the field to every POST form |

**No new permission is introduced.** Acknowledging is part of converting and
carries the same right.

## 9. Existing duplicate / acknowledgement precedents

| | Mechanism | Verdict |
|---|---|---|
| `cand_find_duplicates()` `lib/recruit.php:318` | candidate ↔ **candidate**, confidence **96 / 94 / 72 / 46**, `LIMIT 500` | **The scoring model to reuse** — §6 forbids inventing a new one when the architecture already has one |
| `cand_submission_dupes()` `lib/recruit.php:346` | same person to same client | unchanged |
| `connect_identity_suggestions()` `lib/connect_identity.php:548` | professional ↔ inspector, e-mail then mobile | unchanged |
| **candidate ↔ inspector** | **does not exist** | the gap this step fills (audit finding **N4**) |
| `sub_ack` `views/ops/candidate_detail.php:337` | a **real checkbox** — "I've checked the above — submit anyway" | **the pattern to follow** for the tick |
| `dup_ack` `views/ops/candidate_form.php:55` | `<input type="hidden" name="dup_ack" value="1">`, rendered automatically whenever duplicates were shown | **the pattern NOT to follow** — it acknowledges itself on the next submit. Recorded in §12; not changed by this step |

## 10. Files and functions that will change

| # | File · symbol | Change | Why |
|---|---|---|---|
| 1 | `lib/recruit.php` · **`workforce_matches()`** *(new)* | read-only: applicant → live team members, reusing the existing 96/94/72/46 scale | the candidate↔inspector comparison does not exist anywhere |
| 2 | `lib/recruit.php` · **`workforce_ack_*()`** *(new)* | issue and verify an acknowledgement bound to tenant + candidate + user + evidence + time | §10 requires the acknowledgement to be bound and non-reusable |
| 3 | `lib/recruit.php` · `rcv_convert()` | one new gate **before** the transaction; one new refusal code `WORKFORCE_MATCH` | the action must ask its own question (I27); nothing is written on refusal |
| 4 | `lib/recruit.php` · `RCV_CODES` | one entry | a deterministic, showable refusal |
| 5 | `lib/ops.php` · route `candidate-stage` | pass the submitted tick through to `rcv_convert()`; nothing else | the route is a courier, not a gate |
| 6 | `views/ops/candidate_detail.php` | show the warning and the tick when a strong match exists | §7 and §8 |
| 7 | `tests/test_rb3_step2_dupmatch.php` *(new)* | the X1–X20 matrix plus negatives | §15, §16 |
| 8 | `tests/_rb3s2_worker.php` *(new)* | real-process concurrency | §14 |

## 11. What will deliberately NOT change

- **No atomic acceptance flow, no stage-move ordering change.** The stage move
  still commits first; that is RB-3 Step 3 / RB-1 and needs the owner's §0.2 answer.
- **No RB-2.** No fulfilment or status change.
- **The hidden `make_inspector` checkbox stays.** Removing it is RB-1.
- **No employee-number change.** Step 1 is closed and is not reopened.
- **No new table** — see §12.
- **No new permission, status, lifecycle transition, workflow engine, tenant
  architecture or database architecture.**
- **No demo load/unload redesign.** The Step 1 demo-unload item stays separate.
- **`dup_ack` on the candidate *creation* form is left alone** — it is a
  different screen and a different action, and changing it is not this step.
- **No new identity fields.** No DOB, PAN or Aadhaar is added to make a test pass.

## 12. No new table — and why none is needed

The instruction's §11 requires proof before any table is created. **None is
created.** The acknowledgement is **stateless**: a keyed signature over the exact
thing being acknowledged, carried as the checkbox's own value.

| Requirement (§10) | How the stateless token meets it |
|---|---|
| not reusable for another application | the candidate id is inside the signature |
| not reusable for another candidate | as above |
| not reusable for another tenant | the key lives in **this tenant's** `settings` row, so another tenant's signature cannot verify here |
| not reusable after the evidence changes | the evidence hash is inside the signature |
| not reusable by another user | the user id is inside the signature |
| still valid? | an issued-at timestamp is inside the signature, with a short expiry |
| not pre-checked, not global | an **unticked** checkbox sends nothing at all |

The only stored item is a per-workspace random key, kept in the **existing**
`settings` table (`lib/access.php:983` / `:1011`), which already exists for
exactly this kind of value and is already per-tenant.

**Lifecycle:** created once, on first use. **Cleanup:** none needed — nothing
accumulates. **Indexing:** none — `settings.skey` is already the primary key.
**Concurrency:** two processes creating the key at once is safe; the upsert is
idempotent, and a token signed with either value verifies while that value is
current.

---

## 13. What the gate will do, in order

```
rcv_convert()
  … existing gates 1–7, unchanged …
  NEW 7a  WORKFORCE MATCH
          $m = workforce_matches($cand)
          no STRONG match        → carry on exactly as today
          STRONG match AND a valid, bound, unexpired tick
                                 → carry on, and AUDIT which matches were shown
          STRONG match, no tick  → REFUSE 'WORKFORCE_MATCH'
                                   nothing written · candidate untouched · audited
  … the transaction, unchanged …
```

**Name-only similarity never refuses** — see the rules document.

---

*Nothing is implemented by this document. Code begins after it, per §4.*
