# Phase 5 — Security Results

Phase 5 publishes figures. It grants nothing, moves nothing and decides nothing.
The security questions it must answer are therefore about **what a figure
reveals** and **to whom**.

## What Phase 5 can and cannot do

| | |
|---|---|
| **Can** | Read demand, fulfilment, ageing, ownership history and SLA, and present them |
| **Cannot** | Move a candidate · change a requirement · grant or release a seat · alter an approval · reassign accountability · create, edit or delete any record |

The worst outcome of any Phase 5 refusal is that **a figure is withheld**.

## Entitlement — fail closed

Recruitment metrics join TAPI, and inherit its existing entitlement rule rather
than a new one:

1. every metric declares a lineage `source`;
2. `TAPI_SOURCE_MODULES` maps that source to an access module — `hiring`;
3. `tapi_metric_live()` **withholds** a metric whose lineage is unknown, and
   withholds one whose module the installation is not entitled to;
4. a withheld metric is **NO DATA**, not `0`.

That last point is the security-relevant one. A zero is a number a manager acts
on: "no open positions" is a decision to stop recruiting. An unlicensed figure
must not be able to masquerade as one.

Proved by H1–H5 (every recruitment metric declares a method and a lineage that
maps to `hiring`) and by mutation **P29**, which deletes the lineage mapping.

## Branch and tenant isolation

| Boundary | How | Proved by |
|---|---|---|
| **Tenant** | Structural — one database per tenant. There is no `tenant_id` for Phase 5 to be tricked about, and no Phase 5 query crosses a connection | The architecture, unchanged |
| **Branch — demand** | `scope_clause('r.office_id','r.sbu')`, the same clause the registers use | H7, H8 · mutation **P30** |
| **Branch — recruiter credit** | `rasg_cand_scope('c')`, the same clause the Command Centre uses | K9, K9b · mutation **P31** |
| **Branch — dashboard** | Inherited: the Command Centre's own filters, now feeding one engine | K4 |

A user scoped to Branch B sees less approved headcount than a user who can see
both, and **none of Branch A's source promises**. `no_scope` exists only for
probes and for callers that have already applied a scope of their own; the
mutations that remove scoping are caught.

## Never trusted

- **No tenant identifier is taken from the browser.** There is none to take.
- **A record id is never treated as proof of authorisation.** Every read is
  scoped by clause, not by whether the caller managed to name an id.
- **No hidden or disabled control is relied on.** The KPI engine is read-only;
  there is no control to hide.

## The M4 boundary

`rkpi_target()` needs the business's *needed by* date, which lives on the hiring
request. It asks through `hreq_get()`.

An earlier cut of this engine read `hiring_requests` with its own `SELECT`. M4's
own suite caught it — "no file outside the layer reads or writes
hiring_requests" — and it is now asserted a second time inside the Phase 5 suite
(K10, K10b), because this engine is the one most likely to be tempted again.
Mutation **P32** restores the direct read.

## What is NOT claimed

- **No concurrency evidence, because there is nothing to race.** Phase 5 adds no
  compare-and-swap, no compensator and no contended write. Its only writes are
  ledger entries appended beside a change that has already committed, and a
  failed ledger write is recorded as a failure to *observe*, never as a failure
  to transact — the business action stands. Manufacturing a race to be able to
  report one would be theatre.
- **No penetration testing** of the hosting platform; out of scope for this phase.
