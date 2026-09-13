# 14 — PHASE COMPLETION TEMPLATE
Covers deliverable **32**. Copy this per phase / sub-phase. A phase is COMPLETED only when every box is genuinely true.

---

## Phase identity
- **Phase / Sub-phase / Task ID:**
- **Title:**
- **Status:** NOT STARTED / IN PROGRESS / BLOCKED / READY FOR TEST / TESTING / FAILED / FIXING / READY FOR UAT / UAT PASSED / COMPLETED / DEPRECATED / DEFERRED
- **Owner:** · **Started:** · **Completed:**

## 1. Objective
What business outcome this delivers, in one paragraph of plain language.

## 2. Existing component reused
| Component | File(s) | Decision (REUSE / EXTEND / CONNECT / MAP / REFACTOR / MIGRATE / DEPRECATE / BUILD) | Why not reused, if BUILD |
|---|---|---|---|

> A `BUILD` entry requires an explicit justification that no existing engine fits. Duplicate engines are forbidden (brief §39).

## 3. Dependencies
- Depends on phases:
- Modules touched:
- **Protected paths touched?** (`lib/ops.php`, `lib/access.php`, `lib/licence.php`, `run_schema()`, `config.php`, `lib/idems.php`, `views/dashboard.php`) — if yes, full regression is mandatory.

## 4. Changes
- **Files:**
- **Database (additive only; no drops — there is no rollback):**
- **Routes / UI:**
- **Permissions** (must already exist in `docs/02-permission-matrix.md`; a new permission requires sign-off):
- **Migration + backfill (idempotent?):**

## 5. Acceptance criteria
| # | Criterion | Met? | Evidence |
|---|---|---|---|

## 6. Testing (all levels that apply — brief §25)
| Level | Applicable | Tests added | Result |
|---|---|---|---|
| 1 Static (`php -l`, routes, includes) | | | |
| 2 Unit | | | |
| 3 Database (CRUD, constraints, tenant isolation, migration idempotency) | | | |
| 4 API | usually N/A | | |
| 5 UI (screen, field, validation, state, permission) | | | |
| 6 Workflow (every legal transition) | | | |
| 7 Negative (see §34 matrix) | | | |
| 8 Security (authn, authz, tenant isolation, direct URL, **entitlement bypass**) | | | |
| 9 Cross-module regression | | | |
| 10 End-to-end journey | | | |

### 6.1 Entitlement tests (mandatory for every phase)
- [ ] Paid/active tenant can use it
- [ ] Unpaid tenant refused: menu · route · **direct URL** · form POST · AJAX · public route · cron · export · report · notification · deep link
- [ ] Upgrade path verified, existing data intact
- [ ] Downgrade path verified, historical records preserved
- [ ] Re-activation verified
- [ ] Cross-tenant access refused

### 6.2 Measured suite result (re-measured, never quoted)
```
SQLite:  php tests/run.php      → ____ passed, ____ failed, ____ s
MySQL:   (Phase 2.1 harness)    → ____ passed, ____ failed, ____ s
```
Baseline at Phase 0 was **6948 passed / 0 failed / 81.83 s (SQLite only)**. Any drop must be explained.

### 6.3 Mandatory regression scenario
- [ ] **S-1 Operations-only tenant** still fully functional and Recruitment still unreachable.

## 7. Manual / UAT evidence (brief §33)
Started from a **new tenant**, not seeded data.

| Test ID | Persona | Preconditions | Screen | Action | Expected | Actual | Pass/Fail | Evidence | Defect ID |
|---|---|---|---|---|---|---|---|---|---|

## 8. Defects
| ID | Description | Severity | Status | Fixed in |
|---|---|---|---|---|

## 9. Rollback plan
Schema is forward-only. State exactly how this change is disabled without data loss (feature flag / entitlement switch / stop writing the column).

## 10. Sign-off
- [ ] Acceptance criteria met
- [ ] All applicable test levels executed and passing, **re-measured**
- [ ] Entitlement tests pass
- [ ] S-1 Operations-only regression passes
- [ ] No duplicate engine, master, identity or approval chain introduced
- [ ] No silent removal of data, fields, permissions, workflows or APIs
- [ ] Docs updated in the same commit where roles/permissions/lifecycles changed (repo `CLAUDE.md` rule)
- [ ] UAT passed and evidence recorded

**Approved by: ____________  Date: __________  → Status = COMPLETED**
