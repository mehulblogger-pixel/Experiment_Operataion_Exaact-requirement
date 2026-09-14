# Milestone 11 — UX Consolidation

**Status:** complete · **Suite:** 8,060 passed, 0 failed · **Baseline:** 3c15141 (M10)
**Inspection evidence:** `docs/phase1/M11-INSPECTION-FINDINGS.md` (committed `422c6b4`, before any change)

---

## 1. What the inspection actually found

The inspection was done first and committed before a line was changed. It
contradicted the assumption that a system this size has tangled navigation:

| Measure | Value |
|---|---|
| Areas in the left rail | 8 (+ Operations, which has its own richer home) |
| Tiles across all areas | 99 |
| Distinct routes behind them | 98 |
| Routes offered by **two** areas | **1** |

`lib/areas.php` is a deliberate single source of truth — the rail link and the
area home come from one definition, so they cannot disagree — and
`lib/navindex.php` explicitly reuses it, so the command palette is a **layer
over** the rail, not a second nav tree.

**So M11 did not consolidate navigation menus, because there was only one.**
Reporting that honestly mattered more than finding something to change.

The real fragmentation was somewhere else.

---

## 2. The five things that were actually wrong

### C1 · Nothing could answer "where does this person start?"

Nine home screens exist, and the choice between them was a four-branch cascade
written inline in `index.php`. Every branch had a reason; together they meant no
two customers necessarily saw the same first screen and no single place in the
code explained why.

**Consolidated into one resolver** — `ops_landing_decide()` in `lib/workspace.php`,
the file that already owned landings (REUSE → EXTEND, not BUILD). It is a **pure
decision**: it performs no redirect and writes no session, so it can be read,
tested and explained. `index.php` still carries out the decision and still owns
the once-per-session flags.

**No landing screen was removed.** All nine still exist and still work.

### C2 · "Home" did not reach Home — a real defect

`index.php` said, in its own comment, *"Once per session **either way**, so Home
still reaches the dashboard afterwards."* But `$_SESSION['onb_seen'] = 1` was set
**only** on the cockpit branch.

So an ordinary member of staff — anyone without cockpit rights — in a company
whose setup was unfinished was redirected to `/welcome` **every single time they
clicked Home**, and could not reach their dashboard until somebody else finished
the setup. The code contradicted its own documented intent.

Fixed: the flag is now set on both paths, which is what the comment always said.

### C3 · One label, one URL, two different screens

`/templates` is a router: `?kind=quote` opens quotation templates, anything else
opens report templates. Both the Sales area and the Admin area offered a tile
labelled **"Document templates"** pointing at the **bare** route — so which
screen you got depended on your permissions rather than on the tile you clicked.

Fixed by naming the destination on both:

| Area | Label | Destination |
|---|---|---|
| Sales | "Quotation templates" (follows your terminology setting) | `/templates?kind=quote` |
| Admin | "Report templates" | `/templates?kind=report` |

One authoritative route, two unambiguous doors. **Zero destinations are now
offered by two areas.**

### C4 · The authoritative contract path was stated too late

The partner screen carries a contract form. It is **not** a rogue duplicate — it
has an "against quotation" selector and a "recorded directly" option, so removing
it would remove a legitimate action (§15).

What was wrong: the guidance naming the authoritative path ("register it from the
won quotation") appeared **only once the partner already had live contracts**. The
first person to register a contract was told nothing.

Fixed: the guidance now appears before the form in the empty case too. The door
stays open, and the primary flow is obvious — which is the §1 outcome
(*identify the authoritative flow, keep the better implementation*) achieved
without removing anything.

### C5 · Three screens reachable only by typing the address

Of the eleven address-only screens found, three are unambiguous tenant utilities
with a clean existing gate, and now have a navigation entry:

| Screen | Area | Existing gate, unchanged |
|---|---|---|
| `/backup` | Admin | master or `settings.manage` |
| `/ai-settings` | Admin | master or `settings.manage` |
| `/duplicates` | Directory | admin level, or clients/vendors edit |

The other eight are documented rather than guessed at — see
`M11-KNOWN-LIMITATIONS.md`.

### C6 · "Would this link work?" lied about Marketplace

Found during the manual walkthrough, not by a test. The route gate's **peek** mode
answers "would this link open?" for menus. Marketplace routes have no access
modules (M9's L4), so peek answered **yes** for a company that had not bought
Marketplace — and the click was then refused by `connect_enabled()`.

No menu actually exploited it: every navigation surface is built from the
licence-gated area definitions, which is why the rail, the palette and the
launchpad all correctly hid Marketplace. But a link that opens a refusal is
exactly the dead end §20 forbids, so the peek was made honest using the
`$peekExtra` mechanism already there for this purpose. Peek and handler now agree
in both directions.

---

## 3. What was deliberately NOT done

- **The rail, the area definitions and the command palette were not touched.**
  They are sound; changing them would have been change for its own sake.
- **The Dashboard was not rebuilt** (§5). It was left as it is; its module
  visibility was already corrected in M6.
- **No second implementation of anything.** No new dashboard, pipeline,
  requirement object, detail engine or nav tree.
- **Nothing was merged**: `requisitions` / `cx_requirements` stay distinct (§9);
  candidates, persons, employees, inspectors and `cx_professionals` stay distinct
  (§8); no master was merged (§7).
- **No route was deleted.** Every legacy address still resolves (§25).
- **No data was touched** — no schema change, no migration, no deletion (§26).
- **No permission semantics changed** (§27). A hidden tile is not a security
  mechanism; M5–M10 server-side enforcement remains authoritative, and the tests
  assert that the gate still refuses whatever the rail shows.

---

## 4. Files changed

| File | Change |
|---|---|
| `lib/workspace.php` | `ops_landing_decide()` — one pure resolver for "where does this person start?" |
| `index.php` | uses the resolver; **Home reaches Home** (C2) |
| `lib/areas.php` | templates tiles name their destination; three address-only screens given entries |
| `lib/ops.php` | the gate's peek mode is honest about Marketplace |
| `views/detail.php` | the authoritative contract path is stated before the form |
| `tests/test_m11_ux_consolidation.php` | new — 58 assertions |
| `tests/test_simplify_reportcfg.php`, `tests/test_admin_area_honesty.php` | label literals updated, intent preserved and strengthened |
| `deploy-check.php` | regenerated |

**Five source files.**
