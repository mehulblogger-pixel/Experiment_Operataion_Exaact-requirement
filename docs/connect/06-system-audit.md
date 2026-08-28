# Connect — Full System Audit (against the ITSN vision)

**What this is:** a thorough, file-referenced audit of the entire platform — the mature
EXAACT operations core **and** the new Connect marketplace — measured against the
owner's vision: *one platform in India for everyone with a technical qualification —
ITI/diploma → engineers → MBA → higher designations*, built as a lean trust network
first and expanded after product-market fit.

**Method:** four parallel code surveys (operations/delivery; money/commercial;
identity/access/comms/infra; talent-model/AI/analytics), each returning file:function
references. Format below: **What's there → Gap → How we'll do it → How it's used.**

---

## 0. Executive summary

**The moat is real and already built.** The operations core is deep and
production-grade: scheduling + capacity, attendance with genuine anti-fraud geo
(impossible-travel, round-coordinate, photo/entry-exit enforcement), the
calls→jobs→deputation spine, a very deep inspection-report engine with tamper-evident
QR verification, a GST-correct books ledger where "money follows status", full
costing/profitability, contracts with a blocking commercial gate, signed-key licensing,
mature RBAC/2FA/multi-tenancy, an offline PWA, a reusable recruitment/ATS engine, a
multi-provider AI layer, and a money-ranked "Advisor" that is a genuine USP.

