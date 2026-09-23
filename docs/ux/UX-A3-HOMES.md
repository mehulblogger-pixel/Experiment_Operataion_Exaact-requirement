# UX-A3 — Home, dashboard and the area homes

**Phase A, step 3. Audit only — no code changed.**

Scope: the landing experience and all eleven home screens, measured against the
5-second rule (Part 4), role-based home (Part 5) and actionable queues (Part 32).

Every figure below was measured in Chromium at 1280×900 signed in as a Master
Admin, not read off the source.

---

## Measured: what each home actually presents

| Screen | Headings | Cards | Links | Height | Kind |
|---|---:|---:|---:|---:|---|
| **Recruitment Command Centre** | 20 | 14 | 43 | **2,643px** | dashboard |
| **Dashboard** (`/`) | 10 | 30 | 33 | **1,756px** | dashboard |
| **Operations** | 6 | 5 | 41 | 1,510px | dashboard |
| Owner home | 6 | 2 | 5 | 902px | dashboard |
| Marketplace | 1 | 0 | 19 | 763px | link menu |
| Sales | 1 | 0 | 14 | 538px | link menu |
| Quality & Accreditation | 1 | 0 | 28 | 505px | link menu |
| Money | 1 | 0 | 16 | 447px | link menu |
| Directory | 1 | 0 | 13 | 447px | link menu |
| Insights | 1 | 0 | 9 | 407px | link menu |
| Admin | 1 | 0 | 31 | 370px | link menu |

Two different kinds of screen are wearing the same name.

---

## F-A3-1 · 103 area-home tiles, and not one tells you how many things are waiting
**Class: structural · Severity: HIGH · Confidence: measured by executing the code**

This is the most valuable finding of the step, because the work it implies is
*wiring*, not building.

The area-home tile API already accepts a count and a tone. Its own comment says so:

```php
// Append a tile to the current section. $count -> a badge; $tone one of
// 'red' | 'amber' | 'green' | '' (neutral).
$t = function ($show, $icon, $label, $route, $desc = '', $count = null, $tone = '', $ext = false)
```

The view renders it, and even sums a section's counts for a section-level badge:

```php
if (!empty($tl['count'])) echo ' <span class="op-badge ' . e($tl['tone'] ?: 'info') . '">' . (int)$tl['count'] . '</span>';
$secCount = array_sum(array_map(fn($t) => (int)($t['count'] ?? 0), $s['tiles']));
```

Asked directly, by executing `ops_area_def()` for all eight generic areas:

| | |
|---|---|
| Tiles across the eight area homes | **103** |
| …carrying a count badge | **0** |
| …carrying a tone | 22 |

So the badge is **built, rendered, styled, and used zero times** — while 22 tiles
already set a colour, proving the argument list is reached in practice.

**Why it causes confusion:** every area home answers *"what can I do here?"* and
none answers *"what needs my attention?"*. A finance user opening **Money** sees
16 links and no indication that four jobs are ready to bill. A quality manager
opening **Quality** sees 28 links and cannot tell whether anything is overdue.
That is the brief's *"Which of these 8 screens should I open?"* in its purest
form — and Part 32's instruction to use "counts and actionable queues" applies
to far more than Operations.

**Recommended treatment (C9):** populate the count argument from the KPI and
queue functions that already exist. No new metric logic, no new component, no
schema change — the count flows into a slot that is already waiting for it.

**Risk:** low, but not zero — each count is a query on a page that currently runs
none, so counts must be cheap (the same guarded, single-COUNT pattern the
dashboard already uses) and must fail quiet rather than take the page down.

---

## F-A3-2 · Two home philosophies coexist
**Class: structural · Severity: MEDIUM**

Three homes are **dashboards** (cards, KPIs, attention queues): Dashboard,
Recruitment, Operations. Eight are **flat link menus** with zero cards.

