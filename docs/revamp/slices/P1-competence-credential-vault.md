# Slice P1 — Technical Competence / Credential Vault

**Change-control record (directive Part 25). Classification: EXTEND / CONNECT
(additive, non-destructive). Status: PROPOSED — awaiting approval. No code is
written until this record is approved.**

Priority 1 in `03-target-architecture.md` §8. This is the *lowest-risk* first
slice: ~70% of it already exists; the new work is additive read-models plus one
small optional column and one small optional config table.

---

## 0. Revisit-trigger check (RT1 — enforced every slice, see `00-program.md` §9)

> **Standing question:** *"Does the operational→commercial gap still exist and is
> it now the biggest blocker to value?"*

**Answer for P1 (at proposal time):** Yes, the gap still exists (P4 not built),
but it is **not** what P1 addresses, and P1 is a prerequisite source for P4's
competence/eligibility-driven billing. Therefore we proceed with P1 as planned.
**This same question is answered again at P1's acceptance review** (§10). If, at
that point, the billing gap is judged the biggest blocker to value, the next slice
becomes **P4 (Billable Event)** instead of P2 — automatically, per RT1.

---

## 1. Existing state (what is already there)

The competence/credential capability is substantially built and gated:

- **Certificates** — `inspector_certs` (`lib/ops.php`): `id, inspector_id, name,
  number, issued_date, valid_from, valid_to, status (default 'VALID'),
  is_mandatory, file_name/mime/data, last_reminder`. Expiry chase runs from cron
  (`ops_run_cert_reminders()`), 30-day window.
- **Qualifications / Authorisations / Witness** — `lib/competence.php`:
  - `qualifications` (what a person *has*),
  - `authorisations` (what the body *permits* — status `ACTIVE / SUSPENDED /
    EXPIRED / WITHDRAWN`, scope `ANY / INSPECTION_TYPE / ACTIVITY / CLIENT`,
    levels TRAINEE…AUTHORITY),
  - `witness_assessments` (last watched doing it; outcomes PASS / RETRAIN /
    REASSESS).
  - Engines: `inspector_eligibility($id,$ctx)` → `ELIGIBLE | CHECK | EXPIRING |
    BLOCKED` with reasons; `competence_matrix()`; `competence_training_watch()`
    (certs expiring within N days); `auth_covers()` / `auth_block()` (the
    per-work gate); `auth_run_maintenance()` cron (flips ACTIVE→EXPIRED/SUSPENDED);
    setting `authorisation_enforce` (`auth_enforced()`).
  - Routes: `/competence`, `/auth-add`, `/auth-status`, `/auth-enforce`,
    `/witness-add`. View: `views/ops/competence.php` (matrix + KPI row).
- **Identity documents** — `lib/identity.php`: `person_documents`,
  `person_document_access` (immutable access log), `site_doc_requirements`.
  Engines: `person_docs_summary()`, `iddoc_list()`, `iddoc_verified()`,
  `iddoc_readiness()`, `person_req_docs()`, `sitedoc_required()`/`sitedoc_check()`.
  Encrypted at rest (Phase 2 §53). DPDP-gated by `person.iddoc.view / manage`
  (never a role default). Routes `/identity`, `/iddoc-*`.
- **Gates already firing:** under the accreditation pack, competence + identity
  gates fire on `work.assign` (`lib/packs.php` `PACK_HOOKS`).
- **Module gates:** `mod.competence.*`, `mod.identity.*` (accreditation modules).

## 2. Problem (the gap to close)

Despite the above, three directive requirements are not yet met:

1. **No single per-person "Credential Vault" surface.** Certs, qualifications,
   authorisations and ID documents live on separate screens; there is no one
   place that shows *everything a person holds* and its status at a glance,
   spanning their roles (via `party`).
2. **Credential-level status vocabulary is incomplete.** The directive requires
   per-credential states **Valid / Expiring Soon / Expired / Under Verification /
   Rejected / Superseded / Missing**. Today `inspector_certs.status` is effectively
   just `'VALID'`; "Under Verification / Rejected / Superseded" and a uniform
   "Expiring Soon" band are not represented at the credential level. (The richer
   states exist only at the *authorisation* and *person-eligibility* levels.)
3. **Client-specific competency requirement sets are not configurable as data.**
   `site_doc_requirements` covers documents per site; there is no configurable
   "for Client X / this scope, these credential categories are mandatory" set that
   the vault can check a person against without code changes.

