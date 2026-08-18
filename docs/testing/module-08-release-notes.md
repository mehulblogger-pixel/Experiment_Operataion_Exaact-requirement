# Inspection Ops — Release Notes & Issue Lifecycle — Test & Documentation Report

> **Prompt 3 · Module MOD-IDEMS (Release Notes + finalize/issue).** Read from
> `lib/idems.php` (`ops_idems_release_note`, `idems_release_note_offer`,
> `idems_rn_blockers`, `idems_build_release_note`, `idems_rn_items_from_source`,
> `idems_released_line_items`, `idems_release_block`, `idems_release_statement_default`,
> `document-finalize`/`document-revise`/`document-delete` handlers, `idems_revise_doc`,
> `idems_snapshot_signatures`, `idems_seal_content`, `idems_freeze_presentation`,
> `idems_notify_client_issued`, `document-rn-flag`), `lib/urade.php` (release
> eligibility), views `idems/doc_detail.php`, `idems/release_register.php`.

| | |
|---|---|
| **Module** | Release Notes & Issue lifecycle (MOD-IDEMS/RN) · Area Reporting |
| **Personas** | P-INSP (flag RN, raise), P-VET/P-APPR, P-FINAL (`idems.finalize`/master to issue), P-MASTER, P-CLIENT-neg |
| **Risk weight** | **Highest** — the Release Note clears an item for dispatch; issuing locks the record and notifies the client |
| **Verdict** | Complete-with-defects (confirm RN gating, issue immutability, seal, client-acceptance at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. RN button gating (issued + flag + blockers) verified live. |

---

## A. Module overview

A **Release Note (RN/IRN)** authorises dispatch of an inspected item. Per the workflow
overhaul (W4), it is deliberately gated:

- It can be raised **only from an issued (accepted) inspection report** — not a draft or
  merely-approved one (Master may override for corrections).
- The inspector must **tick "Release Note to be issued"** (`document-rn-flag`) — a
  disposition decision that stays settable even after the report body is locked.
- **Blockers** (`idems_rn_blockers`) must be clear: open hold/witness points, open
  deviations/NCRs, client rejection, or pending client acceptance (when
  `rn_require_client_acceptance` is on).
- The RN carries the source report's identifying + outcome fields (PO items shown as
  **released line items only**), links to it (`source_report_id`, `release_of_id`), and
  gets its own IRN. It **skips vetting** — straight to the approver — then is issued.
- Corrections go through a **revision** (`document-revise`) of the source report, not an
  edit of the issued one.

**Issue/finalize** (`document-finalize`) is the shared terminal step for every report:
full-approval check, QA-critical gate (override logged), pack issue gates (calibration =
hard block; unauthorised signatory = warning + NCR), then **immutability**: status ISSUED,
**signatures snapshotted**, **content sealed**, **presentation frozen**, client notified.

Screens: `/document?id=` (RN button, finalise, revise), `/release-notes` (RN register).

---

## B. Screen-by-screen catalogue

**RN offer** (`idems_release_note_offer`, on `doc_detail`) — the button appears once the
source is issued; it is **shown but not clickable** while any blocker stands, with the
reasons listed; if an RN already exists it links to it (never duplicates).

**Finalise & issue** (playbook CTA → `document-finalize`) — visible to a finalizer;
gated by approval + QA-critical + pack issue rules.

**Revise** (`document-revise`) — drafts a new revision of an issued report (original
stays on file, unchanged); one revision at a time (`EXISTS`).

**`/release-notes`** — RN-only register showing each RN, its source IRN (from data JSON),
and release status; open/issued counts.

**`document-delete`** — soft-delete only (audit row stays); finalizer/master.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-RN-form-001 | RN button hidden until the source is **issued**; on a draft/approved-not-issued report there is no button. |
| TC-RN-form-002 | RN button **not clickable** while any blocker stands; each blocker reason is spelled out. |
| TC-RN-form-003 | `document-rn-flag` toggles `rn_to_issue` and stays settable after body-lock; logs RN_FLAG. |
| TC-RN-form-004 | Raising an RN when one already exists links to it — **no duplicate** (matched on numeric `source_report_id`). |
| TC-RN-form-005 | The RN carries client/vendor/PO/dates/results and **only released line items**; release statement prefilled. |

