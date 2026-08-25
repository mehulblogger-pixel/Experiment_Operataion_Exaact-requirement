# Module 23 — Equipment 360 · Edge-case analysis (pre-build)

**Status:** 📝 SPEC — awaiting decision. P1.

---

## 0. Headline: the master is solid — the missing piece is *impact awareness*

The measuring-equipment control is real and already well-built:

- **Master register** `equipment` (`lib/equipment.php:46-55`) — code, kind, serial, owner
  office, held-by inspector, status enum (`ACTIVE/CALIBRATION/REPAIR/QUARANTINE/RETIRED`),
  calibration interval, ownership (`OWN/HIRED/CLIENT/VENDOR`).
- **Certificate history** `equipment_calibrations` (`:57-63`) — one row per certificate,
  never overwritten; `cal_date`, `valid_to` (expiry), `cal_body`, `traceable_to`, result,
  scanned file.
- **Report link** `report_equipment` (`:65-68`) — which report relied on which instrument,
  stamping `calibration_id` at add-time so a later re-calibration can't rewrite what an
  issued report rested on.
- **At-issue hard block** — `pack_fire('document.issue')` → `report_equipment_block()` →
  `equipment_block()` → `equipment_calibration_on()` walks the certificate history and
  blocks issue if the instrument had no PASS certificate *in force on the inspection date*,
  or is in an unusable status. Never overridable, even by a master.
- **Expiry reminders** — `equipment_run_cal_reminders()` (cron), 30-day window, emails QAC /
  coordinators; `/equipment` register shows a due-soon banner.
- **Register + detail screens** — `/equipment` (list) and `/equip-edit` (identity + full
  calibration history + file-a-certificate form).

So the forward direction (does *this report's* instrument have a live certificate?) is fully
guarded. **What is entirely absent is the reverse direction:** when a certificate lapses or a
calibration is found retrospectively bad, *nothing identifies which already-issued reports and
jobs cited that instrument*. No function, route or screen queries
`report_equipment WHERE equipment_id=?` — even though the FK exists and is indexed for exactly
this direction (`ix_req_equip`, `lib/indexes.php:120`). The reminder emails a human about the
instrument; it never names a single affected report.

**The second, softer gap:** the register is a *register*, not a 360. The detail screen shows
identity + calibration history but no utilisation, no owning-job history, and — critically — no
"reports this instrument was used on" list. The instrument↔report link surfaces only on the
*document* side (`doc_detail.php` "Measuring & test equipment used" panel), never on the
equipment side.

---

## 1. The dual-capture caveat (must be stated, not silently ignored)

Instruments reach a report **two ways**:

1. **Linked** via `report_equipment` — a real FK to the master; reverse-lookupable by join.
   This is what the hard block reads.
2. **Typed** into the IDEMS report `instruments` JSON table (`fkey='instruments'`) — auto-filled
   from the register dropdown but **saved as serial/ID strings**, not FKs. The completeness
   check (`idems_completeness_check` 'calibration') compares each row's due-date against the
   inspection date and reports "N instrument(s) out of calibration" as a *soft* completeness
   item; it does not drive the hard block and is not join-lookupable.

Any impact analysis must decide how it treats (2): join-only (covers linked instruments,
honest about the gap) vs. also string-matching serials in report `data` (covers more, but
fuzzy). The recommended option is **join-first, with an explicit note** that string-only
instruments aren't covered — never a silent partial that reads as "all clear".

---

## 2. Proposed additive layer (recommended = §5-A)

1. **`reports_using_equipment($equipmentId)`** (`lib/equipment.php`) — a read-only reverse
   lookup: `SELECT ... FROM report_equipment re JOIN <report/doc> d ON d.id=re.report_doc_id
   WHERE re.equipment_id=?`, returning each report's id, code, status (draft/vetted/approved/
   issued), inspection/issue date, job, and the `calibration_id` that was in force when linked.
   Uses the existing indexed FK; no schema change.

