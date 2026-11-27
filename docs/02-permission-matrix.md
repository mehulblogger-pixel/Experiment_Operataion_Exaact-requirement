# 02 — Permission Matrix

Who can do what to each object, traced to real `can()` checks and route guards.
Grantable verbs (`PERM_VERBS`, `lib/access.php`): **View**, **Add**, **Edit**,
**Archive**, **Delete**, **Approve** — see the R-20 note below. The cells in the
tables also use the older shorthand for acts that have their own named right:
**Create**, **Close**, **Issue**, **Reopen**, **Export**, **—** (none). **⚠ implicit** = allowed only because no check
blocks it (see `99-gaps-and-risks.md`). Paths relative to `phpapp/`.

> **Company Business Capabilities are NOT permissions.** `lib/connect_capability.php`
> (Combination Engine) gates only *module visibility* per company and never grants or
> removes a `can()` right — this matrix remains the sole authority on who-can-do-what.
> The `/connect-capabilities` admin screen is MASTER_ADMIN-only. See
> `00-master-revamp-prompt.md` Part II.2.

**Column keys:** MA=MASTER_ADMIN, AD=ADMIN, BD=BUSINESS_DIRECTOR, SBU=SBU_HEAD,
BM=BRANCH_MANAGER, BAM=BRANCH_APP_MANAGER, OM=OPERATION_MANAGER, CO=COORDINATOR,
AM=ASST_MANAGER, FIN=FINANCE, INS=INSPECTOR. (SR_INSPECTOR = INSPECTOR + `idems.finalize`;
sales roles BDM/KAM/MM/ME are in the CRM mini-matrix below.)

> ### RATIFIED BY THE OWNER, 2026-10-13 — defaults ship, each company decides
>
> The open question "which modules should each role hold by default?" is
> **answered**, in the owner's words: *"Modules — I think so that is configurable
> with respect to the company."* So this matrix is a **starting point, not a
> fixed policy**: it records what a role holds the day a company is created, and
> every company may then change it for itself. Three layers, each per company and
> each already built:
>
> | Layer | Where the company changes it | Stored as |
> |---|---|---|
> | What the company has **bought** | Licence / product package (provider-side) | entitlement, `PRODUCT_MODULES` |
> | Which modules are **switched on** for the company | **Admin → Modules & features** | `modules_off` setting, per tenant |
> | Which **screens** of a switched-on module are on (plan 42 step A, owner 18 Nov 2026) | **Admin → Modules & features** → "Screens in this module" (+ "Simple start" / "Back to <date>") | `features_off` setting, per tenant (`lib/features.php`) |
> | Which modules **each role** holds | **Admin → Roles & permissions** | `role_access` setting, per tenant |
>
> **Screen switches grant nothing.** A screen switched off is hidden from every
> menu and refused by its address for everybody (`feat_route_blocked()`, asked in
> `index.php` before any handler); switching it on only makes it reachable again
> for the people this matrix already allows. Changing them needs the same right as
> the module switch (`cockpit_can()`: Master Admin or `settings.manage`) — **no new
> permission**. Core screens (jobs, work orders, clients, vendors, vouchers, every
> approvals inbox, users, settings, Modules & features …, `FEAT_CORE`) can never be
> switched off. Records are never deleted.

> `role_defaults()` in `lib/access.php` supplies the shipped default; a company's
> own `role_access` overrides it (`role_grant_perm()` writes through
> `setting_set`, so it lives in that company's own database and never leaks
> across tenants). **Nothing below is hard-coded policy a customer is stuck
> with** — which is why this document states defaults and the hard rule in
> `CLAUDE.md` is about never granting a right the matrix does not *list*, not
> about forbidding a company from narrowing its own roles.

**How to read cells:** the cell shows the strongest right the role holds *by default*.
Master (MA) bypasses every gate (`access.php:530`). "Edit" implies View. Per-user
overrides and the Settings→Roles editor can change any of this at runtime.

> **A person follows their role unless given their own access (fixed 2026-10-20,
> owner's video SAL_002).** The "Add a person" screen used to arrive pre-ticked with
> the **Coordinator's** access whatever role was chosen, and every save stored the
> ticks as a frozen per-user set. A Marketing Executive saved untouched therefore held
> a Coordinator's invoicing, vendors and credit figures — rights this matrix does not
> give the role — and lacked *Create / edit quotations*, which it does. Now the ticks
> are the chosen role's (`role_perm_set()`, Roles & permissions applied) and follow the
> role when it is changed; a save whose ticks equal the role's stores nothing, so the
> person keeps following the role, including later changes to it
> (`user_perms_to_store()`). Only a deliberate difference is stored as the person's
> **own access**; the screen says how it differs and offers one click back to the
> role, and the user register marks such people "own access". Nothing ticked still
> means "the role's access" (empty cannot be stored); removing access is Deactivate.
> No right was added to any role. Guard rail `tests/test_user_perms_follow_role.php`.
>
> **Own access is stored as differences from the role (Q11, owner 21 Nov 2026: "All as
> recommended").** It used to be a frozen copy of the ticks, so a later change to the
> role passed that person by. It is now stored as `@diff,+right,-right` — what is given
> beyond the role and what is taken away from it — and everything else follows the
> role, today and after any later change (`perm_resolve_stored()`, `perm_diff_from()`).
> Every person on a frozen copy was moved **once** (`access_migrate_user_diffs()`),
> each checked to hold exactly the same rights before and after; the value that was
> there is kept on the sealed audit chain (`ACCESS_STORED_AS_DIFFERENCES`). An older
> frozen copy written by anything else is still read exactly as before. **No right is
> added to or removed from anyone.** See 19r.

> **D-07 (decided, 2026-10-04) — what Finance reads.** Business UAT 1.5.4 reported a
> Finance login opening on what looked like an operations menu. Measured: the engine
> granted FINANCE view on jobs, calls, vouchers **and inspection reports**, while this
> matrix gave it "—" on Vouchers and marked Jobs "implicit". Decision: Finance **bills**
> from jobs and calls and **pays** vouchers, so all three are money and are now stated
> here as **View**; inspection reports are the technical finding, carry no figure, and
> were removed from `role_defaults_base()`. Finance holds no operational write right —
> asserted in `tests/test_role_scope_matrix.php`. Office scope stays **ALL**: finance
> reconciles across offices, which is a decision, not an oversight.
> *(Office scope superseded on 16 Nov 2026 — see "0.1 / C1" below.)*

> **0.1 / C1 (owner, 16 Nov 2026 — branch batch) — Finance and the Marketing
> Manager are office-based, chosen per person.** Both roles used to see every
> office by role (`'offices' => 'ALL'`). They now reach **the offices ticked on the
> person's record** ("Offices covered"; tick **All offices** for the company-wide
> picture), else their **home office** — `role_defaults_base()` gives both `OWN`.
> Their **rights do not change**: Finance still sees only finance data, the
> Marketing Manager only sales data, exactly as in the tables below.
> - **Nobody lost anything (C1).** Once per database
>   (`access_migrate_central_roles_scope()`, flag `scope_central_roles_c1_applied`),
>   every **active** person whose role — or whose custom role's base — is Finance or
>   Marketing Manager and whose offices were empty was given **All offices**.
>   Somebody already given offices keeps exactly those; an administrator may narrow
>   anyone afterwards and is never overruled.
> - **New people must be given offices.** A Finance / Marketing Manager with no home
>   office and nothing ticked sees **no** office, and `scope_needs_home()` flags them
>   like any branch role (the "no branch yet" message to them, the User-register
>   notice to an administrator).
> - **Quotation and costing approvals keep working (owner's words).** A level
>   addressed to the Marketing Manager goes to the Marketing Managers whose offices
>   cover the quotation's (or costing's) office (`crm_role_approvers_for()` →
>   `user_scope_covers()`); one with All offices approves every office; where nobody
>   in the role covers that office, the existing fall-back still sends it to everyone
>   in the role, so no quotation is stuck. Proven by
>   `tests/test_branch_batch_access_c1_central_roles.php`.

> **Branch batch, 16 Nov 2026 (QA V51 — owner-approved plan
> `QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md`) — scope corrections. No right was
> added to any role; each change brings the code back to this matrix.**
> - **B1 (DEF-ADM-002 / DEF-OPS-001).** `call_office_clause()` — the work-order
>   register's two-office rule — read an empty office list as "every office", so a
>   branch person with no home office saw every branch's work orders. It now answers
>   like `scope_clause()`: such a person sees **none**, with the existing "no branch
>   yet" message. Same correction in `call_alloc_allowed()` (allocation),
>   `partner_view_level()` (changing a client / vendor) and the hold & witness
>   register. Master Admin / Admin and the company-wide roles are unaffected; a run
>   with nobody signed in (export, CLI) stays unscoped.
> - **B2 (DEF-OPS-046).** Changing a work order's status, priority, criticality,
>   source, validation override or clarifications, and the job's assignment hold /
>   acceptance / reassign / reschedule / cancel / no-show, readiness, delay,
>   communication log and operational issue, now checks branch scope **before
>   anything is saved** (`tosrm_call_scope_ok()` / `tosrm_job_scope_ok()`). Editing a
>   work order (`call-edit`) and its required credit (`call-credit`) are refused to
>   any office that is neither its executing nor its contracting office.
> - **B3 (DEF-OPS-011, DEF-OPS-002).** A work order has two offices. **Opening** it
>   (`/call?id=`) is allowed to the **executing office and the contracting office**
>   (`call_view_scope_allows()`), as the register already listed it; anyone else is
>   refused. **Running** it (status, priority, allocation, the forms above) stays with
>   the office that carries it out (`call_exec_scope_allows()` / `call_exec_office()`:
>   the executing office, else the contracting office, else the managing office).
>   The Operations home, its registers and the scheduling board scope on that same
>   carrying-out office (`call_exec_office_sql()`); a blank executing office is no
>   longer shown to every branch.
> - **B3 (DEF-OPS-037) — the deputations list** (`pdso_deputations()`,
>   `lib/pdso.php`, the deputation register and its dashboard) is limited by the
>   same two-office rule (`call_office_clause()` on the job's / work order's
>   executing office and its contracting office): those two offices list it, a
>   person with no branch lists none, company-wide roles and runs with nobody
>   signed in are unscoped. Test: `tests/test_branch_batch_access_deputations.php`.

> **R-30 (UAT 1.5.4 / H10, 2026-10-11) — "OWN office" now means own office.**
> This matrix confines **Coordinator, Inspector and Branch Manager** to their own
> branch. The engine did not. `ua()` read:
> `$offices = $u['home_office_id'] ? [home] : 'ALL';` — so a role this matrix
> confines to one branch received **every branch** the moment nobody filled in
> that person's home office. The comment on the line said "OWN default"; it was
> the opposite, and nothing anywhere reported it. The owner found it from the
> outside: *"Even Mumbai office coordinator has all access"*, *"All things are
> available to other offices coordinator including the new inspection call."*
>
> Corrected so the failure direction is **closed, not open**: a person this
> matrix confines to a branch, whom the workspace cannot place in one, now sees
> **no** branch. Four readers of that scope had the same hole and are fixed with
> it — `scope_office_clause()`, `scope_clause()`, `scope_allows()` and
> `scope_office_allows()` each treated an empty office list as "no filter".
>
> Two things deliberately did **not** change. **Finance stays company-wide** *(until
> 16 Nov 2026 — Finance is now office-based, see "0.1 / C1" above)*
> (the note above): it is a decision, not an oversight, and `scope_needs_home()`
> never flags a role this matrix gives ALL. And a run with **no signed-in user**
> — the nightly export, the Tally run, the CLI — stays unscoped, because those
> authorise themselves and have always run that way; reading their empty scope
> as "see nothing" emptied a working credit-note export, which is how that
> distinction came to be measured rather than assumed.
>
> Closing a hole must not strand anybody, so the misconfiguration is now
> **visible from both sides**: the person is told their account has no branch yet
> and what to ask for, and any administrator is told how many people are in that
> state with a link to fix them. Proven by `tests/test_scope_playbook.php` (33
> checks, four mutations killed) — the scope playbook the owner asked for.
>
> **Still a decision for the owner, not a defect:** which modules each role holds
> *by default*. A shipped Coordinator holds view on 15 of the 31 modules,
> including Invoicing, Client portal, Clients and Vendors. That is this matrix's
> to state, and no code change may widen or narrow it first.

> **R-31 (UAT 1.5.3, 2026-10-11) — one filing system for modules.** The role
> editor grouped the 31 modules under its own seven headings while the per-user
> editor and the left-hand menu used the navigation's nine areas. Measured: **26
> of 31 modules sat under a different heading on the two screens**, so Invoicing
> and Profitability were filed under "Operations" and Overheads under "Directory
> & masters" — and an administrator looking for **Money** on the role screen
> found no Money at all. This is R-14 (one module with two names) at full scale.
> Fixed the same way R-14 was: the second map is deleted, not synchronised —
> `access_module_areas()` is now the only one. Grouping and labels only; **no
> permission key changed, nothing granted, nothing removed.**

> **R-20 (decided by the owner, 2026-10-09) — the six-verb model.** Until now a
> module produced exactly two rights: `mod.<key>.view`, and one tick the code
> itself labelled *"add / edit"*. The owner could not say *"may edit a job but
> never delete one"*, because deleting was a consequence of being an
> administrator rather than a right that could be handed out. Measured in
> `docs/phase7/PERMISSION-MODEL-AUDIT.md`: of the six verbs asked for, only view
> (31/31) and edit (31/31) were separately grantable — add 3/31, delete 1/31,
> deactivate 3/31, activate 0/31.
>
> **The vocabulary is now six verbs:** View, Add, Edit, Archive, Delete, Approve
> (`PERM_VERBS` in `lib/access.php`). Three decisions shape it:
>
> 1. **Archive is one right, not Deactivate plus Activate.** Whoever may take a
>    record out of use may put it back. Splitting them lets somebody break
>    something and then need an administrator to undo their own slip.
> 2. **Remove and Delete are one act,** except for users — where the app already
>    distinguishes Deactivate, Remove sign-in and (deliberately) no Delete, because
>    an inspection body must keep the record of who did the inspection.
> 3. **Only Approve binds to an existing right.** The seven genuine sign-off
>    rights (`crm.quote.approve`, `idems.finalize`, `complaints.decide`,
>    `hiring.review.clear`, `workforce.report.approve`, `capa.close`, `ncr.close`)
>    *are* the Approve column — no duplicate is created, and the ISO 17020
>    separation of preparing from approving is preserved. Add, Edit, Archive and
>    Delete are always generic. Binding Add was tried and reverted: it auto-granted
>    `crm.contract.register` to sales roles the matrix withholds it from.
>
> **What is enforced, and what is not yet.** The module gate
> (`ops_module_gate()`, `lib/ops.php`) now asks the route's verb on top of module
> access, for nine routes whose present authority a per-module verb can express
> exactly: `inquiry-delete`, `lead-delete`, `call-delete`, `document-delete`,
> `endorsement-delete`, `expense-delete` (Delete) and `audit-close`,
> `capa-close`, `job-close` (Archive). The verb is an **additional** lock — each
> handler's own guard still runs behind it — so enforcement can only tighten and
> never admits anybody the old code refused.
>
> Three destructive routes are deliberately **left to their handlers**:
> `opportunity-delete` and `bill-delete` each share a module and a verb with a
> route whose authority differs today *(since 24 Nov 2026 `opportunity-delete`
> has its own switch, **Delete deals** `crm.deal.delete` — 19y7)* (deleting a lead needs the edit tick;
> deleting an expense admits Finance), and one right cannot mean two things;
> `ncr-reopen`'s guard reaches across modules (`mod.capa.edit` also grants it),
> which no per-module verb can express. *(Since 16 Nov 2026 `ncr-reopen` needs
> `ncr_can_close()` — footnote 20a.)* Guessing at these is how a permission
> change silently locks somebody out, so they are named rather than assumed.
>
> **The migration runs once, not on every read — and why that matters.** Every
> saved permission set carries a vocabulary stamp (`PERM_VOCAB_TAG`, added by
> `perm_close_implications()`, which every save path runs through). A set without
> the stamp predates the verb split and is upgraded as it is read; a set with it
> is returned exactly as stored.
>
> Without the stamp the upgrade re-applied its carry-over on *every* read, so a
> withheld verb came straight back: the owner unticked Delete on Jobs, saved,
> reloaded, and Delete was ticked again. Every function behaved exactly as
> written — the fault lived in the gap between them, and only a real
> save-and-reload in a browser exposed it. Since "may edit a job but never delete
> one" is the whole point of R-20, nothing else in this change matters if that
> regresses. It is pinned in `tests/test_permission_verb_migration.php` under
> "The migration runs ONCE, not on every read".
>
> **Where the owner sets this.** Settings → Roles & access (per role) and
> Users → Edit → Permissions (per person). Both screens draw the grid from ONE
> partial, `views/ops/_perm_verb_grid.php` — R-14 was caused by two permission
> screens drifting apart, so a single implementation is the fix that holds.
> Column headings grant one verb everywhere, row headings grant full control of
> a module, and ticking a stronger verb fills in the weaker ones it must include
> (a saved set is closed under `PERM_VERB_IMPLIES` by `perm_close_implications()`,
> so it can never say "may delete but may not see"). On a phone the grid becomes
> large labelled switches per module rather than a squeezed table: this is a
> desk-first screen, and CLAUDE.md forbids averaging desk and phone into one
> middle.
>
> **No cell in the tables below changes.** Every role can do precisely what it
> could do before: the old "add / edit" tick becomes Add **and** Edit, and on the
> four modules where that tick already carried a destructive act — deleting a lead,
> re-opening a closed nonconformity, closing an internal audit, deleting a bill on
> a job — the stronger verb is granted too, or those people would have lost
> something they can do today. Administrators receive every verb, because deleting
> was previously theirs by role. Asserted in both directions by
> `tests/test_permission_verb_migration.php`; the destructive-action guards are
> asserted by `tests/test_permission_write_guards.php`; the per-role before/after
> equivalence of every enforced route by
> `tests/test_permission_verb_enforcement.php`.
>
> A permission set saved under the old two-verb model is upgraded **as it is
> read** (`perm_upgrade_verbs()`), not rewritten in the database — so the change
> needs no downtime, is idempotent, and cannot half-apply. Three carry-over
> mechanisms feed it, each mirroring a guard in the code: the edit tick's own
> reach, a job title's reach (`is_admin_level()` is seven roles wide, not two),
> and a named right's reach (`ops.job.close`, `idems.finalize`,
> `finance.reconcile`, `ops.call.delete`, `capa.close`).

## Operational & admin modules

| Module / Object | MA | AD | BD | SBU | BM | BAM | OM | CO | AM | FIN | INS |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Calls** ¹ | E·D | E·D | View | View | Edit | View·D | Edit | Edit | Edit | View | — ᵃ |
| **Jobs** ² | E·C | E·C | Allocate | Allocate | E·C | View | E·C | E·C | Allocate | View | View/Close **own** ᵇ |
| **Vouchers** ³ | own · View all · Paid · Reopen | own · View all · Paid · Reopen ᵈ | own · chain ᶜ | own · chain ᶜ | own · chain ᶜ | own · chain ᶜ | own · chain ᶜ | own · chain ᶜ | own · chain ᶜ | own · View at its step · Approve ᶜ (last) · Paid · Reopen (approved) | **own** V/E/Submit · chain ᶜ |
| **Profitability** ⁴ | View | View | View | View | View | — | View | — | — | View | — |
| **Business Partners** ⁵ | E | E | E | E | E | E | E | E | E | View ⁵ᵃ | — |
| **Contracts (open)** ⁶ | Endorse+Approve | Endorse+Approve | Endorse | Endorse | Approve | Approve | Endorse | — | — | Register/Reopen | — |
| **Purchase Orders** ⁷ | E | E | E | E | E | E | E | E | E | E | — |
| **Hiring / Candidates** ⁸ | E | E | E ⁸ᵃ | E ⁸ᵃ | E | — | E | E | — | — | — |
| **Inspector master** ⁹ | E | E | E | E | E | E | E | — | — | — | — |
| **Attendance** ¹⁰ | Recon | Recon | Recon | Recon | Recon | Recon | Recon | Recon | Recon | — | self-mark ⁱⁱ |
| **Office Overheads** ¹¹ | E | E | E | E | E | E | E | — | — | — | — |
| **Lookups** ¹² | E | E | E | E | E | E | E | — | — | — | — |
| **Custom fields (per-entity)** ¹² | E | E | E | E | E | E | E | — | — | — | — |
| **Custom form builder** ¹³ | E | E | — | — | — | — | — | — | — | — | — |
| **Users** ¹⁴ | E(global) | E(global) | — | — | E(own office) | E(own office) | — | — | — | — | — |
| **Dashboards** ¹⁵ | View | View | View | View | View | View(ops) | View | View | View | View(money) | View(own) |
| **Settings** ¹⁶ | E | E | — | — | — | — | — | — | — | — | — |
| **Backup & restore** ¹⁶ᵃ | Own + **another workspace's (typed confirmation)** | Own only | — | — | — | — | — | — | — | — | — |
| **Invoicing / Receipts** ¹⁷ | Full | Full | Raise | Raise | Raise | — | — | Raise | — | Issue | — |
| **Inspection reports (IDEMS)** ¹⁸ | Full | Full | View | Finalise | Finalise | config only | Finalise | Edit | Edit | — (D-07) | Edit(own: inspector or creator) — reads own only |
| **Nonconformities** ²⁰ | Full | Full | View · **Record** | View · **Record** | View · **Record** | View · **Record** | View · **Record** | View · **Record** | View · **Record** | — | — |
| **Corrective actions (CAPA)** ²⁰ | Full | Full | View | View | Edit | — | Edit | View | View | — | — |

**Quality roles** — added 2026-10-18 on the owner's rule, *"only Quality or
Technical Head can close it"*. Before that rule, `ncr.close` and `capa.close`
were held by MASTER_ADMIN and ADMIN alone, so closing a nonconformity was a
system-administrator act. These two roles sit outside the operations line on
purpose: quality judges the work, it does not run it, so neither is in
`MGMT_ROLES` and neither is `is_admin_level`.

| Module / Object | QH (Quality Head) | TH (Technical Head) |
|---|---|---|
| **Nonconformities** ²⁰ | Edit · **Close** | Edit · **Close** |
| **Corrective actions (CAPA)** ²⁰ | Edit · **Close** | Edit · **Close** |
| Complaints & appeals | View | View |
| Calls · Jobs · Inspection reports | View | View |
| Reports (insights) | View | View |
| Audit trail (`idems.audit.view`) | View | View |
| Everything else — users, settings, money, sales, recruitment, accreditation registers | — | — |

Scope is **offices ALL · business units ALL** for both: a nonconformity raised
in one branch is the company's nonconformity, and a quality function scoped to
its own office could not close it.

**Deliberately NOT granted, pending a decision:** the accreditation registers
(equipment & calibration, competence, impartiality, confidentiality, internal
audits, data control). A quality head plausibly owns them; nobody has said so,
and granting ten modules unasked is how a role quietly becomes an
administrator. Raise it with the owner before widening.

**Sales / CRM roles** (they touch only the CRM modules; `—` elsewhere):

| Module | BDM | KAM | MM (Marketing Mgr) | ME (Marketing Exec) |
|---|---|---|---|---|
| Leads & deals ¹⁹ᵇ | View·Add | View·Add | View·Add | View·Add |
| Inquiries ¹⁹ | Edit | Edit | Edit | Edit |
| Quotations ¹⁹ | Create·Send | Create·Send | Create·**Approve**·Send | Create |
| Orders/contracts ¹⁹ | Edit | Edit | Edit | View |
| Sales reports ¹⁹ | View | View | Edit | View |
| Clients (directory) | View | View | Edit | View |
| Contract register ⁶ | — | — | — | — |
| Templates ¹⁹ | — | — | Manage | — |

**Sales switches of their own (permission foundation A, 19r — owner, 21 Nov 2026).**
Each used to ride on another tick or on a job title; on day one each is on for exactly
the people who could already do it (worked out from the old rule, never typed):