---

## D. Functions & logic  *(gating + immutability — highest scrutiny)*

- **RN eligibility** (`idems_release_note_offer` + `idems_rn_blockers`): issued AND
  flagged AND no open HWP AND no open NCR AND not client-rejected AND (client accepted OR
  acceptance not required). **TC-RN-fn-001..005** — one blocker each; all clear ⇒ raiseable.
- **RN creation** (`ops_idems_release_note`): re-checks issued + blockers server-side
  (Master override allowed, still logged); seeds the RN type once; carries fields; sets
  `release_of_id`; generates a fresh IRN; status DRAFT. **TC-RN-fn-006** — a crafted POST
  bypassing the button is still gated server-side.
- **Issue/finalize** (`document-finalize`):
  - full-approval check when a chain exists (Master override);
  - **QA-critical gate** — a critical QA finding blocks issue; Master override needs a
    reason, logged QA_CRITICAL_OVERRIDE;
  - **pack issue gates** — uncalibrated instrument = **hard block (never overridable)**;
    unauthorised signatory = warning + auto-NCR;
  - then **immutability**: `finalized=1, status=ISSUED`, `idems_snapshot_signatures`,
    `idems_seal_content`, `idems_freeze_presentation`, vendor-qualification roll-forward,
    audit-findings→NCR, HWP derivation, client notify. **TC-RN-fn-007..010.**
- **Revision** (`idems_revise_doc`): a new rev; original untouched; one at a time.
  **TC-RN-fn-011** — revision keeps lineage, original stays issued/immutable.
- **Client notify** (`idems_notify_client_issued`): best-effort, never blocks issue.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| source ISSUED → RN DRAFT | raise RN | issued + flagged + blockers clear; no existing RN |
| RN DRAFT → UNDER_REVIEW | submit | **skips vetting** (RN excluded); approval chain built |
| RN UNDER_REVIEW → APPROVED → ISSUED | approve + finalise | approver = directly (no vetter) |
| any report → ISSUED | finalise | approval + QA-critical + pack gates; then immutable |
| ISSUED → (revision DRAFT) | revise | original stays immutable; one rev |
| any → deleted | soft-delete | audit row retained |

- **TC-RN-life-001:** an RN cannot be raised from a non-issued source (non-master).
- **TC-RN-life-002:** issuing makes the report **immutable** — edit/submit/finalize on an
  already-issued doc are refused; the content seal + frozen presentation hold.
- **TC-RN-life-003:** a calibration block cannot be overridden even by a master.

---

## F. Roles, permissions & data scope

Flag RN / raise RN / revise: `is_master()` or `mod.idems.edit`. Finalise/issue/delete:
`is_master()` or `idems.finalize`. QA-critical and (rare) approval overrides: master only,
reason-logged. RN register scoped by office/SBU. Client notification only to the report's
client contacts.

- TC-RN-perm-001: a non-finalizer finalise POST → refused.
- TC-RN-perm-002: RN raise by an unauthorised user → refused.
- TC-RN-perm-003: the calibration hard-block holds regardless of role.

---

## G. Settings

`rn_require_client_acceptance` (adds the client-acceptance blocker), RN type schema &
release statement default, IRN format, QA-critical gate, inspection-pack issue gates
(calibration/authorisation), client-notification recipients & templates. **TC-RN-set-001:**
with acceptance required, an unaccepted report blocks the RN; off, it does not.
**TC-RN-set-002:** the QA-critical gate blocks issue unless overridden with a reason.

---

## H. Cross-module integration

**IDEMS core** (source report; twin/PO settlement; content seal), **Vetting/Approval**
(RN skips vetting; still approved — MOD-07), **Hold/Witness points** (`hwp_open_count`
blocker; `hwp_derive_from_doc` on issue), **NCR/CAPA** (open-NCR blocker; audit→NCR;
signatory→NCR), **Vendor platform** (assessment score roll-forward on issue), **Client
portal** (issued delivery + notification), **Jobs** (final-docs close gate needs the RN).
Idempotency: raising an RN twice, or double-finalize, must not duplicate — TC-RN-int-001.

