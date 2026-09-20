# PHASE 7 — ENTRY AUDIT

## UX Consolidation + Final E2E · current-state audit before any implementation

**Baseline:** repository HEAD `dc8d3c0` · branch `claude/testing-branch-setup-0gqe8n`
**Date:** 2026-09-20
**Nature:** AUDIT ONLY. No production code, schema, migration or test was changed.

---

## 1 · Executive summary

The application is **larger and more complete than it is navigable**. Nearly
every workflow Phase 7 cares about already exists and works; what is missing is
not capability but **continuity** — the joins between finished parts.

Five findings carry most of the weight:

1. **The product has at least five competing answers to "where do I start?"**
   `/` (Dashboard), `/owner`, `/advisor`, `/flow-gaps` and `/recruitment-cc` all
   exist in the left rail, plus `/command-centre` and `/my-work` behind it. Each
   is individually reasonable. Together they mean no user has one home.
2. **The Recruitment → Workforce handoff is a tick-box that is hidden by
   default.** A hired candidate becomes a team member only if somebody ticks
   *"On Accept, also add this person to Inspectors"*. Nothing requires it,
   nothing reports its absence.
3. **A requisition closes on `filled`, not `joined`.** The engine counts both
   separately and correctly, then derives status from `filled` — so a
   requirement can reach "filled" with no workforce record behind it.
4. **There is no repeatable UX regression net.** Of 510 test files, **31** render
   a screen and **zero** drive a real browser or real HTTP. Every browser and
   HTTP verification in this programme has been ad-hoc. A UX consolidation phase
   with no UX test is a phase whose work cannot be defended later.
5. **ADR-001 has been open since Phase 2 and was never decided.** Two supported
   ways into recruitment still exist — the governed path and the direct path.
   This is a *business* decision that Phase 7 cannot take for the owner.

Nothing found here reopens a Phase 6 decision. The duplicate, identity and
organisation architecture behaves as locked.

**One thing the audit did *not* find:** no cross-tenant leak, no permission
bypass, no broken approval chain, and no evidence that any protected module is
damaged. The problems are consolidation problems, not integrity problems.

## 2 · Current architecture snapshot

| | |
|---|---|
| PHP libraries | **230 files · 117 738 lines** |
| Views | **401** (`ops` 315 · `portal` 33 · `pro` 21 · `vendor` 16 · `public` 1) |
| Staff routes (`ops_dispatch`) | **267** |
| Inline routes in `index.php` | 26 |
| Test files | **510** |
| Front doors | **five**, dispatched before the staff app |

The five doors, in `index.php` order: passport `p/…` (973) · client portal
`portal/…` (1001) · vendor portal `vendor/…` (1011) · professional `pro/…`
(1021) · public marketplace `connect` (1046) · then the staff application.
Each exits rather than falling through — a deliberate and sound boundary.

## 3 · Module inventory

| Module | Entry | Screens (indicative) | Engine |
|---|---|---|---|
| Administration / Setup | `/admin` | cockpit home/profile/modules/forms, lookups, users | `lib/setup.php`, `lib/lookups.php` |
| Organisation | `/directory` | partner list/detail/form, dedupe, access requests | `business_partners`, `lib/ops.php`, `lib/dedupe.php` |
| Users / Access | `/admin` | users, roles, permissions | `lib/access.php`, `lib/licence.php` |
| Hiring Requests | `/hiring-requests` | list, detail | `lib/hiringreq.php` |
| Approvals | `/my-approvals`, `/recruit-approvals`, `/approval-rules`, `/approval-delegations` | inbox, matrix, delegation | `lib/recruit_approval.php` — **one engine** |
| Requisitions | `/requisitions`, `/requisition`, `/requisition-new` | list, detail, allocations, position link | `lib/recruit.php`, `lib/reqfulfil.php` |
| Candidates | `/candidates`, `/candidate` | detail, flow, stage, interview, doc, letter, offer | `lib/recruitpipe.php` — **one pipeline** |
| Candidate sources | `/requisition-allocations` | allocation panel | `requisition_allocations` (Phase 4) |
| Marketplace | `/marketplace`, `/connect-*` | requirements, talent, orgs, bench, analytics | `lib/connect_market.php`, `cx_requirements` |
| Workforce | `/inspectors` | inspector list/profile/form | `inspectors` — the technical spine |
| Operations | `/operations` | calls, jobs, vouchers, reconcile | `lib/ops.php` |
| Reporting | `/reporting` | reports, exports | `lib/idems.php`, `lib/tapi.php` |
| Money | `/money` | invoices, billing | `lib/booksui.php` |
| Dashboards | `/`, `/owner`, `/advisor`, `/flow-gaps`, `/command-centre`, `/recruitment-cc` | see §11 | `rkpi_` (25 fns), `tapi_` (93), `adv_` (20) |
| Quality | `/quality` | audits, CAPA, credentials | `lib/audits.php`, `lib/capa.php` |
| Portal / external | `/portal`, `/vendor`, `/pro` | 70 views across three doors | `lib/portal.php`, `lib/cvp.php`, `lib/connect_pro.php` |
| Notifications / SLA | inside approvals | reminders, escalation | `lib/recruit_approval.php` — **one engine** |
| Identity / Connect | `/connect-identity`-family | link, unlink, findings | `lib/connect_identity.php` |

