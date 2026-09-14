# Phase 1 · Milestone 3 — Entitlement Precedence

**One decision path. No competing engine.**

## The chain

```
ACTION
  └─ can($perm)                                        lib/access.php:767
       ├─ licence_blocks($perm)          ← ENTITLEMENT, evaluated FIRST
       │    └─ licence_owner(access) → product
       │         └─ licence_enabled(product)
       │              └─ licence_disabled()
       │                   ├─ signed licence (authoritative)
       │                   ├─ licence_tenant_off()    the company's own choice
       │                   └─ licence_entitled_ceiling()
       │                        ├─ control install  → null  (no limit)
       │                        ├─ recorded         → the list
       │                        └─ blank            → []    (deny all non-core)
       └─ master || permission           ← RBAC, evaluated SECOND
```

**The ordering is the security property.** A master user cannot reach a module
the tenant never bought, because the licence question is answered before the
master bypass is consulted. `tests/test_entitlement_engine.php` asserts this
ordering by reading `can()` itself, so it cannot be reversed without a failing
test.

## Resolution order inside `module_state()`

| # | Test | State | Why it sits here |
|---|---|---|---|
| 1 | not in `PRODUCT_MODULES` | `INVALID_MODULE` | an unrecognised key is never active |
| 2 | `licence_is_core()` | `CORE` | every install needs administration |
| 3 | signed licence excludes it | `LICENCE_BLOCKED` | the contract outranks the cloud |
| 4 | `licence_tenant_off()` names it | `TENANT_DISABLED` | the company's own, reversible choice |
| 5 | ceiling `none` or names it | `ENTITLED` | licensed or purchased |
| 6 | ceiling exists, omits it | `NOT_ENTITLED` | a decision was recorded |
| 7 | hosted, ceiling blank | `UNKNOWN` | **a gap is not a grant** |

## Conflicts, resolved explicitly

| Situation | Answer | Reason |
|---|---|---|
| entitled **and** tenant-disabled | `TENANT_DISABLED` — denied at runtime, still entitled | switching off is not un-buying |
| not entitled **and** tenant-disabled | `NOT_ENTITLED` | what the customer cannot change outranks what they can |
| core **and** named in `modules_off` | `CORE` | ignored on purpose, not obeyed |
| signed licence **and** cloud ceiling | the signed licence | it is the contract |
| control install **and** blank ceiling | `ENTITLED` | the owner is never limited |
| unknown module **and** master user | `INVALID_MODULE` → denied | RBAC cannot grant what commerce never sold |
