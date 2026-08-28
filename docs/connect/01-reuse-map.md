# Connect — Reuse Map (blueprint modules → EXAACT engines)

Companion to [`00-integration-program.md`](00-integration-program.md). One row per
blueprint module / Part-F item, mapped to the EXAACT engine(s) that absorb it, with
the verb (REUSE / EXTEND / CONNECT / CONFIGURE / BUILD) and a note. This is the
evidence behind the claim that most of the platform already exists in EXAACT.

Verbs: **REUSE** = exists, use as-is · **EXTEND** = add to an existing engine ·
**CONNECT** = wire existing pieces together · **CONFIGURE** = a switch / seed data ·
**BUILD** = genuinely new.

All engine paths are under `phpapp/lib/` unless noted.

## Part B — Taxonomy (master data)

| Blueprint | EXAACT engine | Verb | Note |
|---|---|---|---|
| B1–B6 sectors/equipment/material/discipline/stage/standards/certs | `industry.php`, `methods.php`, `competence.php`, `lookups.php` | EXTEND/CONFIGURE | Industry templates + methods + competence schemes exist; add the versioned taxonomy master tables as admin-extensible seed data (K0). |

## Part C — Modules M1–M19

| M | Module | EXAACT engine(s) | Verb | Note |
|---|---|---|---|---|
| M1 | Identity & Access | `access.php`, `identity.php`, `security.php`, `portal.php`, `mghsso.php` | EXTEND | `can()`/roles/2FA/session exist; add open self-service roles per decision 5a. KYC hooks via `identity.php`. |
| M2 | Onboarding & Verification | `competence.php` (P1 vault + `verify_status`), `customforms.php`, `datacontrol.php` | REUSE/EXTEND | Per-item verification queue, expiry reminders, tiers already largely built in the P1 credential vault. Add verification *tiers* labelling. |
| M3 | Digital Passport | `competence.php`, `inspectorprofile.php`, `qr.php` | EXTEND | Make the vault a **public, shareable, QR-verifiable** page (K1). QR encoder already owned (`qr.php`). |
| M4 | Preference Profile | `inspectorprofile.php`, `pdso.php`, `schedule.php` | EXTEND | Work-type / mobility / availability / commercials as structured filterable fields feeding matching. |
| M5 | Assessment Engine | — | BUILD | The one genuinely-missing module (K11). Scenario banks, one-at-a-time player, anti-cheat, badge tiers. Needs SME sign-off (5d). |
| M6 | Trust & Reputation | `rating.php`, `competence.php`, `trust.php`, `satisfaction.php`, `tapi_score.php` | BUILD (compose) | Trust Score 0–1000 is a **read-only rollup** over ratings + verification + reliability + conduct (K5). `trust.php` supplies geo-evidence, not the score. |
| M7 | AI Concierge | `ai.php`, `customforms.php`, `leads.php`, `opportunities.php` | BUILD over AI | Conversation-before-forms intake → structured requirement/call (K4). "Confirm, not create." |
| M8 | Matching & Recommendation | inspector-suggestion + competence eligibility (P2), `pdso.php`, `search.php` | EXTEND | Ranked cards with plain-language reasons; availability freshness; Area-Lead fallback (placement-fee engine already exists, P4c). |
| M9 | Job Lifecycle | calls→jobs→allocation, `trust.php` (geo check-in), `attend.php`, `joblock.php`, `stagegate.php`, `hwpoints.php` | EXTEND | Protocol-vs-outcome rule + hold-points map onto existing job/stage-gate/hold-point engines. New statuses per 5b. |
| M10 | Bulk / Crew | `bulk.php`, `schedboard.php`, `recruit.php` | EXTEND (schema-ready) | Position-manifest layer; productized later, schema-ready at MVP. |
| M11 | Reports & Custody | `idems.php`, `idems_autoform.php`, `pdf.php`, `qr.php`, `retention.php`, `controldocs.php` | EXTEND | O/D/T counterparts + SHA-256 + QR verify + no-delete revisions + retention. IDEMS already does serials/IRN/issue. |
| M12 | Payments & Escrow | `books.php`, `billable.php` (P4), `billing.php`, `receivables.php`, `settlement.php`, `booksbridge.php` | EXTEND/BUILD | Escrow = hold/release **state** over the books ledger (K8). Money truth stays in books. Sign-off 5c. |
| M13 | Ratings & Feedback | `rating.php`, `satisfaction.php` | EXTEND | Two-way structured ratings; company-side reputation mirror. |
| M14 | Disputes & Mediation | `complaints.php`, `ncr.php`, `ncdca.php`, `qualitycase.php`, `reportreview.php` | EXTEND | Evidence-first flow; technical-review-panel for finding disputes; feeds both reputations (K9). |
| M15 | Notifications | notifications/`compose.php`, `crm.php` channels | CONNECT | WhatsApp/SMS/email/in-app templates over existing notification plumbing; external blocker on WhatsApp templates. |
| M16 | Admin & Ops Console | `ops.php`, `orgadmin.php`, `superadmin.php`, `cpanel.php`, `advisor.php` | EXTEND | Verification queue, job monitor, payout control, dispute desk, master-data manager — mostly existing ops surfaces. |
| M17 | Analytics & Market Intel | `mis.php`, `crmdash.php`, `tapi_dash.php`, `indexes.php` | EXTEND | Funnel/liquidity/repeat-rate/heatmaps/rate-benchmarks over existing MIS. |
| M18 | Career Assistant (P3) | `competence.php`, `advisor.php` | EXTEND (later) | Expiry-driven renewal nudges; Phase 3. |
| M19 | Integration Gateway | `books.php`, `crm.php`, `finevent.php`, `booksbridge.php` | REUSE (internal) | Per §3 divergence: internal reuse of money/CRM engines instead of external webhooks. |

