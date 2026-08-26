# EXAACT TPIA OS — Improvement Program Checklist

Living record of the non-destructive, module-by-module improvement program.
The full drill-down spec is in **`docs/improvement-program.docx`**; the per-module
edge-case analyses live in **`docs/edge-cases/`**.

**Rules of engagement**
- One module at a time. Nothing simultaneous.
- Before building a module: a very-detailed edge-case analysis (down to individual
  buttons) is written to `docs/edge-cases/NN-*.md` and reviewed.
- Non-destructive: nothing existing is deleted; improvements are additive.
- Each module ships tested, on branch `claude/quotation-management-workflow-5dokb2`.

**Status key**
`⬜ not started` · `📝 edge-cases drafted (awaiting go)` · `🛠️ building` · `✅ done & pushed`

---

## Progress

| # | Module | Priority | Status | Commit / date |
|---|--------|----------|--------|----------------|
| 01 | Masters | P2 | ✅ done & pushed | 2026-08-26 |
| 02 | Users / Access / Roles | P0 | ✅ done & pushed | 2026-08-26 |
| 03 | Quotations | P1 | ✅ done & pushed | 2026-08-25 |
| 04 | Calls / Service Requests | P1 | ✅ done & pushed | 2026-08-24 |
| 05 | Jobs (Job 360) | P1 | ✅ done & pushed | 2026-08-24 |
| 06 | Inspection / IDEMS core + Applicability | P0 | ✅ done & pushed | 2026-08-24 |
| 07 | Vetting / Technical Review / Approval | P0 | ✅ done & pushed | 2026-08-24 |
| 08 | Report Release / Issue | P0 | ✅ done & pushed | 2026-08-24 |
| 09 | Invoicing | P0 | ✅ done & pushed | 2026-08-25 |
| 10 | Client Portal | P0 | ✅ done & pushed | 2026-08-25 |
| 11 | Vendor / Supplier-Inspector Centre | P1 | ✅ done & pushed | 2026-08-26 |
| 12 | NCR | P1 | ✅ done & pushed | 2026-08-24 |
| 13 | CAPA | P2 | ✅ done & pushed | 2026-08-24 |
| 14 | Settings | P2 | ⬜ | — |
| 15 | Clients / Customer 360 | P2 | ✅ done & pushed | 2026-08-24 |
| 16 | Vendors / Vendor 360 | P2 | ✅ done & pushed | 2026-08-24 |
| 17 | Leads | P2 | ✅ done & pushed | 2026-08-26 |
| 18 | Orders / Contracts (Contract 360) | P1 | ⬜ | — |
| 19 | Inquiries / Requirements | P2 | ✅ done & pushed | 2026-08-26 |
| 20 | Project Costing | P1 | ✅ done & pushed | 2026-08-26 |
| 21 | Hold / Witness Points | P0 | ✅ done & pushed | 2026-08-24 |
| 22 | Complaints | P1 | ✅ done & pushed | 2026-08-24 |
| 23 | Equipment (Equipment 360) | P1 | ✅ done & pushed | 2026-08-25 |
| 24 | Competence (Competence 360) | P0 | ✅ done & pushed | 2026-08-24 |
| 25 | Impartiality | P0 | ✅ done & pushed | 2026-08-24 |
| 26 | Identity | P0 | ✅ done & pushed | 2026-08-26 |
| 27 | Confidentiality | P0 | ✅ done & pushed | 2026-08-26 |
| 28 | Audits | P2 | ✅ done & pushed | 2026-08-26 |
| 29 | Data Control / Governance | P0 | ✅ done & pushed | 2026-08-26 |
| 30 | Vouchers / Expenses | P1 | ✅ done & pushed | 2026-08-24 |
| 31 | Attendance / Reconciliation | P1 | ✅ done & pushed | 2026-08-24 |
| 32 | Profitability (canonical engine) | P0 | ⬜ | — |
| 33 | Overheads | P1 | ✅ done & pushed | 2026-08-26 |
| 34 | Dashboards / Command Centre | P2 | ⬜ | — |
| 35 | Recruitment / Workforce | P1 | ⬜ | — |
| 36 | Licensing / SaaS Admin | P1 | ⬜ | — |
| 37 | Global Search | P1 | ✅ done & pushed | 2026-08-26 |
| 38 | Notification Centre | P2 | ⬜ | — |
| **39** | **My Work** | **P1** | **✅ done & pushed** | 2026-08-24 |
| 40 | Activity Timeline | P2 | ✅ done & pushed | 2026-08-26 |
| 41 | Document Control | P1 | ✅ done & pushed | 2026-08-26 |
| 42 | Change Control | P2 | ✅ done & pushed | 2026-08-26 |
| 43 | Training | P2 | ✅ done & pushed | 2026-08-26 |
| 44 | Evidence | P1 | ✅ done & pushed | 2026-08-26 |
| 45 | AI / Intelligence | P3 | ⬜ | — |
| 46 | Integrations | P2 | ⬜ | — |
| 47 | Mobile / PWA | P1 | ⬜ | — |
| 48 | Report Template Builder | P1 | ✅ done & pushed | 2026-08-26 |
| 49 | Entity 360 Standard | P2 | ⬜ | — |
| 50 | Cross-module Consistency & Regression | P0 | ⬜ | — |

---

## Completion log

_Each module, once done, gets a dated entry here: what was added, what was preserved,
which edge cases were handled, and the commit._

<!-- Append entries below as modules complete. -->

### Module 42 — Change control · 2026-08-26
**Decision:** (A) seal the one supersede that fell off the chain + a consolidated read-only "recent
controlled changes" list on `/audit-log`. A true accreditation-scope register, a generalised
impact-assessment artefact, and a formal change-request workflow object deferred (new state).
**Found:** change control is emergent per object — report reissue, controlled-doc / method /
decision-rule supersede, quote revision. Four already seal the change on the tamper-evident chain;
**`drule_supersede()` was the only supersede that never called `idems_log`** (a change to the
accept/reject criteria — exactly what an assessor wants on the chain). And there was no single
"what controlled thing changed, and why" view.
**Added (additive; one-line seal + read-only surface; no access change):**
- `drule_supersede()` now logs `idems_log('decision_rule', $newId, 'REVISION_OF', …)`, mirroring
  `method_supersede()` exactly. Existing behaviour (new DRAFT + old→SUPERSEDED + carry-forward) intact.
- `controlled_changes($days=90,$limit=200)` + `controlled_changes_count()` — a consolidated list
  unioning report reissues, controlled-doc/method/decision-rule supersessions (sealed chain) and
  quote revisions (`quote_revisions`); object, reference, change, who, when, deep link.
- A **"Recent controlled changes"** panel on `/audit-log` (rides its `idems.audit.view` gate — no new
  route, no new permission).
**Preserved:** `ops_idems_audit`, the chain-verify banner, `method_supersede`, `cdoc_supersede`,
`quote_revisions` — all unchanged. No schema change; nothing deleted.
**Edge cases:** each source in its own try/catch (missing table → empty, never a crash); newest-first
across heterogeneous timestamps; codes/refs shown, never a leaked document number; `days` window and
row caps bound every source.
**Tests:** `test_drules.php` (revision now sealed on the chain) + `test_module42_changecontrol.php`
(18 assertions — the seal fix present, all five domains surfaced, row shape, ordering, wiring, screen
preserved). Suite 2972 passing (only the 3 pre-existing baseline failures remain).
**Spec:** `docs/edge-cases/42-change-control.md`.

### Module 43 — Training · 2026-08-26
**Decision:** (A) an actionable training/certification watch drill-down on the competence screen. A
true training-attendance register, the dormant `qualifications`+`renewal_months` activation, and an
induction record deferred (larger write-heavy additions).
**Found:** there is no dedicated training register — competence (certs/authorisations/witness) is the
spine; certs already remind on expiry and the matrix shows per-inspector **counts**, but there was no
cross-inspector **who + which ticket** worklist.
**Added (additive, read-only, no access change):**
- `competence_training_watch($withinDays=45)` + `_counts()` — a ranked worklist across active
  inspectors of each cert/ticket lapsed or up for refresh within the window, naming the exact person
  and ticket. Reuses `inspector_certs`.
- A **"Training & certification watch"** panel on `/competence` (lapsed-first, linking to the person).
- The **first tests** of this surface.
**Preserved (verified by tests):** `competence_matrix`, the cert reminder cron, the authorisation/
witness engine, and the Module 24 eligibility verdict — all unchanged. No new permission (reuses the
`/competence` manager gate); no schema change; nothing deleted.
**Edge cases:** `docs/edge-cases/43-training.md`.
**Tests:** `tests/test_module43_training.php` (16 assertions). Suite 2954 passed / 3 pre-existing
baseline failures.

