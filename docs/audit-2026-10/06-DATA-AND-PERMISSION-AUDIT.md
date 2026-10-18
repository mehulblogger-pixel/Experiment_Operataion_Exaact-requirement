# D6 — Data and permission audit (Phase F, in progress)

First chapter: **GAP-008 — the routes that carry no module gate.** This was
named the highest-value security task in the charter, so it is done first.

---

## 1. The question

EXAACT dispatches a screen like this (`ops.php:3429`):

```php
if ($mod && !can("mod.$mod.view")) { access_deny($mod); … }
```

`$mod` is resolved from `ops_route_module_map()`, then from a prefix fallback
`ops_module_family()`. **If neither yields a module, that line does nothing** —
the route passes the module check and the licence check untouched. It may still
enforce something of its own further in, and most do. But it is not protected by
the system-wide gate.

So: how many routes are in that position, and does it matter?

---

## 2. Correction first — the number was wrong

The gap register said **99 routes have no module**. That was wrong, and the
mistake is worth naming because it is a common one.

`ops_route_module_map()` has **466 keys**, and I read that as "466 of the 565
dispatched routes are gated". It is not the same thing: **140 of those 466 keys
are not dispatched routes at all** (`lead`, `lead-new`, `lead-move` … handled
elsewhere or left behind), and many dispatched routes are absent from the map.

Measured properly (`evidence/ungated-routes-list.php`, which resolves each
dispatched route exactly as the dispatcher does):

| | Count |
|---|---|
| Dispatched routes | 565 |
| Module resolved from the map | 326 |
| Module recovered by the prefix fallback | 39 |
| **No module at all** | **200** |

So it is **twice** what I reported. The register is corrected.

---

## 3. The answer: a defence-in-depth weakness, not an open door

Having no module gate is not a hole by itself. What matters is "no module gate"
**and** "opens for someone who should not be there". Inspector and Finance are
the two narrowest roles in the business (32 and 59 of 256 screens), so they are
the probes.

`evidence/ungated-triage.py` sorts all 200:

| Bucket | Count | Meaning |
|---|---|---|
| **ENFORCES** | **158** | refused or redirected for both probes — the route applies its own check |
| OPEN-ONE | 17 | opens for one probe |
| OPEN-WIDE | 8 | opens for both |
| SELF | 8 | a personal screen whose content is the signed-in person's own |
| PUBLIC | 9 | sign-in, verification, the agreement, the marketplace storefront |

**158 of 200 are already protected by their own handler.** The 25 that open were
then read one by one (`evidence/ungated-deep-probe.py`), asking the two
questions that decide whether an open screen matters: *can they change
anything* (every `<form action>` on the page, less the site-wide search box),
and *can they see other people's records* (count of data-table rows).

### What an inspector can actually reach

Nine of the 25. Every one of them, with what it turned out to be:

| Route | Rows of data | Can post to | What it is |
|---|---|---|---|
| `approvals-setup` | 0 | nothing | A read-only explainer. The page's own heading to an inspector is **"Nothing here is yours to change"**; the editable half is simply absent (25 KB against the administrator's 51 KB) |
| `complaints-policy` | 0 | nothing | The policy text, read-only, and it says so |
| `reporting` | 0 | nothing | Area hub; says so |
| `get-started` · `my-approvals` · `quality` | 0 | nothing | Hubs and personal lists. The Quality hub shows only tiles the person can use — verified separately in GAP-011 |
| `my-signature` | 0 | `/my-signature` | **Their own signature.** Correct |
| `writing-assistant` | 0 | `/writing-assistant` | A **local phrase expander** (`tech_expand()`, `idems.php:9377`) — string matching against the phrase library, no external call, nothing paid. Inspectors write reports; this is exactly who it is for. Managing the library is separately gated (`idems.type.manage`) |
| `connect` | 0 | `/connect/start` | The **public marketplace storefront** ("Post a job. Find the right inspector."). The form is a **GET** with a search phrase. Nothing is written |

