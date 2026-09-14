# Step 2G — How new client databases get created, and what the industry does

**Date:** 2026-09-14
**Question asked:** *"To set up every user manually for a SQL database is not
possible. What can be the best solution? Can we take reference of any other
cloud-based SaaS like Zoho?"*

---

## 1. The three ways real SaaS products do this

| Model | Who uses it | Isolation | Adding a customer | Fits this account? |
|---|---|---|---|---|
| **A. One database per customer** | enterprise / regulated products, EXAACT today | strongest — enforced by the database | needs a database created | only if something can create databases |
| **B. One schema per customer** | PostgreSQL products | strong | needs a schema created | no — MySQL has no separate schemas |
| **C. One shared database, every row tagged with its customer** | **Zoho, Salesforce, Shopify, HubSpot, Freshworks, Notion, Linear** | enforced by application code | insert one row — instant | yes, but it is a rewrite |

**Model C is what large multi-tenant SaaS actually runs on.** Adding a customer
is a row, not an act of infrastructure; there is one database to back up,
migrate and monitor; and it scales to millions of tenants on the same schema.

Its cost is real and is the reason it is not a free upgrade: isolation stops
being a property of the database and becomes a property of the code. Every
single query must filter by customer, and one missed filter leaks one client's
candidates into another's screen. Products that run Model C invest heavily in
making that filter impossible to forget — a single data-access layer no query
may bypass.

EXAACT is built on Model A today, deliberately: `docs/phase0/15-ARCHITECTURE-LOCK.md`
records one database per tenant with **no tenant column anywhere**. Moving to
Model C means adding a customer column to every table and a filter to every
query across ~200 library files, with a cross-tenant data leak as the failure
mode. That is a re-engineering project, not a fix, and it should not be
attempted while the product is still changing shape.

**Recommendation: stay on Model A. Remove the manual step instead of changing
the architecture.** Revisit Model C when client count reaches the point where
per-client databases are genuinely unmanageable — realistically a few hundred.

## 2. Removing the manual step, in order of preference

Model A only hurts because something must create a database per client. There
are four ways, and the application now tries them in this order
(`saas_db_autocreate_method()`):

1. **`cpanel`** — a cPanel API token. Not available here: this account is on
   mPanel (Step 2F).
2. **`admin`** — a database-admin credential in `config.local.php`. A
   self-managed VPS only.
3. **`self`** — *new* — the application's own database login creates the
   database. Some shared hosting permits this and some does not, and the only
   way to know is to ask the server.
4. **nothing** — a private data file outside the app folder. Safe from uploads,
   zero setup, and the default.

### The new one-click test

Cloud workspaces now opens with **"Test whether this server can create
databases."** It creates one empty database with a random generated name and
drops it again immediately; it never reads, alters or drops anything that
already exists. The answer is remembered.

* **Yes** → every new company gets its own MySQL database automatically, with
  nothing to configure and no per-client work. The problem is solved outright.
* **No** → the screen says so plainly and offers the two options that do work.

The capability is used **only** after the test has proved it. Assuming it would
fail on every single company creation, which is exactly the kind of silent
fallback that caused the original data loss.

New databases are named inside the account's own prefix, derived from the
control database's name (`mghaiapp_ops` → `mghaiapp_xyz_recurit`), because a
hosting account is normally only permitted to own names in its own namespace.
The application's existing login is reused as the workspace's credentials: it
created the database, so it already owns it, and no second user is needed.

## 3. If the answer is no

Nothing is broken, and no manual step is forced:

* **Do nothing** — each company's data is a private file kept **outside the app
  folder**, where the upload method cannot reach it, backed up daily above the
  web root. SQLite is a real database, and for a recruitment workspace with a
  handful of concurrent users it is entirely adequate. Its genuine limit is
  concurrent *writers* — it locks the file per write — which starts to matter at
  dozens of people saving at the same moment inside one company.
* **Create a database per client by hand** when a client is large enough to
  deserve it. Two minutes in mPanel → Databases, then paste four values. This is
  now a clearly-offered choice on "Add a company" rather than an advanced
  fold-away (Step 2F).

Both are already built. Neither depends on any hosting API.

## 4. Not done, and why

* No move to Model C. Out of proportion to the problem, and the wrong moment.
* No change to the Phase 0 architecture lock.
* Phase 1 (entitlement) remains paused.

## 5. Evidence

* `tests/test_storage_safety.php`: **41 passed, 0 failed** (was 30; +11)
* Full regression: **7,125 passed, 0 failed** (was 7,114; zero regressions)
* PHP 8.4.19, SQLite harness. The `CREATE DATABASE` probe is covered for its
  refusal path and its contract; the successful MySQL path is **not** exercised —
  no MySQL server is available in this environment. It is exactly what the
  one-click test on the live server is for.

---

## Addendum — the first live run of the capability test was a FALSE NEGATIVE

**Date:** 2026-09-14

The live test reported:

> This server does NOT let the application create databases by itself.
> `SQLSTATE[42000]: 1044 Access denied for user 'mghaiapp1_mehul'@'localhost' to database 'exaact_probe_9702f7ec01'`

**That conclusion was wrong, and the error message says why.** A panel-managed
hosting account normally *is* allowed to create databases — but only inside its
own name space, granted as something like `` `mghaiapp1\_%` ``. The probe asked
for `exaact_probe_…`, which is outside it. MySQL error 1044 was refusing the
**name**, not the capability.

So the test measured the wrong thing and declared a capability missing that may
well be present. That is a worse failure than not testing at all, because it
sends the operator down the manual path for no reason.

### Fixed

The probe now derives every name space this install can be seen to use — from
the control database's name (`mghaiapp1_ops`) and from the database user
(`mghaiapp1_mehul`) — and tries each in turn before falling back to an
unprefixed name:

```
mghaiapp1_probe_a1b2c3d4      ← this account's own name space, tried first
probe_a1b2c3d4                ← only if the server has no such restriction
```

The prefix that actually succeeded is remembered
(`saas_db_selfcreate_prefix`) and used for every workspace database
thereafter, so they are always created where this account is permitted —
`mghaiapp1_xyz_recurit`. A proven prefix outranks a derived one.

Prefix candidates are validated as plain letters and digits before ever reaching
SQL; anything else is discarded.

### Also corrected: "that is normal on shared hosting"

That sentence was mine and it was a guess stated as fact. Whether a login may
create databases is decided by **the privileges granted to that MySQL user**, not
by the kind of hosting — a managed panel restricts it on a VPS exactly as it does
on shared hosting. The message now says that, and names the user it tested with
and the names it tried, so the answer can be checked rather than believed.

### A third route, for a VPS

On a server of one's own, a database login permitted to create databases is
normally available on request from the host. Dropped into `config.local.php` as
`$SAAS_DB_ADMIN`, it makes provisioning fully automatic through the existing
`admin` method — no code change, no per-client work, ever. This is now offered on
the screen as the third option.

### Evidence

* `tests/test_storage_safety.php`: **49 passed, 0 failed** (was 41; +8)
* Full regression: **7,147 passed, 0 failed** (was 7,139; zero regressions)
* The successful `CREATE DATABASE` path still cannot be exercised here — no MySQL
  server in this environment. The prefix selection, ordering, de-duplication and
  rejection of unsafe prefixes are all tested directly.
