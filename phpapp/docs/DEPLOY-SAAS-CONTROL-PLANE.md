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

> **The one practical routine this adds:** for each new client, someone with cPanel
> access creates an empty MySQL database + user first (2 minutes), then you add the
> company on the console pointing at it. The layout to copy is in
> `tenants.sample.php`. (Whether that cPanel step can be fully automated depends on
> your host — see §9.)

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
- Export the live database (cPanel → phpMyAdmin → Export) **and** keep a copy of
  `config.local.php`. This is the single most important step.
- Record today's live version so we can return to it: it is commit on the branch
  the live site tracks. (If the live site does **not** use Git yet, we take a full
  file backup of the `phpapp` folder as the restore point.)
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
- Update the live site the normal way (§3): pull/upload the whole `phpapp` folder.
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
  *(Note: the customer-facing self-checkout — where a client pays and upgrades
  themselves — is the one remaining piece to build; today you set and charge each
  configuration from the console. See `pending.md` item 4.)*
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

**Adding a company** (MySQL, our confirmed model) — two steps:
1. In **cPanel → MySQL Databases**, create an empty database + a user with all
   privileges on it (about 2 minutes). Note the database name, user and password.
2. On the **Companies** console → *Add a company* → name, owner email + password,
   plan or à-la-carte modules, seats, and the database details from step 1 → Save.
   The system fills that empty database with a fresh, isolated install and the
   owner can sign in by email immediately. The layout is in `tenants.sample.php`.

**Backups now** — you back up **each client's MySQL database**, not just one:
- Export each company's database (phpMyAdmin), **plus** MGH's own database (which
  doubles as the control database holding the company directory).
- A **nightly cPanel full-account backup covers all of them in one shot** — the
  simplest option, and the recommended one.

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

**Stage 0 — Backup**
- [ ] Live MySQL database exported (phpMyAdmin) and copied off the server
- [ ] `config.local.php` copied safely
- [ ] Known-good version / full folder backup recorded

**Stage 1 — Rehearsal on a copy**
- [ ] Staging built from `Testing` with a copy of the live MySQL data
- [ ] 3-company isolation + seat-cap + suspend rehearsal passed
- [ ] Server check green; test suite green (6,410 passing)
- [ ] Confirmed on this host: adding a company auto-builds the client DB (see §9)

**Stage 2 — Ship dark to live**
- [ ] Whole `phpapp` folder updated (cPanel Git → Update from Remote preferred)
- [ ] `config.local.php` / `tenants.php` NOT overwritten
- [ ] One page opened; self-upgrade ran
- [ ] Current site verified normal (username sign-in, main screens, Server check)

**Stage 3 — Pilot**
- [ ] Empty MySQL database + user created in cPanel for the pilot
- [ ] Cloud mode turned on (`tenants.php` written from Settings)
- [ ] Pilot company added pointing at that database; owner signs in by email
- [ ] Pilot day-one task completed; modules + seat cap correct
- [ ] Watched 2–3 days, stable

**Stage 4 — Open + billing**
- [ ] Further companies added one at a time (empty DB first, then console)
- [ ] Live Razorpay keys connected; price book set; per-company billing correct

**Stage 5 — Hand-over**
- [ ] Runbook (§6) shared with whoever runs hosting
- [ ] Per-client MySQL backup routine confirmed running (nightly full-account backup)

---

## 9. Three small host facts worth confirming (I can also self-check these)

None of these blocks us — I will verify each during the Stage 1 rehearsal — but if
you can get quick answers from whoever manages the cPanel, it removes all guesswork:

1. **Does the hosting plan allow enough MySQL databases?** One per client. Some
   shared plans cap the number (e.g. 25). If yours is capped, we simply know the
   client ceiling in advance and can request an upgrade before we hit it.
2. **Is the live site updated via cPanel "Git Version Control", or by uploading a
   ZIP?** Git → *Update from Remote* is the safest (it can never touch your three
   "keep" files). If it is ZIP uploads today, I will note the extra care needed.
3. **Does the host allow the app to run a small background command (PHP `exec`/shell
   or SSH)?** This is what lets *Add a company* build the client's database in one
   click. **If the host blocks it, nothing is lost** — the client's database is
   filled by opening its address once and finishing the 60-second first-run wizard
   instead. I will confirm which path applies during rehearsal and wire it so *Add a
   company* "just works" either way.

---

_Companion documents: `DEPLOY-CHECKLIST.md` (the plain file-update steps),
`DEPLOY-SOP.md` (fresh install), `tenants.sample.php` (client-registry layout),
`pending.md` item 4 (the remaining customer-facing self-checkout). All SaaS
control-plane work lives on the `Testing` branch and has not touched the live site._
