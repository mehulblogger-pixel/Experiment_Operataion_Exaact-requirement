# Module 39 — My Work · Edge-case analysis (pre-build)

**Status:** edge-cases drafted — awaiting go before any code.
**Priority:** P1. **Risk:** low (additive, read-only launcher over existing data).

---

## 0. What this module is (and is NOT)

A single, role-relevant **My Work** landing page that shows each user exactly what
needs *their* action, grouped into lanes, each row linking to the screen that already
handles it. It is a **launcher/queue**, not a new workflow — it performs no state
changes itself.

### Grounding (what already exists — reuse, do not duplicate)
- `ops_pending_tasks()` / `ops_render_pending_tasks()` (`lib/ops.php:6437-6567`) already
  computes a per-user, role-gated, office/SBU-scoped set of "pending task" tiles and is
  rendered as **"Your pending tasks"** on both the dashboard (`views/dashboard.php`) and
  operations home (`views/ops/operations_home.php`). **This is the single source of truth
  and must be reused, not re-queried.**
- Nav already has an area literally named **"My work"** (`lib/navindex.php:57-66`) listing
  links (My job, My report, New report, Endorsement, My voucher) but **no dedicated page**.
- Personal screens that exist: `/my-jobs` (`ops_my_jobs`), `/vouchers` (mine mode). No
  inspector-scoped *reports* screen exists (`/documents` is office/SBU-scoped).
- Report status model is fixed — **do not invent statuses**:
  `IDEMS_STATUS` = DRAFT · SUBMITTED · VETTING · UNDER_REVIEW · APPROVED · ISSUED ·
  REJECTED (shown as "Sent back") · ARCHIVED (`lib/idems.php:63`). Issue = `status='ISSUED'
  AND finalized=1`.
- Job stages fixed: `JOB_STAGES` (`lib/idems.php:39`). Voucher states are string literals
  DRAFT → SUBMITTED → APPROVED → paid.

### Non-destructive guarantees
- **Add** a `/my-work` route + view. **Add** one header link in the existing "My work"
  nav area. **Add** a "See all in My Work →" link on the existing dashboard panel.
- **Do NOT** remove or alter the existing "Your pending tasks" panel, any nav sub-link,
  `/my-jobs`, `/vouchers`, `/documents`, or any status/permission.
- **No new permission** is introduced (hard rule). Every lane reuses the exact gating
  already in `ops_pending_tasks()`.

---

## 1. The proposed design (so edge cases are concrete)

`/my-work` renders `ops_pending_tasks()` output grouped into lanes:

| Lane | Tiles (existing buckets) | Who sees it |
|---|---|---|
| **Do now (approvals & gates)** | quotes to approve · contracts to register/endorse/approve · vouchers to approve · to vet · reports to approve · to issue · to release | whoever the tile already gates for |
| **My reports** | to fix & resubmit (returned) · *(NEW, see §6)* returned-to-draft | inspector (`inspector_id` set) |
| **My jobs** | reports to upload · jobs to close | inspector |
| **My money** | vouchers to submit (mine) | inspector |
| **Quality** | corrective actions (CAPA) · complaints | CAPA/complaints editors named on the item |

Each tile = a **card**: icon · title · **count badge** · one **primary button** linking to
the existing destination (e.g. `/documents?status=VETTING`, `/my-jobs?f=reports`,
`/contract-openings`, `/vouchers`, `/capa`, `/complaints`).

---

## 2. Entry-point / navigation edge cases

1. **"My work" nav area has no landing today.** Add `/my-work` as its top link. Existing
   sub-links (My job, My report, …) must remain and keep working.
2. **Who may open `/my-work`?** Any logged-in user. Each *lane/tile* is independently gated,
   so an office user simply sees fewer lanes. The route itself requires only a valid session.
3. **Dashboard duplication.** The dashboard keeps "Your pending tasks"; add only a small
   "See all in My Work →" link. Do not show two identical panels that read differently.
4. **Deep-link / bookmark to `/my-work` when logged out** → normal login redirect, then land
   on My Work (respect existing auth flow; do not 500).
5. **Universal Back button** must return to wherever the user came from (already app-wide).

---

## 3. Identity edge cases

1. **Office user with no `inspector_id`** → personal inspector lanes (fix & resubmit, reports
   to upload, jobs to close, vouchers to submit) must NOT appear and must NOT error.
   `ops_pending_tasks()` already guards these on `$insId` — verify none render when null.
