# Phase 6 — Action Path Matrix

*Every production path that can create, identify, link, resolve or change an
identity, organisation, taxonomy or demand relationship — and whether that path
enforces the §20 rules.*

**Sources:** `P6-PREIMPLEMENTATION-AUDIT.md` (`69e2539`) ·
`P6-PERSON-REPRESENTATION-ADDENDUM.md` · `P6-CANONICAL-DOMAIN-MODEL.md` ·
`P6-DUPLICATE-AND-IDENTITY-RULES.md` (`45b91df`, §20 locked).

**Scope:** documentation / audit only. No PHP, JavaScript, HTML, CSS, database,
schema, migration, route, API, permission or workflow is changed. **R1–R21 are not
implemented. Q1–Q13 are not answered.**

**Method:** paths were enumerated from the repository, not assumed. Where a path
does not exist it is recorded **ABSENT** rather than omitted.

---

# The two findings that matter most

## FINDING A — A READ PATH CREATES IDENTITY. SEVENTEEN CALL SITES.

`inspectors_list()` — the function behind every "pick a person" list — calls
**`link_inspector_users()`** before it reads:

```php
// Give every active inspector-role login a matching team-member row and link
// it, once per request, before the list is read.
link_inspector_users();
```

That function **creates `inspectors` rows and writes `users.inspector_id`**:

```php
foreach ($rows as $u) {                      // every active INSPECTOR-role login
    $insId = team_member_create($name, 'FIELD', $u['home_office_id'], $u['email']);
    if ($insId) db()->prepare("UPDATE users SET inspector_id=? WHERE id=?")…
}
```

**Seventeen call sites across five files** — `ops.php`, `timesheet.php`,
`equipment.php`, `rating.php`, `callprofit.php`. **Opening an equipment page or a
timesheet can create a person record and an identity link.**

Measured against the §20 rules:

| Rule | Verdict |
|---|---|
| Detection is read-only, never writes a link | **VIOLATED** — this *is* the read path, and it writes |
| Suggestion never pre-applied; a person confirms | **VIOLATED** — fully automatic, nobody confirms |
| Confirmation records actor, timestamp, reason | **ABSENT** — no audit entry at all |
| Link is reversible | **ABSENT** — a column, no undo |
| Link unique at database level | **ABSENT** — `team_member_create()` inserts unconditionally |
| Permission belongs to the action | **VIOLATED** — it inherits whatever gate the *calling page* has |
| A failed link cannot corrupt the source (I22) | **VIOLATED, and self-repeating** — see below |

**The self-repeating orphan.** `team_member_create()` inserts, then a *separate*
`UPDATE users` links it. There is **no transaction**. If the UPDATE fails, the
inspector row is live and unreferenced — and because `users.inspector_id` is still
null, **the very next page load creates another one.** Every subsequent list view
adds one more.

This is the same defect class as R2 (the hiring conversion), on a different path,
and it fires without anybody pressing anything.

> **Classified: MATERIAL. Not fixed here** — §26 is audit only. Recorded as **R22**.

## FINDING B — A SEARCH CREATES CANONICAL TAXONOMY TERMS.

`connect_pro_search_smart()` — the marketplace talent search — calls
`connect_tax_backfill_pending()`, which for up to 200 professionals calls
`connect_profile_tax_backfill()`, which does:

```php
$hit = connect_tax_resolve($term)[0] ?? null;
$nid = $hit ? (int)$hit['id']
            : connect_tax_node_add($relation === 'SKILL' ? 'SKILL' : 'DISCIPLINE', $term);
```

When a free-text term **does not resolve**, a **new canonical taxonomy node is
created from it** — automatically, during a search, with nobody confirming.

Against §20 and the master prompt's §13: *"Unknown values must remain safely
representable until authorised resolution."* Here an unknown value does not stay
unknown — **it becomes canonical vocabulary.**

This does **not** merge or rewrite an existing term, and the node is created in
its own `kind`, so it cannot collide with a Department. That bounds the damage.
But it means the technical taxonomy can grow silently from whatever text sits in a
professional's CSV fields.

> **Classified: MATERIAL for taxonomy governance. Not fixed here.** Recorded as
> **R23**.

---

# A §20 correction this sweep produced

**§20 states: *"No organisation duplicate detector exists."* That is WRONG, and
this document corrects it.**

