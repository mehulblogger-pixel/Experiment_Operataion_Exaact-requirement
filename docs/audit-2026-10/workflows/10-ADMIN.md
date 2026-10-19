# 10 — ADMIN

**Menu:** left rail → **Admin** → `/admin`
**Screens:** 60 — the largest area · **24 tiles in eight sections**
**Modules:** `users`, `settings`, `masters`, `admin` — and **many screens here
have no module gate at all**
**Who can open it** (measured): **Master Admin opens everything. Branch Manager
can open `/users`. Business Director is redirected (302) from `/users`,
`/access` and `/settings`. Inspector and Finance get 403 on `/admin`.**

Per the matrix: Users = `E(global)` for MA/AD, `E(own office)` for BM/BAM,
**— for BD and everyone else**; Settings = `E` for MA/AD only.


---

## Status at 19 Oct 2026 — what changed since this document was first written

*First issued 18 Oct 2026. The changes below are in the code on branch `claude/testing-branch-setup-0gqe8n`; they reach the live site only when it is updated (`../14-LIVE-CHECKS-GUIDE.md`, Part 2). Statements in the body that they made untrue are corrected where they stand and marked **(updated 19 Oct)**.*

| Change | What it means in this area | Ref | How it was verified |
|---|---|---|---|
| Owner's rules reach a saved access grid | A rule the owner gives is written once into any role grid or per-user set an administrator already saved, then never re-applied, so a deliberate untick stands | Q-B1, Q-G | Test |
| **Roles & access: two new grants** | Coordinator level and above: *Nonconformities → Add* (+ View). Finance: *Vendors → View* | Q-B1, Q-G | Role test + HTTP |
| Marketplace plans screen warns | When payment keys are missing, it names the priced plans and packs nobody can buy, with a link to the keys | GAP-037 | Test |
| Self-service purchase is bound to the order | `/buy-verify` takes seats, period and amount from the recorded order and applies a payment exactly once | GAP-030, GAP-031 (P1) | Execution, Razorpay stubbed |
| Licence server hardened | Replayed payments, unbounded terms and unlimited password guesses fixed | GAP-025–027 | Execution |
| **Test-copy switch** | `'test_copy' => true` in a copy's `config.local.php` stops every e-mail and puts a TEST COPY banner on every page. Never set on live | Live guide, Part 7 | Test |
| Confirming a release landed | The footer version (2026.10.1) never changes; `/deploy-check.php` is the check | — | `../14-LIVE-CHECKS-GUIDE.md` Part 3 |

---

## 1. What this area is for, and the one thing to notice first

This is where the product is configured into *this* company's business — people
and permissions, terminology, services offered, approval chains, numbering,
connections to other systems, and the platform's own licensing.

**The thing to notice: a Business Director — the most senior business role — is
redirected away from Users, Roles and Settings, while a Branch Manager can open
the user register.**

| Screen | Business Director | Branch Manager |
|---|---|---|
| `/users` | **302** | **opens** |
| `/access` | **302** | 403 |
| `/settings` | **302** | 403 |

**That is the documented intent, not a defect** — the matrix gives Users to
`E(own office)` for BM/BAM and nothing to BD, on the reasoning that a director
sets strategy while a branch manager runs their branch's people. It is recorded
as **GAP-005** because the *shapes differ* (302 for one, 403 for another) and
because a senior role being redirected with a flash message rather than a clear
refusal reads as a fault. *The permission rule is right; the refusal is
inconsistent.*

**And one structural observation:** of the 60 screens here, a large share carry
**no module gate**, including `/backup`, `/companies`, `/super-admin`,
`/feature-gates`, `/financial-control`, `/marketplace-plans` and
`/marketplace-escrow`. They refuse a least-privileged role (tested in
GAP-008), but the protection is each handler's own rather than systematic.

---

## 2. The eight sections

### Section: **Set up your workspace**

#### `/workspace/setup` — Set up your workspace

| | |
|---|---|
| **No module gate** · no own fields |
| Controls | **Fix** · **Do this** · **+ Add a form field** · **✨ Build forms with AI** · **+ Add a dropdown value** · **+ Add a user** · **+ Add a branch** · **Configure features** · **Change wording** |
| Tile | *"Configure your whole workspace in one place — features, forms, dropdowns, people, wording."* |

