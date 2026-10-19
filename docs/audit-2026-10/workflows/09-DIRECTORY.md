# 9 — DIRECTORY

**Menu:** left rail → **Directory** → `/directory`
**Screens:** 17 · **8 tiles**
**Modules:** `clients`, `vendors`, `masters`, `portal`
**Who can open it** (measured): Master Admin · Business Director · Branch
Manager · Operation Manager · Coordinator · Finance.
**Inspector gets 403 on `/directory`.**


---

## Status at 19 Oct 2026 — what changed since this document was first written

*First issued 18 Oct 2026. The changes below are in the code on branch `claude/testing-branch-setup-0gqe8n`; they reach the live site only when it is updated (`../14-LIVE-CHECKS-GUIDE.md`, Part 2). Statements in the body that they made untrue are corrected where they stand and marked **(updated 19 Oct)**.*

| Change | What it means in this area | Ref | How it was verified |
|---|---|---|---|
| **Finance opens the vendor register** | Read only, at **Directory → Vendor register** (also **Operations → Vendors**). It had been the one role refused | Q-G (owner, "Yes") | **Live over HTTP**: 302 → 200 |
| **Finance opens the client register** | Read only, at **Directory → Client register**. Adding or editing a client is still refused | Q-K (owner, "Yes read only") | **Live over HTTP**: 302 → 200; `/partner-new` still refused |
| D-F1 re-read | The quotation save **does** refuse a BLOCKED client to a non-manager and warns on HOLD (read in `crm.php`, not driven). Work orders are still untested | D-F1 | Code read |

---

## 1. What this area is for

**One company record, used by everything.** A client in Sales, a vendor in
Reporting, a site in Operations and a customer in Money are all the same
underlying record — `business_partners`, **665 rows in the test database**, the
third-largest populated table.

That single-record design is the most important architectural decision in the
system, and this area is where it is maintained. `partner_id` / `client_id` /
`vendor_id` on a quote, a work order, an invoice, a receipt, a candidate, a
nonconformity and a site document all point here.

| Record | What it is | Statuses |
|---|---|---|
| **Business partner** | A company — client, vendor, sub-contractor, or several at once | `STATUSES` = ACTIVE · INACTIVE · **ON_HOLD** · **BLACKLISTED** · **PROSPECT** |
| **Activity** | A dated note of contact with a company | by `kind` and `direction` |
| **Portal user** | A person at a client or vendor with a login | invited → active |
| **Asset issuance** | Stamps, diaries, safety gear issued to a person | `ASSET_STATUS` = ISSUED · RETURNED · LOST · DAMAGED |
| **Master / lookup** | Every dropdown list in the application | `lookup_values` = **1,280 rows** |

---

## 2. Every screen, in depth

### 2.1 `/directory` — the area hub

No module gate, no own fields, one control: **Command Centre →** (the same
unexplained control as Sales, Marketplace, Money and Quality — see §7).

### 2.2 `/clients` and `/vendors` — the registers

| | |
|---|---|
| `/clients` | **no module gate** · 1 field (`status`) |
| `/vendors` | module **`idems`** (not `vendors`) · 1 field (`status`) |
| Controls (both) | **+ Add Client / + Add Vendor** · **⬆ Import from a spreadsheet** · **⬇ Export** · **🔍 Possible duplicates** · **360** · **Open** · **Edit** · **Next →** |
| Columns (both) | Code · Name · **Roles** · Industry · **GSTIN** · Status · Actions |

**Two things stand out.**

**The `Roles` column.** One company can be a client *and* a vendor *and* a
sub-contractor, and the column shows which. That is why there is one register
rather than three, and it is correct: in third-party inspection, the company you
inspect for this month is the company you inspect *at* next month.

**`🔍 Possible duplicates` is on the register itself**, not buried in an admin
screen. Duplicate company records are the commonest data-quality failure in any
business system, and putting the check one click from the register is the right
place for it.

**`Next →`** steps through records without going back to the list — a small
thing that matters when reviewing many.

**On `/vendors` being gated by `idems`:** this is the vendor register inside
**Reporting** (vendor assessment, expediting, document control), covered in
`06-REPORTING.md` §2.9. Its own handler admits any of `mod.idems.view`,
`mod.idems.edit`, `mod.clients.view` or `mod.vendors.view` — 17 of 18 roles
passed, and the only one refused was Finance, who pays vendor invoices.
**(updated 19 Oct)** On the owner's answer to Q-G, Finance now holds
`mod.vendors.view` and opens the register, read only.

### 2.3 `/partner-new` — New business partner

