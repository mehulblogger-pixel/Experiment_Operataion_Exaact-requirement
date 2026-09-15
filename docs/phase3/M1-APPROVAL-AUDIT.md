# Phase 3 · M1 — Approval Foundation Audit

**No code was written before this audit.** Everything below was read from the
code or proved with a probe against a booted application.

## 1. What already exists

### The approval engine — `lib/recruit_approval.php` (Phase 6)

It is **already entity-agnostic**. Four tables:

| Table | What it holds |
|---|---|
| `recruit_approval_rules` | configurable rules: entity + department / BU / grade / position + value band |
| `recruit_approval_levels` | the chain per rule: approver role **or** named user, SLA, reminder, escalation target |
| `recruit_approval_requests` | **`entity` + `entity_id`** — a polymorphic reference — plus status, `current_seq`, requester, timestamps |
| `recruit_approval_steps` | one row per level: approver, SLA due, status, `acted_by`, `acted_at`, `remarks` |

API: `appr_match()` (narrowest rule wins; already vocabulary-aware through
`dept_canon()` since M3) · `appr_start()` · `appr_current_step()` ·
`appr_can_act()` · `appr_act()` (approve/reject, advances or closes the chain) ·
`appr_callback()` (updates the underlying entity) · `appr_inbox()` /
`appr_inbox_count()` · `appr_tick()` (cron reminders + escalations) · e-mail
through the platform mailer.

`APPR_ENTITIES` is `REQUISITION`, `OFFER`, `SALARY`. **`HIRING_REQUEST` is not
there** — that is the one thing the engine does not yet know about.

### What M4 already left in place

`hiring_requests` **already carries every column M1 needs**: `status` (with
`UNDER_REVIEW` already in `HREQ_STATUS`), `approval_required`, `approval_ref`,
`decided_by`, `decided_at`, `decision_note`, `snapshot_json`, `submitted_at`.
`hreq_is_executable()` is the single executable-state question.
`hreq_in_scope()` / `hreq_scope_reason()` are the scope primitives.
`activity_log()` is the audit spine.

**M1 therefore needs no schema change at all.**

## 2. Findings — proved, not assumed

Both were found by reading the code and confirmed with a probe
(`appr_can_act()` called while signed in as a master, with the HR module on and
then off).

### FINDING A — `/my-approvals` has no module gate *(pre-existing)*

```
HR ON    module-gate my-approvals=true   recruit-approvals=true   hiring-requests=true
HR OFF   module-gate my-approvals=true   recruit-approvals=false  hiring-requests=false
```

`my-approvals` appears in **neither** `ops_module_gate()`'s route map **nor**
`ops_module_family()`'s prefix table, so `$mod` resolves to `null` and no module
question is asked. `ops_my_approvals()` itself only requires a signed-in user.
(`recruit-approvals` is gated, but only by accident of the `recruit` prefix.)

### FINDING B — the approval engine's master bypass ignores entitlement *(pre-existing)*

```php
function appr_can_act($step, $user = null) {
    ...
    if (function_exists('is_master') && is_master()) return true;   // ← no licence question
```

Probe result: `appr_can_act()` returned **true with the HR module switched off**.
Together with Finding A, a master on a workspace that has **not bought
recruitment** can open the recruitment approval inbox and act on steps. This is
the same defect class as M4's `hreq_can_decide()`, which §18 says must never
return — and M1 would route Hiring Request approvals through exactly this code.

### FINDING C — the engine has no branch scope

`scope_allows` / `scope_clause` appear **nowhere** in `lib/recruit_approval.php`.
Authority is "are you the named approver / do you hold the approver role", with
no branch question.

### FINDING D — the engine writes no activity audit

`activity_log()` appears **nowhere** in the approval engine. Decisions are
recorded on the step row (`acted_by`, `acted_at`, `remarks`) but nothing reaches
the application's activity trail.

### FINDING E — `requester` is a display name, not an identity

`recruit_approval_requests.requester` stores `user_name()` — a string. It cannot
be used for a reliable self-approval comparison, which is why §11's rule
(`requested_by_id → users.id`) must be answered from `hiring_requests`, not from
the approval row.

### FINDING F — no self-approval rule anywhere

`appr_can_act()` never compares the actor with the requester. Nothing today stops
a person who raised something from approving it, if they hold the approver role.

## 3. REUSE / EXTEND / CONNECT / MAP / BUILD

### REUSE — unchanged
- `recruit_approval_requests` / `recruit_approval_steps` — already polymorphic.
  **No new table, no new column.**
- `appr_start`, `appr_act`, `appr_can_act`, `appr_open`, `appr_steps`,
  `appr_current_step`, `appr_inbox`, `appr_tick`, the e-mail helpers.
- `appr_match()` and the whole rules/levels configuration screen.
- M4: `hreq_is_executable()`, `hreq_in_scope()`, `hreq_scope_reason()`,
  `hreq_can_create()`, `hreq_can_decide()`, the snapshot, `activity_log()`.
- The entitlement chain (`can()` → `licence_blocks()` first) and `is_master_of()`.

### EXTEND — smallest possible
- `APPR_ENTITIES` gains `HIRING_REQUEST`, so an administrator can configure a
  chain for it on the screen that already exists.
- `appr_callback()` gains a `HIRING_REQUEST` branch.
- `appr_act()` gains one guard hook, asked before it mutates: entitlement,
  branch scope and segregation of duties. Entity-scoped so the behaviour of
  `OFFER` / `SALARY` / `REQUISITION` is not changed by M1.
- `appr_can_act()`'s `is_master()` becomes `is_master_of('hiring')` — the
  existing licence-aware master helper (Finding B).
- `ops_my_approvals()` gains the licence question (Finding A).

### CONNECT
- `hreq_submit()` starts a chain when a rule matches; the request goes to
  `UNDER_REVIEW` and `approval_ref` records the chain.
- The chain's completion drives the Hiring Request's state through **one**
  authoritative writer, shared with the direct decision path.

### MAP
- `UNDER_REVIEW` (already in `HREQ_STATUS`) **is** "approval pending". No new
  status vocabulary, no second status store.
- Chain `APPROVED` → `APPROVED`; chain `REJECTED` → `REJECTED`.

### BUILD — genuinely new
- Nothing structural. Only the guard hook and the single decision writer, both
  small, both inside the existing layers.

## 4. Deliberate non-changes

- **`/my-approvals` is given the LICENCE question, not `mod.hiring.view`.**
  Requiring the permission would lock out a configured approver who legitimately
  holds no recruitment module (a Finance approver on an offer chain, say).
  Entitlement is the tenant's contract; capability is the person's role. M1
  fixes the first and does not narrow the second — narrowing the approver
  population is a customer-visible policy change and belongs with the matrix.
- **Segregation is added for `HIRING_REQUEST` only.** Applying it to every
  entity would change existing offer and salary behaviour, which M1 was not
  asked to do. Recorded as a Phase-3 question.
- Findings C and D are fixed **for the Hiring Request path**; the engine-wide
  versions are documented, not silently rewritten.