`find_duplicate_partner($name, $gstin, $pan, $tan, $excludeId)` exists and is
**well built** — it checks in exactly the order §20's own evidence ranking
prescribes:

```
GSTIN → PAN → TAN → normalised legal name
```

It is applied on **three** paths: the interactive partner create (`ops.php:3770`),
the partner import (`partnerimport.php:254`), and lead conversion
(`leads.php:366`, name only).

**The gap is narrower than §20 claimed, and sharper.** The detector covers
`business_partners` only. It is **not** applied to:

- **`/join`** — public organisation onboarding writing `cx_organisations`;
- **`agencies`** — created through the generic master editor.

So organisation duplicate control is a *good* control **applied where somebody
remembered to apply it** — the recurring pattern of this programme.

> **Correction reported, not applied.** Per the working discipline `§20` is not
> edited here. **Owner approval requested** for a one-line correction to its
> Class 5 row and summary table.

---

# The matrix

**Legend.** ✔ present · ✘ absent · **n/a** not applicable · **?** not established
by this sweep.

## 1 · Identity link — professional ↔ inspector

| Path | Exists | Entitlement | Permission | Tenant | Scope | State | Validation | Duplicate | Audit | Transaction | Error |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **UI + POST** `/connect-identity` `action=link` | ✔ | **✔ `connect_enabled()`** | ✔ `connect_market_can()` | structural | **✘** | ✔ both rows must exist, neither already linked elsewhere | **PHP only** | **✔ `act_log`** | single INSERT | message |
| GET | — | — | — | — | — | — | — | — | — | — | read-only list |
| AJAX / API | **ABSENT** | | | | | | | | | | `api.php` is the licence endpoint only |
| Import / bulk | **ABSENT** | | | | | | | | | | |
| Background / cron | **ABSENT** | | | | | | | | | | `cron.php` writes no identity table |
| **Internal function** `connect_identity_link_create()` | ✔ | **✘ none of its own** | **✘ none of its own** | structural | **✘** | ✔ | PHP only | ✔ | single INSERT | return tuple |

## 2 · Identity unlink

| Path | Exists | Entitlement | Permission | Tenant | Scope | Validation | Audit |
|---|---|---|---|---|---|---|---|
| **POST** `/connect-identity` `action=unlink` | ✔ | ✔ | ✔ | structural | **✘** | link must be active | ✔ |
| **POST** `/candidate-unlink-pro` | ✔ | ✔ `hiring` | ✔ `is_coordinator_level()` | structural | **✘ — see below** | link must be active | ✔ |
| Internal `connect_identity_unlink($id)` | ✔ | ✘ | ✘ | structural | **✘** | active only | ✔ |

> **⚠ A record id treated as authorisation.** `/candidate-unlink-pro` takes
> `link_id` straight from POST and passes it to `connect_identity_unlink()`, which
> looks the link up **by id alone**. Nothing checks that the link concerns a
> candidate this user may see. A coordinator can therefore remove **any** identity
> link in the workspace by posting its id — including a professional ↔ inspector
> link that has nothing to do with recruitment.
>
> The damage is bounded (unlink is reversible and audited, and it destroys no
> record) but it breaks the §20 rule that a link must be valid **in scope**, and
> the standing principle that **a record ID is never proof of authorisation**.
>
> **Classified: MATERIAL (authorisation). Recorded as R24.**

## 3 · Identity link — candidate ↔ professional

| Path | Exists | Entitlement | Permission | Scope | Duplicate | Audit |
|---|---|---|---|---|---|---|
| **POST** `/candidate-link-pro` | ✔ | **✔ `hiring` only — see below** | ✔ `is_coordinator_level()` | **✘** | PHP only | ✔ |
| UI suggestion `/candidate-pool` | ✔ read-only | ✔ `hiring` | ✔ | ? | n/a | n/a |
| Internal `connect_identity_candidate_link_create()` | ✔ | ✘ | ✘ | **✘** | PHP only | ✔ |
| AJAX / API / import / bulk / cron | **ABSENT** | | | | | |

