# Module 26 — Identity (DPDP documents) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: already DPDP-mature — the gap is a DPO's cross-cutting view

The identity-document subsystem (`lib/identity.php`) is a genuinely DPDP-aware design, not a bolt-on:

- **Numbers masked by storage design** — `iddoc_list()` / `iddoc_row()` select only `number_last4`;
  the full `doc_number` is read solely inside the reveal/download handlers. `iddoc_mask()` returns
  `•••• last4`.
- **Reveal costs a reason and is logged** (`iddoc-reveal` → `iddoc_log('REVEAL', reason)`), shown
  once via a session flash — never in a URL.
- **The scan is permission-checked** (`iddoc-file` behind `ops_require(iddoc_can_manage())`) and its
  download is logged.
- **Copy-out (SHARE)**, **redaction** (wipes number + file, keeps the row as evidence), **consent**
  (`consent_on/note`) and **purpose** (stamped per row) are all handled.
- **Retention runs** — a nightly sweep auto-redacts documents past `retain_until`; **expiry
  reminders** email the engineer 45 days before a passport/visa lapses.
- **Permissions** `person.iddoc.view` (see a doc is on file, masked) vs `person.iddoc.manage`
  (file/reveal/share/redact) are kept out of role defaults — a named custodian only.

The one real gap: the access log (`person_document_access`) is only ever queried **per person**
(`iddoc_access_log(0, $pid)`). Nobody calls it company-wide, and Data Control never renders it — so a
**Data Protection Officer cannot review "every reveal / copy-out this quarter, across all people"**
from a screen. Every look *is* recorded; a DPO simply can't *review* them all in one place.

Secondary observations (flag, don't change without asking): the access log is a plain table, not the
sealed `idems_audit` chain (not tamper-evident); the `ADMIN` role gets iddoc by default; there is no
holding-review independent of expiry. And the security-critical behaviours (masking, reveal logging,
redaction-preserves-row) had **no test coverage**.

---

## 1. Not in scope (program rules)

- **No loosening of access.** Reuses `iddoc_can_manage()`; no new permission; the review exposes no
  document number (the log never stored one).
- **No hard control / no default change.** The `ADMIN`-gets-iddoc default is noted, not changed.
- **Sealing the access log into `idems_audit`** (tamper-evidence) touches the write path — deferred
  as a secondary option.
- **Nothing deleted.**

---

## 2. Built (recommended additive option)

1. `iddoc_access_review($action, $from, $to)` + `iddoc_access_summary()` — the company-wide log
   across all people, joined to the person name + document kind, filterable by action and date.
   Reads the existing `person_document_access`; carries reasons/recipients/actors/IPs, never a number.
2. A manage-gated **`/iddoc-access` "Access review (DPO)"** route + `views/ops/identity_access.php`:
   a count-by-action header, the filterable log table, and the `iddoc_holders()` "who can open a
   document" roster — linked from the identity register.
3. The **first unit tests** over masking, the list-reader never selecting the full number,
   reveal/share logging, and redaction preserving the record.

---

## 3. Edge cases handled

1. A reveal/share is shown with its reason/recipient; a plain register VIEW (document_id 0) still
   lists, joined to the person by `person_id`.
2. The review **never** selects `doc_number` — a viewer sees who-looked, never a number.
3. Filtering by action returns only that action; by date uses `substr(at,1,10)` (works on SQLite and
   MySQL).
4. Empty window → "No access recorded", never an error.
5. A redacted document still appears in the log (the access happened); its row survives.
6. Gated by `iddoc_can_manage()` — a `view`-only holder does not reach the company-wide screen.

## 4. Guardrails (green)

The masking discipline, the reveal-reason gate, the permission-checked scan, the retention sweep, the
expiry reminders, and both `person.iddoc.*` gates — all unchanged. `test_agency_staff`,
`test_retention` untouched.

## 5. Tests

`tests/test_module26_identity.php` (18 assertions): the DPO review + summary; masking; the
list-reader hides the full number; reveal/share are logged and visible company-wide; redaction wipes
the number but keeps the row; no document number in the review; no new permission.
