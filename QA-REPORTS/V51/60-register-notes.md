# Stage 6: changes for the defect register (52)

Fold these into `QA-REPORTS/V51/52-Sales-Defect-Register.md` when this branch is merged.

| ID | New status | Note |
|---|---|---|
| DEF-13 | **Fixed 21 Nov** (Stage 6, `tests/test_sales_stage6.php`) | Sales settings offers a link to a list only to someone who may open it. The check is the screen's own: module gate, feature switch and its handler's rule. Lists the person may not open are named, with who keeps them ("kept by an administrator (Admin → Lists); ask them to change it"). |
| DEF-17 | **Fixed 21 Nov** (Stage 6) | `/crm-dashboard` is now "Sales funnel report", on the page, the Insights tile and the advisor link. Its "Pipeline board" button opens `/sales-board` for someone who may open it, and the deals register otherwise. Its window is labelled as rolling ("Window · last …"). The Dashboard's link to `/crm-reports` now reads "Quotation report" (owner Q5, Stage 4). |
| DEF-20 | **Fixed 21 Nov** (Stage 6) | `_glance_strip.php` respects `$glanceWantMgmt`. The Dashboard and the Operations home get the ranked actions only, without the slow management pulse; the ranked actions themselves are unchanged. The area homes and the Command Centre still show the pulse. |
| DEF-22 | **Fixed 21 Nov** (Stage 6, approved backend item 20) | Advertising return follows a won deal with no work-order link through its quotation (`calls.quotation_id`, any revision) and its contract number (`calls.contract_number`) to the work order and its invoices. Each invoice is counted once. A deal with neither is still reported "Won, but no order was ever raised". DEF-01's money and branch rules are unchanged. |
| F-02 (test book) | **Fixed 21 Nov** (Stage 6) | Sales home's "Where to go" tiles, and the Sales bar and phone bottom bar, show only screens the person may open. They use the Sales area's tile rules plus the route's module gate (`sxw_area_tiles()`). The F-01 refusal on the Sales bar no longer shows for someone without the Leads module. Once the Branch Manager / BU Head lead access is built, they get the tile through the same gate. |

Also changed in Stage 6 (no register number):
- Set up Sales:
  - The level editor says that each level saves with its own button, and the questions save with the button at the bottom.
  - With no levels it says "so a quotation needs no approval" (docs/02 19k).
- Stage gates:
  - The approver column shows the role's name, not its code.
  - A rule that has held requests offers "Switch off", which is what the server already did.
- Pipelines:
  - "In use" now says what it means on each screen: "Records on it", "In use (offered for new work)" and "Active".
  - The edit screen has a link back to the list.
  - On a phone each stage is a card.
