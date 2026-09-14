# Milestone 8 — Public Routes & Background Execution Enforcement

**Status:** complete · **Suite:** 7,800 passed, 0 failed · **Baseline:** 40f19cc (M7)

---

## 1. What this milestone was for, in plain language

M5 to M7 secured requests made by somebody who is signed in. M8 covers the two
kinds of execution where **nobody is signed in to ask**:

- the **public careers site**, including the application a stranger posts to it;
- the **nightly run**, which was quietly doing paid-module work for every
  workspace, whatever that workspace had bought.

The governing sentence: *there is no such thing as cron = master.* A background
process asks exactly the same question a signed-in user does, through exactly the
same engine.

---

## 2. How a background run picks a workspace — the fact everything rests on

Worth stating plainly, because the whole model depends on it and it is asserted
by test rather than assumed:

| Invocation | Which workspace | Entitlement |
|---|---|---|
| `php cron.php` (command line) | No HTTP host, so `config.php` resolves the **control install** | Never limited — the platform owner's own install |
| `https://<workspace>/cron.php?key=…` | **That workspace**, resolved from the host name | Applies to every step |

The second is the one that mattered. A workspace that had stopped paying for
People & hiring was still having its recruitment approvals ticked and its
placement-fee guarantees flipped every night. One without Sales & CRM still had
its quotations expired and its follow-up e-mails sent. Nobody was signed in, so
nothing asked.

The workspace is resolved from the **host name**, never from a request parameter.

---

## 3. The findings

### F1 · The nightly run did paid work for every module

`cron.php` performs 30-odd distinct jobs. Before M8, **every one of them ran for
every workspace**. Classified from the library each function actually lives in —
not from its name — the paid ones were:

| Module | Nightly work that ran regardless |
|---|---|
| **People & hiring** | placement-fee guarantee flips, recruitment approval ticks |
| **Sales & CRM** | contract expiry warnings, idle-contract auto-close, quotation follow-up e-mails, quotation expiry |
| **Money** | overdue-invoice chasing, the billable-event ledger, the accounts bridge drain |
| **Inspection reporting** | report approval SLA escalations, vendor re-assessment reminders, report resealing |
| **Operations** | job-lock sweeps, calibration reminders, authorisation maintenance, recurring call generation, the MIS digest, NCR/CAPA/complaint chases, site-document expiry, competence, controlled documents, cost-basis backfill |

**Closed** with a per-step gate — 28 steps in all — each naming the access module
its own library belongs to.

### F2 · The frequent advertising sync

`cron_ads.php` runs every few minutes and pulls advertising leads **into the CRM
pipeline**. That is Sales & CRM work, performed unattended. **Closed**, and the
entitlement question is asked *before* the `ads_on()` feature switch — because
entitlement decides what may be switched on at all.

### F3 · The public application submission

Securing a page is not securing the form it posts to. `careers_apply()` is the
function that **creates a candidate, uploads a CV and writes into the recruitment
pipeline**.

It is reachable today only through `careers_route()`, which M5 already gates —
verified, not assumed. M8 asks the question **inside `careers_apply()` as well**,
so the guarantee is structural rather than positional: no future route, callback
or job can reach the paid operation by reaching the function.

Scoped deliberately to the **entitlement** question only. The company's own
Careers opt-in is already enforced at the route, and an opt-in is a feature
choice, not a security boundary — see §6.

---

## 4. Why there is no single exit at the top of `cron.php`

This is the part that would have been easy to get wrong, and the instruction
called it out.

The nightly run is not one module's job. It also trims the audit trail, checks
data integrity, syncs the licence, chases licence expiry and encrypts stored
identity documents. Putting one `if (!entitled) exit;` at the top would mean **a
lapsed HR subscription stops a company's Operations reminders going out** — an
entitlement problem turned into an outage.

So each step is gated individually, through one small helper, and the core work
runs whatever the plan says. Asserted by test in both directions: every paid step
is gated, and every core step is **not**.

At the end of a run the output names what was skipped —
`Skipped — not enabled for this workspace: Sales & CRM, People & hiring` — so an
operator reading the log sees why, without anything being said about how
entitlement is decided.

---

## 5. What was inventoried and deliberately left alone

Reported because "we looked and it was already right" is evidence too.

| Path | Classification | Why |
|---|---|---|
| `lib/saas_provision_cli.php` | **CORE platform** | Provisions a new workspace's database. Gating provisioning on entitlement would be circular — this is the mechanism that *creates* the workspace |
| `lib/saas_sync_cli.php` | **CORE platform** | Writes a company's entitlement **into** its own store. It is the mechanism that *sets* entitlement |
| `get-started` (public signup) | **CORE platform** | Creating a SaaS account is not a paid module. Off by default, and it creates a PENDING application for approval, not a live workspace. §17 |
| `buy`, `buy-verify` | **PUBLIC** | A self-hosted customer paying on the licence server, identified by install id |
| `api.php` | **PUBLIC / SYSTEM** | The licence server. Classified in M6 |
| `verify`, `verify-pdf` | **PUBLIC** | A client checking a report they already hold, by its printed code |
| `login`, `logout`, `forgot`, `reset` | **CORE** | Authentication must never be module-gated |
| `lib/webhookq.php` | **dormant** | An outbound webhook queue with **no callers** — nothing enqueues to it and nothing runs it. Recorded as a limitation, not gated |
| `/pro`, `/join`, `/connect`, `/p/<token>` | **Marketplace / Connect** | The M9 boundary. Documented, not implemented |
| `refresh.php`, `diagnose.php`, `deploy-check.php`, `phase1-inventory.php`, `router.php`, `manifest.php` | **SYSTEM / PUBLIC** | Operator tools and static assets; no module business operations |

---

## 6. Careers stays opt-in — explicitly

§5 warns against turning the Careers switch into a Recruitment dependency. It is
not one, and this is asserted:

| Situation | Result |
|---|---|
| HR entitled + Careers **on** | the public page serves normally |
| HR entitled + Careers **off** | the page does not serve — **and HR stays ENTITLED**, the rest of Recruitment untouched |
| HR **not** entitled + Careers flag left on | refused |
| HR not entitled + direct application POST | refused, and **nothing is written** |

---

## 7. What was NOT built

- **No new entitlement system.** Every check goes through `licence_module_live()`
  → `licence_blocks()` → the existing registry. The M3 precedence is unchanged.
- **No duplicate cron system**, no duplicate public recruitment system.
- **No schema change.** No table, column or index.
- **No business engine touched** — Operations, Quality, Reporting, Money,
  Dashboard, Recruitment, Marketplace, Connect, Workforce, identity, approval,
  pipeline, audit and billing are all unmodified.
- **No M9 work.** Marketplace and Connect are untouched.

---

## 8. Files changed

| File | Change |
|---|---|
| `cron.php` | per-step entitlement gate (28 paid steps); core steps untouched; a skip summary for the operator |
| `cron_ads.php` | the advertising lead sync gated on Sales & CRM, ahead of the feature switch |
| `lib/careers.php` | `careers_apply()` asks the HR question itself |
| `tests/test_m8_public_background_entitlement.php` | new — 125 assertions |
| `deploy-check.php` | checksums regenerated |

**Three source files.** No helper was created; `licence_module_live()` was reused.
