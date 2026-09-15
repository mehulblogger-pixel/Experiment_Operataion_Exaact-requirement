# Phase 3 · M1 — Completion Report

**Approval foundation + hiring request approval workflow.** Commit `5153ba1`
(implementation) and this document's commit.

## 1. Existing approval infrastructure audited

The Phase-6 recruitment approval engine (`lib/recruit_approval.php`): four
tables, rules matched by entity + department/BU/grade/position + value band, a
multi-level chain with SLA, reminders and escalation, a runtime request + steps,
an inbox, a cron tick and e-mail. `recruit_approval_requests` was **already
polymorphic** — `entity` + `entity_id`. Full audit: `M1-APPROVAL-AUDIT.md`.

## 2. What was reused

Both approval tables unchanged; `appr_match`, `appr_start`, `appr_act`,
`appr_can_act`, `appr_open`, `appr_steps`, `appr_current_step`, `appr_inbox`,
`appr_tick`, the mailer, the rules/levels configuration screen. From M4:
`hreq_is_executable()`, `hreq_in_scope()`, `hreq_can_create()`,
`hreq_can_decide()`, the snapshot, every approval column. The entitlement chain
(`can()` → `licence_blocks()` first) and `is_master_of()`.

## 3. What was extended

`APPR_ENTITIES` +1 entry · one `appr_callback()` branch · one `appr_guard()` ·
`is_master()` → `is_master_of('hiring')` · a licence check on `/my-approvals` ·
`hreq_submit()` starts a chain · `hreq_decide()` stands aside for one ·
`ACT_ENTITIES` +2 entries.

## 4. What was newly built

`hreq_apply_decision()` (the one decision writer), `hreq_segregation_blocks()`,
`hreq_approval()` / `hreq_approval_steps()` / `hreq_appr_ctx()`, `appr_guard()`.
Nothing structural. **No table, no column, no permission.**

## 5. Why anything new was necessary

The engine had **no** segregation rule, **no** branch scope and **no**
entitlement question — all three are M1 requirements and none existed. The one
writer exists because a decision can now arrive two ways and they must not be
able to disagree.

## 6. Hiring Request lifecycle

`DRAFT → SUBMITTED → UNDER_REVIEW → APPROVED / REJECTED`, plus `CANCELLED`. No
new status: `UNDER_REVIEW` **is** approval-pending. Detail: `M1-LIFECYCLE.md`.

## 7. Approval authorization model

No hard-coded job title. Chain: be that step's approver (named user or
configured role) **and** pass `appr_guard()`. Direct (no rule matched): M4's
rule — the module **and** a management role — refused while a chain is open.
Detail: `M1-AUTHORIZATION.md`.

## 8. Self-approval

Refused. Compared on `requested_by_id → users.id`, enforced in the engine via
`hreq_segregation_blocks()` — one rule read by both decision paths. Proved
against a requestor who **does** hold the approver role. M4's single master
exception preserved, not broadened.

## 9. Entitlement enforcement

Asked **first**, before anything about the person. An unlicensed workspace
refuses the approver, refuses a master, and refuses the inbox route.

## 10. Branch-scope enforcement

`hreq_in_scope()` inside `appr_guard()` — at the decision, not only the route.
Proved against a foreign-branch user holding the approver role.

## 11. Approval / rejection implementation

`appr_act()` → `appr_callback('HIRING_REQUEST')` → `hreq_apply_decision()`,
which holds the state rule and writes the audit entry. Approval history is the
existing `recruit_approval_steps`; there is no second history store.

## 12. Request → Requisition boundary

`hreq_is_executable()` remains the single authoritative question. Conversion
permitted only from `APPROVED`; refused from `DRAFT`, `SUBMITTED`,
`UNDER_REVIEW`, `REJECTED`, `CANCELLED`, and refused for an unlicensed,
out-of-scope or unauthorized user.

## 13. Database changes

**None.**

## 14. Files changed

`lib/recruit_approval.php` · `lib/hiringreq.php` · `lib/activity.php` ·
`lib/reqfulfil.php` · `views/ops/hiring_request.php` ·
`views/ops/my_approvals.php` · `tests/test_p3m1_approval.php` (new) ·
`tests/test_m4_correction.php` · `deploy-check.php` · six Phase-3 documents ·
`docs/02-permission-matrix.md` · `docs/03-object-lifecycles.md`.

## 15. Mutation test table

Twelve mutations, **twelve caught, none survived**. Full table with protection
removed / expected / actual / verdict: `M1-TESTING.md` §4.

## 16-18. Test results

| Engine | Whole suite | M1 suite |
|---|---|---|
| SQLite | **8902 passed, 0 failed** | 85 assertions, 0 failed |
| **MariaDB 10.11.14** (fresh database) | **8903 passed, 0 failed** | **85 assertions, 0 failed** |

The M1 suite was confirmed to have actually executed on MariaDB — all eleven
sections present, assertions counted inside that section.

## 19. Cross-module regression

Whole suite green on both engines: Operations, Quality, Reporting, Money,
Workforce, CRM, Marketplace/Connect, Dashboard, Recruitment, Project Costing and
the existing approval consumers (offers, salary, requisitions) all pass. No test
was weakened, deleted or skipped; two were **followed** through architectural
change.

## 20. Known limitations

- **Re-approval is not built.** A rejected request is final; the screen says so.
  The fields that will need re-approval are listed in `M1-AUTHORIZATION.md` §6.
- **Segregation and scope guarding are entity-scoped to `HIRING_REQUEST`.**
  Offers, salary structures and requisitions behave exactly as they did in
  Phase 6. Extending them is a policy decision, recorded not taken.
- **`/my-approvals` asks the licence, not `mod.hiring.view`.** Deliberate: the
  permission would lock out a configured approver who holds no recruitment
  module.
- The approval engine still has no audit of its own for the other three
  entities; only the Hiring Request path audits.

## 21. Deferred Phase-3 work

Configurable approval matrix UI, multi-level routing beyond what the engine
already does, delegation, SLA engine, escalation engine, inbox redesign,
notification redesign, recruiter assignment / reassignment / KPI / workload,
Phase 4 sourcing, Marketplace fulfilment, multi-source allocation, identity
convergence, dashboard and UX redesign.

## 22-23. Git

Branch `claude/testing-branch-setup-0gqe8n`. Implementation `5153ba1`; this
report follows it. Working tree clean — mutation testing runs against a copy of
the application **outside the repository**, so a mutation can never be committed.

## Post-implementation codebase search (§29)

| Searched for | Found |
|---|---|
| duplicate approval engine / table | none — the three others (`quote_approvals`, `report_approvals`, `dep_att_approval`) are pre-existing and belong to CRM, IDEMS and attendance |
| duplicate decision logic | none — four `UPDATE hiring_requests SET status` statements, all in the one layer, one of them the single decision writer |
| duplicate permission | **none — M1 did not touch `lib/access.php` at all** |
| duplicate status system | none — `hiring_request_status` lookup only |
| duplicate audit system | none — `activities` is the spine; the `audit_*` tables are the ISO internal-audit register, a different domain |
| unguarded / unscoped mutation | none — enforced by the write-path tests |
| `is_master()` entitlement bypass | none — the only one left is `is_master_of('hiring')` |
| direct status manipulation outside the layer | none |
| hidden approval route / AJAX bypass | none |
| duplicate conversion function | **none added by M1** (0 new `INSERT INTO requisitions`) |

---

## PHASE 3 — M1 COMPLETE — HARD STOP — READY FOR M1 AUDIT
