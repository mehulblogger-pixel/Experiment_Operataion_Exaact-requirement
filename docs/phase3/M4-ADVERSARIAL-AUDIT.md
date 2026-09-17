# PHASE 3 · M4 — INDEPENDENT ADVERSARIAL AUDIT

This audit was run **after M4 had already been declared ACCEPTED**, against the
committed tree, with one instruction: assume the verdict was wrong and find the
proof. No product code was modified during the attack. Every probe ran in a
throwaway copy of `phpapp/` in the scratchpad, against the real production
functions.

**That first verdict was premature, and this audit says so plainly.** The M4 brief
named **nine** execution paths that must respect the executable boundary. When I
declared M4 accepted, **three** were connected. Two of the remainder were reachable
and caused real harm; they are proved, fixed, pinned and re-verified below.

---

## The attack plan

| # | Hypothesis | Method |
|---|---|---|
| F1 | Not every execution path asks the boundary | Count the boundary calls in the operations layer, then attack each unguarded write |
| F2 | A candidate can be **advanced** on a request whose approval was invalidated | Approve → create a requisition and a candidate → make a material change → drive the stage writes the route performs |
| F3 | A candidate can be **attached** to a blocked requisition by **editing** it | Only the INSERT branch was audited; attack the EDIT branch |
| F4 | A no-op save invents a material change and freezes an approved request | Save the approved values back unchanged |
| F5 | The headcount ceiling can be raised without re-approval | Lower the quantity as a non-material change, then spend the old ceiling |

---

## F2 — MATERIAL. Recruitment ran to offer on an unapproved headcount

**Proved.** With the hiring request blocked and `hreq_req_block_reason()` correctly
refusing that requisition, the stage write the `candidate-stage` route performs put
the candidate into `SHORTLISTED` and then `OFFERED`:

```
F2 · the hiring request is now blocked                                    ok
F2 · and the boundary says so for this requisition                        ok
F2 · *** the candidate was SHORTLISTED anyway — the route never asks ***  ok
F2 · *** and OFFERED — recruitment ran to offer on an unapproved request ***
```

The boundary was right. Nobody asked it. The business consequence is the one M4
exists to prevent: an offer extended against headcount the approver had not
approved.

**Root cause** — the same defect family this phase has produced repeatedly: *a rule
applied to the reported instance and not to its siblings*. M4's audit found
`hreq_is_executable()` had one caller; I connected requisition creation, requisition
edit and deployment-group quantity, and stopped counting. Progression — the part of
recruitment that actually spends the headcount — was never wired.

**Fix** — `lib/ops.php`, `candidate-stage` route, **before** the stage write:

```php
$m4Advancing = !in_array($to, ['REJECTED','WITHDRAWN','OFFER_DECLINED','HOLD'], true);
if ($m4Advancing && !empty($cand['requisition_id']) && function_exists('hreq_req_block_reason')) {
    $m4why = hreq_req_block_reason((int) $cand['requisition_id']);
    if ($m4why !== '') { flash($m4why, 'error'); redirect('/candidate?id=' . $id); }
}
```

Advancing only. A candidate can still be rejected, withdrawn, declined or put on
hold while the business decides — the block stops progress, it does not trap people.

## F3 — MATERIAL. A candidate could be attached to a blocked requisition by editing

**Proved.** The candidate INSERT branch asked the boundary. The EDIT branch did not,
so the same forbidden link was made one screen later.

**Fix** — `lib/ops.php`, candidate `POST` handler, before the field list is built:

```php
if (!empty($b['requisition_id']) && function_exists('hreq_req_block_reason')) {
    $m4why = hreq_req_block_reason((int) $b['requisition_id']);
    if ($m4why !== '') { flash($m4why,'error'); redirect($cand ? '/candidate?id='.(int)$cand['id'] : '/candidates'); }
}
```

## F1 — now closed

After the fixes the operations layer calls the boundary at **eight** writes. The
guard is one function; there is still no second executable check.

## F4 — HELD

A save that changes nothing produces no material diff, opens no re-approval and
leaves the request executable. No false freeze.

## F5 — OBSERVATION, not a defect

A quantity **decrease** is deliberately non-material, and the ceiling reads the
**approved** snapshot. So a request approved for 10 and later edited down to 3 can
still fill 10. That is the safe direction (never more than approved) and it is the
asymmetry the matrix specifies — an increase requires re-approval, a decrease does
not. It is recorded here as a **business** question for you, not a security gap:
should lowering the figure also lower what may still be recruited? I have not
changed it, because changing it would alter the approved materiality matrix.

---

## A probe defect of my own

My first F1 probe searched `lib/ops.php` for `if ($cand) {` to locate the edit
branch and the search did not match the file's actual spacing. I did not discard it
quietly: it was testing my guess at the source text, not the product. F3 was then
proved by exercising the edit branch directly, which never depended on it.

When the fixes were pinned into the suite, the two route pins read `lib/ops.php`
between the route's start and its write — the same shape as the **M14-9** defect,
where an assertion matched the comment explaining it. Comments are now stripped
before the pin is applied, and both assertions still pass, so they are matching
real code.

---

## Verification after the fixes

Section **J** (eleven assertions) was added to `tests/test_p3m4_security.php` to pin
both paths permanently, including that closing moves stay open.

| | SQLite | MariaDB 10.11 |
|---|---|---|
| `p3m4_reapproval` | 71 / 0 | 71 / 0 |
| `p3m4_security` | **76 / 0** | **76 / 0** |
| `p3m4_concurrency` | 24 / 0 | 24 / 0 |
| `m4_` | 363 / 0 | 363 / 0 |
| **Complete regression** | **10731 / 0** | **10732 / 0** |

Mutation battery re-run against the fixed tree: **baseline 0 failures · attempted 9
· caught 9 · survived 0**, including M1 (the executable boundary ignores the
re-approval state) and M6 (the tenant boundary is lost), which killed the suite
outright.

## What this audit did not find

No tenant leak, no branch leak, no entitlement bypass, no segregation bypass, no
replay gain, no way to clear `approval_required` after approval, no way to flip a
rejected re-approval, no over-allocation race. Those protections held under attack.
