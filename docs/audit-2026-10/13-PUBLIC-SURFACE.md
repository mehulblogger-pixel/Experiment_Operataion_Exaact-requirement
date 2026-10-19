# 13 — The public surface: every route reachable without signing in

**Audited** 19 October 2026 · **Status** complete · closes the remainder of
**GAP-012** (the three portals were §11; this is everything else dispatched
before `require_login()`)

---

## 1. What a stranger on the internet can reach

Everything `index.php` dispatches **before** it demands a staff login
(`index.php:1060`). This is the surface an attacker sees first, and the part
the first route inventory missed entirely.

| Route | What it is | Verdict |
|---|---|---|
| `/login` | Staff sign-in | **Right.** CSRF on the form; per-user *and* per-IP lockout; TOTP two-step with recovery codes; a five-minute window for the code; session id regenerated on success; every failure logged. Nothing to find. |
| `/forgot`, `/reset` | Password reset | **Right.** Token is 32 random bytes, stored only as a SHA-256 hash, expires, single-use. Nothing to find. |
| `/verify` | Public report verification | **Right.** The code is 16 characters from a 30-symbol alphabet — about 78 bits, unguessable — so the page needs no throttle. The page shows a summary; the signed PDF goes only to the code-holder, and every download is logged with the address. |
| `/verify-pdf` | The report itself, by code | **Right**, for the same reason. |
| `/p/<token>` | A professional's public passport | Gated on an active record; shows `connect_passport_public_data()` only. Has tests. Not read field-by-field. |
| `/join` | Public organisation onboarding | Creates a **pending** organisation for a platform admin to approve. No throttle or CAPTCHA (**P3**, recorded). Has tests. |
| `/complaints-policy` | A static page | — |
| `/buy`, `/buy-verify` | **The ERP's own Razorpay purchase** — a self-hosted customer buys seats for their licence | **Two money defects, both fixed** — §2. |
| `/portal/*`, `/vendor/*`, `/pro/*` | The three external portals | `11-EXTERNAL-PORTAL-AUDIT.md` |

Also checked for the same shape: the **marketplace's** Razorpay flow
(`mkt_pay_verify`, behind `/portal/pay` and `/pro/pay`). It was built the right
way — it reads what was bought from a stored order, never from the form, and
refuses a second pass — with one narrow race, closed in passing (§3).

---

## 2. `/buy-verify` — the signature was right, and everything after it trusted the form

The Razorpay signature is an HMAC over `order_id|payment_id`. The ERP checked it
correctly, with a constant-time compare. **It proves that payment P belongs to
order O — and nothing else.** The old branch then did this:

```php
$seats  = max(1, (int) ($_POST['seats'] ?? 0));          // from the FORM, again
$period = ($_POST['period'] ?? 'month') === 'year' ? 'year' : 'month';
```

and recorded no payment id anywhere. Two consequences, both proven by execution
(`evidence/buy-verify-probe.php`, `phpapp/tests/test_buy_verify.php`):

### GAP-030 (P1) — pay for one seat, verify into five hundred · **FIXED**

Start the cheapest order (1 seat, 1 month), pay it, and POST the genuine
signature back with `seats=500&period=year`. The signature was valid — it was
never about seats — so the licence was signed and issued for **500 seats for a
year**, paid as one seat for a month.

### GAP-031 (P1) — the same payment, as many times as you like · **FIXED**

Nothing recorded the payment id, so the same valid POST re-signed a new licence
and extended the expiry again on every repeat. The record of what had been
issued sat in a swallowed `try/catch`, so a failed write left a licence with no
trace.

### The fix — bind the licence to the order, not the form

- The order is recorded **when it is created** (`billing_orders`, status
  `PENDING`, with the install, seats, period and amount it was priced for).
  Razorpay will only capture a payment matching that order's amount, so those
  three figures are the truth about what was paid for.
- Verification (`buy_apply_payment()`) takes seats, period and amount **from
  that row**. It does not accept seats or period as inputs at all — there is
  nothing to lie to.
- The row is marked `PAID` with a **conditional** update that succeeds exactly
  once, inside one transaction with the licence record. A replay, or two
  deliveries that overlap, find no `PENDING` row and are answered as
  "already applied". A payment for an order this workspace never started is
  refused even with a valid signature. A forged signature marks the order
  `FAILED`, so a later genuine signature cannot revive it.
- The issued licence now carries `order_id`, `payment_id` and the amount paid,
  and a failure to record it **rolls the issue back** instead of being
  swallowed.

This is the same shape `mkt_pay_verify()` already had. The two flows were
written at different times by different hands; one got it right.

> **Method note.** Lifting the verify branch out of `ops_buy()` into a function
> that returns its outcome instead of printing and exiting is what made this
> testable at all — the probe and the test call it with Razorpay stubbed. Two
> things went wrong on the way and were caught by the runs, not by me: the
> function opened a transaction unconditionally and crashed any caller already
> inside one (the harness, and any future batch), and the harness has no
> signing key, which the old branch guarded at the door and the new function
> did not. Both are fixed and pinned.

---

## 3. GAP-032 (P3) — the marketplace flow's check-then-update · **FIXED**

`mkt_pay_verify()` read the order (`status='PENDING'`) and then updated it,
with nothing atomic between the two. Two verifications of the same order
arriving together could both pass the read and both apply. The update is now
conditional on `status='PENDING'` and the second caller is told the payment was
already processed. One line; the test proves the second pass is refused.

---

## 4. Evidence, all re-runnable

| Script | What it establishes |
|---|---|
| `evidence/buy-verify-probe.php` | a 1-seat order verified while the form claims 500 — the licence carries 1; a replay issues nothing; an unknown order is refused |
| `phpapp/tests/test_buy_verify.php` | 16 assertions: order recorded at creation; seats/period from the order; replay refused with the expiry unmoved; foreign order refused; forged signature marks the order FAILED and a later genuine one cannot revive it; the marketplace second pass refused |
