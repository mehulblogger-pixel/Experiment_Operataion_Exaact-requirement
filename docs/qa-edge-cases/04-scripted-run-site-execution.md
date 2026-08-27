# Scripted Test Run — Site execution (attendance, geofence, evidence) with exact figures

**Scope.** The field stage of the E2E flow (`docs/qa-edge-cases/00-end-to-end-flow.md`): the inspector marks
their own attendance (office/site), the geofence checks where "on site" really was, evidence is captured with
EXIF + a hash, and the §35 review catches the marks that look wrong — **advisory** (attendance always counts).
Distances, anomaly messages and the evidence-reuse count below were **produced by the live engine**
(`geo_distance_m`, `attend_anomaly`, `evidence_reuse_count`); ⚙ marks a verified figure, 📘 a cited rule.

---

## 1. Seed data

| Object | Field | Value |
|---|---|---|
| **Job** `JB-SITE` | executing office | Ahmedabad |
| | site geofence | lat **23.0225**, lon **72.5714**, radius **250 m** |
| Inspector | competent, assigned to the job | — |
| Today | inspection day | (run date) |

Two GPS points used below: **on-site** ≈ (23.0230, 72.5716) and **off-site** = (23.0405, 72.5714).

---

## 2. Step-by-step, with exact figures

### Step A — Self-mark OFFICE ⚙(rule)

Inspector marks **OFFICE** for today.
**Expected:** recorded (`attendance.status=OFFICE`); **counts immediately** — advisory (§35). No location needed
for OFFICE unless configured. **Reflected in:** `attendance`; the availability board; the timesheet/cost run.

### Step B — Self-mark SITE, actually on site ⚙

Mark **SITE** with GPS ≈ 60 m from the job geofence.

| Check | **Expected** | Verified |
|---|---|---|
| `geo_distance_m(site, gps)` | **59.0 m** — inside the 250 m radius | ⚙ |
| `attend_anomaly(row)` | **''** (empty — clean, not flagged) | ⚙ |
| Verdict | recorded, counts, **not** in the review queue | ⚙ |

✅ **PASS if** an on-site SITE mark is **not** flagged.

### Step C — Self-mark SITE, but off site ⚙ (the flag)

Mark **SITE** with GPS at (23.0405, 72.5714) — 0.018° north.

| Check | **Expected** | Verified |
|---|---|---|
| `geo_distance_m(site, gps)` | **2,002.0 m ( = 2.00 km)** | ⚙ |
| `attend_anomaly(row)` | **"Marked on site, but the GPS is 2 km from the job site."** | ⚙ |
| Verdict | flagged for review; **still counts** (advisory) | ⚙ |

✅ **PASS if** the mark is flagged with the **exact distance** and yet still counts. **Reflected in:**
`/attendance-review`; `attention_summary` "Attendance to review".

### Step D — Checked in, never checked out ⚙

A past day with a check-in and no check-out.
**Expected:** `attend_anomaly` = **"Checked in but never checked out."** → in the review queue. ⚙

### Step E — Back-dated mark ⚙

A day marked 10 days after the fact.
**Expected:** `attend_anomaly` = **"Marked 10 days after the date."** (threshold > 2 days). ⚙

### Step F — The review workflow (§35)

| Action | Expected | Reflected in |
|---|---|---|
| Coordinator opens the queue | Only the flagged entries (C/D/E), not the clean ones (A/B) | `/attendance-review` |
| **Send back** | `review_status = RETURNED` + note; leaves the coordinator queue | inspector's "returned to me" list |
| Escalate | `review_status = ESCALATED` → the reporting manager | manager review |
| Inspector **re-marks** | the flag **resets** (`review_status = ''`); re-checked afresh | queue count drops |
| Clear (accept despite flag) | `review_status = CLEARED`; the mark stands | — |

✅ **PASS if** only flagged entries surface, send-back moves it to the inspector, and a corrected re-mark clears
the flag. **The attendance never stops counting** — the whole layer is oversight, not a gate.

### Step G — Geofence distances (the numbers behind the flag) ⚙

| From site (23.0225, 72.5714) to… | **Distance** | Inside 250 m? |
|---|---|---|
| (23.0230, 72.5716) — a few doors away | **59.0 m** | ✅ yes (clean) |
| (23.0405, 72.5714) — ~2 km north | **2,002.0 m** | ❌ no (flagged) |