## 3. Proposed solution (additive, reuse-first)

Three parts, smallest-footprint first. Nothing replaces an existing screen or
engine; everything composes them.

### 3a. Credential-status derivation (read-only, no schema change)
A pure function `credential_status($row, $onDate=null)` that maps *existing*
fields to the directive vocabulary:
- `Missing` — a required credential with no record.
- `Expired` — `valid_to` in the past.
- `Expiring Soon` — `valid_to` within the configurable window (reuse the existing
  training-watch window / setting; default 45 days per `competence_training_watch`).
- `Valid` — in date.
- `Under Verification` / `Rejected` / `Superseded` — from the optional
  `verify_status` column (3b); absent → treated as not-set (no behaviour change).

This is the single place the vocabulary is named (mirrors how §79/80 wants one
canonical derivation). It reuses `days_between()`, the training-watch window, and
`auth_live()`/`auth_run_maintenance()` semantics for authorisations.

### 3b. Optional additive column for verification state
`ensure_column('inspector_certs','verify_status',"VARCHAR(20) DEFAULT ''")` —
holds `''` (default, = today's behaviour), `UNDER_VERIFICATION`, `REJECTED`,
`SUPERSEDED`. **Additive; default preserves current behaviour exactly.** A
`verified_by`/`verified_at`/`verify_note` trio may be added the same way if you
want an audit of who verified. No existing column is changed. (If you prefer zero
schema change in P1, this sub-part can be deferred and the three states simply
render as "not tracked yet" — say which you want.)

### 3c. Configurable client competency requirement sets (additive)
Two options — recommend **Option A** for speed and consistency:
- **Option A (reuse):** model requirement sets with the existing no-code builder
  `customforms` (`custom_forms` / `custom_records`) — a "Competency requirement
  set" register keyed by client + optional scope, listing required credential
  categories. Zero new tables.
- **Option B (tiny table):** `CREATE TABLE IF NOT EXISTS competency_requirements
  (id, client_id, scope_type, scope_ref, credential_category, mandatory, notes,
  created_at, created_by)`. One bootstrap probe line in `index.php`.

The vault then checks a person against the applicable set and shows
Fully-/Partially-Qualified / Missing / Expiring per requirement — this is also the
seed of Engine 2 (Requirement↔Resource matching) later.

### 3d. The Vault surface (compose, don't duplicate)
A read-first **Credential Vault** panel reachable from the inspector/person profile
and (optionally) as a tab on Entity-360, rendering:
- identity block (via `person_docs_summary`, respecting `person.iddoc.*` — masked
  unless the viewer holds the DPDP permission),
- certificates with derived status pills (3a),
- qualifications + authorisations (reusing `inspector_eligibility_pill`),
- requirement-set match (3c),
- the existing witness/eligibility summary.

Cross-role display uses `party_render_also()` so one human's vault is visible
whole (KEEP the mapping-layer approach; no table merge).

## 4. Reuse opportunity (what we are NOT rebuilding)

`inspector_certs`, `qualifications`, `authorisations`, `witness_assessments`,
`person_documents`, `site_doc_requirements`, `inspector_eligibility()`,
`competence_matrix()`, `competence_training_watch()`, `iddoc_readiness()`,
`person_docs_summary()`, `party.php` resolver, `customforms`, `datatable`,
`lookups`, `days_between()`, the cron reminders, and the `work.assign` gate — all
reused. **No new report engine, no new document vault, no duplicate person record.**

## 5. Database impact

- **Additive only.** Optional: one column `inspector_certs.verify_status` (+
  optional `verified_by/at/note`) via `ensure_column` (adds only if missing).
  Optional: one small `competency_requirements` table via `CREATE TABLE IF NOT
  EXISTS` **or** zero new tables if Option A (customforms) is chosen.
- **No column is dropped, renamed, or repurposed.** Defaults chosen so existing
  rows behave exactly as before.
- **Bootstrap probe:** if 3b/Option-B are used, add one probe `SELECT` line in
  `index.php` so a live MySQL upload auto-migrates (README §4).

## 6. API / route impact

- **New read routes only** (e.g. `/credential-vault?kind=&id=`), gated by existing
  `mod.competence.view` (and `person.iddoc.*` for the identity block).
- Existing routes (`/competence`, `/auth-*`, `/identity`, `/iddoc-*`) unchanged.
- If 3b is enabled, a small POST (e.g. `/cert-verify`) to set `verify_status`,
  gated by an existing competence/manage right — **no new permission** unless you
  ask for one (see §8).

## 7. Migration requirement

Idempotent, one pass, via the existing `boot()` chain (`competence_migrate()` /
`identity_migrate()` already exist; we extend their `ensure_column` blocks). No
manual migration command; no data backfill needed (derivation is read-time).

## 8. Dependency & permission impact

- **Permissions:** none added. Reuses `mod.competence.*`, `mod.identity.*`,
  `person.iddoc.view/manage`. *If* you later want a distinct "verify credential"
  right, that is a new permission and will be brought to you first (guardrail 3).
- **Gates:** the `work.assign` competence/identity gate is **not modified**; the
  vault only *reads* the same signals. No change to who can be assigned.
- **Docs in lockstep:** if 3b adds `verify_status` states, `docs/03-object-
  lifecycles.md` gets a short "credential verification" note in the same commit;
  `docs/02-permission-matrix.md` is unchanged (no new permission).

## 9. Regression risk & mitigation

- **Risk: low.** All changes are additive and read-first; defaults reproduce
  current behaviour. Primary risk is a mis-derived status pill (display only, not a
  gate).
- **Mitigation:** the derivation is a pure function with unit tests (§10); the
  vault is behind `mod.competence.view` so it is invisible where not granted; the
  `work.assign` gate path is untouched and covered by existing tests.
- **Validation before commit:** run `phpapp/tests/`; `php -l` on changed files;
  reproduce the competence screen + a job-assign with a lapsed cert to confirm the
  gate behaves identically.

## 10. Acceptance criteria (no feature is "done" on the happy path — Part 27)

- **Positive:** a person with a valid mandatory cert shows `Valid`; an authorised
  inspector shows a live authorisation; the vault renders all four credential
  families for one person across roles.
- **Negative:** a person missing a required credential shows `Missing`; a rejected
  cert (if 3b) shows `Rejected` and does not count as satisfying a requirement.
- **Boundary:** `valid_to` exactly today, and exactly at the Expiring-Soon window
  edge, classify correctly; no-expiry credential kinds never show `Expiring/Expired`.
- **Expired credential:** a cert past `valid_to` shows `Expired`; the existing
  `work.assign` gate still blocks/warns exactly as before (unchanged).
- **Permission:** a viewer without `person.iddoc.view` sees the identity block
  **masked**; a viewer without `mod.competence.view` cannot reach the vault.
- **Offline/field:** the vault is read-only and degrades gracefully on the phone
  (no offline write introduced by this slice).
- **Regression:** full suite green; competence matrix, cert reminders cron, and
  `auth_run_maintenance` behave identically.
- **RT1 re-check (recorded here at review):** *does the operational→commercial gap
  now dominate?* — answer recorded; if "yes", next slice = P4.

## 11. Rollback strategy

- Turn `mod.competence` view off to hide the vault (instant, reversible).
- If 3b/Option-B tables/columns were added, they are inert additive objects; they
  can be left in place (no data loss) or dropped. No existing data is touched, so
  rollback is safe and total.

## 12. Delivery checklist (once approved)

1. Extend `competence_migrate()` / `identity_migrate()` with the optional additive
   column/table; add probe line if Option B.
2. Add `credential_status()` derivation + tests.
3. Add the `/credential-vault` read route + view, composing existing summaries.
4. Add requirement-set config (Option A customforms, or Option B table).
5. Update `docs/03-object-lifecycles.md` (only if 3b adds states) + this slice
   doc's §10 with the recorded acceptance results and the RT1 answer.
6. `php -l` + `phpapp/tests/`; commit per logical batch; push **only** to
   `exaact-ops-system-tpia-manpower`.

---

### Open questions for you before coding P1
1. **Verification states (3b):** include the additive `inspector_certs.verify_status`
   column now (recommended — it's what enables *Under Verification / Rejected /
   Superseded*), or defer it and ship P1 with zero schema change first?
2. **Requirement sets (3c):** Option A (reuse `customforms`, zero new tables —
   recommended) or Option B (small dedicated table)?
3. **Vault placement:** person/inspector profile only, or also as an Entity-360 tab?
