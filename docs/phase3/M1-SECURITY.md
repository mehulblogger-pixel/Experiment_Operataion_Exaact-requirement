# Phase 3 · M1 — Security

## 1. Two pre-existing defects M1 closed

Both were found by reading code, confirmed with a probe, and both sat directly in
the path M1 now depends on.

### FINDING A — the approval inbox had no module gate

`/my-approvals` was in neither `ops_module_gate()`'s route map nor
`ops_module_family()`'s prefix table, so **no module question was ever asked**.
`ops_my_approvals()` itself required only a signed-in user.

```
HR ON    my-approvals=true   recruit-approvals=true   hiring-requests=true
HR OFF   my-approvals=true   recruit-approvals=false  hiring-requests=false
                    ↑ the recruitment approval inbox opened on a workspace
                      that had not bought recruitment
```

**Fixed** by asking the **licence** in `ops_my_approvals()`.

> Why the licence and not `mod.hiring.view`: entitlement is the tenant's
> contract, capability is the person's role. Requiring the hiring permission
> would lock out a configured approver who legitimately holds no recruitment
> module — a Finance approver on an offer chain. Narrowing the approver
> population is a policy change M1 was not asked to make.

### FINDING B — the engine's master bypass ignored the licence

```php
if (function_exists('is_master') && is_master()) return true;   // before
if (is_master_of('hiring')) return true;                        // after
```

Probed: `appr_can_act()` returned **true with HR switched off**. Combined with
Finding A, a master on an unlicensed workspace could act on recruitment approval
steps — the same defect class as M4's `hreq_can_decide()`, which must never
return. `is_master_of()` is the existing licence-aware helper: master, but only
for a module the installation actually has.

## 2. Entitlement — tested, not assumed

| Case | Result |
|---|---|
| licensed, rightful approver | allowed |
| **unlicensed**, rightful approver | denied at the engine |
| **unlicensed + master** | denied — `appr_can_act()` and `appr_act()` both refuse |
| direct URL to the inbox, unlicensed | denied by the route |
| direct helper invocation (`appr_act()`) | denied — the guard is at the choke point, not the route |
| AJAX / API | none exists for this path |

Entitlement is asked **first**, before anything about the person.

## 3. Branch scope — at the decision, not at the route

`appr_guard()` asks `hreq_in_scope()`. The test signs in as a foreign-branch user
who **does** hold the approver role, shows `appr_can_act()` says yes, and then
shows the decision is refused and nothing written.

This follows the M4 correction's finding: a route gate is the right place to
refuse early; it is the wrong place to be the only check.

## 4. Segregation of duties

Enforced in the authoritative decision path, compared on
`requested_by_id → users.id`. The test proves it against a requestor who holds
the approver role — it is not a hidden button. One master exception, unchanged
and not broadened.

## 5. FINDING G — the audit calls were dead

`lib/hiringreq.php` (M4) and `lib/reqfulfil.php` (M3) called **`activity_log()`**,
which **does not exist anywhere in this application**. Every call sat behind
`function_exists()`, so all of it was a silent no-op: raising, submitting,
deciding and cancelling a hiring request were **never audited**.

Worse, M4's own test asserted only that the *call* was present — a source-string
match, which passed against a function that does not exist. This is exactly the
failure mode the M1 brief warns about.

**Fixed**: `HIRING_REQUEST` and `REQUISITION` are registered on the real audit
spine (`ACT_ENTITIES`), the six dead calls now call `act_log()`, and the M1 tests
**read the rows back** instead of matching on source text.

## 6. Direct manipulation — what was tried

| Attempt | Result |
|---|---|
| `DRAFT` → `APPROVED` through the one writer | refused (state rule) |
| approve an already-`REJECTED` request | refused; it stays rejected |
| approve a `CANCELLED` request | refused |
| replay the same approval step | refused; the step is no longer pending |
| decide directly while a chain is open | refused; the chain is authoritative |
| convert from `DRAFT` / `SUBMITTED` / `UNDER_REVIEW` / `REJECTED` / `CANCELLED` | refused by `hreq_is_executable()` |
