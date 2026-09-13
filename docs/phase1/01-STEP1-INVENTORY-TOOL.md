# PHASE 1 · STEP 1 — READ-ONLY TENANT ENTITLEMENT INVENTORY

**Status: COMPLETE — tool built, tested, ready to run.**
**No application behaviour was changed. No existing file was modified.**

---

## 1. What this tool is for

Before the meaning of "blank entitlement" can safely change from *allow* to *deny*, we must know what every existing workspace actually has. This tool answers that — **from your live server, without changing anything.**

It reports, for every workspace:

| Field | Meaning |
|---|---|
| tenant / company / status / plan | who they are and what they are on |
| control-side `enabled_modules` | what the control database records as **bought** |
| tenant-side `saas_provisioned` | whether the workspace was ever marked provisioned |
| tenant-side `saas_entitled_modules` | the entitlement **ceiling** actually enforced |
| tenant-side `modules_off` | the customer's own on/off choices |
| **effective modules NOW** | what they can use today |
| **effective modules under DEFAULT-DENY** | what they would be left with |
| **WOULD LOSE** | the exact difference |
| marketplace state | whether Marketplace is live (it is not ceiling-governed today) |
| risk classification | see §5 |

---

## 2. Why it does not simply boot the application

**The application's own boot chain writes entitlement.** `saas_entitlement_ensure()` (`lib/db.php:523`) stamps `saas_entitled_modules` onto any provisioned workspace that has none.

So booting the app — or calling `saas_enter_tenant()` — **would alter the very state we are trying to measure, on production.**

This tool therefore:
- never boots the application,
- never enters a workspace,
- never runs a migration,
- opens each database with a raw connection and issues **SELECT only**.

Every query passes through `p1_ro_query()`, which **hard-refuses** anything that is not `SELECT` / `SHOW` / `PRAGMA` — including writes hidden behind SQL comments or appended after a semicolon. It also refuses to connect to a missing SQLite file, because connecting would *create* one.

**Proven, not asserted:** in testing, every database file was **byte-identical** before and after a full run, and a write pushed deliberately through the guard throws while the data stays unchanged.

---

## 3. How to run it — administrator, in your browser

**No terminal, no SSH, no phpMyAdmin, no editing files.**

1. **Upload two things** to your app folder using cPanel → File Manager (exactly as you upload any build):
   - `phase1-inventory.php` → next to `index.php`
   - the `tools/` folder (containing `phase1_inventory_engine.php`)
2. **Sign in to EXAACT as you normally do**, on your **main address** (the control installation — e.g. `https://operations.mghaiapps.com`).
3. In the **same browser**, open: **`/phase1-inventory.php`**
4. Press the single button: **“Run Phase-1 Entitlement Inventory”**.
5. Press **Download JSON** and send me that file. (There are also *Download report* and *Copy report* buttons.)
6. **Delete `phase1-inventory.php` and the `tools/` folder** from the server afterwards.

### Who can open it
Only a **signed-in administrator** (`is_superuser`) of the **control installation**.

- Not signed in → **404**
- Signed in but not an administrator → **404**

It returns a plain *“Not available.”*, so the page is **not discoverable** by anyone else. It uses your **existing login session** — no new password, and no change to the application's login or security architecture.

### Fallback doors (you should not need these)
- **Command line**, if your host offers it: `php /home/USER/public_html/phase1-inventory.php --json`
- **Key file**, only if nobody can sign in: create `phase1-inventory.key` next to the script containing a long random password you invent, then open `/phase1-inventory.php?key=THAT-PASSWORD`. Delete the key file afterwards. Creating that file needs server access, which is equivalent authority to an admin login.

---

## 4. How to supply the output

Press **Download JSON** on the results page and send me that file (or use **Copy report** and paste it).

- It contains **no passwords** — database credentials are never printed; only the database *name* and host appear, as a label.
- It contains workspace names, plans and module lists. If you would rather redact company names before sending, replace them — the analysis only needs the tenant keys, entitlement fields and risk lines.
- The JSON form (`--json` / `&json=1`) is the most reliable to paste.

