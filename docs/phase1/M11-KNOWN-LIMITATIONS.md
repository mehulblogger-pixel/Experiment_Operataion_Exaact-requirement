# Milestone 11 — Known Limitations

---

## L1 · Nine home screens still exist — the rule was consolidated, not the screens

M11 gave "where does this person start?" one answer. It did **not** reduce the
number of landing screens, because each is genuinely used: a recruitment-only
company needs the recruitment home, a company mid-setup needs the cockpit, the
platform owner needs the owner console.

Consolidating the **screens** would mean redesigning them, which §33 forbids and
which would risk working workflows. The resolver makes the choice explainable and
testable; reducing the set is a product decision for a later milestone.

---

## L2 · Eight screens remain reachable only by typing the address

Three of eleven were given navigation entries. The other eight were left
deliberately:

| Screen | Why it was left |
|---|---|
| `/feature-gates`, `/financial-control`, `/compliance-rules` | Super-admin / control-install screens — not tenant navigation |
| `/dt-columns` | A column-preferences endpoint, not a screen |
| `/billing`, `/entity-360` | Real screens, but which area owns them is a product decision, not mine |
| `/ads-roi`, `/boss-renew` | Same — Sales and Money respectively are plausible, but guessing would put a tile in the wrong place |

Each needs one decision from the product owner, after which adding a tile is a
one-line change.

---

## L3 · Terminology audit produced a map, not renames

§13 asked for a terminology audit and explicitly said **not** to rename technical
entities. The application already has a terminology engine (`term_overrides`,
`Tl()`, `THP()`, `T_REG()`) that lets each company choose its own words — the
Sales templates tile now uses it, so a company that calls a quotation a "proposal"
sees "Proposal templates".

What M11 did **not** do is force a single vocabulary across Requisition /
Requirement / Vacancy / Position or Candidate / Person / Professional. Those words
map to genuinely different objects that §8 and §9 forbid merging, and renaming
the user-facing labels without merging the objects would make two different things
look like one — worse than the inconsistency. The mapping is recorded in
`M11-NAVIGATION-MAP.md` and `M11-DUPLICATE-FLOW-MATRIX.md`.

---

## L4 · Forms, empty states and error messages were audited, not rewritten

§11, §18 and §19 asked for audits. The audit found the gate's two refusal
sentences already correct and distinct, and no SQL or stack-trace text reaching a
user from the gate (asserted). Individual form-level work — field ordering,
conditional disclosure, per-screen empty states — was **not** attempted: it spans
hundreds of screens and cannot be done safely in one milestone without becoming
the frontend redesign §33 forbids.

**This is the largest deliberately unfinished part of M11.** It wants its own
milestone with a screen-by-screen priority list.

---

## L5 · Accessibility and mobile were reviewed, not remediated

§23 and §24 asked for a review using the existing UI architecture. The changes M11
made are label and definition changes that inherit the existing responsive tile
and form styling, so nothing was made worse. A systematic pass — focus order,
labelled inputs, table behaviour at narrow widths across every screen — was not
performed and should not be claimed.

---

## L6 · Marketplace routes are still outside the route-gate map

Unchanged from M9's L4. C6 made the gate's **peek** honest about them, so no menu
can offer a Marketplace link that then refuses — but the routes themselves are
still enforced at their handlers rather than at the map. Closing that needs the
access modules M9's L3 describes.

---

## L7 · The duplicate creation doors were documented, not closed

Six ways to create a customer, four to register a contract. M11 made the
authoritative contract path obvious and left the doors open, because §15 forbids
removing legitimate actions and each door has a real reason (bulk import, lead
conversion, marketplace onboarding).

If the business wants fewer doors, that is a product decision with a data and
training impact, and it should be taken explicitly rather than inside a UX pass.

---

## L8 · MySQL/MariaDB was not executed

Verified, not assumed. No claim of validation. M11 changes no schema and no data.

---

## L9 · Not yet verified on the live server

M5–M11 have not been uploaded. **M9's L1 still applies first:** hosted workspaces
need `connect` added to their entitlement before deployment, or the marketplace
switches off for them.
