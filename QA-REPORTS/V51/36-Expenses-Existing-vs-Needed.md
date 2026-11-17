# Expenses — what exists, what is needed (17 Nov 2026)

This is the "existing vs needed" table your working rules ask for, before any code. It follows your approved design v3.

**The short version:**
- **Almost everything is reused.** The voucher, its lines, the expense types, the travel rates, each person's own rates, the remembered distances and the approval chain all exist already.
- **Only three small things are added:** a **band/level list**, a **claim-period setting**, and a few extra boxes on existing records.
- **No old voucher is changed.** Every record and its history stays as it is.

## What exists, and what happens to it

| What you approved | What exists today | What I will do | Kind |
| --- | --- | --- | --- |
| One claim per person per period | One voucher per person per **month** | **Reuse.** Add a period (from–to) to the same voucher, so it can be weekly, fortnightly or monthly. A month voucher is simply a period of one month | Extend |
| Expenses confirmed when closing the day | Closing a job records expenses in a **separate** place (job expenses), and the voucher pulls the same days again, so a cost can count **twice** | **Reuse the voucher lines.** Closing the day writes straight into the claim. The old job-expense records stay for history, and are no longer added again in profit (also fixes Money M3) | Fix + reuse |
| Job, client, site, date filled in | The voucher line already holds job, client, site and date; "Pull working days" fills them on request | **Reuse.** Filled in by itself when the day is closed, so no button is needed | Reuse |
| Expense types, 5 kinds | Expense types exist with 3 kinds (bill, allowance, per km), a "receipt needed" mark and on/off | **Reuse.** Add the kinds "no bill (amount + note)" and "fixed per month", and "for job days / not for a job" | Extend |
| Per-km travel | Travel modes with a rate per km; each person's own rate can be set | **Reuse** unchanged | Reuse |
| Band / level | **Does not exist.** Only a free-text designation | **New:** a short band list (you name the bands). Each person gets a band | New (small) |
| Amounts by band, with the person's own amount | Each person's allowed types and own rate exist; no limits and no band | **Reuse each person's settings.** Add band amounts, limits, and an **effective-from date**, so an old claim keeps the rate that applied then | Extend |
| Usual distance per site | The program already remembers the km per person and vendor | **Reuse.** Add "approved", so a different distance needs a reason and shows in amber | Extend |
| Bill photo, no-bill expenses | One photo per line, always optional | **Reuse.** A bill type needs its photo before submit. A no-bill type needs a note. Each bill is its own line | Fix |
| Duplicate refused | **Does not exist** | **New check:** the same date, type and amount is refused, and so is the same photo used twice | New check |
| Approval chain | Exists: N+1 … role levels by amount … Finance, which you tested tonight | **Reuse unchanged** | Reuse |
| Approvers never edit (V2) | Master Admin and Admin can edit or reopen anyone's voucher | **Fix:** only the claimant edits. Approvers approve or send back. Finance and admins send back, never edit | Fix |
| Advance entered by Finance (E6) | The claimant types the advance | **Fix:** Finance enters it when marking the claim paid | Fix |
| Deadlines (8 days) and month-end close | The month lock exists (month freeze); there are no day or claim deadlines | **Reuse** the month lock. Add the two deadlines as settings | Extend |
| Print, Excel and Tally export | Exist | **Reuse**, showing the new lines | Reuse |
| Faults found tonight | ₹0 totals on a submitted claim; Save wiping "Others" amounts; an empty "What for" | **Fixed** by the new screens | Fix |

## What is new, through the Reuse Gate

| New piece | Existing equivalent | Why it cannot be reused | Upkeep |
| --- | --- | --- | --- |
| **Band list** | Designation (free text on the person) | Free text cannot carry amounts or be chosen from a list; two people would type it differently | One small list in settings |
| **Claim-period setting** | Fixed month on the voucher | Weekly or fortnightly needs from–to dates; the month stays for accounts | One setting |
| **Duplicate check** | None | — | One rule, no screen |

## Protection of existing data

- Every voucher on opstest and live keeps its lines, totals, approvals and history.
- Old monthly vouchers show in the new screens exactly as they are.
- Old job-close expenses stay visible on the job. They are counted in profit **once**, not twice.
- **Rollback:** restoring the backup taken before the update, or uploading the previous zip, brings the old screens back. The added boxes are simply unused then.

## How it is tested

1. The automatic suite, plus new checks for every rule above.
2. The robot, before and after the change.
3. Your **10 checks** on opstest, listed in the report that comes with the zip.

**One-line answer:** *"Expenses table OK, build it"*.
