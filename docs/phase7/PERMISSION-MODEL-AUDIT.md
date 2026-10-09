# Permission model audit — R-20, step 1

**Date:** 2026-10-08 · **Branch:** `claude/testing-branch-setup-0gqe8n`
**Measured by:** `phpapp/tools/permission-audit.php` (re-runnable: `php tools/permission-audit.php`)

The owner asked, in their own words: *"Start permission audit and hope you have
separated selection each for edit, add, delete, remove, deactivate and activate."*

This document answers that question with measurements, not opinion. Everything in
section 1 and 2 comes out of the tool, so anyone can re-run it and get the same
numbers. Section 3 is the recommendation, which is a judgement call and is the
owner's to accept or change.

---

## 1. The short answer

**No. Today the app can separate only two of those six choices.**

| What you asked for | Can you grant it on its own today? |
|---|---|
| **View** | **Yes — on all 31 modules.** |
| **Add** | **Almost never.** 3 modules only. On the other 28, Add and Edit are the *same single tick*. |
| **Edit** | **Yes — on all 31 modules.** But it also silently carries Add. |
| **Delete** | **Almost never.** 1 module only (Calls). |
| **Deactivate** | **Almost never.** 3 modules only (Jobs, Nonconformities, Corrective actions). |
| **Activate** | **Never.** 0 modules. |
| **Remove** | Not a separate idea in the app except for users — see §3.2. |

The reason is a single line of code. In `phpapp/lib/access.php`:

```php
$p["mod.$k.view"] = "$l — view";
$p["mod.$k.edit"] = "$l — add / edit";     // <- one tick, two verbs
```

Every module produces exactly **two** rights. The label already admits the
problem: *"add / edit"*. There is no third, fourth, fifth or sixth right to tick.

### Where Delete and Deactivate get their authority instead

They are not unprotected — they are protected by **job title rather than by a
tick**. The audit traced all 25 destructive actions in the app. Representative
examples, read from the live source:

```php
// phpapp/lib/crm.php:1357
ops_require(is_admin_level() || is_master(), 'Only an administrator can delete inquiries.');

// phpapp/lib/opportunities.php:909
ops_require(is_admin_level() || is_master(), 'Only an administrator can delete opportunities.');
```

In business terms: **"only an administrator can delete this"** is hard-coded. So
today the owner cannot say *"Priya may edit jobs but must never delete one"* —
because deleting is not a tick, it is a consequence of being an administrator.
Either Priya is an administrator and can delete nearly everything, or she is not
and can delete nearly nothing.

### The Edit tick is wider than its label — and this is the risky part

The audit expected to find destructive actions gated either by a dedicated right or
by job title. It found a third case, which matters more than the other two because
it affects the migration: **in several modules the generic "add / edit" tick is
itself the delete or close right.** Confirmed by reading each one:

| Action | What actually authorises it | So ticking "add / edit" on… |
|---|---|---|
| Delete a lead | `can('mod.leads.edit')` | …Leads also grants **delete** |
| Re-open a closed nonconformity | `can('mod.ncr.edit')` | …Nonconformities also grants **re-open** |
| Close an internal audit | `can('mod.audits.edit')` | …Internal audits also grants **close** |
| Delete a bill on a job | `can('mod.jobs.edit')` | …Jobs also grants **delete a bill** |

So the Edit tick is doing three jobs at once in places — add, edit, *and* destroy.
Nobody reading the screen would know: the label says *"add / edit"*.

**Why this matters for the rebuild.** The obvious migration rule — *"give everyone
View + Add + Edit, and nobody gets Delete until the owner ticks it"* — would quietly
**take away** capabilities these people have today. Someone who manages leads would
lose the ability to delete one. The migration therefore has to be built from what
each permission *actually authorises in the code*, not from what its label says.
This is recorded in §3.4 as a hard rule because it is the single most likely way a
careless migration would break the business.

### The clearest single example of what is missing

Side by side in the nonconformities register (`phpapp/lib/ncr.php`):

```php
ncr-close   ->  ops_require(ncr_can_close(), ...)   // needs the dedicated "close" right
ncr-reopen  ->  (covered by)  ncr_can_raise()       // needs only the generic edit tick
```

In business terms: **closing a nonconformity is a privileged act, but re-opening a
closed one is not.** Anyone who can edit in that register can re-open a finding the
quality manager had closed. Nobody chose that; it is simply what happens when there
is no *activate* right to ask for, so re-opening falls back to the generic Edit
tick. Multiply that across 31 modules and you have R-20.

---

## 2. What the audit found, in full

### 2.1 Nothing is unguarded — the good news first

The tool walked all **416 routes** in the router, found the code behind each one,
and listed every security check it applies.

