# Phase 1 · Milestone 3 — The Deterministic Entitlement Engine

**Date:** 2026-09-14 · **Baseline:** `cc2e169` (M2)

---

## 1. The question

One authoritative function answers it:

```php
module_state($productModuleKey)   // lib/licence.php
```

Everything that needs an entitlement answer asks this. There is one place the
answer is decided and one place to read to know what it will be.

## 2. The seven states

| State | Meaning | Access |
|---|---|---|
| `INVALID_MODULE` | not in `PRODUCT_MODULES` | **DENY** |
| `CORE` | `admin` — every install needs it | **ALLOW, always** |
| `LICENCE_BLOCKED` | a signed licence excludes it | **DENY** |
| `TENANT_DISABLED` | the company switched it off itself | **DENY** (reversibly) |
| `ENTITLED` | licensed, or within the recorded ceiling | **ALLOW** |
| `NOT_ENTITLED` | a ceiling exists and omits it | **DENY** |
| `UNKNOWN` | hosted company, **no entitlement record** | **DENY** ← the change |

## 3. The defect this closes

`licence_entitled_ceiling()` returned `null` for a hosted company with a blank
`saas_entitled_modules`. `null` means *no ceiling*, so both readers —
`licence_disabled()` and `module_entitled()` — concluded that every paid module
was available.

**Absence of evidence was being returned as evidence of purchase.**

An empty ceiling now denies every non-core module. This is the answer
`lk_modules()` already gives for an `INVALID` or `MISSING` signed licence
(`lib/licencekey.php:314`) — a pattern that exists because returning `null` there
was a real defect, caught by the forgery test. Milestone 3 **extends an
established rule rather than inventing a second one.**

## 4. Precedence

Derived from the order the existing code already applies, not newly invented:

```
1  INVALID_MODULE     not in the registry
2  CORE               admin
3  LICENCE_BLOCKED    signed licence is the contract and outranks the cloud
4  TENANT_DISABLED    the company's own choice
5  ENTITLED           licensed, or named by the ceiling
6  NOT_ENTITLED       a ceiling exists and omits it
7  UNKNOWN            hosted, nothing recorded  →  DENY
```

**`NOT_ENTITLED` outranks `TENANT_DISABLED`**: what the customer cannot change
outranks what they can.

## 5. Entitlement and RBAC remain separate

| Question | Asked by | About |
|---|---|---|
| Did this **tenant** buy the module? | `module_entitled()` | commerce |
| May this **user** perform the action? | `can()` | RBAC |

`module_entitled()` is deliberately blind to `TENANT_DISABLED`: a company that
switched a module off still owns it and may switch it back on.

`can()` is unchanged. It still evaluates `licence_blocks()` **before** the master
bypass — the security property that stops a master user reaching an unbought
module — and a test now pins that ordering so it cannot be reversed silently.

## 6. The control install is never limited

`licence_ceiling_source()` returns `'none'` when `current_tenant() === ''`, so the
platform owner's own console has no ceiling and can never be locked out by this
change. Asserted for every module, in the worst state, before anything else.
Self-hosted single businesses are equally unaffected: they are governed by their
signed licence.

## 7. What changed in the code

`lib/licence.php` only:

| Function | Change |
|---|---|
| `licence_tenant_off()` | **new** — the tenant's own off-list, read separately from the ceiling. Extracted from `licence_disabled()`, which now calls it: one reader, no duplicate rule |
| `licence_ceiling_source()` | **new** — `none` / `recorded` / `blank`, the distinction between "not entitled" and "no record" |
| `licence_entitled_ceiling()` | returns `[]` instead of `null` when hosted and blank. Signature unchanged |
| `module_state()` | **new** — the authoritative answer |
| `module_state_is_entitled()` | **new** — the commercial subset |
| `module_entitled()` | now derives from `module_state()`; no rule of its own |

Not touched: `lib/access.php`, `can()`, `lib/ops.php`, routes, API, cron,
Marketplace, `is_master()`, tenant data. **No schema change** — the existing
settings were sufficient.

## 8. Live impact

| Workspace | Ceiling | Before | After |
|---|---|---|---|
| `acme-fire-safety` | `hr` | admin, hr | **admin, hr — unchanged** |
| `sachee-hr` | blank, never opened | n/a | n/a |
| `xyz-recurit` | blank, never opened | n/a | n/a |

No customer loses access. The measured inventory reported `would_lose: []`.

## 9. Deferred, not done

Evidence-based migration of a blank tenant (M4) — including whether a lost
ceiling may be repaired from `product_package`, which would be *manufacturing
entitlement* and is explicitly out of M3. Route, API, export, cron and public
enforcement (M5–M8). Marketplace (M9). `is_master()` audit (M10).

**Known limitation carried forward:** `licence_owner()` still returns `null` for
an access module no product module claims, which reads as *always available*.
M2 proved all 31 are owned, so it cannot fire today; it is a latent fail-open and
belongs to M5.
