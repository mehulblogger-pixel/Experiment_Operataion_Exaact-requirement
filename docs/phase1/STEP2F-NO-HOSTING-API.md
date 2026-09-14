# Step 2F — There is no hosting API on this account (mPanel), and none is needed

**Date:** 2026-09-14
**Supersedes:** the cPanel API instructions in Step 2E §5.1.

---

## 1. The correction

The account is on MilesWeb **mPanel**, not cPanel. mPanel has no API token
screen, so "connect cPanel" cannot be done here. The cPanel code path stays in
the product — it is correct for cPanel hosts and for the public sign-up flow —
but it is **not** the route for this account and must not be presented as a
required step.

## 2. The operator's question, answered

> *"If this is the way, why did books.mghaiapps.com not require any such setup?"*

Because the two applications have different shapes:

| | Books | EXAACT |
|---|---|---|
| Companies per install | one | many |
| Databases needed | **one, ever** | **one per client company** |
| Who creates it | a person, once, by hand | something, every time a client is added |

Books needs one database for its whole life. It was created by hand in the
hosting panel once and its details were pasted into its config file. It never
creates a database at runtime, so it never needs an API.

EXAACT gives each client company its own separate database — that separation is
what stops one client seeing another's candidates. So something must create a
database each time a client is added: either a person in the hosting panel (two
minutes, once per client) or an API. An API would be *convenient*; it was never
*required*.

This distinction had not been stated anywhere, which is why an optional
convenience read as a blocking prerequisite.

## 3. Why the account is already safe without it

The two faults that destroyed the demo workspaces are both fixed in code, and
neither fix depends on a hosting API:

1. A new workspace's data file is written to `exaact_data/` **above the web
   root** — a folder outside the one the upload method clears. Deleting every
   file in the application folder no longer reaches it.
2. A workspace whose data file is missing is **never silently re-created**
   (Step 2E §3), so a mistake stays recoverable instead of being quietly
   papered over with an empty database.

A MySQL database per client is still better — it is a stronger boundary and it
backs up with the account — but it is now an upgrade, not a rescue.

## 4. Change made here

When the server has no way to create a database by itself, "Add a company" no
longer makes the choice silently. It asks, in two plainly-worded options:

* **Set it up for me** — a private data file outside the app folder, one click.
  Default.
* **Use a MySQL database** — strongest; create one in the hosting panel first,
  then paste the four details. With a short note on exactly what to create
  (database, user, all privileges) and a reminder that the panel adds its own
  prefix to both names.

Where a hosting API *is* configured, the screen is unchanged: the database is
created automatically and the operator ticks nothing.

The reasoning for showing the choice rather than deciding it: the two options
differ in how the data can be lost, and the person adding the client is the one
who lives with that. Hiding it is how the original file-in-the-app-folder
default went unnoticed for so long.

## 5. Still open (not blocking)

mPanel's sidebar has a **Developer** section which has not been inspected. If
MilesWeb exposes any database API there, `saas_db_autocreate_method()` is the one
place a third method would be added — the rest of the application would need no
change. Not investigated, not assumed.

## 6. Evidence

* Full regression: **7,114 passed, 0 failed** — unchanged; this is a view-layer
  change with no behavioural code touched.
* The new radio choice is presented only when `saas_can_autocreate_db()` is
  false, which is the state covered by `tests/test_storage_safety.php`.
