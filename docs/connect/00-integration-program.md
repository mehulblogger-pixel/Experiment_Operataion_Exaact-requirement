# Connect — Integration Program (Inspect Connect → EXAACT)

**Status: plan for approval. No feature code is written yet.** This document is the
charter for folding the *MGH Inspect Connect* capability into EXAACT as ONE
platform. It sets the principle, the scope, the guardrails, the decisions that
need the owner's sign-off, and the slice sequence. Its companion,
[`01-reuse-map.md`](01-reuse-map.md), maps every blueprint module to the EXAACT
engine that absorbs it.

Source spec: the frozen blueprint at
`mgh-inspection-bridge/docs/blueprint/inspection-trust-platform-blueprint-v2.0.md`
(and its Laravel reference implementation). The blueprint is the **specification**;
EXAACT is the **home**. We re-implement in EXAACT's stack — we do not run a second app.

---

## 1. The governing principle — "milk and sugar"

The owner's words: *"this module shall be completely separated and included in a
flow such as milk and sugar are mixed… I don't want any third app."*

- **One app: EXAACT.** No second product, no separate VPS, no federated API app.
  The Laravel repo is a reference/spec; the code lands in EXAACT's own stack
  (`index.php` + `ops_dispatch()`, `lib/*.php` engines, `can()`/`scope_clause()`
  access, idempotent migrations via `ensure_column()`/`CREATE TABLE IF NOT EXISTS`
  + `boot()` probes, `views/ops/*.php`, the `tests/run.php` harness).
- **Dissolved, not bolted on.** The marketplace is a native capability of the
  platform — same login, same data, same navigation, same look — not a `/connect`
  skin sitting beside the ops system. A user cannot tell "the marketplace" from
  "the operations system"; it is one product.
- **Reuse before build.** Same law as the ITOP revamp:
  **CONFIGURE → REUSE → EXTEND → CONNECT → REFACTOR → BUILD.** The audit in
  `01-reuse-map.md` shows EXAACT already carries engines for the large majority of
  the blueprint; the genuinely new build is the open two-sided marketplace and the
  reputation Trust Score.

## 2. What we are building (scope)

A **general technical-manpower marketplace, dissolved into EXAACT.** Owner's words:
*"This app is general — finding all types of technical manpower — anyone can post
it here and anyone can apply for it."*

- **Two-sided and open.** A **poster** publishes a technical-manpower requirement;
  an **applicant** applies. Broader than third-party inspection — all technical
  manpower (inspectors, welders, NDT, site/QA-QC engineers, and so on). Inspection
  is the launch vertical, not the boundary.
- On top of this open marketplace sit the blueprint's trust mechanics that make it
  credible: verified **Passport**, **Trust Score**, guided **Concierge** intake,
  **Matching**, protocol-based **job lifecycle**, immutable **reports** with QR
  verification, **escrow**, **ratings**, **disputes**, and the Part-F operational
  governance (scope freeze, site readiness, waiting-time, safety, knowledge capture).

## 3. One deliberate divergence from the blueprint — flagged, and approved by the owner

Blueprint **Part D** mandates the *opposite* of "milk and sugar": it says the
platform must be **standalone-first**, "never depend on MGH infrastructure,"
integrate "via API + signed webhooks only — never shared databases," on its own VPS.

**The owner's instruction overrides Part D.** We dissolve the capability into EXAACT
(one app, shared database, native engines), not federate two apps over an API. This
is a conscious, owner-approved architectural divergence. Consequence: the M19
Integration Gateway / event-bus / webhooks (D1–D6) become **internal reuse** of
EXAACT's own money/CRM engines (`books.php`, `crm.php`, `finevent.php`) rather than
external HMAC webhooks — the integration the blueprint wanted is achieved by being
in the same system, not by calling into it.

## 4. Guardrails (unchanged from the revamp)

- **Additive & non-destructive.** New columns/tables + bootstrap probes; nothing
  dropped or repurposed. Existing desk/field screens keep working untouched.
- **UI/UX law.** Every new screen obeys `docs/05-ui-ux-blueprint.md` and must pass
  the Zero-Training gate. Marketplace screens are phone-first (posters and
  applicants live on phones); ops screens stay desk-capable.
- **No new permission or status without sign-off.** The new roles and lifecycles in
  §5 are listed *for approval*; they are not assumed.
- **Docs move with code.** Permission-matrix and object-lifecycle docs update in the
  same commit as the code that changes them.
- **Branch discipline.** All work on the working branch
  `exaact-ops-system-tpia-manpower`; never a write to the default branch.
- **Money truth stays in `books.php`.** Escrow is a hold-and-release *state* over the
  existing ledger, never a second source of money truth. Anything that moves real
  figures waits for explicit sign-off (as R9/§29 did).

## 5. Decisions that need the owner's sign-off (before the slices they gate)

These are the "stop and ask" items. Each is a proposal to approve or correct — not a
built assumption.

