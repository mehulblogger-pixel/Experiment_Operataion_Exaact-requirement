# Journey H — security. Run 2026-10-10, automated.

**Verdict: PASS, after one real hole was found and closed.**

The playbook lists thirteen checks to do by hand. Rather than do thirteen, I signed
in as each test role against a running copy of the application and asked for **all
753 routes in turn** — which is what H9 means by *"type a forbidden address straight
into the browser bar"*, done exhaustively instead of thirteen times.

A refusal in this application bounces to the home page with a red message rather
than showing an error page, so the test is "did I actually get the screen", not "did
I get an error". Every result below was judged that way.

## What each role could reach

| Role | Routes reached, of 753 | Anything it should not have |
|---|---|---|
| Field inspector | **17** | none, after the fix below |
| Finance | **50** | none |
| Coordinator | **114** | none |

Checked explicitly and found clean: no admin screens (`users`, `access`, `settings`,
`licence`, `billing`, `super-admin`, `tenants`, `approval-rules`, `reset-data`),
no salary or CTC screens for a coordinator, and no recruitment **data** for finance.

## The hole: H9 — eight endpoints with no gate at all

An inspector, who has no clients access of any kind, could type:

```
/partner-contact?id=1   ->  a client contact's NAME, EMAIL and MOBILE
/partner-address?id=1   ->  that client's site address
```

…plus `/partner-sites`, `/partner-pos`, `/partner-gaps`, `/partner-meta`,
`/po-lines`, `/contract-no-check`.

**The cause was not a weak check. It was no check.** These eight were in neither
`ops_route_module_map()` nor `ops_module_family()`, so the module gate computed
"no module" and returned without asking anything — the only routes in the
application sitting entirely outside it. Nothing in the interface ever offered
them, which is exactly the point H9 makes: *hiding a menu item is not security.*

Fixed, verified both ways (inspector refused, coordinator still served), re-swept,
and locked by `tests/test_security_json_endpoints.php`. Recorded as **R-25**.

## The other checks

| | Result |
|---|---|
| **H1–H3, H7** — admin screens as coordinator / finance / inspector | Refused, all roles |
| **H6** — finance reaching recruitment | Opens an empty inbox; **no recruitment data**. That a finance user has a recruitment approvals screen at all is the *duplication* problem, not a security one |
| **H8** — salary / CTC as coordinator | Not reachable |
| **H11** — another company's records | **Does not apply** — single-company install (no tenants table). H10 is the test instead |
| **H12** — a retired user signing in | Refused |
| **H13** — repeated wrong passwords | Locks out ("try again in 15 minutes"), and a real username and a fake one get **identical wording**, so neither can be told from the other |

## What this did NOT cover, stated plainly

- **H4** (approving your own hiring request), **H5** (clearing a Review Required flag
  without the permission) and **H10** (another branch's client commercial terms) need
  seeded business records and a second office to test honestly. They are **not done**.
- The sweep covers **GET** requests. POST actions were covered separately by the R-20
  permission audit, which traced all 416 routes and found every write action guarded.
- One methodology note worth keeping: the first coordinator sweep returned 4 routes
  and looked alarming. The cause was my own H13 test — it had locked that account out
  minutes earlier. Cleared and re-run. A security result that looks surprising is
  worth re-checking before it is believed.
