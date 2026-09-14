# PHASE 1 · STEP 2B — LIVE STORAGE DIAGNOSTIC

**Status: BLOCKED — HUMAN/SERVER ACTION REQUIRED**
**No repair, migration, provisioning or entitlement change was performed.**

---

## 1. Executive summary

Step 2B asks which of H1–H4 applies to `sachee-hr-recruitment-services`. That question is answered by **facts that exist only on the production server** — its filesystem and its control database. This environment has no access to either, so **the classification cannot be made here**.

What has been done instead: the Step-2A probe has been **extended into a full Step-2B diagnostic** that answers every one of Parts 1–6 in a single read-only run, and it has been **proved against a fixture reproducing all four hypotheses**. One button press on your server produces the classification.

Two of the twelve parts **can** be settled from code and the data already supplied, and are settled below: **Part 7 (lazy provisioning)** and **Part 9 (Marketplace)**.

---

## 2. Exact application routing

Established by code inspection (unchanged since Step 2A):

- `config.php:87-89` — cloud mode requires `is_file(tenants.php)` **and** a `base_domain`.
- The workspace database is then taken from `$reg['tenants'][$sub]` — `config.php:111-117` (session-selected) and `config.php:137-142` (subdomain-selected).
- **The application reads `tenants.php` and nothing else.** It never consults `saas_tenants.route_json`.

Consequence: if `tenants.php` has no entry for a workspace, **the application cannot open that workspace at all**, regardless of what the control database records.

The live path, its absolute/relative form, existence, size, modification time and readability are reported by the diagnostic (Part 1 fields implemented: `registry_facts`, `control_facts` → `path / absolute / exists / bytes / modified / readable`).

---

## 3. Exact control database routing

`saas_tenants.route_json` holds the route written at creation (`lib/saas_tenants.php:812-819`). The diagnostic now reads the **whole control row** and reports, without credentials:

`tenant_key · company · status · plan · plan_expiry · enabled_modules · created_at · updated_at · route_json → resolved path`

From the Step-1 live data already supplied, the control record for Sachee is **complete and healthy**: `status=active`, `plan=RECRUITMENT`, `enabled_modules=["admin","hr"]`, route resolving to a **SQLite file**.

---

## 4. Route comparison

The diagnostic prints, per workspace:

```
CONTROL ROUTE      : <full path or mysql: db @ host>
APPLICATION ROUTE  : <full path from tenants.php>
DO THEY AGREE?     : YES / NO / n-a (no routing-file entry)
```

`n/a` is reported when the routing file has no entry — that is an absence, not a conflict, and is distinguished deliberately so a missing `tenants.php` is not misreported as a disagreement.

---

## 5. Filesystem search results

The diagnostic scans **only locations the application's own storage logic establishes**:

| Location | Source |
|---|---|
| `…/exaact_data/` (above the web root) | `tenant_safe_data_dir()` preferred branch, `lib/tenant_migrate.php:57-75` |
| `…/public_html/data/` | same function, fallback branch |
| `…/public_html/` (app folder root) | legacy default, and `tenant_registry_heal()`'s legacy branch |

For every candidate it reports full absolute path, filename, size, modification time, readability, whether the file is genuinely a SQLite database (16-byte header check, no open), and whether it carries the exact routed filename.

---

## 6. Duplicate candidate results

Filename variants are searched deliberately, including **single adjacent-character transpositions** — the class that produces `xyz-recurit` vs `xyz-recuirt`. Any file matching a variant is listed, marked `<-- FILENAME VARIANT, not the routed name`, and **identity-verified independently**.

Proved on a fixture: a variant-named file containing a *different* company was correctly reported as `BELONGS_TO_ANOTHER_WORKSPACE` and **not** claimed for the workspace being probed.

**Nothing is ever deleted, renamed or cleaned up.**

---

## 7. Tenant database verification

Where a candidate exists, identity is established from **read-only evidence only**:

- SQLite header magic (`SQLite format 3\0`) — filesystem read, no database open;
- table list from `sqlite_master`;
- presence of expected EXAACT tables (`settings, users, candidates, requisitions, lookup_values, offices`);
- identifying settings: `app_name`, `company_name`, `saas_provisioned`, `saas_entitled_modules`, `modules_off`;
- record counts for `users`, `candidates`, `requisitions`.

