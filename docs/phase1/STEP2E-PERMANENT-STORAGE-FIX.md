# Step 2E — Permanent fix: workspace data can no longer be deleted by an upload

**Date:** 2026-09-14
**Decision:** The lost data was demo data. Recovery abandoned by the operator's
decision; the permanent fix is implemented instead.
**Status:** Implemented and tested. Phase 1 (entitlement) remains paused.

---

## 1. What went wrong, in one paragraph

A workspace's entire database was a single file *inside the application folder*.
The update method deletes every file in that folder before uploading fresh code.
So the update deleted the data. Then, because a missing file looks exactly like a
workspace that has never been used, the application would have created a fresh
empty one in its place — which is what turns "restore it from a backup" into
"it is gone."

Two independent faults. Both are now fixed.

## 2. Fix 1 — a new workspace gets a real database, not a file

The code already knew how to create a MySQL database two ways:

* a **cPanel API token** — what shared hosting has (MilesWeb and most cPanel
  hosts), used by the public sign-up path;
* a **database-admin credential** in `config.local.php` — a self-managed VPS.

But `tenant_auto_storage()` (lib/tenant_migrate.php) only ever checked the second
one. On cPanel hosting that check fails, so **every workspace created from the
admin console silently became a file** — even on a server perfectly capable of
making a real database. The console's "✨ create the database automatically"
option was hidden for the same reason.

Now there is one answer to the question, `saas_db_autocreate_method()`, which
tries cPanel first and the admin credential second, and one creator,
`saas_autocreate_db()`. Everything that provisions storage uses them:

| Path | Before | After |
|---|---|---|
| Add a company (console) | file, always, on cPanel hosting | **MySQL database** |
| Move an existing workspace to MySQL | offered only on a VPS | offered on cPanel hosting too |
| Public self-sign-up | already used cPanel | unchanged |
| Server with neither method | file | file — but **outside** the app folder |

`saas_autocreate_db($key, false)` creates the database only, skipping the
subdomain, for moving an existing workspace that already has its web address.

## 3. Fix 2 — a missing data file is never silently re-created

This is the fault that decides whether an incident is recoverable.

`saas_tenant_data_missing()` answers one question: *is this workspace's data file
missing when it ought to be there?* It is called from `saas_enter_tenant()` —
the single point every entry path goes through, and, critically, **while the
control database is still the live connection**. One step later the connection
switches to the workspace, and the act of connecting is what creates the empty
file. Checking afterwards would be too late.

If the file is missing on a workspace that was already set up, entry is refused
and the person is told:

> This workspace's data file is missing, so it cannot be opened. It has NOT been
> re-created: an empty workspace here would make the real data harder to
> restore. Restore the file from your hosting backup, or contact support.
> Nothing has been deleted by this refusal.

The login screen shows that instead of "your workspace is still being set up" —
which was the old message, and which would have sent the owner away to wait for
something that was never going to happen.

### How it tells a deleted workspace from a new one

A new column on the control database, `saas_tenants.provisioned_at`. The control
database is MySQL and lives outside the application folder, so no upload can
touch it.

It maintains itself. `saas_tenant_seen_alive_sweep()` runs on ordinary
control-install page loads: for any workspace whose data file is **present and
non-empty**, it stamps the date once and never again. No login, no migration and
no operator action is needed — the system simply notices that the data exists,
and from that moment a missing file is known to be a *missing* file.

Edge cases covered by tests: an empty (zero-byte) file counts as missing, because
that is exactly what a half-made connection leaves behind; a MySQL workspace is
never blocked, because it has no file to lose; a genuinely new workspace is still
created normally.

## 4. What the operator sees

The cPanel settings panel now states the consequence in plain language rather
than leaving it as a technical convenience:

* **Not connected:** *"This also decides how safe each client's data is.
  Without it, a new workspace's data is kept in a file on the server. With it,
  every new workspace gets its own real MySQL database — which no file upload,
  however careless, can delete."*
* **Connected:** *"New workspaces get their own MySQL database automatically."*

The existing per-workspace panel (Companies → a workspace → *Where this
workspace's data is stored*) already warns when a workspace is a file inside the
app folder and offers a one-click, non-destructive move into MySQL. That move
is now available on cPanel hosting, where before it was not.

## 5. What still needs to be done by hand

1. **Connect cPanel** (Cloud workspaces → Automatic provisioning). Create an API
   token in cPanel → Manage API Tokens and paste it. This is what switches new
   workspaces to real databases — the code change alone cannot do it.
2. **Stop deleting before uploading.** Uploading over the top is enough.
   Deleting first buys nothing and is what destroyed the data.
3. For any workspace still shown as a file in the app folder, use
   **Move to MySQL now**. It copies, verifies row counts, then repoints routing;
   the original file is kept.

## 6. Out of scope, deliberately

* No repair or re-creation of the two lost workspaces — the operator confirmed
  the data was demo data.
* No entitlement work. Phase 1 stays paused.
* Backups: `lib/backup.php` already writes above the web root and runs daily per
  signed-in workspace. Scheduling a backup for workspaces nobody signs in to is a
  separate, smaller piece of work and is not included here.

## 7. Evidence

* New suite `tests/test_storage_safety.php`: **30 passed, 0 failed**
* Full regression: **7,114 passed, 0 failed** (was 7,084; +30, zero regressions)
* PHP 8.4.19, SQLite harness. The cPanel API and MySQL creation paths are covered
  by their decision logic; the live API calls themselves are **not** exercised —
  no cPanel server is available in this environment.

## 8. Note on the code fingerprint

This change touches files under `lib/`, so the application's code fingerprint
changes and the next page load will run its normal migration pass. That is the
intended behaviour for an application change (it is how the new
`provisioned_at` column gets added), and it is safe here: both workspaces are
empty, so there is no entitlement state left to preserve.
