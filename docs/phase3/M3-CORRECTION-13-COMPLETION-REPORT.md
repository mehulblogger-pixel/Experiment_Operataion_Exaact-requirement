# M3 CORRECTION #13 — COMPLETION REPORT

The twelve required points, in order.

### 1 · Exact production caller
Two, both in `lib/recruit_approval.php`: **`appr_audit_notify()`** (the decision
notifier, line 1714) and **`appr_audit_sla()`** (the SLA/reminder writer, line
287). Both build a condition key, ask `appr_condition_seen()`, resolve a subject,
and call `act_log(…, ['cond_key' => …])`. The status died one level below them,
at **`lib/activity.php:432`**.

### 2 · Previous ignored-result behaviour
`if ($id > 0 && $ck !== '') act_set_cond_key($id, $ck);   // status ignored`
The four-valued return was not assigned; `act_log()` returns only the row id; both
production sites returned `void`. Zero non-test readers existed for the status
constants, the error channels or the state reporter.

### 3 · New result-consumption path
`act_log()` records the marker's status in a **workspace-keyed slot** (the #11
epoch mechanism), reset on entry so no event inherits another's status, and
exposed through `act_last_cond_status()`. `act_log()`'s own contract is unchanged,
so the 74 callers that pass no `cond_key` are untouched. Both production sites
read it through `appr_cond_outcome()` and return one of five explicit outcomes —
never a boolean, because a truthy `'FAILED'` is the trap #11 named.

### 4 · Duplicate-suppression behaviour
Unchanged when the marker stores: the identical permanent condition is
suppressed, and ten identical refusals still leave one row. Verified through the
production caller, not the helper.

### 5 · Failure behaviour
The marker fails → the event is **still written** (S1) → it carries no marker →
suppression is **not armed** → the caller returns `APPR_COND_UNARMED` and the
reason is logged. The repeat is allowed and attributable; nothing claims the
marker was stored. Fail-open is the only choice inside the existing
architecture — failing closed would suppress on a marker that does not exist, and
a second store would be a second deduplication mechanism. Both are forbidden by
§3, and the reasoning is recorded in the code and in the AUDIT.

### 6 · SQLite focused results
**40 passed, 0 failed.**

### 7 · MariaDB focused results
**40 passed, 0 failed.** MariaDB remains authoritative; nothing is inferred from
SQLite.

### 8 · Mutation attempts / caught / survivors
**Attempted 6 · Caught 6 · Survived 0**, each named with its detecting assertion
in MUTATION-RESULTS. Two earlier runs are reported rather than hidden: one was
**void** (the baseline itself was fatal, so every "survivor" was arithmetic), and
one had a **genuine survivor** — `M-Y1-GLOBAL` — which exposed that the new
status slot's workspace scoping was untested. C13.7 closed it.

### 9 · Full regression
SQLite **10326 passed, 0 failed**. MariaDB **10327 passed, 0 failed**. No
regression in approval, notifications, audit, Operations, Recruitment,
Marketplace, Quality, Money or tenant isolation. No test weakened, deleted or
skipped.

### 10 · Files changed
- `phpapp/lib/activity.php` — the workspace-keyed status slot, its accessor, the
  reset on entry, and the consumption at the old loss point.
- `phpapp/lib/recruit_approval.php` — five caller outcomes, `appr_cond_outcome()`,
  and both production sites returning what they observed.
- `phpapp/tests/test_p3m3c13_consume.php` — 40 production-path assertions.
- `phpapp/deploy_check.php` — regenerated.
- `docs/phase3/M3-CORRECTION-13-{AUDIT,TEST-RESULTS,MUTATION-RESULTS,COMPLETION-REPORT}.md`.

No permission, role, status or lifecycle transition changed, so `docs/01-roles.md`,
`docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` are unchanged
and still agree with the code.

### 11 · Commit
Branch `claude/testing-branch-setup-0gqe8n`, commit titled
*M3 correction #13 — the caller must consume the persistence result*.

### 12 · Remaining M3 findings
Open and untouched by instruction: **H1**, **H3**, **S2**, **S3**, **U2**.

Open from the #12 adversarial audit and explicitly out of scope here, still open:
**A-2** colliding id stamps the wrong record; **A-3** `STORED` survives a
rollback; **A-4** engines disagree on an over-long key; **A-5** byte-truncation
mangles a non-ASCII key; **A-6** the loose-comparison mutation is unpinned;
**A-7** the column channel masks the write channel. A-7's practical severity has
changed: the diagnostic surface now has real readers, so a masked message is no
longer purely theoretical.

**M3 is NOT accepted. M4 is NOT started.**