**The best-designed screen in Admin.** Instead of sending a new customer to
hunt through 24 tiles, it presents what needs doing with **Fix** and **Do this**
buttons against each item — a guided setup that names the next action rather
than the destination.

**"Fix" and "Do this" as button labels** is the Zero Training UI standard
applied to configuration, which is the hardest place to apply it.

---

### Section: **People**

#### `/users` — Organisation & people

| | |
|---|---|
| Module `users` · no own filter fields |
| Controls | **Print** · **Deactivate** · **Reactivate** · **← Back** · **Bulk (Excel)** · **+ Add a person** · **Edit** |

**Deactivate rather than delete** — the correct choice. A person who has left
still authored reports, approved quotes and raised nonconformities; their
records must keep pointing at a real person. **Reactivate** handles the rejoiner.

#### `/hierarchy` — Organisation & people *(the same heading)*

| | |
|---|---|
| Module `users` · 7 `manager_id` fields |
| Controls | **Print** · **Arrange these 5 automatically** · **Expand all** · **Collapse all** · **Fit to screen** · **100%** · **← Home** · **Open offices** · **Open the master** · **Add a person** · **Open People** |
| Columns | **Person · Would report to** |

The reporting chart, drawn. **"Arrange these 5 automatically"** carries a live
count of the unplaced people, and **"Would report to"** is a proposal column —
so the system suggests a hierarchy and you confirm it, rather than demanding you
build it from nothing.

**FINDING (A-F1, P2) — `/users` and `/hierarchy` both render "Organisation &
people"** (GAP-009). They are genuinely different screens — a register and a
chart — and each links to the other (**Open People**, **Add a person**). The
fix is two distinct headings, not a merge.

#### `/user-new` — Add a person

| | |
|---|---|
| Module `users` · **258 fields, 1 required** |
| Controls | (the form) |

**The second-largest screen in the application**, because the per-user
permission grid is embedded in it — the same `perms[mod.*.*]` checkboxes as
`/access`, which is why the count is 258 and why eight 14-column tables appear
on one page.

**Behaviour confirmed:** an empty submit is **refused**, with
> *"A login must belong to a team member. Pick the person from the team, or
> enter their name (first / last) so they are added."*

A good refusal: it states the rule *and* offers both ways to satisfy it.

#### `/access` — Roles & permissions

| | |
|---|---|
| Module `settings` · **200 fields** (`perms[mod.leads.view]`, `perms[mod.leads.add]`, … one per right per module) |
| Controls | **✨ Apply recommended set** · **Reset to default** · **Save access for Coordinator** · **Undo** · **Cancel changes** |
| Columns | **Module · View · Add · Edit · Archive · Delete · Approve · Apply to every module in Sales** |

The role permission grid: every module × every verb. Four things are right about
it:

- **The save button names the role** — *"Save access for Coordinator"* — so you
  cannot save Coordinator's rights while thinking you are editing Finance's.
- **Undo** and **Cancel changes** exist. Permission editing is high-stakes and
  reversible here.
- **"Apply to every module in Sales"** — a whole-area toggle, because ticking
  five verbs across six modules by hand invites mistakes.
- **"✨ Apply recommended set"** — a sane starting point per role.

**0 fields marked required is correct** — it is a grid of checkboxes.

This is the screen whose column header was checked against UAT 1.5.3 in an
earlier milestone, and whose module list was missing Recruitment until that was
fixed.

#### `/role-workspaces` — Role workspaces

| | |
|---|---|
| **No module gate** · **153 fields** · Control: **Save workspace** |
| Tile | *"Per-role landing page & quick-access launchpad."* |

Where each role lands on signing in, and the tiles on their launchpad. **This is
how one product serves an inspector and an accountant**: same application,
different first screen.

#### `/sso` — Single sign-on

**No module gate** · **no fields and no controls measured.**

**FINDING (A-F2, P2) — the single sign-on screen is empty.** The tile says
*"Identity provider settings"*, and the screen rendered with nothing on it. It
may be gated behind a licence or plan, or it may be unbuilt. The tables
`sso_attempts` and `saas_logins` exist. *Needs one check: is SSO available on
this plan, or is the tile promising something that does not exist?*

---

