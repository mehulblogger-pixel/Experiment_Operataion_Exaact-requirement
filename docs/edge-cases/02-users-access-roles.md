# Module 02 — Users / Access / Roles · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-26). Decision: **A + B** (observability AND the hard guards, on the
user's explicit go). Permission-change audit on the sealed `idems_log` chain, a full effective-access
review + toxic-combination detector, plus the "only a master mints/changes a master" and
last-master/self-lockout-on-edit guards. Asserted by `tests/test_module02_access.php`. Matrix
doc/UI reconciliation (§5-C) deferred. P0.

---

## 0. Headline: the gates are sound — but access changes are invisible and unaudited

The authorization model is clean and centralised:

- **One choke point** — `can($perm)` (`access.php:554-558`): licence check → **master short-circuit**
  (`is_superuser`) → the resolved permission set. Nothing gates outside it (a test,
  `test_no_dead_permission_gates`, proves every `can('…')` resolves to a catalogue permission).
- **Resolution order** (`user_effective_perms()` `access.php:442-454`): master ⇒ everything;
  else a non-empty per-user CSV `users.permissions` **replaces** the role default (the R3 model);
  else the role default (`role_perms()` → built-in base + optional `role_access` override).
- **Pointwise SoD already enforced:** voucher approve (maker ≠ checker ≠ claimant), IDEMS finalize
  (approver ≠ issuer), and `complaints.decide` / `capa.close` / `ncr.close` held apart from doing
  the work.
- **Real visibility exists:** Data Control §3 (`data_control.php:146-172`, `access_report()`
  `datacontrol.php:349`) shows, per active user, **8 sensitive powers**, last-login, dormant
  (>90d / never), and two-step; the user list shows last-seen; a stale-permission detector exists.

Against that solid base, the gaps are **observability** and a couple of **guards**:

- **No audit of permission / role / scope changes.** The app has a sealed, hash-chained audit
  (`idems_log`, viewable via `idems.audit.view`) — but the user-save handler
  (`ops.php:7052-7096`) logs only `PASSWORD_CHANGED`; the role-default editor (`ops.php:2516-2527`)
  logs nothing. **Who granted whom which permission, changed a role, or widened a scope — and when —
  is nowhere recorded.** This is the single highest-value gap.
- **No full effective-permissions view.** The only cross-user surface shows **8 powers**, not the
  complete resolved set; the full set is visible only one user at a time, on the edit form's
  pre-ticked boxes. An access review can't see the whole picture in one place.
- **No toxic-combination detector.** SoD is enforced *pointwise* at each action, but nothing flags
  a *user* who holds **both** sides of a maker/checker pair (e.g. can submit a voucher **and**
  approve it; can approve an IDEMS report **and** issue it), or who holds a self-grant power
  (`users.manage.global`).
- **Hard-control gaps (a separate, explicit decision — see §5-B):** a non-master global manager can
  set any user's role (including their **own**) to `MASTER_ADMIN` (`ops.php:6920-6922`) — no "only a
  master mints a master" guard; and the last-Master / self-lockout guard exists **only on
  deactivate** (`security.php:444`), not on the user-**edit** save, so the last master can be demoted
  by a role change and a user can strip their own access.

---

## 1. What is deliberately NOT in scope by default (program rules)

- **Adding or tightening any hard control** — the privilege-escalation guard and the
  last-master-on-edit guard would **change who can do what**, so per CLAUDE.md they are **not**
  built without an explicit go-ahead. They are offered as §5-B, flagged, with the exact behaviour
  spelled out — never bundled silently into the observability work.
- **Granting or removing any permission**, adding a role, or changing the resolution order. The
  observability work reads the *existing* resolved sets; it never writes to authorization.
- **Reconciling the doc/code matrix drift** (`docs/02-permission-matrix.md` anchors are stale) —
  worth doing, but a doc/UI task of its own (§5-C).
- **De-role-ifying `is_admin_level()` / `is_coordinator_level()`** (role-name checks in ~56 files) —
  a broad behavioural refactor, deliberately left alone.

---

## 2. Proposed additive layer (recommended = §5-A)

**Make every access change auditable, and the whole access picture visible — writing nothing to
authorization.**

1. **Permission-change audit** — in the two existing save handlers, diff the *resolved* access
   before/after and write it to the **already-sealed** `idems_log` chain:
   - Per-user save (`ops_users` role/permissions/scope, `ops.php:7052-7096`): on any change to
     `role`, `permissions` (effective set), `scope_offices`, `scope_sbus`, `reports_to_id`, or the
     `is_superuser`/active flag, log `idems_log('user', $id, 'ACCESS_CHANGED', ['old'=>…, 'new'=>…,
     'by'=>actor])` — recording **granted** and **revoked** permission keys, the role change, and the
     scope change, in plain terms.
   - Role-default save (`ops_access`, `ops.php:2516-2527`): log `ROLE_DEFAULTS_CHANGED` with the
     role and the diff of its default set.
   - Reuses the sealed chain, `idems.audit.view`, and the existing diff helpers. **No new permission**
     — the audit is viewable exactly where every other audited action already is.

2. **`access_effective_all()` + an "Effective access" review surface** — a read-only helper that
   returns, per active user, their **full resolved permission set** (via `user_effective_perms()`,
   already the source of truth) plus role, scope, master flag, last-login and dormancy. Rendered as
   an additive section on the existing Data Control / access-review screen (behind the same
   `data.credit`/master gate that screen already uses), so an admin can finally see the complete
   picture per user in one place — not just 8 powers.

