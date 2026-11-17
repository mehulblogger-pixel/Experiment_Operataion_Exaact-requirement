# Next changes and the 16 open points — for your OK before any coding (17 Nov 2026)

You wrote:
- *"If one approver rejects the report the next approver will not show that report approve for pending."*
- *"Director shall be required to approve if the amount is more than certain as marked in level setting. So there will be two conditions: the level of the approvers and amount wherein which approver will approve it."*
- *"Also what about you mention to fix it later."*

This explains exactly what I will change, with examples, and lists the 16 open points with my recommendation for each. **Nothing has been changed yet.**

**How to answer:** *"Change 1 OK, Change 2 OK, 16 points as recommended"*, or name any you want differently.

---

## Change 1 — A report rejected or sent back disappears from the next approvers (DEF-REP-016)

**Today:** a report needs two approvals, Neha (level 1) then Sunil (level 2).
- If Neha **rejects** it or **sends it back**, the report goes back to the inspector.
- But Sunil's step stays open. The report still shows in Sunil's pending list, and he can still approve it. If he does, it becomes "Approved" without the correction.

**After:**
1. When any approver **rejects** or **sends back** a report, every approval step still waiting after them is **closed at once**.
   - The report disappears from Sunil's pending list, his dashboard and his reminders.
   - Sunil sees a note in the report's History: "Rejected at level 1 by Neha: <reason>".
2. A report can be approved **only while it is under review**. Anything else is refused with a plain message, even from an old browser tab.
3. When the inspector corrects and **resubmits**, the approval chain starts again from level 1, as it does today.

**Not changed:** who approves, the levels, and the rule that the approver and the issuer must be different people. No new status.

---

## Change 2 — Vouchers: the approver depends on BOTH the level and the amount

**Today (from the batch just sent):** the chain uses the reporting line (N+1 … N+4) and Finance, each with "needed above ₹". **A Director is reached only if they happen to be in that person's "Reports to" line.**

**After:** on **Admin → Approvals → Expense vouchers** you can add levels by **role**, not only by reporting line. The roles include Business Director, Business Unit Head, Branch Manager and any other role, including your own custom roles. Each level still has its own **"needed above ₹"**.

**Example setup (amounts are only an example; you set your own):**

| Order | Level | Needed when the voucher total is |
| --- | --- | --- |
| 1 | N+1 (Reports to) | always |
| 2 | N+2 | above ₹5,000 |
| 3 | **Business Director** | **above ₹20,000** |
| 4 | Office Finance | always |

**What happens:**
- **₹3,000** → N+1 → Finance.
- **₹8,000** → N+1 → N+2 → Finance.
- **₹25,000** → N+1 → N+2 → **Director** → Finance.

