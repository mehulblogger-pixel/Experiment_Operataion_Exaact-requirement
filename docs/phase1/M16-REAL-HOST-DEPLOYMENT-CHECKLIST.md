# M16 — Real-Host Deployment Runbook (MilesWeb / mPanel)

Written for someone who is **not** a developer. Work top to bottom. Where a step
says *record it*, write the answer down — that is the evidence that closes
Phase 1's remaining untested items.

> Everything below was rehearsed on a live web deployment of this exact build,
> except the items marked **(host-specific)**, which only your server can answer.

---

## The sequence — do not vary it

```
1. Backup        2. Verify backup    3. Upload       4. Open once
5. Deploy check  6. Verify database  7. Verify cron  8. Smoke test
9. Verify logs   10. Confirm release
```

---

### 1. Backup
- [ ] In mPanel → phpMyAdmin, export the whole database (Quick, SQL, gzip).
- [ ] If the app is already live, also take an in-app backup (Settings → Backup).

### 2. Verify the backup — do not skip
- [ ] Download the file and check it is **not 0 bytes** and opens.
- [ ] A backup you have not opened is not a backup.

### 3. Upload
- [ ] Upload the release to the web root. Record the **commit hash** you deployed.
- [ ] Do **not** upload `config.local.php` or `tenants.php` from anywhere else — they belong to the server, not the release.
- [ ] Create/confirm `config.local.php` with the database host, name, user and password, and a real admin password.

### 4. Open the application once
- [ ] Visit the site in a browser.
- [ ] **You will see a Licence Agreement first.** Read it, tick the three boxes, type your name, designation and organisation, and submit. Nothing is installed until you do.
- [ ] The next page **takes about 15 seconds** while 306 tables are created. **Do not refresh.** (Measured: 13.1 s.)
- [ ] Then sign in as `admin` with the password from `config.local.php`.
- [ ] A brand-new workspace opens on **"Welcome — let's set up your system"**, and every menu item returns there until you finish it. That is normal. Complete it.

### 5. Deployment check
- [ ] Open `/deploy-check.php`. Every checksum must match. A mismatch means a half-finished upload — re-upload, do not proceed.
- [ ] **Record:** PHP version, MySQL/MariaDB version shown *(host-specific)*.

### 6. Verify the database
- [ ] In phpMyAdmin, confirm **306 tables** in the database you configured.
- [ ] Confirm it is the *right* database — not another workspace's.
- [ ] The database user needs `CREATE` and `ALTER` **permanently**; the app extends its own schema on upgrade.
- [ ] *(host-specific)* On a panel host the database is usually named `account_dbname`. The app handles that; just use the full name.

### 7. Cron
- [ ] In mPanel → Cron Jobs, add: `/usr/local/bin/php /home/<account>/public_html/cron.php` every 15 minutes. *(Confirm the PHP path with your host.)*
- [ ] Run it once by hand and read the output. It should end without errors. Steps for modules you have not bought announce themselves as skipped — that is correct.
- [ ] **Record:** the cron output.

### 8. Smoke test — the ten checks that matter
- [ ] Sign in; land on a working dashboard.
- [ ] Open one existing job and one existing invoice — your records survived.
- [ ] A branch-limited user sees only their own branch's registers.
- [ ] Ask for another branch's record by editing the id in the address bar — you must be refused.
- [ ] Open a module you have not subscribed to by typing its address — you must get the lock screen, not an error.
- [ ] Download a document — it opens; and a document from another branch is refused.
- [ ] Trigger a deliberate wrong address — an ordinary user must see a reference code, never SQL or a file path.
- [ ] Sign out, sign back in.
- [ ] Open the site on a phone — it should be usable.
- [ ] *(host-specific)* Confirm the address bar shows **https** and that `http://` redirects to it.

### 9. Verify logs
- [ ] mPanel → Errors. There should be nothing new from the deployment.
- [ ] *(host-specific)* Confirm no credentials appear in any log.

### 10. Confirm the release
- [ ] Record: date, time, commit hash, who deployed, and the results of steps 5–9.
- [ ] Send one test e-mail from Settings to your own address. **This has not been tested anywhere else — it needs your real SMTP.**

---

## If something goes wrong

| Symptom | What it means | Do this |
|---|---|---|
| "Licence Agreement" every time | The app folder is not writable | Ask the host to make the app folder writable, then accept again |
| A page about database settings, not an install offer | The password or database name is wrong | Fix `config.local.php`. **The app deliberately refuses to "install" over a live system** |
| "No such workspace" | The subdomain is not in `tenants.php` | Add it, or check the spelling |
| Deploy-check mismatch | Half-finished upload | Re-upload the listed files |
| First page hangs | It is building 306 tables | Wait 30 seconds before doing anything |

**Never** run a migration by hand, restore a backup over a newer schema, or point
a live workspace at another workspace's database. The app refuses an unknown or
unconfigured workspace rather than guessing — verified.

---

## Sessions, when upgrading from before M13

Sessions created before M13 acquire their workspace binding at the next sign-in,
and both paths that could have abused that are independently closed. **No action
is required.** If you would rather everyone started clean, ask your host to clear
the session save path at deploy time — this signs everybody out once.