| Verb | Routes | With no check at all |
|---|---|---|
| view | 343 | 0 write risk |
| add | 24 | **0** |
| edit | 24 | **0** |
| approve | 3 | **0** |
| deactivate | 3 | **0** |
| activate | 1 | **0** |
| delete | 18 | **0** |

**Every action that changes or deletes data is guarded.** There is no hole. This was
the one finding that would have been urgent, and it is clean. The problem in R-20 is
*expressiveness*, not *safety*: the locks all work, there are just far too few keys
to hand out.

**How firmly that is established.** A text search is not proof, so this was checked
three ways:

1. The tool traced all 416 routes to the code behind them and found a check on every
   writing one.
2. All **25 destructive actions** (delete, remove, close, cancel, reopen, merge,
   retire) were then traced to the specific guard that authorises each one. Where
   that guard was **not** in the action's own branch, the surrounding function was
   read line by line to confirm the guard genuinely stands between the request and
   the action, rather than belonging to a neighbouring one. Three different but
   valid patterns were found — the guard in the action's own branch, one guard
   covering every write branch below it, or one covering the whole save block — and
   each of the 25 falls under one of them.
3. That result is now locked by a regression test,
   `phpapp/tests/test_permission_write_guards.php`, which does a proper dominator
   analysis rather than a text search: a guard that protects only a *neighbouring*
   action does not count. The test was verified by deliberately deleting two real
   guards in a throwaway copy of the code; it failed both times, as it must. A test
   that cannot fail proves nothing, so this was checked rather than assumed.

### 2.2 The verb coverage grid as it stands

Produced by the tool. `yes` = a tick exists for that verb alone. `merged` = shares
the Edit tick. `role` = decided by job title, not grantable separately.

| Module | view | add | edit | delete | deactivate | activate |
|---|---|---|---|---|---|---|
| CRM — Inquiries | yes | merged | yes | role | role | role |
| CRM — Quotations | yes | **yes** | yes | role | role | role |
| CRM — Orders / contracts | yes | **yes** | yes | role | role | role |
| CRM — Sales reports | yes | merged | yes | role | role | role |
| Inspection reports (IDEMS) | yes | merged | yes | role | role | role |
| Calls | yes | **yes** | yes | **yes** | role | role |
| Jobs | yes | merged | yes | role | **yes** | role |
| Vouchers | yes | merged | yes | role | role | role |
| Invoicing | yes | merged | yes | role | role | role |
| Profitability | yes | merged | yes | role | role | role |
| Recruitment | yes | merged | yes | role | role | role |
| Attendance reconcile | yes | merged | yes | role | role | role |
| Clients | yes | merged | yes | role | role | role |
| Vendors | yes | merged | yes | role | role | role |
| Equipment & calibration | yes | merged | yes | role | role | role |
| Competence & authorisation | yes | merged | yes | role | role | role |
| Impartiality & conflicts | yes | merged | yes | role | role | role |
| Identity documents | yes | merged | yes | role | role | role |
| Complaints & appeals | yes | merged | yes | role | role | role |
| Leads & pipeline | yes | merged | yes | role | role | role |
| Nonconformities | yes | merged | yes | role | **yes** | role |
| Confidentiality (§4.2) | yes | merged | yes | role | role | role |
| Corrective actions | yes | merged | yes | role | **yes** | role |
| Internal audits & mgmt review | yes | merged | yes | role | role | role |
| Data & information control | yes | merged | yes | role | role | role |
| Client portal | yes | merged | yes | role | role | role |
| Masters | yes | merged | yes | role | role | role |
| Overheads (office finance) | yes | merged | yes | role | role | role |
| Dashboards / reports | yes | merged | yes | role | role | role |
| Users & access | yes | merged | yes | role | role | role |
| Settings | yes | merged | yes | role | role | role |

**Totals out of 31 modules:** view 31 · add 3 · edit 31 · delete 1 ·
deactivate 3 · activate 0.

### 2.3 Why it drifted this way

The audit found **154 hard-coded gate helpers** — small functions that each decide
one policy in code. For example:

```php
// phpapp/lib/ncr.php:183
function ncr_can_close() { return can('ncr.close') || can('capa.close') || is_master(); }
```

The verbs the owner wants *do* exist in this app — they are just written into PHP,
one bespoke rule at a time, instead of being offered as ticks. Counting by the verb
in each helper's name: 28 are about viewing, 22 about managing, 9 about editing,
3 about deciding, 2 about closing.

This is the root cause. Each new feature invented its own private rule rather than
drawing on a shared vocabulary of verbs, because the shared vocabulary only ever
had two words in it.

