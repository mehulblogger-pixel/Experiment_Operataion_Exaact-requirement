# PHASE 1 · STEP 2C — URGENT: BOTH WORKSPACES BECAME UNREADABLE

**Status: STOP — DO NOT UPLOAD, DELETE OR CREATE ANYTHING ON THE SERVER**
**No repair, migration, provisioning or entitlement change was performed.**

---

## 1. What changed, and when

| Run | Time (UTC) | sachee | xyz-recurit |
|---|---|---|---|
| 1 | 2026-09-14 **00:59:14** | ERROR — file not found | **SAFE** — read cleanly: `provisioned=1`, `ceiling=hr`, `modules_off=operations,sales,reporting,money` |
| 2 | 2026-09-14 **01:34:06** | ERROR | **ERROR — file not found** |

In 35 minutes, a workspace that had been read **successfully** became unreadable. The only action in between was **re-uploading `phase1-inventory.php` and the `tools/` folder**.

---

## 2. The tool is not at fault — verified

Before raising any alarm, the current diagnostic code was tested against a fixture with the data file **present** at the routed path:

```
[SAFE] xyz-recurit — Xyz Recurit
  provisioned   : 1
  ceiling       : hr
  modules_off   : operations,sales,reporting,money
  effective NOW : admin,hr
```

**Identical to run 1.** The code reads an existing file correctly. Therefore **the files are genuinely no longer at the routed paths on the server.**

---

## 3. Cause — CONFIRMED BY THE OPERATOR

The operator has confirmed:

> *"I deleted the file of XYZ from folder but not from data or above that folder."*

So the app-folder copy of `tenant-xyz-recurit.sqlite` was **deliberately removed**, on the understanding that a copy remains in the sibling `data/` folder or above the application folder.

**This changes the situation from "possible accidental data loss" to "the live file has moved and the routing is now stale."** That is hypothesis **H2**, and it is recoverable — provided a copy genuinely exists.

**It must still be verified, not assumed.** The routing in the control database continues to point at the deleted app-folder path, so the application cannot open the workspace until either the file or the routing is put back in step. The recovery scan confirms which copies actually exist, where, and whether they are genuinely this workspace's data.

### Search locations widened in response
The scan now also looks in **one level above the application folder** and in the **sibling `data/` folder** — the two places named. In that sibling folder it globs **only `tenant-*.sqlite`**; the `books.mghaiapps.com` files there are never read or listed. Verified by test.

## 3a. Original assessment (retained for the record)

The workspace data files were **SQLite files stored inside the application folder**:

```
…/operations.mghaiapps.com/tenant-xyz-recurit.sqlite          (~3.12 MB of live data)
…/operations.mghaiapps.com/tenant-sachee-hr-recruitment-services.sqlite
```

If the tool was deployed using the usual **“delete the files, then upload the new ones”** procedure, **those data files were deleted along with the application files.**

This is precisely the risk identified earlier in this programme: on this installation the workspaces are file-backed and those files live **inside the deletable folder**. The protections built to prevent it — storing new workspaces above the web root, and the Move-to-MySQL tool — were **never applied to these two existing workspaces**.

**This is not a Phase-1 entitlement problem. Phase 1 is paused.**

*(Superseded by §3: the deletion was deliberate and a copy is expected to exist. The risk is stale routing, not loss — subject to confirmation by the scan.)*

---

## 4. Do this now, in this order

1. **Do not sign in to either workspace, and do not re-create them.** Opening a workspace would create a **new, empty** database at the routed path — which would then look like the real one and make recovery materially harder. This is the single most important thing to avoid right now.
2. **Run the storage diagnostic** (see §5). It now includes a **recovery scan** that reports whether the data still exists anywhere.
3. **Open your MilesWeb control panel → Backups** and check what restore points exist, and their dates. Do not restore yet — send the diagnostic output first.

---

## 5. Run the recovery scan

1. Upload the updated `phase1-inventory.php` and `tools/` folder — **by overwriting only those files. Do not delete anything first.**
2. Sign in as administrator → open `/phase1-inventory.php`
3. Press **“Run Step-2A storage diagnostic”**
4. Press **Download JSON** and send the file.

