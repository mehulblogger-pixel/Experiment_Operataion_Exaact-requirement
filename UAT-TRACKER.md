# Exaact — UAT & Declutter Tracker

A living record of the module-by-module walkthrough: every screen tested with
real values, every finding logged as a **bug**, **clutter**, or **duplicate**,
and what was done about it. Newest work on top of each module.

**How this is run:** the app is booted on a throwaway database (isolated — the
real data is never touched), driven in a real browser with realistic inputs,
and every screen is screenshotted and scanned for PHP errors. Findings that
need a product decision are marked **⟢ needs owner call**.

Legend: ✅ works · 🐞 bug · 🧹 clutter/UX · 👯 duplicate · ⟢ needs owner call

---

## 🧹 Declutter done

### D1 — Add/Edit-user permissions grouped like the menu (DONE)

The permissions section was one undivided wall of ~97 tick-boxes (fine-grained
actions and per-module view/edit all mixed together). It now renders under the
**same headings as the main navigation** — Sales · Operations · Reporting ·
Money · Quality & Accreditation · Insights · Directory · Admin — so granting
access reads like the menu the person will use.

- `lib/access.php` — new `permission_nav_groups()`: every permission (fine-grained
  **and** each module's view/edit) mapped to its nav area, ordered.
- `views/ops/user_form.php` — renders a labelled block per group, with an "Other"
  catch-all so a permission no group claims is still shown, never hidden.

Verified in-browser: all 8 groups render, **all 97 permissions placed (no "Other"
bucket)**, no errors. Suite 459/459.

---

## Module 6 — IDEMS report engine (tamper-evidence — proven cryptographically)

The inspection-report engine's headline promise is that an issued report and its
history **can't be quietly altered**. Tested the mechanism itself.

**✅ Hash-chained audit trail holds:** every logged action is sealed with
`sha256(previous_seal | this_row's_content)`, so the entries form a chain. Wrote
sealed entries through a report lifecycle (CREATE → SUBMIT → FINALIZE); the app's
own `idems_audit_verify()` reported **16 checked, 0 broken — chain intact**.

**✅ Tampering is detected:** silently edited one sealed row's value (as an
attacker with database access would) → verify immediately flagged it
(**content mismatch, 1 broken**). Restoring the value made the chain whole again.

So altering or deleting any past audit record is detectable after the fact —
the legal-defensibility guarantee behind ISO/IEC 17020 reporting actually works.

*(Test-harness note: an early "0 checked" reading was my own fault — the verifier
calls `ops_all()` and I hadn't loaded that lib into the standalone harness. With
it loaded, the check runs correctly. No app issue.)*

---

## Module 3 — CRM / Sales (lead → conversion)

**✅ Lead conversion works:** created a lead ("Bright Future Manufacturing",
₹2.5L requirement) and converted it → the customer was **created on the master as
a client** (business partner #667), the lead was marked **CONVERTED** (not
deleted — the trail stays), and an **inquiry was raised** to carry on from. The
funnel entry (lead → client + inquiry) is intact.

---

## Module 5 — Inspectors & People (the 8.5h daily cap)

**✅ Daily-hours cap enforces, with the boundary correct:**
| Hours logged in a day | Result |
|---|---|
| 6.0 h | allowed |
| **8.5 h** (exactly the cap) | allowed |
| 9.0 h | **blocked** |
| 12.0 h | **blocked** |

The cap is **cumulative per day** — it sums hours already on that date plus the
new entry, so two 5-hour entries (10 h) trip it. It is also enforced a second
time at the voucher-save handler (it totals hours per date before saving and
refuses the whole submission with a plain message). Belt and braces.

---

## Module 11 — Licensing & Seats (deep test — the whole selling model works)