**Engine singularity confirmed** (locked principles 15–21): one approval engine,
one SLA/notification engine inside it, one configurable pipeline, one recruitment
KPI family (`rkpi_`). `tapi_` is the *analytics* family and predates it; they are
not duplicates but they are also not obviously distinguished to a user (F-11).

## 4 · Navigation audit

The left rail (`views/layout_top.php`) renders **20 items**. In order:

`/` Dashboard · `/owner` Owner home · `/search` Search records ·
`/flow-gaps` Where the flow is broken · `/advisor` What to fix ·
`/my-jobs` · `/documents` · `/document-new` · `/endorsements` · `/vouchers` ·
`/sales` · `/marketplace` · `/operations` · `/recruitment-cc` Recruitment ·
`/quality` · `/reporting` · `/money` · `/insights` · `/directory` · `/admin`

**Findings.**

- **Five of the first five items are all "start here" screens** (F-01).
- **Gating is inconsistent** (F-02). Sales, Marketplace, Quality, Reporting,
  Money, Insights, Directory and Admin use `ops_area_has('x')`. Operations uses
  a hand-rolled condition of four `can()` checks plus `licence_enabled()` plus a
  capability check (line 144). Recruitment uses `can('mod.hiring.view')` plus a
  capability check (line 155). Three different patterns for the same question.
- **267 staff routes, 106 nav tiles, 20 rail items.** The audit did not
  enumerate every orphan, but the arithmetic alone establishes that a large
  number of routes are reachable only contextually or not at all (F-03).
- **Terminology engine exists and is half-adopted**: `lib/terms.php` provides
  `T()`, `Tl()`, `Tlp()`, `TP()`, `T_NEW()`, and **76 of 273** `views/ops` files
  use it (F-09).

## 5 · Recruitment end-to-end audit

Traced through code, not assumed.

| Step | Where | Route | Record | Result |
|---|---|---|---|---|
| Hiring request raised | Hiring requests | `/hiring-request` | `hiring_requests` | OK |
| Approval | Approver inbox | `/my-approvals` | `recruit_approval_requests` | OK — one engine, matrix + delegation + SLA |
| Requisition raised from request | Request detail | `hreq_to_requisition()` (`hiringreq.php:1047`) | `requisitions` | OK — refuses unless `hreq_is_executable()`; redirects to `/requisition?id=` |
| **Requisition raised directly** | Requisitions | `/requisition-new` | `requisitions`, `hiring_request_id = NULL` | **ADR-001 open** (F-04) |
| Sources allocated | Requisition | `/requisition-allocations` | `requisition_allocations` | OK (Phase 4) |
| Candidate added | Candidates | `/candidates` | `candidates` | OK |
| Pipeline movement | Candidate | `/candidate-flow`, `/candidate-stage` | `candidates.stage` | OK — configurable |
| Interview / scorecard | Candidate | `/candidate-interview` | interviews | OK |
| Offer / letter | Candidate | `/candidate-offer`, `/candidate-letter` | offers, documents | OK |
| **Accept → workforce** | Candidate detail | stage move + **checkbox** `make_inspector` | `inspectors` via `rcv_convert()` | **F-05 — optional and hidden** |
| Requisition fill | derived | `reqf_sync()` | `requisitions.status` | **F-06 — derived from `filled`, not `joined`** |
| Reporting | Recruitment CC | `/recruitment-cc` | `rkpi_` | OK |

### F-05, in detail

`views/ops/candidate_detail.php:379`:

