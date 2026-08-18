# Inspection Ops — Masters — Test & Documentation Report

> **Prompt 3 · Module MOD-MASTERS.** Traces to Master Inventory v1.0 and Test
> Governance v1.0. Read from `lib/ops.php` (`ops_masters`, `ops_master_handle`),
> `lib/lookups.php` (`lk_admin`, dependent lists), `lib/customforms.php`, and the
> views `views/ops/masters.php`, `master_list.php`, `master_form.php`, `lookups.php`,
> `lookup_values.php`, `custom_fields.php`.

| | |
|---|---|
| **Module** | Masters (MOD-MASTERS) · Area Admin |
| **App build** | branch `claude/quotation-management-workflow-5dokb2` |
| **Personas used** | P-MASTER, P-BM, P-COORD, P-INSP (negative) |
| **Automated baseline** | 1532 passed / 3 accepted-fail |
| **Verdict** | **Complete-with-defects** (see §Q) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Masters is the **reference-data layer** — the lists and records behind every dropdown
and every added field. It has three sub-layers (as the SOP states):

1. **Records you keep** — config-driven CRUD registers (offices, back-office staff,
   office expense heads, expense headings, holidays, subcons/rates, boss numbers,
   travel modes, work norms, etc.), each defined by an entry in `ops_masters()` and
   served by the generic `ops_master_handle($key,$cfg,$action,$method)`.
2. **Dropdown lists** — `lookup_types` / `lookup_values`, including **dependent
   (cascading)** lists (`parent_type_id`), managed at `/lookups`.
3. **Extra fields on a form** — `custom_fields` / `custom_values` (per entity) and
   `custom_forms` / `custom_records`, rendered automatically on Calls/Jobs/Partners.

**Screens:** `SCR-ADM-masters` (`/masters` map), `/m/<key>` (a register's list/new/
edit/delete via `ops_master_handle`), `SCR-ADM-lookups` (`/lookups`, `/lookup?key=`,
`/custom-fields`). **Entry point:** the `ops_masters()` registry is the catalogue.

**Tables owned:** `lookup_types`, `lookup_values`, `custom_fields`, `custom_values`,
`custom_forms`, `custom_records`, `work_norms`, `holidays` (+ it edits `offices`,
`back_office_staff`, `office_expense_heads`, `expense_heads`, `subcons`, `subcon_rates`,
`boss_numbers`, `travel_modes` through the generic engine).

---

## B. Screen-by-screen catalogue

**SCR-ADM-masters `/masters`** — the map of all three layers. Read-only tiles linking
to each register / list. Access: `is_coordinator_level()`.

**`/m/<key>` (generic register)** — served by `ops_master_handle`. States: **list**
(search box `q`, ordered by `cfg.order`), **new** (blank `master_form`), **edit**
(prefilled), **delete** (POST). Fields come from `cfg.fields` — each `[name, label,
type, opts]` where `type ∈ text | select | ref | money | number | check`. `ref` fields
render as a dropdown from `optfn` (e.g. `offices_list`); `money`/`salary` fields carry
`salary=1`; `check` → 0/1. Some registers are **redirect-only** (`goto`) — e.g.
`offices` → `/hierarchy?tab=offices`, `back-office` → `/hierarchy?tab=people` — so a
single canonical editor owns the table and it is never half-written by two forms.

**SCR-ADM-lookups `/lookups`** — list every dropdown list; add a list, add a **dependent
(child) list** (`parent_type_id`), tick which forms it shows on. `/lookup?key=` edits a
single list's values. Access: `is_admin_level()` ("Only admins can manage master
lists"). Dependent lists render via `render_cascade_field` (level selects, `data-level`).

**`/custom-fields?entity=<call|job|partner>`** — add your own boxes to a form,
including cascading-lookup fields; stored in `custom_fields`, values in `custom_values`.

Empty state: a register with no rows shows an empty list + "Add". Locked state: n/a
(masters are always editable by an admin).

---