### 2.4 The one piece of good architecture that makes this affordable

The audit found something that changes the cost of fixing R-20 substantially, and
it is worth stating plainly because it is the difference between a six-week job and
a six-month one.

Every route in the operations app passes through **one** gate before anything else
happens (`phpapp/lib/ops.php:3455`):

```php
function ops_dispatch($route, $method) {
    ops_module_gate($route);          // <- every route, no exceptions
```

And inside that gate, every permission decision funnels through **a single line**
(`phpapp/lib/ops.php:3350`):

```php
if ($mod && !can("mod.$mod.view")) {        // <- the whole app's module gate
```

It decides which module a route belongs to from a **452-entry route-to-module map**
(`phpapp/lib/ops.php:3160`), with a prefix-based fallback (`ops_module_family()`)
for any route nobody added to the map — the comment in the source calls this
*"where the route nobody added to the map hole closes"*, and it does.

**Why this matters for the owner:** adding four verbs does not mean hand-editing 763
places in the code and hoping none was missed. It means:

1. Widening that map from *"this route belongs to Jobs"* to *"this route is the
   **delete** action on Jobs"*.
2. Changing that one line from asking `mod.jobs.view` to asking `mod.jobs.delete`.

The 763 finer checks already scattered through the app then keep working as extra,
narrower conditions **on top** of the central gate — so they can be tidied up
gradually, module by module, instead of all at once in one risky change. The central
gate is what makes the new model enforceable everywhere from day one.

---

## 3. Recommendation

### 3.1 Do not simply add four more columns of ticks

Six verbs × 31 modules = **186 ticks**, and that is before sub-modules; with them it
is realistically 300–500. The owner has already told us twice that the present
101-tick screen is hard to read — R-14 was raised precisely because permissions were
landing under an "Other" heading. **A 300-tick wall is a worse product than the
101-tick wall, not a better one.** Adding verbs without changing the screen would
make the audit's finding true and the product worse at the same time.

So the recommendation has two halves: a better **vocabulary**, and a better
**screen** to show it on.

### 3.2 Recommended vocabulary — six verbs, not seven

This is how Salesforce, Atlassian and Stripe actually model object permissions, and
it resolves the two ambiguities in the original list.

| Verb | What it means in plain terms |
|---|---|
| **View** | Can open and read it. |
| **Add** | Can create a new one. |
| **Edit** | Can change one that exists. |
| **Archive** | Can take it out of use, **and put it back**. |
| **Delete** | Can destroy it permanently. |
| **Approve** | Can sign it off. Only on modules that have an approval step. |

Two deliberate departures from the list as asked, both with a business reason:

**(a) "Deactivate" and "Activate" should be ONE right, called Archive.**
Splitting them creates a trap. A person who can deactivate but not reactivate can
take a client, an inspector or a price list out of service and then be unable to
undo their own mistake — every such slip becomes an administrator's problem. The
mainstream platforms we looked at treat taking something out of use and putting it
back as one right, for that reason. *If the owner
still wants them split, say so and it will be built split — it is two columns
instead of one, not a redesign.*

**(b) "Remove" and "Delete" are the same act, except for people.**
Everywhere in this app except users, "remove" and "delete" describe one thing:
making the record go away. Giving them separate ticks would mean two ticks guarding
one button. **Users are the genuine exception**, and the app already models it
correctly — the Account status panel built in R-13 distinguishes:
- **Deactivate** — they cannot sign in; their history stays intact (reversible)
- **Remove sign-in** — their login is destroyed; the person record stays
- **Delete** — not offered, deliberately, because an inspection body must keep the
  record of who did the inspection

So Users & access keeps its own specific rights rather than the generic six. That is
correct and should not be flattened.

**Approve must never merge into Edit.** For an inspection body under ISO 17020, the
person who writes a report and the person who signs it off must be separable. The
app already honours this (`idems.finalize`, `crm.quote.approve`,
`complaints.decide`); the
new model must keep it.

### 3.3 Recommended screen — a grid, not a longer list

Rows are modules, columns are the six verbs, one tick per cell. The **same**
information as a flat list, in roughly a quarter of the vertical space, and
scannable: the owner can see at a glance that a row is "view-only" or that a column
is "nobody can delete anything".

```
                       View   Add   Edit   Archive   Delete   Approve
  Jobs                  [x]   [x]    [x]     [ ]       [ ]       —
  Invoicing             [x]   [ ]    [ ]     [ ]       [ ]       —
  Inspection reports    [x]   [x]    [x]     [ ]       [ ]      [ ]
```

With four things that remove most of the clicking:

1. **Click a column heading** — "give this person View on everything".
2. **Click a row heading** — "full control of Jobs".
3. **Start from a role template** — the role defaults already in the code
   (`role_defaults()`) become the starting grid, so the usual case is *pick a role,
   adjust two cells, save* rather than 186 decisions.
4. **Sensible cascading** — ticking Edit ticks View; ticking Delete ticks Edit. The
   owner cannot accidentally create a nonsense permission set such as "may delete
   but may not see".

### 3.4 The migration must neither take access away nor hand it out

This is the part that carries real risk, so it is stated as a hard rule:

- Anyone holding `mod.X.edit` today receives **View + Add + Edit** on X — **plus
  Delete or Archive wherever the audit proved that today's edit tick already grants
  it** (see the four confirmed cases in §1). The migration is built from what each
  right authorises in the code, never from its label.
- **Nobody silently gains Delete or Archive anywhere else.** Those stay role-decided
  until the owner deliberately ticks them.
- Administrators and masters receive the new Delete and Archive ticks **pre-set**,
  because they can already do those things today.
- The whole of the above is asserted by a test, not by review. The test enumerates
  every user and every permission before and after, and fails if **any** person's
  effective set of allowed actions changes by even one entry.

Net effect on day one: **every existing user can do precisely what they could do the
day before.** The new power is available but unused until the owner chooses to use
it. Any migration that cannot promise this should not ship.

---

## 4. What this costs, honestly

Because of the central gate in §2.4, this is a gate of its own but a tractable one.
In rough order of effort:

| Work | Why it is needed | Risk |
|---|---|---|
| Extend the vocabulary from 2 verbs to 6 | `lib/access.php` — the heart of the model | Low: additive |
| Carry a verb in the 452-entry route map | So the central gate knows *delete* from *view* | Medium: 452 routes to classify, mechanical and testable |
| Switch the one gate line to ask the verb | `lib/ops.php:3350` — enforces everywhere at once | Low: one line, high leverage |
| Build the grid screen | Phone and desktop, per the UI/UX blueprint | Medium |
| Migration + a test proving nobody gains or loses access | The hard rule in §3.4 | **Highest — this is the part to be careful about** |
| Fold the 154 bespoke gate helpers into the vocabulary, module by module | Otherwise policy stays scattered and this recurs | Low per module, can be done gradually |
| Rewrite `docs/02-permission-matrix.md` | CLAUDE.md: docs and code must never disagree | Low |

The 763 `can()` call sites do **not** all need re-pointing up front. They sit
*behind* the central gate as narrower conditions, so they stay correct while the
verbs are introduced, and can be tightened module by module afterwards. That is the
single biggest reason this is affordable.

### Decision required before any of it starts

CLAUDE.md is explicit: *"Never grant a role a permission that is not in
`docs/02-permission-matrix.md`. If a feature needs a new permission, stop and ask."*
This change creates roughly 150 new permissions, so it stops here for the owner's
decision on three points:

1. **Six verbs — View / Add / Edit / Archive / Delete / Approve?** Or split Archive
   into Deactivate and Activate as originally listed?
2. **Sub-modules.** Should the grid go one level deeper (e.g. Jobs → Allocation,
   Jobs → Billing)? This roughly triples the row count and is the single biggest
   driver of cost. Recommendation: **ship the 31-row grid first**, then deepen only
   the modules where the owner finds it genuinely necessary in use.
3. **The migration rule in §3.4** — confirm that "nobody gains or loses access on
   day one" is the required behaviour.

---

## 4a. What was built, and the one bug worth remembering

Steps 2–4 shipped on `claude/testing-branch-setup-0gqe8n`: the six-verb
vocabulary, enforcement at the single module gate for nine routes, and one
shared grid partial driving both permission screens.

One defect is worth recording because of how it hid. The read-time migration
that keeps everybody's access intact had no way to tell *"saved before the verbs
existed"* from *"the owner deliberately withheld this"*, so it re-applied its
carry-over on every read. Unticking Delete on Jobs saved correctly — and came
back ticked on reload. Every function behaved exactly as written; the fault was
in the gap between them, and no unit test would have found it. A real
save-and-reload in a browser did. The fix is a vocabulary stamp on every saved
set, and the behaviour is now pinned by a test.

It is a good argument for the rule that a feature is not finished until it has
been used the way the owner will use it, rather than only tested in pieces.

## 5. How to reproduce every number here

```
cd phpapp
php tools/permission-audit.php                     # the tables in §2.1 and §2.2
php tools/permission-audit.php --csv               # same data, for a spreadsheet
php tests/run.php permission_write_guards          # the safety facts, as a test
```

The tool reads the router and the source files directly and loads no database, so it
can be re-run safely at any time on any checkout, and will keep telling the truth as
the code changes.