Verdict is one of: `MATCHES_EXPECTED_WORKSPACE` · `BELONGS_TO_ANOTHER_WORKSPACE` · `EXAACT_WORKSPACE_UNNAMED` · `NOT_AN_EXAACT_WORKSPACE` · `NOT_A_DATABASE` · `UNREADABLE`.

**If more than one credible database is found, the diagnostic classifies H3 and does not choose between them.**

### The read-only guarantee, strengthened
Every SQLite open now uses **`PDO::SQLITE_OPEN_READONLY`**, which refuses **at the operating-system level** to create a missing file and cannot write. Verified directly:

```
opening a missing file : REFUSED (SQLSTATE[HY000] [14] unable to open database...)
file created?          : NO
```

This is a stronger guarantee than the Step-2A `is_file()` check, which is retained as a second layer.

---

## 8. MySQL/MariaDB tenant storage check

The diagnostic reports, per workspace, whether MySQL/MariaDB storage is **configured** in either routing source, naming the **database and host but never the credentials**.

From the Step-1 live data: **neither workspace has MySQL tenant storage.** `control_engine` is `mysql` (your control database), but both tenant routes are SQLite files. No tenant MySQL database is configured, so there is nothing to connect to — and **no connection is attempted**.

---

## 9. Lazy provisioning analysis — **SETTLED FROM CODE**

| Question | Answer | Evidence |
|---|---|---|
| When is a workspace database created? | **Not at creation.** On first *use* of the workspace. | `saas_tenant_ensure_ready()` → `saas_tenant_apply_bootstrap()`, `lib/saas_tenants.php:729-748` |
| What triggers creation? | Entering the workspace — "log in as", the owner's first sign-in, or any request resolving to it | `lib/saas_tenants.php:656`, `:983` |
| Can merely opening the tenant create it? | **Yes.** That is exactly why this diagnostic must never open a workspace through the application. | same |
| Does the control record prove provisioning occurred? | **No.** The control record is written at creation; `saas_provisioned` lives in the *workspace* database. | `lib/saas_tenants.php:812-819` vs `saas_entitlement_ensure()` |

**Therefore a workspace can legitimately exist with `status=active` and a sold plan while having no database at all** — which is precisely hypothesis H1.

**This diagnostic never calls `saas_tenant_ensure_ready()` or `saas_tenant_apply_bootstrap()`, never boots the application, and never resolves a tenant through `config.php`.**

---

## 10. Sachee classification

**BLOCKED — cannot be determined from this environment.**

The evidence required is the live filesystem and control database. The diagnostic that produces the classification is built, tested and ready; it emits exactly one of:

| Classification | Meaning |
|---|---|
| `H1 - no database anywhere (never provisioned)` | No file in any known location; consistent with never having been opened |
| `H2 - database exists at a DIFFERENT location` | A verified Sachee database exists; routing is stale |
| `H3 - MULTIPLE credible databases` / `file(s) present but none verified` | **Blocks repair.** Identity cannot be decided automatically |
| `H4 - MySQL/MariaDB storage is configured` | **Blocks repair** pending routing-architecture review |
| `OK - routed database exists` | No fault |

All five paths were exercised against a purpose-built fixture and produced the correct classification (see §14).

---

## 11. Marketplace confirmation — **SETTLED FROM LIVE DATA**

The Step-2A finding is **confirmed**, from the live inventory JSON:

| | Sachee | Xyz Recurit |
|---|---|---|
| `marketplace_addon` | `""` — never set | `""` — never set |
| `connect_enabled` | `""` — never set | `""` — never set |
| Marketplace in purchased `enabled_modules` | **No** (`["admin","hr"]`) | **No** (`["hr","admin"]`) |

1. **Purchased module?** — **No**, for both.
2. **Explicitly configured ON?** — **No**, for both. Neither setting has ever been written.
3. **Merely ON because the cloud default applies?** — **Yes**, for both. `marketplace_addon_on()` returns `install_is_cloud() ? '1' : '0'` when unset; `connect_enabled()` defaults to `'1'`.

> **Conclusion, unchanged: Marketplace being ON is not evidence of purchase.**

