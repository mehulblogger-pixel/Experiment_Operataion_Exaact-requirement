# EXAACT — Everything changed since Phase 1 · Step 2A

**Period covered:** 2026-09-14, from the Step 2A investigation to now
**Branch:** `claude/testing-branch-setup-0gqe8n`
**Commits:** 13 · **Files changed:** 18 · **+2,464 lines**
**Tests:** 7,147 passing, 0 failing (was 7,057 at Step 2A — 90 new tests, zero regressions)

**Phase 1 (the entitlement work) has not started and remains paused.** Everything
below is either diagnosis of a live data-loss incident, or the permanent fixes
that came out of it.

---

## 1. What actually happened, in one page

1. Two live workspaces — Sachee HR and Xyz Recruit — stopped opening.
2. Investigation proved their data files had gone. They had been stored as single
   files **inside the application folder**, which is the folder the update method
   deletes before uploading fresh code.
3. The data was demo data, so recovery was abandoned by your decision and the
   effort went into making it impossible to happen again.
4. Two separate faults had to combine to destroy the data. **Both are now fixed.**
5. Fixing them exposed three further problems — an upload that silently half-
   landed, credentials in the wrong file, and a capability test of mine that gave
   a false answer. All three are fixed too.

---

## 2. Timeline of changes

| # | Commit | What it did |
|---|---|---|
| 1 | `a46cd8e` | **Step 2C** — recovery scan: search every location for the missing workspace data |
| 2 | `121fa89` | **Step 2D** — corrected a false "recovery is possible" verdict |
| 3 | `bbc8817` | **Step 2E** — the permanent fix: real databases, and never re-create a deleted workspace |
| 4 | `b4d3e98` | Housekeeping: keep test folders out of the code download |
| 5 | `1cee5f9` | **Step 2F** — "Add a company" asks where the data goes instead of deciding silently |
| 6 | `82d0dd5` | **Step 2G** — one-click test: can this server create databases by itself? |
| 7 | `ef98613` | Put that test **above** the manual form, and stop naming cPanel (you have mPanel) |
| 8 | `f10654e` | **Step 2H** — `deploy-check.php`: prove an upload actually landed |
| 9 | `d17a1cb` | Fixed a **false negative** in the capability test (wrong database name, not missing permission) |
| 10 | `befef6a` | `deploy-check.php` now says *why* it refused, instead of a bare "page not found" |
| 11 | `90ba7f0` | **Step 2I** — show which config file is really in use; protect the rest from the web |
| 12 | `8efb1b3` | Capability test now proves a database can be **used**, not only created |
| 13 | `1a7955b` | Installation screen now stops a configured server from installing over itself |

---

## 3. The two faults that caused the loss — both fixed

### Fault 1 — workspaces became files when they could have been databases

The code already knew two ways to create a MySQL database: a cPanel API token,
and a database-admin credential. `tenant_auto_storage()` only ever checked the
second. On panel-managed hosting that check fails, so **every workspace created
from the admin console silently became a file** — a file inside the folder your
update method clears.

**Fixed.** One place now answers "can this server create a database?"
(`saas_db_autocreate_method()`), trying in order: a cPanel API token → a
database-admin credential → the application's own login → nothing. Whichever
exists is used. Where none exists, a file is still used, but **outside the
application folder**, where an upload cannot reach it.

### Fault 2 — a missing data file was silently replaced with an empty one

To the application, "file deleted" and "brand-new workspace" looked identical, so
it helpfully created a fresh empty workspace in the same place. **That single
behaviour is what turns "restore it from a backup" into "it is gone."**

**Fixed.** `saas_tenant_data_missing()` runs at `saas_enter_tenant()` — the one
point every entry path goes through, and critically *while the control database
is still connected*, because one step later the connection switches and the act
of connecting is what creates the file. If the data is missing, entry is refused
with a message that says plainly that nothing was created and the data can still
be restored.

To tell a deleted workspace from a new one it uses a new marker,
`saas_tenants.provisioned_at`, in the control database — which lives in MySQL
outside the application folder and no upload can touch. It maintains itself:
on ordinary page loads the application notices "this workspace's data file is
really there" and records it once, forever. No setup, no migration, nothing to do.

---

## 4. Three problems found while fixing those

### 4.1 An upload that silently half-landed

New code was uploaded and the screen did not change. File sizes settled it:
`index.php` on the server was the new file exactly, while `views/ops/tenants.php`
was still the old one. The top-level files replaced correctly and the ones inside
`lib/` and `views/` did not — the normal way a browser File Manager fails. The
application kept serving old code with no error anywhere, and there was no way
for you to see it.

**Fixed** — `deploy-check.php`. One self-contained page: sign in as
administrator, open it, and every stale or missing file is listed by folder with
the size on the server beside the size it should be. It is deliberately one file
carrying its own checksums, because what it must detect is *"the sub-folders did
not upload"*, so it cannot depend on the sub-folders.

### 4.2 Credentials in a file that is never read

Real database credentials were in **both** `config.php` and
`config.local.sample.php`. Only one file matters: `config.local.php` wins and
survives uploads; `config.php` is read only in its absence and is replaced by
every upload; the sample is read by nothing at all. The site was running on the
one file guaranteed to be overwritten, while a second copy of the password sat in
a file doing nothing.