| | |
|---|---|
| **No module gate** · **64 fields, 1 required** (`legal_name`\*) |
| Key fields | `display_name`, `contact_name`, `contact_email`, `contact_mobile`, `home_branch_id`, **`is_client`**, **`is_vendor`**, **`is_subcontractor`**, `client_type`, `client_type_new`, `industry` … |
| Controls | **Use this device's current location** · **Read coordinates** · **🔎 Find on map** · **🗺️ Pick on map** · **Save** · **Cancel** |

**The three role flags** (`is_client`, `is_vendor`, `is_subcontractor`) are the
single-record design in practice. **`client_type_new`** beside `client_type` is
the same "pick one or type a new one" pattern used on enquiries and quotes — it
stops data entry stalling because a category is not yet on the list.

**The four location controls are the standout.** A site address can be captured
by **using the device's current location** (an inspector standing at the gate),
by **reading coordinates**, by **finding it on a map**, or by **picking it on a
map**. For a business whose work happens at other companies' sites, accurate
geolocation is operationally valuable — and it is what makes the geofenced
evidence check in Quality (`05-QUALITY.md` §3.9) possible at all.

**Behaviour confirmed:** submitting empty is **refused**, with
> *"Select at least one role (Client / Vendor / Both)."*

A clear, plain refusal naming exactly what is missing — one of the three good
ones out of eight forms tested. **This is the pattern `/call-new` and
`/candidate-new` should copy.**

### 2.4 `/partner-import` — Import clients & vendors

| | |
|---|---|
| Module `clients` · **2 fields, 1 required** (`sheet`\*) · `default_role` |
| Controls | **Read the file** · **← Directory** · **Empty format (Excel)** · **Empty format (CSV)** · **Everything already on file (Excel)** |
| Columns | **Column · What to put in it** |

**This is how a bulk import should work**, and it is worth setting out:

- **Empty format (Excel)** and **Empty format (CSV)** — download a template
  with the right columns, rather than guessing them.
- **Everything already on file (Excel)** — export what exists, so you edit
  reality instead of re-typing it, and re-import.
- **Column · What to put in it** — the mapping explained in plain words on the
  screen.
- **Read the file** — *preview before committing*, the same pattern as
  Recruitment's organogram import.
- **`default_role`** — set client/vendor once for the whole sheet rather than
  per row.

### 2.5 `/duplicates` — Possible duplicates

| | |
|---|---|
| Module `clients` · **119 fields** — repeating `keep` / `reason` per candidate pair |
| Controls | **Merge them** · **← Client register** · **Vendor register** |
| Columns | **Record A · Record B** |

Side-by-side comparison with a **`keep`** choice and a **`reason`** per pair.

**Requiring a reason for a merge is the right call.** A merge is destructive and
irreversible in effect — two histories become one — and recording *why* is what
makes it defensible later. (The reason field is not marked `required` in the
HTML; whether the server insists was not tested.)

### 2.6 `/activities` and `/activity-add` — Activity

| | |
|---|---|
| Module `clients` · **15 fields, 1 required** · 8 `cols[]` (column chooser) |
| | `kind`, `direction`, `partner_id`, `occurred_at` |
| Controls | **Apply** · **Record it** · **Everything** · **Note** · **Telephone call** · **Meeting** · **Site visit** · **E-mail** · **WhatsApp** |
| Columns | Customer · **Last anything** |

A contact timeline, with type tabs — **Note · Telephone call · Meeting · Site
visit · E-mail · WhatsApp** — and **`direction`** (inbound or outbound).

**"Last anything" as a column heading** is the Zero Training UI standard at its
best: the question a salesperson actually asks is "when did we last touch this
customer, by any means", and that is what the column is called.

**WhatsApp as a first-class activity type** is correct for an Indian B2B
business, where it is often the primary channel.

### 2.7 `/client-holds` — Client holds & blocks

| | |
|---|---|
| **No module gate** · **3 fields, 1 required** (`client_id`\*) · `hold_status`, `hold_reason` |
| Controls | **Save** |
| Columns | Client · Status · **Reason** |
| Tile | *"Put a client on hold or block them before ordering."* |

Credit control. `STATUSES` includes **ON_HOLD** and **BLACKLISTED**, and the
tile says the operative words: **"before ordering"**.

**FINDING (D-F1, P2) — the hold exists but nothing measured enforces it.** No
Sales or Operations screen inspected refuses a quote or a work order for a
client on hold or blacklisted. The flag is set here, the reason is recorded, and
whether anything downstream acts on it is **untested**. *This is the first thing
I would test in this area*: a credit control that does not block is a note, not
a control. (Raised as `01-SALES.md` E-10 from the other side.)