**Nothing further should be changed on the server until that output has been reviewed.**

---

## 5. Reading the result

Each workspace is classified. **The tool never guesses.**

| Risk | Meaning | What happens next |
|---|---|---|
| **SAFE** | Has a real ceiling. Default-deny changes nothing. | No action. |
| **LICENCE_GOVERNED** | A signed licence is present and outranks the cloud ceiling. | No action. |
| **RECOVERABLE** | Would lose modules, **but** the control database records what was bought. | Backfill from the control record. |
| **AMBIGUOUS** | Would lose modules and **neither store** records an entitlement. | **STOP.** A commercial decision is required — this will be reported, never guessed. |
| **ERROR** | The workspace database could not be read. | Must be resolved before any flip. |

The report ends with a verdict line. It says **`DO NOT FLIP DEFAULT-DENY`** while any workspace is AMBIGUOUS or ERROR.

There is also a **Marketplace** line per workspace. Marketplace is not governed by the ceiling today and defaults to ON in cloud, so promoting it to a real module would switch it **off** for anyone currently using it unless it is backfilled first.

---

## 6. Files added (nothing existing was touched)

| File | Purpose |
|---|---|
| `phpapp/tools/phase1_inventory_engine.php` | The engine: read-only guard, entitlement maths, risk classification. **Not in `lib/`** (see §2a) and **not loaded by `index.php`** — the application does not know it exists. |
| `phpapp/phase1-inventory.php` | The runner: admin browser page, plus CLI and key-file fallbacks. |
| `phpapp/tests/test_phase1_inventory.php` | 62 assertions covering the engine. |

**Delete `phase1-inventory.php`, the `tools/` folder (and any `.key` file) from the server once the inventory has been supplied.** It is a one-off diagnostic, not part of the product.

---

## 7. Test evidence (freshly measured, not quoted)

| Run | Result |
|---|---|
| Inventory engine tests alone | **61 passed, 0 failed** *(62 after the missing-file fix)* |
| **Full regression, after the change** | **7010 passed, 0 failed — 81 s** |
| Baseline before the change | 6948 passed, 0 failed — 81 s |
| Difference | **+62 new assertions, zero regressions** |
| Engine | SQLite harness · PHP 8.4.19 |

End-to-end execution was verified against a synthetic four-workspace fixture producing one of each classification (SAFE, RECOVERABLE, AMBIGUOUS, ERROR), and the database files were **byte-identical afterwards**.

The browser layer was tested against a live PHP server with real session cookies:

| Check | Result |
|---|---|
| Stranger, no session | **HTTP 404** |
| Signed in, **not** an administrator | **HTTP 404** |
| Signed-in administrator | HTTP 200, sees the button |
| Administrator runs it | report renders, Download JSON / Copy report present, classifications correct |
| POST with an invalid token (CSRF attempt) | refused — the button is shown again, nothing runs |
| Database credentials in the page | **none** (only a `name @ host` label is ever printed) |
| **Databases after the browser run** | **byte-identical** |
| Command line still works | yes |

**Production database is MySQL/MariaDB.** The harness result above is supplementary. The tool's own MySQL path is exercised when you run it on your server — that run is the authoritative evidence for this step.

---

## 8. What was deliberately NOT done

Per the Step-1 authorisation, none of the following was touched:

entitlement behaviour · `lib/licence.php` default-deny · any tenant data · the backfill · Marketplace entitlement · routes · cron · `is_master()` · any application behaviour.

`git status` after the change shows **three new files and no modified files.**

---

## 9. Next step (requires your approval — not started)

1. You run the tool and return the output.
2. I analyse the real data and resolve the lockout risks L-1, L-2, L-3 and L-6 from the safety check.
3. Only then is the Step-3 backfill designed — and enforcement (Step 5) remains off until the backfill is verified.