## C. Field & validation test cases

| TC ID | Title | Steps → Expected |
|---|---|---|
| TC-MASTERS-mform-001 | Required field enforced | New office expense head, leave **Code** blank → save. Expect: rejected / not inserted (field marked `req`). *(See DEF-MASTERS-002.)* |
| TC-MASTERS-mform-002 | `check` → 0/1 | Tick "In use" → save → reopen → still ticked; untick → 0. Expect: boolean round-trips. |
| TC-MASTERS-mform-003 | `ref` dropdown | "Sits under" lists existing offices from `offices_list`; picking one stores its id; blank stores NULL (not 0). |
| TC-MASTERS-mform-004 | `money`/`number` empty → NULL | Leave CTC blank → stored NULL, not `''`/`0`. |
| TC-MASTERS-mform-005 | Search filters list | `/m/<key>?q=<term>` filters rows case-insensitively across all columns. |
| TC-MASTERS-lk-001 | Add a list value | `/lookup?key=inspection_type`, add "Third-party" → appears in every form that uses `inspection_type`. |
| TC-MASTERS-lk-002 | Dependent (cascading) list | Create child list under a parent; on the target form, choosing the parent value narrows the child options (`render_cascade_field`). |
| TC-MASTERS-lk-003 | Duplicate value guard | Add a value equal to an existing one → expect a dup guard or a single stored value *(confirm — GAP-MASTERS-001)*. |
| TC-MASTERS-cf-001 | Custom field appears | Add a custom text field to `call` entity → the New Call form shows it → value saved to `custom_values` and shown back. |
| TC-MASTERS-cf-002 | Custom cascading field | Add a custom dependent-lookup field → cascade works on the form. |

**Negative/edge:** blank required → refused; very long label (>column) → truncated or
refused, not a 500; script-y input in a label → stored/escaped, never executed
(TC-MASTERS-sec-001); double-submit "Add" → **two rows** unless guarded
(TC-MASTERS-int-001, see DEF).

---

## D. Function & logic

- **Ordering:** each register orders by `cfg.order` (e.g. `is_ahmedabad DESC, name`) —
  verify the intended sort.
- **Redirect-only registers:** `goto` sends the user to the canonical editor; the
  master card must not also present an editable form (prevents half-writes).
- **`created_at` auto-stamp:** insert adds `created_at` when the column exists and is
  not already a field.
- **Custom values:** `custom_save($key,$id,$_POST)` persists extra fields on new/edit.

---

## E. Status & lifecycle

Masters are mostly stateless reference data. The one lifecycle-ish attribute is
`is_active` / status on some registers (e.g. office expense heads `is_active`,
back-office `ACTIVE/INACTIVE`). Test: an **inactive** master value is hidden from new
entries but **retained** on historical records (no retroactive rewrite).

---

## F. Roles, permissions & data scope

| Action | P-MASTER | P-BM | P-COORD | P-INSP |
|---|---|---|---|---|
| View `/masters` map | ✓ | ✓ | ✓ (coordinator-level) | ✗ |
| Edit a register `/m/<key>` | ✓ | ✓ (admin-level) | **⚠ confirm** | ✗ |
| Manage `/lookups` | ✓ | ✓ (admin-level) | ✗ ("Only admins…") | ✗ |
| See salary master fields (CTC/allowances) | ✓ | per `data.salary` | ✗ | ✗ |

- **TC-MASTERS-perm-001:** as **P-INSP**, GET `/lookups` and POST a value → **refused**
  (`is_admin_level` guard). Also try the crafted POST directly (not via a button).
- **TC-MASTERS-perm-002:** `/masters` map is gated at **coordinator-level** but the
  editors at **admin-level** — confirm a coordinator who reaches the map cannot save a
  master (the individual `/m/<key>` and `/lookups` guards must hold). **See
  GAP-MASTERS-002.**
- **TC-MASTERS-perm-003 (salary):** master fields flagged `salary=1` (CTC, allowances
  on back-office/subcon rates) must be hidden from a role without `data.salary`.