2. **Inspector login not linked to an inspector record** → the inspector personal screens
   (`/my-jobs`, `/vouchers`) already `flash(inspector_link_msg())` and redirect. My Work must
   **not** hard-redirect the whole page; instead show a gentle inline notice in the "My
   reports/jobs" lanes ("Your login isn't linked to an inspector record yet — ask an admin")
   and still render any non-inspector lanes the user qualifies for.
3. **Coordinator/manager without an inspector row** → sees approval/vetting/scheduling lanes,
   never the personal inspector lanes. (`is_field_inspector()` returns false for them.)
4. **Master admin** → may qualify for many lanes; grouping + counts prevent overwhelm. No
   tile should appear more than once.
5. **Dual capability (e.g. SR_INSPECTOR who also vets/approves)** → may see both "prepare"
   and "review" lanes. Must never see an approve/issue action **for a report they prepared**
   (see §6.4).
6. **Session role/permission changes mid-session** (`ua()` re-resolves) → next page load
   reflects the new gating; no stale lane should act.

---

## 4. Per-tile / per-button edge cases (every button)

For **each** of the 15 existing tiles, the same matrix applies:

- **Count = 0** → tile is absent (never a "0" card, never a disabled button).
- **Count = 1 vs many** → button label pluralises correctly ("Vet 1 report →" /
  "Vet 3 reports →"). No "1 reports".
- **Stale count** (count changes between render and click) → clicking still lands on the
  live filtered list; the destination already renders its own (possibly empty) result. No
  error, no false "3 waiting" landing on an empty list beyond a harmless mismatch.
- **Destination correctness** (verified against the map):
  - to vet → `/documents?status=VETTING`
  - reports to approve → `/documents?mine=approve`
  - to issue → `/documents?status=APPROVED`
  - to release → `/documents`
  - to fix & resubmit → `/documents?status=REJECTED`
  - reports to upload → `/my-jobs?f=reports`
  - jobs to close → `/my-jobs?f=toclose`
  - vouchers to approve / to submit → `/vouchers`
  - quotes to approve → `/quotes?mine=approve`
  - contracts to register → `/quotes?mine=contract`
  - contracts to endorse/approve → `/contract-openings`
  - corrective actions → `/capa` · complaints → `/complaints`
- **Permission revoked** for a tile's action after landing → the destination screen enforces
  its own gate; My Work never grants access, it only links.
- **Scope** (office/SBU) → counts already scoped via `scope_clause('d.office_id','d.sbu')`;
  a manager sees only their scope. Verify My Work does not widen scope.
- **Accessibility** → each button has a full text label (not icon-only), a screen-reader
  label including the count, visible keyboard focus, and a ≥44px tap target on mobile.

Enumerated interactive controls on the page (nothing else is clickable):
1. One primary button per visible tile (link to existing route).
2. Optional lane collapse/expand toggle — **must degrade gracefully with JS off** (lanes
   open by default; collapse state remembered in `localStorage`, wrapped in try/catch).
3. Dashboard "See all in My Work →" link.
4. Nav "My work" header link → `/my-work`.
No create/edit/delete/approve control lives on this page — it is a launcher only.

---

## 5. Empty-state edge cases

1. **Whole page empty** → "You're all caught up." plus a couple of helpful, role-appropriate
   links (e.g. inspector: "My jobs", "Start a report"; coordinator: "Operations home").
2. **A lane empty** → hide the lane header entirely (no empty section headers).
3. **Everything gated out** (a role with no qualifying lane, e.g. a pure viewer) → the
   caught-up message, never a blank white page.

---

## 6. Reporting-workflow edge cases (the important ones)

