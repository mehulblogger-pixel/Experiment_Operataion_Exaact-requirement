# Slice P2 — Mobilization Readiness

**Change-control record (directive Part 25). Classification: CONNECT (read-only
composition; additive, non-destructive). Status: DELIVERED (P2a). P2b (gate-pass
record + a dedicated Mobilization board route) is staged — see Delivery record.**

Priority 2 in `03-target-architecture.md` §8. Directive Engine 3 (Mobilization &
Gate Pass) + the Mobilization-Officer cockpit (Part 20).

---

## 0. Revisit-trigger check (RT1 — `00-program.md` §9)

**Standing question:** *does the operational→commercial gap now dominate?* —
**No.** P2 is field/mobilization readiness; the billing gap is still addressed by
P4 on the roadmap. Proceeding with P2 as planned.

## 1. Existing state

A great deal already exists in `lib/pdso.php` (Project Deputation & Site Ops):
- **Deputation lifecycle** `dep_status` (Planned → Approved → Mob-pending →
  Mobilized → Active → … → Demobilized → Closed) with history (`dep_status_events`).
- **Mobilization checklist** `dep_checklist` (per job, phase MOB/DEMOB) with
  default items **already including "Site access / gate pass", "ID card", "PPE
  issued", "Induction", "Client approval", "Travel", "Accommodation"** — each
  configurable/removable; and `pdso_mob_readiness($jobId)` (required items open).
- **Manpower plan & gap**, **site history**, **timesheet**, **attendance approval**.
Plus, from other engines: the competence gate (`competence_block` / `auth_block`),
site-document requirements (`sitedoc_check`), general identity readiness
(`person_docs_summary`), credential status (Slice P1 `credential_status`), and
assets (`person_assets_summary`).

## 2. Problem (the gap)

All the signals exist, but on **five different screens**. There is no single
person-centric answer to the directive's core question:

> **"What is preventing this person from mobilizing to this posting?"**

A coordinator has to open competence, identity, site-docs, assets and the
deputation checklist separately and join them in their head.

## 3. Solution (delivered — all reuse, no new data)

- **`mobilization_readiness($jobId)`** (`lib/pdso.php`) — one read-only verdict
  that composes: the deputation MOB checklist (required-open → block), the
  competence gate (`competence_block`, mirrors the real allocation gate), the
  authorisation gate when enforced (`auth_block`), client site-document
  requirements (`sitedoc_check` → block/warn), general identity readiness
  (`person_docs_summary` → warn), credential expiry/rejection (Slice P1 →
  expiring=warn, rejected=block), and outstanding assets (warn). Returns
  `{ready, blockers[], warnings[], checklist, on_date}`. Every probe is
  `function_exists`-guarded and try-safe.
- **`mobilization_readiness_render($jobId)`** — the "🚦 Mobilization readiness"
  panel, shown on the deputation detail (`_deputation_panel.php`), listing the
  blockers and notes with the date the work is due.
- **`mobilization_readiness_badge($jobId)`** — a compact ✓ Ready / ⛔ n blockers
  badge, added as a **Readiness column on the `/deputations` board** (the
  Mobilization-Officer cockpit — see who is blocked at a glance).

## 4–8. Impact

- **DB:** none. No table, no column, no status, no permission.
- **API/routes:** none added; renders inside the existing deputation detail and
  board. Reads existing gates only.
- **Migration:** none.
- **Dependencies:** the allocation gate (`competence_lapsed`/`auth_block`) is
  **unchanged** — readiness only reads it. Mandatory-expired certs appear as a
  competence block (not double-counted as a credential note).
- **Permissions:** unchanged — the deputation detail and board already carry
  their own gates; `docs/02-permission-matrix.md` and
  `docs/03-object-lifecycles.md` need no change (nothing new to record).

## 9. Regression & validation

- `php -l` clean on all changed files.
- New `tests/test_mobilization_readiness.php` = **14/14** (ready path, missing
  checklist = note, required items block, completing them clears it, lapsed
  mandatory cert blocks, rejected credential blocks, badge + render, unallocated
  posting note, null for a missing job).
- **Full suite: 3782 passed, 0 failed.**

## 10. Rollback

Remove the two view lines (panel include + board column); the engine functions
are inert if unreferenced. No data touched.

## Delivery record (P2a) & what is staged (P2b)

**Shipped (P2a):** the readiness engine, the deputation-detail panel, and the
board Readiness column — the directive's "what's blocking this person" answer and
the Mobilization-Officer cockpit view.

**Staged (P2b), pending your go — and note the scope shrank:**
1. **Gate pass:** the target architecture proposed a *minimal gate-pass record*.
   On inspection, a gate pass is **already a default checklist item** ("Site
   access / gate pass", category Access). So P2b's gate pass is likely just a
   small **status/approval on that checklist item** (or a dedicated
   `gate_pass` row only if you want a separate request/approval trail). Either
   introduces a **new status lifecycle** (REQUESTED → APPROVED / REJECTED), which
   the guardrails require me to **confirm with you before building**.
2. **Dedicated `/mobilization` board** filtered to pre-active postings (the board
   column already delivers most of this on `/deputations`).

**RT1 re-check at delivery:** the operational→commercial gap does not yet
dominate; **next slice stays P3 (Inspection execution polish)** unless you decide
to (a) do P2b gate-pass now, or (b) pull P4 (Billable Event) forward.
