# Phase 3 · M3 CORRECTION #3 — TEST RESULTS

## New suite — `tests/test_p3m3c3_raiser.php`

**53 assertions, 0 failed, on both engines.**

| Section | Assertions | What it holds | Brief |
|---|---:|---|---|
| **R1 · canonical identity exists, additively** | 4 | the chain carries `requester_id`; the offer and the salary structure carry `created_by_id`; and `created_by` is still there, doing the only job it was ever fit for | — |
| **R2 · the hiring request still works** | 4 | the chain captures the raiser at creation; the canonical requester is told; **neither namesake is** | D1-1 |
| **R3 · an offer decision reaches its raiser again** | 8 | the offer captures who created it; **the lost notification is back**; the same-name person in another branch and the same-name person with no recruitment right are told nothing; a **historical** offer reports `IDENTITY_UNRESOLVED` and tells nobody — least of all the namesake the old lookup would have found | D1-2…D1-5 |
| **R4 · salary structure** | 4 | captures its creator; its raiser is told; the namesake is not; a structure that no longer exists fails closed and says so | D1-6…D1-8 |
| **R5 · requisition** | 4 | **the chain captured its raiser — the table itself carries no id and none was invented**; the raiser is told; the namesake is not; a historical chain with no captured identity fails closed | D1-9…D1-11 |
| **R6 · identity is not authorization** | 7 | the offer's own id outranks a bad id on the chain; a foreign id → `TENANT_MISMATCH`; inactive → `RECIPIENT_INACTIVE` and nothing sent; unlicensed → `RECIPIENT_UNLICENSED`; **a hand-made request cannot post to an arbitrary id — the record's own identity wins over anything handed in** | D1-12…D1-15 |
| **R7 · audit integrity** | 6 | **five offer decisions with no identity write no repetitive rows**; no dangling reference for an unlinkable entity; a record *with* identity creates no identity-missing event at all; real send attempts and their errors stay observable in the existing outbox; **every row that is written points at a supported, traceable entity** | D2-1…D2-6 |
| **R8 · accurate failure reasons** | 16 | the vocabulary defines all nine codes; missing entity → `ENTITY_UNRESOLVED`; missing identity → `IDENTITY_UNRESOLVED`; out of scope → `RECIPIENT_OUT_OF_SCOPE`, **not "unknown identity"**; an eligible recipient reports the **delivery** outcome and never collapses into an identity problem; and segregation deliberately does **not** silence telling the raiser their own outcome | D3-1…D3-7 |

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **9389 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** (fresh `exaact_m3g`) | **9390 passed, 0 failed** |

| Suite | Result |
|---|---|
| **M3 correction #3** (`p3m3c3_raiser`) | **53 / 0** |
| M3 correction #2 — the whole C1/C2 matrix (`p3m3c2_identity`) | **50 / 0** |
| M3 correction — the whole F1/F2/F3 matrix (`p3m3c_notify`) | **68 / 0** |
| M3 original (`p3m3_sla`) | 176 / 0 |
| M1 approval (`p3m1_approval`) | 114 / 0 |
| M2 matrix, delegation and the M2 correction (`p3m2_matrix`) | 111 / 0 |
| Phase-6 approvals — offer / salary / requisition (`recruit_approval`) | 25 / 0 |
| Offer approval context (`offer_appr_dept`) | 2 / 0 |
| M4 hiring request (`m4_hiring_request`) | 78 / 0 |
| M4 correction (`m4_correction`) | 107 / 0 |
| Recruitment admin (`hiring_admin`) | 11 / 0 |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Recruitment
Command Centre are inside the whole-suite figures. **Nothing was skipped,
weakened, deleted or re-baselined.**

## Two existing assertions were superseded by a better design, and one caught me

**Superseded — updated, and made stricter:**
`ID3 · C1-6` asserted that clearing `hiring_requests.requested_by_id` leaves no
identity. There are now **two** canonical sources, and the chain still knows who
raised it — which is the improvement. The test now asserts that, and then clears
**both** sources to prove fail-closed against a genuinely legacy row. `ID4 · C1-9`
asserted an audit row was written for an offer; under D2 the correct behaviour is
**no** row, so it now asserts no dangling row is created and that the reason is
returned instead.

**Caught me:** `M1.9 · the rejection is audited` began failing because I was
logging `NO_EMAIL` as a failure event — a requester who simply has no e-mail
address produced a row on every decision, which is D2's anti-pattern repeated
inside D2's own fix. The M1 test was **not** touched; the audit policy was.