### Module 40 — Activity Timeline · 2026-08-26
**Decision:** (A) surface the already-logged timeline on the Complaint + NCR detail screens.
**Flagged, NOT changed:** the `act_log` kind-coercion attribution bug (system events render as
person-typed) and the unscoped global feed (office/SBU stored but never filtered) — both are
behaviour/access-affecting and left for a deliberate decision.
**Found:** the activity spine is well-built (denormalised partner_id, auto flag, indexed reads, global
feed, timelines on Customer/Lead/Opportunity/Call/Job) but **Complaint, NCR, Report and Invoice write
to it yet never surface it** on their own detail — the readers exist, just aren't called.
**Added (additive, read-only, no write, no access change):**
- `act_render_timeline($kind,$id,$title)` — a reusable read-only panel reusing `act_for_entity()`
  (date, kind pill grey=system/green=person, subject, body, actor); only this entity's own history.
- Wired onto the **Complaint** and **NCR** detail screens (the ISO records people most need a history
  on).
- The **first tests** of the per-entity timeline surfacing.
**Preserved (verified by tests):** `act_log`, the `activities` table, the global feed and the existing
lead/opportunity/call/job timelines — all unchanged. No new permission; no schema change; no write
path touched; the attribution coercion and feed scoping flagged not modified; nothing deleted.
**Edge cases:** `docs/edge-cases/40-activity-timeline.md`.
**Tests:** `tests/test_module40_activity.php` (13 assertions). Suite 2938 passed / 3 pre-existing
baseline failures.

### Module 48 — Report Template Builder (URFE) · 2026-08-26
**Decision:** (A) form-schema integrity validator (the missing twin of the .docx template validator)
+ first tests. Making duplicate-fkey a hard stop / a `(report_type_id, fkey)` unique index, and a
form-schema approval lifecycle, deferred (hard controls / governance).
**Found:** the .docx template has validation + a draft→review→approve lifecycle, but the **form
schema had neither** — a duplicate field key (index-less, ungated in the hand-edit path) silently
collides on storage and `{{token}}` rendering; an option-less choice / empty table / dangling
condition can go live and break report entry. No format-validity test coverage existed.
**Added (additive, read-only, no hard block):**
- `idems_format_validate($typeId)` — the twin of `idems_template_validate`, same `{level,issues}`
  shape: duplicate fkey → ERROR; option-less choice / empty table / required heading / dangling
  cond_field / no-fields → WARNING.
- A **"Format integrity"** panel on the form builder + a per-type **integrity pill** on the
  report-types list, both explicitly advisory.
- The **first tests** over format validity (none existed for either validator).
**Preserved (verified by tests):** the .docx template validator + lifecycle, freeze-at-issue,
`idems_fields`/`idems_sections`, the builder — all unchanged. No new permission (reuses
`idems.type.manage`); no schema change; no hard block; nothing deleted.
**Edge cases:** `docs/edge-cases/48-report-template-builder.md`.
**Tests:** `tests/test_module48_format.php` (15 assertions). Suite 2925 passed / 3 pre-existing
baseline failures.

### Module 44 — Evidence · 2026-08-26
**Decision:** (A) evidence & on-site readiness row + verify on-site line + first chain-tamper test.
**Flagged, NOT changed:** `/checkin-photo` serves a site-visit photo by id with no per-job scope check
(access-affecting — left for a deliberate decision).
**Found:** the evidence subsystem is strong (per-photo hash chain, EXIF/upload fact-separation,
server-time capture, geofenced check-ins, permission-checked file serving, `/verify`) but the report
never "knew about" its own evidence: it was **issuable with zero photos and no site check-in**
undetected; the verify page never stated somebody was on site; and `chain_verify` was untested.
**Added (additive, read-only, no hard control):**
- `idems_evidence_readiness($doc)` — photos, EXIF on-site count, arrival/departure check-in, chain
  intact — reusing `report_files`, `site_visits`, `chain_verify`.
- An **"Evidence & on-site" row** on the issue-readiness preview — warns on no photos / no check-in /
  broken chain; **never blocks** issue.
- An **"On-site check-in"** line on the public `/verify` page (the strongest evidence it omitted).
- The **first tamper test** of the `evidence_chain` (CONTENT detection).
**Preserved (verified by tests):** the hash chain, fact-separation, freeze-at-issue write-gate, the
report-file permission serving, the geofence — all unchanged. No new permission; no schema change;
advisory/read-only; `/checkin-photo` scope untouched (flagged); nothing deleted.
**Edge cases:** `docs/edge-cases/44-evidence.md`.
**Tests:** `tests/test_module44_evidence.php` (15 assertions). Suite 2910 passed / 3 pre-existing
baseline failures.

### Module 33 — Overheads · 2026-08-26
**Decision:** (A) overhead-recovery reconciliation (pool vs recovered) + first overhead-engine tests.
Reconciling/retiring the two overhead models and the OVERHEAD-head vs contingency% double-count
deferred (deliberate finance decisions).
**Found:** two parallel overhead models that never meet — the per-job oh%/contingency% (Model A,
canonical `job_profit`/`boss_profit`) and the real monthly `office_expenses` pool allocated to SBUs
(Model B) — and the actual pool was **never reconciled against the overhead recovered** through the
oh%. No under/over-recovery figure existed. The real-cost engine had **zero tests**.
**Added (additive, read-only, one canonical engine):**
- `overhead_recovery($officeId,$yr,$mon)` — pool (`office_expense_total`) vs recovered (sum of the
  `overhead`+`contingency` lines the canonical `job_profit` already loads onto the office's jobs that
  month) vs variance. **No second cost formula** — variance is subtraction, like `pc_estimate_vs_actual`.
- An **"Overhead recovery"** panel on the `/cost-run` screen (per office+month, salary-gated).
- **Confidentiality:** recovered is salary-derived → withheld from a viewer without `can_see_salary()`;
  the pool is always shown.
- The **first tests** over the overhead engine.
**Preserved (verified by tests):** `job_profit`/`boss_profit`, the oh%/contingency% settings, the cost
run, `office_expenses`, the SBU P&L — all unchanged. No new permission (reuses the cost-run salary
gate); no schema change; no second cost formula; nothing deleted. Makes the two-model divergence
visible for the first time.
**Edge cases:** `docs/edge-cases/33-overheads.md`.
**Tests:** `tests/test_module33_overheads.php` (14 assertions). Suite 2895 passed / 3 pre-existing
baseline failures.

### Module 01 — Masters (editable-lookup engine) · 2026-08-26
**Decision:** (A) lookup usage counter + dangling/duplicate integrity detectors + delete warning.
Exposing the dormant `active` flag (deactivate-instead-of-delete) deferred (blocking-delete-in-use is
a hard control).
**Found:** the lookup engine is used 364× but has **no usage counts and no data-quality detection**;
the only removal is a hard DELETE that silently orphans records into raw codes (a single deleted value
has no per-value fallback). The `active` flag is dead in the UI.
**Added (additive, read-only, no hard control):**
- `lk_usage_map()` (curated 1:1 key→[table,col,const]) + `lk_value_usage($key,$code)` — how many
  records use a value; null for an untracked list (never a misleading "0 = safe").
- A **"Used by N records"** column on the value editor + a stronger delete confirm when N>0 —
  **advisory** (delete warned, never blocked).
