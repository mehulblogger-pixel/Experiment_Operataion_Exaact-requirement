# PHASE 3 · M6 — ACTION PATH SWEEP

A repository-wide sweep for every path able to WRITE a lifecycle-critical table,
with comments stripped so no finding hides behind an explanation. Each is
classified: **PROTECTED**, **INTENTIONALLY ABSENT**, **LEGACY**, **DANGEROUS**,
**DUPLICATE**.

## A · Every file that writes a lifecycle table

| File | Writes | Classification |
|---|---|---|
| `ops.php` | candidates ×11, requisitions ×5 | **PROTECTED** — the save paths ask the gate, ownership is out of the blind lists (M5), and a compensator follows each write |
| `hiringreq.php` | hiring_requests ×12, requisitions ×2 | **PROTECTED** — M4's own layer: capability, scope, state, segregation, ceiling |
| `recruit_offer.php` | job_offers ×7, candidates ×1 | **PROTECTED as of M6** — `offer_guard()` on create, submit, approve, issue and accept. Was **DANGEROUS** before: none of the five asked anything |
| `recruit_approval.php` | job_offers ×2, requisitions ×2 | **PROTECTED as of M6** — the REQUISITION callback used to write statuses that are not in the lifecycle. Was **DANGEROUS** |
| `recruitpipe.php` | candidates ×4 | **PROTECTED as of M6** — the configured pipeline asks the gate before it advances. Was **DANGEROUS** |
| `recruit_iv.php` | interviews ×2 | **PROTECTED as of M6** — `iv_schedule()` asks the gate. `iv_record()` deliberately does not (see below) |
| `careers.php` | candidates ×2, requisitions ×1 | **PROTECTED** — public intake inherits ownership through M5's door; the requisition write is `careers_published`, a publishing flag |
| `reqfulfil.php` | requisitions ×2 | **PROTECTED** — M3's own status derivation and vacancy cancellation; this is the authority, not a bypass |
| `recruit_assign.php` | recruiter_assignments ×1 | **PROTECTED** — M5's ledger, append-only |
| `recruit_exec.php` | candidates ×1 | **PROTECTED** — the compensating revert only |
| `compliance.php` | candidates ×1 | **PROTECTED** — GDPR erase; fixed column list (`candidate_erase_fields()`), no ownership, no stage |
| `position.php` | requisitions ×1 | **PROTECTED** — writes `position_id` only |
| `projcosting.php` | requisitions ×1 | **PROTECTED** — creates an unowned requisition; asks `mod.hiring.edit` |
| `recruit.php` | candidates ×1 | **PROTECTED** — writes `person_ref` (identity linkage) only |
| `seed_*.php` ×5 | candidates, requisitions | **LEGACY / demo** — CLI seeds, reachable from no route |

## B · Paths checked and INTENTIONALLY ABSENT

| Path | Evidence |
|---|---|
| bulk assignment / bulk stage move | the bulk framework's only adopter is `leads-bulk` |
| candidate or requisition import | the only recruitment import is the org chart (`positions`) |
| AJAX write | the only recruitment AJAX route (`jd-generate`) generates text |
| public API | `api.php` serves one action, `licence`, and never loads these paths |
| background / cron write | no cron task writes a lifecycle column |
| Marketplace → requisitions | the marketplace works on `cx_*`; **no merge**, as the lock requires |

## C · Dynamic write attack (§38)

Textual sweeps do not see a column written through a variable, so every dynamic
builder on these tables was read:

| Builder | Verdict |
|---|---|
| `ops.php` requisition save — `UPDATE requisitions SET $set` | ownership removed from `$fields`; a compensator restores the authorised value after the write, driven by the **service's own** subject list, so a field list that regrows the column cannot change ownership |
| `ops.php` candidate save — `UPDATE candidates SET $set` | same |
| `compliance.php` — `UPDATE candidates SET <sets>` | fixed list; contains no ownership or stage column |
| `custom_save()` | writes `custom_values`, a separate table keyed by entity + record; it cannot reach a column on `requisitions` or `candidates` |
| `formdesign.php` | writes `form_field_layout` / `custom_fields` — labels and visibility, never the record |
| mass assignment (`foreach ($_POST …)`) | none exists on these tables |

M5's mutation **M14** proved a runtime field injection could defeat a
literal-source test. M6 re-attacked the class (mutation **M20**) and it is caught.

## D · Deliberate non-gates, recorded rather than hidden

| Path | Why it is not gated |
|---|---|
| `iv_record()` | records the outcome of an interview that already happened; refusing it would destroy information and advances nothing |
| offer count vs seats | the business deliberately runs more offers than seats because offers are declined; only the **joining** consumes a seat |
| closing moves (reject / withdraw / decline / hold) | closing a candidate out is not spending authority, and a pending re-approval must never trap a person |
| ADR-001 direct candidates | no requisition means no approval to respect and no ceiling to apply |