It reports, read-only:

- every `tenant-*.sqlite` in **every** location this application uses — including **above the web root** and **one level above the application folder** — with size and modification date;
- every **backup snapshot** in `exaact_backups/` (above the web root) and `data-backups/`, per workspace, with counts, total size and the newest snapshot;
- and a verdict: **“DATA FOUND. Recovery is possible.”** or **“NOTHING FOUND in the application folders. Check your hosting provider backups immediately.”**

**Your Books data folder is not touched.** The scan looks only at the locations EXAACT itself uses (`exaact_data/`, `exaact_backups/`, the app folder, and one level above it for `tenant-*.sqlite` only). The `data/` folder belonging to `books.mghaiapps.com` is neither read nor listed.

---

## 6. Where the data may still be

| Source | Location | Survives a folder wipe? |
|---|---|---|
| **Backup snapshots** written by the app | `…/exaact_backups/<workspace>/` — **above the web root** | **Yes** — this is why they were put there |
| Workspace file moved to the safe location | `…/exaact_data/tenant-*.sqlite` | Yes |
| Workspace file in the app folder | `…/operations.mghaiapps.com/tenant-*.sqlite` | **No** |
| **Hosting provider backups** | MilesWeb panel → Backups | Yes — independent of the application |
| Control database (workspaces, plans, logins) | **MySQL** | **Yes — untouched.** Your workspace records, plans and entitlements are intact. |

**The control database is MySQL and is unaffected.** Both workspace records still exist with `status=active` and `plan=RECRUITMENT`. What is missing is the workspace *content*.

Whether the in-app backups exist depends on whether the build containing the backup engine had been deployed and whether those workspaces were used afterwards. The diagnostic answers that definitively.

---

## 7. Phase 1 status

**Paused.** `safe_to_flip` is false and both workspaces read as ERROR, so entitlement cannot be assessed at all. No backfill, no default-deny, no Marketplace change. Phase 1 resumes only once the data position is known and settled.

The Step-2B classification is unchanged in method but now applies to **both** workspaces rather than one.

---

## 8. Marketplace — unchanged and confirmed again

Run 2 confirms the corrected reading. For both workspaces: `marketplace_addon` and `connect_enabled` **never set**, Marketplace **absent** from both purchased records, and the corrected engine now reports:

```
marketplace_needs_backfill : false
marketplace_default_only   : true
"Reachable only because cloud installs default to ON. NOT purchased and NOT
 configured — promoting Marketplace to a real module would correctly lock it."
```

Note that `Marketplace needing backfill` fell from **2** to **0** between the two runs — that is the Step-2A defect fix working as intended, not a change on your server.

---

## 9. Safety statement

Nothing was repaired, migrated, provisioned or changed. No database, file or directory created; no tenant data, settings, entitlement, routing or `tenants.php` modified; `lib/licence.php` untouched; default-deny not enabled; no backfill; no tenant booted and no lazy provisioning triggered. **No file under `lib/` was changed**, so the application's code fingerprint is unchanged.

Only three diagnostic files and this document were modified.

### Test evidence (freshly measured)

| Run | Result |
|---|---|
| Diagnostic assertions | **119 passed, 0 failed** |
| **Full regression** | **7067 passed, 0 failed** |
| Previous baseline | 7057 passed, 0 failed |
| Net | +10 assertions, **zero regressions** |
| PHP / engine | 8.4.19 / SQLite harness |

MySQL/MariaDB tests were **not** performed — no server is available in this environment.

Fixture proof of the recovery scan: with workspace files deleted and only backups surviving above the web root, the scan reported **no live files**, listed **2 snapshots for `xyz-recurit`** with the correct newest by timestamped filename, concluded **“DATA FOUND. Recovery is possible.”**, and left the Books `data/` folder untouched.

---

**PHASE 1 PAUSED — RECOVERY SCAN REQUIRED BEFORE ANY FURTHER ACTION**
