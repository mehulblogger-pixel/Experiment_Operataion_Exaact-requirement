# Universal Technical Professional Passport — architecture & phased plan

Goal: turn the freelancer profile into a reusable **Universal Technical Professional
Passport** with a data-driven taxonomy, one-keyword discovery and intelligent
matching — spanning ITI trades to senior project leadership — **without breaking
existing functionality**. This is a multi-phase program; each phase ships tested
and additive. Do not read any single phase as "the whole thing is done".

## Phase 1 — Discovery (what already exists)

- **Two parallel taxonomies already exist** and are seeded from JSON:
  - **K0 industry** (`lib/connect_taxonomy.php`, `connect_tx_rows`): `cx_sectors` (27),
    `cx_equipment_groups/_types`, `cx_materials`, `cx_disciplines` (22),
    `cx_inspection_stages`, `cx_standards`, `cx_certifications_registry` (24).
  - **K13 qualifications** (`lib/connect_qualtax.php`, `connect_qtx_rows`): `cx_roles`
    (52), `cx_job_families` (20), `cx_qualification_levels` (20), `cx_iti_trades` (28),
    `cx_prof_certifications` (30) — an NSQF-anchored ITI→PM spine.
- **The professional record** `cx_professionals` stores `disciplines` (CSV of codes)
  and `skills` (free text). It was augmented with `role_code`, `job_family_code`,
  `qual_level_code`, `iti_trade_code`, `cert_codes`, `years_experience` — **but
  nothing writes or reads those columns** (dead capacity to wire).
- **Matching** (`lib/connect_match.php`) is token/substring overlap of `skills` +
  `disciplines`; it ignores roles, certs, qual level, location. **Search**
  (`connect_pro_search`) is SQL `LIKE`; requirement search is client-side. **No
  alias/synonym search, no skills master, no project/certification tables for
  professionals.**

Conclusion: **generalize and unify, don't rebuild.** Fold both taxonomies into one
graph; wire the dead columns; keep the CSV working.

## Phase 2/3 — the universal taxonomy GRAPH (shipped)

`lib/connect_tax_graph.php` — additive `cx_tax_*` + `cx_profile_tax` tables:

- **`cx_tax_nodes`** — one node per concept, `kind ∈ {DOMAIN, DISCIPLINE,
  SPECIALIZATION, ROLE, SKILL, ACTIVITY, EQUIPMENT, SYSTEM, CERTIFICATION, STANDARD,
  INDUSTRY, SECTOR, METHOD, MATERIAL, PROJECT_TYPE}`, a `parent_id` tree, `status`
  (ACTIVE/RETIRED, so retiring an item never breaks history), `source`, `code`.
- **`cx_tax_edges`** — many-to-many `RELATED` / `SUGGESTS` relationships beyond the
  tree (a person is multi-discipline; concepts relate across branches).
- **`cx_tax_aliases`** — synonyms/abbreviations → canonical node (the synonym engine).
- **`cx_profile_tax`** — ONE professional ↔ MANY nodes, `relation ∈ {PRIMARY_ROLE,
  ADDITIONAL_ROLE, SPECIALIZATION, SKILL, EQUIPMENT, INDUSTRY, CERTIFICATION}` with
  optional competency / years / last-used / verified. **One master record, many
  expertises.**