```html
<label class="chk" id="hire_chk" style="margin:8px 2px;display:none">
  <input type="checkbox" name="make_inspector" id="mk_insp" value="1">
  On <strong>Accept</strong>, also add this person to Inspectors</label>
```

`lib/ops.php:5775` gates the whole conversion on it:

```php
if ($to === 'ACCEPTED' && !empty($_POST['make_inspector']) && empty($cand['inspector_id'])
    && function_exists('rcv_convert')) {
```

The control is `display:none` until JS reveals it. If it is not ticked, the
candidate is ACCEPTED and **no workforce record is created**. The conversion
itself is sound — Phase 6 Batch 2 made it atomic, branch-correct and
ledger-writing — but whether it happens at all is a checkbox.

### F-06, in detail

`lib/reqfulfil.php:107–110` counts both, correctly and separately:

```php
$out['filled'] = … WHERE requisition_id=? AND stage IN (FILLED_STAGES)
$out['joined'] = … WHERE requisition_id=? AND stage IN (FILLED_STAGES) AND inspector_id IS NOT NULL
```

`$out['remaining'] = requested − filled − cancelled` (line 120), and
`reqf_derive_status()` drives `requisitions.status` from those counts. **`joined`
is computed and then not used for status.** A requisition therefore reaches
"filled" on acceptances alone.

### F-07 — nothing reports the gap

`identity_state_findings()` (`lib/connect_identity.php:690+`) reports
`CANDIDATE_INSPECTOR_MISSING`, `USER_INSPECTOR_MISSING` and
`CONVERTED_NO_LEDGER`. Grep for `ACCEPTED` in that file returns **0**. A hired
candidate that never became a team member is invisible to the very dashboard
built to surface exactly this class of problem.

## 6 · Marketplace end-to-end audit

Marketplace uses `cx_requirements` exclusively in `lib/connect_market.php` (24
references, zero references to `requisitions`). The separation locked as
principle 7 holds in code.

`/connect-talent` (marketplace talent search) and `/candidates` (recruitment
list) are genuinely different objects over different tables. The audit found no
place where the UI implies a Professional and a Candidate are one record.

**Not deeply audited:** allocation → fulfilment → workforce handoff inside
Marketplace was traced structurally but not exercised end-to-end in a browser.
Recorded as a coverage gap rather than a finding.

## 7 · Organisation & identity UX audit

The architecture is correctly separate (`business_partners`, `cx_organisations`,
`agencies`, `inspectors`, `candidates`, `cx_professionals`, `client_users`,
`vendor_users`). The question §9 asks is whether a business user can understand
the relationships.

- **Six doors create an organisation** — `connect_org.php`, `crm.php`, `db.php`,
  `leads.php`, `ops.php`, `partnerimport.php`. Phase 6 Batch 3 wired the
  duplicate detector into every one of them. **Classified B (legitimately
  different), not a duplicate workflow.**
- **Four ways to find a person** — `/candidates`, `/candidate-pool`,
  `/connect-talent`, `/search`. Different scopes over different tables.
  **Classified C (same purpose, different UX)** — see F-10.
- **Merged organisations** now carry `merged_into_id` and resolve to the
  survivor (Phase 6 Batch 3). The audit did not find UI that presents a retired
  organisation as live.
- **Access requests** have a working staff queue at `/access-requests` with a
  count tile, and the screen states plainly that nothing on it grants access.

## 8 · Public registration / access request audit

Behaviour is as locked in Phase 6 Batch 3 and was re-verified during that batch:
one neutral answer for every outcome, byte-identical responses, access requests
raised for a human, approval granting nothing by itself. **No Phase 7 change is
required or advised here**, and the security mechanism must not be altered for
UX reasons.

One carried limitation remains relevant to Phase 7 UX: **the access-request
queue has no notification** (F-12). Staff see a count only if they visit Admin.

## 9 · Roles & permissions audit

`can()` / `licence_enabled()` / `ops_area_has()` / capability checks are the
established mechanisms, and Phase 1 M5–M14 closed the enforcement gaps with
evidence. The audit found **no route where the backend fails to enforce**.

What it did find is **presentation inconsistency** (F-02): the same question
— "may this user see this area?" — is asked three different ways in the left
rail. That is a maintenance and correctness risk, not a current breach.

