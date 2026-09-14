# Milestone 15 — Production Deployment Checklist

For deploying EXAACT to MilesWeb mPanel (or any LAMP host) on MySQL/MariaDB.
Work top to bottom. Nothing here needs a developer.

---

## 1. Before you touch the server

- [ ] **Take a backup of the live database.** Nothing below is reversible without one.
- [ ] Note the current version: the footer of any screen, or `deploy-check.php`.
- [ ] Pick a quiet hour. Step 6 can sign people out.

## 2. What the server must provide

| | Required | Verified in M15 |
|---|---|---|
| **PHP** | 8.1 or newer | 8.4.19 |
| PHP extensions | `pdo_mysql`, `mbstring`, `zip`, `gd`, `curl`, `openssl` | `pdo_mysql` exercised throughout |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ | MariaDB **10.11.14** |
| Character set | `utf8mb4` / `utf8mb4_unicode_ci` | set by the app per connection |
| Disk | the app + room for uploads (documents live **in the database**, so the DB grows) | 306 tables |
| HTTPS | a valid certificate on the domain and every workspace subdomain | deployment item |

The application **sets its own session charset and SQL mode** on each connection,
so a host defaulting to `latin1` or strict `NO_ZERO_DATE` needs no server change.

## 3. Database

- [ ] Create the database (and the tenant databases, in cloud mode), `utf8mb4` / `utf8mb4_unicode_ci`.
- [ ] Create a database user with `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP` on them.
      `CREATE` and `ALTER` are needed permanently — the app builds and extends its own schema on boot.
- [ ] On a panel host, remember the account name space (`acctname_dbname`); the app derives and uses it.
- [ ] Do **not** pre-create tables. The app builds all 306 itself, and re-running is safe.

## 4. Configuration — never in git

- [ ] Copy `config.local.sample.php` → `config.local.php` and fill in host, database, user, password.
- [ ] Set a real admin password there (not the sample one).
- [ ] Confirm `config.local.php` and `tenants.php` are **not** in the repository — they are gitignored, and M15 verified nothing credential-shaped is tracked.
- [ ] File permissions: config readable by the web server, not world-readable.

## 5. Upload and first boot

- [ ] Upload the application files.
- [ ] Open the site once. The schema builds on the first request (~11 s on an empty database; ~1 s thereafter).
- [ ] Open `deploy-check.php` and confirm every checksum matches — that proves the upload is complete, not half-transferred.
- [ ] Sign in as the admin account.

**Re-running is safe.** M15 ran the schema three times over a populated database: 306 tables stayed 306, no duplicates, no data lost.

## 6. Sessions (only when upgrading from before M13)

Sessions created before M13 carry no workspace binding. They acquire one at the
next sign-in, and both paths that could abuse that are independently closed, so
**no action is strictly required**.

- [ ] If you prefer everyone to start clean, rotate the session cookie name or clear the session save path **at deploy time**. This signs everybody out once. It is an operational choice, not a code change.

## 7. Cron

- [ ] Schedule `cron.php` (typically every 15 minutes) and `cron_ads.php` if used.
- [ ] Run it once by hand and read the output. Paid steps announce themselves as skipped when a module is not entitled — that is correct, not a failure.
- [ ] In cloud mode, cron must run **per workspace**; a run without a workspace touches no tenant's data.

## 8. Mail, files, domain

- [ ] Configure the sender address and SMTP in Settings, then send one test mail.
- [ ] Uploaded documents are stored **inside the database**, so a file move needs no separate media directory — but the database backup must be large enough to hold them.
- [ ] Point the domain (and, in cloud mode, the wildcard subdomain) at the app, with HTTPS on both.
- [ ] In cloud mode, set `base_domain` in `tenants.php` and add each workspace.

## 9. Backup

- [ ] Schedule a database backup you have actually restored from once.
- [ ] The in-app backup was verified on MariaDB in M15: 6,505 rows across 306 tables written and read back, deleted rows returned, settings rolled back.

## 10. After deploying — check these five

- [ ] Sign in, and land on a working dashboard.
- [ ] Open one existing job and one existing invoice — records survived.
- [ ] A branch-scoped user sees only their branch's registers.
- [ ] An unentitled module shows the lock screen, not an error.
- [ ] Trigger a deliberate 404: an ordinary user must see a reference code, never SQL or a file path.

---

## Deployment order that must not be varied

```
backup  →  upload files  →  open once (schema builds)  →  deploy-check  →  cron  →  smoke test
```

Never run a migration by hand, never restore a backup over a newer schema, and
never point a live workspace at another workspace's database — the app refuses an
unknown or unconfigured workspace rather than guessing, and M15 verified it.