**No write reached a business record, and no screen showed another person's
data** — every one returned zero data rows.

### What Finance can reach

23 of the 25, which is unsurprising: `operations`, `sales`, `insights`,
`money`, `directory`, `command-centre` are **area hubs**, and a hub renders only
the tiles the viewer can use (all returned 0 data rows; `operations` returned 3,
which is its own tile list). The four screens Finance can post to —
`cost-reconciliation`, `revenue-reconciliation`, `reimbursable-dedup`,
`timesheet` — are reconciliation screens, which is what `finance.reconcile`
is for.

### Verdict

**The missing module gate did not yield a reachable hole.** Every ungated route
I could open as a least-privileged role either refused the action itself, said
plainly that nothing there was theirs to change, or was correctly public or
personal. Credit where it is due: `approvals-setup` answering an inspector with
*"Nothing here is yours to change"* is better behaviour than most systems
manage.

**The weakness is real but it is about the future, not today.** There is no
systematic gate on 200 routes, so protection rests on each handler remembering
to check. 158 remember. A route added tomorrow in this family inherits nothing,
and the one place where that already bites is the money module: the write gate
there accepts `data.credit` (GAP-010) and is safe *only* because every money
route happens to also carry a module. Two of the seventeen money routes get
their module from the prefix fallback rather than the map — a rename away from
losing it.

**Recommendation.** Not "add a module to 200 routes" — many genuinely have none
to add. Instead make the absence **deliberate and visible**: an explicit
allow-list of routes that are intended to be module-free (public, personal,
hub, action handler), and a test that fails when a dispatched route is neither
in the module map nor on that list. Then a new ungated route cannot be added by
accident, which is the actual risk. Severity **P2**, down from P1: no live
exposure found, and the fix is a guard rail rather than a repair.

---

## 4. Found on the way: the vendor register's guard admits almost everybody

**SUSPECTED · P2 · needs the owner's rule**

`/vendors` is not the Directory's supplier list — it is the **vendor register
inside Reporting**, used for expediting and document control
(`ops_idems_vendors()`, `idems.php:4369`). Its guard:

```php
$canView = can('mod.idems.view') || can('mod.idems.edit')
        || can('mod.clients.view') || can('mod.vendors.view')
        || is_master_of(['idems','clients','vendors']);
```

Four different module rights, any one of which admits you. Resolved against
every role, **17 of 18 pass it** — including Inspector, Senior Inspector,
Quality Head, Technical Head and Marketing Executive (the last via
`mod.clients.view`). **The only role refused is FINANCE** — the one function
with the clearest business need for vendor records, since it pays their
invoices.

**A discrepancy I could not settle, recorded rather than smoothed over.** By
that guard an inspector should open the register. Driven, an inspector gets
**302** — while `/documents`, `/document-new` and `/expediting`, all in the same
module, open normally for the same session. So the refusal is specific to
`/vendors` and comes from somewhere I did not isolate; the R-20 route-verb gate
is the likely candidate (`/report-types`, a configuration route, also answers
302 for the same session). **The outcome is safe; the reason is unproven.** It
is wrong to report either "inspectors can read vendor records" (the code reads
that way) or "all is well" (the behaviour reads that way) without closing the
gap, so it stays open.

**Question for the owner (Q-G).** Who should see the vendor register — the
people who write reports against a vendor's work, the people who pay the
vendor, or both? Today it is the former and not the latter, which looks
backwards.

---

## 5. Still to do in Phase F

- [ ] Resolve the `/vendors` discrepancy; map the R-20 verb gate across all routes
- [ ] The allow-list + guard test recommended in §3
- [ ] Every remaining cell of `docs/02-permission-matrix.md` tested as each role
      (Money row 215 and the NCR/CAPA rows are done)
- [ ] Tenant isolation: one tenant's data unreachable from another
- [ ] Office and business-unit scoping, including the `home_office_id`-blank case
      already fixed once
- [ ] Entity integrity: orphans, duplicates, referential gaps across 317 tables
- [ ] Every money and time calculation recomputed independently