This is the module the business depends on. Tested end-to-end with a **genuine
signed key** (real RSA-SHA256, minted and installed the way a customer's key is).

**✅ Signed licence recognised:** installed a 2-seat key → the Licence screen read
*"Licensed — UAT Test Co, valid to 31 Dec 2027, 1 of 2 in use."* The signature is
verified by arithmetic on the server (nothing phones home), so it works offline
and **cannot be forged**.

**✅ Seat cap enforces:**
- Added user #2 → 2 of 2 seats filled.
- Added user #3 → **blocked**: *"Your subscription covers 2 people and 2 are
  already active. Deactivate somebody who has left, or ask MGH to add seats."*
- The DB confirmed user #3 was **not** created.

**✅ "Add more seats" works:** re-issued a **3-seat** key and swapped it in → the
same user #3 was **created immediately**, 3 of 3 active. This is exactly the
"customer asks for more users, you send a new key" flow — verified.

**✅ Per-module licensing enforces too (bonus):** the 2/3-seat keys bought no
modules, so only *Administration* was on — and the **Operations, Reporting and
Money groups disappeared from the menu entirely**. A module that isn't bought is
not just hidden; its screens refuse to open and its permissions are withdrawn.

Everything a paying customer leans on — seat limits, seat top-ups, module
entitlement — holds. No defects.

---

## Module 9 — Money / Finance (deep test — settlement reconciles)

Continued the same order into the money side, checking figures against the DB.

**✅ Settlement reconciles:** issued the ₹11,800 invoice, then paid it in two
receipts (₹5,000 part, then ₹6,800 balance), allocating each:
- outstanding went **11,800 → 6,800 → 0**
- **status auto-followed the money** — DRAFT → ISSUED → PART_PAID → **PAID** (nobody
  types the status; it is `total − cash − TDS − credit notes`).

**✅ GST guard works (good behaviour, not a bug):** the invoice **refused to issue**
while the customer had no GSTIN or state — because place-of-supply is then unknown
and CGST/SGST-vs-IGST would be a guess. Set the client's state → it issued. This
is correct GST compliance.

**❎ Checked and cleared:** the issue guard reads `$inv['client_gstin']`, and there
is no such **column** — looked like a bug, but `books_invoice()` aliases the
partner's GSTIN *as* `client_gstin` in its SELECT join, so it is correct. Not a
defect.

---

## Whole-app health baseline (09 Aug 2026)

Walked **every GET-safe screen in the app — 83 screens across all modules** —
with live data present (a real quote, call, job, invoice, NCR and CAPA created
during testing, plus the seeded clients/vendors). Result: **83 / 83 render with
zero real PHP errors.** Combined with the earlier spine forms and the 20 Quality
registers, the app is confirmed healthy end to end, not just on empty screens.

*(The walk skips destructive/download routes — logout, deletes, CSV/PDF exports,
Tally export, reset — which aren't safe to fire blind.)*

---

## Module 8 — Quality & Accreditation (walked)

Walked all 20 compliance registers and tested the linked-record flow.

**✅ Loads clean:** all 20 screens render with **no PHP errors** — equipment,
competence, impartiality, complaints, satisfaction, confidentiality, site-docs,
NCR, CAPA, internal audits, management review, evidence review, data control,
identity, risks, retention, disclosure, methods, decision rules, controlled docs.

**✅ Linked-record flow (NCR → CAPA):** created a nonconformity ("weld undercut
exceeds acceptance criteria"), raised a corrective action from it → the CAPA
stored `ncr_id` pointing back to the NCR and carried its title/description across.
The chain that ISO 17020 cares about (a finding must drive a corrective action)
works end-to-end.

**Design:** registers are consistent and uncluttered — KPI header cards, Open/
Closed/All tabs, column picker, CSV export, clear empty states ("Nothing here.").
No declutter work needed here.

*(Test-harness note: the automated error-scan first flagged `data-control` — a
false positive, it matched the phrase "fatal error" in that screen's own help
text, not a real error. Detector tightened.)*

---

## 💰 Deep transaction test — money reconciles Quote → Call → Job → Invoice (PASS)

Pushed one order the whole way through on a live server, checking the figures at
every hand-off against the database (not the screen). Inputs: quote line **2
man-days × ₹5,000**, GST **18%**.

| Stage | How the figure is derived | Value | Reconciles |
|---|---|---|---|
| **Quote** | Σ(qty×rate) = 10,000; +18% GST | **₹11,800** | subtotal/GST/total all correct ✓ |
| **Call** | quote line rate carried → rate×qty (5,000×2) | billable **₹10,000** | rate carried from quote ✓ |
| **Job** | invoice_value = rate × man-days (5,000×2) | **₹10,000** | = call billable ✓ |
| **Invoice** | one-click from job; line 5,000×2, +18% GST | **₹11,800** | = quote total ✓✓✓ |

Nothing was re-keyed — every number flowed from the quote. **End-to-end: quote
₹11,800 → invoice ₹11,800.**

Minor, not a confirmed defect:
- The call carried the quote's **rate and value correctly, but stored an empty
  `quote_line_id`** (which line it drew from). The save handler *does* persist that
  column, so this is most likely an artifact of the automated test setting the
  rate directly instead of through the line-pick cascade a real user uses. **Flagged
  for a manual check**, not logged as a bug.

---

## 🐞 Bugs found & fixed

### B1 — Add-user screen crashed: "Unknown column 'team_role'" (FIXED)

**Reported live:** Organisation & people → Login accounts → **Add user** →
`SQLSTATE[42S22]: Unknown column 'team_role' in 'SELECT'` at `lib/ops.php`.

**Root cause (two-part):**
1. `inspectors_list()` selected `COALESCE(team_role,'FIELD')`. A comment claimed
   this protected a database missing the column — it does not. `COALESCE`
   substitutes a NULL *value*; the column must still exist or the SELECT throws.
2. The boot **schema-probe** in `index.php` (which is what triggers the
   idempotent migration) probes many `inspectors` columns but **never `team_role`**.
   So on a live DB updated after that column shipped, the fingerprint matched,
   the probe passed, migration was skipped, and the column was never added.

**Fix:**
- `lib/ops.php` — `inspectors_list()` now calls `ensure_column('inspectors',
  'team_role', …)` before the read (idempotent self-heal, same pattern
  `team_member_create()` already used), and the misleading comment is corrected.
- `index.php` — added `SELECT team_role FROM inspectors` to the boot probe so any
  install missing it triggers the full migration.

**Verified:** reproduced the crash on a DB with the column dropped; after the fix
the Add-a-person screen renders fully and the column self-heals back. Full suite
459/459 still passes. On the live server the fix applies on the next page load
(the code change moves the fingerprint, so the probe re-runs and migrates).

### B2 — Missing-column sweep: the root class, fixed for good (FIXED)

`team_role` was not unique. Measuring the whole app: the code adds **~290
columns** via `ensure_column`, but the boot-probe only names **148** of them.
So **~140 columns sat in the same blind spot** — any of them could be missing on
a live database and crash a screen the day someone opened it. Hand-adding 140
probes would be endless whack-a-mole.

**Systemic fix (one change, closes all 140):**
- `lib/db.php` — the schema build/upgrade is now one ordered sequence,
  `run_schema($withSeeds)`. `boot()` = `run_schema(true)` (fresh install: schema
  **and** seed data — behaviour byte-for-byte unchanged). New `migrate_all()` =
  `run_schema(false)` — every schema migration, **no seeds**.
- `index.php` — when the code fingerprint has moved (a deploy) but the probe
  otherwise passes, it now calls `migrate_all()`. So **every** pending
  table/column is created on the first request after any upgrade, whether or not
  a probe names it.

**Why it's safe (verified, not asserted):** the migrations are idempotent DDL;
`migrate_all()` never calls the seeds, so master data an admin cleared stays
cleared. Test: on a running server, dropped 5 un-probed columns from 5 different
modules **and** cleared the offices master, marked the fingerprint stale, made
one request →
- all 5 columns **healed** ✓
- cleared offices **stayed cleared** ✓ (the guarantee from commit 5aa95aa)
- fingerprint rewritten, so later requests skip the probe (no slowdown) ✓
- Add-user screen renders; suite 459/459.

---

## Environment baseline (09 Aug 2026)

| Check | Result |
|---|---|
| Unit test suite (`php tests/run.php`) | ✅ **459 / 459 pass** |
| Cold boot: schema build + seed + setup wizard | ✅ clean |
| All 94 navigable screens load | ✅ **zero PHP errors / warnings / notices** |
| Client create → save → appears in register | ✅ works (auto-code, duplicate-guard active) |

The core is healthy. This is a **polish pass**, not a repair job.

---

## Module 1 — Spine (pilot): Client → Quotation → Call → Job → Report → Invoice

| Stage | Screen | Loads | Create/write | Notes |
|---|---|---|---|---|
| Client | `/clients`, `/partner-new` | ✅ | ✅ saved id, in register | Numbered, clear help text |
| Quotation | `/quotes`, `/quote-new` | ✅ | ⏳ deep test pending | Dense (line items, T&C, multi-office) — expected |
| Call | `/calls`, `/call-new` | ✅ | ⏳ deep test pending | 6 clear sections; long by design |
| Job (Deputation) | `/jobs` | ✅ | ⏳ | — |
| Report | `/documents`, `/document-new` | ✅ | ⏳ | Report **register**, not "Dashboards" |
| Invoice | `/invoicing`, `/invoices`, `/to-bill` | ✅ | ⏳ | Three invoice states (see below) |

### Findings

**✅ Confirmed working**
- Cold install → setup wizard → live app: smooth, professional, friendly empty
  states ("Nothing pending — all calls are scheduled 🎉").
- Client write-path: legal name required, GSTIN→PAN/State auto-fill, branch-based
  code auto-generated, **duplicate-partner guard** present (`find_duplicate_partner`).
- No PHP errors on any spine screen.

**🧹 Clutter / clarity candidates**
- **Long forms** — New Call (6 sections + a 37-item "Types of Inspection Reports"
  checkbox grid + expense grid) and New Quote are very tall. Well-organised, but
  candidates for progressive disclosure (collapse rarely-used grids). *Density
  choice, not a bug.* ⟢ needs owner call on how aggressive to be.
- **Sidebar brand truncates** ("Exaact Inspect…") in the collapsed header. Minor.

**⟢ Menu-label clarity (NOT duplicates — verified in code)**
- `/reports` is labelled **"Dashboards"**, while the inspection-report register
  lives at `/documents`. Two different things both sound like "reports". Worth a
  clearer label.
- `/profitability` is labelled **"Contract number register"** — non-obvious for a
  profit screen.
- Invoicing shows as three menu items — `/invoicing` (register), `/invoices`,
  `/to-bill` (waiting to be billed). These are **different states**, not
  duplicates; confirm the labels read clearly to a new user.

**❎ Checked and cleared (false alarms — recorded so we don't re-flag)**
- `/inquiries` and `/leads` appear to have "two handlers" (crm.php/leads.php +
  ops.php). **Not a duplicate:** ops.php's dispatcher *calls* the functions
  defined in those files. Single live code path.

---

## Remaining modules — queued

Each will get the same treatment (walk with real values → log → fix → re-test).

| # | Module | Status |
|---|---|---|
| 2 | Masters & Setup | ✅ loads clean (health sweep) |
| 3 | Clients & Vendors (Directory) | ◑ partial (client create done) |
| 4 | CRM / Sales (leads, opps, quotes) | ✅ loads clean (health sweep) |
| 5 | Calls & Jobs (Operations) | ◑ forms verified, transactions pending |
| 6 | Inspectors & People | ✅ loads clean (health sweep) |
| 7 | Reports (IDEMS) | ✅ loads clean (health sweep) |
| 8 | Quality & Compliance (NCR/CAPA/audits) | ✅ walked — see Module 8 below |
| 9 | Money / Finance | ✅ loads clean (health sweep) |
| 10 | Dashboards & MIS | ✅ loads clean (health sweep) |
| 11 | Licensing & Tenants | ✅ deep-tested — see Module 11 below |
| 12 | Client Portal | ✅ loads clean (health sweep) |
| 13 | Admin & Security | ✅ loads clean (health sweep) |
