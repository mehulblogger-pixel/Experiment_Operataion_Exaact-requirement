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

## 2. Two decisions only you can make (before we start)

Everything else I can drive. These two are business calls.

### Decision A — What is `operations.mghaiapps.com` *after* this?

| Option | What it means | My recommendation |
|---|---|---|
| **A1. It becomes the shared front door** and your own MGH operations data becomes the first company behind it ("workspace zero"). | One site, one login page, serves MGH **and** every client. Cleanest long-term. | ✅ **Recommended.** It mirrors how Books works and avoids running two sites. |
| **A2. Keep `operations.mghaiapps.com` exactly as it is** and stand up a **separate** address (e.g. `app.mghaiapps.com`) as the SaaS front door. | Your live operations site is never touched at all; clients live on a brand-new URL. | Safest of all if you are nervous — but it means two installs to maintain. Good as a *temporary* first step, then fold into A1 later. |

I will assume **A1** in the steps below, and call out where **A2** differs.

### Decision B — Where does each client's data live?

Each client company gets its **own private database** (that is what keeps them
isolated). There are two kinds and we can mix them:

| Kind | Setup effort per client | Best for |
|---|---|---|
| **Built-in file database** (one file per client) | **None** — the system creates it automatically when you add the company. | Small clients, the pilot, quick trials. |
| **MySQL database** (a proper server database per client) | You create an empty database + user once in cPanel, then add the company. | Busy clients with many users, and MGH's own workspace. |

**Recommendation:** use the **built-in file database for the pilot and small
clients** (zero friction), and **MySQL for MGH's own workspace and any large
client.** The system supports both at the same time.

> Once you tell me A and B, I will fill the exact hostnames and database names into
> the checklist in §8.

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
- Under **Decision A1**, also register MGH's own operations as a company at this
  point so the front door serves it too. Under **A2**, skip this — MGH stays on its
  untouched site.
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

**Adding a company** — Companies console → *Add a company* → name, owner email +
password, plan or à-la-carte modules, seats → Save. The system builds the private
database and the owner can sign in by email immediately. (For a **MySQL** client,
first create the empty database + user in cPanel, then add the company pointing at
it — the sample layout is in `tenants.sample.php`.)

**Backups now** — you back up **each client's database**, not just one:
- Built-in-database clients: back up each company's data file.
- MySQL clients: export each company's database.
- Keep backing up the **control database** too (it holds the company directory).
A nightly cPanel backup of the whole account covers all of them in one shot — the
simplest option, and recommended.

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
| An update wipes a client's settings/data | `config.local.php`, `data.sqlite` and `tenants.php` are excluded from every update; per-client databases are never in the code package. |
| A client sees another client's data | Each company is a **separate database**; the login routes an email only to its own registered company; unknown emails resolve to nothing. Proven in rehearsal. |
| A client turns on a module they did not buy | Modules are enforced by licence per company and hidden from staff and admin alike; the console is the only place they change. |
| More logins created than paid for | Seat cap blocks the next login with a "buy more seats" message at the limit. Proven in rehearsal. |
| Current users disrupted on go-live | Stage 2 ships the code "dark" — no behaviour change until a company is added. Username sign-in path is unchanged. |
| Database upgrade fails mid-way | The site upgrades itself in one pass on first page load; Stage 1 proves the *real* data upgrades cleanly before it ever runs on live. |
| Hard-to-reverse mistake | Every stage has a fast backout (§5); the whole SaaS mode is a single removable file. |

---

## 8. Go-live checklist (tick as you go)

Fill the blanks once you confirm Decisions A and B.

**Decisions**
- [ ] Decision A chosen: A1 (shared front door) / A2 (separate URL) → `__________`
- [ ] Decision B chosen for the pilot: built-in file DB / MySQL → `__________`
- [ ] Front-door address confirmed: `__________`
- [ ] Pilot company + owner email confirmed: `__________`

**Stage 0 — Backup**
- [ ] Live database exported and copied off the server
- [ ] `config.local.php` copied safely
- [ ] Known-good version / full folder backup recorded

**Stage 1 — Rehearsal on a copy**
- [ ] Staging built from `Testing` with a copy of live data
- [ ] 3-company isolation + seat-cap + suspend rehearsal passed
- [ ] Server check green; test suite green (6,410 passing)

**Stage 2 — Ship dark to live**
- [ ] Whole `phpapp` folder updated (Git pull preferred)
- [ ] `config.local.php` / `data.sqlite` / `tenants.php` NOT overwritten
- [ ] One page opened; self-upgrade ran
- [ ] Current site verified normal (username sign-in, main screens, Server check)

**Stage 3 — Pilot**
- [ ] Cloud mode turned on (`tenants.php` written from Settings)
- [ ] Pilot company added; owner signs in by email into own workspace
- [ ] (A1) MGH operations registered as its own company
- [ ] Pilot day-one task completed; modules + seat cap correct
- [ ] Watched 2–3 days, stable

**Stage 4 — Open + billing**
- [ ] Further companies added one at a time
- [ ] Live Razorpay keys connected; price book set; per-company billing correct

**Stage 5 — Hand-over**
- [ ] Runbook (§6) shared with whoever runs hosting
- [ ] Per-client backup routine confirmed running

---

_Companion documents: `DEPLOY-CHECKLIST.md` (the plain file-update steps),
`DEPLOY-SOP.md` (fresh install), `tenants.sample.php` (client-registry layout),
`pending.md` item 4 (the remaining customer-facing self-checkout). All SaaS
control-plane work lives on the `Testing` branch and has not touched the live site._
