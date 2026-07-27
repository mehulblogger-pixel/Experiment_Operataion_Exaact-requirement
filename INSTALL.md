# Installing on your own server

This is the whole procedure. There is no build step, no package manager and no
external service to sign up for — the app is plain PHP files that create their
own database the first time somebody opens them.

**Tested cold**: a pristine copy of the tracked files was unpacked, the web
server started, and the first page load created **81 tables and the admin
account with nothing configured**. Every screen then opened with no errors.

---

## 1. What the server needs

| Requirement | Notes |
|---|---|
| **PHP 8.0 or newer** | 8.1+ preferred. That is all — no Composer, no Node. |
| **PDO** + `pdo_mysql` | The only hard database requirement. |
| **json, mbstring, session, openssl** | Present in every normal PHP build. |
| **A web server** | Apache, Nginx, LiteSpeed or IIS. Anything that runs PHP. |
| **MySQL 5.7+ / MariaDB 10.3+** | One empty database and a user who owns it. |
| **HTTPS** | Not optional — see §7. |

**Optional.** Each of these is checked at runtime and the feature degrades with
a plain message rather than breaking the app:

| Extension | Turn it off and you lose |
|---|---|
| `zip` | Word (.docx) report and quotation export |
| `gd` | Photo compression, and PNG signatures on PDFs |
| `curl` | The optional AI writing assistant only |

Check what you have:

```bash
php -v
php -m | grep -E 'pdo_mysql|json|mbstring|openssl|zip|gd|curl'
```

---

## 2. Put the files on the server

Copy the whole folder to the web root (`/var/www/html`, `public_html`,
`htdocs` — whatever the server uses). About **2.8 MB, 149 files**. FTP, SFTP,
`scp`, rsync or a git checkout are all fine.

The web root must be the folder containing `index.php`. If you can only serve
from a parent folder, point the virtual host's `DocumentRoot` at this one.

---

## 3. Create the database

Create an **empty** database and a user with full rights on it. Nothing else —
no schema to import, no seed file to load.

```sql
CREATE DATABASE exaact CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'exaact'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON exaact.* TO 'exaact'@'localhost';
FLUSH PRIVILEGES;
```

`utf8mb4` matters — inspection reports carry names, °, ±, ½ and rupee signs.

---

## 4. Point the app at it

Edit **`config.php`**, the only file you have to change:

```php
$DB = [
    'driver' => 'mysql',
    'host'   => 'localhost',
    'name'   => 'exaact',
    'user'   => 'exaact',
    'pass'   => 'a-long-random-password',
];
$ADMIN = ['user' => 'admin', 'pass' => 'change-this-before-first-login'];
```

Everything can also come from environment variables instead — `DB_DRIVER`,
`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `ADMIN_PASSWORD` — which is the
better route under Docker or systemd, because then no password sits in a file.

---

## 5. Open it in a browser

That is the installation. The first request notices the database is empty and
builds the entire schema, the master lists and the admin account.

Sign in with the `$ADMIN` credentials, then immediately:

1. **Settings → Application name** — your company's name. It drives the login
   screen, the sidebar, the browser tab, the PDF letterhead and the "From" name
   on every e-mail.
2. **Settings → Terminology** — if you call things something else.
3. **Change the admin password**, and create real accounts under **Users**.
4. **Masters** — offices, business units, expense headings, holidays.

### Upgrading later

Copy the new files over the old ones and open any page. The same mechanism that
built the schema also migrates it — new tables and columns are added in one
pass, and it is safe to run repeatedly. **Take a database dump first anyway.**

---

## 6. The scheduler (reminders)

`cron.php` sends report-due reminders, overdue-closure escalations, the
management digest and certificate-expiry warnings. Without it the app works
but nobody is chased.

```
# cPanel → Cron Jobs, or /etc/crontab
0 7  * * *  php /path/to/app/cron.php
0 18 * * *  php /path/to/app/cron.php
```

systemd timer, or Windows Task Scheduler calling `php.exe cron.php`, work the
same way. Twice a day is enough.

---

## 7. HTTPS — do not skip this

Sessions, salary figures, client contracts and signed reports all cross the
wire. Two things break without it:

- the session cookie is only marked `Secure` when the request arrives over
  HTTPS, so on plain HTTP it can be read off the network;
- **the offline/installable phone app will not work at all** — browsers refuse
  to register a service worker over HTTP, so engineers lose offline drafts and
  "Add to Home Screen".

Let's Encrypt is free; on shared hosting it is usually one click in the panel.

---

## 8. Backups

Every uploaded file — bills, evidence photos, signed reports, quotation
attachments — is stored **inside the database**, not on disk. That is
deliberate: one dump is a complete backup, and moving servers moves the files
too.

```bash
mysqldump --single-transaction --routines exaact | gzip > exaact-$(date +%F).sql.gz
```

Daily, kept off the server, **and restore one once** to prove the backup is
real. A backup nobody has restored is a hope, not a backup.

The trade-off: the database grows with attachments. Budget roughly
`documents × average size × 1.33` (base64 costs a third). A few GB a year at
normal volumes. Keep an eye on `max_allowed_packet` — 64 MB is a sensible value
if large attachments are expected.

---

## 9. Small installs: no MySQL needed

For a single office, or for a pilot, set `'driver' => 'sqlite'` and the app
keeps everything in one `data.sqlite` file. No database server at all. It is
the same code — the whole test suite runs on it. Move to MySQL when more than a
handful of people are writing at once.

Whichever you choose, `data.sqlite` must **not** be reachable over the web. It
is already excluded by the shipped `.htaccess`; on Nginx add:

```nginx
location ~ \.(sqlite|db)$ { deny all; }
```

---

## 10. Hardening (worth an hour)

- Give the MySQL user rights on **its own database only**.
- Keep `config.php` out of the web root, or `deny` it in the server config.
- Turn `display_errors` **off** in production `php.ini`. The app has its own
  readable error page and only shows technical detail to someone signed in.
- Turn on **two-factor** for admin accounts (Settings → security).
- Set `upload_max_filesize` and `post_max_size` in `php.ini` to at least the
  app's own upload limit (Settings, default 12 MB) or large files fail at the
  web-server layer before the app ever sees them.
