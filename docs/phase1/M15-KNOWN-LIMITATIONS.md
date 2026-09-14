# Milestone 15 — Known Limitations

No critical or high production blocker remains. Each item below is understood and
controlled.

---

## L1 — MySQL was validated on MariaDB 10.11, not on the exact production host

**Severity: Low — UAT item.**

The engine, charset, collation and SQL-mode behaviour were exercised for real,
and the application sets its own session charset and SQL mode, so a host with
different defaults needs no change. But MilesWeb mPanel adds things this
container does not have: its own MySQL build and version, account name-space
grants (`acct_%`), connection limits, and shared-host contention. `lib/db.php`
already retries transient "too many connections"; that path was not exercised
under real contention.

**First deploy is the remaining test.** The checklist's final five checks cover it.

---

## L2 — `invoices.invoice_no` defaults to `''` while the unique index expects `NULL`

**Severity: Low — latent, not active.**

On MySQL the unique index is plain, and MySQL allows many `NULL`s but only one
`''`. The application is correct: `books_invoice_create()` inserts `NULL`
explicitly, and the migration back-fills existing `''` to `NULL` before building
the index. So no production path creates a second blank draft.

The *column default* is nonetheless `''`, so any future code that inserts an
invoice without naming `invoice_no` would fail on the second draft — which is
exactly what a test fixture did, and how this was found. Changing the default is
a schema change and was therefore **not** made here. Recorded for review.

---

## L3 — No live HTTP host: transport layer untested

**Severity: Informational.**

Everything ran in-process against real databases — real routes, sessions,
dispatch, migrations. But with no web server there is nothing to say about TLS
configuration, HTTP security headers, cookie flags as set by the host, or
HTTP-level rate limiting. Those belong to the mPanel deployment.

---

## L4 — Pre-M13 sessions carry no workspace binding

**Severity: Low — carried forward from M13 L1, unchanged.**

They acquire a binding at the next sign-in, and both paths that could have abused
the gap are independently closed. A forced sign-out is available as a deployment
step (checklist §6) if preferred. No code change.

---

## L5 — Performance was sanity-checked, not engineered

**Severity: Informational — by instruction (§24).**

Schema build 11.3 s on an empty database, ~1.2 s steady state; 185 indexes present
and applicable; full 8,246-assertion suite ~2 minutes on MariaDB. No runaway
query, no fatal startup dependency, no broken index. **No load testing was done**
— no concurrent users, no production-sized tables, no slow-query log analysis.
A separate exercise if the business wants throughput numbers.

---

## L6 — Project costing scope is unresolved by design

**Severity: Medium as a policy question, not a defect.**

See `M15-PRODUCTION-READINESS-AUDIT.md` §4. **ARCHITECTURAL DECISION REQUIRED.**

---

## L7 — Test isolation on MySQL is weaker than on SQLite

**Severity: Informational — now understood and contained.**

MySQL treats DDL as an implicit commit, so a test wrapping itself in
`beginTransaction()` and then calling `*_migrate()` is not isolated. The
assertions that depended on this are now delta-based and engine-independent.
Other tests still use the transaction idiom harmlessly, but anyone adding a test
that asserts a **whole-table total** should assert a delta instead.