**Not audited exhaustively:** a full role × workflow × screen matrix was not
built. Doing so properly requires driving each role through the UI, which needs
the browser harness Phase 7 does not yet have (F-08).

## 10 · Tenant & branch scope audit

Tenant isolation is **structural** — one database per tenant. Phase 6 evidence
(CH6/CH7) confirms every access request and every survivor pointer names a
record in the current workspace. Branch scope is enforced (Phase 1 M13, Phase 6
Batch 1 I16). No user-facing scope leak was found.

## 11 · Dashboard & reporting audit

| Screen | Purpose |
|---|---|
| `/` | general dashboard |
| `/owner` | owner home |
| `/advisor` | "What to fix" — action list with money attached |
| `/flow-gaps` | "Where the flow is broken" — skipped hand-offs |
| `/command-centre` | management state-of-the-business |
| `/recruitment-cc` | recruitment command centre |
| `/insights` | analytics area |
| `/my-work` | personal queue |

Each has a defensible reason to exist. **No single one is canonical** (F-01).
KPI supply is `rkpi_` (25 functions, Phase 5, authoritative for recruitment),
`tapi_` (93, analytics), `adv_` (20, advisor). These are not duplicate engines —
but a user cannot tell which screen to trust for a given number (F-11).

**The audit did not verify KPI correctness** and must not: §13 forbids changing
KPI logic, and Phase 5 already established the recruitment numbers.

## 12 · Cross-module handoff audit

| Handoff | Trigger | Automatic? | Clear to user? | Finding |
|---|---|---|---|---|
| Recruitment → Workforce | checkbox on Accept | **No** | **No** | **F-05** |
| Recruitment → Requisition fill | derived from stage | Yes | Partly | **F-06** |
| Hiring request → Requisition | button on request | Manual, guarded | Yes | OK |
| Approval → Execution | status gate | Yes | Yes | OK — `hreq_is_executable()` |
| Candidate → Identity ledger | inside `rcv_convert()` | Yes | n/a | OK (Batch 2) |
| Organisation → Portal | invitation | Manual | Yes | OK |
| Access request → Access | **deliberately none** | No | Yes | Locked by Q26 |
| Marketplace → Workforce | allocation | Not traced end-to-end | — | coverage gap |
| Recruitment → Reporting | `rkpi_` | Yes | Partly | F-11 |

## 13 · Duplicate screen / workflow audit

| Item | Classification | Note |
|---|---|---|
| Six organisation-creation doors | **B — legitimately different** | all share one detector since Batch 3 |
| `/candidates` vs `/candidate-pool` vs `/connect-talent` vs `/search` | **C — same purpose, different UX** | different scopes; no consolidation implied without owner input |
| `/` vs `/owner` vs `/advisor` vs `/flow-gaps` vs `/command-centre` | **E — unclear, owner decision** | all reachable, all reasonable, none canonical |
| Path A vs Path B into recruitment | **E — owner decision** | ADR-001, open since Phase 2 |
| `rkpi_` vs `tapi_` | **B — legitimately different** | recruitment KPI vs analytics |

**No true duplicate (A) was found.** Nothing should be deleted on this audit's
evidence.

## 14 · Form & field UX audit

Not audited field-by-field; that requires the browser harness. Two structural
observations stand on code evidence:

- **87 separate status/stage/state vocabularies** are declared as constants
  across `lib/*.php` (F-13). Many are legitimately distinct domains. The number
  is recorded because label consistency is a Phase 7 objective and this is its
  scale.
- **Terminology is half-centralised** (F-09): 76 of 273 `views/ops` files use
  the `terms.php` engine; the rest hard-code labels.

## 15 · Status & action audit

`STATUSES` for organisations is `ACTIVE, INACTIVE, ON_HOLD, BLACKLISTED,
PROSPECT` plus `MERGED` (code-written only) — established and locked in Batch 3.
`BLACKLISTED` still enforces nothing; the enforced control is `hold_status`
(F-14, carried from Batch 3 as an accepted limitation).

Recruitment stages are configurable per workspace (`lib/recruitpipe.php`), which
is correct, and means status labels are already a tenant-level concern rather
than a code one.

## 16 · Mobile / responsive audit

`assets/css/app.css` carries **29 `@media` rules** and the viewport meta is
present, so there is a responsive foundation.

**192 of 315 `views/ops` files contain a raw `<table>` with no horizontal-scroll
wrapper**; only 2 views use `table-wrap` (F-15). Affected inspector-facing
screens include `inspector_list.php`, `inspector_profile.php`, `job_detail.php`,
`job_form.php`, `attendance_recon.php`.

