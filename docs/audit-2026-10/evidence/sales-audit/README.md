# Sales audit — probes (19 Oct 2026)

Evidence for `../../sales/`. Four investigations, each in its own folder, each
booting the **real application** against a **throwaway SQLite database**
(`phpapp/tests/bootstrap.php`) and driving the **real route handlers** —
module gate and permission checks included — one request per process, as
users holding the shipped default rights of each role.

| Folder | Investigation | Harness | Probes |
|---|---|---|---|
| `A/` | Lifecycle — lead, deal, enquiry, quotation, revision, costing, quote → contract | `plib.php`, `w.php` (`RACE()` fires identical requests at the same instant) | `p00`–`p11` `*.php`, each with its `*.out` |
| `B/` | Pricing, costing, approval bands, checklist, external quotes | `harness.php` | `p1`–`p7` `*.php` with `p*_output.txt` |
| `C/` | Handoffs — contract, work order, job, billable, invoice, Books, Ads Pro, customer data | `boot.php`, `lib.php`, `w.php`, `setup.php`; `run_all.sh` re-runs everything on a fresh database (P11 separately); `adstub/router.php` is a local stand-in for Ads Pro | `p1`–`p11` with `*.out` |
| `D/` | Reporting, templates, permissions, forms | `act.php` | `*.php`; `route_matrix.tsv`, `perm_probe.tsv`, `flow_*.out`, `recon_read.json`, `industry_templates.md` |
| here | Shipped default Sales rights of all 18 roles | — | `lead-roles.php` → `lead-roles.out`; `settings-roles.php` |

`*/NOTES.md` is each investigator's report as handed back, kept so every
`SA-nn` in the audit can be traced to the probe that produced it. The audit
documents are the source of truth; where a note and the audit differ, the audit
records the re-check.

**Running them.** The scripts carry the absolute path of the checkout they were
written in (`/home/user/Experiment_Operataion_Exaact-requirement`); change that
one line if yours differs. Then, from `phpapp/`:

```
php ../docs/audit-2026-10/evidence/sales-audit/lead-roles.php
TMPDIR=/tmp/salesA php ../docs/audit-2026-10/evidence/sales-audit/A/p05_quote_status.php
```

They write only to the throwaway database and the temp folder. Mail is switched
off in the C harness (`sendmail_path=/bin/true`), MGH Books runs in its own
dry-run mode, and Ads Pro is the local stub on `127.0.0.1:8765` with a dummy
token. **Never point them at a live database.**

`count_matrix.py` recounts `../../sales/SALES-END-TO-END-TEST-MATRIX.md` by
result, so the counts in that document cannot drift from its rows.