1. **Two representations of "returned to inspector" exist** (from the map):
   - **Approver *reject*** → `report_docs.status='REJECTED'` (shown "Sent back"). ✅ already
     counted by the "to fix & resubmit" tile.
   - **Approver *sendback*** OR **vetting *RETURNED*** → `report_docs.status='DRAFT'` (reset)
     + `vet_status='RETURNED'` / a `report_approvals.status='SENTBACK'` audit row.
   > **GAP:** a report returned via *sendback* or *vetting-return* lands back at `DRAFT` and
   > currently looks identical to a brand-new draft. The inspector is not told "this was
   > returned to you." This directly undercuts the acceptance criterion "the inspector can
   > clearly see whether the report has been returned for correction."
   - **Proposed additive fix (within Module 39):** a distinct **"Returned for correction"**
     bucket that also catches `status='DRAFT'` reports for *me* whose latest history is a
     vetting-`RETURNED` or approval-`SENTBACK` — shown separately from ordinary new drafts,
     with the reviewer's reason and date. Read-only; no status change.
   - Edge: **do not double-count** a `REJECTED` report (already in "to fix & resubmit") in the
     new returned bucket.
   - Edge: a DRAFT that was returned and then edited but not resubmitted still counts as
     returned until resubmitted (latest history event decides).
   - Edge: a genuinely new DRAFT (never submitted) must **not** appear in the returned bucket.
2. **Segregation must be visible, not enforced-here.** My Work only lists; the existing
   downstream controls still enforce approver≠preparer and issuer≠approver.
3. **Awaiting-my-approval must exclude my own preparation.** Verify
   `idems_awaiting_my_approval_clause()` (`idems.php:5693`) does not surface a report the
   current user prepared. If it can, My Work must not present an "approve" button for it.
4. **Issuer = approver corner case.** A report in "to issue" (`APPROVED, finalized=0`) whose
   only eligible issuer is the person who approved it — issuing is blocked downstream. My
   Work should still list it (someone else must issue) but the button must not imply the
   current user can if segregation forbids it. Note for build: label stays neutral ("Open →")
   rather than "Issue →" when the current user is the approver.

---

## 7. Data-anomaly & integrity edge cases

1. **Orphans** (report with no job, job with no call, voucher with no inspector) → counts must
   not fatal; existing `$cnt` closure already wraps every query in try/catch.
2. **Soft-deleted** report_docs (`deleted=1`) excluded (already handled in the buckets).
3. **Huge counts** (e.g. 500 to vet) → show the number; never inline-list hundreds — the tile
   links out to the paginated register.
4. **Consistency** → the dashboard panel and `/my-work` must show identical numbers because
   both call the same function. Guard against computing tasks twice per request (cache within
   request if needed).
5. **Archived/closed FY** records must not resurface as pending.

---

## 8. Field / mobile edge cases (inspector is phone-first)

1. Inspector's My Work is single-column, big tap targets, **most-urgent lane first**
   (Returned for correction → reports to upload → jobs to close → vouchers).
2. Desk users (coordinator/manager/finance) get a denser multi-column layout on wide screens.
3. No hover-only affordance; everything works on touch.
4. Offline/PWA (Module 47 territory) — out of scope here, but the page must not assume a
   live socket; a plain reload refreshes counts.

---

## 9. Backward-compatibility checklist

- Existing "Your pending tasks" panel unchanged on dashboard & operations home.
- Existing "My work" nav sub-links unchanged; only a header link added.
- `/my-jobs`, `/vouchers`, `/documents`, `/quotes`, `/contract-openings`, `/capa`,
  `/complaints` unchanged.
- No status value, permission, table or route removed.

---

## 10. Tests to write (before marking done)

1. `/my-work` renders (HTTP 200) for: master, branch manager, coordinator, finance,
   field inspector (linked), field inspector (unlinked), pure viewer.
2. Unlinked inspector → gentle notice, no fatal, non-inspector lanes still render.
3. Office user (no `inspector_id`) → no personal inspector lanes, no error.
4. Returned-for-correction bucket surfaces BOTH a `REJECTED` report and a
   `DRAFT`+vetting-`RETURNED`/`SENTBACK` report for the owning inspector; a fresh never-
   submitted DRAFT does NOT appear; no double-count.
5. Counts on `/my-work` equal `ops_pending_tasks()` (single source).
6. No new permission constant introduced (grep the permission matrix).
7. Segregation: a report prepared by user X never shows an "approve"/"issue" action for X.
8. Empty state renders the caught-up message, not a blank page.
9. Every tile button label pluralises correctly and points to the mapped route.

---

## 11. Open decision for you (before I build)

**The §6.1 "Returned for correction" gap** — do you want me to include the new
returned-to-draft bucket in this module (recommended: it's the exact thing you asked for —
"the inspector must clearly see whether the report has been returned"), or keep Module 39
strictly a launcher over the existing buckets and handle the returned-draft surfacing later
in Module 07 (Vetting/Review/Approval)?

Default if you don't specify: **include it here** (additive, read-only, low risk).
