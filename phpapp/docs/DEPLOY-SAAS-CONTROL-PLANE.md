# Go-live plan — turning on the single-URL SaaS control plane

**Audience:** you (the owner) and whoever runs the hosting. Plain language, no
code required to follow it.

**What this plan is for:** we have built, on the `Testing` branch, everything
needed to run **one website that serves many client companies** — each company
signs in at the same address, lands in its own private workspace, sees only the
modules it paid for, and is capped to the seats it bought. This document is the
careful, staged way to switch that on for the live site **without disturbing
anyone who is using it today.**

> **Nothing in this plan touches the live site until you say "go" at each stage.**
> Every stage has a way back. We rehearse on a copy first, then ship the code
> "dark" (present but doing nothing new), then add **one** pilot company, and only
> then open it to real clients.

---

## 1. The one idea that makes this safe

The whole control plane is **additive**. We did not rewrite how the site works —
we added a new lane next to the existing one:

- **Nothing changes until the first company is added.** The moment the new code
  reaches the server, the site behaves *exactly* as it does today. There is no
  visible difference for any current user. The new "company directory" starts
  empty and empty means "behave normally."
- **Existing sign-ins are untouched.** People who log in with a **username** (the
  way everyone does today) follow the old path, unchanged. The new email → company
  routing only activates for someone signing in with an **email address that we
  have actually registered** to a company. Until we register one, there are none.
- **The switch is a file, and it is reversible.** Multi-company ("cloud") mode is
  turned on by one small settings file (`tenants.php`). Present = SaaS mode. Remove
  it = back to a normal single-company site, instantly.

This is why we can ship the code well before we use it, and why the backout at
every stage is quick.

---

## 2. The two decisions — CONFIRMED

Both business calls are now settled.

### Decision A — CONFIRMED: **A1, the shared front door**

`operations.mghaiapps.com` becomes the **single sign-in address for everyone** —
MGH and every client. There is no second site to maintain.

### Decision B — CONFIRMED: **MySQL, per company**

Every client company gets its **own MySQL database** (created once in cPanel), so
each client is fully isolated on a proper server database. We are **not** using the
built-in file database anywhere. MGH's own operations stays on the MySQL database
it already uses.

### What these two choices mean in practice — the very good news

Because MGH already runs on MySQL and we chose the shared front door, **your
current live database does not move and is not migrated at all.** The current
install simply takes on a second role:

- It **stays** MGH's own operations workspace — exactly as it works today, with the
  same data, the same MySQL database, the same username sign-in.
- It **also becomes** the control install: the place where you add and manage other
  companies (the **Companies** console). This is just a new menu, not a new site.

So MGH's data is never copied, exported, re-imported or touched. Each **new client**
gets a fresh, empty MySQL database of its own; MGH keeps the one it has. That is the
lowest-risk shape possible, and it is exactly what the "additive / nothing changes
until you add a company" design gives us.

### The hosting is a VPS — so we go fully one-click, with self-onboarding

Because you run a **VPS** (your own server, not shared cPanel), two limits that
would exist on shared hosting simply do not apply:

- **No cap on the number of databases** — you can host as many client companies as
  the server has room for.
- **The app is allowed to create databases and run its own setup step**, which lets
  us make *Add a company* **truly one-click** — no manual database step at all.

So the confirmed flow on your VPS is the premium one you described:

1. **You** add a company on the console (name, owner email, plan, seats). The app
   **creates that client's MySQL database automatically** and stands up a fresh,
   isolated install inside it.
2. **The client's admin** signs in by email and is walked through a short
   **onboarding wizard** — they enter their own company details (business name,
   logo/branding, financial year, currency) and add their first team members.
   You never key in their details for them; they self-serve, exactly like Notion or
   Linear onboarding.

The only server-side prerequisite for step 1's automatic database creation is a
**database admin credential in `config.local.php`** on the VPS (a MySQL user allowed
to create databases). Your VPS already has this available; it just needs to be set
once. If you would ever prefer *not* to give the app that power, the fallback is the
same two steps done by hand (create the empty DB, then add the company) — but on a
VPS the automatic route is the right one.