> **⚠ Asymmetric entitlement on one ledger.** The marketplace console
> (`/connect-identity`) checks **Connect** entitlement before touching
> `cx_identity_link`. The recruitment route (`/candidate-link-pro`) writes the
> **same ledger**, pointing at the **same `cx_professionals` table**, gated on
> **`hiring` only** — Connect is never asked.
>
> Contrast Phase 4, which refuses a marketplace source outright with
> `NO_ENTITLEMENT` when Connect is not bought. Two modules, one ledger, two
> different answers.
>
> **Classified: material for entitlement consistency. Recorded as R25.**

## 4 · Identity link — candidate → inspector (the hiring conversion)

| Path | Exists | Entitlement | Permission | Scope | State | Duplicate | Audit | Transaction |
|---|---|---|---|---|---|---|---|---|
| **POST** `/candidate-stage` + `make_inspector` | ✔ | ✔ `hiring` | ✔ `is_coordinator_level()` | ✔ via the candidate's requisition | ✔ stage must reach ACCEPTED | **✘ stale check** | **✘** | **✘** |
| Ledger entry written | **ABSENT** | | | | | | | *the audit's headline finding* |
| AJAX / API / import / bulk / cron | **ABSENT** | | | | | | | |

Already recorded as **R1** (missing edge) and **R2** (MATERIAL integrity).

## 5 · Identity link — user → inspector

| Path | Exists | Entitlement | Permission | Scope | Duplicate | Audit | Transaction |
|---|---|---|---|---|---|---|---|
| **Internal, on a READ** `inspectors_list()` → `link_inspector_users()` | ✔ | **inherited from the calling page** | **inherited** | **✘** | **✘** | **✘** | **✘** |
| Import `org_import_link_team()` (people register) | ✔ | ✔ | ✔ `is_master() \|\| users.manage.global` | office from the row | read-then-write | ✘ | ✘ |
| UI / POST of its own | **ABSENT** | | | | | | |

**FINDING A.** Recorded as **R22**.

## 6 · Person grouping — candidate ↔ candidate (`person_ref`)

| Path | Exists | Entitlement | Permission | Scope | Validation | Duplicate | Audit |
|---|---|---|---|---|---|---|---|
| **POST** `/candidate-link-person` | ✔ | ✔ `hiring` | ✔ `is_coordinator_level()` | **✘** | ✔ both rows must exist | group read-then-write | **✘** |
| Internal `person_link_rows()` | ✔ | ✘ | ✘ | **✘** | ✔ | read-then-write | **✘** |
| Suggestion `person_applications()` | ✔ read-only | ✔ | ✔ | ? | n/a | n/a | n/a |
| Unlink / undo | **ABSENT** | | | | | | **no way to separate two applications once joined** |

> **⚠ Scope is not evaluated.** `/candidate-link-person` takes `id` and `other_id`
> from POST and links them. Nothing checks either candidate is within the user's
> branch or SBU scope. A coordinator could group a candidate they own with one
> they cannot see.
>
> Combined with the **absent unlink**, a wrong grouping cannot be undone through
> any route. Recorded as **R18** (audit + reversal) and **R15** (scope).

## 7 · Organisation link and creation

| Path | Exists | Entitlement | Permission | Duplicate control | Audit |
|---|---|---|---|---|---|
| **POST** interactive partner create (`ops.php`) | ✔ | ✔ | ✔ clients/vendors edit | **✔ `find_duplicate_partner()`** | ✔ |
| **Import** `/partner-import` | ✔ | ✔ | ✔ `is_admin_level() \|\| clients/vendors edit` | **✔ same detector** + GSTIN validity | ✔ |
| Lead conversion (`leads.php`) | ✔ | ✔ | ✔ | **partial — name only** | ✔ |
| **PUBLIC POST** `/join` → `cx_organisations` | ✔ | **n/a — public by design** | **none — public** | **✘ NOTHING** | ✔ approval state |
| `agencies` via the generic master editor | ✔ | ✔ | ✔ `admin` | **✘ NOTHING** | master-editor log |
| `cx_organisations.party_id` → `business_partners` | ✔ column | — | — | **✘ nothing enforces or suggests it** | — |
| **`agencies` ↔ `business_partners`** | **ABSENT** | | | | **R4** |
| AJAX / API / bulk / cron | **ABSENT** | | | | |

> **A public route can create unlimited organisation records with no duplicate
> check of any kind.** `/join` lands them as PENDING for an admin to approve,
> which is a real control — but it is an *approval* control, not a *duplicate*
> control, and the approver is shown no possible matches.

