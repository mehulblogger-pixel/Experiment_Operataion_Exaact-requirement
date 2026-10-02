# EXAACT · Phase 7 — Release-Readiness Baseline

**Application baseline:** `cdb9eee` · branch `claude/testing-branch-setup-0gqe8n`
**Established:** 2026-10-02 · documentation only
**Companion:** `MASTER-DEFERRED-BACKLOG-REGISTER.md` (what is outstanding)
**Gate records:** `RECRUITMENT-UNIVERSALISATION-IMPLEMENTATION-READINESS-AUDIT.md` §20b, §20c–§20n

---

## 1. Position

**Implementation gates 0 → 6C are CLOSED.** Release preparation begins. There is no
Gate 7, and this document does not create one: everything below is release
*validation*, not new product development.

The deferred backlog is separate from the critical path. It does not become
implementation work simply because the gates have closed.

---

## 2. Current evidence at `cdb9eee`

| Measure | Result |
|---|---|
| Full regression — SQLite | **16,206 passed / 0 failed** |
| Full regression — MariaDB (authoritative) | **16,205 passed / 0 failed** |
| Gate 6B battery — both engines | **73 passed / 0 failed** |
| Server mutations | **16 / 16 killed** |
| Browser mutations | **6 / 6 killed** |
| Browser assertions (desktop + 360/390/412px) | **60 passed / 0 failed** |

### Protected suites — authoritative MariaDB, from the final Gate 6C audit

| Suite | Result | Suite | Result |
|---|---|---|---|
| Gate 3 | 225 / 0 | Recruitment | 483 / 0 |
| Gate 4 | 142 / 0 | Workforce | 101 / 0 |
| Gate 5 | 101 / 0 | KPI / SLA | 232 / 0 |
| Gate 6 | 102 / 0 | Security | 289 / 0 |
| Gate 6B | 73 / 0 | SaaS isolation | 30 / 0 |
| Module 05 | 14 / 0 | Module 34 | 12 / 0 |

> **These are development/test evidence figures. They are not production
> verification figures.**
>
> Every number above was produced against throwaway SQLite files and local MariaDB
> databases created for the purpose. None of it says anything about the live
> environment, the live data, or the live configuration. Production verification is
> blockers **B-2**, **B-3** and **B-4**, and none has been performed.

### How the full-regression figures were obtained, and one thing to know

Both engines were run in **separate clean worktrees**, sequentially isolated. An
earlier attempt that ran them concurrently in a shared worktree produced 9 and 11
spurious failures, because both runs competed for one tenant-registry file path — the
MariaDB log even carried a SQLite error. That run was discarded as invalid.

**Anyone repeating these runs must give each engine its own checkout.** Two full
suites in one directory will not reproduce these figures.

---

## 3. Release path

```
Documentation Closure              ← this document
        ↓
Release Version Preparation        ← B-1
        ↓
Business UAT                       ← B-5
        ↓
Production Environment Verification ← B-3
        ↓
Production Backup Verification     ← B-2
        ↓
Production Restore Verification    ← B-2
        ↓
Release Packaging
        ↓
Production Deployment              ← B-4
        ↓
Post-Release Checksum Verification ← B-4
        ↓
Production Smoke Test              ← B-4
        ↓
Phase 7 Closure
```

Each step is validation of work already complete. **No step on this path is product
development, and no step may be used to introduce new features.**

---

## 4. Release blockers versus deferred backlog

The distinction matters more than any individual item, so it is stated plainly.

**Release blockers — 1 closed, 4 open.** All must be closed before production
release:

| ID | Blocker | Status |
|---|---|---|
| ~~**B-1**~~ | ~~Stale release version identity~~ | **CLOSED** — resolved at `0f2123d`, 2026-10-02 |
| **B-2** | Production backup + restore not verified | Not started |
| **B-3** | Production environment not verified | Not started |
| **B-4** | Deployment, checksum verification, smoke test not performed | Not started |
| **B-5** | Business UAT not executed or signed off | Not started |

**Deferred backlog (6)** — knowingly not done, **none blocking release**: D-01
(A-F7 reports/pickers), D-02 (aggregated Next Action), D-03 (Person Hub / identity
convergence), D-04 (36px secondary touch targets), D-05 (no SQLite busy timeout),
D-06 (demo unload clears an approver mapping by employee number).

**Open — owner decision or external action (3):** O-01, O-02, O-03.