This matters specifically because `CLAUDE.md` records that **inspectors are
phone-first in the field**. A register that overflows horizontally on a phone is
a field-usability problem, not a cosmetic one.

## 17 · Existing E2E test coverage

| | |
|---|---|
| Test files | **510** |
| Files that render a screen (output buffer) | **31** |
| Files driving a real browser | **0** |
| Files driving real HTTP | **0** |

Engine and invariant coverage is **excellent** — 12 834 (SQLite) / 12 839
(MariaDB) assertions, zero failures, mutation-tested. Screen coverage is thin
and **UX coverage is absent** (F-08).

Every browser and HTTP verification performed in Phases 6 was ad-hoc and lives
in a scratchpad, not in the repository. It cannot be re-run by anyone else.

## 18 · Protected regression areas

Confirmed green on the current HEAD (from the Batch 3 corrective final run):
Operations · Reporting · Money · Workforce · Marketplace · Recruitment ·
Entitlement · Tenant isolation · Identity safety · Organisation safety ·
Phase 4 · Phase 5 · Batch 1 · Batch 2 · Batch 3.
**510 test files, 0 failures, both engines.**

## 19 · Findings register

| ID | Area | Finding | Evidence | Sev | Business impact | Existing engine | Solution | Phase 7? |
|----|------|---------|----------|-----|-----------------|-----------------|----------|----------|
| **F-01** | Navigation | Five competing "start here" screens | `layout_top.php` rail items 1–5; `/command-centre`, `/my-work` behind | **P2** | New users do not know where to begin; training burden; each screen gets partial adoption so none is trusted | existing dashboards | **CONNECT** (choose one canonical, link the rest) | **MUST FIX** |
| **F-02** | Navigation | Three different gating patterns for the same question | `layout_top.php:122,144,155` | **P3** | A future module added the wrong way is shown to users who did not buy it | `ops_area_has()` | **REUSE** | SHOULD FIX |
| **F-03** | Navigation | 267 routes vs 20 rail items vs 106 tiles — unquantified orphans | route/tile counts | **P3** | Features exist that nobody can find; support burden | — | **MAP** (inventory first) | SHOULD FIX |
| **F-04** | Recruitment | Two supported ways into recruitment; ADR-001 open since Phase 2 | `docs/adr/ADR-001`, `/requisition-new` live | **P2** | A customer who adopts approval governance can bypass it; approvals become advisory | `hiringreq` | **OWNER DECISION** | **DECISION** |
| **F-05** | Recruitment → Workforce | Hire→team-member is a hidden checkbox | `candidate_detail.php:379` (`display:none`), `ops.php:5775` | **P1** | A hired person exists in Recruitment but not in Workforce: cannot be deployed, rostered, paid or reported; discovered weeks later | `rcv_convert()` | **CONNECT** | **MUST FIX** |
| **F-06** | Requisition | Status derived from `filled`, not `joined` | `reqfulfil.php:107–110,120` | **P1** | A requirement closes with no workforce record behind it; headcount reporting overstates delivery | `reqfulfil` | **EXTEND** | **MUST FIX** |
| **F-07** | Identity findings | No finding for "accepted but never converted" | `connect_identity.php`, 0 hits for `ACCEPTED` | **P2** | The gap in F-05 is invisible to the dashboard built to surface exactly this | `identity_state_findings()` | **EXTEND** | **MUST FIX** |
| **F-08** | Testing | Zero real-browser and zero real-HTTP tests in the suite | 0 of 510 | **P1** | A UX phase with no UX test cannot prove it did not break anything; every future UX change re-opens the same argument | test harness | **BUILD** (justified: nothing exists) | **MUST FIX** |
| **F-09** | Terminology | Terminology engine used by 76 of 273 ops views | `lib/terms.php` | **P3** | A tenant renames "requisition" and two thirds of screens ignore it | `terms.php` | **REUSE** | SHOULD FIX |
| **F-10** | Search | Four person-finding mechanisms | `/candidates`, `/candidate-pool`, `/connect-talent`, `/search` | **P3** | A recruiter is unsure which list is authoritative | existing | **MAP** | SHOULD FIX |
| **F-11** | Dashboards | Three KPI families, no stated authority per screen | `rkpi_` 25, `tapi_` 93, `adv_` 20 | **P2** | Two screens show two numbers for one question and nobody knows which to quote | `rkpi_` (Phase 5) | **MAP** (label the source) | SHOULD FIX |
| **F-12** | Access requests | Queue has no notification | Batch 3 limitation 4 | **P2** | A genuine customer waits because nobody knew a request arrived | portal/queue | **EXTEND** | SHOULD FIX |
| **F-13** | Status | 87 status/stage vocabularies | `grep const *_STATUS/_STAGES/_STATES` | **P3** | Inconsistent wording across screens; training burden | lookups/terms | **MAP** | NICE TO HAVE |
| **F-14** | Organisation | `BLACKLISTED` enforces nothing | Batch 3 status-semantics audit | **P2** | Staff believe a barred company is barred; it is a red badge only | `hold_status` | **OWNER DECISION** | **DECISION** |
| **F-15** | Mobile | 192 of 315 ops views have unwrapped tables | 2 use `table-wrap` | **P2** | Inspectors are phone-first; registers overflow horizontally in the field | `app.css` | **EXTEND** | **MUST FIX** (inspector screens only) |

