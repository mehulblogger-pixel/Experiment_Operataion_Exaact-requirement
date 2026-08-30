# Connect ↔ Operations — Integration Map

The master brief's first command is *"study the existing app, prepare an
integration map, extend don't rebuild."* This is that map: how the Connect
marketplace layer relates to the existing Operations system, what is already
connected, and the sequenced plan to close the remaining seams — each additive,
tested, and non-destructive.

## Governing principle

**EXTEND AND CONNECT — DO NOT REPLACE OR BREAK.** The marketplace is an
intelligent *sourcing layer above and connected to* the Operations system, not a
separate application. Where a system already performs part of a function, we
integrate with and extend it; we never build a parallel one.

## Status of each seam

| # | Seam | Status | Anchor / reuse target |
|---|------|--------|-----------------------|
| 1 | **Professional identity** (inspector ↔ marketplace pro) | **CONNECTED (phase 14)** | `cx_identity_link`, `connect_identity.php` |
| 2 | Award → billing → invoice | **DONE (pre-existing)** | `connect_bridge.php` → `billable_events` → commercial board |
| 3 | Audit / event log | **DONE — reused** | `act_log()` / `activities` |
| 4 | Award → scheduling / allocation / deputation | **CONNECTED (phase 16)** | `connect_deploy.php` → a PDSO `jobs` deputation |
| 5 | Client private bench / roster + rehire | **CONNECTED (phase 15)** | `cx_client_bench`, `connect_client_bench.php` |
| 6 | Inspection request → manpower sourcing | **CONNECTED (phase 17)** | `connect_source.php` — job ↔ pool via the matcher |
| 7 | Requirement duplicate / template | **CONNECTED (phase 18)** | `connect_reqtools.php` |
| 8 | Configurable matching weights | **CONNECTED (phase 18)** | `connect_match_weights()` + admin |

Most of the passport/marketplace vision (taxonomy graph, passport, CV prefill,
credentials, verification, privacy, client search, hiring home, location +
matching engines) is already built across phases 1–13 — see
`connect-universal-passport.md`.

## Two person stores — the identity finding (seam 1)

- **Internal:** `inspectors` (`lib/ops.php`) — the personnel entity the whole
  inspection / PDSO / expense / voucher chain uses (`jobs.inspector_id`, etc.).
- **Marketplace:** `cx_professionals` (`lib/connect_pro.php`) — the self-registered
  pool with its own login.

They were bridged only **per application** (`cx_applications.inspector_id` +
`applicant_professional_id`, resolved to `cx_engagements.subject_kind ∈
professional | inspector | bench`). There was **no stored link on the person
records**, so the same human was two unlinked identities.

### Phase 14 — unified identity (shipped, non-destructive)

`lib/connect_identity.php` adds one link ledger, `cx_identity_link`, that records
"this professional row and this inspector row are the same person" — **a
relationship, never a merge**. Nothing is renamed, moved, merged or deleted; both
records keep working exactly as before.

- **Resolvers:** `connect_identity_of_professional` / `_of_inspector` /
  `connect_identity_roles($ref)` — "who is this, really?" → one master identity
  with many roles (marketplace pro · internal inspector · bench · …).
- **Suggestions:** `connect_identity_suggestions()` proposes links from a strong
  deterministic signal (shared e-mail, then mobile). **Never auto-links** — a
  person confirms (mirrors the "never auto-publish unverified" rule).
- **Guards:** both records must exist; neither may already be linked to a
  different counterpart; re-linking the same pair is a harmless success.
- **Provenance:** every link/unlink is written to the `activities` spine via
  `act_log()`.
- **Matcher payoff:** `connect_identity_dedupe_rows()` collapses a person who is
  both an internal inspector and a marketplace professional into **one** shortlist
  row (keeps the stronger, annotates "one verified person — staff & marketplace"),
  so the desk never sees a duplicate.
- **Console:** `/connect-identity` (staff, gated by the existing coordinator/
  manager `connect_market_can` right — no new permission): confirm suggestions,
  link/unlink by hand, see linked identities with provenance.

Tests: `tests/test_connect_identity.php` (19).

### Phase 15 — client private bench + rehire (shipped, additive)