### Section: **Approvals**

#### `/approvals-setup` — Approvals

**No module gate**, no own fields.
Tile: *"Who signs off what — recruitment, quotations, reports and delegation, in
one place."*

**This tile is the result of a change the owner asked for.** Four approval
configuration screens were scattered across three areas; they were brought
together here, and **Approvals was moved out of the "Report configuration"
section** into its own — because recruitment approvals filed under reporting
made no sense.

Behind it: `/approval-rules` (report approvals, with `sla_hours`),
`/quote-approval-rules` (value-banded quote approvals, with ₹ bands),
`/recruit-approvals` (the three policy switches), `/approval-delegations`
(who acts for whom, with dates), `/approver-map` (per-inspector approver with
temporary cover).

**Confirmed behaviour worth recording:** an **inspector** who opens
`/approvals-setup` sees a read-only explainer headed **"Nothing here is yours to
change"**, with the editable half simply absent — 25 KB against an
administrator's 51 KB. That is better refusal behaviour than most systems
manage, and it was one of the GAP-008 findings that came back clean.

---

### Section: **Configuration**

#### `/settings` — System settings

| | |
|---|---|
| Module `settings` · **183 fields** |
| Key fields | `mod_on[operations]`, `mod_on[admin]`, `mod_on[sales]`, `mod_on[reporting]`, `mod_on[money]`, `mod_on[hr]`, `mod_on[connect]`, `app_name`, `logo`, `c_primary`, `c_accent`, `c_bg`, `c_surface` |
| Controls | **Save modules** · theme presets: **Corporate Blue · Corporate Orange · Forest Green · Royal Purple · Teal · Crimson · Midnight (dark)** · **🔒 Require it for the money roles** |
| Columns | **Document · Prefix · Separator · Digits · Financial year · Start from · Example** |

Three distinct jobs on one screen:

1. **The module switchboard** — `mod_on[*]` turns whole areas off for this
   company. This is the switch that makes Quality's tiles vanish, and it pairs
   with Marketplace's capability settings.
2. **Branding** — name, logo, seven theme presets including a dark one.
3. **Document numbering** — per document type: prefix, separator, digit width,
   financial-year handling, start number, **and a live Example**. *Showing the
   example is what stops a numbering mistake that would otherwise be discovered
   on an invoice.*

**🔒 Require it for the money roles** — two-factor authentication required for
the roles that touch money. Correct targeting: mandating it for everyone
including field inspectors on patchy mobile data would be counter-productive.

#### `/terminology` — Terminology

| | |
|---|---|
| Module `settings` · **64 fields** |
| Controls | **Use this wording** · **Save terminology** · **Reset to standard wording** · **Back to settings** |
| Tile | *"Rename what things are called on every screen — e.g. 'Requirement', 'Candidate', 'Client'."* |

**Rename the business vocabulary across the whole product.** This is why
`/profitability` is headed "Contract number register" (the label comes from
`T_REG('boss')`) and why `T_NEW('report')` and `Tlp('call')` appear throughout
the code instead of hard-coded words.

**"Reset to standard wording"** is the escape hatch, and **"Use this wording"**
suggests per-term presets.

**The tension worth naming:** this screen is why the glossary work
(`../05-BUSINESS-GLOSSARY.md`) matters and why it is hard. **1,441 distinct
field labels** and **456 distinct button wordings** were measured across 256
screens; some of that variation is this screen doing its job, and some is
inconsistency in hard-coded labels. Telling them apart is what the glossary
has to do.

#### `/company-profile` — Your company

**No module gate** · **38 fields** · legal name, logo, details. The identity
that appears on every issued document.

#### `/ai-settings` — AI providers & models

| | |
|---|---|
| Module `settings` · **42 fields** |
| Controls | **Save pricing & keys** · **Apply the key** · **Save provider key** |
| Tile | *"Connect and tune the assistant used by the form and document builders."* |

**Scoped precisely, and worth stating because it is easy to misread:** the AI is
used by the **form and document builders** — `/ai-forms` suggests fields from a
described process, and the Word-upload template builder reads a document. It is
**not** in the inspection workflow. The writing assistant inspectors use is
local string matching with no external call
(`06-REPORTING.md` §2.13).

