# Deploying on MilesWeb (Node.js)

This is the Node.js edition, built to run on your MilesWeb hosting the way you
expect: upload the folder, point the Node.js app at it, done. No Render.

## One-time setup in the MilesWeb panel

### 1. Create a MySQL database
- Left menu → **Databases** → create a database and a database user, note the
  **name / user / password** (MilesWeb usually prefixes them, e.g. `abcd_inspexops`).

### 2. Upload the app
- Left menu → **File Manager** → open the `schedule.mghaiapps.com` folder.
- Upload the **contents of this `nodeapp` folder** here (or upload the zip and
  Extract). Do **not** upload `node_modules` — the panel installs it for you.

### 3. Create the Node.js app
- Left menu → **Node.js** → **Create / Setup Application**.
- **Application root:** the `schedule.mghaiapps.com` folder you uploaded to.
- **Application startup file:** `app.js`
- **Application URL / domain:** `schedule.mghaiapps.com`
- Node version: 18 or higher.
- Save. Then use the panel's **Run NPM Install** button (installs dependencies).

### 4. Environment variables
In the Node.js app settings, add these (from `.env.example`):
- `SESSION_SECRET` = any long random text
- `ADMIN_USERNAME` = admin
- `ADMIN_PASSWORD` = a strong password (this is your app login)
- `DB_NAME`, `DB_USER`, `DB_PASS` = the MySQL details from step 1
- `DB_HOST` = localhost

### 5. Start / Restart
- Click **Restart** in the Node.js app. On first start it creates the tables and
  the admin login automatically.

Open `https://schedule.mghaiapps.com/` and sign in with the admin username /
password you set.

## After uploading new files later
Always click **Restart** in the Node.js app so it picks up the new code — that
one step is what was missing before.

## Running locally (for testing)
```
cp .env.example .env      # leave DB_* commented to use SQLite
npm install
npm start                 # http://localhost:3000
```