---

## I. Data integrity & audit

Issue writes an immutable record: `idems_seal_content` (content hash for `/verify`),
`idems_snapshot_signatures` (frozen signatures), `idems_freeze_presentation` (schema +
template frozen so later config changes never alter an issued report). Audit logs
RELEASE_NOTE_DRAFT/CREATED, FINALIZE, QA_CRITICAL_OVERRIDE, VENDOR_QUALIFIED, DELETE.
**TC-RN-int-010:** the source↔RN link is by numeric id (JSON-slash-safe); **TC-RN-int-011:**
a post-issue body tamper is detected by the seal and the audit chain.

---

## J. Reports & outputs

The RN PDF (through the same form engine, released line items only, default release
statement); the client-issued notification email; the RN register; `/verify` proving the
seal. **TC-RN-out-001:** the RN PDF shows only released items and the correct release
statement (RELEASED vs RELEASED_COND); **TC-RN-out-002:** the client notification names
the IRN and links to the portal copy.

---

## K. Negative, edge & resilience

Raise an RN from a draft (refused); with an open HWP/NCR (refused); when the client
rejected (refused); when acceptance is required and pending (refused); a duplicate RN
(linked, not created); finalise without full approval; finalise with a critical QA issue
and no override reason; finalise against an uncalibrated instrument (hard block); edit an
issued report (refused); a second concurrent finalise; revise twice.

---

## L. TPIA operational suitability

Matches how a TPIA clears dispatch: a release authorised only after the inspection is
accepted, all holds/deviations closed, and (optionally) the client has accepted — with a
revision route for corrections rather than editing an issued document. Immutability +
seal + frozen presentation give the release the evidential weight a client and an
accreditation body expect.

## M. Management usefulness

RN register with source linkage and release status; blockers surfaced on the report so
the coordinator sees exactly what stops a release; issued-report notification confirms
client delivery. Confirm the blocker list matches the actual open holds/NCRs.

## N. UI/UX

One-click RN button that explains *why* it is disabled; the flag and the button on the
same report; guided playbook; revision keeps the original on file. Terminology via `T*()`.

## O. Security

RN raise / finalise / delete authorised server-side; blockers and the issued-source rule
enforced on crafted POSTs (not just the button state); calibration block non-overridable;
QA-critical override master-only + reason-logged; seal + frozen presentation prevent
post-issue alteration; client notification never leaks cross-scope.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E RN gating + issue |
| 6 Validation | Y | §C blockers |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §A/§D RN skips vetting + immutability |
| 12 Integration | Y | §H (HWP/NCR/portal) |
| 13 Data integrity | **Priority** | §I seal + freeze |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J RN PDF + notify + /verify |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | Y | client-issued |
| 23 Offline | N-A | — |
| 24 AI | Partial | QA-critical gate on issue |
| 25 Licensing | Y | IDEMS module |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | issue date, IRN FY |
| 28 Performance | N-A | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-RN-001 | (verify — Major) | Confirm every RN **blocker** (issued-source, flag, open HWP, open NCR, client-rejected, acceptance-pending) is enforced on the **raise POST**, not only on the button, and the Master override is always logged. |
| GAP-RN-002 | (verify — Major) | Confirm **issue immutability**: an issued report cannot be edited/submitted/finalized again; the content seal + frozen presentation detect/prevent alteration; the calibration block is non-overridable. |
| GAP-RN-003 | (verify) | Confirm an RN raised twice links to the existing one (no duplicate IRN) and double-finalize does not double-notify or double-roll vendor scores. |
| GAP-RN-004 | — | Confirm client-acceptance capture (`client_decision`) is authenticated and cannot be spoofed to clear the acceptance blocker. |

---

## R. Traceability

RTM slice: `/document` (RN button / finalise / revise), `/release-notes`,
`document-rn-flag`, `document-finalize`, `document-revise` × dims 1–29 → TC-RN-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — RN gating enforcement, issue
immutability + seal, and no-duplicate/no-double-issue are the exit conditions.