## 8 · Taxonomy mapping

| Path | Exists | Entitlement | Permission | Duplicate control | Audit |
|---|---|---|---|---|---|
| **POST** department save (`dept_save`) | ✔ | ✔ | ✔ | **✔ `vocab_duplicate_check()` — exact, similar, code collision** | ✔ vocabulary states |
| **POST** designation save | ✔ | ✔ | ✔ | **?** not established whether every write path applies it | ✔ |
| **POST** `/connect-taxonomy-admin` node/alias/edge | ✔ | ✔ Connect | ✔ | **✔ deduped by `kind`+`slug`** | `source` on node |
| **Internal, on a SEARCH** `connect_pro_search_smart()` → `connect_tax_backfill_pending()` | ✔ | inherited | inherited | node dedupe only | **✘** |
| Import / bulk | **ABSENT** | | | | |
| Cron | **ABSENT** | | | | |

**FINDING B.** Recorded as **R23**.

## 9 · Requirement mapping — recruitment ↔ marketplace

| Path | Exists | Entitlement | Permission | Scope | State | Duplicate | Audit | Transaction |
|---|---|---|---|---|---|---|---|---|
| **POST** allocation with `source='MARKETPLACE'` | ✔ | **✔ refuses `NO_ENTITLEMENT` without Connect** | ✔ coordinator band | ✔ `rful_may_touch()` | ✔ lifecycle gate | **✔ Phase 4 COMMITTED ceiling** | ✔ `requisition_allocation_events` | ✔ compensator |
| Reverse `cx_requirements` → requisition | **ABSENT** | | | | | | | *by design or by omission — Q2* |
| AJAX / API / import / bulk / cron | **ABSENT** | | | | | | | |

**This is the best-protected relationship in the system, and it is the model the
others should follow.** Every column is filled.

## 10 · Person record creation — the public surface

Three public routes create a person or organisation representation. They are
listed because a creation path is where a duplicate is born.

| Public route | Creates | Duplicate control | Verdict |
|---|---|---|---|
| **`/pro`** freelancer self-registration | `cx_professionals` | **e-mail rejected at source · MOBILE rejected at source via `connect_pro_duplicates()` · `ux_cx_pro_email` UNIQUE at database level** | **The best-defended creation path in the system** |
| **`/careers`** public application | `candidates` | **✘ none** | A person may apply twice and become two candidates — which §20 says is *legitimate*, but nothing detects or links them |
| **`/join`** organisation onboarding | `cx_organisations` | **✘ none** | See class 7 |

> Worth stating plainly: **the public marketplace route is better defended than
> every internal path.** It blocks a duplicate mobile at source; no internal route
> for inspectors, candidates or organisations does anything equivalent.

## 11 · Other audiences — recorded, not yet assessed

Four further portals dispatch **in front of `require_login()`**, each with its own
table and session key: the **client portal** (`client_users`), the **vendor
portal** (`vendor_users`), the **public passport** `/p/<token>` (read-only) and
**`/verify`** (read-only).

`client_users` and `vendor_users` are **account** representations for external
people. Whether they are *person* representations for Phase 6 purposes is **NOT
ESTABLISHED** by this sweep and is **not assumed** either way.

> **Recorded as Q14**, and as **R26** — extend the person-representation audit to
> cover them before implementation.

---

# Summary — where the §20 rules are enforced

| Relationship | Entitlement | Permission | Scope | Duplicate | Audit | Reversible | Transaction |
|---|---|---|---|---|---|---|---|
| **Requirement mapping (Phase 4)** | **✔** | **✔** | **✔** | **✔** | **✔** | **✔** | **✔** |
| Department / taxonomy admin | ✔ | ✔ | n/a | ✔ | ✔ | ✔ | ? |
| Identity link (marketplace console) | ✔ | ✔ | **✘** | PHP only | ✔ | ✔ | single write |
| Identity link (recruitment route) | **partial** | ✔ | **✘** | PHP only | ✔ | ✔ | single write |
| Organisation (partner paths) | ✔ | ✔ | ? | **✔** | ✔ | n/a | ? |
| Organisation (`/join`, `agencies`) | n/a | n/a | n/a | **✘** | partial | n/a | n/a |
| Person grouping (`person_ref`) | ✔ | ✔ | **✘** | weak | **✘** | **✘** | single write |
| Candidate → inspector | ✔ | ✔ | ✔ | **✘** | **✘** | **✘** | **✘** |
| **User → inspector (read path)** | **inherited** | **inherited** | **✘** | **✘** | **✘** | **✘** | **✘** |
| Taxonomy backfill (search path) | inherited | inherited | n/a | partial | **✘** | ✘ | ✘ |

