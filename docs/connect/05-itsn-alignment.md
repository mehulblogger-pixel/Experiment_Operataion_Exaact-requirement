# Connect — Alignment with the ITSN Vision

**Purpose:** an honest assessment of how far the platform we have built aligns with
the owner's full vision — the **Industrial Technical Services Network (ITSN)**: a
"LinkedIn + Uber + Stripe + Trustpilot" for *all* industrial technical services,
launched lean (connect companies with verified professionals) and expanded after
product-market fit.

## The reframe (and it matters)

- **The product is a trust network, not an ERP.** The launch MVP is *matching companies
  with verified professionals* — done exceptionally well. Report-writing, accounting,
  invoicing, full inspection ops are **later layers** (the owner's Phase 4+), pulled in
  only after PMF.
- **What we have:** we built the Connect trust-network capability **dissolved into
  EXAACT** (a full inspection ERP). The good news — and it's strategically important —
  is that Connect is **cleanly isolated** (`connect_*` libs, `cx_*` tables, its own four
  portals, its own Marketplace area, and a single `connect_enabled` switch). So the
  **Trust Network can stand alone as the lean launch product**, with the ERP/ops
  switched off via product packages and layered back in later. This is exactly the
  "focused MVP now, expand later" posture the vision calls for.

## Scorecard A — the owner's "What I would build first" (the launch priorities)

| # | Priority | Status | Evidence / gap |
|---|---|---|---|
| 1 | **Verified Professional Directory** (searchable, verified) | 🟢 **Mostly done** | `/pro` self-service profiles (A1), talent search over the pool (A3), public Passport with live-status credentials (K1), Trust Score (K5). *Gap: identity/KYC verification (only credentials are verify-flagged).* |
| 2 | **Company Requirement Portal** (post in minutes) | 🟢 **Done** | Client-portal *Hire* (K2b) + guided post (K4) + full desk (K2a). |
| 3 | **AI Matching Engine** (recommend, not just search) | 🟡 **Rules-based done** | Ranked recommendation cards with reasons (K3); Concierge is deterministic. *Gap: true ML/LLM ranking (the `ai.php` layer is not wired into matching yet).* |
| 4 | **Trust & Verification Engine** (identity, certs, reputation) | 🟡 **Half** | Reputation (Trust Score K5) 🟢; certificate verification (`verify_status`, ops verification queue) 🟢. *Gap: identity (gov-ID/passport/face) and business verification ❌.* |
| 5 | **Communication & Hiring Workflow** (messaging, offers, confirmations) | 🟡 **Workflow yes, chat no** | Hire loop (apply→shortlist→offer→award→rate→dispute) 🟢. *Gap: in-app messaging/chat per engagement ❌ (blueprint M15).* |
| 6 | **Agency Workspace** (manage resources, fulfil jobs) | 🟡 **Partial** | Vendor portal apply (K2b) + org accounts/onboarding (B0/B1) 🟢. *Gap: an agency "bench" — add its own employees/freelancers and allocate them to won work ❌.* |
| 7 | **Premium SaaS Modules** (after traction) | 🔴 **Deliberately not yet** | Subscriptions/escrow (K8) are correctly deferred until the network has traction. |

**Launch-MVP readiness (priorities 1–6):** ~**70–75%** — the spine is real; the three
named gaps (messaging, identity verification, agency bench) are what stand between us
and a clean lean launch.

## Scorecard B — the seven phases

| Phase | Status | Notes |
|---|---|---|
| **0 — Research / PRD** | 🔴 Not done (business, not code) | Blueprint + gap-analysis docs exist, but no market research / customer interviews / PRD. This is founder/market work, not a build. |
| **1 — Trust Network MVP** | 🟢 **~75%** | Companies (post/search/invite/shortlist/hire/track) 🟢; Professionals (register/certs/profile/availability/accept-decline/portfolio/ratings) 🟢; Agencies (register/apply) 🟡; Platform (matching 🟢, verification queue 🟢, ratings/reviews 🟢, audit trail 🟢, admin 🟢). Gaps: **messaging, agency bench, identity verification**. |
| **2 — Trust Layer** | 🟡 Partial | Trust Score 🟢, certificate verification 🟢. *Gap: verified identity (gov-ID/passport/face), business verification, verified-experience/endorsements.* |
| **3 — AI Matching** | 🟡 Rules today | Deterministic ranking 🟢; ML/LLM ranking ❌. |
| **4 — Operations Layer** (scheduling, travel, attendance, GPS, docs, approvals, timelines) | 🟢 **Strong** | This is EXAACT's core and it's mature — and the marketplace already bridges into it (award → billable → invoice). It is a *later* layer per the vision, but it is our strongest asset when customers ask for it. |
| **5 — AI Productivity** (voice, photos, report drafting, translation, forecasting, analytics) | 🔴 Minimal | `ai.php` + `advisor.php` + IDEMS report engine exist; the productivity features (voice notes, translation, drafting-for-pros) are not built. |
| **6 — Financial Layer** (escrow, subscriptions, fees, premium, recruitment, training, insurance) | 🟡 Foundations only | Invoicing/billing engine 🟢 (EXAACT); award→invoice bridge 🟢; product packages hint at subscription tiers; escrow (K8) deferred pending money sign-off. Not productized as revenue. |
| **7 — Ecosystem** (training, certification, calibration, insurance partners) | 🔴 Not started | Integration gateway (M19) partial; ecosystem partnerships are a later strategic layer. |

## Scorecard C — the "Four Businesses"

- **Companies** 🟢 · **Professionals** 🟢 · **Agencies** 🟡 (participate, no bench) ·
  **Matching engine** 🟢 (rules) · **SaaS/AI/Verification** 🟡 (Trust Score + cert
  verify; no identity/ML) · **Financial services** 🔴 (foundations only).

## The three gaps that matter for a *lean launch*

Everything else is either done, deliberately deferred, or a later phase. For the
"connect companies with verified professionals" MVP, these are the holes:

1. **In-app messaging / chat** per engagement (M15) — a marketplace without messaging
   forces people back to WhatsApp, which is the exact thing we're replacing.
2. **Identity & business verification** (gov-ID/passport/face for pros; registration
   for companies) — "verified" is the whole promise; today only *certificates* are
   verified, not *identity*.
3. **Agency bench workspace** — an agency adding its own people and allocating them to
   won work, so agencies are first-class fulfillers, not just applicants.

## The strategic strength (the moat, already forming)

- A **verified professional network** with **rich trust/performance history** (Passport +
  Trust Score + ratings) — the hard-to-copy asset.
- **Network effects** wired between companies, agencies and professionals on one graph.
- A mature **operations layer** ready to deepen the relationship post-PMF — a
  differentiator competitors (job boards) can't match.
- **Clean isolation + a switch** means we can ship the lean Trust Network *and* offer the
  operations depth — the same codebase serves "focused MVP" and "expand later."

## Bottom line

**We are strongly aligned with the ITSN vision on the launch spine (~75% of the lean
MVP), with the operations moat already in hand.** The distance to a clean lean launch
is three named features (messaging, identity verification, agency bench), plus the
non-code Phase-0 market work. Everything beyond (AI productivity, financial layer,
ecosystem) is correctly *later*.

## Recommended next step

A **formal system audit** (the owner's ask): a systematic, file-referenced audit of the
present platform against this ITSN framework — what exists, its quality, its gaps, and a
prioritised, launch-first backlog — so the next build decisions are made on evidence,
not impression.