## 20 · Proposed Phase 7 scope

### A · MUST FIX

| ID | Problem | Proposed outcome | Reuse | Benefit | Regression risk |
|---|---|---|---|---|---|
| F-05 | Hire→workforce is a hidden tick-box | The handoff is explicit and its absence impossible to miss | `rcv_convert()` | A hired person always exists where operations look for them | **Medium** — touches the accept path; Batch 2 invariants must hold |
| F-06 | Requisition closes on `filled` | Status distinguishes "accepted" from "joined" | `reqf_counts()` already computes both | Headcount reporting matches reality | **Medium** — `reqf_derive_status()` is load-bearing |
| F-07 | The gap is unreported | A finding for accepted-not-converted | `identity_state_findings()` | The dashboard shows the problem before payroll does | **Low** — detection only |
| F-08 | No UX regression net | A small, repeatable browser suite in the repo | Playwright already available | Phase 7's own work becomes defensible | **Low** — additive |
| F-01 | Five "start here" screens | One canonical home; the others reachable and clearly subordinate | existing dashboards | A new user has one place to begin | **Medium** — navigation is high-visibility |
| F-15 | Unwrapped tables on phone-first screens | Inspector-facing registers usable on a phone | `table-wrap`, `app.css` | Field staff stop pinch-scrolling | **Low** — presentational |

### B · SHOULD FIX
F-02 · F-03 · F-09 · F-10 · F-11 · F-12

### C · NICE TO HAVE
F-13

### D · OUT OF SCOPE
- The live-host workspace incident — **separate infrastructure/recovery issue,
  outside Phase 7 scope**.
- KPI calculation changes (Phase 5 is locked).
- Any change to the Phase 6 public-registration security mechanism.
- Identity convergence, a Person hub, Q1–Q18, R20, R21, R23.

### E · OWNER DECISION REQUIRED
F-04 (ADR-001) · F-14 (BLACKLISTED)

## 21 · Owner decisions required

**DECISION Q29 — the direct requisition path (ADR-001, open since Phase 2)**
*Current situation:* two supported routes into recruitment. Path A
(request → approval → requisition) and Path B (`/requisition-new`, already OPEN,
`hiring_request_id = NULL`). Path B pre-dates the request layer and is how every
existing requisition was created.
*Options:* (a) both always open; (b) Path B disabled per customer by the
platform; (c) Path B behind a workspace setting; (d) Path B removed.
*Recommendation:* **(c)** — a workspace setting, default ON. It breaks nobody,
gives a governance-minded customer a real control, and needs no per-customer
support intervention.
*Impact:* without it, approvals are advisory for any user who knows the other
route.
*If deferred:* the ambiguity persists into Phase 7's navigation work, because
the nav must decide which path to present as primary.

**DECISION Q30 — should `BLACKLISTED` block anything?**
*Current situation:* a red badge that enforces nothing; `hold_status`
(`''`/`HOLD`/`BLOCKED`) is the enforced control.
*Options:* (a) leave as display-only and rename it to something honest;
(b) make it set `hold_status=BLOCKED`; (c) remove it from the status list.
*Recommendation:* **(a)** for Phase 7 — renaming is a UX change; changing what
it enforces is a business-rules change that deserves its own gate.
*Impact:* staff currently believe a barred company is barred.
*If deferred:* the belief persists; someone eventually trades with a company
they thought was blocked.

