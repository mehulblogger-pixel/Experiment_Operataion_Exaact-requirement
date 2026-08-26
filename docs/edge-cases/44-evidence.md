# Module 44 — Evidence · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: strong evidence chain, but the report doesn't "know about" it

The evidence subsystem (`lib/trust.php`) is well-built: a per-photo SHA-256 **hash chain**
(`evidence_chain`, `chain_append`/`chain_verify`), strict EXIF-vs-upload location fact-separation,
server-authoritative capture time, geofenced site check-ins (`site_visits`), permission-checked
report-file serving, and a public `/verify` page. The gaps: a report can be **issued with zero photos
and no site check-in, undetected** (the issue-readiness preview ignores evidence entirely, and
submit-completeness vs job-close live in separate gates); the `/verify` page counts photos but never
states the strongest evidence — that **somebody was actually on site**; and `chain_verify`'s
tamper-detection was **untested**.

**Flagged, NOT changed (access-affecting — needs your go):** `/checkin-photo` serves a
`site_visits` photo by id with **no per-job scope check**, unlike the report-file server
(`idems_file_authorized`). Tightening it changes access, so it is left as-is and flagged.

---

## 1. Built (additive, read-only, no hard control)

1. `idems_evidence_readiness($doc)` — reads the three existing sources: `report_files` (photo count,
   EXIF on-site count), `site_visits` (arrival/departure via `site_visit_kinds_done`), and
   `chain_verify($id)` (chain intact). Read-only; no new table.
2. An **"Evidence & on-site" row** on the issue-readiness preview (`idems_issue_readiness`) — e.g.
   "3 photos (2 located on site) · arrival + departure recorded · evidence chain intact", or a
   **warn** ("no site check-in recorded" / "no photographs on file"). **Warn only — never blocks
   issue** (it doesn't affect `ready`).
3. An **"On-site check-in"** line on the public `/verify` page (via `verify_lookup`) — states
   arrival/departure was recorded and roughly how long, the strongest evidence the page omitted.
4. The **first tamper test** of `evidence_chain` (`chain_verify` CONTENT detection) — the subsystem's
   core integrity claim was untested.

---

## 2. Edge cases handled

1. A desk report with no job → no site-check-in line (nothing to warn about beyond photos).
2. A report with photos + arrival + departure + intact chain → **ok**.
3. A report with no photos, or only a partial check-in, or a broken chain → **warn**, never block.
4. The verify on-site line only shows when a check-in window exists (`in` or `out`), and states
   duration only when both are present.
5. The chain readiness/verify is fail-safe (guarded `function_exists`); a report with no chain
   entries simply omits the chain phrase.

## 3. Guardrails (green)

The hash chain, EXIF/upload fact-separation, the freeze-at-issue write-gate, the report-file
permission serving, and the geofence — all unchanged. No new permission; no schema change; the
evidence row and verify line are advisory/read-only; the `/checkin-photo` scope is untouched
(flagged, not modified); nothing deleted.

## 4. Tests

`tests/test_module44_evidence.php` (15 assertions): the readiness helper counts photos/on-site/
check-in; the issue-readiness preview carries an Evidence & on-site row that warns (never blocks) on
a bare report; a freshly sealed chain verifies and a byte change is detected as CONTENT; the verify
lookup + page carry the on-site check-in.