| Switch | MA | AD | BD | SBU | BM | BAM | OM | CO | AM | FIN | BDM | KAM | MM | ME | QH | TH | SRI | INS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| See other salespeople's lists (Everyone / Choose a person) (`crm.sales.see_all`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — |
| Set sales targets for others (`crm.targets.set`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | ✓ | — | — | — | — | — |
| View deals (`crm.deal.view`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — ¹⁹ʸ | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Add and edit deals (`crm.deal.edit`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Change pipelines and stages (`crm.pipeline.manage`) | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| Download leads to Excel / CSV (`crm.lead.export`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Download deals to Excel / CSV (`crm.deal.export`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Add a customer from a won deal that had no lead (`crm.deal.add_customer`) ¹⁹ʸ⁴ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Delete deals — never a won one or one with a quotation (`crm.deal.delete`) ¹⁹ʸ⁷ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| Download quotations to Excel / CSV (`crm.quote.export`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Change Sales settings (working days, follow-up days, who gets new leads) (`crm.settings.manage`) | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| See what customers were billed and paid (Sales screens and Receivables) (`data.customer_money`) | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | — | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — |
| View project costings (`costing.view`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Prepare project costings (`costing.prepare`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| Manage the cost-head library (`costing.heads.manage`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — |

*Since 21 Nov 2026 (owner, 19x) the Branch Manager and the SBU Head (BM, SBU columns) also hold the two lead and deal downloads, with Leads view, for their branch / business unit; and (DEF-11, 19y) the Marketing Manager (MM column) sets targets for the people of their branches. Every other cell is the day-one holder.*

Read from `role_perms()` with no saved grid. A company that has saved Roles &
permissions gets the same rule applied to its own saved grid and to each person's own
access, so the answer is that company's day-one answer, not this table's.

**Office reach of the sales roles:** BDM, KAM and ME work in their own office; the
**Marketing Manager is office-based since 16 Nov 2026** — the offices ticked on the
person (All offices = company-wide), else their home office — and approves the
quotations and costings of the offices they cover (see "0.1 / C1" above).

---

### Footnotes (each cell traceable)

1. **Calls** — module gate `mod.calls.view` (`ops.php:2243`); create/edit `can('ops.call.create')||is_master()` (`ops.php:3695`) → BM,OM,AM,CO; delete `can('ops.call.delete')` (`ops.php:3588`) → BAM. Module edit default per `access.php:332-346`. **ᵃ** Inspectors have no calls module; they only see a call's context through their own job.
2. **Jobs** — module gate `mod.jobs.view` (`ops.php:2244`). Allocate/edit `is_coordinator_level()` (`ops.php:5163`) → all admin-level + AM + CO. **Close (`job-close`) is gated on `ops.job.close`** (R2 fixed): `ops_require(is_master() || can('ops.job.close') || job_owned_by_me($id))` (`ops.php` job-close) → CO, BM, OM (hold the permission) + master + the **job-owner inspector** bypass (`ops.php:2384-2388`). **Close one visit day (`job-visit-close`)** — coordinator level, master, and (Expenses Phase 1, owner 17 Nov 2026) **the engineer allocated to that day** of their own job (`exp_visit_is_mine()`): it is the "Close own" of this row, and it is where they confirm the day's expenses. **Close a visit day as "No inspection" (`job-visit-no-inspection`, TPIA Phase 1 / D8, owner 25 Nov 2026)** — exactly the same people and the same check as closing a visit day (coordinator level, master, the engineer allocated to that day), on an open job; no new permission and no one's access widens. Closing the job or the day no longer writes `expenses` rows; the claimant's day expenses go to their claim (fn 3). Roles that only *view* jobs — **FIN, SBU, BAM, AM** — can no longer close them (none hold `ops.job.close`). Report-approve `can_approve_report()` (`workforce.php:669-677`).
    ²ᵃ **Branch Application Manager does not allocate (R4 / DEF-OPS-020, owner 16 Nov 2026).** `is_coordinator_level()` includes every `MGMT_ROLES` role, so the BAM — the branch's configuration custodian, whose Jobs cell is **View** — could allocate. Allocating or editing a job (`job-new`, `job-edit`, `job-reassign`, and the Allocate / Edit / reshuffle buttons) now asks `job_allocate_role_can()`: coordinator level **except** the Branch Application Manager, unless the company deliberately ticks **Allocate / edit jobs** (`ops.job.allocate`) for that role or person. Every other role allocates exactly as before.
3. **Vouchers** — branch batch R5 (owner, 16 Nov 2026; DEF-OPS-031/003/033/034; C3; Q1–Q4), **changed by batch 2 (owner, 17 Nov 2026 — `QA-REPORTS/V51/29-Next-Changes-and-Open-Points.md`, Change 2, points 1–3, section A, Q3):** *"Every individual can create, edit, submit their own vouchers. Every voucher is to be approved by the N+1 and other levels as set with respect to amount and reporting. Assistant Manager can approve vouchers of their own reportee. They cannot see anyone else."* Code: `ops.php` (`can_view_voucher`, `can_edit_voucher`, `can_submit_voucher`, `can_mark_voucher_paid`, `can_reopen_voucher`, `voucher_sees_all`, `voucher_finance_sees`, `voucher_register_rows`, the module-gate pass `voucher_route_owner_safe`) and `lib/voucher_approval.php` (the chain; `vappr_is_participant`, `vappr_finance_reached`).
   - **own** — every individual whose login is linked to a team-member record (any role: Inspector, Senior Inspector, Coordinator, Asst. Manager, Branch Manager, Director …) opens a month's voucher for themself, edits it while DRAFT and submits it, with no Vouchers module right: the module gate lets the voucher routes through and each handler checks the record. A desk login without the Vouchers module finds it as "My vouchers" in Find a screen.
   - **chain ᶜ — what anyone else sees (section A, Q3).** Besides their own, a person sees **only** the vouchers they must approve **now** (they, or the delegate of the person, at the current level) and those they **have approved, been counted as signed for, or sent back** (any round). Being named at a later level, holding a role a level uses, or holding the Vouchers module shows **nothing** — **no role sees a branch's vouchers because of its role** (a Branch Manager, Business Director, Business Unit Head or Coordinator outside the chain sees none). An Asst. Manager sees their reportees' vouchers when they reach them as N+1, and nobody else's.
   - **Exceptions** — the **Master Admin and an Admin** see every voucher. **Finance** sees the vouchers that **reach or passed a Finance level** — waiting at Finance, approved, paid, acted on by Finance, or reopened from Approved/Paid — in the **offices it covers** (office-scoped Finance only its ticked offices). Finance edits no one's draft.
   - **V2 — nobody edits someone else's voucher (Expenses Phase 1, owner 17 Nov 2026, `QA-REPORTS/V51/36-Expenses-Existing-vs-Needed.md`).** Only the claimant opens, changes (adds, changes or removes a line), and submits **their own** claim, while it is a DRAFT and within its deadlines. Approvers, Finance, the Master Admin and an Admin **only approve or send back (with a reason)**; Finance marks Paid. The Master Admin and an Admin **no longer** edit somebody else's lines, submit somebody else's draft, or open a period's claim **for** someone else (before: they could). They still **view** every voucher. Code: `can_edit_voucher`, `can_submit_voucher` (owner only), `exp_line_change_problem` (`lib/expenses.php`), `/voucher` (own claim only), `voucher-line`, `voucher-quick-add`.
   - **Expenses are confirmed when the day is closed (V1).** A line exists only on a day the claimant closed on **their own allocated job** — the job close (`/job-close`, the job-owner bypass of fn 2) or their own visit day (`/job-visit-close`: the engineer allocated to that day may close it, which is Jobs "Close own"; before, only coordinator level). The day's lines go only to the claim (`voucher_entries`), never to `expenses` as well. A coordinator closing the job records no expenses for the engineer. "Not for a job" types (scope NONJOB) only where switched on for the person's band or the person (E5). No new permission.
   - **Deadlines and reopen by N+1 (E4).** A day's expenses can be added or changed for **N** days after the day, and a claim must be submitted within **M** days after its period ends (settings, owner's values 8 and 8; Admin → **Expense setup** → Claim rules, `/voucher-rules`, **Master Admin only**, `exp_settings_can()` = `vappr_config_can()` — every save on its four tabs (rules, expense types, bands, default band per role / each person's band) asks it; a person's band and personal allowances on the team-member record stay `is_master()`, as the old Allowances & rates panel. No new permission (owner, 18 Nov 2026)). After a deadline it is locked; the claimant asks **their N+1** ("Reports to") to reopen it, and **only that N+1** may, with a mandatory reason, logged in History (`REOPENED`, level `DEADLINE`). While it waits for them, that N+1 sees the claim (it is waiting for them to act now — the chain rule ᶜ). Nobody else reopens after a deadline — not N+2, Finance, the Master Admin or an Admin. The month freeze still locks editing.
   - **ᶜ Approve = the levels the Master Admin set, never a role on its own.** Admin → Approvals → **Expense vouchers** (`/voucher-approvals`, **Master Admin only**) sets the levels **and their order**: Reporting line **N+1…N+4** (N+1 = the claimant's "Reports to" — `inspectors.reports_to_id`, else the claimant login's `users.reports_to_id`; N+2 = that person's `users.reports_to_id`; …); **any number of role levels** (Change 2) — any role on the role list except the administrators and Finance (Business Director, Business Unit Head, Branch Manager, a custom role …), signed by **any active holder whose office scope covers the voucher's office** (`user_scope_covers`; a custom role built on that role counts, `user_holds_approver_role()` as for quotation approvals), **the first to approve signs**; **Office Finance** (active Finance logins whose office scope covers the voucher's office) and **Central Finance** (active Finance logins with all offices). Each level is "needed when the total is above ₹X" (blank = always; at least one level must be always; **amounts are never preset**). Nothing is set when shipped; until it is, the chain is **N+1, then Finance** (office Finance, else central Finance). The setting saved by the previous batch still reads, in its fixed order. Levels sign in the order set; the reporting line goes upwards (N+1 before N+2; a higher level includes the ones below it); role levels may sit anywhere among them; **Finance always signs last** (office, then central). Example: N+1 always · N+2 > ₹5,000 · Business Director > ₹20,000 · Office Finance always → ₹3,000: N+1 → Finance; ₹8,000: N+1 → N+2 → Finance; ₹25,000: N+1 → N+2 → Director → Finance. Q2: a shorter line ends at its highest person; a claimant with no "Reports to", a switched-off approver, **a role nobody active holds for that office**, or a Finance level nobody covers is signed by an **administrator (MA/AD)**, who is warned (on the voucher, in their inbox and on the setup screen). Q4: every voucher, whoever the claimant. A level is signed by its person (whatever their role — so a Director in a claimant's reporting line signs that level, point 1), their live delegate (`approval_delegations`, entity `VOUCHER` set at `/voucher-delegations` by the Master Admin, or a company-wide delegation; no chaining; a VOUCHER delegation lends nothing elsewhere) or, where nobody can act, an administrator.
   - **No double signing (Change 2)** — when a person who would sign a reporting-line or role level **has already signed an earlier level** of the same voucher (the Director who is also the claimant's N+2), that level **counts as signed**; History records "Business Director — already signed at N+2" (`ALREADY_SIGNED`). **Finance levels are never counted this way** — Finance is the paying check, so another Finance person (or an administrator) signs.
   - **Maker ≠ checker** — the claimant and the submitter never sign (and are never counted as signed); one person signs one level of a voucher.
   - **Send back (Q3)** — whoever may sign the current level may send it back to DRAFT with a **mandatory reason**, shown to the claimant and in History; resubmitting restarts the chain.
   - **Paid (C3)** — **Finance** (in the offices it covers), the Master Admin and an Admin mark an APPROVED voucher Paid; never the claimant. **E6 (owner, 17 Nov 2026):** whoever marks it Paid enters the **advance** already paid to the claimant at that moment (recorded in History); the claimant no longer types the advance, and an advance already on an older voucher is kept.
   - **Reopen (points 2–3)** — **APPROVED** (not yet paid) → DRAFT: **Finance** in the offices it covers, the **Master Admin** or an **Admin**. **PAID** → DRAFT: the **Master Admin or an Admin only**. **SUBMITTED** → DRAFT: the Master Admin or an Admin; everybody in the chain uses **Send back** while it waits for them. **Nobody else reopens** — the Business Director and the Business Unit Head never; coordinators, managers and approvers no longer do — and never the claimant. (A claim locked by a **deadline** is a DRAFT; only the claimant's N+1 reopens it — see "Deadlines" above.) **ᵈ** an Admin has the Master Admin's voucher rights except setting the chain and the expense rules (Master Admin); since V2 neither edits nor submits somebody else's voucher.
   - Every submission, approval, "counted as signed", send-back, reopen (with the status it came from) and payment is recorded (`voucher_approval_log`: who, for whom, level, when, reason) and shown on the voucher's History tab. Pending approvals appear in the approver's Approvals inbox and "Your pending tasks"; approved vouchers appear to Finance as "vouchers to pay". Month-freeze still locks editing.
4. **Profitability** — computed, read-gate only `can('data.profitability')` (`ops.php:6124`); call-profit adds `data.revenue`/admin (`callprofit.php:62`). No lifecycle (see doc 03). **Cross-office (ADR-005):** `data.revenue` says WHETHER a person sees revenue, never WHOSE. On a cross-office job the client-billable figure belongs to the contracting office; the executing office's revenue is its inter-office credit. Reports pass the reader's office into `job_profit()` so every total obeys that, and invoicing / payment totals stay with the office that raises the invoice.
5. **Business Partners** — list gated `mod.clients/vendors.view` (`index.php:879`); create/edit/detail/sub-records are now **guarded (R1 fixed)**: create/edit/add need `mod.clients.edit`/`mod.vendors.edit`/coordinator-level/master; the 360 detail needs the matching view rights. An INSPECTOR can no longer create, edit or read partner records. **Office boundary (ADR-005):** having the right to edit clients is not the right to edit THIS client. `partner_view_level()` gives another office BASIC — who the company is — and withholds contact details, commercial terms, contracts, purchase orders, registrations and notes; the work orders listed follow the existing two-office rule. `partner_can_write()` refuses every change from outside the owning office, at the edit form, every sub-form behind it, the parent-company action, and the JSON endpoints that serve addresses and purchase orders. A party with NO branch set stays FULL to everyone, exactly as `partner_office_sql()` treats it. This grants nothing new — it narrows existing rights by office.
    ⁵ᵃ **Finance — vendors and clients, read only, changed 2026-10-19.** This row already said Finance views business partners, while the code refused Finance both registers. On the owner's answers — Q-G (*"Yes"*, vendors) and Q-K (*"Yes read only"*, clients) — Finance now holds `mod.vendors.view` and `mod.clients.view`, and no edit right on either. The row and the code agree. Verified over HTTP: `/clients` 302 → 200, `/partner-new` still refused (`docs/audit-2026-10/evidence/live-plan-change-finance-clients/`).
6. **Contracts (open)** — **Three journey fixes, owner 13 Nov 2026 ("Go ahead with the three broken items first") — none creates a permission:** (a) *Accounts' own screen for won deals*, `/contracts-to-register` (`ops_contracts_to_register()`), behind the Quotes module and gated `crm.contract.register`/master — the right that registers contracts already. It shows of a deal only what the Finance dashboard already showed (reference, name, customer, value) and registers through the existing `POST /opportunity-contract`, which now sits BEFORE the "can edit deals" check in `ops_opportunities()`: it was below it, so the standard Finance role (no `mod.leads.*`) was refused with "You cannot change an opportunity" — the door the matrix always gave Finance had never opened. Finance still cannot open, edit, move or send a deal. (b) *One sale, one contract*: a deal won through an accepted quotation (`opp_winning_quote()`) can no longer be sent to Accounts or registered on its own (`opp_own_contract_block()`), and is left out of "won deals without a quote" — narrower for everyone, master included. (c) *Add the customer from a won deal with no lead* (`POST /opportunity-add-customer`, `opp_add_customer()`), gated `leads_can_edit()` — exactly who may already make a lead a customer (`lead_convert`), with the same duplicate guard and the same short record. **the two signatures in this row are the STANDARD rule; from 13 Nov 2026 each company may set its own** (owner: "Hope these approvals are not hard coded … all approvals shall be set by the individual organisation as per their own requirements"). Under **Admin → Approvals → Sales → Contract** (`/sales-approvals?tab=contract`; also in Set up Sales) a company chooses: how many approvals a new contract needs (two, one, or none — it then opens when registered); which of its roles (its own custom roles included) and/or one named person give the first signature and the final approval; and whether one person may give both. Settings `contract_open_signatures`, `contract_sig1_roles` / `_user`, `contract_sig2_roles` / `_user`, `contract_same_person`, read by `contract_open_rule()`; `can_endorse_contract_open()` / `can_approve_contract_open()` now answer from that rule. A company that never opens the screen keeps this row exactly (plus, per the owner's 12 Nov rule for approvals, a custom role built on one of these roles). **Who may change the rule:** `settings.manage` or master only (`contract_rule_editable()`) — narrower than Set up Sales itself, so a quotation approver cannot rewrite who opens contracts; everyone else who can open the tab reads the rule. An amendment to an open contract follows the same rule; with "none" it still takes the one final approval. The Master Admin can always sign, as before. No new permission is created. **Any number, one after another (owner, 24 Nov 2026, Q-08 — "Yes"; "as many as needed" from 26 Nov 2026):** the same screen asks for the number — 0, 1, 2, 3 or more (a technical safety limit of 50 stops a typing slip); the signatures between the first and the final approval (settings `contract_sig3…N_roles` / `_user`) are given by the roles and/or one named person the company picks — no role holds one by default, so until the company names someone only the Master Admin can give it. Each waits for the one before it; "one person may give more than one" applies across all of them. Who may decide or see the queue is now "anyone who gives any of the company's signatures" (`contract_is_signer()`: first, in-between or final) — for a company on none, one or two signatures that is exactly the endorse-or-approve people it was. Still behind the Work orders module (see 19b): a person named for a signature who cannot open Work orders is refused at the door, as for the first and final signatures today. No new permission is created. register `can('crm.contract.register')||is_master()` (`crm.php:1830`) → FIN; endorse `can_endorse_contract_open()` (`contracts.php:473-477`) → OM,SBU,BD,ADMIN + `users.manage.branch` (BM,BAM); approve `can_approve_contract_open()` (`contracts.php:478-481`) → BM,BAM; reopen `crm.contract.register` (`contracts.php:690`); **correct the number** `crm.contract.register` (`ops_contract_renumber()`) → FIN — the same right that registered it, because a correction is a re-statement of the registration, not a new authority. It moves the number on every record filed under it (see ADR-004) and is refused when the target number is already in use; it can never open, endorse or approve anything. **Link a work order to its contract / purchase order** `ops.call.create` (`ops_call_link_commercial()`) → the same right that raises one, and it still cannot attach a contract that is not OPEN (master excepted, as on the raise path) nor one belonging to another party, nor touch a work order that has already been invoiced. **Note:** the *partner-screen* contract-add path (`index.php` partner-add `kind=contract`) is now gated by the **same** `can('crm.contract.register')||is_master()` check (R4 fixed) — so both doors are Accounts-only.
7. **Purchase Orders** — now **guarded (R1 fixed)**: viewing a PO (`po`) needs `mod.clients/vendors.view` (or coordinator-level/master); mutating one (pull-quote, add line) or adding via `partner-add kind=po` needs the edit right or `finance.reconcile`. No longer open to any logged-in user.
8. **Hiring** — module gate `mod.hiring.view` (`ops.php:2263`); create/edit & stage-move `is_coordinator_level()` (`ops.php:4317,4486,4557`). Engagement-mode config `is_admin_level()` (`ops.php:2502`). **Hiring-workflow (pipeline/stage) configuration** — route `recruit-pipelines`, gated `is_admin_level()` (`recruitpipe.php` `ops_recruit_pipelines()`); a Phase-2 configurable pipeline engine (per-tenant `recruit_pipelines`/`recruit_stages`). Adds **no new permission** — it reuses the admin-level config gate, exactly like engagement-mode config; the operating `mod.hiring` gates are unchanged. **Position master & org-chart** (Phase 3) — routes `positions`/`positions-org`, view gated `can('mod.hiring.view')`, manage gated `is_coordinator_level()` (`position.php`); `requisition-position` (link a requisition to a position) `is_coordinator_level()`. **Org-chart import** (`positions-import`, `is_coordinator_level()`, `position.php`) — paste or upload a CSV of an existing organogram (Name, Code, Department, Grade, Reports-to, Sanctioned, Occupied) to create the positions and build the reporting tree; header-aware, re-runnable (upserts by code/name, never duplicates), two-pass parent linking, unresolved managers reported. Adds **no new permission**. The manpower-plan validation (cases A–E) shown on the requisition is a **read-only check**, not a new status. Adds **no new permission** — reuses the hiring view + coordinator gates. **Interviews & Documents** (Phase 4) — routes `candidate-interview`/`candidate-doc`, gated `is_coordinator_level()` (`recruit_iv.php`); multi-round interviews with scorecards and a candidate document set with a status lifecycle. Sensitive document files (salary, medical, identity, PAN) are **download-restricted to `is_admin_level()`** (§39). Adds **no new permission**. **Salary structure, HR discussion, Offer & Onboarding** (Phase 5) — route `candidate-offer`/`offer-letter` (`recruit_offer.php`); manage `is_coordinator_level()`, **offer approval `is_admin_level()`** (a manager). An **unapproved offer can never be issued**. Salary figures shown only to `can_see_salary()` (`data.salary`). Onboarding reuses the existing `candidate-stage` → ACCEPTED person spine (no duplicate). Adds **no new permission**. **Configurable compensation** (Phase 5.1A) — route `comp-setup` (`comp_config.php`), `is_admin_level()`: admins define salary headings (earnings/deductions/employer) + statutory flags; the salary structure is computed from them. **Document Studio** (Phase 5.1B) — routes `doc-templates` (`is_admin_level()`) and `candidate-letter` (`can('mod.hiring.view')`) (`doc_templates.php`): configurable offer/appointment/other letter templates + letterhead, auto-filled from candidate data with missing fields highlighted. Adds **no new permission**. **Configurable approval matrix + SLA + escalations** (Phase 6) — routes `recruit-approvals` (configure rules/levels, `is_admin_level()`) and `my-approvals` (act on your own pending step, any signed-in approver whose role/named-user matches, or a master) (`recruit_approval.php`). Rules match by entity (requisition/offer/salary) and by department/BU/grade/position + value band (narrowest wins); each level carries an SLA, a reminder cadence and an escalation target; approve advances, reject/final-approve calls back into the entity (`job_offers`/`requisitions`). `offer_submit()` routes an offer through the chain when a rule matches, coexisting with the existing `is_admin_level()` offer approve as a fallback. Reminders/escalations run on the daily cron (`appr_tick()`) and e-mail through `ops_mail()`. Adds **no new permission** — reuses the admin-level config gate and, for acting, a runtime approver-identity check; **independent of** the platform's separate `/approval-rules` quote/report routing screen, which is untouched. **Recruitment exports + public careers intake** (Phase 7) — route `recruit-export` (`recruit_export.php`) streams CSV of candidates/requirements/offers/funnel gated the same as the Recruitment command centre (`recruit_home_can()`), with money columns still gated `can_see_salary()`. Route `careers-admin` (`is_admin_level()`) toggles a public **careers page** and picks which requirements are advertised (`requisitions.careers_published`, opt-in). The public page (`/careers`, `careers.php` `careers_route()`) is served **in front of `require_login()`** and is **off by default** (`careers_enabled` setting; when off, visitors are redirected to staff login); an application creates a `candidates` row (`source=CAREERS`, stage RECEIVED) against the advertised requisition — a honeypot + per-requisition de-dupe guard the intake, and the public form performs **no privileged action** on any existing record. Adds **no new permission** — additive columns only. **Auto job-description / posting generator** (`recruit_jd.php`) — route `jd-generate` (AJAX, `is_coordinator_level()`, CSRF-checked) turns a requisition's structured fields (role/department/grade/location/discipline/skills/qualification/experience + a new `responsibilities` free-text) into a job description / public posting; deterministic **template** when no AI key, **AI-enriched** (via the existing `ai_chat()` seam) when one is configured, always falling back to the template and always shown for human review. Boilerplate (intro / what-we-offer / how-to-apply / tone) is admin-configurable in the `jd_config` setting on the Careers page screen. Adds **no new permission**. **Recruitment Manager — module-scoped admin** (NEW permission `hiring.admin`, `access.php`): all recruitment **configuration** screens — `recruit-pipelines`, `comp-setup`, `doc-templates`, `recruit-approvals`, `careers-admin` — are now gated `hiring_admin_can()` = `is_admin_level() || can('hiring.admin')` (`ops.php`). A full administrator keeps access unchanged; additionally, a customer can grant a role the new `hiring.admin` permission on the **Roles & permissions** (`/access`) screen to appoint a **Recruitment Manager** who configures **only** the hiring module and holds **no** system-wide powers (cannot manage users, licensing, settings, or other modules). Off by default (in no role's defaults); enables selling Recruitment as a standalone product with its own scoped admin. Offer *approval* stays a manager action (`is_admin_level()`), and platform-wide `role-workspaces` stays master-only. **One-click "Recruitment-only company" preset** (`recruitment_only_provision()`, on the master-only Product-package screen) applies the `RECRUITMENT_HR` package **and** grants `hiring.admin` to the Coordinator role (via the non-destructive `role_grant_perm()`), producing a ready recruitment-only install with a scoped Recruitment Manager. **Extra ready-made letters** (`doc_tpl_seed_extra()`): Confirmation, Relieving/experience and Internship templates ship alongside Offer/Appointment (idempotent, editable, `OTHER` type) — all auto-filled from candidate data by the Document Studio. **Hiring request layer** (Phase 2 · M4) — routes `hiring-requests` / `hiring-request` (`hiringreq.php` `ops_hiring_requests()`), module-gated `mod.hiring.view` like every other hiring route. The request layer is deliberately **capability-gated, not role-gated**: raising, editing, submitting, cancelling or converting a hiring request needs **`mod.hiring.edit`** — the existing module edit right, the same one `projcosting.php` already requires before a costing may create a requisition — asked at each write (`hreq_save()`, `hreq_submit()`, `hreq_cancel()`, `hreq_to_requisition()`) and not only on the route. Adds **no new permission**. *Deciding* a request (approve/reject) is held apart from creating one: `hreq_can_decide()` = `can('mod.hiring.view')` **and** `is_admin_level()` — the module question first, so a role band cannot open a module the workspace has not bought — and the requestor (`hiring_requests.requested_by_id`) **may not decide their own request**, with a master the single stated exception (segregation of duties, mirroring the IDEMS approver≠issuer rule). Branch scope is enforced by `hreq_scope_gate()`, which checks the request **and** the branch named on the way in. Note this gate is **narrower** than the older `is_coordinator_level()` band used by the direct requisition route: the band includes Asst. Manager, to whom this matrix grants no hiring right; the capability does not. The direct requisition route (`requisition-new`, `is_coordinator_level()`) is **unchanged**; the two routes and the open policy question are recorded in `docs/adr/ADR-001-direct-requisition-path.md`. **Hiring request approval** (Phase 3 · M1) — the request is connected to the **existing** Phase-6 approval engine (`recruit_approval_requests`, which was already polymorphic: `entity` + `entity_id`). `APPR_ENTITIES` gains `HIRING_REQUEST`; **no new table, no new column and no new permission**. Deciding through a chain requires being that step's approver (`appr_can_act()` — the named user or the configured role, i.e. *configuration*, never a hard-coded job title), and then passing `appr_guard()`, which asks **entitlement first** (`licence_blocks('mod.hiring.view')`, so a master on an unlicensed workspace is refused), then branch scope (`hreq_in_scope()`), then segregation (`hreq_segregation_blocks()` — the requestor may not approve their own request, master excepted). Deciding **directly**, where no rule matched, keeps M4's rule (the module + `is_admin_level()`) and is refused while a chain is open. Two pre-existing defects were fixed for **every** entity, not just hiring requests: `/my-approvals` asked **no** module question at all (it is in neither route map) and now asks the licence; and `appr_can_act()` began with a bare `is_master()` that walked past the licence, now `is_master_of('hiring')`. The licence — not `mod.hiring.view` — is what `/my-approvals` asks, so a configured approver who legitimately holds no recruitment module (a Finance approver on an offer chain) is not locked out; narrowing the approver population is a policy question, not a defect. **Approval SLA, reminders & escalation** (Phase 3 · M3) — adds **no new permission** and **no new status**. Three things changed about who can do what. (1) **Approval policy is now gated at the write.** `appr_rule_save()`, `appr_rule_set_active()`, `appr_level_save()` and `appr_level_delete()` — which carry `sla_days`, `reminder_days`, `escalate_role` and `escalate_user_id` — previously trusted the `recruit-approvals` route for authorization, so a direct POST or AJAX call reached the policy without it. They now ask `appr_config_can()` themselves: **entitlement first** (`licence_blocks('mod.hiring.view')`, so a master on a workspace without recruitment is refused — no master bypass of entitlement), then the existing `hiring_admin_can()` capability. The population that may configure is unchanged; where the question is asked is not. (2) **The nightly approval tick** (`appr_tick()`) asks the same entitlement question itself, in addition to `cron.php`'s existing `$m8('hiring')` gate. (3) **Notification now follows M2 delegation**: `appr_step_recipients()` adds the current delegates of whoever the step names, so the person the system is waiting for is the person it writes to. This is **visibility only** — `appr_can_act()` remains the only thing that decides who may approve, and an escalation recipient gains nothing at all (`SLA ≠ authority`, `escalation ≠ authority`, `notification ≠ authority`, `inbox visibility ≠ authority`). The `/my-approvals` screen gains a read-only "waiting on someone else" list, restricted to **HIRING_REQUEST** rows whose `requested_by_id` is the viewer's own id, behind the same licence question as the rest of the route. **M3 CORRECTION** — an adversarial audit proved the notification layer broader than the approval layer, so the rule is now stated and enforced: **notification eligibility is never broader than approval visibility.** Recipients (named user, role holders, and their current M2 delegates) are only *candidates*; each is then asked the same question `/my-approvals` asks — `appr_visible()` = `appr_can_act()` + `appr_guard()` — via `appr_may_be_asked()`, which evaluates it **as that candidate** (`appr_as_user()`, session restored in a `finally`) because entitlement, branch scope and segregation are all questions about the current user. Consequences: a holder of the approver's role in **another branch** is no longer e-mailed a hiring request they cannot see or decide; a **requestor** who happens to hold the approver role is no longer asked by e-mail to approve their own request; a **named** approver outside the branch scope is refused too (being named is not a bypass); and an unlicensed workspace notifies nobody. Escalation notices use a second, lower disclosure level, `appr_may_be_told()` — entitlement, the record existing and branch scope, **without** segregation, since telling the person who raised a request that it is late is the point — so an escalation contact who cannot see the branch is not told what it concerns, and the message states that it confers no authority. `appr_role_emails()` ("every active holder of this role, anywhere") is **deleted**. Also: **approval delegation writes now ask `appr_config_can()`**, the same entitlement-then-capability gate as the approval matrix, closing an asymmetry in which a workspace without recruitment could not edit its matrix but could still move approval authority; create, edit, revoke and deactivate are all covered at the write. Adds **no new permission** and **no new status**. **Recruiter accountability — the assignment door** (Phase 3 · M5) — `recruit_assign.php`. Who is accountable for a requirement (`requisitions.recruiter_id` / `manager_id`) and who is chasing a candidate (`candidates.recruiter_id`) is no longer written by whatever form happened to post it. Every production path — `requisition-new`, `requisition-edit`, `candidate-new`, `candidate-edit` and the public careers intake — now changes ownership only through `rasg_assign()`, which asks, in this order and each failing closed: **entitlement first** (`licence_blocks('mod.hiring.view')`, with **no master bypass**), then the **existing** `is_coordinator_level()` band the recruitment write routes already require, then the record, then the **actor's** branch scope (`scope_allows`), then the requisition/candidate state, then the **M4** execution boundary, then — the questions nobody was asking before — that the person being given the work **exists in this workspace**, is **not deactivated** (`is_active` and `deactivated_at` both read) and **covers that branch themselves** (evaluated as that person via `appr_as_user()`, so the scope rule cannot drift from `ua()`). A change also carries the owner the screen was showing, and a save whose baseline no longer matches is refused rather than merged. **Adds no new permission and no new status**: the population that may assign is unchanged, what changed is that the questions are asked at the write instead of being implied by a dropdown. The public careers page **inherits** a requirement's recruiter rather than copying it, and skips **only** the permission question — an application can never hand work to somebody who has left. Ownership history is kept in an append-only `recruiter_assignments` ledger and on the activity spine, so a reassignment no longer erases who was accountable. **Visibility narrowed (same change):** the Recruitment command centre (`rcc_data()`) and the recruitment CSV export (`recruit_export.php`) built their WHERE clauses from the screen's own filters and **no branch scope at all**, so every branch's candidates, requirements and **recruiter names** were visible to anyone who could open recruitment. Both now scope like the registers beside them — requirements by `scope_clause()`, candidates through the requirement they are worked against (`scope_office_clause()`, whose "no office means everybody" rule keeps unattached candidates from vanishing) plus the module's existing `recruit_sbu_clause()`. No rule was invented; the existing ones are now applied where they were missing. **Integrated execution gate** (Phase 3 · M6) — `recruit_exec.php`. Recruitment may only SPEND an approved requirement through one composed question, `rexec_block_reason()`, which asks the existing authorities and reports the first refusal: the requirement's own lifecycle status (M3), then **M4's** execution boundary (`hreq_req_block_reason`), then — for a joining only — whether an approved seat is actually free (M3's `reqf_counts`). It is now asked by the paths that never asked before: **offer** create/submit/approve/issue/accept (`recruit_offer.php`), **interview scheduling** (`iv_schedule`), the **configured pipeline** (`recruitpipe_cand_goto`) and the **joining** itself, alongside the candidate and requisition saves M4 already covered. Consequences for who can do what: an offer can no longer be created or issued, an interview can no longer be scheduled, and a candidate can no longer be advanced, on a requirement whose approval has been invalidated or that is CANCELLED/CLOSED; and **no more people may join than were approved** — a joining written past the gate is reverted, audited and not counted. Deliberately NOT gated, and asserted as such: recording the OUTCOME of an interview that already happened (refusing it would destroy information); the NUMBER of offers, since the business runs more offers than seats because offers are declined, and only the joining consumes a seat; closing a candidate out (reject / withdraw / decline / hold), so a pending re-approval never traps a person; and ADR-001 direct candidates with no requisition, which have no approval to respect and no ceiling to apply. **Adds no new permission, no new status and no new route.** Also fixed in the same milestone: the Phase-6 approval engine's REQUISITION callback wrote `status='approved'` and `status='on_hold'` — neither is in the requisition lifecycle (`docs/03-object-lifecycles.md`), and the measured effect was that an approved requirement disappeared from the command centre's open demand and from every status-based query. The decision is now recorded in the chain, in `approved_by` and on the audit spine, and the STATUS is left to M3's `reqf_sync()`, the only thing entitled to derive it. **Capacity and incumbency — ratified business rule (invariant I21).** Recruitment must never exceed approved capacity and must never displace an established holder merely to manufacture a concurrency winner; where simultaneous claims cannot be deterministically resolved without risking displacement, the system may refuse the contested claims and leave the capacity available for a subsequent valid transaction. In practice: a joining is refused when no approved seat remains, and a joining written past that check is reverted by a compensating check that asks only whether a seat was free for the ARRIVING claim — it never re-ranks the people already seated. Two earlier attempts to guarantee a winner under a dead heat (ranking seat-holders by decision time, then by id) each let an arriving candidate displace an established one and were abandoned. The refusal never consumes the capacity: the reverted candidate returns to their prior stage, the requirement's counts are re-synced, no joining is recorded, and the seat is immediately usable by the next valid transaction. Moving a candidate who ALREADY holds a seat onto a different requirement is treated as a **joining on the destination**, not an advance, so it cannot push that requirement past its approved seats. Adds no permission and no status. **The company's own candidate pool, shared by every office — and the candidate lock** (Branch batch B6, owner's answers 16 Nov 2026; **lock rule rewritten in batch 2, owner 17 Nov 2026** — QA-REPORTS/V51/29 section C, points 7, 8, 9 and Q2; `lib/recruit_pool.php`). The candidate pool is the company's **own internal pool** (CVs received directly, from consultants, HR …) — **not** the Marketplace, which is untouched. No branch owns a candidate. (1) **Visibility.** A **Master Admin switch "Offices can see each other's candidate pool"** (setting `recruit_pool_shared`, **ON by default**; shown and saved only for `is_master()` on the Hiring workflows screen `recruit-pipelines`, an Admin who posts it is refused; the change is on the audit trail) decides what the **candidate pool** screen (`candidate-pool`), a requisition's **"Best matches from the pool"**, **search** and the Recruitment home's re-approach list show — one definition, `rpool_list_clause()`: ON = every candidate who has **not joined**, in the viewer's Business Unit; OFF = only the office's own candidates (through the requisition they are worked against, `rasg_cand_scope()`), with **unattached candidates visible to every office** (they belong to nobody). Before B6 the Best-matches query and the pool screen had **no scope at all** (DEF-REC-003). (2) **Before the lock — everything is shown to every office.** A candidate who is unattached, or linked to an office's requisition with **no interview scheduled yet**, may be opened **in full** by every office under ON (page, CV, documents; `cand_pool_may_view()`), subject only to the Business Unit rule and `data.salary` for salary figures as for any viewer. Merely being linked to a requisition does **not** lock (owner's Q2). What another office may *do* before the lock: **take them on** — link them to one of **its own** requisitions (`candidate-edit` to an in-scope requisition; the move is written on the candidate's history, "Taken on by the <office> office") and then schedule its own interview there. Nothing else (stage, interview, document, offer) is written against the other office's requisition; the refusal says how to take them on. **The first office to schedule an interview takes the lock.** (3) **The lock — from the first interview scheduled.** A candidate is *locked* with an office from the moment the **first interview** is scheduled (an interview on the Interviews panel that is not CANCELLED, recorded against the requisition it was scheduled for — `interviews.requisition_id` — of that office; or the candidate standing at an interview, offer or final stage kind, or on a configured pipeline at or after its first interview/offer/final stage), and stays locked through **offer, onboarding and joining until the process ends**. The office holding the lock = the office of the requisition the candidate is worked against. Every office sees the badge **"Locked — in recruitment with <office>"** / **"Locked — onboarding with <office>"**. Anybody whose office scope does **not** cover that office sees **only the name and the badge** in every list (pool screen, Best matches, register, search — no code, no details, no Open/Edit) and **cannot open the candidate page or use any candidate route, by any method** (`cand_lock_require()`, asked by `cand_scope_gate()` for every `ops_candidates` route and by `candidate-interview`, `candidate-doc`, `candidate-offer`/`offer-letter`, `candidate-letter`, `candidate-flow`, `candidate-pipestage`, `candidate-review`) — refused with *"<Name> is locked — in recruitment with the <office> office. Only that office can open or work on this candidate until their process ends."* At the write the same lock is asked by **one guard, `cand_other_office_block()`** (also in `iv_schedule()`, `offer_create()`, `recruitpipe_cand_goto()` and the assignment door `rasg_may_touch()` → code `IN_PROCESS_ELSEWHERE`). The lock is the **person's**: another record of the same person (same mobile, last ten digits, or same e-mail, any case) is locked with them, so a second office cannot start a parallel process on an older duplicate. **Company-wide roles** (Master Admin, Admin, Business Director … — every office in scope) are never blocked; the locking office works as normal. A person who has **joined** stays that office's record (other offices cannot open it). (4) **A second record while locked — blocked (point 8).** Creating a new candidate (`candidate-new`) with the same mobile or e-mail (normalised) as a person **locked with an office the creator does not cover** is refused, *"Save anyway"* or not, with a plain message naming the office (`cand_duplicate_lock_block()`). A look-alike that is **not** locked keeps the soft duplicate warning with "Save anyway". (5) **When the process ends** (rejected / withdrawn / offer declined / did not join / dropped) the lock is lifted and the candidate is back in **every office's pool and "Best matches"** (point 9) — a person who was hired is not offered. Another office starts with them by linking them to one of its own requisitions. Offer *approval* through a configured chain (`/my-approvals`) is an approver's step, not an office working on the candidate, and is not asked this question. (6) **Stays branch-limited (DEF-REC-003 fixed):** the Recruitment home **"Today" and "Risks"** panels (and the KPI counts beside them) are each office's own to-do list, `rasg_cand_scope()` via `recruit_worklist_clause()`. The candidate register's row set is unchanged; locked rows are masked as above. Adds **no new permission and no new status** — it only ever refuses.
    ⁸ᵃ **Business Director and Business Unit Head — Edit (owner, 16 Nov 2026, R1 "allowed to change").** Both roles are in `MGMT_ROLES`, so the `is_coordinator_level()` gates above have always let them add and edit candidates, schedule interviews, draft offers, record joining and manage positions; the cells said View and are corrected to **Edit** so the table and this footnote agree (no code changed). The Business Unit Head works only on candidates and requirements of **their own business unit** (their `sbus` reach, `recruit_sbu_clause()`). **Approving an offer stays a manager decision, `is_admin_level()`, as above.**
9. **Inspector master** — `/m/inspectors`, master config `access='admin'` → `is_admin_level()` (`ops.php:2049,2460`); allowances/rates `is_master()` (`ops.php:3418`); salary field gated `can_see_salary()` (`ops.php:3428`).
10. **Attendance** — reconcile `is_coordinator_level()` (`ops.php:6177`); **ⁱⁱ** self-mark (`attend-mark`) is intentionally ungated beyond "login linked to an inspector" (`attend.php:189`, gaps §B-4).
11. **Office overheads** — edit `is_admin_level()||can('settings.manage')` (`ops.php:6041`); global default `settings.manage||master` (`ops.php:6052`).
12. **Lookups & per-entity custom fields** — `is_admin_level()` (`lookups.php:635`); delete built-in list `is_master()` only (`lookups.php:659`). Not in the module-gate map — guarded in the handler.
13. **Custom form builder** — `cform_can_manage()` = `is_master()||can('settings.manage')` (`customforms.php:64,91`).
14. **Users** — `can('users.manage.branch')||can('users.manage.global')` (`ops.php:6608`); branch managers limited to own office (`ops.php:6618`); role-access editor `is_master()` (`ops.php:2412`). Global default = MA/AD only. **Role workspaces** (additive) — `role-workspaces` (`workspace.php` `ops_role_workspaces()`), `is_master()`: per-role landing page + a curated dashboard launchpad, stored per-tenant in the `workspace_config` setting. Every landing and tile is re-checked against the user's own permission-filtered menu (`ops_nav_index`) at render/redirect time, so a workspace **can never expose a screen the user is not already allowed to open**. Personal override `my-start` (self-service, any signed-in user) sets `users.start_route`, validated the same way. Adds **no new permission**. Data-scope hardening (same change): the dashboard "open requisitions" tile and the candidate-form requisition dropdown (`requisitions_list()`) now apply `scope_clause()` like the main list, so neither leaks other branches' requisitions.
15. **Dashboards** — landing `/` only needs login; `ops_reports` needs a `dash.*` perm (`ops.php:6416`), each tile gated individually. Inspector gets the personal branch (`dashboard.php:23`).
    ¹⁵ᵃ **The Operations home is operations work, not a dashboard (R4 / DEF-OPS-004, owner 16 Nov 2026).** The Operations home (`/operations`) and its work lists — counters, pending scheduling, backlog, schedule and assignment registers, the operations desk and capacity outlook — need the **Calls or Jobs module** or coordinator level (`tosrm_ops_desk_can()`). The *Operations dashboard* tick (`dash.operations`) alone no longer opens them: it is held by the sales roles (BDM, KAM, Marketing Manager) for their dashboard tiles, and this matrix gives them no operations right. The dashboard widgets themselves are unchanged.
16. **Settings** — `can('settings.manage')` (`ops.php:6806`); module-licence toggle `is_master()` (`ops.php:6812`); seed/reset `is_master()` (`ops.php:2531+`).
    ¹⁶ᵃ **Backup & restore** (`/backup`, added 2026-10-19 — finding A-F3, GAP-048). Opening the screen, taking a backup, downloading one and restoring **this workspace's own** backup need `settings.manage` or master (`backup.php`, `ops_backup()`): MASTER_ADMIN and ADMIN. **Restoring another workspace's backup** — which replaces this company's records *and its user accounts* with another company's — is refused to everyone except a **master administrator who types this workspace's name** to confirm (`backup_check()`). A file that is not an EXAACT backup, an unknown format, or a damaged table is refused to everyone. Every restore keeps the restorer's own account and is written to the activity log with who, which file and which workspace it came from. Before this, an ordinary administrator could upload any company's backup, or a file that was not a backup at all, and it was applied.
17. **Invoicing/Receipts** — open/view `books_can()`=finance.reconcile||data.credit||master (`booksui.php:87`) → FIN, CO, BD/SBU/BM (via data.credit), MA; all mutations `$canIssue||data.credit` (`booksui.php:190`); **issue** `books_can_issue()`=finance.reconcile||master (`booksui.php:257`) → FIN; cancel same (`booksui.php:265`).
    **CORRECTED 2026-10-18.** This row read `BD — · SBU — · BM View · CO View` and the code did not match it. Driven as each role against a throwaway database (`docs/audit-2026-10/evidence/books-write-authority.py`), a **Business Director, SBU Head, Branch Manager and Coordinator can each record money received and start a draft invoice** — a coordinator really wrote receipt RCP/2627/0001 in the test. **The owner reviewed this on 2026-10-18 and accepted the current behaviour**, so the row now says `Raise`: may open the billing workspace, record a receipt and start a draft invoice. The table was wrong, not the code.
    `Raise` is deliberately not `Full`. **Issuing** an invoice, **cancelling** one and raising a **credit note** remain `finance.reconcile || is_master()` and were each tested and refused for BD, BM and CO. Accounts still owns the issued document.
    **The mechanism is still a defect even though the outcome is accepted** (GAP-010, P2 after the owner's decision): the write gate's key is `data.credit`, whose own definition is *"See the inter-office credit figures"* (`access.php:133`) — a read permission about a figure on a job screen, standing in for a right that should say "may change the books". It is harmless today only because every money route also requires `mod.invoicing.view`; a money route added without a module gate would be writable on `data.credit` alone. The permanent fix is a named `finance.books.write` right granted to exactly the roles in this row, leaving `data.credit` to do the job it is named for. **No code was changed for this** — the owner accepted the behaviour, so only the documentation moved.
17b. **Universal man-day / man-month billing** (owner's rule, 31 Oct 2026; `lib/billunits.php`) — **adds no new permission**; each action asks a right that already existed for the same kind of act:
   - *Raise one invoice from due units* (`billable-invoice`, and "Raise invoice" on a job via `job-bill`) — `billunit_can_invoice()` = the existing job-bill gate (`finance.reconcile` / `mod.invoicing.view` / master of invoicing).
   - *A job's billing terms* (`job-billing` act=terms) — `billunit_can_set_job_terms()` = the job-edit gate, `is_coordinator_level()` / master.
   - *End a job early* (terminated / completed early / demobilised; act=end) — `billunit_can_end_early()` = the job-close right `ops.job.close` / master.
   - *Add a contractual charge, release units for billing now ("Bill now"), whether suspension is charged* (act=charge / release / suspension) — `billunit_can_adjust()` = `billable_can_manage()` = `finance.reconcile` / master (D1).
   - *A salary break-up from the CTC on a costing role* (owner, 13 Nov 2026 — `project-costing-ctc-heads`) — the costing edit right (`pc_can_edit()`), the same people who already type the heads by hand. It reads the company's salary components; changing those stays with `hiring_admin_can()` on Compensation setup. Either Save on the costing page saves the role box and the settings box together, under the same costing edit right and lock as each did alone. No permission added.
   - *Raise a hiring request / requisition from a costing* (`project-costing-to-hreq`, `project-costing-to-req`) — the existing hiring-request right (`hreq_can_create()` / `mod.hiring.edit`); offered only when the costing is APPROVED and its quotation ACCEPTED by the client (owner, 9 Nov 2026). The request is then approved by the existing hiring-request approvers; its raiser cannot approve it. No permission is added.
   - *A contract added from the client record* (MOD-REP, owner, 9 Nov 2026 — `partner-contract-new`, a pop-up over Client → Contract Numbers, and "+ New contract" on the PO form) — the **same door and gates** as before (`/partner-add?kind=contract`: Sales & CRM licensed, `crm.contract.register` or master, the client edit right and office boundary). New: a blank contract number gets the company's next number (`gen_contract_number()`, BRANCH/C/FY/NNNNN, the client's branch by default), and the contract records its branch and who asked for it, so it enters the same endorse → approve flow as the CRM path. No permission added.
   - *Billing by milestones* (owner, 12 Nov 2026 — "Milestone billing — yes, go ahead as proposed"; `lib/milestones.php`) — **no permission added**. Changing a contract's schedule and marking milestones reached also need **Money (invoicing) bought** (`billable_money_live()`, as for billing terms); the quotation's schedule needs only Sales:
     - *Set the schedule on a quotation* (`quote-milestones`) — the quotation edit right (`crm.quote.create` / `mod.quotes.edit` / master of quotes), only while the quotation may be edited (not once it has gone to the customer — that is a revision). The schedule is part of the quotation's commercial fingerprint, so changing it cancels an approval given or in progress (D-07).
     - *Change a contract's schedule* (`contract-milestones` act=save) — `crm.contract.register` / master, as for the contract's billing terms. On an **open** contract it is an **amendment** with the same two signatures as a change of value, dates or billing terms (D-25); milestones already reached never change.
     - *Mark a milestone reached / undo it* (act=reach / undo) — `ops.job.close` / master (Coordinator, Branch Manager, Operation Manager — "the coordinator or a manager"), inside the person's own branch and business unit (`user_scope_covers`). Finance does not mark milestones; it bills them.
     - *Bill a reached milestone* — the existing "Raise one invoice from due units" gate (`billunit_can_invoice()`); issuing stays `finance.reconcile` / master.
     - *Open a milestone's proof file* (`milestone-file`) — whoever may open the contract.
   - *The levels a costing will need, shown before it is submitted* (owner, 14 Nov 2026) — no permission added. Shown on the costing page to whoever may already open it; it names the people who already approve that level, which grants nothing.
   - *Reopening an APPROVED costing* (owner, 14 Nov 2026 — "only the highest authority who has last approved it" / "Any one at Z level who is delegated his work or designation above Z") — **narrower for the preparer, wider for the last approver's level:** `project-costing-reopen` on an APPROVED costing is gated by `pc_can_reopen_approved()` instead of `pc_can_edit()`: the approver of the highest level that approved it; when they are away, someone they have delegated quotation approvals to (`appr_delegates_of(…,'QUOTE')`) or someone above them in their reporting line (`reporting_chain()`). Nobody else — not a colleague at the same level without a delegation, **not the master** (owner, 14 Nov 2026: "only the highest level approver can open the costing. No one else"), never the preparer, even holding the edit right. A Branch Manager or Business Director who signed the last level may reopen it without holding the costing edit right — the owner's explicit decision. Reopening a SUBMITTED or REJECTED costing keeps `pc_can_edit()`. The quotation lock (below) still overrides everyone. Being told of a send-back (e-mail + dashboard) grants nothing.
   - *A costing locked from its quotation's approval; an editable copy for a revision* (owner, 14 Nov 2026) — no permission added. "Reopen as draft" (`project-costing-reopen`) is refused for everyone, master included, once a quotation revision the costing prices has gone for approval or beyond; edits and unlinking likewise. "✎ Change the costing for this revision" (`project-costing-revise`) is gated by the costing edit right (`pc_can_edit()`) — the same people who may already create a costing for a quotation — and works only on the current revision while it is a draft or sent back. The copy is prepared by whoever pressed it, so they cannot approve it themselves (D-11 B unchanged).
   - *A revision's costing made automatically; the costing number; the margin warning* (owner, 14 Nov 2026) — no permission added. When a quotation is revised, the costing copy (its next "Rev") is made only if the person revising already holds the costing edit right (`pc_can_edit()`); otherwise a colleague with that right uses "✎ Change the costing for this revision" as before. The copy is prepared by whoever revised, so they cannot approve it (D-11 B unchanged). The costing number and the margin warning are displays — shown to whoever may already open the costing; they grant nothing and block nothing.
   - *An enquiry's outcome follows its quotation; Dropped with a reason* (owner, 13 Nov 2026) — no permission added. Won / Lost / Quoted are set by the system from the enquiry's quotations; nobody gains the power to set them (before, anyone with `mod.inquiries.edit` could set any status from the form — that is now narrower). Dropping (`inquiry-drop`, reason required, only with no quotation) and reopening (`inquiry-reopen`) need `mod.inquiries.edit` and the enquiry's branch — the people who could already set "Dropped" from the dropdown.
   - *A costing's approval trail; "Create quotation" only once approved* (owner, 13 Nov 2026) — no permission added. Every step is recorded on the costing's history and shown on the costing page to whoever may already open the costing; it names people, which grants nothing. "Create quotation" is drawn only for a costing in APPROVED — the gate on `project-costing-to-quote` (`mod.quotes.edit` + APPROVED) is unchanged; before, the button was drawn at every stage to everyone holding `mod.quotes.edit` (the author and approvers included) and the route refused it. Who may approve, and whether levels sign in order or together, are unchanged.
   - *Unlinking a costing from a quotation or deal* (`project-costing-attach`) — gate unchanged; every link and unlink is now written to the costing's activity log and to the quotation's history, and an unlinked costing is listed under Project costing → "Not on a quotation" and in "attach existing…" (branch scope as the register). **From 13 Nov 2026 the unlink is refused for everyone — master included — once the quotation has gone to the client, been won or has a contract (a deal: Won, with Accounts, or with a contract).** Nobody gains anything: the same people may link and unlink, only while the quotation is still a draft or in approval.
   - *The rest of the lead-to-contract walk (owner, 13 Nov 2026: "Go ahead with 2.1 and the rest of the bounce list") — no permission is created and nobody gains an action.* (a) **A deal quotes with the customer name it has** (`opp_create_quote()`): the gate is unchanged (`opp_can_edit()` on `POST /opportunity-new-quote`); what is dropped is the demand for a client-master record first. The customer record is still created only by acceptance (`quote_land_on_client()`), by whoever may already mark a quotation accepted. (b) **Naming who takes the next step** (`crm_people_who_can()`): an approved quotation names who may send it (`crm.quote.send`) to someone who may not; a won one names Accounts (`crm.contract.register`) to Sales. It reads who already holds the right — being named grants nothing; administrators are named last. (c) **No link to a page that refuses**: the quotation's link to its deal or lead (`crm_quote_deal_link()`, `crm_quote_lead_link()`) and the Lead / Opportunity tiles of the record strip (`chain_strip()`) are links only for `mod.leads.view` (and, for a deal, the visitor's branch scope) — the same right the page's own door checks. Finance, who register the contract but may not open the pipeline, see the reference as plain text. This narrows what is drawn; it widens nothing. (d) **One press for Accounts** in the amber box of a won quotation posts the existing `quote-contract` with `auto_contract=1` — same gate (`crm.contract.register`), same PENDING → signatures path. (e) **"Contracts opened — raise the work order"** on the dashboard and on `/raise-call` (`contracts_awaiting_first_call()`): shown to `ops.call.create`/master, inside the visitor's branch scope — the people who may already raise a work order from an open contract.
   - *Discipline on a login* (MOD-REP, owner, 9 Nov 2026 — "Inspector – Mechanical / Electrical") — the user form's existing gate (who may add or edit logins); the choice is saved as the linked **team member's** trade (`inspectors.trade_id`, Masters → Trade / discipline), or on the login itself when it has no team record. No permission added.
   - *Recruitment, owner's notes of 9 Nov 2026* — no permission added: interview scheduling, results and re-interviews stay `is_coordinator_level()` (`candidate-interview`); dropping a candidate and "did not join" stay the stage-move right `is_coordinator_level()` (`candidate-stage`), now with a required reason; joining stays `candidate-joined` (`is_coordinator_level()`) and also files the person under their department. Rules: docs/03, Candidate and Interview.
   - *A hired person's cost against the costing* (Part 3, owner, 9 Nov 2026) — no permission added. The candidate's Offer tab and the costing page's recruitment table compare the candidate's actual monthly CTC (offer, else salary structure) with the costed role at the quoted rate. Pay figures are shown only with `data.salary` (`can_see_salary()`); others see the margin verdict only. Submitting an offer (`is_coordinator_level()`, unchanged) whose CTC takes the role below the costing's target margin needs a written reason, kept on the offer (`job_offers.margin_note`) for the approver.
   - *Insights screens follow the dashboard ticks* (owner, 10 Nov 2026 — a Finance login with only "Financial dashboard" saw every dashboard). No permission added; existing rights now decide each screen: **Analytics & performance** (`/analytics`) shows only the views a person's ticks cover — Executive needs Operations **and** Financial, Operations/Service/Vendor/Client need Operations, Quality needs Operations or People & compliance (`tapi_dashboards_for_me()`); the hub opens only when at least one view is theirs. **Management dashboard** (`/mis`) needs Operations **and** Financial, or admin level (`mis_can()`). The **Platform** health light on the area pages shows only to people who may open `/system-status` (`notifications_can_view()`). The Dashboards screen (`/reports`) already showed each section by its own tick. The **Sales dashboard** follows Sales → "Sales reports: View" (`mod.crm_reports.view`), which the Finance role holds by default.
   - *Marketplace, owner's notes of 10 Nov 2026* — no permission added. Changing a posted requirement's details uses the gate that already posts and awards it (`connect_market_can()`, coordinator/master). The verification desk's new "Open file" link streams a professional's uploaded document only through the desk's existing gate (`connect_verify_can()`) and only for a person who has a verification waiting on the desk; each opening is logged. Owner Home now has one Marketplace switch — the "Marketplace" module tick sets `connect_enabled` — instead of two; who may use it (the owner) is unchanged. Agency bench: the "add an organisation" link is shown only to masters, who already hold that screen.
   - *Marketplace replacement* (owner, 11 Nov 2026) — no permission added. Giving an awarded job to someone else uses the gates that already award it: staff `connect_market_can()` on `/connect-requirement`; the client who posted it under `market.post` on `portal/hire-req` (ownership re-checked). A client may invite or a coordinator may put forward people onto an awarded job only while its booking has ended without a replacement.
   - *Marketplace closing dates* (owner, 11 Nov 2026) — no permission added. Extending or changing a requirement's closing date uses the gates that already manage it (staff `connect_market_can()`; the posting client under `market.post`, own requirements only). How long requirements stay open and when the reminder goes are master-only settings (`is_master()`), like the commission rate beside them.
   - *Requisition → "Post this to the Marketplace"* (owner, 11 Nov 2026 — "Yes if the user is subscribed and paid for market place or has bought any pack") — no permission added. The button shows, and works, only when the company has the paid Marketplace module and has it switched on, and the person holds the desk's existing posting right (`connect_market_can()`: licence `connect` + switch + coordinator/master). It makes a **draft** the coordinator checks and posts; the client's name and the billing rate are never copied, and it is posted as the company's own work, so it does not appear in the client's portal. (Credit packs belong to portal clients posting their own requirements, where `mkt_can_use('CLIENT', …, 'posts')` already applies.)
   - *One Marketplace commission* (owner, 11 Nov 2026 — "If practically that is feasible … do the best possible") — the platform commission is **one rate**, charged once, on the professional's fee only (never on expenses), set by a **master** on Marketplace requirements (`connect_commission_pct`), with a new master-only **client share** (`connect_commission_client_share`, default 50 = the even split always used; the rest comes out of the professional's fee). Escrow no longer has its own rate: `mkt_escrow_commission_pct()` reads the one rate, and the escrow screen's separate field — which any desk user (`connect_market_can()`) could change — is removed. This **narrows** who can change a commission (now master only); no right is added.
   - *Quotation approval — a unit's level* (owner, 12 Nov 2026) — no permission added. A level set for one business unit now also applies to a quotation whose lines are in that unit, and its step is signed only by the people whose data scope covers that unit (19h, applied to the step's own unit instead of the quotation's primary unit). Guard rail `tests/test_quote_unit_from_lines.php`.
   - *Waiting approvals follow a changed approver* (owner, 12 Nov 2026) — no permission added. Whoever may already change an approval setup (Set up Sales levels; recruitment approval levels; deal-stage gate rules; report approval rules and the approver map) now also moves the steps still waiting at that level to the new approver; the new approver then holds those steps exactly as if the item had been submitted after the change. Decided steps are not touched. Guard rail `tests/test_approval_follows_new_approver.php`.
   - *Approvals on every dashboard; a company's own roles in approvals* (owner, 12 Nov 2026 — "All approval pending under any designations or level must show as pending task on their individual dashboard"). (1) "Your pending tasks" now lists every approval queue the person can act on — quotations, project costings, recruitment chains, deals held at a stage gate, lead reassignments — from the same list as the Approvals page (`approvals_hub_sections()`); no one sees an item they could not already act on. (2) An approval addressed to a role is also open to holders of a **custom role built on that role** (Roles & access → custom role, base = that role) — `approver_role_keys()` / `user_holds_approver_role()`, used by quotation, costing, recruitment, stage-gate and report approvals. A custom role already takes its base role's permissions; it now also takes its approvals, never another role's. (3) Approval levels in Set up Sales may name a custom role directly. (4) A level whose role nobody active holds now says so on the quotation instead of "any approver".
   - *Company letterhead on documents* — no permission change: the invoice, offer and appointment letters, costing sheet and expense voucher print the company identity from Admin → Company profile (or the quote letterhead) through one helper (`doc_identity()` / `doc_letterhead_html()`); each document keeps its own gate.
   - *Flexible costing heads* (owner, 9 Nov 2026 — `lib/costing_heads.php`). **Editing a role's heads** (amount / % of other heads / quantity × rate / formula; per month, day, year or one time; remarks; minimum / maximum) is part of editing the costing — the existing `pc_can_edit()` on an unlocked costing (`project-costing-line-save`); the live figures (`project-costing-line-preview`) save nothing and need the same costing access (`pc_can()` + branch scope). **The costing-head library, its dated values, the working days in a month and the templates** (`project-costing-heads`) are Masters-level setup — the "Costing head" Masters list grew into this screen — so they keep the **Lookups gate, `is_admin_level()` (row 12)**, via `ch_can_manage()`; everyone with costing access may read the screen and use the library and templates on a role. A value is copied into a role when it is added, so changing the library never changes a costing already made. No permission is added.
   - *A contract's billing terms* (`contract-billing`) — requested by `billunit_can_set_contract_terms()` = `crm.contract.register` / master. **Owner's answer, 8 Nov 2026 (Option 1):** on an **OPEN** contract a change of billing terms is a D-25 amendment — it needs a reason and the same two signatures (endorse `can_endorse_contract_open()`, approve `can_approve_contract_open()`, approver ≠ endorser unless master); the current terms stay in force until then (`billunit_contract_terms_request()`, applied by `contract_amendment_apply()`). A contract not yet open is changed directly (an endorsement already given is withdrawn). No permission is added.
   - BILLED is never set by hand: units are billed only by an issued invoice line; the "Mark billed" attestation is refused for units (`billable_mark_billed`).
   - Viewing the job's Billing panel follows the job page; the board keeps `billable_can()`.
18. **IDEMS reports** — view `mod.idems.view` (`ops.php:2290`); create/edit `mod.idems.edit` + `idems_can_edit_doc` (`idems.php:4185,3467`); approve step `idems_can_act_step` (`idems.php:5732`); finalise `is_master()||idems.finalize` with **approver≠issuer** (`idems_issue_blocked_for_approver()`) — a company switch `idems_sod_issue`, ON by default, which only `settings.manage` may turn off (owner, 31 Oct 2026; no new permission; a same-person issue is audited as `SAME_PERSON_ISSUE`); type/format config `idems.type.manage` (`idems.php:4604+`); timestamps `idems.timestamp.edit` → BAM (`idems.php:7357`); audit log `idems.audit.view` (`idems.php:9772`).
    **Where the report set-up screens are reached (MOD-REP, owner, 9 Nov 2026) — no permission changed.** Admin → *Report configuration* now has tiles for **Report types** (`/report-types`: `is_master() || idems.type.manage || master.manage`), **IRN numbering rules** (`/irn-rules`: `is_master() || idems.type.manage`) and the **Phrase library** (`/phrase-library`: `is_master() || idems.type.manage || master.manage`) — each tile shown to exactly the people its own handler already admits (and only with Reporting licensed). The register's header buttons stay. The former Reporting → *Governance* tab (`/compliance`, `/incidents`, `/consents`, `/data-requests` — data-protection and cyber-security obligations, not reports) moved to Admin → *Data protection & security*; gate unchanged, `is_master() || settings.manage`. This also makes `docs/01-roles.md` and `04-flows/branch_app_manager.md` ("via Admin tiles") true of the code.
    **Branch reach, own reports and reopening (owner, 16 Nov 2026 — `QA-REPORTS/V51/27` B4, B5, R2, Q6, C2/0.3, Reporting A3). No new permission; each line brings the app back to this matrix or follows the owner's own words:**
    - **Which offices (B4, DEF-REP-015).** A report is opened — page, register, PDF, Word, files — and edited — details, body, submit — by its **issuing** office (`report_docs.office_id`) or the **executing** office of its work order / job. The **contracting** office of that work order may also open it and, **only before issue** (Q6), edit it; every such edit is written to the report's History (*"Edited by Priya Shah (Mumbai — contracting office): Location, Remarks changed"*). Anyone else is refused, including on the typed edit / fill address that used to open. Saving the edit form no longer wipes the client and vendor. Issue and approve rules are unchanged (`idems_doc_access()`, `idems_can_edit_doc()`).
    - **Starting from a work order (B5, DEF-REP-007).** *New inspection report → Start from an inspection call* offers only work orders whose executing or contracting office is in the person's scope, and `?call=` / `?job=` for any other loads nothing.
    - **Inspector = Edit (own) (R2, DEF-REP-001).** An Inspector (or Senior Inspector) edits only reports where they are the inspector, or which they created (`idems_is_own_report()`). Coordinators and above keep the column above.
    - **Sending a report back to the inspector (C2 / 0.3).** After submission, approval or issue the inspector edits again only if a **Coordinator-or-above of the executing office** who holds the report right in this table (Edit or Finalise — so not BD "View" nor BAM "config only"), or the person acting for them through the existing **Approval delegation**, sends it back with a mandatory reason: submitted / at vetting / under review → Draft, and approved → Draft (the approval chain then runs again). An issued report is corrected only by a **new revision** (R1, R2 …), started by the same person (Reporting A3, DEF-REP-002 — the route is now wired). Lifecycle: `docs/03`, *Inspection report*.
    **Report batch of 17 Nov 2026 (owner's go-ahead on `QA-REPORTS/V51/29` — Change 1, points 4, 5, 6 and §B). No new permission and no new status; each line narrows a right or follows the owner's own words:**
    - **A rejected or sent-back report leaves the later approvers (Change 1, DEF-REP-016).** An approval step is acted on — approve, reject, send back, delegate — only while the report is **Under review** (`idems_can_act_step()`, `ops_idems_approve()`); anything else, an old browser tab included, is refused with a plain message and nothing changes. A reject or send-back closes every approval step still waiting after it (`idems_close_later_steps()`), so the report leaves those approvers' pending list, dashboard count, inbox and overdue reminders, and the History reads *"Rejected at level 1 by Neha Shah: <reason>"* with the closed levels named. Every "waiting on me" query, the approver re-routes and the SLA reminder require the report to be Under review. Resubmitting rebuilds the chain from level 1, as before. Who approves, the levels and approver ≠ issuer are unchanged.
    - **Revision of an issued report — also the issuing office (point 4).** Besides the executing office's Coordinator-or-above (above), a **Coordinator-or-above of the ISSUING office** (`report_docs.office_id`), when it is a different office, may start a new revision, and so may the person acting for them through Approval delegation (the same rule as sending back; `idems_revision_actor()`). The History names which office.
    - **Inspectors read only their own reports (point 5).** An Inspector or Senior Inspector opens — page, PDF, Word, photos and files, register, release-note register, search — only the reports where they are the inspector or which they created (`idems_doc_access()` → `idems_inspector_may_read()`, `idems_inspector_own_sql()`). Kept, because the work needs it: a report whose approval chain names them as approver (or delegate), and, for a holder of `idems.finalize` (Senior Inspector "Finalise"), the reports that vetting / issue right covers. Coordinators and above unchanged.
    - **The contracting office does not submit (point 6).** It still opens and, before submission, edits a report (Q6, each edit in the History), but it has no Submit button and a typed submit is refused (`idems_can_submit_doc()`). The issuing or executing office submits — and the report's own inspector, wherever they are posted.
    - **"Start next stage report" (§B, stage inspection reports).** On an **approved or issued** report (not a Release Note, not one superseded by a revision) the button `document-next-stage` is offered to: **(a)** an Inspector allocated to a job on the **same work order** (`call_id`) — the next stage is theirs, from their job page (they may not open the earlier report itself, point 5); **(b)** a **Coordinator-or-above of the issuing or executing office** holding the report right in this table (Edit or Finalise — not BD "View", not BAM "config only"), who picks the inspector from those allocated to jobs on that work order, else the executing office's inspectors. Nobody else (not the contracting office, not another branch, not an unallocated inspector). It creates a new report — the inspector's own (inspector on it; creator = whoever pressed) — and gives no right over the earlier report, which never changes. Lifecycle: `docs/03`, *Inspection report*.
19. **CRM modules** — module gates `mod.inquiries/quotes/crm_orders/crm_reports.*`; fine-grained `crm.quote.create/approve/send`, `crm.template.manage` per `access.php:456-461`. Quote-approve default = MARKETING_MANAGER only (`access.php:459`); **not** BRANCH_MANAGER.
    **Owner's rules for quotations (20 Oct 2026, Sales audit D-02…D-08) — enforced at the server (`crm_quote_transition_problem()`, `lib/crm.php`), not only by the buttons:**
    - **Approval is what the company's approval rules say.** A quotation needs approval before it is sent only when a rule covers its value, or the company's answer for an uncovered value (`quote_unmatched_approval`: **the highest level's approvers — the default since the owner's answer of 20 Oct** · any approver · no approval) asks for one. Rule values are measured **before tax**. *Send* still needs `crm.quote.send`; a quotation that needs approval can be sent only once APPROVED.
    - **Who approves** is the rule's named person or role, as before. A person who holds that authority **may approve a quotation they prepared** (a Branch Manager preparing in place of a Sales Executive; a one-person company); a lower level never can, because the step is not addressed to them. Each such approval is recorded as a *self-approval* in the history. An approval step is signed only while the quotation is awaiting approval, and only by an approver whose **branch scope** covers the quotation.
    - **Any change to the commercial terms or the terms & conditions cancels an approval** (given or in progress): the quotation returns to DRAFT and its steps are removed, with the old and new totals in the history. Contact person, subject, letter and notes are not commercial.
    - **Recording the customer's decision** (accepted / lost) belongs to **the person assigned to the quotation** — its owner, else whoever raised it — or a master. ACCEPTED is final; LOST needs a reason. Nobody types in EXPIRED, REJECTED or DRAFT: the sweep, the approver and a commercial edit set those. *This is not a new right* — it narrows who may use the existing `quote-status` route (Finance, Branch Manager and Business Director could previously record acceptance with view access alone, Sales audit SA-03).
    - **Owner's quotation notes, 14 Nov 2026 — no permission added; one route narrowed to match this matrix:**
      (a) *"Submit for approval" is drawn only for someone the server lets submit* (`crm_quote_can_work()` — the quote's editors or the person assigned) and only when nothing would refuse it; otherwise the page shows the reason, or who submits it. Before, a Branch Manager was shown the button and refused. The Approve button likewise appears only when it is that level's turn.
      (b) *Top management signs last* (`appr_open_levels()`, quotations and costings alike): a Business Director level — or, without one, the highest level — signs only after every other level, whatever its level number or the "one after another / at the same time" answer; at the same time, nobody signs before the people in the chain who report to them. Who approves each level is unchanged; only when.
      (c) *Sending is `crm.quote.send`, everywhere.* `quote-compose` (open in Gmail / the Outlook .eml / mailto) used to admit anyone with `crm.quote.create`, so a Marketing Executive — who "Cannot: send or approve quotes" — was offered "Send from my mailbox"; it now asks `crm.quote.send` like `quote-status → SENT`. **Narrower for the Marketing Executive; nobody gains anything.** "✓ I have sent it — mark sent" is the same `quote-status → SENT` (same gate), marking it sent without the system e-mailing the client a second time.
      (d) Display only: one "📎 Documents" and one back button on the quotation page; a jump from "On this quote" lands with its section in view.
    - **Draft copies and the Word copy (owner, 24 Nov 2026, Q-05 — "Yes as per recommendation") — no permission added; one download narrowed.** Everyone who may open a quotation may still download its PDF (managers read drafts); before it has cleared its approval every page is marked "DRAFT — NOT FOR CLIENT". The editable **Word** copy (`quote-doc`) is offered only once it has cleared, and only to holders of `crm.quote.send` (the existing send right) or a master — **narrower: a preparer, approver or viewer without the send right no longer downloads Word**. Every PDF and Word download is written to the quotation's History. "Send from my mailbox" (`quote-compose`) is refused before approval like `quote-status → SENT`; the approval rules are unchanged. **26 Nov 2026 (owner: "go ahead and fix everything as recommended") — no permission added, two doors narrowed:** a draft PDF is printed **unsigned** ("Unsigned draft" in place of the signature image); an earlier revision prints "SUPERSEDED — see Rev NN" on every page, and its Word copy and "Send from my mailbox" are refused for everyone, master included. A contract number on the Quotes register, the quote's Contract panel and Sales → Orders & payments is a link to the contract page only for people who may already open it (`contract_can_view()`, the page's own gate); others see the number as before.
    - **Owner's decisions, 14 Nov 2026 (second batch) — one rule narrowed by the owner's choice; no permission added:**
      (a) *Assigned work — option C: "The assigned person and the people they report to can work on it; others can view."* An enquiry with an assignee (`crm_inquiries.assigned_to`) and a quotation with an owner (`owner_id`, else its creator) are worked — enquiry save, drop/reopen, making a quotation from it; quotation edit, submit, revise, send (incl. Gmail/Outlook), mark lost — only by that person and the people above them in their reporting line (`reporting_chain()`), always within the rights they already hold (`crm_assignee_allows()`, `crm_inquiry_can_work()`, `crm_quote_can_work()`). Everyone else who may see it views it, with a line saying who works on it. Unassigned, or assigned to someone no longer active: the team, as before. The master always may. **Narrower for colleagues outside the assignee's line; nobody gains anything.** A quotation made from an assigned enquiry is assigned to the enquiry's assignee. Approving and recording the customer's answer are unchanged.
      (b) *The coordinator's newly opened contracts* — "No work yet — remind me on <date>" (`POST /raise-call do=snooze`, `contract_fresh_snooze()`) is gated exactly like the list: `ops.call.create` (or master) and the contract within the person's scope. A contract shows from its start date to its end date. No new permission.
      (c) Display only: the enquiry register's button follows the enquiry (Open → Quote; Quoted → open its quotation, "+ another"; Won → open it; Lost → Quote again; Dropped → none), each gated as before (`mod.quotes.edit` / `mod.quotes.view`) plus (a). The quotation's Costing tab and its popup open the costing for those who may already (`pc_can`/`pc_can_edit`); `/quote-costing-card` and `/quote-costing-lines` are read-only and gated like the quotation's edit screen.
    - **Costing, owner's notes of 14 Nov 2026 (SAL-188 testing) — one route narrowed; no permission added:** *Taking a submitted costing back out of approval* (`project-costing-reopen` on SUBMITTED) is now **its preparer's only** (`pc_is_author()`); before, everyone with the costing edit right — approvers included — had "Reopen as draft" ("Every approver has a button to reopen the draft"). Approvers send it back, with a reason, as before. An APPROVED costing stays with the last approver's level (`pc_can_reopen_approved()`, unchanged); DRAFT/REJECTED unchanged. The list of approval levels at the top of a submitted costing is a display — it grants nothing.
    - A superseded revision cannot move; a contract is registered only on the current revision of an ACCEPTED quotation (`crm.contract.register`, unchanged). A procurement-portal registration (`/quote-external`) is recorded as SENT or LOST only.

19a. **Set up Sales in plain questions** (`/sales-setup`, `lib/setupqa.php`, owner's request 2026-10-20) — module `quotes`; answering needs `crm.quote.approve`, `settings.manage` or a master: **the same people who may already change the quotation approval rules and the pre-order checklist. Adds no permission.** Each answer writes a setting the code already reads: `quote_unmatched_approval` (a value no rule covers: highest level — default · any approver · none), `quote_self_approval` (may the preparer approve their own quotation within their own authority — default *yes*, the owner's D-06), `preorder_checklist_on` / `preorder_checklist_require`, `quote_manmonth_costing` (must a man-month quotation come from an approved project costing — default *yes*, the owner's D-11 A). Every change is written to the activity log in the words of the question. The set also asks **after how many days a quotation waiting at one approval level is opened to the next level up** (`quote_approval_escalate_days`: never — default · 1 · 2 · 3 · 7; owner's D-11a, 2026-10-20). An escalated step may then be signed by its own approvers **or** by the approvers of the next level in the rules (the step stores `escalate_role` / `escalate_user_id`); this widens *which step* a person who already holds `crm.quote.approve` may sign — it grants no permission to anyone who does not. **Levels edited in place (owner, SAL-002, 2026-10-20):** the quotation approval levels are added, changed and removed on Set up Sales itself, through the same routes and the same check as the rule screen (`crm.quote.approve` or a master) — a person who may answer the questions but not change rules sees the levels read-only. The page also states every other Sales approval and who gives it (deal-stage rules, project costing, contract opening and amendment, reopening). **No permission is added.** Same day: a level for **one business unit** was stored as "any" (form `SBU`, save and matcher `Business Unit`) and so applied to every quotation; `crm_rule_is_unit()` now reads both and the save stores `SBU`. Guard rail `tests/test_sales_setup_levels.php`.
    **Who covers for a quotation approver** (`/quote-delegations`, module `quotes`, owner's D-11a): an approver who is away names a delegate, from a date to a date, with a reason. It reuses the existing approval delegations (`approval_delegations`, entity `QUOTE`, or blank = everything). Who may set one: a master, `settings.manage`, or `crm.quote.approve` for their own approvals — the same people who answer Set up Sales. A delegate signs only within the branch scope they already have, and is recorded as signing *for* the delegator. **Levels sign in order** (owner's D-05): a level-2 step is refused until every level-1 step has signed; a rejection at any level still ends the chain.
    **Project costing — the author never approves** (owner's D-11 B, 2026-10-20: "No"): who may approve a costing is unchanged (`pc_can_approve()`: a master, admin level, or `crm.quote.approve`), but **the person who prepared it is refused**, whatever their role (`pc_is_author()`). A costing-based quotation's prices may be changed away from its costing **only by the quotation's creator** (or a master of quotations), with a note (D-11 A). Both narrow existing rights; **no permission is added**.
    **Contract-review checklist waiver** (owner's D-10, 2026-10-20): who may tick is unchanged; a checklist that cannot be completed may be **waived, with a reason, by someone who may approve that quotation** — a master of quotations, a holder of `crm.quote.approve`, or the approver it is routed to — never by its own preparer when the company has answered *no* to self-approval (D-06). The reason, the name and the date show on the quotation and in its history. **No permission is added.**

19d. **Industry templates — the owner's answer, 2026-10-20 (D-29, "Yes"):** *adding a template's pipelines* (`/industry` → **Add these pipelines**) stays with whoever could apply a template before (`industry_can()`: `settings.manage`, `mod.settings.view` or a master of settings) and **now changes only pipelines and list values**. *Making it the company's industry* — the wording everyone sees and which accreditation rules (ISO/IEC 17020 / 17025) run — is **administrators only** (`industry_can_company()`: `settings.manage` or a master of settings, with Sales or Operations in the plan), after a confirmation that lists each change. A `mod.settings.view` holder such as the Business Director **loses** the ability to re-word the application or switch accreditation off. The first-time setup wizard (an administrator) still does both. **No permission is added.**

19e. **Contract amendments — the owner's answer, 2026-10-20 (D-25, "Yes"):** a change to an **open** contract's value, dates or quantities is requested by whoever may edit contracts today (`crm.contract.register` or a master) and takes **the same two signatures as opening a contract**: endorsed by `can_endorse_contract_open()` and approved by `can_approve_contract_open()`, the approver someone other than the endorser (unless a master). Rejecting needs either right and a reason. Re-opening is refused unless the contract is closed or rejected. **No permission is added; an edit right that used to change an open contract's figures alone now needs the two signers.** From 8 Nov 2026 the same applies to an open contract's **billing terms** (owner, Option 1).

19f. **Reopening a lost lead or a closed deal — the owner's answer, 2026-10-20 (D-22, "Yes"):** "a manager" may reopen, with a reason that is logged — someone who already holds sales-manager authority: `mod.leads.edit` (configures the pipelines), `crm.quote.approve` (quotation approvers — "sales managers / designated approvers"), or a master of leads (`leads_can_reopen()`). Branch scope applies as to any lead or deal. A won deal already handed on as an order or contract is not reopened. **No permission is added.**

19g. **Marking a lead lost and reassigning it — the owner's answer, 2026-10-20:** *"if lead is lost the responsible person must mark as lost with a valid reason and remarks. Reassigning shall be done only after manager approval."* Marking lost is **narrowed** to the lead's owner (anyone who may edit leads when it has no owner; a master always), with a listed reason and remarks. Changing the owner of an owned lead is **narrowed** to a request that a manager over the lead approves — the owner's reporting manager, or a holder of `mod.leads.edit` / `crm.quote.approve` whose data scope covers the lead (`lead_user_is_manager_for()`); such a manager reassigns directly. Approving needs **no lead-register right** (a Branch Manager approving for their Key Accounts Manager has none): it is decided from Approvals, where only the person the request waits on sees it. **No permission is added.** Lifecycle in `docs/03-object-lifecycles.md`; guard rail `tests/test_lead_lost_reassign.php`.

19h. **Approvals follow the organisation — 2026-10-20 (owner's organisation chart):** a quotation approval level addressed to a **role** goes to the people in that role whose data scope covers the quotation's branch **and** business unit — the Branch Manager over that branch, the SBU Head of that unit — not to everyone in the role company-wide (who were all e-mailed and could all sign). If nobody in the role covers it, it falls back to everyone in the role so nothing is stuck, and Set up Sales lists such gaps. A level addressed to a named person is unaffected. The Approvals inbox now also lists quotations at the viewer's level and lead reassignments. **This narrows who may sign; no permission is added.** `crm_role_approvers_for()`, `user_scope_of()`; guard rail `tests/test_approval_follows_org.php`.

19i. **Project costing approval follows the quotation levels — the owner's answer, 2026-10-20:** *"approval level as of quotation shall be applicable."* Who may approve a costing was anyone with `crm.quote.approve` or admin level (never its author). It is **narrowed** to the person the costing's current level waits for — the same levels, routing and coverage as a quotation of that value (19h); its author still never signs (D-11 B). A costing must be tagged with an enquiry or lead (or the deal / quotation / requisition it came from). Decided on the costing or from Approvals. **No permission is added.** `pc_build_approvals()`, `pc_can_act()`; guard rail `tests/test_costing_levels.php`.

19j. **The field screen is for field roles — 2026-10-20 (owner's screenshot):** the inspector's phone-first screen (My jobs, My vouchers, site check-in) replaced the whole menu of anyone below coordinator level who was linked to a team member — and since every login must belong to a team member, that was every Business Development Manager, Key Accounts Manager, Marketing Executive, Finance and Quality/Technical Head created through Add a person. It now follows the role only: Inspector and Senior Inspector (or a custom role built on them). **No permission changes; what each role may open is as this matrix says.** `is_field_inspector()`; guard rail `tests/test_desk_roles_keep_desk_menu.php`.
19l. **Each screen follows its own tick — 2026-10-24 (owner, test book SAL-000: "if we add any other permission or delete any permission it still remains default").** Adding a tick always worked; removing one did not reach four screens, because they opened on some *other* right as well. Narrowed to this matrix, no right added to any role:
    - **Simple Sales screens** (plan 42 step C, owner 18 Nov 2026 — the owner's Excel CRM pattern): **Sales home** `/sales-home`, **Today** `/sales-today`, **Pipeline board** `/sales-board` open on the existing Leads / Opportunities view right (`sx_can()` = Sales licensed and `leads_can_view() || opp_can_view()`); **Sales dashboard (new)** `/sales-dashboard` on **Sales reports** (`crmdash_can()`), like `/crm-dashboard`. They read the existing records through their own office-scoped readers (`leads_all`, `opp_all`, receivables `ar_rows` only when `ar_can()`), so nobody sees a record their register would not show. A person sees **their own** list; only management roles (`is_admin_level()`) or the Master Admin may pick another salesperson or "Everyone". **No new permission.**
    - **One firm record — Sales view of the client register** (owner, 18 Nov 2026: "the firm shall be universal, opened from Sales as well as from Directory"). A client, vendor and sub-contractor are tick-boxes on ONE `business_partners` record; there is one registration form (`/partner-new`, opened from Directory, Sales and Operations' "+ Add") and one contact form (`partner-add kind=contact`, now with a **Buying role** from the editable `contact_buying_role` list). The Sales area's **Client register** tile opens the same `/clients` register with `?view=sales` columns (open deals, pipeline, won this FY, last contact; **amount owed only when `ar_can()`**). It needs the existing `mod.clients.view` (the register's own gate) and Sales (`sx_can()`); deals come through `opp_all()` so the deal register's office scope applies. Every way a firm is created — form, lead → customer, deal → customer, quote for a new name, branch company, Excel import, Connect registration — numbers it by the one rule `gen_partner_code` (office-year-name-00000); existing numbers are never rewritten. The old quick-add box (`/quick-add?kind=client|vendor`) no longer creates a firm and points to the form. **No new permission, no new status.**
    - **Simple Sales parts 2–3** (owner, 18 Nov 2026). All need Sales (`sx_can()`), as part 1. **New lead** `/sales-lead` needs `leads_can_edit()` (the lead register's own edit right) and saves through `lead_create()`; the owner is the office's salesperson from the setting `sales_owner_by_office`, else the person typing; only management (`is_admin_level()` / Master Admin) may hand it to someone else. **Log a conversation** — ONE form (`views/ops/_log_form.php`, owner 19 Nov 2026) on the lead page and the deal page; Today's "📝 Log" opens it on the record. It posts to `/sales-log`, which needs `leads_can_edit()` for a lead or `opp_can_edit()` for a deal and writes the record's own timeline (`lead_log_contact()` / `act_log`); a GET of `/sales-log` only sends the person to the record's form. **Orders & payments** `/sales-orders`: billed / received / overdue only when `ar_can()`; quotations outside the person's offices are left out (`scope_allows`). **Renewals** `/sales-renewals` reads `partner_contracts`. **Targets** `/sales-targets`: everyone sees their own row; only management with **Sales reports** (`sx_can_see_all() && crmdash_can()`) sets them; every change is a new `sales_targets` row with the figure before it (history kept, latest counts); the salesperson sees each change before → after on their Sales home until they press "OK, seen" (their own rows only) and is e-mailed what changed. **Sales settings** `/sales-settings`: `is_master() || settings.manage` (the same gate as other company settings); outcome → working days, weekly off, stale days and office → salesperson are settings (example values marked); changes go on the settings audit chain. The quote screen shows how its GST splits (CGST + SGST or IGST, `books_is_igst`) — display only, no figure changes. **No new permission, no new status.**
    - **Sales test book fixes (19 Nov 2026, test book SALA / SALB / SALC) — office reach made the same everywhere; no right added to any role.** *Orders & payments*: a person who may see receivables (`ar_can()`) but is not management (Finance) sees every order **of their offices**, not only "their own" (they own none). *Renewals* (screen, Today, Sales home) list only contracts of the person's offices (`scope_office_allows` on the contract's branch, else its client's home branch). *Pipeline board "Confirmed orders"* and *Sales home → Data health* count only what the person can open. *Person chips* list only people with leads or deals in the person's offices. *New lead → Give it to* and *Sales settings → New leads go to* accept only someone whose own office access covers that office (`sx_user_covers_office()`). *Leads and deals* are opened (and a deal moved) only in an office the person works for; "Work this as a deal" only on a lead of the person's offices; a quotation of another office cannot be attached to a deal; an **accepted** quotation cannot be detached from its deal. *Raise the work order* on a won deal needs the Work orders edit right (`mod.calls.edit`) at the write as well as on the button. *Who covers for a quotation approver*: `crm.quote.approve` sets cover **for their own approvals only** (as 19a already said); a master or `settings.manage` for anyone. *Billing schedule* on a quotation and the *follow-up e-mail* follow the quotation's branch scope and the assigned-work rule. The Sales Records tiles for Deals and Pipelines appear only to people the Leads module lets in (the route's own gate). The 📝 Log outcome with 0 days ("you pick") needs a date. *Round 2 (owner, 19 Nov 2026 — "Fix all smaller problems"):* a deal-stage gate's approvers are only people whose office access covers the deal's branch (`gate_people_fit()`); the approver a request is waiting on (e.g. the Business Unit Head, who holds no Leads module) may post `/approval-act` for THAT request from the Approvals inbox — the route gate lets them through only when `gate_can_act()` says it waits on them, and nothing else of Leads opens; "Withdraw the request" is shown only to the person who raised it (or a master). Follow-up buttons on a quotation are shown only to people who may change follow-ups (`crm.quote.send` / `crm.quote.create`). A sent quotation's documents cannot be added (except by a master) or removed by a typed address. No right added to any role.
    - **Sales dashboard** (`/crm-dashboard`, `crmdash_can()`) opens on **Sales reports** (`mod.crm_reports.view`) alone. It also opened on Leads or Quotes view, so unticking Sales reports changed nothing. Every role whose defaults hold leads or quotes also holds Sales reports, so no role loses it by default.
    - **Customer satisfaction** (`sat_can_view()`), **Disclosure consent** (`disclosure_can_view()`) and **Site entry documents** (`sitedocs_can_view()`) no longer open on **Client register view alone**. That gave every Sales role (and Finance) a Quality & Accreditation menu, while the Sales table above says the Sales roles touch the CRM modules only. They now open on their own modules (Complaints; Confidentiality / Data control; Identity) or on Clients/Jobs held by management or by someone who works on jobs (`works_operations()` = `is_admin_level()` or Jobs Edit). Measured per role before and after: only BDM, KAM, MM, ME and FIN lose them; every other role keeps exactly what it had. A client's own satisfaction still shows on its Customer 360 to the people who may open that client.
    - **Finance and Jobs.** Jobs is **View** for FIN, but the R-20 carry-over gave Finance Delete on Jobs (for `expense-delete`), and Delete implies Edit, so Finance passed every "may edit jobs" check (bills, job site, hold points). The carry-over is removed and `expense-delete` is no longer verb-gated: its handler already admits `finance.reconcile`, so Finance still removes expense lines and holds nothing more on Jobs. The IDEMS row's FIN cell now reads "— (D-07)", as decided on 2026-10-04 and as the code already did.
    Guard rail `tests/test_screens_follow_own_tick.php`.

19o. **Create quotation asks: revise, or new — and who created each quotation (owner, 15 Nov 2026; update v51.1). No permission is added.**
    - *Revise or new.* When an approved costing's enquiry, lead or deal — or, failing those, the same client — already has a live quotation (its current revision is a Draft, Waiting for approval, Approved, Sent, Expired or Rejected; never Won or Lost), "📝 Create quotation" asks: **"Revise Q-… (recommended)"** or **"Make a separate new quotation"**. Pressing it without choosing makes nothing. Revising needs exactly what the "Revise" button needs — `crm.quote.create`, `mod.quotes.edit` or master of quotations, and the assignment rule (`crm_quote_can_work`); making a quotation still needs `mod.quotes.edit`. The new revision carries one line per costing role at the costing's prices (a role named like an existing line keeps that line's site and settings), keeps non-manpower lines (travel and the like), and the costing is linked to it; the earlier revision and its costing stay as they were. Only quotations in the person's own data scope are offered. `pc_quote_candidates()`, `pc_revise_quote_from_costing()`, `crm_quote_make_revision()` (one piece of code for this and the "Revise" button). A separate quotation made beside live ones is written in its history, naming them.
    - *Who created it.* Every quotation's history now starts "Created by NAME on DATE, TIME — from …" (costing, enquiry, lead, deal, a new one, or registered from a portal), and every revision line names who raised it. The revision table shows **Created by**. Guard rail `tests/test_v511_revise_or_new.php`.

19p. **Advertising return — money and branches (DEF-01, security review 21 Nov 2026; owner: "proceed with your recommendation"; 21 Nov: "Yes, branch-limited users will no longer see cost per lead or return per rupee"). No permission is added; this narrows what is shown.** *What the advertising actually earned* (`/ads-roi`) still opens on Leads view (`roi_can()` = `ads_can_view()`), unchanged. It showed **invoiced, collected and not-yet-received** amounts — receivables — to every Leads viewer, and for **every branch**. Now: (1) leads and deals on it are the viewer's own branches only (`scope_office_clause('l.office_id')` / `('o.office_id')`, the registers' own rule; a lead with no branch, or an advertising lead not yet in the CRM, stays visible as in the register); (2) invoiced / collected / not yet received, the "invoiced but unpaid" and "never invoiced" breaks, and the matching CSV columns appear only with the receivables right (`roi_money_can()` = `ar_can()`); without it the invoice tables are not read; (3) cost per lead and the return per rupee are shown only to someone who sees every branch (`roi_whole_company()`), because campaign spend is not split by branch; (4) the *What to fix* item "Advertising you are paying for that has produced nothing" is made only for a viewer who sees every branch, and its "invoiced but not received" sentence only with `ar_can()`. With the default roles this removes the money from the **Marketing Executive** and limits **branch-limited** BDM / KAM / Marketing Manager users to their branches; the Business Director, Admin and Master Admin see exactly what they saw before. Guard rail `tests/test_def01_ads_roi_money.php`.

19q. **Collection figures follow the person's branches (DEF-08, 21 Nov 2026; owner: "data shown shall be as per the permission set in our settings"). No permission is added; this narrows what is shown.** Who may see them is unchanged (`ar_can()`). *Orders & payments* (`/sales-orders`): **Received this month** counts the receipts of the person's branches by the receipt register's own rule (`scope_office_clause('r.office_id')`), and **Days to get paid** the invoices of their branches and business units by the invoice register's rule (`scope_clause('i.office_id','i.sbu')`); when one salesperson is picked, both follow that person's orders (the invoices matched to them, as the Received column). *Client register, Sales view* (`/clients?view=sales`): **Owed to us** counts only invoices of the person's branches (`books_outstanding($pid, true)`). The ledger and the advisor keep the whole-customer figure they always showed; Customer 360 follows the branches since DEF-25 (19s). A person who sees every branch sees exactly what they saw before. Guard rail `tests/test_def_batch1.php`.

19r. **Permission foundation A — nine Sales rights become switches of their own (owner, 21 Nov 2026: "All as recommended", permission-screen proposal Q1–Q9 and Q11). New permissions, approved by the owner; on day one nobody gains or loses anything.** Each was decided by something the administrator could not see or separate. Now each is a named right, shown on Roles & permissions and on a person's Permissions tab, and asked by its gate:
    - *See other salespeople's lists* — `crm.sales.see_all` (`sx_can_see_all()`); was the management job title (`is_admin_level()`).
    - *Set sales targets for others* — `crm.targets.set` (`sxt_can_set()`); was a management job title holding Sales reports. A Master Admin still needs Sales reports switched on. DEF-11's widening (sales managers set targets for their branches) is built in Stage 5, not here.
    - *View deals* / *Add and edit deals* — `crm.deal.view` / `crm.deal.edit` (`opp_can_view()` / `opp_can_edit()`); were "Leads, Quotations or Enquiries" view / edit. Opening the Deals register still also needs Leads view (its route gate), as before.
    - *Change pipelines and stages* — `crm.pipeline.manage` (`pipe_can()`); was Leads edit (which Leads delete carries) or *Manage system settings*.
    - *Download leads / deals / quotations to Excel / CSV* — `crm.lead.export`, `crm.deal.export`, `crm.quote.export` (`leads_can_export()`, `opp_can_export()`, `crm_quote_can_export()`, asked at the download and the button is hidden without it); were "anyone who can open the list". The Sales reports CSV (`/crm-reports`) is a report and is unchanged.
    - *Change Sales settings* — `crm.settings.manage` (`sx_settings_can()`, `/sales-settings` and its tile); was *Manage system settings*, the same tick as every company setting.
    - *See what customers were billed and paid (Sales screens and Receivables)* — `data.customer_money` (`ar_can()`, still with Invoicing in the plan); was *inter-office credit figures* or *Credit reconciliation*. Those two keep their own meanings everywhere else.
    - *View / Prepare project costings* — `costing.view` / `costing.prepare` (`pc_can()` / `pc_can_edit()`, still with Sales or People & hiring in the plan); were "Quotations, Enquiries or Recruitment" or a management job title. Approving a costing is unchanged (the approval levels; `pc_can_approve()`, `pc_can_act()`).
    - *Manage the cost-head library* — `costing.heads.manage` (`ch_can_manage()`); was a management job title.
  **How day one is kept exact:** the old rule of each switch is kept in the code (`perm_split_rules()`) and applied, as it is read, to every set saved before this (the built-in defaults, a saved Roles & permissions grid, a person's own access) — the read-time pattern of the verb split. A set saved after it carries the stamp `@split1`, so a switch left off there is a decision and is never worked out again. Nothing in the database was rewritten for this. Proved for 41 people — every role, branch-limited and all-branch, four custom roles, and own access stored in every format ever written — gate by gate, every older right through `can()`, the Sales tiles, and 190 screens and downloads as real sign-ins, with the built-in defaults and with a saved grid, before and after the move of Q11: `tests/test_perm_a_equivalence.php` (snapshot taken on the code before, `tests/fixtures/pre_perm_a/`). That each switch works on its own (off for one role, on for one person): `tests/test_perm_a_switches.php`. The default holders are in the table under the Sales / CRM roles above.

19s. **Customer 360 money and Orders' "Overdue" follow the viewer (DEF-25, owner 21 Nov 2026: "All as recommended"). No permission is added; this narrows what is shown.** *Customer 360* (`/customer`): **Owed**, the overdue figure, the ageing and the invoices listed count only invoices of the viewer's branches and business units, by the invoice register's own rule (`scope_clause('office_id','sbu')`, `books_outstanding($pid, true)`) — the same as the client register's Sales view that links to it. *Orders & payments*: when one salesperson is picked, **Overdue** lists only the invoices of that person's orders, as *Received this month* and *Days to get paid* already did (19q); a row of the older job-based register has no order behind it and is not counted for one person (`sx_overdue_for()`). A person who sees every branch, or "Everyone", sees exactly what they saw before. The customer ledger and the advisor keep the whole-customer figure. Guard rail `tests/test_perm_a_switches.php`.

19u. **Sales Stage 2 — Leads (owner, 21 Nov 2026: approved design pack, "All as recommended"). No permission is added; nobody gains or loses access.** The leads register, the full lead form, the quick lead, the conversion screen and the lead page keep their gates (`leads_can_view()`, `leads_can_edit()`, `lead_scope_gate()`, the register's branch scope) and their downloads (`crm.lead.export`, 19r). Three things are new and follow the existing rights: (1) **business unit on a lead** (DEF-15) — set on the full form and on the lead page's "Edit the details" by whoever may edit the lead; only the units in the person's own data scope (`scope_sbus()`, `lead_sbu_options()`) are offered and accepted, as the branch list is limited to their branches; (2) **duplicate check** on the full lead form while typing and on save — the quick lead's own check (`sx_lead_dupes()`), asked of `/lead-new` itself, so only someone who may add leads gets an answer; (3) the register's **row actions** (call, WhatsApp, Log) — Log only for someone who may edit leads. Guard rail `tests/test_sales_stage2.php`.

19v. **Sales Stage 3 — Enquiries and Deals (owner, 21 Nov 2026: approved design pack, "All as recommended"). No permission is added; one new screen; nobody's register access changes.** The inquiries register, the inquiry form, the deals register, the deal page and the new-deal form keep their gates (`mod.inquiries.view` / `mod.inquiries.edit` / "may work it" `crm_inquiry_can_work()`; deals: the Leads module gate plus `opp_can_view()` / `opp_can_edit()`, i.e. `crm.deal.view` / `crm.deal.edit`; delete: management or Master Admin). What is new:
    - **The inquiry's own page, read-only** (`/inquiry?id=`, DEF-14). It opens with the **Inquiries view** right (the route's module gate) and the register's branch **and** business-unit scope (`crm_inquiry_in_scope()`); a view-only person can now read an inquiry they could already see in the register. Edit, Quote, Project costing, Drop and Reopen on it keep their own rights and the "may work it" rule. Links that *open* an inquiry (chain strip, activity list, search, advisor, costing page) go to this page; saving, dropping and reopening return to it.
    - **The inquiries register** moves onto the shared register: paging, sorting, a column chooser and a **CSV**. The CSV is offered to whoever can open the register and holds exactly its rows (the person's branches and business units, the filter shown) — the same rule the leads, deals and quotations registers followed before their own download switches; there is no separate "download inquiries" switch.
    - **The deal forms' checks** (approved items 6 and 7): a value of 0 or more and real dates (`lead_fields_err()`, the lead form's own rule); the business unit on the new-deal form and "Edit the details" from the units the person's data scope covers (`lead_sbu_options()`; a unit not sent, or sent unchanged, is left as it is); a typed win % is not taken — it follows the stage (DEF-15).
    - **Wording** (DEF-16): a deal with only a typed name can be quoted; nothing says "drag".
    The owner's decision of 21 Nov that **Finance may open deals** is recorded but not yet built — see PENDING.md (one question about the Branch Manager and the Business Unit Head, who hold "View deals" since 19r). Guard rail `tests/test_sales_stage3.php`.

19w. **Sales Stage 4 — Quotations, costing, templates, approvals (owner, 21 Nov 2026: approved design pack, "All as recommended"). No permission is added; nobody gains a right.** Every quotation screen keeps its gates (`mod.quotes.view` / `mod.quotes.edit`, `crm.quote.create` / `.approve` / `.send`, `crm.contract.register`, the branch scope `crm_quote_scope_gate()`, the assignment rule `crm_quote_can_work()`), and so do costing (`costing.view` / `costing.prepare` / `costing.heads.manage`, 19r), templates and approval rules. What is new or corrected:
    - **The quotation register** (approved item 8) moves onto the shared register: paging, sorting, a column chooser, filters by owner / branch / business unit (which only narrow the person's own rows) and a search that also finds the contact and the contract number. The CSV holds every page of the same rows and still needs **"Download quotations"** (`crm.quote.export`, 19r).
    - **Withdraw to draft** (approved item 9; new lifecycle move PENDING_APPROVAL → DRAFT, docs/03): only the quotation's **preparer** (`crm_quote_is_mine()` — the person it is assigned to / who prepared it), only while it waits for approval and has not gone out, with a note. The open steps close as WITHDRAWN (kept, never deleted), the approvers it was waiting on are e-mailed, and the history records who, why and which levels had already signed (`crm_quote_withdraw_problem()`, route `/quote-withdraw`).
    - **Deciding a quotation from the Approvals inbox** (approved item 12): the same `/quote-approve` handler and rules — only the person the open level waits on (`crm_quote_my_open_step()`, the test `crm_quote_ids_awaiting_me()` already used), a comment to reject, the pre-order checklist, levels in order; only the return address differs.
    - **Contract signatures in the Approvals inbox** (approved item 13): the contract openings the Contract openings screen offers this person a button for (`contract_open_stage()`), signed through the same `/contract-open` handler. A person with such a signature waiting may now open `/approvals` (`approvals_hub_can_view()`), which shows them only what is theirs to sign — the same rule quotation approvers already had. The dashboard keeps counting them once, as "contracts to endorse / approve".
    - **DEF-18 — buttons follow the server:** "+ External" only for `crm.quote.create` or a master (the route's own check); a file's ✕ only for `crm.quote.create` or a master on a quotation not yet sent; the Attach form for `crm.quote.create` / `mod.quotes.edit` / master of quotations, and on a sent one for a master; **Edit** also on an APPROVED quotation (the edit screen always took it; a commercial change cancels the approval, D-07); the playbook's "Edit the quote" only for someone the edit screen lets in. Nobody gains a right; buttons that only led to a refusal are gone.
    - **DEF-05** (a revision shows the earlier revisions' files, linked), **DEF-06** (before-GST is the main figure on the register, the quotation page and the Quotation report — the renamed `/crm-reports`, owner Q5) and **DEF-23** (`/quotes-export`, `/quote-final` open the register) change no right.
    Guard rail `tests/test_sales_stage4.php`.

19y. **Sales Stage 5 — Orders & payments, Renewals, Targets, Client register (owner, 21 Nov 2026: approved designs, "All as recommended"; DEF-07 confirmed by Finance; ledger decision).** Screens keep their gates (`sx_can()`; receivables `ar_can()`; Targets `sxt_can_set()` = `crm.targets.set`; Sales settings `sx_settings_can()`). What changes:
    - **Who sets targets (DEF-11, A) — a deliberate widening:** management and the Master Admin as before, **plus the Marketing Manager** (the default role holding quotation approval; lead set-up is already management) — `crm.targets.set` in its defaults and once in saved grids / own sets (owner rule `DEF-11-2026-11-21`). A setter who is not management sets targets only for the **people of their own branches** (`sxt_people_for_setter()`, on the screen and at the save). An empty box is stored as **"no target"**, not ₹0; the history is complete and paged; any financial year can be picked.
    - **Received (DEF-07, Finance: "some payments are without TDS and some deduct TDS")** on Orders & payments = payments received + the TDS actually deducted on those receipts (`books_settled()` cash + TDS; no rate is ever assumed); **credit notes are shown apart**; still owed = billed − received − credit notes. The order's value **before GST** is the main figure (DEF-06). Billing and payment figures, and the matching CSV columns (new download, backend item 15), only with `ar_can()`, as before.
    - **Renewals (DEF-10):** open contracts only (`open_status` OPEN); one look-ahead window for Renewals, Today and Sales home — the setting `sales_renewal_days` (Sales settings; 60 to start, marked as an example). **Contract value** on the list only for people the contract page shows it to (`sx_contract_money_can()` = master, credit data, revenue data or Finance reconcile; owner Q2) — a narrowing. The contract number links only for people who may open contract pages. **Log a renewal call** (owner Q3) writes a conversation on the **client's own timeline** naming the contract — for whoever may log on a lead or deal (`sx_renewal_log_can()`; Branch Manager / SBU Head, view only, cannot), and only for a contract of the person's branches.
    - **Customer ledger and money follow branches (owner, 21 Nov 2026: "customer ledger should follow branches but central finance team can access each and every customer ledger and details irrespective of the branches"):** the ledger (`/ledger`, `books_ledger(…, true)`) and its balance, and the *What to fix* items "unmatched receipts" and "silent customers' balances", count only the invoices, receipts and credit notes of the person's branches, each by its own register's rule. Anyone whose data scope is every branch — central Finance, management, a master — sees them whole, as before.
    - **DEF-12:** the "your target was changed" notice is put away on Sales home itself (it posted to Targets, which may be switched off).
    Guard rail `tests/test_sales_stage5.php`; `tests/test_perm_a_equivalence.php` allows only the Marketing Manager's target-setting as the second intended change.

19x. **The Branch Manager and the SBU Head open and download leads and deals — the owner, 21 Nov 2026** (*"branch manager and SBU head can sure open the lead and check all other details as they are also responsible for business individually for their branch and business units respectively"* … *"Yes but yes they shall be able to download"*; and *"finance does not need to see the details of deal"*). **A deliberate widening, for these two roles only.** The Branch Manager and the SBU Head (and a role the company built on either) receive **Leads view** (`mod.leads.view`) and the two downloads (`crm.lead.export`, `crm.deal.export`); they already held **View deals** (`crm.deal.view`, 19r). By default (`module_defaults()`, `ACCESS_LEAD_VIEWERS`) and once in a saved Roles & access grid and in a person's own stored set (owner rule `BM-SBU-LEADS-2026-11-21`, `access_owner_grants()` — additive, once per database, so an administrator who later switches it off is not overruled). **View and download only:** Leads view is given outside the module list so the job-title Delete on Leads (management) is **not** carried onto it, and the lead and deal screens now also ask that the person *works leads* — Add or Edit on Leads, or a master (`leads_works_leads()`, read with `leads_can_edit()` / `opp_can_edit()`); every lead worker holds Add (19b), so nobody who could change leads or deals before loses it. **Scope:** a Branch Manager sees their branch's leads and deals (`scope_office_clause`, as every register). An SBU Head covers every branch, so leads and deals are also limited to **their business units** for anyone who covers every branch but only some units (`scope_sales_clause()`, `scope_sales_allows()` — lists, counts, search, the funnel, the advertising return and a single lead or deal page; a record with no unit stays visible, as one with no branch does). People limited by branch are unaffected. **Side effects, all view-only and within the same branch / unit:** the Leads, Deals and Pipelines tiles and the Sales bar's Leads link now open for them (test-book finding F-01); the stage-gate register and *What the advertising actually earned* open (money only with `ar_can()`, 19p). **Finance is unchanged:** no Leads, so the deal register and deal pages stay closed; Sales home, Today, orders and payments are as before. Guard rails `tests/test_bm_sbu_lead_view.php` (40 checks: open, download, branch / unit limits, every write refused, Finance and others unchanged, the one-time rule) and `tests/test_perm_a_equivalence.php` (the only differences from the 21 Nov snapshot are these gains, for these roles).

19t. **"Who can do what" — one screen for roles, people and branches (batch B, owner 21 Nov 2026: "All as recommended", permission-screen proposal). No permission is added and nobody's access changes; it is a new way of seeing and switching the rights above.** `/who-can` (`lib/whocan.php`, `views/ops/whocan.php`) replaces the Admin tile *Roles & permissions*; the old screen (`/access`) stays reachable from it ("Old layout") for one release. Tabs: **Roles** (Master Admin only — the gate of `/access`), **People** (whoever may manage users — the gate of `/user-edit`; a branch user manager only for their own branch, and only the rights `assignable_permissions()` lets them hand out), **Compare roles**, **Company switches** (a summary; changed on Modules & features as before) and **Plan** (read-only). Rights are shown as Area → Feature → Sub-feature, **one switch per right** (two rights are never merged), and exactly the rights each old screen drew. **Storage is the old screens' own:** a role is saved by posting to `/access` with the same fields (`ops_access()`; `_back=who` only chooses where the person lands); a person's switches and branches go through `user_access_from_post()`, which the person form (`/user-edit`) now calls too, then `user_perms_to_store()` (19r, "@diff") and the same `ACCESS_CHANGED` audit entry. "What they will see" for a person is worked out by asking the real gates as that person (`appr_as_user()`). A role the company added has no switches of its own (it uses its base role's, `role_effective_key()`), so the screen shows its base role's switches locked with a link to them — the old screen accepted a save for it that changed nothing. Q10 ("whose records: own / team / branch / all") was then built on this screen (19z). **One correction:** the person form's guard "you cannot remove your own user-management access" now reads what the stored value grants (`user_access_keeps_user_mgmt()`), not its text — since 19r stored a person's access as "@diff,…", the text test refused every save of one's own record that kept another exception. Guard rail `tests/test_whocan_screen.php` (the same boxes ticked the same way for every role and the 41 Batch A people; the same stored value for untouched saves and switched sub-features through both screens; the preview against the real gates; the same administrators get in).

19z. **Q10 — whose records a person sees: Own / Team / Branch / All (owner, 21 Nov 2026: "Keep this"), and the Sales manager's team (owner, 21 Nov 2026: "Sales manager can see only their own list on sales home and today is ok. But they can also see details of his reportee"). A setting, not a permission; on day one nobody's lists change.** Set on *Who can do what* for a **role** (Own / Team / Branch) and, for one **person**, "As the role" / Own / Team / Branch / All. **Branch** = everything in the person's branches and business units — the rule every Sales register already followed, and what every role is on day one. **All** = Branch with the person's branches set to *All branches* (the existing data scope; only a company-wide user manager may set it). **Own** = only records the person owns; **Team** = theirs and those of everyone below them in *Reports to*, however deep (`sales_team_ids()`). Own and Team only **narrow inside** the branch and unit limits — they never add a branch. The owner of a lead or deal is its owner (`owner_user_id`); of an inquiry, the person it is assigned to; of a quotation, its owner, else whoever made it (`crm_quote_owner_user_id()`). **Unassigned records (owner, 21 Nov 2026, answer "a"):** a lead, deal or inquiry with **no owner** stays visible to an Own / Team person **inside their branches and units** — in the lists and when opened by its address alike — so somebody picks it up with the existing *Take these myself* / allocate (`sales_owner_sql()` / `sales_owner_allows()` treat "no owner" as allowed; branch and unit limits still apply beside it). Sales home / Today "Me" figures stay the person's own. A quotation always has a maker, so its rule is unchanged (`$unassigned = false`). No permission is added. Applied in one place for leads and deals (`scope_sales_clause()` — every list, count, search, the board, Sales home, Today, the dashboard, the funnel, the advertising return — and `scope_sales_allows()` / `lead_scope_gate()` / `opp_scope_gate()` for one record by its address), for quotations by `scope_quote_clause()` and `crm_quote_scope_gate()`, for inquiries in their register, their "waiting for a quote" list and `crm_inquiry_in_scope()`, and for Orders & payments (`sx_orders()`). Accounts' "awaiting a contract number" queue is a work queue, not a salesperson's list, and keeps branch scope only. The Master Admin is never limited. Stored as the setting `sales_records_mode` (role and person choices), **not** as permission ticks, so "Select everything" and a master's every-right can never switch someone to "own records only"; each save is written to the sealed audit chain (`ROLE_RECORDS_SCOPE_CHANGED`; a person's under `ACCESS_CHANGED`). **Sales manager's team:** on Sales home, Today, the board and the Sales dashboard, a person **without** "See other salespeople's lists" (19r) still opens on their own list, and may now pick anyone who reports to them (*My team*, `sx_my_reportees()`), never "Everyone" and never anyone else — records they could already open in the registers of their branch. A person set to Own has no team picker. Guard rail `tests/test_q10_scope.php` (day one: 51 people — every role, branch-limited and all-branch, unit-limited, management, master — get character-for-character the clauses built before Q10; Own / Team / Branch on every register and on opening one record; person over role; "All"; the team picker; the saves and the audit entry).

19ab. **Access and records — the owner's 26 Nov 2026 test results ("go ahead and fix everything as recommended"). No permission is added; one leak is closed and one register is narrowed to the rule every other register already follows.** (1) **Branch Manager / SBU Head add no lead anywhere (R-04 / R-06).** 19x made them read-only on leads and deals, but the Quick lead page (`/sales-lead`, opened by the Sales home *New lead* tile and the Sales menu tile) asked only `leads_can_edit()`, which they hold through Enquiries edit — so they could save a lead. It now asks the same rule as *+ New lead* on the register, `leads_can_edit() && leads_works_leads()`. The Sales home shortcuts *New lead*, *New deal* and *Log activity* are shown only to someone the server lets do them (reading shortcuts — Orders won, Print a quote — stay). The lead page's *Delete this lead* follows exactly the server's delete rule (Edit on Leads, or a master); an administrator-level job title without it is no longer offered a button that is then refused. **Enquiries are untouched**: the Branch Manager and the SBU Head still record and edit enquiries as before (owner has not decided) — *since 27 Nov 2026 read-only, see 19ac*. (2) **Hand-over only to someone who could open it (LP-20).** Giving a lead (owner change, reassignment request, or allocation on creation), a deal (owner, or moving its branch / unit while someone holds it) or an enquiry (*Assigned to*) to a person is refused, with the reason, when that person's branches or business units do not cover the record — the same boundary the record's own page asks (`sales_handover_problem()` on `user_scope_covers()`; a Master Admin can be given anything). The pick-lists offer only people who could open it, plus whoever holds it now (`sales_people_covering()`). Who may hand over is unchanged. (3) **"My team" (R-11)** lists only reportees who do sales work — who own a lead or deal, or whose rights add / change leads or edit deals (`sx_team_people()`, `sx_user_works_sales()`). Which records a person may open is unchanged. (4) **Deletions recorded (DL-20) and Directory → Activity scoped.** Deleting a lead, deal or enquiry is now written as a **Deleted** system entry (registered kind `DELETED`, never offered to type by hand, marked as the system's), carrying the record's branch and unit and who deleted it; find it under Directory → Activity → *Deleted*, or on the client's timeline. The Activity register (`/activities`) used to list every branch's entries to anyone who could open it; it now asks `scope_office_clause('a.office_id')` like every other register (an entry with no branch stays visible, as for leads). Who may open the register is unchanged. (5) **Lead → client already on file (J1-05):** the GSTIN / PAN boxes are hidden (and not sent) when a client on file is chosen; the save already ignored them. (6) A person's *Recent changes* on Who can do what now names a whose-records change (old → new), and the add-a-person form no longer says a company role can be fine-tuned under Roles & access — it always has its base role's switches (19t). Guard rail `tests/test_owner26_access.php`.

19ac. **Enquiries read-only for the Branch Manager and the SBU Head; closing a job without a report — the owner, 27 Nov 2026 (decisions "3. Ok" and "4. A ok"). One narrowing; no permission is added.** (1) **Enquiries (decision 4a).** Since 21 Nov (19x) the Branch Manager and the SBU Head read and download leads and deals but do not change them; enquiries had been left as they were (19ab). They now follow the same rule: they **open and download** their branch's / unit's enquiries (Enquiries view, the register's CSV, the enquiry page) but **do not create, edit, assign, drop / reopen or delete** them — the *+ New enquiry* button, the page's Edit and Drop, and the register's Delete are not shown, and the server refuses `inquiry-new`, `inquiry-edit`, `inquiry-drop`, `inquiry-reopen` and `inquiry-delete` with *"Your role reads enquiries but does not change them"* (`crm_inquiry_can_write()`, `ACCESS_INQUIRY_READ_ONLY`). Their Enquiries edit came from the job-title Delete on Enquiries (management, `perm_legacy_role_grants()`), not from working enquiries; the rule therefore keys on the role (and a role the company built on either), and an administrator who deliberately ticks **Add** on Enquiries for one of these roles on *Who can do what* lets it work enquiries again. **Everyone else is unchanged** — the Business Director, Admin, BDM, KAM, Marketing Manager and Executive keep exactly what they had. *Who can do what* shows the two roles as *"Enquiries: view (read only for this role)"*. (2) **Closing a job with no report on file (decision 3, step one)** is a lifecycle rule (docs/03 Job), not a right: who may close a job is unchanged (`ops.job.close`, the job's own engineer, a master). The only new *who* is a notice: when a report is written on a job that was closed without one, the **Operations Manager(s) covering the job's branch** are e-mailed (else that branch's Branch Manager, else the managers' list used for other operations alerts — `job_branch_ops_manager_emails()`). Nobody gains access to anything by it. Guard rail `tests/test_owner27_ops.php`.

19ab2. **Billing addresses — the owner's 27 Nov 2026 test results (BL-03, BL-05, BL-07) and answer A3 ("both shall have same form and have GSTIN value input so we don't have duplicate screens"). No permission is added.** (1) **One form for a billing address.** The master record's Addresses tab offers the same "+ Add a billing address / GSTIN" form as the Billing addresses box (`views/ops/_billing_location_form.php`, saved by `custid_add_location()` through `/partner-billing-location`); its plain address form no longer offers a client a GSTIN-less "Billing Address", and the server refuses one (`/partner-add`). Old GSTIN-less ones stay, labelled. Who may add one is unchanged (`custid_can_edit()`). (2) **Head office.** The Main billing line uses only one of the record's own addresses (`custid_head_office_address()`), never a billing location's. *Mark as head office* (`/partner-address-head-office`) asks exactly the `/partner-add` gate and the office boundary (`partner_can_write()`). (3) **"Who already has this GSTIN?" as it is typed** (`/gstin-who`, POST with the page token): it answers only someone who would get the same answer as a refusal on Save — who may add or edit a client or billing address, or turn a lead, deal or won quotation into a customer (`custid_lookup_can()`) — and returns no more than that refusal names (the code and the name). (4) The Registration tab lists the GSTINs read from Billing addresses (not stored twice). Guard rail `tests/test_owner27_addresses.php`.

19ac. **One person, one level — the owner's decision 1 of 27 Nov 2026 ("Yes"). No permission is added; an approval right is narrowed.** On a **quotation**, a **project costing** and a **recruitment approval request** (hiring request, requisition, offer, salary structure — the shared chain), a person who has already signed one level cannot sign another level of the same record — not when the later level is addressed to them by name or by role, not as the cover for an approver who is away (delegation), and not by escalation. Signatures are matched by user id (kept from 27 Nov 2026 on `quote_approvals`, `recruit_approval_steps`; `project_costing_approvals` already had it), and older signatures by the name they were signed under. **When that leaves a level with nobody:** a quotation level is opened at once to the level above — the same opening a level gets when it has waited too long (D-11a, `crm_quote_level_above()`, reused by the nightly escalation) — and History says so; with nobody above, only the Master Admin signs it. A costing level falls back, as it already did for its author, to anyone else who may approve; a recruitment level waits for its other approvers, its SLA escalation, or the Master Admin. **The Master Admin is not held by the rule** — it is the last resort and could already sign any level. The quotation's *Next step* names a cover as "level 3: Test Director (Business Director) (cover: Test SBU Head)", and stops naming someone who has already signed another level. (QP-10, same day) **Send again**: on a Sent quotation, "✉ Send again from the company mailbox" e-mails the same PDF to the client again — the same action and the same right (`crm.quote.send`, assigned person or their reporting line) as "✉ Send to client"; it stays Sent and History records "Sent again by <name> to <address>". (Q3) The editable Word copy has a **system format** built in (same content as the client PDF); a company's uploaded format is used when it is the company's choice under Quote templates, and More ▾ offers every format. Who may download Word is unchanged (19 / Q-05). Guard rail `tests/test_owner27_quotes.php`.

19n. **Quotation approval is an escalating chain — the owner's decision, 2026-10-24 (test book SAL-003):** a quotation needs the level its value falls in and every level below it, in order (docs/03). Who signs each level is unchanged (19h: the people in the level's role who cover the quotation's branch and business unit, or the one named person). Overlapping levels and a higher range with a lower level number are refused at the save. **No permission is added**; more people now sign a large quotation, which is the decision. Guard rail `tests/test_quote_escalating_chain.php`.
19k. **Approval rules — the owner's answers, 2026-10-21:** (1) a quotation made from an approved project costing, at the costing's prices, is not approved a second time; (2) with no approval levels set up, a quotation needs no approval (it used to wait for anyone who may approve — top management included); (3) Set up Sales asks whether levels approve one after another or all at the same time (`quote_approval_mode`), for quotations and costings alike. The approvers are still exactly those the levels name (19h); **no permission is added.** Guard rail `tests/test_quote_approval_owner_21oct.php`.

19c. **An enquiry belongs to its branch — the owner's answer, 2026-10-20 ("Yes"):** an enquiry records the branch of the person who recorded it (a converted lead's enquiry takes the lead's branch), and **branch scope applies to it exactly as to a quotation**: the register, the "waiting for a quote" count, search, opening, editing, deleting and quoting from it all follow the person's office scope (`crm_inquiry_in_scope()`, `scope_office_clause('i.office_id')`). An enquiry with **no branch** — older rows and website imports — is visible to every branch, the rule unassigned leads already follow. Who may add or edit enquiries is unchanged; **this narrows what a branch-scoped person sees and adds no permission.**

19b. **Leads & deals — the owner's rule, 2026-10-20 (Sales audit D-01):** "Sales executive, sales manager and anyone who is assigned to do the job." The four sales roles (BDM, KAM, Marketing Manager, Marketing Executive) receive `mod.leads.view` and `mod.leads.add` by default (`module_defaults()`; applied once to a saved grid as owner rule `D-01-2026-10-20`, `access_owner_grants()`). Anyone else the company assigns the work to is given the same on **Roles & access**. Editing a lead or deal rides on the Enquiries edit right these roles already hold (`leads_can_edit()`, `opp_can_edit()`); **Delete is not granted**, and **configuring the pipelines** (`pipe_can()`, `mod.leads.edit`) stays company setup. Before this rule only MA, Admin and BD could open Leads, Opportunities or Pipelines. Two doors were moved to the module their users already hold, with no change to who may act: **register the contract for a deal won without a quotation** (`opportunity-contract`) now sits behind Quotes, which Finance reads, and still requires `crm.contract.register`; **contract opening** (`contract-open`) now sits behind Work orders, which the Operation Manager holds, and still requires `can_endorse_contract_open()` / `can_approve_contract_open()`.
19y. **D1 — Finance in Sales (owner, 21 Nov 2026, findings SH-08: "as recommended"). A deliberate NARROWING, for Finance only.** *"Finance is not required to have any details of sales except the Quotes register wherein they can check the quote and customer register wherein they will have only standard view … In quotes register they can only view the quotes nothing else."* Finance (and a role the company built on it) keeps **Quotes — view** (no create, edit, submit, approve or send; the download stays with `crm.quote.export`) and the **Client register's normal view**. Removed: **View deals** (`crm.deal.view`) — and with it everything behind `sx_can()`: Sales home, Today, the Pipeline board, the Sales dashboard, Orders & payments, Renewals, Targets and the Client register's *Sales* view, each refused with "Sales is not open to you" — and the **Sales reports** and **Orders / contracts** module rights (`mod.crm_reports.*`, `mod.crm_orders.*`; neither gated anything Finance needs). **Unchanged:** receivables with ageing (Money → Receivables, `ar_can()`), the customer ledger by branch with central Finance seeing all (Stage 5), *Contracts to register* and registering a deal's contract (`crm.contract.register`). How: `module_defaults()` FINANCE; the switch split no longer derives View deals for Finance from Quotes view (`PERM_SPLIT_NOT_FOR`); and once per database the owner rule `D1-FINANCE-SALES-2026-11-21` (`access_owner_revokes()` / `access_apply_owner_revokes()`, the counterpart of the grants) takes these rights out of a saved Finance grid and out of an older frozen per-person copy (written to the audit chain). A person stored as "role + differences" follows the role; a `+right` an administrator gave on purpose is left alone, and an administrator may switch View deals back on for the role on *Who can do what*. No permission is added. Guard rails `tests/test_owner_decisions_d1_d3.php` (Finance × every Sales screen, the Quotes register, the one-time rule and its idempotence) and `tests/test_perm_a_equivalence.php` (the only narrowed answers are Finance's).

19y2. **D2 — who works one lead or deal (owner, 21 Nov 2026, findings J5-01 / LR-05: "as recommended"). A narrowing; no permission is added.** Changing the stage of a lead or deal, logging on it, editing it, filing on it, converting the lead, quoting / ordering / handing to Accounts from the deal is for **the person handling it** (its owner), **the people above them in *Reports to*** and **an administrator** (the Admin role, or Master Admin) — `sales_record_can_work()`, the same reporting-line rule as an assigned enquiry or quotation (`crm_assignee_allows()`, owner 14 Nov 2026). Everybody else who may open it **reads it only**, and is told why: *"Only <owner> or their manager can change this."* (server refusal and a "View only" note on the page; the Log button on Today is not offered on such rows). A record with **no owner** (or whose owner has left) is anybody's in the branch to pick up (Q10 answer "a", 19z). The Branch Manager and SBU Head stay view and download only (19x). Reopening a closed lead or deal (a manager, D-22), deleting (management), marking lost (owner, 19g) and Accounts' contract registration keep their own rules. Guard rail `tests/test_owner_decisions_d1_d3.php` (Marketing Executive, KAM, the owner BDM, their Marketing Manager, Admin × stage / log on a lead and a deal; the unassigned lead; the Branch Manager).

19y3. **D6 — adding a customer from Sales (owner, 21 Nov 2026, findings J4-05: "as recommended"). Nothing widens; no permission is added.** A sales person adds a customer by **converting the lead** (GSTIN / PAN checked, 19gst). The "+ Add new" customer link beside a client picker on the deal, inquiry, quotation and lead-conversion forms is shown only to someone the add form itself admits (`partner_can_add()` — Master Admin, Clients or Vendors edit, or coordinator and above: the existing `/partner-new` rule, now one function), so nobody is offered a link that ends in "You do not have permission" (owner findings B4). A **won deal with no lead** keeps its own GSTIN / PAN-checked "add the customer" step on the deal (19gst), since it has no lead to convert. Guard rail `tests/test_owner_findings_b.php`.

19y4. **A switch for adding a customer from a won deal with no lead (owner, 22 Nov 2026, answer 9: *"Don't hardcode it but allowed from permission list you can add one in that permission list."*).** The won-deal *"add the customer"* step (19y3, 19gst) was decided by a fixed rule (`leads_can_edit() && leads_works_leads()`). It is now the switch **"Add a customer from a won deal that had no lead"** (`crm.deal.add_customer`) on *Who can do what* (Deals) and in Roles & access, used by `opp_can_add_customer()` for the step and its form; the Master Admin and a Leads / Enquiries module master keep their override. **On day one it is held by exactly the people the old rule let in** — the rule is kept in `perm_split_rules()`, so a role's built-in rights and any set saved before foundation A derive it, and the one-time owner rule `ADD-CUSTOMER-SWITCH-2026-11-22` (`access_owner_grants()`, now also rule-based through `access_owner_grant_applies()`) adds it to a saved role grid or a person's own access that already satisfies the old rule. The column above is that rule applied to each role. **The switch alone is not enough:** the person must also work leads and deals (`leads_works_leads()`), so a Branch Manager or SBU Head who only reads them (21 Nov, a88afc2) stays unable to add a customer even if given the switch. Nothing widens. Guard rail `tests/test_owner_answers_2211.php`.

19y5. **Recording an invoice or payment on a job is Finance's (owner, 22 Nov 2026, Operations test book Q-OP-04: "Finance"). A narrowing; no permission is added.** The manual *Invoice / payment / credit* entry on a job (`/job-invoice`) was open to anyone who could see the inter-office credit figures (`data.credit` — coordinators and managers). It is now `job_invoice_record_can()`: the credit-reconciliation right Finance holds (`finance.reconcile`, also the Admin role) or the Master Admin. Everyone else who sees the panel reads the invoice / payment / credit flags with "Finance records these". Seeing the credit figures, raising the GST invoice from a closed job (`/job-bill`, unchanged) and advance status (`/job-advance`, unchanged) keep their rules. Guard rail `tests/test_owner_answers_2211.php`.

19y6. **A site check-in is recorded only by the engineer assigned to the job (owner, 22 Nov 2026, Operations test book F-OP-01: "Yes fix it").** `/site-checkin` recorded a check-in on any job for anyone signed in. It now requires the signed-in person's own engineer record to be the job's engineer or one given a day of it in the per-day plan (`site_checkin_is_assigned()`, the same rule as the top-bar punch), and stamps that person. Coordinators no longer record a check-in on an engineer's behalf; they see the record and the note "Check-in is recorded by the engineer assigned to this job". Viewing check-in photos keeps its branch rule. Guard rail `tests/test_owner_answers_2211.php`.

19y7. **A switch for deleting deals (owner, 24 Nov 2026, "as recommended" — after asking *"Can we delete the deals as we can delete the leads?"*).** Deleting a deal (`opportunity-delete`, and the button on the deal page) was decided by the job title: any management role (`is_admin_level()`, MGMT_ROLES) — which included the Branch Manager and the SBU Head, who since 21 Nov (19x) only open and download deals, and the Branch App Manager and the Operation Manager. It is now the switch **"Delete deals"** (`crm.deal.delete`) on *Who can do what* (Deals) and in Roles & access, read by `opp_can_delete()` for the handler and the button. **It starts with the Admin and the Business Director** (the Master Admin holds every right); the Branch Manager, SBU Head, Branch App Manager and Operation Manager lose it, deliberately — the one narrowing of this change (`ACCESS_DEAL_DELETE_NARROWED`, through `PERM_SPLIT_NOT_FOR`, so a set saved before foundation A does not re-derive it for them). The one-time owner rule `DEAL-DELETE-SWITCH-2026-11-24` (`access_owner_grants()`) adds it to a saved grid or a person's own access of the Admin and the Business Director; nobody else ever held the switch, so nothing is revoked, and an administrator may later give it to anyone on purpose. **Unchanged:** a won deal, or one linked to a quotation, still cannot be deleted (mark it lost instead), the branch / unit / whose-records scope of the deal page still applies, and each deletion is still written to the activity history (`act_log` … DELETED — who, when, the deal's number and name), shown on the client's timeline (Customer 360) when the deal was for a client on file. Guard rail `tests/test_deal_delete_switch.php`; the day-one proof `tests/test_perm_a_equivalence.php` allows exactly this narrowing.

19y8. **A Branch filter on the Sales screens (owner, 26 Nov 2026, R-05: *"For Director or person having central data access like business head, directors and so on … they shall have filter branch wise also."* — "fix everything as recommended"). A view filter, not a permission; nobody sees anything new.** Leads, Deals, Enquiries, the Pipeline board, the Sales dashboard, Sales home and Today get the Quotes register's **Branch** filter, shown only to a person who sees **more than one branch** and offering only those branches (`sx_branch_choices()` — `scope_office_allows()`). The pick applies to that one GET of those seven screens and their CSV downloads (`sx_branch_begin()` from `ops_dispatch()`), and is added beside the existing rules in `scope_sales_clause()`, `scope_quote_clause()`, the enquiry register and its "waiting for a quote" list, the Renewals rows (`sx_scope_contracts()`) and the receivables rows on Today / the dashboard — so it can only **narrow** what the branch, business-unit and whose-records rules (19z) already allow. A branch the person does not see, typed into the address, is ignored; a save (POST) is never narrowed; a one-branch person gets no control and a typed branch changes nothing. The last pick is remembered per screen for the session; "All my branches" clears it. Guard rail `tests/test_owner26_screens.php`.

19gst. **A customer's GSTIN, PAN and billing locations — the owner's D1–D4, 21 Nov 2026 ("as recommended"). No permission is added; no table is added.** *"When we win the customer it shall be mandatory to have GSTIN and PAN, matched to the central customer database to avoid duplication; a customer can have one PAN but multiple billing states with respect to their branches."*
    - **Won = identity required.** The new-client form (and its "+ Add new" pop-up), lead → customer, won deal → customer, a quotation accepted for a name not on the master, a branch company and the Excel import ask for the GSTIN (its check digit is verified; PAN and state are read from it, and a typed PAN must be the one inside it) — or one of three recorded exceptions (D2): *not GST-registered* (PAN + reason), *overseas* (country + their own tax number), *government department without a PAN* (reason; listed for Finance). A vendor-only record keeps them optional. The public Connect sign-up checks what is typed (check digit, PAN = GSTIN's) but does not require them — a sign-up is not yet a won customer (D3 below then applies). `custid_from_input()`, `custid_resolve_new_customer()`.
    - **Matched against the central list.** The same GSTIN (as a customer's main GSTIN or as one of its billing locations) is that customer — refused. The same PAN with a new GSTIN is the **same customer in another state** — never a second record: the person is offered to add it as a **billing location** of the customer on file, which only someone who may edit that customer can do. A similar name (ignoring Pvt Ltd / Private Limited / Ltd / Limited / LLP / Co / Company / & Co / Inc and punctuation — owner Q-02, also for lead-against-lead) is a warning the person confirms. `custid_match()`, `find_duplicate_partner()` (confidence `LOCATION`), `normalize_name()`.
    - **Billing locations (D1/D4).** A customer's main location is its own record (GSTIN, primary address); each further state is a `partner_addresses` row with `is_billing = 1`, its own GSTIN (carrying the customer's PAN) and billing address. Added, renamed or stopped for new bills by **exactly who may edit the client record** (`custid_can_edit()` = the partner-edit gate and its office boundary). A quotation, a contract (through its quotation) and an invoice name the location they are billed to; the place of supply, the buyer GSTIN and CGST + SGST / IGST follow it (`books_their_state()` / `books_is_igst()` take the location).
    - **Another office in the same state, and the box on the Sales page (owner, 25 Nov 2026: Q3 "Yes").** A billing location may carry the **same GSTIN** as the main record or as another location — GST's "additional place of business", e.g. a second Gujarat office — provided it has its **own address and city**; the very same address twice under one GSTIN is refused (`custid_same_address_as()`), and a GSTIN of another customer is still refused. Its state and tax are read from the GSTIN exactly as before (same state as the main record = CGST + SGST), the invoice copy carries that office's address, and pick-lists add the city where two locations share a GSTIN (`custid_location_words()`). The same box ("Billing addresses", button **"+ Add a billing address / GSTIN"**) is now also on the Sales customer page (`/customer`), from the same partial and with the **same rule** — `custid_can_edit()`; a person who may only view the customer sees the list without Add / Change / Stop — and returns to the page it was used on (`custid_back_url()`, only `/customer` or `/partner`). The customer page's other button is renamed **"Create a separate branch company (handled by another of our offices)"**; it is unchanged. **No permission is added and no one's access widens.** Guard rail `tests/test_billing_same_gstin.php`.
    - **Bills never change afterwards.** Every invoice now keeps a copy of who it was made out to — name, GSTIN, state, billing address (`invoices.bill_*`, `custid_invoice_snapshot()`), refreshed while a draft and frozen when issued; the print reads the copy. Invoices raised before this keep reading the customer record exactly as they always did.
    - **D3 — existing customers.** A customer with no GSTIN and PAN (and no recorded exception) is listed under Data health on Sales home and asked for them at its **next edit** (the client form) and its **next invoice** (`books_issue_missing()`: it cannot be issued until they are on the record). Old invoices are untouched.
    - **D1 — existing branch companies.** "Move under the main customer" (`/partner-move-under`, shown on a branch company with the same PAN as its main record): a preview of everything that would move, then — only when a person presses it with a reason — the branch's invoices keep a copy of exactly what they print today, its GSTIN becomes a billing location of the main customer, its quotations / contracts / invoices point at that location, every other reference moves (`dd_merge()`), and the branch record is retired with a note (never deleted). Allowed to those who may merge duplicate records (master, `settings.manage`, `mod.clients.edit`) and who may edit both records. A branch company with a **different** PAN stays a separate company. Guard rail `tests/test_gstin_billing.php`.

20. **Nonconformities / corrective actions** — module gates `mod.ncr.*` and `mod.capa.*`; closing is `ncr.close` / `capa.close` (`access.php:617`, `access.php:209`). Both rows above are **generated from the application's own `role_perms()`**, not written by hand — see `docs/audit-2026-10/evidence/role-rights-probe.php`, which reads the role list from `ORG_ROLES` so it cannot drift. Routes: `/issues`, `/ncr`, `/departures` are module `ncr`; `/capa` is module `capa` (`ops.php:3313`).
    ²⁰ᵃ **Containment, disposition and reopening go to whoever may close (R3 / DEF-QUA-006, owner rule Q-B1 19 Oct 2026, plan R3 16 Nov 2026).** `ncr_can_raise()` accepts `mod.capa.edit`, which the Branch Manager and Operation Manager hold, so they could record containment, decide the disposition (including *Accept as it stands*) and — by a direct request — reopen a closed nonconformity, although the row above gives them **View · Record**. `ncr-contain` and `ncr-disposition` now also need `ncr_can_close()` (`ncr_can_decide()`), and `ncr-reopen` needs `ncr_can_close()`: the **Quality / Technical Head and the administrators**. The screen offers those forms only to them. **Recording** a nonconformity is unchanged. *Not changed, raised as a question:* assigning an owner / due date and opening a corrective action from a nonconformity (`ncr-assign`, `ncr-capa`) still follow `ncr_can_raise()`.
    ²⁰ᵇ **Hold & witness points (X1 / DEF-QUA-002, owner 16 Nov 2026).** The register (`/hold-points`) and its actions (`hw-point-new / clear / waive / cancel / reopen / derive`) were mapped to a module "hold-points" that does not exist, so every role — the Master Admin included — was refused. They are now on the **Reporting** module (`idems`), the first right the screen's own checks ask (`hwp_can_view()` / `hwp_can_edit()`): **view** = anyone holding Reporting view (Admin, BD, SBU Head, BM, BAM, OM, AM, CO, Quality / Technical Head, inspectors), **raise / clear / waive / cancel / reopen** = Reporting or Jobs edit (MA, AD, BM, OM, AM, CO, inspectors). Finance holds no Reporting right (D-07: inspection findings are not money) and is not offered the register. Each action is also branch-scoped to the job the point sits on, and the register lists only the person's offices.
    **Added 2026-10-18, on the owner's rule *"only Quality or Technical Head can close it"*.** Before that, `ncr.close` and `capa.close` were held by MASTER_ADMIN and ADMIN alone — closing a nonconformity was a system-administrator act, which for an accredited inspection body is both a bottleneck and a segregation-of-duties weakness. The two quality roles are in the **Quality roles** table above. MASTER_ADMIN and ADMIN keep the rights because they resolve to the whole permission list; whether ADMIN should lose them is an open question for the owner, not an audit decision.
    **Still open (recorded, not guessed):** (a) who may **raise** a nonconformity — today nobody below Business Director can even open the register, and inspectors, who meet the nonconformity in the field, cannot; (b) whether everyone who may see a corrective action should also see the nonconformity behind it — five roles currently see the remedy with the finding hidden. See `docs/audit-2026-10/08-GAP-REGISTER.md`, GAP-004.

> ### RATIFIED BY THE OWNER, 2026-10-15 — one way in to approval configuration
>
> The owner found four approval **configuration** screens scattered across three
> areas and asked for them to be brought together:
>
> | Screen | Was filed under |
> |---|---|
> | Recruitment approvals (`/recruit-approvals`) | Recruitment |
> | Quote & report approvals (`/approval-rules`) | Admin |
> | Report approver map (`/approver-map`) | Reporting › Reports (IDEMS) |
> | Approval delegation (`/approval-delegations`) | Recruitment |
>
> `/approvals-setup` is now one workbench listing them, Admin carries **one**
> Approvals entry instead of two, and all four screens share a tab strip so they
> read as one place. **This is a way IN, not a merge:** every screen keeps its
> own route, its own handler and its own gate, and the workbench offers only
> what the viewer may already open — `apsetup_areas()` asks exactly the
> questions the menu tiles asked.
>
> **It grants nothing.** A recruitment administrator (`hiring.admin`) still
> reaches recruitment approvals and still cannot reach quote approvals;
> `lib/approvals_setup.php` contains no `ops_require()` at all, because it
> decides nothing. `tests/test_approvals_workbench.php` (51 assertions) fails if
> an area is ever offered to somebody whose own `can` is false.
>
> Not to be confused with **`/approvals`**, which remains the approvals **inbox**
> (R-27, `lib/approvals_hub.php`) — "what is waiting on me?" as against "where
> are the rules set?". The two screens link to each other.

## Connect — marketplace (external portals, K2b)

> ### RATIFIED BY THE OWNER, 2026-10-13 — the Coordinator and the marketplace
>
> The open question "should a Coordinator see Marketplace?" is **answered: yes.**
> The owner's words: *"Coordinator can see market place to find any resource if
> available in applicable region. Suppose Ahmedabad coordinator is looking for
> freelancer in Chennai he should be able to filter it accordingly."*
>
> This needed **no new permission** — `connect_market_can()` already admits the
> coordinator band. What it does settle is a rule that must not be "tidied away"
> later, because the whole module depends on it:
>
> | | Office-scoped? | Why |
> |---|---|---|
> | **Discovery** — searching the shared pool (`/connect-talent`, `/connect-bench`) | **No, deliberately** | A marketplace that shows a coordinator only their own branch is not a marketplace. The owner's Ahmedabad→Chennai case *is* the requirement. |
> | **Action** — invite, source, award, assign (`/connect-source`, award, `rasg_assign`) | **Yes, unchanged** | Finding somebody is not hiring them. Every action keeps its own module right and, where a job or requirement is involved, that record's own scope. |
>
> The region question is answered by **the professional's own declared mobility**
> (`connect_location_match`: exact city → within their stated travel radius →
> a city/state they selected → Pan-India → international), **never** by the
> searcher's branch. So a Chennai search returns the people who have said they
> will work in Chennai and **excludes** those who have not — a coordinator is
> never handed somebody who cannot take the job. This is the one place the
> R-30 office-scope tightening is intentionally not applied, and
> `tests/test_marketplace_region_filter.php` fails if anyone wraps the pool
> search in `scope_clause()`.

**Branch batch B7 — owner, 16 Nov 2026 (DEF-MKT-006). Applies the
"action keeps its own scope" row above to the Marketplace's own records. No new
permission, no new status.** Everything here is *inside the posting company*:
no outside-facing screen (`/pro/*`, the public job board, careers, the client
and agency portals, search, matching,
notifications, e-mail) shows or depends on a posting's office, and how
professionals and outside clients find, apply to or post work is unchanged.

- **A requirement belongs to one of our offices** (`cx_requirements.office_id`,
  additive). It is set automatically — the posting staff member's home office,
  or the requisition's office when made with "Post to Marketplace" from a
  requisition — and never taken from the form. Older rows were filled once
  from the requisition's office, else the home office of the one user whose
  name is on the posting; a row that could not be traced, and any posting made
  with no staff member signed in (client/agency portal, seed, CLI), has **no
  office**.
- **Seeing stays company-wide:** every office on the desk sees every posting
  and its applicants, on the board and on the posting itself.
- **Acting follows the office:** awarding, booking, billing, deploying,
  closing, changing, extending, adding or moving applicants, ratings,
  concerns, terms, readiness, vouchers, inviting from `/connect-talent` and
  allocating from `/connect-bench` (`cx_requirement_can_act`) are for a person
  whose office scope covers the posting's office (`scope_office_allows`);
  company-wide roles cover every office. Anyone else gets a plain refusal and
  does not see those buttons. "Duplicate" stays open to all: it makes the
  person's own new draft, in their own office. A form may not point at an
  application, booking or voucher of a different posting.
- **A posting with no office** stays actionable by every office that may use
  the Marketplace desk, exactly as before — no existing data is locked away.
  (`tests/test_branch_batch_marketplace.php`)

**Batch 2 — owner, 17 Nov 2026 (plan 29, point 13). No new permission.**
"Put forward one of your own team" on a requirement (`/connect-requirement`)
offers **the staff member's own office's team members only**
(`cx_own_team_inspectors()`: active `inspectors` whose home office is within
their office scope, `scope_office_clause('home_office_id')`). Company-wide roles
see everyone; a team member with no home office stays offered to all. The
`apply` action refuses a typed id of another office's team member
(`cx_own_team_allows()`), and the **suggested matches** offer only own-office
team members too (`connect_match_for_requirement(..., $inspectorIds)`, applied
before the one-person de-duplication). **Marketplace professionals, discovery
(`/connect-talent`, `/connect-bench`) and everything outsiders see are
unchanged.** (`tests/test_batch2_marketplace_own_team_passports.php`)

**Passports are for Marketplace professionals only — owner, 17 Nov 2026 (plan
29, point 14 and section D).** *"If I am an employee with SGS and SGS uses this
app, as an inspector I will not have any passport."* No permission is added or
removed; a staff screen and a public page stop serving the internal team.

- A **passport** belongs to a Marketplace professional: a `cx_professionals`
  row with its own `passport_token`, built on `/pro/profile` and shared from the
  professional's dashboard as `/p/<token>`. **Unchanged.**
- A company's **internal team member** (an `inspectors` row) has **no
  passport**. The staff "Team passports" tile is removed from the Marketplace
  area (and so from "Go to anything"); `/passport-share` keeps its gate
  (`connect_passport_can`) but now only says *"Passports are for Marketplace
  professionals; your own team members do not have one."* — it mints, shows and
  regenerates nothing. The requirement page's suggested matches show no
  "Passport" button for a team member (a professional's stays).
- The public page `/p/<token>` tells the two apart by **which table holds the
  token** (`connect_passport_lookup()` `_kind`, professionals asked first): a
  token on an inspector record shows *"This passport is not available."* (404,
  nothing about the person). A professional who is **linked** to an inspector
  record (`cx_identity_link` — a relationship, never a merge) keeps their own
  professional passport; only the inspector record's token stops.
- **No data is deleted:** `inspectors.passport_token` stays in the database,
  unused.

The manpower-marketplace staff desk (K2a) reuses coordinator/master and adds **no**
permission. The **professional-identity console** (`/connect-identity`, linking an
internal `inspectors` record to a marketplace `cx_professionals` record — a
relationship, never a merge) reuses the **same** coordinator/manager/master gate
(`connect_identity_admin_can` → `connect_market_can`) and adds **no new
permission**.

**Phase 6 · Batch 1 — one entitlement for every writer of the identity ledger
(R25 · invariant I26). This CHANGES who can do what, and is the only such change
in the batch.** `cx_identity_link` is written from two places: the marketplace
console above, and the recruitment candidate screen (`/candidate-link-pro`,
`/candidate-unlink-pro`). The console asked for Connect; the recruitment routes
asked only whether Recruitment was bought, so the same ledger was protected by
two different questions and a workspace without Connect could still create,
change and remove marketplace-professional identity relationships. Every writer —
`connect_identity_link_create()`, `connect_identity_candidate_link_create()` and
`connect_identity_unlink()` — now asks `connect_identity_admin_can()` **itself**,
so a route cannot inherit a weaker gate than the ledger requires. **No new
permission and no new entitlement engine**: the existing composition
(`licence_module_live('connect')` + the coordinator/manager/master band) is
reused unchanged. **Consequence:** a customer entitled to Recruitment but *not*
to Connect can no longer link a candidate to a marketplace professional. This was
approved by the owner on the explicit ground that such a customer has no
marketplace professional to link to, so the capability was unusable in any case
and permitting the write was entitlement leakage. The coordinator/manager/master
band itself is unchanged.

**ADR-001 DECIDED — recruitment starts from an approved request (owner, this
change).** A workspace policy, `requisition_requires_request`, **default ON**:
a NEW requisition may only be created by converting an **approved** hiring
request. Implemented as ADR-001 recommended — a policy, not a hard-coded block —
because the right answer differs per customer; an administrator switches it off
on Admin → System settings where the authorisation is the client's own order.

**Adds no permission and narrows none.** The same people may create requisitions;
they must now start from an approved request. The policy is asked at the WRITE in
both doors that create one — the direct requisition form (beside its INSERT) and
`pc_make_requisition()`, because the project-costing route is the direct path by
its own description and would otherwise have been a side door. The governed route
`hreq_to_requisition()` is **never** gated by it: once the policy is on it is the
only way in.

**Requisitions that already exist are untouched** (ADR-001 §1, M4 §39) — still
readable, editable, recruitable and closeable. No approval is invented for them
and none is demanded of them. Refusing them would be an outage, not a control.

ADR-001's option (d) — aligning the direct route's role band `is_coordinator_level()`
with the governed route's capability `mod.hiring.edit` — stays **open**: it
narrows an existing gate and needs its own regression pass.

**Branch visibility of parties, and of the numbers that describe them
(owner decision, this change).** Three things were settled together; none of them
adds, removes or widens a **permission** — each narrows what an already-permitted
user is *shown*, which is scope, not authority.

1. **A branch sees its own parties — in every list and every dropdown.** The
   client and vendor directory (`/clients`, `/vendors`), the dashboard tiles that
   count them, **and every picker that offers a party** now scope on
   `business_partners.home_branch_id` through the existing
   `scope_office_clause()`. That column has always existed and has always been
   editable on the 360 screen; nothing scoped on it, so every branch read the
   whole company's directory. **A party with no branch set stays visible to
   everyone** — the same "unassigned belongs to nobody, so it must not vanish"
   rule leads, opportunities and complaints already use. Every existing party is
   unassigned, so a strict filter would have emptied the register for every
   branch user on the day it shipped; the directory tightens as the field is
   filled in instead of going dark. Making it strict later is a one-line change
   and a data question, not a permission question.

   The rule has one home, `partner_office_sql()` (`access.php`), asked by the two
   shared helpers `clients_list()` / `vendors_list()` and by every list that
   builds its own SQL — **29 call sites**. A register that hides a party while
   the form beside it still offers that party is worse than no scoping at all,
   so the two are held to the same rule by construction.

   **Three party queries stay unscoped, deliberately:** the duplicate check
   before creating a party (a branch-blind "does this name exist?" is what stops
   two branches creating two records for one client), an internal trace helper,
   and **`inspectors_list()`**, the team-member picker. That last one is a
   standing business rule, not an oversight: EXAACT carries an **inter-office
   credit** on every job for the case where one office's person does another
   office's work, so scoping the picker would end cross-office deputation.
   Changing it is an owner decision about how the business runs.

    **Changed 2026-10-19, on the owner's rule (Q-B1):** *"Coordinator, Manager, Branch Manager or application manager — in short all equal to coordinator and above — can record."* **Record** is the Add verb on `ncr` (`mod.ncr.add`), which by the application's own rule implies View (`PERM_VERB_IMPLIES`), so these seven roles also read the register. It is granted to exactly `ACCESS_NCR_RECORDERS` (`access.php`) — the set `is_coordinator_level()` already means. **Not** granted: containment, disposition, assignment, the corrective action and closing, which stay `mod.ncr.edit` / `ncr.close` (the quality roles and administrators). Inspectors are below coordinator level and are not included. The screens ask `ncr_can_record()` for the form and `ncr_can_raise()` for every working route after it. A stored role grid or per-user set receives the right once (`access_apply_owner_grants()`), so it reaches a live system whose grid was already saved, and an administrator who later unticks it is not overruled. Verified by role over HTTP: `docs/audit-2026-10/evidence/live-owner-rules/`; guard rail `tests/test_owner_rules_ncr_vendors.php`.
2. **The two-office rule for work orders is now stated once.** A work order
   belongs to the office that **contracted** it as well as the one **executing**
   it, and both must see it. The calls register has always done this; the
   dashboard's open-work-orders count scoped on the executing office alone and so
   **under-reported against the very list it linked to**. The rule now lives in
   `call_office_clause()` (`access.php`) and both callers ask it. The register's
   behaviour is unchanged — this is the count catching up, not a widening.

3. **Three counts that ignored scope now honour it.** The open-leads and
   open-deals dashboard tiles and `quotes_expired_count()` (the Command Centre's
   "Quotations lapsed") were hand-written `COUNT(*)`s that reported the whole
   company while their registers scoped by branch. Each now uses
   `scope_office_clause()` on the column its own register already scopes on.

**The rule these four share:** a count and the list it links to must answer the
same question. Where they disagreed, the list was right. Evidence and the full
tile-by-tile trace are in `docs/phase7/OFFICE-SCOPE-DASHBOARD-AUDIT.md`;
`tests/test_office_scope_counts.php` holds the behavioural proof, and each fix
was mutation-tested by reverting it and confirming the suite fails.

**Scope on identity relationships (R15 · invariant I16 — PARTIAL, deliberately).**
No identity path evaluated branch scope at all. Each writer now applies
**per-end visibility**: you may not build or break a relationship out of a record
you are not allowed to open — an inspector by `scope_allows()` on its
`home_office_id` + `sbu`, a candidate by the new `scope_sbu_allows()` (candidates
carry a business unit and **no** branch, so `scope_allows(null, …)` would read
the missing office as Ahmedabad and wrongly refuse every branch-scoped user), and
a marketplace professional by existence alone, because it is **tenant-global** and
carries neither. This grants nothing: it applies an existing rule where it was
missing. Whether the *relationship itself* carries a branch — a branch-scoped
inspector linked to a tenant-global professional — is **open (Q5/Q11)** and is
**not** decided here. `scope_sbu_allows()` is the SBU-only scalar twin of
`scope_allows()`/`scope_office_allows()` and confers no rights of its own.

**A record id is never authorisation (R24 · invariant I25).**
`/candidate-unlink-pro` passed the posted `link_id` straight to the ledger, so any
live link in the workspace — including a professional↔inspector link with no
connection to candidates — could be removed from a candidate screen. The ledger
now takes the record the caller is acting for and refuses any link that is not
that record's, using the **same words** it uses for a link that does not exist, so
the refusal cannot be used to enumerate other people's relationships. The
marketplace console states the same expectation for its own axis. **No permission
changes**; the population that may unlink is identical.

**Reading is not creating (R22 · invariant I23).** `inspectors_list()` called
`link_inspector_users()`, which created `inspectors` rows and wrote
`users.inspector_id` — at seventeen call sites, inheriting whatever gate the
calling page had. The call is removed and nothing lazy replaces it.
`link_inspector_users()` survives as an **explicit** reconciliation on the People
screen that asks for `users.manage.branch` / `users.manage.global` **itself**
rather than inheriting the caller's gate (invariant I27), and is transactional so
a failure cannot leave a team member belonging to nobody. **No new permission** —
this is the right the People screen already required to link a login to a team
member by hand. The residual backlog is reported by `team_unlinked_logins()` on
the People screen and in system status, so it is visible rather than silently
healed. The **award→deployment bridge** (the "Create deployment" action on
the awarded requirement desk, which creates a PDSO `jobs` deputation from a
marketplace award — assigning the internal inspector the awarded person is linked
to) runs inside that same coordinator/master requirement desk and adds **no new
permission**; final operational authorization continues through the existing
competence/PDSO controls (ISO 17020 §41). The **unified sourcing screen**
(`/connect-source?job=ID`, linked from the job detail) ranks internal inspectors,
marketplace professionals and the client's bench for an inspection job and lets
staff assign the awarded person onto `jobs.inspector_id`; it reuses the same
coordinator/master talent right (`connect_source_can` → `connect_market_can`),
adds **no new permission**, and refuses to place a marketplace professional until
they are linked to an inspector record — so competence/authorization is not
bypassed (§41). **Requirement reuse** (duplicate + templates) runs inside the
existing client `market.post` right (client `portal/hire`) and the
coordinator/master requirement desk (staff Duplicate) — **no new permission**.
The **matching-weights admin** (`/connect-match-weights`, `connect_match_weights_can`)
is **master/admin only** and tunes ranking configuration, not access — it grants
no rights over any record, so it adds no data permission to the matrix. The **external self-service** side (K2b) adds two named portal
permissions, in the portals' own permission systems — separate from `ORG_ROLES`:

20. **Client portal — post a requirement + search the pool** — `pcan('market.post')` (`portal.php` `PORTAL_PERMS`; routes `portal/hire`, `portal/hire-req`, `portal/find`). A logged-in **client (company)** posts a technical-manpower requirement (posted to its own `poster_party_id`) and manages **its own** requirements' applications (shortlist / offer / award / reject) — ownership enforced in the route. The **same** permission also gates `portal/find` and `portal/hiring`: the client searches the shared professional pool (one keyword + filters), sees privacy-safe cards, **requests contact** (the professional approves — no contact is exposed by the search itself; enforced by `connect_privacy_resolve`), **invites** a professional onto one of **its own** open requirements (ownership re-checked in the route), and — on `portal/hiring`, the **buyer home** for a marketplace-first client — sees its own open requirements, applicants awaiting a decision, sent contact requests and saved searches (all scoped to `portal_partner_id()`). A marketplace-first client (a self-service `cx_organisations` signup with no inspection footprint) is routed to `portal/hiring` as its home and its nav drops the inspection menu; an established inspection client keeps its dashboard and merely gains a hiring shortcut. The same right also gates `portal/roster` — the client's **private bench**: a relationship over `cx_professionals` (add from marketplace/previous/manual, private notes & ratings, rehire onto its own open requirement), with the private relationship data scoped to `client_party_id` and never exposed to the professional or other clients. No new permission — browsing/inviting/the buyer home/the private bench are the discovery half of the same hire right. Granted by default to a full-access client user; removable per client user via the portal team editor. Maps `company` → client portal (adopted role→portal mapping). **Booking (owner, 11 Nov 2026 — "Should a client be able to record the booking from their own portal?" — "Yes"):** under the same `market.post` right, on `portal/hire-req`, the client records — and may change — the booking of a requirement **it posted and has awarded** (`AWARDED` or `CLOSED`): how it is booked (man-days, man-months, deputation, ongoing, frequency), how much, from and to (`connect_engage_client_save`, ownership re-checked). The client does **not** set the price: the rate stays the one the company booked, else the awarded bid, and the company's rate model, cadence and status are kept. Once the job has gone to billing, its vouchers have been invoiced, or the booking has ended, the client can no longer change it. Each save is on the job's activity timeline.
21. **Vendor portal — apply to requirements** — `vcan('market.apply')` (`cvp.php` `VENDOR_PERMS`; route `vendor/opportunities`). A logged-in **vendor / agency** browses OPEN/SHORTLISTING requirements and applies as itself (`applicant_party_id` = the vendor party; one application per requirement). Maps `agency` → vendor portal. (Agency-side *posting* reuses the same engine and is a later toggle; not enabled in K2b.)

### Client portal — the eleven permissions, and where each is enforced

The client portal has its **own** permission system (`PORTAL_PERMS` in
`lib/portal.php`), granted and withheld per colleague by the client's own main
contact through the portal team editor. It is separate from `ORG_ROLES` and
grants nothing on the staff side.

The governing rule is stated in the code (`lib/portal.php:1745`) and is a
**hard rule for this project**:

> *"Refuse rather than hide: hiding a link but leaving the address reachable is
> the bug this whole file exists to avoid."*

So every portal permission must be enforced **at the address**, not only in the
menu — and, where it guards a write, **inside the write** as well, so a second
caller cannot reach it through a door nobody locked.

| Permission | Enforced at |
|---|---|
| `calls` | `portal/calls`, `portal/call` |
| `reports` | `portal/reports`, `portal/report` |
| `reports.decide` | `portal/report-decision` |
| `invoices` | `portal/invoices` |
| `request` | `portal/request` **and inside `portal_request_create()`** |
| `complaint` | `portal/complaints`, `portal/complaint-new` |
| `deputation` | `portal/deputations`, `portal/dep-gate` |
| `deputation.approve` | `portal/dep-approve` |
| `issues` | `portal/issues`, `portal/issue` |
| `market.post` | `portal/hiring`, `portal/hire`, `portal/hire-req`, `portal/find`, `portal/talent`, `portal/roster` |
| `market.vouchers` | `portal/voucher`, `portal/voucher-file`, `portal/report-file` |

Frozen as `phpapp/tests/fixtures/portal_routes_gated.tsv`; a lost gate fails
`test_portal_perm_enforcement.php` **by route name**.

**The portal assistant is bound by these same permissions.** `/portal/assistant`
and `/vendor/assistant` read the signed-in user's rights before assembling
anything: the reports section requires `reports`, the nonconformity section
requires `issues`, and a user entitled to neither is told so instead of being
shown a box. The assistant is **not** a second route to a record the user was
refused — which it was until 18 Oct 2026 (**GAP-014**). Any new assistant
section must extend `cvp_ai_scope_keys()` with the permission that guards the
screen showing the same data.

Routes deliberately open to any signed-in portal user: `portal` (each tile tests
`pcan()` itself), `portal/login`, `portal/logout`, `portal/accept`,
`portal/password`, `portal/alerts`, `portal/reputation`, `portal/plans`,
`portal/pay`. Two are gated **harder** than a permission: `portal/team` requires
`cvp_client_is_admin()` and answers **404** to everyone else, and `portal/bench`
requires the signed-in party to resolve to an active agency organisation.

**The site limit and the alerts (branch batch P2/P3, owner, 16 Nov 2026 — Client
portal pack A1/A2).** No permission is added; two existing limits now reach the
places they missed:

- **Site limit on deputations (DEF-CP-003).** A client person limited to some
  sites ("Which sites" on Directory → Client portal → Change) sees and acts on
  the deputations of **those sites only**: `portal/deputations` (the deputation
  list and the attendance periods), `portal/dep-approve` (a period at another
  site is "not found", so its billable quantity cannot be approved),
  `portal/dep-gate`, and the "attendance to approve" count on the overview. The
  site is the work order's, as for work orders and reports; a deputation whose
  work order has no site recorded stays visible. No site limit = every site, as
  before. (`pdso_portal_deputations/approvals/approval($…, $siteIds)`.)
- **Alerts (DEF-CP-002).** `portal/alerts` and the Alerts count show each person
  only the alerts for what they may open: report alerts need `reports` **and**
  the report to be within their site limit; nonconformity alerts need `issues`;
  marketplace requirement alerts need `market.post`. "Mark read" / "Mark all
  read" act only on the alerts that person is shown, so one colleague cannot
  clear another's. The vendor portal's alerts follow the same rule with its own
  rights (`reports`, `issues`, `qualification`). (`CVP_EVENT_NEEDS`,
  `cvp_notify_viewer()` in `lib/cvp.php`.)

**Staff side of the client portal (branch batch P4/P1, owner, 16 Nov 2026 —
Client portal pack A4, Directory pack A1).** Still `mod.portal.edit` (unchanged
roles); the reach is narrowed to match the invite list:

- **Whose portal people (CP-021).** The register's "Who can sign in" list and its
  actions — Change (`/portal-user-perms`), Withdraw / Restore
  (`/portal-user-toggle`) and New link (`/portal-user-reinvite`) — cover **the
  same clients as the invite list**: the clients of the staff member's own
  office(s) (`partner_office_sql()` on the client's home branch). Company-wide
  roles (Master Admin, Admin) see every client; a client
  with no home branch is visible to all, as on the client register. Another
  office's client person is "not found" / refused, and nothing changes.
- **New link (CP-021).** The fresh sign-in link is **e-mailed to the client
  person's own address** through the app's mail (`ops_mail`, kind
  `portal_reset`, so the e-mail log keeps no copy of the link). Staff see only
  whether it was sent — never the link. (A brand-new invitation is unchanged:
  the link is shown once so staff can pass it on.)
- **Change permissions saves THAT person (DEF-DIR-005).** The form names the
  client it was drawn for; a save is refused whole unless the portal user
  belongs to that client and the client is within the staff member's reach, and
  a site limit can only name that client's own sites.

**Batch 2 — owner, 17 Nov 2026 (plan 29, points 10, 11, 12).** No permission is
added, removed or given to anyone new; each point narrows the reach of a right
people already hold, to match the client register above.

- **Vendor portal register (point 10).** Still `mod.portal.edit` (unchanged
  roles). The "Who can sign in" list on `/vendor-users` and its actions —
  Withdraw / Restore (`/vendor-user-toggle`) and New link
  (`/vendor-user-reinvite`) — cover **the vendors of the staff member's own
  office(s)**, the same rule as the client register (`partner_office_sql()` on
  the vendor's home branch, through `cvp_vendor_staff_partner_sql()` =
  `portal_staff_partner_sql()`). Company-wide roles see every vendor; a vendor
  with no home branch is visible to all. Another office's vendor person is
  refused and nothing changes. **New link** is **e-mailed to the vendor
  person's own address** (`cvp_vendor_send_new_link()` → `ops_mail`, kind
  `portal_reset`, so the e-mail log keeps no copy of the link). Staff see only
  "sent" / "could not be sent" — never the link, and nothing is left in the
  staff session. (A brand-new vendor invitation is unchanged: the link is shown
  once so staff can pass it on.)
- **Client requests (point 11).** "What clients have asked for" on
  `/portal-users`, the Directory tile's "new requests" count, and every answer
  on `/portal-request` (Accept & raise the call, Just acknowledge, Decline,
  Raise the call now) cover **requests from the clients of the staff member's
  own office(s)** (`portal_requests_all()` / `portal_staff_request()`, the same
  `partner_office_sql('p.home_branch_id')` rule). Company-wide roles see all; a
  request from a client with no home branch is visible to all. Another office's
  request is refused: no status change, no work order.
- **Site limit — the last three places (point 12).** A client person limited to
  some sites is shown only those sites (the work order's site; a work order with
  no site, or a report with no work order, stays in — the same rule as their
  report and deputation lists) in: the portal **assistant's report list**
  (`cvp_ai_scope_context`), the **"reports to accept or reject"** count
  (`cvp_action_centre`), and the client **dashboard tiles** for deputations
  (Active Projects, Resources Deployed, Upcoming Deployments, Pending
  Mobilizations, Schedule Conflicts, Pending Site Actions, Pending Client
  Approval) and reports (Pending Technical Reviews, Reports Issued)
  (`connect_client_dash($party, $role, $siteIds)`). No site limit = every site,
  as before. The money tiles (billable events) carry no site and are unchanged.
  (`tests/test_batch2_portal_vendor_link_requests.php`,
  `tests/test_batch2_portal_site_counts.php`)

The **professional portal** (`/pro/*`, `cx_professionals`) has no per-user
permission layer by design: an account there is **one individual**, with no
colleagues to restrict. Its routes are bounded by the signed-in professional's
own id.

See `docs/audit-2026-10/11-EXTERNAL-PORTAL-AUDIT.md` for the measured audit of
all 77 external-portal routes.

Neither touches `ORG_ROLES` or any staff permission; the staff desk lifecycle
(K2a) and these external capabilities operate on the same additive
`cx_requirements` / `cx_applications` tables.

### Qualification & role taxonomy — configuration (K13 / #2)

The ITI→MBA qualification taxonomy (`/connect-qualifications`) adds **no new
permission** — it reuses the **Lookups** gate (row 12) exactly:

- **View** (read-only, at a glance): `connect_qualtax_can()` — master, admin-level
  or coordinator-level (same readers as the K0 industry taxonomy).
- **Configure** (add / edit / switch on–off every job family, role, qualification
  level, ITI trade, certification): `connect_qualtax_manage_can()` = `is_admin_level()`
  — the same door as Lookups & custom fields. The masters are runtime data, not
  hard-coded; a seeded row is marked **built-in** (`is_system=1`) and can be edited
  or switched off by an admin.
- **Hard-delete a built-in row**: `is_master()` only (mirrors Lookups' "delete
  built-in list = Super Admin only"). Admin-added rows are deletable by any admin.

No `ORG_ROLES` entry, object status or module gate is introduced.

### Verification & moderation desk (K14 / #3)

The verification desk (`/connect-verify`) adds **no new named permission** — it
reuses the coordinator/moderation level, like other back-office review desks:

- **Review** (approve / reject a pending identity or credential check, which
  recomputes the professional's verification tier): `connect_verify_can()` =
  `is_master()` or `is_coordinator_level()`.
- **Submit** a check about oneself: a **professional** does this from their own
  `/pro` portal (`pro/verify`) on their own record — the portal's own session
  (`cxpid`), not an `ORG_ROLES` permission.
- The tier ladder (Registered → ID-verified → Credential-verified → Proven) is
  written to the existing `cx_professionals.verification_tier` column and is
  elevated **only** by a genuine VERIFIED decision (human moderator now, or a
  KYC/DigiLocker provider through the same seam later) — never by a deterministic
  format pre-screen alone. No object status or module gate is introduced.

### Rating-integrity desk (Review & Reputation)

Two-way ratings (`cx_ratings`, K9) gain a **payer-reputation** view (a professional
records how a client paid — on-time / late / partial / unpaid — so a freelancer sees
a client's reputation before accepting) and a **rating-integrity dispute**
(`cx_rating_disputes`, lifecycle in `03-object-lifecycles.md`). Adds **no new named
permission**:

- **Raise a report** on a rating **about oneself**: a **professional** from their own
  `/pro/reputation` (portal session `cxpid`), a **client** from `/portal/reputation`
  (scoped to `portal_partner_id()`). Ownership is re-checked in the route — a party can
  only report a rating whose ratee is themselves.
- **Investigate & decide** (`/rating-disputes`): reuses the coordinator/moderation
  level — `connect_market_can()` — like the K9b dispute and the verification desk.
  Outcome `UPHELD` / `ANNOTATED` / `REMOVED`; a removed rating is hidden from scores,
  never deleted. No object status (beyond the new object's own) or module gate is
  introduced.

### In-app messaging (K15 / #4)

Per-engagement two-way threads (`cx_messages`, keyed to a `cx_applications` row)
add **no new named permission**:

- **Staff desk** (`/connect-messages`): read/post on any marketplace thread —
  `connect_msg_staff_can()` = `is_master()` or `is_coordinator_level()` (same as
  the rest of the marketplace desk).
- **Professional** (`/pro/messages`): read/post **only on their own engagements**
  (`applicant_professional_id` = the logged-in `cxpid`), enforced by
  `connect_msg_pro_owns()`; their own `/pro` portal session, not an `ORG_ROLES`
  permission.
- The engine is identity-agnostic (`staff | professional | client | vendor |
  inspector`), so the client and vendor portals attach to the same threads later
  under their own portal sessions. No object status or module gate is introduced.

### Reusable KPI board (ops "concern" + client + freelancer + inspector dashboards)

`connect_kpi_board($scope)` + `connect_kpi_render()` (`lib/connect_kpi.php`) are
**read-only** and add **no new permission, table or status**. One engine powers
all four dashboards; the difference is only the **audience + scope**:

- **Ops dashboard** (`views/dashboard.php`, managers/coordinators branch): staff
  scope — aggregates across all clients. Shown only when `connect_enabled()`.
- **Client portal dashboard** (`views/portal/dashboard.php`): client scope —
  `party_id = portal_partner_id()`, so a client sees only their own figures.
- **Freelancer dashboard** (`views/pro/dashboard.php`, `audience = 'pro'`): the
  professional's own scope — `party_id = cx_professionals.id` from the `/pro`
  session (`$_SESSION['cxpid']`). The freelancer sees only **their own** cockpit:
  assignments (`connect_engage_summary_pro`), booked value (deterministic
  man-days/months × rate from `connect_engage_describe` — never a payout), their
  applications (`cx_applications.applicant_professional_id`), client ratings on
  their own awards (`cx_ratings` ⋈ `cx_applications`), and verification tier/trust
  (`connect_verify_*` / `connect_trust_score_pro`). Escrow/earnings stay out of
  scope (gated slice #10).
- **Inspector dashboard** (`views/dashboard.php`, `is_field_inspector()` branch,
  `audience = 'inspector'`): the field engineer's own scope — `party_id =`
  the user's `inspector_id`. Reuses **only** the core `jobs` register scoped to
  their own `inspector_id` (active / completed / overdue / reports-pending — the
  same counts the inspector dashboard already ran) plus the inspector rating
  summary (`cx_rating_summary_for_inspector`). **Least-privilege: the board
  carries NO money figure** — billing and profitability are never the
  inspector's — so it adds no visibility the role did not already have.
- Every metric **reuses an existing engine** — `financial_rollup(['partner_id'])`
  (revenue), `complaints.partner_id` (concerns), `report_docs.client_id` (reports
  pending), `jobs↔calls.client_id` (inspections), `cx_ratings`/`rating_all`
  (ratings), plus the professional-side engines above — so no metric is
  re-implemented. Each figure is only ever as visible as the register behind it
  (client scoping via `portal_partner_id()`; freelancer scoping via the `/pro`
  session identity).
- **One card design.** `connect_kpi_render()` emits the design-system's universal
  KPI card markup (`.kpi-row` / `.kpi` / `.tone-*` / `.pill`, per
  `docs/DESIGN-SYSTEM.md`), so on any screen that loads `assets/css/app.css` the
  board **is** the shared component. The self-contained client/freelancer portals
  get an identical look from a single zero-specificity `:where()` fallback that
  reads each portal's own tokens — the real component always wins where present,
  so there is one look and no style drift.

### Engagements / bookings + freelancer self-service (K20)

The booking model (`cx_engagements`) and the freelancer gap-fill add **no new
permission**:

- **Record / edit a booking basis** (man-days / man-months / long-term deputation /
  continuous / regular frequency), rate, dates and lifecycle status: on the
  requirement desk, which is already coordinator/master-gated (K2a). A booking can
  be recorded only **after** the requirement is AWARDED, and its subject is derived
  from the awarded application (professional / inspector / bench).
- **Withdraw an application**: a professional withdraws only their **own** live
  application (APPLIED / SHORTLISTED / OFFERED → WITHDRAWN) via their `/pro`
  session (`connect_pro_withdraw`, scoped to `cxpid`); never someone else's.
- **My bookings** (`/pro/bookings`): a professional sees only engagements where they
  are the subject (subject-scoped). No object status beyond the documented
  application/requirement lifecycles is introduced; `cx_engagements.status`
  (BOOKED / ACTIVE / COMPLETED / CANCELLED) is the engagement's own lifecycle.

### Engagement vouchers — inclusive / exclusive, per-day / per-deployment (K21)

The claim raised against a booking (`lib/connect_engvoucher.php`). Adds **no new
named permission**; it reuses the identities already in place:

- **Rate model chosen at posting.** The client sets `rate_inclusive`
  (all-inclusive vs fee-only) and `voucher_cadence` (per-day vs per-deployment) on
  the requirement when posting; the booking inherits them, and the desk can adjust
  them on the booking form (coordinator/master gate, K2a). This decides whether the
  voucher may claim reimbursable heads (travel / hotel / local conveyance /
  allowances) — an INCLUSIVE rate claims none.
- **Raise / edit / submit a voucher**: the subject owns only their **own** vouchers.
  A marketplace professional does this in their `/pro` session (`/pro/vouchers`,
  scoped to `cxpid`); the same engine serves an **inspector on a company/agency
  roll** and an **agency-bench** person (subject-scoped, `connect_engv_for_subject`).
  A draft is editable; once **SUBMITTED** it is locked to the raiser.
- **Approve / mark paid**: on the marketplace desk's existing coordinator/master
  gate — **no new permission**. The voucher lifecycle
  (`DRAFT → SUBMITTED → APPROVED → PAID`, `REJECTED` return path) is recorded in
  `docs/03-object-lifecycles.md`. Every money figure stays scoped to the person who
  earned it; nothing here widens who can see billing or profitability.
- **Platform commission + settlement + report gate** (matchmaker model — the
  platform is not a paymaster and carries no liability): a nominal commission on the
  **fee only**, shared between client and professional by the master-set client share — default 50/50 (rate = admin setting `connect_commission_pct`, default
  5%). Neither side "pays" through the platform — **both confirm** payment (client
  paid / professional received), and only when both confirm does the voucher settle
  (→ PAID). The professional's **inspection report** (`cx_engagement_reports`) is
  withheld from the client until the engagement is cleared; the client report-serve
  route (`portal/report-file`) enforces this (HTTP 402 until cleared). **No new
  permission** — the client uses `market.vouchers`, the professional their own `/pro`
  session; commission and settlement are recorded, not gated by a new right.
  The **commission rate** is set on the marketplace board (`/connect-requirements`)
  by a **master** only (`setting connect_commission_pct`), which also shows the
  platform's earned / settled / in-review commission rollup. On a **client-posted**
  job the ops desk shows the vouchers **read-only** — the approve / send-back /
  mark-paid and raise actions are hidden (the client drives them in its portal);
  `connect_requirement_client_posted()` decides this from the poster being a client
  party.
- **Client review of a posted-job voucher** (marketplace matchmaker model): the
  client who posted the job reviews the professional's claim in its portal — sees the
  fee, day lines and **receipts**, then **returns for clarification** (with a note) or
  **approves**. Gated by a **new client-portal permission `market.vouchers`** (portal
  permission system, not the staff `can()` matrix — doc 02's staff matrix does not
  govern portals); ownership is by the voucher's `poster_party_id`, so a client sees
  only its own posted-job vouchers. The professional reopens a returned voucher and
  resubmits. No new voucher status.
- **Supporting documents (receipts / bills)**: the raiser attaches receipts to back
  the claim; the approver sees them with the voucher. Allowed only while the voucher
  is DRAFT or SUBMITTED, frozen once approved/paid. A professional serves and manages
  only their own (`/pro/voucher-file`, subject-scoped); the desk views/uploads on the
  same coordinator/master marketplace gate (`/connect-voucher-file`). Additive
  `cx_engagement_voucher_files`; **no new permission**.

### Matching, cross-pool trust & AI re-ranking (K17 / #6)

Adds **no new permission**. The recommender and its cross-pool Trust Score render
on the requirement desk, which is already coordinator/master-gated (K2a). The
optional **"Rank with AI"** toggle simply reuses the existing `ai.php` seam
(`ai_enabled()`) plus a `connect_ai_match` on/off setting (admin, via Settings);
AI may only reorder/annotate the rule-provided shortlist — it can never change
eligibility, invent a candidate, or bypass any gate.

### Labour-market analytics (K19 / #8)

The analytics dashboard (`/connect-analytics`) is **read-only** and adds **no new
permission**: `connect_analytics_can()` = `is_master()` or `is_coordinator_level()`
(the marketplace/reporting desk). Every figure is a live aggregation over the
existing cx_* tables — no new table, status or module gate is introduced.

### Agency bench workspace (K18 / #7)

The agency bench (`/connect-bench`) adds **no new named permission** and enforces
the **privacy invariant**:

- **Manage** an agency's private roster + allocations: `connect_bench_can()` =
  `is_master()` or `is_coordinator_level()` (the marketplace desk).
- **Privacy**: bench people live in their own `cx_bench` table, scoped by
  `org_id`, and are **never** written into the shared self-registered pool
  (`cx_professionals`) or surfaced in public search / the shared recommender — an
  agency's employees stay private to that agency (an invariant asserted by
  `test_connect_bench.php`). Every bench read/allocation is org-scoped, so one
  agency can never see or allocate another's people.
- Only orgs of type `MANPOWER_AGENCY` / `RECRUITMENT_AGENCY` may hold a bench.
  No object status or module gate is introduced.

### WhatsApp / SMS / Email channel (K16 / #5)

Outbound alerts (`/connect-channels`) add **no new named permission**:

- **View** the channel desk (outbound log, counts): `connect_channels_can()` =
  `is_master()` or `is_coordinator_level()`.
- **Configure** (set delivery mode; edit / approve / enable templates):
  `connect_channels_manage_can()` = `is_admin_level()` — same door as Settings /
  Lookups.
- **Consent** is the professional's own: they opt in per channel (WhatsApp / SMS /
  Email) from their `/pro` profile; WhatsApp/SMS need a mobile. No opt-in → no
  message. Nothing is *sent* until an admin sets mode `live` AND a provider is
  connected AND the template is APPROVED — until then messages are recorded, not
  sent. No object status or module gate is introduced.

---

**Public workspace signup (`tenant_requests`).** A new company applies for its own
operations workspace at `/get-started` (public; off until the operator enables it).
Approve / decline / provision on the Workspaces panel reuse **`can_manage_tenants()`**
(Master Admin, base/control domain only) — **no new permission**. See
`03-object-lifecycles.md` → `tenant_requests.status`.


---

**Multi-source fulfilment (Phase 4)** — `recruit_fulfil.php`, route
`requisition-allocations` (POST only), module `hiring`. An approved requirement's
headcount can now be **promised to several sources** (own payroll, direct
recruitment, internal transfer, manpower agency, sub-contract agency, supplier,
freelancer, consultant, marketplace, client bench) without becoming several
requirements. **Adds no new permission, no new route family and no new status on
any existing object**; the one new object's lifecycle is in
`03-object-lifecycles.md` → *Fulfilment allocation*.

*Who may change sourcing.* `rful_may_touch()` asks, in this order, each failing
closed: **entitlement first** (`licence_blocks('mod.hiring.view')`, with **no
master bypass** — a superuser on a workspace that has not bought hiring is
refused exactly like anyone else), then the **existing** `is_coordinator_level()`
band the recruitment write routes already require, then the record, then the
**actor's** branch scope (`scope_allows`). The order matters and is asserted: a
refusal is decided about the *person* before anything that would describe the
record, so refusals cannot be used to probe another branch's requirement or its
remaining headcount. Promising or resizing seats is **recruitment execution**, so
it also passes **M6's** gate (`rexec_block_reason`), which asks **M4's**
boundary — Phase 4 adds no second opinion about whether a requirement may be
executed. Giving seats **back** (release / cancel) is deliberately *not* gated on
executability: tidying up after a requirement is cancelled is not execution.

*Who may credit a source with a person.* The candidate↔allocation link
(`candidates.allocation_id`, one additive nullable column) is accountability, not
a form field, so — exactly as M5 did with ownership — it **left the blind field
list** on the candidate save and travels one door, `rful_attach()`, asked about
the requirement the save is **producing**, not the one the candidate is leaving.
`rful_enforce_candidate()` re-reads the row after **every** write path (candidate
create, candidate edit, stage move) and removes any link that points at another
requirement's allocation, at a closed one, or at a source already credited to its
promise — so a link written by a raw statement or by a field list somebody adds
in future is undone, not merely refused at the door.

*What Phase 4 may never do.* It never decides whether somebody holds a position
and never removes anybody from one — that stays M6's. The worst a Phase 4 control
can do is drop a **credit** back to the direct path. Under contention it applies
the ratified capacity rule unchanged (invariant I21): **never overfill, never
displace an established holder**; where simultaneous claims cannot be resolved
without displacement the contested claims are refused and the capacity is left
for a later valid transaction. A source entity named on an allocation (a supplier
from `business_partners`, a marketplace requirement from `cx_requirements`, a
professional from `cx_professionals`, a person from `inspectors`) must exist **in
this workspace** — isolation is structural, one database per tenant, so a row
from elsewhere is simply absent and is refused rather than written — and a source
whose entity lives behind a module the workspace has not bought (marketplace,
professionals → `connect`) is refused outright, because hiding the option from a
dropdown is not a control.

*Vocabulary.* The source list **extends the existing configurable
`req_sourcing_model` lookup** already registered in Masters — no new master was
built. A workspace may add its own sources without a line of code; the shipped
values always stay valid so that narrowing the list never makes yesterday's
allocations unreadable.

---

## Requirement change control & requirement reviews (Gates 2 and 3)

Two permissions were added to the Recruitment group. Both are **per role**, both are
configurable on the Roles & permissions screen (`/access`), both ship in **no role's
defaults**, and neither carries any system-wide power.

| Permission | What it allows | Shipped default |
|---|---|---|
| `hiring.material_change.propose` | Propose a change to an **approved** requirement, which goes to change control instead of editing the record (Gate 2) | in no *operational* role's defaults |
| `hiring.review.clear` | **Decide** a requirement review on a candidate — continue or reject — after a stricter approved version became effective (Gate 3) | in no *operational* role's defaults |

> **CORRECTION, made in Gate 4.** The two rows above previously read "in no role's
> defaults". That was inaccurate, and the inaccuracy mattered because it is the kind
> of claim somebody would rely on when deciding who can do what.
>
> `role_defaults_base()` gives **MASTER_ADMIN and ADMIN `array_keys(PERMISSIONS)`** —
> every permission in the catalogue — by this product's own long-standing design.
> So both permissions above ARE held by an administrator out of the box. What is true
> is that they are in no *operational* role's defaults: a Coordinator, Asst. Manager,
> Branch Manager, Inspector and so on do not get them, and an organisation grants
> them deliberately. Gate 4's own battery asserts that distinction directly rather
> than restating the old claim.

**`hiring.material_change.propose` EXTENDS, it does not replace.** Gate 2's model is
role defaults **plus** this permission **plus** recruitment scope — three things that
add up. A role that may already change this requirement keeps that ability; the
permission extends the same ability to roles whose defaults do not include changing
requirements; scope applies on top of both. Requiring the permission *instead* would
have silently removed, on upgrade, something every existing recruiter can do today.

**`hiring.review.clear` is deliberately NOT the general recruitment edit right, and
is deliberately NOT additive to it.** Somebody who may move candidates all day is not
thereby entitled to rule that a person still meets a requirement the business has
just raised — that is the judgement this permission places with a named population.
So deciding a review needs this permission **and** recruitment scope, and nothing
else grants it.

**Visibility is not authority.** Anyone who can legitimately open the candidate can
see that a review is open, what changed, and which versions are involved. Deciding it
is a separate question, asked inside `crev_continue()` / `crev_reject()` /
`crev_reconsider()` rather than on the screen — so a direct POST, a crafted URL, a
stage move, an offer, a joining, a workforce conversion or a future caller all meet
the same refusal. A user without the permission, and a user with the permission but
scoped to another branch, are both refused at the service.

**Scope derives through the requirement**, as it always has: candidate → requirement
→ office / business unit. Gate 3 adds **no candidate office column**.

**What a review stops:** while one is open, an **offer** and a **joining** can never
proceed — those are commitments to a person. An organisation may optionally let
screening and interviewing continue (`crev_allow_screening`), which is off by
default. This is answered inside the existing execution gate
(`rexec_block_reason()`), per action, in the existing `REXEC_ACTIONS` vocabulary —
there is no second action-policy engine.

Neither gate adds a route that bypasses the module gates: the one new route
(`candidate-review`) is reached only by somebody who can already open the candidate,
and it performs no authority check of its own precisely so that the check cannot be
skipped by reaching the functions another way.

---

## Self-approval governance (Gate 4)

Approval authority was never a permission in this product and still is not: it is
`appr_can_act()` — being the step's named user or its configured role — followed by
`appr_guard()`. Gate 4 changes **who may approve their own request**, and makes that
the organisation's decision instead of a line of code.

### The two switches

| Setting | Question | Shipped default |
|---|---|---|
| `appr_self_approval` | May **anyone** decide a request they raised? | **OFF** |
| `appr_self_master_exception` | May a **superuser** decide a request they raised, when the above is off? | **OFF** for a new organisation |

Both are **organisation-level**, in the `settings` table that already *is* this
product's organisation configuration (one database per tenant, so a setting is
per-organisation by construction). They are configured on **Recruitment approvals**
(`/recruit-approvals`, gated `hiring_admin_can()`), and changing either is recorded
on the sealed audit chain by `setting_set()` — the setting, its old value, its new
value, who changed it and when.

There is **no per-user mechanism** and Gate 4 does not create one: no
`users.allow_self_approval`, no per-request exemption, no username comparison, no
route-level rule. The only individual attribute involved is `users.is_superuser`,
which is this platform's existing master model.

### What the rule now covers — and what it covered before

`appr_guard()` used to open with `if ($entity !== 'HIRING_REQUEST') return '';`. So
the requester/approver comparison existed for a hiring request and for **nothing
else**: a requisition, an offer, a salary structure and — after Gate 2 — a material
change to an approved requirement were every one of them decided with no segregation
check at all. The person who proposed a change could approve their own change.

It is now asked at that **one choke point**, for every entity on the common engine
(`HIRING_REQUEST`, `REQUISITION`, `OFFER`, `SALARY`, `HREQ_CHANGE`, `REQ_CHANGE`),
using the requester identity the engine already records — the business object's own
raiser id where it has one, the chain's `requester_id` otherwise, and **never a
name**. The direct offer-approval fallback (used when no rule matches) asks the same
function, so the control is not present on one path and absent on the other.

*Candidate Hiring* is listed in Gate 4's brief but **does not exist as an approval
entity in this product** — Gate 3 explicitly deferred it. There is nothing to
enforce the rule on, and Gate 4 does not create it.

### The master exception, and what an ADMIN does not get

`is_master()` is `users.is_superuser` → the `MASTER_ADMIN` role. An **ADMIN role is
not a superuser**, so an administrator does **not** get the self-approval exception
however many permissions they hold — asserted directly, because "admin" and
"superuser" are easy to conflate and the whole control rests on the difference.

Every use of the exception is written to the audit spine with
`outcome='SELF_APPROVAL_EXCEPTION'` and carries the requester id, the approver id,
`requester_equals_approver`, which exception was used, the organisation, the approval
entity, the request reference, the path (chain or direct) and the time.

### A configuration change never reaches a decision already in flight

The policy in force when a chain is opened is **stamped onto the request**
(`recruit_approval_requests.self_policy`), exactly as the engine already freezes each
level's SLA policy onto its step. A request raised under one policy is judged under
that policy however the organisation reconfigures itself afterwards; a request raised
after the change gets the new one. A row from before Gate 4 carries no stamp and is
judged under the current configuration, which is the only policy it has.

### Capabilities that remain separate

```
Approval authority   ≠   Recruitment edit
                     ≠   hiring.review.clear   (Gate 3)
                     ≠   data.salary
```

Being a named approver grants none of the others, and none of the others grants
approval authority.

---

## Review Required triggers & the utilisation breakdown (Gate 6B) — no permission changed

Gate 6B put two existing decisions where a person can see them. Neither adds,
removes or moves a permission, and neither is a new door.

| What changed | Who may do it |
|---|---|
| Switch the "redefined" Review Required trigger on or off | the **existing** gate on the Recruitment approval-rules screen — `hiring_admin_can()` (administrator, or `hiring.admin`). No new permission. |
| Switch the "stricter" trigger off | **nobody.** It is a constant in the engine (`CREV_TRIGGER_ALWAYS`), not a setting. There is no control for it and writing the setting by hand has no effect. |
| See the per-person utilisation breakdown | unchanged — whoever could already open Reports, with the same office scope |

Three things worth being explicit about:

- **The trigger was always configurable; it was never *visible*.** Since Gate 3
  the `crev_trigger_redefined` setting has governed this, but only a database
  write could change it — unusable for an administrator, and because it bypassed
  `setting_set()`, completely unaudited. Gate 6B moves it onto the screen that
  already carries the organisation's other recruitment governance decisions, so
  the change now goes through the sealed configuration audit trail like any other
  setting. The default is ON, so no existing organisation's behaviour moved.
- **A locked rule is shown, not hidden.** The screen states that a stricter
  requirement always raises a review and that this is not configurable, and
  offers no control for it. Hiding it would leave an administrator guessing why
  reviews still appear; offering a switch that the engine ignores would be worse.
- **Being left out of a sum is not being hidden.** The utilisation breakdown now
  omits people who have been hired but have not started. The same people remain
  on the team register, remain selectable in that very screen's person filter,
  and are named on the availability board as hired but not yet joined. No
  visibility rule changed.

---

## Workforce activation (Gate 5) — no permission changed

Gate 5 moved **when** a hired person becomes operationally active (on joining,
not on acceptance). It did **not** move **who** may do anything, and it adds no
permission. Recorded here so a later reader does not go looking for one.

| Action | Who, before and after Gate 5 |
|---|---|
| Record a joining / clear a joining | coordinator level (`is_coordinator_level()`) **plus** recruitment scope on the application |
| Convert an accepted candidate to a team member | unchanged — coordinator level, scope, branch, team, and the execution boundary |
| Change a team member's status by hand | the existing Inspectors master form (admin) |

Two consequences worth being explicit about:

- **The joining screen is the only activation mechanism.** An administrator can
  still set a status by hand on the Inspector form — that door existed before and
  is unchanged — but nothing else in the product activates anybody. Deliberately
  one door, not two.
- **Visibility is still not authority.** A joining-pending colleague is visible in
  the full team list and in the Next Action follow-up to everyone who could
  already see team members. Seeing that somebody has not joined confers no right
  to record their joining.

```
Workforce activation   =   the joining screen only
                       ≠   acceptance of an offer
                       ≠   approval authority (Gate 4)
                       ≠   hiring.review.clear (Gate 3)
```

## Self-serve companies — sign up, trial, plan, view-only, clean-up (owner, 22 Oct 2026) — no new permission

The owner's decisions: a company may sign itself up, try the **full application for
30 days** (no card), choose and pay for its own modules and logins, and becomes
**view-only** — never locked out — when its trial or paid period ends. A free-trial
company nobody signs in to is e-mailed every **15 days** and deleted after **90 days**.
Built in `lib/selfserve.php`. **No permission was added**; each action reuses an
existing gate.

| Action | Who | Gate |
|---|---|---|
| Sign up at `/start` (company name, name, e-mail, mobile, password, an e-mail code) | anybody, while the platform owner has switched self sign-up on | public, in front of `require_login()`; form token, robot trap, 5 sign-ups an hour per connection, 5 tries per code, code valid 30 minutes |
| Switch self sign-up on/off; set trial days, trial storage, trial logins, warning interval, deletion days, automatic deletion | the platform owner | the Companies console — `superadmin_can()` (unchanged) |
| Set module and login prices, Razorpay keys | the platform owner, on the control install **only** | `billing-config` now also refuses inside any company workspace (`current_tenant() !== ''`). Before 22 Oct a company's own Master Admin could change the prices and keys its own purchases used |
| Choose, change or renew the company's plan and pay | the company's owner / administrators | `billing_can_manage()` = Master Admin or `settings.manage` (unchanged gate of `/subscription`) |
| Read everything, print, export while view-only | every user of the company, as their role already allows | unchanged — only *saving* is refused (`saas_blocks_write()`), except paying, signing out, changing one's own password / second factor / signature |
| See when every company was last used, its trial and paid dates, warnings sent, deletion date, and the list of deleted companies | the platform owner | the Companies console |
| Set the storage allowance (base GB per company, GB per login, extra pack size and price) | the platform owner | the Companies console — "Storage allowance" panel; applied to every hosted company at once and every night |
| Grant one company extra storage (a special arrangement) | the platform owner | the Companies console — Manage → "Extra storage granted (GB)" |
| Buy extra storage packs | a self-serve company's owner / administrators | `billing_can_manage()` — part of "Choose / change your plan", same Q-L rules |
| See the company's storage used, allowance and what uses the space | the company's owner / administrators | `/subscription` (`billing_can_manage()`); every user sees the line at 100% |
| See a company's payments; refund a payment (full or part) or a second payment for the same order | the platform owner | Companies → Manage → Payments & refunds — `superadmin_can()` (the existing console gate). The owner's refund rules (8 Nov 2026) are in doc 03, Plan payment |
| Save the Razorpay webhook secret | the platform owner, on the control install only | Platform setup — `billing_keys_refusal()`, the same gate as the payment keys |
| Open a refund's GST credit note | the company's owner / administrators (their own company's only); the platform owner | `/subscription-invoice?cn=` (`billing_can_manage()`, and the credit note must belong to a refund in this company); `/gst-invoices` (`superadmin_can()`) |
| Razorpay's payment notice | Razorpay (no account) | `/razorpay-webhook`, public, refused without a valid signature for the saved secret; it only names an order, and what is applied is read back from Razorpay with the platform's keys |

**Storage allowance (owner, 22 Oct 2026 — "storage usage and limits on the paid users also").**
Every company hosted on the platform — free trial, self-serve paid, or created by
hand on the console — has an allowance once the platform owner has set one:
base GB + GB per login × its logins + bought packs × pack size + any extra GB
granted. Nothing set means no limit; no number is built in. At the allowance only
**new files** are refused (`saas_storage_full()` in `index.php`); records still
save, nothing stored is touched or deleted, and the company is not made view-only.
Owners are e-mailed once as 80 %, 95 % and 100 % are crossed (nightly run). A
self-hosted licence install is never limited by this.

A payment is applied from the order **recorded on the server** when the payment
window was opened (`saas_orders`), once; the browser can no longer say what was
bought (before 22 Oct, `/subscription-verify` and `/billing-verify` applied the
modules and seats posted back by the page). The platform owner's "Log in as" never
counts as the company using its workspace. Companies created by hand on the console
are not self-serve and none of the trial, view-only or deletion rules apply to them.

A marketplace company or agency that joined through `/join` may sign in at
`/portal/login` while the marketplace is on, even when staff have the client portal
switched off (it used to be told "sign in now" and then shown "Not found"); clients
*invited* by staff still need the client portal on. Both kinds can now reset a
forgotten password themselves (`/portal/forgot`, a one-hour, single-use link).

**Marketplace sign-ups follow one switch (22 Oct 2026).** A professional may
register at `/pro/register` only while the marketplace is available on the install
(`install_marketplace_enabled()` — the add-on AND the kill-switch), exactly like a
company or agency at `/join`. Before, the professional sign-up asked only the
kill-switch. **Coupons with online payment:** a valid coupon is taken off the
amount charged by Razorpay, recorded on the order, and counted as used only when
the payment is confirmed (it used to be ignored whenever online payment was on).

**Platform setup (owner, 22 Oct 2026) — `/platform-setup`, platform owner only** (`superadmin_can()`: Master
Admin on the main site; a company's administrators are refused and never see it). It holds the
**platform e-mail** every hosted company uses when it has not set up its own (a general sender and a
separate **account & password** sender for sign-up codes, temporary passwords and reset links), the
**payment keys check** (asks Razorpay to confirm the keys; creates nothing, moves no money), the
**go-live checklist**, and the main site's recent e-mails. The platform mailbox password is kept in
the protected data folder above the web root, never in a company database, so no company can read
it. Sign-up codes, temporary passwords and reset links are never kept in any e-mail log. No new
permission — it reuses the Super Admin gate.

**Super Admin's own address (owner, 22 Oct 2026: "Super admin shall have complete different
login")** — Platform setup → *Super Admin address*. The platform owner saves an address such as
`operations-admin.mghaiapps.com` (it is added to the main site's own addresses in the workspace
registry, so it opens the platform's own site and never a company), creates it in the hosting, opens
it, signs in there and presses **Lock**. The lock can only be switched on *from* that address. Once
locked:

| | Main address (every company) | Super Admin address |
|---|---|---|
| Super Admin screens (Owner home, Platform setup, Companies, Cloud workspaces, Control panel, Pricing, Marketplace plans/switch, Feature gates, product package, payment keys) | refused; an old link is sent on to the Super Admin address (menu shows "Super Admin ↗") | platform owner only |
| Who can sign in | everyone, as before | only the platform owner's Master Admin account(s) on the main site; company e-mails are never routed into a company here; any other account is told where to sign in |
| Public pages (sign-up, marketplace, portals, verify…) | as before | not served — only the sign-in page |

The browser keeps a separate sign-in per address. Emergency unlock without the app: an empty file
named `superadmin-unlock` in the protected data folder, or the server setting
`OPS_SUPERADMIN_ANY_HOST=1`. Company workspace names can never take the address's name (nor
`admin`, `www`, `mail`, `webmail`, `cpanel`, `whm`, `api`, `app`, `ftp`, `start`, `login`). No new
permission — it narrows the existing Super Admin gate (`superadmin_can()`, `can_manage_tenants()`,
the payment-keys save) to one address.

**No built-in prices; GST and tax invoices (owner, 22 Oct 2026: "keep it open to configure in
admin panel, don't hardcode it")** — every commercial number is a setting the platform owner saves
(`lib/commercial.php`). Prices, the yearly discount, GST, the AI top-up and seat costs are on
**Plans, pricing & usage** (`/pricing-usage`); storage and trial are under **Companies**. That
screen is now the only place prices are edited: the Companies 💳 panel and the Billing form no
longer edit them, and Billing keeps only the payment keys. An unset price means the item is not
for sale, and a customer is told "Not for sale yet".

When GST is switched on (it needs the platform's GSTIN, legal name, address, rate and SAC):
- it is added to, or included in, the amount charged, split by place of supply;
- each payment gets a GST tax invoice from one platform-wide series per financial year;
- the invoice is visible at `/subscription-invoice`, to the same people who manage the company's
  subscription (`billing_can_manage()`).

No new permission — the existing Super Admin gate (`superadmin_can()`) and subscription gate are
reused.

**Customer's GSTIN on the bill; GST invoice register (owner, 22 Oct 2026: "if customer wants bill
with his gst number then??")**

- **Bill to at checkout.** Checkout has a **Bill to** section: registered name, GSTIN, state and
  billing address. It is filled from the company profile and can be changed.
  - The GSTIN is checked for format, state code and its check character.
  - The buyer's state sets the place of supply, so the tax is IGST or CGST + SGST.
  - "Save to our company profile" writes those details back to the profile. Only people who
    manage the subscription can do this (`billing_can_manage()` = Master Admin or
    `settings.manage`), which are the same people who may edit the company profile.
- **GST invoices register** (`/gst-invoices`, platform owner only, `superadmin_can()`).
  - It lists every tax invoice issued to every company, for the GST return.
  - It shows monthly totals and offers a CSV download for the accountant (B2B / B2C, place of
    supply, IGST / CGST / SGST).
  - The owner can **correct the buyer** on an issued invoice, for example to add a GSTIN given
    after payment. The amount and tax never change; only the IGST / CGST + SGST split follows the
    new place of supply. The old details, who made the change, when and why are kept with the
    invoice. The company sees the corrected invoice.
- **SAC check.** The SAC must be six digits beginning 99 (e.g. 997331).

No new permission.