Full detail for every item is in `MASTER-DEFERRED-BACKLOG-REGISTER.md`. Nothing in
the deferred list is a blocker, and nothing in the blocker list is backlog.

---

## 5. B-1 — CLOSED at `0f2123d` (was the one code-level release blocker)

> **Status, 2026-10-02: RESOLVED.** The release identity is now `2026.10.1` /
> `2026-10-02` and the checksum manifest has been regenerated (677 files). The
> package identity resolves to `exaact-2026.10.1`. The table below is retained as
> the historical record of what the blocker was and why it mattered.

| | |
|---|---|
| **Current** | `APP_VERSION = '2026.07.1'` · `APP_VERSION_DATE = '2026-07-27'` |
| **Proposed** | `APP_VERSION = '2026.10.1'` · `APP_VERSION_DATE = '2026-10-02'` |
| **Where** | `phpapp/lib/preflight.php:25-26` |
| **Why it matters** | `tools/release.sh` names the package from `APP_VERSION`, and the administrator deployment tool reports it. The stamp was last changed in `b963490`, **697 commits** ago — before every gate from 1A through 6C-F1. A package built today would be named `exaact-2026.07.1`, indistinguishable from a build predating all of this work. |

*(Historical note, true when written: "Not changed in this documentation task,
deliberately. It is the next controlled release-preparation action, and it must be
done together with regenerating the deploy checksum manifest, because changing a
tracked file invalidates the manifest.")*

**That is what was done.** Commit `0f2123d` changed the two constants and
regenerated the manifest in the same commit — two files, four lines. No behaviour
changed and no test needed updating, because `APP_VERSION` is never compared or
branched on: the only `version_compare()` in the application tests PHP's own
version. Validation at that commit: deploy_check 14/0, parse_all 1/0, boot 25/0,
schema_guard 6/0, release_note_button 8/0, `git diff --check` clean.

---

## 6. Production safety — the current, factual position

- **No production deployment has occurred.**
- **No production database has been modified.**
- **No production data has been migrated**, and none has been read.
- **No production figures appear anywhere in this programme's evidence.** Where a
  document quotes row counts — including Gate 6A's §17 data analysis — those are
  development or demo snapshots. **No production figure may be inferred from them.**
- The stale local `phpapp/tenants.php` in the original working copy **is not a
  production defect.** It is a git-ignored artifact left by the defective pre-F1 test
  runs, it has never been tracked in any commit, and no application code in the Gate
  6B change reads or writes it.
- **Clean worktrees and fresh clones do not reproduce it.** Proven at `cdb9eee`: the
  corrected battery, the full SQLite suite and the full MariaDB suite each left it
  absent.

---

## 7. Business UAT

The playbook is `docs/phase7/EXAACT-BUSINESS-UAT-END-TO-END-PLAYBOOK.md` — 111
sections, eight journeys (A Recruitment, B TPIA order-to-cash, C Marketplace, D Money
and billing, E Dashboards, F Search, G Inspector's phone, H Negative and security),
plus safety rules, a role map, a module map and a "do not press" list.

**It is not rewritten by this task.** Two checks were added for the Gate 6B work:
**UAT-G6B-01** (the Review Required trigger on Recruitment → Approval Rules) and
**UAT-G6B-02** (the utilisation report's per-person breakdown), in the playbook's new
§23.

**The playbook's own stated limitation stands and must be respected:** it was written
without internet access to `https://operations.mghaiapps.com`, so its §0
reconciliation step — confirming the playbook matches your live site — must be done
first and must not be skipped.

**During UAT: no documents issued, no money moved, no real business records
altered.** The "do not press" list exists for exactly this reason.

---

## 8. Production environment verification scope (B-3)

Read-only, against the live site:

1. Version and build identity as reported by the deployment tool.
2. Checksum verification across all deployable files via `deploy-check.php`.
3. PHP version and required extensions.
4. MariaDB reachability and engine version.
5. Writability of the paths the application needs.
6. Licence and entitlement state.
7. Cloud-mode and base-domain settings.
8. Control database confirmed separate from tenant databases.

---

## 9. Backup and restore verification — a release blocker, not a backlog item (B-2)

The capability exists and is unit-tested (`backup_create`, `backup_list`,
`backup_restore`, `backup_export`, `backup_import_json`, `backup_auto_daily`;
`test_backup.php`, 16 assertions passing). **Capability is not evidence.**

Before release, a production backup must be:

1. **Taken** from production.
2. **Restored** into an isolated scratch database.
3. **Proven readable.**
4. **Checked for money-bearing tables.**
5. **Checked for people / workforce tables.**
6. **Confirmed against expected row counts.**
7. **Confirmed not to have affected production.**
8. **Automatic daily backup execution verified** as actually running, not merely
   present.

**None of the eight has been performed.** This document does not claim otherwise, and
no later document should, unless actual evidence exists. A backup that has never been
restored is not a backup.

---

## 10. Production smoke test scope (B-4)

`tools/smoke.js` provides a route sweep usable as the mechanical half. In addition,
after deployment: sign in as administrator, coordinator, finance and inspector; one
requisition read; one work-order read; one invoice read **without issuing**; the
availability board; the utilisation report; the approval-rules screen; the audit
trail; and one phone-width pass of the inspector's screens.

**All reads. No document issued. No money moved.**

---

## 11. Go / no-go conditions

**Go** only when every one of these holds:

1. ~~**B-1** resolved — version bumped, checksum manifest regenerated, committed.~~ **DONE at `0f2123d`.**
2. **B-5** — Business UAT signed off by the owner, including UAT-G6B-01 and
   UAT-G6B-02.
3. **B-3** — production environment verification clean.
4. **B-2** — a production backup restored and **proven**, including the daily job.
5. **B-4** — post-upload checksum verification clean.
6. **B-4** — production smoke test clean.
7. A written rollback procedure the owner has read and accepted.

**No-go** on any of: a failed restore, a checksum mismatch, any failure in UAT
journey H (negative and security), or any blocker discovered during verification.

---

## 12. What this baseline does not do

It does not deploy, package, bump the version, access production, run UAT, or run a
backup. It does not start an implementation gate. It records the position so that the
next action — controlled release-version preparation — begins from an agreed and
evidenced starting point.

---

## 13. Verification status — 2026-10-02, release candidate `0f2123d`

An audit was run to close Business UAT and begin production verification. It could
close neither, and this section records what was actually checked so the next
attempt does not repeat it.

### B-5 · Business UAT — OPEN. Not executed.

The repository was searched for evidence of an executed UAT against this release
candidate. There is none:

| Evidence sought | Found |
|---|---|
| A commit after `0f2123d` | **none** — the tree is clean and `0f2123d..HEAD` is empty |
| A UAT results or sign-off document for 2026.10.1 | **none** |
| Recorded outcomes in `EXAACT-UAT-CONSOLE.data.json` | **none.** Its JSON keys are `coverage, do, expect, gates, group, id, key, label, n, steps, sub, text` — 229 steps *to perform*, with no result, status, verdict, date or signed-by field anywhere. It is a checklist, not a record. |
| `P7-FINAL-RELEASE-UAT.md` | **Not evidence for this candidate.** Dated 2026-09-22, baseline `295e5d8`, 13,251 assertions — a different and earlier release attempt, written before Gates 0–6C existed. Its own §2, §3 and §4 record production environment verification, backup verification and the smoke test as **BLOCKED**. |

**No journey A–H result exists, and neither UAT-G6B-01 nor UAT-G6B-02 has been
run.** B-5 therefore stays OPEN. Nothing about the product is implied by this: it
means the acceptance activity has not happened yet.

### B-3 · Production environment verification — OPEN. Not attempted.

Gated on UAT passing, so not begun. Separately, it is **not performable from the
development environment**, which was re-checked on 2026-10-02:

| Prerequisite | State |
|---|---|
| `phpapp/config.local.php` (would hold production credentials) | **absent** |
| Production hostname in any config file | **none** |
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` environment variables | **none set** |

This matches what `P7-FINAL-RELEASE-UAT.md` found in September. B-3 needs either
production credentials supplied to a verification environment, or an administrator
performing the read-only checks in §8 from a browser on the live site.

### B-2 · Backup and restore verification — OPEN. Not attempted.

Gated on UAT, and likewise not performable here: `exaact_backups/` contains only
`__control/` — development and demo snapshots, the same source Gate 6A §17 used.
**No production backup is present in this environment**, so none of the eight steps
in §9 can be evidenced from it.

### B-4 · Deployment, checksum verification, smoke test — OPEN.

Unchanged. Depends on B-1 (done), B-2, B-3 and B-5.

### Production safety during this audit

No production system was contacted. No production data was read, written or
migrated; no configuration was changed; nothing was deployed, packaged or uploaded.
The only changes were to the two documents recording blocker status.
