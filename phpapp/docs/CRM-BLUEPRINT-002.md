# CRM Blueprint 002 — UI/UX, measured against the running screens

**Status:** received, audited against 122 screens. Not yet built.
**Companion to:** `CRM-BLUEPRINT-001.md` (data model and features).

---

## 1 · Audit — what the interface already does

| Blueprint asks for | Today |
|---|---|
| Left sidebar | **Have** — accordion, one section open, remembers your last |
| Breadcrumbs | **Have** — on every register and detail screen |
| Fast navigation / type to find | **Have** — filters the menu, works offline, Enter opens top match |
| Collapsible rail | **Have** — 60px icon rail, remembered per browser |
| Role-based dashboards | **Have** — sections reordered per role, plus the new "Waiting on somebody" band |
| Status badges, colour-coded priorities | **Have** — one pill component used throughout |
| Responsive / mobile-first | **Have** — PWA, camera, GPS, offline draft queue |
| Auto-save | **Have** — offline queue on field forms |
| Export | **Have** — CSV on every register |
| Consistent typography and spacing | **Have** — one stylesheet, shared components |

**Missing, and each is a real build:**

| Gap | Size | Note |
|---|---|---|
| **Global record search** | Medium | The search box today filters *menu items*, not records. 101 routes, no cross-entity search |
| **Customer 360 single page** | Large | Blueprint 001 P3. The data all exists — this is assembly |
| **Notification centre** | Medium | Today: flash messages and e-mail. Nothing persists, nothing is a list |
| **Unified calendar** | Large | No `calendar_events` table at all |
| **Recently viewed** | Small | |
| **Favourites / pinned records** | Small | I checked — the earlier "have" was a false positive |
| **Keyboard shortcuts** | Small | |
| **Table: sort, group, column choice, bulk actions, inline edit** | Medium ×1 | **42 list screens** — build the component once, adopt everywhere |
| **Real light/dark mode** | Medium | There is a "Midnight" colour preset, but no `prefers-color-scheme` and no toggle |

**Accessibility is the weakest area, and the blueprint asks for it explicitly:**
- 9 `aria-` attributes in the navigation (added last week)
- **1 across all 122 operational screens**
- 4 `:focus` rules in the entire stylesheet

That is not "keyboard-friendly and screen-reader ready". It is a shell.

---

## 2 · Where I disagree

1. **"Understandable within 5 seconds" cannot hold on every screen.** A
   management review under §8.9.2 has fifteen required inputs. A confidentiality
   breach needs eight facts before it can close. The honest rule is *five
   seconds to understand what the screen is for, and what to do next* — not
   five seconds to understand everything on it. I will design to that.

2. **Infinite scrolling is wrong here — pick pagination.** These are registers
   somebody audits and cites by row. "Scroll until you find it" is not a
   position you can quote to an assessor, and it breaks Ctrl+F.

3. **"Voice input (future-ready)" and "offline (future-ready)" should come off
   the list.** Offline is already built. "Future-ready" for voice means nothing
   is built and nothing is designed for — it is a line that survives review
   without ever becoming work. Either schedule it or drop it.

4. **Dark mode has a hidden cost.** The generated PDFs, the letterhead, the
   signature stamping and the report templates all assume light. Dark mode is
   a day for the screens and a decision about what happens when somebody prints
   from a dark screen. Worth doing — not free.

5. **"Support tickets" and "Renewals" appear in your Customer 360 list but do
   not exist as objects.** Same finding as blueprint 001: the screen cannot show
   what the database does not hold. Either they get built or they come off the
   360 spec.

---

## 3 · Build order

Sequenced by *value per day*, not by the blueprint's order.

**U1 · The shared table component** — sort, filter, group, choose columns, bulk
select, inline edit, pagination. Built once, adopted across 42 registers.
*Highest leverage in the whole blueprint.*

**U2 · Global search** — one box, one index over customers, contacts, inquiries,
quotations, calls, deputations, reports, invoices, nonconformities, complaints.
Partial match, recent searches, keyboard-first (`/` to focus, arrows, Enter).

**U3 · Customer 360** — the screen that makes it feel like a CRM. Depends on the
activity spine from blueprint 001 P1.

**U4 · Notification centre** — persist what is currently a flash message. Feeds
from the registers already emitting: follow-ups, approvals, overdue
nonconformities, rejected reports, expiring documents.

**U5 · Accessibility pass** — labels, focus rings, landmarks, skip link,
keyboard traps. Done as one sweep across the shared components, not screen by
screen.

**U6 · Recently viewed · favourites · keyboard shortcuts** — small, and they
land better once U1 and U2 exist.

**U7 · Calendar** — needs `calendar_events` and `tasks` from blueprint 001 P4.

**U8 · Light/dark themes** — after the print decision.

---

## 4 · Rules I will hold myself to

Taken from your blueprint, made testable:

- Every list screen uses **the same** table component — no bespoke tables.
- Every destructive action confirms, and says what will be lost.
- Every error message says what to do next, never a stack trace.
- Every new screen ships with its `aria-` labels and a visible focus ring, or it
  does not ship.
- No new bespoke CSS where a shared component exists.
- Pagination, never infinite scroll, on anything auditable.

---

## 5 · What I need from you

1. **Support tickets and renewals — real objects, or off the 360 spec?**
2. **Dark mode — do you want it for screens only, or must printing follow?**
3. **Which three screens do your people use most?** I will hold those to the
   30-second rule first, and let the rest follow the components.
