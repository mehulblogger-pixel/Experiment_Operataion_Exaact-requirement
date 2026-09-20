# Phase 6 · Batch 3 — Organisation status: what it actually means, and what the
# duplicate detector should do about it

**Status of this document: PROPOSAL FOR OWNER APPROVAL. No product code changed.**
`git diff aef8974 HEAD -- phpapp` remains **0 lines**.

Written because the owner selected **Q28 Option C (status-specific rules)** and then
instructed, correctly:

> "We should not invent the detailed rules ourselves before implementation. The
> developer should first inspect the actual meanings and existing usage of
> ACTIVE INACTIVE CLOSED SUSPENDED and then propose the exact behaviour for your
> approval before changing the detector."

That inspection is done. It changed the question.

---

## 1 · The first thing found: two of the four statuses do not exist

The question named four statuses — ACTIVE, INACTIVE, CLOSED, SUSPENDED. The real
vocabulary on a company record is:

`lib/helpers.php:175`
```php
const STATUSES = ['ACTIVE'=>'Active','INACTIVE'=>'Inactive','ON_HOLD'=>'On hold',
                  'BLACKLISTED'=>'Blacklisted','PROSPECT'=>'Prospect'];
```

**CLOSED and SUSPENDED are not company statuses in this product.** SUSPENDED exists,
but on a different thing entirely — a *vendor approval record*
(`lib/idems.php:590`), which is a separate axis described in §3.

This is my own error, and it is the **eighth defective instrument** found in Batch 3:
the A5 probe planted the values CLOSED and SUSPENDED on company records and observed
that the detector ignored them. It ignored them because **the product can never
produce them**. The experiment had no valid subject.

A5's actual claim — *the duplicate detector never consults company status* — still
stands, and is verified independently: the word `status` appears **zero** times in
`find_duplicate_partner()`, which reads
`SELECT ... FROM business_partners WHERE id <> ?` with no status filter at all
(`lib/ops.php:995`). The claim survives. The matrix I was about to propose for it
would have been fiction. Recording this rather than quietly correcting it, per the
standing rule for this batch.

## 2 · The sixth status nobody listed — and the defect it exposes

There is a sixth value the dropdown cannot produce, written only by the product's own
merge routine (`lib/dedupe.php:168`):

```php
db()->prepare("UPDATE business_partners SET status='MERGED', legal_name=?, description=? WHERE id=?")
```

`MERGED` means: *this record was a duplicate, a human already resolved it, and this
copy has been retired in favour of another.*

The duplicate detector has never heard of it. The consequence is worse than noise.
Proven on **both engines**, identical results:

```
created keep=666 drop=667, both GSTIN 27AAAAA0000A1Z5
BEFORE merge · detector says: DUPLICATE id=666 by=GSTIN (EXACT)
merge result: OK
  post-merge row 666: status=ACTIVE gstin=27AAAAA0000A1Z5
  post-merge row 667: status=MERGED gstin=27AAAAA0000A1Z5  <- identifiers NOT cleared

AFTER merge · a NEW registration with the same GSTIN is told: DUPLICATE id=667
   -> it points at: id=667 *** THE RETIRED RECORD ***

AFTER merge · dashboard findings still reporting this pair: 1
   PARTNER_DUPLICATE_TAXID :: {"identifier":"GSTIN","organisations":[666,667]}
```

Two distinct failures, in business terms:

1. **The remedy does not clear the complaint.** The dashboard tells the user "these
   two companies look like duplicates". The user does the exact thing the product
   offers — merges them. The warning returns, unchanged, permanently. There is no
   action available to a user that makes it go away. A warning that cannot be
   cleared trains people to ignore every warning next to it.

2. **New applicants are directed to a dead record.** Because the merge does not clear
   the retired record's tax identifiers, and the detector returns the *first* match
   it finds, a company registering with that GSTIN is matched against the retired
   copy — not the live one. Under the approved **Q26 claim/access-request flow**,
   that applicant would be invited to claim an organisation that no longer trades.
   This is the concrete reason Q26 and Q28 must be implemented together.

## 3 · Why status is a weak signal: there are three axes, and this is the decorative one

| # | Axis | Column / table | Vocabulary | Who sets it | Actually enforced? |
|---|------|----------------|-----------|-------------|--------------------|
| 1 | **Directory lifecycle** | `business_partners.status` | ACTIVE, INACTIVE, ON_HOLD, BLACKLISTED, PROSPECT (+ MERGED, code-written) | Any user with edit rights, via a free dropdown | **No.** It filters some pickers and colours a badge. Nothing is refused because of it. |
| 2 | **Trading hold** | `business_partners.hold_status` | `''`, HOLD, BLOCKED (`lib/ops.php:719`) | A dedicated screen, reason mandatory, audited | **Yes** — `partner_hold_blocks()` stops a non-manager raising work |
| 3 | **Vendor approval** | `vendor_profiles.approval_status` | PROSPECT, UNDER_ASSESSMENT, APPROVED, CONDITIONAL, EXPIRED, SUSPENDED, BLACKLISTED | Vendor qualification process | **Yes**, within vendor flows |

