# Phase 3 · M2 — Completion Report
### Approval Matrix, Authority & Delegation

**1. What existed.** A substantial configurable matrix: `recruit_approval_rules`
/ `_levels` / `_requests` / `_steps`, a sequential chain (`seq` + `current_seq`),
an administration screen, a cron tick, and **org-chart approver resolution**
(`__MGR1__`…`__HOD__`, walking the position tree). No approval role was ever
hard-coded. Full audit: `M2-AUDIT.md`.

**2. Reused.** All four tables; `appr_match`/`start`/`act`/`can_act`/`inbox`/`tick`;
the rules screen; org-chart resolution; the audit spine; M1's guard, single
decision writer and single executability question. **Priority reuses the existing
`sort`.**

**3. Extended.** Three additive nullable columns on the existing rules table:
`applies_office_id`, `effective_from`, `effective_to`.

**4. Newly built.** One table: `approval_delegations`.

**5. Why unavoidable.** Branch was the real functional gap. Dates let a policy be
scheduled and retired. And nothing could represent a standing delegation — IDEMS's
`delegated_to` is a nullable integer on one step of another module's engine, with
no delegator, dates, scope or revocation.

**6. What determines the matching rule.** Entity · department (vocabulary-aware
since M3) · business unit · grade · position · **branch** · size band ·
**effective dates** · active. Details and the four deliberately-unimplemented
conditions: `M2-MATRIX-RULES.md`.

**7. Precedence.** **Specificity → match order (`sort`) → age.** Deterministic
before M2, now documented, with branch counting toward specificity, the ranked
runners-up exposed by `appr_match_all()`, and the tie asserted five times running.

**8. Approver resolution.** Named user · acting for that user · configured role ·
acting for a role-holder — plus org-chart tokens resolved at chain creation.

**9. Capability.** `can('mod.hiring.*')` through the one `can()` choke point,
licence first. **10. Branch scope.** `hreq_in_scope()` inside `appr_guard()`, at
the decision. **11. Self-approval.** `hreq_segregation_blocks()` compares the
acting user against `requested_by_id`.

**12. Orphan roles.** Warned at configuration, shown as "nobody" in the preview,
and at runtime the request waits — non-executable, never auto-approved.

**13. Levels.** Sequential, unchanged.

**14. Delegation.** Active + in-window + entity + branch + the delegator
genuinely holds the authority + the delegate independently passes entitlement,
capability, scope and segregation. `M2-DELEGATION.md`.

**15. Delegation vs segregation.** The requestor given the approver's delegated
authority is **still refused** — a behavioural test, because it is the case a
reader will most doubt.

**16. Configuration protection.** `hiring_admin_can()` on both screens; the
delegation route is entitlement-mapped, so an unlicensed workspace is refused
**including a master**.

**17. Policy history.** `rule_id` + `rule_name` stamped on the chain; renaming
afterwards does not rewrite a finished chain.

**18–19. Inbox / M1 Finding 2 — CLOSED.** `appr_visible()` makes seeing the same
question as acting, entity-aware. It calls `appr_can_act()` first, so it can only
narrow, never widen; Offer, Salary and Requisition queues are untouched. The
probe that found the leak reports **0 rows instead of 1**, and mutation D13
guards it.

**20. Database changes.** Three additive nullable columns (`ensure_column`, the
existing idempotent helper) + one additive table. Forward-only, non-destructive,
tenant-safe.

**21. Files changed.** `lib/recruit_approval.php` · `lib/hiringreq.php` ·
`lib/activity.php` · `lib/ops.php` · `lib/navindex.php` ·
`views/ops/approval_rules.php` · `views/ops/approval_delegations.php` *(new)* ·
`tests/test_p3m2_matrix.php` *(new)* · `deploy-check.php` · seven Phase-3
documents.

**22. Mutations.** **Eighteen of eighteen caught, none survived.** Table and the
three first-run survivals — all gaps in my own tests, strengthened rather than
excused — in `M2-TEST-RESULTS.md` §5.

**23–24.** SQLite **9017 / 0**; **MariaDB 10.11.14 9018 / 0** (fresh database);
M2 suite **86 / 0 on both**, all 11 sections confirmed present on MariaDB.

**25. Shared approval regression.** `p3m1_approval` 114/0 · `recruit_approval`
25/0 · `offer_appr_dept` 2/0 · `m4_correction` 107/0 · `m4_hiring_request` 78/0 ·
`m3_multi_vacancy` 99/0 · `hiring_admin` 11/0. Nothing weakened, deleted or
skipped.

**26. Known limitations.**
- **Parallel approval unsupported** — the engine tracks one `current_seq`.
- **Four conditions not implemented**: `request_type`, `employment_type`,
  `priority`, `required_by`.
- **No "mandatory approval" tenant switch** — no-match still means the request is
  decided directly, exactly as M1 left it. Changing that would alter every
  existing installation on upgrade, so §8's instruction was to document rather
  than change silently. **This is an open business decision.**
- **Delegation does not chain**, by design.
- The approval engine still writes no audit of its own for Offer, Salary and
  Requisition; only the Hiring Request path and the configuration are audited.

**27. Deferred.** SLA timers, escalation, reminders, notification redesign,
approval-inbox redesign, recruiter assignment / workload / KPI, sourcing,
Marketplace matching, identity convergence — all M3 and later.

**28–29.** Commits `8d68ad6` · `9e8ebbf` · `d8ed2b8` · `715fb2c` and this report,
on `claude/testing-branch-setup-0gqe8n`. **Working tree clean** — mutation
testing runs against a copy outside the repository.

---

## PHASE 3 — M2 COMPLETE — HARD STOP — READY FOR M2 AUDIT
