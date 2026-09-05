# MGH Hire — capabilities (v1.1)

Beyond the core hiring pipeline, this release adds the following. None of them
introduces a new permission — they reuse the matrix in `01-roles-and-permissions.md`.

## Email notifications (`lib/mail.php`)
- Every notification is written to an **outbox** (`emails` table) first, then
  delivery is attempted — so there is always a visible record.
- Automatic on: **requisition approved**, **interview scheduled**, **offer
  issued**, **candidate rejected** (optional), and **new careers application**
  (to the HR inbox).
- Configured on **Branding → Email** (sender, HR inbox, on/off, test send).
  Delivery uses PHP `mail()` where the host allows it; otherwise messages wait as
  `pending`. SMTP can be added later behind the same `notify()` call.

## Licence / seats (`lib/licence.php`)
- Seat-based. A **signed licence key** (issued by the vendor) sets the plan,
  seat count and expiry. The signature means a self-hosted customer **cannot**
  raise their own seat limit by editing stored values.
- A seat = one **active** user. Adding or re-enabling a user beyond the limit is
  **blocked**; an expired licence blocks new users.
- **Billing** screen shows usage; free tier = 3 seats when no key is applied.
- Vendor mints a key with: `php tools/licence-issue.php "Customer" 25 2027-03-31 Pro`
  (use the same `MGHHIRE_LICENCE_SECRET` as the customer's install).

## Public careers page (`pages/careers.php`)
- A no-login page at `?p=careers` listing **approved** requisitions with an apply
  form. Applications become candidates at the first stage (source = "Careers
  page") and email the HR inbox.
- Toggle and intro text on **Branding → Public careers page**.

## Résumé auto-reading (`lib/cv.php`)
- Extracts **name, email, phone, skills** from an uploaded CV (text / .docx /
  simple .pdf) or pasted text, filling any blank fields. Rules-based and
  dependency-free; degrades gracefully to "what we could find". An AI extractor
  can be layered behind `cv_extract()` later.

## My Pending Tasks (`lib/tasks.php`)
- A role-aware worklist: requisitions awaiting approval, new applications to
  screen, interviews awaiting an outcome (overdue), candidates with no movement
  for N+ days (`stall_days`, default 7), and offers awaiting acceptance.
- Shown as an **attention band** on the Dashboard and a dedicated **My Tasks**
  page. Every item links straight to the action.
