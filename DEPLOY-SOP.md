# Installation SOP — run the system on a server OR on a single laptop

This is the complete procedure for both ways of running the system. Pick the
one that matches you. Either way there is **no build step and nothing to
compile** — it is plain PHP that builds its own database the first time a page
is opened.

The file you download is **`exaact-<version>.zip`** plus a **`.sha256`**
checksum line to confirm the download arrived intact.

---

## Which one do I want?

| | **A. Hosting / server** | **B. Single laptop** |
|---|---|---|
| Best for | Many people, always-on, accessed over the internet | One person / one office, offline, quick trial |
| Needs | A web host with PHP (e.g. your cPanel) | A laptop with PHP installed |
| Database | Built-in **or** MySQL | Built-in (nothing to set up) |
| Start it | Always running | Double-click `start` when you want it |

You can start on the laptop and move to hosting later — the files are identical.

---

## A. Install on hosting / a server (cPanel)

**You need:** your hosting login, and PHP 8.1+ (every normal host has it).

1. **Verify the download (optional).** The `.sha256` file holds one line per
   archive. It only matters that the file was not corrupted in transit.

2. **Upload the files.**
   - cPanel → **File Manager** → open **`public_html`** (or the sub-folder /
     sub-domain you want the app to live in).
   - **Upload** `exaact-<version>.zip`.
   - Select it → **Extract**. You now have the app files in that folder.
   - *(If extracting made a sub-folder like `exaact-<version>/`, move the files
     up one level so `index.php` sits directly in the web folder.)*

3. **Open the site in a browser** — `https://your-domain-or-subdomain/`.
   The **Set up the system** screen appears.

4. **Click “⚡ One-click start”.**
   - This uses the **built-in database** — nothing else to create. Best for a
     small team or to get going immediately.
   - **OR**, for a busy multi-user site, open **“Connect MySQL instead”** and
     enter the database name / user / password from
     **cPanel → Databases → MySQL Databases** (create an empty database and a
     user with all privileges there first). It tests and saves them.

5. **Finish the short first-run wizard** — set the **admin password**, the
   **company name**, currency and financial year. Done.

6. **Sign in** and start using it.

> **HTTPS is required** for the camera, GPS punch-in and location features to
> work in the browser. Turn on the free SSL your host provides (cPanel →
> Security → SSL/TLS, or “Let’s Encrypt”).

### Keeping it running (hosting)

- **Nightly reminders / digests (optional):** cPanel → **Cron Jobs**, run once a
  day: `php /home/<youraccount>/public_html/cron.php`
- **Backups:** cPanel → **Backup**. If you used the built-in database, also copy
  the file **`data.sqlite`** from the app folder. If you used MySQL, export the
  database from **phpMyAdmin**.

---

## B. Run on a single laptop (double-click)

**You need:** PHP 8.1+ installed once on the laptop. Check by opening a terminal
/ command prompt and typing `php -v`. If it prints a version, you are ready. If
not, see *“Installing PHP once”* below.

1. **Unzip** `exaact-<version>.zip` to any folder — e.g. Desktop.

2. **Start it:**
   - **Windows:** double-click **`start.bat`**
   - **Mac:** double-click **`start.command`** *(the first time: right-click →
     **Open** → **Open**, to allow it)*
   - **Linux:** run `./start.command` in the folder.

3. A small black window opens and your **browser opens automatically** at
   `http://127.0.0.1:8080`. (If it doesn’t open, type that address yourself.)

4. **Finish the short first-run wizard** — admin password, company name. Done.
   On a laptop the built-in database is used automatically; there is **no
   database screen** to fill in.

5. **To stop the app:** close the black window. **To use it again:** double-click
   `start` again.

> Everything stays on this laptop. The data lives in one file, **`data.sqlite`**,
> in the app folder — copy that file to back up or to move to another machine.

### Installing PHP once (only if `php -v` failed)

- **Windows:** download the **Thread-Safe** zip from
  <https://windows.php.net/download/>, unzip to `C:\php`, add `C:\php` to your
  PATH, and in `php.ini` enable `extension=pdo_sqlite` and `extension=mbstring`.
- **Mac:** install Homebrew from <https://brew.sh>, then `brew install php`.
- **Linux (Debian/Ubuntu):** `sudo apt install php-cli php-sqlite3`

---

## Upgrading later (both ways)

1. Take a backup first (the `data.sqlite` file, or a MySQL export).
2. Upload/extract the new files **over** the old ones.
3. Open any page — the database updates itself automatically.

**Never overwrite `config.local.php`** — that one file holds your own settings
and is deliberately kept out of the package so an upgrade cannot erase it.

---

## If something looks wrong

- **A blank page or “database” error on hosting:** the built-in one-click path
  needs the app folder to be writable, or use MySQL. Re-open the site to return
  to the setup screen.
- **The camera / punch-in won’t give a location:** the site must be on
  **https://** (hosting) — on a laptop `127.0.0.1` is treated as secure, so it
  works there too.
- **Check the system’s own health:** sign in → **Settings → Server check**.
