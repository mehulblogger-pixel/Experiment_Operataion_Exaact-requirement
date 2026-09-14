# PHASE 1 · STEP 2A — INVENTORY ERROR ANALYSIS

**No application behaviour, entitlement, route, cron, RBAC or tenant data was changed.**
Investigation and read-only diagnostics only.

Live inventory (2026-09-14T00:59:14Z): 2 workspaces · 1 SAFE · 1 ERROR · 0 AMBIGUOUS · `safe_to_flip = false`.

---

## 1. Exact storage resolution path

There are **two independent routing sources**, and they are read by **different consumers**:

| Consumer | Reads | Evidence |
|---|---|---|
| **The application** | `tenants.php` (the routing file) **ONLY** | `config.php:87-89` — cloud mode requires `is_file(tenants.php)` and a `base_domain`; the workspace database is then taken from `$reg['tenants'][$sub]` (`config.php:111-117, 137-142`) |
| **The inventory tool** | `saas_tenants.route_json` first, `tenants.php` as fallback | `tools/phase1_inventory_engine.php` → `p1_resolve_route()` |

At creation the two are written from the **same** `$db` array, so they agree:
`tenant_add($nkey, $company, $db)` writes the routing file, and `saas_tenant_upsert($nkey, ['route_json' => json_encode($db)])` writes the control record (`lib/saas_tenants.php:812-819`).

They can drift apart afterwards, most notably through `tenant_registry_heal()`, whose legacy branch writes an **app-folder** path when a control record carries no usable route:
```php
$entry['sqlite'] = __DIR__ . '/../tenant-' . $key . '.sqlite';   // lib/tenants.php
```

**Where a new workspace's file is placed** depends on which build created it:

| Build | Location chosen | Code |
|---|---|---|
| Older | app folder root — `…/public_html/tenant-<key>.sqlite` | `dirname(__DIR__) . '/tenant-' . $nkey . '.sqlite'` |
| Current | **above the web root** — `…/exaact_data/tenant-<key>.sqlite` | `tenant_auto_storage()` → `tenant_default_sqlite_path()` → `tenant_safe_data_dir()` (`lib/tenant_migrate.php:57-75`), which prefers `dirname(dirname(__DIR__)) . '/exaact_data'` |

**Critically, the data file is created lazily** — not when the workspace is added, but the first time that workspace is actually opened (`saas_tenant_ensure_ready()` → `saas_tenant_apply_bootstrap()`, `lib/saas_tenants.php:729-748`). A workspace can therefore exist in the control database with **no data file at all**.

---

## 2. Control database evidence

From the live inventory JSON, for `sachee-hr-recruitment-services`:

```
status            : active
plan              : RECRUITMENT
enabled_modules   : ["admin","hr"]
route_json        : resolves to a SQLite file route
storage (label)   : "file: tenant-sachee-hr-recruitment-services.sqlite"
```

The control record is **complete and healthy**. The workspace was properly created and properly sold a RECRUITMENT plan.

**`control_engine` is `mysql`** — your control database is MySQL/MariaDB, as expected.

---

## 3. Tenant database evidence

```
error         : workspace data file not found (not created — this tool never writes)
provisioned   : ""      <- NOT evidence; see below
ceiling_raw   : ""      <- NOT evidence
effective_now : []      <- NOT evidence
```

**Important correction to avoid a wrong conclusion:** the blank `provisioned`, `ceiling_raw` and `modules_off` values for this workspace are **artefacts of the read failure**, not findings. When the database cannot be opened, `p1_build_row()` is called with an empty settings array, so every field renders blank. **Nothing is known about this workspace's entitlement — that is precisely why it is classified ERROR and not AMBIGUOUS.**

By contrast `xyz-recurit` read cleanly: `provisioned=1`, `ceiling=hr`, `modules_off=operations,sales,reporting,money`, effective `admin,hr` both today and under default-deny → **would lose nothing**.

---

## 4. Sachee failure — root cause

**The tool did exactly what it should: it refused to connect.** PDO *creates* a SQLite file that does not exist, and creating one would have been a write. The guard added in Step 1 stopped that (`workspace data file not found (not created — this tool never writes)`).

So the finding is factual: **there is no data file at the path the control database records.** Two explanations remain, and they are distinguished only by what exists on your server:

| | Hypothesis | Meaning | Likelihood |
|---|---|---|---|
| **H1** | **The workspace has never been opened.** The control record was created, but nobody has ever signed into that workspace, so the lazily-created data file does not exist yet. | Benign. Nothing is lost — there is no data to lose. Entitlement is simply not yet stamped. | **Most likely** — it matches every symptom, including the absent `saas_provisioned`. |
| **H2** | **The file exists in a different location than the route records** — e.g. above the web root in `exaact_data/` while the route points at the app folder, or vice versa. | Real data exists but routing is stale. Must be corrected, not re-created. | Possible — this is exactly the situation your question describes. |

**I cannot choose between these from here**, because the inventory report prints only the *filename* (`basename()`), not the directory. That is a limitation of my own Step-1 tool, and it is why Step 2A needed a dedicated probe.

---

## 5. Is the inventory tool itself correct?

**Yes — with one interpretation defect, now fixed, and one reporting limitation, now fixed.**