## Part D — Ecosystem integration

| D | Item | Disposition | Note |
|---|---|---|---|
| D1–D6 | Event bus / Hub / Books / CRM / AI / public API | **Superseded by "milk and sugar"** | We are *inside* EXAACT, so integration is native reuse of `books.php` (D3), `crm.php` (D4), `ai.php` (D5), `finevent.php` (event stream). External public API (D6) only if third parties are later needed. |

## Part F — Operational governance & field intelligence

| F | Item | EXAACT engine(s) | Verb | Note |
|---|---|---|---|---|
| F1 | Commercial Protection (term-sheet) | `terms.php`, `agreement.php`, `contracts.php`, `recruit.php` (`req_commercials`) | EXTEND | Term-sheet folded into the booking agreement; job can't publish without every field. |
| F2 | Scope Freeze / change orders | `stagegate.php`, `hwpoints.php`, `idems.php` | BUILD/EXTEND | Item-count against frozen scope; change order on creep. New statuses per 5b. |
| F3 | Site Readiness Verification | `pdso.php` (mobilization readiness, P2), `customforms.php` | EXTEND | Extend the readiness verdict into a blocking pre-mobilization checklist. |
| F4 | Waiting-Time Policy | `attend.php`, `trust.php` (check-in/out), `timesheet.php` | EXTEND | Add inspection start/end markers; auto-apply agreed waiting charges. |
| F5 | Site Behaviour Scores (P2) | `satisfaction.php`, `vendor360.php`, `rating.php` | EXTEND (later) | Needs volume; P2. |
| F6 | Conflict Prediction (P2) | `advisor.php`, `mis.php`, `decisionrules.php` | EXTEND (later) | Pure analytics over data already collected. |
| F7 | Ethical Pressure Register (P2) | `complaints.php`, `confidentiality.php`, `disclosure.php` | BUILD (careful) | Admin-only, legally sensitive; design reviewed by counsel before ship. |
| F8 | Inspector Safety (SOS / missed-checkout) | `trust.php` (check-in/out), `geofence.php`, notifications | BUILD-basic | SOS button + missed-checkout alert at MVP. |
| F9 | Knowledge Capture | `customforms.php`, `vendor360.php`, `tasks.php` | BUILD-light | One question at job close, attached to site/vendor, surfaced to next inspector. |
| F10 | Fraud Detection (basic) | `dedupe.php`, `trust.php` (geo), `qr.php`, `identity.php` | EXTEND | Perceptual/document-hash dedupe + GPS-spoof heuristics; flags → quiet review, never auto-penalty. |
| F11 | Black Swan Register | ops runbook (doc) first | DOC | SOP manual first; incident console later (P2). |
| F12 | Lessons-Learned Loop | `complaints.php`, `tasks.php`, `capa.php` | EXTEND (process) | Mandatory "what should change?" field routes to a backlog. |
| F13 | AI Operations Advisor | `advisor.php` | EXTEND | `advisor.php` already does "problem → cost → fix → who"; add requirement-specific readiness scoring (K12). |

## Headline

Of ~19 modules + 13 Part-F items, the large majority are **REUSE / EXTEND / CONNECT**
over engines EXAACT already ships. The genuinely-new **BUILD** is concentrated in:
the open two-sided **marketplace** (post/apply, K2), the reputation **Trust Score**
(K5), the **Assessment player** (K11, M5), and specific Part-F mechanisms (scope
freeze, safety, knowledge capture, ethics register). This is why the work is an
*integration*, not a rewrite.