2. **`equipment_calibration_impact($equipmentId)`** — layers a *verdict* on §2.1 without
   changing any data:
   - For each linked report, compare the certificate it rested on (`calibration_id`, or the
     cert in force on its date) against the instrument's **current** certificate history.
   - Classify: **OK** (rested on a valid, still-good certificate); **REVIEW** (rested on a
     certificate later superseded, or the instrument since went to a FAIL / unusable status, or
     the report's date falls in a now-uncovered gap). *Never* auto-invalidate — REVIEW is a
     flag for a human quality decision.
   - Only **issued/approved** reports matter for impact (a draft will re-hit the hard block on
     issue); drafts are listed but not counted as impact.

3. **Equipment 360 surface** on `/equip-edit` (or a `?tab=impact`):
   - **"Reports & jobs using this instrument"** — the reverse-lookup list, each with its status
     pill and the certificate it rested on.
   - **"Calibration impact"** panel — the REVIEW count and list, worded as *"these issued
     reports may need a controlled quality review — decide per your quality procedure; nothing
     is auto-invalidated"*. A clean instrument shows "no issued report is affected".
   - The dual-capture note when the instrument appears in string-only JSON tables that aren't
     linked.

4. **(Optional, same option) tie into the expiry reminder** — when
   `equipment_run_cal_reminders()` fires for a lapsed instrument, include the *count* of issued
   reports that rested on it ("… used on 3 issued reports — review at /equip-edit?id=…"), so the
   human who gets the email knows the blast radius. Read-only; no new email type.

Reuses `report_equipment` (+ `ix_req_equip`), `equipment_calibrations`, `equipment_calibration_on`,
`equipment_can_manage`, the register/detail views, the reminder cron. **No schema change; no new
permission** (reuses `equipment_can_manage()` / `mod.equipment.view`).

**Deferred (noted, §5-B):** a full equipment **lifecycle/maintenance** layer (utilisation
metrics, maintenance timeline, condition beyond the status enum) and forcing the string-only
IDEMS JSON instruments through `report_equipment` — both are larger, deliberate changes.

---

## 3. Edge cases

1. **Instrument never used on any report** → reverse lookup empty; the panel says "not yet used
   on any report", never an error.
2. **Instrument used only on drafts** → listed, but impact count is 0 (a draft re-hits the hard
   block on issue; it isn't a released-work risk).
3. **Certificate lapses today, reports issued last month under a then-valid cert** → those
   reports rested on a valid certificate *on their date* → **OK**, not REVIEW. Expiry does not
   retroactively taint correctly-covered past work. (This is the core "flag, don't
   auto-invalidate, and don't over-flag" rule.)
4. **A calibration found retrospectively bad (result flipped to FAIL, or cert revoked)** → every
   issued report that rested on that certificate → **REVIEW**. This is the case the whole module
   exists for.
5. **Gap in certificate coverage** — report dated in a window with no PASS certificate in force
   → REVIEW (it should never have issued; if it did, it's exactly what a review must catch).
6. **Instrument in string-only JSON, not linked** → not join-lookupable; surfaced as a caveat
   ("N reports cite this serial in text but aren't linked — not covered by impact analysis"),
   never counted silently as clean.
7. **Re-calibration after issue** → the report keeps the `calibration_id` it was stamped with;
   the impact verdict compares that stamped cert, not merely "the latest", so a good report
   isn't falsely flagged by a newer certificate.
8. **Retired / quarantined instrument** → still reverse-lookupable (past reports used it); the
   impact panel still evaluates them. Retiring an instrument doesn't hide its history.
9. **Hired / client-owned instrument** → same treatment; ownership doesn't change impact logic.
10. **Performance** → one indexed reverse-lookup query + one certificate-history read per
    instrument-detail view; not per-report N+1. The reminder-count add is one `COUNT(*)`.
11. **Permissions** → the impact panel is behind `equipment_can_manage()` (register mutation
    level) or the existing `mod.equipment.view`; it exposes no report content a viewer couldn't
    already see, only the linkage.
12. **Read-only** → nothing here writes, blocks, or invalidates. The at-issue hard block is the
    only enforcement point and is untouched.
13. **Mobile** → the lists are single-column; coordinators/QAC read this desk-first, but it
    degrades cleanly.

---

## 4. Guardrails (must stay green)

- The `document.issue` calibration hard block (`report_equipment_block` →
  `equipment_calibration_on`, never overridable) — **unchanged**; the impact analysis reads the
  same helpers, adds no bypass.
- The certificate-never-overwritten history, the `calibration_id` stamp-at-add-time, the
  expiry-reminder cron, the register due-soon banner, the document-side "equipment used" panel —
  all unchanged.
- `test_simplify_quality_split` (route + record-type), `test_module08_issue_readiness`
  (issue-readiness covers the instrument gate) — untouched.
- No existing route, table, column or permission removed or narrowed.

---

## 5. OPEN DECISION — impact-flagging 360, or also a maintenance/lifecycle layer?

- **(A) Equipment 360 impact surface (recommended, P1):** add the reverse lookup
  (`reports_using_equipment`) + a read-only calibration-impact verdict that lists the *issued*
  reports which may need controlled quality review when a certificate lapses or is found bad —
  **flag, never auto-invalidate**, honest about string-only instruments — surfaced on the
  equipment detail, plus the blast-radius count in the expiry reminder. Turns the register into
  a 360 on the axis that matters (what released work depends on this instrument) with no schema
  change, no new permission, and the hard block untouched.
- **(B) Also add a maintenance/lifecycle layer** — utilisation metrics, a maintenance/service
  timeline, a distinct condition model beyond the status enum, and forcing the string-only IDEMS
  JSON instruments through `report_equipment` so *everything* is join-lookupable. Larger, touches
  the report-entry path and adds schema; better done deliberately after (A) proves the impact
  surface.

Default if you don't specify: **(A)**.

---

## 6. Tests

1. `reports_using_equipment($eqId)`: returns the reports linked via `report_equipment`; empty
   for an unused instrument; independent of report status.
2. `equipment_calibration_impact`: an issued report resting on a still-valid certificate →
   **not** flagged; an issued report whose certificate was later flipped to FAIL / revoked →
   **REVIEW**; a report dated in an uncovered gap → REVIEW; a draft → listed but not counted.
3. Expiry does not retroactively flag a report that was correctly covered on its own date
   (edge case 3).
4. The `document.issue` hard block and `equipment_calibration_on` are unchanged; the impact
   analysis is read-only and adds no override.
5. No new permission constant; the panel is behind the existing `equipment_can_manage` /
   `mod.equipment.view`.
