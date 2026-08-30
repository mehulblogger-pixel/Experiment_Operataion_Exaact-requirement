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
7. **Matching foundation** — extend `connect_match` to score against the graph
   (mandatory/preferred/advantage), with match explanations, reused by jobs,
   requirements, inspector selection and deployment. Plus taxonomy admin CRUD
   (add/edit/retire/relate/alias) and verification/privacy states.
