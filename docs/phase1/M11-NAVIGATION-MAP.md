# Milestone 11 — Navigation Map

Measured by booting the application and asking the navigation engine, not by
reading filenames.

---

## 1. The shape of the navigation

```
left rail  (8 areas + Operations)
   │
   ├─ area home            one per area, generated from ops_area_def()
   │     └─ tiles          102 tiles, 102 distinct destinations
   │
   ├─ Operations home      its own richer home (ops_operations_home)
   │
   └─ command palette      ops_nav_index() — flattens the SAME gated
                           definitions, so it can never offer a screen
                           the rail would have hidden
```

One definition, three presentations. `lib/navindex.php` states this in its own
header, and the code matches it.

---

## 2. Areas and what they hold

| Area | Sections | Tiles | Gate |
|---|---:|---:|---|
| Operations | own home | — | `operations` licensed |
| Sales | 1 | 9 | `sales` licensed |
| Marketplace | 1 | 13 | `connect` licensed (M9) |
| Quality | 2 | 23 | within Operations |
| Reporting | 4 | 8 | `reporting` licensed |
| Money | 2 | 11 | `money` licensed |
| Insights | 1 | 4 | core |
| Directory | 2 | 8 | core (+1 in M11) |
| Admin | 7 | 26 | core (+2 in M11) |

**Before M11:** 99 tiles, 98 distinct routes, **1** route in two areas.
**After M11:** 102 tiles, **102 distinct destinations, 0** offered by two areas.

---

## 3. Where a person lands — now one rule

`ops_landing_decide($user, $onboardingSeen, $roleLandingUsed)` in
`lib/workspace.php`. Pure: no redirect, no session write, no output.

| # | Condition | Mode | Goes to |
|---|---|---|---|
| 1 | setup unfinished **and** this person can finish it | `cockpit` | `/workspace/setup` |
| 2 | setup unfinished **and** they cannot | `welcome` | `/welcome` |
| 3 | a personal or role landing is configured, and they may open it | `role` | that route |
| 4 | Operations not licensed **and** People & hiring is | `recruitment` | recruitment home, rendered in place |
| 5 | otherwise | `dashboard` | the dashboard |

Each answer carries a `why` a person can read. 1 and 2 fire **once per session**
— both of them, which is what C2 fixed.

---

## 4. The nine home screens — all still present

| Screen | Rendered by | Kept because |
|---|---|---|
| `dashboard` | `index.php` | the default home |
| `operations_home` | `lib/tosrm.php` | Operations is the deepest area and earns its own |
| `area_home` ×8 | `lib/areas.php` | one generated page per area |
| `crm_dashboard` | `lib/crmdash.php` | a Sales working view, reached from Sales |
| `recruitment_home` | `lib/recruit.php` | HOME for a recruitment-only company |
| `cockpit_home` | `lib/setup_cockpit.php` | the setup front door |
| `owner_home` | `lib/owner_home.php` | the platform owner's console |
| `welcome` | `lib/onboarding.php` | orientation for a company mid-setup |
| `role_workspaces` | `lib/workspace.php` | admin screen for configuring landings |

---

## 5. Route coverage

| Measure | Value |
|---|---|
| Dispatcher routes (`case $route ===` form) | 262 (the true total is higher — prefix and regex routes are not counted) |
| Reachable from navigation | 109 → **112** after M11 |
| Leaf actions, correctly absent from navigation | 25 |
| Record-detail screens reached by clicking a row | the bulk of the remainder — correct design, not a gap |
| Genuinely address-only screens | **11 → 8** after M11 |

---

## 6. Entitlement and navigation

| Signal | Behaviour |
|---|---|
| Area in the rail | `ops_area_has()` — hidden when the module is not licensed |
| Tile inside an area | permission-gated, and the area is already licence-gated |
| Command palette | built from the same gated definitions |
| Workspace launchpad | filters through `ops_module_gate($route, true)` |
| Gate peek (`ops_module_gate($r, true)`) | now honest about Marketplace too (C6) |
| The actual click | **M5–M10 server-side enforcement — the authoritative boundary** |

Verified in the walkthrough: with Operations + Reporting only, the Money, Sales
and Marketplace areas are hidden from the rail, the palette offers none of their
routes, and typing the address is still refused.

---

## 7. The two messages a refusal uses

| Situation | What the user is told |
|---|---|
| The company has not got the module | "The *Money* module is not switched on for this installation." |
| The person has not got the right | "You don't have access to the *invoicing* module. Ask your administrator." |

Two different sentences for two different problems — so nobody is sent on an
errand that cannot succeed. No SQL text, stack trace or licence internal reaches
a user.
