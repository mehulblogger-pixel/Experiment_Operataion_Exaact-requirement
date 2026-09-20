# PHASE 7 — REVENUE READINESS AUDIT

**Question:** can EXAACT accept a real paying customer for the defined initial
product scope?

**Baseline:** `3f5bb75` · **Date:** 2026-09-20
**Nature:** audit and programme control. **No production code, schema or
migration was changed.** Evidence was gathered by code inspection and by running
existing tests and read-only probes.

---

## 1 · Method and honesty statement

This audit does **not** repeat the Phase 7 entry audit. It uses its findings as
the baseline and asks one narrower question: *what would a paying customer hit?*

**What was verified in this action:** R20 inspector duplicate integrity (probe) ·
F-05 hire→workforce trace (code) · F-06 requisition semantics (code) ·
Q32 workforce/inspector model (code) · tenant isolation (184 assertions,
MariaDB) · deployment artefacts (read-only).

**What was NOT verified and is not claimed:** the TPIA operations chain
(call → schedule → inspection → report → QA → timesheet → expense → billing) was
inspected structurally but **not traced end-to-end in a browser** · Money and
Reporting journeys were not executed · no production deployment was performed.

Where this audit does not know, it says so. Nothing is marked PASS on inference.

## 2 · R20 — inspector duplicate integrity (§19)

**This was the item most likely to be a release blocker, so it was probed, not
reasoned about.**

`inspectors` has **no unique index, no generated key and no index at all** in
`lib/indexes.php`. Probe result (`scratchpad/p7/r20.php`, SQLite):

```
three inspector rows created for ONE person: 1, 2, 3
  identical name+email+emp_code accepted?  YES — no constraint
  duplicate emp_code accepted?             YES
  duplicate email accepted?                YES
  inspectors on the deployment picker        3
  an INSPECTOR_DUPLICATE finding exists?   *** NO ***
```

Three creation paths exist (`lib/ops.php` ×2, `lib/recruit.php` via
`rcv_convert()`), and the deployment picker is
`SELECT * FROM inspectors WHERE … ORDER BY name` (`ops.php:4521`) with no
de-duplication.

**What it corrupts.** `jobs` and `attendance` key on `inspector_id`, and
utilisation/attendance reporting reads through those joins. One human held as two
rows therefore splits: deployment choice, attendance, inspection history and
per-person utilisation.

**What it does NOT corrupt.** Invoice totals. Each job is still billed once; the
sum across the business is unaffected. This is **misleading per-person business
status**, not incorrect money.

**Classification.** Split, because the two halves have very different risk:

| | Classification | Reason |
|---|---|---|
| **R20-a — make duplicates visible** | 🔴 **RELEASE BLOCKER** | A TPIA business sells inspector utilisation. Shipping a product whose core number can silently be wrong, with nothing anywhere reporting it, is not credible. The fix is **EXTEND** `identity_state_findings()` — detection only, non-destructive |
| **R20-b — prevent duplicates** | 🟠 **UAT BLOCKER** | A constraint over existing dirty data is the riskier half and needs the Batch 3 guard pattern plus a reconciliation decision |

## 3 · F-05 — hire → workforce → inspector (§23)

The eight questions, answered from code:

| # | Question | Answer | Evidence |
|---|---|---|---|
| 1 | Can a candidate be ACCEPTED? | **Yes** | `recruitpipe`, configurable stages |
| 2 | Can they be considered hired? | **Yes** — ACCEPTED is a filled stage | `REQF_FILLED_STAGES` |
| 3 | Can they become Workforce? | **Only via `rcv_convert()`** | `lib/recruit.php:1273` |
| 4 | Can they become Inspector? | **That is the only conversion target** | `rcv_convert()` writes `inspectors` only |
| 5 | What if `make_inspector` is not selected? | **Nothing happens. The candidate is ACCEPTED with no workforce record, silently** | `ops.php:5775` gates on `!empty($_POST['make_inspector'])`; the control is `display:none` (`candidate_detail.php:379`) |
| 6 | Is that correct for non-technical roles? | **Unclear — this is Q32.** `back_office_staff` exists as a separate table but **no candidate path reaches it**. `inspectors.staff_kind` ∈ {ASSET, FREELANCER, SUBCON} is *engagement type*, not technical-vs-not | `ops.php:131`, `recruit.php:1332` |
| 7 | Is there an audit trail? | **Yes** — `rcv_log()`, refusal codes, and Batch 2 made it atomic and ledger-writing | `recruit.php:1273+` |
| 8 | Can it be reversed safely? | **Partly.** `candidate-unlink-pro` reverses the *identity link*. No reverse for the inspector row itself was found | `ops.php:6133` |