Engine: `connect_tax_node_add/children/roots` (drill-down), `_alias_add`, `_relate`,
`connect_tax_resolve` (ranked alias/synonym/code resolution), `connect_tax_suggest`
(pick NDT → offer RT/UT/MT/PT), `connect_tax_expand` (a concept's reach),
`connect_profile_tax_attach/for/detach`, and **`connect_tax_find_professionals`** —
one keyword → resolve → expand → find & rank people across roles/skills/equipment/
certs.

`connect_tax_generalize()` (idempotent, marker `tax_graph_v1`, runs at boot) imports
**both** taxonomies into the graph and adds a curated multi-domain tree (Electrical→
Power→Transmission→roles; Mechanical→Static→Pressure-Vessels→roles; NDT methods;
Welding+certs; HSE/QAQC/Civil roles) with aliases and suggests-edges. **348 nodes,
478 aliases** on the seeded set. `connect_profile_tax_backfill($proId)` maps a
professional's existing CSV into graph nodes (single source of truth; CSV stays).

Guarantees: strictly additive; the flat masters, CSV columns, matching, search and
every screen keep working; tests in `tests/test_connect_tax_graph.php` (26).

## Location & Mobility Engine (K-GEO, shipped backbone)

`lib/connect_geo.php` — one universal, reusable location engine (every profile type,
every matching workflow), additive:

- **`cx_geo_places`** — structured Country → State → City with coordinates (India +
  ~44 industrial cities seeded, states, international regions + GCC countries),
  admin/seed-extensible; `cx_profile_places` for a professional's SELECTED working
  places. Structured base + mobility columns added to `cx_professionals`
  (`base_place_id/state/country/lat/lng/pincode`, `mobility_mode`, `intl_regions`),
  **alongside** the legacy `base_city/pan_india/overseas/travel_radius_km` (kept).
- **`geo_haversine`** — great-circle distance; a missing coordinate returns INF, never
  a false "0 km".
- **`connect_location_match($pro, $job)`** — the priority-tier matcher the whole
  marketplace consumes: **1** exact city · **2** within travel radius (haversine) ·
  **3** selected region/city · **4** Pan-India · **5** international (region) · **0**
  outside. Lower tier = stronger.
- **`connect_geo_save_mobility`** — enforces the strict conditional rules server-side:
  Pan-India clears the travel radius and selected places; overseas gates the regions.
  The conditional profile UI (radius disabled when Pan-India, etc.) mirrors this and
  is part of the profile-experience phase.

Tests: `tests/test_connect_geo.php` (17) — distance, all five tiers, Pan-India-disables-
radius. The engine is built to be reused for inspectors/engineers/PMs, not per-role.

## Phases 4–7 — roadmap (next tested slices)

4. **Passport profile experience** — replace the long form with Identify → Select →
   Suggest → Confirm: taxonomy-driven drill-down, multi-select chips, suggested
   skills/equipment/certs, primary vs additional expertise, competency/years,
   project-experience and structured certification sections, mobile-first, save-draft.
   Writes to `cx_profile_tax` (and finally populates the dead qualtax columns).
5. **CV-first prefill** — upload CV → extract → map to taxonomy → user confirms
   (Suggested/Confirmed/Rejected states); never overwrite verified data silently.
6. **Taxonomy-aware search & discovery** — wire `connect_tax_find_professionals`
   into the talent desk + client search (one-keyword + advanced filters + ranked
   result cards + privacy-aware contact reveal).
7. **Matching foundation** — *shipped (additive)*: `connect_match_for_requirement`
   now enriches the professional pool with a **taxonomy-graph bonus** (resolve the
   requirement title n-grams + discipline to nodes, expand, overlap the candidate's
   `cx_profile_tax`, weighted by relation) and a **location bonus** (the K-GEO
   priority tier), producing plain-language **reasons** ("✓ Pressure Vessel
   Inspector", "✓ Within 300 km (208 km)", "⚠ Outside declared area") shown on the
   desk recommendation cards. Token scoring stays as the floor; the graph/location
   are additive bonuses. Tests: `tests/test_connect_match_passport.php`.

8. **Taxonomy admin CRUD** — *shipped*: `/connect-taxonomy-admin` (master/admin
   gate), engine `connect_tax_node_update` / `_set_status` / `_alias_delete` /
   `_edge_delete` / `_admin_nodes`. Add/rename/retire nodes (status-based, so
   history never breaks), manage synonyms and RELATED/SUGGESTS relations, reparent,
   filter by kind + text. A rename keeps the old name as a synonym; a colliding
   rename is refused. Tests: `tests/test_connect_tax_admin.php` (11).

9. **CV-first prefill** — *shipped*: `/pro/cv` (`lib/connect_cv.php`). Paste CV text
   (or scan the uploaded CV — txt/docx robust, pdf best-effort) → `connect_cv_scan`
   maps it against the taxonomy's alias vocabulary and the geo place names by
   in-memory n-gram scan (exact alias hits only, so high precision) → the
   professional **confirms** which detected roles/skills/certs/equipment to add
   (each with a relation) and whether to set the base city. Nothing is saved until
   confirmed; never overwrites existing data. An LLM extractor is a future seam.
   Tests: `tests/test_connect_cv.php` (8).

10. **Structured certifications & project experience** — *shipped*
    (`lib/connect_credentials.php`, route `/pro/credentials`). `cx_pro_certs`
    (name/authority/number/level/discipline/issue+expiry/document/verified) with an
    automatic expiry status (VALID / EXPIRING ≤60d / EXPIRED) — a cert links to a
    CERTIFICATION taxonomy node and **mirrors into `cx_profile_tax`** so the
    one-keyword search + matching find it. `cx_pro_projects` (title/role/client/
    industry/location/equipment/scope/dates) as first-class experience. Add/edit/
    remove, ownership-scoped; cert-name autocomplete against the taxonomy; optional
    certificate document upload. Linked from the passport. Tests:
    `tests/test_connect_credentials.php` (16).

11. **Verification & privacy states** — *shipped* (`lib/connect_privacy.php`,
    extended `lib/connect_credentials.php`, route `/pro/privacy`). Two honest
    guarantees:
    - **Verification is never self-declared.** A certificate's badge no longer
      comes from a caller flag; `connect_cred_cert_request_verify` files a
      `CREDENTIAL` check in the shared moderation ledger (`cx_verifications`,
      requires an uploaded document), and `connect_cred_reconcile` flips the
      cert (and its `cx_profile_tax` mirror) to Verified only after a moderator
      decides. Badges read Unverified / Pending / Verified / Not-verified.
    - **A per-professional privacy model** the whole marketplace reads through
      ONE resolver (`connect_privacy_resolve`): contact (hidden / on_request /
      public), rate (hidden / band / public), identity (full / first-initial),
      and a discovery `listed` switch — all with defaults that preserve prior
      behaviour. Contact is revealed only by an explicit, logged grant
      (`cx_pro_contact_reveals`) or an existing engagement — never because a
      client asked. Phone-first `/pro/privacy` screen with a live "what a client
      sees" preview. No permission-matrix change (a pro governs their own data;
      client screens *consume* the reveal). Tests:
      `tests/test_connect_privacy.php` (29) + verification assertions in
      `tests/test_connect_credentials.php` (25).

12. **Client advanced-search + result cards + contact-reveal** — *shipped*
    (`lib/connect_client_search.php`, route `/portal/find`, `views/portal/find.php`).
    The buyer side that consumes everything above. A client searches the shared
    pool by ONE keyword + structured filters (discipline / work-type / location /
    available-now); ranking reuses `connect_pro_search_smart` (taxonomy graph +
    location tiers). Each result is a **privacy-safe card** built by
    `connect_client_card` through `connect_privacy_resolve`: masked name where the
    pro chose first-initial, a verification-tier badge (ID / Credential / Proven),
    **verified** certification chips (VERIFIED only), taxonomy match reasons,
    location fit, rate mode, and a contact block that is either shown (engaged /
    public / approved), a **Request contact** button (on_request), a pending
    notice, or "platform messages only" (hidden). The reveal loop:
    `connect_privacy_reveal_request` records a REQUEST → the professional sees it
    in a **Contact requests** inbox on `/pro/privacy` and
    `connect_privacy_reveal_approve` / `_decline` decides →
    `connect_privacy_engaged` auto-reveals for an awarded requirement with no
    approval needed. A client can also **Invite** a professional straight onto one
    of its own open requirements. Only `privacy_listed=1` professionals appear.
    Tests: `tests/test_connect_client_search.php` (23).

13. **Hiring home for marketplace clients** — *shipped* (`lib/connect_hiring.php`,
    route `/portal/hiring`, `views/portal/hiring.php`, intent-aware
    `views/portal/login.php`). The buyer counterpart to the passport. A company
    that self-registers to hire (`connect_org_register`) now gets a purpose-built
    experience instead of the inspection dashboard: `/portal/login?for=hire` speaks
    to hiring; `portal_marketplace_first()` (a `cx_organisations` signup with no
    inspection footprint, agencies excluded) routes them to `/portal/hiring` and
    drops the inspection menu from their nav (header reads "· Hiring"). The home
    (`connect_hiring_home`) leads with **Search the pool** / **Post a requirement**,
    then shows live counts (open requirements, applicants awaiting a decision,
    awarded), **saved searches** (`cx_client_saved_search`, saved from the Find
    screen, one-tap re-run), the client's **open requirements** with per-job
    applicant/needs-review counts, and **contact requests** they have sent with
    live status (awaiting / shared). An established inspection client keeps its
    dashboard and gains a hiring shortcut card. Read-only over the marketplace +
    privacy engines; no new permission (the hire right, `market.post`, already
    covers it). Tests: `tests/test_connect_hiring.php` (15).

14. **Unified professional identity** — *shipped* (`lib/connect_identity.php`,
    console `/connect-identity`). The first Operations↔Connect *connection* slice
    (see `connect-integration-map.md`). The same human could exist twice — an
    internal `inspectors` row and a marketplace `cx_professionals` row — bridged
    only per application. `cx_identity_link` now records that the two records are
    the same person — **a relationship, never a merge** (nothing renamed/moved/
    deleted). Resolvers (`connect_identity_of_professional/_of_inspector/_roles`)
    give one master identity with many roles; `connect_identity_suggestions`
    proposes links from a shared e-mail/mobile (confirmed, never auto-linked);
    `connect_identity_dedupe_rows` collapses a dual-role person to one matcher row;
    every link/unlink is logged via `act_log()`. Staff console gated by the
    existing coordinator right (no new permission). Tests:
    `tests/test_connect_identity.php` (19).

15. **Client private bench + rehire** — *shipped* (`lib/connect_client_bench.php`,
    `/portal/roster`). The demand-side bench: `cx_client_bench` is a relationship
    over `cx_professionals` (one row per client+professional — the same person on
    many benches, no duplicate record). Add from the marketplace (`Add to bench`
    on a search card), from previous applicants/engagements, or by hand (linkable
    to a real profile later). Private note, client rating, preferred flag and
    preferred rate are client-only. Rehire invites a bench person onto an open
    requirement. Reuses `connect_client_card` for privacy-safe rendering and the
    `market.post` right (no new permission). Tests:
    `tests/test_connect_client_bench.php` (19). See `connect-integration-map.md`.

16. **Award → deployment** — *shipped* (`lib/connect_deploy.php`). The second
    Operations↔Connect connection: a marketplace award becomes a real **PDSO
    deputation** (`jobs` row, `job_type='DEPUTATION'`) via a "Create deployment"
    action on the awarded requirement desk — copying the proven award→billing
    bridge. Idempotent (one per requirement). WHO deploys resolves through the
    unified identity (phase 14): an inspector goes straight on; a linked
    professional goes on their inspector record; an unlinked one deploys
    UNASSIGNED with a prompt to link — so ISO 17020 competence/authorization
    keeps running through the existing controls. PDSO (mobilization, attendance,
    site register, conflict detection) then applies unchanged. Two additive
    `jobs` columns only. Tests: `tests/test_connect_deploy.php` (14). See
    `connect-integration-map.md`.

17. **Inspection request → unified sourcing** — *shipped* (`lib/connect_source.php`,
    `/connect-source?job=ID`, linked from the job detail). Staff resource an
    existing Operations inspection job from every pool at once: the job becomes a
    requirement-shaped view fed to the existing matcher, ranking internal
    inspectors + marketplace professionals (deduped by identity, with
    reasons/eligibility/location) and pinning anyone on this client's private
    bench (resolved through the identity link). Assignment is controlled: an
    internal inspector is placed directly; a marketplace professional staffs the
    job only once linked to an inspector record (else "Link to assign") — ISO
    17020 competence/authorization keeps running through the existing controls.
    One guarded write (`jobs.inspector_id`), logged; no new permission/table.
    Tests: `tests/test_connect_source.php` (16). See `connect-integration-map.md`.

18. **Requirement reuse + configurable match weights** — *shipped*
    (`lib/connect_reqtools.php`, `connect_match_weights()`,
    `/connect-match-weights`). §49: duplicate any requirement into a fresh DRAFT
    (shape + crew copied, not the award) and save/reuse named templates
    (`cx_req_templates`) — both on the existing `cx_requirement_create`, wired into
    the client hire screen and the staff desk. §23: matching weights are now an
    admin-tunable JSON setting whose defaults equal the historical literals
    (behaviour unchanged until re-weighted, clamped 0–100), with a master/admin
    screen. Tests: `tests/test_connect_reqtools.php` (26). See
    `connect-integration-map.md`.

### Program status
   The passport programme (Phases 1–12) is shipped and tested: universal taxonomy
   graph, location engine, passport UX, CV prefill, structured credentials,
   taxonomy admin, matching, verification, privacy, and the client search. All
   additive; full suite green. Future seams noted inline (LLM CV extractor, org/
   inspector verification tiers, a real KYC provider).