**Scope is the column that is empty almost everywhere.** Not one identity-link
path evaluates branch scope. Every one of them checks only that the rows exist —
which is precisely the failure mode §20 rule 14 names: *a link must be valid in
scope, not merely reference two existing rows.*

---

# Paths recorded ABSENT

Stated so absence is a finding, not an omission.

| Path kind | Status |
|---|---|
| **AJAX** endpoints for any identity, organisation or taxonomy relationship | **ABSENT** |
| **API** — `api.php` is a 44-line licence endpoint; it loads no identity library and exposes one action | **ABSENT** |
| **Cron / background** writes to any identity, person, organisation or taxonomy table | **ABSENT** — `cron.php` and `cron_ads.php` are reminder and advertising runners |
| **Bulk** operations on identity links | **ABSENT** (`bulk` and `leads-bulk` are unrelated) |
| **Import** of identity links | **ABSENT** |
| **Reverse** marketplace → recruitment navigation | **ABSENT** — Q2 |
| **Unlink** for `person_ref` groupings | **ABSENT** — R18 |
| **`agencies` ↔ `business_partners`** cross-reference | **ABSENT** — R4 |
| Duplicate detection for **inspectors** | **ABSENT** — R20 |
| Duplicate detection for **candidates** at creation | **ABSENT** |
| Duplicate detection for **`cx_organisations`** and **`agencies`** | **ABSENT** |
| A recordable **"keep separate"** decision anywhere | **ABSENT** — R21 |

---

# New requirements identified by §26

*Added to R1–R21. **None implemented.***

| # | Requirement | Severity |
|---|---|---|
| **R22** | **`link_inspector_users()` must stop creating identity on a read path** — and must not be able to generate a repeating orphan. Seventeen call sites | **MATERIAL** |
| **R23** | **Taxonomy nodes must not be created automatically by a search.** An unresolved term must stay unresolved until somebody authorises it | **MATERIAL (governance)** |
| **R24** | **`/candidate-unlink-pro` must not treat a posted link id as authorisation** — it can remove any link in the workspace | **MATERIAL (authorisation)** |
| **R25** | **Entitlement must be consistent across one ledger** — the recruitment route writes `cx_identity_link` without asking Connect; the marketplace console asks | Material |
| **R26** | **Extend the person-representation audit to `client_users` and `vendor_users`** before implementation | Prerequisite |
| **R27** | **Apply organisation duplicate detection to `/join` and `agencies`** — the detector exists and is good; it is simply not wired to two of the three paths | Material |
| **R28** | **Establish whether every designation write path runs the near-duplicate check** — marked `?` above, not assumed either way | Evidence |

# New open questions

**Q14 — Are `client_users` and `vendor_users` person representations for Phase 6?
OPEN.** They are portal accounts for external humans, with their own tables and
session keys. Not assumed either way.

**Q15 — Should `/careers` detect that an applicant already exists?
BUSINESS DECISION REQUIRED.** §20 says several applications from one person are
legitimate, so this is not a duplicate to prevent — but it may be a link to
suggest. Detecting it would also mean a public route reading the candidate pool,
which has privacy implications.

**Q16 — Should the `/join` approver be shown possible existing organisations?
BUSINESS DECISION REQUIRED.** The approval step exists; the evidence is not put in
front of the approver.

---

# What this document does NOT do

- **Implements nothing.** R1–R28 unimplemented.
- **Answers no open question.** Q1–Q16 remain open.
- **Fixes none of the four material findings** — that is a later, authorised step.
- **Does not edit §20**, whose Class 5 correction is reported above for approval.
- **Does not claim completeness on two points**, marked `?` rather than guessed:
  whether every designation write path runs the near-duplicate check (R28), and
  the scope behaviour of the read-only suggestion screens.
- **Measures no live data.** No counts of actual duplicate or cross-scope links
  exist; that is §31, and it needs implementation first.