**DECISION Q31 — which dashboard is canonical?**
*Current situation:* `/`, `/owner`, `/advisor`, `/flow-gaps`, `/command-centre`,
`/recruitment-cc`, `/my-work` all exist and are all reasonable.
*Options:* (a) `/` becomes the home and others become named views within it;
(b) role-based home (the role-workspace engine already exists);
(c) leave as is and only fix labelling.
*Recommendation:* **(b)** — the configurable role-workspace engine is already
built, so this is REUSE rather than BUILD.
*Impact:* this is the single largest driver of "I don't know where to start".
*If deferred:* F-01 cannot be closed, and it is the headline UX complaint.

## 22 · Out-of-scope items

As §20 D. The live-host incident is recorded here once, for completeness:
**separate infrastructure/recovery issue — outside Phase 7 scope.** No
application change is proposed, implied or required for it.

## 23 · Technical debt identified

- `portal_migrate()` and `indexes_migrate()` set their epoch marker **before**
  doing their work, so a mid-way failure is never retried within the epoch.
  Not a Phase 7 item; recorded because it is the same family as the static-marker
  traps found in Batch 3.
- 87 status vocabularies (F-13).
- Terminology half-adoption (F-09).
- `views/ops` at 315 files with no shared table component (F-15's root cause).

## 24 · Risks

| Risk | Mitigation |
|---|---|
| Navigation work is high-visibility and easy to get wrong | Do F-08 (browser tests) **first**, so the rest is defensible |
| F-05/F-06 touch the accept path, which Batch 2 and M3 both hardened | Treat Batch 2 invariants and `reqf_*` counts as protected; test-first |
| "UX consolidation" invites rebuilding | Locked principles 25–26: reuse/extend/configure; never rebuild a working dashboard |
| Scope creep into Phase 6 territory | Q26/Q27 mechanisms are locked; no UX reason justifies changing them |

## 25 · Recommended implementation sequence

1. **F-08 first.** Build the small repeatable browser suite before changing any
   screen. Everything after this is then provable.
2. **F-06, then F-05, then F-07.** Fix the counting, then the handoff, then the
   detection — in that order, because the detector should be able to prove the
   handoff works.
3. **Q29 / Q31 decisions**, then **F-01** navigation consolidation.
4. **F-15** inspector-facing tables.
5. **B-list** (F-02, F-03, F-09, F-10, F-11, F-12) as capacity allows.

## 26 · Evidence appendix

| Claim | Evidence |
|---|---|
| 230 libs / 117 738 lines / 401 views / 510 tests / 267 routes | direct count at HEAD `dc8d3c0` |
| Five front doors | `index.php:973, 1001, 1011, 1021, 1046` |
| 20 rail items, three gating patterns | `views/layout_top.php:84–179` |
| Hidden hire checkbox | `views/ops/candidate_detail.php:379` |
| Conversion gated on it | `lib/ops.php:5775` |
| `filled` vs `joined` | `lib/reqfulfil.php:107–110`, `:120` |
| No ACCEPTED finding | `grep -c ACCEPTED lib/connect_identity.php` → 0 |
| ADR-001 open | `docs/adr/ADR-001-direct-requisition-path.md:3` |
| `/requisition-new` live | `lib/ops.php:3200`, `:5390` |
| 0 browser / 0 HTTP tests | `grep -rl playwright\|chromium tests/` → 0; `grep -rl curl_init tests/` → 0 |
| 192 unwrapped tables | per-file scan of `views/ops/*.php` |
| 87 status vocabularies | `grep -c "^const .*_(STATUS\|STAGES\|STATES) = " lib/*.php` |
| Terminology 76/273 | per-file scan |
| Marketplace separation | `lib/connect_market.php` — 24 × `cx_requirements`, 0 × `requisitions` |

### What this audit did NOT inspect

Stated so the report is not read as more complete than it is:

- a full role × workflow × screen permission matrix (needs the browser harness);
- Marketplace allocation → fulfilment → workforce, end-to-end in a browser;
- form-by-form field review;
- portal/vendor/pro door UX in depth (70 views);
- KPI numerical correctness (deliberately — §13 forbids it).

Each is a coverage gap, not a finding. None is claimed as working or broken.

---

**PHASE 7 ENTRY AUDIT — READY FOR OWNER REVIEW**