**The rules that make it work:**
- **Which Director:** any Business Director whose reach covers the voucher's office. With your current setup that is every Business Director; the first to approve signs that level.
- **No double signing:** if the person who must sign a role level has **already signed an earlier level** of the same voucher (the Director is also that person's N+2, for example), that level counts as signed. They are not asked twice.
- **Order:** levels run in the order you set. **Finance always stays last.**
- **Away:** a Director who is away can be covered through the existing "Who covers for a voucher approver" page.
- **No holder:** if nobody holds the role, the voucher waits for an admin with a clear warning, the same as for a missing "Reports to" today.

**Not changed:** amounts are never preset. Send back, Paid by Finance, and Finance last all stay as they are.

---

## The 16 open points from the last report — my recommendation for each

| # | Point | My recommendation |
| --- | --- | --- |
| 1 | A Director who is someone's N+2 signs that level | **Yes** (and with Change 2 they never sign twice) |
| 2 | Asst. Manager and vouchers | **Sees the whole branch's vouchers (view)**; the permission list already gives them voucher rights |
| 3 | Business Unit Head reopening vouchers | **No reopen, view only**, like the Director (the permission list says View) |
| 4 | Revision of an issued report | **Also the issuing office's Coordinator**, when it differs from the executing office |
| 5 | Inspectors reading other inspectors' reports | **Own reports only**, for reading too |
| 6 | Contracting office submitting a report | **No.** Only the issuing or executing office submits; the contracting office may edit before submission |
| 7 | Another office opening a candidate in the shared pool | **A reduced view:** CV, skills and the badge, **without offer or salary** |
| 8 | A second record for the same person (same mobile) while in process elsewhere | **Blocked**, with the name of the office working with them |
| 9 | A candidate whose process ended (rejected, withdrawn) | **Appears again in every office's "Best matches"** |
| 10 | Vendor portal "New link" | **Same fix as clients:** e-mailed only, never shown to staff |
| 11 | Client requests ("What clients have asked for") | **Limited to the Coordinator's own office's clients** |
| 12 | Remaining client-dashboard counts | **Follow the client person's site limit** |
| 13 | "Put forward one of your own team" on a posting | **Only your own office's people** |
| 14 | The staff "Team passports" screen | **Remove it from the menu** (there is no team passport in your Marketplace). The data stays; nothing else changes |
| 15 | Branch / Operation Manager assigning a nonconformity's owner and opening a corrective action | **Keep allowed.** Organising the work is not deciding it; containment, disposition and closing stay with the Quality / Technical Head |
| 16 | DEF-REP-016 | **Fix:** that is Change 1 above, as you said |

**After your OK:**
1. I build Change 1, Change 2 and the 16 points as one batch, with tests.
2. I run the full test suites and the robot before/after.
3. I send one opstest zip, with the check list and the rollback steps.

---

## Your answers (17 Nov 2026) and how I will build them

**A. Vouchers (point 2), in your words:** *"Every individual can create, edit, submit their own vouchers. Every voucher is to be approved by the N+1 and other levels as set with respect to amount and reporting. Assistant Manager can approve vouchers of their own reportee. They cannot see anyone else. Very configurable in admin panel."*

**What I will build:**
- **Everyone** (inspector, Coordinator, Asst. Manager, Branch Manager, anyone) creates, edits and submits **their own** vouchers.
- **Each person sees only:**
  - their own vouchers;
  - the vouchers they must approve, or have approved, in the chain.

  **Nobody sees a whole branch's vouchers** just because of their role. The exceptions are Master Admin and Admin (everything), and Finance for the vouchers that reach its Finance step.
- **The chain is set on Admin → Approvals → Expense vouchers.** Its levels are:
  - reporting line N+1 … N+4;
  - by role (Director, BU Head, any role);
  - Office Finance and Central Finance.

  Each level has its own **"needed above ₹"** and its place in the order (Change 2).
- *Example:* Asst. Manager Ravi has two inspectors reporting to him.
  - He sees his own vouchers and theirs (as N+1).
  - He does not see other inspectors' vouchers, even in the same branch.

**B. Stage inspection reports (point 3): new feature.** In your words: *"the next inspector must have a direct editable copy of the report from the previous inspection, to keep the numbering under that contract; or the coordinator can select the report for the inspector, editable, with the next report number."*

**What I will build:** a button **"Start next stage report"** on an approved or issued report.
- **Who may press it:**
  - the inspector allocated to the next stage, meaning a job on the same work order;
  - or the Coordinator, who picks which inspector it is for.
- **What it creates:**
  - a **new report**, **editable** by that inspector;
  - with the **next report number** in the same numbering series;
  - **pre-filled** from the previous report: client, PO, item, specs, form fields and previous findings;
  - **left empty:** result, inspection date, photos, signatures and approvals;
  - linked to it ("continues from …/IR/000001").
- **The previous report** is never changed.
- *Example:*
  1. Stage 1: Inspector Amit issues `MGH/AHM/2026/TBREF/IR/000001`.
  2. Stage 2 is allocated to Rahul. On Amit's report he presses "Start next stage report" and gets `…/IR/000002`, already filled, which he completes and submits.

**C. Candidate pool (point 4), in your words:** *"Before any process, everything is shown to every office. Once the process starts by any office (interview), a badge shows the candidate as locked, with no access to any other office."*

**What I will build:**
- **Before any process:** every office sees the **full** candidate.
- **From the moment an office schedules the first interview:** the candidate shows **"Locked — in recruitment with Ahmedabad"** in every other office.
  - They **cannot open** the candidate or act on them; they see only the name and the badge.
  - This replaces the "reduced view" idea (point 7).
- **When that process ends** (rejected, withdrawn, did not join), the lock is lifted.

**D. Passports (point 5):** internal team members have **no passport**. Only Marketplace professionals (freelancers) do. I **remove the staff "Team passports" screen** from the menu; the Marketplace professional's own passport is unchanged.

**The other points (1, 3, 5, 6, 8–13, 15, 16):** I will build them **as recommended** in the table above, unless you say otherwise.

### Three small questions (my recommendation in bold)

| # | Question | Recommendation |
| --- | --- | --- |
| Q1 | Report numbers: the next stage report takes the next number in the existing series (per client and report type). Do you also want the **contract number inside the report number**, e.g. `…/C-0012/IR/0003`? | **Not now.** Keep the current series. A contract token can be added to the number pattern later if your clients ask for it |
| Q2 | Candidate lock: start at the **first interview scheduled**, or already when the office **links the candidate to its requisition**? | **First interview scheduled**, as you wrote |
| Q3 | Vouchers: may a **Branch Manager** still see all vouchers of their branch (to monitor), or strictly own + chain like everyone else? | **Strictly own + chain**, as you wrote. Admin and Finance cover the rest |

**One-line answer:** *"Go ahead, Q1–Q3 as recommended."*