> **Small build note:** the one-click auto-create of a client's database, and
> leaving the onboarding wizard on for the new client, are a small, well-scoped
> addition on top of what is already built and proven (today the console can add a
> company against a database that already exists). This is the natural next
> increment — see the closing note.

---

## 3. What actually changes on the server

For the person doing the upload, here is the full list of what is new. It is a
normal update — "upload the whole `phpapp` folder over the old one," exactly as in
`DEPLOY-CHECKLIST.md` — plus **two** one-time SaaS touches.

**Carried in by the normal update (nothing to do by hand):**

- New brains for the control plane: `lib/saas_tenants.php`, and two helper scripts
  `lib/saas_provision_cli.php` (builds a new company's database) and
  `lib/saas_sync_cli.php` (pushes a plan/seat/module change into a live company).
- The **Companies** console screen (`views/ops/saas_companies.php`) — where you add
  a company, set its plan, seats and exact modules, see a live price, suspend, or
  "log in as" it for support.
- Small, safe edits to the sign-in handler and the config loader so an **email can
  find its company**. Username sign-in is unchanged.
- A new directory table (`saas_tenants`) that the site **creates by itself** on the
  first page load after the update — it starts empty, so nothing changes yet.

**The two one-time SaaS touches (only when you choose to go multi-company):**

1. **Turn on cloud mode** — create the `tenants.php` registry file (easiest from
   **Settings → Cloud workspaces**, which writes it for you). This is the switch
   from §1. It is kept out of every future update, so it is never overwritten.
2. **Add the first company** from the **Companies** console.

**Two files must never be overwritten by an update** (same rule as today, now three):

| Keep on the server, never upload over it | Why |
|---|---|
| `config.local.php` | your real database + admin settings |
| `data.sqlite` | your data (only if you use the built-in database) |
| **`tenants.php`** | **new** — the client-company registry that makes it a SaaS site |

These are already ignored by Git, so a **cPanel "Git → Update from Remote"**
deploy can never touch them. That is the safest way to update.

---

## 4. The rollout — five stages, each with a way back

### Stage 0 — Back up and note the "known-good" point (10 min)
- Dump the live database on the VPS (`mysqldump`) **and** keep a copy of
  `config.local.php`. This is the single most important step.
- Record today's live commit so we can return to it — a plain `git` rollback on the
  VPS to that commit restores the exact previous code in seconds.
- **Backout at this stage:** nothing has changed. There is nothing to undo.

### Stage 1 — Rehearse on a copy, not the live site (half a day)
- Stand up a **staging** copy at a throwaway address (e.g. `staging.mghaiapps.com`
  or a local machine) from the `Testing` branch, using a **copy** of the live data.
- Run the full rehearsal we have already scripted: add three companies, prove they
  cannot see each other's data, prove the seat cap blocks the 4th login, bump a
  plan and push it live, suspend a company and confirm it shows "Workspace paused."
- Confirm the **health check** is green (Settings → Server check) and the automated
  test suite passes (it currently reports **6,410 checks passing, 0 failing**).
- **Backout:** throw the staging copy away. The live site was never involved.

> We have already done this rehearsal successfully on the `Testing` branch. Stage 1
> repeats it against a copy of the *real* data, which is the only new information it
> adds — that real data upgrades cleanly.

### Stage 2 — Ship the code to live, "dark" (15 min, low risk)
- Merge the approved code into the branch the VPS tracks, then `git pull` on the VPS
  (Git leaves `config.local.php` and `tenants.php` untouched — they are ignored).
- Open one page so the site upgrades its own database (this creates the empty
  `saas_tenants` directory). **Do NOT turn on cloud mode yet. Do NOT add a company.**
- **Result:** the live site looks and works **exactly** as before to every current
  user. The new code is present but idle — this is the whole point of "additive."
- **Verify:** sign in as usual (username), click through the main screens, run
  Settings → Server check. Everything normal.
- **Backout:** re-deploy the previous version from Stage 0. Because nothing was
  switched on, this is a plain rollback with no data implications.

### Stage 3 — Add ONE pilot company behind the same URL (1 hour, reversible)
- Pick a **safe first client** — ideally an internal/friendly one, or Asme
  Pharmaceutical if they are ready.
- Turn on cloud mode (Settings → Cloud workspaces) and, from the **Companies**
  console, **Add a company**: its name, owner email + password, plan (or à-la-carte
  module set), and seats. The system builds its private database automatically.
- MGH's own operations needs **no** migration: the current install *is* the control
  install and MGH's workspace at the same time (see §2). MGH keeps signing in exactly
  as today; only the new client signs in by email into its own database.
- **Verify the pilot end-to-end:** the pilot owner signs in **by email** at the same
  URL, lands in their **own** workspace, sees only their modules, invites staff up
  to the seat cap, and runs their real day-one task (for Asme: build the recruitment
  masters and run one candidate through the pipeline).
- **Watch for 2–3 days** before widening. Keep the pilot small.
- **Backout:** suspend the pilot company (one click → they see "Workspace paused"),
  or remove cloud mode entirely to fall straight back to the single-company site.
  The pilot's data sits in its own database and is untouched by the rollback.

### Stage 4 — Open it to real clients + turn on billing (ongoing)
- Once the pilot is happy, add further companies the same way, one at a time.
- Turn on **per-seat billing** for real: connect your live Razorpay keys and use the
  console's price book so each company is charged for its exact modules + seats.
- **Customer self-service is built:** each company's own admin has a **Subscription**
  screen (`/subscription`) to add modules or seats and pay online themselves, with a
  live quote; a paid purchase unlocks immediately and is held as a floor your pushes
  never revoke. The only thing that needs the live account is the real Razorpay
  charge — exercise it once here at Stage 1 with your live keys.
- **Backout:** per company — suspend or downgrade any single company without
  affecting the others.

### Stage 5 — Hand-over and day-2 routines
- Write the short internal runbook (it is §6 below) and make sure whoever runs
  hosting has it: how to add a company, how backups now work, how to change a plan.

---

## 5. If something looks wrong — the backout summary

| At this point | To undo | Effect |
|---|---|---|
| After Stage 2 (code shipped, dark) | Re-deploy the Stage 0 version | Full return to today's site. No data change. |
| After Stage 3 (pilot added) | Suspend the pilot, **or** delete `tenants.php` | Site returns to single-company behaviour instantly; pilot data preserved in its own database. |
| A single client misbehaving | Suspend **just that company** from the console | Only that client is paused ("Workspace paused"); everyone else unaffected. |
| A bad plan/seat/module change | Re-set it in the console and push again | The change is re-synced to that company's live database in seconds. |

The golden safety property: **client databases are separate**, so a problem with
one client, or a rollback of the platform, never risks another client's data or
MGH's own.

---

## 6. New day-2 routines (the short runbook)

**Adding a company** (VPS, one-click) — a single step:
- On the **Companies** console → *Add a company* → name, owner email + password,
  plan or à-la-carte modules, seats → Save. The app **creates the client's MySQL
  database automatically** and stands up a fresh, isolated install in it. The owner
  then signs in by email and is guided through the **onboarding wizard** to enter
  their company details and first team members.

**Backups now** — you back up **each client's MySQL database**, not just one:
- On the VPS, a **nightly `mysqldump --all-databases`** (or your panel's scheduled
  backup) captures every client's database **plus** MGH's own control database in
  one job. Keep those dumps off the server. This is the simplest, recommended route.

**Changing a plan, seats or modules** — Companies console → the company → adjust →
Save. The change is pushed into that company's live site automatically.

**Pausing / ending a client** — Suspend from the console. They see a polite
"Workspace paused" page and cannot sign in; reactivating restores them exactly.

**Supporting a client** — "Log in as" from the console opens their workspace so you
can see what they see, without knowing their password.

---

## 7. Risk register (what could go wrong, and the guard we built)

| Risk | Guard already in place |
|---|---|
| An update wipes a client's settings/data | `config.local.php` and `tenants.php` are excluded from every update, and every client's data lives in its **own MySQL database** — never inside the code package, so an upload cannot reach it. |
| A client sees another client's data | Each company is a **separate database**; the login routes an email only to its own registered company; unknown emails resolve to nothing. Proven in rehearsal. |
| A client turns on a module they did not buy | Modules are enforced by licence per company and hidden from staff and admin alike; the console is the only place they change. |
| More logins created than paid for | Seat cap blocks the next login with a "buy more seats" message at the limit. Proven in rehearsal. |
| Current users disrupted on go-live | Stage 2 ships the code "dark" — no behaviour change until a company is added. Username sign-in path is unchanged. |
| Database upgrade fails mid-way | The site upgrades itself in one pass on first page load; Stage 1 proves the *real* data upgrades cleanly before it ever runs on live. |
| Hard-to-reverse mistake | Every stage has a fast backout (§5); the whole SaaS mode is a single removable file. |

---

## 8. Go-live checklist (tick as you go)

**Decisions — CONFIRMED**
- [x] Decision A: **A1 — shared front door** at `operations.mghaiapps.com`
- [x] Decision B: **MySQL, one database per client**; MGH stays on its current MySQL DB, no migration
- [ ] Pilot company + owner email confirmed: `__________`

**Build (before Stage 2) — the one-click + onboarding increment — DONE**
- [x] Auto-create the client's MySQL database when a company is added (VPS) —
      `saas_mysql_provision_db()`, driven by a DB-admin credential in `config.local.php`
- [x] New company lands its owner in the onboarding wizard (self-entered company
      profile) — provisioner flags onboarding; the setup wizard fires on first login
- [x] Tests green (suite passing); self-onboarding proven end-to-end in the browser
- [ ] MySQL auto-create exercised against the real server (done at Stage 1 on the VPS)

**Stage 0 — Backup**
- [ ] Live MySQL database dumped (`mysqldump`) and copied off the server
- [ ] `config.local.php` copied safely
- [ ] Known-good commit on the live branch recorded (for a quick `git` rollback)

**Stage 1 — Rehearsal on a copy**
- [ ] Staging built from `Testing` with a copy of the live MySQL data
- [ ] One-click add → auto-created DB → client self-onboards, proven
- [ ] 3-company isolation + seat-cap + suspend rehearsal passed
- [ ] Server check green; test suite green

**Stage 2 — Ship dark to live**
- [ ] VPS pulls the approved code (`git pull` on the branch the VPS tracks)
- [ ] `config.local.php` / `tenants.php` NOT touched (Git ignores them)
- [ ] One page opened; self-upgrade ran
- [ ] Current site verified normal (username sign-in, main screens, Server check)

**Stage 3 — Pilot**
- [ ] Database-admin credential present in `config.local.php` (enables auto-create)
- [ ] Cloud mode turned on (`tenants.php` written from Settings)
- [ ] Pilot company added in one click; owner signs in by email and self-onboards
- [ ] Pilot day-one task completed; modules + seat cap correct
- [ ] Watched 2–3 days, stable

**Stage 4 — Open + billing**
- [ ] Further companies added one at a time (one click each)
- [ ] Live Razorpay keys connected; price book set; per-company billing correct

**Stage 5 — Hand-over**
- [ ] Runbook (§6) shared with whoever runs the VPS
- [ ] Nightly all-databases backup confirmed running

---

## 9. Your VPS — the three host facts, resolved

On a VPS (your own server) all three unknowns from the shared-hosting version are
settled in the good direction:

1. **Database count:** **no limit.** Host as many client companies as the server has
   room for; grow the VPS when it fills, not because of an artificial cap.
2. **Updating the live site:** **you code it and push; the VPS pulls the code.** The
   go-live route is a Git pull on the server (or a deploy hook that runs `git pull`).
   This is the safest kind of update — it never touches your protected files
   (`config.local.php`, `tenants.php`). *(Working note: all new code lands on the
   `Testing` branch. "Going live" is a deliberate, human-approved merge into the
   branch the VPS tracks, followed by the pull — never an automatic push to live.)*
3. **Running the setup step:** **allowed.** The VPS lets the app create databases and
   run its own provisioning, so *Add a company* is genuinely one-click and the client
   self-onboards (see §2). The only setup is a database-admin credential in
   `config.local.php`, which the VPS already has.

---

_Companion documents: `DEPLOY-CHECKLIST.md` (the plain file-update steps),
`DEPLOY-SOP.md` (fresh install), `tenants.sample.php` (client-registry layout),
`pending.md` item 4 (the remaining customer-facing self-checkout). All SaaS
control-plane work lives on the `Testing` branch and has not touched the live site._