---

## G. Settings

- `masters_seeded`, `offices_seeded`, `org_types_seeded`, `expense_heads_seeded`,
  `partners_seeded` — one-time seed guards. **TC-MASTERS-set-001:** re-running migrate
  does not double-seed (guards hold).
- Terminology: master labels use `T*()` (e.g. `TP('sbu')`) — a renamed term must show
  on the master screens too (see §N / SUB-TERM).

---

## H. Cross-module integration

Masters feed **everywhere**: `inspection_type` → Calls/Jobs/Reports; `sbu`,
`designation`, `department` → org/allocation; `expense_heading` → Vouchers;
`payment_term`, `quote_*` → Quotations; `report_category`, `inspection_disposition`,
`release_status` → IDEMS; product/vendor lists → Directory. **TC-MASTERS-int-002:** add
a value → it is immediately selectable on every consuming form (no cache staleness).
**Idempotency (TC-MASTERS-int-001):** a double-clicked "Add" must not create two
identical rows — **DEF-MASTERS-001** (no dedup on generic insert).

---

## I. Data integrity & audit trail

- **DELETE has no referential guard.** `ops_master_handle` DELETE runs
  `DELETE FROM $table WHERE id=?` with no check that the value/record is in use.
  **TC-MASTERS-int-003:** delete a lookup value that a live report/call references →
  the reference is **orphaned** (the record now points at a missing value). This is
  **DEF-MASTERS-003 (Major/P2)** — recommend a "in use, cannot delete / deactivate
  instead" guard, matching the reversible-hide pattern used elsewhere (report types).
- **Audit trail:** confirm master edits are captured (the app has a hash-chain audit
  for IDEMS; master CRUD should at least log who/when). **GAP-MASTERS-003** if master
  changes are not audited.
- Injection-safe: `$table` and field names come from server-side `cfg` (not user
  input); values are parameterised. **TC-MASTERS-sec-001** confirms a script-y label is
  stored and rendered escaped, never executed.

---

## J. Reports & outputs

Masters are not themselves rendered to PDF/Word, but their **values print** on reports,
quotes and invoices. **TC-MASTERS-out-001:** a renamed/added value shows correctly on a
downstream **PDF and Word** output, not just on screen. `org_register_csv` /
`partner_template_csv` export/import round-trips (see §K).

---

## K. Negative, edge & resilience

- Empty registry (fresh tenant) → seed guards populate defaults; no crash.
- Import: `partner_template_csv` / org register CSV — malformed row rejected **with a
  reason**, not silently dropped (SUB-IMPORT). **TC-MASTERS-imp-001.**
- Large list (hundreds of values) → `/lookup` still usable (search/scroll).

---

## L. TPIA operational suitability

Strong: everything a TPIA configures (inspection types, dispositions, release statuses,
ITP clauses via report design, expense heads, SBUs, offices) is **editable, not
hardcoded** — this is what lets one codebase serve any TPIA. Confirm the seeded
defaults are **industry-neutral** (they are, per prior terminology work) and that an
industry pack can re-seed vocabulary.

## M. Management usefulness

Indirect: correct masters drive correct dashboards, TAT and profitability. A wrong
`sbu`/`office` mapping mis-attributes revenue. **TC-MASTERS-mgmt-001:** an office/BU
added here appears in the dashboard filters and rollups.

## N. UI/UX & accessibility

The `/masters` map is a clean three-layer index (SOP-documented). Redirect-only cards
prevent the "two editors, half a record" trap. Terminology flows (labels via `T*()`).
Confirm keyboard/labels on the cascade selects (SUB-TERM / a11y).

## O. Security (module)

- Authorisation on the **action**: `/lookups` and `/m/<key>` POSTs must be refused for
  a non-admin even when the request is crafted directly (not via a hidden-only button).
  **TC-MASTERS-sec-002.**
- CSRF: master POSTs go through the global CSRF gate — confirm the token is required.
- No injection (table/columns from config; values parameterised).

