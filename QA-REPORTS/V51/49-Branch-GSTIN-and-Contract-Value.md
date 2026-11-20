# Branch GSTIN, and contract value before GST (20 Nov 2026)

**You said**

1. *"Yes add Branch GSTIN. Company can have only one office or can have multiple office across India. So code it accordingly."*
2. *"No GST inclusion in contract value."*

**This zip** (update **@@RELEASE@@**) replaces update 329f18f and contains everything in it.

---

## 1. Branch GSTIN

**Where:** Admin → Organisation → open a branch → **Branch GSTIN (optional)**.

**How a company sets it up**

| Your company | What to do | What happens |
|---|---|---|
| **One office** | Nothing | Everything bills under the company GSTIN (Admin → Company profile), as today |
| **Several offices, one GST registration** | Nothing | The same: every office uses the company GSTIN |
| **Several offices in different states, each registered** | Type each registered branch's GSTIN | That branch's quotations, invoices and Tally export use its own GSTIN and state |
| **A site office under a registered branch** | Leave the site office empty | It bills under its **parent branch's** GSTIN, e.g. a Pune site office under Mumbai bills as Mumbai |

**Example: company in Gujarat (24…), Mumbai branch GSTIN 27ABCDE1234F1Z5**

| Billed from | Client in | GST shown and charged |
|---|---|---|
| Mumbai | Pune (Maharashtra) | **CGST 9% + SGST 9%**; GSTIN on the invoice: 27ABCDE1234F1Z5 |
| Mumbai | Ahmedabad (Gujarat) | **IGST 18%** |
| Ahmedabad (no branch GSTIN) | Pune | IGST 18%, as before |
| Ahmedabad | Ahmedabad | CGST + SGST, as before |

**Checks when you save a branch GSTIN**

- It must be 15 characters, in the form 27ABCDE1234F1Z5.
- Its first two digits must be a real state code.
- It must carry the **same PAN as the company's GSTIN**, because one company has many registrations but one PAN.

If it fails a check you are told why; the GSTIN is not saved, and the rest of the branch is.

**Safety**

- Each invoice records the GSTIN and state it was billed under while it is a **draft**.
- An invoice that is already **issued is never changed**, even if a branch GSTIN is added or corrected later.
- Moving a draft invoice to another branch updates its GSTIN and its GST split.

**Where the branch GSTIN is used**

- the quotation PDF letterhead;
- the quotation's CGST/SGST or IGST line;
- the invoice and its printout;
- the Tally export's "our state".

## 2. Contract value before GST (your Sales pack A2)

| | Before | Now |
|---|---|---|
| Contract registered from a ₹10,00,000 + 18% quotation | ₹11,80,000 | **₹10,00,000** |
| Client PO filled from the quotation | quotation total incl. GST | **₹10,00,000** (its lines' amounts before GST) |
| The value boxes on the contract and PO forms | "Value" | **"Value before GST"** |

- GST is added on each **invoice**, as before.
- **Contracts already registered keep their value.** Nothing old is rewritten. Correct one by hand only if you want to.

## 3. The Sales test book

All 82 problems are now closed:

- 81 are fixed;
- 1 (SALA-069) works as you approved on 13 Nov.

Your two answers are recorded in the book against SALB-062 and SALB-082.

## 4. Tests

| Check | Result |
|---|---|
| New automatic tests: branch GSTIN (one office, several offices, site office under a branch, wrong GSTIN, other PAN, issued invoice unchanged) and contract value | **@@T_NEW@@** |
| Every other test file for Sales, quotations, contracts, invoices, GST/Tally, branches | **@@T_REL@@** |
| Robot: the screen-by-screen cases | **@@ROBOT@@** |

## 5. Your check list on opstest (8 checks)

1. Admin → Organisation → Mumbai → type a GSTIN with **another PAN**. It is refused, and the message says why.
2. Type Mumbai's real GSTIN, with the same PAN as the company. Save.
3. Make a quotation in **Mumbai** for a client in **Maharashtra**. The GST line shows **CGST 9% + SGST 9%**, and the PDF letterhead shows Mumbai's GSTIN.
4. The same for a client in **Gujarat**: **IGST 18%**.
5. Raise a Mumbai invoice for the Maharashtra client. The invoice shows Mumbai's GSTIN, with CGST + SGST.
6. Make the same kind of quotation from **Ahmedabad**. Nothing changes from before.
7. Register a contract from an accepted ₹10,00,000 + 18% quotation. The value offered is **₹10,00,000**, under "Value before GST".
8. Open an **older** contract. Its value is unchanged.
