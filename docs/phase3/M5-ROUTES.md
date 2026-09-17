# PHASE 3 · M5 — ROUTES AND ENFORCEMENT POINTS

M5 adds **no new route and no new screen**. It moves three columns out of the
blind form-save lists they were riding in, and puts one door in front of them.

| Path | Production entry | M5 enforcement |
|---|---|---|
| Create a requisition | `ops.php` `requisition-new` POST | the requisition is inserted **unowned**; the owner is then set through `rasg_assign()` with the same controls as an edit. A refused owner leaves the requirement unassigned **and says so on screen** |
| Edit a requisition | `ops.php` `requisition-edit` POST | ownership is applied **before** the rest of the save, so a refused ownership change refuses the whole save instead of half-applying it |
| Create a candidate | `ops.php` `candidate-new` POST | as above, for `candidates.recruiter_id` |
| Edit a candidate | `ops.php` `candidate-edit` POST | as above |
| Public careers application | `careers.php` `careers_apply()`, served **in front of `require_login()`** | the recruiter is **inherited** through `rasg_inherit_from_requisition()`, which skips **only** the permission question; everything else — entitlement, the record, the state, the M4 boundary, and every question about the person — is still asked |
| Requisition from an approved hiring request | `hreq_to_requisition()` | writes **no** owner; the requirement is created unassigned and appears in the unassigned queue |
| Requisition from a project costing line | `pc_line_to_requisition()` | writes **no** owner; same |
| Recruitment command centre | `recruit_cc.php` `rcc_data()` | now **branch/BU-scoped**, through `rcc_scope_req()` / `rcc_scope_cand()` |
| Recruitment CSV export | `recruit_export.php` | inherits the same scope, because it shares the same two WHERE builders |

## The single authoritative door

`rasg_assign()` — the only writer of `requisitions.recruiter_id`,
`requisitions.manager_id` and `candidates.recruiter_id`. `rasg_check()` is its
pure question, for a screen that wants to know in advance; asking it early is a
convenience, and asking it again at the write is what actually protects anything.

A repository-wide sweep (probe **J7**) asserts that **no library outside
`recruit_assign.php` writes an ownership column** — an INSERT or UPDATE that
names one, anywhere in `lib/`, fails the test suite.

## Why the create path inserts unowned and then assigns

It would have been shorter to validate the posted recruiter and include the
column in the INSERT. That is the shape that produced the defect: two code paths
that *look* equivalent, drifting apart the first time one of them gains a check
the other does not. Creating unowned and assigning through the door means
**create and edit are literally the same code**, and probe **I** asserts they
refuse identically.
