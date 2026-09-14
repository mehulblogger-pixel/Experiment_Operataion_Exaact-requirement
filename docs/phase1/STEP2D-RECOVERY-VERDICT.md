# Phase 1 · Step 2D — Recovery verdict from the live server

**Date:** 2026-09-14
**Status:** Diagnostic complete. Phase 1 remains **paused**.
**Nothing was written, created, moved or deleted to produce this document.**

---

## 1. What the live run actually found

Three reports were produced from the production server:

| Run | Time (UTC) | Result |
|---|---|---|
| Inventory 1 | 00:59:14 | `xyz-recurit` = **SAFE** and readable (provisioned=1, ceiling=hr) |
| Inventory 2 | 01:34:06 | both workspaces = **ERROR — data file not found** |
| Inventory 3 | 02:02:55 | both workspaces = **ERROR — data file not found** |
| Storage probe | 02:02:36 | see below |

The storage probe is the decisive document. Its facts:

```
App folder                : /var/www/<account>/operations.mghaiapps.com
Routing file tenants.php  : NOT PRESENT
above web root (exaact_data)  : DOES NOT EXIST
app folder /data              : exists — no workspace file
app folder root (legacy)      : exists — no workspace file
one level above the app folder: no workspace file
sibling 'data' folder         : no workspace file
LIVE WORKSPACE FILES FOUND    : NONE
BACKUP SNAPSHOTS              : __control only — 2 snapshots, newest 00:57:54
```

Both workspaces classify as **H1 — no database in any location this
application uses.**

## 2. The verdict, stated plainly

**The data files for `xyz-recurit` and `sachee-hr-recruitment-services` no
longer exist anywhere the application can reach, and neither workspace has a
backup snapshot.**

The only snapshots on the server are in the `__control` folder. `__control` is
the *routing directory* — the list of which workspaces exist and where their
data lives. It contains **no candidate, no requisition, no user record** from
either workspace. It cannot restore either workspace.

`xyz-recurit` held real data 35 minutes before it disappeared: inventory run 1
read it successfully. So this is deletion, not a workspace that was never used.

## 3. A defect in my own report — corrected

The live probe printed:

```
>> DATA FOUND. Recovery is possible. Do not upload or delete anything further.
```

**That line was wrong.** The verdict was computed as "any backup folder has
snapshots", and the `__control` folder does — so two workspaces with no data
and no backup were reported as recoverable. A reassuring sentence produced from
irrelevant evidence is worse than no sentence at all, and it was mine.

Fixed: the verdict is now computed **per workspace, from that workspace's own
evidence only**. `__control` is labelled in the output as the routing directory,
not workspace records, and can never make a workspace look recoverable. Four
tests now hold this closed, including one built from the exact live data above.

Two further gaps found while fixing it, also closed:

* The report never listed **which folders it searched**, so "nothing found"
  had to be taken on trust. It now prints every folder, whether it existed, was
  readable, and how many matching files it held.
* The sweep matched only the exact name `tenant-*.sqlite`. A hand-made safety
  copy (`….sqlite.bak`, `…​.old`) or a file moved into a neighbouring folder was
  invisible to it. It now matches renamed and suffixed copies, and searches one
  level deeper beneath the shared account root.

Other applications' databases are still never listed, never opened and never
touched — matching is by filename only, restricted to this application's
`tenant-` prefix and to workspace keys from the control database. Tested.

## 4. Root cause

This is not a bug in a feature. It is the **storage design meeting the
deployment method**, and it was predictable:

1. Each workspace's entire database was a **single file inside the application
   folder** (`tenant-<key>.sqlite`).
2. The routing file `tenants.php` also lived **inside the application folder**.
3. The deployment method is: *delete every file in the application folder except
   `config.php`, then upload the fresh code.*

Step 3 deletes the files created by steps 1 and 2. The evidence matches exactly:
`tenants.php` is gone, the workspace files are gone, and the freshly uploaded
code is present.