*(Haversine, `geo_distance_m`, `lib/trust.php`. Radius default 250 m; a site may override it.)*

### Step H — Evidence capture 📘

Upload a site photo.
**Expected:** EXIF **time + GPS** extracted, a **SHA1 hash** stored, and the file appended to the tamper-evident
trust chain. The report's evidence readiness (Module 44) then shows: *N photos (M located on site by EXIF),
arrival + departure recorded, evidence chain intact* — advisory on issue (§10). **Reflected in:** `report_files`;
the report's Evidence panel; the issue-readiness "Evidence & on-site" row.

### Step I — Reused evidence detected (§68) ⚙

The **same** photo hash appears on reports across **two different jobs**.

| Check | **Expected** | Verified |
|---|---|---|
| `evidence_reuse_count()` | **+1** reuse group | ⚙ |
| `evidence_reuse_groups()` top | sha1 shared, **jobs = 2** | ⚙ |

✅ **PASS if** one photo used on two jobs raises exactly **one** reuse group (`jobs = 2`). A unique photo adds
nothing. **Reflected in:** the evidence-reuse surface (Module 44). Detection groups by **distinct job**, so the
same photo appearing twice on the *same* job does not count.

### Step J — Hold / witness point 📘

Reach a hold or witness point during the visit.
**Expected:** it is a first-class intervention point (Module 21); closing the job **warns** that a hold isn't
cleared by closing. **Reflected in:** the job's hold/witness panel; the job-close warning.

---

## 3. Edge cases

| # | Change | Expected | Sev |
|---|---|---|---|
| E1 | Geofence off (`geofence_on=0`) | Off-site GPS still *captured*; the flag still computes from distance (oversight, not a hard gate) | 🟡 |
| E2 | SITE marked with **no** GPS | Location is required to mark IN as SITE — refused at capture until GPS is allowed | 🟡 |
| E3 | Exactly on the radius (250 m) | 250 m = boundary; > 250 m flags, ≤ 250 m clean | ⚪ |
| E4 | Mark exactly 2 days late | Not flagged (threshold is **> 2** days); 3 days → flagged | ⚪ |
| E5 | Reviewer clears a genuine off-site | `CLEARED`; it stays counted and out of the queue; the decision is audited | 🟡 |
| E6 | Timesheet/cost run reads attendance | Reads it **regardless** of review state — advisory never blocks payroll | 🔴 |
| E7 | Same photo twice on the **same** job | **Not** a reuse group (grouping is by distinct job) | 🟡 |
| E8 | Tampered evidence file | Trust chain verify fails → "evidence chain BROKEN" (advisory warn on issue) | 🟠 |
| E9 | Cross-office fetch of a check-in photo by id | Denied (§51 single-record scope) | 🟠 |

---

## 4. Pass/fail summary

| Assertion | Expected | Verified | Pass? |
|---|---|---|---|
| On-site distance | **59.0 m** (inside 250 m) → clean | ⚙ | ☐ |
| Off-site distance | **2,002.0 m** (2.00 km) → flagged | ⚙ | ☐ |
| Off-site anomaly text | "Marked on site, but the GPS is **2 km** from the job site." | ⚙ | ☐ |
| Open check-out anomaly | "Checked in but never checked out." | ⚙ | ☐ |
| Back-dated anomaly | "Marked **10 days** after the date." | ⚙ | ☐ |
| Clean on-site mark | anomaly = '' (not flagged) | ⚙ | ☐ |
| Review is advisory | attendance counts regardless of review state | 📘 | ☐ |
| Send back → re-mark resets flag | `RETURNED` → re-mark → `''` | 📘 (see §35 test) | ☐ |
| Evidence reuse | same photo on 2 jobs → **1 group, jobs=2** | ⚙ | ☐ |
| Same photo on same job | **0** reuse groups | 📘 | ☐ |

*⚙ verified against the live engine on 2026-08-27 (Ahmedabad site 23.0225/72.5714, radius 250 m); 📘 cited rule.
Set so far: 00 flow, 01 money, 02 report/issue, 03 commercial, 04 site execution.*
