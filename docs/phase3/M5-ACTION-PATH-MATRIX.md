# PHASE 3 · M5 — ACTION PATH MATRIX

**Rule of this milestone:** an action is *protected* only when **every** production
path that can perform it passes the **same** control. One protected route proves
nothing about its siblings. This matrix is built from repository inspection —
every row names the file and line that actually performs the write.

Recruiter accountability in EXAACT is carried by three columns:

| Column | Meaning | Added |
|---|---|---|
| `requisitions.recruiter_id` | **Responsible 1** — the recruiter accountable for filling the requirement | `recruit_cc.php:22` |
| `requisitions.manager_id` | **Responsible 2** — the manager accountable for the requirement | `recruit_cc.php:23` |
| `candidates.recruiter_id` | who is chasing this person | `recruit_cc.php:25` |

Any write to one of those three columns is an **ownership change** and belongs in
this matrix, whatever screen it came from.

---

## A · Every path that can change recruiter ownership

Found by searching the whole repository for reads and writes of those columns
(`grep -rn "recruiter_id\|manager_id" --include=*.php`), then reading each hit.

| # | Business action | Path | Entry point | Write |
|---|---|---|---|---|
| P1 | **Create requisition with recruiter** | UI form + POST `/requisition-new` | `ops.php:4963` `ops_requisitions()` | `ops.php:5066` INSERT |
| P2 | **Edit / change / reassign / remove requisition recruiter** | UI form + POST `/requisition-edit` | `ops.php:4963` | `ops.php:5037` UPDATE |
| P3 | **Create candidate with recruiter** | UI form + POST `/candidate-new` | `ops.php:5167` `ops_candidates()` | `ops.php:5403` INSERT |
| P4 | **Edit / change / remove candidate recruiter** | UI form + POST `/candidate-edit` | `ops.php:5167` | `ops.php:5379` UPDATE |
| P5 | **Public careers application inherits the requisition's recruiter** | public POST `/careers`, **in front of `require_login()`** | `careers.php:183` `careers_route()` → `careers.php:91` `careers_apply()` | `careers.php:144` INSERT |
| P6 | **Requisition raised from an approved hiring request** | `hreq_to_requisition()` | `hiringreq.php` | `hiringreq.php:1050` INSERT — **writes no recruiter**; the requisition is created **unowned** |
| P7 | **Requisition raised from a project costing line** | `pc_line_to_requisition()` | `projcosting.php` | `projcosting.php:356` INSERT — **writes no recruiter** |
| P8 | Demo / seed data | `seed_recruit_cc.php:43,51,80` | CLI seed only, never a user route | writes recruiter ids directly |

## B · Paths that do NOT exist — checked, not assumed

| Path asked for | Result of inspection |
|---|---|
| **Bulk assignment** | The bulk framework is `datatable.php:141` + `bulk.php`; its only adopter is `leads.php:1009` (`leads-bulk`). **No bulk action touches requisitions or candidates.** |
| **Import assignment** | The only recruitment import is `positions-import` (org chart → `positions`, `position.php`). It writes no `recruiter_id`. `partner-import` is clients. **No candidate/requisition import exists.** |
| **AJAX assignment** | The only recruitment AJAX route is `jd-generate` (`recruit_jd.php`), which generates text. **No AJAX route writes ownership.** |
| **API assignment** | `api.php` is 44 lines and serves exactly one action — `action=licence`. It does not load the recruitment module's write paths. **No public API can assign.** |
| **Background / scheduled assignment** | `cron.php` runs reminders, sweeps and `appr_tick()`. **No cron task writes `recruiter_id` or `manager_id`.** |
| **Candidate ownership via deployment / workforce** | `workforce.php`, `connect_deploy.php`, `connect_hiring.php`, `reqfulfil.php`, `candpool.php` — **none reference the ownership columns.** |
| **Marketplace / portal** | Portal hiring works on `cx_*` marketplace tables and its own award path; it does not write these columns. |

**These absences are a finding, not a gap:** any future bulk, import, AJAX, API or
background assignment must call the one door defined below, or it re-opens M5.

## C · What each existing path checked BEFORE M5

| Control | P1 create req | P2 edit req | P3 create cand | P4 edit cand | P5 public careers |
|---|---|---|---|---|---|
| Entitlement (licence) | route gate `ops.php:2629` | route gate | route gate | route gate | **none — public** |
| Permission | `is_coordinator_level()` | `is_coordinator_level()` | `is_coordinator_level()` | `is_coordinator_level()` | **none — public** |
| Tenant | structural (one DB per tenant) | structural | structural | structural | structural |
| Branch / scope of the **record** | `req_scope_gate()` | `req_scope_gate()` | `cand_scope_gate()` | `cand_scope_gate()` | n/a |
| Scope of the **recruiter being assigned** | **NONE** | **NONE** | **NONE** | **NONE** | **NONE** |
| Recruiter exists in this database | **NONE** | **NONE** | **NONE** | **NONE** | **NONE** |
| Recruiter is active | **NONE** (the dropdown shows active users only — `rcc_users()` `recruit_cc.php:71` — but the save casts whatever was posted: `ops.php:5004`) | **NONE** | **NONE** | **NONE** | **NONE** |
| Requisition state | **NONE** | M4 boundary only | **NONE** | M4 boundary only | n/a |
| M4 execution boundary | n/a | yes (`ops.php:5027`) | yes | yes | **not asked** |
| Audit of the ownership change | **NONE** | **NONE** | **NONE** | **NONE** | **NONE** |
| Historical ownership kept | **NO — the previous owner is overwritten** | **NO** | **NO** | **NO** | n/a |
| Concurrency protection | **NONE — last write wins** | **NONE** | **NONE** | **NONE** | **NONE** |

### The five gaps this proves

1. **`recruiter_id` was never validated.** `ops.php:5004` casts the posted value to
   an integer and writes it. A crafted POST could name a **deactivated** user, a
   **deleted** user, or an id that has never existed — creating ownership nobody
   holds. `rcc_recruiter_perf()` (`recruit_cc.php:292`) then shows it on the
   dashboard as *"User #7"*. Hidden dropdown ≠ security.
2. **No scope test on the person being assigned.** A Mumbai requisition could be
   made the accountability of an Ahmedabad-only recruiter.
3. **No history.** Changing the recruiter overwrote the previous one. After a
   reassignment nothing in the database said who used to be accountable.
4. **No audit.** An ownership change wrote no activity row of any kind.
5. **No concurrency control.** Two managers reassigning at the same moment: last
   write wins silently, and neither is told.

## D · Exposure paths (read, not write)

| # | Path | Exposes | Control |
|---|---|---|---|
| X1 | Recruitment command centre `recruit_cc.php:289` `rcc_recruiter_perf()` | per-recruiter posted / working / recruited / earned / lost | dashboard scope (`scope_clause`) + route entitlement |
| X2 | CSV export `recruit_export.php:69,93` | recruiter **name** on candidate and requirement rows | `recruit_home_can()`; money columns additionally `can_see_salary()` |
| X3 | "Needs attention" list `recruit_cc.php:267` | recruiter name per waiting candidate | dashboard scope |

Exposure paths **display** ownership; they do not change it. They are in scope for
**reconciliation** (M5-RECONCILIATION-RESULTS.md) rather than for the assignment
control.