3. **`access_toxic_combos()` — a toxic-combination detector (read-only, advisory):** over each
   user's effective set, flag the maker/checker pairs the code **already** treats as segregated:
   - **Voucher** — holds the ability to submit *and* approve (self-approval risk).
   - **IDEMS** — holds `idems.finalize`/approve *and* the issue capability (approver = issuer risk).
   - **Quality** — does the work *and* holds `complaints.decide` / `capa.close` / `ncr.close`.
   - **Self-grant** — holds `users.manage.global` (can grant themselves anything) alongside
     `settings.manage` / `data.salary`.
   Each is a **flag for a human access review**, never a block — it changes no gate, exactly like the
   equipment calibration-impact and attendance-reconciliation flags built in earlier modules. A
   master naturally trips every combo (they hold everything); the detector notes that master is
   expected, and highlights **non-master** users holding a toxic pair, which is the real signal.

Reuses: `idems_log` + `idems.audit.view`, `user_effective_perms()`, `all_permissions()`,
`access_report()`/Data Control, the known SoD pairs. **No new permission; no schema change; no gate
changed.**

---

## 3. Edge cases

1. **No effective change on save** (admin opens the edit form and saves with nothing changed) → **no**
   audit entry (diff empty); the log records real changes only, not every save.
2. **Master edited** → a change to a master's record still logs; but the detector notes a master is
   *expected* to hold everything, so master rows are labelled "master — holds all by design", not
   listed as anomalies.
3. **Per-user override vs role default** → the audit diffs the **resolved** set (what
   `user_effective_perms` returns), so switching a user from "follows role" to a custom set logs the
   actual granted/revoked keys, not the raw CSV, which is what a reviewer needs.
4. **Role-default change affects many users** → the `ROLE_DEFAULTS_CHANGED` entry records the role +
   set diff once; it does not fabricate a per-user entry for everyone on that role (they inherit).
5. **Scope widened (OWN → ALL, or office added)** → logged as a scope change (the quiet
   privilege-increase that today leaves no trace).
6. **Deactivation / reactivation** → already partly logged (`USER_REMOVED`); the access audit adds
   the active-flag flip to the same trail for completeness, without duplicating the retire-path log.
7. **Toxic combo on a legitimately dual-hatted small office** → flagged, **not** blocked; the review
   surface explains it's advisory (a two-person office may *have* to double-hat). The flag exists so
   the risk is a conscious, recorded decision — never an enforced denial.
8. **A user with an empty effective set** (locked out via R3 CSV) → surfaced as "no effective
   permissions" so the lock-out foot-gun is visible in the review, complementing the existing
   pre-tick/reset mitigations.
9. **Performance** → the effective-access table is one pass over active users, each resolved via the
   existing cached `user_effective_perms`; the toxic-combo check is set membership, not a query storm.
10. **Confidentiality** → the review surface is behind the same gate as Data Control today; it exposes
    no secret (permissions are not secrets to an access reviewer) and no password/2FA material.
11. **Audit immutability** → entries go through the existing hash-sealed chain; the module adds
    entries, never edits or deletes, preserving tamper-evidence.

---

## 4. Guardrails (must stay green)

- `can()` and the resolution order (master → per-user → role → deny), `ua()`, `user_effective_perms`,
  `assignable_permissions`, the R3 preserve-unseen-perms and reset-to-default behaviour, the
  branch-vs-global assignable subset, and every existing SoD gate — **all unchanged**. The module
  only **reads** the resolved sets and **appends** audit entries.
- `test_perms_no_lockout`, `test_no_dead_permission_gates`, `test_role_scoping`,
  `test_call_allocation_scope`, `test_qap_scope`, `test_approver_roles`, `test_tosrm_authz` —
  untouched.
- No permission granted or revoked; no role added; no gate tightened; no schema change.

---

## 5. OPEN DECISION — observability now; the hard guards are a separate, explicit choice

- **(A) Access observability (recommended, P0):** the permission-change **audit** (role/permission/
  scope changes to the sealed `idems_log` chain), the full **effective-access** review surface, and
  the advisory **toxic-combination** detector. Purely observational — grants nobody anything, changes
  no gate, adds no schema. This is the safe, high-value first move.
- **(B) ALSO add the hard guards (needs your explicit go — these change who-can-do-what):**
  1. **"Only a master mints or demotes a master"** — a non-master user-manager can no longer set a
     target's role to (or away from) `MASTER_ADMIN` on the edit form (closes the privilege-escalation
     hole at `ops.php:6920-6922`).
  2. **Last-master / self-lockout guard on the edit save** — the same protection that exists on
     deactivate, extended to role/permission edits: the last active master cannot be demoted, and a
     user cannot strip their own `users.manage.*`.
  Both are genuine security fixes but are **new hard controls**, so per the program's rules I will
  build them only if you say so.
- **(C) Reconcile the doc/code matrix + render the full role×permission matrix in the UI** — fix the
  stale anchors in `docs/02-permission-matrix.md` and add an admin screen showing the whole matrix.
  Larger doc/UI work; deferred.

Default if you don't specify: **(A)**. I recommend you also approve **(B)** given the
privilege-escalation hole, but I will not touch a hard control without your word.

---

## 6. Tests

1. Audit: changing a user's role/permissions/scope writes an `ACCESS_CHANGED` entry naming the
   granted/revoked keys and the actor; a no-op save writes nothing; a role-default change writes
   `ROLE_DEFAULTS_CHANGED`.
2. `access_effective_all()`: returns each active user's full resolved permission set (matching
   `user_effective_perms`), role, scope, master flag, dormancy.
3. `access_toxic_combos()`: flags a **non-master** user holding voucher submit+approve, or IDEMS
   approve+issue, or `users.manage.global`+`data.salary`; does **not** list a master as an anomaly;
   flags nothing for a clean single-hatted user.
4. Preservation: `can()`, the resolution order, the SoD gates, and the R3 model are unchanged; no
   permission is granted or revoked by the module; no new permission constant is introduced.