**The trust network is ~75% built** (this session's Connect work): post → search/match →
apply → shortlist → award → rate → dispute, a self-service freelancer pool, public
Passport, Trust Score, crew booking, an award→invoice bridge, and an organisation-account
model with self-onboarding.

**But five things stand between here and the vision**, and two of them are structural:

1. **Two disconnected talent silos** — the marketplace intelligence (matching, trust,
   ratings, passport, credential vault, eligibility) is hard-wired to the internal
   `inspectors` table; the self-registered `cx_professionals` pool the mission depends on
   gets **none** of it. *(The marketplace recommender literally matches from `inspectors`,
   not the freelancer pool.)*
2. **Inspection-only ontology** — no generic qualification framework (NSQF, ITI/NCVT
   trades, diploma/degree, MBA/managerial, HSE/commissioning/audit as first-class).
3. **No identity/KYC verification** of any person or company — "verified" is the promise,
   and today only *certificates* are manually attested; identity is self-asserted.
4. **No in-app messaging** — only one-way notifications; people fall back to WhatsApp.
5. **No public-registration governance** — no moderation/verification queue or admin CRUD
   for the open pool; and no SMS/WhatsApp channel, no payment/escrow rail.

**Verdict:** we have an unusually strong *operations* platform with a *good* trust-network
layer bolted on — but to become the ITSN, the next work is **unifying the talent model,
generalising the taxonomy, and building the trust/communication/verification spine** the
open network needs. None of it fights the codebase; it's the natural next layer.

---

## PART 1 — What's there (maturity map)

| Domain | Key engines (file) | Maturity |
|---|---|---|
| Scheduling / capacity / mobilization | `schedule.php`, `schedboard.php`, `pdso.php` | 🟢 Mature core |
| Attendance + anti-fraud geo | `attend.php`, `attendreview.php`, `geofence.php`, `trust.php` (EXIF) | 🟢 Mature core |
| Calls → jobs → deputation → timesheet | `ops.php` (550 KB), `pdso.php`, `timesheet.php` | 🟢 Mature core |
| Inspection report engine + O/D/T + QR verify | `idems.php` (767 KB), `urfe/uire/urade`, `pdf.php`, `qr.php` | 🟢 Deepest subsystem |
| Documents / custody / data governance | `controldocs.php`, `datacontrol.php`, `assets.php` (retention thin) | 🟢 Solid |
| Books ledger / GST / receipts / credit notes | `books.php`, `booksui.php`, `receivables.php`, `booksbridge.php` | 🟢 Mature core |
| Billable bridge (ops→commercial→invoice) | `billable.php`, `connect_bridge.php` | 🟢 Mature core |
| Costing / profitability / rev-recon | `costing.php`, `callprofit.php`, `projcosting.php`, `revrecon.php` | 🟢 Mature core |
| Contracts + commercial gate | `contracts.php`, `terms.php` | 🟢 Mature core |
| Packaging / licensing / signed keys / seats | `licence.php`, `packs.php`, `licencekey.php`, `licenceissue.php` | 🟢 Mature core |
| RBAC / permissions / office scoping | `access.php` | 🟢 Mature |
| Auth security (hashing, TOTP 2FA, lockout) | `security.php`, `helpers.php`, `mghsso.php` | 🟢 Mature |
| Multi-tenancy (subdomain→own DB) + SSO | `tenants.php`, `mghsso.php`, `cpanel.php` | 🟢 Mature |
| Offline PWA (service worker + sync-state) | `sw.js`, `assets/js/offline.js`, `manifest.php` | 🟡 Moderate (no offline photos) |
| Recruitment / ATS + assignment commercials | `recruit.php`, `recruit_cc.php` | 🟢 Mature, domain-neutral |
| AI multi-provider layer | `ai.php` (OpenAI/Anthropic/Gemini/…) | 🟢 Plumbing mature; wired only to doc-extraction |
| Analytics / MIS / KPI + money-ranked Advisor | `mis.php`, `tapi*.php`, `advisor.php`, `crmdash.php` | 🟢 Mature; inspection-centric |
| **Connect marketplace** (post/apply/match/award/rate/dispute) | `connect_market.php`, `connect_match.php`, `connect_ratings.php`, `connect_disputes.php` | 🟢 Built this session |
| **Freelancer pool + portal** (profile/apply/search) | `connect_pro.php`, `cx_professionals` | 🟡 MVP, siloed |
| Passport (public, QR) + Trust Score | `connect_passport.php`, `connect_trust.php`, `qr.php` | 🟢 Built; inspector-keyed |
| Crew booking / award→invoice bridge | `connect_crew.php`, `connect_bridge.php` | 🟢 Built |
| Org accounts + entitlements + onboarding | `connect_org.php` (`/join`) | 🟡 Representation only (no live gating) |

---

## PART 2 — The gaps that matter (with the plan for each)

> **Progress (#1, step 1 ✅):** the marketplace **recommender and the public
> Passport now span both pools.** `connect_match_for_requirement` scores internal
> inspectors *and* self-registered professionals against a requirement (inspectors
> keep full eligibility/rating/trust scoring; professionals score on
> skills/availability and show honestly as **New / Unverified** until verified &
> rated); recommendation cards carry a **Freelancer** badge, the right Passport
> link (`/p/<token>`) and the right apply field; and `connect_passport_lookup`
> resolves a professional's token too. 12 tests; fixes the "recommender ignores
> freelancers" bug. **Remaining:** a single `cx_person` identity + cross-pool
> ratings/Trust Score + an entity-agnostic credential vault (follow-on slices).

> **Progress (#2 ✅):** the **qualification & role taxonomy** is built — the
> ITI→MBA answer to the mission. `lib/connect_qualtax.php` seeds five additive
> `cx_*` master tables from `data/connect_qualifications.json`: an **NSQF-anchored
> qualification ladder** (20 levels: school → ITI/apprentice → vocational →
> diploma → degree → PG/MBA → doctorate → professional), **20 job families** with
> **52 roles** nested under them (each with its minimum entry band, blue-collar to
> manager), **28 ITI trades** and **30 professional certifications** (welding, NDT,
> API, PMP, Six Sigma, NEBOSH, ISO LA, …). **Inspection is now just one vertical**
> (family `INSP`), not the whole ontology. A read-only Zero-Training screen at
> `/connect-qualifications`, and six additive, optional columns on
> `cx_professionals` (`job_family_code`, `role_code`, `qual_level_code`,
> `iti_trade_code`, `cert_codes`, `years_experience`) so a person can state where
> they sit on the ladder. Idempotent seed; no new permission or status.
> **Fully configurable — nothing hard-coded:** every family, role, level, ITI
> trade and certification is runtime data with an admin **Configure** editor
> (add / edit / switch on–off / delete), gated exactly like Lookups
> (`is_admin_level()` to manage; `is_master()` to hard-delete a built-in row).
> Seeded rows are marked `is_system=1`; switching one off hides it from the
> marketplace without breaking references. 50 tests. **Remaining:** wire the
> columns into the `/pro` register/edit form and the requirement builder, and let
> matching use family/role/level (follow-on slices).

### GAP 1 — Two disconnected talent pools *(structural; highest priority)*
- **What's there:** `inspectors` (internal, fully instrumented — competence, eligibility,
  vault, rating, passport, trust, matching) **and** `cx_professionals` (public freelancer
  pool — an isolated MVP). Matching (`connect_match.php:116`), Trust
  (`connect_trust.php`), Ratings (`cx_ratings`), Passport, Credential Vault and Eligibility
  are **all wired to `inspectors` only**. The recommender ignores the freelancer pool.
- **Gap:** The self-registered professionals the mission depends on receive **none** of
  the platform's intelligence. Two silos = a broken promise and duplicate logic.
- **How we'll do it:** introduce a **unified person model** (an entity-kind-agnostic
  "professional" identity that both an internal inspector and a self-listed freelancer
  resolve to), then point `connect_match` / `connect_trust` / `connect_ratings` /
  passport / eligibility at the unified pool. Additive: a `cx_person` bridging id + a
  read-view that both tables map to; migrate readers one at a time behind tests.
- **How it's used:** a freelancer who signs up is instantly matchable, rateable, gets a
  Trust Score and a Passport — the same as an internal inspector. One graph, one identity.

### GAP 2 — Inspection-only ontology *(structural; the broadened-mission blocker)*
- **What's there:** `data/connect_taxonomy.json` + `cx_disciplines`/`cx_inspection_stages`/
  `cx_certifications_registry` model industrial NDE/welding/pressure-equipment inspection
  **exclusively** (CSWIP, ASNT, API 510/570, fit-up/PWHT/hydro/FAT).
- **Gap:** no representation of **ITI/NCVT trades, NSQF levels, diploma/degree
  qualifications, MBA/managerial designations, or job families** beyond inspection.
- **How we'll do it:** add a **layered qualification & role taxonomy** — job families →
  roles → qualifications (NSQF level, ITI trade, diploma, degree, professional cert) →
  skills — with **inspection kept as one vertical** on top of it. Additive `cx_*` master
  tables seeded like the existing taxonomy; the profile captures structured
  education/qualification instead of only free-text.
- **How it's used:** an ITI fitter, a diploma draughtsman, a degree QA engineer, an MBA
  project manager and a CSWIP welding inspector all describe themselves in one consistent
  vocabulary — and companies search across all of them.

### GAP 3 — Identity & business verification absent *(launch-blocking for "verified")*

> **Progress (#3 ✅):** the **verification & moderation engine** is built.
> `lib/connect_verify.php` adds a `cx_verifications` ledger and a real
> **tier ladder** (Registered → ID-verified → Credential-verified → Proven) that
> now writes `cx_professionals.verification_tier` (previously never elevated).
> **Deterministic pre-screens** validate PAN (format), GSTIN (mod-36 checksum) and
> Aadhaar (Verhoeff) — but **honestly**: a format pass is NOT verification, it
> queues as PENDING; a format fail is auto-rejected; the tier moves only on a real
> **VERIFIED** decision. A **moderation desk** at `/connect-verify` (coordinator
> level, no new permission) approves/rejects; professionals submit their own checks
> from `/pro/verify`. A **pluggable provider seam** (`connect_verify_provider_for`)
> lets DigiLocker / a KYC vendor / face-liveness slot in under the same keys with
> no other change. Only the **masked** identifier is stored (last 4). The Passport
> surfaces the tier as an honest badge. 25 tests. **Remaining:** wire an actual KYC
> provider, org (GSTIN/CIN) tier, and document upload/storage (follow-on slices).
- **What's there:** an identity-**document vault** (`identity.php` — stores PAN/Aadhaar/
  passport scans, DPDP-aware, masked, access-logged) and **manual** certificate
  attestation (`competence.php` `verify_status`). `verification_tier` on `cx_professionals`
  is written once as `registered` and **never elevated**.
- **Gap:** neither a **person's** nor a **company's** identity is *verified* anywhere. No
  gov-ID/DigiLocker/PAN/Aadhaar/face/liveness, no business-registration (GST/CIN/Udyam),
  no KYC provider wired.
- **How we'll do it:** a **verification engine** with pluggable providers (DigiLocker for
  ID/education, PAN/GSTIN validation APIs, an optional face/liveness step), driving a
  real **verification-tier ladder** (Registered → ID-verified → Credential-verified →
  Proven) surfaced on the Passport and Trust Score. Start with document + deterministic
  checks; add a KYC vendor behind the same seam.
