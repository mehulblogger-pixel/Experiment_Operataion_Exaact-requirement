# Milestone 9 — Known Limitations

---

## L1 · Existing hosted workspaces lose the marketplace until it is granted

The one that matters commercially. Marketplace is now a paid module, so a hosted
workspace whose entitlement does not name `connect` loses it — including
workspaces that have been using it, because `connect_enabled` **defaulted to ON**.

No entitlement was manufactured for them, deliberately: M4's standing rule is that
absence of evidence is not evidence of purchase, and a setting that defaults to ON
is not a record of a sale.

**Action required of the platform owner:** add `connect` to the entitlement of
each workspace that has bought the marketplace, in the Companies console. The
console already offers it — it iterates `PRODUCT_MODULES`, so no console change
was needed.

Unaffected: the control install and any self-hosted single business (no ceiling
applies to them).

---

## L2 · No plan tier includes Marketplace yet

`superadmin_tiers()` defines which modules each plan grants, and none names
`connect`. So until a tier is updated, Marketplace is sold as a **separately paid
module** per workspace rather than as part of a plan.

That is a pricing decision, not a code defect — recorded so it is made
deliberately.

---

## L3 · Marketplace has no fine-grained access modules

`connect` claims no access modules, so there is no `mod.marketplace.view` /
`.edit`, and the marketplace cannot be delegated at the level other modules can.
Its screens continue to gate on `connect_market_can()` — master or coordinator
level.

This is the existing design, preserved on purpose: inventing RBAC surface that
nothing reads and no role grants is how a module becomes unusable for the
customers who bought it. Giving the marketplace proper access modules is real
RBAC work with role-default and migration consequences, and belongs in its own
milestone.

**Consequence today:** a workspace that buys Marketplace gives it to everyone at
coordinator level or above. It cannot yet be restricted to a marketplace desk.

---

## L4 · The marketplace routes are still outside the M5 route-gate map

All 21 are enforced at their handlers' `connect_*_can()` gates, which is a real
chokepoint and is asserted for every one of them — but they are not in
`ops_module_gate()`'s map, so they do not benefit from the map's second layer.

Adding them would require the access modules L3 describes. Until then, a **new**
Connect route whose handler forgets to call a `connect_*_can()` gate would be
ungated. A test asserting that every `connect-*` route's handler calls one would
close that; it is a build-time guard and was not in M9's scope.

---

## L5 · The engagement → Operations seam is entitlement-gated at the marketplace end only

Marketplace engagements can flow through to Operations deployment. M9 gates the
**marketplace** end. Records already created in Operations before the marketplace
was switched off remain ordinary Operations records and stay workable — which is
correct (Marketplace OFF ≠ Operations OFF), but it does mean the Operations side
does not itself ask whether the marketplace was entitled when the record was
created. It is a historical record, not a live marketplace operation.

---

## L6 · The dormant webhook queue would need per-channel ownership

Unchanged from M8's L3: `lib/webhookq.php` has no callers. If it is ever wired up
and carries marketplace events, each channel needs its module established at
enqueue time. Recorded so it is not discovered later.

---

## L7 · MySQL/MariaDB was not executed

Verified, not assumed. No claim of MySQL validation. See `M9-TEST-RESULTS.md` §2.

---

## L8 · Not yet verified on the live server

M5 through M9 have not been uploaded to `operations.mghaiapps.com`.
`deploy-check.php` — regenerated here — confirms the uploaded files match this
code. **Deploying M9 without doing L1 first will switch the marketplace off for
every hosted workspace.**