- `lk_dangling_total()` + a **dangling-value** integrity check ("every stored dropdown code still
  exists in its list") and a **duplicate-code** integrity check, in the `integrity_checks()`
  framework (surfaced on Data Control + the Module 29 nightly run).
- The **first tests** over lookup usage / dangling / duplicate detection.
**Preserved (verified by tests):** `lk_options_or` and the seeding/fallback engine, the admin
screens, the routes — all unchanged. No new permission; no schema change; delete warned not blocked;
nothing deleted.
**Edge cases:** `docs/edge-cases/01-masters.md`.
**Tests:** `tests/test_module01_masters.php` (16 assertions). Suite 2881 passed / 3 pre-existing
baseline failures.

### Module 41 — Document Control (ISO 17020 §8.3) · 2026-08-26
**Decision:** (A) review-due readiness + compliance-board row + cron reminder + supersede audit log.
Read-acknowledgement register, enforced approval, and a distinct `mod.doccontrol.*` permission
deferred.
**Found:** a real controlled-document register exists (`lib/controldocs.php`) with versioning,
supersession and review-due dates — but it was an island: the review-due signal was computed yet
**never reached the cron dispatcher or the compliance board** (the two surfaces every other
accreditation register uses), and supersession was the one lifecycle event not on the sealed trail.
**Added (additive, read-mostly, no hard control):**
- `cdoc_readiness()` — current / review-overdue / never-approved counts.
- A **§8.3 compliance-board row** ("the document in use is the current, approved revision").
- `cdoc_run_reminders()` — e-mails the quality owner about overdue/unapproved documents, wired into
  `cron.php` **weekly-guarded**. Notifies; changes no document.
- **Supersession now writes a `SUPERSEDED` `idems_log` entry** (the previously-missing sealed-trail
  event), completing the evidence chain.
- The **first tests** over readiness/reminder and the supersede audit write.
**Preserved (verified by tests):** the register, versioning/supersession chain, obsolete-control
unambiguity, routes/views — all unchanged. No new permission (reuses `cdoc_can_*`); no schema change;
no hard control (approval still not enforced before CURRENT — noted); nothing deleted.
**Edge cases:** `docs/edge-cases/41-document-control.md`.
**Tests:** `tests/test_module41_doccontrol.php` (13 assertions). Suite 2865 passed / 3 pre-existing
baseline failures.

### Module 37 — Global Search · 2026-08-26
**Decision:** (A) add the missing spine entities (opportunities, invoices) as scope-respecting
sources + first search test. Vouchers/POs deferred (weak/unclear searchability). **Flagged, NOT
changed:** the existing contracts/inquiries sources are permission-gated but not office-scoped —
tightening it is access-changing, so it is left as-is and flagged for a deliberate decision.
**Found:** global search is well-built (permission-gated registry, no query-then-filter leak), but
several spine records were reachable-but-unfindable by their own reference — invoices (only via a
job's invoice_number), opportunities, vouchers, POs. No executable search test existed.
**Added (additive, scope-respecting, no access change):**
- An **Opportunities** source (gated `opp_can_view()`, office+SBU scoped, matches OPP ref/name/
  partner/contact, deep-links `/opportunity?id=`).
- An **Invoices** source (gated `books_can()`, office+SBU scoped, matches invoice no/partner/PO/
  contract number, deep-links `/invoice?id=`).
- The **first executable search test** — pinning the permission-gate-before-query and scope-clause
  behaviour.
**Preserved (verified by tests):** every existing source, the `if(!$can)return` gate, the scope
clauses, the omnibox, the command palette, the reference-jump — all unchanged. No new permission; no
schema change; no access changed; the contracts/inquiries scoping untouched (flagged); nothing
deleted.
**Edge cases:** `docs/edge-cases/37-global-search.md`.
**Tests:** `tests/test_module37_search.php` (11 assertions). Suite 2852 passed / 3 pre-existing
baseline failures.

### Module 19 — Inquiries / Requirements · 2026-08-26
**Decision:** (A) stale/un-quoted inquiry detector + advisor worklist + surface the dead
`assigned_to`. Inquiry-level conversion reporting, response-SLA analytics, and the QUOTED-reverse
deferred.
**Found:** the inquiry rung of the funnel was blind to time — an OPEN inquiry sat un-quoted forever
(no aging, reminder or worklist) though `received_date`/`created_at` were captured; the same pattern
was already built for leads (Module 17) and quotes (Module 03). `assigned_to` was dead data.
**Added (additive, read-only, no hard control):**
- `inquiry_sla_days()` (setting, default 7) + `inquiries_due()` / `inquiries_due_count()` — OPEN
  inquiries older than the service level, SBU-scoped, carrying the owner; QUOTED/DROPPED never chased;
  never changes a status.
- `adv_inquiries_unquoted()` — a business-advisor "Inquiries waiting for a quotation" card mirroring
  `adv_cold_leads()`, registered in `adv_all()`, explicitly advisory.
- Register surfacing: a waiting-for-quote banner + an **Owner column** (finally reading the dead
  `assigned_to`) + a ⏳ Nd pill on overdue OPEN rows.
- The **first tests** over the aging behaviour.
**Preserved (verified by tests):** the register, form, create/edit/delete guards, the auto-QUOTED
flip, the lead↔inquiry↔quote links — all unchanged. No new permission; no schema change (a setting +
reading existing columns); no access changed; no inquiry auto-dropped; nothing deleted.
**Edge cases:** `docs/edge-cases/19-inquiries.md`.
**Tests:** `tests/test_module19_inquiries.php` (14 assertions). Suite 2841 passed / 3 pre-existing
baseline failures.

### Module 17 — Leads · 2026-08-26
**Decision:** (A) cold-lead / overdue-follow-up detector (advisor check + register tile) + first
lifecycle-guard tests. Source-attribution funnel, first-response SLA, bulk-lost reason, and
transactional conversion deferred.
**Found:** the leads subsystem is rich (board, score, dedupe, conversion with `lead_id` carried
through the spine) but the top of the funnel rotted silently: `next_action_on` was **stored and
advertised as "drives your follow-up list" yet no query read it**, and `lead_stalled()` was
surfaced only in-page — no advisor check, no cron, no follow-ups-due list. A cold lead produced zero
proactive signal (the pre-Module-03 quote problem).
**Added (additive, read-only, no hard control):**
- `leads_due()` / `leads_due_count()` — the open leads past their stage service level, or with an
  overdue `next_action_on` (finally reading the dead field), each with plain-English reasons;
  converted/lost leads never chased.
- `adv_cold_leads()` — a business-advisor "Leads going cold" card mirroring the existing
  `adv_stalled_deals()` opportunity check, registered in `adv_all()`, explicitly advisory ("a lead
  is never closed for you").
- A **"Need attention now"** tile + banner on the leads register.
- The **first tests** over the subsystem — cold-lead detection + the previously-untested WON-forces-
  convert / LOST-needs-reason guards.
**Preserved (verified by tests):** the board, score engine, dedupe, conversion, `lead_move` guards,
every route — unchanged. No new permission; no schema change; no access changed; no lead auto-closed;
nothing deleted.
**Edge cases:** `docs/edge-cases/17-leads.md`.
**Tests:** `tests/test_module17_leads.php` (14 assertions). Suite 2827 passed / 3 pre-existing
baseline failures.

### Module 28 — Internal Audits (§8.8) + Management Review (§8.9) · 2026-08-26
**Decision:** (A) overdue reminders + MR-decision→CAPA link + per-clause next-due + first gate tests.
**Found:** the subsystem (`lib/audits.php`) is mature (coverage board, auto-gathered MR inputs,
§8.8.2 independence gate, close/complete gates, finding→CAPA loop) but had three holes: **no overdue
reminder** (the only accreditation registers with a readiness signal but no cron nudge); **MR
decisions never reached the CAPA register** (`mr_actions.capa_ref` dead in the live path); and **zero
tests**.
**Added (additive, read-mostly, no hard control):**
- `audits_run_reminders()` + `reviews_run_reminders()` — read only the existing readiness and e-mail
  the audit/quality owner about uncovered clauses, unactioned findings, an overdue review or open
  decisions. Wired into `cron.php`, **weekly-guarded**. They notify; they change no record.
- **MR-decision → CAPA**: a `review-action-capa` route + "🛠 Raise CAPA" button raising a corrective
  action from a management-review decision (`source='MGMT_REVIEW'`), writing `capa_ref`/`capa_id`
  back (new additive `mr_actions.capa_id`). Closes the asymmetry with the §8.8 finding→CAPA loop.
- **Per-clause days-since + next-due** added to `audit_coverage()` (additive keys).
- The **first tests** over the subsystem — reminders, the §8.8.2 independence refusal, the
  close-block, the coverage enrichment, the MR→CAPA link.
**Preserved (verified by tests):** the coverage board, the MR auto-gather, the independence gate, the
close/complete gates, every route — unchanged. No new permission; one additive nullable column; no
access or data changed; nothing deleted.
**Edge cases:** `docs/edge-cases/28-audits.md`.
**Tests:** `tests/test_module28_audits.php` (15 assertions). Suite 2813 passed / 3 pre-existing
baseline failures.

### Module 29 — Data Control / Governance (§7.11) · 2026-08-26
**Decision:** (A) scheduled integrity self-test + money orphan detectors + audit-chain banner.
(Module 02 already added the access-audit / effective-access / toxic-combo work here.)
**Found:** the §7.11 console has a real integrity-check registry that writes dated pass/fail evidence
to `data_check_runs` via `integrity_run()` — but **nothing fired it on a schedule**, so the history
was starved and `run_stale` permanently red (the same "records only what someone remembered" pattern
that moved `audit_trim_old()` to nightly cron). The money tables had no orphan detector, and the
sealed audit chain was verified in the console's own check but **not surfaced on `/audit-log`** where
the trail is read.
**Added (additive, evidence-only — no access/data change):**
- A **per-day-guarded `integrity_run()`** block in `cron.php` (mirroring `audit_trim_old`) — writes
  one dated pass/fail evidence row a day; a failed run never stops the nightly job.
- Two **money orphan detectors** in `integrity_checks()`: `ventry_voucher` (voucher line → real
  voucher) and `invline_invoice` (invoice line → real invoice) — COUNT-only, skipped-safe.
- An **audit-chain-intact banner** on `/audit-log` surfacing `idems_audit_verify()` — "chain intact"
  / "chain broken (first at #id)".
- The **first tests** over the integrity registry, `integrity_run()`, and the new detectors.
**Preserved (verified by tests):** the check registry, `data_check_runs`, the failure log, the access
matrix, the retention/DSAR surfaces — all unchanged. No new permission; no schema change (reuses
`data_check_runs` + a setting); no access or data changed; nothing deleted.
**Edge cases:** `docs/edge-cases/29-data-control.md`.
**Tests:** `tests/test_module29_datacontrol.php` (14 assertions). Suite 2798 passed / 3 pre-existing
baseline failures.

### Module 27 — Confidentiality (ISO 17020 §4.2) · 2026-08-26
**Decision:** (A) connect the governance pillar to the work + the readiness board; a hard allocation
gate and a per-job acknowledgement write-stamp deferred (would change who-can-do-what / add a write
path).
**Found:** confidentiality has two pillars that never meet — a complete governance pillar
(`lib/confidentiality.php`: undertakings, client NDAs, a breach register auto-raising a §4.2 NCR) and
a strong runtime disclosure gate (`cvp_visibility_sql`, fail-closed). But the undertaking an inspector
signs was **never surfaced at the job**, `conf_readiness()` was **never on the compliance board**, and
the whole governance pillar was **untested**.
**Added (additive, read-only, no hard control):**
- `conf_job_status($job)` — the §4.2 picture for a job: the assigned inspector's undertaking state
  (ok / lapsed / none) + the client's own NDA obligation-end. Shows only this job's inspector and
  client; blocks nothing.
- `conf_open_breach_count()` — open confidentiality breaches, for the board.
- A read-only **Confidentiality (§4.2)** panel on the job Overview (management/assessor evidence, not
  field inspectors), explicitly advisory.
- **`conf_readiness()` registered on the compliance readiness board** (`lib/compliance.php`), beside
  impartiality and competence — §4.2 is finally visible where accreditation posture is reviewed.
- The **first tests** for the governance pillar (undertaking in-force logic, coverage states, NDA
  obligation math, job status, breach count).
**Preserved (verified by tests):** the disclosure gate, the undertaking/NDA/breach registers, the
impartiality gate, and the rest of the compliance board — all unchanged. No new permission; no schema
change; no hard control; nothing deleted; the board row is additive beside impartiality.
**Edge cases:** `docs/edge-cases/27-confidentiality.md`.
**Tests:** `tests/test_module27_confidentiality.php` (21 assertions). Suite 2784 passed / 3
pre-existing baseline failures.

### Module 26 — Identity (DPDP documents) · 2026-08-26
**Decision:** (A) a company-wide DPO access-review surface + the first safeguard tests. Sealing the
access log into the `idems_audit` chain (secondary) deferred (touches the write path).
**Found:** the identity subsystem is already very DPDP-mature — numbers masked by storage design
(only `number_last4` ever selected), reveals require a reason and are logged, the scan is
permission-checked, the retention sweep runs nightly, expiry reminders email the engineer, and
consent/purpose are recorded. The one real gap: the access log (`person_document_access`) was only
ever queried **per person** — a DPO could not answer "show me every reveal/copy-out this quarter
across all people" from a screen.
**Added (additive, read-only, loosens nothing):**
- `iddoc_access_review($action,$from,$to)` + `iddoc_access_summary()` — the company-wide log across
  all people, joined to the person's name and document kind, filterable by action/date. Exposes
  reasons/recipients/actors/IPs — **never a document number** (the log never stored one).
- A manage-gated **"Access review (DPO)"** route/view (filters + a count-by-action header + the
  `iddoc_holders()` "who can open a document" roster), linked from the identity register.
- The **first tests** over the security-critical behaviours that had no coverage: masking, the
  list-reader never selecting the full number, reveal/share logging, and redaction preserving the
  record while wiping the number/file.
**Preserved (verified by tests):** the masking discipline, the reveal-reason gate, the
permission-checked scan, the retention sweep, and both `person.iddoc.*` gates — all unchanged. No new
permission (reuses `iddoc_can_manage`); no schema change; no document number exposed to anyone who
couldn't already reveal it; nothing deleted.
**Edge cases:** `docs/edge-cases/26-identity.md`.
**Tests:** `tests/test_module26_identity.php` (18 assertions). Suite 2763 passed / 3 pre-existing
baseline failures.

### Module 20 — Project Costing · 2026-08-26
**Decision:** (A) estimate-vs-actual reconciliation + the first estimate-math tests. Margin-floor
guard and CTC-derived manpower deferred.
**Found:** project costing (`lib/projcosting.php`) is a **pre-award estimate only** — nothing rolls
**actual** cost up against the bid, and structurally the estimate couldn't even be joined to the
contract (no `boss_id`/`contract_number` on `project_costings`). So "did this project make its bid
margin?" was unanswerable. The actuals engine (`boss_profit`) never reads the estimate.
**Added (additive, read-only, one canonical engine):**
- `pc_for_boss($bossId)` — links a contract (a `boss_numbers` row) to its bid costing by matching the
  boss number to `quotations.contract_number` (a boss number **is** the contract number). Implemented
  **derive-on-read** — no schema change at all, works for existing data — preferring the APPROVED
  costing. Null when a contract was won without a pre-award costing.
- `pc_estimate_vs_actual($bossId)` — Estimated (`exp_revenue/cost/profit` from `pc_rollup`) vs Actual
  (`boss_profit` — the sole actuals engine) with a variance. **Invents no new margin formula.**
- An **"Estimated vs actual — did it make its bid margin?"** panel on the `data.profitability`-gated
  profitability detail, with a "beat/missed by N points" line. Actual cost/profit stay behind
  `can_see_salary()` exactly as the rest of the screen does — no cost/margin reaches a role that
  couldn't already see contract profitability.
- The **first unit tests** over the estimate math (`pc_line_calc`/`pc_rollup`) — the seed's
  "hand-checkable" numbers are finally checked.
**Preserved (verified by tests):** the estimate engine, the actuals engine (`boss_profit`), the
profitability gate and `pc_can()` — all unchanged. No new permission; no schema change; no widened
exposure; nothing deleted.
**Edge cases:** `docs/edge-cases/20-project-costing.md`.
**Tests:** `tests/test_module20_costing.php` (20 assertions). Suite 2745 passed / 3 pre-existing
baseline failures.

### Module 11 — Vendor / Supplier-Inspector Portal · 2026-08-26
**Decision:** (A) vendor qualification visibility + expiry alert. Vendor-perms admin UI and a vendor
billing/payable view deferred.
**Found:** the vendor portal's confidentiality and scoping are already sound (own `vendor_users`/`vuid`
identity, confidentiality-first visibility gate, `vendor_visible` share flag, both single-fetches
already scoped, no cost/margin selected). The standout gap: a vendor **could not see their own
qualification standing** — `vendor_profiles` (approval_status, valid_until, reassess_on) +
`vendor_status_events` are staff-maintained but had **no vendor consumer**, and there was **no
expiry warning**.
**Added (additive, read-only, confidentiality-first):**
- `cvp_vendor_qualification()` — the session vendor's own profile reduced to **safe fields**
  (status + label, vendor type/category, approved-on, valid-until, reassess-on, days-to-expiry,
  expiring/expired flags). **Never** selects `last_score`, `vendor_rating`, `last_band`, `risk_class`,
  `notes` or `updated_by`. Null-safe when no profile exists.
- `cvp_vendor_qualification_events()` — the status timeline reduced to new-status + date + source;
  the internal score, reason and actor are omitted.
- A **"Your qualification"** route/view (status pill, expiry line, safe history) + a dashboard status
  card + a nav entry, behind a new additive `VENDOR_PERMS` key `qualification` (blank perms =
  everything, so every existing vendor sees it).
- A `QUALIFICATION_EXPIRING` alert emitted by the existing state-derived `cvp_notify_sync('VENDOR')`
  when `valid_until` is within `idems_vendor_reminder_days()` or already past — idempotent via the
  existing notify natural key.
**Preserved (verified by tests):** the vendor identity model, the confidentiality visibility gate,
the `vendor_visible` share flag, the single-fetch scope, the NCR respond loop, and every existing
vendor read — all unchanged. No numeric grade exposed; no schema change; no hard control; nothing
deleted.
**Edge cases:** `docs/edge-cases/11-vendor-portal.md`.
**Tests:** `tests/test_module11_vendor.php` (18 assertions). Suite 2725 passed / 3 pre-existing
baseline failures.

### Module 02 — Users / Access / Roles · 2026-08-26
**Decision:** **A + B** — the user explicitly approved the hard guards alongside the observability
work. Matrix doc/UI reconciliation (§5-C) deferred.
**Found:** the authorization model is clean (one `can()` choke point, master short-circuit → per-user
CSV override → role default; pointwise SoD already enforced) with good dormant/2FA visibility. Gaps:
(1) **no audit of permission/role/scope changes** — the sealed `idems_log` chain exists but the
user-save logged only passwords; (2) **no full effective-permissions view** (only 8 powers shown)
and **no toxic-combination detector**; (3) a **privilege-escalation hole** — a non-master global
manager could set anyone's (incl. their own) role to `MASTER_ADMIN`; (4) the last-master/self-lockout
guard existed only on *deactivate*, not on the *edit* save.
**Added (A — observability, purely additive, no gate change):**
- `access_diff($old,$new)` — a plain-English diff of two users' **resolved** access (role, granted/
  revoked permissions, scope, master/active), empty when nothing authorization-relevant changed.
- **Permission-change audit** — the per-user save (`ops_users`) now logs `ACCESS_CHANGED` and the
  role-default editor (`ops_access`) logs `ROLE_DEFAULTS_CHANGED` to the sealed `idems_log` chain,
  finally recording who granted whom what, when. Viewable where every other audit already is.
- `access_toxic_combos()` + enriched `access_report()`/`access_effective_all()` — the Data Control
  "Who can get at it" table now shows each user's **full** resolved permission count and a
  **segregation-of-duties flags** panel: non-master users holding both sides of a maker/checker pair
  (create+approve quotes, approve+issue reports, decide-complaint+close-CAPA, global-user-manage).
  Advisory only — a master is never listed (holds all by design).
**Added (B — hard guards, on the user's explicit go, changes who-can-do-what):**
- **Only a master mints or changes a master** — a non-master user-manager can no longer promote
  anyone (incl. themselves) to `MASTER_ADMIN`, nor edit an existing master's account. Closes the
  privilege-escalation hole.
- **Last-master / self-lockout guard on the edit save** — the last active master can't be demoted by
  a role change on the edit form, and a user can't strip their own `users.manage.*`.
**Preserved (verified by tests):** `can()` and the resolution order, `ua()`, `user_effective_perms`,
`assignable_permissions`, the R3 preserve-unseen/reset-to-default behaviour, and every existing SoD
gate — all unchanged. No permission granted or revoked; no new permission constant; no schema change.
**Edge cases:** `docs/edge-cases/02-users-access-roles.md`.
**Tests:** `tests/test_module02_access.php` (21 assertions). Suite 2707 passed / 3 pre-existing
baseline failures.

### Module 10 — Client Portal · 2026-08-25
**Decision:** (A) register-backed portal invoices + close the intra-client site-scope hole; a
satisfaction-survey portal surface and an "invoice raised" email deferred (§5-C).
**Found:** the portal is the most disciplined subsystem (own identity `client_users`/`cuid` that can
never be a staff user, dispatch before the auth gate, IDOR avoided by `client_id` in the WHERE
clause, finalized-only reports, PDFs served from the authorised row, cost/margin never selected —
**no cross-client leak**). Two gaps: (1) **it showed the client the wrong money** — `portal_invoices()`
read the legacy `jobs` mirror, not the `invoices` register, so a **consolidated** invoice showed once
per job, a **manual (no-`job_id`) invoice was invisible**, and a **part-payment read as unpaid**;
(2) intra-client **site scope** was applied on list views but omitted on single-record fetches
(`portal_call`, `portal_report`, the complaint job-picker).
**Added (additive, non-destructive):**
- `portal_invoices_register()` — reads the real `invoices` scoped by `partner_id`
  (`ISSUED/PART_PAID/PAID`, never a draft), outstanding from `books_settled` (so a part-payment reads
  as half, a TDS-settled invoice reads as paid), **only client-safe columns** (number/date/due/gross/
  outstanding/status) — never a cost, margin, credit term or internal note.
- `portal_invoices()` now returns the **superset**: register rows first, then any legacy `jobs`-mirror
  invoice the register doesn't cover, **de-duplicated by number** — so consolidated and manual
  invoices appear and nothing a client saw before disappears. The dashboard outstanding/overdue and
  the invoices view now show the true remaining balance (a "Part-paid" pill).
- **Least privilege:** `portal_call()`, `portal_report()` and the complaint job-picker now apply the
  **same** `portal_site_sql` the list views already apply, so a site-restricted user can't open a
  same-company record outside their site by id. A blank `site_ids` still sees everything.
**Preserved (verified by tests):** the identity model, the finalized-only report gate + secure PDF
serving, the invite-token lifecycle, the CVP visibility gate, and every partner-scoped read — all
unchanged; the `jobs.invoice_*` mirror and its non-portal readers (profitability, MIS, money desk)
untouched. No new permission; no schema change; no confidential column newly exposed. First automated
coverage of portal invoice correctness.
**Edge cases:** `docs/edge-cases/10-client-portal.md`.
**Tests:** `tests/test_module10_portal.php` (18 assertions). Suite 2686 passed / 3 pre-existing
baseline failures.

### Module 09 — Invoicing · 2026-08-25
**Decision:** (A) invoice-number integrity + overdue-receivables reminder; SoD maker-checker
(§5-B) and the invoice↔report-issued gate (§5-C) and unifying profitability/portal onto the
register deferred.
**Found:** invoicing is two systems bridged — a mature relational **Books register**
(`invoices/invoice_lines/receipts/receipt_allocations/credit_notes`, GST/IGST frozen at create,
TDS on the receipt, part-payment, round-off, ledger, ageing, credit notes, Tally export, gapless
numbering **allocated at issue**) plus a legacy per-job flag mirror kept in sync for older screens.
The register is solid; the standout gap was a **safety** one: `invoices.invoice_no` had only a
plain index, and numbering is a read-max-then-write with a retry loop — so two simultaneous "Issue"
clicks had a genuine TOCTOU window to mint a **duplicate invoice number** (legally unrecoverable in
a filed GST return). Plus ageing was view-only — nothing chased an overdue invoice.
**Added (additive, non-destructive):**
- **DB-enforced uniqueness** on the money-document numbers — unissued drafts now carry **NULL**
  (a UNIQUE index allows many NULLs; SQLite gets a **partial** unique index that also exempts any
  legacy `''`), built **defensively**: `books_unique_number_index` skips the index when legacy
  duplicates exist (surfaced by `books_duplicate_numbers` as a red banner on the invoice register)
  rather than crashing the boot; `books_backfill_null_numbers` normalises existing empties.
- **Hardened `books_issue`** — allocate + write under a retry, so a concurrent Issue that grabbed
  the number makes this write fail on the UNIQUE index and **re-allocate** the next one instead of
  committing a duplicate. The read-max numbering and the gapless-at-issue behaviour are unchanged.
- **`ar_overdue_reminders()` cron** (wired into `cron.php`, same shape as the calibration reminder)
  — chases ISSUED/PART_PAID invoices past due with money still owed, e-mails finance a concise
  list, and stamps a new additive `reminded_at` so a daily run doesn't re-nag; skips paid,
  within-terms and cancelled invoices; idempotent.
**Preserved (verified by tests):** the numbering-at-issue (gapless), GST/IGST + round-off, the
receipt→allocation decoupling, overpayment refusal, the double-billing guard, the closed-job line
gate, cancel/credit-note logic, the job mirror/un-mirror and the Tally export — all unchanged. No
new permission; only additive schema (one column + indexes). `test_bill_by_project` and the other
billing tests stay green.
**Edge cases:** `docs/edge-cases/09-invoicing.md`.
**Tests:** `tests/test_module09_invoicing.php` (19 assertions). Suite 2668 passed / 3 pre-existing
baseline failures.

### Module 03 — Quotations · 2026-08-25
**Decision:** (A) expiry awareness + the approval-bypass guard; margin-at-quote (→ Module 20/32)
and the online client accept/reject portal deferred.
**Found:** the quotation engine (`lib/crm.php`) is mature — full revision chains (immutable once
SENT), amount/BU approval chains with maker-checker retract, a pre-order checklist, contract
conversion with bidirectional FKs, Word/PDF output, a follow-up cadence, a sales handoff wall. Two
concrete gaps: **`EXPIRED` was a fully-built but unreachable status** — `validity_days` was stored
and printed but never compared to `sent_at`, no code ever set EXPIRED, no cron expired quotes, so a
SENT quote sat open (and in the pipeline) forever; and a **direct `quote-status → APPROVED` could
skip the multi-level approval chain**.
**Added (additive, non-destructive):**
- `quote_validity($q)` — read-only: is a SENT/EXPIRED quote past `sent_at + validity_days`?
  (`validity_days=0`/blank = open-ended; DRAFT/closed never expire). Never blocks.
- `crm_expire_quotes()` — an opt-in cron (wired into `cron.php`, same shape as the calibration
  reminder) that stamps the **already-defined** `EXPIRED` status on lapsed open SENT quotes;
  idempotent; skips accepted/lost and contract-linked quotes; logs the change.
- **Surfaced:** an Expired KPI + tab on the register, a "past validity" pill on still-SENT rows
  before the sweep runs, a past-validity/near-expiry banner on the detail, and Accept/Revise
  actions extended to an EXPIRED quote (accept records "accepted after validity had lapsed").
- **Analytics:** EXPIRED split out of the "lost" state set into its own count — a lapsed quote is
  no longer silently counted as a regretted loss.
- **Guardrail:** `crm_quote_needs_chain` / `crm_quote_chain_satisfied` — when an active approval
  rule matches the amount/BU, the direct `quote-status → APPROVED` is routed through the chain; a
  master may override, **logged as a bypass**, not silent. No matching rule ⇒ unchanged.
- **Docs:** added the full Quotation lifecycle (incl. the SENT→EXPIRED transition) to
  `docs/03-object-lifecycles.md` in the same commit.
**Preserved (verified by tests):** the revision chain + sent-quote immutability lock, the approval
chain + retract, the pre-order checklist, the one-quote→one-contract guard, the contract-registration
flow, the handoff wall, Word/PDF, and the follow-up cadence — all unchanged. No new status (EXPIRED
already existed); no new permission; no schema change.
**Edge cases:** `docs/edge-cases/03-quotations.md`.
**Tests:** `tests/test_module03_quotations.php` (26 assertions). Suite 2649 passed / 3 pre-existing
baseline failures.

### Module 23 — Equipment (Equipment 360) · 2026-08-25
**Decision:** (A) impact-flagging 360; a maintenance/lifecycle layer and forcing the string-only
IDEMS-JSON instruments through the FK deferred to a deliberate later change.
**Found:** the measuring-equipment control is already solid — a real master (`equipment`), a
never-overwritten certificate history (`equipment_calibrations`), a report→instrument link
(`report_equipment`), a non-overridable at-issue hard block that judges each instrument against
the certificate in force **on the inspection date**, and a 30-day expiry-reminder cron. The
forward question ("does this report's instrument have a live certificate?") is fully guarded.
**The gap:** the **reverse** question was never asked — when a certificate lapses or is later
found bad, *which already-released reports and jobs rested on this instrument?* No code queried
`report_equipment WHERE equipment_id=?`, though the FK exists and is indexed (`ix_req_equip`). The
reminder emailed a human about the instrument but named no affected report; the detail screen was
a register, not a 360.
**Added (additive, read-only, NO auto-invalidation):**
- `reports_using_equipment($eqId)` — the reverse lookup (joins `report_docs`), every report that
  named the instrument with its status, job, work date and the certificate it was stamped against.
- `equipment_calibration_impact($eqId)` — a per-report verdict: **Covered** (rested on a valid PASS
  certificate on the work date — stays Covered even after that certificate later expires) vs
  **Review** (the certificate it rested on was later marked FAIL or removed/revoked, or no valid
  certificate covered the work date). Only **released** reports (APPROVED/ISSUED/finalised) count as
  impact; a draft is listed but not counted (it re-hits the hard block on issue). Ordinary expiry
  after the work and supersession by a newer certificate do **not** raise Review (no over-flagging).
- An **"Reports & jobs using this instrument"** panel on the equipment detail with a calibration-
  impact banner ("N released reports may need a controlled quality review — nothing is
  auto-invalidated"), honest that string-only (unlinked) instruments aren't covered.
- The expiry reminder now carries the **blast-radius count** ("Used on N released reports — review
  at /equip-edit?id=…").
**Preserved (verified by tests):** the `document.issue` calibration hard block
(`report_equipment_block` → `equipment_calibration_on`, never overridable), the never-overwritten
history, the `calibration_id` stamp-at-add-time, the reminder cron and register banner — all
unchanged. No schema change; no new permission (reuses `equipment_can_manage` / `master.manage`).
First automated coverage of the reverse impact lookup.
**Edge cases:** `docs/edge-cases/23-equipment.md`.
**Tests:** `tests/test_module23_equipment.php` (18 assertions). Suite 2623 passed / 3 pre-existing
baseline failures.

### Module 31 — Attendance / Reconciliation · 2026-08-24
**Decision:** (A) reconciliation view + flags; fully separating the conflated stores deferred to
a deliberate migration.
**Found:** only site presence (`site_visits`) is a distinct store — working/attendance/billed
hours all collapse into `voucher_entries.hours`, with a second `attendance` record never
reconciled to it. The one recon screen only compares to an external HR CSV. Of the five checks,
**impossible timing was entirely absent** (negative check-in→check-out spans silently dropped).
**Added (additive, read-only):** `attend_anomalies($inspectorId, $from, $to)` — cross-checks the
existing data and flags all five: **impossible timing** (EXIT before ENTRY — the new check),
**excessive hours** (over `hours_cap`), **missing check-out** (ENTRY, no EXIT, past day),
**overlapping jobs** (two jobs a day), and **presence↔hours mismatch** (on site but no voucher
hours; hours but no check-in when check-in is expected). Surfaced as a **Reconciliation flags**
panel + a count card on the timesheet; leave/off days excused.
**Preserved (verified by tests):** the daily-hours cap at entry, the punch-ordering guards, the
double-booking soft-stop, `site_visit_close_missing`, the HR-CSV recon, and the timesheet build —
all unchanged (recon only reads). No schema change; no new permission. First coverage of the
impossible-timing check.
**Edge cases:** `docs/edge-cases/31-attendance.md`.
**Tests:** `tests/test_module31_attendance.php` (11 assertions). Suite 2605 passed / 3 pre-existing
baseline failures.

### Module 30 — Vouchers / Expenses (fast field capture) · 2026-08-24
**Decision:** (A) quick-add expense + receipt photo + job bridge; GPS auto-capture from
check-in deferred. Answered the user's question: the coordinator's job-close `expenses` are
client-billable job costs (kept separate); the inspector's own expense goes on the monthly
voucher — and the quick-add's optional job + the job-screen "Log my expense" bridge make a
job-linked expense land there.
**Found:** the voucher is a monthly 12-column grid; no per-expense receipt photo (only one
whole-voucher file); the R5 maker-checker/reopen guards are solid and must be preserved.
**Added (additive):**
- Per-line receipt storage (`receipt_data`/`_mime`/`_name` via `ensure_column`, backward
  compatible).
- `voucher-quick-add` — one form (amount + type + optional job + note + receipt photo) that
  auto-fills claimant/date/currency, opens THIS month's voucher, writes one categorised line
  (catch-all head when none chosen), rolls up the total — all through `can_edit_voucher`, so a
  submitted/paid/frozen month is refused, never written past.
- `voucher-line-receipt` — serves a line's receipt to a permitted viewer only; a 📎 on lines
  that have one.
- Job bridge: an assigned inspector's "🧾 Log my expense" on the job screen opens the quick-add
  with the job pre-selected (`/voucher?addjob=…`).
**Preserved (verified by tests):** the R5 maker≠checker approval guard, the PAID-reopen guard,
the DRAFT-only edit lock, the month-frozen lock, the monthly grid and pull-from-jobs, and the
per-job client-billable `expenses` — all unchanged. No new permission.
**Edge cases:** `docs/edge-cases/30-vouchers.md`.
**Tests:** `tests/test_module30_vouchers.php` (13 assertions). Suite 2594 passed / 3 pre-existing
baseline failures.

### Module 16 — Vendors / Vendor 360 · 2026-08-24
**Decision:** (A) consolidated scorecard + CAPA section, reusing the existing engines; vendor
financials deferred (no vendor financial data exists — a module of its own).
**Found:** the Vendor 360 is mature but its composite signals are scattered (quality performance,
delivery risk, expediting, qualification currency separately), there's no CAPA section, and no
vendor financial data.
**Added (additive, read-only, NO new scoring math):**
- `idems_vendor_scorecard()` — one card assembling the EXISTING signals: performance score+band
  (`idems_vendor_performance`, the headline — not recomputed), delivery risk, expediting
  reliability, qualification currency (status/valid-until/reassess-overdue), and open NCR/
  complaint counts.
- `idems_vendor_capas()` — the CAPAs linked to the vendor via its NCRs/complaints, de-duplicated.
- A **Scorecard card** at the top of the Vendor 360 and a **Corrective actions** panel.
**Preserved (verified by tests):** the scoring engines (`idems_vendor_performance`/`_delivery_
risk`/`_expediting_perf`), the qualification lifecycle, the existing panels/register/portal — all
unchanged. No new score formula; no schema change; no new permission. First automated coverage of
the composite score.
**Edge cases:** `docs/edge-cases/16-vendor-360.md`.
**Tests:** `tests/test_module16_vendor360.php` (12 assertions). Suite 2581 passed / 3 pre-existing
baseline failures.

### Module 15 — Client / Customer 360 · 2026-08-24
**Decision:** (A) fill the cheap missing sections; defer per-client margin to the canonical
profitability engine (Module 32) and a shared 360 scaffold to Module 49.
**Found:** `/customer` is the canonical Customer 360 and already rich, but missing an
issued-reports list (only a rejected COUNT existed), the full multi-site list (primary only),
satisfaction, margin, and forecast demand.
**Added (additive, read-only, reusing the gated `c360_load` assembly):**
- `c360_reports()` — the reports actually issued to the client (fills the biggest gap; gated by
  the reporting module) + an "Reports issued" panel linking to each `/document`.
- `c360_sites()` — the full site list (was primary-only) shown in the contacts panel.
- `c360_satisfaction()` — latest + average CSAT from `satisfaction_surveys` when the module is on
  and permitted; a Satisfaction card, skipped cleanly otherwise.
**Deferred (noted):** per-client margin → Module 32 (no bespoke profit formula here, per the
program rule); shared 360 component → Module 49; upcoming-demand forecast.
**Preserved (verified by tests):** the `c360_on()` gating + `c360_try()` crash-safety, the Money
section's single-source financials, `/partner` and `/ledger` — all unchanged. No schema change;
no new permission. First direct automated coverage of the Customer 360 assembly.
**Edge cases:** `docs/edge-cases/15-client-360.md`.
**Tests:** `tests/test_module15_client360.php` (12 assertions). Suite 2571 passed / 3 pre-existing
baseline failures.

### Module 04 — Calls (one user-facing lifecycle) · 2026-08-24
**Decision:** (A) present the unified lifecycle; raw system status for admins only. No writes to
either status column; R6 transition rules untouched.
**Found:** calls carry two status systems — legacy `status` (OPEN/FORWARDED/ALLOCATED) and
operational `op_status` (`CALL_STATUSES` = every spec lifecycle stage). They aren't synced;
`tosrm_call_status()` already returns the single value (op_status, else derived from legacy).
But the call detail **leaked the raw legacy status to every user**, the register showed a
job-count 3-state, and the real lifecycle label only appeared in the manager-gated TOSRM panel.
**Added (additive, read-only):** `call_status_label($call)` — the one user-facing lifecycle
label + pill tone, from `tosrm_call_status` over `CALL_STATUSES`. The call detail now shows this
unified label (raw legacy/op values shown **only to admins** as "system: …"); the register shows
the unified status pill per row (the existing scheduling chips kept, additive).
**Preserved (verified by tests):** the two status columns, `tosrm_derive_status`, the R6
transition rules, the TOSRM panel/playbook/nowband, and the register scheduling chips — all
unchanged. No new status; no writes; no new permission.
**Edge cases:** `docs/edge-cases/04-calls.md`.
**Tests:** `tests/test_module04_calls.php` (18 assertions). Suite 2559 passed / 3 pre-existing
baseline failures.

### Module 22 — Complaints (unified workflow + SLA) · 2026-08-24
**Decision:** (A) surface stage + SLA + tests — no schema change; effectiveness stays on CAPA.
**Found:** complaints already run the full path (create→ack→triage→investigate→decide→CAPA→
notify→close) with a real close-gate, plus two configurable SLA clocks with reminders. Gaps: no
single "where is this?" stage (status is only OPEN/CLOSED), SLA was two clocks not one badge,
and no lifecycle test coverage.
**Added (additive, read-only):** `cmp_stage($c)` — a derived stage (Received → Acknowledged →
Triaged → Investigated → Decided → Corrective action → Complainant told → Closed) with position
and next step, from the existing columns; and `cmp_sla($c)` — one consolidated SLA badge (On
track / Ack overdue / Decision overdue / Met / Met-late, the last read from stored dates). A
**progress strip + SLA badge** on the complaint detail, and a **stage + SLA** in the register's
State column.
**Preserved (verified by tests):** the close-gate (incl. upheld⇒CAPA-required), the §7.5.4 decide
impartiality gate, the SLA clocks/settings, and the portal intake — all unchanged. No schema
change; no new permission. Also fills the previously-absent lifecycle/SLA/close-gate coverage.
**Edge cases:** `docs/edge-cases/22-complaints.md`.
**Tests:** `tests/test_module22_complaints.php` (24 assertions). Suite 2541 passed / 3
pre-existing baseline failures.

### Module 13 — CAPA (configurable RCA) · 2026-08-24
**Decision:** (A) configurable methods + gate tests only — the optional structured 5-Why aid is
deferred.
**Found:** CAPA is strong — the spec's headline (an effectiveness gate that blocks closure:
verification required, `effective='NO'` refuses "done") already exists. Gaps: the RCA method list
was a hardcoded const (not configurable), and the close/verify gates had no unit tests.
**Added (additive):**
- `capa_rc_methods()` reads an **editable lookup** (`capa_rc_method`), seeded from the built-in
  defaults via `lk_ensure_type_map` in `capa_migrate` (its own type — no collision with the
  NCDCA `rc_method` lookup). The picker and the `capa-cause` validation both read it, so a body
  can add/rename methods through the masters editor; empty lookup → the const (backward
  compatible); legacy stored codes keep their labels.
- Filled the previously-missing behavioural coverage of `capa_close_missing` /
  `capa_close_block`: every requirement (root cause, method, similar-check, action, completion,
  verification) blocks closure until met, and a CAPA verified NOT effective cannot be closed.
**Preserved (verified by tests):** the effectiveness gate, the close checklist, the lifecycle,
and the NCDCA `rc_method` lookup — all unchanged. No schema change; no new permission.
**Edge cases:** `docs/edge-cases/13-capa.md`.
**Tests:** `tests/test_module13_capa.php` (14 assertions). Suite 2517 passed / 3 pre-existing
baseline failures.

### Module 12 — NCR (toward a Quality Case) · 2026-08-24
**Decision:** (A) surface & fix only — reuse the mature NCR tables; keep RCA/verification on
CAPA (Module 13).
**Found:** the NCR subsystem is mature (4-state lifecycle, gated closure, event timeline, six
auto-origins, bidirectional NCR↔CAPA link). Two concrete gaps: the per-job NCR chip linked
`/ncr?job=` but the register **ignored** the param (landing on the full register), and the
Job-360 Quality section was just a count chip.
**Added (additive):**
- Fixed the register to honour **`?job=`/`?report=`** (scoped, office-scope respected), so the
  job/report chip lands on that entity's nonconformities.
- `ncr_for_job()` + `ncr_reachable()` helpers.
- A Job-360 **Quality panel** (fold) listing the job's NCRs — ref, severity, status,
  owner/due, and the **linked CAPA** — each linking to the NCR detail, with a raise-NCR link.
  Gated on NCR reachability (permission + accreditation pack); auto-opens when any is open.
**Preserved (verified by tests):** the NCR lifecycle constants, `ncr_close_missing` gate, the
`ncr_create` funnel and the NCR↔CAPA coupling — all unchanged. No new permission.
**Edge cases:** `docs/edge-cases/12-ncr.md`.
**Tests:** `tests/test_module12_ncr.php` (13 assertions). Suite 2503 passed / 3 pre-existing
baseline failures.

### Module 25 — Impartiality / Conflict of Interest · 2026-08-24
**Decision:** (A) familiarity (repeated assignment) is an advisory Review, never a hard block;
threshold a setting (`impartiality_familiarity_jobs`, default 6).
**Found:** impartiality already HARD-blocks allocation on a declared OPEN/UNACCEPTABLE threat
(non-overridable), with a declare→decide lifecycle — but it computes nothing, has no
repeated-assignment/rotation logic, and the gate had **no test coverage**.
**Added (additive, read-only):** `inspector_impartiality($inspectorId, $ctx)` → CLEAR / REVIEW /
CONFLICT. CONFLICT mirrors `imp_block` (a declared blocking threat, client-scoped or
person-general); REVIEW adds the one computable COI signal — **repeated assignment to the same
client ≥ threshold in 12 months** (consider rotation) — plus a due/expired declaration. An
advisory verdict **pill on the suggested-inspector chips** at allocation (shown next to the
competence pill, only when not Clear). `imp_familiarity_threshold()` setting.
**Preserved (verified by tests):** the non-overridable declared-threat hard block, the register,
the decide lifecycle, the per-job declaration checkbox — all unchanged. No new hard control; no
new permission. Also fills the previously-missing behavioural coverage of `imp_block` (OPEN
blocks; client-scoped only blocks that client; a decided threat clears).
**Noted, not changed:** the impartiality screen is gated on `mod.competence.view` rather than
`mod.impartiality.view` — flagged for a future permission cleanup, left untouched.
**Edge cases:** `docs/edge-cases/25-impartiality.md`.
**Tests:** `tests/test_module25_impartiality.php` (17 assertions). Suite 2490 passed / 3
pre-existing baseline failures.

### Module 24 — Competence (eligibility at allocation) · 2026-08-24
**Decision:** (A) the verdict mirrors the existing gate; wrong-discipline / out-of-SBU are
advisory "Check", not new hard blocks.
**Found:** allocation already hard-blocks a lapsed **mandatory** cert (manager override), with an
opt-in authorisation gate; advisory `tosrm_competence_warn` for assigned jobs. But there was no
single per-(inspector × job) verdict shown **while choosing**, `skill_ids`/`sbus` were unused,
and the competence spine had **no automated test coverage**.
**Added (additive, read-only):** `inspector_eligibility($inspectorId, $ctx)` → a verdict
ELIGIBLE / EXPIRING / CHECK / BLOCKED that mirrors the save-time gate (lapsed mandatory cert =
BLOCKED; authorisation = BLOCKED only when enforcement is on) and adds advisory signals
(expiring cert = EXPIRING; wrong discipline / out-of-SBU = CHECK). A verdict **pill on the
suggested-inspector chips** at allocation, so the call is visible before submit — nobody hidden.
**Preserved (verified by tests):** the `pack_fire('work.assign')` hard gate, the override
authority, and the enforcement toggle — all unchanged. No new hard control; no new permission.
Also fills the previously-missing behavioural coverage of the gate (mandatory vs non-mandatory).
**Edge cases:** `docs/edge-cases/24-competence.md`.
**Tests:** `tests/test_module24_competence.php` (15 assertions). Suite 2473 passed / 3
pre-existing baseline failures.

### Module 21 — Hold / Witness Points · 2026-08-24
**Decision:** (A) warn loudly, no new hard blocks — the Release Note stays the one hard gate.
**Found:** the subsystem already exists (hw_points; HOLD/WITNESS/REVIEW/CLEARANCE;
OPEN/CLEARED/WAIVED/CANCELLED; auto-derived + manual; audited), hard-blocks the Release Note
(master override) and is checked at report submit; advisory elsewhere. Shown on job / report /
release checklist / register / nav. Missing at the completion moments and on lists.
**Added (additive, advisory):** `hwp_job_summary($jobId)` (open count + by-type + label) and
`hwp_open_counts_for_jobs($ids)` (batched, no per-row query storm); a prominent open-points
**warning on the job-close screen** and on the **day-by-day completion panel** — "the
manufacturer should not proceed/despatch until cleared or waived", explaining that closing does
not clear them. (The schedule board is a person×day availability matrix — a poor fit for a
per-job badge — so it was deliberately not badged; managers see open points via the job blockers
header, the /hold-points register and the nav badge.)
**Preserved (verified by tests):** the Release Note hard gate, the hw_points model, the job
hold/witness fold, and the register — all unchanged. No new hard block; no new permission.
**Edge cases:** `docs/edge-cases/21-hold-witness.md`.
**Tests:** `tests/test_module21_hold_witness.php` (11 assertions). Suite 2459 passed / 3
pre-existing baseline failures.

### Module 05 — Jobs (Job 360) · 2026-08-24
**Found:** the job screen is already a rich 360 (4 tabs, a nowband next-action heuristic, a
glance chip index, and panels for schedule/site/reports/costs/billing). The gap vs the
universal-UX rule: no single **Stage · Owner · Blockers** header (the nowband gives the next
step but not the owner, and blockers were scattered across separate banners).
**Added (additive, read-only):** `job_now($job)` → stage label, current **owner** (Coordinator
→ Inspector → Reviewer/approver → Inspector-to-close → —, reflecting Module 07 review state),
and a **consolidated blockers list** (lock, HOLD reasons, open hold/witness points, bills
required) each linking to its panel. A compact **Stage · With · Blockers** strip now sits above
the nowband. Money blockers are hidden from a field inspector.
**Preserved (verified by tests):** the four tabs, every fold pinned by the declutter tests, the
`job_glance` keys, the `$fieldInspector`/`$canSeeProfit` gates, and the deep-link opener — all
untouched (the header is a new strip above the tab container). No new permission.
**Edge cases:** `docs/edge-cases/05-job-360.md`.
**Tests:** `tests/test_module05_job360.php` (14 assertions). Suite 2448 passed / 3 pre-existing
baseline failures.

### Module 06 — Inspection / IDEMS core + Applicability · 2026-08-24
**Decision:** (A) surface & formalize only — reuse the existing applicability mechanism, do
not add a second (inspection-type) mapping engine.
**Found:** applicability already existed — per-job `deliverables`, a `service_report_map`
(service→report types, client overrides), a soft-narrowed create form (never to nothing, with
a "Need a different one?" escape), and a per-job reports panel. So this module surfaces it,
not rebuilds it.
**Added (additive, read-only):** `idems_job_applicability($job)` — the applicable formats with
**where each came from** (service agreement / client-specific / chosen on the call) and the
**not-applicable** formats (catalogue minus agreed, minus already-written). The job "Reports on
this job" panel now shows the source note per format and a **collapsed "Other formats — not
applicable to this job"** list, each still one click to raise anyway (flagged "not allocated").
**Preserved (verified by tests):** the create form's never-narrow-to-nothing + escape hatch; the
deliverables/service-map mechanism; no report type ever hidden; no new permission.
**Edge cases:** `docs/edge-cases/06-applicability.md`.
**Tests:** `tests/test_module06_applicability.php` (13 assertions). Suite 2434 passed / 3
pre-existing baseline failures.

### Module 08 — Report Release / Issue · 2026-08-24
**Added (additive, read-only):** a **"Ready to issue" panel** on the report screen that
previews the same gates the finalize handler enforces — approval complete, issuer≠approver
(viewer-specific), QA-critical, and the instrument-calibration/signer accreditation pack —
each shown as ✓ ok / ⚠ warn / ⛔ block with its reason, plus an overall ready/not-ready line
and **immutability/revision** clarity ("issuing locks it; corrections are a new revision").
Shown only for an APPROVED, not-yet-finalized report to a finalizer.
**Preserved (verified by tests):** the finalize handler and every gate unchanged — issuer≠
approver, approval-complete, QA-critical audited override, uncalibrated-instrument hard block,
unauthorised-signer warn+NCR, immutable seal/snapshot/freeze. `pack_fire` is a pure evaluator,
reused read-only. No new permission.
**Edge cases:** `docs/edge-cases/08-report-release-issue.md`.
**Tests:** `tests/test_module08_issue_readiness.php` (13 assertions). Suite 2421 passed / 3
pre-existing baseline failures.

### Module 07 — Vetting / Technical Review / Approval · 2026-08-24
**Decisions taken:** self-review → soft warning + acknowledge (11.1-A); notify on return →
**also email** (11.2); status labels disambiguated (11.3-yes).
**Added (additive UX, no control weakened):**
- **Provenance strip** on the report screen — Prepared / Vetted / Approved / Issued, each
  with actor + date or pending/not-required, built read-only from stored fields.
- **Return-reason banner** — a returned/rejected report now shows the reviewer's actual
  reason, who and when, prominently at the top (was "read the remark below").
- **Status disambiguation** — REJECTED → "Rejected — revise & resubmit"; a returned draft →
  "Returned for correction" (display only; stored status unchanged).
- **Soft self-review acknowledgement** — if you prepared a report, vetting/approving your
  own work now needs a one-tick confirmation (never blocks; preserves master exception).
- **Email to the inspector on return** — reject / send-back / vetting-return now notify the
  inspector (previously no notification fired at all). Best-effort, never blocks.
- **Segregation visibility** — the approver is told why they cannot issue their own approval.
**Preserved (verified by tests):** issuer≠approver finalize guard; mandatory return reasons;
all gates and transitions; no new permission; report status model untouched.
**Edge cases:** `docs/edge-cases/07-vetting-review-approval.md`.
**Tests:** `tests/test_module07_quality_gate.php` (25 assertions). Suite 2408 passed / 3
pre-existing baseline failures.

### Module 39 — My Work · 2026-08-24
**Added (non-destructive):**
- New `/my-work` route + `views/ops/my_work.php`: one role-relevant landing page that
  groups the existing pending-task buckets into lanes (Do now · My reports · My jobs ·
  My money · Quality), phone-first for field inspectors.
- New **"Returned for correction"** bucket: reports a vetter RETURNED or an approver SENT
  BACK (reset to DRAFT) are now surfaced distinctly from ordinary new drafts — via a new
  `/documents?mine=returned` filter — so an inspector always sees a report has come back.
- Nav gains a top-level **My Work** destination; the dashboard "Your pending tasks" panel
  links to it.
**Preserved:** `ops_pending_tasks()` is the single source (reused, not duplicated); the
dashboard/operations-home panel, `/my-jobs`, `/vouchers`, `/documents`, all statuses and
permissions unchanged. No new permission. Report status model untouched.
**Edge cases handled:** see `docs/edge-cases/39-my-work.md` — returned-vs-rejected disjoint
(no double count), fresh draft excluded, unlinked-inspector notice, empty state, office
user sees no personal lanes, lane grouping, per-tile pluralised links, accessibility.
**Tests:** `tests/test_my_work.php` (16 assertions). Suite 2385 passed / 3 pre-existing
baseline failures unrelated to this change.
**Commit:** on branch `claude/quotation-management-workflow-5dokb2`.
