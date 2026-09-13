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

## 3. How to run it

### Option A — command line (preferred)

In cPanel → **Cron Jobs**, add a one-off command (or run over SSH):

```
php /home/USER/public_html/phase1-inventory.php
```

For machine-readable output:

```
php /home/USER/public_html/phase1-inventory.php --json
```

### Option B — browser (when no command line is available)

1. Upload `phase1-inventory.php` to the app folder (it is in the repository root, alongside `index.php`).
2. Create a file next to it called **`phase1-inventory.key`** containing **one line: a long random password you invent**.
3. Visit:
   `https://YOUR-DOMAIN/phase1-inventory.php?key=THAT-PASSWORD`
   (add `&json=1` for JSON)
4. **Delete `phase1-inventory.key` when finished.**

Without that key file the browser route returns `404 Not available.` — so the page cannot be discovered or run by anyone else.

### Run it on the CONTROL install
Run it at your **base domain** (the platform/owner install — e.g. `operations.mghaiapps.com`), not inside a workspace. The tool forces control-install resolution itself, but running it there is clearest.

---

## 4. How to supply the output

Copy **the entire output** and send it back for review.

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
| `phpapp/lib/phase1_inventory.php` | The engine: read-only guard, entitlement maths, risk classification. **Not loaded by `index.php`** — the application does not know it exists. |
| `phpapp/phase1-inventory.php` | The standalone runner (CLI + key-gated browser). |
| `phpapp/tests/test_phase1_inventory.php` | 62 assertions covering the engine. |

**Delete `phase1-inventory.php` (and the `.key` file) from the server once the inventory has been supplied.** It is a one-off diagnostic, not part of the product.

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