So a company that declines AI loses two configuration conveniences and nothing
operational. Related: `ai-topup-order` / `ai-topup-verify` routes exist, so
usage is metered.

#### `/ai-forms` — Build forms from your process

**No module gate** · no own fields · controls **Open AI settings** ·
**Back to dashboard**.
*"Describe your recruitment process and let AI suggest the extra fields and
dropdown lists."*

#### `/service-scope` — Service scope

| | |
|---|---|
| Module `settings` · **22 fields, 2 required** |
| Controls | **Save services offered** · **Load** · **Add dependency** |

Which services this company offers and where. **"Add dependency"** is the
notable one — `service_dependencies` is a table, so one service can require
another. For an inspection body that is real: witnessing a hydrotest requires
the pressure-testing capability.

#### `/service-formats` — Report formats by service line

Module `settings` · 14 fields · *"The report format each service allocates."*
The join from Admin into Reporting: picking a service on a work order
determines which report format the job gets.

#### `/backup` — Backup & restore

| | |
|---|---|
| **No module gate** · **1 field, 1 required** |
| Controls | **💾 Back up now** · **↺ Restore** · **↺ Upload & restore** · **⬇️ Download** |
| Tile | *"Download a copy of this workspace, or restore one."* |

**That a customer can take their own backup and download it is a genuine
strength** — no dependence on the vendor to get their data out.

**FINDING (A-F3, P1 — the most consequential finding in this area).**
**Restore** and **Upload & restore** replace the workspace's data, and this
screen has **no module gate**; its protection is whatever the handler applies.
**Neither control was fired** — destructive actions were deliberately not
tested — so three things are unknown:

1. **Who may press Restore?** Nothing in `docs/02-permission-matrix.md` covers
   backup or restore.
2. **Does `Upload & restore` validate the file?** An uploaded archive is
   untrusted input that becomes the database.
3. **Is a restore logged**, and is there a confirmation?

**This should be the first thing checked in this area.** A restore is the single
most destructive act in the product — it can discard every invoice, report and
nonconformity since the backup — and it sits behind one button on an ungated
screen.

#### `/custom-fields`, `/cforms`, `/lookups`, `/feature-gates`

| Screen | Gate | Fields | What |
|---|---|---|---|
| `/custom-fields` | none | 6, 2 required | Extra fields per entity |
| `/cforms` | none | 4, 1 required | Custom forms |
| `/lookups` | none | 26, 1 required | Masters (the second Masters screen — see A-F4) |
| `/feature-gates` | none | 36 | Feature switches |

**FINDING (A-F4, P3) — three screens for the dropdown lists.** `/masters`
(module `masters`), `/lookups` (no gate, 26 fields) and the Directory's
`/masters` tile all concern the same `lookup_types` / `lookup_values` tables
(148 and 1,280 rows). Which is the front door is not obvious.

---

### Section: **Masters**

`/masters` — *"The lists behind every dropdown."* Module `masters`, governed by
`master.manage` (MA/AD/BD/SBU/BM/BAM/OM — **not** Coordinator). Also
`/work-norms` (weekly days and hours per designation per office — the
denominator for utilisation) and `/industry` / `/industry-apply` (the twelve
industry pipeline templates, gated by `settings`, which is why a salesperson
configuring a funnel can be refused — `01-SALES.md` S-F4).

---

### Section: **Report configuration**

`/templates` — the report template library (13 fields, 1 required) — and
`/audit-log` — the audit trail (who changed what, when, with **the role held at
the time** and the IP, filterable by **high-risk only**, exportable to CSV, and
**with no delete control**: the `Clear` button resets the filter, as established
in `06-REPORTING.md` §2.12).

Also reached: `/irn-rules`, `/approver-map`, `/sla-targets`.

---

### Section: **Connections**

| Screen | Gate | Fields | Controls |
|---|---|---|---|
| `/books-bridge` — **📗 MGH Books connection** | none | 5 | **Save connection** |
| `/adspro` — Ads Pro connection | `leads` | 4 | **Save** · **Test the connection** · **Bring leads across** · … |
| `/licence` — Licence | none | 2 | **Apply the key** · **Save provider key** · **Licence console →** |
| `/integrations` — Integration health | `admin` | 0 | — |
| `/notifications` — Notification log | `admin` | 2 | — |