Moving from Operations (5 cards, attention queues) to Money (0 cards, 16 links)
is moving between two different products. The brief's central complaint —
*"Which application am I in?"* — is caused here as much as anywhere.

**Note in defence of the link menus:** they are fast, calm and predictable, which
is exactly Part 47. The problem is not that they are plain; it is that they are a
*different kind of screen* from their siblings, and that they omit attention
entirely. Fixing F-A3-1 largely fixes this without making them heavier.

---

## F-A3-3 · The Recruitment Command Centre is the least usable screen in the product
**Class: structural · Severity: HIGH**

**2,643px tall — roughly three full screens — with 20 headings, 14 cards and 43
links.** It is the largest, densest landing screen by every measure taken.

Combined with F-A2-6 (20 distinct destinations, configuration at the same visual
weight as daily work), this screen fails the 5-second rule on all five questions
at once. A recruiter cannot tell from it what needs doing today.

**Recommended treatment (C8):** the pipeline model from Part 30 — needs
attention → open positions → candidates → hiring → joined — with configuration
(pipelines, document templates, compensation setup, org import) moved to a
"Set up" grouping and to Admin. **Nothing is deleted**; the destinations remain
reachable.

---

## F-A3-4 · The Dashboard is 30 cards and two screens tall for a senior user
**Class: structural · Severity: MEDIUM**

1,756px, 30 cards, 10 headings for a Master Admin. The sections are sensible —
*Waiting on somebody*, *Needs attention*, *Money desk*, *Job status*, *Pending
scheduling*, *Reports awaiting your approval* — but they are presented at even
weight, so the eye has nowhere to land first.

This is a **prioritisation** problem, not a content one. The brief's model is
"Today" first, then "Needs attention", then "Quick actions"; the material is all
present and correctly permission-gated, but not ranked.

**One concrete gap:** across all four dashboard-style homes there is exactly
**1 create-link on the Dashboard, 2 on Operations, 2 on Recruitment and 0 on the
eight area homes.** Part 4's "Quick actions" band effectively does not exist.

---

## What is already right — and must not be "improved"

The brief assumes role-based home and needs-attention are missing. They are not,
and rebuilding them would be a step backwards.

- **The dashboard is genuinely role-aware** — 21 role/permission conditionals. An
  inspector gets their own cockpit (open jobs, reports pending, overdue,
  voucher); a reviewer's approval queue appears **only when something is actually
  waiting**, with the count in the label.
- **It greets by name, role, office and date**, exactly as Part 4 describes:
  *"Good morning, admin 👋 · Master Admin · Wednesday, 23 Sep 2026"*.
- **"Needs attention" and "Waiting on somebody" already exist** as named sections.
- **One KPI engine, not several.** The inspector cockpit reuses
  `connect_kpi_board()` — the same board that powers the client and freelancer
  dashboards. Its own comment states the rule: *"One engine, one card design, no
  duplicate metric code."* Part 5's warning against duplicate KPI logic is
  already observed, and C4 must continue to observe it.
- **A configurable per-role launchpad already exists** (`workspace_launchpad_html`),
  permission-safe and silent when unconfigured.
- **"Today" is already a concept** on the dashboard (7 references, including
  availability-today and today's scheduling).
- **The area homes already group into sections with tabs** when an area is large,
  so the structure to hang counts on is in place.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A3-1 | 103 area tiles, 0 counts — badge built and unused | structural | HIGH |
| F-A3-2 | Dashboards and flat link menus both called "home" | structural | MEDIUM |
| F-A3-3 | Recruitment home: 2,643px, 20 headings, 43 links | structural | HIGH |
| F-A3-4 | Dashboard unranked at 30 cards; no quick-actions band | structural | MEDIUM |

**No product decision required.** All four are presentation and information
ordering. F-A3-1 in particular needs no new engine — only that existing counts be
passed to an argument that already exists, renders, and is already reached by 22
tiles setting a tone.
