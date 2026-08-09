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
| 2 | Masters & Setup | ⏳ queued |
| 3 | Clients & Vendors (Directory) | ◑ partial (client create done) |
| 4 | CRM / Sales (leads, opps, quotes) | ⏳ queued |
| 5 | Calls & Jobs (Operations) | ◑ forms verified, transactions pending |
| 6 | Inspectors & People | ⏳ queued |
| 7 | Reports (IDEMS) | ⏳ queued |
| 8 | Quality & Compliance (NCR/CAPA/audits) | ⏳ queued |
| 9 | Money / Finance | ⏳ queued |
| 10 | Dashboards & MIS | ⏳ queued |
| 11 | Licensing & Tenants | ⏳ queued |
| 12 | Client Portal | ⏳ queued |
| 13 | Admin & Security | ⏳ queued |