- **How it's used:** a company sees a **green "identity verified"** badge and hires with
  confidence; a fake profile can't reach the verified tiers. This is the core trust
  differentiator competitors (job boards) don't have.

### GAP 4 — No in-app messaging / chat *(launch-blocking)*

> **Progress (#4 ✅):** **two-way in-app messaging** is built.
> `lib/connect_msg.php` adds `cx_messages` + `cx_message_reads` — a thread per
> **engagement** (keyed to a `cx_applications` row). The **staff desk** chats at
> `/connect-messages` (inbox + thread + reply; coordinator level, no new
> permission) and each applicant row on a requirement has a 💬 Message link; the
> **professional** has their own inbox at `/pro/messages`, scoped to their own
> engagements. Per-reader unread cursors (never counting your own messages) drive
> nav badges on both sides. The thread is retained as the hiring + dispute record
> (blueprint M15). The engine is identity-agnostic (staff/professional/client/
> vendor/inspector). 22 tests. **Remaining:** attach the client & vendor portal
> surfaces to the same threads, and email/WhatsApp nudges (folds into #5).
- **What's there:** one-way notification feeds (`portal_notifications`, `cvp_notify_*`),
  SMTP email, and a "conversation-before-forms" concierge (not user-to-user).
- **Gap:** no **two-way messaging** between client ↔ professional ↔ agency, per
  engagement. People leave the platform for WhatsApp — the exact thing we replace.
- **How we'll do it:** an additive **per-engagement message thread** (`cx_messages` keyed
  to a requirement/application), with in-app inbox + notification/email nudges, retained
  as dispute evidence (the blueprint's M15). Reuses the existing notification plumbing.
- **How it's used:** a client asks the shortlisted welder "can you start Monday?" inside
  the platform; the thread becomes part of the hiring record and the dispute trail.

### GAP 5 — Public-registration governance & channels *(launch-blocking at India scale)*

> **Progress (#5 ✅, channel):** the **WhatsApp / SMS / Email channel** is built
> behind the notification seam. `lib/connect_channels.php` adds
> `cx_channel_templates` (runtime-editable, compliance-gated) + `cx_channel_messages`
> (outbound log) + per-channel **consent** columns on `cx_professionals`. Honest by
> **delivery mode** (setting `connect_channels_mode`): `off` (default) records
> messages as QUEUED — nothing sent, nothing faked; `log` simulates (LOGGED);
> `live` hands APPROVED-template messages to a **registered provider** via the
> `connect_channel_providers` seam (WhatsApp Business / SMS gateway drop in with no
> other change). Consent-first: professionals opt in per channel from `/pro`
> profile (WhatsApp/SMS need a mobile); only masked contacts are stored. Templates
> for new-message / shortlisted / awarded / job-match, editable + approvable at
> `/connect-channels` (admin). A desk message auto-nudges an opted-in professional
> (ties #4 → #5). 17 tests. **Note:** the moderation-queue half of this gap shipped
> as #3; **escrow** is #10 (CA/RBI/legal). **Remaining:** wire a real BSP + approve
> live templates; DLT registration; job-match trigger on new requirements.
- **What's there:** self-registration for freelancers (`/pro`) and orgs (`/join`); no
  admin CRUD or moderation over `cx_professionals`; no SMS/WhatsApp; no payment rail.
- **Gap:** at open-registration scale there is **no fraud/quality moderation queue**, the
  audience's primary channel (**WhatsApp/SMS**) is unwired, and there is **no
  escrow/gateway** to intermediate payments.
- **How we'll do it:** (a) a **moderation/verification queue** (approve, verify-tier,
  suspend, merge duplicates) over the pool; (b) a **WhatsApp Business + SMS** channel
  behind the notification seam (templates approved separately); (c) **escrow** as a later
  Phase-6 slice behind CA/RBI + legal sign-off (K8), reusing the books ledger as the money
  truth.
- **How it's used:** ops keep the pool clean and trusted; professionals get job alerts on
  WhatsApp without opening the app; payments (later) flow safely through the platform.

### GAP 6 — Matching/Trust don't reach freelancers; no AI ranking *(coherence + differentiation)*
- **What's there:** deterministic, explainable matching (`connect_match.php`) and Trust
  (`connect_trust.php`) — both **inspector-keyed**; a mature **AI provider layer**
  (`ai.php`) wired only to document extraction, **never to matching**.
- **Gap:** the freelancer pool isn't matched or scored; the AI layer that exists doesn't
  power the marketplace intelligence.
- **How we'll do it:** after GAP 1 (unified pool), point matching/trust at it; then add an
  **optional AI/semantic layer** (embeddings over profiles + requirements via `ai_chat`)
  to complement the rules — recommend, not just filter.
- **How it's used:** "I need an API 570 inspector tomorrow in Jamnagar" returns a ranked,
  reasoned shortlist drawn from the whole pool, blending rules + AI.

### GAP 7 — No labour-market analytics *(strategic moat)*
- **What's there:** deep inspection/CRM/finance MIS + the money-ranked Advisor; only a
  thin `cx_market_summary` for the marketplace.
- **Gap:** no **talent supply-vs-demand, fill-rate, time-to-fill, rate-benchmark, pool-
  growth** intelligence — the data a talent network monetises.
- **How we'll do it:** a marketplace analytics layer over the `cx_*` tables (reusing the
  TAPI/Advisor patterns) — demand heatmaps by discipline/geo, fill funnel, rate bands.
- **How it's used:** ops see where demand outstrips supply; later, anonymised market
  intelligence becomes a paid product (blueprint M17).

---

## PART 3 — The broadened mission (ITI → MBA): generality verdict

| Engine | Generality | Note |
|---|---|---|
| Recruitment / ATS (`recruit.php`) | 🟢 Largely neutral | Designation/qualification/experience/cost-model fields + AI JD parsing already generic |
| AI provider layer, RBAC, tenancy, Advisor | 🟢 Neutral | Carry over with minor vocabulary generalisation |
| Books / costing / billing | 🟢 Neutral | Money engine is domain-agnostic |
| Taxonomy + credential vault + eligibility | 🔴 Inspection-only | Needs the layered qualification framework (GAP 2) + entity-kind-agnostic vault (GAP 1) |
| Marketplace matching / trust / passport | 🟡 Built but siloed + inspection-flavoured | Unify pool (GAP 1) + generalise vocab (GAP 2) |

**Lowest-effort path to the broadened mission:** GAP 1 (unify the pool) + GAP 2
(qualification taxonomy) unlock ITI→MBA breadth; the recruitment engine, money core,
AI plumbing, RBAC/tenancy and Advisor already carry over.

---

## PART 4 — Prioritised, launch-first backlog

Ranked by "distance to a credible, trusted, lean launch of the ITSN".

| # | Item | Type | Why now | Effort |
|---|---|---|---|---|
| **1** | **Unify the talent pool** (one person model; point match/trust/ratings/passport/vault at it) — _match + passport done ✅; cx_person/cross-pool-trust/vault remain_ | Structural | Without it the freelancer pool is a dead end; fixes the "recommender ignores freelancers" bug | M–L |
| **2** | **Qualification & role taxonomy** (NSQF / ITI / diploma / degree / MBA / job families; inspection as a vertical) — **✅ master data + screen done; profile/matching wiring remains** | Structural | Unlocks the ITI→MBA mission | M |
| **3** | **Freelancer verification + moderation queue** (tier ladder; admin CRUD; DigiLocker/PAN/GST checks) — **✅ engine + queue + deterministic checks + tier ladder + provider seam done; real KYC provider & doc upload remain** | Trust | "Verified" is the promise; quality control at scale | M–L |
| **4** | **In-app messaging** (per-engagement threads) — **✅ engine + staff desk + professional portal done; client/vendor surfaces & nudges remain** | Trust/UX | Stops the WhatsApp leak; dispute evidence | M |
| **5** | **WhatsApp + SMS channel** (behind the notification seam) — **✅ engine + templates + consent + modes + provider seam + nudge done; real BSP + DLT/template approval remain (external)** | Reach | This audience lives on WhatsApp | M (blocked on template approval) |
| **6** | **Marketplace matching/trust on the unified pool + optional AI ranking** | Intelligence | Recommend across the whole pool | M |
| **7** | **Agency bench workspace** (agency adds/allocates its own people) | Supply | Agencies as fulfillers, not just applicants | M |
| **8** | **Labour-market analytics** (supply/demand, fill rate, rate benchmarks) | Moat/Revenue | Data product; ops insight | M |
| **9** | **Phase-B2 per-org gating + isolation** (topology decision + security review) | Architecture | Enforce org entitlements across orgs | L (needs sign-off) |
| **10** | **Escrow / payment rail** (Phase 6) | Money | Only after PMF + CA/RBI/legal | L (external blockers) |
| — | **Phase-0 market research / PRD / customer interviews** | Business | Founder/market work, not code | — |

**Everything above is additive** and fits the discipline used so far (new `cx_*` tables,
new engines, tests green, docs-with-code, default branch never touched).

---

## PART 5 — Recommended sequence

1. **#1 + #2 (unify pool + qualification taxonomy)** — the structural unlock; do first.
2. **#3 + #4 (verification/moderation + messaging)** — makes the network *trusted* and
   *sticky*: the essence of the lean launch.
3. **#5 + #6 (WhatsApp/SMS + matching on the unified pool + AI)** — reach and intelligence.
4. **#7 + #8 (agency bench + market analytics)** — supply depth and the data moat.
5. **#9 + #10 (per-org isolation, escrow)** — architecture and money, at PMF, with the
   sign-offs and reviews they require.

This turns the strong operations platform we have into the **Industrial Technical
Services Network** the vision describes — launched lean (a verified, searchable,
messageable talent network for everyone from ITI to MBA), with the operations, money and
report engines already in hand for the "expand after PMF" phases.
