# Installing MGH Hire

No build step, no package manager, no external service. The app creates its own
database on the first page load. Pick the path that matches you.

---

## A. On a laptop / desktop (one person, offline, quick)

1. Copy the `mgh-hire` folder anywhere (e.g. the Desktop).
2. Double-click **`start.command`** (Mac / Linux) or **`start.bat`** (Windows).
   - Mac, first time: right-click → **Open** → **Open**.
3. Your browser opens at `http://127.0.0.1:8090`. Sign in and go.
4. Stop = close the black window. Reopen = double-click `start` again.

Needs **PHP 8.1+**. If it's missing, the launcher tells you how to get it. Your
data is one file, `data.sqlite`, next to the app — copy it to back up or move.

---

## B. On a website / hosting (cPanel — many users, always online)

1. Upload the `mgh-hire` folder into `public_html` (or a sub-folder/sub-domain).
2. Copy `config.local.sample.php` to **`config.local.php`** and set the admin
   password. For a small team leave the database as `sqlite`; for a busy site set
   `driver => 'mysql'` and fill in a database you create in cPanel → MySQL
   Databases.
3. Open the site in a browser. The database builds itself. Sign in.
4. **Turn on HTTPS** (cPanel → SSL/TLS, or Let's Encrypt) — required so the login
   cookie is marked secure.

The web root must be the folder containing `index.php`.

---

## Requirements

| Need | Notes |
|---|---|
| **PHP 8.0+** (8.1+ preferred) | With `pdo` and either `pdo_sqlite` or `pdo_mysql`. |
| `json`, `mbstring`, `session`, `openssl` | Present in every normal PHP build. |
| A web server (server path) | Apache/Nginx/LiteSpeed/IIS. |
| MySQL 5.7+ / MariaDB 10.3+ | Only if you choose the `mysql` driver. |

---

## Security (worth ten minutes)

- **Change the admin password** on first sign-in (Users screen).
- `config.local.php` and `data.sqlite` are already denied to the web by the
  shipped `.htaccess`. On Nginx add: `location ~ \.(sqlite|db)$ { deny all; }`
- Turn `display_errors` **off** in production `php.ini`.
- Keep a backup: laptop → copy `data.sqlite`; MySQL →
  `mysqldump --single-transaction dbname | gzip > backup.sql.gz`.

---

## Upgrading later

Copy the new files over the old ones and open any page — the database migrates
itself in one safe pass. **Never overwrite `config.local.php` or `data.sqlite`.**
Take a backup first.