### 5a. New roles / access (open self-service) — RESOLVED BY ADOPTION
The Inspect Connect code **already defines** the account types, and the owner has
directed us to adopt them rather than invent new ones. See
[`02-identities-and-taxonomy.md`](02-identities-and-taxonomy.md). The adopted roles:
`inspector` · `company` (team sub-roles `owner/requester/approver/finance`) ·
`agency` (the manpower/technical agency — posts *and* supplies) · `admin` ·
`superadmin`. This already **is** the open two-sided marketplace: companies and
agencies post; inspectors and agencies apply.

The only residual question is **how each role maps onto EXAACT's existing access
systems** (`ORG_ROLES`, client portal `pcan()`, vendor portal `vendor_users`) — the
recommended mapping is in `02-identities-and-taxonomy.md §2` and needs a yes/adjust.
New *permissions* (post / apply / shortlist / award) get written into
`docs/02-permission-matrix.md` as each slice lands.

### 5b. New object lifecycles (need entries in `docs/03-object-lifecycles.md`)
- **Requirement posting:** `DRAFT → OPEN → SHORTLISTING → AWARDED → CLOSED`
  (+ `CANCELLED`, `EXPIRED`).
- **Application:** `APPLIED → SHORTLISTED → OFFERED → ACCEPTED`
  (+ `DECLINED`, `WITHDRAWN`, `REJECTED`).
- **Job (marketplace path), from the blueprint's protocol-based flow:**
  `Draft → Agreement signed → Matched → Offer → Accepted → Escrow funded →
  Mobilized → In progress → Protocol Completed → Report submitted → Client review →
  Completed → Paid → Rated`. Must reconcile with EXAACT's existing
  call→job→report→invoice lifecycle rather than duplicate it.

### 5c. Money — escrow model
Fund-before-mobilize → release-on-protocol-completion touches money truth. Needs
sign-off on the model (hold/release states over `books.php`; provider is manual at
first, like the reference app's `ManualEscrowProvider`) before any figure moves.

### 5d. External blockers (not code — start in parallel)
WhatsApp Business API templates; escrow gateway + CA/RBI sign-off; lawyer sign-off
(T&C, Tri-Party Agreement, NDA); SME reviewer for assessment content. These gate
*launch*, not our build, but they have weeks of lead time.

## 6. Proposed slice sequence

Each slice is additive, passes the Zero-Training gate, and ships tests green before
push — exactly like P1–P9. Ordered lowest-risk / highest-reuse first; the money and
new-role slices are deliberately later, behind their §5 sign-offs.

| # | Slice | Verb | Gated by |
|---|---|---|---|
| **K0** ✅ | Import the adopted taxonomy (`taxonomy.json`: 27 sectors / 11 equipment groups / 18 materials / 22 disciplines / 17 stages / 13 standards / 24 certs + versioning) as admin-extensible seed tables. **Delivered:** additive `cx_*` masters, idempotent insert-if-empty seed, read-only screen at `/connect-taxonomy`, `lib/connect_taxonomy.php` + `data/connect_taxonomy.json`; 15 tests. Existing files: +5 lines, 0 deletions. | CONFIGURE/EXTEND | — |
| **K1** | Digital Passport — public, shareable, QR-verifiable credential page over the P1 vault | EXTEND | — |
| **K2** | Requirement posting + application (the marketplace core) over `recruit.php` requisitions | EXTEND/BUILD | 5a, 5b |
| **K3** | Matching & recommendation cards (Best Match / Value / Nearest) over suggestion + competence | EXTEND | K0 |
| **K4** | AI Concierge guided intake → builds a requirement/call | BUILD over `ai.php` | K0 |
| **K5** | Trust Score 0–1000 (read-only) from `rating.php` + competence + `trust.php` events | BUILD | — |
| **K6** | Protocol-based job lifecycle + geo check-in/telemetry over `trust.php`/`attend.php` | EXTEND | 5b |
| **K7** | Reports: O/D/T counterparts + QR public verification over `idems.php` + `qr.php` | EXTEND | — |
| **K8** | Escrow hold → protocol-release over `books.php` | EXTEND/BUILD | 5c |
| **K9** | Two-way ratings + disputes/mediation over `rating.php` + `complaints.php`/`ncr.php` | EXTEND | — |
| **K10** | Part-F governance: commercial term-sheet, scope freeze, site readiness, waiting-time | BUILD/EXTEND | 5b |
| **K11** | Assessment player (M5) — the one fully-new module | BUILD | 5d (SME) |
| **K12** | Operations Advisor readiness scoring over `advisor.php` | EXTEND | K10 |

Notifications (M15/WhatsApp) and market analytics (M17) thread through the slices via
the existing notifications and MIS engines; they are CONNECT work, not standalone slices.

## 7. How we proceed

1. Owner reviews this plan + `01-reuse-map.md`.
2. Owner answers the §5 decisions (roles, lifecycles, escrow model).
3. We build slice by slice on "Go ahead with Kx," reusing engines, additive-only,
   tests green before every push, each screen through the Zero-Training gate, docs
   moving with code — all on the working branch, never default.