Filling in the sample instead of copying and renaming it first is an entirely
reasonable mistake — it is the file that carries the instructions. What was
missing is that **nothing in the product said which file was in use.**

**Fixed** — `deploy-check.php` now opens with *"Where your database settings
live"*: each file, whether it holds real details, whether the application reads
it, which one supplied the live database, and numbered steps ordered so they
cannot strand the site. No password is ever shown.

### 4.3 My own capability test gave a false answer

The first live run reported *"this server does NOT let the application create
databases"*. The error said `1044 Access denied for user 'mghaiapp1_mehul' to
database 'exaact_probe_…'`. A panel-managed account normally **may** create
databases — but only inside its own name space. The probe had asked for a name
outside it, so MySQL was refusing the **name**, not the permission.

The test measured the wrong thing and declared a capability missing that might
well have been present — worse than not testing at all, because it would have
sent you down the manual path for no reason.

**Fixed.** The probe now tries your account's own name space first
(`mghaiapp1_probe_…`), then a plain name, remembers whichever succeeded, and
names every attempt in the message so the answer can be checked rather than
believed. *(On the retest both were refused, so the "no" is now genuine — hence
the pending request to MilesWeb.)*

---

## 5. Smaller corrections

* **"That is normal on shared hosting"** — my wording, and a guess stated as
  fact. Whether a login may create databases is set by the privileges on that
  MySQL user, not by the kind of hosting; a managed panel restricts it on a VPS
  exactly as on shared hosting. Corrected everywhere.
* **cPanel wording removed** — you are on mPanel. Manual steps now say "your
  hosting panel", and note that the panel prefixes database names, which is the
  usual reason a pasted name will not connect.
* **A recovery verdict computed from the wrong evidence** — the scan answered
  "recovery is possible" because *some* backup folder had snapshots. The only one
  was `__control`, the routing index, which can restore nothing. Verdicts are now
  per workspace, from that workspace's own evidence.
* **Create is not the same as use** — a grant can allow creating a database
  without allowing tables to be built in it. The test now proves both, because
  discovering it halfway through creating a real client is far worse.
* **Installation screen guard** — renaming the config file pointed the app at an
  empty database, and it offered to install itself. It now warns in red, first,
  that the data is untouched and the settings are pointing at the wrong database.
* **`.htaccess`** — protected `config.php` only. Now also
  `config.local.php`, `config.local.sample.php`, `tenants.php` and
  `tenants.sample.php`. `tenants.php` matters most: it carries every workspace's
  database details.
* **Stray files removed from the code download** — an empty `data.sqlite` and a
  test-run folder that shipped with every download.

---

## 6. Files changed

**Application**

| File | Why |
|---|---|
| `lib/saas_tenants.php` | automatic database creation, the missing-data guard, the capability test |
| `lib/tenant_migrate.php` | new workspaces prefer a real database over a file |
| `lib/tenants.php` | the capability test button and its result |
| `lib/cpanel.php` | create a database without also making a subdomain |
| `lib/agreement.php` | warn before an already-configured server installs over itself |
| `lib/ops.php` | route for the capability test |
| `index.php` | the self-maintaining "this workspace's data exists" marker; a precise sign-in message |
| `views/ops/tenants.php` | the capability-test panel, placed above the manual form |
| `views/ops/saas_companies.php` | ask where a new company's data goes |
| `.htaccess` | protect every credential-bearing file |

**New tools**

| File | Why |
|---|---|
| `deploy-check.php` | prove an upload landed; show which config file is in use |
| `tools/make_deploy_check.php` | regenerates the above with fresh checksums |
| `tools/deploy_check_template.php` | its source |
| `phase1-inventory.php`, `tools/phase1_inventory_engine.php` | the read-only diagnostics used throughout |

**Tests** — `tests/test_storage_safety.php` (49), `tests/test_deploy_check.php`
(14), plus additions to `tests/test_phase1_inventory.php` (136).

---

## 7. Where things stand

| | Status |
|---|---|
| Client data safe from an upload | ✅ Done |
| Deleted data never silently replaced | ✅ Done |
| You can see whether an upload landed | ✅ Done |
| You can see which config file is live | ✅ Done |
| You can add clients today | ✅ Yes — approve from **Workspace requests** |
| Fully automatic databases | ⏳ Waiting on one `GRANT` from MilesWeb |
| Phase 1 — entitlement | ⏸ Paused, not started |
| Rewrite to the shared-database model | ❌ Not now — revisit at a few hundred clients |

### The one outstanding item

MilesWeb to run, as root:

```sql
GRANT ALL PRIVILEGES ON `mghaiapp1\_%`.* TO 'mghaiapp1_mehul'@'localhost';
FLUSH PRIVILEGES;
```

Then: **Settings → Cloud workspaces → "Test whether this server can create
databases"** turns green, and every future client gets their own MySQL database
automatically. No file change, no upload, no new password.

---

## 8. Honest limits of the testing

* PHP 8.4.19 against a SQLite test harness; 7,147 assertions pass.
* `deploy-check.php` was verified over **real HTTP** against a booted
  application, signed in and signed out.
* **MySQL itself was not exercised** — there is no MySQL server in the build
  environment. The `CREATE DATABASE` path, the cPanel API calls and the live
  grant can only be confirmed on your server, which is exactly what the one-click
  test on the Cloud workspaces screen is for.
