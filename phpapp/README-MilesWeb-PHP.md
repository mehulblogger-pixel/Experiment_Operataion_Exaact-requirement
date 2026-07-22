# Inspection Ops — PHP edition (deploys like your other MilesWeb sites)

Plain PHP + MySQL. **No build step, no npm, no Node process** — upload the files
and it runs, exactly like your books/adspro sites.

## Deploy on MilesWeb (5 minutes, all in the panel)

### 1. Create a MySQL database
- MilesWeb panel → **Databases** → create a database + user.
- Note the **database name, username, password** (MilesWeb usually prefixes
  them, e.g. `abcd_inspexops`).

### 2. Upload the files
- **File Manager** → open the folder for your app's subdomain
  (e.g. `ops.mghaiapps.com`). If that subdomain is set up as a **Node app**,
  delete that Node application first (Node.js panel) so it becomes a normal
  PHP site — or just use a fresh subdomain / folder.
- Upload the **contents of this folder** so that `index.php` sits **directly**
  in the subdomain folder (not inside a sub-folder).

### 3. Enter your database details
- Open **`config.php`** in the File Manager editor and fill in:
  ```php
  'name'   => 'your_db_name',
  'user'   => 'your_db_user',
  'pass'   => 'your_db_password',
  ```
- Save.

### 4. Open the site
Visit `https://ops.mghaiapps.com/` (or wherever you uploaded).
On the first visit it **creates its own tables and loads your 665 clients/
vendors automatically**, then shows the login.

- Username: `admin`
- Password: `admin12345`  (change it after first login)

That's it — no restart, no install, no Node.js panel. Every future update is
just: upload the changed files. Done.

## Notes
- Requires PHP 8+ with the `pdo_mysql` extension (standard on MilesWeb).
- To change the admin password later, do it inside the app (or set the
  `ADMIN_PASSWORD` value in `config.php`).
- `.htaccess` gives clean URLs; MilesWeb (Apache/LiteSpeed) supports it out of
  the box — the same mechanism WordPress uses.
