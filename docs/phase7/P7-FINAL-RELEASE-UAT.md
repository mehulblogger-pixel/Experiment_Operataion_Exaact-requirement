# EXAACT Phase 7 Final Release & UAT

## 1. Baseline

Worked from `295e5d8` (clean tree, branch `Testing` → `origin/claude/testing-branch-setup-0gqe8n`).
Baseline commits `f39775c`, `1a8b001`, `65fc036`, `295e5d8` confirmed before any change.
No completed architecture was reopened.

## 2. Production Environment Verification

**BLOCKED.** Checked directly, not assumed:

| Evidence sought | Found |
|---|---|
| `config.local.php` (production credentials) | **absent** |
| production DB / host environment variables | **none** |
| production hostname in config | **none** |
| backups directory | **empty** |

No production hostname, credentials, workspace identity or database is reachable
from this environment. §3 and §25 say stop at that boundary rather than
improvise, so **Gates P1, P2 and P3 were not attempted and no production result
is claimed.**

## 3. Backup Verification

**BLOCKED.**

```
Backup available : NO — the backups directory is empty
Backup date/time : n/a
Backup scope     : n/a
Recovery evidence: n/a
Verified by      : nobody — no backup was reachable
```

No smoke test was run against production, because §5 requires a usable backup
first and none exists here.

## 4. Production Smoke Test

**BLOCKED** — depends on §2 and §3.

## 5. Browser Verification

**PASS — 31 assertions, 0 failed, 0 JavaScript errors, 0 failed requests.**
Real Chromium via the project's existing Playwright install. No competing
framework was built; `tools/p7-browser-uat.js` follows `tools/smoke.js`'s pattern.

| | Result |
|---|---|
| sign in, first-run wizard released | PASS |
| requirements / applicants / recruitment screens | PASS |
| requirement offers a **visible** team choice, real vocabulary | PASS |
| requirement saves and lands on the requirement | PASS |
| applicant created through the form | PASS |
| **no** "also add to Inspectors" tick on the screen | PASS |
| stage control visible on the Recruitment tab | PASS |
| choosing Accept **reveals** the team confirmation | PASS |
| applicant shown as Accepted (Hired), no raw error | PASS |
| signed out, a protected register sends you to sign in, no stack trace | PASS |

### Three false passes this gate caught — in the walk, not the product

1. **Setup "completed" without saving.** A bare `button[type=submit]` matched the
   layout's first button, not the setup form's. Every later screen was the wizard,
   and "renders cleanly" was asserting against the wrong page.
2. **A requirement "saved" onto `/search`.** The same unscoped selector hit the
   global search box. The walk now asserts *where it arrived*, not merely that the
   URL changed.
3. **An applicant "saved" via the CV-upload form**, which shares the same action.
   Now scoped to the form that owns the name fields.

Every submit is scoped to its own form, and `open()` refuses to pass when a page
redirected somewhere else, whatever status it returned.

### Two things that looked like defects and were not

- The stage and joining panels live on the **Recruitment tab**; the screen opens
  on Overview. A person clicks the tab, and so does the walk now.
- `/workspace/setup` is an ordinary settings screen, not the first-run wizard.

## 6. Mobile Verification

**PASS — 21 assertions, 0 failed**, at **360×800, 390×844, 412×915**, mobile
emulation with touch.

| Check | 360 | 390 | 412 |
|---|---|---|---|
| page loads | PASS | PASS | PASS |
| **no horizontal overflow** | PASS | PASS | PASS |
| every control ≥ 44px tall | PASS* | PASS* | PASS* |
| primary CTA exists | PASS | PASS | PASS |
| CTA reachable, not buried | PASS (458px) | PASS (428px) | PASS (428px) |
| no clipped text | PASS | PASS | PASS |
| no JavaScript errors | PASS | PASS | PASS |

\* after the fix below.

**The one genuine mobile defect, and the smallest fix.** Measured on a phone,
three controls were **15–16px tall**: the header "Sign in", "join as a
professional" and "Staff sign-in". Those are the secondary ways into the
product, and a 16px target on a 390px screen is a link you miss. They now have a
44px tappable height, phone widths only, with no change to how they read — same
size, same colour, still inline links. The desktop layout is untouched.