**`/integrations` — "Integration health" — is the right idea and may be the
answer to a gap found elsewhere.** `07-MONEY.md` Y-F1 records that
`books_outbox` has no screen, so a failed push to MGH Books would be invisible,
whereas Sales shows its Ads Pro outbox with a **Failed** tab. If
`/integrations` surfaces outbox health for every connection, that gap may
already be closed. **It rendered with no fields and no controls in the test
environment** (nothing is connected there), so this could not be established.
*One check with Books connected would settle it.*

`/books-bridge`, `/books-bridge-drain` and `/books-bridge-save` are the bridge's
own routes; `books_dryrun()` lets documents be pushed without committing — use
it before going live.

---

### Section: **Super admin** — the platform, not the company

These are the screens that run **EXAACT as a product** rather than this
customer's business.

#### `/super-admin` — Control Panel

**No module gate** · controls **Turn OFF** · **＋ Load demo data** ·
**Remove demo data**.
*"Licence, seats, modules, subscription, tenants and system tools in one place."*

**Load / Remove demo data** is the right pair for a product that must demo well
and then start clean.

#### `/companies` — Companies

| | |
|---|---|
| **No module gate** · **26 fields, 2 required** · Controls: **Create company** · **Save prices** |
| Tile | *"Every company on the platform — plan, seats, modules, suspend and **log in as**. (Super-admin only)"* |

**"Log in as" is impersonation**, and it deserves to be called out plainly.

Support staff being able to enter a customer's workspace as a user is a normal
and useful capability — and it is also the most powerful thing in the product.
**Nothing in `docs/02-permission-matrix.md` mentions it.** The questions that
matter:

- Is an impersonation **logged** — and visible to the customer?
- Can the impersonator **act**, or only look?
- Does the audit trail record **the real person** or the impersonated one? (The
  audit trail does record **the role held at the time**, which is a good sign.)

**FINDING (A-F5, P1) — impersonation exists and is undocumented.** Not
necessarily wrong; definitely unspecified. For a customer under accreditation,
"who could have changed this record" must include this path.

#### `/product-package` — Product package

| | |
|---|---|
| **No module gate** · Controls: **Set up now** · **Apply EXAACT TPIA** · **Apply EXAACT Technical Staffing** · **Apply EXAACT Recruitment** · **Apply EXAACT Recruitment — In-house HR** · **In use** · **← Control panel** |

**Four product packs in one codebase**, each switching on a different bundle:

| Pack | The business it serves |
|---|---|
| **EXAACT TPIA** | Third-party inspection agency — the full inspection business |
| **EXAACT Technical Staffing** | Supplying technical manpower — deputation, site operations |
| **EXAACT Recruitment** | Recruitment as a service, for clients |
| **EXAACT Recruitment — In-house HR** | Hiring your own staff |

**This explains the four-ways-to-match-people-to-work finding** (`04-RECRUITMENT.md`
R-F4): Recruitment, Deputation, Marketplace and `mgh-hire` are not four
accidents — they are the capabilities of four products sharing one platform.
**That reframes open question Q-M3 completely:** the question is not "which
duplicate should we delete" but "which packs are we selling, and does each have
a coherent story". *This single screen is the most useful context in the
audit for that decision.*

#### Billing and licensing

| Screen | Gate | Fields | What |
|---|---|---|---|
| `/billing` — **Users & billing** | none | 8 | seats and billing |
| `/pricing-usage` — Plans, pricing & usage | none | 18 | plans and metered usage |
| `/tenants` — Cloud workspaces | none | 1, 1 required | the tenant list |
| `/issue-licence` — Licence console | none | 0 | issuing licences |
| `/marketplace-plans` | none | **33, 5 required** | marketplace plans and limits |
| `/marketplace-escrow` | none | 2 | **marketplace escrow** |
| `/compliance-rules` — Compliance rules & fees | none | **16, 3 required** | rules and fees |
| `/financial-control` | none | 2 | financial control |
| `/preflight` — Server check | `settings` | 0 | environment check |
| `/system-status` — System status | `admin` | 0 | system health |

**`/marketplace-escrow` sits in Admin, not Marketplace** — which is consistent
with M-F6: the marketplace's money is administered separately from the
inspection business's money, and from here rather than from its own area.