**Classification: 🔴 RELEASE BLOCKER.** A customer hires someone, the system
accepts it, and the person never appears in Workforce — so they cannot be
deployed, rostered or reported on. It is discovered later, by absence.
**Solution type: CONNECT** — the conversion engine already exists and is sound.

## 4 · F-06 — requisition status semantics (§22)

The data model already distinguishes four things, correctly:

| Field | Meaning | Source |
|---|---|---|
| `requested` | headcount approved | `reqf_requested()` |
| `filled` | candidates in a FILLED stage (**ACCEPTED**) | `reqfulfil.php:107` |
| `joined` | of those, the ones with an `inspector_id` | `reqfulfil.php:109` |
| `remaining` | `requested − filled − cancelled` | `reqfulfil.php:120` |

**`joined` is computed and then never used for status.** `reqf_derive_status()`
drives `requisitions.status` from `filled`. So a requisition reports
*"Hired (filled)"* when the business reality is *"candidates accepted, nobody has
joined"*.

**The minimum change, using the existing architecture:** `remaining` and the
derived status should distinguish accepted-not-joined from joined. Both numbers
already exist — **no new field and no new status value is required.**
**Solution type: EXTEND.** Do not implement until the semantics are confirmed —
this is part of Q32.

**Classification: 🔴 RELEASE BLOCKER** — "materially misleading business status"
is a listed blocker example, and this is the clearest instance of it.

## 5 · F-07 — can management see the pipeline? (§24)

| Stage | Visible today? |
|---|---|
| Selected / Offered / Offer accepted | **Yes** — configurable pipeline stages |
| Joining pending | **No distinct concept** — collapsed into ACCEPTED |
| Joined | **Computed (`joined`) but not surfaced** |
| Workforce created | **No** — and nothing reports its absence (§3) |
| Inspector created | same as Workforce (they are the same act today) |
| Deployment pending / Deployed | Operations side; not traced in this action |

**Minimum extension:** surface the `joined` count that already exists, and add an
accepted-not-converted finding. **Use `rkpi_` — do not build a second KPI
engine.** **Solution type: EXTEND.**

**Classification: 🟠 UAT BLOCKER** — management cannot see the gap, but the gap
itself is F-05/F-06, which are the blockers.

## 6 · Tenant isolation (§15)

**PASS.** Isolation is structural — one database per tenant — and was verified in
this action on MariaDB: `test_saas_tenants` **154/0**, `test_saas_isolation`
**30/0**. Phase 1 M13 closed three cross-tenant paths with evidence; Phase 6
CH6/CH7 confirm no pointer or request escapes the workspace.

No separate Tenant A/B harness was built, because one already exists and passes.

## 7 · Permissions (§16)

Backend enforcement is the established mechanism (`can()`, `licence_enabled()`,
`ops_area_has()`, capability checks) and Phase 1 M5–M14 closed the gaps with
evidence, including 7 IDORs and 3 cross-tenant paths.

**Not verified in this action:** a role × critical-journey matrix driven through
the UI. That needs the browser smoke layer (§9) and is recorded as a coverage
gap, not a finding.

**Known presentation inconsistency:** three different gating patterns in the left
rail (F-02) — 🟡 customer experience, not a breach.

## 8 · Customer journey status

| Journey | Status | Basis |
|---|---|---|
| A · Onboarding (registration → workspace) | **PASS with limitation** | Phase 6 Batch 3 verified over real HTTP and browser; no production proof |
| B · Recruitment (need → approval → requisition → candidate → offer) | **PASS** | engines locked, 25 test files |
| B · Recruitment (**→ joining**) | **FAIL** | F-05, F-06 |
| C · Workforce (joined → workforce → role → branch) | **FAIL** | F-05 (may never be created), R20 (may be duplicated) |
| D · Inspector (expertise → availability → assignment) | **NOT VERIFIED** | not traced in this action |
| E · TPIA operations (call → inspection → report → QA → timesheet → expense → billing) | **NOT VERIFIED** | structurally present, 24 Operations + 17 Money test files pass; **not traced end-to-end** |

## 9 · Real browser / HTTP smoke (§25)

**Current state: zero.** Of 510 test files, 31 render a screen, **0** drive a
browser and **0** drive HTTP. Every browser/HTTP check in Phases 6 was ad-hoc and
lives in a scratchpad.

**Required minimum** (login · onboarding · workspace · hiring request/requisition
· candidate · approval · hiring · workforce · inspector · operations · report ·
billing · dashboard, plus critical HTTP/security checks).
**Solution type: BUILD** — justified, because nothing exists. Keep it small.

**Classification: 🟠 UAT BLOCKER.** It does not stop a customer using the
product; it stops us proving we have not broken it.

## 10 · Production deployment readiness (§26) — read-only