| | Assessment |
|---|---|
| Refusing to connect to a missing file | **Correct.** Connecting would have written. |
| Classifying it ERROR, not AMBIGUOUS | **Correct.** Entitlement is unknown, not missing. |
| `safe_to_flip = false` while an ERROR exists | **Correct.** Default-deny must not be enabled. |
| Preferring `route_json` over the routing file | **Divergent from the application**, which reads the routing file only. Not wrong, but it means the tool and the app could inspect different databases. **Now surfaced explicitly by the Step-2A probe.** |
| Showing only the filename | **Limitation** — hid the directory, which is the decisive fact. **Now fixed:** the probe prints full paths. |
| `marketplace_needs_backfill: true` for both workspaces | **DEFECT — corrected.** See §7. |

---

## 6. Does production MySQL/MariaDB contain the tenant database?

**No — and this is an important finding in its own right.**

Your **control** database is MySQL (`control_engine: mysql`), but **both workspaces are file-backed (SQLite)**:

```
sachee-hr-recruitment-services  ->  file: tenant-sachee-hr-recruitment-services.sqlite
xyz-recurit                     ->  file: tenant-xyz-recurit.sqlite
```

So there is **no MySQL/MariaDB database for either tenant**. The "move a workspace to MySQL" capability exists in the product but **has not been applied to either workspace**.

Consequence, unchanged from the earlier finding: **`xyz-recurit`'s live data is a file inside the app folder** and remains vulnerable to a delete-and-re-upload deployment. That is a separate, already-known risk — **not** Phase 1 scope, and not touched here.

**Answering your question directly:** the code that stores workspace files **above the web root** does exist (`tenant_safe_data_dir()` → `exaact_data/`), but it applies **only to workspaces created by a build that includes it**. `xyz-recurit` predates it and still lives in the app folder. Whether `sachee` used the new location is exactly what the probe settles.

---

## 7. Marketplace finding

**Marketplace is ON by existing default only. It was NOT purchased and was NOT configured.**

Evidence, for **both** workspaces:

```
marketplace_addon : ""     (setting never written)
connect_enabled   : ""     (setting never written)
enabled_modules   : ["admin","hr"]   (Marketplace absent)
```

- `marketplace_addon_on()` returns `install_is_cloud() ? '1' : '0'` when unset — so on a cloud install it reads ON **purely from the default**.
- `connect_enabled()` defaults to `'1'`.
- Marketplace does **not** appear in either workspace's purchased module record.

**Classification: an existing default only.** Not actual customer configuration, and not an intentional purchased entitlement.

**My Step-1 tool got this wrong** and flagged `marketplace_needs_backfill: true` for both — which, if acted on, would have manufactured an entitlement nobody bought. **Now corrected.** The engine distinguishes three separate states — *purchased* (in the control record), *explicitly switched on* (`marketplace_addon = '1'`), and *on by default* (never set) — and backfills only the first two. For your two workspaces it now reports:

> *"Reachable only because cloud installs default to ON. NOT purchased and NOT configured — promoting Marketplace to a real module would correctly lock it. No backfill."*

---

## 8. Recommended next action

**One read-only server action**, then a decision.

A **Step-2A storage diagnostic** has been built, tested and added to the same administrator page. It opens nothing, creates nothing and writes nothing. It reports, per workspace:

- the **full path** recorded in the control database,
- the **full path** recorded in the routing file,
- whether those two **agree**,
- which one the **application** would use versus which the **inventory** used,
- whether each path **exists**,
- and a scan of **every candidate location** — above the web root (`exaact_data/`), the app folder `data/`, and the app folder root — listing every `tenant-*.sqlite` file with its size.

That last scan also answers the duplicate-file question you raised: if two files exist for one workspace (for example under slightly different spellings), it will list both.

**To run it:** sign in as administrator → open `/phase1-inventory.php` → press **“Run Step-2A storage diagnostic”** → **Download JSON** and send it.

Then:
- **If H1 (no file anywhere):** nothing is lost. The workspace simply has not been opened. The decision is commercial — is it a real customer or an abandoned test record? No repair is needed; it is excluded from the entitlement backfill and does not block default-deny once classified.
- **If H2 (file exists elsewhere):** the routing is stale. The fix is to correct the routing to point at the real file — **never** to create a new one, which would silently orphan live data.

**Until this is settled, `safe_to_flip` stays false and default-deny must not be enabled.**

---

## 9. Statement of no change

- **No application behaviour was changed.** `git status` shows three modified files, all diagnostic: `phase1-inventory.php`, `tools/phase1_inventory_engine.php`, `tests/test_phase1_inventory.php`.
- **No file under `lib/` was touched**, so the application's code fingerprint is unchanged and no migration or entitlement write is triggered on deployment.
- `lib/licence.php` untouched · default-deny not enabled · no entitlement backfilled · Marketplace entitlement unchanged · no tenant data read or written beyond SELECTs · routes, cron, `is_master()` and RBAC untouched.
- **No database was created, repaired or modified.** The missing workspace file was **not** created — verified by test.

### Test evidence (freshly measured)

| Run | Result |
|---|---|
| Diagnostic tests | **82 passed, 0 failed** |
| **Full regression** | **7030 passed, 0 failed** |
| Previous baseline | 7010 passed, 0 failed |
| Net | +20 assertions, **zero regressions** |

Fixture proof: against a workspace whose file really sat **above the web root** while its route pointed at the app folder, the probe reported the routed path as missing, located the real file above the web root, and **did not create** the missing file. Every file was byte-identical afterwards.

Engine: SQLite harness · PHP 8.4.19. **Production is MySQL/MariaDB**; the authoritative run is the one you perform on the live server.

---

**PHASE 1 STEP 2A COMPLETE — NEXT ACTION IDENTIFIED**
*(one read-only server run required to distinguish H1 from H2; do not proceed to entitlement backfill or default-deny)*
