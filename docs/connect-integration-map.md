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
| 4 | Award → scheduling / allocation / deputation | **OPEN** | reuse `pdso.php` (no second scheduler) |
| 5 | Client private bench / roster + rehire | **CONNECTED (phase 15)** | `cx_client_bench`, `connect_client_bench.php` |
| 6 | Inspection request → manpower sourcing | **OPEN** | bridge `calls`/`jobs` ↔ pool via the matcher |
| 7 | Requirement duplicate / template | **OPEN** | extend `connect_market.php` |
| 8 | Configurable matching weights | **OPEN** | lift `connect_match.php` literals to settings |

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

## Sequenced plan for the remaining seams

Each will follow the same discipline (audit → extend the existing engine → tests
→ screenshot → no breakage), one at a time:

1. **Award → schedule → deploy** (seam 4) — copy the proven award→billing bridge
   pattern to hand a marketplace award (resolved through the identity link and
   `subject_kind`) into the existing PDSO deputation, so hire → deploy → report →
   invoice is one flow with no re-entry.
3. **Inspection request → sourcing** (seam 6) — let a `call`/`job` source people
   from internal staff / approved inspectors / marketplace / bench through
   controlled rules, respecting ISO 17020 competence/impartiality controls.
4. **Requirement templates / duplicate** (seam 7) and **configurable match
   weights** (seam 8).