| Item | State |
|---|---|
| Deployment SOP / checklist / install docs | **Present** |
| `deploy-check.php` (checksum verification) | **Present and maintained** |
| `diagnose.php` | Present |
| Secrets | `config.local.php` **is git-ignored** (`phpapp/.gitignore:3`) |
| Scheduler | `cron.php` present, documented for cPanel/systemd |
| File-backed tenant storage | Now placed in `exaact_data`, **a sibling above the web root**, with an in-app fallback (`tenant_migrate.php:48–51`) |
| MySQL/MariaDB production path | Supported and authoritative in testing |
| Backups | **Not verified** — hosting-side, outside the repository |
| **Production deployment** | **NEVER PERFORMED** |

**No destructive action was taken and none is proposed.** The known live-host
workspace incident is **a separate infrastructure/recovery issue, outside Phase 7
scope**.

## 11 · Findings and classification

| ID | Finding | Class | Solution | Engine |
|---|---|---|---|---|
| **RB-1** | F-05 — hire→workforce is a hidden checkbox; a hired person may never exist in Workforce | 🔴 **RELEASE BLOCKER** | CONNECT | `rcv_convert()` |
| **RB-2** | F-06 — requisition reports "filled" when nobody joined | 🔴 **RELEASE BLOCKER** | EXTEND | `reqf_counts()` |
| **RB-3** | R20-a — duplicate inspectors freely creatable and **undetected** | 🔴 **RELEASE BLOCKER** | EXTEND | `identity_state_findings()` |
| **UB-1** | R20-b — duplicate inspectors not *prevented* | 🟠 UAT | EXTEND | Batch 3 guard pattern |
| **UB-2** | F-07 — management cannot see joined vs accepted | 🟠 UAT | EXTEND | `rkpi_` |
| **UB-3** | No browser/HTTP smoke layer | 🟠 UAT | BUILD (justified) | — |
| **UB-4** | F-01 — five competing start screens | 🟠 UAT | CONNECT | role-workspace engine |
| **CX-1** | F-15 — 192 of 315 ops views have unwrapped tables; inspectors are phone-first | 🟡 CX | EXTEND | `app.css` |
| **CX-2** | F-12 — access-request queue has no notification | 🟡 CX | EXTEND | portal |
| **CX-3** | F-11 — three KPI families, no stated authority per screen | 🟡 CX | MAP | `rkpi_` |
| **CX-4** | F-02 — three nav gating patterns | 🟡 CX | REUSE | `ops_area_has()` |
| **D-1** | F-03, F-09, F-10, F-13 and the deferred backlog | 🟢 DEFERRED | — | — |

## 12 · R23 taxonomy (§20)

Inspected only against the customer-facing critical journey. Taxonomy
inconsistency does **not** prevent customer setup, recruitment, inspector
assignment or reporting. It can allow an unresolved free-text skill to become
canonical through marketplace search (I24).

**Documented and deferred** — `DEFERRED-BACKLOG.md` C-02. Not a revenue blocker.
No taxonomy governance project is proposed.

## 13 · Owner decisions

**Q29 — Requisition governance.** Two paths are live: Path A (request → approval
→ requisition) and Path B (`/requisition-new`, already OPEN, `hiring_request_id
= NULL`). ADR-001 has been open since Phase 2. *Options:* both open · Path B per
customer · Path B behind a workspace setting · Path B removed.
*Recommendation:* **workspace setting, default ON** — breaks nobody, gives a
governance customer a real control. *Deferred:* approvals stay advisory for
anyone who knows the other route. **Neither route is to be silently removed.**

**Q30 — `BLACKLISTED`.** Currently a red badge that enforces nothing; the
enforced control is `hold_status` (`''`/`HOLD`/`BLOCKED`). *Customer
expectation:* that a blacklisted company cannot be traded with. *Risk:*
reputational, low frequency. *Recommendation:* **mark as a controlled limitation
and rename the label honestly.** **Do not build a blacklist enforcement engine in
this action.** *Revenue blocker:* **No.**

**Q31 — Role-based home.** Seven "start here" screens exist. The configurable
role-workspace engine already exists. *Recommendation:* **REUSE it** — one
obvious start per role (Owner, Admin, Recruiter/HR, Operations, Inspector, QA,
Finance). **Do not build another dashboard.**

**Q32 — Workforce vs Inspector.** Current implementation: the only conversion
target is `inspectors`. `back_office_staff` exists but no candidate path reaches
it. `staff_kind` is engagement type, not technical-vs-not. So **today, every
converted hire becomes an Inspector.** *Question for the owner:* is that correct,
or must a non-technical hire become Workforce without becoming an Inspector?
**This decision gates the shape of the RB-1 and RB-2 fixes**, which is why they
are not implemented in this action.

## 14 · Verdict

Recorded in `REVENUE-READINESS-EXECUTIVE-SUMMARY.md`.