This is the precise risk raised at the start of this work —
*"you can take another way which can never delete any tenant data"* — and the
fix for it was written (`lib/tenant_migrate.php`, `lib/backup.php`) but had not
yet been applied to these two workspaces. That gap is mine.

The routing file will repair itself: `tenant_registry_heal()` (lib/tenants.php:97)
rebuilds `tenants.php` from the control database on the next control-install page
load. **The data will not repair itself.**

## 5. What survives

The control database (MySQL) is intact. Both workspaces still exist as records:

| Workspace | Company | Status | Plan | Modules | Created |
|---|---|---|---|---|---|
| `xyz-recurit` | Xyz Recurit | active | RECRUITMENT | admin, hr | 2026-09-11 02:50 |
| `sachee-hr-recruitment-services` | Sachee HR Recruitment Services | active | RECRUITMENT | admin, hr | 2026-09-11 16:42 |

So the workspaces, their plans and their purchased modules are not lost. Only
the records *inside* them are.

## 6. ⚠️ The one action that would make this worse

**Do not sign in to either workspace, and do not re-create them.**

With `tenants.php` rebuilt, opening a workspace triggers lazy provisioning
(`saas_tenant_ensure_ready()` → `saas_tenant_apply_bootstrap()`), which creates
a **brand-new empty database file at the routed path**. A hosting restore would
then have to be told to overwrite a file that looks legitimate, and the empty
file could silently win. Every hour that nobody opens them keeps recovery clean.

## 7. Remaining recovery routes, in priority order

1. **MilesWeb / cPanel account backup (JetBackup).** The strongest option. The
   files existed at 00:59 on 2026-09-14, so any daily snapshot taken before the
   deletion contains them. Restore *only* these two paths:
   `…/operations.mghaiapps.com/tenant-xyz-recurit.sqlite`
   `…/operations.mghaiapps.com/tenant-sachee-hr-recruitment-services.sqlite`
   Do **not** restore the whole account — that would roll the control database
   back too.
2. **File Manager trash.** cPanel moves deleted files to `.trash` in the home
   folder unless "skip trash" was ticked.
3. **Any copy taken before the upload** — a download to a PC, a zip, a
   duplicate made in File Manager.

If none of these yields a file, the records in those two workspaces are gone and
must be re-entered. There is no honest alternative answer.

## 8. Permanent fix (proposed — not implemented, awaiting approval)

So this cannot recur:

1. **Stop storing workspace data inside the web folder.** Provision every new
   workspace directly into **MySQL/MariaDB**, and migrate existing file-backed
   workspaces. A database is not a file in the upload folder, so no upload can
   delete it. The engine already exists (`lib/tenant_migrate.php`).
2. **Automatic per-workspace backup above the web root**, on a schedule and
   before every upgrade — not only for the control install. `lib/backup.php`
   already writes to `exaact_backups` outside the app folder; it needs to run for
   every workspace, not just `__control`.
3. **Move deployment off "delete everything".** Uploading over the top is enough;
   deleting first is what destroys state. Better still, a release folder swap.
4. **A start-up guard**: if a workspace is marked provisioned in the control
   database but its data file is missing, refuse to create a fresh empty one —
   show an explicit "data file missing, contact support" page instead. Silent
   re-creation is how a recoverable situation becomes an unrecoverable one.

## 9. Phase 1 status

**PAUSED.** Both workspaces read as ERROR, so entitlement cannot be measured, and
no entitlement decision may be taken on unreadable data. No backfill, no
default-deny, no Marketplace change.

## 10. Evidence

* Phase 1 diagnostic suite: **136 passed, 0 failed** (was 119; +17 for this step)
* Full regression suite: **7,084 passed, 0 failed** (was 7,067; zero regressions)
* PHP 8.4.19, SQLite harness. MySQL/MariaDB paths **not** exercised — no server
  available in this environment.