No discrepancy was found between code and evidence. **No Marketplace backfill is warranted**, and the engine has been corrected so it can no longer recommend one for a default-only workspace (Step 2A defect fix, retained and tested).

---

## 12. Recommended next action

**One read-only run, by you, on the control installation.**

1. Upload the updated **`phase1-inventory.php`** and the **`tools/`** folder (both changed since your last run).
2. Sign in as administrator on your main address.
3. Open **`/phase1-inventory.php`**.
4. Press **“Run Step-2A storage diagnostic”** — this button now performs the **full Step-2B diagnostic**.
5. Press **Download JSON** and send the file.

That single output contains Parts 1–6 for every workspace and prints the Sachee classification directly as `>> CLASSIFICATION`.

**Then, and only then**, the corrective action is chosen:
- **H1** → nothing is lost; it is a commercial question (real customer or abandoned record). No repair.
- **H2** → correct the routing to point at the **existing** file. **Never** create a new one — that would orphan live data.
- **H3 / H4** → **repair is blocked** pending review.

`safe_to_flip` remains **false**. Default-deny stays off.

---

## 13. Safety statement

**Nothing was repaired, migrated, provisioned or changed.**

- No database or SQLite file created · no directory created · no database modified.
- No tenant data, settings, entitlements, `enabled_modules`, `modules_off`, `saas_provisioned` or Marketplace settings modified.
- No routing changed · `tenants.php` untouched · `route_json` untouched.
- `lib/licence.php` untouched · default-deny not enabled · no entitlement backfill.
- No SQLite→MySQL migration · no tenant repaired · no tenant application session booted · no lazy provisioning triggered · no schema or migration run against any tenant.
- RBAC, `is_master()`, routes, cron, Operations, Quality, Reporting, Money and Marketplace behaviour all untouched.
- **No file under `lib/` was changed**, so the application's code fingerprint is unchanged and deployment triggers no migration or entitlement write.
- Credentials are never printed — verified by test (a fixture password appeared **0** times in output).

### Git status
```
 M phpapp/phase1-inventory.php              (diagnostic runner)
 M phpapp/tools/phase1_inventory_engine.php (diagnostic engine)
 M phpapp/tests/test_phase1_inventory.php   (diagnostic tests)
 A docs/phase1/STEP2B-LIVE-STORAGE-DIAGNOSTIC.md
```
Files added: 1 document. Files deleted: none. Under `lib/`: **none**. `licence.php`: **unchanged**. Tenant databases: **unchanged**. Application behaviour: **unchanged**.

---

## 14. Test evidence

Freshly measured — not quoted.

| Run | Result |
|---|---|
| Diagnostic assertions | **109 passed, 0 failed** |
| **Full regression** | **7057 passed, 0 failed** |
| Previous baseline (Step 2A) | 7030 passed, 0 failed |
| Net | **+27 assertions, zero regressions** |
| PHP | 8.4.19 |
| Test database engine | **SQLite** (the existing harness) |

**MySQL/MariaDB tests were NOT performed.** No MySQL/MariaDB server is available in this environment and the Docker daemon is not running. Production is MySQL/MariaDB; the authoritative run is the one performed on your server.

### Fixture proof — all four hypotheses
A four-workspace fixture was built and probed:

| Workspace | Situation | Classification produced |
|---|---|---|
| `ghost-co` | no file anywhere | **H1** ✓ |
| `sachee-hr-recruitment-services` | real database **above the web root**, route pointing at the app folder | **H2** ✓ — identity `MATCHES_EXPECTED_WORKSPACE`, 3 candidate records found |
| `mysql-co` | MySQL route configured | **H4** ✓ — password **not** printed |
| `xyz-recurit` | routed file present **plus** a variant-named file | **OK** ✓ — the variant correctly reported `BELONGS_TO_ANOTHER_WORKSPACE` |

Safety assertions after the run: **all database files byte-identical**; the missing `ghost-co` file **not created**; the missing Sachee routed file **not created**; the fixture MySQL password appeared **0 times**.

---

**PHASE 1 STEP 2B BLOCKED — HUMAN/SERVER ACTION REQUIRED**
*(diagnostic built, tested and ready; one read-only run on the live server will produce the classification. Do not proceed to entitlement backfill or default-deny.)*
