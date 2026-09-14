# Milestone 12 — Locked Module UX

**Status:** complete · **Suite:** 8,148 passed, 0 failed · **Baseline:** 1bf27b5 (M11)

---

## 1. What the inspection found

Before changing anything, the §2 inspection asked how the application actually
communicates access today. The answer was one line:

```php
function ops_require($ok, $msg = 'You do not have access to that screen.') {
    if (!$ok) { flash($msg, 'error'); redirect('/'); }
}
```

**Every refusal in the application went through it.** Whatever had gone wrong —
the company never bought the module, the licence lapsed, the workspace switched
it off, or this person simply has no right to the screen — the user was:

- **bounced to the dashboard**, losing their place;
- given **one line of red toast** with no next step;
- and, on a POST or a `fetch()`, answered with a **redirect**, which is the wrong
  answer entirely.

There was **no lock screen in the application at all.** `views/ops/` contained no
locked, unavailable, upgrade or denied screen.

What the inspection *also* found is that the words were already half right: M5
had split the refusal into two sentences — "the module is not switched on for
this installation" versus "you don't have access… ask your administrator". The
distinction existed; the delivery did not.

---

## 2. What M12 added — a presenter, not an engine

One new library, `lib/access_state.php`, and one new view,
`views/ops/module_locked.php`.

**It decides nothing.** It asks the existing engine — `module_state()` from M3,
`licence_owner()` from M2, `can()` from the RBAC layer — and turns the answer into
words a person can act on. M5–M10 remain the only thing that controls access, and
the tests assert it:

- the gate hands `access_deny()` a refusal it has **already decided**;
- `lib/ops.php` never calls `access_state_for()` to ask *whether* to allow.

### The eight states, and the words each gets

| State | What the user is told | Next action offered |
|---|---|---|
| `CORE` / `ENTITLED` | "*Money* is available in this workspace." | none — it is open |
| `NOT_ENTITLED` | "*People & hiring* isn't included in your current subscription." | admin → **Review your subscription**; others → "your administrator can add it to your plan" |
| `LICENCE_BLOCKED` | "*Money* is unavailable because this installation's licence is not active." | admin → **Check the licence** |
| `TENANT_DISABLED` | "*Money* has been switched off for this workspace." | admin → **Review your subscription**; and both are told **the work recorded in it is untouched** |
| `NO_PERMISSION` | "You don't have permission to open *People & hiring*." | "Ask your workspace administrator" — **never an upgrade prompt** |
| `UNKNOWN` / `INVALID_MODULE` | "This feature isn't available in this workspace." | a safe, generic answer — **the internal key is never shown** |

### The distinction §14 makes mandatory

Asserted in both directions:

- **not entitled** → says *subscription*, and **never** says permission;
- **entitled but this person may not** → says *permission*, and **never** says
  subscription.

Telling somebody to ask their administrator for a module the company never bought
sends them on an errand that cannot succeed. Telling somebody to upgrade when they
merely lack a permission is just as wrong.

---

## 3. Request shape is respected (§10)

| Request | Answer |
|---|---|
| A page | the lock screen, **HTTP 403** |
| `XMLHttpRequest` or `Accept: application/json` | structured JSON, **HTTP 403**, with `reason: permission` or `reason: subscription` |
| Anything else | plain text, 403 |

No blank page, no PHP warning, no SQL error, no malformed JSON, and **no
redirect to somewhere the user did not ask to be**.

---

## 4. Administrator vs ordinary user (§7)

The difference is driven by `access_can_subscribe()`, which reuses
`billing_can_manage()` — the **same gate that guards `/subscription`**, so the
offer can never point at a screen the person cannot open.

- **Can act on a subscription** → offered the existing `/subscription` screen.
- **Cannot** → told who to ask. **No privileged control is exposed to them.**

---

## 5. Nothing commercial was invented (§6, §11)

`/subscription` already existed: *"a company's own self-service subscription —
review the plan, and buy extra modules / seats à la carte with a live quote,
paying online."* M12 links the locked state to it.

**No price, no payment mechanism and no billing system was created.** Asserted by
test: no currency-and-digit pattern and no payment vocabulary appears anywhere in
the locked experience.

---

## 6. A master is still subject to entitlement (§8)

`master + entitled` → available. `master + not entitled` → the same locked screen
as anyone else. The screen never says "you are an administrator, therefore access
is available", and the tests check for that sentence's absence.

---

## 7. What was NOT done

- **No second entitlement system.** One presenter over the existing engine.
- **The Dashboard was not rebuilt** (§15, §30). The audit confirmed it already
  respects entitlement — HR, Sales and Money panels are hidden under S-1, and
  `ar_can()` / `books_can()` are closed (the work M6 and M10 did).
- **No RBAC, subscription or licence architecture change.**
- **M11's navigation was not disturbed** (§16) — unavailable modules stay hidden
  from the rail, and the lock screen is the single consistent destination for
  anyone who arrives by a typed address, an old bookmark or a link.
- **No new frontend framework, stylesheet or script** (§23). The screen reuses
  the existing `panel` / `btn` / `msg` styles and inherits their responsiveness.
- **No data touched** — no schema change, no migration, no deletion.

---

## 8. Files changed

| File | Change |
|---|---|
| `lib/access_state.php` | **new** — the presenter: eight states, the words for each, role-aware next action, JSON/HTML/text delivery, 403 |
| `views/ops/module_locked.php` | **new** — one lock screen, built from existing styles |
| `lib/ops.php` | the gate's two refusal points now reach the lock screen |
| `index.php` | loads the new library in the boot chain |
| `tests/test_m12_locked_module_ux.php` | **new** — 88 assertions |
| `tests/test_m10_master_entitlement.php` | `access_can_subscribe` classified as core on the probe baseline (see the test-results document) |
| `deploy-check.php` | regenerated |

**Two new files, two edited.**
