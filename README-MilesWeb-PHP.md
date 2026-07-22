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

---

## Operations & Finance modules (all 5 phases)

The app now covers the full SGS Ahmedabad blueprint:

- **Calls** — call register (client, vendor, IBO, region, SBU, product, dates).
- **Jobs** — allocation, inspector/sub-con assignment, BOSS number, schedule &
  random dates, expected credit (mandatory), credit direction (received/given),
  reporting frequency, folder link.
- **Closure** — inspector uploads report + enters SBU-wise expenses; TAT is
  computed automatically; job locks.
- **Masters** — Inspectors, Sub-contractors, Sub-con rate matrix, BOSS numbers,
  Holidays, Attendance, Credit reconciliation.
- **Comp-off** — earned automatically when a job date falls on a Sunday
  (30-day expiry).
- **Dashboards** — profitability (salary + 8% overhead), utilization, TAT,
  credit received vs given, reconciliation (expected vs actual).

### Roles
Set a user's role on the **Users** screen (Admin only):

- **Master Admin** — everything, including salary & profit figures.
- **Admin** — scheduling, reconciliation, dashboards (no salary).
- **Coordinator** — create calls & jobs, pick BOSS, enter expected credit.
- **Inspector** — sees only *My Jobs*; uploads reports & expenses.

Link an Inspector login to their inspector record (Users → Linked inspector)
so *My Jobs* shows the right jobs.

### Assignment / closure / reminder emails
Emails are **logged** by default. To actually **send** them, add these to
`config.php` (or as environment variables):

```php
putenv('OPS_MAIL_ENABLED=1');
putenv('OPS_MAIL_FROM=ops@mghaiapps.com');
```

### Automatic reminders (cPanel Cron)
MilesWeb panel → **Cron Jobs** → add two jobs pointing at `cron.php`:

```
0 7  * * *   php /home/USER/public_html/cron.php   # report-due, 07:00
0 18 * * *   php /home/USER/public_html/cron.php   # overdue-closure, 18:00
```

(Replace the path with your real one from File Manager.) One reminder per job
per day; frequency-aware (daily / alternate / weekly / fortnightly / monthly).

### Per-user billing (seats)
Set `SEAT_LIMIT` (e.g. `putenv('SEAT_LIMIT=10');` in `config.php`). The Users
screen then blocks adding active users beyond the limit — deactivate one to free
a seat; the old user's data stays intact.

### First-run security
Change the default `admin` / `admin12345` password immediately via the
**Password** link in the top bar.

---

## Making it your own — configurable master lists & custom fields (Admin)

The app adapts to any field-operations company **without code changes**, from
**Masters → "Make it your own"**:

- **Master lists & dropdowns** (`/lookups`) — every dropdown in the app is an
  editable list. Add/remove values, or create a whole new list.
- **Dependent (cascading) lists** — when you create a list, pick a "Depends on"
  parent to make it filter by the parent's value. Examples that ship built-in:
  - **SBU → Activity code** (Activity values belong under an SBU)
  - **Product family → Wax type → Tier** (e.g. Candles → Soy wax → Premium)
  Any depth is supported.
- **Custom fields** (`/custom-fields`) — add your own fields to the **Call** or
  **Job** form (text / number / date / dropdown). A dropdown field can use any
  master list; pick the deepest list of a dependent chain to get cascading
  selects automatically. New fields appear on the form and the detail page
  instantly — no code, no redeploy.

Built-in lists are marked "Built-in": you can edit their values but not delete
the list. Lists you create are fully removable.