`lib/connect_client_bench.php` adds the **demand-side** bench (distinct from the
agency/supply bench). `cx_client_bench` is a **relationship over
`cx_professionals`** — one row per (client, professional), so the same person sits
on many clients' benches with **no duplicated person record** (§18, §48). Three
sources (§16): a marketplace professional (`Add to bench` on a search card), a
**previous** professional (`connect_client_bench_previous` — applied to / engaged
on this client's requirements), or a **manual** off-platform entry that can later
be **linked** to a real profile (`connect_client_bench_link`, never a duplicate).
Private note, the client's own rating, preferred flag and preferred rate live on
the relationship and are **client-only** (§17) — a separate `client_party_id`-scoped
table no professional-facing read ever touches. **Rehire** (§49) invites a bench
person straight onto one of the client's own open requirements. Screen
`/portal/roster` ("My bench" in the marketplace nav); gated by the existing
`market.post` right. Tests: `tests/test_connect_client_bench.php` (19).

### Phase 16 — award → deployment (shipped, additive)

`lib/connect_deploy.php` copies the award→billing bridge pattern to make a
marketplace award an actual Operations **deployment**. A PDSO deployment *is* a
`jobs` row with `job_type='DEPUTATION'` + `dep_status` + `dep_site`, so
`connect_deploy_from_engagement($requirementId)` creates exactly that (idempotent
— one per requirement, keyed by two additive `jobs` columns `source_module` +
`source_requirement_id`). WHO is deployed resolves through the unified identity
(Connection #1): an awarded internal inspector goes straight onto
`jobs.inspector_id`; an awarded marketplace professional goes onto the inspector
it is **linked** to, and if not linked the deployment is created **UNASSIGNED**
with a message to link them — so ISO 17020 competence/authorization still runs
through the existing inspector controls (§41), nothing bypassed. A "Create
deployment" / "Sync deployment" action on the awarded requirement desk (beside
"Send to billing") drives it; PDSO (mobilization, attendance, site register,
conflict detection) then applies unchanged. Tests:
`tests/test_connect_deploy.php` (14).

### Phase 17 — inspection request → unified sourcing (shipped, additive)

`lib/connect_source.php` lets staff resource an EXISTING Operations inspection job
from every pool at once. It builds a requirement-shaped view of the job
(`connect_source_pseudo_req`) and reuses `connect_match_for_requirement` to rank
internal inspectors + marketplace professionals (deduped by identity, with
reasons/eligibility/location), then annotates each with a source, whether it can
be assigned now, and whether it sits on THIS client's private bench (resolved
through the identity link too, so a client-known person surfaces first even when
shown as their inspector). Assignment is CONTROLLED (`connect_source_assign`): an
internal inspector is placed directly; a marketplace professional can staff an
inspection job ONLY once linked to an inspector record (Connection #1) — else
"Link to assign", so ISO 17020 competence/authorization keeps running through the
existing inspector controls (§41). Screen `/connect-source?job=ID` (linked from
the job detail), gated by the coordinator/master talent right; the one write is a
guarded `jobs.inspector_id` assignment (the field job-edit already sets), logged.
Tests: `tests/test_connect_source.php` (16).

### Phase 18 — requirement reuse + configurable match weights (shipped, additive)

Two cleanups, both built on the existing engines. **Reuse (§49, seam 7):**
`lib/connect_reqtools.php` — `connect_requirement_duplicate()` clones any
requirement into a fresh DRAFT (shape + crew positions copied; award/applications/
status deliberately not), and `cx_req_templates` saves a requirement's shape under
a name to start new ones from. Both call the existing `cx_requirement_create` —
no parallel posting flow. Wired into the client `portal/hire` (Duplicate · ★
Template · Start-from-a-template) and the staff requirement desk (Duplicate).
**Weights (§23, seam 8):** `connect_match_weights()` reads one JSON setting whose
DEFAULTS equal the scorer's historical literals (so behaviour is unchanged until
someone re-weights), clamped 0–100; the scorer (`cx_match_score`,
`connect_match_tax_bonus`, `connect_match_location_bonus`) reads it. Admin screen
`/connect-match-weights` (master/admin) tunes skills / reputation / credentials /
taxonomy / location / eligibility, with a reset. Tests:
`tests/test_connect_reqtools.php` (26).

## Program status — the ecosystem is connected

All eight integration seams are now CONNECTED or pre-existing/reused. The thread
runs end to end in both directions: **marketplace award → deploy → billing →
invoice**, and **inspection job → source from the pool**, over one unified
professional identity, with client-private benches and reusable requirements. Any
further work (recruitment-candidate sourcing, an Engagement entity to replace the
`contract_number` string, financial dual-truth convergence) is a deliberate future
slice, not a gap in the connection.
