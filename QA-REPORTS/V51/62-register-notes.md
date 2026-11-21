# Register notes from Batch F (customer GSTIN / PAN, billing locations) — to fold into report 52

Branch `claude/gstin-billing-0gqe8n`. Owner decision: D1–D4 "as recommended", 21 Nov 2026.

## Owner decisions now built
| Item | Status |
|---|---|
| D1 — one customer per PAN, with billing locations (state + GSTIN + billing address); quotation, contract and invoice pick the location; invoice keeps a copy of who it was billed to | **Built 21 Nov** (`tests/test_gstin_billing.php`, 72/72) |
| D1 — "Move under the main customer" for existing branch companies with the same PAN (preview, reason, audit; never automatic) | **Built 21 Nov** |
| D2 — exceptions: not GST-registered (PAN + reason), overseas (country + their tax number), government department without PAN (reason; listed for Finance) | **Built 21 Nov** |
| D3 — existing customers without GSTIN/PAN listed in Data health; required at the next edit and the next invoice; old invoices untouched | **Built 21 Nov** |
| D4 — billing locations added by whoever may edit the client record; no new permission | **Built 21 Nov** |
| Q-02 — duplicate names compared without Pvt Ltd / Ltd / LLP / Co / Inc / punctuation (customers, and open lead against open lead) | **Built 21 Nov** |

## New defects found and fixed in this batch
| ID | Severity | Where | What was wrong | Fix |
|---|---|---|---|---|
| DEF-28 | Medium | Client edit | Editing a client did not check the GSTIN at all (only the new-client form did, and only its shape). | The same check as the new-client form, including the check digit. **Fixed.** |
| DEF-29 | **High** | Excel import | A row for a company's other-state branch (same PAN) matched the main record and **overwrote its GSTIN and state**. | Such a row becomes a billing location; the main GSTIN is never overwritten; a different PAN is refused. **Fixed.** |
| DEF-30 | Low | Invoice "not ready to issue" | The check read a column that does not exist (`client_gstin` without a default), raising a PHP warning. | Read safely. **Fixed.** |
| DEF-31 | Low | Sales home → Data health | "Customers with a GSTIN that does not look right" also counted vendors and checked only the shape. | Customers only, real check digit. **Fixed.** |
| DEF-32 | Medium | Client edit | A branch company whose main record has a PAN could not be edited at all ("Another company already uses these details … matched by PAN"). | Records of one group may share a PAN; two unrelated records may not. **Fixed.** |
| DEF-33 | Medium | Invoice page | "Set branch" on a draft invoice posted to `/invoice-set-branch`, which was never routed (the button led nowhere). | Routed under the invoicing module. **Fixed.** |
| DEF-34 | Low | Invoice print | A printed invoice read the customer's *current* name, GSTIN and address, so editing a customer rewrote bills already sent. | New invoices keep a copy; the print reads it. Older invoices unchanged. **Fixed.** |

## For the robot / test book
- Robot cases that accept a quotation for a **typed** customer name now meet the GSTIN/PAN boxes (the customer is being won). Cases that create a client through `/partner-new` need a GSTIN (or an exception).
- Robot / opstest data: clients without GSTIN and PAN can no longer have an invoice **issued** until they are added (D3). Clients with a GSTIN but no PAN count as complete.
- Winning a deal that has no customer no longer makes a customer on its own (it would have none of the tax identity): the deal is won and the message says "Open the lead and convert it", where the GSTIN / PAN are asked.

## Existing automatic tests changed for the intended rule (not weakened)
| Test | Change |
|---|---|
| `test_firm_one_record`, `test_lead_simple_path`, `test_sales_book_round2`, `test_sales_duplicates`, `test_sales_enquiry_branch`, `test_sales_checklist_gates`, `test_journey_broken_three` | Lead conversions / "a different company" acceptances now pass a recorded exception (not GST-registered + PAN + reason) — a new customer needs its tax identity. |
| `test_won_deal_closes_lead` | A won deal with no customer: now checks that **no** customer is made without GSTIN / PAN and the person is told why, then converts with the identity. |
| `test_office_client_branches` | Its source checks look for the new identity checks (`custid_gstin_owner`, `custid_gstin_problem` — check digit, not only shape) and a wider window. |
| `test_field08_lead_converts_on_win` | Source window widened (the acceptance branch grew by the GSTIN / PAN step). |
| `test_p6_batch3`, `test_p6_batch3_corrective` | Their made-up GSTINs (e.g. `24AAABB1111A1Z9`) now carry the real check character (`…1ZZ`), because sign-up now checks it. The "public form is not an oracle" checks are unchanged and pass: a wrong check digit gets the same reply whoever asks. |

## Pre-existing failures (identical on a83effc, not this batch)
- `test_m10_master_entitlement` 1 (`sx_contract_money_can` unclassified), `test_m2_org_structure` 1, `test_b4_terminology_relationships` 1 (term count 33 vs 32), `test_journey_broken_three` 1 ("who may" source check).
- `test_p6_batch3` B4 (three simultaneous sign-ups read the same answer) fails now and then on a83effc too — a timing race; it passed on the last run here.
