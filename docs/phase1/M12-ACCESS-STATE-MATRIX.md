# Milestone 12 — Access State Matrix

Every combination of state and role, with the navigation behaviour, the direct-URL
result, the words shown and the next action. All rows verified — the bold ones by
the manual walkthrough in §26, the rest by the 88-assertion suite.

**Server-side enforcement (M5–M10) is authoritative in every row.** The navigation
and message columns describe what the user *understands*, never what *controls*.

---

## 1. Paid module — the full matrix

| # | State | User role | Module (example) | Navigation | Direct URL | Message | Action offered | Expected result |
|---|---|---|---|---|---|---|---|---|
| 1 | **ENTITLED** | any with permission | People & hiring | visible in rail | opens | — | — | **Normal access** |
| 2 | **ENTITLED** | user *without* the permission | People & hiring | hidden (tile is permission-gated) | **403 → lock screen** | "You don't have permission to open People & hiring." | "Ask your workspace administrator…" | **Denied — permission** |
| 3 | **NOT_ENTITLED** | workspace administrator | People & hiring | hidden from rail | **403 → lock screen** | "People & hiring isn't included in your current subscription." | **Review your subscription** → `/subscription` | **Denied — subscription** |
| 4 | **NOT_ENTITLED** | ordinary user | People & hiring | hidden from rail | **403 → lock screen** | same sentence | *none* — "Your workspace administrator can add it to your plan." | **Denied — subscription** |
| 5 | **NOT_ENTITLED** | **master** | People & hiring | hidden from rail | **403 → lock screen** | same sentence | Review your subscription | **Denied — master is NOT exempt** |
| 6 | **TENANT_DISABLED** | administrator | Money | hidden from rail | 403 → lock screen | "Money has been switched off for this workspace." | Review your subscription | **Denied — and told the recorded work is untouched** |
| 7 | **TENANT_DISABLED** | ordinary user | Money | hidden | 403 → lock screen | same | none — "your administrator can switch it back on. Nothing recorded in it has been lost." | Denied |
| 8 | **LICENCE_BLOCKED** | administrator | Money | hidden | 403 → lock screen | "Money is unavailable because this installation's licence is not active." | **Check the licence** → `/licence` | **Denied — licence, not subscription** |
| 9 | **LICENCE_BLOCKED** | ordinary user | Money | hidden | 403 → lock screen | same | none — "your administrator can restore it" | Denied |
| 10 | **UNKNOWN** (blank entitlement record) | any | any paid | hidden | 403 → lock screen | "…isn't available in this workspace." | admin: Review your subscription | **Denied — fail closed** |
| 11 | **INVALID_MODULE** (unrecognised key) | any | — | n/a | 403 → lock screen | "This feature isn't available in this workspace." | admin: Review your subscription | **Denied — and the internal key is never shown** |
| 12 | **CORE** | any with permission | Administration | always visible | opens | — | — | **Always available** |
| 13 | **CORE** | user without the permission | Administration | permission-gated | 403 → lock screen | "You don't have permission to open Administration." | Ask your administrator | Denied — permission |

---

## 2. Marketplace (M9) — the same matrix, no special case

| State | Role | Navigation | Direct URL | Message | Result |
|---|---|---|---|---|---|
| Not entitled | master | area hidden from rail | 403 → lock screen | "Marketplace & Connect isn't included in your current subscription." | **Denied** |
| Not entitled | ordinary user | hidden | 403 → lock screen | same, no upgrade control | Denied |
| Entitled | any with permission | area visible | opens | — | Normal access |

M9's entitlement logic was **not modified**. The peek/handler agreement M11 added
(C6) means no menu can offer a Marketplace link that then refuses.

---

## 3. S-1 — the mandatory scenario (§19)

Operations ON · Reporting ON · HR OFF · Sales OFF · Money OFF, as a master:

| Module | State | Navigation | Direct URL | Result |
|---|---|---|---|---|
| Operations (`jobs`, `calls`) | ENTITLED | visible | opens | **works normally** |
| Reporting (`idems`) | ENTITLED | visible | opens | **works normally** |
| Administration (`masters`) | CORE | visible | opens | **works normally** |
| People & hiring | NOT_ENTITLED | hidden | 403 → lock | **locked** |
| Sales & CRM (`quotes`) | NOT_ENTITLED | hidden | 403 → lock | **locked** |
| Money (`invoicing`) | NOT_ENTITLED | hidden | 403 → lock | **locked** |

Operations and Reporting are undisturbed by the locked-module UX.

---

## 4. Request shape (§10)

| Request | Status | Body |
|---|---|---|
| Page (`Accept: text/html`) | **403** | the lock screen — heading, sentence, hint, action, "Back to your dashboard" |
| `X-Requested-With: XMLHttpRequest` | **403** | `{"ok":false,"error":"access","reason":"subscription"\|"permission","message":…,"hint":…,"action":…}` |
| `Accept: application/json` | **403** | the same JSON |
| POST | **403** | whichever of the above matches the request |

Never a blank page, a PHP warning, a SQL error, malformed JSON, or a redirect
away from what the user asked for.

---

## 5. Lifecycle (§12)

| Step | State | What the user sees | Data |
|---|---|---|---|
| Module ON | ENTITLED | normal module | intact |
| Module OFF | NOT_ENTITLED / TENANT_DISABLED | lock screen, with the reassurance that recorded work is untouched | **intact — nothing deleted** |
| Module ON again | ENTITLED | normal module restored | **intact, same records** |

Verified by counting rows before, during and after.

---

## 6. What is never shown to a user (§22)

SQL errors · table names · PHP errors · internal module identifiers
(`hiring`, `idems`, `invoicing`) · licence internals · tenant ids · access-module
keys · database details.

The user sees the **product label** — "People & hiring", "Money",
"Marketplace & Connect" — or, when nothing can be resolved, the neutral phrase
"This feature". Asserted by test for the unresolvable case.
