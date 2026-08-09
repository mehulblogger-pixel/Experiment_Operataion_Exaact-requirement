# Updating your live site to the latest files

Plain steps to push the newest code onto a site that is **already running**
(cPanel / MilesWeb-style hosting). This is *not* a fresh install — it is the
"upload the new files over the old ones" job. It takes about 10 minutes.

> **Why this matters:** most "it's not working" problems on the live site have
> turned out to be **old files still running** — the fix or feature was written
> but never uploaded. The report builder (14a/14b), the Add-user crash, the new
> quotation and geo-fence features: all of them are in the code and only need to
> reach the server. Doing this once brings **everything** up to date.

---

## The golden rule

**Upload the WHOLE `phpapp` folder, not a few hand-picked files.** Picking
individual files is exactly how a site ends up half-updated. Replace the lot
(except the two "keep" files below) and nothing gets missed.

**Never overwrite these two — they are yours, not part of the update:**

| Keep | Why |
|---|---|
| `config.local.php` | your own settings (company, options). Kept out of the package on purpose. |
| `data.sqlite` | your data — **only if you use the built-in database.** On MySQL there is no such file. |

---

## Step 1 — Back up first (2 min, never skip)

- **MySQL site:** cPanel → **phpMyAdmin** → pick your database → **Export** →
  *Quick / SQL* → **Go**. Save the `.sql` file somewhere off the server.
- **Built-in database site:** cPanel → **File Manager** → open the app folder →
  download **`data.sqlite`** and keep a copy.

A backup you have never restored is only a hope — but for a files-only update
this one is your safety net if anything looks wrong afterward.

---

## Step 2 — Get the latest files

**Best (if your host's cPanel has "Git™ Version Control"):**
1. cPanel → **Git Version Control** → your repository → **Pull or Deploy** →
   **Update from Remote**. It fetches the newest code straight in.
2. This route *never* touches `config.local.php` or `data.sqlite` — they are
   already ignored by Git — so it is the safest.

**Otherwise (download a ZIP):**
1. On GitHub, open the branch, click **Code ▾ → Download ZIP**.
2. That ZIP has the newest `phpapp` folder inside it.

---

## Step 3 — Upload over the old files

1. cPanel → **File Manager** → open the folder that serves your site (usually
   **`public_html`**, or the sub-folder/sub-domain the app lives in — the one
   with `index.php` in it).
2. **Upload** the ZIP.
3. Select it → **Extract** → let it **overwrite** the existing files.
4. If extracting made a sub-folder (e.g. `phpapp/…` inside), move the files up
   one level so **`index.php` sits directly in the web folder** again.
5. **Do not overwrite** `config.local.php` or `data.sqlite` (Step "golden rule").
   If the upload asks, choose **keep existing** for those two.

> Files that begin with a dot (like `.htaccess`) are hidden in File Manager until
> you turn on **Settings → Show hidden files** — make sure `.htaccess` uploaded.

---

## Step 4 — Open one page (the app upgrades itself)

Visit **any page** of the site once. On that first load the app notices the code
changed and **upgrades its own database automatically** — it adds any new tables
and columns in one pass. You do not run anything by hand. You will just see your
normal screen.

*(This is the mechanism that heals the "Unknown column" type errors on its own.
It only runs the once, right after an update.)*

---

## Step 5 — Check it worked (1 min)

1. **Sign in → Settings → Server check** — the app's own health page.
2. **Re-test what was wrong before.** For this update, that means:
   - **Organisation & people → Login accounts → Add user** — opens, no error.
   - **Reporting → Report templates → open a type → Form builder** — the
     **➕ Scope of activities** button and the **Instrument** field type are there.
   - **New quotation** — pick a client and Payment terms fill in; each site row
     has a **Vendor** picker and a **"Site to be confirmed"** type.
   - **A client/vendor's form** — a **📍 Site location** section for the geo-fence.

If all of that is present, the site is fully up to date.

---

## If something looks wrong

| What you see | What it means / do |
|---|---|
| A "database" / "Unknown column" message | Open any page once more — the self-upgrade runs on the first load after an update. |
| Blank page after upload | `config.local.php` was probably overwritten. Restore your copy of it (or re-enter settings), then reload. |
| The site sends every address to "page not found" | `.htaccess` did not upload. Turn on *Show hidden files* and upload it. |
| A feature still missing | Some files were skipped — re-upload the **whole** `phpapp` folder (the golden rule), not selected files. |

---

## The short version (for next time)

1. Back up the database.
2. Pull (Git) **or** upload+extract the whole `phpapp` folder, overwriting —
   **except** `config.local.php` and `data.sqlite`.
3. Open one page. Done.
4. Spot-check Settings → Server check.
