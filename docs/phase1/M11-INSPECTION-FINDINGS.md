# Milestone 11 — Inspection Findings (evidence before any change)

**Branch:** `claude/testing-branch-setup-0gqe8n` at `3c15141` (after M10)
**Status:** inspection only — **no code has been changed.**

---

## 1. What was measured, and how

Everything below is measured from the branch by booting the real application as a
master on the control install and asking the navigation engine itself — not by
reading filenames or guessing.

---

## 2. The navigation engine is in better shape than expected

This is the finding that should shape M11, because it contradicts the usual
assumption that a system this size has tangled navigation.

| Measure | Value |
|---|---|
| Areas in the left rail | **8** (sales, marketplace, quality, reporting, money, insights, directory, admin) + Operations, which has its own richer home |
| Tiles across all areas | **99** |
| Distinct routes behind those tiles | **98** |
| Routes appearing in **more than one** area | **1** — `/templates` (sales **and** admin) |
| Command-palette destinations | **153** (145 distinct routes) |

`lib/areas.php` is a deliberate single source of truth: the rail link and the
area home are generated from one definition, so they cannot disagree.
`lib/navindex.php` explicitly reuses it — the palette is a **layer over** the
rail, not a second nav tree. Its own header says so.

**So there is no duplicated menu system to consolidate.** One cross-area
duplicate exists, and it is `/templates`.

---

## 3. Where the real fragmentation is: landing surfaces

Nine distinct "home" screens exist:

| Screen | Rendered by |
|---|---|
| `dashboard` (549 lines) | `index.php`, the default landing |
| `operations_home` (211) | `lib/tosrm.php` |
| `area_home` (112) | `lib/areas.php` — one per area, ×8 |
| `crm_dashboard` (191) | `lib/crmdash.php` |
| `recruitment_home` (174) | `lib/recruit.php` |
| `cockpit_home` (133) | `lib/setup_cockpit.php` |
| `owner_home` (103) | `lib/owner_home.php` |
| `welcome` (45) | `lib/onboarding.php` |
| `role_workspaces` (64) | `lib/workspace.php` |

And the decision about **which one a person lands on** is a four-branch cascade
in `index.php` (lines 1168–1205):

1. setup incomplete **and** the user can open the cockpit → `/workspace/setup`
2. setup incomplete otherwise → `/welcome`
3. a configured per-role landing exists → that route
4. Operations not licensed **and** HR is → `recruitment_home`
5. otherwise → `dashboard`

Each branch was added for a real reason. Together they mean **no two customers
necessarily see the same first screen**, and there is no single place that
answers "where does this person start?".

---

## 4. Navigation coverage of the route surface

| Measure | Value | Note |
|---|---|---|
| Routes the dispatcher serves (`case $route ===` form) | **262** | the true total is higher — prefix and regex routes are not counted here |
| Reachable from navigation (rail, tiles or palette) | **109** | |
| Not reachable from navigation | **153** | |
| — of those, leaf actions (`-new`, `-save`, `-export`, …) | **25** | correctly absent from navigation |
| — of those, standalone screens with no navigation entry | **128** | |

**The 128 needs an honest caveat**, and I would not present it as 128 orphans.
A large share are **record-detail screens reached by clicking a row** — `contract`,
`customer`, `candidate-interview`, `incident` — which is correct design, not a gap.

The genuinely notable ones are screens that are neither a detail view nor
reachable from anywhere: `backup`, `billing`, `ai-settings`, `feature-gates`,
`financial-control`, `duplicates`, `entity-360`, `dt-columns`, `compliance-rules`,
`ads-roi`, `boss-renew`. Those are address-only today.

---

## 5. Duplicate business flows — verified against the code

An automated pass over `INSERT INTO` by route produced 16 candidate tables, but
that heuristic misattributes (it binds an INSERT to the nearest preceding route
label). **The numbers below are from reading the actual call sites**, with seed
and demo files excluded.

| Business action | Production doors | Files |
|---|---|---|
| **Create a customer / partner** | **6** | `index.php:1278` (partner-new), `lib/leads.php:656` (lead conversion), `lib/crm.php:2604`, `lib/ops.php:3758`, `lib/partnerimport.php:320` (bulk import), `lib/connect_org.php:148` (marketplace onboarding) |
| **Register a contract** | **4** | `lib/crm.php:900`, `lib/crm.php:2140`, `lib/opportunities.php:766`, `index.php` (partner-add `kind=contract`) |
| **Create a voucher** | **2** | `lib/ops.php:5579`, `lib/ops.php:5645` (quick-add) |
| **Add PO line items** | **2** | `index.php` (partner-add), `index.php` (po) |

Several of these are **legitimately** separate — bulk import, lead conversion and
marketplace onboarding each have a real reason to create a partner. That is
exactly why this needs a decision rather than a sweep.

Two of them are already known to have caused defects:

- the **contract** doors — M6 found the partner-screen door created contracts with
  no permission check at all, bypassing the won-quote → Finance handoff;
- the **books drain** and **Ads sync** — M10 found the screen and the nightly job
  disagreeing about entitlement. Same shape: one operation, two doors.

---

## 6. What I am NOT yet able to decide

M11 changes what people see and removes flows. Unlike M2–M10, a wrong call here
is **user-visible and business-affecting** — removing a door that a real team uses
daily is a worse outcome than leaving a duplicate in place.

The previous milestones each carried an explicit protected list ("do not merge
`requisitions` with `cx_requirements`", "Quality stays inside Operations",
"do not redesign the Dashboard"). For M11 I do not have the equivalent, so I
cannot safely decide:

1. **Which landing surfaces may be consolidated**, and which must stay (the
   recruitment-only home and the cockpit both exist to serve specific customers).
2. **Which duplicate doors may be closed** — e.g. is the partner-screen contract
   door to be removed now that M6 gated it, or kept?
3. **Whether the 11 address-only screens** should be given navigation entries,
   hidden, or left as they are.
4. **Whether `/templates` appearing in two areas** is a defect or deliberate.
5. What "duplicate flow removal" means for **records already created** through a
   door that is removed.

---

## 7. Recommendation

Give me the M11 prompt with its protected list and acceptance criteria, and I
will work from this evidence base. If you would rather I proceed on judgement, my
proposed scope — smallest safe change, consistent with M2–M10 — would be:

- **Do not touch** the rail, the area definitions or the palette. They are sound.
- **Consolidate the landing decision** into one documented resolver, without
  deleting any landing screen — so "where does this person start?" has one answer
  and one test, and every existing screen still works.
- **Close the duplicate contract door** on the partner screen (already gated in
  M6), directing it to the CRM path, and leave the other partner-creation doors
  alone with their reasons documented.
- **Give the 11 address-only screens** a navigation entry or an explicit "reached
  from X" note, whichever each deserves.
- **Fix `/templates`** appearing in two areas.

Everything else would be documented rather than changed.