Two consequences the owner should weigh before approving anything:

- **`BLACKLISTED` on axis 1 blocks nothing today.** It is a red badge. The real
  blocking control is axis 2 (`BLOCKED`). Anyone reading "Blacklisted" and assuming
  the company is barred from doing business is mistaken — that is worth fixing, but
  it is **not** Batch 3 scope and I am not proposing it here.

- **The vocabulary is tenant-editable.** `lk_options_or('partner_status', STATUSES)`
  means a tenant admin can rename a code, delete one, or invent new ones
  (`lib/lookups.php:1047`, `:1067`). `is_system` protects the *list* from deletion,
  not the individual codes. **A value this mutable must never be the thing that
  decides whether a stranger is allowed to register.**

## 4 · The proposed rule

One principle, from which every rule below follows:

> **Status may make the system say less. It must never make the system allow more.**

Status is good enough to suppress a *warning on a screen*. It is not good enough to
open a *door*.

| # | Rule | Applies to | Why |
|---|------|-----------|-----|
| **R1** | A record whose status is `MERGED` is **excluded** from duplicate findings. | Dashboard detection | Makes the warning clearable. The human already resolved it; that is what MERGED records. |
| **R2** | Every other status — ACTIVE, INACTIVE, ON_HOLD, BLACKLISTED, PROSPECT, and any code a tenant invents — is **still reported**. | Dashboard detection | An inactive company with a duplicate record is still a data problem. Only a *resolved* duplicate stops being one. |
| **R3** | Public registration refusal **ignores status completely** and still matches against every record, whatever its status. | Public `/join` refusal | A dormant, on-hold or blacklisted company must not be able to re-register itself as a fresh clean record. The naive reading of "status-specific rules" — *only consider active companies* — would create exactly that hole. |
| **R4** | When the matched record is `MERGED`, the person is pointed at the **surviving** record, never the retired one. | Refusal message + Q26 claim flow | Otherwise the claim flow hands people a dead record (§2, failure 2). |
| **R5** | A status the code does not recognise is treated as **live** — reported and matched. | Both | Fail safe, never fail open. A tenant renaming a code must not silently disable protection. |
| **R6** | The duplicate detector does **not** consult `hold_status` or vendor `approval_status`. | Both | A blocked client is still one company. Being blocked is a *commercial* fact, not an *identity* fact. Mixing the axes would make the detector unexplainable. |

Read back in plain business terms: **the only thing status changes is whether we keep
nagging you about a duplicate you have already fixed. It never changes who is allowed
in.**

## 5 · One decision I cannot make for you

**R4 has no data to stand on.** When the product retires a record it writes prose —
`"Merged into ACME-01 — Acme Ltd"` into the description field, plus a note on the
survivor. There is **no machine-readable pointer** from the retired record to the one
that replaced it; `merged_into_id` does not exist.

The product already solves this exact problem twice, in its own house style:

- `controlled_docs.superseded_by_id` (`lib/controldocs.php:30`)
- `decision_rules.superseded_by_id` (`lib/decisionrules.php:29`)

**Option 1 (recommended).** Follow the existing pattern — add `merged_into_id` to
company records, set by the merge routine, backfilled for existing merged records
where the prose can be resolved unambiguously (and left empty where it cannot).
R4 then works, and "where did this company go?" becomes answerable anywhere in the
product, not just in the duplicate screen. Reuses an established convention; invents
nothing.

**Option 2.** Drop R4. Merged records are excluded from *warnings* (R1) but a new
applicant matching a retired record is told only "this company already exists" with
no usable pointer. Cheaper; leaves the Q26 claim flow with a known dead end.

**Option 3.** Clear the tax identifiers on the retired record at merge time.
**I recommend against this.** It destroys evidence — the retired record would no
longer show why it was ever thought to be the same company — and it directly
contradicts the owner's Q27 decision to preserve evidence rather than erase it.

## 6 · What this does not change

- No new status is introduced, and no status is removed.
- No transition is added — `docs/03-object-lifecycles.md` is unaffected by R1–R6
  (Option 1 in §5 adds a *pointer*, not a state).
- No permission changes. R1–R6 grant nothing to anybody.
- The merge behaviour itself is untouched under R1, R2, R3, R5, R6.
- Axis 2 and axis 3 are not touched at all.

## 7 · What is needed before implementation starts

1. **Approve or amend R1–R6.**
2. **Choose §5 Option 1, 2 or 3.** R4 is un-implementable until this is settled.
3. Confirm this is folded into the **Batch 3 Corrective Implementation Prompt**
   alongside A1–A6, not issued separately — R4 and the Q26 claim flow are the same
   piece of work and must not be built twice.

**HARD STOP. No detector change will be made until the above is approved.**
