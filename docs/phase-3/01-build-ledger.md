# Phase 3 Build Ledger

The ten items Phase 2 deliberately held back, built here — each by **composing the foundations Phase 2
laid** rather than starting a new subsystem. For each: what it affects · why it was required · what it
adds · how it eases the work (and who it helps).

- **3,740 tests passing, 0 failed** · **10 builds across 4 waves** · non-destructive, reuse over rebuild.
- **No new permission and no sign-off** was needed for any Phase-3 item.
- The through-line is reuse: every item reads through an engine Phase 2 already built (tasks, the money
  stream, the activity spine, the person matcher, visibility) instead of forking a new one.
- Suite grew **3,584 → 3,740** across Phase 3, zero failures throughout. Full trail: `docs/phase-3/00-program.md`.

| Wave | Count | In short |
|---|---|---|
| A — Foundations | 2 | the two things the surfaces would need to read |
| B — Surfaces | 2 | one place to act (personal), one to oversee (management) |
| C — Platform | 1 | reusable integration plumbing |
| D — Depth, previews & consistency | 5 | larger UX/lifecycle builds, each a composition |

---

## Wave A — Foundations

### §26 · Canonical task  `test_p3_tasks`
- **Affects:** the "waiting on me" world — My Work and everywhere work is tracked.
- **Why required:** the existing lists only *derive counts* ("3 quotes to approve"); there was no way to write down, assign, and tick off an ad-hoc follow-up.
- **Adds:** a persisted task — create / assign / due / done, optionally linked to a record; assigning to others is a coordinator act, within branch scope.
- **Eases:** *Everyone* — a real, trackable to-do that sits alongside the system's own reminders, unified into one My Work badge. **Built on:** `ops_pending_tasks`, scope (§51), activity spine (§17).

### §27 · Financial-event stream  `test_p3_finevent`
- **Affects:** every screen that asks "what happened with the money" — client-360, and the Command Centre.
- **Why required:** money lived as records in five tables (quotes, invoices, receipts, credit notes); each screen re-shaped them its own way.
- **Adds:** one uniform, time-ordered stream (quote accepted → invoice → receipt → credit) + a rollup (committed · billed · received · outstanding).
- **Eases:** *Finance* — one money feed everywhere; because it reads the books ledger, it can never drift from it. **Built on:** the books ledger, `job_profit` (§28), revenue reconciliation (§29).

---

## Wave B — Surfaces

### §19 · Action Centre  `test_p3_action_centre`
- **Affects:** the My Work landing page.
- **Why required:** count cards can't say what's most urgent, and can't show an individual written-down task at all.
- **Adds:** one priority-ordered "do this next" band merging the derived approvals with your §26 tasks — overdue first, then due-today, then warnings.
- **Eases:** *Everyone* — an overdue follow-up out-ranks a routine approval; delivered on My Work, not a competing screen. **Built on:** `ops_pending_tasks`, tasks (§26).

### §20 · Command Centre  `test_p3_command_centre`
- **Affects:** the management view of the business.
- **Why required:** attention, money and platform health each lived on their own screen — no single "state of the business".
- **Adds:** one board with three *separate* bands — needs-attention, money (the §27 rollup), and platform health.
- **Eases:** *Management* — the whole picture at a glance, with business KPIs and technical health kept deliberately apart, never blended into one misleading score. **Built on:** `attention_summary`, `system_status`, finevent (§27).

---

## Wave C — Platform

### §50 · Generic integration queue  `test_p3_webhookq`
- **Affects:** how the app sends events to outside systems.
- **Why required:** each integration (Books, Ads) hand-rolled its own outbox table *and* its own retry loop; every new one repeated the plumbing.
- **Adds:** one reusable queue with dedupe, bounded retries and exponential backoff, and an injectable delivery step — off by default until an install wires a real sender.
- **Eases:** *Engineering* — a new integration writes a small delivery callback, not a table and a loop; the bespoke outboxes keep working and report into one health view. **Built on:** `integration_health` (§46), the existing outboxes (retained).

---

## Wave D — Depth, previews & consistency

### §16 · Vendor-360 depth  `test_p3_vendor360`
- **Affects:** the vendor detail screen.
- **Why required:** it was rich on quality (assessments, audits, scorecard) but lacked the vendor's people and its full history — the things client-360 has.
- **Adds:** a contacts panel where each contact is recognised as one person across the system, and the full activity timeline.
- **Eases:** *Procurement & account managers* — vendors at parity with clients; a vendor contact who's also a candidate is linked, not hunted for. **Built on:** party (§23/24), activity spine (§17).

### §8 · Report-template persona preview  `test_p3_template_preview`
- **Affects:** the report form builder.
- **Why required:** a flat field list hid *who sees what* — an author couldn't tell a client-facing field from an internal one at a glance.
- **Adds:** a preview splitting each field into recipient-facing vs internal-only, flagging conditional and scored fields.
- **Eases:** *Template authors* — catch a field you think clients see that's actually hidden (or scoring machinery leaking out) before the template is used. **Built on:** the visibility vocabulary (§72), the `hidden`/`cond_field`/`weight` columns.

### §49 · Uniform Entity-360  `test_p3_entity360`
- **Affects:** any entity — a job, an NCR, a corrective action, a candidate.
- **Why required:** only clients and vendors had a "whole story" view; everything else had a bare detail page.
- **Adds:** one 360 route that assembles a consistent panel set — tasks, history, and the kind-appropriate quality or person panel — for any registered entity.
- **Eases:** *Everyone* — the same "whole story" shape wherever you are, composed from the engines already built rather than rewriting the bespoke 360s. **Built on:** tasks (§26), activity (§17), quality case (§39), party (§23/24).

### §34 · Dashboard "at a glance" strip  `test_p3_dashboard_glance`
- **Affects:** the area landing pages (Sales, Quality, Money, …).
- **Why required:** they were navigation tiles with no live state above them.
- **Adds:** a role-aware strip — your next actions for everyone, and, for a manager, a compact pulse (attention, money outstanding, platform health).
- **Eases:** *Everyone* sees their state on landing; a non-manager never sees the management pulse; permission-aware, nothing new computed. **Built on:** `action_centre` (§19), `attention_summary`, finevent (§27), `system_status`.

### §35 · Attendance review  `test_p3_attend_review`
- **Affects:** self-marked attendance (office or site).
- **Why required:** an inspector marks their own attendance, but there was no oversight when a mark was wrong or goofed.
- **Adds:** only *anomalous* marks surface — off-site GPS, a missing check-out, a back-dated mark; a coordinator (or manager on escalation) sends them back to re-mark.
- **Eases:** *Coordinators & managers* catch and return the few suspect entries; the inspector keeps owning their record. Advisory — attendance still counts, nothing downstream stalls. **Built on:** the existing attendance capture, the geofence distance helper, `attention_summary`.

---

*Exaact TPIA OS · Phase 3 — the deferred builds. All changes non-destructive; every item a composition of
Phase-2 foundations. Full detail in `docs/phase-3/00-program.md`.*