### 2.8 `/portal-users` and `/vendor-users` — the client and vendor portals

| | |
|---|---|
| Module `portal` · **3 fields, 2 required** (`partner_id`\* / `vendor_id`\*, `email`\*) · `name` |
| Controls | **Switch it on** · **Create the invitation** |
| `/portal-users` columns | Client · Person · E-mail · State · **What they can do** · **Last signed in** |
| `/vendor-users` columns | Vendor · Person · E-mail · State · **Last signed in** |

**External people with logins into your system** — a client seeing their own
reports, a vendor seeing what is expected of them.

- **"Switch it on"** — the portal is off until deliberately enabled, per party.
  The right default.
- **"Create the invitation"** — invite, rather than create a password for
  someone else.
- **`email` is required** — correct, it is the identity.
- **"What they can do"** on the register — the per-user portal permissions,
  visible without opening the record.
- **"Last signed in"** — dormant external accounts are a security exposure, and
  this is how you find them.

Portal permissions are governed by `PORTAL_PERMS` and `pcan()` (matrix fn. 20),
**a separate permission system from the staff one** — which is right: a client
user is not a role in your organisation.

### 2.9 `/asset-register` — Asset issuance register

| | |
|---|---|
| **No module gate** · **18 fields, 1 required** (`person_id`\*) |
| | `person`, `type`, `status`, `issued_on`, then repeating **`asset_type[]`**, **`asset_name[]`**, **`identifier[]`**, **`quantity[]`**, **`condition_issued[]`** |
| Controls | **+ Add another asset** · **Issue them** |
| Columns | Type · Description · **Serial / stamp no.** · Qty · **Condition** |
| Statuses | `ASSET_STATUS` = ISSUED · RETURNED · **LOST** · **DAMAGED** |
| Tile | *"Stamps, diaries, safety gear & devices issued to engineers — acknowledged and tracked."* |

**The inspector's stamp is the point.** An inspection stamp is a controlled
item: whoever holds it can endorse work in the body's name. Tracking it by
**serial / stamp number**, with the **condition at issue**, who holds it, and
whether it came back — ISSUED · RETURNED · **LOST** · DAMAGED — is an
accreditation matter, not stationery management.