**Against the Version 3 direction**, the page already follows it: brand + Sign
in; "Post a job. Find the right inspector. Get it done."; supporting copy; a
requirement input; **Post a requirement →**; "join as a professional"; a trust
row; three user-type cards **stacked in one column**, not a squeezed desktop
grid; how-it-works; sign-in; footer. The Operations door ("Run an inspection
company? … Start your workspace →") **exists** and is correctly conditional on
the workspace offering tenant signup — its absence in the test workspace is that
condition, not a gap.

## 7. Recruitment Verification

**PASS.** Browser-level: requirement (team = COORD) → applicant → acceptance →
workforce record → joined. Acceptance creates the workforce record with **no
tick anywhere**, the team is the requirement's decision and not the FIELD
default, and `joined_at` is empty until somebody says otherwise.

## 8. Workforce / Inspector Verification

**PASS.** Employee number issued on acceptance; classification carried from the
requirement; R20 protections unchanged and green (44 assertions).

## 9. TPIA Revenue Chain

**PASS** — unchanged from `65fc036`, re-run green: 32 assertions, one of
everything from customer to invoice.

## 10. Money / Billing Verification

**PASS.** The billing gate blocks with the job open or the report in draft, and
clears only when closed and issued. No financial document was created outside
the test fixtures, and no financial history was modified.

## 11. Tenant Isolation

**PASS** — 298 assertions, both engines.

## 12. Permission Verification

**PASS.** Signed out, a protected register redirects to sign-in with no stack
trace, SQL error or blank page. The login CSRF token is enforced and was
honoured rather than bypassed.

## 13. Regression

Run after the view change, engines run sequentially:

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **13,251 passed · 0 failed** |
| **MariaDB 10.11.14** | **13,251 passed · 0 failed** |

Operations, Recruitment, Workforce, Marketplace, Reporting, Money/Billing,
Tenant isolation and Entitlement all PASS on both.

## 14. Mutation Results

Unchanged from the baseline: no unexplained survivors. This gate changed one
view's CSS and added two test tools; no production logic changed, so no new
mutation target applies.

## 15. Data Safety

**PASS.** This gate wrote nothing outside throwaway SQLite workspaces. No
production data was touched — none was reachable. No historical record was
rewritten, deleted or merged.

## 16. Mobile UX Findings

**Fixed:** touch targets on three secondary links (above).

## 17. Known Non-Blocking Findings

1. The applicant screen reopens on its **Overview** tab after Mark-as-joined, so
   a user returns to the Recruitment tab to see the result. The control is
   reachable either way. Remembering the tab would be a small improvement.
2. Enhanced "searchable" selects hide their native control, so the walk sets
   those programmatically while driving ordinary selects as a person does.
   Recorded so the evidence is not over-read.
3. At 360px the "Or join as a professional · … Sign in →" line now wraps to two
   lines. Readable, slightly looser.

## 18. Remaining Blockers

1. **Production deployment / environment verification** — no access.
2. **Production backup verification** — no backup reachable.
3. **Production smoke test** — depends on both.

## 19. Final Release Decision

### Release matrix

| Gate | Status | Evidence |
|---|---|---|
| Production identity | **BLOCKED** | no credentials, host or workspace reachable (§2) |
| Backup | **BLOCKED** | backups directory empty; nothing to verify (§3) |
| Production smoke | **BLOCKED** | depends on the two above |
| Authentication | **PASS** | browser: CSRF enforced, sign-in, sign-out, protected register redirects |
| Recruitment | **PASS** | browser walk B4–B6, 31 assertions |
| Workforce | **PASS** | record created on acceptance with employee number and the requirement's team |
| Inspector / R20 | **PASS** | 44 assertions; 8/8 mutants caught (baseline, re-run green) |
| Operations | **PASS** | regression 563 · 0 |
| Reporting | **PASS** | regression 653 · 0 |
| Money / Billing | **PASS** | gate blocks then clears; 673 · 0 |
| Marketplace | **PASS** | 1317 · 0; Connect front door renders on mobile |
| Tenant isolation | **PASS** | 298 · 0 |
| Browser UX | **PASS** | 31 · 0, no JS errors, no failed requests |
| Mobile UX | **PASS** | 21 · 0 at 360 / 390 / 412 after the touch-target fix |
| Full regression | **PASS** | SQLite 13,251 · 0 · MariaDB 13,251 · 0 |
| Data safety | **PASS** | nothing written outside throwaway workspaces |
| UAT | **PASS** | checklist prepared and exercised at browser level |

### Status

## 🟡 UAT READY — PRODUCTION DEPLOYMENT PENDING

Every non-production gate passed. The only outstanding items are production
access, a verified backup, and the smoke test that depends on them. No defect
is outstanding.

### What is needed to close the remaining three

1. Production host, credentials and confirmation of which workspace/database is
   the live one.
2. A backup taken before deployment, with its date, scope and where it lives.
3. Permission to run the controlled smoke test — and whether any financial
   document may be issued during it (by default, none was, and none should be).
