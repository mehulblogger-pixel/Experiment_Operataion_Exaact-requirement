# PHASE 3 · M4 — ROUTES AND ENFORCEMENT POINTS

M4 adds **no new route**. It adds enforcement to paths that already exist, and the
checks sit **at the write**, so a direct URL, a crafted POST or a future AJAX
handler meets the same refusal the form does.

| Path | Production entry | M4 enforcement |
|---|---|---|
| Edit a hiring request | `hreq_save()` | permission · scope · **`approval_required` refused after approval** · materiality classified against the approved snapshot · re-approval opened atomically |
| Submit | `hreq_submit()` | unchanged (M1) |
| Decide / **re-decide** | `hreq_apply_decision()` | one state gate above every write, for both routes · the approved snapshot is captured **at the decision** |
| Raise a requisition | `hreq_to_requisition()` | permission · scope · **executable boundary** · headcount ceiling **before and after** the insert |
| Edit a requisition | `lib/ops.php` requisition save | **executable boundary** · ceiling re-checked after the write |
| Deployment groups | `req_groups_save()` → quantity overwrite | ceiling re-checked after the overwrite |
| Create a candidate against a requisition | `lib/ops.php` candidate insert | **executable boundary**, asked at the write |
| **Edit a candidate onto a requisition** | `lib/ops.php` candidate `POST` handler | **executable boundary**, asked before the field list is built — *added by the adversarial audit* |
| **Advance a candidate — shortlist, interview, offer** | `lib/ops.php` `candidate-stage` route | **executable boundary**, asked before the stage write, for **advancing moves only**; reject / withdraw / decline / hold stay open — *added by the adversarial audit* |
| Scheduler | `appr_tick()` | unchanged — M3's engine carries re-approval chains with no M4 equivalent |
| Dashboard | `recruit_cc.php` → `appr_sla_summary()` | unchanged — re-approval chains appear in the existing approval counts |

## Advancing versus closing

A pending re-approval stops recruitment from **progressing** on a headcount that is
no longer approved. It does not trap a candidate. `REJECTED`, `WITHDRAWN`,
`OFFER_DECLINED` and `HOLD` remain available throughout, so a recruiter can always
close a candidate out while the business decides.

## The single authoritative boundary

`hreq_is_executable()` — status **and** re-approval attribute — with
`hreq_block_reason()` giving the refusal in business words and
`hreq_req_block_reason($requisitionId)` answering the same question for any
execution path that holds a requisition. There is no second executable check. The operations layer calls it at **eight**
places, one for each write that would progress recruitment.