**`condition_issued` matters for the return conversation** ("it was already
scratched"), and **LOST** as a distinct state is what triggers invalidating a
stamp.

The tile says **"acknowledged"**, implying the holder signs for it. That
acknowledgement step was not inspected.

### 2.10 `/masters` — Masters

| | |
|---|---|
| Module `masters` · no own fields · Control: **Clear** |

**Every dropdown in the application.** `lookup_types` (148 rows) and
`lookup_values` (**1,280 rows**) make this the second-most-populated table set
in the test database — which tells you how much of this product is configured
rather than coded.

Governed by `master.manage`, held by MA/AD/BD/SBU/BM/BAM/OM — **not**
Coordinator (matrix: Lookups = E for those seven, — for CO/AM/FIN/INS).

### 2.11 `/work-norms` — Working norms

| | |
|---|---|
| Module `masters` · 4 fields: `designation`, `office_id`, **`weekly_days`**, **`weekly_hours`** |
| Controls | **Save norm** |
| Columns | Designation · Office · Weekly days · Weekly hours |

**Expected working days and hours per designation per office.** This is the
denominator for utilisation: man-days available cannot be computed without
knowing that a Senior Inspector in Ahmedabad is expected to work 6 days and 48
hours while someone elsewhere works 5 and 40.

**Per office as well as per designation** is the detail that makes it correct
for a multi-branch business — and it feeds Operations' capacity outlook and
Insights' man-day figures.

### 2.12 Screens from other areas reached from here

`/documents` (Reporting), `/hiring-request` (Recruitment), `/reporting` — the
Directory home links out, as every hub does.

---

## 3. Data flow — Directory is the spine

**This area is referenced by everything and references almost nothing.**

### Arrives from

| From | What | Grade |
|---|---|---|
| **A spreadsheet** | Bulk client/vendor import, with templates and a preview | **CONFIRMED** (controls exist) |
| **Marketplace** | A self-registered organisation becomes a party; `/access-requests` catches those claiming an existing client | **READ** |
| **Sales** | `client_name` typed on an enquiry or quote when the company is not yet on file | **CONFIRMED** (the field pair exists) |
| **Admin / Settings** | Offices (`home_branch_id`), business units, industries | **CONFIRMED** |
| **A phone's GPS** | Site coordinates, via **Use this device's current location** | **CONFIRMED** (control exists) |

### Leaves for — everywhere

| To | What | Grade |
|---|---|---|
| **Sales** | `client_id` on every lead, opportunity, enquiry, quote, costing | **CONFIRMED** |
| **Operations** | `client_id`, `vendor_id`, **`site_address_id`** on every work order; site coordinates for the geofence | **CONFIRMED** |
| **Reporting** | `client_id`, `vendor_id` on every report and endorsement; vendor assessment | **CONFIRMED** |
| **Money** | `partner_id` on every invoice and receipt; GSTIN, payment terms, credit days | **CONFIRMED** |
| **Quality** | `partner_id` on complaints, impartiality threats, site documents | **CONFIRMED** |
| **Recruitment** | `client_id`, `proposed_site` for manpower supply | **CONFIRMED** |
| **All dropdowns** | `lookup_values` — 1,280 rows feeding every select in the application | **CONFIRMED** |
| **Operations / Insights** | **Working norms** are the denominator for man-days and utilisation | **READ** |
| **External people** | Portal logins for client and vendor staff | **READ** |

**The consequence:** a change here is felt in every area. Merging two duplicate
companies rewrites history across quotes, work orders, reports and invoices;
retiring a lookup value affects every screen with that dropdown; changing a
working norm moves every utilisation figure.

**That is the strongest argument for the `reason` field on a merge** — and for
treating this area as the most change-controlled in the system, which the
permission matrix only partly reflects (`/clients`, `/partner-new`,
`/client-holds` and `/asset-register` have **no module gate**).

---

## 4. Scenarios

### S-1 — A new client, from first contact to first invoice

1. **Directory → Client register → + Add Client** (`/partner-new`).
   `legal_name`\* is the only required field, but fill `display_name`,
   `contact_*`, `home_branch_id`, `industry`, `client_type` (or type a new one
   with `client_type_new`), and **tick `is_client`** — submitting with no role
   is refused: *"Select at least one role (Client / Vendor / Both)."*
2. **Capture the site properly.** Either **Use this device's current location**
   (standing at the gate), **Read coordinates**, **🔎 Find on map**, or
   **🗺️ Pick on map**. *This is what later lets Quality check that site
   photographs were taken inside the geofence.*
3. Add the GSTIN — it is a register column, and Money needs it for the invoice's
   place of supply.
4. **🔍 Possible duplicates** — check you have not just created a second record
   for an existing customer.
5. **Record it** on **Activity**: the first **Telephone call** or **Meeting**,
   with `direction` and `occurred_at`.
6. **Sales** picks the company up as `client_id` on a lead, then an enquiry,
   then a quote (`01-SALES.md` S-1).
7. **Operations** files the work order against it, with `site_address_id`.
8. **Money** invoices `partner_id`, using the GSTIN and `credit_days`.
9. Optionally: **Directory → Client portal → Switch it on → Create the
   invitation**, so the client can see their own reports.

### S-2 — Importing 400 companies from a spreadsheet

1. **Directory → Client register → ⬆ Import from a spreadsheet**
   (`/partner-import`).
2. **Download Empty format (Excel)** — the template with the right columns —
   or, if you are correcting existing data, **Everything already on file
   (Excel)**, so you edit reality rather than re-typing it.
3. Read the **Column · What to put in it** table on the screen.
4. Set **`default_role`** so the whole sheet lands as clients (or vendors)
   without a per-row flag.
5. **Read the file** — **preview before committing.**
6. Import, then **🔍 Possible duplicates** → review pairs side by side as
   **Record A · Record B**, choose which to **`keep`**, give a **`reason`**,
   **Merge them**.

### S-3 — A client stops paying

1. **Directory → Client holds & blocks** (`/client-holds`).
2. `client_id`\*, set `hold_status` (ON_HOLD or BLACKLISTED), and record the
   **`hold_reason`**. **Save.**
3. The tile's intent is *"before ordering"* — so no new work should be taken.
4. **But verify this yourself before relying on it.** Nothing measured in Sales
   or Operations refuses a quote or work order for a held client (D-F1). Try
   raising one and see.
5. **Money → Receivables ageing**, by **due date**, for the collections
   conversation.
6. **Directory → Activity** — record each chase as a **Telephone call** or
   **E-mail**, so **"Last anything"** shows the effort.

### S-4 — Issuing an inspector their stamp

1. **Directory → Asset issuance register** (`/asset-register`).
2. `person_id`\*, `issued_on`, then per item: **`asset_type[]`**,
   **`asset_name[]`**, **`identifier[]`** (the serial or stamp number),
   `quantity[]`, **`condition_issued[]`**. **+ Add another asset** for each.
3. **Issue them.** Status **ISSUED**; the tile says the holder
   **acknowledges** it.
4. On return: **RETURNED**, compared against `condition_issued`.
5. If a stamp is **LOST**, that is a controlled-item incident — whoever holds it
   can endorse work in the body's name. Quality's incident register
   (`05-QUALITY.md` §4.6) is the place for it, and invalidating the stamp is a
   business process this audit did not trace.

---

## 5. Edge cases

| # | Edge case | What happens | Grade |
|---|---|---|---|
| E-1 | Save a new partner with nothing filled | **Refused:** *"Select at least one role (Client / Vendor / Both)."* One of the three good refusals out of eight forms tested | **CONFIRMED by execution** |
| E-2 | One company is both client and vendor | Designed for — three role flags and a **Roles** column | **CONFIRMED** |
| E-3 | The same company entered twice | **🔍 Possible duplicates** on the register, side-by-side **Record A / Record B**, with `keep` and `reason`, then **Merge them** | **CONFIRMED** (controls exist) |
| E-4 | A merge is wrong | **Untested, and it is the risk worth testing.** A merge rewrites history across quotes, work orders, reports and invoices. Whether it can be undone is unknown, and `reason` is not marked required in the HTML | **UNTESTED — priority** |
| E-5 | A blacklisted client is quoted anyway | **Nothing measured blocks it** (D-F1) | **UNTESTED — priority** |
| E-6 | A lookup value is retired while records use it | `/masters` has **Clear**; retirement behaviour was not inspected. Marketplace's taxonomy uses **Retire** rather than delete, which is the pattern to follow | **UNTESTED** |
| E-7 | A client portal user leaves the client company | **"Last signed in"** on the register finds dormant accounts; the state can be changed | **CONFIRMED** (column exists) |
| E-8 | A working norm is changed mid-year | Every man-day and utilisation figure moves. No versioning or effective-date field was seen on `/work-norms` | **SUSPECTED** |
| E-9 | An inspector loses their stamp | **LOST** is a state. The downstream consequence — invalidating the stamp, and any endorsement made with it — is not traced in the system | **UNTESTED** |
| E-10 | A site has no coordinates | Four capture methods are offered. What the geofenced evidence check does with a site that has none is untested (`05-QUALITY.md` E-9) | **UNTESTED** |
| E-11 | Two people edit one partner record at once | **No protection** — `business_partners` is outside the `['calls','jobs']` concurrency allow-list. Given that everything references this record, it is the worst place for a silent last-save-wins | **CONFIRMED** |
| E-12 | An inspector opens the Directory | **403.** Correct per the matrix (Business Partners: INS = —) | **CONFIRMED** |
| E-13 | Finance opens the client register | **Opens, View** per the matrix. Correct — Accounts needs GSTIN and payment terms | **CONFIRMED** |

---

## 6. Findings raised in this area

| Id | Severity | Finding |
|---|---|---|
| **D-F1** | **P2** | **A client hold or blacklist is recorded but nothing measured enforces it.** No Sales or Operations screen refuses a quote or work order for a held client. A credit control that does not block is a note |
| **D-F2** | **P2** | **No concurrency protection on `business_partners`** — the record every other area points at. Last save wins, silently (`03-OPERATIONS.md` §4) |
| **D-F3** | **P2** | **A merge rewrites history across six areas and may not be reversible.** `reason` is not marked required in the HTML, and undo was not tested |
| **D-F4** | **P2** | `/clients`, `/partner-new`, `/client-holds` and `/asset-register` have **no module gate**, on the area every other area depends on |
| **D-F5** | **P2** | `/vendors` is gated by **`idems`**; its handler admitted 17 of 18 roles and refused only Finance. **Finance part fixed 19 Oct (Q-G)**; the odd module gate remains |
| **D-F6** | **P3** | **Working norms have no effective date**, so changing one silently moves historical utilisation figures |
| **D-F7** | **P3** | The stamp **LOST** state has no traced consequence — invalidating the stamp and any endorsement made with it |
| **D-F8** | — | *Credit:* one company record with **three role flags** and a **Roles** column · **🔍 Possible duplicates** on the register itself · a merge that asks **which to keep and why** · an import with **downloadable templates**, an **export-and-re-import** path, a **Column · What to put in it** map and a **preview** before committing · **four ways to capture a site location**, including the device's own GPS · **"Last anything"** as a column heading · **WhatsApp** as a first-class activity type · portals that are **off until switched on** and work by **invitation** · **"What they can do"** and **"Last signed in"** on the portal register · stamp tracking by **serial number with condition at issue** and **LOST** as a state · **working norms per designation *and* per office** · and the refusal *"Select at least one role (Client / Vendor / Both)."* |