---

## P. Coverage scorecard (29 dimensions)

| # | Dimension | Covered | Evidence / TC | Findings |
|---|---|---|---|---|
| 1 UI/Screens | Y | §B | — |
| 2 Fields | Y | TC-MASTERS-mform-* | — |
| 3 Buttons/Actions | Y | list/new/edit/delete | — |
| 4 Functions | Y | §D | — |
| 5 Statuses | Partial | §E | is_active only |
| 6 Validation | Partial | TC-mform-001 | DEF-MASTERS-002 |
| 7 Negative/Edge | Y | §C/§K | DEF-MASTERS-001 |
| 8 Roles/Perms | Y | §F | GAP-MASTERS-002 |
| 9 Data scope | Partial | §F | masters are global; salary-field hiding is the scoped part |
| 10 Settings | Y | §G | — |
| 11 Workflow | Y | SOP | — |
| 12 Integration | Y | §H | DEF-MASTERS-001 |
| 13 Data integrity | **N** | §I | **DEF-MASTERS-003 (orphan on delete)** |
| 14 Audit/Immutability | Partial | §I | GAP-MASTERS-003 |
| 15 Reports/Outputs | Y | §J | — |
| 16 TPIA suitability | Y | §L | — |
| 17 Mgmt usefulness | Y | §M | — |
| 18 UI/UX/a11y | Y | §N | confirm cascade a11y |
| 19 Gap analysis | Y | §Q | — |
| 20 Security | Y | §O | — |
| 21 Migration/Import | Partial | §K | TC-MASTERS-imp-001 |
| 22 Notifications | N-A | — | masters send none |
| 23 Offline/PWA | N-A | — | admin screen, online |
| 24 AI-assist | N-A | — | — |
| 25 Licensing/Seats | N-A | — | — |
| 26 Multi-tenant/Terminology | Y | §L/§N | — |
| 27 Time/FY | Partial | created_at | holidays/FY masters feed §27 elsewhere |
| 28 Performance | Partial | §K | large-list sample |
| 29 Backup/Continuity | N-A here | — | covered at platform level |

**Verdict:** **Complete-with-defects** — one **Major** data-integrity defect
(orphan-on-delete) must be resolved or deferred with risk acceptance before Masters is
signed **Complete**.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title | Detail → Recommendation |
|---|---|---|---|
| DEF-MASTERS-001 | Major·P2 | Double-submit creates duplicate master rows | Generic insert has no dedup/idempotency. → guard on a natural key (code/label) or disable the submit after first click. |
| DEF-MASTERS-002 | Major·P2 | `req` fields not server-enforced on generic insert | The `req` flag drives the form UI, but `ops_master_handle` inserts whatever is posted. → validate required fields server-side before insert/update. *(Confirm per register.)* |
| DEF-MASTERS-003 | Major·P2 | Delete of an in-use master value orphans references | No referential guard on `DELETE FROM $table`. → block delete when referenced; offer **deactivate** (reversible), mirroring the report-types "hide empty" pattern. |
| GAP-MASTERS-001 | — | Duplicate-value guard on lookups unconfirmed | Verify adding an identical value is prevented or coalesced. |
| GAP-MASTERS-002 | — | `/masters` map is coordinator-level, editors admin-level | Confirm a coordinator cannot save any master; if intended read-only, label it so. |
| GAP-MASTERS-003 | — | Master edits may not be in the audit trail | Confirm who-changed-what is logged for reference-data changes (SOX/ISO records). |

---

## R. Traceability

RTM slice appended: `SCR-ADM-masters`, `/m/<key>`, `SCR-ADM-lookups`,
`/custom-fields` × dimensions 1–29 → the TC-MASTERS-* IDs above → results → DEF/GAP.

**Module verdict:** **Complete-with-defects.** To reach **Complete**: resolve or
risk-accept DEF-MASTERS-001/002/003 and close GAP-MASTERS-001/002/003.
