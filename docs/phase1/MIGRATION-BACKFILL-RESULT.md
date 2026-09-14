# Phase 1 · Milestone 4 — Existing Customer Entitlement Migration

**Date:** 2026-09-14 · **Baseline:** `7eeccfd` (M3, 7,322 passing)
**Schema changes: NONE.** Customer data written: none by this milestone.

---

## 1. The rule

**Entitlement is never manufactured.** A plan name, a package, an old default, a
blank record, the presence of code, a route, a permission or a master user prove
nothing about what a customer bought. Only the commercial record does.

Where there is no evidence, the workspace is **classified and left untouched** —
never granted, never quietly stripped.

## 2. Control-side vs runtime-side (§8 conclusion)

| Field | Where | Authoritative for migration? |
|---|---|---|
| `saas_tenants.enabled_modules` | control DB | **YES** — the only field whose purpose is recording what was sold. Written by provisioning, by the super-admin console ("tick exactly what they pay for") and by billing: the acts of selling |
| `saas_tenants.plan` | control DB | **NO** — a template that *seeds* `enabled_modules`; it is not itself a record of sale |
| `saas_tenants.status` | control DB | gates migration (inactive ⇒ BLOCKED) |
| `saas_entitled_modules` | tenant | the runtime ceiling — the thing being migrated **to** |
| `saas_paid_modules` | tenant | secondary evidence of purchase; used only by the boot-chain backfill (§5) |
| `product_package` | tenant | **NO** — the plan again, tenant-side. Explicitly not used |
| `modules_off` | tenant | the customer's own reversible choice. **Never** read as cancelling a purchase |
| `saas_provisioned` | tenant | whether there is any runtime state to migrate at all |

## 3. Classification

`entmig_assess($control, $runtime)` — a pure function over two plain arrays, so
every case is testable without a database.

| Order | Condition | Class |
|---|---|---|
| 1 | workspace unreadable | `ERROR` |
| 2 | signed licence present | `BLOCKED` — the licence is authoritative |
| 3 | customer not `active` | `BLOCKED` — a commercial decision, not a migration |
| 4 | never opened | `NO_CHANGE_REQUIRED` — provisioning writes the ceiling at first sign-in |
| 5 | ceiling recorded **and** matches the commercial record | `NO_CHANGE_REQUIRED` |
| 6 | ceiling recorded **and** differs | `AMBIGUOUS` — two deliberate records disagree; a person decides |
| 7 | ceiling blank **and** the commercial record names something sold | `SAFE_TO_MIGRATE` |
| 8 | ceiling blank **and** nothing recorded | `AMBIGUOUS` — nothing to migrate from |

Core is normalised away on both sides, so a record of `[admin,hr]` and a ceiling
of `[hr]` compare as **equal** rather than as a disagreement.

## 4. Why migration cannot remove access

Under M3 a blank ceiling entitles **nothing**. So for every `SAFE_TO_MIGRATE`
record the *before* is empty and migration can only **restore**. A guard covers
the case anyway: if `lost` is ever non-empty the record is downgraded to
`AMBIGUOUS` and not applied. **No silent lockouts.**

## 5. A defect found and repaired: `saas_entitlement_ensure()`

`lib/saas_tenants.php:369`, called from the boot chain (`lib/db.php:523`).

It set a blank ceiling to *"whatever is switched on right now"*. With a blank
ceiling that was **everything** — so an ordinary page load wrote a manufactured
purchase of every module permanently into the customer's record. This is exactly
what §6 forbids, and it had **no test at all**.

M3 closed the hole it fed on, which left it writing an empty string on every page
load for ever. It now grants only from evidence the workspace itself holds
(`saas_paid_modules`) and otherwise **writes nothing**, leaving the workspace
`UNKNOWN` for the deliberate, audited migration to resolve from the commercial
record. Six tests now cover it, including the control install and idempotency.

## 6. Applying, recovery, audit, idempotency

`entmig_apply_here($assessment)` writes into the workspace currently entered:

| Setting | Purpose |
|---|---|
| `saas_entitled_modules` | the migrated ceiling |
| `saas_entitled_modules_prev` | **recovery** — the value before, written once and never overwritten by a later run |
| `saas_entitlement_migrated_at` | when |
| `saas_entitlement_migrated_from` | the evidence it came from |

**Idempotent:** an unchanged source writes *nothing* — not the ceiling, not the
timestamp, not the audit row. **Audit:** the existing sealed `idems_log` records
`ENTITLEMENT_MIGRATED` with before, after and evidence. No second audit system.
**Non-destructive:** nothing is deleted; the previous value is preserved.

## 7. Live assessment

The read-only assessment is part of the existing admin-gated inventory tool, so
the report the operator reads and the decision the migration would take come from
**one classifier and cannot drift apart**.

Against the three workspaces as last reported:

| Workspace | Sold | Ceiling | Class |
|---|---|---|---|
| `acme-fire-safety-recuirtment-company` | hr | hr | **NO_CHANGE_REQUIRED** |
| `sachee-hr-recruitment-services` | hr | — | **ERROR** (never opened, unreadable) |
| `xyz-recurit` | hr | — | **ERROR** (never opened, unreadable) |

**No workspace requires migration.** Acme already matches its commercial record
exactly; the other two have no runtime state to migrate and will receive their
ceiling from provisioning when first opened.

⚠️ This is computed from the last reported state. **The live assessment must be
re-run on the server** to satisfy "every existing customer assessed" against
current data.

## 8. Known limitations

* The live assessment requires the operator to run the tool; this environment
  cannot reach the production database.
* A workspace that cannot be read is `ERROR` and is neither assessed nor
  migrated — correct, and it means an unopened workspace is never resolved by
  migration. It does not need to be: provisioning gives it a ceiling on first
  sign-in.
* `saas_paid_modules` is treated as evidence by the boot-chain backfill. It is
  written only by the purchase path, but it is tenant-side and therefore weaker
  than the control record. Documented rather than assumed.