---

## 3. Data flow — Admin configures everything and is configured by nothing

### Arrives from

| From | What | Grade |
|---|---|---|
| **The platform owner** | Licence keys, plan, seats, product pack, tenant records | **READ** |
| **A spreadsheet** | **Bulk (Excel)** user import | **CONFIRMED** (control exists) |
| **An identity provider** | SSO — **but the screen rendered empty** (A-F2) | **UNCONFIRMED** |
| **MGH Books / Ads Pro** | Connection state and health | **READ** |

### Leaves for — every area

| To | What | Grade |
|---|---|---|
| **Every area** | `mod_on[*]` — whether the area exists for this company at all | **CONFIRMED** |
| **Every screen** | **Terminology** — what things are called | **CONFIRMED** |
| **Every screen** | Branding: name, logo, theme | **CONFIRMED** |
| **Every role** | Permissions (`/access`), the landing page and launchpad (`/role-workspaces`) | **CONFIRMED** |
| **Every dropdown** | `lookup_values`, 1,280 rows | **CONFIRMED** |
| **Every document** | Numbering: prefix, separator, digits, financial year, start number, with a live **Example** | **CONFIRMED** |
| **Sales** | Industry pipeline templates; quote approval bands | **CONFIRMED** |
| **Operations** | SLA targets (the work order's Target column); working norms (the man-day denominator) | **CONFIRMED** |
| **Reporting** | IRN numbering; report formats per service; approval rules and approver map; templates | **CONFIRMED** |
| **Money** | Numbering series per office; Tally ledger names; expense heads and apportionment bases | **CONFIRMED** |
| **Quality** | Retention periods; SLA targets; competence enforcement | **READ** |
| **Recruitment** | Positions, departments, the org chart; approval policy switches and delegation | **CONFIRMED** |
| **Marketplace** | `connect_enabled()`; plans and limits; escrow | **CONFIRMED** |

**Admin is the root of the dependency tree.** Nothing else configures it, and it
configures everything — which is why a change here has the widest blast radius
in the product, and why the two P1 findings in this document (restore, and
impersonation) are about *unspecified authority over everything* rather than
about any one screen.

---

## 4. Scenarios

### S-1 — Setting up a new customer from nothing

1. **Super admin → Companies → Create company.** Plan, seats, modules.
2. **Super admin → Product package → Apply EXAACT TPIA** (or Technical
   Staffing, Recruitment, or Recruitment — In-house HR). This switches on the
   right bundle in one click.
3. **Admin → Set up your workspace** (`/workspace/setup`) — work the list,
   pressing **Fix** and **Do this**. It will take you through features, form
   fields, dropdown values, people, branches and wording.
4. **Your company** (`/company-profile`) — legal name, logo, details, for every
   issued document.
5. **System settings** (`/settings`) — turn areas on or off with `mod_on[*]`;
   pick a theme; and **set the document numbering**, checking the live
   **Example** for each document type before saving.
6. **Terminology** — rename the vocabulary to the customer's words
   ("Requirement" or "Indent"? "Work order" or "Call"?). **Reset to standard
   wording** if it goes wrong.
7. **Service scope** — which services are offered and where, with
   **Add dependency** where one service needs another. Then **Report formats by
   service line**.
8. **Masters** — the dropdown lists; **Working norms** — weekly days and hours
   per designation per office, *before anyone computes utilisation*.
9. **Organisation & people** → **+ Add a person**, or **Bulk (Excel)**. Then
   **Organisation & people** (the chart) → **Arrange these 5 automatically** and
   confirm the **Would report to** proposals.
10. **Roles & permissions** → per role, **✨ Apply recommended set**, adjust,
    then **Save access for \<role\>**. **Undo** is there if needed.
11. **Role workspaces** — where each role lands and what is on their launchpad.
12. **Approvals** (`/approvals-setup`) — quote bands, report rules with SLA
    hours, the three recruitment policy switches, delegation and the approver
    map.
13. **Connections** — **📗 MGH Books** (use `books_dryrun()` first), Ads Pro,
    the licence key.
14. **Backup & restore → 💾 Back up now**, and **⬇️ Download** it, before
    anyone starts entering real work.
15. **Super admin → Remove demo data** once the real data is in.

### S-2 — Giving a new coordinator the right access

1. **Admin → Organisation & people → + Add a person.** 258 fields, because the
   permission grid is on the page. Submitting empty is refused: *"A login must
   belong to a team member…"*
2. Set the role, the **home office** and the office/business-unit scope.
   *An earlier milestone fixed a case where a blank home office granted ALL
   offices — the failure now closes rather than falls open, and misconfigured
   users are surfaced to an administrator.*
3. **Admin → Roles & permissions**, pick Coordinator, press
   **✨ Apply recommended set**, adjust with the per-module verb columns or
   **"Apply to every module in Sales"**, then **Save access for Coordinator**.
4. **Role workspaces** — set where a coordinator lands.
5. Check it: sign in as them, or confirm against
   `../evidence/role-access-matrix.md`.
6. **If they need to record receipts**, note that is governed by `data.credit`
   — which the owner reviewed and accepted on 2026-10-18 (GAP-010).

### S-3 — Connecting MGH Books safely

1. **Admin → 📗 MGH Books connection** → fill the five fields →
   **Save connection**.
2. **Leave it in dry-run** (`books_dryrun()`) and raise a test invoice. The
   document is pushed without committing.
3. Check `/integrations` — **Integration health** — for the push state. *(In
   the test environment this screen rendered empty, with nothing connected; it
   may be the answer to the missing outbox screen in Money — Y-F1.)*
4. Go live. From then on **Books owns paid, outstanding and IRN**, and EXAACT
   never recomputes them.
5. Set `books_app_url` so `books_switch_ready()` is true and the Money area
   shows **📗 Accounts & GST ↗**.

### S-4 — A customer asks for their data back, or something goes badly wrong

1. **Admin → Backup & restore → 💾 Back up now**, then **⬇️ Download**. The
   customer has their own copy, without the vendor's help.
2. **To restore:** **↺ Restore** (a backup held here) or **↺ Upload & restore**
   (a file they supply).
3. **Before relying on this, answer the three questions in A-F3:** who may press
   Restore, whether an uploaded archive is validated, and whether the act is
   logged. **This audit did not fire either control**, and a restore can discard
   every invoice, report and nonconformity since the backup.
4. Afterwards, **Admin → Audit trail** to see what changed and who did it — with
   the **role held at the time** and the IP recorded.

---

## 5. Edge cases

| # | Edge case | What happens | Grade |
|---|---|---|---|
| E-1 | A Business Director opens Users or Settings | **302 redirect.** Documented intent (matrix: Users = — for BD), but the *shape* differs from the 403 a Branch Manager gets on `/access` — GAP-005 | **CONFIRMED** |
| E-2 | A Branch Manager opens the user register | **Opens** — `E(own office)` per the matrix. Correct | **CONFIRMED** |
| E-3 | An inspector opens `/approvals-setup` | A read-only explainer headed **"Nothing here is yours to change"**, editable half absent. Exemplary | **CONFIRMED by execution** |
| E-4 | Submit **Add a person** empty | **Refused:** *"A login must belong to a team member. Pick the person from the team, or enter their name…"* | **CONFIRMED by execution** |
| E-5 | A person leaves the company | **Deactivate**, not delete — their authored reports and approvals keep pointing at a real person. **Reactivate** for a rejoiner | **CONFIRMED** (controls exist) |
| E-6 | A user is created with no home office | Fixed in an earlier milestone: the failure **closes** rather than falling open to ALL offices, and misconfigured users are surfaced to an administrator | **CONFIRMED** (that fix is tested) |
| E-7 | Permissions saved to the wrong role | Guarded by design: the button says **"Save access for Coordinator"**, and **Undo** / **Cancel changes** exist | **CONFIRMED** |
| E-8 | An area is switched off with live data in it | `mod_on[*]` hides it. Whether the data remains and reappears when switched back on was not tested | **UNTESTED** |
| E-9 | Terminology is changed after records exist | Labels are rendered through `T_*()` helpers, so screens follow immediately. Whether stored text (report titles, template wording) follows is untested | **UNTESTED** |
| E-10 | Document numbering is changed mid-year | The live **Example** column prevents the obvious mistake. What happens to the existing series is untested — and for GST this matters | **UNTESTED — priority** |
| E-11 | **Upload & restore** with a corrupt or hostile archive | **Not tested.** An uploaded archive becomes the database; validation is unestablished (A-F3) | **NOT TESTED — priority** |
| E-12 | Super admin uses **log in as** | **Undocumented.** Whether it is logged, whether the impersonator can act, and whose identity the audit trail records are all unestablished (A-F5) | **NOT TESTED — priority** |
| E-13 | Two-factor is required for money roles | **🔒 Require it for the money roles** — targeted rather than blanket, which is right for field staff on mobile data | **CONFIRMED** (control exists) |
| E-14 | SSO is needed | **The screen rendered empty** (A-F2) | **UNCONFIRMED** |
| E-15 | Demo data is left in a live workspace | **Remove demo data** on the Super Admin panel | **CONFIRMED** (control exists) |
| E-16 | A service is offered that depends on another | **Add dependency** on `/service-scope`; `service_dependencies` is a table | **CONFIRMED** |
| E-17 | Two administrators edit permissions at once | **No concurrency protection** — outside the `['calls','jobs']` allow-list. On the permission grid, last save wins silently | **CONFIRMED** |

---

## 6. Findings raised in this area

| Id | Severity | Finding |
|---|---|---|
| **A-F3** | **P1** | **Backup/restore has no module gate and no documented authority.** **↺ Restore** and **↺ Upload & restore** replace the workspace's data; an uploaded archive becomes the database. Who may press it, whether the file is validated, and whether the act is logged are all unestablished. *Check this first* |
| **A-F5** | **P1** | **Super-admin impersonation ("log in as") is undocumented.** Not necessarily wrong, definitely unspecified: is it logged, can the impersonator act, and whose identity does the audit trail record? For a customer under accreditation, "who could have changed this" must include this path |
| **A-F6** | **P2** | **Many Admin screens have no module gate**, including `/backup`, `/companies`, `/super-admin`, `/feature-gates`, `/financial-control`, `/marketplace-plans`, `/marketplace-escrow`. They refuse a least-privileged role, but by each handler's own check rather than systematically (GAP-008) |
| **A-F1** | **P2** | `/users` and `/hierarchy` both render **"Organisation & people"** (GAP-009). Two genuinely different screens needing two headings |
| **A-F2** | **P2** | **The single sign-on screen rendered empty.** Licence-gated, or unbuilt? The tile promises "Identity provider settings" |
| **A-F7** | **P2** | **Document numbering can be changed mid-year** with no tested effect on the existing series. The live **Example** prevents the input mistake but not the consequence — and for a GST series this matters (E-10) |
| **A-F4** | **P3** | **Three screens for the dropdown lists** — `/masters`, `/lookups`, and the Directory's Masters tile |
| **A-F8** | — | **Context that reframes an earlier finding:** `/product-package` offers **four product packs** — TPIA, Technical Staffing, Recruitment, Recruitment In-house HR. The "four ways to match people to work" (`04-RECRUITMENT.md` R-F4) are the capabilities of four products sharing one platform, not four accidents. **Q-M3 should be re-asked as "which packs are we selling?"** |
| **A-F9** | — | *Credit:* **"Set up your workspace"** with **Fix** and **Do this** buttons · a save button that **names the role** · **Undo** on the permission grid · **"Apply to every module in Sales"** · **✨ Apply recommended set** · **Deactivate** rather than delete, with **Reactivate** · **"Arrange these 5 automatically"** with a live count and **"Would report to"** proposals · a **live Example** beside every numbering rule · **two-factor targeted at the money roles** · **Reset to standard wording** · **Add dependency** between services · **Load / Remove demo data** · a **customer-downloadable backup** · an audit trail that records **the role held at the time** and has **no delete control** · and the inspector's read-only **"Nothing here is yours to change"** |

---

## 7. Three things to check in this area before anything else

1. **Who can restore a backup, and is an uploaded archive validated?** (A-F3)
2. **Is super-admin impersonation logged, and whose name does the audit trail
   record?** (A-F5)
3. **What happens to an existing document series when its numbering rule
   changes mid-year?** (A-F7)

All three are about **unspecified authority over everything**, which is the
characteristic risk of a configuration area — and none of the three could be
settled without firing a destructive control or asking the owner.
