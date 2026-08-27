# Scripted Test Run — Onboarding & Access Scope (with exact verdicts)

**Scope.** The security foundation of the E2E flow (`docs/qa-edge-cases/00-end-to-end-flow.md`): who a user is,
what they can do, and which office/SBU their data is confined to — because this scope silently filters every
later stage (a quote, call, job or invoice is only ever *its branch's*). The access verdicts below were
**produced by the live engine** (`scope_offices`, `scope_allows`, `can`); ⚙ marks a verified result, 📘 a
cited rule.

**The two dials.** Every user resolves to (1) a **permission set** — `can('x')` — and (2) an **office/SBU
scope** — `scope_offices()` / `scope_sbus()` = `'ALL'` or a list. Lists are filtered by `scope_clause()`;
single records by `scope_allows()` (§51). Both fail **closed**.

---

## 1. Seed data

| Object | Field | Value |
|---|---|---|
| Office **Ahmedabad** | is_ahmedabad, id | 1 (HQ / the fail-closed default office) |
| Office **Branch B** | id | 18 |
| **Master** | is_superuser | 1 |
| **Branch manager** | role BRANCH_MANAGER, home office | Branch B (18) — a "OWN" scope role |
| **Override user** | role COORDINATOR, `permissions` | `"mod.jobs.view"` (a custom list) |

---

## 2. Step-by-step, with exact verdicts

### Step A — The master ⚙

| Check | **Expected** | Verified |
|---|---|---|
| `scope_offices()` | **"ALL"** | ⚙ |
| `scope_allows(any office)` | **TRUE** | ⚙ |
| `can(any permission)` | true (bypasses every gate) | 📘 |

The master is the installation's root key — all offices, all SBUs, all permissions.

### Step B — A branch user (BRANCH_MANAGER, home = Branch B) ⚙

| Check | **Expected** | Verified |
|---|---|---|
| `scope_offices()` | **[18]** (own branch only) | ⚙ |
| `scope_allows(18)` — own branch | **TRUE** | ⚙ |
| `scope_allows(1)` — Ahmedabad (another branch) | **FALSE** — cross-office denied (§51) | ⚙ |
| `scope_allows(null)` — no office on the record | **FALSE** — null resolves to Ahmedabad, which is out of this user's scope (**fail-closed**) | ⚙ |

✅ **PASS if** a branch user is allowed their own office, denied another, and denied a record with **no** office
(the default is *deny*, not *allow*). This one function is the scalar twin of the list scope — the same rule on
a single record.

### Step C — Single-record access (§51 IDOR) 📘

The branch user tries to open a record from **another** branch **directly by id** (not via a list):

| Route | Guarded by | **Expected** |
|---|---|---|
| `/job?id=` (other office) | `scope_allows(executing_office, sbu)` | **denied** |
| `/document`, `/document-pdf` | `scope_allows(office, sbu)` | denied |
| `/invoice`, `/invoice-print` | `scope_allows(office)` (when set) | denied |
| `/endorsement-file`, `/checkin-photo` | via the parent's office/SBU | denied |

✅ **PASS if** every fetch-by-id is denied out of scope — matching the list scope, so there is no "guess the id"
back-door. (This is exactly the gap §51 closed; see `tests/test_p2_idor_scope.php`.)

### Step D — Global search scope (§22) 📘

The branch user searches inquiries / contracts.
**Expected:** only in-scope rows returned — the search box honours the **same** SBU/office scope as every list;
no leak through the one door that used to skip the check. **Reflected in:** global search;
`tests/test_p2_search_scope.php`. 🟠

### Step E — Per-user override **replaces** role defaults (the footgun) ⚙

Give the override user `permissions = "mod.jobs.view"`:

| Check | **Expected** | Verified |
|---|---|---|
| `can('mod.jobs.view')` | **TRUE** | ⚙ |
| `can('ops.job.close')` | **FALSE** — a COORDINATOR default, **replaced** (not added) | ⚙ |
| `can('ops.call.create')` | **FALSE** — gone | ⚙ |

✅ **PASS if** a custom `permissions` list gives **exactly** those rights and drops the rest. This is powerful
and a **known footgun**: an override is a *replacement*, not an addition. (Module `access.php:442`
`user_effective_perms` — module defaults are merged so a login never loses screen access, but named
permissions are exactly the list.)

### Step F — Lock-out footgun 📘

Give a user an override that lacks **any** admin/settings permission.
**Expected:** the user can no longer reach the admin area — recoverable **only by a master** (R3/R11). The admin
area is hidden for a role with no admin access (not shown-then-403). **Reflected in:** nav; area homes. 🟠

### Step G — Sign-in security 📘

| Case | Expected | Reflected in |
|---|---|---|
| Wrong password ×N | account throttled / locked per policy | `login_attempts`; sign-in |
| 2FA enabled | a phone code required as well as the password | two-factor screen |
| Invitation / SSO | handoffs accepted or refused and recorded | `sso.php`; audit |

### Step H — Accreditation pack off 📘

Turn `accredited_pack_on()` off.
**Expected:** the ISO-17020 registers (NCR, CAPA, audits, equipment, competence, impartiality, data control)
are **hidden even for a master** — the platform runs as a plain ops tool. Turn it back on → they reappear.
**Reflected in:** nav; area homes. 🟡

### Step I — SBU scope (the second dimension) 📘

A user scoped to SBU = NDT only.
**Expected:** `scope_allows(office, 'CIV')` = **FALSE** even if the office is in scope — both dimensions must
pass. `data.*` figures roll up only within the user's SBUs. **Reflected in:** every SBU-segmented list/figure. 🟠

---

## 3. Edge cases

| # | Change | Expected | Sev |
|---|---|---|---|
| E1 | Record with no office at all | Treated as Ahmedabad; a non-Ahmedabad branch user is **denied** (fail-closed) | 🟠 |
| E2 | Master opens the same out-of-branch record | **Allowed** — master exempt | 🟠 |
| E3 | Override user given a `mod.*` perm | Keeps module access (defaults merged) but named perms are exactly the list | 🟡 |
| E4 | Override user given **no** `mod.*` | Module defaults still merged so they keep screen access (never fully locked out of their own screens) | 🟡 |
| E5 | Role default OWN vs ALL | OWN → `[home_office]`; ALL → `"ALL"`; a `scope_offices` setting can pin a custom list | 🟡 |
| E6 | Home office unset + non-ALL role | Falls back to Ahmedabad `[id]` (never empty → never "sees nothing" or "sees all") | 🟠 |
| E7 | User deactivated mid-session | Next request refused | 🟠 |
| E8 | SBU in scope, office not | Denied (both dimensions AND-ed) | 🟠 |

---

## 4. Pass/fail summary

| Assertion | Expected | Verified | Pass? |
|---|---|---|---|
| Master scope | `scope_offices()` = **"ALL"**; `scope_allows(any)` = TRUE | ⚙ | ☐ |
| Branch scope | `scope_offices()` = **[18]** | ⚙ | ☐ |
| Own office | `scope_allows(18)` = **TRUE** | ⚙ | ☐ |
| Cross office | `scope_allows(1)` = **FALSE** (§51) | ⚙ | ☐ |
| Null office | `scope_allows(null)` = **FALSE** (→ Ahmedabad, fail-closed) | ⚙ | ☐ |
| Override grants exactly its list | `can('mod.jobs.view')`=TRUE, `can('ops.job.close')`=**FALSE** | ⚙ | ☐ |
| Single-record IDOR | out-of-branch `/job?id=`, `/invoice?id=` denied | 📘 (§51 test) | ☐ |
| Search scope | branch user's search returns only in-scope rows | 📘 (§22 test) | ☐ |
| Lock-out footgun | override with no admin perm → admin hidden, master-recoverable | 📘 | ☐ |
| Accreditation pack off | ISO registers hidden even for a master | 📘 | ☐ |

---

*⚙ verified against the live engine on 2026-08-27 (Ahmedabad id 1, Branch B id 18); 📘 cited rule / referenced
test. This completes the scripted set over the flow map:*

- `00-end-to-end-flow.md` — the whole spine + golden invariants
- `01` money · `02` report→issue · `03` quotation→contract · `04` site execution · **`05` onboarding & scope**

*The onboarding scope proven here is the invariant every other stage depends on: a value set by a branch user
stays inside that branch, on lists and by direct id, all the way to the invoice.*
