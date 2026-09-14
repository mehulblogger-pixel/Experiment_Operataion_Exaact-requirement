# Milestone 12 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

Fully green suite, no schema change, no data change, nothing deleted, no billing
built. Nine limitations recorded — **L1 and L2 are product decisions deliberately
left open**, not oversights.

---

## 1. What the inspection found

Every refusal in the application went through one line — `flash + redirect('/')` —
and **there was no lock screen in the application at all**. Whatever had gone
wrong, the user was bounced to the dashboard with a red toast, lost their place,
got no next step, and a blocked `fetch()` was answered with a redirect.

The *words* were already half right: M5 had split the refusal into "the module is
not switched on" versus "ask your administrator". The distinction existed; the
delivery did not.

---

## 2. What was built — a presenter, not an engine

`lib/access_state.php` and `views/ops/module_locked.php`.

It **decides nothing**. It asks the existing engine (`module_state()` from M3,
`licence_owner()` from M2, `can()` from RBAC) and turns the answer into words.
Asserted by test: the gate hands it a refusal it has **already decided**, and
`lib/ops.php` never asks the presenter *whether* to allow anything.

| State | What the user is told | Action |
|---|---|---|
| NOT_ENTITLED | "…isn't included in your current subscription." | admin → **Review your subscription**; others → who to ask |
| LICENCE_BLOCKED | "…the licence is not active." | admin → **Check the licence** |
| TENANT_DISABLED | "…has been switched off for this workspace." | + **the recorded work is untouched** |
| NO_PERMISSION | "You don't have permission to open…" | **never an upgrade prompt** |
| UNKNOWN / INVALID | "…isn't available in this workspace." | safe and generic — **the internal key is never shown** |
| CORE / ENTITLED | "…is available in this workspace." | — |

**§14's mandatory distinction is asserted in both directions:** not-entitled says
*subscription* and never permission; no-permission says *permission* and never
subscription.

---

## 3. Security posture is unchanged

The UI explains; **M5–M10 still control**. Under S-1 as a master, with the lock
screen in place: the permission gate refuses, the route gate refuses a direct URL,
the export URL refuses, the entitlement engine refuses, and Marketplace refuses.

A master gets the same locked screen as anyone else, and the screen never says
being an administrator grants access.

Requests get 403 — a page gets the lock screen, a `fetch()` gets JSON naming
`reason: permission` or `reason: subscription`. No blank page, PHP warning, SQL
error, malformed JSON, or redirect away from what was asked for.

---

## 4. Nothing commercial invented

`/subscription` already existed as the company's own self-service plan screen.
M12 links to it, gated by the same `billing_can_manage()` that guards it — so the
offer can never point at a screen the person cannot open. **No price, no payment
mechanism, no billing system.** Asserted by test.

---

## 5. Testing

| | Result |
|---|---|
| **Focused M12** | **88 passed, 0 failed** |
| **Full regression** | **8,148 passed · 0 failed · 0 skipped · 460 files · 99 s · PHP 8.4.19** (M11 baseline 8,060) |
| Operations / Reporting / Money / Sales / Recruitment / Marketplace / M2–M11 suites | all pass |
| **Manual walkthrough** | performed — **and it found a defect** |
| **MySQL/MariaDB** | **NOT EXECUTED** — no server installed (verified). No claim of validation |

**The walkthrough found a real defect:** the lock screen's heading read "not
available" from the permission flag alone, so an available module would have read
as locked. Heading and icon now follow the state.

**One existing test changed — and it is the guard working.** M10's probe fired on
M12's new `access_can_subscribe()`, naming it. That is exactly its purpose: a new
gate must be classified before it is accepted. Classified **Category C (core)** —
it answers "may this person act on a subscription", which is core billing
administration; a master on core admin alone must be able to reach it or the
workspace could never buy anything. The assertion was **not** weakened; it still
fails for any other unclassified opener.

Two assertions of **my own** failed on first run — both matched text inside
**comments**. I corrected my assertions, not the code.

---

## 6. Acceptance criteria

**PASS (26):** entitled modules clear · unentitled locked/clear · licence-blocked
distinguishable · tenant-disabled handled · permission vs subscription
distinguishable · master cannot bypass · direct URLs protected · POST/AJAX
protected · dashboard respects entitlement · navigation consistent with M11 · no
duplicate locked experiences · upgrade uses existing mechanism only · no pricing
invented · no data deleted · Operations functional · Reporting functional · Money
functional when entitled · Sales functional when entitled · Recruitment functional
when entitled · Marketplace functional when entitled · cross-tenant isolation ·
manual walkthrough completed · full regression passes · no test unjustifiably
weakened · documentation complete · limitations documented · M13 not started ·
Phase 2 not started

**REVIEWED, not independently audited (2):** mobile usability · accessibility
basics. The screen inherits the existing panel styles, uses a real heading and a
real link, and carries its state in words rather than colour — but no
screen-reader test, contrast measurement or real-device check was performed, and
I will not claim one (L6).

---

## 7. Carried forward

**L1** — finer-grained action refusals inside a module still flash-and-redirect.
Defensible (they are about a record, not a module, and the user keeps their place)
but it is not the single experience §16 describes.

**L2** — unavailable modules stay **hidden** rather than shown as locked with an
upgrade prompt. The commercial argument for showing them to administrators is
real; whether ordinary users see them too is **a product decision**, and it is a
small change once taken.

**L7** — a workspace that cannot self-serve has no in-app "request this module"
path. Building one is a new capability.

⚠️ **Before deployment, M9's L1 still applies:** hosted workspaces need `connect`
added to their entitlement — and after M12 their users will now meet a lock screen
saying precisely that, which makes getting it right beforehand more visible.

---

**STOP. M12 ends here.** M13 and Phase 2 have not been started.
